<?php
/**
 * Cash Out Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$today = current_time('Y-m-d');
$cashout_records = CFI_Financial::get_cashout($today);
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
                <i class="fas fa-money-bill-wave"></i>
                <?php esc_html_e('Cash Out Record', 'chinemerem-foods'); ?>
            </h1>
            <div class="cfi-page-actions">
                <?php
                $cashout_history = get_page_by_path('cfi-cash-out-history');
                $history_url = $cashout_history ? get_permalink($cashout_history->ID) : home_url('/cash-out-history/');
                ?>
                <a href="<?php echo esc_url($history_url); ?>" class="cfi-btn-history-custom">
                    <i class="fas fa-history"></i>
                    <?php esc_html_e('View History', 'chinemerem-foods'); ?>
                </a>
            </div>
        </div>
        
        <div class="cfi-glass" style="margin-bottom: 1.5rem;">
            <h3><?php esc_html_e('Record Cash Out', 'chinemerem-foods'); ?></h3>
            <form id="cfi-cashout-form" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                <div class="cfi-form-group" style="flex: 1; min-width: 150px; margin: 0;">
                    <label for="cashout-amount"><?php esc_html_e('Amount (₦)', 'chinemerem-foods'); ?></label>
                    <input type="number" id="cashout-amount" class="cfi-input" min="0" step="0.01" required>
                </div>
                <div class="cfi-form-group" style="flex: 1; min-width: 200px; margin: 0;">
                    <label><?php esc_html_e('Bank', 'chinemerem-foods'); ?></label>
                    <div class="cfi-bank-options" style="display: block; background: none; padding: 0;">
                        <label class="cfi-bank-option">
                            <input type="radio" name="cashout-bank" value="Moniepoint MFB" checked>
                            <span><?php esc_html_e('Moniepoint MFB', 'chinemerem-foods'); ?></span>
                        </label>
                        <label class="cfi-bank-option">
                            <input type="radio" name="cashout-bank" value="Access Bank PLC">
                            <span><?php esc_html_e('Access Bank PLC', 'chinemerem-foods'); ?></span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="cfi-btn cfi-btn-warning">
                    <i class="fas fa-paper-plane"></i>
                    <?php esc_html_e('Register Cash Out', 'chinemerem-foods'); ?>
                </button>
            </form>
        </div>
        
        <div class="cfi-glass">
            <h3><?php esc_html_e('Today\'s Cash Out Records', 'chinemerem-foods'); ?></h3>
            
            <div class="cfi-table-wrapper">
                <table class="cfi-table cfi-table-responsive">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Time', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Amount (₦)', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Bank', 'chinemerem-foods'); ?></th>
                            <th><?php esc_html_e('Staff', 'chinemerem-foods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cashout_records)) : ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem;">
                                <?php esc_html_e('No cash out records today', 'chinemerem-foods'); ?>
                            </td>
                        </tr>
                        <?php else : ?>
                        <?php 
                        $total = 0;
                        foreach ($cashout_records as $record) : 
                            $total += $record->amount;
                        ?>
                        <tr>
                            <td data-label="<?php esc_attr_e('Time', 'chinemerem-foods'); ?>"><?php echo esc_html(substr($record->cashout_time, 0, 5)); ?></td>
                            <td data-label="<?php esc_attr_e('Amount', 'chinemerem-foods'); ?>"><?php echo esc_html(CFI_Products::format_price($record->amount)); ?></td>
                            <td data-label="<?php esc_attr_e('Bank', 'chinemerem-foods'); ?>"><?php echo esc_html($record->bank_name); ?></td>
                            <td data-label="<?php esc_attr_e('Staff', 'chinemerem-foods'); ?>"><?php echo esc_html($record->staff_name); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($cashout_records)) : ?>
                    <tfoot>
                        <tr style="background: var(--cfi-primary); color: var(--cfi-white);">
                            <td><strong><?php esc_html_e('Total', 'chinemerem-foods'); ?></strong></td>
                            <td colspan="3"><strong><?php echo esc_html(CFI_Products::format_price($total)); ?></strong></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
jQuery(document).ready(function($) {
    $('#cfi-cashout-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const amount = parseFloat($('#cashout-amount').val()) || 0;
        const bank = $('input[name="cashout-bank"]:checked').val();
        
        // Validate no negative values
        if (amount < 0) {
            CFI.negativeValuePopup.show(['Amount']);
            return;
        }
        
        if (amount <= 0) {
            CFI.toast.warning('Please enter a valid amount');
            return;
        }
        
        btn.prop('disabled', true).text('Processing...');
        
        CFI.ajax.request('add_cashout', {
            amount: amount,
            bank_name: bank
        }).then(function(data) {
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Register Cash Out');
            CFI.successPopup.show({
                title: 'Cash Out Recorded!',
                message: data.message || 'Cash out has been recorded successfully.',
                details: {
                    'Amount': '₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 0}),
                    'Bank': bank
                }
            });
        }).catch(function(error) {
            CFI.toast.error(error);
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Register Cash Out');
        });
    });
});
</script>
