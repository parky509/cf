<?php
/**
 * Debtor Order Product Summary Page Template
 * Renamed from Credit Order Summary - shows debtor orders
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
    .cfi-page-header-custom {
        background: linear-gradient(135deg, #001943, #002960);
        color: #ffffff !important;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .cfi-page-header-custom h1 {
        margin: 0;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: #ffffff !important;
        font-weight: 600;
    }
    .cfi-page-header-custom h1 i { color: #ffffff !important; font-size: 0.75rem; }
    .cfi-btn-history-custom {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.65rem;
        text-decoration: none;
        background: #ffffff !important;
        color: #001943 !important;
        border: 2px solid #001943;
        box-shadow: 0 2px 8px rgba(0,25,67,0.15);
    }
    .cfi-btn-history-custom i { color: #001943 !important; }
    .cfi-btn-history-custom:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
</style>
<main class="cfi-main">
    <div class="cfi-container">
        <div class="cfi-page-header-custom">
            <h1>
                <i class="fas fa-chart-pie"></i>
                <?php esc_html_e('Debtor Order Summary', 'chinemerem-foods'); ?>
            </h1>
            <div class="cfi-page-actions">
                <?php
                $debtors_record = get_page_by_path('cfi-debtors-record');
                $debtors_url = $debtors_record ? get_permalink($debtors_record->ID) : home_url('/debtors-record/');
                ?>
                <a href="<?php echo esc_url($debtors_url); ?>" class="cfi-btn-history-custom">
                    <i class="fas fa-user-clock"></i>
                    <?php esc_html_e('Debtors', 'chinemerem-foods'); ?>
                </a>
            </div>
        </div>
        
        <div class="cfi-filters cfi-glass">
            <div class="cfi-filter-group">
                <label for="cfi-summary-date"><?php esc_html_e('Date:', 'chinemerem-foods'); ?></label>
                <input type="date" id="cfi-summary-date" class="cfi-input" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
            </div>
            <button type="button" id="cfi-load-summary" class="cfi-btn cfi-btn-primary cfi-btn-sm">
                <i class="fas fa-sync"></i>
                <?php esc_html_e('Load', 'chinemerem-foods'); ?>
            </button>
        </div>
        
        <div class="cfi-glass">
            <h3>
                <i class="fas fa-user-clock" style="color: var(--cfi-warning);"></i>
                <?php esc_html_e('Debtor Orders Summary', 'chinemerem-foods'); ?>
            </h3>
            <p style="color: var(--cfi-gray);"><?php esc_html_e('Products ordered today by debtors (credit orders). Resets daily.', 'chinemerem-foods'); ?></p>
            
            <div class="cfi-table-wrapper">
                <table id="cfi-summary-table" class="cfi-table cfi-table-responsive">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Total Quantity', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Staff & Times', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated by JavaScript -->
                    </tbody>
                    <tfoot id="cfi-summary-total">
                    </tfoot>
                </table>
            </div>
        </div>
        
        <div class="cfi-glass" style="margin-top: 1.5rem;">
            <h4><i class="fas fa-chart-pie"></i> <?php esc_html_e('Analytics', 'chinemerem-foods'); ?></h4>
            <div id="cfi-analytics" class="cfi-cards-grid" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </div>
</main>

<script>
jQuery(document).ready(function($) {
    function loadSummary() {
        const date = $('#cfi-summary-date').val();
        
        CFI.ajax.request('get_order_product_summary', {
            date: date,
            type: 'credit'
        }).then(function(data) {
            const tbody = $('#cfi-summary-table tbody');
            const tfoot = $('#cfi-summary-total');
            const analytics = $('#cfi-analytics');
            
            tbody.empty();
            tfoot.empty();
            analytics.empty();
            
            if (!data.summary || data.summary.length === 0) {
                tbody.append('<tr><td colspan="3" style="text-align: center;"><?php esc_html_e('No credit orders yet today', 'chinemerem-foods'); ?></td></tr>');
                return;
            }
            
            let grandTotal = 0;
            
            data.summary.forEach(function(item) {
                grandTotal += parseFloat(item.total_quantity);
                
                tbody.append(`
                    <tr>
                        <td data-label="<?php esc_attr_e('Product', 'chinemerem-foods'); ?>"><strong>${item.name}</strong></td>
                        <td data-label="<?php esc_attr_e('Total Quantity', 'chinemerem-foods'); ?>">
                            <span style="font-size: 1.25rem; font-weight: 700; color: var(--cfi-warning);">${CFI.utils.formatNumber(item.total_quantity)}</span>
                        </td>
                        <td data-label="<?php esc_attr_e('Staff & Times', 'chinemerem-foods'); ?>">${item.staff_info || '-'}</td>
                    </tr>
                `);
            });
            
            tfoot.append(`
                <tr style="background: var(--cfi-warning); color: white;">
                    <td><strong><?php esc_html_e('Grand Total', 'chinemerem-foods'); ?></strong></td>
                    <td><strong style="font-size: 1.5rem;">${CFI.utils.formatNumber(grandTotal)}</strong></td>
                    <td></td>
                </tr>
            `);
            
            // Analytics
            analytics.append(`
                <div class="cfi-card" style="text-align: center;">
                    <div class="cfi-card-icon" style="background: var(--cfi-warning); width: 40px; height: 40px; margin: 0 auto;">
                        <i class="fas fa-boxes" style="font-size: 1rem;"></i>
                    </div>
                    <p style="font-size: 2rem; font-weight: 700; color: var(--cfi-primary); margin: 0.5rem 0 0;">${data.summary.length}</p>
                    <p style="font-size: 0.75rem; color: var(--cfi-gray); margin: 0;"><?php esc_html_e('Products', 'chinemerem-foods'); ?></p>
                </div>
                <div class="cfi-card" style="text-align: center;">
                    <div class="cfi-card-icon" style="background: var(--cfi-danger); width: 40px; height: 40px; margin: 0 auto;">
                        <i class="fas fa-cubes" style="font-size: 1rem;"></i>
                    </div>
                    <p style="font-size: 2rem; font-weight: 700; color: var(--cfi-primary); margin: 0.5rem 0 0;">${CFI.utils.formatNumber(grandTotal)}</p>
                    <p style="font-size: 0.75rem; color: var(--cfi-gray); margin: 0;"><?php esc_html_e('Total Units', 'chinemerem-foods'); ?></p>
                </div>
            `);
        }).catch(function(error) {
            CFI.toast.error(error);
        });
    }
    
    $('#cfi-load-summary, #cfi-summary-date').on('click change', function() {
        loadSummary();
    });
    
    loadSummary();
});
</script>
