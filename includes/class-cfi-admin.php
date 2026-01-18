<?php
/**
 * Admin Panel Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Chinemerem Foods', 'chinemerem-foods'),
            __('Chinemerem Foods', 'chinemerem-foods'),
            'manage_options',
            'cfi-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-store',
            30
        );
        
        add_submenu_page(
            'cfi-dashboard',
            __('Products', 'chinemerem-foods'),
            __('Products', 'chinemerem-foods'),
            'cfi_manage_products',
            'cfi-products',
            array($this, 'render_products')
        );
        
        add_submenu_page(
            'cfi-dashboard',
            __('Debtors', 'chinemerem-foods'),
            __('Debtors', 'chinemerem-foods'),
            'cfi_manage_debtors',
            'cfi-debtors',
            array($this, 'render_debtors')
        );
        
        add_submenu_page(
            'cfi-dashboard',
            __('Users', 'chinemerem-foods'),
            __('Users', 'chinemerem-foods'),
            'cfi_manage_users',
            'cfi-users',
            array($this, 'render_users')
        );
        
        add_submenu_page(
            'cfi-dashboard',
            __('Backup', 'chinemerem-foods'),
            __('Backup', 'chinemerem-foods'),
            'manage_options',
            'cfi-backup',
            array($this, 'render_backup')
        );
        
        add_submenu_page(
            'cfi-dashboard',
            __('Settings', 'chinemerem-foods'),
            __('Settings', 'chinemerem-foods'),
            'manage_options',
            'cfi-settings',
            array($this, 'render_settings')
        );
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Chinemerem Foods Dashboard', 'chinemerem-foods'); ?></h1>
            
            <div class="cfi-admin-dashboard">
                <div class="cfi-admin-cards">
                    <div class="cfi-admin-card">
                        <h3><?php esc_html_e('Total Products', 'chinemerem-foods'); ?></h3>
                        <p class="cfi-admin-number"><?php echo count(CFI_Products::get_all()); ?></p>
                    </div>
                    
                    <div class="cfi-admin-card">
                        <h3><?php esc_html_e('Today\'s Orders', 'chinemerem-foods'); ?></h3>
                        <p class="cfi-admin-number"><?php echo count(CFI_Orders::get_by_date(current_time('Y-m-d'))); ?></p>
                    </div>
                    
                    <div class="cfi-admin-card">
                        <h3><?php esc_html_e('Active Debtors', 'chinemerem-foods'); ?></h3>
                        <p class="cfi-admin-number"><?php echo count(CFI_Debtors::get_all()); ?></p>
                    </div>
                    
                    <div class="cfi-admin-card">
                        <h3><?php esc_html_e('Today\'s Sales', 'chinemerem-foods'); ?></h3>
                        <?php 
                        $totals = CFI_Orders::get_daily_totals(current_time('Y-m-d'));
                        ?>
                        <p class="cfi-admin-number"><?php echo CFI_Products::format_price($totals->total_sales ?: 0); ?></p>
                    </div>
                </div>
                
                <div class="cfi-admin-links">
                    <h2><?php esc_html_e('Quick Links', 'chinemerem-foods'); ?></h2>
                    <?php
                    $home_page = get_page_by_path('cfi-home');
                    if ($home_page) :
                    ?>
                    <a href="<?php echo esc_url(get_permalink($home_page->ID)); ?>" class="button button-primary" target="_blank">
                        <?php esc_html_e('Go to Frontend', 'chinemerem-foods'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render products page
     */
    public function render_products() {
        $products = CFI_Products::get_all('');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Products', 'chinemerem-foods'); ?></h1>
            
            <div class="cfi-admin-products">
                <form id="cfi-add-product-form" class="cfi-admin-form">
                    <h3><?php esc_html_e('Add New Product', 'chinemerem-foods'); ?></h3>
                    <input type="text" name="name" placeholder="<?php esc_attr_e('Product Name', 'chinemerem-foods'); ?>" required>
                    <input type="number" name="price" placeholder="<?php esc_attr_e('Price', 'chinemerem-foods'); ?>" step="0.01" required>
                    <input type="text" name="unit" placeholder="<?php esc_attr_e('Unit (e.g., kg, piece)', 'chinemerem-foods'); ?>">
                    <input type="text" name="category" placeholder="<?php esc_attr_e('Category', 'chinemerem-foods'); ?>">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Add Product', 'chinemerem-foods'); ?></button>
                </form>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Name', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Price', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Unit', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Category', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Status', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Actions', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product) : ?>
                        <tr>
                            <td><?php echo esc_html($product->id); ?></td>
                            <td><?php echo esc_html($product->name); ?></td>
                            <td><?php echo esc_html(CFI_Products::format_price($product->price)); ?></td>
                            <td><?php echo esc_html($product->unit); ?></td>
                            <td><?php echo esc_html($product->category); ?></td>
                            <td><?php echo esc_html($product->status); ?></td>
                            <td>
                                <button class="button cfi-edit-product" data-id="<?php echo esc_attr($product->id); ?>">
                                    <?php esc_html_e('Edit', 'chinemerem-foods'); ?>
                                </button>
                                <button class="button cfi-delete-product" data-id="<?php echo esc_attr($product->id); ?>">
                                    <?php esc_html_e('Delete', 'chinemerem-foods'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render debtors page
     */
    public function render_debtors() {
        $debtors = CFI_Debtors::get_all('');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Debtors', 'chinemerem-foods'); ?></h1>
            
            <div class="cfi-admin-debtors">
                <form id="cfi-add-debtor-form" class="cfi-admin-form">
                    <h3><?php esc_html_e('Add New Debtor', 'chinemerem-foods'); ?></h3>
                    <input type="text" name="name" placeholder="<?php esc_attr_e('Name', 'chinemerem-foods'); ?>" required>
                    <input type="text" name="phone" placeholder="<?php esc_attr_e('Phone', 'chinemerem-foods'); ?>">
                    <input type="email" name="email" placeholder="<?php esc_attr_e('Email', 'chinemerem-foods'); ?>">
                    <textarea name="address" placeholder="<?php esc_attr_e('Address', 'chinemerem-foods'); ?>"></textarea>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Add Debtor', 'chinemerem-foods'); ?></button>
                </form>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Name', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Phone', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Total Debt', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Status', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Actions', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($debtors as $debtor) : ?>
                        <tr>
                            <td><?php echo esc_html($debtor->id); ?></td>
                            <td><?php echo esc_html($debtor->name); ?></td>
                            <td><?php echo esc_html($debtor->phone); ?></td>
                            <td><?php echo esc_html(CFI_Products::format_price($debtor->total_debt)); ?></td>
                            <td><?php echo esc_html($debtor->status); ?></td>
                            <td>
                                <button class="button cfi-edit-debtor" data-id="<?php echo esc_attr($debtor->id); ?>">
                                    <?php esc_html_e('Edit', 'chinemerem-foods'); ?>
                                </button>
                                <button class="button cfi-delete-debtor" data-id="<?php echo esc_attr($debtor->id); ?>">
                                    <?php esc_html_e('Delete', 'chinemerem-foods'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render users page
     */
    public function render_users() {
        $users = get_users(array(
            'role__in' => array('administrator', 'cfi_admin', 'cfi_staff'),
            'orderby' => 'display_name'
        ));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('CFI Users', 'chinemerem-foods'); ?></h1>
            
            <div class="cfi-admin-users">
                <form id="cfi-add-user-form" class="cfi-admin-form">
                    <h3><?php esc_html_e('Add New User', 'chinemerem-foods'); ?></h3>
                    <input type="text" name="username" placeholder="<?php esc_attr_e('Username', 'chinemerem-foods'); ?>" required>
                    <input type="email" name="email" placeholder="<?php esc_attr_e('Email', 'chinemerem-foods'); ?>" required>
                    <input type="password" name="password" placeholder="<?php esc_attr_e('Password', 'chinemerem-foods'); ?>" required>
                    <input type="text" name="name" placeholder="<?php esc_attr_e('Display Name', 'chinemerem-foods'); ?>" required>
                    <select name="role">
                        <option value="cfi_staff"><?php esc_html_e('Staff', 'chinemerem-foods'); ?></option>
                        <option value="cfi_admin"><?php esc_html_e('Admin', 'chinemerem-foods'); ?></option>
                    </select>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Add User', 'chinemerem-foods'); ?></button>
                </form>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Username', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Name', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Email', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Role', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Actions', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html($user->ID); ?></td>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                            <td>
                                <button class="button cfi-edit-user" data-id="<?php echo esc_attr($user->ID); ?>">
                                    <?php esc_html_e('Edit', 'chinemerem-foods'); ?>
                                </button>
                                <?php if ($user->ID !== get_current_user_id()) : ?>
                                <button class="button cfi-delete-user" data-id="<?php echo esc_attr($user->ID); ?>">
                                    <?php esc_html_e('Delete', 'chinemerem-foods'); ?>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render backup page
     */
    public function render_backup() {
        $backups = CFI_Backup::get_list();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Backup & Restore', 'chinemerem-foods'); ?></h1>
            
            <div class="cfi-admin-backup">
                <div class="cfi-backup-actions">
                    <h3><?php esc_html_e('Create Backup', 'chinemerem-foods'); ?></h3>
                    <button id="cfi-create-backup" class="button button-primary">
                        <?php esc_html_e('Create Full Backup', 'chinemerem-foods'); ?>
                    </button>
                    
                    <h3><?php esc_html_e('Restore Backup', 'chinemerem-foods'); ?></h3>
                    <form id="cfi-restore-backup-form" enctype="multipart/form-data">
                        <input type="file" name="backup_file" accept=".json" required>
                        <button type="submit" class="button"><?php esc_html_e('Restore', 'chinemerem-foods'); ?></button>
                    </form>
                </div>
                
                <h3><?php esc_html_e('Backup History', 'chinemerem-foods'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('File', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Size', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Type', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Status', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup) : ?>
                        <tr>
                            <td><?php echo esc_html($backup->backup_date); ?></td>
                            <td><?php echo esc_html($backup->backup_file); ?></td>
                            <td><?php echo esc_html(size_format($backup->backup_size)); ?></td>
                            <td><?php echo esc_html($backup->backup_type); ?></td>
                            <td><?php echo esc_html($backup->status); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        // Handle form submission
        if (isset($_POST['cfi_save_settings']) && check_admin_referer('cfi_settings_nonce')) {
            update_option('cfi_currency_symbol', sanitize_text_field($_POST['cfi_currency_symbol'] ?? '₦'));
            update_option('cfi_business_name', sanitize_text_field($_POST['cfi_business_name'] ?? 'Chinemerem Foods'));
            update_option('cfi_login_background_image', esc_url_raw($_POST['cfi_login_background_image'] ?? ''));
            update_option('cfi_login_logo_image', esc_url_raw($_POST['cfi_login_logo_image'] ?? ''));
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'chinemerem-foods') . '</p></div>';
        }
        
        // Get current settings
        $currency_symbol = get_option('cfi_currency_symbol', '₦');
        $business_name = get_option('cfi_business_name', 'Chinemerem Foods');
        $login_bg_image = get_option('cfi_login_background_image', '');
        $login_logo = get_option('cfi_login_logo_image', '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Chinemerem Foods Settings', 'chinemerem-foods'); ?></h1>
            
            <div class="cfi-admin-settings">
                <form method="post" action="">
                    <?php wp_nonce_field('cfi_settings_nonce'); ?>
                    
                    <h2><?php esc_html_e('General Settings', 'chinemerem-foods'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Currency Symbol', 'chinemerem-foods'); ?></th>
                            <td>
                                <input type="text" name="cfi_currency_symbol" value="<?php echo esc_attr($currency_symbol); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Currency symbol to display (e.g., ₦, $, €)', 'chinemerem-foods'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Business Name', 'chinemerem-foods'); ?></th>
                            <td>
                                <input type="text" name="cfi_business_name" value="<?php echo esc_attr($business_name); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Your business name displayed across the site', 'chinemerem-foods'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2><?php esc_html_e('Login Page Customization', 'chinemerem-foods'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Login Background Image', 'chinemerem-foods'); ?></th>
                            <td>
                                <div class="cfi-image-upload-wrapper">
                                    <input type="text" name="cfi_login_background_image" id="cfi_login_background_image" value="<?php echo esc_attr($login_bg_image); ?>" class="regular-text" placeholder="<?php esc_attr_e('Select or enter image URL', 'chinemerem-foods'); ?>">
                                    <button type="button" class="button cfi-upload-btn" data-target="cfi_login_background_image"><?php esc_html_e('Upload Image', 'chinemerem-foods'); ?></button>
                                    <?php if ($login_bg_image) : ?>
                                    <button type="button" class="button cfi-remove-btn" data-target="cfi_login_background_image"><?php esc_html_e('Remove', 'chinemerem-foods'); ?></button>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Upload a full-width background image for the login page. Recommended size: 1920x1080px or larger.', 'chinemerem-foods'); ?></p>
                                <?php if ($login_bg_image) : ?>
                                <div class="cfi-image-preview" style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($login_bg_image); ?>" alt="Login Background Preview" style="max-width: 300px; height: auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Login Logo Image', 'chinemerem-foods'); ?></th>
                            <td>
                                <div class="cfi-image-upload-wrapper">
                                    <input type="text" name="cfi_login_logo_image" id="cfi_login_logo_image" value="<?php echo esc_attr($login_logo); ?>" class="regular-text" placeholder="<?php esc_attr_e('Select or enter logo URL', 'chinemerem-foods'); ?>">
                                    <button type="button" class="button cfi-upload-btn" data-target="cfi_login_logo_image"><?php esc_html_e('Upload Logo', 'chinemerem-foods'); ?></button>
                                    <?php if ($login_logo) : ?>
                                    <button type="button" class="button cfi-remove-btn" data-target="cfi_login_logo_image"><?php esc_html_e('Remove', 'chinemerem-foods'); ?></button>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Upload a custom logo for the login page. Recommended size: 200x200px. Leave empty to use default.', 'chinemerem-foods'); ?></p>
                                <?php if ($login_logo) : ?>
                                <div class="cfi-image-preview" style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($login_logo); ?>" alt="Login Logo Preview" style="max-width: 100px; height: auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="cfi_save_settings" class="button button-primary button-hero"><?php esc_html_e('Save All Settings', 'chinemerem-foods'); ?></button>
                    </p>
                </form>
                
                <hr style="margin: 30px 0;">
                
                <h2><?php esc_html_e('Page Management', 'chinemerem-foods'); ?></h2>
                <p><?php esc_html_e('If any CFI pages are missing, click the button below to recreate them:', 'chinemerem-foods'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('cfi_recreate_pages_nonce'); ?>
                    <button type="submit" name="cfi_recreate_pages" class="button button-secondary"><?php esc_html_e('Recreate Missing Pages', 'chinemerem-foods'); ?></button>
                </form>
                <?php
                if (isset($_POST['cfi_recreate_pages']) && check_admin_referer('cfi_recreate_pages_nonce')) {
                    $created = CFI_Pages::recreate_pages();
                    echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('%d pages created successfully!', 'chinemerem-foods'), $created) . '</p></div>';
                }
                ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Media uploader for image fields
            $('.cfi-upload-btn').on('click', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                var frame = wp.media({
                    title: '<?php esc_html_e('Select Image', 'chinemerem-foods'); ?>',
                    button: { text: '<?php esc_html_e('Use this image', 'chinemerem-foods'); ?>' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#' + targetId).val(attachment.url);
                });
                frame.open();
            });
            
            // Remove image
            $('.cfi-remove-btn').on('click', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                $('#' + targetId).val('');
                $(this).closest('td').find('.cfi-image-preview').remove();
                $(this).remove();
            });
        });
        </script>
        <?php
    }
}
