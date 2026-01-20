<?php
/**
 * Debtors Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Debtors {
    
    /**
     * Get all debtors
     */
    public static function get_all($status = 'active') {
        global $wpdb;
        $table = CFI_Database::get_table('debtors');
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s ORDER BY name ASC",
                $status
            )
        );
    }
    
    /**
     * Get single debtor
     */
    public static function get($id) {
        global $wpdb;
        $table = CFI_Database::get_table('debtors');
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)
        );
    }
    
    /**
     * Add new debtor (admin only)
     */
    public static function add($name, $phone = '', $email = '', $address = '') {
        global $wpdb;
        $table = CFI_Database::get_table('debtors');
        
        $result = $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'total_debt' => 0,
                'status' => 'active',
                'created_by' => get_current_user_id(),
            ),
            array('%s', '%s', '%s', '%s', '%f', '%s', '%d')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update debtor (admin only)
     */
    public static function update($id, $name, $phone = '', $email = '', $address = '') {
        global $wpdb;
        $table = CFI_Database::get_table('debtors');
        
        return $wpdb->update(
            $table,
            array(
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Delete debtor (soft delete)
     */
    public static function delete($id) {
        global $wpdb;
        $table = CFI_Database::get_table('debtors');
        
        return $wpdb->update(
            $table,
            array('status' => 'deleted'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Add order for debtor
     */
    public static function add_order($debtor_id, $order_data) {
        global $wpdb;
        
        // Get debtor
        $debtor = self::get($debtor_id);
        if (!$debtor) {
            return array('success' => false, 'message' => __('Debtor not found', 'chinemerem-foods'));
        }
        
        // Submit order
        $order_data['order_type'] = 'credit';
        $order_data['debtor_id'] = $debtor_id;
        $order_data['payment_method'] = 'credit';
        $order_data['transfer_amount'] = 0;
        $order_data['cash_amount'] = 0;
        
        $result = CFI_Orders::submit($order_data);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Update debtor balance
        $balance_before = $debtor->total_debt;
        $new_balance = $balance_before + $order_data['grand_total'];
        
        $table_debtors = CFI_Database::get_table('debtors');
        $wpdb->update(
            $table_debtors,
            array('total_debt' => $new_balance),
            array('id' => $debtor_id),
            array('%f'),
            array('%d')
        );
        
        // Record transaction
        $table_trans = CFI_Database::get_table('debtor_transactions');
        $wpdb->insert(
            $table_trans,
            array(
                'debtor_id' => $debtor_id,
                'transaction_type' => 'order',
                'order_id' => $result['order_id'],
                'amount' => $order_data['grand_total'],
                'balance_before' => $balance_before,
                'balance_after' => $new_balance,
                'description' => __('New order placed', 'chinemerem-foods'),
                'staff_id' => get_current_user_id(),
                'transaction_date' => current_time('Y-m-d'),
                'transaction_time' => current_time('H:i:s'),
            ),
            array('%d', '%s', '%d', '%f', '%f', '%f', '%s', '%d', '%s', '%s')
        );
        
        return array('success' => true, 'new_balance' => $new_balance);
    }
    
    /**
     * Add payment for debtor
     */
    public static function add_payment($debtor_id, $payment_data) {
        global $wpdb;
        
        // Get debtor
        $debtor = self::get($debtor_id);
        if (!$debtor) {
            return array('success' => false, 'message' => __('Debtor not found', 'chinemerem-foods'));
        }
        
        $balance_before = $debtor->total_debt;
        $total_payment = floatval($payment_data['transfer_amount']) + 
                        floatval($payment_data['cash_amount']) + 
                        floatval($payment_data['home_calculation']);
        
        if ($total_payment <= 0) {
            return array('success' => false, 'message' => __('Invalid payment amount', 'chinemerem-foods'));
        }
        
        if ($total_payment > $balance_before) {
            return array('success' => false, 'message' => __('Payment exceeds debt balance', 'chinemerem-foods'));
        }
        
        $new_balance = $balance_before - $total_payment;
        
        // Update debtor balance
        $table_debtors = CFI_Database::get_table('debtors');
        $wpdb->update(
            $table_debtors,
            array('total_debt' => $new_balance),
            array('id' => $debtor_id),
            array('%f'),
            array('%d')
        );
        
        // Record transaction
        $table_trans = CFI_Database::get_table('debtor_transactions');
        $wpdb->insert(
            $table_trans,
            array(
                'debtor_id' => $debtor_id,
                'transaction_type' => 'payment',
                'amount' => $total_payment,
                'payment_method' => $payment_data['payment_method'],
                'bank_name' => $payment_data['bank_name'],
                'transfer_amount' => $payment_data['transfer_amount'],
                'cash_amount' => $payment_data['cash_amount'],
                'home_calculation_amount' => $payment_data['home_calculation'],
                'balance_before' => $balance_before,
                'balance_after' => $new_balance,
                'description' => __('Debt payment received', 'chinemerem-foods'),
                'staff_id' => get_current_user_id(),
                'transaction_date' => current_time('Y-m-d'),
                'transaction_time' => current_time('H:i:s'),
            ),
            array('%d', '%s', '%f', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s')
        );
        
        // Record transfer if applicable
        if ($payment_data['transfer_amount'] > 0) {
            CFI_Orders::record_transfer(
                $debtor_id, 
                'debtor', 
                $payment_data['transfer_amount'], 
                $payment_data['bank_name'], 
                get_current_user_id(),
                $debtor->name
            );
        }
        
        // Update financial summary
        CFI_Financial::update_from_debtor_payment($payment_data, current_time('Y-m-d'));
        
        return array('success' => true, 'new_balance' => $new_balance);
    }
    
    /**
     * Get debtor transaction history
     */
    public static function get_history($debtor_id = 0) {
        global $wpdb;
        $table = CFI_Database::get_table('debtor_transactions');
        $table_debtors = CFI_Database::get_table('debtors');
        
        $where = '1=1';
        $params = array();
        
        if ($debtor_id) {
            $where .= ' AND dt.debtor_id = %d';
            $params[] = $debtor_id;
        }
        
        $query = "SELECT dt.*, d.name as debtor_name, u.display_name as staff_name 
                  FROM $table dt 
                  JOIN $table_debtors d ON dt.debtor_id = d.id 
                  LEFT JOIN {$wpdb->users} u ON dt.staff_id = u.ID 
                  WHERE $where 
                  ORDER BY dt.transaction_date DESC, dt.transaction_time DESC";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }
}
