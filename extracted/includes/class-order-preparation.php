<?php
/**
 * Order Preparation Class
 * Handles order preparation inventory tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Order_Preparation {
    
    /**
     * Save order preparation data
     */
    public static function save($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_order_preparation';
        
        $product_id = intval($data['product_id'] ?? 0);
        $date = sanitize_text_field($data['date'] ?? date('Y-m-d'));
        $total_added = floatval($data['total_added'] ?? 0);
        $total_sold = floatval($data['total_sold'] ?? 0);
        $remarks = sanitize_textarea_field($data['remarks'] ?? '');
        $staff_id = Stand120_Auth::get_current_staff_id();
        $is_admin = Stand120_Auth::is_admin();
        $admin_opening = isset($data['opening_value']) && $is_admin ? floatval($data['opening_value']) : null;
        
        if (!$product_id) {
            return array('success' => false, 'message' => 'Product ID required');
        }
        
        // Get existing record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND prep_date = %s",
            $product_id, $date
        ));
        
        // Get opening value
        $opening = 0;
        if ($existing) {
            $opening = $existing->opening_value;
        } else {
            // Get most recent previous closing as today's opening
            $prev_record = $wpdb->get_row($wpdb->prepare(
                "SELECT closing_value FROM $table WHERE product_id = %d AND prep_date < %s ORDER BY prep_date DESC LIMIT 1",
                $product_id, $date
            ));
            $opening = $prev_record ? $prev_record->closing_value : 0;
        }
        
        // If admin provided an explicit opening value, use it
        if ($admin_opening !== null) {
            $opening = $admin_opening;
        }
        
        // Calculate closing
        $closing = $opening + $total_added - $total_sold;
        
        if ($existing) {
            // Update existing
            $update_data = array(
                'total_added' => $total_added,
                'total_sold' => $total_sold,
                'closing_value' => $closing,
                'remarks' => $remarks,
                'staff_id' => $staff_id
            );
            // Persist admin-provided opening value
            if ($admin_opening !== null) {
                $update_data['opening_value'] = $opening;
            }
            $wpdb->update($table, $update_data, array('id' => $existing->id));
        } else {
            // Insert new
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'prep_date' => $date,
                'opening_value' => $opening,
                'total_added' => $total_added,
                'total_sold' => $total_sold,
                'closing_value' => $closing,
                'remarks' => $remarks,
                'staff_id' => $staff_id
            ));
        }
        
        return array(
            'success' => true,
            'message' => 'Order preparation saved',
            'data' => array(
                'opening' => $opening,
                'total_added' => $total_added,
                'total_sold' => $total_sold,
                'closing' => $closing,
                'remarks' => $remarks
            )
        );
    }
    
    /**
     * Update opening value (admin only)
     */
    public static function update_opening($product_id, $value, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_order_preparation';
        
        // Get existing record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND prep_date = %s",
            $product_id, $date
        ));
        
        if ($existing) {
            // Recalculate closing
            $closing = $value + $existing->total_added - $existing->total_sold;
            
            $wpdb->update($table, array(
                'opening_value' => $value,
                'closing_value' => $closing
            ), array('id' => $existing->id));
        } else {
            // Create new record
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'prep_date' => $date,
                'opening_value' => $value,
                'total_added' => 0,
                'total_sold' => 0,
                'closing_value' => $value
            ));
        }
        
        Stand120_Database::log_activity('update_opening', 'stand120_order_preparation', $product_id);
        
        return array('success' => true, 'message' => 'Opening value updated');
    }
    
    /**
     * Get order preparation for a date
     */
    public static function get_for_date($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_order_preparation';
        $products_table = $wpdb->prefix . 'stand120_products';
        
        // Get all menu item products
        $menu_items = Stand120_Database::get_menu_items();
        
        $data = array();
        
        foreach ($menu_items as $item) {
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND prep_date = %s",
                $item->id, $date
            ));
            
            if ($record) {
                $data[] = array(
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'opening' => floatval($record->opening_value),
                    'total_added' => floatval($record->total_added),
                    'total_sold' => floatval($record->total_sold),
                    'closing' => floatval($record->closing_value),
                    'remarks' => $record->remarks ?? ''
                );
            } else {
                // Get most recent previous closing
                $prev_record = $wpdb->get_row($wpdb->prepare(
                    "SELECT closing_value FROM $table WHERE product_id = %d AND prep_date < %s ORDER BY prep_date DESC LIMIT 1",
                    $item->id, $date
                ));
                $opening = $prev_record ? floatval($prev_record->closing_value) : 0;
                
                $data[] = array(
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'opening' => $opening,
                    'total_added' => 0,
                    'total_sold' => 0,
                    'closing' => $opening,
                    'remarks' => ''
                );
            }
        }
        
        return array('data' => $data, 'date' => $date);
    }
    
    /**
     * Get order preparation history
     */
    public static function get_history($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_order_preparation';
        $products_table = $wpdb->prefix . 'stand120_products';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $sql = "SELECT op.*, p.name as product_name, s.full_name as staff_name
                FROM $table op
                LEFT JOIN $products_table p ON op.product_id = p.id
                LEFT JOIN $staff_table s ON op.staff_id = s.id
                WHERE 1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND op.prep_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND op.prep_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['product_id'])) {
            $sql .= " AND op.product_id = %d";
            $params[] = $filters['product_id'];
        }
        
        $sql .= " ORDER BY op.prep_date DESC, p.name ASC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = str_replace("SELECT op.*, p.name as product_name, s.full_name as staff_name", "SELECT COUNT(*)", $sql);
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
}