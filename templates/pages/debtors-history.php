<?php
/**
 * Debtors History Page Template - COMPLETE REBUILD v3
 * Zero caching, direct database queries
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

// Clear any WordPress object cache
wp_cache_flush();

global $wpdb;
$wpdb->flush();  // Clear wpdb query cache

$is_super_admin = CFI_Auth::is_super_admin();
$message = '';
$message_type = '';

// Table names
$trans_table = $wpdb->prefix . 'cfi_debtor_transactions';
$debtors_table = $wpdb->prefix . 'cfi_debtors';
$orders_table = $wpdb->prefix . 'cfi_orders';
$order_items_table = $wpdb->prefix . 'cfi_order_items';

// Handle Delete Action
if (isset($_POST['cfi_delete_trans']) && $is_super_admin && wp_verify_nonce($_POST['cfi_del_nonce'], 'cfi_del_trans')) {
    $trans_id = intval($_POST['trans_id']);
    $result = $wpdb->delete($trans_table, array('id' => $trans_id), array('%d'));
    if ($result) {
        $message = 'Transaction deleted';
        $message_type = 'success';
    } else {
        $message = 'Delete failed';
        $message_type = 'error';
    }
}

// Get selected debtor filter
$selected_debtor = isset($_GET['debtor']) ? intval($_GET['debtor']) : 0;

// Build query with SQL_NO_CACHE for fresh data
$where = '1=1';
$params = array();
if ($selected_debtor) {
    $where .= ' AND dt.debtor_id = %d';
    $params[] = $selected_debtor;
}

$query = "SELECT SQL_NO_CACHE dt.*, d.name as debtor_name, u.display_name as staff_name 
          FROM `{$trans_table}` dt 
          LEFT JOIN `{$debtors_table}` d ON dt.debtor_id = d.id 
          LEFT JOIN `{$wpdb->users}` u ON dt.staff_id = u.ID 
          WHERE {$where} 
          ORDER BY dt.id DESC 
          LIMIT 100";

$history = $params ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

// Get debtors for filter
$debtors = $wpdb->get_results("SELECT * FROM {$debtors_table} WHERE status = 'active' ORDER BY name ASC");

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
<title>Debtors History</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;padding:0;background:#f8fafc}
.container{max-width:1200px;margin:0 auto;padding:1rem}
.header{background:linear-gradient(135deg,#001943,#003366);color:#fff;padding:0.75rem 1rem;border-radius:12px;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem}
.header h1{margin:0;font-size:0.85rem;display:flex;align-items:center;gap:0.5rem}
.btn{display:inline-flex;align-items:center;gap:0.4rem;padding:0.5rem 0.75rem;border:none;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;font-size:0.7rem;transition:all 0.2s}
.btn-primary{background:#001943;color:#fff}
.btn-white{background:#fff;color:#001943}
.glass{background:rgba(255,255,255,0.95);border-radius:12px;padding:1.5rem;box-shadow:0 4px 20px rgba(0,25,67,0.1);border:2px solid rgba(0,25,67,0.1);margin-bottom:1.5rem}
.filters{display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;margin-bottom:1.5rem}
.filter-group{flex:1;min-width:150px}
.filter-group label{display:block;margin-bottom:0.5rem;font-weight:600;color:#001943;font-size:0.85rem}
.select{width:100%;padding:0.6rem;border:2px solid #e2e8f0;border-radius:8px;font-size:0.75rem}
table{width:100%;border-collapse:collapse;font-size:0.8rem}
th{background:#001943;color:#fff;padding:0.6rem 0.4rem;text-align:left;font-size:0.75rem}
td{padding:0.5rem 0.4rem;border-bottom:1px solid #e2e8f0}
tr:hover{background:rgba(0,25,67,0.02)}
.badge{display:inline-flex;align-items:center;gap:0.25rem;padding:0.2rem 0.5rem;border-radius:20px;font-size:0.65rem;font-weight:600}
.badge-order{background:#fee2e2;color:#991b1b}
.badge-payment{background:#dcfce7;color:#166534}
.badge-initial{background:#dbeafe;color:#1e40af}
.badge-adjustment{background:#fef3c7;color:#92400e}
.empty{text-align:center;padding:3rem;color:#64748b}
.empty i{font-size:3rem;margin-bottom:1rem;display:block}
.action-btn{padding:0.25rem 0.4rem;border:none;border-radius:4px;cursor:pointer;font-size:0.65rem}
.btn-del{background:#dc2626;color:#fff}
.btn-view{background:#001943;color:#fff;margin-right:0.25rem}
.btn-print{background:#7c3aed;color:#fff}
.alert{padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem}
.alert-success{background:#dcfce7;color:#166534}
.alert-error{background:#fee2e2;color:#991b1b}
.modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:1rem}
.modal.active{display:flex}
.modal-content{background:#fff;max-width:500px;width:100%;max-height:90vh;overflow-y:auto;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,0.3)}
.modal-header{background:#001943;color:#fff;padding:1rem;display:flex;justify-content:space-between;align-items:center}
.modal-header h3{margin:0;font-size:1rem}
.modal-close{background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer}
.modal-body{padding:1.5rem}
.order-item{display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #e2e8f0;font-size:0.85rem}
.order-item:last-child{border-bottom:none}
.order-total{font-weight:700;font-size:1.1rem;color:#001943;padding-top:0.5rem;margin-top:0.5rem;border-top:2px solid #001943;display:flex;justify-content:space-between}
@media(max-width:768px){
table,table thead,table tbody,table th,table td,table tr{display:block}
table thead{display:none}
table tr{margin-bottom:1rem;border:1px solid #e2e8f0;border-radius:8px;padding:0.5rem}
table td{display:flex;justify-content:space-between;padding:0.5rem;border:none}
table td:before{content:attr(data-label);font-weight:600;color:#001943}
}
</style>
</head>
<body>
<main class="container">
<div class="header">
<h1><i class="fas fa-history"></i> Debtors History<?php if ($selected_debtor) : $dinfo = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$debtors_table} WHERE id = %d", $selected_debtor)); if ($dinfo) : ?> - <?php echo esc_html($dinfo->name); ?><?php endif; endif; ?></h1>
<a href="<?php echo home_url('/debtors-record/'); ?>" class="btn btn-white"><i class="fas fa-user-clock"></i> Current Debtors</a>
</div>

<form method="GET" class="glass filters">
<div class="filter-group">
<label for="debtor">Filter by Debtor:</label>
<select name="debtor" id="debtor" class="select">
<option value="">All Debtors</option>
<?php foreach ($debtors as $d) : ?>
<option value="<?php echo esc_attr($d->id); ?>" <?php selected($selected_debtor, $d->id); ?>><?php echo esc_html($d->name); ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
</form>

<?php if ($message) : ?>
<div class="alert alert-<?php echo $message_type; ?>"><?php echo esc_html($message); ?></div>
<?php endif; ?>

<div class="glass">
<?php if (empty($history)) : ?>
<div class="empty"><i class="fas fa-inbox"></i><h3>No Transaction History</h3><p>Debtor transactions will appear here.</p></div>
<?php else : ?>
<div style="overflow-x:auto">
<table>
<thead><tr><th>Date</th><th>Time</th><th>Debtor</th><th>Type</th><th>Amount</th><th>Before</th><th>After</th><th>Details</th><th>Staff</th><?php if ($is_super_admin) : ?><th>Action</th><?php endif; ?></tr></thead>
<tbody>
<?php foreach ($history as $rec) : 
$type = $rec->transaction_type;
$badge = 'badge-' . $type;
$icon = $type === 'order' ? 'cart-plus' : ($type === 'payment' ? 'money-check' : ($type === 'initial' ? 'plus-circle' : 'edit'));
?>
<tr>
<td data-label="Date"><?php echo esc_html($rec->transaction_date); ?></td>
<td data-label="Time"><?php echo esc_html(substr($rec->transaction_time, 0, 5)); ?></td>
<td data-label="Debtor"><?php echo esc_html($rec->debtor_name ?: 'Unknown'); ?></td>
<td data-label="Type"><span class="badge <?php echo esc_attr($badge); ?>"><i class="fas fa-<?php echo esc_attr($icon); ?>"></i> <?php echo esc_html(ucfirst($type)); ?></span></td>
<td data-label="Amount" style="font-weight:600;color:<?php echo $type === 'order' ? '#dc2626' : '#16a34a'; ?>"><?php echo $type === 'order' ? '+' : '-'; ?>₦<?php echo number_format((float)$rec->amount, 2); ?></td>
<td data-label="Before">₦<?php echo number_format((float)$rec->balance_before, 2); ?></td>
<td data-label="After" style="font-weight:600">₦<?php echo number_format((float)$rec->balance_after, 2); ?></td>
<td data-label="Details">
<?php if ($type === 'order' && $rec->order_id) : ?>
<button type="button" class="action-btn btn-view" onclick="viewOrder(<?php echo esc_attr($rec->order_id); ?>)"><i class="fas fa-eye"></i></button>
<button type="button" class="action-btn btn-print" onclick="reprintOrder(<?php echo esc_attr($rec->order_id); ?>)"><i class="fas fa-print"></i></button>
<?php elseif ($type === 'payment') : ?>
<button type="button" class="action-btn btn-print" onclick="reprintPay(<?php echo esc_attr($rec->id); ?>)"><i class="fas fa-print"></i></button>
<?php else : ?>-<?php endif; ?>
</td>
<td data-label="Staff"><?php echo esc_html($rec->staff_name ?: '-'); ?></td>
<?php if ($is_super_admin) : ?>
<td><form method="POST" style="display:inline" onsubmit="return confirm('Delete this?')"><input type="hidden" name="trans_id" value="<?php echo esc_attr($rec->id); ?>"><?php wp_nonce_field('cfi_del_trans', 'cfi_del_nonce'); ?><button type="submit" name="cfi_delete_trans" class="action-btn btn-del"><i class="fas fa-trash"></i></button></form></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</main>

<div class="modal" id="order-modal">
<div class="modal-content">
<div class="modal-header"><h3><i class="fas fa-receipt"></i> Order Details</h3><button type="button" class="modal-close" onclick="closeModal()">&times;</button></div>
<div class="modal-body" id="order-body"><div style="text-align:center;padding:2rem"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#001943"></i><p>Loading...</p></div></div>
</div>
</div>

<?php
// Prepare payment data for JavaScript
$pay_data = array();
foreach ($history as $rec) {
    if ($rec->transaction_type === 'payment') {
        $pay_data[$rec->id] = array(
            'id' => $rec->id,
            'debtor_name' => $rec->debtor_name,
            'amount' => $rec->amount,
            'payment_method' => $rec->payment_method,
            'cash_amount' => $rec->cash_amount,
            'transfer_amount' => $rec->transfer_amount,
            'balance_before' => $rec->balance_before,
            'balance_after' => $rec->balance_after,
            'transaction_date' => $rec->transaction_date,
            'transaction_time' => substr($rec->transaction_time, 0, 5),
            'staff_name' => $rec->staff_name
        );
    }
}
?>
<script>
var payData=<?php echo json_encode($pay_data); ?>;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[match];
    });
}

function viewOrder(id){
var modal=document.getElementById('order-modal'),body=document.getElementById('order-body');
modal.classList.add('active');
fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=cfi_get_order_details&order_id='+id)
.then(function(r){return r.json()})
.then(function(d){
if(d.success){var o=d.data,h='<div>';
h+='<p style="margin:0 0 0.5rem"><strong>Order #:</strong> '+escapeHtml(o.order_number||'N/A')+'</p>';
h+='<p style="margin:0 0 0.5rem"><strong>Date:</strong> '+escapeHtml(o.order_date||'N/A')+'</p>';
h+='<p style="margin:0 0 1rem"><strong>Customer:</strong> '+escapeHtml(o.customer_name||'N/A')+'</p>';
if(o.items&&o.items.length>0){var totalDiscount=0;h+='<div style="margin:1rem 0"><table style="width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed;border:1px solid #001943">';
h+='<tr style="font-weight:600;background:#f1f5f9"><th style="text-align:left;width:44%;border:1px solid #001943">Item</th><th style="text-align:right;width:18%;border:1px solid #001943">Price</th><th style="width:12%;border:1px solid #001943">Qty</th><th style="text-align:right;width:26%;border:1px solid #001943">Total</th></tr>';
o.items.forEach(function(i){var discountDisplay=Number(i.discount)>0?'-₦'+formatReceiptNumber(i.discount):'-';totalDiscount+=Number(i.discount)||0;h+='<tr><td style="border:1px solid #001943">'+escapeHtml(i.product_name)+'</td><td style="text-align:right;border:1px solid #001943">₦'+formatReceiptNumber(i.price)+'</td><td style="text-align:center;border:1px solid #001943">'+formatReceiptNumber(i.quantity)+'</td><td style="text-align:right;border:1px solid #001943">₦'+formatReceiptNumber(i.total)+'</td></tr>';h+='<tr style="background:#f8fafc"><td style="border:1px solid #001943" colspan="3">Discount</td><td style="text-align:right;color:#c00;border:1px solid #001943">'+discountDisplay+'</td></tr>'});h+='</table></div>';
h+='<p style="display:flex;justify-content:space-between;margin:0.5rem 0;font-weight:600;color:#c00"><span>Total Discount:</span><span>-₦'+formatReceiptNumber(totalDiscount)+'</span></p>';}
h+='<div class="order-total" style="font-weight:700"><span>Total:</span><span>₦'+formatReceiptNumber(o.grand_total||0)+'</span></div></div>';
body.innerHTML=h}else{body.innerHTML='<div style="text-align:center;padding:2rem;color:#991b1b">Failed to load</div>'}
}).catch(function(){body.innerHTML='<div style="text-align:center;padding:2rem;color:#991b1b">Error loading</div>'});
}

function closeModal(){document.getElementById('order-modal').classList.remove('active')}

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

function formatReceiptNumber(value) {
    var num = Number(value);
    if (Number.isNaN(num)) {
        return '0';
    }
    return num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function generateOrderESCPOS(o) {
    var text = '';
    var line = '--------------------------------';
    text += '       CHINEMEREM FOODS\n';
    text += '    Credit Order (Reprint)\n';
    text += line + '\n';
    text += 'Order: ' + (o.order_number||'N/A') + '\n';
    text += 'Date: ' + (o.order_date||'N/A') + '\n';
    text += 'Time: ' + (o.order_time||'N/A') + '\n';
    text += 'Customer: ' + (o.customer_name||'N/A') + '\n';
    text += line + '\n';
    text += 'ITEM        PRICE QTY TOTAL\n';
    text += line + '\n';
    if(o.items&&o.items.length>0){o.items.forEach(function(i){
        var name = (i.product_name || '').substring(0, 10).padEnd(10);
        var price = String(formatReceiptNumber(i.price)).padStart(6);
        var qty = String(formatReceiptNumber(i.quantity)).padStart(3);
        var amt = String(formatReceiptNumber(i.total)).padStart(8);
        var discount = i.discount > 0 ? '-N' + formatReceiptNumber(i.discount) : '-';
        var discountLabel = '  Discount:';
        text += name + ' ' + price + ' ' + qty + ' ' + amt + '\n';
        text += discountLabel + String(discount).padStart(line.length - discountLabel.length) + '\n';
    })}
    text += line + '\n';
    var totalDiscount=0;
    if(o.items&&o.items.length>0){o.items.forEach(function(i){totalDiscount+=Number(i.discount)||0})}
    text += 'Total Discount:   -N' + String(formatReceiptNumber(totalDiscount)).padStart(9) + '\n';
    text += 'ORDER TOTAL:       N' + String(formatReceiptNumber(o.grand_total||0)).padStart(9) + '\n';
    text += line + '\n';
    text += '  Credit order - Payment pending\n';
    text += '      Powered by BendlessTech\n';
    text += '\n\n\n';
    return text;
}

function generatePaymentESCPOS(p) {
    var text = '';
    var line = '--------------------------------';
    text += '       CHINEMEREM FOODS\n';
    text += '   Debt Payment (Reprint)\n';
    text += line + '\n';
    text += 'Receipt: PAY-' + p.id + '\n';
    text += 'Date: ' + p.transaction_date + '\n';
    text += 'Time: ' + p.transaction_time + '\n';
    text += 'Debtor: ' + p.debtor_name + '\n';
    text += 'Staff: ' + (p.staff_name||'-') + '\n';
    text += line + '\n';
    text += 'Balance Before: N' + formatReceiptNumber(p.balance_before) + '\n';
    text += 'PAYMENT:        N' + formatReceiptNumber(p.amount) + '\n';
    if(p.transfer_amount>0) text += '  Via Transfer: N' + formatReceiptNumber(p.transfer_amount) + '\n';
    if(p.cash_amount>0) text += '  Via Cash:     N' + formatReceiptNumber(p.cash_amount) + '\n';
    text += line + '\n';
    text += 'NEW BALANCE:    N' + formatReceiptNumber(p.balance_after) + '\n';
    text += line + '\n';
    text += '   Payment received with thanks!\n';
    text += '      Powered by BendlessTech\n';
    text += '\n\n\n';
    return text;
}

function reprintOrder(id){
fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=cfi_get_order_details&order_id='+id)
.then(function(r){return r.json()})
.then(function(d){if(d.success)printOrder(d.data);else alert('Failed to load')})
.catch(function(){alert('Error loading')});
}

async function printOrder(o){
// Try Bluetooth first
if ('bluetooth' in navigator) {
    var receiptText = generateOrderESCPOS(o);
    var printed = await printToBluetoothPrinter(receiptText);
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
 h+='body{font-family:"Courier New",Courier,monospace;font-size:13px;width:80mm;max-width:80mm;margin:0 auto;padding:3mm;line-height:1.4;color:#000}';
h+='.header{text-align:center;padding:8px 0;border-bottom:2px dashed #000;margin-bottom:10px}';
h+='.header h2{font-size:16px;font-weight:bold;margin:0 0 5px}';
h+='.header p{font-size:11px;margin:0}';
h+='.info{margin:10px 0;padding:8px 0;border-bottom:1px dashed #000}';
h+='.info p{display:flex;justify-content:space-between;margin:6px 0;font-size:12px}';
 h+='.items-table{width:100%;margin:10px 0;border-collapse:collapse;font-size:11px;table-layout:fixed;border:1px solid #000}';
 h+='.items-table th{border:1px solid #000;padding:4px 2px;text-align:center;font-weight:bold;background:#f1f1f1}';
 h+='.items-table td{padding:4px 2px;text-align:center;border:1px solid #000}';
 h+='.items-table th:first-child,.items-table td:first-child{text-align:left;width:44%}';
 h+='.items-table th:nth-child(2),.items-table td:nth-child(2){text-align:right;width:18%}';
 h+='.items-table th:nth-child(3),.items-table td:nth-child(3){width:12%}';
 h+='.items-table th:nth-child(4),.items-table td:nth-child(4){text-align:right;width:26%;font-weight:bold}';
 h+='.items-table .discount-row td{font-style:italic;background:#f5f5f5}';
 h+='.items-table .discount-label{text-align:left}';
 h+='.items-table .discount-value{text-align:right;color:#c00}';
h+='.total{margin:10px 0;padding:10px 0;border-top:2px solid #000}';
h+='.total p{display:flex;justify-content:space-between;margin:6px 0;font-size:14px;font-weight:bold}';
h+='.footer{text-align:center;margin-top:15px;padding-top:10px;border-top:1px dashed #000;font-size:10px}';
h+='.no-print{margin:15px 0;text-align:center}';
h+='.print-btn{background:#7c3aed;color:#fff;border:none;padding:12px 30px;font-size:14px;border-radius:5px;cursor:pointer}';
h+='@media print{.no-print{display:none !important}}';
h+='</style></head><body>';
h+='<div class="header"><h2>CHINEMEREM FOODS</h2><p>Credit Order Receipt (Reprint)</p></div>';
h+='<div class="info">';
h+='<p><span>Order #:</span><span>'+(o.order_number||'N/A')+'</span></p>';
h+='<p><span>Date:</span><span>'+(o.order_date||'N/A')+'</span></p>';
h+='<p><span>Time:</span><span>'+(o.order_time||'N/A')+'</span></p>';
h+='<p><span>Customer:</span><span style="font-weight:bold">'+(o.customer_name||'N/A')+'</span></p>';
h+='</div>';
 h+='<table class="items-table">';
 h+='<tr><th>ITEM</th><th>PRICE</th><th>QTY</th><th>TOTAL</th></tr>';
 if(o.items&&o.items.length>0){var totalDiscount=0;o.items.forEach(function(i){
     var discountDisplay = Number(i.discount) > 0 ? '-N'+formatReceiptNumber(i.discount) : '-';
     totalDiscount+=Number(i.discount)||0;
 h+='<tr class="item-row"><td>'+i.product_name+'</td><td>N'+formatReceiptNumber(i.price)+'</td><td>'+formatReceiptNumber(i.quantity)+'</td><td>N'+formatReceiptNumber(i.total)+'</td></tr>';
 h+='<tr class="discount-row"><td class="discount-label" colspan="3">Discount</td><td class="discount-value">'+discountDisplay+'</td></tr>';
 })}
 h+='</table>';
 h+='<div class="total">';
  h+='<p><span>TOTAL DISCOUNT:</span><span>-N'+formatReceiptNumber(totalDiscount||0)+'</span></p>';
  h+='<p><span>ORDER TOTAL:</span><span>N'+formatReceiptNumber(o.grand_total||0)+'</span></p>';
h+='</div>';
h+='<div class="footer"><p>This is a credit order - Payment pending</p><p style="margin-top:5px">Powered by BendlessTech</p></div>';
h+='</body></html>';
w.document.write(h);w.document.close();
w.onload=function(){setTimeout(function(){w.print()},300)};
}

async function reprintPay(id){
var p=payData[id];if(!p){alert('Not found');return}
// Try Bluetooth first
if ('bluetooth' in navigator) {
    var receiptText = generatePaymentESCPOS(p);
    var printed = await printToBluetoothPrinter(receiptText);
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
h+='<div class="header"><h2>CHINEMEREM FOODS</h2><p>Debt Payment Receipt (Reprint)</p></div>';
h+='<div class="info">';
h+='<p><span>Receipt #:</span><span>PAY-'+p.id+'</span></p>';
h+='<p><span>Date:</span><span>'+p.transaction_date+'</span></p>';
h+='<p><span>Time:</span><span>'+p.transaction_time+'</span></p>';
h+='<p><span>Debtor:</span><span style="font-weight:bold">'+p.debtor_name+'</span></p>';
h+='<p><span>Staff:</span><span>'+(p.staff_name||'-')+'</span></p>';
h+='</div>';
h+='<div class="payment">';
 h+='<p><span>Balance Before:</span><span style="color:#cc0000">N'+formatReceiptNumber(p.balance_before)+'</span></p>';
 h+='<p class="big"><span>PAYMENT AMOUNT:</span><span>N'+formatReceiptNumber(p.amount)+'</span></p>';
 if(p.transfer_amount>0)h+='<p><span>  - Via Transfer:</span><span>N'+formatReceiptNumber(p.transfer_amount)+'</span></p>';
 if(p.cash_amount>0)h+='<p><span>  - Via Cash:</span><span>N'+formatReceiptNumber(p.cash_amount)+'</span></p>';
h+='</div>';
h+='<div class="total">';
var balColor=parseFloat(p.balance_after)>0?'#cc0000':'#008800';
 h+='<p style="color:'+balColor+'"><span>NEW BALANCE:</span><span>N'+formatReceiptNumber(p.balance_after)+'</span></p>';
h+='</div>';
h+='<div class="footer"><p>Payment received with thanks!</p><p style="margin-top:5px">Powered by BendlessTech</p></div>';
h+='</body></html>';
w.document.write(h);w.document.close();
w.onload=function(){setTimeout(function(){w.print()},300)};
}

// Close modal on outside click or Escape
document.getElementById('order-modal').addEventListener('click',function(e){if(e.target===this)closeModal()});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal()});

// Prevent form resubmission on back button - but do NOT auto-reload
if(window.history.replaceState)window.history.replaceState(null,null,window.location.href);
</script>
</body>
</html>
