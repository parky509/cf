<?php
/**
 * Reconciliation Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Reconciliation {
    
    /**
     * Reconcile a date (requires 2 admins)
     */
    public static function reconcile($date) {
        global $wpdb;
        $table = CFI_Database::get_table('reconciliation');
        
        $user_id = get_current_user_id();
        
        // Check if user is admin
        if (!CFI_Auth::is_cfi_admin()) {
            return array('success' => false, 'message' => __('Only admins can reconcile', 'chinemerem-foods'));
        }
        
        // Get existing record
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE reconcile_date = %s", $date)
        );
        
        if ($existing) {
            if ($existing->is_complete) {
                return array('success' => false, 'message' => __('This date is already reconciled', 'chinemerem-foods'));
            }
            
            // Check if this is the second admin
            if ($existing->admin1_id == $user_id) {
                return array('success' => false, 'message' => __('You have already signed. Waiting for second admin.', 'chinemerem-foods'));
            }
            
            // Second admin signing
            $wpdb->update(
                $table,
                array(
                    'admin2_id' => $user_id,
                    'admin2_time' => current_time('mysql'),
                    'is_complete' => 1,
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%d'),
                array('%d')
            );
            
            return array('success' => true, 'message' => __('Reconciliation complete!', 'chinemerem-foods'));
        } else {
            // First admin signing
            $wpdb->insert(
                $table,
                array(
                    'reconcile_date' => $date,
                    'admin1_id' => $user_id,
                    'admin1_time' => current_time('mysql'),
                    'is_complete' => 0,
                ),
                array('%s', '%d', '%s', '%d')
            );
            
            return array('success' => true, 'message' => __('First signature recorded. Waiting for second admin.', 'chinemerem-foods'));
        }
    }
    
    /**
     * Get reconciliation status for a month
     */
    public static function get_month($month) {
        global $wpdb;
        $table = CFI_Database::get_table('reconciliation');
        
        $start_date = $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, 
                    u1.display_name as admin1_name, 
                    u2.display_name as admin2_name 
                FROM $table r 
                LEFT JOIN {$wpdb->users} u1 ON r.admin1_id = u1.ID 
                LEFT JOIN {$wpdb->users} u2 ON r.admin2_id = u2.ID 
                WHERE r.reconcile_date BETWEEN %s AND %s 
                ORDER BY r.reconcile_date",
                $start_date,
                $end_date
            )
        );
        
        // Build calendar data
        $calendar = array();
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $record = null;
            
            foreach ($records as $r) {
                if ($r->reconcile_date === $date) {
                    $record = $r;
                    break;
                }
            }
            
            $calendar[] = array(
                'date' => $date,
                'day' => $current->format('j'),
                'is_past' => $date < current_time('Y-m-d'),
                'is_today' => $date === current_time('Y-m-d'),
                'is_reconciled' => $record && $record->is_complete,
                'has_first_signature' => $record && $record->admin1_id && !$record->is_complete,
                'admin1' => $record ? $record->admin1_name : null,
                'admin2' => $record ? $record->admin2_name : null,
            );
            
            $current->modify('+1 day');
        }
        
        return $calendar;
    }
    
    /**
     * Get reconciliation history
     */
    public static function get_history($start_date = '', $end_date = '') {
        global $wpdb;
        $table = CFI_Database::get_table('reconciliation');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'r.reconcile_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'r.reconcile_date <= %s';
            $params[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT r.*, 
                    u1.display_name as admin1_name, 
                    u2.display_name as admin2_name 
                  FROM $table r 
                  LEFT JOIN {$wpdb->users} u1 ON r.admin1_id = u1.ID 
                  LEFT JOIN {$wpdb->users} u2 ON r.admin2_id = u2.ID 
                  WHERE $where_clause 
                  ORDER BY r.reconcile_date DESC";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }
}
