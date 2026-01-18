<?php
/**
 * Financial Summary Page Template - ULTIMATE FIX
 * ALWAYS calculates from source data using direct SQL queries
 * Uses mysqli/PDO directly to bypass all WordPress caching
 */

if (!defined('ABSPATH')) {
    exit;
}

// ========================================
// ANTI-CACHING HEADERS - MAXIMUM FORCE
// ========================================
if (!headers_sent()) {
    header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0, s-maxage=0, proxy-revalidate');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:01 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('ETag: "' . time() . '-' . mt_rand() . '"');
    header('Vary: *');
}

// Access global $wpdb
global $wpdb;

// ========================================
// ULTIMATE CACHE FLUSH - FORCE EVERYTHING
// ========================================
// Clear all WordPress caches
wp_cache_flush();
if (function_exists('wp_suspend_cache_addition')) {
    wp_suspend_cache_addition(true);
}
if (function_exists('wp_suspend_cache_invalidation')) {
    wp_suspend_cache_invalidation(true);
}

// Clear $wpdb caches
if (method_exists($wpdb, 'flush')) {
    $wpdb->flush();
}

// Force fresh database connection by closing and reopening
if (method_exists($wpdb, 'close')) {
    $wpdb->close();
}
if (method_exists($wpdb, 'check_connection')) {
    $wpdb->check_connection(false);
}

// Disable MySQL query cache for this session
$wpdb->query("SET SESSION query_cache_type = OFF");

// Clear any WordPress transients related to financial data
delete_transient('cfi_financial_summary');
delete_transient('cfi_daily_summary');

// ========================================
// TABLE NAMES
// ========================================
$today = current_time('Y-m-d');
$yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
$table_orders = $wpdb->prefix . 'cfi_orders';
$table_cashout = $wpdb->prefix . 'cfi_cashout';
$table_expenses = $wpdb->prefix . 'cfi_expenses';
$table_transactions = $wpdb->prefix . 'cfi_debtor_transactions';
$table_financial = $wpdb->prefix . 'cfi_financial_summary';

// ========================================
// ENSURE TODAY'S RECORD EXISTS WITH PROPER OLD_CASH
// This is CRITICAL for persistence between days
// ========================================
$today_exists = $wpdb->get_var("SELECT id FROM $table_financial WHERE record_date = '$today' LIMIT 1");

if (!$today_exists) {
    // Get yesterday's cash_left to use as today's old_cash
    $yesterday_cash_left = $wpdb->get_var("SELECT cash_left FROM $table_financial WHERE record_date = '$yesterday' LIMIT 1");
    $yesterday_cash_left = ($yesterday_cash_left !== null && $yesterday_cash_left !== false) ? floatval($yesterday_cash_left) : 0;
    
    // Create today's record with yesterday's cash_left as old_cash
    $wpdb->insert(
        $table_financial,
        array(
            'record_date' => $today,
            'total_sales' => 0,
            'transfer_from_orders' => 0,
            'transfer_from_cashout' => 0,
            'transfer_from_debtors' => 0,
            'debtors_cash' => 0,
            'expenses' => 0,
            'old_cash' => $yesterday_cash_left,
            'cash_to_bank' => 0,
            'cash_sales' => 0,
            'cash_left' => $yesterday_cash_left,
        ),
        array('%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f')
    );
}

// ========================================
// CALCULATE ALL VALUES FROM SOURCE DATA
// This ensures we ALWAYS show correct values
// ========================================

// ========================================
// DIRECT SQL QUERIES - BYPASS ALL CACHING
// Using direct SQL strings to bypass WordPress prepared statement caching
// ========================================

// 1. Get order totals (ONLY cash orders, NOT credit/debtor orders)
$query_total_sales = "SELECT IFNULL(SUM(grand_total), 0) FROM $table_orders WHERE order_date = '$today' AND status = 'completed' AND order_type = 'cash'";
$result_total_sales = $wpdb->get_var($query_total_sales);
$total_sales = ($result_total_sales !== null && $result_total_sales !== false) ? floatval($result_total_sales) : 0;

