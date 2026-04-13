<?php
/**
 * Admin Panel Class
 * Handles admin management functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Admin_Panel {
    
    /**
     * Get all data for admin panel
     */
    public static function get_admin_data() {
        return array(
            'products' => Stand120_Database::get_products(null, 'active'),
            'staff' => Stand120_Auth::get_all_staff(),
            'product_types' => array(
                array('value' => 'menu', 'label' => 'Menu Item'),
                array('value' => 'fruit', 'label' => 'Fruit'),
                array('value' => 'non_fruit', 'label' => 'Non-Fruit')
            )
        );
    }
    
    /**
     * Bulk update products
     */
    public static function bulk_update_products($products) {
        if (!Stand120_Auth::is_admin()) {
            return array('success' => false, 'message' => 'Unauthorized');
        }
        
        foreach ($products as $product) {
            $id = intval($product['id'] ?? 0);
            
            if ($id) {
                Stand120_Database::update_product($id, array(
                    'name' => sanitize_text_field($product['name'] ?? ''),
                    'price' => floatval($product['price'] ?? 0),
                    'type' => sanitize_text_field($product['type'] ?? 'menu')
                ));
            } else {
                Stand120_Database::add_product(array(
                    'name' => sanitize_text_field($product['name'] ?? ''),
                    'price' => floatval($product['price'] ?? 0),
                    'type' => sanitize_text_field($product['type'] ?? 'menu')
                ));
            }
        }
        
        Stand120_Database::log_activity('bulk_update_products', 'stand120_products');
        
        return array('success' => true, 'message' => 'Products updated successfully');
    }
    
    /**
     * Update opening values for all inventory tables
     */
    public static function update_all_opening_values($data) {
        if (!Stand120_Auth::is_admin()) {
            return array('success' => false, 'message' => 'Unauthorized');
        }
        
        $table = sanitize_text_field($data['table'] ?? '');
        $date = sanitize_text_field($data['date'] ?? date('Y-m-d'));
        $values = $data['values'] ?? array();
        
        foreach ($values as $item) {
            $product_id = intval($item['product_id'] ?? 0);
            $value = floatval($item['value'] ?? 0);
            
            if (!$product_id) continue;
            
            switch ($table) {
                case 'order_preparation':
                    Stand120_Order_Preparation::update_opening($product_id, $value, $date);
                    break;
                case 'stock_inventory':
                    Stand120_Stock_Inventory::update_opening($product_id, $value, $date);
                    break;
                case 'chopping_inventory':
                    Stand120_Chopping_Inventory::update_opening($product_id, $value, $date);
                    break;
            }
        }
        
        return array('success' => true, 'message' => 'Opening values updated');
    }

    /**
     * Clear all records and history tables
     */
    public static function clear_all_records() {
        if (!Stand120_Auth::is_admin()) {
            return array('success' => false, 'message' => 'Unauthorized');
        }
        
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        if (!preg_match('/^[a-z0-9_-]+$/i', $prefix)) {
            return array('success' => false, 'message' => 'Invalid table prefix');
        }
        
        $tables = array(
            'stand120_orders',
            'stand120_order_items',
            'stand120_order_preparation',
            'stand120_stock_inventory',
            'stand120_chopping_inventory',
            'stand120_import_records',
            'stand120_expenses',
            'stand120_financial_summary',
            'stand120_sync_queue',
            'stand120_activity_log'
        );
        
        foreach ($tables as $table) {
            $table_name = $prefix . $table;
            if (!preg_match('/^[a-z0-9_-]+$/i', $table_name)) {
                return array('success' => false, 'message' => 'Invalid table name');
            }
            $table_name = esc_sql($table_name);
            $result = $wpdb->query("TRUNCATE TABLE `$table_name`");
            if ($result === false) {
                return array('success' => false, 'message' => 'Failed to clear records: ' . $wpdb->last_error);
            }
        }
        
        return array('success' => true, 'message' => 'All records cleared');
    }
    
    /**
     * Get activity logs
     */
    public static function get_activity_logs($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_activity_log';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $sql = "SELECT al.*, s.full_name as staff_name
                FROM $table al
                LEFT JOIN $staff_table s ON al.staff_id = s.id
                WHERE 1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(al.created_at) >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(al.created_at) <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['staff_id'])) {
            $sql .= " AND al.staff_id = %d";
            $params[] = $filters['staff_id'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = %s";
            $params[] = $filters['action'];
        }
        
        $sql .= " ORDER BY al.created_at DESC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $per_page;
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        if (!empty($params)) {
            $logs = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $logs = $wpdb->get_results($sql);
        }
        
        return array('logs' => $logs);
    }
    
    /**
     * Export data
     */
    public static function export_data($type, $date_from, $date_to) {
        if (!Stand120_Auth::is_admin()) {
            return array('success' => false, 'message' => 'Unauthorized');
        }
        
        global $wpdb;
        $data = array();
        
        switch ($type) {
            case 'orders':
                $result = Stand120_Take_Order::get_orders(array(
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'per_page' => 10000
                ));
                $data = $result['orders'];
                break;
                
            case 'financial':
                $result = Stand120_Financial_Summary::get_history(array(
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'per_page' => 10000
                ));
                $data = $result['records'];
                break;
                
            case 'stock':
                $result = Stand120_Stock_Inventory::get_history(array(
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'per_page' => 10000
                ));
                $data = $result['records'];
                break;
                
            case 'preparation':
                $result = Stand120_Order_Preparation::get_history(array(
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'per_page' => 10000
                ));
                $data = $result['records'];
                break;
                
            case 'chopping':
                $result = Stand120_Chopping_Inventory::get_history(array(
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'per_page' => 10000
                ));
                $data = $result['records'];
                break;
                
            case 'imports':
                $result = Stand120_Import_Record::get_history(array(
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'per_page' => 10000
                ));
                $data = $result['records'];
                break;
        }
        
        return array('success' => true, 'data' => $data);
    }
}