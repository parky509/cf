<?php
/**
 * Financial Summary Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Financial {
    /**
     * Format analytics display date in site timezone
     */
    private static function format_analytics_display_date($date, DateTimeZone $timezone) {
        $date_object = DateTimeImmutable::createFromFormat('Y-m-d', $date, $timezone);
        if ($date_object instanceof DateTimeImmutable) {
            return wp_date('M j, Y', $date_object->getTimestamp(), $timezone);
        }
        return $date;
    }

    /**
     * Normalize analytics date input
     */
    private static function normalize_analytics_date($date) {
        if (empty($date)) {
            return '';
        }

        $date = sanitize_text_field($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        return $date;
    }

    /**
     * Ensure analytics query results return an object
     */
    private static function ensure_result_object($result) {
        return $result ?: (object) array();
    }
    
    /**
     * Initialize financial record for a date
     * CRITICAL: Properly handles old_cash from previous day
     */
    public static function initialize_date($date) {
        global $wpdb;
        $table = CFI_Database::get_table('financial_summary');
        
        // Flush caches to ensure fresh data
        wp_cache_flush();
        if (method_exists($wpdb, 'flush')) {
            $wpdb->flush();
        }
        
        // Check if already exists using SQL_NO_CACHE
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT SQL_NO_CACHE id FROM $table WHERE record_date = %s", $date)
        );
        
        if ($existing) {
            return $existing;
        }
        
        // Get previous day's cash_left as today's old_cash
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $old_cash = $wpdb->get_var(
            $wpdb->prepare("SELECT SQL_NO_CACHE cash_left FROM $table WHERE record_date = %s", $yesterday)
        );
        $old_cash = floatval($old_cash ?: 0);
        
        $wpdb->insert(
            $table,
            array(
                'record_date' => $date,
                'total_sales' => 0,
                'transfer_from_orders' => 0,
                'transfer_from_cashout' => 0,
                'transfer_from_debtors' => 0,
                'debtors_cash' => 0,
                'expenses' => 0,
                'old_cash' => $old_cash,
                'cash_to_bank' => 0,
                'cash_sales' => 0,
                'cash_left' => $old_cash,
            ),
            array('%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get financial summary for a date
     * ALWAYS recalculates from source data for accuracy
     */
    public static function get_summary($date) {
        global $wpdb;
        $table = CFI_Database::get_table('financial_summary');
        
        // Flush all caches to ensure fresh data
        wp_cache_flush();
        if (method_exists($wpdb, 'flush')) {
            $wpdb->flush();
        }
        
        // Initialize the date (creates record with proper old_cash if doesn't exist)
        self::initialize_date($date);
        
        // Recalculate values from source data
        self::recalculate($date);
        
        // Use SQL_NO_CACHE to bypass MySQL query cache
        return $wpdb->get_row(
            $wpdb->prepare("SELECT SQL_NO_CACHE * FROM $table WHERE record_date = %s", $date)
        );
    }
    
    /**
     * Recalculate financial summary from source data
     * This is the CORE function that calculates all values from source tables
     */
    public static function recalculate($date) {
        global $wpdb;
        $table = CFI_Database::get_table('financial_summary');
        $table_orders = CFI_Database::get_table('orders');
        $table_cashout = CFI_Database::get_table('cashout');
        $table_expenses = CFI_Database::get_table('expenses');
        $table_transactions = CFI_Database::get_table('debtor_transactions');
        
        // Flush caches to ensure fresh data from source tables
        wp_cache_flush();
        if (method_exists($wpdb, 'flush')) {
            $wpdb->flush();
        }
        
        // Get order totals - ONLY cash orders, NOT credit/debtor orders
        // Using separate get_var calls to avoid null object issues
        $total_sales = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SQL_NO_CACHE COALESCE(SUM(grand_total), 0) FROM $table_orders 
            WHERE order_date = %s AND status = 'completed' AND order_type = 'cash'",
            $date
        )) ?: 0);
        
        $transfer_from_orders = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SQL_NO_CACHE COALESCE(SUM(transfer_amount), 0) FROM $table_orders 
            WHERE order_date = %s AND status = 'completed' AND order_type = 'cash'",
            $date
        )) ?: 0);
        
        $cash_sales = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SQL_NO_CACHE COALESCE(SUM(cash_amount), 0) FROM $table_orders 
            WHERE order_date = %s AND status = 'completed' AND order_type = 'cash'",
            $date
        )) ?: 0);
        
        // Get cash out totals
        $cashout_total = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SQL_NO_CACHE COALESCE(SUM(amount), 0) FROM $table_cashout WHERE cashout_date = %s",
            $date
        )) ?: 0);
        
        // Get debtor payments
        $debtors_cash = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SQL_NO_CACHE COALESCE(SUM(cash_amount), 0) FROM $table_transactions 
            WHERE DATE(transaction_date) = %s AND transaction_type = 'payment'",
            $date
        )) ?: 0);
        
        $debtors_transfer = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SQL_NO_CACHE COALESCE(SUM(transfer_amount), 0) FROM $table_transactions 
            WHERE DATE(transaction_date) = %s AND transaction_type = 'payment'",
            $date
        )) ?: 0);
        
        // Get expenses
        $expenses = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SQL_NO_CACHE COALESCE(SUM(amount), 0) FROM $table_expenses WHERE expense_date = %s",
            $date
        )) ?: 0);
        
        // Get current record with SQL_NO_CACHE for old_cash and cash_to_bank (manual entries)
        $current = $wpdb->get_row(
            $wpdb->prepare("SELECT SQL_NO_CACHE * FROM $table WHERE record_date = %s", $date)
        );
        
        $old_cash = floatval($current->old_cash ?? 0);
        $cash_to_bank = floatval($current->cash_to_bank ?? 0);
        
        // Calculate cash left using the formula
        // cash_left = total_sales - transfer_from_orders - transfer_from_cashout + debtors_cash - expenses + old_cash - cash_to_bank
        $cash_left = $total_sales - $transfer_from_orders - $cashout_total + $debtors_cash - $expenses + $old_cash - $cash_to_bank;
        
        $wpdb->update(
            $table,
            array(
                'total_sales' => $total_sales,
                'transfer_from_orders' => $transfer_from_orders,
                'transfer_from_cashout' => $cashout_total,
                'transfer_from_debtors' => $debtors_transfer,
                'debtors_cash' => $debtors_cash,
                'expenses' => $expenses,
                'cash_sales' => $cash_sales,
                'cash_left' => $cash_left,
            ),
            array('record_date' => $date),
            array('%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f'),
            array('%s')
        );
    }
    
    /**
     * Update from order submission
     */
    public static function update_from_order($order_data, $date) {
        self::recalculate($date);
    }
    
    /**
     * Update from debtor payment
     */
    public static function update_from_debtor_payment($payment_data, $date) {
        self::recalculate($date);
    }
    
    /**
     * Update expenses
     */
    public static function update_expenses($date) {
        self::recalculate($date);
    }
    
    /**
     * Update cash to bank
     */
    public static function update_cash_to_bank($amount) {
        global $wpdb;
        $table = CFI_Database::get_table('financial_summary');
        $table_history = CFI_Database::get_table('financial_history');
        
        $date = current_time('Y-m-d');
        self::initialize_date($date);
        
        $current = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE record_date = %s", $date)
        );
        
        // Record history
        if ($amount != $current->cash_to_bank) {
            $wpdb->insert(
                $table_history,
                array(
                    'financial_id' => $current->id,
                    'record_date' => $date,
                    'field_name' => 'cash_to_bank',
                    'old_value' => $current->cash_to_bank,
                    'new_value' => $amount,
                    'staff_id' => get_current_user_id(),
                ),
                array('%d', '%s', '%s', '%f', '%f', '%d')
            );
        }
        
        $wpdb->update(
            $table,
            array('cash_to_bank' => $amount),
            array('record_date' => $date),
            array('%f'),
            array('%s')
        );
        
        self::recalculate($date);
        
        return true;
    }
    
    /**
     * Add cash out record
     */
    public static function add_cashout($amount, $bank_name) {
        global $wpdb;
        $table = CFI_Database::get_table('cashout');
        
        $date = current_time('Y-m-d');
        $time = current_time('H:i:s');
        $staff_id = get_current_user_id();
        
        $result = $wpdb->insert(
            $table,
            array(
                'amount' => $amount,
                'bank_name' => $bank_name,
                'cashout_date' => $date,
                'cashout_time' => $time,
                'staff_id' => $staff_id,
            ),
            array('%f', '%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            // Record transfer
            CFI_Orders::record_transfer($wpdb->insert_id, 'cashout', $amount, $bank_name, $staff_id);
            
            // Update financial summary
            self::recalculate($date);
        }
        
        return $result;
    }
    
    /**
     * Get cash out records for a date
     */
    public static function get_cashout($date) {
        global $wpdb;
        $table = CFI_Database::get_table('cashout');
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, u.display_name as staff_name 
                FROM $table c 
                LEFT JOIN {$wpdb->users} u ON c.staff_id = u.ID 
                WHERE c.cashout_date = %s 
                ORDER BY c.cashout_time DESC",
                $date
            )
        );
    }
    
    /**
     * Get cash out total for a date
     */
    public static function get_cashout_total($date) {
        global $wpdb;
        $table = CFI_Database::get_table('cashout');
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE cashout_date = %s",
                $date
            )
        );
    }
    
    /**
     * Get debtor payment totals for a date
     */
    public static function get_debtor_totals($date) {
        global $wpdb;
        $table = CFI_Database::get_table('debtor_transactions');
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COALESCE(SUM(transfer_amount), 0) as transfer,
                    COALESCE(SUM(cash_amount), 0) as cash
                FROM $table 
                WHERE transaction_date = %s AND transaction_type = 'payment'",
                $date
            )
        );
        
        return array(
            'transfer' => $result->transfer ?: 0,
            'cash' => $result->cash ?: 0
        );
    }
    
    /**
     * Get financial history
     */
    public static function get_history($start_date = '', $end_date = '') {
        global $wpdb;
        $table = CFI_Database::get_table('financial_summary');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'record_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'record_date <= %s';
            $params[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY record_date DESC";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }
    
    /**
     * Get transfer history
     */
    public static function get_transfer_history($start_date = '', $end_date = '', $source = '') {
        global $wpdb;
        $table = CFI_Database::get_table('transfer_history');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'th.transfer_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'th.transfer_date <= %s';
            $params[] = $end_date;
        }
        
        if ($source) {
            $where[] = 'th.source = %s';
            $params[] = $source;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT th.*, u.display_name as staff_name 
                  FROM $table th 
                  LEFT JOIN {$wpdb->users} u ON th.staff_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY th.transfer_date DESC, th.transfer_time DESC";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }

    /**
     * Get analytics date range for a period
     */
    public static function get_analytics_range($period = 'daily') {
        $period = in_array($period, array('daily', 'weekly', 'monthly'), true) ? $period : 'daily';
        $timezone = wp_timezone();
        $timestamp = current_time('timestamp');
        $end_date = wp_date('Y-m-d', $timestamp, $timezone);

        switch ($period) {
            case 'weekly':
                $start_of_week = (int) get_option('start_of_week', 1);
                $day_of_week = (int) wp_date('w', $timestamp, $timezone);
                $days_since_start = ($day_of_week - $start_of_week + 7) % 7;
                $start_timestamp = strtotime("-{$days_since_start} days", $timestamp);
                $start_date = wp_date('Y-m-d', $start_timestamp, $timezone);
                $label = sprintf(__('Week of %s', 'chinemerem-foods'), wp_date('M j, Y', $start_timestamp, $timezone));
                break;
            case 'monthly':
                $start_date = wp_date('Y-m-01', $timestamp, $timezone);
                $label = wp_date('F Y', $timestamp, $timezone);
                break;
            default:
                $start_date = $end_date;
                $label = wp_date('M j, Y', $timestamp, $timezone);
                break;
        }

        $start_display = self::format_analytics_display_date($start_date, $timezone);
        $end_display = self::format_analytics_display_date($end_date, $timezone);

        return array(
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'label' => $label,
            'start_display' => $start_display,
            'end_display' => $end_display,
        );
    }

    /**
     * Get analytics summary for a period
     */
    public static function get_analytics_summary($period = 'daily', $start_date = '', $end_date = '') {
        global $wpdb;

        $range = self::get_analytics_range($period);
        $custom_start = self::normalize_analytics_date($start_date);
        $custom_end = self::normalize_analytics_date($end_date);

        if ($custom_start || $custom_end) {
            $timezone = wp_timezone();
            $range['period'] = 'custom';
            $range['start_date'] = $custom_start ?: $range['start_date'];
            $range['end_date'] = $custom_end ?: $range['end_date'];

            if ($range['end_date'] < $range['start_date']) {
                $swap = $range['start_date'];
                $range['start_date'] = $range['end_date'];
                $range['end_date'] = $swap;
            }

            $range['label'] = __('Custom Range', 'chinemerem-foods');
            $range['start_display'] = self::format_analytics_display_date($range['start_date'], $timezone);
            $range['end_display'] = self::format_analytics_display_date($range['end_date'], $timezone);
        }

        $start_date = $range['start_date'];
        $end_date = $range['end_date'];

        $orders_table = CFI_Database::get_table('orders');
        $transactions_table = CFI_Database::get_table('debtor_transactions');
        $expenses_table = CFI_Database::get_table('expenses');
        $transfers_table = CFI_Database::get_table('transfer_history');
        $cashout_table = CFI_Database::get_table('cashout');

        $orders = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_orders,
                    COALESCE(SUM(grand_total), 0) as total_sales,
                    COALESCE(SUM(CASE WHEN order_type = 'cash' THEN 1 ELSE 0 END), 0) as cash_orders,
                    COALESCE(SUM(CASE WHEN order_type = 'credit' THEN 1 ELSE 0 END), 0) as credit_orders,
                    COALESCE(SUM(CASE WHEN order_type = 'cash' THEN grand_total ELSE 0 END), 0) as cash_sales,
                    COALESCE(SUM(CASE WHEN order_type = 'credit' THEN grand_total ELSE 0 END), 0) as credit_sales,
                    COALESCE(SUM(CASE WHEN order_type = 'cash' THEN transfer_amount ELSE 0 END), 0) as transfer_sales,
                    COALESCE(SUM(CASE WHEN order_type = 'cash' THEN cash_amount ELSE 0 END), 0) as cash_received
                FROM $orders_table
                WHERE order_date BETWEEN %s AND %s AND status = 'completed'",
                $start_date,
                $end_date
            )
        );

        $debtors = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN transaction_type = 'order' THEN amount ELSE 0 END), 0) as orders_total,
                    COALESCE(SUM(CASE WHEN transaction_type = 'order' THEN 1 ELSE 0 END), 0) as orders_count,
                    COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) as payments_total,
                    COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN transfer_amount ELSE 0 END), 0) as payments_transfer,
                    COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN cash_amount ELSE 0 END), 0) as payments_cash,
                    COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN home_calculation_amount ELSE 0 END), 0) as payments_home,
                    COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN 1 ELSE 0 END), 0) as payments_count
                FROM $transactions_table
                WHERE transaction_date BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        $expenses = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_count,
                    COALESCE(SUM(amount), 0) as total_amount
                FROM $expenses_table
                WHERE expense_date BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        $transfers = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_count,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN source = 'order' THEN amount ELSE 0 END), 0) as orders_amount,
                    COALESCE(SUM(CASE WHEN source = 'debtor' THEN amount ELSE 0 END), 0) as debtors_amount,
                    COALESCE(SUM(CASE WHEN source = 'cashout' THEN amount ELSE 0 END), 0) as cashout_amount
                FROM $transfers_table
                WHERE transfer_date BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        $cashout = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_count,
                    COALESCE(SUM(amount), 0) as total_amount
                FROM $cashout_table
                WHERE cashout_date BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        $orders = self::ensure_result_object($orders);
        $debtors = self::ensure_result_object($debtors);
        $expenses = self::ensure_result_object($expenses);
        $transfers = self::ensure_result_object($transfers);
        $cashout = self::ensure_result_object($cashout);

        return array(
            'range' => $range,
            'orders' => array(
                'total_orders' => intval($orders->total_orders ?? 0),
                'cash_orders' => intval($orders->cash_orders ?? 0),
                'credit_orders' => intval($orders->credit_orders ?? 0),
                'total_sales' => floatval($orders->total_sales ?? 0),
                'cash_sales' => floatval($orders->cash_sales ?? 0),
                'credit_sales' => floatval($orders->credit_sales ?? 0),
                'cash_received' => floatval($orders->cash_received ?? 0),
                'transfer_sales' => floatval($orders->transfer_sales ?? 0),
            ),
            'debtors' => array(
                'orders_total' => floatval($debtors->orders_total ?? 0),
                'orders_count' => intval($debtors->orders_count ?? 0),
                'payments_total' => floatval($debtors->payments_total ?? 0),
                'payments_transfer' => floatval($debtors->payments_transfer ?? 0),
                'payments_cash' => floatval($debtors->payments_cash ?? 0),
                'payments_home' => floatval($debtors->payments_home ?? 0),
                'payments_count' => intval($debtors->payments_count ?? 0),
            ),
            'expenses' => array(
                'total_count' => intval($expenses->total_count ?? 0),
                'total_amount' => floatval($expenses->total_amount ?? 0),
            ),
            'transfers' => array(
                'total_count' => intval($transfers->total_count ?? 0),
                'total_amount' => floatval($transfers->total_amount ?? 0),
                'orders_amount' => floatval($transfers->orders_amount ?? 0),
                'debtors_amount' => floatval($transfers->debtors_amount ?? 0),
                'cashout_amount' => floatval($transfers->cashout_amount ?? 0),
            ),
            'cashout' => array(
                'total_count' => intval($cashout->total_count ?? 0),
                'total_amount' => floatval($cashout->total_amount ?? 0),
            ),
        );
    }
    
    /**
     * End of day processing
     */
    public static function end_of_day() {
        $today = current_time('Y-m-d');
        $tomorrow = gmdate('Y-m-d', strtotime($today . ' +1 day'));
        
        // Get today's cash_left
        $summary = self::get_summary($today);
        
        // Initialize tomorrow with today's cash_left as old_cash
        self::initialize_date($tomorrow);
        
        global $wpdb;
        $table = CFI_Database::get_table('financial_summary');
        
        $wpdb->update(
            $table,
            array('old_cash' => $summary->cash_left),
            array('record_date' => $tomorrow),
            array('%f'),
            array('%s')
        );
    }
    
    /**
     * Update daily summary - alias for recalculate
     */
    public static function update_daily_summary($date) {
        self::initialize_date($date);
        self::recalculate($date);
    }
    
    /**
     * Daily reset
     */
    public static function daily_reset() {
        self::end_of_day();
    }
}
