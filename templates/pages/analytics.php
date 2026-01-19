<?php
/**
 * Analytics Overview Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!CFI_Auth::is_cfi_admin()) {
    echo '<div class="cfi-main"><div class="cfi-container"><div class="cfi-glass" style="text-align: center; padding: 3rem;"><i class="fa-solid fa-lock" style="font-size: 3rem; color: #dc2626; margin-bottom: 1rem;"></i><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div></div></div>';
    return;
}

$period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : 'daily';
$sanitize_date = function($value) {
    if (empty($value)) {
        return '';
    }
    $value = sanitize_text_field($value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
};
$start_date = isset($_GET['start_date']) ? $sanitize_date(wp_unslash($_GET['start_date'])) : '';
$end_date = isset($_GET['end_date']) ? $sanitize_date(wp_unslash($_GET['end_date'])) : '';
$summary = CFI_Financial::get_analytics_summary($period, $start_date, $end_date);
$range = $summary['range'] ?? array();
$range_display = '';
if (!empty($range['start_display']) && !empty($range['end_display'])) {
    $range_display = $range['start_display'] . ' - ' . $range['end_display'];
} elseif (!empty($range['start_display'])) {
    $range_display = $range['start_display'];
} elseif (!empty($range['end_display'])) {
    $range_display = $range['end_display'];
}

$format_currency = function($value) {
    return '₦' . number_format((float) $value, 2);
};
$format_number = function($value) {
    return number_format((float) $value, 0);
};
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    .cfi-analytics-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .cfi-analytics-filters {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    .cfi-analytics-range {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        color: #64748b;
        font-size: 0.75rem;
    }
    .cfi-analytics-range strong {
        color: #001943;
        font-size: 0.8rem;
    }
    .cfi-analytics-date-group {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .cfi-analytics-date-group .cfi-input {
        min-width: 140px;
    }
    .cfi-analytics-loading {
        display: none;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        color: #001943;
    }
    .cfi-analytics-loading.visible {
        display: inline-flex;
    }
    .cfi-analytics-section {
        margin-top: 1.5rem;
    }
    .cfi-analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    .cfi-analytics-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 1rem;
        border: 2px solid rgba(0, 25, 67, 0.08);
        box-shadow: 0 6px 18px rgba(0, 25, 67, 0.08);
        text-align: center;
    }
    .cfi-analytics-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #001943;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    .cfi-analytics-icon::before {
        content: attr(data-icon);
        font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
        font-size: 1rem;
        line-height: 1;
    }
    .cfi-analytics-icon i {
        display: none;
    }
    .cfi-analytics-icon i,
    .cfi-analytics-header i,
    .cfi-analytics-section h3 i,
    .cfi-analytics-loading i {
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
    }
    .cfi-analytics-value {
        font-size: 1.15rem;
        font-weight: 700;
        color: #001943;
    }
    .cfi-analytics-label {
        font-size: 0.7rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    @media (max-width: 768px) {
        .cfi-analytics-filters {
            flex-direction: column;
            align-items: flex-start;
        }
        .cfi-analytics-range {
            align-items: flex-start;
        }
    }
</style>

<main class="cfi-main">
    <div class="cfi-container">
        <div class="cfi-page-title cfi-analytics-header">
            <h1>
                <i class="fas fa-chart-line"></i>
                <?php esc_html_e('Analytics Overview', 'chinemerem-foods'); ?>
            </h1>
            <span class="cfi-analytics-loading" id="cfi-analytics-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <?php esc_html_e('Refreshing metrics...', 'chinemerem-foods'); ?>
            </span>
        </div>

        <div class="cfi-glass cfi-analytics-filters">
            <div class="cfi-filter-group">
                <label for="cfi-analytics-period"><?php esc_html_e('Filter Period', 'chinemerem-foods'); ?></label>
                <select id="cfi-analytics-period" class="cfi-select">
                    <option value="daily" <?php selected($period, 'daily'); ?>><?php esc_html_e('Daily', 'chinemerem-foods'); ?></option>
                    <option value="weekly" <?php selected($period, 'weekly'); ?>><?php esc_html_e('Weekly', 'chinemerem-foods'); ?></option>
                    <option value="monthly" <?php selected($period, 'monthly'); ?>><?php esc_html_e('Monthly', 'chinemerem-foods'); ?></option>
                </select>
            </div>
            <div class="cfi-filter-group">
                <label><?php esc_html_e('Date Range', 'chinemerem-foods'); ?></label>
                <div class="cfi-analytics-date-group">
                    <input type="date" id="cfi-analytics-start" class="cfi-input" value="<?php echo esc_attr($range['start_date'] ?? ''); ?>">
                    <input type="date" id="cfi-analytics-end" class="cfi-input" value="<?php echo esc_attr($range['end_date'] ?? ''); ?>">
                </div>
            </div>
            <div class="cfi-filter-group">
                <label>&nbsp;</label>
                <button type="button" id="cfi-analytics-apply" class="cfi-btn cfi-btn-primary">
                    <?php esc_html_e('Apply Dates', 'chinemerem-foods'); ?>
                </button>
            </div>
            <div class="cfi-analytics-range">
                <strong id="cfi-analytics-range-label"><?php echo esc_html($range['label'] ?? ''); ?></strong>
                <span id="cfi-analytics-range-dates">
                    <?php echo esc_html($range_display); ?>
                </span>
                <span><?php esc_html_e('Completed records only', 'chinemerem-foods'); ?></span>
            </div>
        </div>

        <div class="cfi-glass cfi-analytics-section">
            <h3><i class="fas fa-shopping-cart" style="color: #16a34a;"></i> <?php esc_html_e('Order Performance', 'chinemerem-foods'); ?></h3>
            <div class="cfi-analytics-grid">
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🧾"><i class="fas fa-receipt"></i></div>
                    <div class="cfi-analytics-value" data-analytics="orders.total_orders" data-format="number"><?php echo esc_html($format_number($summary['orders']['total_orders'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Total Orders', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="💵"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="cfi-analytics-value" data-analytics="orders.cash_orders" data-format="number"><?php echo esc_html($format_number($summary['orders']['cash_orders'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Cash Orders', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🕒"><i class="fas fa-user-clock"></i></div>
                    <div class="cfi-analytics-value" data-analytics="orders.credit_orders" data-format="number"><?php echo esc_html($format_number($summary['orders']['credit_orders'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Credit Orders', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="💰"><i class="fas fa-coins"></i></div>
                    <div class="cfi-analytics-value" data-analytics="orders.total_sales" data-format="currency"><?php echo esc_html($format_currency($summary['orders']['total_sales'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Total Sales', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="👛"><i class="fas fa-wallet"></i></div>
                    <div class="cfi-analytics-value" data-analytics="orders.cash_received" data-format="currency"><?php echo esc_html($format_currency($summary['orders']['cash_received'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Cash Received', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="💳"><i class="fas fa-credit-card"></i></div>
                    <div class="cfi-analytics-value" data-analytics="orders.transfer_sales" data-format="currency"><?php echo esc_html($format_currency($summary['orders']['transfer_sales'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Transfer Sales', 'chinemerem-foods'); ?></div>
                </div>
            </div>
        </div>

        <div class="cfi-glass cfi-analytics-section">
            <h3><i class="fas fa-user-clock" style="color: #f59e0b;"></i> <?php esc_html_e('Debtors Performance', 'chinemerem-foods'); ?></h3>
            <div class="cfi-analytics-grid">
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🧾"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="cfi-analytics-value" data-analytics="debtors.orders_total" data-format="currency"><?php echo esc_html($format_currency($summary['debtors']['orders_total'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Debtor Orders Value', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="📋"><i class="fas fa-list-check"></i></div>
                    <div class="cfi-analytics-value" data-analytics="debtors.orders_count" data-format="number"><?php echo esc_html($format_number($summary['debtors']['orders_count'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Debtor Orders Count', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="💰"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="cfi-analytics-value" data-analytics="debtors.payments_total" data-format="currency"><?php echo esc_html($format_currency($summary['debtors']['payments_total'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Payments Received', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="💵"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="cfi-analytics-value" data-analytics="debtors.payments_cash" data-format="currency"><?php echo esc_html($format_currency($summary['debtors']['payments_cash'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Payments Cash', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🏦"><i class="fas fa-university"></i></div>
                    <div class="cfi-analytics-value" data-analytics="debtors.payments_transfer" data-format="currency"><?php echo esc_html($format_currency($summary['debtors']['payments_transfer'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Payments Transfer', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🏠"><i class="fas fa-house-user"></i></div>
                    <div class="cfi-analytics-value" data-analytics="debtors.payments_home" data-format="currency"><?php echo esc_html($format_currency($summary['debtors']['payments_home'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Home Calculations', 'chinemerem-foods'); ?></div>
                </div>
            </div>
        </div>

        <div class="cfi-glass cfi-analytics-section">
            <h3><i class="fas fa-file-invoice-dollar" style="color: #dc2626;"></i> <?php esc_html_e('Expenses', 'chinemerem-foods'); ?></h3>
            <div class="cfi-analytics-grid">
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🧾"><i class="fas fa-receipt"></i></div>
                    <div class="cfi-analytics-value" data-analytics="expenses.total_amount" data-format="currency"><?php echo esc_html($format_currency($summary['expenses']['total_amount'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Total Expenses', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="📋"><i class="fas fa-list"></i></div>
                    <div class="cfi-analytics-value" data-analytics="expenses.total_count" data-format="number"><?php echo esc_html($format_number($summary['expenses']['total_count'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Expense Entries', 'chinemerem-foods'); ?></div>
                </div>
            </div>
        </div>

        <div class="cfi-glass cfi-analytics-section">
            <h3><i class="fas fa-exchange-alt" style="color: #0ea5e9;"></i> <?php esc_html_e('Transfers', 'chinemerem-foods'); ?></h3>
            <div class="cfi-analytics-grid">
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🔁"><i class="fas fa-right-left"></i></div>
                    <div class="cfi-analytics-value" data-analytics="transfers.total_amount" data-format="currency"><?php echo esc_html($format_currency($summary['transfers']['total_amount'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Total Transfers', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🛒"><i class="fas fa-cart-shopping"></i></div>
                    <div class="cfi-analytics-value" data-analytics="transfers.orders_amount" data-format="currency"><?php echo esc_html($format_currency($summary['transfers']['orders_amount'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Order Transfers', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🧑‍💼"><i class="fas fa-user-clock"></i></div>
                    <div class="cfi-analytics-value" data-analytics="transfers.debtors_amount" data-format="currency"><?php echo esc_html($format_currency($summary['transfers']['debtors_amount'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Debtor Transfers', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🐖"><i class="fas fa-piggy-bank"></i></div>
                    <div class="cfi-analytics-value" data-analytics="transfers.cashout_amount" data-format="currency"><?php echo esc_html($format_currency($summary['transfers']['cashout_amount'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Cash Out Transfers', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="✅"><i class="fas fa-clipboard-check"></i></div>
                    <div class="cfi-analytics-value" data-analytics="transfers.total_count" data-format="number"><?php echo esc_html($format_number($summary['transfers']['total_count'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Transfer Entries', 'chinemerem-foods'); ?></div>
                </div>
            </div>
        </div>

        <div class="cfi-glass cfi-analytics-section">
            <h3><i class="fas fa-university" style="color: #6366f1;"></i> <?php esc_html_e('Cash Out', 'chinemerem-foods'); ?></h3>
            <div class="cfi-analytics-grid">
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="🏦"><i class="fas fa-building-columns"></i></div>
                    <div class="cfi-analytics-value" data-analytics="cashout.total_amount" data-format="currency"><?php echo esc_html($format_currency($summary['cashout']['total_amount'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Total Cash Out', 'chinemerem-foods'); ?></div>
                </div>
                <div class="cfi-analytics-card">
                    <div class="cfi-analytics-icon" data-icon="📋"><i class="fas fa-list-ol"></i></div>
                    <div class="cfi-analytics-value" data-analytics="cashout.total_count" data-format="number"><?php echo esc_html($format_number($summary['cashout']['total_count'] ?? 0)); ?></div>
                    <div class="cfi-analytics-label"><?php esc_html_e('Cash Out Entries', 'chinemerem-foods'); ?></div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    jQuery(document).ready(function($) {
        const summaryData = <?php echo wp_json_encode($summary, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const $startInput = $('#cfi-analytics-start');
        const $endInput = $('#cfi-analytics-end');
        const currencyLocale = {
            style: 'currency',
            currency: 'NGN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        };
        const numberLocale = { maximumFractionDigits: 0 };

        function getValue(source, path) {
            return path.split('.').reduce(function(accumulator, key) {
                if (accumulator && Object.prototype.hasOwnProperty.call(accumulator, key)) {
                    return accumulator[key];
                }
                return null;
            }, source);
        }

        function formatValue(value, format) {
            const numeric = parseFloat(value) || 0;
            if (format === 'currency') {
                if (typeof CFI !== 'undefined' && CFI.utils && CFI.utils.formatCurrency) {
                    return CFI.utils.formatCurrency(numeric);
                }
                return numeric.toLocaleString('en-NG', currencyLocale);
            }
            if (typeof CFI !== 'undefined' && CFI.utils && CFI.utils.formatNumber) {
                return CFI.utils.formatNumber(numeric);
            }
            return numeric.toLocaleString('en-NG', numberLocale);
        }

        function updateSummary(summary) {
            if (!summary) {
                return;
            }

            const range = summary.range || {};
            $('#cfi-analytics-range-label').text(range.label || '');
            if (range.start_display && range.end_display) {
                $('#cfi-analytics-range-dates').text(range.start_display + ' - ' + range.end_display);
            } else if (range.start_display) {
                $('#cfi-analytics-range-dates').text(range.start_display);
            } else if (range.end_display) {
                $('#cfi-analytics-range-dates').text(range.end_display);
            }
            if (range.start_date) {
                $startInput.val(range.start_date);
            }
            if (range.end_date) {
                $endInput.val(range.end_date);
            }

            $('[data-analytics]').each(function() {
                const key = $(this).data('analytics');
                const format = $(this).data('format');
                const value = getValue(summary, key);
                $(this).text(formatValue(value, format));
            });
        }

        function setLoading(isLoading) {
            $('#cfi-analytics-loading').toggleClass('visible', isLoading);
        }

        function updateUrl(period, startDate, endDate) {
            const url = new URL(window.location.href);
            if (period) {
                url.searchParams.set('period', period);
            }
            if (startDate) {
                url.searchParams.set('start_date', startDate);
            } else {
                url.searchParams.delete('start_date');
            }
            if (endDate) {
                url.searchParams.set('end_date', endDate);
            } else {
                url.searchParams.delete('end_date');
            }
            window.history.replaceState({}, '', url.toString());
        }

        function fetchSummary(period, dates) {
            setLoading(true);
            const payload = { period: period };
            if (dates && dates.start) {
                payload.start_date = dates.start;
            }
            if (dates && dates.end) {
                payload.end_date = dates.end;
            }
            CFI.ajax.request('get_analytics_summary', payload)
                .then(function(data) {
                    if (data && data.summary) {
                        updateSummary(data.summary);
                    }
                })
                .catch(function(error) {
                    if (CFI.toast) {
                        CFI.toast.error(error);
                    } else {
                        alert(error);
                    }
                })
                .finally(function() {
                    setLoading(false);
                });
        }

        function getDateFilters() {
            return {
                start: $startInput.val(),
                end: $endInput.val()
            };
        }

        $('#cfi-analytics-period').on('change', function() {
            const period = $(this).val();
            const dates = getDateFilters();
            updateUrl(period, dates.start, dates.end);
            fetchSummary(period, dates);
        });

        $('#cfi-analytics-apply').on('click', function() {
            const period = $('#cfi-analytics-period').val();
            const dates = getDateFilters();
            updateUrl(period, dates.start, dates.end);
            fetchSummary(period, dates);
        });

        $startInput.add($endInput).on('change', function() {
            const period = $('#cfi-analytics-period').val();
            const dates = getDateFilters();
            updateUrl(period, dates.start, dates.end);
            fetchSummary(period, dates);
        });

        updateSummary(summaryData);
    });
</script>
