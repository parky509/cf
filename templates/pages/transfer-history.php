<?php
/**
 * Transfer History Page Template - WITH SUPER ADMIN EDIT/DELETE
 * No caching for real-time updates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent caching - ensure fresh data every time
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Ensure database tables exist
CFI_Database::create_tables();

$is_super_admin = CFI_Auth::is_super_admin();
$message = '';
$message_type = '';

// Handle Delete Action
if (isset($_POST['cfi_delete_transfer']) && $is_super_admin && wp_verify_nonce($_POST['cfi_delete_nonce'], 'cfi_delete_transfer')) {
    global $wpdb;
    $transfer_id = intval($_POST['transfer_id']);
    $transfers_table = $wpdb->prefix . 'cfi_transfer_history';
    $result = $wpdb->delete($transfers_table, array('id' => $transfer_id), array('%d'));
    if ($result) {
        $message = 'Transfer record deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete transfer record';
        $message_type = 'error';
    }
}

// Handle Edit Action
if (isset($_POST['cfi_edit_transfer']) && $is_super_admin && wp_verify_nonce($_POST['cfi_edit_nonce'], 'cfi_edit_transfer')) {
    global $wpdb;
    $transfer_id = intval($_POST['transfer_id']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $amount = floatval($_POST['amount']);
    $bank_name = sanitize_text_field($_POST['bank_name']);
    
    $transfers_table = $wpdb->prefix . 'cfi_transfer_history';
    $result = $wpdb->update(
        $transfers_table,
        array('customer_name' => $customer_name, 'amount' => $amount, 'bank_name' => $bank_name),
        array('id' => $transfer_id),
        array('%s', '%f', '%s'),
        array('%d')
    );
    if ($result !== false) {
        $message = 'Transfer record updated successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to update transfer record';
        $message_type = 'error';
    }
}

$today = current_time('Y-m-d');
$start_date = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : $today;
$end_date = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : $today;

// Get transfers directly from database with customer name
global $wpdb;
$transfers_table = $wpdb->prefix . 'cfi_transfer_history';
$users_table = $wpdb->users;

$order_transfers = $wpdb->get_results($wpdb->prepare(
    "SELECT t.*, u.display_name as staff_name 
     FROM $transfers_table t 
     LEFT JOIN $users_table u ON t.staff_id = u.ID 
     WHERE t.source = 'order' AND t.transfer_date BETWEEN %s AND %s 
     ORDER BY t.transfer_date DESC, t.transfer_time DESC",
    $start_date, $end_date
));

$cashout_transfers = $wpdb->get_results($wpdb->prepare(
    "SELECT t.*, u.display_name as staff_name 
     FROM $transfers_table t 
     LEFT JOIN $users_table u ON t.staff_id = u.ID 
     WHERE t.source = 'cashout' AND t.transfer_date BETWEEN %s AND %s 
     ORDER BY t.transfer_date DESC, t.transfer_time DESC",
    $start_date, $end_date
));

$debtor_transfers = $wpdb->get_results($wpdb->prepare(
    "SELECT t.*, u.display_name as staff_name 
     FROM $transfers_table t 
     LEFT JOIN $users_table u ON t.staff_id = u.ID 
     WHERE t.source = 'debtor' AND t.transfer_date BETWEEN %s AND %s 
     ORDER BY t.transfer_date DESC, t.transfer_time DESC",
    $start_date, $end_date
));
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
            gap: 1rem;
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
        .btn-outline { background: white; border: 2px solid #001943; color: #001943; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
        .glass {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,25,67,0.1);
            border: 2px solid rgba(0,25,67,0.1);
            margin-bottom: 1.5rem;
        }
        .glass h3 { color: #001943; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
        
        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 1.5rem;
        }
        .filter-group { }
        .filter-group label { display: block; font-weight: 600; color: #001943; font-size: 0.8rem; margin-bottom: 0.25rem; }
        .filter-input {
            padding: 0.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 700px; }
        th { background: #001943; color: white; padding: 0.75rem 0.5rem; text-align: left; white-space: nowrap; }
        td { padding: 0.6rem 0.5rem; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        .customer-name { font-weight: 600; color: #001943; }
        .amount { font-weight: 600; color: #16a34a; }
        .bank { color: #7c3aed; font-weight: 500; }
        .action-btn { padding: 0.3rem 0.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.75rem; margin: 0.1rem; }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-delete { background: #dc2626; color: white; }
        .btn-edit:hover { background: #2563eb; }
        .btn-delete:hover { background: #b91c1c; }
        .alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; padding: 1.5rem; max-width: 400px; width: 90%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-header h3 { margin: 0; color: #001943; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: 600; color: #001943; font-size: 0.85rem; }
        .form-input { width: 100%; padding: 0.6rem; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 0.9rem; }
        
        tfoot td { background: #001943; color: white; font-weight: 600; }
        .empty { text-align: center; padding: 2rem; color: #64748b; }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            .filters { flex-direction: column; }
            table { font-size: 0.75rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-exchange-alt"></i> Transfer History</h1>
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
    
    <!-- Transfers from Orders -->
    <div class="glass">
        <h3><i class="fas fa-shopping-cart"></i> Transfers from Orders</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Customer Name</th>
                        <th>Amount (₦)</th>
                        <th>Bank</th>
                        <th>Staff</th>
                        <?php if ($is_super_admin) : ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($order_transfers)) : ?>
                    <tr><td colspan="<?php echo $is_super_admin ? '7' : '6'; ?>" class="empty">No transfer records found</td></tr>
                    <?php else : ?>
                    <?php 
                    $total = 0;
                    foreach ($order_transfers as $t) : 
                        $total += $t->amount;
                    ?>
                    <tr>
                        <td><?php echo esc_html($t->transfer_date); ?></td>
                        <td><?php echo esc_html(substr($t->transfer_time, 0, 5)); ?></td>
                        <td class="customer-name"><?php echo esc_html($t->customer_name ?: '-'); ?></td>
                        <td class="amount">₦<?php echo number_format($t->amount, 0); ?></td>
                        <td class="bank"><?php echo esc_html($t->bank_name); ?></td>
                        <td><?php echo esc_html($t->staff_name ?: 'Unknown'); ?></td>
                        <?php if ($is_super_admin) : ?>
                        <td>
                            <button type="button" class="action-btn btn-edit" onclick="openEditModal(<?php echo esc_attr($t->id); ?>, '<?php echo esc_js($t->customer_name); ?>', <?php echo esc_attr($t->amount); ?>, '<?php echo esc_js($t->bank_name); ?>')"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this record?');">
                                <?php wp_nonce_field('cfi_delete_transfer', 'cfi_delete_nonce'); ?>
                                <input type="hidden" name="transfer_id" value="<?php echo esc_attr($t->id); ?>">
                                <button type="submit" name="cfi_delete_transfer" class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($order_transfers)) : ?>
                <tfoot>
                    <tr>
                        <td colspan="3"><strong>Total</strong></td>
                        <td colspan="<?php echo $is_super_admin ? '4' : '3'; ?>"><strong>₦<?php echo number_format($total, 0); ?></strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <!-- Transfers from Cash Out -->
    <div class="glass">
        <h3><i class="fas fa-money-bill-wave"></i> Transfers from Cash Out</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Customer Name</th>
                        <th>Amount (₦)</th>
                        <th>Bank</th>
                        <th>Staff</th>
                        <?php if ($is_super_admin) : ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cashout_transfers)) : ?>
                    <tr><td colspan="<?php echo $is_super_admin ? '7' : '6'; ?>" class="empty">No cash out records found</td></tr>
                    <?php else : ?>
                    <?php 
                    $total_cashout = 0;
                    foreach ($cashout_transfers as $t) : 
                        $total_cashout += $t->amount;
                    ?>
                    <tr>
                        <td><?php echo esc_html($t->transfer_date); ?></td>
                        <td><?php echo esc_html(substr($t->transfer_time, 0, 5)); ?></td>
                        <td class="customer-name"><?php echo esc_html($t->customer_name ?: '-'); ?></td>
                        <td class="amount">₦<?php echo number_format($t->amount, 0); ?></td>
                        <td class="bank"><?php echo esc_html($t->bank_name); ?></td>
                        <td><?php echo esc_html($t->staff_name ?: 'Unknown'); ?></td>
                        <?php if ($is_super_admin) : ?>
                        <td>
                            <button type="button" class="action-btn btn-edit" onclick="openEditModal(<?php echo esc_attr($t->id); ?>, '<?php echo esc_js($t->customer_name); ?>', <?php echo esc_attr($t->amount); ?>, '<?php echo esc_js($t->bank_name); ?>')"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this record?');">
                                <?php wp_nonce_field('cfi_delete_transfer', 'cfi_delete_nonce'); ?>
                                <input type="hidden" name="transfer_id" value="<?php echo esc_attr($t->id); ?>">
                                <button type="submit" name="cfi_delete_transfer" class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($cashout_transfers)) : ?>
                <tfoot>
                    <tr>
                        <td colspan="3"><strong>Total</strong></td>
                        <td colspan="<?php echo $is_super_admin ? '4' : '3'; ?>"><strong>₦<?php echo number_format($total_cashout, 0); ?></strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <!-- Transfers from Debtors -->
    <div class="glass">
        <h3><i class="fas fa-user-clock"></i> Transfers from Debtors</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Debtor Name</th>
                        <th>Amount (₦)</th>
                        <th>Bank</th>
                        <th>Staff</th>
                        <?php if ($is_super_admin) : ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($debtor_transfers)) : ?>
                    <tr><td colspan="<?php echo $is_super_admin ? '7' : '6'; ?>" class="empty">No debtor transfer records found</td></tr>
                    <?php else : ?>
                    <?php 
                    $total_debtor = 0;
                    foreach ($debtor_transfers as $t) : 
                        $total_debtor += $t->amount;
                    ?>
                    <tr>
                        <td><?php echo esc_html($t->transfer_date); ?></td>
                        <td><?php echo esc_html(substr($t->transfer_time, 0, 5)); ?></td>
                        <td class="customer-name"><?php echo esc_html($t->customer_name ?: '-'); ?></td>
                        <td class="amount">₦<?php echo number_format($t->amount, 0); ?></td>
                        <td class="bank"><?php echo esc_html($t->bank_name); ?></td>
                        <td><?php echo esc_html($t->staff_name ?: 'Unknown'); ?></td>
                        <?php if ($is_super_admin) : ?>
                        <td>
                            <button type="button" class="action-btn btn-edit" onclick="openEditModal(<?php echo esc_attr($t->id); ?>, '<?php echo esc_js($t->customer_name); ?>', <?php echo esc_attr($t->amount); ?>, '<?php echo esc_js($t->bank_name); ?>')"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this record?');">
                                <?php wp_nonce_field('cfi_delete_transfer', 'cfi_delete_nonce'); ?>
                                <input type="hidden" name="transfer_id" value="<?php echo esc_attr($t->id); ?>">
                                <button type="submit" name="cfi_delete_transfer" class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($debtor_transfers)) : ?>
                <tfoot>
                    <tr>
                        <td colspan="3"><strong>Total</strong></td>
                        <td colspan="<?php echo $is_super_admin ? '4' : '3'; ?>"><strong>₦<?php echo number_format($total_debtor, 0); ?></strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php if ($is_super_admin) : ?>
<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Transfer</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <?php wp_nonce_field('cfi_edit_transfer', 'cfi_edit_nonce'); ?>
            <input type="hidden" name="transfer_id" id="edit_transfer_id">
            <div class="form-group">
                <label>Customer Name</label>
                <input type="text" name="customer_name" id="edit_customer_name" class="form-input">
            </div>
            <div class="form-group">
                <label>Amount (₦)</label>
                <input type="number" name="amount" id="edit_amount" class="form-input" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label>Bank</label>
                <select name="bank_name" id="edit_bank_name" class="form-input">
                    <option value="Moniepoint MFB">Moniepoint MFB</option>
                    <option value="Access Bank PLC">Access Bank PLC</option>
                </select>
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="cfi_edit_transfer" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, customerName, amount, bankName) {
    document.getElementById('edit_transfer_id').value = id;
    document.getElementById('edit_customer_name').value = customerName || '';
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_bank_name').value = bankName;
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}
</script>
<?php endif; ?>

</body>
</html>