$query_transfer_orders = "SELECT IFNULL(SUM(transfer_amount), 0) FROM $table_orders WHERE order_date = '$today' AND status = 'completed' AND order_type = 'cash'";
$result_transfer_orders = $wpdb->get_var($query_transfer_orders);
$transfer_from_orders = ($result_transfer_orders !== null && $result_transfer_orders !== false) ? floatval($result_transfer_orders) : 0;

$query_cash_sales = "SELECT IFNULL(SUM(cash_amount), 0) FROM $table_orders WHERE order_date = '$today' AND status = 'completed' AND order_type = 'cash'";
$result_cash_sales = $wpdb->get_var($query_cash_sales);
$cash_sales = ($result_cash_sales !== null && $result_cash_sales !== false) ? floatval($result_cash_sales) : 0;

// 2. Get cash out total
$query_cashout = "SELECT IFNULL(SUM(amount), 0) FROM $table_cashout WHERE cashout_date = '$today'";
$result_cashout = $wpdb->get_var($query_cashout);
$transfer_from_cashout = ($result_cashout !== null && $result_cashout !== false) ? floatval($result_cashout) : 0;

// 3. Get expenses total
$query_expenses = "SELECT IFNULL(SUM(amount), 0) FROM $table_expenses WHERE expense_date = '$today'";
$result_expenses = $wpdb->get_var($query_expenses);
$expenses = ($result_expenses !== null && $result_expenses !== false) ? floatval($result_expenses) : 0;

// 4. Get debtor payment totals
$query_debtors_cash = "SELECT IFNULL(SUM(cash_amount), 0) FROM $table_transactions WHERE DATE(transaction_date) = '$today' AND transaction_type = 'payment'";
$result_debtors_cash = $wpdb->get_var($query_debtors_cash);
$debtors_cash = ($result_debtors_cash !== null && $result_debtors_cash !== false) ? floatval($result_debtors_cash) : 0;

$query_debtors_transfer = "SELECT IFNULL(SUM(transfer_amount), 0) FROM $table_transactions WHERE DATE(transaction_date) = '$today' AND transaction_type = 'payment'";
$result_debtors_transfer = $wpdb->get_var($query_debtors_transfer);
$transfer_from_debtors = ($result_debtors_transfer !== null && $result_debtors_transfer !== false) ? floatval($result_debtors_transfer) : 0;

// 5. Get old_cash and cash_to_bank from saved record (these are manual/persistent entries)
$query_saved = "SELECT old_cash, cash_to_bank FROM $table_financial WHERE record_date = '$today' LIMIT 1";
$saved_record = $wpdb->get_row($query_saved);

$old_cash = ($saved_record && isset($saved_record->old_cash)) ? floatval($saved_record->old_cash) : 0;
$cash_to_bank = ($saved_record && isset($saved_record->cash_to_bank)) ? floatval($saved_record->cash_to_bank) : 0;

// Debug logging - remove in production
error_log("CFI Financial Summary - Date: $today, Total Sales: $total_sales, Transfer Orders: $transfer_from_orders, Cash Sales: $cash_sales, Cashout: $transfer_from_cashout, Expenses: $expenses, Debtors Cash: $debtors_cash, Old Cash: $old_cash, Cash to Bank: $cash_to_bank");

// 6. Calculate cash_left using the formula
// cash_left = total_sales - transfer_from_orders - transfer_from_cashout + debtors_cash - expenses + old_cash - cash_to_bank
$cash_left = $total_sales - $transfer_from_orders - $transfer_from_cashout + $debtors_cash - $expenses + $old_cash - $cash_to_bank;

// 7. UPDATE the financial_summary table with the calculated values (for history persistence)
$wpdb->update(
    $table_financial,
    array(
        'total_sales' => $total_sales,
        'transfer_from_orders' => $transfer_from_orders,
        'transfer_from_cashout' => $transfer_from_cashout,
        'transfer_from_debtors' => $transfer_from_debtors,
        'debtors_cash' => $debtors_cash,
        'expenses' => $expenses,
        'cash_sales' => $cash_sales,
        'cash_left' => $cash_left,
    ),
    array('record_date' => $today),
    array('%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f'),
    array('%s')
);

