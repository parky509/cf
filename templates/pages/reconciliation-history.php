<?php
/**
 * Reconciliation History Page Template - WITH 3 STAFF PATTERN
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_super_admin = CFI_Auth::is_super_admin();
$message = '';
$message_type = '';

// Handle Delete Action
if (isset($_POST['cfi_delete_reconciliation']) && $is_super_admin && wp_verify_nonce($_POST['cfi_delete_nonce'], 'cfi_delete_reconciliation')) {
    global $wpdb;
    $record_id = intval($_POST['record_id']);
    $history_table = $wpdb->prefix . 'cfi_reconciliation_history';
    $result = $wpdb->delete($history_table, array('id' => $record_id), array('%d'));
    if ($result) {
        $message = 'Reconciliation record deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete record';
        $message_type = 'error';
    }
}

// Get date range
$start_date = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : gmdate('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : current_time('Y-m-d');

// Get reconciliation history with 3 staff joins
global $wpdb;
$history_table = $wpdb->prefix . 'cfi_reconciliation_history';
$users_table = $wpdb->users;
$history = $wpdb->get_results($wpdb->prepare(
    "SELECT h.*, 
            u1.display_name as staff1_name, 
            u2.display_name as staff2_name,
            u3.display_name as staff3_name 
     FROM $history_table h 
     LEFT JOIN $users_table u1 ON h.staff1_id = u1.ID 
     LEFT JOIN $users_table u2 ON h.staff2_id = u2.ID 
     LEFT JOIN $users_table u3 ON h.staff3_id = u3.ID 
     WHERE h.reconcile_date BETWEEN %s AND %s 
     ORDER BY h.reconcile_date DESC",
    $start_date,
    $end_date
));
?>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    .cfi-recon-container { max-width: 1400px; margin: 0 auto; padding: 1rem; }
    
    .cfi-recon-header {
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
    .cfi-recon-header h1 { margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 0.4rem; color: #ffffff !important; font-weight: 600; }
    .cfi-recon-header h1 i { color: #ffffff !important; font-size: 0.75rem; }
    
    .cfi-recon-btn {
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
    .cfi-recon-btn-primary { background: #001943; color: white; }
    .cfi-recon-btn-outline { background: white; border: 2px solid #001943; color: #001943; }
    .cfi-recon-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    
    .cfi-recon-glass {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0,25,67,0.1);
        border: 2px solid rgba(0,25,67,0.1);
        margin-bottom: 1.5rem;
    }
    .cfi-recon-glass h3 { color: #001943; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; }
    
    .cfi-recon-filters {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-bottom: 1.5rem;
    }
    .cfi-recon-filter-group label { display: block; font-weight: 600; color: #001943; font-size: 0.7rem; margin-bottom: 0.25rem; }
    .cfi-recon-filter-input {
        padding: 0.5rem;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.75rem;
    }
    
    .cfi-recon-table-wrapper { overflow-x: auto; }
    .cfi-recon-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; min-width: 1100px; }
    .cfi-recon-table th { background: #001943; color: white; padding: 0.6rem 0.4rem; text-align: left; white-space: nowrap; font-size: 0.65rem; }
    .cfi-recon-table td { padding: 0.5rem 0.4rem; border-bottom: 1px solid #e2e8f0; font-size: 0.7rem; }
    .cfi-recon-table tr:hover { background: #f8fafc; }
    
    .cfi-recon-status { padding: 0.25rem 0.5rem; border-radius: 20px; font-weight: 600; font-size: 0.6rem; display: inline-block; }
    .cfi-recon-status-completed { background: #dcfce7; color: #166534; }
    .cfi-recon-status-pending { background: #fef3c7; color: #92400e; }
    
    .cfi-recon-date-col { font-weight: 600; color: #001943; }
    
    .cfi-recon-action-btn { padding: 0.25rem 0.4rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.65rem; }
    .cfi-recon-btn-delete { background: #dc2626; color: white; }
    .cfi-recon-btn-delete:hover { background: #b91c1c; }
    .cfi-recon-alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .cfi-recon-alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .cfi-recon-alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    
    .cfi-recon-empty { text-align: center; padding: 2rem; color: #64748b; }
    
    @media (max-width: 768px) {
        .cfi-recon-header { flex-direction: column; text-align: center; }
        .cfi-recon-filters { flex-direction: column; }
        .cfi-recon-table { font-size: 0.65rem; }
        .cfi-recon-table th, .cfi-recon-table td { padding: 0.4rem 0.25rem; }
    }
</style>

<div class="cfi-recon-container">
    <div class="cfi-recon-header">
        <h1><i class="fas fa-calendar-check"></i> Reconciliation History</h1>
        <a href="<?php echo esc_url(home_url('/reconciliation/')); ?>" class="cfi-recon-btn cfi-recon-btn-outline">
            <i class="fas fa-calendar"></i> Calendar View
        </a>
    </div>
    
    <div class="cfi-recon-glass">
        <form method="GET" class="cfi-recon-filters">
            <div class="cfi-recon-filter-group">
                <label>From Date</label>
                <input type="date" name="start" class="cfi-recon-filter-input" value="<?php echo esc_attr($start_date); ?>">
            </div>
            <div class="cfi-recon-filter-group">
                <label>To Date</label>
                <input type="date" name="end" class="cfi-recon-filter-input" value="<?php echo esc_attr($end_date); ?>">
            </div>
            <button type="submit" class="cfi-recon-btn cfi-recon-btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>
    
    <?php if ($message) : ?>
    <div class="cfi-recon-alert cfi-recon-alert-<?php echo $message_type; ?>">
        <?php echo esc_html($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="cfi-recon-glass">
        <h3><i class="fas fa-table"></i> Reconciliation Records</h3>
        <div class="cfi-recon-table-wrapper">
            <table class="cfi-recon-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff 1</th>
                        <th>Time</th>
                        <th>Remarks</th>
                        <th>Staff 2</th>
                        <th>Time</th>
                        <th>Remarks</th>
                        <th>Staff 3</th>
                        <th>Time</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <?php if ($is_super_admin) : ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)) : ?>
                    <tr><td colspan="<?php echo $is_super_admin ? '12' : '11'; ?>" class="cfi-recon-empty">No reconciliation records found for the selected period</td></tr>
                    <?php else : ?>
                    <?php foreach ($history as $record) : ?>
                    <tr>
                        <td class="cfi-recon-date-col"><?php echo esc_html($record->reconcile_date); ?></td>
                        <td><?php echo esc_html($record->staff1_name ?: 'N/A'); ?></td>
                        <td><?php echo $record->staff1_time ? esc_html(date('H:i', strtotime($record->staff1_time))) : 'N/A'; ?></td>
                        <td><?php echo esc_html($record->staff1_remarks ?: '-'); ?></td>
                        <td><?php echo esc_html($record->staff2_name ?: 'N/A'); ?></td>
                        <td><?php echo $record->staff2_time ? esc_html(date('H:i', strtotime($record->staff2_time))) : 'N/A'; ?></td>
                        <td><?php echo esc_html($record->staff2_remarks ?: '-'); ?></td>
                        <td><?php echo esc_html($record->staff3_name ?: 'N/A'); ?></td>
                        <td><?php echo $record->staff3_time ? esc_html(date('H:i', strtotime($record->staff3_time))) : 'N/A'; ?></td>
                        <td><?php echo esc_html($record->staff3_remarks ?: '-'); ?></td>
                        <td>
                            <span class="cfi-recon-status cfi-recon-status-<?php echo $record->status === 'completed' ? 'completed' : 'pending'; ?>">
                                <?php echo ucfirst(esc_html($record->status)); ?>
                            </span>
                        </td>
                        <?php if ($is_super_admin) : ?>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this reconciliation record?');">
                                <?php wp_nonce_field('cfi_delete_reconciliation', 'cfi_delete_nonce'); ?>
                                <input type="hidden" name="record_id" value="<?php echo esc_attr($record->id); ?>">
                                <button type="submit" name="cfi_delete_reconciliation" class="cfi-recon-action-btn cfi-recon-btn-delete"><i class="fas fa-trash"></i></button>
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
