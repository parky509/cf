<?php
/**
 * Reconciliation Calendar Page Template - REBUILT FOR 3 STAFF RECONCILIATION
 * Any user can reconcile - 3 different staff must sign each date
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure database tables exist
CFI_Database::create_tables();

$message = '';
$message_type = '';

// Process reconciliation submission
if (isset($_POST['cfi_reconcile_submit']) && wp_verify_nonce($_POST['cfi_reconcile_nonce'], 'cfi_reconcile')) {
    global $wpdb;
    $recon_table = $wpdb->prefix . 'cfi_reconciliation';
    $history_table = $wpdb->prefix . 'cfi_reconciliation_history';
    
    $reconcile_date = sanitize_text_field($_POST['reconcile_date']);
    $remarks = sanitize_textarea_field($_POST['remarks']);
    $current_user_id = get_current_user_id();
    
    // Check if record exists
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $recon_table WHERE reconcile_date = %s",
        $reconcile_date
    ));
    
    if ($existing) {
        // Check if user already signed
        $already_signed = ($existing->staff1_id == $current_user_id) || 
                         ($existing->staff2_id == $current_user_id) || 
                         ($existing->staff3_id == $current_user_id);
        
        if ($already_signed) {
            $message = 'You have already signed this date. Another staff member must sign.';
            $message_type = 'error';
        } elseif ($existing->is_complete) {
            $message = 'This date has already been fully reconciled.';
            $message_type = 'error';
        } elseif (!$existing->staff2_id) {
            // Second staff signing
            $wpdb->update(
                $recon_table,
                array(
                    'staff2_id' => $current_user_id,
                    'staff2_time' => current_time('mysql'),
                    'staff2_remarks' => $remarks,
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%s'),
                array('%d')
            );
            
            $message = 'Second staff signed (2/3). Waiting for third staff to complete reconciliation.';
            $message_type = 'success';
        } elseif (!$existing->staff3_id) {
            // Third staff signing - complete!
            $wpdb->update(
                $recon_table,
                array(
                    'staff3_id' => $current_user_id,
                    'staff3_time' => current_time('mysql'),
                    'staff3_remarks' => $remarks,
                    'is_complete' => 1
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%d'),
                array('%d')
            );
            
            // Add to history
            $wpdb->insert(
                $history_table,
                array(
                    'reconcile_date' => $reconcile_date,
                    'staff1_id' => $existing->staff1_id,
                    'staff1_time' => $existing->staff1_time,
                    'staff1_remarks' => $existing->staff1_remarks,
                    'staff2_id' => $existing->staff2_id,
                    'staff2_time' => $existing->staff2_time,
                    'staff2_remarks' => $existing->staff2_remarks,
                    'staff3_id' => $current_user_id,
                    'staff3_time' => current_time('mysql'),
                    'staff3_remarks' => $remarks,
                    'status' => 'completed'
                ),
                array('%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
            );
            
            $message = 'Date reconciled successfully! All 3 staff have signed.';
            $message_type = 'success';
        }
    } else {
        // First staff signing
        $wpdb->insert(
            $recon_table,
            array(
                'reconcile_date' => $reconcile_date,
                'staff1_id' => $current_user_id,
                'staff1_time' => current_time('mysql'),
                'staff1_remarks' => $remarks,
                'is_complete' => 0
            ),
            array('%s', '%d', '%s', '%s', '%d')
        );
        
        $message = 'First staff signed (1/3). Waiting for 2 more staff to reconcile.';
        $message_type = 'success';
    }
}

$current_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : current_time('Y-m');

// Get reconciliation data for the month
global $wpdb;
$recon_table = $wpdb->prefix . 'cfi_reconciliation';
$month_start = $current_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

$recon_records = array();
$records = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $recon_table WHERE reconcile_date BETWEEN %s AND %s",
    $month_start, $month_end
));
foreach ($records as $r) {
    $recon_records[$r->reconcile_date] = $r;
}

// Get history
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
    $month_start, $month_end
));

// Build calendar
$first_day_of_month = strtotime($month_start);
$days_in_month = date('t', $first_day_of_month);
$first_day_weekday = date('w', $first_day_of_month);
$today = current_time('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f8fafc; min-height: 100vh; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 1rem; }
        
        .page-header {
            background: linear-gradient(135deg, #001943, #002960);
            color: #ffffff !important;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        .btn-outline { background: transparent; border: 2px solid #001943; color: #001943; }
        .btn:hover { transform: translateY(-2px); }
        
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
            margin-bottom: 1.5rem;
        }
        .filter-input {
            padding: 0.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
        }
        
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .calendar-header {
            background: #001943;
            color: white;
            text-align: center;
            padding: 0.5rem;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            min-height: 60px;
        }
        .calendar-day:hover { border-color: #001943; background: #f8fafc; }
        .calendar-day.disabled { background: #f1f5f9; cursor: default; opacity: 0.5; }
        .calendar-day.today { border-color: #001943; border-width: 3px; }
        .calendar-day.reconciled { background: #dcfce7; border-color: #16a34a; }
        .calendar-day.partial { background: #fef3c7; border-color: #d97706; }
        .calendar-day .day-number { font-weight: 600; color: #001943; }
        .calendar-day .status-icon { font-size: 0.8rem; margin-top: 0.25rem; }
        .calendar-day .status-icon.complete { color: #16a34a; }
        .calendar-day .status-icon.partial { color: #d97706; }
        
        .legend {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; }
        .legend-item .box { width: 20px; height: 20px; border-radius: 4px; border: 2px solid; }
        .legend-item .box.reconciled { background: #dcfce7; border-color: #16a34a; }
        .legend-item .box.partial { background: #fef3c7; border-color: #d97706; }
        .legend-item .box.pending { background: white; border-color: #e2e8f0; }
        
        .info-box { background: #f1f5f9; padding: 1rem; border-radius: 8px; margin-top: 1rem; font-size: 0.85rem; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            max-width: 450px;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
        }
        .modal-header {
            background: #001943;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { margin: 0; }
        .modal-close { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943; }
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-input:focus { outline: none; border-color: #001943; }
        textarea.form-input { min-height: 100px; resize: vertical; }
        .modal-actions { display: flex; gap: 0.5rem; margin-top: 1.5rem; }
        .modal-actions .btn { flex: 1; justify-content: center; }
        
        /* History table */
        .history-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; margin-top: 1rem; }
        .history-table th { background: #001943; color: white; padding: 0.5rem; text-align: left; }
        .history-table td { padding: 0.5rem; border-bottom: 1px solid #e2e8f0; }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .status-badge.completed { background: #dcfce7; color: #166534; }
        
        @media (max-width: 768px) {
            .calendar-day { min-height: 45px; }
            .calendar-header { font-size: 0.65rem; padding: 0.25rem; }
            .calendar-day .day-number { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Reconciliation Calendar</h1>
        <?php 
        $history_page = get_page_by_path('reconciliation-history');
        $history_url = $history_page ? get_permalink($history_page) : home_url('/reconciliation-history/');
        ?>
        <a href="<?php echo esc_url($history_url); ?>" class="btn" style="background: #ffffff !important; color: #001943 !important; font-weight: 700 !important; box-shadow: 0 2px 10px rgba(0,0,0,0.3) !important;">
            <i class="fas fa-history" style="color: #001943 !important;"></i> View History
        </a>
    </div>
    
    <?php if ($message) : ?>
    <div class="alert alert-<?php echo esc_attr($message_type); ?>">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo esc_html($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="glass">
        <form method="GET" class="filters">
            <input type="month" name="month" class="filter-input" value="<?php echo esc_attr($current_month); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sync"></i> Load Month
            </button>
        </form>
        
        <h3><?php echo esc_html(date('F Y', $first_day_of_month)); ?></h3>
        
        <div class="calendar">
            <div class="calendar-header">Sun</div>
            <div class="calendar-header">Mon</div>
            <div class="calendar-header">Tue</div>
            <div class="calendar-header">Wed</div>
            <div class="calendar-header">Thu</div>
            <div class="calendar-header">Fri</div>
            <div class="calendar-header">Sat</div>
            
            <?php for ($i = 0; $i < $first_day_weekday; $i++) : ?>
            <div class="calendar-day disabled"></div>
            <?php endfor; ?>
            
            <?php for ($day = 1; $day <= $days_in_month; $day++) : 
                $date = $current_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                $record = isset($recon_records[$date]) ? $recon_records[$date] : null;
                $is_reconciled = $record && $record->is_complete;
                $sign_count = 0;
                if ($record) {
                    if ($record->staff1_id) $sign_count++;
                    if ($record->staff2_id) $sign_count++;
                    if ($record->staff3_id) $sign_count++;
                }
                $is_partial = $record && $sign_count > 0 && !$record->is_complete;
                $is_today = $date === $today;
                $is_future = $date > $today;
                
                $classes = array('calendar-day');
                if ($is_reconciled) $classes[] = 'reconciled';
                elseif ($is_partial) $classes[] = 'partial';
                if ($is_today) $classes[] = 'today';
                if ($is_future || $is_reconciled) $classes[] = 'disabled';
            ?>
            <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" 
                 <?php if (!$is_future && !$is_reconciled) : ?>
                 onclick="openModal('<?php echo esc_attr($date); ?>')"
                 <?php endif; ?>>
                <span class="day-number"><?php echo $day; ?></span>
                <?php if ($is_reconciled) : ?>
                <i class="fas fa-check status-icon complete"></i>
                <?php elseif ($is_partial) : ?>
                <span class="status-icon partial" style="font-size: 0.6rem;"><?php echo $sign_count; ?>/3</span>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <span class="box reconciled"></span>
                <span>Reconciled (3 staff signed)</span>
            </div>
            <div class="legend-item">
                <span class="box partial"></span>
                <span>Partial (Waiting for more staff)</span>
            </div>
            <div class="legend-item">
                <span class="box pending"></span>
                <span>Not Reconciled</span>
            </div>
        </div>
        
        <div class="info-box">
            <p><i class="fas fa-info-circle"></i> Any user can sign reconciliation. 3 different staff members must sign each date.</p>
        </div>
    </div>
    
    <!-- History Section -->
    <div class="glass">
        <h3><i class="fas fa-history"></i> Reconciliation History - <?php echo esc_html(date('F Y', $first_day_of_month)); ?></h3>
        
        <?php if (empty($history)) : ?>
        <p style="text-align: center; color: #64748b; padding: 2rem;">No reconciliation history for this month</p>
        <?php else : ?>
        <div style="overflow-x: auto;">
        <table class="history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Staff 1</th>
                    <th>Staff 2</th>
                    <th>Staff 3</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h) : ?>
                <tr>
                    <td><?php echo esc_html($h->reconcile_date); ?></td>
                    <td><?php echo esc_html($h->staff1_name ?: '-'); ?></td>
                    <td><?php echo esc_html($h->staff2_name ?: '-'); ?></td>
                    <td><?php echo esc_html($h->staff3_name ?: '-'); ?></td>
                    <td><span class="status-badge completed">Completed</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reconciliation Modal -->
<div class="modal" id="recon-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-check"></i> Reconcile Date</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <?php wp_nonce_field('cfi_reconcile', 'cfi_reconcile_nonce'); ?>
                <input type="hidden" name="reconcile_date" id="modal-date">
                
                <div class="form-group">
                    <label>Date</label>
                    <input type="text" class="form-input" id="modal-date-display" readonly>
                </div>
                
                <div class="form-group">
                    <label>Remarks (Optional)</label>
                    <textarea name="remarks" class="form-input" placeholder="Enter any remarks about this reconciliation..."></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" required style="width: 20px; height: 20px;">
                        <span>I confirm that I have verified all records for this date</span>
                    </label>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="cfi_reconcile_submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Sign & Reconcile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(date) {
    document.getElementById('modal-date').value = date;
    document.getElementById('modal-date-display').value = date;
    document.getElementById('recon-modal').classList.add('active');
}

function closeModal() {
    document.getElementById('recon-modal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('recon-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
