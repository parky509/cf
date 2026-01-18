<?php
/**
 * Take Order Page Template - REBUILT WITH CONFIRMATION POPUP & FIXED RECEIPT MODAL
 * Uses direct form POST for reliability
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure database tables exist
CFI_Database::create_tables();

$message = '';
$message_type = '';
$receipt_data = null;

// Process order submission
if (isset($_POST['cfi_submit_order']) && wp_verify_nonce($_POST['cfi_order_nonce'], 'cfi_take_order')) {
    global $wpdb;
    
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $transfer_amount = floatval($_POST['transfer_amount']);
    $cash_amount = floatval($_POST['cash_amount']);
    $bank_name = sanitize_text_field($_POST['bank_name']);
    $items = isset($_POST['items']) ? $_POST['items'] : array();
    
    // Validate customer name for transfer payments
    if ($payment_method === 'transfer' && empty($customer_name)) {
        $message = 'Customer name is required for transfer/card payments!';
        $message_type = 'error';
    } else {
        $order_items = array();
        $total_qty = 0;
        $total_amount = 0;
        $total_discount = 0;
        
        foreach ($items as $item) {
            $product_id = intval($item['product_id']);
            $quantity = floatval($item['quantity']);
            $discount = floatval($item['discount']);
            
            if ($quantity > 0 && $product_id > 0) {
                $product = CFI_Products::get($product_id);
                if ($product) {
                    $item_total = ($product->price * $quantity) - $discount;
                    $total_qty += $quantity;
                    $total_amount += ($product->price * $quantity);
                    $total_discount += $discount;
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
        
        if (empty($order_items)) {
            $message = 'Please add at least one item to the order!';
            $message_type = 'error';
        } else {
            $grand_total = $total_amount - $total_discount;
            $order_number = 'ORD-' . gmdate('Ymd') . '-' . substr(uniqid(), -6);
            
            // Insert order
            $orders_table = $wpdb->prefix . 'cfi_orders';
            $result = $wpdb->insert(
                $orders_table,
                array(
                    'order_number' => $order_number,
                    'order_type' => 'cash',
                    'customer_name' => $customer_name,
                    'total_quantity' => $total_qty,
                    'total_amount' => $total_amount,
                    'discount_amount' => $total_discount,
                    'grand_total' => $grand_total,
                    'payment_method' => $payment_method,
                    'transfer_amount' => $transfer_amount,
                    'cash_amount' => $cash_amount,
                    'bank_name' => $bank_name,
                    'staff_id' => get_current_user_id(),
                    'order_date' => current_time('Y-m-d'),
                    'order_time' => current_time('H:i:s'),
                    'status' => 'completed'
                ),
                array('%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%f', '%f', '%s', '%d', '%s', '%s', '%s')
            );
            
            if ($result) {
                $order_id = $wpdb->insert_id;
                
                // Insert order items
                $items_table = $wpdb->prefix . 'cfi_order_items';
                foreach ($order_items as $item) {
                    $wpdb->insert(
                        $items_table,
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
                    
                    // Update stock cash_supply column for this product
                    CFI_Stock::update_cash_supply($item['product_id'], $item['quantity'], current_time('Y-m-d'));
                }
                
                // Record transfer if applicable
                if ($transfer_amount > 0) {
                    $transfer_table = $wpdb->prefix . 'cfi_transfer_history';
                    $wpdb->insert(
                        $transfer_table,
                        array(
                            'source' => 'order',
                            'source_id' => $order_id,
                            'customer_name' => $customer_name,
                            'amount' => $transfer_amount,
                            'bank_name' => $bank_name,
                            'staff_id' => get_current_user_id(),
                            'transfer_date' => current_time('Y-m-d'),
                            'transfer_time' => current_time('H:i:s')
                        ),
                        array('%s', '%d', '%s', '%f', '%s', '%d', '%s', '%s')
                    );
                }
                
                // Update financial summary
                CFI_Financial::update_daily_summary(current_time('Y-m-d'));
                
                // Prepare receipt data and store in transient for PRG pattern
                $receipt_data = array(
                    'order_number' => $order_number,
                    'date' => current_time('d/m/Y'),
                    'time' => current_time('H:i'),
                    'customer_name' => $customer_name,
                    'items' => $order_items,
                    'total_qty' => $total_qty,
                    'subtotal' => $total_amount,
                    'discount' => $total_discount,
                    'grand_total' => $grand_total,
                    'payment_method' => $payment_method,
                    'transfer_amount' => $transfer_amount,
                    'cash_amount' => $cash_amount,
                    'bank_name' => $bank_name,
                    'staff' => wp_get_current_user()->display_name
                );
                
                // Store receipt in transient (expires in 5 minutes) for PRG pattern
                $receipt_key = 'cfi_order_receipt_' . get_current_user_id() . '_' . time();
                set_transient($receipt_key, $receipt_data, 5 * MINUTE_IN_SECONDS);
                
                // Redirect to same page with receipt key to prevent form resubmission
                $redirect_url = add_query_arg(array(
                    'order_success' => '1',
                    'receipt_key' => $receipt_key
                ), strtok($_SERVER['REQUEST_URI'], '?'));
                
                wp_redirect($redirect_url);
                exit;
            } else {
                $message = 'Failed to save order. Please try again.';
                $message_type = 'error';
            }
        }
    }
}

// Check for order success and load receipt from transient
$receipt_data = null;
if (isset($_GET['order_success']) && $_GET['order_success'] === '1' && isset($_GET['receipt_key'])) {
    $receipt_data = get_transient($_GET['receipt_key']);
    if ($receipt_data) {
        delete_transient($_GET['receipt_key']); // Delete after reading
    }
}

if (!function_exists('cfi_format_receipt_value')) {
    function cfi_format_receipt_value($value) {
        $formatted = number_format((float) $value, 2, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }
}

// Get products
$products = CFI_Products::get_all();
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f8fafc; min-height: 100vh; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
        
        .page-header {
            background: linear-gradient(135deg, #001943, #002960);
            color: white !important;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .page-header h1 { margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 0.4rem; color: #ffffff !important; font-weight: 600; }
        .page-header h1 i { color: #ffffff !important; font-size: 0.75rem; }
        .page-header a, .page-header span { color: #001943 !important; }
        .header-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.65rem;
            transition: all 0.3s;
        }
        .btn-primary { background: #001943; color: white !important; }
        .btn-success { background: #16a34a; color: white !important; }
        .btn-outline { background: #ffffff !important; border: 2px solid #001943; color: #001943 !important; }
        .btn-print { background: #7c3aed; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-lg { padding: 1rem 1.5rem; font-size: 1rem; }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .glass {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,25,67,0.1);
            border: 2px solid rgba(0,25,67,0.1);
            margin-bottom: 1.5rem;
        }
        .glass h3 { color: #001943; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943; font-size: 0.85rem; }
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-input:focus { outline: none; border-color: #001943; }
        .form-input.required { border-color: #dc2626; }
        
        .table-wrapper { overflow-x: auto; margin: 0 -0.5rem; }
        .order-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 600px; }
        .order-table th { background: #001943; color: white; padding: 0.75rem 0.5rem; text-align: left; white-space: nowrap; }
        .order-table td { padding: 0.5rem; border-bottom: 1px solid #e2e8f0; }
        .order-table input { width: 70px; padding: 0.4rem; border: 1px solid #e2e8f0; border-radius: 4px; text-align: center; }
        .order-table .product-name { font-weight: 600; color: #001943; }
        .order-table .price { color: #001943; font-weight: 500; }
        .order-table .row-total { font-weight: 600; color: #16a34a; }
        
        .order-summary {
            background: linear-gradient(135deg, #001943, #002960);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        .summary-item { text-align: center; }
        .summary-label { font-size: 0.75rem; opacity: 0.9; margin-bottom: 0.25rem; }
        .summary-value { font-size: 1.25rem; font-weight: 700; }
        .summary-value.grand { font-size: 1.75rem; color: #4ade80; }
        
        .payment-section { margin-top: 1.5rem; }
        .payment-methods { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .payment-method {
            flex: 1;
            min-width: 120px;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            position: relative;
        }
        .payment-method:hover { border-color: #001943; }
        .payment-method.selected { border-color: #001943; background: rgba(0,25,67,0.15); box-shadow: 0 0 0 3px rgba(0,25,67,0.1); }
        .payment-method i { display: block; font-size: 1.5rem; color: #001943; margin-bottom: 0.5rem; }
        .payment-method span { font-weight: 600; color: #001943; font-size: 0.85rem; }
        
        .bank-options, .customer-name-group { margin: 1rem 0; }
        .bank-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }
        .bank-option input { width: auto; }
        
        .payment-amounts { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f1f5f9;
            border-radius: 8px;
        }
        .checkbox-group input { width: 20px; height: 20px; }
        .checkbox-group span { font-weight: 500; color: #001943; }
        
        /* Receipt Modal */
        .receipt-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }
        .receipt-content {
            background: white;
            max-width: 400px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 12px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        .receipt-header {
            background: #001943;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .receipt-body { padding: 1.5rem; }
        .receipt-company { text-align: center; margin-bottom: 1rem; border-bottom: 2px dashed #e2e8f0; padding-bottom: 1rem; }
        .receipt-company h2 { color: #001943; margin: 0 0 0.25rem 0; }
        .receipt-company p { color: #64748b; font-size: 0.8rem; margin: 0; }
        .receipt-info { margin-bottom: 1rem; font-size: 0.85rem; }
        .receipt-info p { margin: 0.25rem 0; display: flex; justify-content: space-between; }
        .receipt-items { border-top: 1px solid #001943; border-bottom: 1px solid #001943; padding: 0.75rem 0; margin: 0.75rem 0; }
        .receipt-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; table-layout: fixed; border: 1px solid #001943; }
        .receipt-table th { text-align: center; font-weight: 600; border: 1px solid #001943; padding: 0.45rem 0.3rem; background: #f1f5f9; }
        .receipt-table td { padding: 0.45rem 0.3rem; text-align: center; border: 1px solid #001943; line-height: 1.3; }
        .receipt-table th:first-child, .receipt-table td:first-child { text-align: left; width: 40%; }
        .receipt-table th:nth-child(2), .receipt-table td:nth-child(2) { text-align: right; width: 20%; }
        .receipt-table th:nth-child(3), .receipt-table td:nth-child(3) { width: 14%; }
        .receipt-table th:nth-child(4), .receipt-table td:nth-child(4) { text-align: right; width: 26%; font-weight: 600; }
        .receipt-table .discount-row td { font-size: 0.7rem; font-style: italic; background: #f8fafc; }
        .receipt-table .discount-label { text-align: left; }
        .receipt-table .discount-value { text-align: right; color: #dc2626; font-weight: 600; }
        .receipt-totals { margin-top: 0.5rem; font-size: 0.85rem; }
        .receipt-totals p { display: flex; justify-content: space-between; margin: 0.25rem 0; }
        .receipt-totals .grand { font-size: 1.1rem; font-weight: 700; color: #001943; border-top: 2px solid #001943; padding-top: 0.5rem; margin-top: 0.5rem; }
        .receipt-footer { text-align: center; margin-top: 1rem; padding-top: 1rem; border-top: 2px dashed #e2e8f0; font-size: 0.75rem; color: #64748b; }
        .receipt-actions { display: flex; gap: 0.5rem; padding: 1rem; background: #f1f5f9; }
        .receipt-actions .btn { flex: 1; justify-content: center; }
        
        /* Confirmation Modal */
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }
        .confirm-modal.active { display: flex; }
        .confirm-content {
            background: white;
            max-width: 500px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 12px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        .confirm-header {
            background: #001943;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .confirm-header h3 { margin: 0; }
        .confirm-close { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .confirm-body { padding: 1.5rem; }
        .confirm-items { margin: 1rem 0; }
        .confirm-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
        .confirm-totals { background: #001943; color: white; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
        .confirm-totals p { display: flex; justify-content: space-between; margin: 0.25rem 0; }
        .confirm-totals .grand { font-size: 1.25rem; font-weight: 700; color: #4ade80; }
        .confirm-actions { display: flex; gap: 0.5rem; }
        .confirm-actions .btn { flex: 1; justify-content: center; }
        
        @media print {
            body * { visibility: hidden; }
            .receipt-body, .receipt-body * { visibility: visible; }
            .receipt-body { position: absolute; left: 0; top: 0; width: 80mm; }
        }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; padding: 0.5rem 0.75rem; }
            .page-header h1 { font-size: 0.8rem; }
            .order-table { font-size: 0.75rem; }
            .order-table input { width: 50px; padding: 0.3rem; }
            .btn { font-size: 0.7rem; padding: 0.4rem 0.6rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-cart-plus"></i> Take Order</h1>
        <div class="header-buttons">
            <?php $order_history = get_page_by_path('cfi-order-history'); ?>
            <a href="<?php echo $order_history ? esc_url(get_permalink($order_history->ID)) : home_url('/order-history/'); ?>" class="btn btn-outline" style="background: white !important; color: #001943 !important; font-weight: 600;">
                <i class="fas fa-history" style="color: #001943 !important;"></i> Order History
            </a>
            <?php $transfer_history = get_page_by_path('cfi-transfer-history'); ?>
            <a href="<?php echo $transfer_history ? esc_url(get_permalink($transfer_history->ID)) : home_url('/transfer-history/'); ?>" class="btn btn-outline" style="background: white !important; color: #001943 !important; font-weight: 600;">
                <i class="fas fa-exchange-alt" style="color: #001943 !important;"></i> Transfer History
            </a>
        </div>
    </div>
    
    <?php if ($message) : ?>
    <div class="alert alert-<?php echo esc_attr($message_type); ?>">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo esc_html($message); ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" id="order-form">
        <?php wp_nonce_field('cfi_take_order', 'cfi_order_nonce'); ?>
        <input type="hidden" name="payment_method" id="payment-method" value="transfer">
        
        <div class="glass">
            <h3><i class="fas fa-shopping-cart"></i> Order Items</h3>
            
            <div class="table-wrapper">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price (₦)</th>
                            <th>Qty</th>
                            <th>Disc (₦)</th>
                            <th>Total (₦)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $idx => $product) : ?>
                        <tr class="order-row" data-price="<?php echo esc_attr($product->price); ?>">
                            <td class="product-name">
                                <?php echo esc_html($product->name); ?>
                                <input type="hidden" name="items[<?php echo $idx; ?>][product_id]" value="<?php echo esc_attr($product->id); ?>">
                            </td>
                            <td class="price"><?php echo number_format($product->price, 0); ?></td>
                            <td>
                                <input type="number" name="items[<?php echo $idx; ?>][quantity]" class="qty-input" value="0" min="0" step="0.5" oninput="calculateRow(this)">
                            </td>
                            <td>
                                <input type="number" name="items[<?php echo $idx; ?>][discount]" class="disc-input" value="0" min="0" step="1" oninput="calculateRow(this)">
                            </td>
                            <td class="row-total">0</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="order-summary">
                <div class="summary-item">
                    <div class="summary-label">Total Qty</div>
                    <div class="summary-value" id="total-qty">0</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Subtotal</div>
                    <div class="summary-value" id="subtotal">₦0</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Discount</div>
                    <div class="summary-value" id="total-discount">₦0</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Grand Total</div>
                    <div class="summary-value grand" id="grand-total">₦0</div>
                </div>
            </div>
        </div>
        
        <div class="glass payment-section">
            <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
            
            <div class="payment-methods">
                <div class="payment-method selected" data-method="transfer" onclick="togglePayment(this)">
                    <input type="checkbox" id="use_transfer" style="display: none;" checked>
                    <i class="fas fa-credit-card"></i>
                    <span>Transfer/Card</span>
                </div>
                <div class="payment-method" data-method="cash" onclick="togglePayment(this)">
                    <input type="checkbox" id="use_cash" style="display: none;">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Cash</span>
                </div>
            </div>
            <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;"><i class="fas fa-info-circle"></i> Click a payment method to select it. You can select both for split payments.</p>
            
            <!-- Customer Name (Required for Transfer) -->
            <div class="customer-name-group" id="customer-name-group" style="display: block;">
                <div class="form-group">
                    <label for="customer_name"><i class="fas fa-user"></i> Customer Name <span style="color: #dc2626;">*</span> (Required for Transfer)</label>
                    <input type="text" id="customer_name" name="customer_name" class="form-input" placeholder="Enter customer name for transfer...">
                </div>
            </div>
            
            <div class="bank-options" id="bank-options" style="display: block;">
                <label class="bank-option">
                    <input type="radio" name="bank_name" value="Moniepoint MFB" checked>
                    <span>Moniepoint MFB</span>
                </label>
                <label class="bank-option">
                    <input type="radio" name="bank_name" value="Access Bank PLC">
                    <span>Access Bank PLC</span>
                </label>
            </div>
            
            <div class="payment-amounts">
                <div class="form-group" id="transfer-group" style="display: block;">
                    <label>Transfer Amount (₦)</label>
                    <input type="number" id="transfer_amount" name="transfer_amount" class="form-input" value="0" min="0" step="0.01" oninput="updatePaymentBalance()">
                </div>
                <div class="form-group" id="cash-group" style="display: none;">
                    <label>Cash Amount (₦)</label>
                    <input type="number" id="cash_amount" name="cash_amount" class="form-input" value="0" min="0" step="0.01" oninput="updatePaymentBalance()">
                </div>
            </div>
            <div id="payment-balance" style="display: none; padding: 0.75rem; background: #fef3c7; border-radius: 8px; margin-top: 0.5rem; font-size: 0.85rem; color: #92400e;">
                <i class="fas fa-exclamation-triangle"></i> <span id="payment-balance-text"></span>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="confirm-payment" required>
                <span>I confirm that payment has been received</span>
            </div>
            
            <button type="button" onclick="showConfirmation()" class="btn btn-success btn-lg" style="width: 100%;">
                <i class="fas fa-check-circle"></i> Review & Submit Order
            </button>
        </div>
    </form>
</div>

<!-- Confirmation Modal -->
<div class="confirm-modal" id="confirm-modal">
    <div class="confirm-content">
        <div class="confirm-header">
            <h3><i class="fas fa-clipboard-check"></i> Review Order</h3>
            <button type="button" class="confirm-close" onclick="hideConfirmation()">&times;</button>
        </div>
        <div class="confirm-body">
            <h4 style="color: #001943; margin-bottom: 1rem;">Order Items</h4>
            <div class="confirm-items" id="confirm-items-list">
                <!-- Populated by JavaScript -->
            </div>
            
            <div class="confirm-totals" id="confirm-totals">
                <!-- Populated by JavaScript -->
            </div>
            
            <p id="confirm-payment-info" style="text-align: center; font-weight: 600; color: #001943; margin: 1rem 0;"></p>
            
            <div class="confirm-actions">
                <button type="button" onclick="hideConfirmation()" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Edit Order
                </button>
                <button type="button" onclick="submitOrder()" class="btn btn-success">
                    <i class="fas fa-check"></i> Confirm & Submit
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($receipt_data) : ?>
<!-- Receipt Modal -->
<div class="receipt-modal" id="receipt-modal">
    <div class="receipt-content">
        <div class="receipt-header">
            <h3><i class="fas fa-receipt"></i> Receipt</h3>
            <button onclick="closeReceipt()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div class="receipt-body" id="receipt-print-area">
            <div class="receipt-company">
                <h2>Chinemerem Foods</h2>
                <p>Inventory Management System</p>
            </div>
            
            <div class="receipt-info">
                <p><span>Order #:</span> <strong><?php echo esc_html($receipt_data['order_number']); ?></strong></p>
                <p><span>Date:</span> <?php echo esc_html($receipt_data['date']); ?></p>
                <p><span>Time:</span> <?php echo esc_html($receipt_data['time']); ?></p>
                <?php if (!empty($receipt_data['customer_name'])) : ?>
                <p><span>Customer:</span> <?php echo esc_html($receipt_data['customer_name']); ?></p>
                <?php endif; ?>
                <p><span>Staff:</span> <?php echo esc_html($receipt_data['staff']); ?></p>
            </div>
            
            <div class="receipt-items">
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipt_data['items'] as $item) : ?>
                        <tr class="item-row">
                            <td><?php echo esc_html($item['product_name']); ?></td>
                            <td>₦<?php echo cfi_format_receipt_value($item['price']); ?></td>
                            <td><?php echo cfi_format_receipt_value($item['quantity']); ?></td>
                            <td>₦<?php echo cfi_format_receipt_value($item['total']); ?></td>
                        </tr>
                        <tr class="discount-row">
                            <td class="discount-label" colspan="3">Discount</td>
                            <td class="discount-value"><?php echo $item['discount'] > 0 ? '-₦' . cfi_format_receipt_value($item['discount']) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="receipt-totals">
                <p><span>Subtotal:</span> <span><strong>₦<?php echo cfi_format_receipt_value($receipt_data['subtotal']); ?></strong></span></p>
                <p><span>Total Discount:</span> <span><strong>-₦<?php echo cfi_format_receipt_value($receipt_data['discount']); ?></strong></span></p>
                <p class="grand"><span>Grand Total:</span> <span><strong>₦<?php echo cfi_format_receipt_value($receipt_data['grand_total']); ?></strong></span></p>
                <p><span>Payment:</span> <span><?php echo ucfirst($receipt_data['payment_method']); ?></span></p>
                <?php if ($receipt_data['transfer_amount'] > 0) : ?>
                <p><span>Transfer:</span> <span>₦<?php echo cfi_format_receipt_value($receipt_data['transfer_amount']); ?></span></p>
                <?php endif; ?>
                <?php if ($receipt_data['cash_amount'] > 0) : ?>
                <p><span>Cash:</span> <span>₦<?php echo cfi_format_receipt_value($receipt_data['cash_amount']); ?></span></p>
                <?php endif; ?>
            </div>
            
            <div class="receipt-footer">
                <p>Thank you for your patronage!</p>
                <p>Powered by BendlessTech</p>
            </div>
        </div>
        <div class="receipt-actions">
            <button onclick="printReceipt()" class="btn btn-print">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="closeReceipt()" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Order
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function calculateRow(input) {
    var row = input.closest('.order-row');
    var price = parseFloat(row.dataset.price) || 0;
    var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    var disc = parseFloat(row.querySelector('.disc-input').value) || 0;
    var total = (price * qty) - disc;
    if (total < 0) total = 0;
    row.querySelector('.row-total').textContent = total.toLocaleString();
    calculateTotals();
}

function calculateTotals() {
    var rows = document.querySelectorAll('.order-row');
    var totalQty = 0;
    var subtotal = 0;
    var totalDisc = 0;
    
    rows.forEach(function(row) {
        var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        var disc = parseFloat(row.querySelector('.disc-input').value) || 0;
        var price = parseFloat(row.dataset.price) || 0;
        totalQty += qty;
        subtotal += (price * qty);
        totalDisc += disc;
    });
    
    var grandTotal = subtotal - totalDisc;
    
    document.getElementById('total-qty').textContent = totalQty;
    document.getElementById('subtotal').textContent = '₦' + subtotal.toLocaleString();
    document.getElementById('total-discount').textContent = '₦' + totalDisc.toLocaleString();
    document.getElementById('grand-total').textContent = '₦' + grandTotal.toLocaleString();
    
    // Auto-fill payment amount based on selected methods
    var useTransfer = document.getElementById('use_transfer').checked;
    var useCash = document.getElementById('use_cash').checked;
    
    if (useTransfer && useCash) {
        // Split payment - don't auto-fill, let user decide
    } else if (useTransfer) {
        document.getElementById('transfer_amount').value = grandTotal;
        document.getElementById('cash_amount').value = 0;
    } else if (useCash) {
        document.getElementById('cash_amount').value = grandTotal;
        document.getElementById('transfer_amount').value = 0;
    }
    
    updatePaymentBalance();
}

function togglePayment(el) {
    el.classList.toggle('selected');
    var method = el.dataset.method;
    
    if (method === 'transfer') {
        var checkbox = document.getElementById('use_transfer');
        checkbox.checked = !checkbox.checked;
        document.getElementById('transfer-group').style.display = checkbox.checked ? 'block' : 'none';
        document.getElementById('bank-options').style.display = checkbox.checked ? 'block' : 'none';
        document.getElementById('customer-name-group').style.display = checkbox.checked ? 'block' : 'none';
        if (!checkbox.checked) {
            document.getElementById('transfer_amount').value = 0;
        }
    } else if (method === 'cash') {
        var checkbox = document.getElementById('use_cash');
        checkbox.checked = !checkbox.checked;
        document.getElementById('cash-group').style.display = checkbox.checked ? 'block' : 'none';
        if (!checkbox.checked) {
            document.getElementById('cash_amount').value = 0;
        }
    }
    
    // Update hidden payment_method field
    var useTransfer = document.getElementById('use_transfer').checked;
    var useCash = document.getElementById('use_cash').checked;
    if (useTransfer && useCash) {
        document.getElementById('payment-method').value = 'split';
    } else if (useTransfer) {
        document.getElementById('payment-method').value = 'transfer';
    } else {
        document.getElementById('payment-method').value = 'cash';
    }
    
    calculateTotals();
}

function updatePaymentBalance() {
    var grandTotal = 0;
    var rows = document.querySelectorAll('.order-row');
    rows.forEach(function(row) {
        var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        var disc = parseFloat(row.querySelector('.disc-input').value) || 0;
        var price = parseFloat(row.dataset.price) || 0;
        grandTotal += (price * qty) - disc;
    });
    
    var transferAmt = parseFloat(document.getElementById('transfer_amount').value) || 0;
    var cashAmt = parseFloat(document.getElementById('cash_amount').value) || 0;
    var totalPayment = transferAmt + cashAmt;
    var diff = grandTotal - totalPayment;
    
    var balanceDiv = document.getElementById('payment-balance');
    var balanceText = document.getElementById('payment-balance-text');
    
    if (Math.abs(diff) > 0.01 && grandTotal > 0) {
        balanceDiv.style.display = 'block';
        if (diff > 0) {
            balanceText.textContent = 'Payment is ₦' + diff.toLocaleString() + ' short of grand total';
            balanceDiv.style.background = '#fee2e2';
            balanceDiv.style.color = '#991b1b';
        } else {
            balanceText.textContent = 'Payment exceeds grand total by ₦' + Math.abs(diff).toLocaleString();
            balanceDiv.style.background = '#fef3c7';
            balanceDiv.style.color = '#92400e';
        }
    } else {
        balanceDiv.style.display = 'none';
    }
}

// Show confirmation modal
function showConfirmation() {
    // Check for negative values first
    var hasNegatives = false;
    var negativeFields = [];
    
    document.querySelectorAll('#order-form input[type="number"]').forEach(function(input) {
        var val = parseFloat(input.value) || 0;
        if (val < 0) {
            hasNegatives = true;
            var row = input.closest('.order-row');
            var label = row ? row.querySelector('.product-name')?.textContent : 'Field';
            negativeFields.push(label || 'Amount field');
            input.style.borderColor = '#ef4444';
            input.style.backgroundColor = '#fef2f2';
        }
    });
    
    if (hasNegatives) {
        if (typeof CFI !== 'undefined' && CFI.negativeValuePopup) {
            CFI.negativeValuePopup.show(negativeFields);
        } else {
            alert('Negative values are not allowed! Please check your input and try again.');
        }
        return;
    }
    
    var confirmCheckbox = document.getElementById('confirm-payment');
    if (!confirmCheckbox.checked) {
        alert('Please confirm that payment has been received!');
        confirmCheckbox.focus();
        return;
    }
    
    var method = document.getElementById('payment-method').value;
    var customerName = document.getElementById('customer_name').value.trim();
    
    if (method === 'transfer' && !customerName) {
        alert('Customer name is required for transfer/card payments!');
        document.getElementById('customer_name').classList.add('required');
        document.getElementById('customer_name').focus();
        return;
    }
    
    // Build items list for confirmation
    var rows = document.querySelectorAll('.order-row');
    var itemsHtml = '';
    var hasItems = false;
    var totalQty = 0;
    var subtotal = 0;
    var totalDisc = 0;
    
    rows.forEach(function(row) {
        var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        var disc = parseFloat(row.querySelector('.disc-input').value) || 0;
        var price = parseFloat(row.dataset.price) || 0;
        var name = row.querySelector('.product-name').textContent.trim();
        
        if (qty > 0) {
            hasItems = true;
            var itemTotal = (price * qty) - disc;
            totalQty += qty;
            subtotal += (price * qty);
            totalDisc += disc;
            itemsHtml += '<div class="confirm-item"><span>' + name + ' x ' + qty + '</span><span>₦' + itemTotal.toLocaleString() + '</span></div>';
        }
    });
    
    if (!hasItems) {
        alert('Please add at least one item to the order!');
        return;
    }
    
    var grandTotal = subtotal - totalDisc;
    
    document.getElementById('confirm-items-list').innerHTML = itemsHtml;
    
    var totalsHtml = '<p><span>Total Qty:</span> <span>' + totalQty + '</span></p>';
    totalsHtml += '<p><span>Subtotal:</span> <span>₦' + subtotal.toLocaleString() + '</span></p>';
    if (totalDisc > 0) {
        totalsHtml += '<p><span>Discount:</span> <span>-₦' + totalDisc.toLocaleString() + '</span></p>';
    }
    totalsHtml += '<p class="grand"><span>Grand Total:</span> <span>₦' + grandTotal.toLocaleString() + '</span></p>';
    document.getElementById('confirm-totals').innerHTML = totalsHtml;
    
    var paymentInfo = 'Payment: ' + method.charAt(0).toUpperCase() + method.slice(1);
    if (customerName) {
        paymentInfo += ' | Customer: ' + customerName;
    }
    document.getElementById('confirm-payment-info').textContent = paymentInfo;
    
    document.getElementById('confirm-modal').classList.add('active');
}

function hideConfirmation() {
    document.getElementById('confirm-modal').classList.remove('active');
}

function submitOrder() {
    hideConfirmation();
    // Add hidden submit button and trigger form submission
    var form = document.getElementById('order-form');
    var submitBtn = document.createElement('input');
    submitBtn.type = 'hidden';
    submitBtn.name = 'cfi_submit_order';
    submitBtn.value = '1';
    form.appendChild(submitBtn);
    form.submit();
}

// Bluetooth thermal printer connection
var bluetoothDevice = null;
var printerCharacteristic = null;

async function connectBluetoothPrinter() {
    try {
        // Request Bluetooth device with common thermal printer services
        bluetoothDevice = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb', '49535343-fe7d-4ae5-8fa9-9fafd205e455', 'e7810a71-73ae-499d-8c15-faa9aef0c3f2']
        });
        
        const server = await bluetoothDevice.gatt.connect();
        
        // Try common printer service UUIDs
        const serviceUUIDs = [
            '000018f0-0000-1000-8000-00805f9b34fb',
            '49535343-fe7d-4ae5-8fa9-9fafd205e455',
            'e7810a71-73ae-499d-8c15-faa9aef0c3f2'
        ];
        
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
        
        // Fallback: get all services and find writable characteristic
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
        if (!connected) {
            alert('Could not connect to Bluetooth printer. Using browser print instead.');
            return false;
        }
    }
    
    try {
        // ESC/POS commands
        const encoder = new TextEncoder();
        const ESC = 0x1B;
        const GS = 0x1D;
        
        // Initialize printer
        let commands = new Uint8Array([ESC, 0x40]); // ESC @ - Initialize
        await printerCharacteristic.writeValue(commands);
        
        // Print text
        const textData = encoder.encode(text);
        const chunkSize = 100;
        for (let i = 0; i < textData.length; i += chunkSize) {
            const chunk = textData.slice(i, i + chunkSize);
            await printerCharacteristic.writeValue(chunk);
            await new Promise(r => setTimeout(r, 50));
        }
        
        // Feed paper and cut
        commands = new Uint8Array([0x0A, 0x0A, 0x0A, GS, 0x56, 0x00]);
        await printerCharacteristic.writeValue(commands);
        
        return true;
    } catch (error) {
        console.error('Print failed:', error);
        return false;
    }
}

function formatReceiptNumber(value) {
    var num = Number(value);
    if (Number.isNaN(num)) {
        return '0';
    }
    return num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function generateESCPOSReceipt() {
    var text = '';
    var line = '--------------------------------';
    
    // Header - centered
    text += '       CHINEMEREM FOODS\n';
    text += '        Sales Receipt\n';
    text += line + '\n';
    
    // Order info
    text += 'Order: <?php echo isset($receipt_data['order_number']) ? esc_js($receipt_data['order_number']) : ''; ?>\n';
    text += 'Date: <?php echo isset($receipt_data['date']) ? esc_js($receipt_data['date']) : ''; ?>\n';
    text += 'Time: <?php echo isset($receipt_data['time']) ? esc_js($receipt_data['time']) : ''; ?>\n';
    <?php if (isset($receipt_data['customer_name']) && !empty($receipt_data['customer_name'])) : ?>
    text += 'Customer: <?php echo esc_js($receipt_data['customer_name']); ?>\n';
    <?php endif; ?>
    text += 'Staff: <?php echo isset($receipt_data['staff']) ? esc_js($receipt_data['staff']) : ''; ?>\n';
    text += line + '\n';
    
    // Items
    text += 'ITEM'.padEnd(10) + '|' + 'PRICE'.padEnd(7) + '|' + 'QTY'.padEnd(4) + '|' + 'TOTAL'.padEnd(8) + '\n';
    text += line + '\n';
    <?php if (isset($receipt_data['items'])) : foreach ($receipt_data['items'] as $item) : ?>
    text += '<?php echo str_pad(substr(esc_js($item['product_name']), 0, 10), 10); ?>' +
        '|' + String(formatReceiptNumber('<?php echo esc_js($item['price']); ?>')).padStart(7) +
        '|' + String(formatReceiptNumber('<?php echo esc_js($item['quantity']); ?>')).padStart(4) +
        '|' + String(formatReceiptNumber('<?php echo esc_js($item['total']); ?>')).padStart(8) + '\n';
    text += '  Discount:' +
        String('<?php echo esc_js($item['discount'] > 0 ? '-N' . cfi_format_receipt_value($item['discount']) : '-'); ?>')
            .padStart(line.length - 11) + '\n\n';
    <?php endforeach; endif; ?>
    text += line + '\n';
    
    // Totals
    text += 'Subtotal:          N<?php echo isset($receipt_data['subtotal']) ? str_pad(cfi_format_receipt_value($receipt_data['subtotal']), 9, ' ', STR_PAD_LEFT) : '        0'; ?>\n';
    text += 'Total Discount:   -N<?php echo isset($receipt_data['discount']) ? str_pad(cfi_format_receipt_value($receipt_data['discount']), 9, ' ', STR_PAD_LEFT) : '        0'; ?>\n';
    text += line + '\n';
    text += 'GRAND TOTAL:       N<?php echo isset($receipt_data['grand_total']) ? str_pad(cfi_format_receipt_value($receipt_data['grand_total']), 9, ' ', STR_PAD_LEFT) : '        0'; ?>\n';
    text += 'Payment: <?php echo isset($receipt_data['payment_method']) ? ucfirst(esc_js($receipt_data['payment_method'])) : ''; ?>\n';
    text += line + '\n';
    
    // Footer
    text += '   Thank you for your patronage!\n';
    text += '      Powered by BendlessTech\n';
    text += '\n\n\n';
    
    return text;
}

async function printReceipt() {
    // Try Bluetooth printing first if available
    if ('bluetooth' in navigator) {
        var receiptText = generateESCPOSReceipt();
        var printed = await printToBluetoothPrinter(receiptText);
        if (printed) {
            alert('Receipt printed successfully!');
            return;
        }
    }
    
    // Fallback to browser print dialog
    var printWindow = window.open('', '_blank', 'width=400,height=700');
    
    if (!printWindow) {
        alert('Please allow popups to print receipts');
        return;
    }
    
    printWindow.document.write('<!DOCTYPE html><html><head><title>Print Receipt</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('@page{size:80mm auto;margin:0}');
    printWindow.document.write('*{margin:0;padding:0;box-sizing:border-box}');
    printWindow.document.write('html,body{width:100%!important;max-width:100%!important;margin:0!important;padding:0!important}');
    printWindow.document.write('body{font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.3;color:#000;background:#fff}');
    printWindow.document.write('.receipt{width:100%;padding:3mm}');
    printWindow.document.write('.header{text-align:center;padding:10px 0;border-bottom:3px double #000;margin-bottom:12px}');
    printWindow.document.write('.header h2{font-size:20px;font-weight:900;margin:0 0 5px;letter-spacing:1px;text-transform:uppercase}');
    printWindow.document.write('.header p{font-size:14px;margin:0;font-weight:700}');
    printWindow.document.write('.info{margin:10px 0;padding:10px 0;border-bottom:2px solid #000}');
    printWindow.document.write('.info-row{display:flex;justify-content:space-between;margin:6px 0;font-size:12px}');
    printWindow.document.write('.info-row .label{font-weight:600}');
    printWindow.document.write('.info-row .value{font-weight:900}');
    printWindow.document.write('.items-table{width:100%;margin:10px 0;border-collapse:collapse;font-size:12px;table-layout:fixed}');
    printWindow.document.write('.items-table th{background:#000;color:#fff;padding:9px 4px;font-size:11px;font-weight:900;text-align:center;border:2px solid #000}');
    printWindow.document.write('.items-table th:first-child{text-align:left;width:40%}');
    printWindow.document.write('.items-table th:nth-child(2){width:20%}');
    printWindow.document.write('.items-table th:nth-child(3){width:14%}');
    printWindow.document.write('.items-table th:nth-child(4){width:26%}');
    printWindow.document.write('.items-table td{padding:10px 4px;border:2px solid #000;vertical-align:middle;font-size:11px;line-height:1.4}');
    printWindow.document.write('.items-table td:first-child{text-align:left;font-weight:600}');
    printWindow.document.write('.items-table td:nth-child(2){text-align:right}');
    printWindow.document.write('.items-table td:nth-child(3){text-align:center}');
    printWindow.document.write('.items-table td:nth-child(4){text-align:right;font-weight:900;font-size:12px}');
    printWindow.document.write('.items-table .discount-row td{font-style:italic;background:#f3f3f3}');
    printWindow.document.write('.items-table .discount-label{text-align:left}');
    printWindow.document.write('.items-table .discount-value{text-align:right;color:#c00;font-weight:900}');
    printWindow.document.write('.items-table tr:nth-child(even){background:#f0f0f0}');
    printWindow.document.write('.totals{margin:12px 0;padding:10px 0;border-top:3px solid #000}');
    printWindow.document.write('.total-row{display:flex;justify-content:space-between;margin:6px 0;font-size:13px}');
    printWindow.document.write('.total-row .label{font-weight:700}');
    printWindow.document.write('.total-row .value{font-weight:900}');
    printWindow.document.write('.grand-total{background:#000;color:#fff;padding:12px 8px;margin:10px 0;font-size:16px;font-weight:900;display:flex;justify-content:space-between}');
    printWindow.document.write('.payment-info{margin:10px 0;padding:10px 0;border-top:2px dashed #000;border-bottom:2px dashed #000}');
    printWindow.document.write('.payment-row{display:flex;justify-content:space-between;margin:6px 0;font-size:12px}');
    printWindow.document.write('.payment-row .label{font-weight:600}');
    printWindow.document.write('.payment-row .value{font-weight:900}');
    printWindow.document.write('.footer{text-align:center;margin-top:12px;padding-top:10px;font-size:11px}');
    printWindow.document.write('.footer p{margin:4px 0}');
    printWindow.document.write('.footer .thanks{font-weight:900;font-size:13px}');
    printWindow.document.write('@media print{html,body{width:100%!important}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}');
    printWindow.document.write('</style></head><body>');
    
    // Build receipt content - full width 80mm format with PRICE column and per-item discounts
    var h = '<div class="receipt">';
    h += '<div class="header"><h2>CHINEMEREM FOODS</h2><p>*** SALES RECEIPT ***</p></div>';
    
    h += '<div class="info">';
    h += '<div class="info-row"><span class="label">Order No:</span><span class="value"><?php echo isset($receipt_data['order_number']) ? esc_js($receipt_data['order_number']) : ''; ?></span></div>';
    h += '<div class="info-row"><span class="label">Date:</span><span class="value"><?php echo isset($receipt_data['date']) ? esc_js($receipt_data['date']) : ''; ?></span></div>';
    h += '<div class="info-row"><span class="label">Time:</span><span class="value"><?php echo isset($receipt_data['time']) ? esc_js($receipt_data['time']) : ''; ?></span></div>';
    <?php if (isset($receipt_data['customer_name']) && !empty($receipt_data['customer_name'])) : ?>
    h += '<div class="info-row"><span class="label">Customer:</span><span class="value"><?php echo esc_js($receipt_data['customer_name']); ?></span></div>';
    <?php endif; ?>
    h += '<div class="info-row"><span class="label">Staff:</span><span class="value"><?php echo isset($receipt_data['staff']) ? esc_js($receipt_data['staff']) : ''; ?></span></div>';
    h += '</div>';
    
    // Table with ITEM, PRICE, QTY, AMOUNT columns
    h += '<table class="items-table">';
    h += '<tr><th>ITEM</th><th>PRICE</th><th>QTY</th><th>AMOUNT</th></tr>';
    <?php if (isset($receipt_data['items'])) : foreach ($receipt_data['items'] as $item) : ?>
    h += '<tr class="item-row">';
    h += '<td><?php echo esc_js($item['product_name']); ?></td>';
    h += '<td>₦<?php echo cfi_format_receipt_value($item['price']); ?></td>';
    h += '<td style="text-align:center"><?php echo cfi_format_receipt_value($item['quantity']); ?></td>';
    h += '<td><strong>₦<?php echo cfi_format_receipt_value($item['total']); ?></strong></td>';
    h += '</tr>';
    h += '<tr class="discount-row"><td class="discount-label" colspan="3">Discount</td><td class="discount-value"><?php echo $item['discount'] > 0 ? '-₦' . cfi_format_receipt_value($item['discount']) : '-'; ?></td></tr>';
    <?php endforeach; endif; ?>
    h += '</table>';
    
    h += '<div class="totals">';
    h += '<div class="total-row"><span class="label">Subtotal:</span><span class="value">₦<?php echo isset($receipt_data['subtotal']) ? cfi_format_receipt_value($receipt_data['subtotal']) : '0'; ?></span></div>';
    h += '<div class="total-row"><span class="label">Total Discount:</span><span class="value" style="color:#c00">-₦<?php echo isset($receipt_data['discount']) ? cfi_format_receipt_value($receipt_data['discount']) : '0'; ?></span></div>';
    h += '</div>';
    
    h += '<div class="grand-total"><span>GRAND TOTAL:</span><span>₦<?php echo isset($receipt_data['grand_total']) ? cfi_format_receipt_value($receipt_data['grand_total']) : '0'; ?></span></div>';
    
    h += '<div class="payment-info">';
    h += '<div class="payment-row"><span class="label">Payment Method:</span><span class="value"><?php echo isset($receipt_data['payment_method']) ? ucfirst(esc_js($receipt_data['payment_method'])) : ''; ?></span></div>';
    <?php if (isset($receipt_data['transfer_amount']) && $receipt_data['transfer_amount'] > 0) : ?>
    h += '<div class="payment-row"><span class="label">Transfer/Card:</span><span class="value">₦<?php echo cfi_format_receipt_value($receipt_data['transfer_amount']); ?></span></div>';
    <?php endif; ?>
    <?php if (isset($receipt_data['cash_amount']) && $receipt_data['cash_amount'] > 0) : ?>
    h += '<div class="payment-row"><span class="label">Cash:</span><span class="value">₦<?php echo cfi_format_receipt_value($receipt_data['cash_amount']); ?></span></div>';
    <?php endif; ?>
    <?php if (isset($receipt_data['bank_name']) && !empty($receipt_data['bank_name'])) : ?>
    h += '<div class="payment-row"><span class="label">Bank:</span><span class="value"><?php echo esc_js($receipt_data['bank_name']); ?></span></div>';
    <?php endif; ?>
    h += '</div>';
    
    h += '<div class="footer"><p class="thanks">Thank you for your patronage!</p><p>We appreciate your business</p><p style="margin-top:5px;font-size:9px">Powered by BendlessTech</p></div>';
    h += '</div>';
    
    printWindow.document.write(h);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    // Auto-print after content loads
    printWindow.onload = function() {
        setTimeout(function() { printWindow.print(); }, 300);
    };
}

function generateTextReceipt() {
    // Build text-format receipt for 80mm (72mm printable) thermal printer
    // 32 characters per line is standard for 80mm
    var lineWidth = 32;
    var lines = [];
    
    // Helper functions
    function centerText(text) {
        var padding = Math.floor((lineWidth - text.length) / 2);
        return ' '.repeat(Math.max(0, padding)) + text;
    }
    
    function leftRight(left, right) {
        var space = lineWidth - left.length - right.length;
        return left + ' '.repeat(Math.max(1, space)) + right;
    }
    
    function separator(char) {
        return char.repeat(lineWidth);
    }
    
    // Company Header
    lines.push(centerText('CHINEMEREM FOODS'));
    lines.push(centerText('Inventory Management'));
    lines.push(separator('='));
    
    // Order Info
    <?php if ($receipt_data) : ?>
    lines.push(leftRight('Order #:', '<?php echo esc_js($receipt_data['order_number']); ?>'));
    lines.push(leftRight('Date:', '<?php echo esc_js($receipt_data['date']); ?>'));
    lines.push(leftRight('Time:', '<?php echo esc_js($receipt_data['time']); ?>'));
    <?php if (!empty($receipt_data['customer_name'])) : ?>
    lines.push(leftRight('Customer:', '<?php echo esc_js($receipt_data['customer_name']); ?>'));
    <?php endif; ?>
    lines.push(leftRight('Staff:', '<?php echo esc_js($receipt_data['staff']); ?>'));
    lines.push(separator('-'));
    
    // Items Header
    lines.push('ITEM'.padEnd(10) + '|' + 'PRICE'.padEnd(7) + '|' + 'QTY'.padEnd(4) + '|' + 'TOTAL'.padEnd(8));
    lines.push(separator('-'));
    
    // Items
    <?php foreach ($receipt_data['items'] as $item) : ?>
    var itemName = '<?php echo esc_js(substr($item['product_name'], 0, 10)); ?>';
    var price = '<?php echo esc_js($item['price']); ?>';
    var qty = '<?php echo esc_js($item['quantity']); ?>';
    var discount = '<?php echo esc_js($item['discount']); ?>';
    var amount = '<?php echo esc_js($item['total']); ?>';
    var discountDisplay = Number(discount) > 0 ? formatReceiptNumber(discount) : '-';
    lines.push(
        itemName.padEnd(10) + '|' +
        formatReceiptNumber(price).padStart(7) + '|' +
        formatReceiptNumber(qty).padStart(4) + '|' +
        formatReceiptNumber(amount).padStart(8)
    );
    lines.push(
        '  Discount:' +
        String(Number(discount) > 0 ? '-N' + formatReceiptNumber(discount) : '-')
            .padStart(lineWidth - 11)
    );
    lines.push('');
    <?php endforeach; ?>
    
    lines.push(separator('-'));
    
    // Totals
    lines.push(leftRight('Subtotal:', 'N<?php echo cfi_format_receipt_value($receipt_data['subtotal']); ?>'));
    lines.push(leftRight('Total Discount:', '-N<?php echo cfi_format_receipt_value($receipt_data['discount']); ?>'));
    lines.push(separator('='));
    lines.push(leftRight('GRAND TOTAL:', 'N<?php echo cfi_format_receipt_value($receipt_data['grand_total']); ?>'));
    lines.push(separator('='));
    
    // Payment Info
    lines.push(leftRight('Payment:', '<?php echo ucfirst(esc_js($receipt_data['payment_method'])); ?>'));
    <?php if ($receipt_data['transfer_amount'] > 0) : ?>
    lines.push(leftRight('Transfer:', 'N<?php echo cfi_format_receipt_value($receipt_data['transfer_amount']); ?>'));
    <?php endif; ?>
    <?php if ($receipt_data['cash_amount'] > 0) : ?>
    lines.push(leftRight('Cash:', 'N<?php echo cfi_format_receipt_value($receipt_data['cash_amount']); ?>'));
    <?php endif; ?>
    <?php endif; ?>
    
    lines.push('');
    lines.push(separator('-'));
    lines.push(centerText('Thank you for'));
    lines.push(centerText('your patronage!'));
    lines.push(separator('-'));
    lines.push(centerText('Powered by'));
    lines.push(centerText('BendlessTech'));
    lines.push('');
    
    return lines.join('\n');
}

function closeReceipt() {
    var modal = document.getElementById('receipt-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
    // Redirect to fresh page
    window.location.href = window.location.pathname;
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    var confirmModal = document.getElementById('confirm-modal');
    if (e.target === confirmModal) {
        hideConfirmation();
    }
});

// Close receipt modal when clicking outside or pressing Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideConfirmation();
        closeReceipt();
    }
});
</script>
</body>
</html>
