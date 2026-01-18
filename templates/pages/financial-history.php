<?php
/**
 * Financial History Page Template - WITH SUPER ADMIN DELETE
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_super_admin = CFI_Auth::is_super_admin();
$message = '';
$message_type = '';

// Handle Delete Action
if (isset($_POST['cfi_delete_financial']) && $is_super_admin && wp_verify_nonce($_POST['cfi_delete_nonce'], 'cfi_delete_financial')) {
    global $wpdb;
    $record_id = intval($_POST['record_id']);
    $summary_table = $wpdb->prefix . 'cfi_daily_summary';
    $result = $wpdb->delete($summary_table, array('id' => $record_id), array('%d'));
    if ($result) {
        $message = 'Financial record deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete record';
        $message_type = 'error';
    }
}

// Get date range
$start_date = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : gmdate('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : current_time('Y-m-d');

// Get financial history
$history = CFI_Financial::get_history($start_date, $end_date);
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
            font-size: 0.85rem;
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
        .filter-group label { display: block; font-weight: 600; color: #001943; font-size: 0.8rem; margin-bottom: 0.25rem; }
        .filter-input {
            padding: 0.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.75rem; min-width: 900px; }
        th { background: #001943; color: white; padding: 0.6rem 0.4rem; text-align: left; white-space: nowrap; font-size: 0.65rem; }
        td { padding: 0.5rem 0.4rem; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        
        .positive { color: #16a34a !important; }
        .negative { color: #dc2626 !important; }
        .highlight { font-weight: 700; color: #16a34a; font-size: 0.75rem; }
        .date-col { font-weight: 600; color: #001943; }
        
        .action-btn { padding: 0.25rem 0.4rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.65rem; }
        .btn-delete { background: #dc2626; color: white; }
        .btn-delete:hover { background: #b91c1c; }
        .alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .empty { text-align: center; padding: 2rem; color: #64748b; }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            .filters { flex-direction: column; }
            table { font-size: 0.65rem; }
            th, td { padding: 0.4rem 0.25rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Financial History</h1>
        <a href="/financial-summary/" class="btn btn-outline" style="background: white;">
            <i class="fas fa-calculator"></i> Today's Summary
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
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>
    
    <?php if ($message) : ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <?php echo esc_html($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="glass">
        <h3><i class="fas fa-table"></i> Financial Records</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Sales</th>
                        <th>Transfer (Orders)</th>
                        <th>Cash Sales</th>
                        <th>Cash Out</th>
                        <th>Debtors Cash</th>
                        <th>Expenses</th>
                        <th>Old Cash</th>
                        <th>Cash to Bank</th>
                        <th>Cash Left</th>
                        <?php if ($is_super_admin) : ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)) : ?>
                    <tr><td colspan="<?php echo $is_super_admin ? '11' : '10'; ?>" class="empty">No financial records found for the selected period</td></tr>
                    <?php else : ?>
                    <?php foreach ($history as $record) : ?>
                    <tr>
                        <td class="date-col"><?php echo esc_html($record->record_date); ?></td>
                        <td>₦<?php echo number_format($record->total_sales, 0); ?></td>
                        <td class="negative">-₦<?php echo number_format($record->transfer_from_orders, 0); ?></td>
                        <td>₦<?php echo number_format($record->cash_sales, 0); ?></td>
                        <td class="negative">-₦<?php echo number_format($record->transfer_from_cashout, 0); ?></td>
                        <td class="positive">+₦<?php echo number_format($record->debtors_cash, 0); ?></td>
                        <td class="negative">-₦<?php echo number_format($record->expenses, 0); ?></td>
                        <td>₦<?php echo number_format($record->old_cash, 0); ?></td>
                        <td class="negative">-₦<?php echo number_format($record->cash_to_bank, 0); ?></td>
                        <td class="highlight">₦<?php echo number_format($record->cash_left, 0); ?></td>
                        <?php if ($is_super_admin) : ?>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this financial record?');">
                                <?php wp_nonce_field('cfi_delete_financial', 'cfi_delete_nonce'); ?>
                                <input type="hidden" name="record_id" value="<?php echo esc_attr($record->id); ?>">
                                <button type="submit" name="cfi_delete_financial" class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
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
</body>
</html>
