<?php
/**
 * Home/Dashboard Page Template - REBUILT FROM SCRATCH v2
 * Uses Font Awesome 6 solid icons with inline SVG fallback
 * Full-width buttons in cards, small Manage Products button
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define cards with Font Awesome 6 solid icons (fas prefix)
// Using Unicode characters as fallback if FA fails to load
$cards = array(
    array(
        'slug' => 'take-order',
        'title' => 'Take Order',
        'icon' => 'fas fa-cart-shopping',
        'unicode' => '&#xf07a;',
        'description' => 'Take new customer orders with cash or transfer payment',
    ),
    array(
        'slug' => 'stock-record',
        'title' => 'Stock Inventory',
        'icon' => 'fas fa-warehouse',
        'unicode' => '&#xf494;',
        'description' => 'View and manage daily stock inventory records',
    ),
    array(
        'slug' => 'packing-store',
        'title' => 'Packing Store',
        'icon' => 'fas fa-box',
        'unicode' => '&#xf466;',
        'description' => 'Manage packing store transfers and inventory',
    ),
    array(
        'slug' => 'debtors-record',
        'title' => 'Debtors Record',
        'icon' => 'fas fa-users',
        'unicode' => '&#xf0c0;',
        'description' => 'Manage debtor accounts and payments',
    ),
    array(
        'slug' => 'expenses',
        'title' => 'Expenses Record',
        'icon' => 'fas fa-receipt',
        'unicode' => '&#xf543;',
        'description' => 'Record and track daily business expenses',
    ),
    array(
        'slug' => 'import-record',
        'title' => 'Import Record',
        'icon' => 'fas fa-truck',
        'unicode' => '&#xf0d1;',
        'description' => 'Record product imports and deliveries',
    ),
    array(
        'slug' => 'not-supplied',
        'title' => 'Not Supplied Record',
        'icon' => 'fas fa-circle-xmark',
        'unicode' => '&#xf057;',
        'description' => 'Track orders that were not supplied',
    ),
    array(
        'slug' => 'supplied-today',
        'title' => 'Supplied Today',
        'icon' => 'fas fa-circle-check',
        'unicode' => '&#xf058;',
        'description' => 'Record products supplied today',
    ),
    array(
        'slug' => 'order-product-summary',
        'title' => 'Order Product Summary',
        'icon' => 'fas fa-chart-bar',
        'unicode' => '&#xf080;',
        'description' => 'View daily order product analytics',
    ),
    array(
        'slug' => 'credit-order-summary',
        'title' => 'Debtor Order Summary',
        'icon' => 'fas fa-chart-pie',
        'unicode' => '&#xf200;',
        'description' => 'View debtor order product analytics',
    ),
    array(
        'slug' => 'cash-out',
        'title' => 'Cash Out Record',
        'icon' => 'fas fa-money-bill',
        'unicode' => '&#xf0d6;',
        'description' => 'Record cash transfers to bank accounts',
    ),
    array(
        'slug' => 'transfer-history',
        'title' => 'Transfer History',
        'icon' => 'fas fa-right-left',
        'unicode' => '&#xf362;',
        'description' => 'View all transfer/card payment history',
    ),
    array(
        'slug' => 'financial-summary',
        'title' => 'Financial Summary',
        'icon' => 'fas fa-calculator',
        'unicode' => '&#xf1ec;',
        'description' => 'View daily financial summary and reports',
    ),
    array(
        'slug' => 'reconciliation',
        'title' => 'Reconciliation Calendar',
        'icon' => 'fas fa-calendar-check',
        'unicode' => '&#xf274;',
        'description' => 'Track daily reconciliation status',
    ),
);

if (!defined('CFI_PAGE_PREFIX')) {
    define('CFI_PAGE_PREFIX', 'cfi-');
}
// Prefix used for CFI page slugs.
$page_prefix = CFI_PAGE_PREFIX;

/**
 * Resolve a page by slug with optional CFI prefix handling.
 *
 * @param string $slug Non-empty page slug to resolve.
 * @param string $prefix Prefix for CFI page slugs (e.g., "cfi-"). The lookup tries the
 *                       exact slug, then removes the prefix if present, or adds the prefix if absent.
 * @return WP_Post|null
 */
function cfi_resolve_page_by_slug($slug, $prefix) {
    $page = get_page_by_path($slug);
    if ($page) {
        return $page;
    }
    $has_prefix = $prefix !== '' && (function_exists('str_starts_with')
        ? str_starts_with($slug, $prefix)
        : (strlen($slug) >= strlen($prefix) && substr($slug, 0, strlen($prefix)) === $prefix));
    if ($has_prefix) {
        $trimmed = substr($slug, strlen($prefix));
        return get_page_by_path($trimmed);
    }
    return get_page_by_path($prefix . $slug);
}

if (CFI_Auth::is_cfi_admin()) {
    $cards[] = array(
        'url' => home_url('/analytics/'),
        'title' => 'Analytics Overview',
        'icon' => 'fas fa-chart-line',
        'unicode' => '&#xf3e6;',
        'description' => 'View business performance analytics',
    );
}