// Unique page load ID for debugging
$page_load_id = time() . '_' . mt_rand(100000, 999999);
?>

<!-- NO-CACHE META TAGS to prevent browser caching -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="robots" content="noindex, nofollow, noarchive">

<!-- Unique page ID to detect stale cached pages -->
<input type="hidden" id="cfi-page-load-id" value="<?php echo esc_attr($page_load_id); ?>">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

<style>
/* Financial Summary Styles - Rebuilt */
.cfi-financial-container {
    max-width: 800px !important;
    margin: 0 auto !important;
    padding: 12px !important;
}

.cfi-financial-container.cfi-summary-loading .cfi-financial-value,
.cfi-financial-container.cfi-summary-loading .cfi-cash-left-value {
    visibility: hidden;
}

.cfi-financial-title {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex-wrap: wrap !important;
    gap: 10px !important;
    margin-bottom: 15px !important;
}

.cfi-financial-title h1 {
    color: #001943 !important;
    font-size: 0.75rem !important;
    margin: 0 !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    font-weight: 600 !important;
}

.cfi-financial-title h1 i {
    color: #001943 !important;
}

.cfi-history-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    padding: 6px 12px !important;
    background: #001943 !important;
    color: white !important;
    text-decoration: none !important;
    border-radius: 6px !important;
    font-size: 0.75rem !important;
    transition: all 0.3s ease !important;
}

.cfi-history-btn:hover {
    background: #002a6b !important;
    color: white !important;
    transform: translateY(-2px) !important;
}

.cfi-financial-card {
    background: rgba(255, 255, 255, 0.95) !important;
    border-radius: 12px !important;
    padding: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 25, 67, 0.15), 0 0 0 1px rgba(0, 25, 67, 0.1) !important;
    border: 2px solid rgba(0, 25, 67, 0.1) !important;
}

.cfi-financial-card h3 {
    color: #001943 !important;
    font-size: 0.95rem !important;
    margin: 0 0 12px 0 !important;
    padding-bottom: 10px !important;
    border-bottom: 2px solid rgba(0, 25, 67, 0.1) !important;
}

/* Financial Table */
.cfi-financial-table {
    width: 100% !important;
    border-collapse: collapse !important;
}

.cfi-financial-row {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 8px 12px !important;
    border-bottom: 1px solid rgba(0, 25, 67, 0.1) !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
}

.cfi-financial-row:nth-child(odd) {
    background: rgba(0, 25, 67, 0.02) !important;
}

.cfi-financial-row:last-child {
    border-bottom: none !important;
}

.cfi-financial-label {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    color: #001943 !important;
    font-weight: 600 !important;
    font-size: 0.75rem !important;
    flex: 1 !important;
    min-width: 150px !important;
}

.cfi-financial-label i {
    width: 22px !important;
    height: 22px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: #001943 !important;
    color: white !important;
    border-radius: 6px !important;
    font-size: 0.65rem !important;
}

.cfi-financial-value {
    font-size: 0.85rem !important;
    font-weight: 700 !important;
    color: #001943 !important;
    text-align: right !important;
    min-width: 90px !important;
}

.cfi-financial-value.positive {
    color: #10b981 !important;
}

.cfi-financial-value.negative {
    color: #ef4444 !important;
}

.cfi-financial-value.muted {
    color: #6b7280 !important;
    font-size: 0.75rem !important;
}

.cfi-financial-value.highlight {
    font-size: 0.95rem !important;
    color: #001943 !important;
}

/* Cash to Bank Input Row */
.cfi-input-row {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    justify-content: flex-end !important;
}

