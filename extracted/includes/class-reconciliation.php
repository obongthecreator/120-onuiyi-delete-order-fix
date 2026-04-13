<?php
/**
 * Reconciliation Class
 * Handles daily record reconciliation by two admins
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Reconciliation {
    
    /**
     * Submit a reconciliation for a date
     */
    public static function submit($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_reconciliation';
        
        $date = sanitize_text_field($data['date'] ?? '');
        $remark = sanitize_text_field($data['remark'] ?? '');
        $staff_id = Stand120_Auth::get_current_staff_id();
        
        if (empty($date)) {
            return array('success' => false, 'message' => 'Date is required');
        }
        
        if (!Stand120_Auth::is_admin()) {
            return array('success' => false, 'message' => 'Only admins can reconcile records');
        }
        
        // Check if this admin already reconciled this date
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE reconcile_date = %s AND staff_id = %d",
            $date, $staff_id
        ));
        
        if ($existing) {
            // Update existing reconciliation
            $wpdb->update($table, array(
                'remark' => $remark,
                'updated_at' => current_time('mysql')
            ), array('id' => $existing->id));
            
            Stand120_Database::log_activity('update_reconciliation', 'stand120_reconciliation', $existing->id);
            
            return array('success' => true, 'message' => 'Reconciliation updated');
        }
        
        // Check how many admins already reconciled this date
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE reconcile_date = %s",
            $date
        ));
        
        if ($count >= 2) {
            return array('success' => false, 'message' => 'This date has already been fully reconciled by 2 admins');
        }
        
        // Insert new reconciliation
        $result = $wpdb->insert($table, array(
            'reconcile_date' => $date,
            'staff_id' => $staff_id,
            'remark' => $remark,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
        
        if ($result === false) {
            return array('success' => false, 'message' => 'Failed to save reconciliation');
        }
        
        Stand120_Database::log_activity('submit_reconciliation', 'stand120_reconciliation', $wpdb->insert_id);
        
        // Check if this completes the reconciliation (2 admins done)
        $new_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE reconcile_date = %s",
            $date
        ));
        
        $is_complete = ($new_count >= 2);
        
        return array(
            'success' => true,
            'message' => $is_complete ? 'Date fully reconciled by both admins' : 'Reconciliation submitted. Waiting for second admin.',
            'is_complete' => $is_complete
        );
    }
    
    /**
     * Get reconciliation status for a date range
     */
    public static function get_status($start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_reconciliation';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, s.full_name as staff_name 
             FROM $table r 
             LEFT JOIN $staff_table s ON r.staff_id = s.id 
             WHERE r.reconcile_date BETWEEN %s AND %s 
             ORDER BY r.reconcile_date ASC, r.created_at ASC",
            $start_date, $end_date
        ));
        
        // Group by date
        $dates = array();
        foreach ($records as $record) {
            $d = $record->reconcile_date;
            if (!isset($dates[$d])) {
                $dates[$d] = array(
                    'date' => $d,
                    'reconciliations' => array(),
                    'is_complete' => false
                );
            }
            $dates[$d]['reconciliations'][] = array(
                'id' => $record->id,
                'staff_id' => $record->staff_id,
                'staff_name' => $record->staff_name,
                'remark' => $record->remark,
                'created_at' => $record->created_at
            );
            if (count($dates[$d]['reconciliations']) >= 2) {
                $dates[$d]['is_complete'] = true;
            }
        }
        
        return $dates;
    }
    
    /**
     * Get reconciliation for a specific date
     */
    public static function get_for_date($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_reconciliation';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, s.full_name as staff_name 
             FROM $table r 
             LEFT JOIN $staff_table s ON r.staff_id = s.id 
             WHERE r.reconcile_date = %s 
             ORDER BY r.created_at ASC",
            $date
        ));
        
        $current_staff_id = Stand120_Auth::get_current_staff_id();
        $has_submitted = false;
        $reconciliations = array();
        
        foreach ($records as $record) {
            if ((int)$record->staff_id === (int)$current_staff_id) {
                $has_submitted = true;
            }
            $reconciliations[] = array(
                'id' => $record->id,
                'staff_id' => $record->staff_id,
                'staff_name' => $record->staff_name,
                'remark' => $record->remark,
                'created_at' => $record->created_at
            );
        }
        
        return array(
            'date' => $date,
            'reconciliations' => $reconciliations,
            'count' => count($reconciliations),
            'is_complete' => count($reconciliations) >= 2,
            'has_submitted' => $has_submitted
        );
    }
}