// Build URLs for each card
foreach ($cards as &$card) {
    if (empty($card['url'])) {
        $page = cfi_resolve_page_by_slug($card['slug'], $page_prefix);
        if ($page) {
            $card['url'] = get_permalink($page->ID);
        } else {
            $card['url'] = home_url('/' . $card['slug'] . '/');
        }
    }
}
unset($card);
?>
<!-- Font Awesome 6 CDN loaded inline for reliability -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<style>
.cfi-home-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
    gap: 1.5rem !important;
    padding: 1rem 0 !important;
}
.cfi-home-card {
    background: rgba(255, 255, 255, 0.95) !important;
    border: 2px solid rgba(0, 25, 67, 0.2) !important;
    border-radius: 16px !important;
    padding: 1.5rem !important;
    text-decoration: none !important;
    color: #001943 !important;
    box-shadow: 0 4px 20px rgba(0, 25, 67, 0.15) !important;
    transition: all 0.3s ease !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
}
.cfi-home-card:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 8px 30px rgba(0, 25, 67, 0.25) !important;
    border-color: #001943 !important;
}
.cfi-home-card-icon {
    width: 56px !important;
    height: 56px !important;
    background: #001943 !important;
    color: #ffffff !important;
    border-radius: 12px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 1.5rem !important;
    margin-bottom: 1rem !important;
    font-family: 'Font Awesome 6 Free', 'FontAwesome', sans-serif !important;
    font-weight: 900 !important;
}
.cfi-home-card-icon i,
.cfi-home-card-icon .fa,
.cfi-home-card-icon .fas,
.cfi-home-card-icon [class*="fa-"] {
    color: #ffffff !important;
    font-size: 1.5rem !important;
    display: inline-block !important;
    font-family: 'Font Awesome 6 Free', 'FontAwesome', sans-serif !important;
    font-weight: 900 !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
}
.cfi-home-card-title {
    font-size: 1.1rem !important;
    font-weight: 700 !important;
    color: #001943 !important;
    margin-bottom: 0.5rem !important;
}
.cfi-home-card-desc {
    font-size: 0.75rem !important;
    color: #64748b !important;
    margin-bottom: 1rem !important;
    line-height: 1.5 !important;
    flex-grow: 1 !important;
}
.cfi-home-card-btn {
    background: #001943 !important;
    color: #ffffff !important;
    padding: 0.6rem 1rem !important;
    border-radius: 8px !important;
    font-size: 0.85rem !important;
    font-weight: 600 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
    width: 100% !important;
    text-align: center !important;
    text-decoration: none !important;
}
.cfi-home-card-btn:hover {
    background: #002a66 !important;
}
.cfi-home-card-btn i,
.cfi-home-card-btn .fa,
.cfi-home-card-btn .fas {
    color: #ffffff !important;
    font-family: 'Font Awesome 6 Free', 'FontAwesome', sans-serif !important;
    font-weight: 900 !important;
}
.cfi-admin-section {
    margin-top: 2rem !important;
    padding: 1.5rem !important;
    background: rgba(255,255,255,0.95) !important;
    border: 2px solid rgba(0,25,67,0.2) !important;
    border-radius: 16px !important;
    box-shadow: 0 4px 20px rgba(0,25,67,0.15) !important;
}
.cfi-admin-title {
    color: #001943 !important;
    margin-bottom: 1rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    font-size: 1.1rem !important;
    font-weight: 700 !important;
}
.cfi-admin-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    background: #001943 !important;
    color: #ffffff !important;
    padding: 0.5rem 1rem !important;
    border-radius: 8px !important;
    text-decoration: none !important;
    font-weight: 600 !important;
    font-size: 0.85rem !important;
}
.cfi-admin-btn:hover {
    background: #002a66 !important;
    color: #ffffff !important;
}
.cfi-admin-btn i {
    color: #ffffff !important;
}
</style>

<main class="cfi-main">
    <div class="cfi-container">
        <div class="cfi-page-title" style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <img src="<?php echo esc_url(CFI_PLUGIN_URL . 'assets/images/logo.svg'); ?>" alt="Logo" style="width: 50px; height: 50px; object-fit: contain;" onerror="this.style.display='none'">
                <div>
                    <h1 style="color: #001943 !important; font-size: 1.5rem !important; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-house" style="color: #001943 !important;"></i>
                        Dashboard
                    </h1>
                    <span style="color: #64748b; font-size: 0.85rem;"><?php echo esc_html(current_time('l, F j, Y')); ?></span>
                </div>
            </div>
        </div>
        
        <div class="cfi-home-grid">
            <?php foreach ($cards as $card) : ?>
            <a href="<?php echo esc_url($card['url']); ?>" class="cfi-home-card">
                <div class="cfi-home-card-icon">
                    <i class="<?php echo esc_attr($card['icon']); ?>"></i>
                </div>
                <div class="cfi-home-card-title"><?php echo esc_html($card['title']); ?></div>
                <div class="cfi-home-card-desc"><?php echo esc_html($card['description']); ?></div>
                <span class="cfi-home-card-btn">
                    Open <i class="fas fa-arrow-right"></i>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        
        <?php if (CFI_Auth::is_cfi_admin()) : ?>
        <div class="cfi-admin-section">
            <h3 class="cfi-admin-title">
                <i class="fas fa-gear" style="color: #001943 !important;"></i> Quick Admin Actions
            </h3>
            <a href="<?php echo esc_url(home_url('/admin-panel/')); ?>" class="cfi-admin-btn">
                <i class="fas fa-plus"></i>
                Manage Products
            </a>
        </div>
        <?php endif; ?>
    </div>
</main>
