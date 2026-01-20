<?php
/**
 * Import Record Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$products = CFI_Products::get_all();
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
                <i class="fas fa-truck-loading"></i>
                <?php esc_html_e('Import Record', 'chinemerem-foods'); ?>
            </h1>
            <div class="cfi-page-actions">
                <?php
                $import_history = get_page_by_path('cfi-import-history');
                $history_url = $import_history ? get_permalink($import_history->ID) : home_url('/import-history/');
                ?>
                <a href="<?php echo esc_url($history_url); ?>" class="cfi-btn-history-custom">
                    <i class="fas fa-history"></i>
                    <?php esc_html_e('View History', 'chinemerem-foods'); ?>
                </a>
            </div>
        </div>
        
        <div id="cfi-import-form" class="cfi-glass">
            <h3><?php esc_html_e('Record Import', 'chinemerem-foods'); ?></h3>
            
            <div class="cfi-form-row" style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="cfi-form-group" style="flex: 1; min-width: 200px; margin: 0;">
                    <label for="import-sender"><?php esc_html_e('Sender', 'chinemerem-foods'); ?></label>
                    <input type="text" id="import-sender" class="cfi-input" placeholder="<?php esc_attr_e('Enter sender name...', 'chinemerem-foods'); ?>" required>
                </div>
                <div class="cfi-form-group" style="flex: 1; min-width: 200px; margin: 0;">
                    <label for="import-driver"><?php esc_html_e('Driver\'s Name', 'chinemerem-foods'); ?></label>
                    <input type="text" id="import-driver" class="cfi-input" placeholder="<?php esc_attr_e('Enter driver name...', 'chinemerem-foods'); ?>" required>
                </div>
            </div>
            
            <div class="cfi-table-wrapper">
                <table id="cfi-import-table" class="cfi-table cfi-table-responsive">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Item', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Quantity', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Remark', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product) : ?>
                        <tr data-product-id="<?php echo esc_attr($product->id); ?>">
                            <td data-label="<?php esc_attr_e('Item', 'chinemerem-foods'); ?>"><?php echo esc_html($product->name); ?></td>
                            <td data-label="<?php esc_attr_e('Quantity', 'chinemerem-foods'); ?>">
                                <input type="number" class="cfi-input cfi-import-qty" min="0" step="0.01" value="0">
                            </td>
                            <td data-label="<?php esc_attr_e('Remark', 'chinemerem-foods'); ?>">
                                <input type="text" class="cfi-input cfi-import-remark" placeholder="<?php esc_attr_e('Optional remark...', 'chinemerem-foods'); ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="button" id="cfi-submit-import" class="cfi-btn cfi-btn-success">
                    <i class="fas fa-save"></i>
                    <?php esc_html_e('Submit Import Record', 'chinemerem-foods'); ?>
                </button>
            </div>
        </div>
    </div>
</main>

<script>
jQuery(document).ready(function($) {
    $('#cfi-submit-import').on('click', function() {
        const btn = $(this);
        
        // Validate no negative values
        if (!CFI.utils.validateNoNegatives('#cfi-import-table')) {
            return;
        }
        
        const sender = ($('#import-sender').val() || '').trim();
        const driver = ($('#import-driver').val() || '').trim();

        if (!sender || !driver) {
            CFI.toast.error('Sender and driver names are required');
            if (!sender) {
                $('#import-sender').focus();
            } else {
                $('#import-driver').focus();
            }
            return;
        }
        const imports = [];
        
        $('#cfi-import-table tbody tr').each(function() {
            const row = $(this);
            const qty = parseFloat(row.find('.cfi-import-qty').val()) || 0;
            
            if (qty > 0) {
                imports.push({
                    product_id: row.data('product-id'),
                    quantity: qty,
                    sender: sender,
                    driver_name: driver,
                    remark: row.find('.cfi-import-remark').val()
                });
            }
        });
        
        if (imports.length === 0) {
            CFI.toast.warning('Please enter at least one quantity');
            return;
        }
        
        btn.prop('disabled', true).text('Submitting...');
        
        CFI.ajax.request('add_import', {
            imports: JSON.stringify(imports)
        }).then(function(data) {
            btn.prop('disabled', false).html('<i class="fas fa-save"></i> Submit Import Record');
            CFI.successPopup.show({
                title: 'Import Recorded!',
                message: data.message || 'Import record has been saved successfully.',
                details: {
                    'Items Recorded': imports.length,
                    'Sender': sender || 'N/A',
                    'Driver': driver || 'N/A'
                }
            });
        }).catch(function(error) {
            CFI.toast.error(error);
            btn.prop('disabled', false).html('<i class="fas fa-save"></i> Submit Import Record');
        });
    });
});
</script>
