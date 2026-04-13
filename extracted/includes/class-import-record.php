<?php
/**
 * Import Record Class
 * Handles import/purchase records tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Import_Record {
    
    /**
     * Save import record
     */
    public static function save($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_import_records';
        
        $product_id = intval($data['product_id'] ?? 0);
        $date = sanitize_text_field($data['date'] ?? date('Y-m-d'));
        $quantity = floatval($data['quantity'] ?? 0);
        $staff_id = Stand120_Auth::get_current_staff_id();
        
        if (!$product_id) {
            return array('success' => false, 'message' => 'Product ID required');
        }
        
        // Get product to check type
        $product = Stand120_Database::get_product($product_id);
        
        if (!$product) {
            return array('success' => false, 'message' => 'Product not found');
        }
        
        // Get existing record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND import_date = %s",
            $product_id, $date
        ));
        
        if ($existing) {
            // Update existing
            $wpdb->update($table, array(
                'quantity_imported' => $quantity,
                'sync_status' => 'syncing',
                'staff_id' => $staff_id
            ), array('id' => $existing->id));
        } else {
            // Insert new
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'import_date' => $date,
                'quantity_imported' => $quantity,
                'sync_status' => 'syncing',
                'staff_id' => $staff_id
            ));
        }
        
        // Update connected forms based on product type
        if ($product->type === 'fruit') {
            // Update chopping inventory import_whole
            Stand120_Chopping_Inventory::update_import_whole($product_id, $quantity, $date);
        } else {
            // Update stock inventory added_packs for non-fruit
            Stand120_Stock_Inventory::update_added_from_import($product_id, $quantity, $date);
        }
        
        // Update sync status
        $wpdb->update($table, array(
            'sync_status' => 'synced'
        ), array('product_id' => $product_id, 'import_date' => $date));
        
        return array(
            'success' => true,
            'message' => 'Import record saved',
            'data' => array(
                'product_id' => $product_id,
                'quantity' => $quantity,
                'sync_status' => 'synced'
            )
        );
    }
    
    /**
     * Get import records for a date
     */
    public static function get_for_date($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_import_records';
        
        // Get all products (fruits + non-fruits)
        $products = Stand120_Database::get_inventory_products();
        
        $data = array();
        
        foreach ($products as $product) {
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND import_date = %s",
                $product->id, $date
            ));
            
            $data[] = array(
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_type' => $product->type,
                'quantity' => $record ? floatval($record->quantity_imported) : 0,
                'sync_status' => $record ? $record->sync_status : 'pending'
            );
        }
        
        return array('data' => $data, 'date' => $date);
    }
    
    /**
     * Get import history
     */
    public static function get_history($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_import_records';
        $products_table = $wpdb->prefix . 'stand120_products';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $sql = "SELECT ir.*, p.name as product_name, p.type as product_type, s.full_name as staff_name
                FROM $table ir
                LEFT JOIN $products_table p ON ir.product_id = p.id
                LEFT JOIN $staff_table s ON ir.staff_id = s.id
                WHERE 1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND ir.import_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND ir.import_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['product_id'])) {
            $sql .= " AND ir.product_id = %d";
            $params[] = $filters['product_id'];
        }
        
        $sql .= " ORDER BY ir.import_date DESC, p.name ASC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = str_replace("SELECT ir.*, p.name as product_name, p.type as product_type, s.full_name as staff_name", "SELECT COUNT(*)", $sql);
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
