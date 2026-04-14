<?php
/**
 * Reconciliation Class
 * Handles daily record reconciliation by two admins.
 *
 * Business rules:
 * - Only admins can submit reconciliations.
 * - Each admin can submit once per date (update if re-submitted).
 * - A date is "fully reconciled" when 2 different admins have submitted.
 * - Once fully reconciled, no further submissions are allowed for that date.
 *
 * Self-healing:
 * - Automatically repairs stale unique keys on the table before every submit.
 * - Cleans up corrupted 0000-00-00 rows.
 * - Creates the table if it doesn't exist.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Reconciliation {

    /** @var bool Whether ensure_table() has already run this request. */
    private static $table_checked = false;

    /**
     * Ensure the reconciliation table exists with the correct schema.
     * Runs once per PHP request. Repairs stale unique keys, cleans corrupted rows,
     * and creates the table from scratch if it's missing.
     */
    private static function ensure_table() {
        if (self::$table_checked) {
            return;
        }
        self::$table_checked = true;

        global $wpdb;
        $table = $wpdb->prefix . 'stand120_reconciliation';

        // If table doesn't exist at all, create it
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE $table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                reconcile_date date NOT NULL,
                staff_id mediumint(9) NOT NULL,
                remark text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY date_staff (reconcile_date, staff_id),
                KEY reconcile_date (reconcile_date)
            ) $charset_collate");
            return; // Fresh table, no repair needed
        }

        // Clean up any corrupted rows with 0000-00-00 date
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE reconcile_date = %s",
            '0000-00-00'
        ));

        // Get all indexes on the table
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table");
        if (!$indexes) {
            return;
        }

        // Build a map of key name → info
        $keys = array();
        foreach ($indexes as $idx) {
            $kn = $idx->Key_name;
            if ($kn === 'PRIMARY') {
                continue;
            }
            if (!isset($keys[$kn])) {
                $keys[$kn] = array(
                    'unique'  => !$idx->Non_unique,
                    'columns' => array(),
                );
            }
            $keys[$kn]['columns'][] = $idx->Column_name;
        }

        // Drop ANY unique key that is single-column on reconcile_date
        // (this is the stale key that blocks the 2-admin design)
        foreach ($keys as $kn => $info) {
            if ($info['unique']
                && count($info['columns']) === 1
                && $info['columns'][0] === 'reconcile_date'
            ) {
                $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $kn);
                $wpdb->query("ALTER TABLE $table DROP INDEX `$safe`");
            }
        }

        // Ensure the correct composite unique key exists
        if (!isset($keys['date_staff'])) {
            // Check again after dropping — the name might have been reused
            $check = $wpdb->get_results(
                "SHOW INDEX FROM $table WHERE Key_name = 'date_staff'"
            );
            if (empty($check)) {
                $wpdb->query(
                    "ALTER TABLE $table ADD UNIQUE KEY date_staff (reconcile_date, staff_id)"
                );
            }
        }
    }

    /**
     * Validate a date string is a real YYYY-MM-DD date.
     *
     * @param string $date The date string to validate.
     * @return bool True if valid, false otherwise.
     */
    private static function is_valid_date($date) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Submit a reconciliation for a date.
     *
     * @param array $data Expected keys: 'date' (YYYY-MM-DD), 'remark' (optional string).
     * @return array Result with 'success', 'message', and optionally 'is_complete'.
     */
    public static function submit($data) {
        global $wpdb;

        // Self-heal: repair table schema before any DB operations
        self::ensure_table();

        $table    = $wpdb->prefix . 'stand120_reconciliation';
        $date     = sanitize_text_field($data['date'] ?? '');
        $remark   = sanitize_text_field($data['remark'] ?? '');
        $staff_id = Stand120_Auth::get_current_staff_id();

        // --- Validation ---
        if (empty($date)) {
            return array('success' => false, 'message' => 'Date is required');
        }

        if (!self::is_valid_date($date)) {
            return array(
                'success' => false,
                'message' => 'Invalid date format. Expected YYYY-MM-DD, got: ' . $date,
            );
        }

        if (!Stand120_Auth::is_admin()) {
            return array('success' => false, 'message' => 'Only admins can reconcile records');
        }

        if (empty($staff_id)) {
            return array(
                'success' => false,
                'message' => 'Could not identify staff member. Please log out and log in again.',
            );
        }

        // --- Check existing state ---
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE reconcile_date = %s AND staff_id = %d",
            $date,
            $staff_id
        ));

        if ($existing) {
            // This admin already submitted for this date — update the remark
            $ok = $wpdb->update(
                $table,
                array(
                    'remark'     => $remark,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $existing->id),
                array('%s', '%s'),
                array('%d')
            );

            if ($ok === false) {
                return array(
                    'success' => false,
                    'message' => 'Failed to update reconciliation: ' . $wpdb->last_error,
                );
            }

            Stand120_Database::log_activity(
                'update_reconciliation',
                'stand120_reconciliation',
                $existing->id
            );

            return array('success' => true, 'message' => 'Reconciliation updated');
        }

        // How many distinct admins have already reconciled this date?
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE reconcile_date = %s",
            $date
        ));

        if ($count >= 2) {
            return array(
                'success' => false,
                'message' => 'This date has already been fully reconciled by 2 admins',
            );
        }

        // --- Insert new reconciliation ---
        $ok = $wpdb->insert(
            $table,
            array(
                'reconcile_date' => $date,
                'staff_id'       => $staff_id,
                'remark'         => $remark,
                'created_at'     => current_time('mysql'),
                'updated_at'     => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );

        if ($ok === false) {
            // If insert failed, it might be a stale unique key we missed.
            // Force a full key repair and retry once.
            self::$table_checked = false;
            self::ensure_table();

            $ok = $wpdb->insert(
                $table,
                array(
                    'reconcile_date' => $date,
                    'staff_id'       => $staff_id,
                    'remark'         => $remark,
                    'created_at'     => current_time('mysql'),
                    'updated_at'     => current_time('mysql'),
                ),
                array('%s', '%d', '%s', '%s', '%s')
            );

            if ($ok === false) {
                return array(
                    'success' => false,
                    'message' => 'Failed to save reconciliation: ' . $wpdb->last_error,
                );
            }
        }

        Stand120_Database::log_activity(
            'submit_reconciliation',
            'stand120_reconciliation',
            $wpdb->insert_id
        );

        // Check if this completes the reconciliation (2 admins done)
        $new_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE reconcile_date = %s",
            $date
        ));

        $is_complete = ($new_count >= 2);

        return array(
            'success'     => true,
            'message'     => $is_complete
                ? 'Date fully reconciled by both admins'
                : 'Reconciliation submitted. Waiting for second admin.',
            'is_complete' => $is_complete,
        );
    }

    /**
     * Get reconciliation status for a date range.
     *
     * @param string $start_date YYYY-MM-DD
     * @param string $end_date   YYYY-MM-DD
     * @return array Keyed by date string; each value has 'date', 'reconciliations', 'is_complete'.
     */
    public static function get_status($start_date, $end_date) {
        global $wpdb;

        self::ensure_table();

        $table       = $wpdb->prefix . 'stand120_reconciliation';
        $staff_table = $wpdb->prefix . 'stand120_staff';

        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, s.full_name AS staff_name
             FROM $table r
             LEFT JOIN $staff_table s ON r.staff_id = s.id
             WHERE r.reconcile_date BETWEEN %s AND %s
             ORDER BY r.reconcile_date ASC, r.created_at ASC",
            $start_date,
            $end_date
        ));

        $dates = array();
        if ($records) {
            foreach ($records as $record) {
                $d = $record->reconcile_date;
                if (!isset($dates[$d])) {
                    $dates[$d] = array(
                        'date'            => $d,
                        'reconciliations' => array(),
                        'is_complete'     => false,
                    );
                }
                $dates[$d]['reconciliations'][] = array(
                    'id'         => $record->id,
                    'staff_id'   => $record->staff_id,
                    'staff_name' => $record->staff_name,
                    'remark'     => $record->remark,
                    'created_at' => $record->created_at,
                );
                if (count($dates[$d]['reconciliations']) >= 2) {
                    $dates[$d]['is_complete'] = true;
                }
            }
        }

        return $dates;
    }

    /**
     * Get reconciliation details for a specific date.
     *
     * @param string $date YYYY-MM-DD
     * @return array With 'date', 'reconciliations', 'count', 'is_complete', 'has_submitted'.
     */
    public static function get_for_date($date) {
        global $wpdb;

        self::ensure_table();

        $table       = $wpdb->prefix . 'stand120_reconciliation';
        $staff_table = $wpdb->prefix . 'stand120_staff';

        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, s.full_name AS staff_name
             FROM $table r
             LEFT JOIN $staff_table s ON r.staff_id = s.id
             WHERE r.reconcile_date = %s
             ORDER BY r.created_at ASC",
            $date
        ));

        $current_staff_id = Stand120_Auth::get_current_staff_id();
        $has_submitted    = false;
        $reconciliations  = array();

        if ($records) {
            foreach ($records as $record) {
                if ((int) $record->staff_id === (int) $current_staff_id) {
                    $has_submitted = true;
                }
                $reconciliations[] = array(
                    'id'         => $record->id,
                    'staff_id'   => $record->staff_id,
                    'staff_name' => $record->staff_name,
                    'remark'     => $record->remark,
                    'created_at' => $record->created_at,
                );
            }
        }

        return array(
            'date'            => $date,
            'reconciliations' => $reconciliations,
            'count'           => count($reconciliations),
            'is_complete'     => count($reconciliations) >= 2,
            'has_submitted'   => $has_submitted,
        );
    }
}