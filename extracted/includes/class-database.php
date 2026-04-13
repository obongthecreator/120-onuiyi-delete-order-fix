<?php
/**
 * Database Class
 * Handles all database operations for 120 Stand Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Database {
    
    /**
     * Create all required database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Products table
        $table_products = $wpdb->prefix . 'stand120_products';
        $sql_products = "CREATE TABLE $table_products (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0,
            type varchar(50) NOT NULL DEFAULT 'non_fruit',
            unit varchar(50) DEFAULT 'piece',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_products);
        
        // Staff/Users table extension
        $table_staff = $wpdb->prefix . 'stand120_staff';
        $sql_staff = "CREATE TABLE $table_staff (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            full_name varchar(255) NOT NULL,
            phone varchar(50),
            role varchar(50) DEFAULT 'staff',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_staff);
        
        // Take Orders table
        $table_orders = $wpdb->prefix . 'stand120_orders';
        $sql_orders = "CREATE TABLE $table_orders (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            staff_id mediumint(9) NOT NULL,
            order_date date NOT NULL,
            order_time time NOT NULL,
            subtotal decimal(10,2) NOT NULL DEFAULT 0,
            delivery_fee decimal(10,2) DEFAULT 0,
            grand_total decimal(10,2) NOT NULL DEFAULT 0,
            payment_method varchar(50) NOT NULL,
            cash_amount decimal(10,2) DEFAULT 0,
            transfer_amount decimal(10,2) DEFAULT 0,
            payment_confirmed tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'completed',
            synced tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY staff_id (staff_id),
            KEY order_date (order_date)
        ) $charset_collate;";
        dbDelta($sql_orders);
        
        // Order Items table
        $table_order_items = $wpdb->prefix . 'stand120_order_items';
        $sql_order_items = "CREATE TABLE $table_order_items (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id mediumint(9) NOT NULL,
            product_id mediumint(9) NOT NULL,
            product_name varchar(255) NOT NULL,
            price decimal(10,2) NOT NULL,
            quantity int NOT NULL DEFAULT 0,
            total decimal(10,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta($sql_order_items);
        
        // Order Preparation table
        $table_order_prep = $wpdb->prefix . 'stand120_order_preparation';
        $sql_order_prep = "CREATE TABLE $table_order_prep (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            prep_date date NOT NULL,
            opening_value decimal(10,2) DEFAULT 0,
            total_added decimal(10,2) DEFAULT 0,
            total_sold decimal(10,2) DEFAULT 0,
            closing_value decimal(10,2) DEFAULT 0,
            staff_id mediumint(9),
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_date (product_id, prep_date),
            KEY prep_date (prep_date)
        ) $charset_collate;";
        dbDelta($sql_order_prep);
        
        // Stock Inventory table
        $table_stock = $wpdb->prefix . 'stand120_stock_inventory';
        $sql_stock = "CREATE TABLE $table_stock (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            stock_date date NOT NULL,
            opening_packs decimal(10,2) DEFAULT 0,
            added_packs decimal(10,2) DEFAULT 0,
            used_packs decimal(10,2) DEFAULT 0,
            closing_packs decimal(10,2) DEFAULT 0,
            staff_id mediumint(9),
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_date (product_id, stock_date),
            KEY stock_date (stock_date)
        ) $charset_collate;";
        dbDelta($sql_stock);
        
        // Chopping Inventory table
        $table_chopping = $wpdb->prefix . 'stand120_chopping_inventory';
        $sql_chopping = "CREATE TABLE $table_chopping (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            chop_date date NOT NULL,
            opening_whole decimal(10,2) DEFAULT 0,
            import_whole decimal(10,2) DEFAULT 0,
            prepared_whole decimal(10,2) DEFAULT 0,
            closing_whole decimal(10,2) DEFAULT 0,
            packs_gotten decimal(10,2) DEFAULT 0,
            remarks text,
            staff_id mediumint(9),
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_date (product_id, chop_date),
            KEY chop_date (chop_date)
        ) $charset_collate;";
        dbDelta($sql_chopping);
        
        // Import Records table
        $table_import = $wpdb->prefix . 'stand120_import_records';
        $sql_import = "CREATE TABLE $table_import (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            import_date date NOT NULL,
            quantity_imported decimal(10,2) DEFAULT 0,
            sync_status varchar(50) DEFAULT 'synced',
            staff_id mediumint(9),
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_date (product_id, import_date),
            KEY import_date (import_date)
        ) $charset_collate;";
        dbDelta($sql_import);
        
        // Expenses table (individual expense line items)
        $table_expenses = $wpdb->prefix . 'stand120_expenses';
        $sql_expenses = "CREATE TABLE $table_expenses (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            expense_date date NOT NULL,
            description varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0,
            staff_id mediumint(9),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY expense_date (expense_date)
        ) $charset_collate;";
        dbDelta($sql_expenses);
        
        // Financial Summary table
        $table_financial = $wpdb->prefix . 'stand120_financial_summary';
        $sql_financial = "CREATE TABLE $table_financial (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            summary_date date NOT NULL,
            total_sales decimal(10,2) DEFAULT 0,
            transfer_sales decimal(10,2) DEFAULT 0,
            cash_sales decimal(10,2) DEFAULT 0,
            delivery_fees decimal(10,2) DEFAULT 0,
            extras_amount decimal(10,2) DEFAULT 0,
            extras_remark text,
            expenses_amount decimal(10,2) DEFAULT 0,
            expenses_remark text,
            market_card_expense decimal(10,2) DEFAULT 0,
            old_cash decimal(10,2) DEFAULT 0,
            cash_left decimal(10,2) DEFAULT 0,
            staff_id mediumint(9),
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY summary_date (summary_date)
        ) $charset_collate;";
        dbDelta($sql_financial);
        
        // Offline Sync Queue table
        $table_sync = $wpdb->prefix . 'stand120_sync_queue';
        $sql_sync = "CREATE TABLE $table_sync (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            action_type varchar(50) NOT NULL,
            table_name varchar(100) NOT NULL,
            record_data longtext NOT NULL,
            staff_id mediumint(9),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            synced tinyint(1) DEFAULT 0,
            synced_at datetime,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_sync);
        
        // Activity Log table
        $table_log = $wpdb->prefix . 'stand120_activity_log';
        $sql_log = "CREATE TABLE $table_log (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            staff_id mediumint(9),
            action varchar(255) NOT NULL,
            table_name varchar(100),
            record_id mediumint(9),
            old_value longtext,
            new_value longtext,
            ip_address varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY staff_id (staff_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_log);
        
        // Reconciliation table
        $table_reconciliation = $wpdb->prefix . 'stand120_reconciliation';
        $sql_reconciliation = "CREATE TABLE $table_reconciliation (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            reconcile_date date NOT NULL,
            staff_id mediumint(9) NOT NULL,
            remark text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date_staff (reconcile_date, staff_id),
            KEY reconcile_date (reconcile_date)
        ) $charset_collate;";
        dbDelta($sql_reconciliation);
    }
    
    /**
     * Insert default data
     */
    public static function insert_default_data() {
        global $wpdb;
        
        $table_products = $wpdb->prefix . 'stand120_products';
        
        // Check if products already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_products");
        
        if ($count == 0) {
            // Insert default fruit products
            $fruits = array(
                array('name' => 'Watermelon', 'price' => 500, 'type' => 'fruit'),
                array('name' => 'Pineapple', 'price' => 500, 'type' => 'fruit'),
                array('name' => 'Pawpaw', 'price' => 400, 'type' => 'fruit'),
                array('name' => 'Apple', 'price' => 600, 'type' => 'fruit'),
                array('name' => 'Banana', 'price' => 300, 'type' => 'fruit'),
                array('name' => 'Orange', 'price' => 400, 'type' => 'fruit'),
                array('name' => 'Mango', 'price' => 500, 'type' => 'fruit'),
                array('name' => 'Grape', 'price' => 700, 'type' => 'fruit'),
            );
            
            // Insert default non-fruit products
            $non_fruits = array(
                array('name' => 'Condensed Milk', 'price' => 800, 'type' => 'non_fruit'),
                array('name' => 'Evaporated Milk', 'price' => 600, 'type' => 'non_fruit'),
                array('name' => 'Yoghurt', 'price' => 500, 'type' => 'non_fruit'),
                array('name' => 'Ice Cream', 'price' => 400, 'type' => 'non_fruit'),
                array('name' => 'Disposable Cup (Small)', 'price' => 0, 'type' => 'non_fruit'),
                array('name' => 'Disposable Cup (Medium)', 'price' => 0, 'type' => 'non_fruit'),
                array('name' => 'Disposable Cup (Large)', 'price' => 0, 'type' => 'non_fruit'),
                array('name' => 'Spoon', 'price' => 0, 'type' => 'non_fruit'),
            );
            
            // Insert menu items (sellable products)
            $menu_items = array(
                array('name' => 'Mini Fruit Salad', 'price' => 1500, 'type' => 'menu'),
                array('name' => 'Small Fruit Salad', 'price' => 2000, 'type' => 'menu'),
                array('name' => 'Medium Fruit Salad', 'price' => 3000, 'type' => 'menu'),
                array('name' => 'Large Fruit Salad', 'price' => 4000, 'type' => 'menu'),
                array('name' => 'Premium Fruit Salad', 'price' => 5000, 'type' => 'menu'),
                array('name' => 'Extra Condensed Milk', 'price' => 500, 'type' => 'menu'),
                array('name' => 'Extra Yoghurt', 'price' => 300, 'type' => 'menu'),
            );
            
            foreach (array_merge($fruits, $non_fruits, $menu_items) as $product) {
                $wpdb->insert($table_products, $product);
            }
        }
    }
    
    /**
     * Get all products
     */
    public static function get_products($type = null, $status = 'active') {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_products';
        
        $sql = "SELECT * FROM $table WHERE status = %s";
        $params = array($status);
        
        if ($type) {
            $sql .= " AND type = %s";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY name ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Get menu items for take order
     */
    public static function get_menu_items() {
        return self::get_products('menu');
    }
    
    /**
     * Get fruit products
     */
    public static function get_fruits() {
        return self::get_products('fruit');
    }
    
    /**
     * Get non-fruit products
     */
    public static function get_non_fruits() {
        return self::get_products('non_fruit');
    }
    
    /**
     * Get inventory products (fruits + non-fruits)
     */
    public static function get_inventory_products() {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_products';
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'active' AND type IN ('fruit', 'non_fruit') ORDER BY type, name ASC"
        );
    }
    
    /**
     * Get single product
     */
    public static function get_product($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_products';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Add product
     */
    public static function add_product($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_products';
        
        $result = $wpdb->insert($table, $data, array('%s', '%f', '%s', '%s'));
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update product
     */
    public static function update_product($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_products';
        
        return $wpdb->update($table, $data, array('id' => $id), array('%s', '%f', '%s'), array('%d'));
    }
    
    /**
     * Delete product (soft delete)
     */
    public static function delete_product($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_products';
        
        return $wpdb->update($table, array('status' => 'deleted'), array('id' => $id));
    }
    
    /**
     * Log activity
     */
    public static function log_activity($action, $table_name = null, $record_id = null, $old_value = null, $new_value = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_activity_log';
        
        $staff_id = Stand120_Auth::get_current_staff_id();
        
        // Get client IP address safely
        $ip_address = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_address = trim($ip_list[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip_address = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
        // Sanitize IP address
        $ip_address = filter_var($ip_address, FILTER_VALIDATE_IP) ? $ip_address : '';
        
        // JSON-encode arrays and objects to prevent fatal errors
        if (is_array($old_value) || is_object($old_value)) {
            $old_value = wp_json_encode($old_value);
        }
        if (is_array($new_value) || is_object($new_value)) {
            $new_value = wp_json_encode($new_value);
        }
        
        $wpdb->insert($table, array(
            'staff_id' => $staff_id,
            'action' => $action,
            'table_name' => $table_name,
            'record_id' => $record_id,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'ip_address' => $ip_address
        ));
    }
}