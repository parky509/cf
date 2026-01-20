<?php
/**
 * Packing Store Page Template - REBUILT TO DISPLAY PRODUCTS FROM ADMIN
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure database tables exist
CFI_Database::create_tables();

$message = '';
$message_type = '';

// Process save
if (isset($_POST['cfi_save_packing']) && wp_verify_nonce($_POST['cfi_packing_nonce'], 'cfi_packing_store')) {
    global $wpdb;
    $packing_table = $wpdb->prefix . 'cfi_packing_store';
    $stock_table = $wpdb->prefix . 'cfi_stock';
    $record_date = sanitize_text_field($_POST['record_date']);
    $items = isset($_POST['packing']) ? $_POST['packing'] : array();
    
    foreach ($items as $product_id => $data) {
        $product_id = intval($product_id);
        $to_packing = floatval($data['to_packing']);
        $from_packing = floatval($data['from_packing']);
        $balance_in_packing = floatval($data['balance_in_packing']);
        $from_sales = floatval($data['from_sales']); // Read-only, synced from stock
        $to_sales = floatval($data['to_sales']);
        $balance_remark = sanitize_text_field($data['balance_remark']);
        
        // Check if packing record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $packing_table WHERE product_id = %d AND record_date = %s",
            $product_id, $record_date
        ));
        
        $opening = $existing ? floatval($existing->opening) : 0;
        // Closing = Opening - To Packing + From Packing + From Sales - To Sales
        $closing = $opening - $to_packing + $from_packing + $from_sales - $to_sales;
        
        if ($existing) {
            $wpdb->update(
                $packing_table,
                array(
                    'to_packing' => $to_packing,
                    'from_packing' => $from_packing,
                    'balance_in_packing' => $balance_in_packing,
                    'from_sales' => $from_sales,
                    'to_sales' => $to_sales,
                    'closing' => $closing,
                    'balance_remark' => $balance_remark,
                    'staff_id' => get_current_user_id()
                ),
                array('id' => $existing->id),
                array('%f', '%f', '%f', '%f', '%f', '%f', '%s', '%d'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $packing_table,
                array(
                    'product_id' => $product_id,
                    'record_date' => $record_date,
                    'opening' => 0,
                    'to_packing' => $to_packing,
                    'from_packing' => $from_packing,
                    'balance_in_packing' => $balance_in_packing,
                    'from_sales' => $from_sales,
                    'to_sales' => $to_sales,
                    'closing' => $closing,
                    'balance_remark' => $balance_remark,
                    'staff_id' => get_current_user_id()
                ),
                array('%d', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%d')
            );
        }
        
        // Sync to_sales to stock table's from_packing_store (from pack in stock = to sales in packing)
        if ($to_sales > 0) {
            $existing_stock = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $stock_table WHERE product_id = %d AND record_date = %s",
                $product_id, $record_date
            ));
            
            if ($existing_stock) {
                $wpdb->update(
                    $stock_table,
                    array('from_packing_store' => $to_sales),
                    array('id' => $existing_stock->id),
                    array('%f'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $stock_table,
                    array(
                        'product_id' => $product_id,
                        'record_date' => $record_date,
                        'from_packing_store' => $to_sales,
                        'staff_id' => get_current_user_id()
                    ),
                    array('%d', '%s', '%f', '%d')
                );
            }
        }
    }
    
    $message = 'Packing store records saved successfully!';
    $message_type = 'success';
}

// Get products
$products = CFI_Products::get_all();
$record_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : current_time('Y-m-d');

// Get packing records for the date
global $wpdb;
$packing_table = $wpdb->prefix . 'cfi_packing_store';
$stock_table = $wpdb->prefix . 'cfi_stock';
$packing_records = array();
$records = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $packing_table WHERE record_date = %s",
    $record_date
));
foreach ($records as $r) {
    $packing_records[$r->product_id] = $r;
}

// Get stock records to sync from_sales (stock's to_packing_store = packing's from_sales)
$stock_records = array();
$stock_data = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $stock_table WHERE record_date = %s",
    $record_date
));
foreach ($stock_data as $s) {
    $stock_records[$s->product_id] = $s;
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
        
        .container { max-width: 1400px; margin: 0 auto; padding: 1rem; }
        
        .page-header {
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
        .page-header h1 { margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 0.4rem; color: #ffffff !important; font-weight: 600; }
        .page-header h1 i { color: #ffffff !important; font-size: 0.75rem; }
        
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
        .btn-primary { background: #001943; color: white; }
        .btn-success { background: #16a34a; color: white; }
        .btn-outline { background: white; border: 2px solid #001943; color: #001943; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
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
        .glass h3 { color: #001943; margin: 0 0 1rem 0; }
        
        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { }
        .filter-group label { display: block; font-weight: 600; color: #001943; font-size: 0.8rem; margin-bottom: 0.25rem; }
        .filter-input {
            padding: 0.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; min-width: 900px; }
        th { background: #001943; color: white; padding: 0.6rem 0.3rem; text-align: center; white-space: nowrap; font-size: 0.75rem; }
        td { padding: 0.4rem 0.3rem; border-bottom: 1px solid #e2e8f0; text-align: center; }
        tr:hover { background: #f8fafc; }
        .product-name { text-align: left !important; font-weight: 600; color: #001943; white-space: nowrap; }
        
        table input {
            width: 60px;
            padding: 0.3rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            text-align: center;
            font-size: 0.8rem;
        }
        table input:focus { outline: none; border-color: #001943; }
        
        .closing { font-weight: 700; color: #16a34a; }
        .closing.negative { color: #dc2626; }
        
        .info-box {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
        .info-box h4 { color: #001943; margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem; }
        
        .empty { text-align: center; padding: 3rem; color: #64748b; }
        .empty i { font-size: 3rem; margin-bottom: 1rem; display: block; }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            table { font-size: 0.65rem; }
            table input { width: 45px; padding: 0.2rem; }
            th, td { padding: 0.3rem 0.2rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-box-open"></i> Packing Store</h1>
        <?php $packing_history = get_page_by_path('cfi-packing-history'); ?>
        <a href="<?php echo $packing_history ? esc_url(get_permalink($packing_history->ID)) : home_url('/packing-history/'); ?>" class="btn btn-outline" style="background: white !important; color: #001943 !important; font-weight: 600;">
            <i class="fas fa-history" style="color: #001943 !important;"></i> View History
        </a>
    </div>
    
    <?php if ($message) : ?>
    <div class="alert alert-<?php echo esc_attr($message_type); ?>">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo esc_html($message); ?>
    </div>
    <?php if ($message_type === 'success') : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof CFI !== 'undefined' && CFI.successPopup) {
            CFI.successPopup.show({
                title: 'Packing Store Updated!',
                message: '<?php echo esc_js($message); ?>',
                details: {
                    'Date': '<?php echo esc_js(date('d M Y', strtotime($record_date))); ?>'
                },
                refreshOnClose: false
            });
        }
    });
    </script>
    <?php endif; ?>
    <?php endif; ?>
    
    <div class="glass">
        <form method="GET" class="filters">
            <div class="filter-group">
                <label>Select Date</label>
                <input type="date" name="date" class="filter-input" value="<?php echo esc_attr($record_date); ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sync"></i> Load
            </button>
        </form>
    </div>
    
    <?php if (empty($products)) : ?>
    <div class="glass empty">
        <i class="fas fa-box"></i>
        <h3>No Products Found</h3>
        <p>Please add products from the Admin Panel first.</p>
        <a href="/admin-panel/" class="btn btn-primary" style="margin-top: 1rem;">
            <i class="fas fa-plus"></i> Add Products
        </a>
    </div>
    <?php else : ?>
    
    <form method="POST">
        <?php wp_nonce_field('cfi_packing_store', 'cfi_packing_nonce'); ?>
        <input type="hidden" name="record_date" value="<?php echo esc_attr($record_date); ?>">
        
        <div class="glass">
            <h3><i class="fas fa-table"></i> Packing Store Records - <?php echo esc_html(date('d M Y', strtotime($record_date))); ?></h3>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Open</th>
                            <th>To Pack</th>
                            <th>Fr Pack</th>
                            <th>Balance</th>
                            <th>Fr Sales</th>
                            <th>To Sales</th>
                            <th>Remark</th>
                            <th>Close</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product) : 
                            $record = isset($packing_records[$product->id]) ? $packing_records[$product->id] : null;
                            $stock_rec = isset($stock_records[$product->id]) ? $stock_records[$product->id] : null;
                            $opening = $record ? $record->opening : 0;
                            $to_packing = $record ? $record->to_packing : 0;
                            $from_packing = $record ? $record->from_packing : 0;
                            $balance = $record ? $record->balance_in_packing : 0;
                            // from_sales syncs from stock's to_packing_store
                            $from_sales = $stock_rec ? floatval($stock_rec->to_packing_store) : 0;
                            $to_sales = $record ? $record->to_sales : 0;
                            $closing = $record ? $record->closing : 0;
                            $remark = $record ? $record->balance_remark : '';
                        ?>
                        <tr data-opening="<?php echo esc_attr($opening); ?>">
                            <td class="product-name"><?php echo esc_html($product->name); ?></td>
                            <td><?php echo number_format($opening, 0); ?></td>
                            <td>
                                <input type="number" name="packing[<?php echo $product->id; ?>][to_packing]" value="<?php echo esc_attr($to_packing); ?>" min="0" step="0.5" oninput="calculateClosing(this)">
                            </td>
                            <td>
                                <input type="number" name="packing[<?php echo $product->id; ?>][from_packing]" value="<?php echo esc_attr($from_packing); ?>" min="0" step="0.5" oninput="calculateClosing(this)">
                            </td>
                            <td>
                                <input type="number" name="packing[<?php echo $product->id; ?>][balance_in_packing]" value="<?php echo esc_attr($balance); ?>" min="0" step="0.5" oninput="calculateClosing(this)">
                            </td>
                            <td>
                                <!-- from_sales is read-only, synced from Stock's to_packing_store -->
                                <input type="number" name="packing[<?php echo $product->id; ?>][from_sales]" value="<?php echo esc_attr($from_sales); ?>" readonly style="background: #f1f5f9; cursor: not-allowed;" title="Synced from Stock's To Pack column">
                            </td>
                            <td>
                                <input type="number" name="packing[<?php echo $product->id; ?>][to_sales]" value="<?php echo esc_attr($to_sales); ?>" min="0" step="0.5" oninput="calculateClosing(this)">
                            </td>
                            <td>
                                <input type="text" name="packing[<?php echo $product->id; ?>][balance_remark]" value="<?php echo esc_attr($remark); ?>" style="width: 80px;">
                            </td>
                            <td class="closing <?php echo $closing < 0 ? 'negative' : ''; ?>"><?php echo number_format($closing, 1); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" name="cfi_save_packing" class="btn btn-success">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </form>
    
    <div class="info-box">
        <h4><i class="fas fa-info-circle"></i> Calculation Formula</h4>
        <p><strong>Close</strong> = Opening - To Packing + From Packing + From Sales - To Sales</p>
        <p style="font-size: 0.8rem; color: #64748b; margin-top: 0.5rem;">Note: Balance is carried over but not included in closing calculation.</p>
    </div>
    <?php endif; ?>
</div>

<script>
function calculateClosing(input) {
    var row = input.closest('tr');
    var opening = parseFloat(row.dataset.opening) || 0;
    var inputs = row.querySelectorAll('input[type="number"]');
    var toPacking = parseFloat(inputs[0].value) || 0;
    var fromPacking = parseFloat(inputs[1].value) || 0;
    var fromSales = parseFloat(inputs[3].value) || 0;
    var toSales = parseFloat(inputs[4].value) || 0;
    
    var closing = opening - toPacking + fromPacking + fromSales - toSales;
    var closingCell = row.querySelector('.closing');
    closingCell.textContent = closing.toFixed(1);
    closingCell.classList.toggle('negative', closing < 0);
}

// Smart input: select all content on focus so user can type directly
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('table input[type="number"]').forEach(function(input) {
        input.addEventListener('focus', function() {
            var self = this;
            setTimeout(function() {
                self.select();
            }, 10);
        });
    });
    
    // Add form validation for negative values
    var form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            var hasNegatives = false;
            var negativeFields = [];
            
            form.querySelectorAll('input[type="number"]').forEach(function(input) {
                var val = parseFloat(input.value) || 0;
                if (val < 0) {
                    hasNegatives = true;
                    var row = input.closest('tr');
                    var label = row ? row.querySelector('td:first-child').textContent : 'Field';
                    negativeFields.push(label);
                    input.style.borderColor = '#ef4444';
                    input.style.backgroundColor = '#fef2f2';
                }
            });
            
            if (hasNegatives) {
                e.preventDefault();
                if (typeof CFI !== 'undefined' && CFI.negativeValuePopup) {
                    CFI.negativeValuePopup.show(negativeFields);
                } else {
                    alert('Negative values are not allowed! Please check your input and try again.');
                }
                return false;
            }
        });
    }
});
</script>
</body>
</html>
