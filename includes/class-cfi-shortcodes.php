<?php
/**
 * Shortcodes Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Shortcodes {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
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
        add_shortcode('cfi_page', array($this, 'render_page'));
    }
    
    /**
     * Render page shortcode
     */
    public function render_page($atts) {
        $atts = shortcode_atts(array(
            'template' => 'home',
        ), $atts, 'cfi_page');
        
        // Check authentication for non-login pages
        if ($atts['template'] !== 'login' && !is_user_logged_in()) {
            return $this->render_login_redirect();
        }
        
        // Get user info
        $user_info = CFI_Auth::get_current_user_info();
        
        // Start output buffer
        ob_start();
        
        // Render header (except for login page)
        if ($atts['template'] !== 'login') {
            $this->render_header($user_info);
        }
        
        // Render page content
        $this->render_template($atts['template'], $user_info);
        
        // Render footer (except for login page)
        if ($atts['template'] !== 'login') {
            $this->render_footer();
            // Mobile nav removed per user request
            $this->render_scroll_to_top();
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render login redirect
     */
    private function render_login_redirect() {
        $login_page = get_page_by_path('cfi-login');
        $login_url = $login_page ? get_permalink($login_page->ID) : wp_login_url();
        
        return '<div class="cfi-login-required">
            <p>' . esc_html__('Please login to access this page.', 'chinemerem-foods') . '</p>
            <a href="' . esc_url($login_url) . '" class="cfi-btn cfi-btn-primary">' . esc_html__('Login', 'chinemerem-foods') . '</a>
        </div>';
    }
    
    /**
     * Render header
     */
    private function render_header($user_info) {
        $home_page = get_page_by_path('cfi-home');
        $home_url = $home_page ? get_permalink($home_page->ID) : home_url();
        $menu_items = CFI_Pages::get_menu_items();
        ?>
        <header class="cfi-header">
            <div class="cfi-header-inner">
                <div class="cfi-header-left">
                    <a href="<?php echo esc_url($home_url); ?>" class="cfi-logo">
                        <img src="<?php echo esc_url(CFI_PLUGIN_URL . 'assets/images/logo.svg'); ?>" alt="Chinemerem Foods">
                        <span>Chinemerem Foods</span>
                    </a>
                    <!-- Hamburger removed per user request -->
                </div>
                
                <nav class="cfi-nav" id="cfi-main-nav">
                    <ul>
                        <?php foreach ($menu_items as $item) : ?>
                        <li>
                            <a href="<?php echo esc_url($item['url']); ?>">
                                <i class="fas <?php echo esc_attr($item['icon']); ?>"></i>
                                <span><?php echo esc_html($item['title']); ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
                
                <div class="cfi-header-right">
                    <div class="cfi-user-info">
                        <span class="cfi-user-name"><?php echo esc_html($user_info['name']); ?></span>
                        <span class="cfi-user-role"><?php echo esc_html($user_info['role']); ?></span>
                    </div>
                    <div class="cfi-datetime">
                        <span class="cfi-date" id="cfi-current-date"></span>
                        <span class="cfi-time" id="cfi-current-time"></span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin-post.php') . '?action=cfi_do_logout'); ?>" class="cfi-btn cfi-btn-outline" id="cfi-logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span><?php esc_html_e('Logout', 'chinemerem-foods'); ?></span>
                    </a>
                </div>
            </div>
        </header>
        <?php
    }
    
    /**
     * Render footer
     */
    private function render_footer() {
        ?>
        <footer class="cfi-footer">
            <div class="cfi-footer-inner">
                <p>&copy; <?php echo esc_html(gmdate('Y')); ?> Chinemerem Foods. <?php esc_html_e('All rights reserved.', 'chinemerem-foods'); ?></p>
                <p><?php esc_html_e('Designed by', 'chinemerem-foods'); ?> <a href="https://bendlestech.com" target="_blank" rel="noopener">BendlessTech</a></p>
            </div>
        </footer>
        <?php
    }
    
    /**
     * Render mobile navigation with sleek bottom bar
     */
    private function render_mobile_nav() {
        // Get page URLs using WordPress functions - use non-cfi format as user specified
        $home_page = get_page_by_path('home');
        $order_page = get_page_by_path('take-order');
        $stock_page = get_page_by_path('stock-record');
        $history_page = get_page_by_path('transfer-history');
        $profile_page = get_page_by_path('profile');
        
        // Build mobile nav items with proper page URLs (fallback to /page/ format without cfi-)
        $mobile_nav = array(
            array('url' => $home_page ? get_permalink($home_page->ID) : home_url('/home/'), 'icon' => 'fa-home', 'label' => 'Home'),
            array('url' => $order_page ? get_permalink($order_page->ID) : home_url('/take-order/'), 'icon' => 'fa-cart-plus', 'label' => 'Order'),
            array('url' => $stock_page ? get_permalink($stock_page->ID) : home_url('/stock-record/'), 'icon' => 'fa-boxes', 'label' => 'Stock'),
            array('url' => $history_page ? get_permalink($history_page->ID) : home_url('/transfer-history/'), 'icon' => 'fa-exchange-alt', 'label' => 'History'),
            array('url' => $profile_page ? get_permalink($profile_page->ID) : home_url('/profile/'), 'icon' => 'fa-user-circle', 'label' => 'Profile'),
        );
        ?>
        <nav class="cfi-mobile-nav" id="cfi-mobile-nav">
            <?php foreach ($mobile_nav as $item) : ?>
            <a href="<?php echo esc_url($item['url']); ?>" class="cfi-mobile-nav-item">
                <i class="fas <?php echo esc_attr($item['icon']); ?>"></i>
                <span><?php echo esc_html($item['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }
    
    /**
     * Render scroll to top button
     */
    private function render_scroll_to_top() {
        ?>
        <button class="cfi-scroll-top" id="cfi-scroll-top" aria-label="Scroll to top">
            <svg class="cfi-scroll-progress" viewBox="0 0 100 100">
                <circle class="cfi-scroll-track" cx="50" cy="50" r="45"/>
                <circle class="cfi-scroll-indicator" cx="50" cy="50" r="45"/>
            </svg>
            <i class="fas fa-chevron-up"></i>
        </button>
        <?php
    }
    
    /**
     * Render template
     */
    private function render_template($template, $user_info) {
        $template_file = CFI_PLUGIN_DIR . 'templates/pages/' . $template . '.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="cfi-error">Template not found: ' . esc_html($template) . '</div>';
        }
    }
}
