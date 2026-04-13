<?php
/**
 * Take Order Class
 * Handles order creation and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Take_Order {
    
    /**
     * Submit a new order
     */
    public static function submit_order($data) {
        global $wpdb;
        
        // Enable error reporting for debugging
        $wpdb->show_errors();
        
        // First, ensure user is logged in
        if (!is_user_logged_in()) {
            return array('success' => false, 'message' => 'User is not logged in. Please refresh the page and login again.');
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        // ALWAYS try to get or create staff record directly here
        $staff = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $staff_table WHERE user_id = %d",
            $user_id
        ));
        
        if ($staff) {
            $staff_id = $staff->id;
            // Ensure status is active
            $wpdb->update($staff_table, array('status' => 'active'), array('id' => $staff_id));
        } else {
            // Create new staff record directly
            $is_admin = in_array('administrator', (array) $user->roles);
            $insert_result = $wpdb->insert($staff_table, array(
                'user_id' => $user_id,
                'full_name' => $user->display_name ?: $user->user_login,
                'role' => $is_admin ? 'admin' : 'staff',
                'status' => 'active'
            ));
            
            if ($insert_result === false) {
                return array('success' => false, 'message' => 'Could not create staff record: ' . $wpdb->last_error);
            }
            $staff_id = $wpdb->insert_id;
        }
        
        if (!$staff_id || $staff_id == 0) {
            return array('success' => false, 'message' => 'Could not identify staff member (ID: ' . $staff_id . '). Please logout and login again.');
        }
        
        // Validate and parse items
        $items = array();
        if (isset($data['items'])) {
            if (is_array($data['items'])) {
                $items = $data['items'];
            } elseif (is_string($data['items'])) {
                $decoded = json_decode(stripslashes($data['items']), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $items = $decoded;
                }
            }
        }
        
        if (empty($items)) {
            return array('success' => false, 'message' => 'No items in order');
        }
        
        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['total'] ?? 0);
        }
        
        $delivery_fee = floatval($data['delivery_fee'] ?? 0);
        $grand_total = $subtotal + $delivery_fee;
        
        // Payment method
        $payment_method = sanitize_text_field($data['payment_method'] ?? 'cash');
        $cash_amount = floatval($data['cash_amount'] ?? 0);
        $transfer_amount = floatval($data['transfer_amount'] ?? 0);
        
        // Auto-set amounts based on payment method if not explicitly provided
        if ($payment_method === 'transfer' && $transfer_amount == 0) {
            // Full amount is transfer
            $transfer_amount = $grand_total;
            $cash_amount = 0;
        } elseif ($payment_method === 'cash' && $cash_amount == 0) {
            // Full amount is cash
            $cash_amount = $grand_total;
            $transfer_amount = 0;
        } elseif ($payment_method === 'both') {
            // Both methods - validate total matches
            if (($cash_amount + $transfer_amount) < $grand_total) {
                return array('success' => false, 'message' => 'Payment amounts do not match total');
            }
        }
        
        $payment_confirmed = isset($data['payment_confirmed']) && $data['payment_confirmed'] ? 1 : 0;
        
        // Insert order
        $orders_table = $wpdb->prefix . 'stand120_orders';
        $insert_result = $wpdb->insert($orders_table, array(
            'staff_id' => $staff_id,
            'order_date' => date('Y-m-d'),
            'order_time' => date('H:i:s'),
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery_fee,
            'grand_total' => $grand_total,
            'payment_method' => $payment_method,
            'cash_amount' => $cash_amount,
            'transfer_amount' => $transfer_amount,
            'payment_confirmed' => $payment_confirmed,
            'synced' => isset($data['offline']) ? 0 : 1
        ));
        
        if ($insert_result === false) {
            return array('success' => false, 'message' => 'Database error: ' . $wpdb->last_error);
        }
        
        $order_id = $wpdb->insert_id;
        
        if (!$order_id) {
            return array('success' => false, 'message' => 'Failed to create order');
        }
        
        // Insert order items
        $items_table = $wpdb->prefix . 'stand120_order_items';
        foreach ($items as $item) {
            $wpdb->insert($items_table, array(
                'order_id' => $order_id,
                'product_id' => intval($item['product_id'] ?? 0),
                'product_name' => sanitize_text_field($item['product_name'] ?? ''),
                'price' => floatval($item['price'] ?? 0),
                'quantity' => intval($item['quantity'] ?? 0),
                'total' => floatval($item['total'] ?? 0)
            ));
        }
        
        // Update financial summary
        self::update_financial_summary($payment_method, $cash_amount, $transfer_amount, $grand_total, $delivery_fee, $staff_id);
        
        Stand120_Database::log_activity('submit_order', 'stand120_orders', $order_id);
        
        return array(
            'success' => true,
            'message' => 'Order submitted successfully',
            'order_id' => $order_id
        );
    }
    
    /**
     * Update financial summary after order
     */
    private static function update_financial_summary($payment_method, $cash_amount, $transfer_amount, $grand_total, $delivery_fee, $staff_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_financial_summary';
        $today = date('Y-m-d');
        
        if (!$staff_id) {
            $staff_id = Stand120_Auth::get_current_staff_id();
        }
        
        // Get existing record for today
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE summary_date = %s",
            $today
        ));
        
        if ($existing) {
            // Update existing
            $new_total_sales = $existing->total_sales + $grand_total;
            $new_cash_sales = $existing->cash_sales + $cash_amount;
            $new_transfer_sales = $existing->transfer_sales + $transfer_amount;
            $new_delivery_fees = $existing->delivery_fees + $delivery_fee;
            $new_cash_left = ($new_cash_sales + $existing->old_cash + $existing->extras_amount) - $existing->expenses_amount;
            
            $wpdb->update($table, array(
                'total_sales' => $new_total_sales,
                'cash_sales' => $new_cash_sales,
                'transfer_sales' => $new_transfer_sales,
                'delivery_fees' => $new_delivery_fees,
                'cash_left' => $new_cash_left,
                'staff_id' => $staff_id
            ), array('id' => $existing->id));
        } else {
            // Get most recent previous cash_left as today's old cash
            $prev_record = $wpdb->get_row($wpdb->prepare(
                "SELECT cash_left FROM $table WHERE summary_date < %s ORDER BY summary_date DESC LIMIT 1",
                $today
            ));
            $old_cash = $prev_record ? floatval($prev_record->cash_left) : 0;
            
            // Create new record
            $cash_left = ($cash_amount + $old_cash); // No expenses yet
            
            $wpdb->insert($table, array(
                'summary_date' => $today,
                'total_sales' => $grand_total,
                'cash_sales' => $cash_amount,
                'transfer_sales' => $transfer_amount,
                'delivery_fees' => $delivery_fee,
                'extras_amount' => 0,
                'expenses_amount' => 0,
                'old_cash' => $old_cash,
                'cash_left' => $cash_left,
                'staff_id' => $staff_id
            ));
        }
    }
    
    /**
     * Get orders with filters
     */
    public static function get_orders($filters = array()) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'stand120_orders';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $sql = "SELECT o.*, s.full_name as staff_name 
                FROM $orders_table o 
                LEFT JOIN $staff_table s ON o.staff_id = s.id 
                WHERE 1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND o.order_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND o.order_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['staff_id'])) {
            $sql .= " AND o.staff_id = %d";
            $params[] = $filters['staff_id'];
        }
        
        $sql .= " ORDER BY o.order_date DESC, o.order_time DESC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = str_replace("SELECT o.*, s.full_name as staff_name", "SELECT COUNT(*)", $sql);
        if (!empty($params)) {
            $total = $wpdb->get_var($wpdb->prepare($count_sql, $params));
        } else {
            $total = $wpdb->get_var($count_sql);
        }
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        if (!empty($params)) {
            $orders = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $orders = $wpdb->get_results($sql);
        }
        
        // Get items for each order
        $items_table = $wpdb->prefix . 'stand120_order_items';
        foreach ($orders as &$order) {
            $order->items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $items_table WHERE order_id = %d",
                $order->id
            ));
        }
        
        return array(
            'orders' => $orders,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Get single order
     */
    public static function get_order($id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'stand120_orders';
        $items_table = $wpdb->prefix . 'stand120_order_items';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, s.full_name as staff_name 
            FROM $orders_table o 
            LEFT JOIN $staff_table s ON o.staff_id = s.id 
            WHERE o.id = %d",
            $id
        ));
        
        if ($order) {
            $order->items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $items_table WHERE order_id = %d",
                $id
            ));
        }
        
        return $order;
    }
    
    /**
     * Get today's orders summary
     */
    public static function get_today_summary() {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_orders';
        $today = date('Y-m-d');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(grand_total) as total_sales,
                SUM(cash_amount) as cash_sales,
                SUM(transfer_amount) as transfer_sales,
                SUM(delivery_fee) as delivery_fees
            FROM $table 
            WHERE order_date = %s",
            $today
        ));
    }
}
