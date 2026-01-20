<?php
/**
 * Expenses Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Expenses {
    
    /**
     * Add expense
     */
    public static function add($description, $amount) {
        global $wpdb;
        $table = CFI_Database::get_table('expenses');
        
        $date = current_time('Y-m-d');
        $time = current_time('H:i:s');
        $staff_id = get_current_user_id();
        
        $result = $wpdb->insert(
            $table,
            array(
                'description' => $description,
                'amount' => $amount,
                'expense_date' => $date,
                'expense_time' => $time,
                'staff_id' => $staff_id,
            ),
            array('%s', '%f', '%s', '%s', '%d')
        );
        
        if ($result) {
            // Update financial summary
            CFI_Financial::update_expenses($date);
        }
        
        return $result;
    }
    
    /**
     * Get expenses by date
     */
    public static function get_by_date($date) {
        global $wpdb;
        $table = CFI_Database::get_table('expenses');
        
        $expenses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, u.display_name as staff_name 
                FROM $table e 
                LEFT JOIN {$wpdb->users} u ON e.staff_id = u.ID 
                WHERE e.expense_date = %s 
                ORDER BY e.expense_time DESC",
                $date
            )
        );
        
        // Calculate total
        $total = 0;
        foreach ($expenses as $expense) {
            $total += $expense->amount;
        }
        
        return array(
            'expenses' => $expenses,
            'total' => $total
        );
    }
    
    /**
     * Get total expenses for date
     */
    public static function get_total($date) {
        global $wpdb;
        $table = CFI_Database::get_table('expenses');
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE expense_date = %s",
                $date
            )
        );
    }
    
    /**
     * Get expense history
     */
    public static function get_history($start_date = '', $end_date = '') {
        global $wpdb;
        $table = CFI_Database::get_table('expenses');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'e.expense_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'e.expense_date <= %s';
            $params[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT e.*, u.display_name as staff_name 
                  FROM $table e 
                  LEFT JOIN {$wpdb->users} u ON e.staff_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY e.expense_date DESC, e.expense_time DESC";
        
        $expenses = $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
        
        // Group by date
        $grouped = array();
        foreach ($expenses as $expense) {
            $date = $expense->expense_date;
            if (!isset($grouped[$date])) {
                $grouped[$date] = array(
                    'date' => $date,
                    'expenses' => array(),
                    'total' => 0
                );
            }
            $grouped[$date]['expenses'][] = $expense;
            $grouped[$date]['total'] += $expense->amount;
        }
        
        return array_values($grouped);
    }
    
    /**
     * Update expense
     */
    public static function update($id, $description, $amount) {
        global $wpdb;
        $table = CFI_Database::get_table('expenses');
        
        $result = $wpdb->update(
            $table,
            array(
                'description' => $description,
                'amount' => $amount,
            ),
            array('id' => $id),
            array('%s', '%f'),
            array('%d')
        );
        
        if ($result !== false) {
            // Get the expense date and update financial summary
            $expense = $wpdb->get_row(
                $wpdb->prepare("SELECT expense_date FROM $table WHERE id = %d", $id)
            );
            if ($expense) {
                CFI_Financial::update_expenses($expense->expense_date);
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Delete expense
     */
    public static function delete($id) {
        global $wpdb;
        $table = CFI_Database::get_table('expenses');
        
        // Get the expense date first
        $expense = $wpdb->get_row(
            $wpdb->prepare("SELECT expense_date FROM $table WHERE id = %d", $id)
        );
        
        $result = $wpdb->delete($table, array('id' => $id), array('%d'));
        
        if ($result && $expense) {
            // Update financial summary
            CFI_Financial::update_expenses($expense->expense_date);
        }
        
        return $result;
    }
}
