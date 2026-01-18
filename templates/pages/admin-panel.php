<?php
/**
 * Admin Panel Page Template - REBUILT FROM SCRATCH
 * Uses direct database insertion for reliability
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only admins can access this page
if (!CFI_Auth::is_cfi_admin()) {
    echo '<div class="cfi-main"><div class="cfi-container"><div class="cfi-glass" style="text-align: center; padding: 3rem;"><i class="fa-solid fa-lock" style="font-size: 3rem; color: #dc2626; margin-bottom: 1rem;"></i><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div></div></div>';
    return;
}

// Ensure database tables exist - run on every admin panel load
CFI_Database::create_tables();

// Process form submissions directly (no AJAX - more reliable)
$message = '';
$message_type = '';

// Add Product Form Submission
if (isset($_POST['cfi_add_product_submit']) && wp_verify_nonce($_POST['cfi_product_nonce'], 'cfi_add_product')) {
    $product_name = sanitize_text_field($_POST['product_name']);
    $product_price = floatval($_POST['product_price']);
    
    if (empty($product_name)) {
        $message = 'Please enter a product name';
        $message_type = 'error';
    } elseif ($product_price <= 0) {
        $message = 'Please enter a valid price greater than 0';
        $message_type = 'error';
    } else {
        global $wpdb;
        $table = $wpdb->prefix . 'cfi_products';
        
        $result = $wpdb->insert(
            $table,
            array(
                'name' => $product_name,
                'price' => $product_price,
                'unit' => 'unit',
                'category' => '',
                'status' => 'active',
            ),
            array('%s', '%f', '%s', '%s', '%s')
        );
        
        if ($result) {
            $message = 'Product "' . esc_html($product_name) . '" added successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to add product. Database error: ' . $wpdb->last_error;
            $message_type = 'error';
        }
    }
}

// Delete Product
if (isset($_POST['cfi_delete_product']) && wp_verify_nonce($_POST['cfi_delete_nonce'], 'cfi_delete_product')) {
    $product_id = intval($_POST['product_id']);
    global $wpdb;
    $table = $wpdb->prefix . 'cfi_products';
    
    $result = $wpdb->update(
        $table,
        array('status' => 'deleted'),
        array('id' => $product_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        $message = 'Product deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete product';
        $message_type = 'error';
    }
}

// Edit Product
if (isset($_POST['cfi_edit_product_submit']) && wp_verify_nonce($_POST['cfi_edit_nonce'], 'cfi_edit_product')) {
    $product_id = intval($_POST['edit_product_id']);
    $product_name = sanitize_text_field($_POST['edit_product_name']);
    $product_price = floatval($_POST['edit_product_price']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'cfi_products';
    
    $result = $wpdb->update(
        $table,
        array('name' => $product_name, 'price' => $product_price),
        array('id' => $product_id),
        array('%s', '%f'),
        array('%d')
    );
    
    if ($result !== false) {
        $message = 'Product updated successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to update product';
        $message_type = 'error';
    }
}

// Add Debtor Form Submission
if (isset($_POST['cfi_add_debtor_submit']) && wp_verify_nonce($_POST['cfi_debtor_nonce'], 'cfi_add_debtor')) {
    $debtor_name = sanitize_text_field($_POST['debtor_name']);
    $debtor_phone = sanitize_text_field($_POST['debtor_phone']);
    $initial_debt = floatval($_POST['debtor_initial_debt']);
    
    if (empty($debtor_name)) {
        $message = 'Please enter a debtor name';
        $message_type = 'error';
    } else {
        global $wpdb;
        $table = $wpdb->prefix . 'cfi_debtors';
        
        $result = $wpdb->insert(
            $table,
            array(
                'name' => $debtor_name,
                'phone' => $debtor_phone,
                'email' => '',
                'address' => '',
                'total_debt' => $initial_debt,
                'status' => 'active',
                'created_by' => get_current_user_id(),
            ),
            array('%s', '%s', '%s', '%s', '%f', '%s', '%d')
        );
        
        if ($result) {
            // Record initial debt as a transaction if debt > 0
            if ($initial_debt > 0) {
                $debtor_id = $wpdb->insert_id;
                $trans_table = $wpdb->prefix . 'cfi_debtor_transactions';
                $wpdb->insert(
                    $trans_table,
                    array(
                        'debtor_id' => $debtor_id,
                        'transaction_type' => 'initial',
                        'amount' => $initial_debt,
                        'balance_before' => 0,
                        'balance_after' => $initial_debt,
                        'description' => 'Initial debt balance',
                        'staff_id' => get_current_user_id(),
                        'transaction_date' => current_time('Y-m-d'),
                        'transaction_time' => current_time('H:i:s')
                    ),
                    array('%d', '%s', '%f', '%f', '%f', '%s', '%d', '%s', '%s')
                );
            }
            $message = 'Debtor "' . esc_html($debtor_name) . '" added with initial debt: ₦' . number_format($initial_debt, 2);
            $message_type = 'success';
        } else {
            $message = 'Failed to add debtor. Database error: ' . $wpdb->last_error;
            $message_type = 'error';
        }
    }
}

// Update Debtor Debt Amount
if (isset($_POST['cfi_update_debtor_debt']) && wp_verify_nonce($_POST['cfi_update_debt_nonce'], 'cfi_update_debtor_debt')) {
    $debtor_id = intval($_POST['debtor_id']);
    $new_debt = floatval($_POST['new_debt_amount']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'cfi_debtors';
    $debtor = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $debtor_id));
    
    if ($debtor) {
        $old_debt = $debtor->total_debt;
        $wpdb->update($table, array('total_debt' => $new_debt), array('id' => $debtor_id), array('%f'), array('%d'));
        
        // Record the adjustment
        $trans_table = $wpdb->prefix . 'cfi_debtor_transactions';
        $wpdb->insert(
            $trans_table,
            array(
                'debtor_id' => $debtor_id,
                'transaction_type' => 'adjustment',
                'amount' => abs($new_debt - $old_debt),
                'balance_before' => $old_debt,
                'balance_after' => $new_debt,
                'description' => 'Admin adjusted debt from ₦' . number_format($old_debt, 2) . ' to ₦' . number_format($new_debt, 2),
                'staff_id' => get_current_user_id(),
                'transaction_date' => current_time('Y-m-d'),
                'transaction_time' => current_time('H:i:s')
            ),
            array('%d', '%s', '%f', '%f', '%f', '%s', '%d', '%s', '%s')
        );
        
        $message = 'Debt updated for ' . esc_html($debtor->name);
        $message_type = 'success';
    } else {
        $message = 'Debtor not found';
        $message_type = 'error';
    }
}

// Delete Debtor
if (isset($_POST['cfi_delete_debtor']) && wp_verify_nonce($_POST['cfi_debtor_delete_nonce'], 'cfi_delete_debtor')) {
    $debtor_id = intval($_POST['debtor_id']);
    global $wpdb;
    $table = $wpdb->prefix . 'cfi_debtors';
    
    $result = $wpdb->update(
        $table,
        array('status' => 'deleted'),
        array('id' => $debtor_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        $message = 'Debtor deleted successfully';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete debtor';
        $message_type = 'error';
    }
}

// Clear All Test Data (Super Admin Only)
if (isset($_POST['cfi_clear_all_data']) && wp_verify_nonce($_POST['cfi_clear_data_nonce'], 'cfi_clear_all_data') && CFI_Auth::is_super_admin()) {
    global $wpdb;
    
    // Tables to clear (all records and histories)
    $tables_to_clear = array(
        'cfi_orders',
        'cfi_order_items',
        'cfi_stock',
        'cfi_stock_history',
        'cfi_packing_store',
        'cfi_packing_history',
        'cfi_debtor_transactions',
        'cfi_expenses',
        'cfi_imports',
        'cfi_not_supplied',
        'cfi_supplied_today',
        'cfi_cashout',
        'cfi_financial_summary',
        'cfi_financial_history',
        'cfi_transfer_history',
        'cfi_reconciliation',
        'cfi_reconciliation_history',
        'cfi_sync_queue',
    );
    
    $success_count = 0;
    foreach ($tables_to_clear as $table) {
        $full_table = $wpdb->prefix . $table;
        $result = $wpdb->query("TRUNCATE TABLE `$full_table`");
        if ($result !== false) {
            $success_count++;
        }
    }
    
    // Reset debtors' debt to 0 (keep debtors but clear their debts)
    $wpdb->query("UPDATE `{$wpdb->prefix}cfi_debtors` SET `total_debt` = 0 WHERE 1=1");
    
    if ($success_count > 0) {
        $message = 'All test data has been cleared! ' . $success_count . ' tables emptied. Debtor balances reset to ₦0.';
        $message_type = 'success';
    } else {
        $message = 'Failed to clear data. Please try again.';
        $message_type = 'error';
    }
}

// Fetch products and debtors
global $wpdb;
$products_table = $wpdb->prefix . 'cfi_products';
$products = $wpdb->get_results("SELECT * FROM $products_table WHERE status != 'deleted' ORDER BY name ASC");

$debtors_table = $wpdb->prefix . 'cfi_debtors';
$debtors = $wpdb->get_results("SELECT * FROM $debtors_table WHERE status = 'active' ORDER BY name ASC");

$is_super_admin = CFI_Auth::is_super_admin();
?>
<main class="cfi-main">
    <div class="cfi-container">
        <div class="cfi-page-title">
            <h1>
                <i class="fa-solid fa-gear"></i>
                Admin Panel
            </h1>
        </div>
        
        <?php if ($message) : ?>
        <div class="cfi-alert cfi-alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>" style="padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; background: <?php echo $message_type === 'success' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $message_type === 'success' ? '#166534' : '#991b1b'; ?>; border: 1px solid <?php echo $message_type === 'success' ? '#86efac' : '#fecaca'; ?>;">
            <i class="fa-solid <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo esc_html($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Products Section -->
        <div class="cfi-admin-section cfi-glass" style="margin-bottom: 1.5rem;">
            <h3><i class="fa-solid fa-box"></i> Products Management</h3>
            
            <form method="POST" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin-bottom: 1.5rem; padding: 1rem; background: rgba(0,25,67,0.03); border-radius: 8px;">
                <?php wp_nonce_field('cfi_add_product', 'cfi_product_nonce'); ?>
                <div class="cfi-form-group" style="flex: 2; min-width: 180px; margin: 0;">
                    <label for="product_name" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943;">Product Name</label>
                    <input type="text" id="product_name" name="product_name" class="cfi-input" placeholder="Enter product name" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                </div>
                <div class="cfi-form-group" style="flex: 1; min-width: 120px; margin: 0;">
                    <label for="product_price" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943;">Price (₦)</label>
                    <input type="number" id="product_price" name="product_price" class="cfi-input" step="0.01" min="0.01" placeholder="0.00" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                </div>
                <button type="submit" name="cfi_add_product_submit" class="cfi-btn cfi-btn-success" style="background: #001943; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-plus"></i>
                    Add Product
                </button>
            </form>
            
            <div class="cfi-table-wrapper">
                <table class="cfi-table cfi-table-responsive" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #001943; color: white;">
                            <th style="padding: 0.75rem; text-align: left;">Name</th>
                            <th style="padding: 0.75rem; text-align: left;">Price</th>
                            <th style="padding: 0.75rem; text-align: left;">Status</th>
                            <th style="padding: 0.75rem; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)) : ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem;">
                                <i class="fa-solid fa-box" style="font-size: 2rem; color: #94a3b8; display: block; margin-bottom: 1rem;"></i>
                                <p>No products yet. Add your first product above.</p>
                            </td>
                        </tr>
                        <?php else : ?>
                        <?php foreach ($products as $product) : ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem;"><?php echo esc_html($product->name); ?></td>
                            <td style="padding: 0.75rem;">₦<?php echo number_format((float)$product->price, 2); ?></td>
                            <td style="padding: 0.75rem;">
                                <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: <?php echo $product->status === 'active' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $product->status === 'active' ? '#166534' : '#991b1b'; ?>;">
                                    <?php echo ucfirst($product->status); ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem;">
                                <button type="button" class="cfi-edit-product" data-id="<?php echo esc_attr($product->id); ?>" data-name="<?php echo esc_attr($product->name); ?>" data-price="<?php echo esc_attr($product->price); ?>" style="background: #e2e8f0; color: #001943; border: none; padding: 0.5rem 0.75rem; border-radius: 6px; cursor: pointer; margin-right: 0.5rem;">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <?php wp_nonce_field('cfi_delete_product', 'cfi_delete_nonce'); ?>
                                    <input type="hidden" name="product_id" value="<?php echo esc_attr($product->id); ?>">
                                    <button type="submit" name="cfi_delete_product" onclick="return confirm('Are you sure you want to delete this product?');" style="background: #dc2626; color: white; border: none; padding: 0.5rem 0.75rem; border-radius: 6px; cursor: pointer;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($is_super_admin) : ?>
        <!-- Debtors Section - Super Admin Only -->
        <div class="cfi-admin-section cfi-glass" style="margin-bottom: 1.5rem;">
            <h3><i class="fa-solid fa-user-tag"></i> Debtors Management <span style="font-size: 0.75rem; color: #f59e0b;">(Super Admin)</span></h3>
            
            <form method="POST" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin-bottom: 1.5rem; padding: 1rem; background: rgba(0,25,67,0.03); border-radius: 8px;">
                <?php wp_nonce_field('cfi_add_debtor', 'cfi_debtor_nonce'); ?>
                <div class="cfi-form-group" style="flex: 1; min-width: 150px; margin: 0;">
                    <label for="debtor_name" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943;">Name</label>
                    <input type="text" id="debtor_name" name="debtor_name" class="cfi-input" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                </div>
                <div class="cfi-form-group" style="flex: 1; min-width: 120px; margin: 0;">
                    <label for="debtor_phone" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943;">Phone</label>
                    <input type="text" id="debtor_phone" name="debtor_phone" class="cfi-input" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                </div>
                <div class="cfi-form-group" style="flex: 1; min-width: 120px; margin: 0;">
                    <label for="debtor_initial_debt" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943;">Initial Debt (₦)</label>
                    <input type="number" id="debtor_initial_debt" name="debtor_initial_debt" class="cfi-input" step="0.01" min="0" value="0" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                </div>
                <button type="submit" name="cfi_add_debtor_submit" class="cfi-btn cfi-btn-success" style="background: #001943; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-user-plus"></i>
                    Add Debtor
                </button>
            </form>
            
            <div class="cfi-table-wrapper">
                <table class="cfi-table cfi-table-responsive" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #001943; color: white;">
                            <th style="padding: 0.75rem; text-align: left;">Name</th>
                            <th style="padding: 0.75rem; text-align: left;">Phone</th>
                            <th style="padding: 0.75rem; text-align: left;">Debt</th>
                            <th style="padding: 0.75rem; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($debtors)) : ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem;">
                                <p>No debtors yet.</p>
                            </td>
                        </tr>
                        <?php else : ?>
                        <?php foreach ($debtors as $debtor) : ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem;"><?php echo esc_html($debtor->name); ?></td>
                            <td style="padding: 0.75rem;"><?php echo esc_html($debtor->phone); ?></td>
                            <td style="padding: 0.75rem; color: <?php echo $debtor->total_debt > 0 ? '#dc2626' : '#16a34a'; ?>; font-weight: 600;">₦<?php echo number_format((float)$debtor->total_debt, 2); ?></td>
                            <td style="padding: 0.75rem;">
                                <button type="button" class="cfi-edit-debtor" data-id="<?php echo esc_attr($debtor->id); ?>" data-name="<?php echo esc_attr($debtor->name); ?>" data-debt="<?php echo esc_attr($debtor->total_debt); ?>" style="background: #f59e0b; color: white; border: none; padding: 0.5rem 0.75rem; border-radius: 6px; cursor: pointer; margin-right: 0.25rem;" title="Edit Debt Amount">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <?php wp_nonce_field('cfi_delete_debtor', 'cfi_debtor_delete_nonce'); ?>
                                    <input type="hidden" name="debtor_id" value="<?php echo esc_attr($debtor->id); ?>">
                                    <button type="submit" name="cfi_delete_debtor" onclick="return confirm('Delete this debtor?');" style="background: #dc2626; color: white; border: none; padding: 0.5rem 0.75rem; border-radius: 6px; cursor: pointer;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Clear All Test Data Section - Super Admin Only -->
        <div class="cfi-admin-section cfi-glass" style="margin-bottom: 1.5rem; border: 2px solid #dc2626;">
            <h3 style="color: #dc2626;"><i class="fa-solid fa-exclamation-triangle"></i> Danger Zone <span style="font-size: 0.75rem; color: #f59e0b;">(Super Admin)</span></h3>
            <p style="color: #64748b; margin-bottom: 1rem;">This action will permanently delete all records and histories from the system. Use this to clear test data before going live. Products and debtors will be kept but debtor balances will be reset to ₦0.</p>
            
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                <h4 style="color: #dc2626; margin: 0 0 0.5rem 0;"><i class="fa-solid fa-warning"></i> Warning: This will delete:</h4>
                <ul style="color: #991b1b; margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                    <li>All Orders & Order History</li>
                    <li>All Stock Records & History</li>
                    <li>All Packing Store Records & History</li>
                    <li>All Debtor Transactions (balances reset to ₦0)</li>
                    <li>All Expenses Records</li>
                    <li>All Import Records</li>
                    <li>All Not Supplied & Supplied Today Records</li>
                    <li>All Cash Out Records</li>
                    <li>All Financial Summary & History</li>
                    <li>All Transfer History</li>
                    <li>All Reconciliation Records & History</li>
                </ul>
            </div>
            
            <form method="POST" onsubmit="return confirmClearData();">
                <?php wp_nonce_field('cfi_clear_all_data', 'cfi_clear_data_nonce'); ?>
                <button type="submit" name="cfi_clear_all_data" style="background: #dc2626; color: white; padding: 1rem 2rem; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.75rem; font-size: 1rem;">
                    <i class="fa-solid fa-trash-can"></i>
                    Clear All Test Data
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Edit Product Modal -->
<div id="cfi-edit-product-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; align-items: center; justify-content: center;">
    <div class="cfi-modal-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 25, 67, 0.5);"></div>
    <div class="cfi-modal-content cfi-glass" style="position: relative; max-width: 400px; width: 90%; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,25,67,0.2);">
        <h3 style="color: #001943; margin-bottom: 1.5rem;"><i class="fa-solid fa-pen"></i> Edit Product</h3>
        <form method="POST">
            <?php wp_nonce_field('cfi_edit_product', 'cfi_edit_nonce'); ?>
            <input type="hidden" name="edit_product_id" id="edit-prod-id">
            <div style="margin-bottom: 1rem;">
                <label for="edit_product_name" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943;">Product Name</label>
                <input type="text" id="edit-prod-name" name="edit_product_name" class="cfi-input" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label for="edit_product_price" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943;">Price (₦)</label>
                <input type="number" id="edit-prod-price" name="edit_product_price" class="cfi-input" step="0.01" min="0" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="cfi-modal-close" style="background: #e2e8f0; color: #001943; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" name="cfi_edit_product_submit" style="background: #001943; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Debtor Debt Modal -->
<div id="cfi-edit-debtor-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; align-items: center; justify-content: center;">
    <div class="cfi-modal-overlay-debtor" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 25, 67, 0.5);"></div>
    <div class="cfi-modal-content cfi-glass" style="position: relative; max-width: 400px; width: 90%; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,25,67,0.2);">
        <h3 style="color: #001943; margin-bottom: 1.5rem;"><i class="fa-solid fa-money-bill"></i> Edit Debt Amount</h3>
        <p id="edit-debtor-name-display" style="color: #64748b; margin-bottom: 1rem;"></p>
        <form method="POST">
            <?php wp_nonce_field('cfi_update_debtor_debt', 'cfi_update_debt_nonce'); ?>
            <input type="hidden" name="debtor_id" id="edit-debtor-id">
            <div style="margin-bottom: 1rem;">
                <label for="new_debt_amount" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #001943;">New Debt Amount (₦)</label>
                <input type="number" id="edit-debtor-debt" name="new_debt_amount" class="cfi-input" step="0.01" min="0" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="cfi-modal-close-debtor" style="background: #e2e8f0; color: #001943; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" name="cfi_update_debtor_debt" style="background: #f59e0b; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Update Debt</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Edit Product - Open Modal
    $(document).on('click', '.cfi-edit-product', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var price = $(this).data('price');
        
        $('#edit-prod-id').val(id);
        $('#edit-prod-name').val(name);
        $('#edit-prod-price').val(price);
        $('#cfi-edit-product-modal').css('display', 'flex');
    });
    
    // Edit Debtor - Open Modal
    $(document).on('click', '.cfi-edit-debtor', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var debt = $(this).data('debt');
        
        $('#edit-debtor-id').val(id);
        $('#edit-debtor-name-display').text('Debtor: ' + name);
        $('#edit-debtor-debt').val(debt);
        $('#cfi-edit-debtor-modal').css('display', 'flex');
    });
    
    // Close Modals
    $(document).on('click', '.cfi-modal-close, .cfi-modal-overlay', function() {
        $('#cfi-edit-product-modal').hide();
    });
    $(document).on('click', '.cfi-modal-close-debtor, .cfi-modal-overlay-debtor', function() {
        $('#cfi-edit-debtor-modal').hide();
    });
});

// Confirm Clear All Data
function confirmClearData() {
    var confirm1 = confirm('⚠️ WARNING: This will permanently delete ALL records and histories!\n\nAre you sure you want to clear all test data?');
    if (!confirm1) return false;
    
    var confirm2 = confirm('🚨 FINAL WARNING: This action CANNOT be undone!\n\nType "yes" in the next prompt to confirm deletion.');
    if (!confirm2) return false;
    
    var typeConfirm = prompt('Type "DELETE" to confirm you want to clear all test data:');
    if (typeConfirm !== 'DELETE') {
        alert('Deletion cancelled. You did not type "DELETE".');
        return false;
    }
    
    return true;
}
</script>
