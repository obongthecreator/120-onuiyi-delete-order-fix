<?php
/**
 * Financial Summary Class
 * Handles daily financial summaries
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Financial_Summary {
    
    /**
     * Save financial summary
     */
    public static function save($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_financial_summary';
        
        $date = sanitize_text_field($data['date'] ?? date('Y-m-d'));
        $extras_amount = floatval($data['extras_amount'] ?? 0);
        $extras_remark = sanitize_textarea_field($data['extras_remark'] ?? '');
        $expenses_amount = floatval($data['expenses_amount'] ?? 0);
        $expenses_remark = sanitize_textarea_field($data['expenses_remark'] ?? '');
        $market_card_expense_input = isset($data['market_card_expense']) ? floatval($data['market_card_expense']) : null;
        $staff_id = Stand120_Auth::get_current_staff_id();
        
        // Admin can override old_cash
        $admin_old_cash = isset($data['old_cash']) && Stand120_Auth::is_admin() ? floatval($data['old_cash']) : null;
        
        // Get existing record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE summary_date = %s",
            $date
        ));
        
        if ($existing) {
            $old_cash = ($admin_old_cash !== null) ? $admin_old_cash : floatval($existing->old_cash);
            
            // Market Card Expense Left is manually inputted (not from expenses table)
            $market_card_expense = ($market_card_expense_input !== null) ? $market_card_expense_input : floatval($existing->market_card_expense ?? 0);
            
            // Recalculate cash left: Cash Left = (Cash Sales + Old Cash + Card Expense Cash Left + Extras) - Cash Expense
            $cash_left = ($existing->cash_sales + $old_cash + $market_card_expense + $extras_amount) - $expenses_amount;
            
            $update_data = array(
                'extras_amount' => $extras_amount,
                'extras_remark' => $extras_remark,
                'expenses_amount' => $expenses_amount,
                'expenses_remark' => $expenses_remark,
                'market_card_expense' => $market_card_expense,
                'cash_left' => $cash_left,
                'staff_id' => $staff_id
            );
            
            // Only update old_cash if admin changed it
            if ($admin_old_cash !== null) {
                $update_data['old_cash'] = $admin_old_cash;
            }
            
            // Update existing
            $wpdb->update($table, $update_data, array('id' => $existing->id));
            
            // If admin changed old_cash, also update yesterday's cash_left to match
            if ($admin_old_cash !== null) {
                $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
                $yesterday_record = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table WHERE summary_date = %s",
                    $yesterday
                ));
                if ($yesterday_record) {
                    $wpdb->update($table, array('cash_left' => $admin_old_cash), array('id' => $yesterday_record->id));
                }
            }
            
            return array(
                'success' => true,
                'message' => 'Financial summary updated',
                'data' => self::get_for_date($date)
            );
        } else {
            // Get yesterday's cash left as today's old cash
            $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
            $yesterday_record = $wpdb->get_row($wpdb->prepare(
                "SELECT cash_left FROM $table WHERE summary_date = %s",
                $yesterday
            ));
            $old_cash = ($admin_old_cash !== null) ? $admin_old_cash : ($yesterday_record ? floatval($yesterday_record->cash_left) : 0);
            
            // Get today's totals from orders
            $orders_table = $wpdb->prefix . 'stand120_orders';
            $totals = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    SUM(grand_total) as total_sales,
                    SUM(transfer_amount) as transfer_sales,
                    SUM(cash_amount) as cash_sales,
                    SUM(delivery_fee) as delivery_fees
                FROM $orders_table
                WHERE order_date = %s",
                $date
            ));
            
            $total_sales = floatval($totals->total_sales ?? 0);
            $transfer_sales = floatval($totals->transfer_sales ?? 0);
            $cash_sales = floatval($totals->cash_sales ?? 0);
            $delivery_fees = floatval($totals->delivery_fees ?? 0);
            
            $market_card_expense = ($market_card_expense_input !== null) ? $market_card_expense_input : 0;
            
            $cash_left = ($cash_sales + $old_cash + $market_card_expense + $extras_amount) - $expenses_amount;
            
            // Insert new
            $wpdb->insert($table, array(
                'summary_date' => $date,
                'total_sales' => $total_sales,
                'transfer_sales' => $transfer_sales,
                'cash_sales' => $cash_sales,
                'delivery_fees' => $delivery_fees,
                'extras_amount' => $extras_amount,
                'extras_remark' => $extras_remark,
                'expenses_amount' => $expenses_amount,
                'expenses_remark' => $expenses_remark,
                'market_card_expense' => $market_card_expense,
                'old_cash' => $old_cash,
                'cash_left' => $cash_left,
                'staff_id' => $staff_id
            ));
            
            // If admin changed old_cash, also update yesterday's cash_left to match
            if ($admin_old_cash !== null) {
                $yesterday_id_record = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table WHERE summary_date = %s",
                    $yesterday
                ));
                if ($yesterday_id_record) {
                    $wpdb->update($table, array('cash_left' => $admin_old_cash), array('id' => $yesterday_id_record->id));
                }
            }
            
            return array(
                'success' => true,
                'message' => 'Financial summary created',
                'data' => self::get_for_date($date)
            );
        }
    }
    
    /**
     * Update sales totals (called after each order)
     */
    public static function update_sales_totals($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_financial_summary';
        $orders_table = $wpdb->prefix . 'stand120_orders';
        
        // Get today's totals from orders
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(grand_total) as total_sales,
                SUM(transfer_amount) as transfer_sales,
                SUM(cash_amount) as cash_sales,
                SUM(delivery_fee) as delivery_fees
            FROM $orders_table
            WHERE order_date = %s",
            $date
        ));
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE summary_date = %s",
            $date
        ));
        
        if ($existing) {
            $cash_left = (floatval($totals->cash_sales ?? 0) + $existing->old_cash + floatval($existing->market_card_expense ?? 0) + $existing->extras_amount) - $existing->expenses_amount;
            
            $wpdb->update($table, array(
                'total_sales' => floatval($totals->total_sales ?? 0),
                'transfer_sales' => floatval($totals->transfer_sales ?? 0),
                'cash_sales' => floatval($totals->cash_sales ?? 0),
                'delivery_fees' => floatval($totals->delivery_fees ?? 0),
                'cash_left' => $cash_left
            ), array('id' => $existing->id));
        }
    }
    
    /**
     * Get financial summary for a date
     */
    public static function get_for_date($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_financial_summary';
        $orders_table = $wpdb->prefix . 'stand120_orders';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE summary_date = %s",
            $date
        ));
        
        if ($record) {
            return array(
                'date' => $date,
                'total_sales' => floatval($record->total_sales),
                'transfer_sales' => floatval($record->transfer_sales),
                'cash_sales' => floatval($record->cash_sales),
                'delivery_fees' => floatval($record->delivery_fees),
                'extras_amount' => floatval($record->extras_amount),
                'extras_remark' => $record->extras_remark,
                'expenses_amount' => floatval($record->expenses_amount),
                'expenses_remark' => $record->expenses_remark,
                'market_card_expense' => floatval($record->market_card_expense ?? 0),
                'old_cash' => floatval($record->old_cash),
                'cash_left' => floatval($record->cash_left)
            );
        }
        
        // No record exists, calculate from orders
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(grand_total) as total_sales,
                SUM(transfer_amount) as transfer_sales,
                SUM(cash_amount) as cash_sales,
                SUM(delivery_fee) as delivery_fees
            FROM $orders_table
            WHERE order_date = %s",
            $date
        ));
        
        // Get yesterday's cash left
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $yesterday_record = $wpdb->get_row($wpdb->prepare(
            "SELECT cash_left FROM $table WHERE summary_date = %s",
            $yesterday
        ));
        $old_cash = $yesterday_record ? floatval($yesterday_record->cash_left) : 0;
        
        $cash_sales = floatval($totals->cash_sales ?? 0);
        
        return array(
            'date' => $date,
            'total_sales' => floatval($totals->total_sales ?? 0),
            'transfer_sales' => floatval($totals->transfer_sales ?? 0),
            'cash_sales' => $cash_sales,
            'delivery_fees' => floatval($totals->delivery_fees ?? 0),
            'extras_amount' => 0,
            'extras_remark' => '',
            'expenses_amount' => 0,
            'expenses_remark' => '',
            'market_card_expense' => 0,
            'old_cash' => $old_cash,
            'cash_left' => $cash_sales + $old_cash
        );
    }
    
    /**
     * Self-heal financial summary records with wrong calculations.
     * Recalculates cash_left for all records in the given date range
     * and updates any that don't match the formula:
     * Cash Left = (Cash Sales + Old Cash + Card Expense Cash Left + Extras) - Cash Expense
     */
    public static function recalculate_records($date_from = null, $date_to = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_financial_summary';
        
        $sql = "SELECT * FROM $table WHERE 1=1";
        $params = array();
        
        if (!empty($date_from)) {
            $sql .= " AND summary_date >= %s";
            $params[] = $date_from;
        }
        if (!empty($date_to)) {
            $sql .= " AND summary_date <= %s";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY summary_date ASC";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $records = $wpdb->get_results($sql);
        }
        
        $fixed = 0;
        
        foreach ($records as $record) {
            $correct_cash_left = (floatval($record->cash_sales) + floatval($record->old_cash) + floatval($record->market_card_expense ?? 0) + floatval($record->extras_amount)) - floatval($record->expenses_amount);
            
            $stored_cash_left = floatval($record->cash_left);
            
            // Fix if cash_left is wrong
            if (abs($stored_cash_left - $correct_cash_left) > 0.01) {
                $wpdb->update($table, array(
                    'cash_left' => $correct_cash_left
                ), array('id' => $record->id));
                $fixed++;
            }
        }
        
        return $fixed;
    }
    
    /**
     * Get financial summary history
     */
    public static function get_history($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_financial_summary';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $sql = "SELECT fs.*, s.full_name as staff_name
                FROM $table fs
                LEFT JOIN $staff_table s ON fs.staff_id = s.id
                WHERE 1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND fs.summary_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND fs.summary_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY fs.summary_date DESC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = str_replace("SELECT fs.*, s.full_name as staff_name", "SELECT COUNT(*)", $sql);
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
        
        return array(
            'records' => $records,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Update a specific financial summary record (admin-only)
     * Allows editing old_cash and cash_left for any date
     */
    public static function update_record($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_financial_summary';
        
        $record_id = intval($data['record_id'] ?? 0);
        if (!$record_id) {
            return array('success' => false, 'message' => 'Invalid record ID');
        }
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $record_id
        ));
        
        if (!$existing) {
            return array('success' => false, 'message' => 'Record not found');
        }
        
        $update_data = array();
        
        if (isset($data['old_cash'])) {
            $update_data['old_cash'] = floatval($data['old_cash']);
        }
        
        if (isset($data['cash_left'])) {
            $update_data['cash_left'] = floatval($data['cash_left']);
        }
        
        if (isset($data['market_card_expense'])) {
            $update_data['market_card_expense'] = floatval($data['market_card_expense']);
        }
        
        if (empty($update_data)) {
            return array('success' => false, 'message' => 'No fields to update');
        }
        
        $wpdb->update($table, $update_data, array('id' => $record_id));
        
        // If market_card_expense was changed, recalculate cash_left for this record
        if (isset($data['market_card_expense'])) {
            $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $record_id));
            if ($updated) {
                $new_cash_left = (floatval($updated->cash_sales) + floatval($updated->old_cash) + floatval($updated->market_card_expense) + floatval($updated->extras_amount)) - floatval($updated->expenses_amount);
                $wpdb->update($table, array('cash_left' => $new_cash_left), array('id' => $record_id));
                
                // Also propagate cash_left to next day's old_cash
                $next_date = date('Y-m-d', strtotime($updated->summary_date . ' +1 day'));
                $next_record = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table WHERE summary_date = %s",
                    $next_date
                ));
                if ($next_record) {
                    $wpdb->update($table, array('old_cash' => $new_cash_left), array('id' => $next_record->id));
                }
            }
        }
        
        // Propagate linked changes between days
        $record_date = $existing->summary_date;
        
        // If old_cash was changed, update previous day's cash_left to match
        if (isset($data['old_cash'])) {
            $prev_date = date('Y-m-d', strtotime($record_date . ' -1 day'));
            $prev_record = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE summary_date = %s",
                $prev_date
            ));
            if ($prev_record) {
                $wpdb->update($table, array('cash_left' => floatval($data['old_cash'])), array('id' => $prev_record->id));
            }
        }
        
        // If cash_left was changed, update next day's old_cash to match
        if (isset($data['cash_left'])) {
            $next_date = date('Y-m-d', strtotime($record_date . ' +1 day'));
            $next_record = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE summary_date = %s",
                $next_date
            ));
            if ($next_record) {
                $wpdb->update($table, array('old_cash' => floatval($data['cash_left'])), array('id' => $next_record->id));
            }
        }
        
        return array(
            'success' => true,
            'message' => 'Record updated successfully'
        );
    }
}