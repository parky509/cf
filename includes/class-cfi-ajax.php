<?php
/**
 * AJAX Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Ajax {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->register_ajax_handlers();
    }
    
    /**
     * Register all AJAX handlers
     */
    private function register_ajax_handlers() {
        $actions = array(
            // Products
            'get_products',
            'add_product',
            'update_product',
            'delete_product',
            
            // Orders
            'submit_order',
            'get_orders',
            'get_order_history',
            'get_order_product_summary',
            'get_order_details',
            
            // Stock
            'get_stock',
            'update_stock',
            'get_stock_history',
            
            // Packing
            'get_packing',
            'update_packing',
            'get_packing_history',
            
            // Debtors
            'get_debtors',
            'get_debtor_balances',
            'add_debtor',
            'update_debtor',
            'delete_debtor',
            'debtor_order',
            'debtor_payment',
            'get_debtor_history',
            
            // Expenses
            'add_expense',
            'get_expenses',
            'get_expense_history',
            
            // Imports
            'add_import',
            'get_imports',
            'get_import_history',
            
            // Not Supplied
            'add_not_supplied',
            'get_not_supplied',
            'mark_as_supplied',
            'get_not_supplied_history',
            
            // Supplied Today
            'add_supplied_today',
            'get_supplied_today',
            'get_supplied_today_history',
            
            // Cash Out
            'add_cashout',
            'get_cashout',
            
            // Financial
            'get_financial_summary',
            'update_financial',
            'get_financial_history',
            
            // Transfer History
            'get_transfer_history',
            
            // Reconciliation
            'reconcile_date',
            'get_reconciliation',
            
            // Backup
            'download_backup',
            'upload_backup',
            'get_backup_list',
            
            // Sync
            'sync_offline_data',
            
            // Admin
            'get_users',
            'add_user',
            'update_user',
            'delete_user',
            'delete_history',
            'update_history',
            'recreate_pages',
        );
        
        foreach ($actions as $action) {
            add_action('wp_ajax_cfi_' . $action, array($this, 'handle_' . $action));
        }
    }
    
    /**
     * Verify nonce and user permissions
     */
    private function verify_request($admin_only = false) {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'cfi_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'chinemerem-foods')));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please login to continue', 'chinemerem-foods')));
        }
        
        if ($admin_only && !CFI_Auth::is_cfi_admin()) {
            wp_send_json_error(array('message' => __('You do not have permission for this action', 'chinemerem-foods')));
        }
        
        return true;
    }
    
    /**
     * Get products
     */
    public function handle_get_products() {
        $this->verify_request();
        $products = CFI_Products::get_all();
        wp_send_json_success(array('products' => $products));
    }
    
    /**
     * Add product
     */
    public function handle_add_product() {
        $this->verify_request(true);
        
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $unit = isset($_POST['unit']) ? sanitize_text_field(wp_unslash($_POST['unit'])) : 'unit';
        $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Product name is required', 'chinemerem-foods')));
        }
        
        if ($price <= 0) {
            wp_send_json_error(array('message' => __('Price must be greater than 0', 'chinemerem-foods')));
        }
        
        $result = CFI_Products::add($name, $price, $unit, $category);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Product added successfully', 'chinemerem-foods'),
                'product_id' => $result
            ));
        } else {
            global $wpdb;
            $db_error = $wpdb->last_error;
            $error_message = __('Failed to add product', 'chinemerem-foods');
            if (!empty($db_error)) {
                error_log('CFI Add Product DB Error: ' . $db_error);
            }
            wp_send_json_error(array('message' => $error_message));
        }
    }
    
    /**
     * Update product
     */
    public function handle_update_product() {
        $this->verify_request(true);
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $unit = isset($_POST['unit']) ? sanitize_text_field(wp_unslash($_POST['unit'])) : 'unit';
        $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid product ID', 'chinemerem-foods')));
        }
        
        $result = CFI_Products::update($id, $name, $price, $unit, $category);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Product updated successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update product', 'chinemerem-foods')));
        }
    }
    
    /**
     * Delete product
     */
    public function handle_delete_product() {
        $this->verify_request(true);
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid product ID', 'chinemerem-foods')));
        }
        
        $result = CFI_Products::delete($id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Product deleted successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete product', 'chinemerem-foods')));
        }
    }
    
    /**
     * Submit order
     */
    public function handle_submit_order() {
        $this->verify_request();
        
        $order_data = isset($_POST['order']) ? json_decode(stripslashes($_POST['order']), true) : array();
        
        if (empty($order_data)) {
            wp_send_json_error(array('message' => __('Invalid order data', 'chinemerem-foods')));
        }
        
        // Sanitize order data
        $order_data = $this->sanitize_order_data($order_data);
        
        $result = CFI_Orders::submit($order_data);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Order submitted successfully', 'chinemerem-foods'),
                'order_id' => $result['order_id']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Sanitize order data
     */
    private function sanitize_order_data($data) {
        $sanitized = array();
        
        $sanitized['items'] = array();
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $sanitized['items'][] = array(
                    'product_id' => intval($item['product_id']),
                    'quantity' => floatval($item['quantity']),
                    'price' => floatval($item['price']),
                    'discount' => floatval($item['discount'] ?? 0),
                    'total' => floatval($item['total'])
                );
            }
        }
        
        $sanitized['payment_method'] = sanitize_text_field($data['payment_method'] ?? 'cash');
        $sanitized['transfer_amount'] = floatval($data['transfer_amount'] ?? 0);
        $sanitized['cash_amount'] = floatval($data['cash_amount'] ?? 0);
        $sanitized['bank_name'] = sanitize_text_field($data['bank_name'] ?? '');
        $sanitized['total_quantity'] = floatval($data['total_quantity'] ?? 0);
        $sanitized['total_amount'] = floatval($data['total_amount'] ?? 0);
        $sanitized['discount_amount'] = floatval($data['discount_amount'] ?? 0);
        $sanitized['grand_total'] = floatval($data['grand_total'] ?? 0);
        $sanitized['debtor_id'] = intval($data['debtor_id'] ?? 0);
        $sanitized['order_type'] = sanitize_text_field($data['order_type'] ?? 'cash');
        
        return $sanitized;
    }
    
    /**
     * Get orders
     */
    public function handle_get_orders() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $orders = CFI_Orders::get_by_date($date);
        
        wp_send_json_success(array('orders' => $orders));
    }
    
    /**
     * Get order history
     */
    public function handle_get_order_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
        
        $history = CFI_Orders::get_history($start_date, $end_date, $page, $per_page);
        
        wp_send_json_success($history);
    }
    
    /**
     * Get order product summary
     */
    public function handle_get_order_product_summary() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'cash';
        
        $summary = CFI_Orders::get_product_summary($date, $type);
        
        wp_send_json_success(array('summary' => $summary));
    }
    
    /**
     * Get order details by order ID
     */
    public function handle_get_order_details() {
        // Allow GET request for this action (no nonce needed, just user login)
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please login to continue', 'chinemerem-foods')));
        }
        
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Invalid order ID', 'chinemerem-foods')));
        }
        
        global $wpdb;
        $orders_table = $wpdb->prefix . 'cfi_orders';
        $items_table = $wpdb->prefix . 'cfi_order_items';
        $products_table = $wpdb->prefix . 'cfi_products';
        
        // Get order details
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'chinemerem-foods')));
        }
        
        // Get order items with product names
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.*, p.name as product_name 
             FROM $items_table oi 
             LEFT JOIN $products_table p ON oi.product_id = p.id 
             WHERE oi.order_id = %d",
            $order_id
        ));
        
        wp_send_json_success(array(
            'order_number' => $order->order_number,
            'order_date' => $order->order_date,
            'order_time' => $order->order_time,
            'customer_name' => $order->customer_name,
            'grand_total' => $order->grand_total,
            'items' => $items
        ));
    }
    
    /**
     * Get stock
     */
    public function handle_get_stock() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $stock = CFI_Stock::get_by_date($date);
        
        wp_send_json_success(array('stock' => $stock));
    }
    
    /**
     * Update stock
     */
    public function handle_update_stock() {
        $this->verify_request();
        
        $stock_data = isset($_POST['stock']) ? json_decode(stripslashes($_POST['stock']), true) : array();
        
        if (empty($stock_data)) {
            wp_send_json_error(array('message' => __('Invalid stock data', 'chinemerem-foods')));
        }
        
        $result = CFI_Stock::update($stock_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Stock updated successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update stock', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get stock history
     */
    public function handle_get_stock_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        $history = CFI_Stock::get_history($start_date, $end_date, $product_id);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Get packing store
     */
    public function handle_get_packing() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $packing = CFI_Packing::get_by_date($date);
        
        wp_send_json_success(array('packing' => $packing));
    }
    
    /**
     * Update packing store
     */
    public function handle_update_packing() {
        $this->verify_request();
        
        $packing_data = isset($_POST['packing']) ? json_decode(stripslashes($_POST['packing']), true) : array();
        
        if (empty($packing_data)) {
            wp_send_json_error(array('message' => __('Invalid packing data', 'chinemerem-foods')));
        }
        
        $result = CFI_Packing::update($packing_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Packing store updated successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update packing store', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get packing history
     */
    public function handle_get_packing_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        $history = CFI_Packing::get_history($start_date, $end_date);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Get debtors
     */
    public function handle_get_debtors() {
        $this->verify_request();
        
        $debtors = CFI_Debtors::get_all();
        
        wp_send_json_success(array('debtors' => $debtors));
    }

    /**
     * Get debtor balances from latest transactions
     */
    public function handle_get_debtor_balances() {
        $this->verify_request();
        
        global $wpdb;
        $debtors_table = $wpdb->prefix . 'cfi_debtors';
        $trans_table = $wpdb->prefix . 'cfi_debtor_transactions';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $debtors_table) || !preg_match('/^[A-Za-z0-9_]+$/', $trans_table)) {
            wp_send_json_error(array('message' => __('Invalid table name', 'chinemerem-foods')));
        }
        $safe_debtors_table = esc_sql($debtors_table);
        $safe_trans_table = esc_sql($trans_table);
        
        $latest_debt_table = "(
            SELECT dt.debtor_id, dt.balance_after
            FROM `{$safe_trans_table}` dt
            INNER JOIN (
                SELECT debtor_id, MAX(id) AS max_id
                FROM `{$safe_trans_table}`
                GROUP BY debtor_id
            ) latest ON latest.max_id = dt.id
        )";
        
        $rows = $wpdb->get_results(
            "SELECT d.id, COALESCE(latest.balance_after, d.total_debt) AS balance
            FROM `{$safe_debtors_table}` d
            LEFT JOIN {$latest_debt_table} AS latest ON latest.debtor_id = d.id
            WHERE d.status = 'active'"
        );
        
        $balances = array();
        foreach ($rows as $row) {
            $balances[$row->id] = floatval($row->balance);
        }
        
        wp_send_json_success(array('balances' => $balances));
    }
    
    /**
     * Add debtor
     */
    public function handle_add_debtor() {
        $this->verify_request(true);
        
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $address = isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '';
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Debtor name is required', 'chinemerem-foods')));
        }
        
        $result = CFI_Debtors::add($name, $phone, $email, $address);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Debtor added successfully', 'chinemerem-foods'),
                'debtor_id' => $result
            ));
        } else {
            global $wpdb;
            $db_error = $wpdb->last_error;
            if (!empty($db_error)) {
                error_log('CFI Add Debtor DB Error: ' . $db_error);
            }
            wp_send_json_error(array('message' => __('Failed to add debtor', 'chinemerem-foods')));
        }
    }
    
    /**
     * Update debtor
     */
    public function handle_update_debtor() {
        $this->verify_request(true);
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $address = isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '';
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid debtor ID', 'chinemerem-foods')));
        }
        
        $result = CFI_Debtors::update($id, $name, $phone, $email, $address);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Debtor updated successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update debtor', 'chinemerem-foods')));
        }
    }
    
    /**
     * Delete debtor
     */
    public function handle_delete_debtor() {
        $this->verify_request(true);
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid debtor ID', 'chinemerem-foods')));
        }
        
        $result = CFI_Debtors::delete($id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Debtor deleted successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete debtor', 'chinemerem-foods')));
        }
    }
    
    /**
     * Debtor order
     */
    public function handle_debtor_order() {
        $this->verify_request();
        
        $debtor_id = isset($_POST['debtor_id']) ? intval($_POST['debtor_id']) : 0;
        $order_data = isset($_POST['order']) ? json_decode(stripslashes($_POST['order']), true) : array();
        
        if (!$debtor_id) {
            wp_send_json_error(array('message' => __('Invalid debtor ID', 'chinemerem-foods')));
        }
        
        $order_data = $this->sanitize_order_data($order_data);
        $order_data['debtor_id'] = $debtor_id;
        $order_data['order_type'] = 'credit';
        
        $result = CFI_Debtors::add_order($debtor_id, $order_data);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => __('Order added to debtor successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Debtor payment
     */
    public function handle_debtor_payment() {
        $this->verify_request();
        
        $debtor_id = isset($_POST['debtor_id']) ? intval($_POST['debtor_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
        $bank_name = isset($_POST['bank_name']) ? sanitize_text_field(wp_unslash($_POST['bank_name'])) : '';
        $transfer_amount = isset($_POST['transfer_amount']) ? floatval($_POST['transfer_amount']) : 0;
        $cash_amount = isset($_POST['cash_amount']) ? floatval($_POST['cash_amount']) : 0;
        $home_calculation = isset($_POST['home_calculation']) ? floatval($_POST['home_calculation']) : 0;
        
        if (!$debtor_id) {
            wp_send_json_error(array('message' => __('Invalid debtor ID', 'chinemerem-foods')));
        }
        
        // Only admin can use home calculation
        if ($home_calculation > 0 && !CFI_Auth::is_cfi_admin()) {
            wp_send_json_error(array('message' => __('Only admin can use home calculation', 'chinemerem-foods')));
        }
        
        $result = CFI_Debtors::add_payment($debtor_id, array(
            'amount' => $amount,
            'payment_method' => $payment_method,
            'bank_name' => $bank_name,
            'transfer_amount' => $transfer_amount,
            'cash_amount' => $cash_amount,
            'home_calculation' => $home_calculation
        ));
        
        if ($result['success']) {
            wp_send_json_success(array('message' => __('Payment recorded successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Get debtor history
     */
    public function handle_get_debtor_history() {
        $this->verify_request();
        
        $debtor_id = isset($_POST['debtor_id']) ? intval($_POST['debtor_id']) : 0;
        
        $history = CFI_Debtors::get_history($debtor_id);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Add expense
     */
    public function handle_add_expense() {
        $this->verify_request();
        
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        
        if (empty($description)) {
            wp_send_json_error(array('message' => __('Expense description is required', 'chinemerem-foods')));
        }
        
        $result = CFI_Expenses::add($description, $amount);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Expense added successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to add expense', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get expenses
     */
    public function handle_get_expenses() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $expenses = CFI_Expenses::get_by_date($date);
        
        wp_send_json_success(array('expenses' => $expenses));
    }
    
    /**
     * Get expense history
     */
    public function handle_get_expense_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        $history = CFI_Expenses::get_history($start_date, $end_date);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Add import
     */
    public function handle_add_import() {
        $this->verify_request();
        
        $imports = isset($_POST['imports']) ? json_decode(stripslashes($_POST['imports']), true) : array();
        
        if (empty($imports)) {
            wp_send_json_error(array('message' => __('Invalid import data', 'chinemerem-foods')));
        }
        
        $result = CFI_Imports::add($imports);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Import record added successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to add import record', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get imports
     */
    public function handle_get_imports() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $imports = CFI_Imports::get_by_date($date);
        
        wp_send_json_success(array('imports' => $imports));
    }
    
    /**
     * Get import history
     */
    public function handle_get_import_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        $history = CFI_Imports::get_history($start_date, $end_date);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Add not supplied record
     */
    public function handle_add_not_supplied() {
        $this->verify_request();
        
        $records = isset($_POST['records']) ? json_decode(stripslashes($_POST['records']), true) : array();
        
        if (empty($records)) {
            wp_send_json_error(array('message' => __('Invalid data', 'chinemerem-foods')));
        }
        
        $result = CFI_Stock::add_not_supplied($records);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Record added successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to add record', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get not supplied records
     */
    public function handle_get_not_supplied() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $records = CFI_Stock::get_not_supplied($date);
        
        wp_send_json_success(array('records' => $records));
    }
    
    /**
     * Mark as supplied
     */
    public function handle_mark_as_supplied() {
        $this->verify_request();
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid record ID', 'chinemerem-foods')));
        }
        
        $result = CFI_Stock::mark_as_supplied($id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Marked as supplied', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update record', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get not supplied history
     */
    public function handle_get_not_supplied_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        $history = CFI_Stock::get_not_supplied_history($start_date, $end_date);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Add supplied today record
     */
    public function handle_add_supplied_today() {
        $this->verify_request();
        
        $records = isset($_POST['records']) ? json_decode(stripslashes($_POST['records']), true) : array();
        
        if (empty($records)) {
            wp_send_json_error(array('message' => __('Invalid data', 'chinemerem-foods')));
        }
        
        $result = CFI_Stock::add_supplied_today($records);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Record added successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to add record', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get supplied today records
     */
    public function handle_get_supplied_today() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $records = CFI_Stock::get_supplied_today($date);
        
        wp_send_json_success(array('records' => $records));
    }
    
    /**
     * Get supplied today history
     */
    public function handle_get_supplied_today_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        $history = CFI_Stock::get_supplied_today_history($start_date, $end_date);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Add cash out
     */
    public function handle_add_cashout() {
        $this->verify_request();
        
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $bank_name = isset($_POST['bank_name']) ? sanitize_text_field(wp_unslash($_POST['bank_name'])) : 'Moniepoint MFB';
        
        if ($amount <= 0) {
            wp_send_json_error(array('message' => __('Invalid amount', 'chinemerem-foods')));
        }
        
        $result = CFI_Financial::add_cashout($amount, $bank_name);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Cash out recorded successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to record cash out', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get cash out records
     */
    public function handle_get_cashout() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $records = CFI_Financial::get_cashout($date);
        
        wp_send_json_success(array('records' => $records));
    }
    
    /**
     * Get financial summary
     */
    public function handle_get_financial_summary() {
        $this->verify_request();
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        $summary = CFI_Financial::get_summary($date);
        
        wp_send_json_success(array('summary' => $summary));
    }
    
    /**
     * Update financial summary
     */
    public function handle_update_financial() {
        $this->verify_request();
        
        $cash_to_bank = isset($_POST['cash_to_bank']) ? floatval($_POST['cash_to_bank']) : 0;
        
        $result = CFI_Financial::update_cash_to_bank($cash_to_bank);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Financial summary updated', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update', 'chinemerem-foods')));
        }
    }
    
    /**
     * Get financial history
     */
    public function handle_get_financial_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        $history = CFI_Financial::get_history($start_date, $end_date);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Get transfer history
     */
    public function handle_get_transfer_history() {
        $this->verify_request();
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        
        $history = CFI_Financial::get_transfer_history($start_date, $end_date, $source);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Reconcile date
     */
    public function handle_reconcile_date() {
        $this->verify_request(true);
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
        
        if (empty($date)) {
            wp_send_json_error(array('message' => __('Invalid date', 'chinemerem-foods')));
        }
        
        $result = CFI_Reconciliation::reconcile($date);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Get reconciliation
     */
    public function handle_get_reconciliation() {
        $this->verify_request();
        
        $month = isset($_POST['month']) ? sanitize_text_field(wp_unslash($_POST['month'])) : current_time('Y-m');
        $records = CFI_Reconciliation::get_month($month);
        
        wp_send_json_success(array('records' => $records));
    }
    
    /**
     * Download backup
     */
    public function handle_download_backup() {
        $this->verify_request(true);
        
        $table = isset($_POST['table']) ? sanitize_text_field(wp_unslash($_POST['table'])) : '';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        $result = CFI_Backup::create_backup($table, $start_date, $end_date);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Backup created successfully', 'chinemerem-foods'),
                'file_url' => $result['file_url']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Upload backup
     */
    public function handle_upload_backup() {
        $this->verify_request(true);
        
        if (!isset($_FILES['backup_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'chinemerem-foods')));
        }
        
        $result = CFI_Backup::restore_backup($_FILES['backup_file']);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => __('Backup restored successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Get backup list
     */
    public function handle_get_backup_list() {
        $this->verify_request(true);
        
        $backups = CFI_Backup::get_list();
        
        wp_send_json_success(array('backups' => $backups));
    }
    
    /**
     * Sync offline data
     */
    public function handle_sync_offline_data() {
        $this->verify_request();
        
        $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : array();
        
        if (empty($data)) {
            wp_send_json_success(array('message' => __('No data to sync', 'chinemerem-foods')));
        }
        
        $results = array();
        
        foreach ($data as $item) {
            $type = sanitize_text_field($item['type'] ?? '');
            $payload = $item['data'] ?? array();
            
            switch ($type) {
                case 'order':
                    $payload = $this->sanitize_order_data($payload);
                    $result = CFI_Orders::submit($payload);
                    $results[] = array('type' => $type, 'success' => $result['success']);
                    break;
                    
                case 'expense':
                    $result = CFI_Expenses::add(
                        sanitize_textarea_field($payload['description'] ?? ''),
                        floatval($payload['amount'] ?? 0)
                    );
                    $results[] = array('type' => $type, 'success' => (bool) $result);
                    break;
                    
                // Add more sync types as needed
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Sync completed', 'chinemerem-foods'),
            'results' => $results
        ));
    }
    
    /**
     * Get users (admin only)
     */
    public function handle_get_users() {
        $this->verify_request(true);
        
        $users = get_users(array(
            'role__in' => array('administrator', 'cfi_admin', 'cfi_staff'),
            'orderby' => 'display_name'
        ));
        
        $user_list = array();
        foreach ($users as $user) {
            $user_list[] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'role' => implode(', ', $user->roles)
            );
        }
        
        wp_send_json_success(array('users' => $user_list));
    }
    
    /**
     * Add user (admin only)
     */
    public function handle_add_user() {
        $this->verify_request(true);
        
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : 'cfi_staff';
        
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('All fields are required', 'chinemerem-foods')));
        }
        
        // Validate role
        if (!in_array($role, array('cfi_staff', 'cfi_admin'))) {
            $role = 'cfi_staff';
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Update user
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $name,
            'role' => $role
        ));
        
        wp_send_json_success(array('message' => __('User added successfully', 'chinemerem-foods')));
    }
    
    /**
     * Update user (admin only)
     */
    public function handle_update_user() {
        $this->verify_request(true);
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user ID', 'chinemerem-foods')));
        }
        
        $user_data = array('ID' => $user_id);
        
        if (!empty($name)) {
            $user_data['display_name'] = $name;
        }
        
        if (!empty($email)) {
            $user_data['user_email'] = $email;
        }
        
        if (!empty($password)) {
            $user_data['user_pass'] = $password;
        }
        
        $result = wp_update_user($user_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Update role if provided
        if (!empty($role) && in_array($role, array('cfi_staff', 'cfi_admin'))) {
            $user = new WP_User($user_id);
            $user->set_role($role);
        }
        
        wp_send_json_success(array('message' => __('User updated successfully', 'chinemerem-foods')));
    }
    
    /**
     * Delete user (super admin only)
     */
    public function handle_delete_user() {
        $this->verify_request(true);
        
        if (!CFI_Auth::is_super_admin()) {
            wp_send_json_error(array('message' => __('Only super admin can delete users', 'chinemerem-foods')));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user ID', 'chinemerem-foods')));
        }
        
        // Prevent deleting self
        if ($user_id === get_current_user_id()) {
            wp_send_json_error(array('message' => __('Cannot delete yourself', 'chinemerem-foods')));
        }
        
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($user_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('User deleted successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete user', 'chinemerem-foods')));
        }
    }
    
    /**
     * Delete history (super admin only)
     */
    public function handle_delete_history() {
        $this->verify_request(true);
        
        if (!CFI_Auth::is_super_admin()) {
            wp_send_json_error(array('message' => __('Only super admin can delete history', 'chinemerem-foods')));
        }
        
        $table = isset($_POST['table']) ? sanitize_text_field(wp_unslash($_POST['table'])) : '';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($table) || !$id) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'chinemerem-foods')));
        }
        
        global $wpdb;
        $table_name = CFI_Database::get_table($table);
        
        $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
        
        if ($result) {
            wp_send_json_success(array('message' => __('Record deleted successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete record', 'chinemerem-foods')));
        }
    }
    
    /**
     * Update history (super admin only)
     */
    public function handle_update_history() {
        $this->verify_request(true);
        
        if (!CFI_Auth::is_super_admin()) {
            wp_send_json_error(array('message' => __('Only super admin can edit history', 'chinemerem-foods')));
        }
        
        $table = isset($_POST['table']) ? sanitize_text_field(wp_unslash($_POST['table'])) : '';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : array();
        
        if (empty($table) || !$id || empty($data)) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'chinemerem-foods')));
        }
        
        global $wpdb;
        $table_name = CFI_Database::get_table($table);
        
        // Sanitize data
        $sanitized_data = array();
        foreach ($data as $key => $value) {
            $sanitized_data[sanitize_key($key)] = is_numeric($value) ? $value : sanitize_text_field($value);
        }
        
        $result = $wpdb->update($table_name, $sanitized_data, array('id' => $id));
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Record updated successfully', 'chinemerem-foods')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update record', 'chinemerem-foods')));
        }
    }
    
    /**
     * Recreate missing pages (admin only)
     */
    public function handle_recreate_pages() {
        $this->verify_request(true);
        
        $created = CFI_Pages::recreate_pages();
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d pages created successfully', 'chinemerem-foods'), $created),
            'created' => $created
        ));
    }
}
