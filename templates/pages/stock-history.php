<?php
/**
 * Stock History Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<main class="cfi-main">
    <div class="cfi-container">
        <div class="cfi-page-title">
            <h1>
                <i class="fas fa-history"></i>
                <?php esc_html_e('Stock History', 'chinemerem-foods'); ?>
            </h1>
            <div class="cfi-page-actions">
                <?php $stock_record = get_page_by_path('cfi-stock-record'); ?>
                <?php if ($stock_record) : ?>
                <a href="<?php echo esc_url(get_permalink($stock_record->ID)); ?>" class="cfi-btn cfi-btn-primary cfi-btn-sm">
                    <i class="fas fa-boxes"></i>
                    <?php esc_html_e('Current Stock', 'chinemerem-foods'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="cfi-filters cfi-glass">
            <div class="cfi-filter-group">
                <label for="cfi-history-start"><?php esc_html_e('From:', 'chinemerem-foods'); ?></label>
                <input type="date" id="cfi-history-start" class="cfi-input" value="<?php echo esc_attr(date('Y-m-d', strtotime('-7 days'))); ?>">
            </div>
            <div class="cfi-filter-group">
                <label for="cfi-history-end"><?php esc_html_e('To:', 'chinemerem-foods'); ?></label>
                <input type="date" id="cfi-history-end" class="cfi-input" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
            </div>
            <div class="cfi-filter-group">
                <label for="cfi-product-filter"><?php esc_html_e('Product:', 'chinemerem-foods'); ?></label>
                <select id="cfi-product-filter" class="cfi-select">
                    <option value=""><?php esc_html_e('All Products', 'chinemerem-foods'); ?></option>
                    <?php
                    $products = CFI_Products::get_all();
                    foreach ($products as $product) :
                    ?>
                    <option value="<?php echo esc_attr($product->id); ?>"><?php echo esc_html($product->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" id="cfi-load-history" class="cfi-btn cfi-btn-primary cfi-btn-sm">
                <i class="fas fa-search"></i>
                <?php esc_html_e('Search', 'chinemerem-foods'); ?>
            </button>
        </div>
        
        <div id="cfi-stock-history" class="cfi-glass">
            <div class="cfi-table-wrapper">
                <table id="cfi-history-table" class="cfi-table cfi-table-responsive">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Product', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Opening', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Import', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Cash Supply', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Credit Supply', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Not Supplied', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Supplied Today', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('To Packing', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('From Packing', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Closing', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
jQuery(document).ready(function($) {
    function loadHistory() {
        const startDate = $('#cfi-history-start').val();
        const endDate = $('#cfi-history-end').val();
        const productId = $('#cfi-product-filter').val();
        
        CFI.ajax.request('get_stock_history', {
            start_date: startDate,
            end_date: endDate,
            product_id: productId
        }).then(function(data) {
            const tbody = $('#cfi-history-table tbody');
            tbody.empty();
            
            if (!data.history || data.history.length === 0) {
                tbody.append('<tr><td colspan="11" style="text-align: center;"><?php esc_html_e('No records found', 'chinemerem-foods'); ?></td></tr>');
                return;
            }
            
            data.history.forEach(function(record) {
                tbody.append(`
                    <tr>
                        <td data-label="<?php esc_attr_e('Date', 'chinemerem-foods'); ?>">${record.record_date}</td>
                        <td data-label="<?php esc_attr_e('Product', 'chinemerem-foods'); ?>">${record.product_name}</td>
                        <td data-label="<?php esc_attr_e('Opening', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.opening)}</td>
                        <td data-label="<?php esc_attr_e('Import', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.import_qty)}</td>
                        <td data-label="<?php esc_attr_e('Cash Supply', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.cash_supply)}</td>
                        <td data-label="<?php esc_attr_e('Credit Supply', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.credit_supply)}</td>
                        <td data-label="<?php esc_attr_e('Not Supplied', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.not_supplied)}</td>
                        <td data-label="<?php esc_attr_e('Supplied Today', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.supplied_today)}</td>
                        <td data-label="<?php esc_attr_e('To Packing', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.to_packing_store)}</td>
                        <td data-label="<?php esc_attr_e('From Packing', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.from_packing_store)}</td>
                        <td data-label="<?php esc_attr_e('Closing', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.closing)}</td>
                    </tr>
                `);
            });
        }).catch(function(error) {
            CFI.toast.error(error);
        });
    }
    
    $('#cfi-load-history').on('click', function() {
        loadHistory();
    });
    
    loadHistory();
});
</script>
