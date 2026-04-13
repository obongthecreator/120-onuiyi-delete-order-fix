<?php
/**
 * Stock Inventory Class
 * Handles stock inventory tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Stock_Inventory {
    
    /**
     * Get the most recent previous day's closing value for a product.
     * Looks back to find the last record, not just yesterday.
     */
    private static function get_previous_closing($product_id, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_stock_inventory';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT closing_packs FROM $table WHERE product_id = %d AND stock_date < %s ORDER BY stock_date DESC LIMIT 1",
            $product_id, $date
        ));
        
        return $record ? floatval($record->closing_packs) : 0;
    }
    
    /**
     * Save stock inventory data
     */
    public static function save($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_stock_inventory';
        
        $product_id = intval($data['product_id'] ?? 0);
        $date = sanitize_text_field($data['date'] ?? date('Y-m-d'));
        $used_packs = floatval($data['used_packs'] ?? 0);
        $staff_id = Stand120_Auth::get_current_staff_id();
        $is_admin = Stand120_Auth::is_admin();
        $admin_opening = isset($data['opening_packs']) && $is_admin ? floatval($data['opening_packs']) : null;
        
        if (!$product_id) {
            return array('success' => false, 'message' => 'Product ID required');
        }
        
        // Get existing record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND stock_date = %s",
            $product_id, $date
        ));
        
        // Get opening value
        $opening = 0;
        $added = 0;
        
        if ($existing) {
            $opening = $existing->opening_packs;
            // Always re-read added_packs from source to stay in sync
            $product = Stand120_Database::get_product($product_id);
            if ($product && $product->type === 'fruit') {
                $added = self::get_packs_from_chopping($product_id, $date);
            } elseif ($product && $product->type === 'non_fruit') {
                $added = self::get_imported_packs($product_id, $date);
            } else {
                $added = $existing->added_packs;
            }
        } else {
            // Get most recent previous day's closing as today's opening
            $opening = self::get_previous_closing($product_id, $date);
            
            // Get added packs from import records (for non-fruits)
            $product = Stand120_Database::get_product($product_id);
            if ($product && $product->type === 'non_fruit') {
                $added = self::get_imported_packs($product_id, $date);
            } elseif ($product && $product->type === 'fruit') {
                // For fruits, get from chopping inventory packs gotten
                $added = self::get_packs_from_chopping($product_id, $date);
            }
        }
        
        // If admin provided an explicit opening value, use it
        if ($admin_opening !== null) {
            $opening = $admin_opening;
        }
        
        // Calculate closing
        $closing = $opening + $added - $used_packs;
        
        if ($existing) {
            // Update existing - include added_packs since it may have been refreshed from source
            $update_data = array(
                'added_packs' => $added,
                'used_packs' => $used_packs,
                'closing_packs' => $closing,
                'staff_id' => $staff_id
            );
            // Persist admin-provided opening value
            if ($admin_opening !== null) {
                $update_data['opening_packs'] = $opening;
            }
            $wpdb->update($table, $update_data, array('id' => $existing->id));
        } else {
            // Insert new
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'stock_date' => $date,
                'opening_packs' => $opening,
                'added_packs' => $added,
                'used_packs' => $used_packs,
                'closing_packs' => $closing,
                'staff_id' => $staff_id
            ));
        }
        
        return array(
            'success' => true,
            'message' => 'Stock inventory saved',
            'data' => array(
                'opening' => $opening,
                'added' => $added,
                'used' => $used_packs,
                'closing' => $closing
            )
        );
    }
    
    /**
     * Get imported packs from import records
     */
    private static function get_imported_packs($product_id, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_import_records';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT quantity_imported FROM $table WHERE product_id = %d AND import_date = %s",
            $product_id, $date
        ));
        
        return $record ? floatval($record->quantity_imported) : 0;
    }
    
    /**
     * Get packs from chopping inventory
     */
    private static function get_packs_from_chopping($product_id, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_chopping_inventory';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT packs_gotten FROM $table WHERE product_id = %d AND chop_date = %s",
            $product_id, $date
        ));
        
        return $record ? floatval($record->packs_gotten) : 0;
    }
    
    /**
     * Update added packs from import
     */
    public static function update_added_from_import($product_id, $quantity, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_stock_inventory';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND stock_date = %s",
            $product_id, $date
        ));
        
        if ($existing) {
            $closing = $existing->opening_packs + $quantity - $existing->used_packs;
            $wpdb->update($table, array(
                'added_packs' => $quantity,
                'closing_packs' => $closing
            ), array('id' => $existing->id));
        } else {
            // Get most recent previous day's closing
            $opening = self::get_previous_closing($product_id, $date);
            
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'stock_date' => $date,
                'opening_packs' => $opening,
                'added_packs' => $quantity,
                'used_packs' => 0,
                'closing_packs' => $opening + $quantity
            ));
        }
    }
    
    /**
     * Update opening value (admin only)
     */
    public static function update_opening($product_id, $value, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_stock_inventory';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND stock_date = %s",
            $product_id, $date
        ));
        
        if ($existing) {
            $closing = $value + $existing->added_packs - $existing->used_packs;
            $wpdb->update($table, array(
                'opening_packs' => $value,
                'closing_packs' => $closing
            ), array('id' => $existing->id));
        } else {
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'stock_date' => $date,
                'opening_packs' => $value,
                'added_packs' => 0,
                'used_packs' => 0,
                'closing_packs' => $value
            ));
        }
        
        Stand120_Database::log_activity('update_opening', 'stand120_stock_inventory', $product_id);
        
        return array('success' => true, 'message' => 'Opening value updated');
    }
    
    /**
     * Get stock inventory for a date
     */
    public static function get_for_date($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_stock_inventory';
        
        // Get all inventory products (fruits + non-fruits)
        $products = Stand120_Database::get_inventory_products();
        
        $data = array();
        
        foreach ($products as $product) {
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND stock_date = %s",
                $product->id, $date
            ));
            
            if ($record) {
                $data[] = array(
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_type' => $product->type,
                    'opening' => floatval($record->opening_packs),
                    'added' => floatval($record->added_packs),
                    'used' => floatval($record->used_packs),
                    'closing' => floatval($record->closing_packs)
                );
            } else {
                // Get most recent previous day's closing as opening
                $opening = self::get_previous_closing($product->id, $date);
                
                // Get added from import or chopping
                $added = 0;
                if ($product->type === 'non_fruit') {
                    $added = self::get_imported_packs($product->id, $date);
                } else {
                    $added = self::get_packs_from_chopping($product->id, $date);
                }
                
                $data[] = array(
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_type' => $product->type,
                    'opening' => $opening,
                    'added' => $added,
                    'used' => 0,
                    'closing' => $opening + $added
                );
            }
        }
        
        return array('data' => $data, 'date' => $date);
    }
    
    /**
     * Self-heal stock inventory records.
     * Ensures each record's opening_packs matches the previous day's closing_packs,
     * and recalculates closing_packs = opening + added - used.
     */
    public static function recalculate_records($date_from = null, $date_to = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_stock_inventory';
        
        $sql = "SELECT * FROM $table WHERE 1=1";
        $params = array();
        
        if (!empty($date_from)) {
            $sql .= " AND stock_date >= %s";
            $params[] = $date_from;
        }
        if (!empty($date_to)) {
            $sql .= " AND stock_date <= %s";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY stock_date ASC, product_id ASC";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $records = $wpdb->get_results($sql);
        }
        
        $fixed = 0;
        
        foreach ($records as $record) {
            // Find the most recent previous closing for this product
            $prev = $wpdb->get_row($wpdb->prepare(
                "SELECT closing_packs FROM $table WHERE product_id = %d AND stock_date < %s ORDER BY stock_date DESC LIMIT 1",
                $record->product_id, $record->stock_date
            ));
            $correct_opening = $prev ? floatval($prev->closing_packs) : 0;
            
            $current_opening = floatval($record->opening_packs);
            $added = floatval($record->added_packs);
            $used = floatval($record->used_packs);
            $correct_closing = $correct_opening + $added - $used;
            $current_closing = floatval($record->closing_packs);
            
            $opening_wrong = abs($current_opening - $correct_opening) > 0.01;
            $closing_wrong = abs($current_closing - $correct_closing) > 0.01;
            
            if ($opening_wrong || $closing_wrong) {
                $wpdb->update($table, array(
                    'opening_packs' => $correct_opening,
                    'closing_packs' => $correct_closing
                ), array('id' => $record->id));
                $fixed++;
            }
        }
        
        return $fixed;
    }
    
    /**
     * Get stock inventory history
     */
    public static function get_history($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_stock_inventory';
        $products_table = $wpdb->prefix . 'stand120_products';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $sql = "SELECT si.*, p.name as product_name, p.type as product_type, s.full_name as staff_name
                FROM $table si
                LEFT JOIN $products_table p ON si.product_id = p.id
                LEFT JOIN $staff_table s ON si.staff_id = s.id
                WHERE 1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND si.stock_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND si.stock_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['product_id'])) {
            $sql .= " AND si.product_id = %d";
            $params[] = $filters['product_id'];
        }
        
        $sql .= " ORDER BY si.stock_date DESC, p.name ASC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = str_replace("SELECT si.*, p.name as product_name, p.type as product_type, s.full_name as staff_name", "SELECT COUNT(*)", $sql);
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