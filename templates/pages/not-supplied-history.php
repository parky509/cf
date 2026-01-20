<?php
/**
 * Not Supplied History Page Template
 * 
 * Action column has checkbox to mark as supplied
 * Status column comes after Action to show the final result
 * Once supplied, the row is disabled for further changes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
/* Checkbox styling for supplied action */
.cfi-supply-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--cfi-success, #28a745);
}
.cfi-supply-checkbox:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
.cfi-row-supplied {
    background-color: rgba(40, 167, 69, 0.1) !important;
    opacity: 0.8;
}
.cfi-row-supplied td {
    color: #666;
}
</style>

<main class="cfi-main">
    <div class="cfi-container">
        <div class="cfi-page-title">
            <h1>
                <i class="fas fa-history"></i>
                <?php esc_html_e('Not Supplied History', 'chinemerem-foods'); ?>
            </h1>
            <div class="cfi-page-actions">
                <?php 
                $not_supplied_page = get_page_by_path('not-supplied');
                $not_supplied_url = $not_supplied_page ? get_permalink($not_supplied_page->ID) : home_url('/not-supplied/');
                ?>
                <a href="<?php echo esc_url($not_supplied_url); ?>" class="cfi-btn cfi-btn-primary cfi-btn-sm">
                    <i class="fas fa-times-circle"></i>
                    <?php esc_html_e('Today\'s Record', 'chinemerem-foods'); ?>
                </a>
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
        
        <div id="cfi-not-supplied-history" class="cfi-glass">
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
                            <th><?php esc_html_e('Action', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Status', 'chinemerem-foods'); ?></th>
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
        
        CFI.ajax.request('get_not_supplied_history', {
            start_date: startDate,
            end_date: endDate
        }).then(function(data) {
            const tbody = $('#cfi-history-table tbody');
            tbody.empty();
            
            if (!data.history || data.history.length === 0) {
                tbody.append('<tr><td colspan="9" style="text-align: center;"><?php esc_html_e('No records found', 'chinemerem-foods'); ?></td></tr>');
                return;
            }
            
            data.history.forEach(function(record) {
                const isSupplied = record.is_supplied == 1;
                
                // Status column shows final result (comes last)
                const statusHtml = isSupplied 
                    ? '<span style="color: var(--cfi-success); font-weight: bold;"><i class="fas fa-check-circle"></i> <?php esc_html_e('Supplied', 'chinemerem-foods'); ?></span>'
                    : '<span style="color: var(--cfi-warning);"><i class="fas fa-clock"></i> <?php esc_html_e('Pending', 'chinemerem-foods'); ?></span>';
                
                // Action column has checkbox (empty if not supplied, checked and disabled if supplied)
                const actionHtml = isSupplied 
                    ? `<input type="checkbox" class="cfi-supply-checkbox" data-id="${record.id}" checked disabled title="<?php esc_attr_e('Already supplied', 'chinemerem-foods'); ?>">`
                    : `<input type="checkbox" class="cfi-supply-checkbox" data-id="${record.id}" title="<?php esc_attr_e('Click to mark as supplied', 'chinemerem-foods'); ?>">`;
                
                const rowClass = isSupplied ? 'cfi-row-supplied' : '';
                
                tbody.append(`
                    <tr class="${rowClass}" data-record-id="${record.id}">
                        <td data-label="<?php esc_attr_e('Date', 'chinemerem-foods'); ?>">${record.record_date}</td>
                        <td data-label="<?php esc_attr_e('Time', 'chinemerem-foods'); ?>">${record.record_time}</td>
                        <td data-label="<?php esc_attr_e('Product', 'chinemerem-foods'); ?>">${record.product_name}</td>
                        <td data-label="<?php esc_attr_e('Quantity', 'chinemerem-foods'); ?>">${CFI.utils.formatNumber(record.quantity)}</td>
                        <td data-label="<?php esc_attr_e('Customer', 'chinemerem-foods'); ?>">${record.customer_name || '-'}</td>
                        <td data-label="<?php esc_attr_e('Remark', 'chinemerem-foods'); ?>">${record.remark || '-'}</td>
                        <td data-label="<?php esc_attr_e('Staff', 'chinemerem-foods'); ?>">${record.staff_name || '-'}</td>
                        <td data-label="<?php esc_attr_e('Action', 'chinemerem-foods'); ?>">${actionHtml}</td>
                        <td data-label="<?php esc_attr_e('Status', 'chinemerem-foods'); ?>">${statusHtml}</td>
                    </tr>
                `);
            });
        }).catch(function(error) {
            CFI.toast.error(error);
        });
    }
    
    // Handle checkbox change to mark as supplied
    $(document).on('change', '.cfi-supply-checkbox:not(:disabled)', function() {
        const checkbox = $(this);
        const id = checkbox.data('id');
        const row = checkbox.closest('tr');
        
        if (!checkbox.is(':checked')) {
            // Prevent unchecking
            return;
        }
        
        if (!confirm('<?php esc_html_e('Mark this item as supplied? This action cannot be undone.', 'chinemerem-foods'); ?>')) {
            checkbox.prop('checked', false);
            return;
        }
        
        // Disable checkbox immediately while processing
        checkbox.prop('disabled', true);
        
        CFI.ajax.request('mark_as_supplied', { id: id }).then(function(data) {
            CFI.toast.success(data.message || '<?php esc_html_e('Item marked as supplied', 'chinemerem-foods'); ?>');
            
            // Update the row to show supplied status
            row.addClass('cfi-row-supplied');
            
            // Update status column (last column)
            row.find('td:last').html('<span style="color: var(--cfi-success); font-weight: bold;"><i class="fas fa-check-circle"></i> <?php esc_html_e('Supplied', 'chinemerem-foods'); ?></span>');
            
            // Checkbox stays checked and disabled
            checkbox.attr('title', '<?php esc_attr_e('Already supplied', 'chinemerem-foods'); ?>');
            
        }).catch(function(error) {
            CFI.toast.error(error);
            // Revert checkbox state on error
            checkbox.prop('checked', false);
            checkbox.prop('disabled', false);
        });
    });
    
    $('#cfi-load-history').on('click', function() {
        loadHistory();
    });
    
    loadHistory();
});
</script>
