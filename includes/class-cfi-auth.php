<?php
/**
 * Authentication Handler Class
 * Simple login/logout using WordPress admin-post handler
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Auth {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Use admin_post hooks - these are guaranteed to work
        add_action('admin_post_nopriv_cfi_do_login', array($this, 'handle_login'));
        add_action('admin_post_cfi_do_login', array($this, 'handle_login'));
        add_action('admin_post_nopriv_cfi_do_logout', array($this, 'handle_logout'));
        add_action('admin_post_cfi_do_logout', array($this, 'handle_logout'));
        
        // Override WordPress logout redirect to always go to /sign-in/
        add_filter('logout_redirect', array($this, 'custom_logout_redirect'), 10, 3);
        add_filter('wp_logout_url', array($this, 'custom_logout_url'), 10, 2);
    }
    
    /**
     * Custom logout redirect - always go to /sign-in/
     */
    public function custom_logout_redirect($redirect_to, $requested_redirect_to, $user) {
        return home_url('/sign-in/?logout=1');
    }
    
    /**
     * Custom logout URL - use our admin-post handler
     */
    public function custom_logout_url($logout_url, $redirect) {
        return admin_url('admin-post.php') . '?action=cfi_do_logout';
    }
    
    /**
     * Handle Login Form Submission
     */
    public function handle_login() {
        // Get credentials
        $username = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        
        // Validate
        if (empty($username) || empty($password)) {
            wp_safe_redirect(home_url('/sign-in/?error=empty'));
            exit;
        }
        
        // Try to login
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember
        );
        
        $user = wp_signon($creds, is_ssl());
        
        if (is_wp_error($user)) {
            wp_safe_redirect(home_url('/sign-in/?error=invalid'));
            exit;
        }
        
        // Check access
        if (!$this->user_has_cfi_access($user)) {
            wp_logout();
            wp_safe_redirect(home_url('/sign-in/?error=access'));
            exit;
        }
        
        // Success - redirect to home
        wp_safe_redirect(home_url('/home/'));
        exit;
    }
    
    /**
     * Handle Logout
     */
    public function handle_logout() {
        wp_logout();
        wp_safe_redirect(home_url('/sign-in/?logout=1'));
        exit;
    }
    
    /**
     * Check if user has CFI access
     */
    public function user_has_cfi_access($user) {
        $allowed_roles = array('administrator', 'cfi_admin', 'cfi_staff');
        foreach ($allowed_roles as $role) {
            if (in_array($role, (array) $user->roles)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if current user is CFI admin
     */
    public static function is_cfi_admin() {
        $user = wp_get_current_user();
        return in_array('administrator', (array) $user->roles) || in_array('cfi_admin', (array) $user->roles);
    }
    
    /**
     * Check if current user is super admin
     */
    public static function is_super_admin() {
        $user = wp_get_current_user();
        return in_array('administrator', (array) $user->roles);
    }
    
    /**
     * Check if current user is staff
     */
    public static function is_cfi_staff() {
        $user = wp_get_current_user();
        return in_array('cfi_staff', (array) $user->roles);
    }
    
    /**
     * Get user role display name
     */
    public function get_user_role_display($user = null) {
        if (!$user) {
            $user = wp_get_current_user();
        }
        
        if (in_array('administrator', (array) $user->roles)) {
            return __('Super Admin', 'chinemerem-foods');
        } elseif (in_array('cfi_admin', (array) $user->roles)) {
            return __('Admin', 'chinemerem-foods');
        } elseif (in_array('cfi_staff', (array) $user->roles)) {
            return __('Staff', 'chinemerem-foods');
        }
        
        return __('Guest', 'chinemerem-foods');
    }
    
    /**
     * Get current user info
     */
    public static function get_current_user_info() {
        $user = wp_get_current_user();
        
        if (!$user->ID) {
            return null;
        }
        
        $auth = self::get_instance();
        
        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'role' => $auth->get_user_role_display($user),
            'is_admin' => self::is_cfi_admin(),
            'is_super_admin' => self::is_super_admin()
        );
    }
}
