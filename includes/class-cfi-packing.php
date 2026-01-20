<?php
/**
 * Packing Store Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Packing {
    
    /**
     * Initialize packing record for a product
     */
    public static function initialize_product($product_id, $date = null) {
        global $wpdb;
        $table = CFI_Database::get_table('packing_store');
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        // Check if already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $date
            )
        );
        
        if ($existing) {
            return $existing;
        }
        
        // Get previous day's closing and balance as today's opening
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $prev = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT closing, balance_in_packing FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $yesterday
            )
        );
        
        $opening = $prev ? $prev->closing : 0;
        $balance = $prev ? $prev->balance_in_packing : 0;
        
        $wpdb->insert(
            $table,
            array(
                'product_id' => $product_id,
                'record_date' => $date,
                'opening' => $opening,
                'to_packing' => 0,
                'from_packing' => 0,
                'balance_in_packing' => $balance,
                'from_sales' => 0,
                'to_sales' => 0,
                'closing' => $opening,
                'balance_remark' => '',
            ),
            array('%d', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get packing records by date
     */
    public static function get_by_date($date) {
        global $wpdb;
        $table_packing = CFI_Database::get_table('packing_store');
        $table_products = CFI_Database::get_table('products');
        
        // Ensure all products have packing records for this date
        $products = CFI_Products::get_all();
        foreach ($products as $product) {
            self::initialize_product($product->id, $date);
        }
        
        $query = $wpdb->prepare(
            "SELECT ps.*, p.name as product_name, p.price 
            FROM $table_packing ps 
            JOIN $table_products p ON ps.product_id = p.id 
            WHERE ps.record_date = %s AND p.status = 'active'
            ORDER BY p.name",
            $date
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update packing records
     */
    public static function update($data) {
        global $wpdb;
        $table = CFI_Database::get_table('packing_store');
        $table_history = CFI_Database::get_table('packing_history');
        
        $staff_id = get_current_user_id();
        $date = current_time('Y-m-d');
        
        foreach ($data as $item) {
            $product_id = intval($item['product_id']);
            $to_packing = floatval($item['to_packing'] ?? 0);
            $from_packing = floatval($item['from_packing'] ?? 0);
            $balance = floatval($item['balance_in_packing'] ?? 0);
            $to_sales = floatval($item['to_sales'] ?? 0);
            $remark = sanitize_textarea_field($item['balance_remark'] ?? '');
            
            // Get current record
            $current = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE product_id = %d AND record_date = %s",
                    $product_id,
                    $date
                )
            );
            
            if (!$current) {
                self::initialize_product($product_id, $date);
                continue;
            }
            
            // Calculate closing
            // closing = opening - to_packing + from_packing + from_sales - to_sales
            $closing = $current->opening - $to_packing + $from_packing + $current->from_sales - $to_sales;
            
            // Record history for changes
            $fields_to_check = array(
                'to_packing' => $to_packing,
                'from_packing' => $from_packing,
                'balance_in_packing' => $balance,
                'to_sales' => $to_sales,
                'balance_remark' => $remark,
            );
            
            foreach ($fields_to_check as $field => $new_value) {
                $old_value = $current->$field;
                if ($new_value != $old_value) {
                    $wpdb->insert(
                        $table_history,
                        array(
                            'packing_id' => $current->id,
                            'product_id' => $product_id,
                            'record_date' => $date,
                            'field_name' => $field,
                            'old_value' => $old_value,
                            'new_value' => $new_value,
                            'staff_id' => $staff_id,
                        ),
                        array('%d', '%d', '%s', '%s', '%s', '%s', '%d')
                    );
                }
            }
            
            // Update packing record
            $wpdb->update(
                $table,
                array(
                    'to_packing' => $to_packing,
                    'from_packing' => $from_packing,
                    'balance_in_packing' => $balance,
                    'to_sales' => $to_sales,
                    'closing' => $closing,
                    'balance_remark' => $remark,
                    'staff_id' => $staff_id,
                ),
                array('id' => $current->id),
                array('%f', '%f', '%f', '%f', '%f', '%s', '%d'),
                array('%d')
            );
            
            // Update stock from_packing_store if to_sales changed
            if ($to_sales != $current->to_sales) {
                $diff = $to_sales - $current->to_sales;
                CFI_Stock::update_from_packing($product_id, $diff, $date);
            }
        }
        
        return true;
    }
    
    /**
     * Update from_sales (called from stock when to_packing_store is updated)
     */
    public static function update_from_sales($product_id, $value, $date) {
        global $wpdb;
        $table = CFI_Database::get_table('packing_store');
        
        self::initialize_product($product_id, $date);
        
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $date
            )
        );
        
        $closing = $current->opening - $current->to_packing + $current->from_packing + $value - $current->to_sales;
        
        $wpdb->update(
            $table,
            array(
                'from_sales' => $value,
                'closing' => $closing,
            ),
            array('id' => $current->id),
            array('%f', '%f'),
            array('%d')
        );
    }
    
    /**
     * Get packing history
     */
    public static function get_history($start_date = '', $end_date = '') {
        global $wpdb;
        $table = CFI_Database::get_table('packing_store');
        $table_products = CFI_Database::get_table('products');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'ps.record_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'ps.record_date <= %s';
            $params[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT ps.*, p.name as product_name, u.display_name as staff_name 
                  FROM $table ps 
                  JOIN $table_products p ON ps.product_id = p.id 
                  LEFT JOIN {$wpdb->users} u ON ps.staff_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY ps.record_date DESC, p.name";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }
    
    /**
     * Daily reset - Carry forward closing and balance to next day
     */
    public static function daily_reset() {
        global $wpdb;
        $table = CFI_Database::get_table('packing_store');
        
        $today = current_time('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
        
        $products = CFI_Products::get_all();
        
        foreach ($products as $product) {
            $current = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT closing, balance_in_packing FROM $table WHERE product_id = %d AND record_date = %s",
                    $product->id,
                    $today
                )
            );
            
            self::initialize_product($product->id, $tomorrow);
            
            if ($current) {
                $wpdb->update(
                    $table,
                    array(
                        'opening' => $current->closing,
                        'closing' => $current->closing,
                        'balance_in_packing' => $current->balance_in_packing,
                    ),
                    array('product_id' => $product->id, 'record_date' => $tomorrow),
                    array('%f', '%f', '%f'),
                    array('%d', '%s')
                );
            }
        }
    }
}
