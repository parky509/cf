<?php
/**
 * Expenses Page Template - REBUILT WITH MULTIPLE EXPENSE ROWS
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure database tables exist
CFI_Database::create_tables();

$message = '';
$message_type = '';

// Process expenses submission
if (isset($_POST['cfi_submit_expenses']) && wp_verify_nonce($_POST['cfi_expenses_nonce'], 'cfi_add_expenses')) {
    global $wpdb;
    $expenses_table = $wpdb->prefix . 'cfi_expenses';
    $items = isset($_POST['expenses']) ? $_POST['expenses'] : array();
    $added_count = 0;
    $total_added = 0;
    
    foreach ($items as $item) {
        $description = sanitize_text_field($item['description']);
        $amount = floatval($item['amount']);
        
        if (!empty($description) && $amount > 0) {
            $result = $wpdb->insert(
                $expenses_table,
                array(
                    'description' => $description,
                    'amount' => $amount,
                    'expense_date' => current_time('Y-m-d'),
                    'expense_time' => current_time('H:i:s'),
                    'staff_id' => get_current_user_id()
                ),
                array('%s', '%f', '%s', '%s', '%d')
            );
            
            if ($result) {
                $added_count++;
                $total_added += $amount;
            }
        }
    }
    
    if ($added_count > 0) {
        // Update financial summary
        CFI_Financial::update_daily_summary(current_time('Y-m-d'));
        
        $message = $added_count . ' expense(s) added successfully! Total: ₦' . number_format($total_added, 0);
        $message_type = 'success';
    } else {
        $message = 'No valid expenses to add. Please enter description and amount.';
        $message_type = 'error';
    }
}

// Get today's expenses
$today = current_time('Y-m-d');
global $wpdb;
$expenses_table = $wpdb->prefix . 'cfi_expenses';
$users_table = $wpdb->users;
$expenses = $wpdb->get_results($wpdb->prepare(
    "SELECT e.*, u.display_name as staff_name 
     FROM $expenses_table e 
     LEFT JOIN $users_table u ON e.staff_id = u.ID 
     WHERE e.expense_date = %s 
     ORDER BY e.expense_time DESC",
    $today
));
$total_expenses = 0;
foreach ($expenses as $exp) {
    $total_expenses += $exp->amount;
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
        
        .container { max-width: 1000px; margin: 0 auto; padding: 1rem; }
        
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
        .page-header a, .page-header span { color: #ffffff !important; }
        
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
        .btn-danger { background: #dc2626; color: white; }
        .btn-outline { background: white; border: 2px solid #001943; color: #001943; }
        .btn-sm { padding: 0.4rem 0.6rem; font-size: 0.65rem; }
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
        .glass h3 { color: #001943; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
        
        .expense-rows { margin-bottom: 1rem; }
        .expense-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            flex-wrap: wrap;
        }
        .expense-row .row-number {
            width: 30px;
            height: 30px;
            background: #001943;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        .expense-row input {
            flex: 1;
            min-width: 150px;
            padding: 0.6rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        .expense-row input:focus { outline: none; border-color: #001943; }
        .expense-row .amount-input { max-width: 150px; }
        .expense-row .remove-btn {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .expense-row .remove-btn:hover { background: #fecaca; }
        
        .add-row-btn {
            width: 100%;
            padding: 0.75rem;
            border: 2px dashed #001943;
            background: rgba(0,25,67,0.05);
            color: #001943;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .add-row-btn:hover { background: rgba(0,25,67,0.1); }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #001943; color: white; padding: 0.75rem 0.5rem; text-align: left; }
        td { padding: 0.6rem 0.5rem; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        .amount { font-weight: 600; color: #dc2626; }
        tfoot td { background: #001943; color: white; font-weight: 600; }
        .empty { text-align: center; padding: 2rem; color: #64748b; }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            .expense-row { flex-direction: column; align-items: stretch; }
            .expense-row .row-number { display: none; }
            .expense-row input { min-width: 100%; max-width: 100% !important; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Expenses Record</h1>
        <?php $expenses_history = get_page_by_path('cfi-expenses-history'); ?>
        <a href="<?php echo $expenses_history ? esc_url(get_permalink($expenses_history->ID)) : home_url('/expenses-history/'); ?>" class="btn btn-outline" style="background: white !important; color: #001943 !important; font-weight: 600;">
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
                title: 'Expenses Recorded!',
                message: '<?php echo esc_js($message); ?>',
                details: {},
                refreshOnClose: false
            });
        }
    });
    </script>
    <?php endif; ?>
    <?php endif; ?>
    
    <div class="glass">
        <h3><i class="fas fa-plus-circle"></i> Add Expenses</h3>
        <form method="POST" id="expenses-form">
            <?php wp_nonce_field('cfi_add_expenses', 'cfi_expenses_nonce'); ?>
            
            <div class="expense-rows" id="expense-rows">
                <div class="expense-row">
                    <span class="row-number">1</span>
                    <input type="text" name="expenses[0][description]" placeholder="Enter expense description..." required>
                    <input type="number" name="expenses[0][amount]" class="amount-input" placeholder="Amount (₦)" min="0" step="0.01" required oninput="calculateTotal()">
                    <button type="button" class="remove-btn" onclick="removeRow(this)" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <button type="button" class="add-row-btn" onclick="addRow()">
                <i class="fas fa-plus"></i> Add Another Expense
            </button>
            
            <!-- Real-time Total Display -->
            <div id="expenses-total-display" style="background: linear-gradient(135deg, #001943, #002960); color: white; padding: 1rem; border-radius: 8px; margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 600;"><i class="fas fa-calculator"></i> Total Expenses:</span>
                <span id="running-total" style="font-size: 1.5rem; font-weight: 700;">₦0</span>
            </div>
            
            <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Clear
                </button>
                <button type="submit" name="cfi_submit_expenses" class="btn btn-success">
                    <i class="fas fa-save"></i> Submit Expenses
                </button>
            </div>
        </form>
    </div>
    
    <div class="glass">
        <h3><i class="fas fa-list"></i> Today's Expenses</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Description</th>
                        <th>Amount (₦)</th>
                        <th>Staff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)) : ?>
                    <tr><td colspan="4" class="empty">No expenses recorded today</td></tr>
                    <?php else : ?>
                    <?php foreach ($expenses as $exp) : ?>
                    <tr>
                        <td><?php echo esc_html(substr($exp->expense_time, 0, 5)); ?></td>
                        <td><?php echo esc_html($exp->description); ?></td>
                        <td class="amount">₦<?php echo number_format($exp->amount, 0); ?></td>
                        <td><?php echo esc_html($exp->staff_name ?: 'Unknown'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($expenses)) : ?>
                <tfoot>
                    <tr>
                        <td colspan="2"><strong>Total</strong></td>
                        <td colspan="2"><strong>₦<?php echo number_format($total_expenses, 0); ?></strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
var rowCount = 1;

function addRow() {
    rowCount++;
    var container = document.getElementById('expense-rows');
    var newRow = document.createElement('div');
    newRow.className = 'expense-row';
    newRow.innerHTML = '<span class="row-number">' + rowCount + '</span>' +
        '<input type="text" name="expenses[' + (rowCount-1) + '][description]" placeholder="Enter expense description...">' +
        '<input type="number" name="expenses[' + (rowCount-1) + '][amount]" class="amount-input" placeholder="Amount (₦)" min="0" step="0.01" oninput="calculateTotal()">' +
        '<button type="button" class="remove-btn" onclick="removeRow(this)" title="Remove"><i class="fas fa-times"></i></button>';
    container.appendChild(newRow);
    calculateTotal();
}

function removeRow(btn) {
    var rows = document.querySelectorAll('.expense-row');
    if (rows.length > 1) {
        btn.closest('.expense-row').remove();
        // Renumber rows
        document.querySelectorAll('.expense-row .row-number').forEach(function(el, idx) {
            el.textContent = idx + 1;
        });
        calculateTotal();
    }
}

function calculateTotal() {
    var inputs = document.querySelectorAll('.amount-input');
    var total = 0;
    inputs.forEach(function(input) {
        var val = parseFloat(input.value) || 0;
        total += val;
    });
    document.getElementById('running-total').textContent = '₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 0, maximumFractionDigits: 0});
}

// Add event listeners and form validation
document.addEventListener('DOMContentLoaded', function() {
    var firstInput = document.querySelector('.expense-row .amount-input');
    if (firstInput) {
        firstInput.addEventListener('input', calculateTotal);
    }
    
    // Add form validation for negative values
    var form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            var hasNegatives = false;
            var negativeFields = [];
            
            form.querySelectorAll('.amount-input').forEach(function(input) {
                var val = parseFloat(input.value) || 0;
                if (val < 0) {
                    hasNegatives = true;
                    var row = input.closest('.expense-row');
                    var descInput = row.querySelector('input[type="text"]');
                    var label = descInput ? descInput.value : 'Expense';
                    negativeFields.push(label || 'Amount field');
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
