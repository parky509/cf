<?php
/**
 * Cash Out History Page Template
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
                <?php esc_html_e('Cash Out Transfer History', 'chinemerem-foods'); ?>
            </h1>
            <div class="cfi-page-actions">
                <?php $cash_out = get_page_by_path('cfi-cash-out'); ?>
                <?php if ($cash_out) : ?>
                <a href="<?php echo esc_url(get_permalink($cash_out->ID)); ?>" class="cfi-btn cfi-btn-primary cfi-btn-sm">
                    <i class="fas fa-money-bill-wave"></i>
                    <?php esc_html_e('Record Cash Out', 'chinemerem-foods'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="cfi-filters cfi-glass">
            <div class="cfi-filter-group">
                <label for="cfi-history-start"><?php esc_html_e('From:', 'chinemerem-foods'); ?></label>
                <input type="date" id="cfi-history-start" class="cfi-input" value="<?php echo esc_attr(date('Y-m-d', strtotime('-30 days'))); ?>">
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
        
        <div id="cfi-cashout-history" class="cfi-glass">
            <div class="cfi-table-wrapper">
                <table id="cfi-history-table" class="cfi-table cfi-table-responsive">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Time', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Amount', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Bank', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Staff', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated by JavaScript -->
                    </tbody>
                    <tfoot id="cfi-history-total">
                    </tfoot>
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
        
        CFI.ajax.request('get_transfer_history', {
            start_date: startDate,
            end_date: endDate,
            source: 'cashout'
        }).then(function(data) {
            const tbody = $('#cfi-history-table tbody');
            const tfoot = $('#cfi-history-total');
            tbody.empty();
            tfoot.empty();
            
            if (!data.history || data.history.length === 0) {
                tbody.append('<tr><td colspan="5" style="text-align: center;"><?php esc_html_e('No cash out records found', 'chinemerem-foods'); ?></td></tr>');
                return;
            }
            
            let total = 0;
            
            data.history.forEach(function(record) {
                total += parseFloat(record.amount);
                
                tbody.append(`
                    <tr>
                        <td data-label="<?php esc_attr_e('Date', 'chinemerem-foods'); ?>">${record.transfer_date}</td>
                        <td data-label="<?php esc_attr_e('Time', 'chinemerem-foods'); ?>">${record.transfer_time}</td>
                        <td data-label="<?php esc_attr_e('Amount', 'chinemerem-foods'); ?>">
                            <strong style="color: var(--cfi-danger);">${CFI.utils.formatCurrency(record.amount)}</strong>
                        </td>
                        <td data-label="<?php esc_attr_e('Bank', 'chinemerem-foods'); ?>">
                            <i class="fas fa-university"></i> ${record.bank_name || '-'}
                        </td>
                        <td data-label="<?php esc_attr_e('Staff', 'chinemerem-foods'); ?>">${record.staff_name || '-'}</td>
                    </tr>
                `);
            });
            
            tfoot.append(`
                <tr style="background: var(--cfi-primary); color: white;">
                    <td colspan="2"><strong><?php esc_html_e('Total Cash Out', 'chinemerem-foods'); ?></strong></td>
                    <td colspan="3"><strong style="font-size: 1.25rem;">${CFI.utils.formatCurrency(total)}</strong></td>
                </tr>
            `);
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
