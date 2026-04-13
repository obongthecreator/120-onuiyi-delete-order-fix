<?php
/**
 * Expense Record Class
 * Handles individual market expense line items
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Expense_Record {
    
    /**
     * Save expense items for a date (replaces all items for that date)
     */
    public static function save($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_expenses';
        
        $date = sanitize_text_field($data['date'] ?? date('Y-m-d'));
        $items = $data['items'] ?? array();
        $staff_id = Stand120_Auth::get_current_staff_id();
        
        // Delete existing items for this date
        $wpdb->delete($table, array('expense_date' => $date));
        
        $total = 0;
        
        // Insert new items
        if (is_string($items)) {
            $items = json_decode(stripslashes($items), true);
        }
        
        if (is_array($items)) {
            foreach ($items as $item) {
                $description = sanitize_text_field($item['description'] ?? '');
                $amount = floatval($item['amount'] ?? 0);
                
                if (empty($description) && $amount <= 0) {
                    continue; // Skip empty rows
                }
                
                $wpdb->insert($table, array(
                    'expense_date' => $date,
                    'description' => $description,
                    'amount' => $amount,
                    'staff_id' => $staff_id
                ));
                
                $total += $amount;
            }
        }
        
        Stand120_Database::log_activity('save_expenses', 'stand120_expenses');
        
        return array(
            'success' => true,
            'message' => 'Market expenses saved',
            'data' => array(
                'date' => $date,
                'total' => $total,
                'items' => self::get_items_for_date($date)
            )
        );
    }
    
    /**
     * Get expense items for a specific date
     */
    public static function get_for_date($date) {
        $items = self::get_items_for_date($date);
        $total = 0;
        foreach ($items as $item) {
            $total += floatval($item->amount);
        }
        
        return array(
            'date' => $date,
            'items' => $items,
            'total' => $total
        );
    }
    
    /**
     * Get raw items for a date
     */
    private static function get_items_for_date($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_expenses';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE expense_date = %s ORDER BY id ASC",
            $date
        ));
    }
    
    /**
     * Delete a single expense item
     */
    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_expenses';
        
        // Get the item first to know the date
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
        
        if (!$item) {
            return array('success' => false, 'message' => 'Expense not found');
        }
        
        $wpdb->delete($table, array('id' => $id));
        
        // Recalculate total for the date and sync
        $date = $item->expense_date;
        $items = self::get_items_for_date($date);
        $total = 0;
        foreach ($items as $remaining) {
            $total += floatval($remaining->amount);
        }
        Stand120_Database::log_activity('delete_expense', 'stand120_expenses', $id);
        
        return array(
            'success' => true,
            'message' => 'Expense deleted',
            'data' => array(
                'date' => $date,
                'total' => $total
            )
        );
    }
    
    /**
     * Get expense history with pagination
     */
    public static function get_history($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_expenses';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $sql = "SELECT e.*, s.full_name as staff_name
                FROM $table e
                LEFT JOIN $staff_table s ON e.staff_id = s.id
                WHERE 1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND e.expense_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND e.expense_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY e.expense_date DESC, e.id ASC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = str_replace("SELECT e.*, s.full_name as staff_name", "SELECT COUNT(*)", $sql);
        if (!empty($params)) {
            $total = $wpdb->get_var($wpdb->prepare($count_sql, $params));
        } else {
            $total = $wpdb->get_var($count_sql);
        }
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $records = $wpdb->get_results($sql);
        }
        
        // Get daily totals for the filtered range
        $totals_sql = "SELECT expense_date, SUM(amount) as daily_total
                       FROM $table WHERE 1=1";
        $totals_params = array();
        
        if (!empty($filters['date_from'])) {
            $totals_sql .= " AND expense_date >= %s";
            $totals_params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $totals_sql .= " AND expense_date <= %s";
            $totals_params[] = $filters['date_to'];
        }
        $totals_sql .= " GROUP BY expense_date ORDER BY expense_date DESC";
        
        if (!empty($totals_params)) {
            $daily_totals = $wpdb->get_results($wpdb->prepare($totals_sql, $totals_params));
        } else {
            $daily_totals = $wpdb->get_results($totals_sql);
        }
        
        return array(
            'records' => $records,
            'daily_totals' => $daily_totals,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Get total expenses for a date
     */
    public static function get_total_for_date($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_expenses';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table WHERE expense_date = %s",
            $date
        ));
        
        return floatval($total ?? 0);
    }
}