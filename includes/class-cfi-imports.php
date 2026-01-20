<?php
/**
 * Imports Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Imports {
    
    /**
     * Add import records
     */
    public static function add($imports) {
        global $wpdb;
        $table = CFI_Database::get_table('imports');
        
        $date = current_time('Y-m-d');
        $time = current_time('H:i:s');
        $staff_id = get_current_user_id();
        
        foreach ($imports as $import) {
            $product_id = intval($import['product_id']);
            $quantity = floatval($import['quantity']);
            $sender = sanitize_text_field($import['sender'] ?? '');
            $driver_name = sanitize_text_field($import['driver_name'] ?? '');
            $remark = sanitize_textarea_field($import['remark'] ?? '');
            
            if ($quantity <= 0) {
                continue;
            }

            if ($sender === '' || $driver_name === '') {
                return false;
            }
            
            $wpdb->insert(
                $table,
                array(
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'sender' => $sender,
                    'driver_name' => $driver_name,
                    'remark' => $remark,
                    'import_date' => $date,
                    'import_time' => $time,
                    'staff_id' => $staff_id,
                ),
                array('%d', '%f', '%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            // Update stock import quantity
            CFI_Stock::update_import($product_id, $quantity, $date);
        }
        
        return true;
    }
    
    /**
     * Get imports by date
     */
    public static function get_by_date($date) {
        global $wpdb;
        $table = CFI_Database::get_table('imports');
        $table_products = CFI_Database::get_table('products');
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.*, p.name as product_name, u.display_name as staff_name 
                FROM $table i 
                JOIN $table_products p ON i.product_id = p.id 
                LEFT JOIN {$wpdb->users} u ON i.staff_id = u.ID 
                WHERE i.import_date = %s 
                ORDER BY i.import_time DESC",
                $date
            )
        );
    }
    
    /**
     * Get total imports for a product on a date
     */
    public static function get_product_total($product_id, $date) {
        global $wpdb;
        $table = CFI_Database::get_table('imports');
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM $table WHERE product_id = %d AND import_date = %s",
                $product_id,
                $date
            )
        );
    }
    
    /**
     * Get import history
     */
    public static function get_history($start_date = '', $end_date = '') {
        global $wpdb;
        $table = CFI_Database::get_table('imports');
        $table_products = CFI_Database::get_table('products');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'i.import_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'i.import_date <= %s';
            $params[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT i.*, p.name as product_name, u.display_name as staff_name 
                  FROM $table i 
                  JOIN $table_products p ON i.product_id = p.id 
                  LEFT JOIN {$wpdb->users} u ON i.staff_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY i.import_date DESC, i.import_time DESC";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }
    
    /**
     * Update import record
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = CFI_Database::get_table('imports');
        
        // Get old record
        $old = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)
        );
        
        if (!$old) {
            return false;
        }
        
        $new_quantity = floatval($data['quantity']);
        $quantity_diff = $new_quantity - $old->quantity;
        
        $result = $wpdb->update(
            $table,
            array(
                'quantity' => $new_quantity,
                'sender' => sanitize_text_field($data['sender'] ?? ''),
                'driver_name' => sanitize_text_field($data['driver_name'] ?? ''),
                'remark' => sanitize_textarea_field($data['remark'] ?? ''),
            ),
            array('id' => $id),
            array('%f', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false && $quantity_diff != 0) {
            // Update stock import quantity
            CFI_Stock::update_import($old->product_id, $quantity_diff, $old->import_date);
        }
        
        return $result !== false;
    }
    
    /**
     * Delete import record
     */
    public static function delete($id) {
        global $wpdb;
        $table = CFI_Database::get_table('imports');
        
        // Get record first
        $record = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)
        );
        
        if (!$record) {
            return false;
        }
        
        $result = $wpdb->delete($table, array('id' => $id), array('%d'));
        
        if ($result) {
            // Update stock (subtract the deleted import)
            CFI_Stock::update_import($record->product_id, -$record->quantity, $record->import_date);
        }
        
        return $result;
    }
}
