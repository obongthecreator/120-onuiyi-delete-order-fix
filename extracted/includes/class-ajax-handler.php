<?php
/**
 * AJAX Handler Class
 * Handles all AJAX requests for the inventory system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Ajax_Handler {
    
    /**
     * Actions that do not require nonce validation.
     * Using static property instead of class const for PHP 7.0 compatibility.
     */
    private static $nonce_exempt_actions = array('login', 'check_login_status');

    private static function is_nonce_exempt_action($action) {
        return in_array($action, self::$nonce_exempt_actions, true);
    }

    /**
     * Handle AJAX requests
     */
    public static function handle() {
        $action = sanitize_text_field($_POST['stand120_action'] ?? '');
        
        // Login action doesn't require nonce verification (user isn't logged in yet)
        // But still verify the nonce is properly formatted
        if (!self::is_nonce_exempt_action($action)) {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'stand120_nonce')) {
                wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
                return;
            }
        }
        
        switch ($action) {
            // Auth actions
            case 'login':
                self::handle_login();
                break;
            case 'check_login_status':
                self::check_login_status();
                break;
            case 'logout':
                self::handle_logout();
                break;
            
            // Product actions
            case 'get_products':
                self::get_products();
                break;
            case 'get_menu_items':
                self::get_menu_items();
                break;
            case 'add_product':
                self::add_product();
                break;
            case 'update_product':
                self::update_product();
                break;
            case 'delete_product':
                self::delete_product();
                break;
            
            // Take Order actions
            case 'submit_order':
                self::submit_order();
                break;
            case 'get_orders':
                self::get_orders();
                break;
            case 'get_order':
                self::get_order();
                break;
            
            // Order Preparation actions
            case 'save_order_preparation':
                self::save_order_preparation();
                break;
            case 'get_order_preparation':
                self::get_order_preparation();
                break;
            case 'get_order_preparation_history':
                self::get_order_preparation_history();
                break;
            case 'update_opening_values':
                self::update_opening_values();
                break;
            case 'update_all_opening_values':
                self::update_all_opening_values();
                break;
            
            // Stock Inventory actions
            case 'save_stock_inventory':
                self::save_stock_inventory();
                break;
            case 'get_stock_inventory':
                self::get_stock_inventory();
                break;
            case 'get_stock_inventory_history':
                self::get_stock_inventory_history();
                break;
            
            // Chopping Inventory actions
            case 'save_chopping_inventory':
                self::save_chopping_inventory();
                break;
            case 'get_chopping_inventory':
                self::get_chopping_inventory();
                break;
            case 'get_chopping_inventory_history':
                self::get_chopping_inventory_history();
                break;
            
            // Import Record actions
            case 'save_import_record':
                self::save_import_record();
                break;
            case 'get_import_records':
                self::get_import_records();
                break;
            case 'get_import_history':
                self::get_import_history();
                break;
            
            // Financial Summary actions
            case 'save_financial_summary':
                self::save_financial_summary();
                break;
            case 'get_financial_summary':
                self::get_financial_summary();
                break;
            case 'get_financial_summary_history':
                self::get_financial_summary_history();
                break;
            case 'update_financial_summary_record':
                self::update_financial_summary_record();
                break;
            
            // Market Expense actions
            case 'submit_expenses':
                self::submit_expenses();
                break;
            case 'get_expenses':
                self::get_expenses();
                break;
            case 'delete_expense':
                self::delete_expense_item();
                break;
            case 'get_expense_history':
                self::get_expense_history();
                break;
            
            // Product Summary actions
            case 'get_product_summary':
                self::get_product_summary();
                break;
            
            // Sync actions
            case 'sync_offline_data':
                self::sync_offline_data();
                break;
            
            // Staff actions
            case 'get_staff':
                self::get_staff();
                break;
            case 'create_staff':
                self::create_staff();
                break;
            case 'update_staff':
                self::update_staff_action();
                break;
            case 'delete_staff':
                self::delete_staff();
                break;
            
            // Analytics
            case 'get_analytics':
                self::get_analytics();
                break;
            case 'export_data':
                self::export_data();
                break;
            case 'clear_all_records':
                self::clear_all_records();
                break;
            
            // Reconciliation actions
            case 'submit_reconciliation':
                self::submit_reconciliation();
                break;
            case 'get_reconciliation_status':
                self::get_reconciliation_status();
                break;
            case 'get_reconciliation_for_date':
                self::get_reconciliation_for_date();
                break;
            
            default:
                wp_send_json_error(array('message' => 'Invalid action'));
        }
    }
    
    /**
     * Handle login
     */
    private static function handle_login() {
        // Use wp_unslash + trim to preserve special chars in usernames/emails.
        // sanitize_text_field strips HTML entities and can corrupt usernames that
        // contain characters like & or <.
        $username = trim(wp_unslash($_POST['username'] ?? ''));
        $password = wp_unslash($_POST['password'] ?? '');
        
        if (empty($username) || empty($password)) {
            wp_send_json_error(array('message' => 'Please enter username and password'));
        }
        
        $result = Stand120_Auth::login($username, $password);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle logout
     */
    private static function handle_logout() {
        $result = Stand120_Auth::logout();
        wp_send_json_success($result);
    }
    
    /**
     * Get products
     */
    private static function get_products() {
        $type = sanitize_text_field($_POST['type'] ?? '');
        $products = Stand120_Database::get_products($type ?: null);
        wp_send_json_success(array('products' => $products));
    }
    
    /**
     * Get menu items
     */
    private static function get_menu_items() {
        $items = Stand120_Database::get_menu_items();
        wp_send_json_success(array('items' => $items));
    }
    
    /**
     * Add product
     */
    private static function add_product() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'type' => sanitize_text_field($_POST['type'] ?? 'menu'),
            'status' => 'active'
        );
        
        if (empty($data['name'])) {
            wp_send_json_error(array('message' => 'Product name is required'));
            return;
        }
        
        $result = Stand120_Database::add_product($data);
        
        if ($result === false) {
            global $wpdb;
            wp_send_json_error(array('message' => 'Failed to add product: ' . $wpdb->last_error));
            return;
        }
        
        Stand120_Database::log_activity('add_product', 'stand120_products', null, null, $data);
        
        wp_send_json_success(array('message' => 'Product added successfully', 'product_id' => $result));
    }
    
    /**
     * Update product
     */
    private static function update_product() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            wp_send_json_error(array('message' => 'Product ID is required'));
            return;
        }
        
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'type' => sanitize_text_field($_POST['type'] ?? 'menu')
        );
        
        $old = Stand120_Database::get_product($id);
        $result = Stand120_Database::update_product($id, $data);
        Stand120_Database::log_activity('update_product', 'stand120_products', $id, $old, $data);
        
        wp_send_json_success(array('message' => 'Product updated successfully'));
    }
    
    /**
     * Delete product
     */
    private static function delete_product() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            wp_send_json_error(array('message' => 'Product ID is required'));
            return;
        }
        
        Stand120_Database::delete_product($id);
        Stand120_Database::log_activity('delete_product', 'stand120_products', $id);
        
        wp_send_json_success(array('message' => 'Product deleted successfully'));
    }
    
    /**
     * Submit order
     */
    private static function submit_order() {
        // Check if user is logged into WordPress at all
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Session expired. Please refresh the page and login again.'));
            return;
        }
        
        // Skip the Stand120 logged in check - just use WordPress login status
        // This ensures the system works even if the staff record doesn't exist yet
        
        $result = Stand120_Take_Order::submit_order($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get orders
     */
    private static function get_orders() {
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'staff_id' => intval($_POST['staff_id'] ?? 0),
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 20)
        );
        
        $result = Stand120_Take_Order::get_orders($filters);
        wp_send_json_success($result);
    }
    
    /**
     * Get single order
     */
    private static function get_order() {
        $id = intval($_POST['order_id'] ?? 0);
        $order = Stand120_Take_Order::get_order($id);
        
        if ($order) {
            wp_send_json_success(array('order' => $order));
        } else {
            wp_send_json_error(array('message' => 'Order not found'));
        }
    }
    
    /**
     * Save order preparation
     */
    private static function save_order_preparation() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        $result = Stand120_Order_Preparation::save($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get order preparation
     */
    private static function get_order_preparation() {
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $result = Stand120_Order_Preparation::get_for_date($date);
        wp_send_json_success($result);
    }
    
    /**
     * Get order preparation history
     */
    private static function get_order_preparation_history() {
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'product_id' => intval($_POST['product_id'] ?? 0),
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 20)
        );
        
        $result = Stand120_Order_Preparation::get_history($filters);
        wp_send_json_success($result);
    }
    
    /**
     * Update opening values (admin only)
     */
    private static function update_opening_values() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $table = sanitize_text_field($_POST['table'] ?? '');
        $product_id = intval($_POST['product_id'] ?? 0);
        $value = floatval($_POST['value'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        
        switch ($table) {
            case 'order_preparation':
                $result = Stand120_Order_Preparation::update_opening($product_id, $value, $date);
                break;
            case 'stock_inventory':
                $result = Stand120_Stock_Inventory::update_opening($product_id, $value, $date);
                break;
            case 'chopping_inventory':
                $result = Stand120_Chopping_Inventory::update_opening($product_id, $value, $date);
                break;
            default:
                wp_send_json_error(array('message' => 'Invalid table'));
                return;
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Check login status
     */
    private static function check_login_status() {
        wp_send_json_success(array(
            'is_logged_in' => Stand120_Auth::is_logged_in()
        ));
    }

    /**
     * Update opening values in bulk (admin only)
     */
    private static function update_all_opening_values() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $result = Stand120_Admin_Panel::update_all_opening_values($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Save stock inventory
     */
    private static function save_stock_inventory() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        $result = Stand120_Stock_Inventory::save($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get stock inventory
     */
    private static function get_stock_inventory() {
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $result = Stand120_Stock_Inventory::get_for_date($date);
        wp_send_json_success($result);
    }
    
    /**
     * Get stock inventory history
     */
    private static function get_stock_inventory_history() {
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'product_id' => intval($_POST['product_id'] ?? 0),
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 20)
        );
        
        $result = Stand120_Stock_Inventory::get_history($filters);
        wp_send_json_success($result);
    }
    
    /**
     * Save chopping inventory
     */
    private static function save_chopping_inventory() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        $result = Stand120_Chopping_Inventory::save($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get chopping inventory
     */
    private static function get_chopping_inventory() {
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $result = Stand120_Chopping_Inventory::get_for_date($date);
        wp_send_json_success($result);
    }
    
    /**
     * Get chopping inventory history
     */
    private static function get_chopping_inventory_history() {
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'product_id' => intval($_POST['product_id'] ?? 0),
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 20)
        );
        
        $result = Stand120_Chopping_Inventory::get_history($filters);
        wp_send_json_success($result);
    }
    
    /**
     * Save import record
     */
    private static function save_import_record() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        $result = Stand120_Import_Record::save($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get import records
     */
    private static function get_import_records() {
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $result = Stand120_Import_Record::get_for_date($date);
        wp_send_json_success($result);
    }
    
    /**
     * Get import history
     */
    private static function get_import_history() {
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'product_id' => intval($_POST['product_id'] ?? 0),
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 20)
        );
        
        $result = Stand120_Import_Record::get_history($filters);
        wp_send_json_success($result);
    }
    
    /**
     * Save financial summary
     */
    private static function save_financial_summary() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        $result = Stand120_Financial_Summary::save($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get financial summary
     */
    private static function get_financial_summary() {
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $result = Stand120_Financial_Summary::get_for_date($date);
        wp_send_json_success($result);
    }
    
    /**
     * Get financial summary history
     */
    private static function get_financial_summary_history() {
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 20)
        );
        
        $result = Stand120_Financial_Summary::get_history($filters);
        wp_send_json_success($result);
    }
    
    /**
     * Update a financial summary record (admin-only, for editing old_cash/cash_left)
     */
    private static function update_financial_summary_record() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Admin access required'));
            return;
        }
        
        $result = Stand120_Financial_Summary::update_record($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get product summary
     */
    private static function get_product_summary() {
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? date('Y-m-d')),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? date('Y-m-d')),
            'product_id' => intval($_POST['product_id'] ?? 0),
            'staff_id' => intval($_POST['staff_id'] ?? 0),
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 50)
        );
        
        $result = Stand120_Product_Summary::get_summary($filters);
        wp_send_json_success($result);
    }
    
    /**
     * Sync offline data
     */
    private static function sync_offline_data() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        $data = json_decode(stripslashes($_POST['offline_data'] ?? '[]'), true);
        
        if (!is_array($data)) {
            wp_send_json_error(array('message' => 'Invalid data format'));
        }
        
        $results = array();
        
        foreach ($data as $item) {
            $type = $item['type'] ?? '';
            $record = $item['data'] ?? array();
            
            switch ($type) {
                case 'order':
                    $result = Stand120_Take_Order::submit_order($record);
                    break;
                case 'order_preparation':
                    $result = Stand120_Order_Preparation::save($record);
                    break;
                case 'stock_inventory':
                    $result = Stand120_Stock_Inventory::save($record);
                    break;
                case 'chopping_inventory':
                    $result = Stand120_Chopping_Inventory::save($record);
                    break;
                case 'import_record':
                    $result = Stand120_Import_Record::save($record);
                    break;
                case 'financial_summary':
                    $result = Stand120_Financial_Summary::save($record);
                    break;
                default:
                    $result = array('success' => false, 'message' => 'Unknown type');
            }
            
            $results[] = array(
                'type' => $type,
                'local_id' => $item['local_id'] ?? '',
                'result' => $result
            );
        }
        
        wp_send_json_success(array(
            'message' => 'Sync completed',
            'results' => $results
        ));
    }
    
    /**
     * Get staff
     */
    private static function get_staff() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $staff = Stand120_Auth::get_all_staff();
        wp_send_json_success(array('staff' => $staff));
    }
    
    /**
     * Create staff
     */
    private static function create_staff() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $data = array(
            'username' => sanitize_user($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'email' => sanitize_email($_POST['email'] ?? ''),
            'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? '')
        );
        
        $result = Stand120_Auth::create_staff($data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Update staff
     */
    private static function update_staff_action() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $data = array(
            'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'status' => sanitize_text_field($_POST['status'] ?? 'active')
        );
        
        $result = Stand120_Auth::update_staff($staff_id, $data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Delete staff
     */
    private static function delete_staff() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $result = Stand120_Auth::delete_staff($staff_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get analytics
     */
    private static function get_analytics() {
        $type = sanitize_text_field($_POST['type'] ?? 'overview');
        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));
        $period = sanitize_text_field($_POST['period'] ?? '');
        $reference_date = sanitize_text_field($_POST['date'] ?? $date_to);
        
        if ($period) {
            $reference_timestamp = strtotime($reference_date);
            if (!$reference_timestamp) {
                $reference_timestamp = time();
            }
            switch ($period) {
                case 'daily':
                    $date_from = $reference_date;
                    $date_to = $reference_date;
                    break;
                case 'weekly':
                    $date_from = date('Y-m-d', strtotime('monday this week', $reference_timestamp));
                    $date_to = date('Y-m-d', strtotime('sunday this week', $reference_timestamp));
                    break;
                case 'monthly':
                    $date_from = date('Y-m-01', $reference_timestamp);
                    $date_to = date('Y-m-t', $reference_timestamp);
                    break;
            }
        }
        
        global $wpdb;
        
        $analytics = array();
        
        switch ($type) {
            case 'overview':
                // Total sales
                $orders_table = $wpdb->prefix . 'stand120_orders';
                $analytics['total_sales'] = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(grand_total) FROM $orders_table WHERE order_date BETWEEN %s AND %s",
                    $date_from, $date_to
                )) ?: 0;
                
                $analytics['total_orders'] = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $orders_table WHERE order_date BETWEEN %s AND %s",
                    $date_from, $date_to
                )) ?: 0;
                
                // Staff performance
                $staff_table = $wpdb->prefix . 'stand120_staff';
                $analytics['staff_performance'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT s.full_name, COUNT(o.id) as order_count, SUM(o.grand_total) as total_sales
                    FROM $staff_table s
                    LEFT JOIN $orders_table o ON s.id = o.staff_id AND o.order_date BETWEEN %s AND %s
                    WHERE s.status = 'active'
                    GROUP BY s.id
                    ORDER BY total_sales DESC",
                    $date_from, $date_to
                ));
                
                // Daily sales trend
                $analytics['daily_sales'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT order_date, SUM(grand_total) as total
                    FROM $orders_table
                    WHERE order_date BETWEEN %s AND %s
                    GROUP BY order_date
                    ORDER BY order_date ASC",
                    $date_from, $date_to
                ));
                
                // Top products
                $items_table = $wpdb->prefix . 'stand120_order_items';
                $analytics['top_products'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT oi.product_name, SUM(oi.quantity) as qty_sold, SUM(oi.total) as revenue
                    FROM $items_table oi
                    JOIN $orders_table o ON oi.order_id = o.id
                    WHERE o.order_date BETWEEN %s AND %s
                    GROUP BY oi.product_id
                    ORDER BY qty_sold DESC
                    LIMIT 10",
                    $date_from, $date_to
                ));
                
                $products_table = $wpdb->prefix . 'stand120_products';
                $prep_table = $wpdb->prefix . 'stand120_order_preparation';
                $stock_table = $wpdb->prefix . 'stand120_stock_inventory';
                $chop_table = $wpdb->prefix . 'stand120_chopping_inventory';
                $import_table = $wpdb->prefix . 'stand120_import_records';
                $financial_table = $wpdb->prefix . 'stand120_financial_summary';
                
                $analytics['preparation'] = array(
                    'summary' => $wpdb->get_row($wpdb->prepare(
                        "SELECT 
                            SUM(total_added) as total_added,
                            SUM(total_sold) as total_sold,
                            SUM(closing_value) as closing_value
                        FROM $prep_table
                        WHERE prep_date BETWEEN %s AND %s",
                        $date_from, $date_to
                    )),
                    'records' => $wpdb->get_results($wpdb->prepare(
                        "SELECT p.name as product_name,
                            SUM(op.total_added) as total_added,
                            SUM(op.total_sold) as total_sold,
                            SUM(op.closing_value) as closing_value
                        FROM $prep_table op
                        JOIN $products_table p ON op.product_id = p.id
                        WHERE op.prep_date BETWEEN %s AND %s
                        GROUP BY op.product_id
                        ORDER BY p.name ASC",
                        $date_from, $date_to
                    ))
                );
                
                $analytics['stock'] = array(
                    'summary' => $wpdb->get_row($wpdb->prepare(
                        "SELECT 
                            SUM(added_packs) as added_packs,
                            SUM(used_packs) as used_packs,
                            SUM(closing_packs) as closing_packs
                        FROM $stock_table
                        WHERE stock_date BETWEEN %s AND %s",
                        $date_from, $date_to
                    )),
                    'records' => $wpdb->get_results($wpdb->prepare(
                        "SELECT p.name as product_name, p.type as product_type,
                            SUM(si.added_packs) as added_packs,
                            SUM(si.used_packs) as used_packs,
                            SUM(si.closing_packs) as closing_packs
                        FROM $stock_table si
                        JOIN $products_table p ON si.product_id = p.id
                        WHERE si.stock_date BETWEEN %s AND %s
                        GROUP BY si.product_id
                        ORDER BY p.name ASC",
                        $date_from, $date_to
                    ))
                );
                
                $analytics['chopping'] = array(
                    'summary' => $wpdb->get_row($wpdb->prepare(
                        "SELECT 
                            SUM(import_whole) as import_whole,
                            SUM(prepared_whole) as prepared_whole,
                            SUM(packs_gotten) as packs_gotten
                        FROM $chop_table
                        WHERE chop_date BETWEEN %s AND %s",
                        $date_from, $date_to
                    )),
                    'records' => $wpdb->get_results($wpdb->prepare(
                        "SELECT p.name as product_name,
                            SUM(ci.import_whole) as import_whole,
                            SUM(ci.prepared_whole) as prepared_whole,
                            SUM(ci.packs_gotten) as packs_gotten
                        FROM $chop_table ci
                        JOIN $products_table p ON ci.product_id = p.id
                        WHERE ci.chop_date BETWEEN %s AND %s
                        GROUP BY ci.product_id
                        ORDER BY p.name ASC",
                        $date_from, $date_to
                    ))
                );
                
                $analytics['imports'] = array(
                    'summary' => $wpdb->get_row($wpdb->prepare(
                        "SELECT SUM(quantity_imported) as quantity_imported
                        FROM $import_table
                        WHERE import_date BETWEEN %s AND %s",
                        $date_from, $date_to
                    )),
                    'records' => $wpdb->get_results($wpdb->prepare(
                        "SELECT p.name as product_name, p.type as product_type,
                            SUM(ir.quantity_imported) as quantity_imported
                        FROM $import_table ir
                        JOIN $products_table p ON ir.product_id = p.id
                        WHERE ir.import_date BETWEEN %s AND %s
                        GROUP BY ir.product_id
                        ORDER BY p.name ASC",
                        $date_from, $date_to
                    ))
                );
                
                $analytics['financials'] = $wpdb->get_row($wpdb->prepare(
                    "SELECT 
                        SUM(total_sales) as total_sales,
                        SUM(cash_sales) as cash_sales,
                        SUM(transfer_sales) as transfer_sales,
                        SUM(delivery_fees) as delivery_fees,
                        SUM(extras_amount) as extras_amount,
                        SUM(expenses_amount) as expenses_amount
                    FROM $financial_table
                    WHERE summary_date BETWEEN %s AND %s",
                    $date_from, $date_to
                ));
                
                $analytics['period'] = array(
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'period' => $period ?: 'custom'
                );
                break;
                
            case 'inventory':
                // Stock levels
                $stock_table = $wpdb->prefix . 'stand120_stock_inventory';
                $products_table = $wpdb->prefix . 'stand120_products';
                $analytics['current_stock'] = $wpdb->get_results(
                    "SELECT p.name, s.closing_packs
                    FROM $stock_table s
                    JOIN $products_table p ON s.product_id = p.id
                    WHERE s.stock_date = CURDATE()
                    ORDER BY p.name"
                );
                break;
        }
        
        wp_send_json_success(array('analytics' => $analytics));
    }

    /**
     * Export data (admin only)
     */
    private static function export_data() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');
        
        $allowed_types = array('orders', 'financial', 'stock', 'preparation', 'chopping', 'imports');
        if (!in_array($type, $allowed_types, true)) {
            wp_send_json_error(array('message' => 'Invalid export type'));
            return;
        }
        
        $from_date = $date_from ? DateTime::createFromFormat('Y-m-d', $date_from) : null;
        $to_date = $date_to ? DateTime::createFromFormat('Y-m-d', $date_to) : null;
        $from_valid = !$date_from || ($from_date instanceof DateTime && $from_date->format('Y-m-d') === $date_from);
        $to_valid = !$date_to || ($to_date instanceof DateTime && $to_date->format('Y-m-d') === $date_to);
        
        if (!$from_valid || !$to_valid) {
            wp_send_json_error(array('message' => 'Invalid date range'));
            return;
        }
        
        $result = Stand120_Admin_Panel::export_data($type, $date_from, $date_to);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Clear all records and histories (admin only)
     */
    private static function clear_all_records() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
            return;
        }
        
        $result = Stand120_Admin_Panel::clear_all_records();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Submit/save market expenses
     */
    private static function submit_expenses() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        $result = Stand120_Expense_Record::save($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get expenses for a date
     */
    private static function get_expenses() {
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $result = Stand120_Expense_Record::get_for_date($date);
        wp_send_json_success($result);
    }
    
    /**
     * Delete a single expense item
     */
    private static function delete_expense_item() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login to continue'));
            return;
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => 'Expense ID required'));
            return;
        }
        
        $result = Stand120_Expense_Record::delete($id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get expense history
     */
    private static function get_expense_history() {
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 50)
        );
        
        $result = Stand120_Expense_Record::get_history($filters);
        wp_send_json_success($result);
    }
    
    /**
     * Submit reconciliation
     */
    private static function submit_reconciliation() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Only admins can reconcile records'));
            return;
        }
        
        $data = array(
            'date' => sanitize_text_field($_POST['date'] ?? ''),
            'remark' => sanitize_text_field($_POST['remark'] ?? '')
        );
        
        $result = Stand120_Reconciliation::submit($data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get reconciliation status for a date range
     */
    private static function get_reconciliation_status() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $start_date = sanitize_text_field($_POST['start_date'] ?? date('Y-m-01'));
        $end_date = sanitize_text_field($_POST['end_date'] ?? date('Y-m-t'));
        
        $dates = Stand120_Reconciliation::get_status($start_date, $end_date);
        
        wp_send_json_success(array('dates' => $dates));
    }
    
    /**
     * Get reconciliation for a specific date
     */
    private static function get_reconciliation_for_date() {
        if (!Stand120_Auth::is_admin()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        if (empty($date)) {
            wp_send_json_error(array('message' => 'Date is required'));
            return;
        }
        
        $result = Stand120_Reconciliation::get_for_date($date);
        
        wp_send_json_success($result);
    }
}