.cfi-financial-input {
    width: 100px !important;
    padding: 6px 8px !important;
    border: 2px solid rgba(0, 25, 67, 0.2) !important;
    border-radius: 6px !important;
    font-size: 0.8rem !important;
    font-weight: 600 !important;
    text-align: right !important;
    background: white !important;
    color: #001943 !important;
    transition: all 0.3s ease !important;
}

.cfi-financial-input:focus {
    outline: none !important;
    border-color: #001943 !important;
    box-shadow: 0 0 0 3px rgba(0, 25, 67, 0.1) !important;
}

.cfi-save-btn {
    padding: 6px 12px !important;
    background: #001943 !important;
    color: white !important;
    border: none !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    font-size: 0.75rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 4px !important;
    transition: all 0.3s ease !important;
}

.cfi-save-btn:hover {
    background: #002a6b !important;
    transform: translateY(-2px) !important;
}

.cfi-save-btn:disabled {
    background: #9ca3af !important;
    cursor: not-allowed !important;
    transform: none !important;
}

/* Cash Left Footer */
.cfi-cash-left-row {
    background: linear-gradient(135deg, #10b981, #059669) !important;
    border-radius: 10px !important;
    margin-top: 12px !important;
    padding: 12px 16px !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex-wrap: wrap !important;
    gap: 8px !important;
}

.cfi-cash-left-label {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    color: white !important;
    font-size: 0.85rem !important;
    font-weight: 700 !important;
}

.cfi-cash-left-label i {
    font-size: 1.1rem !important;
}

.cfi-cash-left-value {
    font-size: 1.25rem !important;
    font-weight: 800 !important;
    color: white !important;
}

/* Formula Box */
.cfi-formula-box {
    margin-top: 12px !important;
    padding: 10px 14px !important;
    background: rgba(0, 25, 67, 0.05) !important;
    border-radius: 10px !important;
    border-left: 3px solid #001943 !important;
}

.cfi-formula-box h4 {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    color: #001943 !important;
    margin: 0 0 6px 0 !important;
    font-size: 0.8rem !important;
}

.cfi-formula-box p {
    color: #4b5563 !important;
    margin: 0 !important;
    font-size: 0.7rem !important;
    line-height: 1.5 !important;
}

/* Refresh indicator */
.cfi-refresh-indicator {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    padding: 6px 12px !important;
    background: rgba(16, 185, 129, 0.1) !important;
    color: #10b981 !important;
    border-radius: 6px !important;
    font-size: 0.7rem !important;
    margin-bottom: 10px !important;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .cfi-financial-container {
        padding: 15px !important;
    }
    
    .cfi-financial-title {
        flex-direction: column !important;
        align-items: flex-start !important;
    }
    
    .cfi-financial-title h1 {
        font-size: 1.4rem !important;
    }
    
    .cfi-financial-card {
        padding: 15px !important;
    }
    
    .cfi-financial-row {
        flex-direction: column !important;
        align-items: flex-start !important;
        padding: 12px !important;
    }
    
    .cfi-financial-label {
        min-width: 100% !important;
        font-size: 0.85rem !important;
    }
    
    .cfi-financial-value {
        width: 100% !important;
        text-align: left !important;
        padding-left: 40px !important;
        font-size: 1rem !important;
    }
    
    .cfi-input-row {
        width: 100% !important;
        justify-content: flex-start !important;
        padding-left: 40px !important;
    }
    
    .cfi-financial-input {
        flex: 1 !important;
        max-width: 150px !important;
    }
    
    .cfi-cash-left-row {
        flex-direction: column !important;
        text-align: center !important;
        padding: 15px !important;
    }
    
    .cfi-cash-left-value {
        font-size: 1.5rem !important;
    }
}
</style>

<div class="cfi-financial-container">
    <div class="cfi-financial-title">
        <h1>
            <i class="fas fa-calculator"></i>
            Financial Summary
        </h1>
        <?php
        $financial_history = get_page_by_path('cfi-financial-history');
        $history_url = $financial_history ? get_permalink($financial_history->ID) : home_url('/financial-history/');
        ?>
        <a href="<?php echo esc_url($history_url); ?>" class="cfi-history-btn">
            <i class="fas fa-history"></i>
            View History
        </a>
    </div>
    
    <div class="cfi-financial-card">
        <h3><i class="fas fa-calendar-day"></i> Today's Summary - <?php echo esc_html(current_time('l, F j, Y')); ?></h3>
        
        <div class="cfi-refresh-indicator" id="cfi-auto-refresh">
            <i class="fas fa-sync-alt"></i>
            <span>Auto-refresh: Values update in real-time</span>
        </div>
        
        <div class="cfi-financial-table">
            <!-- Total Sales -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Total Sales (Take Order)</span>
                </div>
                <div class="cfi-financial-value highlight" id="val-total-sales" data-value="<?php echo esc_attr($total_sales); ?>">
                    ₦<?php echo number_format($total_sales, 2); ?>
                </div>
            </div>
            
            <!-- Transfer/Card from Orders -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-credit-card"></i>
                    <span>Transfer/Card (Orders)</span>
                </div>
                <div class="cfi-financial-value negative" id="val-transfer-orders" data-value="<?php echo esc_attr($transfer_from_orders); ?>">
                    -₦<?php echo number_format($transfer_from_orders, 2); ?>
                </div>
            </div>
            
            <!-- Cash Sales -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Cash Sales</span>
                </div>
                <div class="cfi-financial-value" id="val-cash-sales" data-value="<?php echo esc_attr($cash_sales); ?>">
                    ₦<?php echo number_format($cash_sales, 2); ?>
                </div>
            </div>
            
            <!-- Transfer/Card from Cash Out -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-university"></i>
                    <span>Transfer/Card (Cash Out)</span>
                </div>
                <div class="cfi-financial-value negative" id="val-transfer-cashout" data-value="<?php echo esc_attr($transfer_from_cashout); ?>">
                    -₦<?php echo number_format($transfer_from_cashout, 2); ?>
                </div>
            </div>
            
            <!-- Transfer/Card from Debtors (not in calc) -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-user-clock"></i>
                    <span>Transfer/Card (Debtors)</span>
                </div>
                <div class="cfi-financial-value muted" id="val-transfer-debtors" data-value="<?php echo esc_attr($transfer_from_debtors); ?>">
                    ₦<?php echo number_format($transfer_from_debtors, 2); ?> <small>(not in calc)</small>
                </div>
            </div>
            
            <!-- Debtors Cash -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Debtors Cash</span>
                </div>
                <div class="cfi-financial-value positive" id="val-debtors-cash" data-value="<?php echo esc_attr($debtors_cash); ?>">
                    +₦<?php echo number_format($debtors_cash, 2); ?>
                </div>
            </div>
            
            <!-- Expenses -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Expenses</span>
                </div>
                <div class="cfi-financial-value negative" id="val-expenses" data-value="<?php echo esc_attr($expenses); ?>">
                    -₦<?php echo number_format($expenses, 2); ?>
                </div>
            </div>
            
            <!-- Old Cash -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-wallet"></i>
                    <span>Old Cash (Yesterday)</span>
                </div>
                <div class="cfi-financial-value positive" id="val-old-cash" data-value="<?php echo esc_attr($old_cash); ?>">
                    +₦<?php echo number_format($old_cash, 2); ?>
                </div>
            </div>
            
            <!-- Cash to Bank (editable) -->
            <div class="cfi-financial-row">
                <div class="cfi-financial-label">
                    <i class="fas fa-piggy-bank"></i>
                    <span>Cash to Bank</span>
                </div>
                <div class="cfi-input-row">
                    <span style="color: #ef4444; font-weight: 600;">-₦</span>
                    <input type="number" 
                           id="cfi-cash-to-bank" 
                           class="cfi-financial-input" 
                           min="0" 
                           step="0.01" 
                           value="<?php echo esc_attr($cash_to_bank); ?>"
                           data-value="<?php echo esc_attr($cash_to_bank); ?>">
                    <button type="button" id="cfi-save-btn" class="cfi-save-btn">
                        <i class="fas fa-save"></i>
                        Save
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Cash Left Footer -->
        <div class="cfi-cash-left-row">
            <div class="cfi-cash-left-label">
                <i class="fas fa-coins"></i>
                <span>Cash Left</span>
            </div>
            <div class="cfi-cash-left-value" id="cfi-cash-left">
                ₦<?php echo number_format($cash_left, 2); ?>
            </div>
        </div>
        
        <!-- Formula Box -->
        <div class="cfi-formula-box">
            <h4><i class="fas fa-info-circle"></i> Calculation Formula</h4>
            <p><strong>Cash Left</strong> = Total Sales - Transfer/Card (Orders) - Transfer/Card (Cash Out) + Debtors Cash - Expenses + Old Cash - Cash to Bank</p>
        </div>
    </div>
</div>

<script>
(function() {
    // Format number with commas
    function formatCurrency(num) {
        return '₦' + parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = '<?php echo wp_create_nonce('cfi_nonce'); ?>';
    var summaryDate = '<?php echo esc_js($today); ?>';
    var summaryContainer = document.querySelector('.cfi-financial-container');
    var refreshToken = null;
    
    function setSummaryLoading(isLoading) {
        if (!summaryContainer) {
            return;
        }
        if (isLoading) {
            summaryContainer.classList.add('cfi-summary-loading');
        } else {
            summaryContainer.classList.remove('cfi-summary-loading');
        }
    }
    
    function setSummaryValue(id, value, options) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        var numericValue = parseFloat(value) || 0;
        el.setAttribute('data-value', numericValue);
        var displayValue = options && options.absolute ? Math.abs(numericValue) : numericValue;
        var formatted = formatCurrency(displayValue);
        
        if (options && options.prefix) {
            formatted = options.prefix + formatted;
        }
        
        if (options && options.note) {
            el.innerHTML = formatted + ' <small>(' + options.note + ')</small>';
        } else {
            el.textContent = formatted;
        }
    }
    
    function updateSummaryDisplay(summary) {
        if (!summary) {
            return;
        }
        
        setSummaryValue('val-total-sales', summary.total_sales);
        setSummaryValue('val-transfer-orders', summary.transfer_from_orders, { prefix: '-', absolute: true });
        setSummaryValue('val-cash-sales', summary.cash_sales);
        setSummaryValue('val-transfer-cashout', summary.transfer_from_cashout, { prefix: '-', absolute: true });
        setSummaryValue('val-transfer-debtors', summary.transfer_from_debtors, { note: 'not in calc' });
        setSummaryValue('val-debtors-cash', summary.debtors_cash, { prefix: '+', absolute: true });
        setSummaryValue('val-expenses', summary.expenses, { prefix: '-', absolute: true });
        setSummaryValue('val-old-cash', summary.old_cash, { prefix: '+', absolute: true });
        
        var cashToBankInput = document.getElementById('cfi-cash-to-bank');
        if (cashToBankInput) {
            cashToBankInput.setAttribute('data-value', summary.cash_to_bank);
            if (document.activeElement !== cashToBankInput) {
                cashToBankInput.value = summary.cash_to_bank;
            }
        }
        
        calculateCashLeft();
    }
    
    function refreshSummary(options) {
        var requestId = Date.now().toString(36) + Math.random().toString(36).slice(2);
        refreshToken = requestId;
        var showLoading = options && options.showLoading;
        if (showLoading) {
            setSummaryLoading(true);
        }
        var formData = new FormData();
        formData.append('action', 'cfi_get_financial_summary');
        formData.append('nonce', nonce);
        formData.append('date', summaryDate);
        
        return fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (requestId !== refreshToken) {
                return;
            }
            if (data.success && data.data && data.data.summary) {
                updateSummaryDisplay(data.data.summary);
            }
        })
        .catch(function() {
            return null;
        })
        .finally(function() {
            if (showLoading && requestId === refreshToken) {
                setSummaryLoading(false);
            }
        });
    }
    
    // Calculate Cash Left in real-time
    function calculateCashLeft() {
        var totalSales = parseFloat(document.getElementById('val-total-sales').getAttribute('data-value')) || 0;
        var transferOrders = parseFloat(document.getElementById('val-transfer-orders').getAttribute('data-value')) || 0;
        var transferCashout = parseFloat(document.getElementById('val-transfer-cashout').getAttribute('data-value')) || 0;
        var debtorsCash = parseFloat(document.getElementById('val-debtors-cash').getAttribute('data-value')) || 0;
        var expenses = parseFloat(document.getElementById('val-expenses').getAttribute('data-value')) || 0;
        var oldCash = parseFloat(document.getElementById('val-old-cash').getAttribute('data-value')) || 0;
        var cashToBank = parseFloat(document.getElementById('cfi-cash-to-bank').value) || 0;
        
        // Formula: Total Sales - Transfer/Card (Orders) - Transfer/Card (Cash Out) + Debtors Cash - Expenses + Old Cash - Cash to Bank
        var cashLeft = totalSales - transferOrders - transferCashout + debtorsCash - expenses + oldCash - cashToBank;
        
        var cashLeftEl = document.getElementById('cfi-cash-left');
        if (cashLeftEl) {
            cashLeftEl.textContent = formatCurrency(cashLeft);
            
            // Change color based on value
            var parentEl = cashLeftEl.closest('.cfi-cash-left-row');
            if (parentEl) {
                if (cashLeft < 0) {
                    parentEl.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
                } else {
                    parentEl.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                }
            }
        }
        
        return cashLeft;
    }
    
    // Attach event listener to Cash to Bank input for real-time calculation
    var cashToBankInput = document.getElementById('cfi-cash-to-bank');
    if (cashToBankInput) {
        cashToBankInput.addEventListener('input', function() {
            calculateCashLeft();
        });
        cashToBankInput.addEventListener('change', function() {
            calculateCashLeft();
        });
    }
    
    // Save button functionality
    var saveBtn = document.getElementById('cfi-save-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            var btn = this;
            var amount = parseFloat(document.getElementById('cfi-cash-to-bank').value) || 0;
            
            // Check for negative values
            if (amount < 0) {
                if (typeof CFI !== 'undefined' && CFI.negativeValuePopup) {
                    CFI.negativeValuePopup.show(['Cash to Bank']);
                } else {
                    alert('Negative values are not allowed! Please check your input and try again.');
                }
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            // Make AJAX request
            var formData = new FormData();
            formData.append('action', 'cfi_update_financial');
            formData.append('nonce', nonce);
            formData.append('cash_to_bank', amount);
            
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    var cashToBankField = document.getElementById('cfi-cash-to-bank');
                    if (cashToBankField) {
                        cashToBankField.setAttribute('data-value', amount);
                    }
                    
                    refreshSummary();
                    
                    if (typeof CFI !== 'undefined' && CFI.toast) {
                        CFI.toast.success('Cash to Bank saved successfully!');
                    } else {
                        alert('Cash to Bank saved successfully!');
                    }
                    
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Save';
                } else {
                    alert('Error: ' + (data.data || 'Failed to save'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Save';
                }
            })
            .catch(function(error) {
                alert('Error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save';
            });
        });
    }
    
    // Auto-refresh data every 5 seconds
    setInterval(function() {
        refreshSummary().finally(function() {
            var indicator = document.getElementById('cfi-auto-refresh');
            if (indicator) {
                var now = new Date();
                indicator.innerHTML = '<i class="fas fa-sync-alt"></i> Last check: ' + now.toLocaleTimeString();
            }
        });
    }, 5000);
    
    // Initial calculation
    calculateCashLeft();
    refreshSummary({ showLoading: true });
    
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            refreshSummary();
        }
    });
    
    // Handle browser back/forward cache (bfcache) - reload if restored from cache
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // Page was restored from bfcache - force reload with timestamp
            window.location.href = window.location.pathname + '?t=' + Date.now();
        }
    });
})();
</script>
