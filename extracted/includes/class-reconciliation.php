<?php
/**
 * Reconciliation Class — rebuilt from scratch.
 *
 * Handles daily record reconciliation by two admins.
 *
 * Business rules
 * ─────────────
 * • Only admins can submit reconciliations.
 * • Each admin can submit once per date (update on re-submit).
 * • A date is "fully reconciled" when 2 different admins have submitted.
 * • Once fully reconciled no further submissions are allowed.
 *
 * Reliability
 * ───────────
 * • Nuclear table repair on first request of every PHP process:
 *     – creates the table if missing
 *     – cleans corrupted 0000-00-00 / NULL rows
 *     – removes duplicate (date, staff) rows keeping the newest
 *     – drops ALL non-PRIMARY indexes then recreates only the correct ones
 * • Uses INSERT … ON DUPLICATE KEY UPDATE for atomic upsert so the insert
 *   cannot fail even if a stale unique key slipped past repair.
 * • Falls back to manual check-then-insert if the upsert returns false.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Reconciliation {

    /** @var bool Whether repair() has already run this request. */
    private static $repaired = false;

    /* ──────────────────────────────────────────────
     *  Table helpers
     * ────────────────────────────────────────────── */

    /**
     * Return the full table name (with WP prefix).
     */
    private static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'stand120_reconciliation';
    }

    /**
     * Nuclear table repair — runs once per PHP request.
     *
     * 1. Creates the table when it is missing.
     * 2. Deletes corrupted rows (0000-00-00, NULL dates).
     * 3. Deduplicates (date, staff_id) rows — keeps highest id.
     * 4. Drops EVERY non-PRIMARY index.
     * 5. Re-adds only the correct composite UNIQUE KEY and plain KEY.
     */
    private static function repair() {
        if (self::$repaired) {
            return;
        }
        self::$repaired = true;

        global $wpdb;
        $table = self::table_name();

        // Suppress DB errors so ALTER / DROP don't trigger PHP notices.
        $suppress = $wpdb->suppress_errors(true);

        // ── Step 1: Create if missing ──────────────────────────────────
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            $charset = $wpdb->get_charset_collate();
            $wpdb->query(
                "CREATE TABLE {$table} (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    reconcile_date date NOT NULL,
                    staff_id mediumint(9) NOT NULL,
                    remark text,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY date_staff (reconcile_date, staff_id),
                    KEY reconcile_date (reconcile_date)
                ) {$charset}"
            );
            $wpdb->suppress_errors($suppress);
            return;
        }

        // ── Step 2: Clean corrupted rows ───────────────────────────────
        $wpdb->query(
            "DELETE FROM {$table} WHERE reconcile_date IS NULL OR reconcile_date = '0000-00-00'"
        );

        // ── Step 3: Remove duplicates (keep highest id) ────────────────
        $wpdb->query(
            "DELETE t1 FROM {$table} t1
             INNER JOIN {$table} t2
             WHERE t1.reconcile_date = t2.reconcile_date
               AND t1.staff_id = t2.staff_id
               AND t1.id < t2.id"
        );

        // ── Step 4: Drop ALL non-PRIMARY indexes ───────────────────────
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}");
        if ($indexes) {
            $dropped = array();
            foreach ($indexes as $idx) {
                $name = $idx->Key_name;
                if ('PRIMARY' === $name || in_array($name, $dropped, true)) {
                    continue;
                }
                $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
                if (!empty($safe)) {
                    $wpdb->query("ALTER TABLE {$table} DROP INDEX `{$safe}`");
                    $dropped[] = $name;
                }
            }
        }

        // ── Step 5: Re-add correct indexes ─────────────────────────────
        $wpdb->query(
            "ALTER TABLE {$table} ADD UNIQUE KEY date_staff (reconcile_date, staff_id)"
        );
        $wpdb->query(
            "ALTER TABLE {$table} ADD KEY reconcile_date (reconcile_date)"
        );

        $wpdb->suppress_errors($suppress);
    }

    /* ──────────────────────────────────────────────
     *  Validation
     * ────────────────────────────────────────────── */

    /**
     * Validate a YYYY-MM-DD string is a real calendar date.
     */
    private static function is_valid_date($date) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /* ──────────────────────────────────────────────
     *  Public API
     * ────────────────────────────────────────────── */

    /**
     * Submit (or update) a reconciliation for a date.
     *
     * @param  array $data  Keys: date (YYYY-MM-DD), remark (optional).
     * @return array        Keys: success, message, is_complete.
     */
    public static function submit($data) {
        global $wpdb;
        self::repair();

        $table    = self::table_name();
        $date     = sanitize_text_field($data['date'] ?? '');
        $remark   = sanitize_text_field($data['remark'] ?? '');
        $staff_id = Stand120_Auth::get_current_staff_id();

        // ── Validation ─────────────────────────────────────────────────
        if (empty($date)) {
            return array('success' => false, 'message' => 'Date is required.');
        }
        if (!self::is_valid_date($date)) {
            return array('success' => false, 'message' => 'Invalid date format.');
        }
        if (!Stand120_Auth::is_admin()) {
            return array('success' => false, 'message' => 'Only admins can reconcile records.');
        }
        if (empty($staff_id)) {
            return array(
                'success' => false,
                'message' => 'Staff member not identified. Please log out and log in again.',
            );
        }

        // ── Does this admin already have a row for this date? ──────────
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE reconcile_date = %s AND staff_id = %d LIMIT 1",
            $date,
            $staff_id
        ));

        $now = current_time('mysql');

        if ($existing_id) {
            // Update existing record.
            $ok = $wpdb->update(
                $table,
                array('remark' => $remark, 'updated_at' => $now),
                array('id' => $existing_id),
                array('%s', '%s'),
                array('%d')
            );
            if (false === $ok) {
                return array('success' => false, 'message' => 'Update failed: ' . $wpdb->last_error);
            }

            Stand120_Database::log_activity('update_reconciliation', 'stand120_reconciliation', (int) $existing_id);

            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT staff_id) FROM {$table} WHERE reconcile_date = %s",
                $date
            ));
            return array(
                'success'     => true,
                'message'     => 'Reconciliation updated successfully.',
                'is_complete' => $total >= 2,
            );
        }

        // ── Is the date already fully reconciled? ──────────────────────
        $admin_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT staff_id) FROM {$table} WHERE reconcile_date = %s",
            $date
        ));
        if ($admin_count >= 2) {
            return array(
                'success' => false,
                'message' => 'This date is already fully reconciled by 2 admins.',
            );
        }

        // ── Insert — atomic upsert (bulletproof against stale keys) ────
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (reconcile_date, staff_id, remark, created_at, updated_at)
             VALUES (%s, %d, %s, %s, %s)
             ON DUPLICATE KEY UPDATE remark = VALUES(remark), updated_at = VALUES(updated_at)",
            $date,
            $staff_id,
            $remark,
            $now,
            $now
        ));

        if (false === $result) {
            // Last resort: force a second repair pass and try a plain insert.
            self::$repaired = false;
            self::repair();

            $result = $wpdb->insert(
                $table,
                array(
                    'reconcile_date' => $date,
                    'staff_id'       => $staff_id,
                    'remark'         => $remark,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ),
                array('%s', '%d', '%s', '%s', '%s')
            );
            if (false === $result) {
                return array('success' => false, 'message' => 'Save failed: ' . $wpdb->last_error);
            }
        }

        $insert_id = $wpdb->insert_id;
        if ($insert_id) {
            Stand120_Database::log_activity('submit_reconciliation', 'stand120_reconciliation', $insert_id);
        }

        // ── Check completion ───────────────────────────────────────────
        $new_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT staff_id) FROM {$table} WHERE reconcile_date = %s",
            $date
        ));
        $is_complete = $new_count >= 2;

        return array(
            'success'     => true,
            'message'     => $is_complete
                ? 'Date fully reconciled by both admins!'
                : 'Reconciliation submitted. Waiting for second admin.',
            'is_complete' => $is_complete,
        );
    }

    /**
     * Get reconciliation status for a date range (calendar view).
     *
     * @param  string $start_date YYYY-MM-DD
     * @param  string $end_date   YYYY-MM-DD
     * @return array  Keyed by date → { date, reconciliations[], is_complete }
     */
    public static function get_status($start_date, $end_date) {
        global $wpdb;
        self::repair();

        $table       = self::table_name();
        $staff_table = $wpdb->prefix . 'stand120_staff';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, s.full_name AS staff_name
             FROM {$table} r
             LEFT JOIN {$staff_table} s ON r.staff_id = s.id
             WHERE r.reconcile_date BETWEEN %s AND %s
             ORDER BY r.reconcile_date ASC, r.created_at ASC",
            $start_date,
            $end_date
        ));

        $dates = array();
        if ($rows) {
            foreach ($rows as $row) {
                $d = $row->reconcile_date;
                if (!isset($dates[$d])) {
                    $dates[$d] = array(
                        'date'            => $d,
                        'reconciliations' => array(),
                        'is_complete'     => false,
                    );
                }
                $dates[$d]['reconciliations'][] = array(
                    'id'         => $row->id,
                    'staff_id'   => $row->staff_id,
                    'staff_name' => $row->staff_name ? $row->staff_name : 'Admin',
                    'remark'     => $row->remark,
                    'created_at' => $row->created_at,
                );
                if (count($dates[$d]['reconciliations']) >= 2) {
                    $dates[$d]['is_complete'] = true;
                }
            }
        }

        return $dates;
    }

    /**
     * Get reconciliation details for one date.
     *
     * @param  string $date YYYY-MM-DD
     * @return array  { date, reconciliations[], count, is_complete, has_submitted }
     */
    public static function get_for_date($date) {
        global $wpdb;
        self::repair();

        $table       = self::table_name();
        $staff_table = $wpdb->prefix . 'stand120_staff';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, s.full_name AS staff_name
             FROM {$table} r
             LEFT JOIN {$staff_table} s ON r.staff_id = s.id
             WHERE r.reconcile_date = %s
             ORDER BY r.created_at ASC",
            $date
        ));

        $current_staff    = Stand120_Auth::get_current_staff_id();
        $has_submitted    = false;
        $reconciliations  = array();

        if ($rows) {
            foreach ($rows as $row) {
                if ((int) $row->staff_id === (int) $current_staff) {
                    $has_submitted = true;
                }
                $reconciliations[] = array(
                    'id'         => $row->id,
                    'staff_id'   => $row->staff_id,
                    'staff_name' => $row->staff_name ? $row->staff_name : 'Admin',
                    'remark'     => $row->remark,
                    'created_at' => $row->created_at,
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

    /**
     * Get reconciliation history (paginated, newest first).
     *
     * @param  int $page     1-based page number.
     * @param  int $per_page Rows per page.
     * @return array { records[], total, page, per_page, total_pages }
     */
    public static function get_history($page = 1, $per_page = 50) {
        global $wpdb;
        self::repair();

        $table       = self::table_name();
        $staff_table = $wpdb->prefix . 'stand120_staff';

        $page     = max(1, (int) $page);
        $per_page = max(1, min(100, (int) $per_page));
        $offset   = ($page - 1) * $per_page;

        // Total distinct dates that have any reconciliation record.
        $total = (int) $wpdb->get_var("SELECT COUNT(DISTINCT reconcile_date) FROM {$table}");

        // Paginated list of distinct dates (newest first).
        $date_list = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT reconcile_date FROM {$table}
             ORDER BY reconcile_date DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $records = array();
        if ($date_list) {
            // Build safe IN clause.
            $placeholders = implode(',', array_fill(0, count($date_list), '%s'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, s.full_name AS staff_name
                     FROM {$table} r
                     LEFT JOIN {$staff_table} s ON r.staff_id = s.id
                     WHERE r.reconcile_date IN ({$placeholders})
                     ORDER BY r.reconcile_date DESC, r.created_at ASC",
                    $date_list
                )
            );

            if ($rows) {
                foreach ($rows as $row) {
                    $d = $row->reconcile_date;
                    if (!isset($records[$d])) {
                        $records[$d] = array(
                            'date'            => $d,
                            'reconciliations' => array(),
                            'is_complete'     => false,
                        );
                    }
                    $records[$d]['reconciliations'][] = array(
                        'id'         => $row->id,
                        'staff_id'   => $row->staff_id,
                        'staff_name' => $row->staff_name ? $row->staff_name : 'Admin',
                        'remark'     => $row->remark,
                        'created_at' => $row->created_at,
                    );
                    if (count($records[$d]['reconciliations']) >= 2) {
                        $records[$d]['is_complete'] = true;
                    }
                }
            }
        }

        return array(
            'records'     => array_values($records),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        );
    }
}