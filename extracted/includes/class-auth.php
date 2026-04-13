<?php
/**
 * Authentication Class
 * Handles user authentication and authorization
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Auth {
    
    /**
     * Check if user is logged in
     */
    public static function is_logged_in() {
        return is_user_logged_in() && self::has_access();
    }
    
    /**
     * Check if user has access to the inventory system
     */
    public static function has_access() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        
        // Allow WordPress administrators always
        if (in_array('administrator', (array) $user->roles)) {
            return true;
        }
        
        // Allow stand120_staff role
        if (in_array('stand120_staff', (array) $user->roles)) {
            return true;
        }
        
        // Allow users with stand120_access capability
        if ($user->has_cap('stand120_access')) {
            return true;
        }
        
        // Allow any logged-in user - this makes the system more flexible
        // The login function adds the capability to new users
        return true;
    }
    
    /**
     * Check if user is a super admin (WordPress multisite super admin or single-site admin)
     */
    public static function is_super_admin() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // In multisite, use WordPress's built-in check
        if (function_exists('is_super_admin') && is_super_admin()) {
            return true;
        }
        
        // In single site, treat administrators as super admins
        $user = wp_get_current_user();
        return in_array('administrator', (array) $user->roles);
    }
    
    /**
     * Check if user is admin
     */
    public static function is_admin() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        
        // Allow WordPress administrators always
        if (in_array('administrator', (array) $user->roles)) {
            return true;
        }
        
        return $user->has_cap('stand120_admin');
    }
    
    /**
     * Get current staff ID
     */
    public static function get_current_staff_id() {
        if (!is_user_logged_in()) {
            return 0;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_staff';
        $user_id = get_current_user_id();
        
        // First try to get any existing staff record (active or inactive)
        $staff = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if ($staff) {
            // If staff exists but is inactive, reactivate it
            if ($staff->status !== 'active') {
                $wpdb->update($table, array('status' => 'active'), array('id' => $staff->id));
            }
            return $staff->id;
        }
        
        // Auto-create staff record for any logged-in user
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', (array) $user->roles);
        
        $result = $wpdb->insert($table, array(
            'user_id' => $user_id,
            'full_name' => $user->display_name ?: $user->user_login,
            'role' => $is_admin ? 'admin' : 'staff',
            'status' => 'active'
        ));
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return 0;
    }
    
    /**
     * Get current user data
     */
    public static function get_current_user_data() {
        if (!is_user_logged_in()) {
            return null;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_staff';
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $staff = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        return array(
            'user_id' => $user_id,
            'staff_id' => $staff ? $staff->id : 0,
            'username' => $user->user_login,
            'display_name' => $staff ? $staff->full_name : $user->display_name,
            'email' => $user->user_email,
            'role' => $staff ? $staff->role : (current_user_can('administrator') ? 'admin' : 'staff'),
            'is_admin' => self::is_admin()
        );
    }
    
    /**
     * Resolve a login identifier to a WordPress user object.
     * Tries: user_login (exact), email, user_login (case-insensitive), display_name.
     */
    private static function resolve_user($identifier) {
        // 1. Exact user_login match
        $user = get_user_by('login', $identifier);
        if ($user) {
            return $user;
        }
        
        // 2. Email match
        if (is_email($identifier)) {
            $user = get_user_by('email', $identifier);
            if ($user) {
                return $user;
            }
        }
        
        // 3. Case-insensitive user_login lookup via DB
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE LOWER(user_login) = LOWER(%s) LIMIT 1",
            $identifier
        ));
        if ($row) {
            return get_user_by('id', $row->ID);
        }
        
        // 4. Display name match (some users try their display name)
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE display_name = %s LIMIT 1",
            $identifier
        ));
        if ($row) {
            return get_user_by('id', $row->ID);
        }
        
        return null;
    }
    
    /**
     * Login user
     * Uses wp_authenticate() + wp_set_auth_cookie() instead of wp_signon()
     * to avoid cookie-setting issues in AJAX contexts.
     */
    public static function login($username, $password) {
        // Resolve the identifier to a real WP user object
        $resolved_user = self::resolve_user($username);
        
        if (!$resolved_user) {
            return array(
                'success' => false,
                'message' => 'No account found with that username or email.'
            );
        }
        
        // Authenticate using the resolved user_login (wp_authenticate needs the exact login)
        $user = wp_authenticate($resolved_user->user_login, $password);
        
        if (is_wp_error($user)) {
            // Provide a clearer message
            $error_code = $user->get_error_code();
            if ($error_code === 'incorrect_password') {
                return array(
                    'success' => false,
                    'message' => 'Incorrect password. Please try again.'
                );
            }
            return array(
                'success' => false,
                'message' => $user->get_error_message()
            );
        }
        
        // Set the auth cookie manually — this is the fix for AJAX login failures.
        // wp_signon() can fail to set cookies when headers are already sent in AJAX.
        wp_set_auth_cookie($user->ID, true, is_ssl());
        wp_set_current_user($user->ID);
        
        // Ensure user has access capability
        $user_roles = (array) $user->roles;
        $is_admin = in_array('administrator', $user_roles);
        $is_staff_role = in_array('stand120_staff', $user_roles);
        $has_access_cap = $user->has_cap('stand120_access');
        
        if (!$is_admin && !$is_staff_role && !$has_access_cap) {
            $user->add_cap('stand120_access');
        }
        
        // Log activity
        Stand120_Database::log_activity('login');
        
        return array(
            'success' => true,
            'message' => 'Login successful',
            'user' => self::get_current_user_data()
        );
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        Stand120_Database::log_activity('logout');
        wp_logout();
        
        return array(
            'success' => true,
            'message' => 'Logout successful'
        );
    }
    
    /**
     * Create staff user
     */
    public static function create_staff($data) {
        if (!self::is_admin()) {
            return array(
                'success' => false,
                'message' => 'Unauthorized'
            );
        }
        
        // Create WordPress user
        $user_id = wp_create_user(
            sanitize_user($data['username']),
            $data['password'],
            sanitize_email($data['email'])
        );
        
        if (is_wp_error($user_id)) {
            return array(
                'success' => false,
                'message' => $user_id->get_error_message()
            );
        }
        
        // Add role and capability
        $user = new WP_User($user_id);
        $user->set_role('stand120_staff');
        
        // Create staff record
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_staff';
        
        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'full_name' => sanitize_text_field($data['full_name']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'role' => 'staff'
        ));
        
        Stand120_Database::log_activity('create_staff', 'stand120_staff', $wpdb->insert_id);
        
        return array(
            'success' => true,
            'message' => 'Staff created successfully',
            'staff_id' => $wpdb->insert_id
        );
    }
    
    /**
     * Update staff user
     */
    public static function update_staff($staff_id, $data) {
        if (!self::is_admin()) {
            return array(
                'success' => false,
                'message' => 'Unauthorized'
            );
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_staff';
        
        $update_data = array();
        
        if (isset($data['full_name'])) {
            $update_data['full_name'] = sanitize_text_field($data['full_name']);
        }
        if (isset($data['phone'])) {
            $update_data['phone'] = sanitize_text_field($data['phone']);
        }
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        if (!empty($update_data)) {
            $wpdb->update($table, $update_data, array('id' => $staff_id));
        }
        
        // Update password if provided
        if (!empty($data['password'])) {
            $staff = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $staff_id));
            if ($staff) {
                wp_set_password($data['password'], $staff->user_id);
            }
        }
        
        Stand120_Database::log_activity('update_staff', 'stand120_staff', $staff_id);
        
        return array(
            'success' => true,
            'message' => 'Staff updated successfully'
        );
    }
    
    /**
     * Get all staff
     */
    public static function get_all_staff() {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_staff';
        
        return $wpdb->get_results("SELECT * FROM $table WHERE status = 'active' ORDER BY full_name ASC");
    }
    
    /**
     * Get staff by ID
     */
    public static function get_staff($staff_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_staff';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $staff_id));
    }
    
    /**
     * Delete staff (soft delete)
     */
    public static function delete_staff($staff_id) {
        if (!self::is_admin()) {
            return array(
                'success' => false,
                'message' => 'Unauthorized'
            );
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_staff';
        
        $wpdb->update($table, array('status' => 'inactive'), array('id' => $staff_id));
        
        Stand120_Database::log_activity('delete_staff', 'stand120_staff', $staff_id);
        
        return array(
            'success' => true,
            'message' => 'Staff deleted successfully'
        );
    }
}