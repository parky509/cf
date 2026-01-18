<?php
/**
 * Orders Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('cfi_format_receipt_time')) {
    function cfi_format_receipt_time($date, $time) {
        $format = strlen($time) > 5 ? 'Y-m-d H:i:s' : 'Y-m-d H:i';
        $date_time = DateTime::createFromFormat($format, trim($date . ' ' . $time), wp_timezone());
        return $date_time ? $date_time->format('g:i A') : $time;
    }
}

class CFI_Orders {
    
    /**
     * Submit new order
     */
    public static function submit($data) {
        global $wpdb;
        
        // Generate unique order number
        $order_number = self::generate_order_number();
        
        // Check for duplicate
        $table_orders = CFI_Database::get_table('orders');
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_orders WHERE order_number = %s",
                $order_number
            )
        );
        
        if ($existing) {
            return array('success' => false, 'message' => __('Duplicate order detected', 'chinemerem-foods'));
        }
        
        $current_date = current_time('Y-m-d');
        $current_time = current_time('H:i:s');
        $staff_id = get_current_user_id();
        
        // Insert order
        $result = $wpdb->insert(
            $table_orders,
            array(
                'order_number' => $order_number,
                'order_type' => $data['order_type'],
                'total_quantity' => $data['total_quantity'],
                'total_amount' => $data['total_amount'],
                'discount_amount' => $data['discount_amount'],
                'grand_total' => $data['grand_total'],
                'payment_method' => $data['payment_method'],
                'transfer_amount' => $data['transfer_amount'],
                'cash_amount' => $data['cash_amount'],
                'bank_name' => $data['bank_name'],
                'debtor_id' => $data['debtor_id'] ?: null,
                'staff_id' => $staff_id,
                'order_date' => $current_date,
                'order_time' => $current_time,
                'status' => 'completed',
                'sync_status' => 'synced',
            ),
            array('%s', '%s', '%f', '%f', '%f', '%f', '%s', '%f', '%f', '%s', '%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return array('success' => false, 'message' => __('Failed to create order', 'chinemerem-foods'));
        }
        
        $order_id = $wpdb->insert_id;
        
        // Insert order items
        $table_items = CFI_Database::get_table('order_items');
        foreach ($data['items'] as $item) {
            $wpdb->insert(
                $table_items,
                array(
                    'order_id' => $order_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'total' => $item['total'],
                ),
                array('%d', '%d', '%f', '%f', '%f', '%f')
            );
            
            // Update stock (cash supply or credit supply)
            if ($data['order_type'] === 'cash') {
                CFI_Stock::update_cash_supply($item['product_id'], $item['quantity'], $current_date);
            } else {
                CFI_Stock::update_credit_supply($item['product_id'], $item['quantity'], $current_date);
            }
        }
        
        // Record transfer history if applicable
        if ($data['transfer_amount'] > 0) {
            self::record_transfer($order_id, 'order', $data['transfer_amount'], $data['bank_name'], $staff_id);
        }
        
        // Update financial summary
        CFI_Financial::update_from_order($data, $current_date);
        
        return array('success' => true, 'order_id' => $order_id);
    }
    
    /**
     * Generate unique order number using cryptographically secure random bytes
     */
    private static function generate_order_number() {
        $prefix = 'CFI';
        $date = current_time('Ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));
        return $prefix . $date . $random;
    }
    
    /**
     * Record transfer payment
     */
    public static function record_transfer($source_id, $source, $amount, $bank_name, $staff_id, $customer_name = '') {
        global $wpdb;
        $table = CFI_Database::get_table('transfer_history');
        
        $wpdb->insert(
            $table,
            array(
                'source' => $source,
                'source_id' => $source_id,
                'customer_name' => $customer_name,
                'amount' => $amount,
                'bank_name' => $bank_name,
                'transfer_date' => current_time('Y-m-d'),
                'transfer_time' => current_time('H:i:s'),
                'staff_id' => $staff_id,
            ),
            array('%s', '%d', '%s', '%f', '%s', '%s', '%s', '%d')
        );
    }
    
    /**
     * Get orders by date
     */
    public static function get_by_date($date) {
        global $wpdb;
        $table = CFI_Database::get_table('orders');
        $table_items = CFI_Database::get_table('order_items');
        $table_products = CFI_Database::get_table('products');
        
        $orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*, u.display_name as staff_name 
                FROM $table o 
                LEFT JOIN {$wpdb->users} u ON o.staff_id = u.ID 
                WHERE o.order_date = %s 
                ORDER BY o.created_at DESC",
                $date
            )
        );
        
        foreach ($orders as &$order) {
            $order->items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT oi.*, p.name as product_name 
                    FROM $table_items oi 
                    LEFT JOIN $table_products p ON oi.product_id = p.id 
                    WHERE oi.order_id = %d",
                    $order->id
                )
            );
        }
        
        return $orders;
    }
    
    /**
     * Get order history
     */
    public static function get_history($start_date = '', $end_date = '', $page = 1, $per_page = 50) {
        global $wpdb;
        $table = CFI_Database::get_table('orders');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'order_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'order_date <= %s';
            $params[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;
        
        $count_query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        $total = $wpdb->get_var($params ? $wpdb->prepare($count_query, $params) : $count_query);
        
        $query = "SELECT o.*, u.display_name as staff_name 
                  FROM $table o 
                  LEFT JOIN {$wpdb->users} u ON o.staff_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY o.order_date DESC, o.order_time DESC 
                  LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $orders = $wpdb->get_results($wpdb->prepare($query, $params));
        
        return array(
            'orders' => $orders,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page,
        );
    }
    
    /**
     * Get product summary for a date
     */
    public static function get_product_summary($date, $type = 'cash') {
        global $wpdb;
        $table_orders = CFI_Database::get_table('orders');
        $table_items = CFI_Database::get_table('order_items');
        $table_products = CFI_Database::get_table('products');
        
        $query = $wpdb->prepare(
            "SELECT p.id, p.name, SUM(oi.quantity) as total_quantity, 
                    GROUP_CONCAT(CONCAT(u.display_name, ' (', o.order_time, ')') SEPARATOR ', ') as staff_info
            FROM $table_items oi
            JOIN $table_orders o ON oi.order_id = o.id
            JOIN $table_products p ON oi.product_id = p.id
            LEFT JOIN {$wpdb->users} u ON o.staff_id = u.ID
            WHERE o.order_date = %s AND o.order_type = %s
            GROUP BY p.id, p.name
            ORDER BY p.name",
            $date,
            $type
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Daily reset - Archive and clear daily data
     */
    public static function daily_reset() {
        // Orders are kept in history, no reset needed
        // Just ensure financial summary is updated
        CFI_Financial::end_of_day();
    }
    
    /**
     * Get daily totals
     * IMPORTANT: Total Sales should ONLY include cash orders (order_type = 'cash'), NOT credit/debtor orders
     * Credit orders are on credit and don't count as actual sales until paid
     */
    public static function get_daily_totals($date) {
        global $wpdb;
        $table = CFI_Database::get_table('orders');
        
        // Flush caches for fresh data
        wp_cache_flush();
        $wpdb->flush();
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SQL_NO_CACHE
                    SUM(CASE WHEN order_type = 'cash' THEN grand_total ELSE 0 END) as total_sales,
                    SUM(CASE WHEN order_type = 'cash' THEN transfer_amount ELSE 0 END) as total_transfer,
                    SUM(CASE WHEN order_type = 'cash' THEN cash_amount ELSE 0 END) as total_cash,
                    SUM(CASE WHEN order_type = 'cash' THEN grand_total ELSE 0 END) as cash_sales,
                    SUM(CASE WHEN order_type = 'credit' THEN grand_total ELSE 0 END) as credit_sales
                FROM $table 
                WHERE order_date = %s AND status = 'completed'",
                $date
            )
        );
    }
}
