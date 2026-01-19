<?php
/**
 * Order History Page Template - WITH SUPER ADMIN EDIT/DELETE AND REPRINT RECEIPT
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure database tables exist
CFI_Database::create_tables();

$is_super_admin = CFI_Auth::is_super_admin();
$message = '';
$message_type = '';

// Handle Delete Action
if (isset($_POST['cfi_delete_order']) && $is_super_admin && wp_verify_nonce($_POST['cfi_delete_nonce'], 'cfi_delete_order')) {
    global $wpdb;
    $order_id = intval($_POST['order_id']);
    $orders_table = $wpdb->prefix . 'cfi_orders';
    $result = $wpdb->delete($orders_table, array('id' => $order_id), array('%d'));
    if ($result) {
        $message = 'Order deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete order';
        $message_type = 'error';
    }
}

$today = current_time('Y-m-d');
$start_date = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : $today;
$end_date = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : $today;

// Get orders with items
global $wpdb;
$orders_table = $wpdb->prefix . 'cfi_orders';
$items_table = $wpdb->prefix . 'cfi_order_items';
$products_table = $wpdb->prefix . 'cfi_products';
$users_table = $wpdb->users;

$orders = $wpdb->get_results($wpdb->prepare(
    "SELECT SQL_NO_CACHE o.*, u.display_name as staff_name 
     FROM $orders_table o 
     LEFT JOIN $users_table u ON o.staff_id = u.ID 
     WHERE o.order_date BETWEEN %s AND %s 
     ORDER BY o.order_date DESC, o.order_time DESC",
    $start_date, $end_date
));

// Get order items for receipt reprinting
$order_items = array();
foreach ($orders as $order) {
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.*, p.name as product_name 
         FROM $items_table oi 
         LEFT JOIN $products_table p ON oi.product_id = p.id 
         WHERE oi.order_id = %d",
        $order->id
    ));
    $order_items[$order->id] = $items;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f8fafc; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
        .page-header { background: linear-gradient(135deg, #001943, #002960); color: white !important; padding: 0.75rem 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
        .page-header h1 { margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 0.4rem; color: #ffffff !important; font-weight: 600; }
        .page-header h1 i { color: #ffffff !important; font-size: 0.75rem; }
        .page-header a, .page-header span { color: #ffffff !important; }
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.75rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.65rem; transition: all 0.3s; }
        .btn-primary { background: #001943; color: white; }
        .btn-outline { background: white; border: 2px solid #001943; color: #001943; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .glass { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,25,67,0.1); border: 2px solid rgba(0,25,67,0.1); margin-bottom: 1.5rem; }
        .filters { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1.5rem; }
        .filter-group label { display: block; font-weight: 600; color: #001943; font-size: 0.8rem; margin-bottom: 0.25rem; }
        .filter-input { padding: 0.5rem; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; min-width: 800px; }
        th { background: #001943; color: white; padding: 0.6rem 0.4rem; text-align: left; white-space: nowrap; font-size: 0.75rem; }
        td { padding: 0.5rem 0.4rem; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        .order-num { font-weight: 600; color: #001943; }
        .amount { font-weight: 600; color: #16a34a; }
        .type-cash { color: #16a34a; font-weight: 600; }
        .type-credit { color: #dc2626; font-weight: 600; }
        .btn-print { background: #7c3aed; color: white; }
        .btn-print:hover { background: #6d28d9; }
        .action-btn { padding: 0.25rem 0.4rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.7rem; margin: 0.1rem; }
        .btn-delete { background: #dc2626; color: white; }
        .btn-delete:hover { background: #b91c1c; }
        .alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .empty { text-align: center; padding: 2rem; color: #64748b; }
        
        /* Receipt Modal */
        .receipt-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .receipt-modal.active { display: flex; }
        .receipt-content { background: white; max-width: 80mm; width: 100%; max-height: 90vh; overflow-y: auto; border-radius: 12px; }
        .receipt-header { background: #001943; color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0; }
        .receipt-header h3 { margin: 0; font-size: 1rem; }
        .receipt-body { padding: 1rem; font-family: 'Courier New', monospace; font-size: 0.8rem; width: 100%; box-sizing: border-box; }
        .receipt-company { text-align: center; margin-bottom: 0.5rem; }
        .receipt-company h2 { margin: 0; font-size: 1rem; font-weight: 800; letter-spacing: 0.5px; }
        .receipt-company p { margin: 0; font-size: 0.75rem; color: #666; }
        .receipt-divider { border-top: 1px dashed #333; margin: 0.5rem 0; }
        .receipt-info p { margin: 0.2rem 0; display: flex; justify-content: space-between; }
        .receipt-items { margin: 0.5rem 0; }
        .receipt-row { display: grid; grid-template-columns: 1.6fr 0.8fr 0.5fr 0.9fr; gap: 6px; align-items: baseline; }
        .receipt-row .item-price, .receipt-row .item-qty, .receipt-row .item-total { text-align: right; }
        .receipt-item-header { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #0f172a; }
        .receipt-item { padding: 0.35rem 0; border-bottom: 1px dashed #e2e8f0; }
        .receipt-item:last-child { border-bottom: none; }
        .receipt-item-discount { display: flex; justify-content: space-between; font-size: 0.7rem; margin-top: 0.2rem; }
        .receipt-item-discount .receipt-amount { color: #dc2626; }
        .receipt-amount { font-weight: 700; }
        .receipt-totals p { margin: 0.2rem 0; display: flex; justify-content: space-between; }
        .receipt-totals .grand { font-weight: 700; font-size: 1rem; }
        .receipt-footer { text-align: center; margin-top: 0.5rem; font-size: 0.75rem; color: #666; }
        .receipt-actions { padding: 1rem; display: flex; gap: 0.5rem; justify-content: center; border-top: 1px solid #e2e8f0; }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            .filters { flex-direction: column; }
            table { font-size: 0.7rem; }
        }
        
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; left: 0; top: 0; width: 80mm; margin: 0; padding: 2mm; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Order History</h1>
        <a href="/take-order/" class="btn btn-outline" style="background: white;">
            <i class="fas fa-cart-plus"></i> Take Order
        </a>
    </div>
    
    <div class="glass">
        <form method="GET" class="filters">
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="start" class="filter-input" value="<?php echo esc_attr($start_date); ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="end" class="filter-input" value="<?php echo esc_attr($end_date); ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>
    
    <?php if ($message) : ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <?php echo esc_html($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="glass">
        <h3 style="color: #001943; margin: 0 0 1rem 0;"><i class="fas fa-shopping-cart"></i> Orders</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total (₦)</th>
                        <th>Payment</th>
                        <th>Staff</th>
                        <th>Receipt</th>
                        <?php if ($is_super_admin) : ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)) : ?>
                    <tr><td colspan="<?php echo $is_super_admin ? '11' : '10'; ?>" class="empty">No orders found for this period</td></tr>
                    <?php else : ?>
                    <?php foreach ($orders as $order) : ?>
                    <tr>
                        <td class="order-num"><?php echo esc_html($order->order_number); ?></td>
                        <td><?php echo esc_html($order->order_date); ?></td>
                        <td><?php echo esc_html(cfi_format_receipt_time($order->order_date, $order->order_time)); ?></td>
                        <td class="type-<?php echo esc_attr($order->order_type); ?>"><?php echo ucfirst(esc_html($order->order_type)); ?></td>
                        <td><?php echo esc_html($order->customer_name ?: '-'); ?></td>
                        <td><?php echo esc_html($order->total_quantity ?: '-'); ?></td>
                        <td class="amount">₦<?php echo number_format($order->grand_total, 0); ?></td>
                        <td><?php echo ucfirst(esc_html($order->payment_method)); ?></td>
                        <td><?php echo esc_html($order->staff_name ?: 'Unknown'); ?></td>
                        <td>
                            <button type="button" class="action-btn btn-print" onclick="showReceipt(<?php echo esc_attr($order->id); ?>)">
                                <i class="fas fa-print"></i>
                            </button>
                        </td>
                        <?php if ($is_super_admin) : ?>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this order? This cannot be undone.');">
                                <?php wp_nonce_field('cfi_delete_order', 'cfi_delete_nonce'); ?>
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>">
                                <button type="submit" name="cfi_delete_order" class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="receipt-modal" id="receipt-modal">
    <div class="receipt-content">
        <div class="receipt-header">
            <h3><i class="fas fa-receipt"></i> Receipt</h3>
            <button onclick="closeReceipt()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div class="receipt-body" id="receipt-body">
            <!-- Will be filled by JavaScript -->
        </div>
        <div class="receipt-actions">
            <button onclick="printReceipt()" class="btn btn-print">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="closeReceipt()" class="btn btn-primary">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Hidden print area -->
<div id="print-area" style="display: none;"></div>

<!-- Store order data for JavaScript -->
<script>
var orderData = <?php echo json_encode(array_map(function($order) use ($order_items) {
    $items = isset($order_items[$order->id]) ? $order_items[$order->id] : array();
    return array(
        'id' => $order->id,
        'order_number' => $order->order_number,
        'order_date' => $order->order_date,
        'order_time' => cfi_format_receipt_time($order->order_date, $order->order_time),
        'customer_name' => $order->customer_name,
        'total_quantity' => $order->total_quantity,
        'total_amount' => $order->total_amount,
        'discount_amount' => $order->discount_amount,
        'grand_total' => $order->grand_total,
        'payment_method' => $order->payment_method,
        'transfer_amount' => $order->transfer_amount,
        'cash_amount' => $order->cash_amount,
        'staff_name' => $order->staff_name,
        'items' => array_map(function($item) {
            return array(
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'discount' => $item->discount,
                'total' => $item->total
            );
        }, $items)
    );
}, $orders)); ?>;

function formatReceiptNumber(value) {
    var num = Number(value);
    if (Number.isNaN(num)) {
        return '0';
    }
    return num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function showReceipt(orderId) {
    var order = orderData.find(function(o) { return o.id == orderId; });
    if (!order) {
        alert('Order not found');
        return;
    }
    
    // Store order for Bluetooth printing
    currentPrintOrder = order;
    
    var html = '<div id="print-content">';
    html += '<div class="receipt-company"><h2>CHINEMEREM FOODS</h2><p>Inventory Management System</p></div>';
    html += '<div class="receipt-divider"></div>';
    html += '<div class="receipt-info">';
    html += '<p><span>Order #:</span><strong>' + order.order_number + '</strong></p>';
    html += '<p><span>Date:</span><span>' + order.order_date + '</span></p>';
    html += '<p><span>Time:</span><span>' + order.order_time + '</span></p>';
    if (order.customer_name) {
        html += '<p><span>Customer:</span><span>' + order.customer_name + '</span></p>';
    }
    html += '<p><span>Staff:</span><span>' + (order.staff_name || 'Unknown') + '</span></p>';
    html += '</div>';
    html += '<div class="receipt-divider"></div>';
    
    html += '<div class="receipt-items">';
    html += '<div class="receipt-row receipt-item-header"><span>Item</span><span class="item-price">Price</span><span class="item-qty">Qty</span><span class="item-total">Total</span></div>';
    if (order.items && order.items.length > 0) {
        order.items.forEach(function(item) {
            var discountDisplay = Number(item.discount) > 0 ? '-₦' + formatReceiptNumber(item.discount) : '-';
            html += '<div class="receipt-item">';
            html += '<div class="receipt-row receipt-item-row">';
            html += '<span>' + item.product_name + '</span>';
            html += '<span class="item-price receipt-amount">₦' + formatReceiptNumber(item.price) + '</span>';
            html += '<span class="item-qty receipt-amount">' + formatReceiptNumber(item.quantity) + '</span>';
            html += '<span class="item-total receipt-amount">₦' + formatReceiptNumber(item.total) + '</span>';
            html += '</div>';
            html += '<div class="receipt-item-discount"><span>Discount:</span><span class="receipt-amount">' + discountDisplay + '</span></div>';
            html += '</div>';
        });
    }
    html += '</div>';
    html += '<div class="receipt-divider"></div>';
    
    html += '<div class="receipt-totals">';
    html += '<p><span>Subtotal:</span><span class="receipt-amount">₦' + formatReceiptNumber(order.total_amount) + '</span></p>';
    html += '<p><span>Total Discount:</span><span class="receipt-amount">-₦' + formatReceiptNumber(order.discount_amount || 0) + '</span></p>';
    html += '<p class="grand"><span>GRAND TOTAL:</span><span class="receipt-amount">₦' + formatReceiptNumber(order.grand_total) + '</span></p>';
    html += '<p><span>Payment:</span><span>' + (order.payment_method || 'Cash') + '</span></p>';
    if (order.transfer_amount > 0) {
        html += '<p><span>Transfer:</span><span class="receipt-amount">₦' + formatReceiptNumber(order.transfer_amount) + '</span></p>';
    }
    if (order.cash_amount > 0) {
        html += '<p><span>Cash:</span><span class="receipt-amount">₦' + formatReceiptNumber(order.cash_amount) + '</span></p>';
    }
    html += '</div>';
    html += '<div class="receipt-divider"></div>';
    
    html += '<div class="receipt-footer">';
    html += '<p>Thank you for your patronage!</p>';
    html += '<p>Powered by BendlessTech</p>';
    html += '</div>';
    html += '</div>';
    
    document.getElementById('receipt-body').innerHTML = html;
    document.getElementById('receipt-modal').classList.add('active');
}

function closeReceipt() {
    currentPrintOrder = null;
    document.getElementById('receipt-modal').classList.remove('active');
}

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
        const ESC = 0x1B;
        const GS = 0x1D;
        let commands = new Uint8Array([ESC, 0x40]);
        await printerCharacteristic.writeValue(commands);
        const textData = encoder.encode(text);
        const chunkSize = 100;
        for (let i = 0; i < textData.length; i += chunkSize) {
            const chunk = textData.slice(i, i + chunkSize);
            await printerCharacteristic.writeValue(chunk);
            await new Promise(r => setTimeout(r, 50));
        }
        commands = new Uint8Array([0x0A, 0x0A, 0x0A, GS, 0x56, 0x00]);
        await printerCharacteristic.writeValue(commands);
        return true;
    } catch (error) {
        console.error('Print failed:', error);
        return false;
    }
}

function generateESCPOSReceiptFromOrder(order) {
    var text = '';
    var ESC = '\x1b';
    var GS = '\x1d';
    var boldOn = ESC + 'E' + '\x01';
    var boldOff = ESC + 'E' + '\x00';
    var doubleOn = GS + '!' + '\x11';
    var doubleOff = GS + '!' + '\x00';
    var lineWidth = 48;
    var itemWidth = 20;
    var priceWidth = 8;
    var qtyWidth = 5;
    var totalWidth = 12;
    var line = '-'.repeat(lineWidth);

    function centerText(text, width) {
        var useWidth = width || lineWidth;
        var padding = Math.floor((useWidth - text.length) / 2);
        return ' '.repeat(Math.max(0, padding)) + text;
    }

    function leftRight(left, right) {
        var space = lineWidth - left.length - right.length;
        return left + ' '.repeat(Math.max(1, space)) + right;
    }

    function leftRightBold(left, right) {
        var space = lineWidth - left.length - right.length;
        return left + ' '.repeat(Math.max(1, space)) + boldOn + right + boldOff;
    }

    text += doubleOn + boldOn + centerText('CHINEMEREM FOODS', Math.floor(lineWidth / 2)) + boldOff + doubleOff + '\n';
    text += centerText('Sales Receipt') + '\n';
    text += line + '\n';
    text += 'Order: ' + order.order_number + '\n';
    text += 'Date: ' + order.order_date + '\n';
    text += 'Time: ' + order.order_time + '\n';
    if (order.customer_name) text += 'Customer: ' + order.customer_name + '\n';
    text += 'Staff: ' + (order.staff_name || 'Unknown') + '\n';
    text += line + '\n';
    text += 'ITEM'.padEnd(itemWidth) + ' ' + 'PRICE'.padStart(priceWidth) + ' ' + 'QTY'.padStart(qtyWidth) + ' ' + 'TOTAL'.padStart(totalWidth) + '\n';
    text += line + '\n';
    if (order.items && order.items.length > 0) {
        order.items.forEach(function(item) {
            var name = (item.product_name || '').substring(0, itemWidth).padEnd(itemWidth);
            var price = String(formatReceiptNumber(item.price)).padStart(priceWidth);
            var qty = String(formatReceiptNumber(item.quantity)).padStart(qtyWidth);
            var total = String(formatReceiptNumber(item.total)).padStart(totalWidth);
            var discount = Number(item.discount) > 0 ? '-N' + formatReceiptNumber(item.discount) : '-';
            text += name + ' ' + boldOn + price + boldOff + ' ' + boldOn + qty + boldOff + ' ' + boldOn + total + boldOff + '\n';
            text += leftRightBold('Discount:', discount) + '\n\n';
        });
    }
    text += line + '\n';
    text += leftRightBold('Subtotal:', 'N' + formatReceiptNumber(order.total_amount)) + '\n';
    text += leftRightBold('Total Discount:', '-N' + formatReceiptNumber(order.discount_amount || 0)) + '\n';
    text += line + '\n';
    text += leftRightBold('GRAND TOTAL:', 'N' + formatReceiptNumber(order.grand_total)) + '\n';
    text += leftRight('Payment:', (order.payment_method || 'Cash')) + '\n';
    if (order.transfer_amount > 0) text += leftRightBold('Transfer:', 'N' + formatReceiptNumber(order.transfer_amount)) + '\n';
    if (order.cash_amount > 0) text += leftRightBold('Cash:', 'N' + formatReceiptNumber(order.cash_amount)) + '\n';
    text += line + '\n';
    text += centerText('Thank you for your patronage!') + '\n';
    text += centerText('Powered by BendlessTech') + '\n';
    text += '\n\n\n';
    return text;
}

var currentPrintOrder = null;

async function printReceipt() {
    var printContent = document.getElementById('print-content');
    if (!printContent) {
        alert('No receipt content found');
        return;
    }
    
    // Try Bluetooth printing first
    if ('bluetooth' in navigator && currentPrintOrder) {
        var receiptText = generateESCPOSReceiptFromOrder(currentPrintOrder);
        var printed = await printToBluetoothPrinter(receiptText);
        if (printed) {
            alert('Receipt printed successfully!');
            return;
        }
    }
    
    // Fallback to browser print dialog
    var printWindow = window.open('', '_blank', 'width=350,height=700');
    
    printWindow.document.write('<!DOCTYPE html><html><head><title>Print Receipt</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('@page{size:80mm auto;margin:0}');
    printWindow.document.write('*{margin:0;padding:0;box-sizing:border-box}');
    printWindow.document.write('body{font-family:"Courier New",Courier,monospace;font-size:12px;width:80mm;max-width:80mm;margin:0 auto;padding:3mm;line-height:1.4;color:#000}');
    printWindow.document.write('.receipt-company{text-align:center;margin-bottom:6px}');
    printWindow.document.write('.receipt-company h2{font-size:16px;font-weight:800;margin:0 0 4px;letter-spacing:0.5px}');
    printWindow.document.write('.receipt-company p{font-size:11px;margin:0}');
    printWindow.document.write('.receipt-divider{border-top:1px dashed #000;margin:6px 0}');
    printWindow.document.write('.receipt-info p{display:flex;justify-content:space-between;margin:4px 0;font-size:12px}');
    printWindow.document.write('.receipt-row{display:grid;grid-template-columns:1.6fr 0.8fr 0.5fr 0.9fr;gap:6px;align-items:baseline;font-size:11px}');
    printWindow.document.write('.receipt-row .item-price,.receipt-row .item-qty,.receipt-row .item-total{text-align:right}');
    printWindow.document.write('.receipt-item-header{font-size:10px;font-weight:700;text-transform:uppercase}');
    printWindow.document.write('.receipt-item{padding:4px 0;border-bottom:1px dashed #999}');
    printWindow.document.write('.receipt-item:last-child{border-bottom:none}');
    printWindow.document.write('.receipt-item-discount{display:flex;justify-content:space-between;font-size:10px;margin-top:2px}');
    printWindow.document.write('.receipt-amount{font-weight:700}');
    printWindow.document.write('.receipt-totals p{display:flex;justify-content:space-between;margin:4px 0;font-size:12px}');
    printWindow.document.write('.receipt-totals .grand{font-size:13px;font-weight:700}');
    printWindow.document.write('.receipt-footer{text-align:center;margin-top:6px;font-size:10px}');
    printWindow.document.write('@media print{body{padding:0}}');
    printWindow.document.write('</style></head><body>');
    printWindow.document.write(printContent.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    printWindow.onload = function() {
        setTimeout(function() { printWindow.print(); }, 300);
    };
}

// Close modal when clicking outside
document.getElementById('receipt-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReceipt();
    }
});
</script>
</body>
</html>
