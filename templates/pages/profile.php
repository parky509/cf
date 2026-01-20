<?php
/**
 * Profile Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_info = CFI_Auth::get_current_user_info();
$user = wp_get_current_user();

// Build logout URL using admin-post.php
$logout_url = admin_url('admin-post.php') . '?action=cfi_do_logout';
?>
<main class="cfi-main">
    <div class="cfi-container">
        <div class="cfi-page-title">
            <h1>
                <i class="fas fa-user"></i>
                <?php esc_html_e('My Profile', 'chinemerem-foods'); ?>
            </h1>
        </div>
        
        <div class="cfi-glass" style="max-width: 600px;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 100px; height: 100px; background: var(--cfi-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2.5rem; color: var(--cfi-white);">
                    <i class="fas fa-user"></i>
                </div>
                <h2 style="margin: 0;"><?php echo esc_html($user_info['name']); ?></h2>
                <span style="background: var(--cfi-accent); color: var(--cfi-white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem;">
                    <?php echo esc_html($user_info['role']); ?>
                </span>
            </div>
            
            <div style="border-top: 1px solid var(--cfi-border); padding-top: 1.5rem;">
                <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--cfi-border);">
                    <span style="color: var(--cfi-gray);"><i class="fas fa-user"></i> <?php esc_html_e('Username', 'chinemerem-foods'); ?></span>
                    <span><strong><?php echo esc_html($user->user_login); ?></strong></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--cfi-border);">
                    <span style="color: var(--cfi-gray);"><i class="fas fa-envelope"></i> <?php esc_html_e('Email', 'chinemerem-foods'); ?></span>
                    <span><strong><?php echo esc_html($user->user_email); ?></strong></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--cfi-border);">
                    <span style="color: var(--cfi-gray);"><i class="fas fa-calendar"></i> <?php esc_html_e('Member Since', 'chinemerem-foods'); ?></span>
                    <span><strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))); ?></strong></span>
                </div>
            </div>
            
            <div style="margin-top: 2rem; text-align: center;">
                <a href="<?php echo esc_url($logout_url); ?>" class="cfi-btn cfi-btn-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php esc_html_e('Logout', 'chinemerem-foods'); ?>
                </a>
            </div>
        </div>
    </div>
</main>
