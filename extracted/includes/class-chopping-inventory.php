<?php
/**
 * Chopping Inventory Class
 * Handles chopping/preparation inventory tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Chopping_Inventory {
    
    /**
     * Get the most recent previous day's closing value for a product.
     * Looks back up to 60 days to find the last record, not just yesterday.
     */
    private static function get_previous_closing($product_id, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_chopping_inventory';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT closing_whole FROM $table WHERE product_id = %d AND chop_date < %s ORDER BY chop_date DESC LIMIT 1",
            $product_id, $date
        ));
        
        return $record ? floatval($record->closing_whole) : 0;
    }
    
    /**
     * Save chopping inventory data
     */
    public static function save($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_chopping_inventory';
        
        $product_id = intval($data['product_id'] ?? 0);
        $date = sanitize_text_field($data['date'] ?? date('Y-m-d'));
        $prepared = floatval($data['prepared'] ?? 0);
        $packs_gotten = floatval($data['packs_gotten'] ?? 0);
        $remarks = sanitize_textarea_field($data['remarks'] ?? '');
        $staff_id = Stand120_Auth::get_current_staff_id();
        $is_admin = Stand120_Auth::is_admin();
        $admin_opening = isset($data['opening_whole']) && $is_admin ? floatval($data['opening_whole']) : null;
        
        if (!$product_id) {
            return array('success' => false, 'message' => 'Product ID required');
        }
        
        // Get existing record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND chop_date = %s",
            $product_id, $date
        ));
        
        // Get opening value
        $opening = 0;
        $import_whole = 0;
        
        if ($existing) {
            $opening = $existing->opening_whole;
            $import_whole = $existing->import_whole;
        } else {
            // Get most recent previous day's closing as today's opening
            $opening = self::get_previous_closing($product_id, $date);
            
            // Get import from import records
            $import_whole = self::get_import_whole($product_id, $date);
        }
        
        // If admin provided an explicit opening value, use it
        if ($admin_opening !== null) {
            $opening = $admin_opening;
        }
        
        // Calculate closing = opening + import - prepared
        $closing = $opening + $import_whole - $prepared;
        
        if ($existing) {
            // Update existing
            $update_data = array(
                'prepared_whole' => $prepared,
                'closing_whole' => $closing,
                'packs_gotten' => $packs_gotten,
                'remarks' => $remarks,
                'staff_id' => $staff_id
            );
            // Persist admin-provided opening value
            if ($admin_opening !== null) {
                $update_data['opening_whole'] = $opening;
            }
            $wpdb->update($table, $update_data, array('id' => $existing->id));
        } else {
            // Insert new
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'chop_date' => $date,
                'opening_whole' => $opening,
                'import_whole' => $import_whole,
                'prepared_whole' => $prepared,
                'closing_whole' => $closing,
                'packs_gotten' => $packs_gotten,
                'remarks' => $remarks,
                'staff_id' => $staff_id
            ));
        }
        
        // Always update stock inventory with packs gotten (even if 0, to keep in sync)
        Stand120_Stock_Inventory::update_added_from_import($product_id, $packs_gotten, $date);
        
        return array(
            'success' => true,
            'message' => 'Chopping inventory saved',
            'data' => array(
                'opening' => $opening,
                'import' => $import_whole,
                'prepared' => $prepared,
                'closing' => $closing,
                'packs_gotten' => $packs_gotten
            )
        );
    }
    
    /**
     * Get import whole from import records
     */
    private static function get_import_whole($product_id, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_import_records';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT quantity_imported FROM $table WHERE product_id = %d AND import_date = %s",
            $product_id, $date
        ));
        
        return $record ? floatval($record->quantity_imported) : 0;
    }
    
    /**
     * Update import whole from import records
     */
    public static function update_import_whole($product_id, $quantity, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_chopping_inventory';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND chop_date = %s",
            $product_id, $date
        ));
        
        if ($existing) {
            $closing = $existing->opening_whole + $quantity - $existing->prepared_whole;
            $wpdb->update($table, array(
                'import_whole' => $quantity,
                'closing_whole' => $closing
            ), array('id' => $existing->id));
        } else {
            // Get most recent previous day's closing
            $opening = self::get_previous_closing($product_id, $date);
            
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'chop_date' => $date,
                'opening_whole' => $opening,
                'import_whole' => $quantity,
                'prepared_whole' => 0,
                'closing_whole' => $opening + $quantity,
                'packs_gotten' => 0
            ));
        }
    }
    
    /**
     * Update opening value (admin only)
     */
    public static function update_opening($product_id, $value, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_chopping_inventory';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND chop_date = %s",
            $product_id, $date
        ));
        
        if ($existing) {
            $closing = $value + $existing->import_whole - $existing->prepared_whole;
            $wpdb->update($table, array(
                'opening_whole' => $value,
                'closing_whole' => $closing
            ), array('id' => $existing->id));
        } else {
            $wpdb->insert($table, array(
                'product_id' => $product_id,
                'chop_date' => $date,
                'opening_whole' => $value,
                'import_whole' => 0,
                'prepared_whole' => 0,
                'closing_whole' => $value,
                'packs_gotten' => 0
            ));
        }
        
        Stand120_Database::log_activity('update_opening', 'stand120_chopping_inventory', $product_id);
        
        return array('success' => true, 'message' => 'Opening value updated');
    }
    
    /**
     * Get chopping inventory for a date
     */
    public static function get_for_date($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_chopping_inventory';
        
        // Get all fruit products
        $fruits = Stand120_Database::get_fruits();
        
        $data = array();
        
        foreach ($fruits as $fruit) {
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND chop_date = %s",
                $fruit->id, $date
            ));
            
            if ($record) {
                $data[] = array(
                    'product_id' => $fruit->id,
                    'product_name' => $fruit->name,
                    'opening' => floatval($record->opening_whole),
                    'import' => floatval($record->import_whole),
                    'prepared' => floatval($record->prepared_whole),
                    'closing' => floatval($record->closing_whole),
                    'packs_gotten' => floatval($record->packs_gotten),
                    'remarks' => $record->remarks
                );
            } else {
                // Get most recent previous day's closing as opening
                $opening = self::get_previous_closing($fruit->id, $date);
                
                // Get import from import records
                $import = self::get_import_whole($fruit->id, $date);
                
                $data[] = array(
                    'product_id' => $fruit->id,
                    'product_name' => $fruit->name,
                    'opening' => $opening,
                    'import' => $import,
                    'prepared' => 0,
                    'closing' => $opening + $import,
                    'packs_gotten' => 0,
                    'remarks' => ''
                );
            }
        }
        
        return array('data' => $data, 'date' => $date);
    }
    
    /**
     * Self-heal chopping inventory records.
     * Ensures each record's opening_whole matches the previous day's closing_whole,
     * and recalculates closing_whole = opening + import - prepared.
     */
    public static function recalculate_records($date_from = null, $date_to = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_chopping_inventory';
        
        $sql = "SELECT * FROM $table WHERE 1=1";
        $params = array();
        
        if (!empty($date_from)) {
            $sql .= " AND chop_date >= %s";
            $params[] = $date_from;
        }
        if (!empty($date_to)) {
            $sql .= " AND chop_date <= %s";
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY chop_date ASC, product_id ASC";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $records = $wpdb->get_results($sql);
        }
        
        $fixed = 0;
        
        foreach ($records as $record) {
            // Find the most recent previous closing for this product
            $prev = $wpdb->get_row($wpdb->prepare(
                "SELECT closing_whole FROM $table WHERE product_id = %d AND chop_date < %s ORDER BY chop_date DESC LIMIT 1",
                $record->product_id, $record->chop_date
            ));
            $correct_opening = $prev ? floatval($prev->closing_whole) : 0;
            
            $current_opening = floatval($record->opening_whole);
            $import = floatval($record->import_whole);
            $prepared = floatval($record->prepared_whole);
            $correct_closing = $correct_opening + $import - $prepared;
            $current_closing = floatval($record->closing_whole);
            
            $opening_wrong = abs($current_opening - $correct_opening) > 0.01;
            $closing_wrong = abs($current_closing - $correct_closing) > 0.01;
            
            if ($opening_wrong || $closing_wrong) {
                $wpdb->update($table, array(
                    'opening_whole' => $correct_opening,
                    'closing_whole' => $correct_closing
                ), array('id' => $record->id));
                $fixed++;
            }
        }
        
        return $fixed;
    }
    
    /**
     * Get chopping inventory history
     */
    public static function get_history($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_chopping_inventory';
        $products_table = $wpdb->prefix . 'stand120_products';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $sql = "SELECT ci.*, p.name as product_name, s.full_name as staff_name
                FROM $table ci
                LEFT JOIN $products_table p ON ci.product_id = p.id
                LEFT JOIN $staff_table s ON ci.staff_id = s.id
                WHERE 1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND ci.chop_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND ci.chop_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['product_id'])) {
            $sql .= " AND ci.product_id = %d";
            $params[] = $filters['product_id'];
        }
        
        $sql .= " ORDER BY ci.chop_date DESC, p.name ASC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = str_replace("SELECT ci.*, p.name as product_name, s.full_name as staff_name", "SELECT COUNT(*)", $sql);
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