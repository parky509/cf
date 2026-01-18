<?php
/**
 * Debtors Record Page Template - COMPLETE REBUILD v3
 * Brutal fix: Zero caching, direct wpdb queries, proper balance tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

// Force fresh page - aggressive no-cache headers
if (!headers_sent()) {
    header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0, s-maxage=0, post-check=0, pre-check=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
    header('Vary: *');
}

// Clear any WordPress object cache for debtors
wp_cache_flush();

// Ensure database tables exist
CFI_Database::create_tables();

global $wpdb;
$is_admin = CFI_Auth::is_cfi_admin();
$message = '';
$message_type = '';

// Table names
$debtors_table = $wpdb->prefix . 'cfi_debtors';
$orders_table = $wpdb->prefix . 'cfi_orders';
$order_items_table = $wpdb->prefix . 'cfi_order_items';
$trans_table = $wpdb->prefix . 'cfi_debtor_transactions';

// Process Take Order Form
if (isset($_POST['cfi_debtor_order_submit']) && wp_verify_nonce($_POST['cfi_debtor_order_nonce'], 'cfi_debtor_order')) {
    $debtor_id = intval($_POST['debtor_id']);
    $items = isset($_POST['order_items']) ? $_POST['order_items'] : array();
    
    if (empty($items)) {
        $message = 'Please add at least one item to the order';
        $message_type = 'error';
    } else {
        // Get current debtor data with fresh query
        $debtor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$debtors_table} WHERE id = %d LIMIT 1",
            $debtor_id
        ));
        
        if ($debtor) {
            $total_amount = 0;
            $order_items = array();
            
            foreach ($items as $item) {
                $product_id = intval($item['product_id']);
                $quantity = floatval($item['quantity']);
                $discount = floatval($item['discount']);
                
                if ($quantity > 0 && $product_id > 0) {
                    $product = CFI_Products::get($product_id);
                    if ($product) {
                        $item_total = ($product->price * $quantity) - $discount;
                        $total_amount += $item_total;
                        $order_items[] = array(
                            'product_id' => $product_id,
                            'product_name' => $product->name,
                            'price' => $product->price,
                            'quantity' => $quantity,
                            'discount' => $discount,
                            'total' => $item_total
                        );
                    }
                }
            }
            
            if ($total_amount > 0) {
                $order_number = 'ORD-' . date('Ymd') . '-' . substr(uniqid(), -6);
                $total_qty = 0;
                $total_discount = 0;
                foreach ($order_items as $item) {
                    $total_qty += $item['quantity'];
                    $total_discount += $item['discount'];
                }
                
                // Insert order
                $wpdb->insert(
                    $orders_table,
                    array(
                        'order_number' => $order_number,
                        'order_type' => 'credit',
                        'customer_name' => $debtor->name,
                        'debtor_id' => $debtor_id,
                        'total_quantity' => $total_qty,
                        'total_amount' => $total_amount + $total_discount,
                        'discount_amount' => $total_discount,
                        'grand_total' => $total_amount,
                        'payment_method' => 'credit',
                        'transfer_amount' => 0,
                        'cash_amount' => 0,
                        'bank_name' => '',
                        'staff_id' => get_current_user_id(),
                        'order_date' => current_time('Y-m-d'),
                        'order_time' => current_time('H:i:s'),
                        'status' => 'completed'
                    ),
                    array('%s', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s', '%f', '%f', '%s', '%d', '%s', '%s', '%s')
                );
                $order_id = $wpdb->insert_id;
                
                // Insert order items
                foreach ($order_items as $item) {
                    $wpdb->insert(
                        $order_items_table,
                        array(
                            'order_id' => $order_id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                            'discount' => $item['discount'],
                            'total' => $item['total']
                        ),
                        array('%d', '%d', '%f', '%f', '%f', '%f')
                    );
                    CFI_Stock::update_credit_supply($item['product_id'], $item['quantity'], current_time('Y-m-d'));
                }
                
                // Update debtor balance using direct UPDATE query
                $balance_before = floatval($debtor->total_debt);
                $new_balance = $balance_before + $total_amount;
                
                // CRITICAL: Direct SQL update without any caching
                $update_result = $wpdb->query($wpdb->prepare(
                    "UPDATE `{$debtors_table}` SET `total_debt` = %f, `updated_at` = %s WHERE `id` = %d",
                    $new_balance,
                    current_time('mysql'),
                    $debtor_id
                ));
                
                // Record transaction
                $wpdb->insert(
                    $trans_table,
                    array(
                        'debtor_id' => $debtor_id,
                        'transaction_type' => 'order',
                        'order_id' => $order_id,
                        'amount' => $total_amount,
                        'balance_before' => $balance_before,
                        'balance_after' => $new_balance,
                        'description' => 'New order: ' . $order_number,
                        'staff_id' => get_current_user_id(),
                        'transaction_date' => current_time('Y-m-d'),
                        'transaction_time' => current_time('H:i:s')
                    ),
                    array('%d', '%s', '%d', '%f', '%f', '%f', '%s', '%d', '%s', '%s')
                );
                
                CFI_Financial::update_daily_summary(current_time('Y-m-d'));
                
                // Store receipt for display after redirect
                $receipt_key = 'cfi_order_' . get_current_user_id() . '_' . time();
                set_transient($receipt_key, array(
                    'order_number' => $order_number,
                    'date' => current_time('d/m/Y'),
                    'time' => current_time('H:i'),
                    'debtor_name' => $debtor->name,
                    'items' => $order_items,
                    'total' => $total_amount,
                    'new_balance' => $new_balance,
                    'staff' => wp_get_current_user()->display_name
                ), 300);
                
                // PRG: Redirect to prevent resubmission
                wp_redirect(add_query_arg(array('order_done' => '1', 'rk' => $receipt_key), remove_query_arg(array('debtor', 'action'))));
                exit;
            }
        }
    }
}

// Process Clear Debt Form
if (isset($_POST['cfi_clear_debt_submit']) && wp_verify_nonce($_POST['cfi_clear_debt_nonce'], 'cfi_clear_debt')) {
    $debtor_id = intval($_POST['debtor_id']);
    $use_transfer = isset($_POST['use_transfer']);
    $use_cash = isset($_POST['use_cash']);
    $use_home = isset($_POST['use_home']);
    
    $methods = array();
    if ($use_transfer) $methods[] = 'transfer';
    if ($use_cash) $methods[] = 'cash';
    if ($use_home) $methods[] = 'home';
    $payment_method = !empty($methods) ? implode('_', $methods) : 'cash';
    
    $transfer_amount = $use_transfer ? floatval($_POST['transfer_amount']) : 0;
    $cash_amount = $use_cash ? floatval($_POST['cash_amount']) : 0;
    $home_amount = $use_home ? floatval($_POST['home_amount']) : 0;
    $bank_name = sanitize_text_field($_POST['bank_name']);
    $total_payment = $transfer_amount + $cash_amount + $home_amount;
    
    if ($total_payment > 0) {
        $debtor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$debtors_table} WHERE id = %d LIMIT 1",
            $debtor_id
        ));
        
        if ($debtor && $total_payment <= $debtor->total_debt) {
            $balance_before = floatval($debtor->total_debt);
            $new_balance = $balance_before - $total_payment;
            
            // CRITICAL: Direct SQL update without any caching
            $update_result = $wpdb->query($wpdb->prepare(
                "UPDATE `{$debtors_table}` SET `total_debt` = %f, `updated_at` = %s WHERE `id` = %d",
                $new_balance,
                current_time('mysql'),
                $debtor_id
            ));
            
            // Record transaction
            $wpdb->insert(
                $trans_table,
                array(
                    'debtor_id' => $debtor_id,
                    'transaction_type' => 'payment',
                    'amount' => $total_payment,
                    'payment_method' => $payment_method,
                    'bank_name' => $bank_name,
                    'transfer_amount' => $transfer_amount,
                    'cash_amount' => $cash_amount,
                    'home_calculation_amount' => $home_amount,
                    'balance_before' => $balance_before,
                    'balance_after' => $new_balance,
                    'description' => 'Debt payment received',
                    'staff_id' => get_current_user_id(),
                    'transaction_date' => current_time('Y-m-d'),
                    'transaction_time' => current_time('H:i:s')
                ),
                array('%d', '%s', '%f', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s')
            );
            
            // Record transfer if applicable
            if ($transfer_amount > 0) {
                $transfer_table = $wpdb->prefix . 'cfi_transfer_history';
                $wpdb->insert(
                    $transfer_table,
                    array(
                        'source' => 'debtor',
                        'source_id' => $debtor_id,
                        'customer_name' => $debtor->name,
                        'amount' => $transfer_amount,
                        'bank_name' => $bank_name,
                        'staff_id' => get_current_user_id(),
                        'transfer_date' => current_time('Y-m-d'),
                        'transfer_time' => current_time('H:i:s')
                    ),
                    array('%s', '%d', '%s', '%f', '%s', '%d', '%s', '%s')
                );
            }
            
            CFI_Financial::update_daily_summary(current_time('Y-m-d'));
            
            // Store payment receipt
            $pay_key = 'cfi_pay_' . get_current_user_id() . '_' . time();
            set_transient($pay_key, array(
                'receipt_number' => 'PAY-' . date('Ymd') . '-' . substr(uniqid(), -6),
                'date' => current_time('d/m/Y'),
                'time' => current_time('H:i'),
                'debtor_name' => $debtor->name,
                'payment_amount' => $total_payment,
                'transfer_amount' => $transfer_amount,
                'cash_amount' => $cash_amount,
                'home_amount' => $home_amount,
                'bank_name' => $bank_name,
                'balance_before' => $balance_before,
                'new_balance' => $new_balance,
                'staff' => wp_get_current_user()->display_name
            ), 300);
            
            // PRG: Redirect
            wp_redirect(add_query_arg(array('pay_done' => '1', 'pk' => $pay_key), remove_query_arg(array('debtor', 'action'))));
            exit;
        }
    }
}

// Load receipt data from transients after redirect
$order_receipt = null;
$payment_receipt = null;

if (isset($_GET['order_done']) && isset($_GET['rk'])) {
    $order_receipt = get_transient($_GET['rk']);
    if ($order_receipt) {
        delete_transient($_GET['rk']);
    }
}

if (isset($_GET['pay_done']) && isset($_GET['pk'])) {
    $payment_receipt = get_transient($_GET['pk']);
    if ($payment_receipt) {
        delete_transient($_GET['pk']);
    }
}

// Get fresh data - use SQL_NO_CACHE and bypass WordPress object cache
$wpdb->flush();  // Clear any cached query results
$debtors = $wpdb->get_results("SELECT SQL_NO_CACHE * FROM `{$debtors_table}` WHERE `status` = 'active' ORDER BY `name` ASC");
$products = CFI_Products::get_all();

$selected_debtor_id = isset($_GET['debtor']) ? intval($_GET['debtor']) : 0;
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$selected_debtor = null;
if ($selected_debtor_id) {
    $selected_debtor = $wpdb->get_row($wpdb->prepare(
        "SELECT SQL_NO_CACHE * FROM `{$debtors_table}` WHERE `id` = %d LIMIT 1",
        $selected_debtor_id
    ));
}

// Generate unique page ID to break caching
$page_uid = substr(md5(microtime(true)), 0, 8);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="page-uid" content="<?php echo $page_uid; ?>">
<title>Debtors Record</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;padding:0;background:#f8fafc}
.container{max-width:1200px;margin:0 auto;padding:1rem}
.header{background:linear-gradient(135deg,#001943,#003366);color:#fff;padding:0.75rem 1rem;border-radius:12px;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem}
.header h1{margin:0;font-size:0.85rem;display:flex;align-items:center;gap:0.5rem}
.btn{display:inline-flex;align-items:center;gap:0.4rem;padding:0.5rem 0.75rem;border:none;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;font-size:0.7rem;transition:all 0.2s}
.btn-primary{background:#001943;color:#fff}
.btn-success{background:#16a34a;color:#fff}
.btn-outline{background:#fff;border:2px solid #001943;color:#001943}
.btn-white{background:#fff;color:#001943}
.card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem}
.card{background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 4px 20px rgba(0,25,67,0.1);border:2px solid rgba(0,25,67,0.1)}
.card-name{font-size:1.2rem;color:#001943;margin:0 0 0.5rem}
.card-phone{color:#64748b;font-size:0.85rem;margin:0 0 1rem}
.card-balance{font-size:1.5rem;font-weight:700;color:#dc2626;margin-bottom:1rem}
.card-balance.zero{color:#16a34a}
.card-actions{display:flex;gap:0.5rem;flex-wrap:wrap}
.card-actions .btn{flex:1;justify-content:center}
.glass{background:rgba(255,255,255,0.95);border-radius:12px;padding:1.5rem;box-shadow:0 4px 20px rgba(0,25,67,0.1);border:2px solid rgba(0,25,67,0.1);margin-bottom:1.5rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;margin-bottom:0.5rem;font-weight:600;color:#001943;font-size:0.85rem}
.input{width:100%;padding:0.75rem;border:2px solid #e2e8f0;border-radius:8px;font-size:1rem}
.input:focus{outline:none;border-color:#001943}
table{width:100%;border-collapse:collapse;font-size:0.85rem}
th{background:#001943;color:#fff;padding:0.6rem 0.4rem;text-align:left}
td{padding:0.5rem 0.4rem;border-bottom:1px solid #e2e8f0}
table input{width:70px;padding:0.4rem;border:1px solid #e2e8f0;border-radius:4px;text-align:center}
.order-total{background:#001943;color:#fff;padding:1rem;border-radius:8px;margin-top:1rem;display:flex;justify-content:space-between;align-items:center}
.total-value{font-size:1.5rem;font-weight:700}
.grand-display{margin-top:0.5rem;padding:1rem;background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;border-radius:8px;text-align:center;display:none}
.grand-display span{display:block;font-size:0.9rem}
.grand-display strong{font-size:2rem;font-weight:700}
.payment-methods{display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem}
.payment-method{flex:1;min-width:100px;padding:0.75rem;border:2px solid #e2e8f0;border-radius:8px;text-align:center;cursor:pointer;transition:all 0.2s;position:relative}
.payment-method.selected{border-color:#001943;background:rgba(0,25,67,0.15);box-shadow:0 0 0 3px rgba(0,25,67,0.1)}
.payment-method i{display:block;font-size:1.5rem;color:#001943;margin-bottom:0.5rem}
.bank-options{margin-bottom:1rem}
.bank-option{display:flex;align-items:center;gap:0.5rem;padding:0.5rem;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:0.5rem;cursor:pointer}
.bank-option input{width:auto}
.back-link{color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;font-size:0.85rem}
.back-link:hover{text-decoration:underline}
.section-title{font-size:1rem;color:#fff;margin:0 0 1rem;padding:0.75rem 1rem;background:linear-gradient(135deg,#001943,#003366);border-radius:10px;display:flex;align-items:center;gap:0.5rem}
.empty{text-align:center;padding:3rem;color:#64748b}
.empty i{font-size:3rem;margin-bottom:1rem;display:block}
.modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;padding:1rem}
.modal-content{background:#fff;max-width:400px;width:100%;max-height:90vh;overflow-y:auto;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,0.3)}
.modal-header{padding:1rem;display:flex;justify-content:space-between;align-items:center}
.modal-header.order{background:#001943;color:#fff}
.modal-header.payment{background:#16a34a;color:#fff}
.modal-header h3{margin:0}
.modal-close{background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer}
.modal-body{padding:1.5rem}
.modal-footer{display:flex;gap:0.5rem;padding:1rem;background:#f1f5f9}
.modal-footer .btn{flex:1;justify-content:center}
.receipt-info{margin-bottom:1rem;font-size:0.85rem}
.receipt-info p{margin:0.25rem 0;display:flex;justify-content:space-between}
.receipt-items{border-top:1px dashed #e2e8f0;border-bottom:1px dashed #e2e8f0;padding:0.5rem 0;margin:0.5rem 0}
.receipt-item{display:flex;justify-content:space-between;padding:0.25rem 0;font-size:0.8rem}
.receipt-total{font-size:1.1rem;font-weight:700;color:#001943;border-top:2px solid #001943;padding-top:0.5rem;margin-top:0.5rem;display:flex;justify-content:space-between}
.receipt-footer{text-align:center;margin-top:1rem;padding-top:1rem;border-top:2px dashed #e2e8f0;font-size:0.75rem;color:#64748b}
@media(max-width:768px){
.header{flex-direction:column;text-align:center}
.card-actions{flex-direction:column}
table input{width:50px}
}
</style>
</head>
<body>
<main class="container">
<div class="header">
<?php if ($selected_debtor && ($action === 'order' || $action === 'pay')) : ?>
<a href="<?php echo esc_url(remove_query_arg(array('debtor','action'))); ?>" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>
<h1><?php echo $action === 'order' ? '<i class="fas fa-cart-plus"></i> Take Order - ' : '<i class="fas fa-money-check"></i> Clear Debt - '; ?><?php echo esc_html($selected_debtor->name); ?></h1>
<?php else : ?>
<h1><i class="fas fa-user-clock"></i> Debtors Record</h1>
<a href="<?php echo home_url('/debtors-history/'); ?>" class="btn btn-white"><i class="fas fa-history"></i> View History</a>
<?php endif; ?>
</div>

<?php if ($selected_debtor && $action === 'order') : ?>
<div class="glass">
<h3 style="color:#001943;margin-top:0"><i class="fas fa-shopping-cart"></i> Order Items</h3>
<p><strong>Current Debt:</strong> <span style="color:#dc2626">₦<?php echo number_format($selected_debtor->total_debt, 2); ?></span></p>
<form method="POST" id="order-form">
<?php wp_nonce_field('cfi_debtor_order', 'cfi_debtor_order_nonce'); ?>
<input type="hidden" name="debtor_id" value="<?php echo esc_attr($selected_debtor->id); ?>">
<div style="overflow-x:auto">
<table>
<thead><tr><th style="width:40%">Item</th><th>Price (₦)</th><th>Qty</th><th>Disc (₦)</th><th>Total (₦)</th></tr></thead>
<tbody>
<?php foreach ($products as $idx => $product) : ?>
<tr class="order-row" data-price="<?php echo esc_attr($product->price); ?>">
<td><?php echo esc_html($product->name); ?><input type="hidden" name="order_items[<?php echo $idx; ?>][product_id]" value="<?php echo esc_attr($product->id); ?>"></td>
<td style="color:#001943;font-weight:600"><?php echo number_format($product->price, 2); ?></td>
<td><input type="number" name="order_items[<?php echo $idx; ?>][quantity]" class="qty" value="0" min="0" step="0.5" oninput="calcRow(this)"></td>
<td><input type="number" name="order_items[<?php echo $idx; ?>][discount]" class="disc" value="0" min="0" step="0.01" oninput="calcRow(this)"></td>
<td class="row-total" style="font-weight:600;color:#001943">0.00</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="order-total"><span>Grand Total:</span><span class="total-value" id="grand-total">₦0.00</span></div>
<div class="grand-display" id="grand-display"><span>Amount to add to debt:</span><strong id="grand-highlight">₦0.00</strong></div>
<div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end">
<a href="<?php echo esc_url(remove_query_arg(array('debtor','action'))); ?>" class="btn btn-outline">Cancel</a>
<button type="submit" name="cfi_debtor_order_submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add to Debt</button>
</div>
</form>
</div>
<script>
function calcRow(el){var r=el.closest('.order-row'),p=parseFloat(r.dataset.price)||0,q=parseFloat(r.querySelector('.qty').value)||0,d=parseFloat(r.querySelector('.disc').value)||0,t=(p*q)-d;if(t<0)t=0;r.querySelector('.row-total').textContent=t.toFixed(2);calcTotal()}
function calcTotal(){var tots=document.querySelectorAll('.row-total'),g=0;tots.forEach(function(e){g+=parseFloat(e.textContent)||0});var f='₦'+g.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');document.getElementById('grand-total').textContent=f;var disp=document.getElementById('grand-display'),hl=document.getElementById('grand-highlight');if(g>0){disp.style.display='block';hl.textContent=f}else{disp.style.display='none'}}
document.querySelectorAll('input[type="number"]').forEach(function(i){i.addEventListener('focus',function(){var s=this;setTimeout(function(){s.select()},10)})});
</script>

<?php elseif ($selected_debtor && $action === 'pay') : ?>
<div class="glass">
<h3 style="color:#001943;margin-top:0"><i class="fas fa-money-check"></i> Record Payment</h3>
<p><strong>Debtor:</strong> <?php echo esc_html($selected_debtor->name); ?></p>
<p><strong>Outstanding Balance:</strong> <span style="color:#dc2626;font-size:1.5rem;font-weight:700">₦<?php echo number_format($selected_debtor->total_debt, 2); ?></span></p>
<?php if ($selected_debtor->total_debt <= 0) : ?>
<div style="background:#dcfce7;color:#166534;padding:1rem;border-radius:8px;margin:1rem 0"><i class="fas fa-check-circle"></i> No outstanding debt!</div>
<a href="<?php echo esc_url(remove_query_arg(array('debtor','action'))); ?>" class="btn btn-primary">Back to Debtors</a>
<?php else : ?>
<form method="POST" id="pay-form">
<?php wp_nonce_field('cfi_clear_debt', 'cfi_clear_debt_nonce'); ?>
<input type="hidden" name="debtor_id" value="<?php echo esc_attr($selected_debtor->id); ?>">
<h4 style="color:#001943">Select Payment Method(s)</h4>
<p style="font-size:0.75rem;color:#64748b;margin-bottom:0.75rem"><i class="fas fa-info-circle"></i> Click to select payment method(s)</p>
<div class="payment-methods">
<div class="payment-method selected" data-method="transfer" onclick="togglePay(this)"><input type="checkbox" name="use_transfer" id="use_transfer" style="display:none" checked><i class="fas fa-credit-card"></i><span>Transfer/Card</span></div>
<div class="payment-method" data-method="cash" onclick="togglePay(this)"><input type="checkbox" name="use_cash" id="use_cash" style="display:none"><i class="fas fa-money-bill-wave"></i><span>Cash</span></div>
<?php if ($is_admin) : ?>
<div class="payment-method" data-method="home" onclick="togglePay(this)"><input type="checkbox" name="use_home" id="use_home" style="display:none"><i class="fas fa-home"></i><span>Home Calc</span></div>
<?php endif; ?>
</div>
<div class="bank-options" id="bank-opts" style="display:block">
<h4 style="color:#001943">Select Bank</h4>
<label class="bank-option"><input type="radio" name="bank_name" value="Moniepoint MFB" checked><span>Moniepoint MFB</span></label>
<label class="bank-option"><input type="radio" name="bank_name" value="Access Bank PLC"><span>Access Bank PLC</span></label>
</div>
<div id="pay-amounts">
<div class="form-group" id="transfer-grp" style="display:block"><label>Transfer Amount (₦)</label><input type="number" id="transfer_amount" name="transfer_amount" class="input" value="0" min="0" step="0.01" oninput="updatePayTotal()"></div>
<div class="form-group" id="cash-grp" style="display:none"><label>Cash Amount (₦)</label><input type="number" id="cash_amount" name="cash_amount" class="input" value="0" min="0" step="0.01" oninput="updatePayTotal()"></div>
<?php if ($is_admin) : ?>
<div class="form-group" id="home-grp" style="display:none"><label>Home Calculation (₦)</label><input type="number" id="home_amount" name="home_amount" class="input" value="0" min="0" step="0.01" oninput="updatePayTotal()"></div>
<?php else : ?>
<input type="hidden" name="home_amount" value="0">
<?php endif; ?>
</div>
<div style="margin-top:1rem;padding:1rem;background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;border-radius:8px;text-align:center"><span style="font-size:0.9rem">Total Payment:</span><strong id="pay-total" style="font-size:1.5rem;display:block">₦0.00</strong></div>
<div id="pay-warn" style="display:none;margin-top:0.5rem;padding:0.75rem;border-radius:8px;font-size:0.85rem"></div>
<div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end">
<a href="<?php echo esc_url(remove_query_arg(array('debtor','action'))); ?>" class="btn btn-outline">Cancel</a>
<button type="submit" name="cfi_clear_debt_submit" class="btn btn-success"><i class="fas fa-check"></i> Record Payment</button>
</div>
</form>
<script>
var debt=<?php echo floatval($selected_debtor->total_debt); ?>;
function togglePay(el){
    el.classList.toggle('selected');
    var m=el.dataset.method;
    var c=el.querySelector('input[type="checkbox"]');
    c.checked=el.classList.contains('selected');
    if(m==='transfer'){
        document.getElementById('transfer-grp').style.display=c.checked?'block':'none';
        document.getElementById('bank-opts').style.display=c.checked?'block':'none';
        if(!c.checked)document.getElementById('transfer_amount').value=0;
    } else if(m==='cash'){
        document.getElementById('cash-grp').style.display=c.checked?'block':'none';
        if(!c.checked)document.getElementById('cash_amount').value=0;
    } else if(m==='home'){
        var hg=document.getElementById('home-grp');
        if(hg){hg.style.display=c.checked?'block':'none';if(!c.checked)document.getElementById('home_amount').value=0}
    }
    updatePayTotal();
}
function updatePayTotal(){var t=parseFloat(document.getElementById('transfer_amount').value)||0,ca=parseFloat(document.getElementById('cash_amount').value)||0,h=document.getElementById('home_amount'),ha=h?(parseFloat(h.value)||0):0,tot=t+ca+ha;
document.getElementById('pay-total').textContent='₦'+tot.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
var diff=debt-tot,w=document.getElementById('pay-warn');
if(Math.abs(diff)>0.01&&tot>0){w.style.display='block';if(diff>0){w.textContent='Payment is ₦'+diff.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',')+' less than debt';w.style.background='#fee2e2';w.style.color='#991b1b'}else{w.textContent='Payment exceeds debt by ₦'+Math.abs(diff).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');w.style.background='#fef3c7';w.style.color='#92400e'}}else{w.style.display='none'}}
</script>
<?php endif; ?>
</div>

<?php else : ?>
<div class="section-title"><i class="fas fa-users"></i><span>Current Debtors</span></div>
<?php if (empty($debtors)) : ?>
<div class="glass empty"><i class="fas fa-users"></i><h3>No Debtors Found</h3><p>Admin can add debtors from the Admin Panel.</p></div>
<?php else : ?>
<div class="card-grid">
<?php foreach ($debtors as $debtor) : ?>
<div class="card">
<h3 class="card-name"><?php echo esc_html($debtor->name); ?></h3>
<?php if ($debtor->phone) : ?><p class="card-phone"><i class="fas fa-phone"></i> <?php echo esc_html($debtor->phone); ?></p><?php endif; ?>
<div class="card-balance <?php echo $debtor->total_debt <= 0 ? 'zero' : ''; ?>">₦<?php echo number_format($debtor->total_debt, 2); ?></div>
<div class="card-actions">
<a href="<?php echo esc_url(add_query_arg(array('debtor'=>$debtor->id,'action'=>'order'))); ?>" class="btn btn-primary"><i class="fas fa-cart-plus"></i> Order</a>
<a href="<?php echo esc_url(add_query_arg(array('debtor'=>$debtor->id,'action'=>'pay'))); ?>" class="btn btn-success"><i class="fas fa-money-check"></i> Clear Debt</a>
</div>
<div style="margin-top:0.75rem"><a href="<?php echo esc_url(home_url('/debtors-history/?debtor='.$debtor->id)); ?>" class="btn btn-outline" style="width:100%;justify-content:center"><i class="fas fa-history"></i> View History</a></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</main>

<?php if ($order_receipt) : ?>
<div class="modal" id="order-modal">
<div class="modal-content">
<div class="modal-header order"><h3><i class="fas fa-receipt"></i> Credit Receipt</h3><button class="modal-close" onclick="closeOrderModal()">&times;</button></div>
<div class="modal-body" id="order-print-area">
<div style="text-align:center;margin-bottom:1rem;border-bottom:2px dashed #e2e8f0;padding-bottom:1rem"><h2 style="color:#001943;margin:0 0 0.25rem">Chinemerem Foods</h2><p style="color:#64748b;font-size:0.8rem;margin:0">Credit Order Receipt</p></div>
<div class="receipt-info">
<p><span>Order #:</span><strong><?php echo esc_html($order_receipt['order_number']); ?></strong></p>
<p><span>Date:</span><?php echo esc_html($order_receipt['date']); ?></p>
<p><span>Time:</span><?php echo esc_html($order_receipt['time']); ?></p>
<p><span>Debtor:</span><strong style="color:#dc2626"><?php echo esc_html($order_receipt['debtor_name']); ?></strong></p>
<p><span>Staff:</span><?php echo esc_html($order_receipt['staff']); ?></p>
</div>
<div class="receipt-items">
<div class="receipt-item" style="font-weight:600;background:#f1f5f9;padding:0.5rem;border-radius:4px"><span style="flex:1">Item</span><span style="width:40px;text-align:center">Qty</span><span style="width:80px;text-align:right">Amount</span></div>
<?php foreach ($order_receipt['items'] as $item) : ?>
<div class="receipt-item"><span style="flex:1"><?php echo esc_html($item['product_name']); ?></span><span style="width:40px;text-align:center"><?php echo esc_html($item['quantity']); ?></span><span style="width:80px;text-align:right;font-weight:600">₦<?php echo number_format($item['total'], 0); ?></span></div>
<?php endforeach; ?>
</div>
<div class="receipt-total"><span>Order Total:</span><span>₦<?php echo number_format($order_receipt['total'], 0); ?></span></div>
<p style="display:flex;justify-content:space-between;color:#dc2626;font-weight:600"><span>New Balance:</span><span>₦<?php echo number_format($order_receipt['new_balance'], 0); ?></span></p>
<div class="receipt-footer"><p style="margin:0">This is a credit order</p><p style="margin:0">Payment pending</p></div>
</div>
<div class="modal-footer">
<button onclick="printOrderReceipt()" class="btn" style="background:#7c3aed;color:#fff"><i class="fas fa-print"></i> Print</button>
<button onclick="closeOrderModal()" class="btn btn-success"><i class="fas fa-check"></i> Done</button>
</div>
</div>
</div>
<script>
async function printOrderReceipt(){
// Try Bluetooth printing first
if ('bluetooth' in navigator) {
    var text = '';
    var line = '================================';
    text += '       CHINEMEREM FOODS\n';
    text += '      Credit Order Receipt\n';
    text += line + '\n';
    text += 'Order: <?php echo esc_js($order_receipt['order_number']); ?>\n';
    text += 'Date: <?php echo esc_js($order_receipt['date']); ?>\n';
    text += 'Time: <?php echo esc_js($order_receipt['time']); ?>\n';
    text += 'Debtor: <?php echo esc_js($order_receipt['debtor_name']); ?>\n';
    text += 'Staff: <?php echo esc_js($order_receipt['staff']); ?>\n';
    text += line + '\n';
    text += 'ITEM       PRICE  QTY   AMOUNT\n';
    text += '--------------------------------\n';
    <?php foreach ($order_receipt['items'] as $item) : ?>
    text += '<?php echo str_pad(substr(esc_js($item['product_name']), 0, 10), 10); ?> N<?php echo str_pad(number_format($item['price'], 0), 5); ?> <?php echo str_pad(intval($item['quantity']), 3); ?> N<?php echo str_pad(number_format($item['total'], 0), 6, ' ', STR_PAD_LEFT); ?>\n';
    <?php if (isset($item['discount']) && $item['discount'] > 0) : ?>text += '           Disc: -N<?php echo number_format($item['discount'], 0); ?>\n';<?php endif; ?>
    <?php endforeach; ?>
    text += line + '\n';
    text += 'ORDER TOTAL:       N<?php echo str_pad(number_format($order_receipt['total'], 0), 9, ' ', STR_PAD_LEFT); ?>\n';
    text += 'NEW BALANCE:       N<?php echo str_pad(number_format($order_receipt['new_balance'], 0), 9, ' ', STR_PAD_LEFT); ?>\n';
    text += line + '\n';
    text += '  This is a credit order\n';
    text += '      Payment pending\n';
    text += '   Powered by BendlessTech\n';
    text += '\n\n\n';
    
    var printed = await printToBluetoothPrinter(text);
    if (printed) {
        alert('Receipt printed successfully!');
        return;
    }
}

// Fallback to browser print with improved formatting - FULL WIDTH 80mm with PRICE column
var w=window.open('','_blank','width=400,height=700');
var h='<!DOCTYPE html><html><head><title>Print Receipt</title>';
h+='<style>';
h+='@page{size:80mm auto;margin:0}';
h+='*{margin:0;padding:0;box-sizing:border-box}';
h+='html,body{width:100%!important;max-width:100%!important;margin:0!important;padding:0!important}';
h+='body{font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.3;color:#000;background:#fff}';
h+='.receipt{width:100%;padding:3mm}';
h+='.header{text-align:center;padding:10px 0;border-bottom:3px double #000;margin-bottom:12px}';
h+='.header h2{font-size:20px;font-weight:900;margin:0 0 5px;text-transform:uppercase}';
h+='.header p{font-size:14px;margin:0;font-weight:700;color:#c00}';
h+='.info{margin:10px 0;padding:10px 0;border-bottom:2px solid #000}';
h+='.info-row{display:flex;justify-content:space-between;margin:6px 0;font-size:12px}';
h+='.info-row .label{font-weight:600}';
h+='.info-row .value{font-weight:900}';
h+='.items-table{width:100%;margin:10px 0;border-collapse:collapse;font-size:12px;table-layout:fixed}';
h+='.items-table th{background:#000;color:#fff;padding:8px 4px;font-size:11px;font-weight:900;text-align:center;border:2px solid #000}';
h+='.items-table th:first-child{text-align:left;width:30%}';
h+='.items-table th:nth-child(2){width:18%}';
h+='.items-table th:nth-child(3){width:12%}';
h+='.items-table th:nth-child(4){width:15%}';
h+='.items-table th:nth-child(5){width:25%}';
h+='.items-table td{padding:8px 4px;border:2px solid #000;vertical-align:middle;font-size:11px}';
h+='.items-table td:first-child{text-align:left;font-weight:600}';
h+='.items-table td:nth-child(2){text-align:right}';
h+='.items-table td:nth-child(3){text-align:center}';
h+='.items-table td:nth-child(4){text-align:center;color:#c00}';
h+='.items-table td:nth-child(5){text-align:right;font-weight:900;font-size:12px}';
h+='.items-table tr:nth-child(even){background:#f0f0f0}';
h+='.totals{margin:12px 0;padding:10px 0;border-top:3px solid #000}';
h+='.total-row{display:flex;justify-content:space-between;margin:6px 0;font-size:14px;font-weight:900}';
h+='.grand-total{background:#000;color:#fff;padding:12px 8px;margin:10px 0;font-size:16px;font-weight:900;display:flex;justify-content:space-between}';
h+='.balance-row{display:flex;justify-content:space-between;margin:10px 0;font-size:15px;font-weight:900;color:#c00}';
h+='.credit-note{background:#ffe0e0;color:#c00;padding:10px;text-align:center;font-weight:900;margin:12px 0;border:3px solid #c00;font-size:13px}';
h+='.footer{text-align:center;margin-top:12px;padding-top:10px;border-top:2px dashed #000;font-size:11px}';
h+='.footer .thanks{font-weight:900;font-size:13px}';
h+='@media print{html,body{width:100%!important}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}';
h+='</style></head><body>';
h+='<div class="receipt">';
h+='<div class="header"><h2>CHINEMEREM FOODS</h2><p>*** CREDIT ORDER ***</p></div>';
h+='<div class="info">';
h+='<div class="info-row"><span class="label">Order No:</span><span class="value"><?php echo esc_js($order_receipt['order_number']); ?></span></div>';
h+='<div class="info-row"><span class="label">Date:</span><span class="value"><?php echo esc_js($order_receipt['date']); ?></span></div>';
h+='<div class="info-row"><span class="label">Time:</span><span class="value"><?php echo esc_js($order_receipt['time']); ?></span></div>';
h+='<div class="info-row"><span class="label">Debtor:</span><span class="value" style="color:#c00"><?php echo esc_js($order_receipt['debtor_name']); ?></span></div>';
h+='<div class="info-row"><span class="label">Staff:</span><span class="value"><?php echo esc_js($order_receipt['staff']); ?></span></div>';
h+='</div>';
h+='<table class="items-table">';
h+='<tr><th>ITEM</th><th>PRICE</th><th>QTY</th><th>DISC</th><th>AMOUNT</th></tr>';
<?php foreach ($order_receipt['items'] as $item) : ?>
h+='<tr>';
h+='<td><?php echo esc_js($item['product_name']); ?></td>';
h+='<td>₦<?php echo number_format($item['price'], 0); ?></td>';
h+='<td style="text-align:center"><?php echo intval($item['quantity']); ?></td>';
h+='<td style="text-align:center;color:#c00"><?php echo isset($item['discount']) && $item['discount'] > 0 ? '-₦' . number_format($item['discount'], 0) : '-'; ?></td>';
h+='<td><strong>₦<?php echo number_format($item['total'], 0); ?></strong></td>';
h+='</tr>';
<?php endforeach; ?>
h+='</table>';
h+='<div class="grand-total"><span>ORDER TOTAL:</span><span>₦<?php echo number_format($order_receipt['total'], 0); ?></span></div>';
h+='<div class="balance-row"><span>NEW BALANCE:</span><span>₦<?php echo number_format($order_receipt['new_balance'], 0); ?></span></div>';
h+='<div class="credit-note">⚠ CREDIT ORDER - PAYMENT PENDING</div>';
h+='<div class="footer"><p class="thanks">Thank you for your patronage!</p><p style="margin-top:5px;font-size:9px">Powered by BendlessTech</p></div>';
h+='</div>';
h+='</body></html>';
w.document.write(h);w.document.close();
w.onload=function(){setTimeout(function(){w.print()},300)};
}
function closeOrderModal(){document.getElementById('order-modal').style.display='none';window.location.href='<?php echo esc_url(add_query_arg('t', time(), remove_query_arg(array('order_done','rk')))); ?>'}
</script>
<?php endif; ?>

<?php if ($payment_receipt) : ?>
<div class="modal" id="pay-modal">
<div class="modal-content">
<div class="modal-header payment"><h3><i class="fas fa-receipt"></i> Payment Receipt</h3><button class="modal-close" onclick="closePayModal()">&times;</button></div>
<div class="modal-body">
<div style="text-align:center;margin-bottom:1rem;border-bottom:2px dashed #e2e8f0;padding-bottom:1rem"><h2 style="color:#001943;margin:0 0 0.25rem">Chinemerem Foods</h2><p style="color:#64748b;font-size:0.8rem;margin:0">Debt Payment Receipt</p></div>
<div class="receipt-info">
<p><span>Receipt #:</span><strong><?php echo esc_html($payment_receipt['receipt_number']); ?></strong></p>
<p><span>Date:</span><?php echo esc_html($payment_receipt['date']); ?></p>
<p><span>Time:</span><?php echo esc_html($payment_receipt['time']); ?></p>
<p><span>Debtor:</span><strong style="color:#16a34a"><?php echo esc_html($payment_receipt['debtor_name']); ?></strong></p>
<p><span>Staff:</span><?php echo esc_html($payment_receipt['staff']); ?></p>
</div>
<div class="receipt-items">
<p style="display:flex;justify-content:space-between"><span>Balance Before:</span><span style="color:#dc2626">₦<?php echo number_format($payment_receipt['balance_before'], 0); ?></span></p>
<p style="display:flex;justify-content:space-between;font-weight:600;font-size:1.1rem;color:#16a34a"><span>Payment Amount:</span><span>₦<?php echo number_format($payment_receipt['payment_amount'], 0); ?></span></p>
<?php if ($payment_receipt['transfer_amount'] > 0) : ?><p style="display:flex;justify-content:space-between;font-size:0.8rem"><span>- Via Transfer (<?php echo esc_html($payment_receipt['bank_name']); ?>):</span><span>₦<?php echo number_format($payment_receipt['transfer_amount'], 0); ?></span></p><?php endif; ?>
<?php if ($payment_receipt['cash_amount'] > 0) : ?><p style="display:flex;justify-content:space-between;font-size:0.8rem"><span>- Via Cash:</span><span>₦<?php echo number_format($payment_receipt['cash_amount'], 0); ?></span></p><?php endif; ?>
<?php if ($payment_receipt['home_amount'] > 0) : ?><p style="display:flex;justify-content:space-between;font-size:0.8rem"><span>- Home Calculation:</span><span>₦<?php echo number_format($payment_receipt['home_amount'], 0); ?></span></p><?php endif; ?>
</div>
<div class="receipt-total"><span>New Balance:</span><span style="color:<?php echo $payment_receipt['new_balance'] > 0 ? '#dc2626' : '#16a34a'; ?>">₦<?php echo number_format($payment_receipt['new_balance'], 0); ?></span></div>
<div class="receipt-footer"><p style="margin:0">Payment received with thanks</p><p style="margin:0">Powered by BendlessTech</p></div>
</div>
<div class="modal-footer">
<button onclick="printPayReceipt()" class="btn" style="background:#7c3aed;color:#fff"><i class="fas fa-print"></i> Print</button>
<button onclick="closePayModal()" class="btn btn-success"><i class="fas fa-check"></i> Done</button>
</div>
</div>
</div>
<script>
async function printPayReceipt(){
// Try Bluetooth printing first
if ('bluetooth' in navigator) {
    var text = '';
    var line = '--------------------------------';
    text += '       CHINEMEREM FOODS\n';
    text += '     Debt Payment Receipt\n';
    text += line + '\n';
    text += 'Receipt: <?php echo esc_js($payment_receipt['receipt_number']); ?>\n';
    text += 'Date: <?php echo esc_js($payment_receipt['date']); ?>\n';
    text += 'Time: <?php echo esc_js($payment_receipt['time']); ?>\n';
    text += 'Debtor: <?php echo esc_js($payment_receipt['debtor_name']); ?>\n';
    text += 'Staff: <?php echo esc_js($payment_receipt['staff']); ?>\n';
    text += line + '\n';
    text += 'Balance Before:    N<?php echo str_pad(number_format($payment_receipt['balance_before'], 0), 9, ' ', STR_PAD_LEFT); ?>\n';
    text += line + '\n';
    text += 'PAYMENT AMOUNT:    N<?php echo str_pad(number_format($payment_receipt['payment_amount'], 0), 9, ' ', STR_PAD_LEFT); ?>\n';
    <?php if ($payment_receipt['transfer_amount'] > 0) : ?>text += '  - Transfer:      N<?php echo str_pad(number_format($payment_receipt['transfer_amount'], 0), 9, ' ', STR_PAD_LEFT); ?>\n';<?php endif; ?>
    <?php if ($payment_receipt['cash_amount'] > 0) : ?>text += '  - Cash:          N<?php echo str_pad(number_format($payment_receipt['cash_amount'], 0), 9, ' ', STR_PAD_LEFT); ?>\n';<?php endif; ?>
    <?php if ($payment_receipt['home_amount'] > 0) : ?>text += '  - Home Calc:     N<?php echo str_pad(number_format($payment_receipt['home_amount'], 0), 9, ' ', STR_PAD_LEFT); ?>\n';<?php endif; ?>
    text += line + '\n';
    text += 'NEW BALANCE:       N<?php echo str_pad(number_format($payment_receipt['new_balance'], 0), 9, ' ', STR_PAD_LEFT); ?>\n';
    text += line + '\n';
    text += '  Payment received with thanks!\n';
    text += '     Powered by BendlessTech\n';
    text += '\n\n\n';
    
    var printed = await printToBluetoothPrinter(text);
    if (printed) {
        alert('Receipt printed successfully!');
        return;
    }
}

// Fallback to browser print
var w=window.open('','_blank','width=350,height=700');
var h='<!DOCTYPE html><html><head><title>Print Receipt</title>';
h+='<style>';
h+='@page{size:80mm auto;margin:0}';
h+='*{margin:0;padding:0;box-sizing:border-box}';
h+='body{font-family:"Courier New",Courier,monospace;font-size:13px;width:72mm;max-width:72mm;margin:0 auto;padding:5mm;line-height:1.4;color:#000}';
h+='.header{text-align:center;padding:8px 0;border-bottom:2px dashed #000;margin-bottom:10px}';
h+='.header h2{font-size:16px;font-weight:bold;margin:0 0 5px}';
h+='.header p{font-size:11px;margin:0}';
h+='.info{margin:10px 0;padding:8px 0;border-bottom:1px dashed #000}';
h+='.info p{display:flex;justify-content:space-between;margin:6px 0;font-size:12px}';
h+='.payment{margin:10px 0;padding:10px 0;border-bottom:1px dashed #000}';
h+='.payment p{display:flex;justify-content:space-between;margin:6px 0;font-size:12px}';
h+='.payment .big{font-size:14px;font-weight:bold;color:#008800}';
h+='.total{margin:10px 0;padding:10px 0;border-top:2px solid #000}';
h+='.total p{display:flex;justify-content:space-between;margin:6px 0;font-size:14px;font-weight:bold}';
h+='.footer{text-align:center;margin-top:15px;padding-top:10px;border-top:1px dashed #000;font-size:10px}';
h+='.no-print{margin:15px 0;text-align:center}';
h+='.print-btn{background:#16a34a;color:#fff;border:none;padding:12px 30px;font-size:14px;border-radius:5px;cursor:pointer}';
h+='@media print{.no-print{display:none !important}}';
h+='</style></head><body>';
h+='<div class="header"><h2>CHINEMEREM FOODS</h2><p>Debt Payment Receipt</p></div>';
h+='<div class="info">';
h+='<p><span>Receipt #:</span><span><?php echo esc_js($payment_receipt['receipt_number']); ?></span></p>';
h+='<p><span>Date:</span><span><?php echo esc_js($payment_receipt['date']); ?></span></p>';
h+='<p><span>Time:</span><span><?php echo esc_js($payment_receipt['time']); ?></span></p>';
h+='<p><span>Debtor:</span><span style="font-weight:bold"><?php echo esc_js($payment_receipt['debtor_name']); ?></span></p>';
h+='<p><span>Staff:</span><span><?php echo esc_js($payment_receipt['staff']); ?></span></p>';
h+='</div>';
h+='<div class="payment">';
h+='<p><span>Balance Before:</span><span style="color:#cc0000">N<?php echo number_format($payment_receipt['balance_before'], 0); ?></span></p>';
h+='<p class="big"><span>PAYMENT AMOUNT:</span><span>N<?php echo number_format($payment_receipt['payment_amount'], 0); ?></span></p>';
<?php if ($payment_receipt['transfer_amount'] > 0) : ?>h+='<p><span>  - Via Transfer (<?php echo esc_js($payment_receipt['bank_name']); ?>):</span><span>N<?php echo number_format($payment_receipt['transfer_amount'], 0); ?></span></p>';<?php endif; ?>
<?php if ($payment_receipt['cash_amount'] > 0) : ?>h+='<p><span>  - Via Cash:</span><span>N<?php echo number_format($payment_receipt['cash_amount'], 0); ?></span></p>';<?php endif; ?>
<?php if ($payment_receipt['home_amount'] > 0) : ?>h+='<p><span>  - Home Calculation:</span><span>N<?php echo number_format($payment_receipt['home_amount'], 0); ?></span></p>';<?php endif; ?>
h+='</div>';
h+='<div class="total">';
h+='<p style="color:<?php echo $payment_receipt['new_balance'] > 0 ? '#cc0000' : '#008800'; ?>"><span>NEW BALANCE:</span><span>N<?php echo number_format($payment_receipt['new_balance'], 0); ?></span></p>';
h+='</div>';
h+='<div class="footer"><p>Payment received with thanks!</p><p style="margin-top:5px">Powered by BendlessTech</p></div>';
h+='</body></html>';
w.document.write(h);w.document.close();
w.onload=function(){setTimeout(function(){w.print()},300)};
}
function closePayModal(){document.getElementById('pay-modal').style.display='none';window.location.href='<?php echo esc_url(add_query_arg('t', time(), remove_query_arg(array('pay_done','pk')))); ?>'}
</script>
<?php endif; ?>

<script>
// Bluetooth thermal printer support
var bluetoothDevice = null;
var printerCharacteristic = null;

async function connectBluetoothPrinter() {
    try {
        bluetoothDevice = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb', '49535343-fe7d-4ae5-8fa9-9fafd205e455', 'e7810a71-73ae-499d-8c15-faa9aef0c3f2']
        });
        const server = await bluetoothDevice.gatt.connect();
        const serviceUUIDs = ['000018f0-0000-1000-8000-00805f9b34fb', '49535343-fe7d-4ae5-8fa9-9fafd205e455', 'e7810a71-73ae-499d-8c15-faa9aef0c3f2'];
        for (let uuid of serviceUUIDs) {
            try {
                const service = await server.getPrimaryService(uuid);
                const characteristics = await service.getCharacteristics();
                for (let char of characteristics) {
                    if (char.properties.write || char.properties.writeWithoutResponse) {
                        printerCharacteristic = char;
                        return true;
                    }
                }
            } catch (e) { continue; }
        }
        const services = await server.getPrimaryServices();
        for (let service of services) {
            const chars = await service.getCharacteristics();
            for (let char of chars) {
                if (char.properties.write || char.properties.writeWithoutResponse) {
                    printerCharacteristic = char;
                    return true;
                }
            }
        }
        throw new Error('No writable characteristic found');
    } catch (error) {
        console.error('Bluetooth connection failed:', error);
        return false;
    }
}

async function printToBluetoothPrinter(text) {
    if (!printerCharacteristic) {
        const connected = await connectBluetoothPrinter();
        if (!connected) return false;
    }
    try {
        const encoder = new TextEncoder();
        await printerCharacteristic.writeValue(new Uint8Array([0x1B, 0x40]));
        const textData = encoder.encode(text);
        for (let i = 0; i < textData.length; i += 100) {
            await printerCharacteristic.writeValue(textData.slice(i, i + 100));
            await new Promise(r => setTimeout(r, 50));
        }
        await printerCharacteristic.writeValue(new Uint8Array([0x0A, 0x0A, 0x0A, 0x1D, 0x56, 0x00]));
        return true;
    } catch (error) {
        console.error('Print failed:', error);
        return false;
    }
}

// Prevent form resubmission on back button - but do NOT auto-reload
if(window.history.replaceState)window.history.replaceState(null,null,window.location.href);

window.addEventListener('pageshow', function(event) {
    var navEntry = null;
    if (window.performance && typeof window.performance.getEntriesByType === 'function') {
        navEntry = window.performance.getEntriesByType('navigation')[0];
    }
    var isBackForward = false;
    // Some browsers use back_forward while others expose back-forward.
    if (navEntry && typeof navEntry.type === 'string') {
        isBackForward = navEntry.type === 'back_forward' || navEntry.type === 'back-forward';
    }
    if (event.persisted || isBackForward) {
        window.location.reload();
    }
});
</script>
</body>
</html>
