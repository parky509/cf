<?php
/**
 * Pages Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Pages {
    
    /**
     * Get all page slugs
     */
    public static function get_page_slugs() {
        return array(
            'cfi-login',
            'cfi-home',
            'cfi-take-order',
            'cfi-order-history',
            'cfi-transfer-history',
            'cfi-stock-record',
            'cfi-stock-history',
            'cfi-packing-store',
            'cfi-packing-history',
            'cfi-debtors-record',
            'cfi-debtors-history',
            'cfi-expenses',
            'cfi-expenses-history',
            'cfi-import-record',
            'cfi-import-history',
            'cfi-not-supplied',
            'cfi-not-supplied-history',
            'cfi-supplied-today',
            'cfi-supplied-today-history',
            'cfi-order-product-summary',
            'cfi-credit-order-summary',
            'cfi-cash-out',
            'cfi-financial-summary',
            'cfi-financial-history',
            'cfi-cash-out-history',
            'cfi-reconciliation',
            'cfi-admin-panel',
            'cfi-profile',
            'cfi-analytics',
        );
    }
    
    /**
     * Get page configurations
     */
    public static function get_pages_config() {
        return array(
            array(
                'slug' => 'cfi-login',
                'title' => __('Login', 'chinemerem-foods'),
                'template' => 'login',
                'icon' => 'fa-sign-in-alt',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-home',
                'title' => __('Dashboard', 'chinemerem-foods'),
                'template' => 'home',
                'icon' => 'fa-home',
                'show_in_menu' => true,
            ),
            array(
                'slug' => 'cfi-take-order',
                'title' => __('Take Order', 'chinemerem-foods'),
                'template' => 'take-order',
                'icon' => 'fa-cart-plus',
                'show_in_menu' => true,
                'description' => __('Take new customer orders', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-order-history',
                'title' => __('Order History', 'chinemerem-foods'),
                'template' => 'order-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-transfer-history',
                'title' => __('Transfer History', 'chinemerem-foods'),
                'template' => 'transfer-history',
                'icon' => 'fa-exchange-alt',
                'show_in_menu' => true,
                'description' => __('View all transfer payments', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-stock-record',
                'title' => __('Stock Inventory', 'chinemerem-foods'),
                'template' => 'stock-record',
                'icon' => 'fa-boxes',
                'show_in_menu' => true,
                'description' => __('Manage stock inventory', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-stock-history',
                'title' => __('Stock History', 'chinemerem-foods'),
                'template' => 'stock-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-packing-store',
                'title' => __('Packing Store', 'chinemerem-foods'),
                'template' => 'packing-store',
                'icon' => 'fa-box-open',
                'show_in_menu' => true,
                'description' => __('Manage packing store inventory', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-packing-history',
                'title' => __('Packing Store History', 'chinemerem-foods'),
                'template' => 'packing-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-debtors-record',
                'title' => __('Debtors Record', 'chinemerem-foods'),
                'template' => 'debtors-record',
                'icon' => 'fa-user-clock',
                'show_in_menu' => true,
                'description' => __('Manage debtor accounts', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-debtors-history',
                'title' => __('Debtors History', 'chinemerem-foods'),
                'template' => 'debtors-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-expenses',
                'title' => __('Expenses Record', 'chinemerem-foods'),
                'template' => 'expenses',
                'icon' => 'fa-file-invoice-dollar',
                'show_in_menu' => true,
                'description' => __('Record daily expenses', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-expenses-history',
                'title' => __('Expenses History', 'chinemerem-foods'),
                'template' => 'expenses-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-import-record',
                'title' => __('Import Record', 'chinemerem-foods'),
                'template' => 'import-record',
                'icon' => 'fa-truck-loading',
                'show_in_menu' => true,
                'description' => __('Record product imports', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-import-history',
                'title' => __('Import History', 'chinemerem-foods'),
                'template' => 'import-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-not-supplied',
                'title' => __('Not Supplied Record', 'chinemerem-foods'),
                'template' => 'not-supplied',
                'icon' => 'fa-times-circle',
                'show_in_menu' => true,
                'description' => __('Track unsupplied orders', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-not-supplied-history',
                'title' => __('Not Supplied History', 'chinemerem-foods'),
                'template' => 'not-supplied-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-supplied-today',
                'title' => __('Supplied Today', 'chinemerem-foods'),
                'template' => 'supplied-today',
                'icon' => 'fa-check-circle',
                'show_in_menu' => true,
                'description' => __('Track today\'s supplies', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-supplied-today-history',
                'title' => __('Supplied Today History', 'chinemerem-foods'),
                'template' => 'supplied-today-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-order-product-summary',
                'title' => __('Order Product Summary', 'chinemerem-foods'),
                'template' => 'order-product-summary',
                'icon' => 'fa-chart-bar',
                'show_in_menu' => true,
                'description' => __('Daily order product analytics', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-credit-order-summary',
                'title' => __('Debtor Order Summary', 'chinemerem-foods'),
                'template' => 'credit-order-summary',
                'icon' => 'fa-chart-pie',
                'show_in_menu' => true,
                'description' => __('Debtor order product analytics', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-cash-out',
                'title' => __('Cash Out Record', 'chinemerem-foods'),
                'template' => 'cash-out',
                'icon' => 'fa-money-bill-wave',
                'show_in_menu' => true,
                'description' => __('Record cash transfers to bank', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-cash-out-history',
                'title' => __('Cash Out History', 'chinemerem-foods'),
                'template' => 'cash-out-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-financial-summary',
                'title' => __('Financial Summary', 'chinemerem-foods'),
                'template' => 'financial-summary',
                'icon' => 'fa-calculator',
                'show_in_menu' => true,
                'description' => __('Daily financial overview', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-analytics',
                'title' => __('Analytics Overview', 'chinemerem-foods'),
                'template' => 'analytics',
                'icon' => 'fa-chart-line',
                'show_in_menu' => true,
                'admin_only' => true,
                'description' => __('Performance analytics for key forms', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-financial-history',
                'title' => __('Financial History', 'chinemerem-foods'),
                'template' => 'financial-history',
                'icon' => 'fa-history',
                'show_in_menu' => false,
            ),
            array(
                'slug' => 'cfi-reconciliation',
                'title' => __('Reconciliation Calendar', 'chinemerem-foods'),
                'template' => 'reconciliation',
                'icon' => 'fa-calendar-check',
                'show_in_menu' => true,
                'description' => __('Daily reconciliation tracking', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-admin-panel',
                'title' => __('Admin Panel', 'chinemerem-foods'),
                'template' => 'admin-panel',
                'icon' => 'fa-cogs',
                'show_in_menu' => true,
                'admin_only' => true,
                'description' => __('System administration', 'chinemerem-foods'),
            ),
            array(
                'slug' => 'cfi-profile',
                'title' => __('Profile', 'chinemerem-foods'),
                'template' => 'profile',
                'icon' => 'fa-user',
                'show_in_menu' => true,
            ),
        );
    }
    
    /**
     * Create all pages on plugin activation
     */
    public static function create_pages() {
        $pages = self::get_pages_config();
        
        foreach ($pages as $page) {
            $existing = get_page_by_path($page['slug']);
            
            if (!$existing) {
                $page_data = array(
                    'post_title'    => $page['title'],
                    'post_name'     => $page['slug'],
                    'post_content'  => '[cfi_page template="' . $page['template'] . '"]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                    'post_author'   => 1,
                    'page_template' => 'templates/cfi-full-width.php',
                );
                
                wp_insert_post($page_data);
            }
        }
    }
    
    /**
     * Delete all pages on plugin uninstall
     */
    public static function delete_pages() {
        $slugs = self::get_page_slugs();
        
        foreach ($slugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page) {
                wp_delete_post($page->ID, true);
            }
        }
    }
    
    /**
     * Get menu items for navigation
     */
    public static function get_menu_items($mobile = false) {
        $pages = self::get_pages_config();
        $menu_items = array();
        
        $mobile_items = array('cfi-home', 'cfi-take-order', 'cfi-stock-record', 'cfi-transfer-history', 'cfi-profile');
        
        foreach ($pages as $page) {
            if (!isset($page['show_in_menu']) || !$page['show_in_menu']) {
                continue;
            }
            
            // Check admin only pages
            if (isset($page['admin_only']) && $page['admin_only'] && !CFI_Auth::is_cfi_admin()) {
                continue;
            }
            
            if ($mobile && !in_array($page['slug'], $mobile_items)) {
                continue;
            }
            
            $page_obj = get_page_by_path($page['slug']);
            if ($page_obj) {
                $menu_items[] = array(
                    'title' => $page['title'],
                    'url' => get_permalink($page_obj->ID),
                    'icon' => $page['icon'],
                    'slug' => $page['slug'],
                    'description' => $page['description'] ?? '',
                );
            }
        }
        
        return $menu_items;
    }
    
    /**
     * Get home page cards
     */
    public static function get_home_cards() {
        $cards = array(
            array(
                'slug' => 'cfi-take-order',
                'title' => __('Take Order', 'chinemerem-foods'),
                'icon' => 'fa-cart-plus',
                'description' => __('Take new customer orders with cash or transfer payment', 'chinemerem-foods'),
                'color' => '#1a365d',
            ),
            array(
                'slug' => 'cfi-stock-record',
                'title' => __('Stock Inventory', 'chinemerem-foods'),
                'icon' => 'fa-boxes',
                'description' => __('View and manage daily stock inventory records', 'chinemerem-foods'),
                'color' => '#2c5282',
            ),
            array(
                'slug' => 'cfi-packing-store',
                'title' => __('Packing Store', 'chinemerem-foods'),
                'icon' => 'fa-box-open',
                'description' => __('Manage packing store transfers and inventory', 'chinemerem-foods'),
                'color' => '#2b6cb0',
            ),
            array(
                'slug' => 'cfi-debtors-record',
                'title' => __('Debtors Record', 'chinemerem-foods'),
                'icon' => 'fa-user-clock',
                'description' => __('Manage debtor accounts and payments', 'chinemerem-foods'),
                'color' => '#3182ce',
            ),
            array(
                'slug' => 'cfi-expenses',
                'title' => __('Expenses Record', 'chinemerem-foods'),
                'icon' => 'fa-file-invoice-dollar',
                'description' => __('Record and track daily business expenses', 'chinemerem-foods'),
                'color' => '#4299e1',
            ),
            array(
                'slug' => 'cfi-import-record',
                'title' => __('Import Record', 'chinemerem-foods'),
                'icon' => 'fa-truck-loading',
                'description' => __('Record product imports and deliveries', 'chinemerem-foods'),
                'color' => '#1a365d',
            ),
            array(
                'slug' => 'cfi-not-supplied',
                'title' => __('Not Supplied Record', 'chinemerem-foods'),
                'icon' => 'fa-times-circle',
                'description' => __('Track orders that were not supplied', 'chinemerem-foods'),
                'color' => '#c53030',
            ),
            array(
                'slug' => 'cfi-supplied-today',
                'title' => __('Supplied Today', 'chinemerem-foods'),
                'icon' => 'fa-check-circle',
                'description' => __('Record products supplied today', 'chinemerem-foods'),
                'color' => '#38a169',
            ),
            array(
                'slug' => 'cfi-order-product-summary',
                'title' => __('Order Product Summary', 'chinemerem-foods'),
                'icon' => 'fa-chart-bar',
                'description' => __('View daily order product analytics', 'chinemerem-foods'),
                'color' => '#805ad5',
            ),
            array(
                'slug' => 'cfi-credit-order-summary',
                'title' => __('Debtor Order Summary', 'chinemerem-foods'),
                'icon' => 'fa-chart-pie',
                'description' => __('View debtor order product analytics', 'chinemerem-foods'),
                'color' => '#d53f8c',
            ),
            array(
                'slug' => 'cfi-cash-out',
                'title' => __('Cash Out Record', 'chinemerem-foods'),
                'icon' => 'fa-money-bill-wave',
                'description' => __('Record cash transfers to bank accounts', 'chinemerem-foods'),
                'color' => '#dd6b20',
            ),
            array(
                'slug' => 'cfi-transfer-history',
                'title' => __('Transfer History', 'chinemerem-foods'),
                'icon' => 'fa-exchange-alt',
                'description' => __('View all transfer/card payment history', 'chinemerem-foods'),
                'color' => '#319795',
            ),
            array(
                'slug' => 'cfi-cash-out-history',
                'title' => __('Cash Out History', 'chinemerem-foods'),
                'icon' => 'fa-history',
                'description' => __('View cash out transfer history', 'chinemerem-foods'),
                'color' => '#718096',
            ),
            array(
                'slug' => 'cfi-financial-summary',
                'title' => __('Financial Summary', 'chinemerem-foods'),
                'icon' => 'fa-calculator',
                'description' => __('View daily financial summary and reports', 'chinemerem-foods'),
                'color' => '#2f855a',
            ),
            array(
                'slug' => 'cfi-reconciliation',
                'title' => __('Reconciliation Calendar', 'chinemerem-foods'),
                'icon' => 'fa-calendar-check',
                'description' => __('Track daily reconciliation status', 'chinemerem-foods'),
                'color' => '#744210',
            ),
        );
        
        $result = array();
        foreach ($cards as $card) {
            $page = get_page_by_path($card['slug']);
            if ($page) {
                $card['url'] = get_permalink($page->ID);
            } else {
                // Fallback URL using slug directly
                $card['url'] = home_url('/' . $card['slug'] . '/');
            }
            $result[] = $card;
        }
        
        return $result;
    }
    
    /**
     * Recreate all pages (can be called manually if pages are missing)
     */
    public static function recreate_pages() {
        $pages = self::get_pages_config();
        $created = 0;
        
        foreach ($pages as $page) {
            $existing = get_page_by_path($page['slug']);
            
            if (!$existing) {
                $page_data = array(
                    'post_title'    => $page['title'],
                    'post_name'     => $page['slug'],
                    'post_content'  => '[cfi_page template="' . $page['template'] . '"]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                    'post_author'   => get_current_user_id() ? get_current_user_id() : 1,
                    'page_template' => '',
                );
                
                $result = wp_insert_post($page_data);
                if (!is_wp_error($result)) {
                    $created++;
                }
            }
        }
        
        return $created;
    }
}
