<?php
/**
 * Expenses History Page Template - WITH SUPER ADMIN DELETE
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
if (isset($_POST['cfi_delete_expense']) && $is_super_admin && wp_verify_nonce($_POST['cfi_delete_nonce'], 'cfi_delete_expense')) {
    global $wpdb;
    $expense_id = intval($_POST['expense_id']);
    $expenses_table = $wpdb->prefix . 'cfi_expenses';
    $result = $wpdb->delete($expenses_table, array('id' => $expense_id), array('%d'));
    if ($result) {
        $message = 'Expense deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete expense';
        $message_type = 'error';
    }
}

$start_date = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : gmdate('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : current_time('Y-m-d');

// Get expenses
global $wpdb;
$expenses_table = $wpdb->prefix . 'cfi_expenses';
$users_table = $wpdb->users;

$expenses = $wpdb->get_results($wpdb->prepare(
    "SELECT e.*, u.display_name as staff_name 
     FROM $expenses_table e 
     LEFT JOIN $users_table u ON e.staff_id = u.ID 
     WHERE e.expense_date BETWEEN %s AND %s 
     ORDER BY e.expense_date DESC, e.expense_time DESC",
    $start_date, $end_date
));

// Group by date
$grouped = array();
foreach ($expenses as $exp) {
    if (!isset($grouped[$exp->expense_date])) {
        $grouped[$exp->expense_date] = array('date' => $exp->expense_date, 'expenses' => array(), 'total' => 0);
    }
    $grouped[$exp->expense_date]['expenses'][] = $exp;
    $grouped[$exp->expense_date]['total'] += $exp->amount;
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
        .page-header { background: linear-gradient(135deg, #001943, #002960); color: #ffffff !important; padding: 0.75rem 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
        .page-header h1 { margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 0.4rem; color: #ffffff !important; font-weight: 600; }
        .page-header h1 i { color: #ffffff !important; font-size: 0.75rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.75rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.65rem; transition: all 0.3s; }
        .btn-primary { background: #001943; color: white; }
        .btn-outline { background: white; border: 2px solid #001943; color: #001943; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .glass { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,25,67,0.1); border: 2px solid rgba(0,25,67,0.1); margin-bottom: 1.5rem; }
        .filters { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1.5rem; }
        .filter-group label { display: block; font-weight: 600; color: #001943; font-size: 0.8rem; margin-bottom: 0.25rem; }
        .filter-input { padding: 0.5rem; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; }
        .day-header { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: #001943; color: white; border-radius: 8px; margin-bottom: 0.75rem; font-size: 0.8rem; }
        .day-total { color: #f87171; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #f1f5f9; padding: 0.6rem 0.5rem; text-align: left; color: #001943; font-size: 0.75rem; }
        td { padding: 0.5rem; border-bottom: 1px solid #e2e8f0; }
        .amount { font-weight: 600; color: #dc2626; }
        .action-btn { padding: 0.25rem 0.4rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.65rem; }
        .btn-delete { background: #dc2626; color: white; }
        .alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .empty { text-align: center; padding: 2rem; color: #64748b; }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            .filters { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Expenses History</h1>
        <a href="/expenses/" class="btn btn-outline" style="background: white;">
            <i class="fas fa-plus"></i> Add Expense
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
        <?php if (empty($grouped)) : ?>
        <p class="empty">No expenses found for this period</p>
        <?php else : ?>
        <?php foreach ($grouped as $day) : ?>
        <div style="margin-bottom: 1.5rem;">
            <div class="day-header">
                <span><i class="fas fa-calendar"></i> <?php echo esc_html($day['date']); ?></span>
                <span class="day-total">Total: ₦<?php echo number_format($day['total'], 0); ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Staff</th>
                        <?php if ($is_super_admin) : ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($day['expenses'] as $exp) : ?>
                    <tr>
                        <td><?php echo esc_html(substr($exp->expense_time, 0, 5)); ?></td>
                        <td><?php echo esc_html($exp->description); ?></td>
                        <td class="amount">₦<?php echo number_format($exp->amount, 0); ?></td>
                        <td><?php echo esc_html($exp->staff_name ?: 'Unknown'); ?></td>
                        <?php if ($is_super_admin) : ?>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this expense?');">
                                <?php wp_nonce_field('cfi_delete_expense', 'cfi_delete_nonce'); ?>
                                <input type="hidden" name="expense_id" value="<?php echo esc_attr($exp->id); ?>">
                                <button type="submit" name="cfi_delete_expense" class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
