<?php
/**
 * Supplied Today History Page Template
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
                <?php esc_html_e('Supplied Today History', 'chinemerem-foods'); ?>
            </h1>
            <div class="cfi-page-actions">
                <?php $supplied_today = get_page_by_path('cfi-supplied-today'); ?>
                <?php if ($supplied_today) : ?>
                <a href="<?php echo esc_url(get_permalink($supplied_today->ID)); ?>" class="cfi-btn cfi-btn-primary cfi-btn-sm">
                    <i class="fas fa-check-circle"></i>
                    <?php esc_html_e('Today\'s Record', 'chinemerem-foods'); ?>
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
            <button type="button" id="cfi-load-history" class="cfi-btn cfi-btn-primary cfi-btn-sm">
                <i class="fas fa-search"></i>
                <?php esc_html_e('Search', 'chinemerem-foods'); ?>
            </button>
        </div>
        
        <div id="cfi-supplied-today-history" class="cfi-glass">
            <div class="cfi-table-wrapper">
                <table id="cfi-history-table" class="cfi-table cfi-table-responsive">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Time', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Product', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Quantity', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Customer', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Remark', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Staff', 'chinemerem-foods'); ?></th>
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
        
        CFI.ajax.request('get_supplied_today_history', {
            start_date: startDate,
            end_date: endDate
        }).then(function(data) {
            const tbody = $('#cfi-history-table tbody');
            tbody.empty();
            
            if (!data.history || data.history.length === 0) {
                tbody.append('<tr><td colspan="7" style="text-align: center;"><?php esc_html_e('No records found', 'chinemerem-foods'); ?></td></tr>');
                return;
            }
            
            data.history.forEach(function(record) {
                tbody.append(`
                    <tr>
                        <td data-label="<?php esc_attr_e('Date', 'chinemerem-foods'); ?>">${record.record_date}</td>
                        <td data-label="<?php esc_attr_e('Time', 'chinemerem-foods'); ?>">${record.record_time}</td>
                        <td data-label="<?php esc_attr_e('Product', 'chinemerem-foods'); ?>">${record.product_name}</td>
                        <td data-label="<?php esc_attr_e('Quantity', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.quantity)}</td>
                        <td data-label="<?php esc_attr_e('Customer', 'chinemerem-foods'); ?>">${record.customer_name || '-'}</td>
                        <td data-label="<?php esc_attr_e('Remark', 'chinemerem-foods'); ?>">${record.remark || '-'}</td>
                        <td data-label="<?php esc_attr_e('Staff', 'chinemerem-foods'); ?>">${record.staff_name || '-'}</td>
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
