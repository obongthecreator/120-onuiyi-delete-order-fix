<?php
/**
 * Plugin Name: 120 Stand Inventory Management
 * Plugin URI: https://120stand.com
 * Description: A comprehensive inventory management system for 120 Stand fruit salad business with offline capability, real-time sync, and beautiful glassmorphism UI.
 * Version: 1.7.0
 * Author: 120 Stand
 * Author URI: https://120stand.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 120-stand-inventory
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Require PHP 7.0+ to avoid fatal errors that take down the entire site
if (version_compare(PHP_VERSION, '7.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>120 Stand Inventory</strong> requires PHP 7.0 or higher. Your server is running PHP ' . PHP_VERSION . '.</p></div>';
    });
    return; // Stop loading the plugin
}

// Define plugin constants
define('STAND120_VERSION', '1.7.0');
define('STAND120_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STAND120_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STAND120_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class Stand120_Inventory {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->include_files();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_stand120_action', array($this, 'handle_ajax'));
        add_action('wp_ajax_nopriv_stand120_action', array($this, 'handle_ajax'));
        
        // Add rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_pages'));
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        require_once STAND120_PLUGIN_DIR . 'includes/class-database.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-auth.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-take-order.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-order-preparation.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-stock-inventory.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-chopping-inventory.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-import-record.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-product-summary.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-financial-summary.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-expense-record.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-admin-panel.php';
        require_once STAND120_PLUGIN_DIR . 'includes/class-reconciliation.php';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        Stand120_Database::create_tables();
        Stand120_Database::insert_default_data();
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        
        // Create staff role
        add_role('stand120_staff', '120 Stand Staff', array(
            'read' => true,
            'stand120_access' => true
        ));
        
        // Add capability to admin
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('stand120_access');
            $admin->add_cap('stand120_admin');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('120-stand-inventory', false, dirname(STAND120_PLUGIN_BASENAME) . '/languages');
        
        // Auto-flush rewrite rules when plugin version changes
        $stored_version = get_option('stand120_plugin_version', '0');
        if ($stored_version !== STAND120_VERSION) {
            Stand120_Database::create_tables();
            flush_rewrite_rules();
            update_option('stand120_plugin_version', STAND120_VERSION);
        }
    }
    
    /**
     * Add rewrite rules for custom pages
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^120-stand/?$', 'index.php?stand120_page=home', 'top');
        add_rewrite_rule('^120-stand/login/?$', 'index.php?stand120_page=login', 'top');
        add_rewrite_rule('^120-stand/take-order/?$', 'index.php?stand120_page=take-order', 'top');
        add_rewrite_rule('^120-stand/take-order-history/?$', 'index.php?stand120_page=take-order-history', 'top');
        add_rewrite_rule('^120-stand/order-preparation/?$', 'index.php?stand120_page=order-preparation', 'top');
        add_rewrite_rule('^120-stand/order-preparation-history/?$', 'index.php?stand120_page=order-preparation-history', 'top');
        add_rewrite_rule('^120-stand/stock-inventory/?$', 'index.php?stand120_page=stock-inventory', 'top');
        add_rewrite_rule('^120-stand/stock-inventory-history/?$', 'index.php?stand120_page=stock-inventory-history', 'top');
        add_rewrite_rule('^120-stand/chopping-inventory/?$', 'index.php?stand120_page=chopping-inventory', 'top');
        add_rewrite_rule('^120-stand/chopping-inventory-history/?$', 'index.php?stand120_page=chopping-inventory-history', 'top');
        add_rewrite_rule('^120-stand/import-record/?$', 'index.php?stand120_page=import-record', 'top');
        add_rewrite_rule('^120-stand/import-record-history/?$', 'index.php?stand120_page=import-record-history', 'top');
        add_rewrite_rule('^120-stand/product-summary/?$', 'index.php?stand120_page=product-summary', 'top');
        add_rewrite_rule('^120-stand/financial-summary/?$', 'index.php?stand120_page=financial-summary', 'top');
        add_rewrite_rule('^120-stand/financial-summary-history/?$', 'index.php?stand120_page=financial-summary-history', 'top');
        add_rewrite_rule('^120-stand/expense-record/?$', 'index.php?stand120_page=expense-record', 'top');
        add_rewrite_rule('^120-stand/expense-history/?$', 'index.php?stand120_page=expense-history', 'top');
        add_rewrite_rule('^120-stand/admin-panel/?$', 'index.php?stand120_page=admin-panel', 'top');
        add_rewrite_rule('^120-stand/profile/?$', 'index.php?stand120_page=profile', 'top');
        add_rewrite_rule('^120-stand/analytics/?$', 'index.php?stand120_page=analytics', 'top');
        add_rewrite_rule('^120-stand/reconciliation/?$', 'index.php?stand120_page=reconciliation', 'top');
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'stand120_page';
        return $vars;
    }
    
    /**
     * Handle custom pages
     */
    public function handle_custom_pages() {
        $page = get_query_var('stand120_page');
        
        if (!$page) {
            return;
        }
        
        // Check if user needs to be logged in (except login page)
        if ($page !== 'login' && !Stand120_Auth::is_logged_in()) {
            wp_redirect(home_url('/120-stand/login/'));
            exit;
        }
        
        // Check admin access for admin panel
        if ($page === 'admin-panel' && !Stand120_Auth::is_admin()) {
            wp_redirect(home_url('/120-stand/'));
            exit;
        }
        
        // Check admin access for reconciliation
        if ($page === 'reconciliation' && !Stand120_Auth::is_admin()) {
            wp_redirect(home_url('/120-stand/'));
            exit;
        }
        
        $template_file= STAND120_PLUGIN_DIR . 'templates/' . $page . '.php';
        
        if (file_exists($template_file)) {
            // Prevent browser from caching plugin pages so users always get
            // fresh content without needing a hard refresh.
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            include $template_file;
            exit;
        }
    }
    
    /**
     * Enqueue scripts and styles
     *
     * Note: Custom page templates (header.php / footer.php / login.php) load
     * jQuery, CSS, stand120_ajax config, main.js and sw-register.js directly
     * via <script>/<link> tags.  We intentionally do NOT wp_enqueue them here
     * because doing so causes wp_footer() to output the same assets a second
     * time, which:
     *   1. Triggers const-redeclaration SyntaxErrors in main.js
     *   2. Loads WordPress's bundled jQuery which calls jQuery.noConflict(),
     *      removing the global $ that modules outside the IIFE rely on.
     */
    public function enqueue_scripts() {
        // Templates handle their own asset loading — nothing to enqueue.
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax() {
        Stand120_Ajax_Handler::handle();
    }
}

// Initialize plugin
function stand120_inventory() {
    return Stand120_Inventory::get_instance();
}

// Start the plugin
stand120_inventory();