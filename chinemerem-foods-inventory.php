<?php
/**
 * Plugin Name: Chinemerem Foods Inventory Management
 * Plugin URI: https://chinemeremfoods.com
 * Description: A comprehensive inventory management system for Chinemerem Foods with glassmorphism UI, offline support, and real-time calculations.
 * Version: 1.0.0
 * Author: BendlessTech
 * Author URI: https://bendlestech.com
 * Text Domain: chinemerem-foods
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CFI_VERSION', '1.0.0');
define('CFI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CFI_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
final class Chinemerem_Foods_Inventory {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-database.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-auth.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-ajax.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-pages.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-products.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-orders.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-stock.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-debtors.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-expenses.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-imports.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-packing.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-financial.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-reconciliation.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-backup.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-shortcodes.php';
        require_once CFI_PLUGIN_DIR . 'includes/class-cfi-admin.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Init actions
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Performance: Add resource hints for faster loading
        add_action('wp_head', array($this, 'add_resource_hints'), 1);
        
        // Performance: Add preload directives
        add_filter('wp_resource_hints', array($this, 'resource_hints'), 10, 2);
        
        // Template redirect for login check
        add_action('template_redirect', array($this, 'check_authentication'));
        
        // Daily cron for reset and backup
        add_action('cfi_daily_reset', array($this, 'daily_reset'));
        add_action('cfi_daily_backup', array($this, 'daily_backup'));
        
        // Add custom user role
        add_action('init', array($this, 'add_custom_roles'));
    }
    
    /**
     * Add resource hints for faster external resource loading
     */
    public function add_resource_hints() {
        // Force desktop viewport width for mobile devices - auto-scales to fit screen
        echo '<meta name="viewport" content="width=1200, initial-scale=0.333, maximum-scale=1, user-scalable=yes">' . "\n";
        
        // PWA manifest
        echo '<link rel="manifest" href="' . esc_url(CFI_PLUGIN_URL . 'assets/manifest.json') . '">' . "\n";
        echo '<meta name="theme-color" content="#001943">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="CFI">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url(CFI_PLUGIN_URL . 'assets/images/icon-192x192.png') . '">' . "\n";
        
        echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
        echo '<link rel="dns-prefetch" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="https://fonts.gstatic.com">' . "\n";
        echo '<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">' . "\n";
        
        // Auto-scale script for perfect fit on any screen width
        echo '<script>
        (function(){
            function setScale() {
                var w = window.innerWidth || document.documentElement.clientWidth;
                if (w < 1200) {
                    var scale = w / 1200;
                    var vp = document.querySelector("meta[name=viewport]");
                    if (vp) {
                        vp.setAttribute("content", "width=1200, initial-scale=" + scale.toFixed(4) + ", maximum-scale=1, user-scalable=yes");
                    }
                }
            }
            setScale();
            window.addEventListener("resize", setScale);
            window.addEventListener("orientationchange", setScale);
        })();
        </script>' . "\n";
    }
    
    /**
     * Add preload resource hints
     */
    public function resource_hints($hints, $relation_type) {
        if ('preconnect' === $relation_type) {
            $hints[] = array(
                'href' => 'https://fonts.googleapis.com',
                'crossorigin' => 'anonymous',
            );
            $hints[] = array(
                'href' => 'https://fonts.gstatic.com',
                'crossorigin' => 'anonymous',
            );
        }
        return $hints;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        CFI_Database::create_tables();
        
        // Create pages
        CFI_Pages::create_pages();
        
        // Add custom roles
        $this->add_custom_roles();
        
        // Schedule daily cron jobs
        if (!wp_next_scheduled('cfi_daily_reset')) {
            wp_schedule_event(strtotime('today 23:59:59'), 'daily', 'cfi_daily_reset');
        }
        if (!wp_next_scheduled('cfi_daily_backup')) {
            wp_schedule_event(strtotime('today 23:00:00'), 'daily', 'cfi_daily_backup');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('cfi_daily_reset');
        wp_clear_scheduled_hook('cfi_daily_backup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize classes
        CFI_Auth::get_instance();
        CFI_Ajax::get_instance();
        CFI_Shortcodes::get_instance();
        CFI_Admin::get_instance();
    }

    /**
     * Add custom user roles
     */
    public function add_custom_roles() {
        // Add CFI Staff role
        add_role('cfi_staff', __('CFI Staff', 'chinemerem-foods'), array(
            'read' => true,
            'cfi_take_orders' => true,
            'cfi_view_stock' => true,
            'cfi_record_expenses' => true,
        ));
        
        // Add CFI Admin role
        add_role('cfi_admin', __('CFI Admin', 'chinemerem-foods'), array(
            'read' => true,
            'cfi_take_orders' => true,
            'cfi_view_stock' => true,
            'cfi_record_expenses' => true,
            'cfi_manage_products' => true,
            'cfi_manage_debtors' => true,
            'cfi_manage_users' => true,
            'cfi_reconcile' => true,
            'cfi_view_reports' => true,
        ));
        
        // Add capabilities to administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('cfi_take_orders');
            $admin->add_cap('cfi_view_stock');
            $admin->add_cap('cfi_record_expenses');
            $admin->add_cap('cfi_manage_products');
            $admin->add_cap('cfi_manage_debtors');
            $admin->add_cap('cfi_manage_users');
            $admin->add_cap('cfi_reconcile');
            $admin->add_cap('cfi_view_reports');
            $admin->add_cap('cfi_super_admin');
            $admin->add_cap('cfi_manage_history');
        }
    }

    /**
     * Enqueue frontend scripts and styles - Performance Optimized
     */
    public function enqueue_scripts() {
        // Google Fonts - optimized with display=swap for faster text rendering
        wp_enqueue_style(
            'cfi-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            array(),
            null
        );
        
        // Add font-display swap attribute
        add_filter('style_loader_tag', array($this, 'add_font_display_swap'), 10, 2);
        
        // Font Awesome for icons - using minified CDN
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            array(),
            '6.5.1'
        );
        
        // Main stylesheet
        wp_enqueue_style(
            'cfi-main-style',
            CFI_PLUGIN_URL . 'assets/css/main.css',
            array('font-awesome'),
            CFI_VERSION
        );
        
        // Main JavaScript - defer loading for non-blocking
        wp_enqueue_script(
            'cfi-main-script',
            CFI_PLUGIN_URL . 'assets/js/main.js',
            array('jquery'),
            CFI_VERSION,
            true
        );
        
        // Add defer attribute to scripts for faster page load
        add_filter('script_loader_tag', array($this, 'add_defer_attribute'), 10, 2);
        
        // Localize script with minimal data for faster parsing
        wp_localize_script('cfi-main-script', 'cfiData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cfi_nonce'),
            'pluginUrl' => CFI_PLUGIN_URL,
            'isLoggedIn' => is_user_logged_in(),
            'currentUser' => wp_get_current_user()->display_name,
            'userRole' => $this->get_user_role_display(),
        ));
        
        // Service Worker for offline functionality - async loading
        wp_enqueue_script(
            'cfi-sw-register',
            CFI_PLUGIN_URL . 'assets/js/sw-register.js',
            array(),
            CFI_VERSION,
            true
        );
    }
    
    /**
     * Add defer attribute to scripts for non-blocking loading
     */
    public function add_defer_attribute($tag, $handle) {
        // Only defer our plugin scripts
        if (strpos($handle, 'cfi-') === 0 && strpos($handle, 'cfi-main-script') !== false) {
            return str_replace(' src', ' defer src', $tag);
        }
        return $tag;
    }
    
    /**
     * Add font-display swap for faster text rendering
     */
    public function add_font_display_swap($tag, $handle) {
        if ($handle === 'cfi-google-fonts') {
            return str_replace("rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $tag);
        }
        return $tag;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only on CFI admin pages
        if (strpos($hook, 'cfi-') === false && strpos($hook, 'chinemerem') === false) {
            return;
        }
        
        // Enqueue WordPress media library for image uploads
        wp_enqueue_media();
        
        wp_enqueue_style(
            'cfi-admin-style',
            CFI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CFI_VERSION
        );
        
        wp_enqueue_script(
            'cfi-admin-script',
            CFI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CFI_VERSION,
            true
        );
        
        // Localize script with nonce for AJAX requests
        wp_localize_script('cfi-admin-script', 'cfiData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cfi_nonce'),
            'pluginUrl' => CFI_PLUGIN_URL,
        ));
    }

    /**
     * Check authentication for ALL pages
     * Force redirect non-logged users to login page
     */
    public function check_authentication() {
        // Don't redirect on admin pages
        if (is_admin()) {
            return;
        }
        
        // Don't redirect on AJAX requests
        if (wp_doing_ajax()) {
            return;
        }
        
        // Don't redirect on REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        
        // Don't redirect on cron
        if (wp_doing_cron()) {
            return;
        }
        
        // Don't redirect on WordPress login/registration pages
        if (in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'))) {
            return;
        }
        
        // Custom login page URL - exclude from redirect
        $login_url = '/sign-in/';
        $current_url = $_SERVER['REQUEST_URI'];
        
        // Allowed URLs for non-logged users
        $allowed_urls = array(
            '/sign-in',
            '/sign-in/',
            '/login',
            '/login/',
            '/cfi-login',
            '/cfi-login/',
            '/wp-login.php',
            '/wp-admin'
        );
        
        // Check if current URL is an allowed page
        foreach ($allowed_urls as $allowed) {
            if (strpos($current_url, $allowed) !== false) {
                return;
            }
        }
        
        // Force redirect ALL non-logged users to login page
        if (!is_user_logged_in()) {
            $login_page_url = home_url($login_url);
            wp_redirect($login_page_url);
            exit;
        }
    }

    /**
     * Daily reset function
     */
    public function daily_reset() {
        CFI_Stock::daily_reset();
        CFI_Financial::daily_reset();
        CFI_Orders::daily_reset();
    }

    /**
     * Daily backup function
     */
    public function daily_backup() {
        CFI_Backup::create_daily_backup();
    }

    /**
     * Get user role display name
     */
    private function get_user_role_display() {
        $user = wp_get_current_user();
        if (in_array('administrator', $user->roles)) {
            return __('Super Admin', 'chinemerem-foods');
        } elseif (in_array('cfi_admin', $user->roles)) {
            return __('Admin', 'chinemerem-foods');
        } elseif (in_array('cfi_staff', $user->roles)) {
            return __('Staff', 'chinemerem-foods');
        }
        return __('Guest', 'chinemerem-foods');
    }
}

// Initialize the plugin
function cfi_init() {
    return Chinemerem_Foods_Inventory::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'cfi_init');
