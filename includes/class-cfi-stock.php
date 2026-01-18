<?php
/**
 * Stock Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Stock {
    
    /**
     * Initialize stock record for a product
     */
    public static function initialize_product($product_id, $date = null) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        
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
        
        // Get previous day's closing as today's opening
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $opening = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT closing FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $yesterday
            )
        );
        
        $wpdb->insert(
            $table,
            array(
                'product_id' => $product_id,
                'record_date' => $date,
                'opening' => $opening ?: 0,
                'import_qty' => 0,
                'cash_supply' => 0,
                'credit_supply' => 0,
                'not_supplied' => 0,
                'supplied_today' => 0,
                'to_packing_store' => 0,
                'from_packing_store' => 0,
                'closing' => $opening ?: 0,
            ),
            array('%d', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get stock records by date
     */
    public static function get_by_date($date) {
        global $wpdb;
        $table_stock = CFI_Database::get_table('stock');
        $table_products = CFI_Database::get_table('products');
        
        // Ensure all products have stock records for this date
        $products = CFI_Products::get_all();
        foreach ($products as $product) {
            self::initialize_product($product->id, $date);
        }
        
        $query = $wpdb->prepare(
            "SELECT s.*, p.name as product_name, p.price 
            FROM $table_stock s 
            JOIN $table_products p ON s.product_id = p.id 
            WHERE s.record_date = %s AND p.status = 'active'
            ORDER BY p.name",
            $date
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update stock record
     */
    public static function update($data) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        $table_history = CFI_Database::get_table('stock_history');
        
        $staff_id = get_current_user_id();
        $date = current_time('Y-m-d');
        
        foreach ($data as $item) {
            $product_id = intval($item['product_id']);
            $to_packing = floatval($item['to_packing_store'] ?? 0);
            
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
            
            // Calculate new closing
            $closing = $current->opening + $current->import_qty - $current->cash_supply - 
                       $current->credit_supply + $current->not_supplied - $current->supplied_today - 
                       $to_packing + $current->from_packing_store;
            
            // Record history if value changed
            if ($to_packing != $current->to_packing_store) {
                $wpdb->insert(
                    $table_history,
                    array(
                        'stock_id' => $current->id,
                        'product_id' => $product_id,
                        'record_date' => $date,
                        'field_name' => 'to_packing_store',
                        'old_value' => $current->to_packing_store,
                        'new_value' => $to_packing,
                        'staff_id' => $staff_id,
                    ),
                    array('%d', '%d', '%s', '%s', '%f', '%f', '%d')
                );
            }
            
            // Update stock
            $wpdb->update(
                $table,
                array(
                    'to_packing_store' => $to_packing,
                    'closing' => $closing,
                    'staff_id' => $staff_id,
                ),
                array('id' => $current->id),
                array('%f', '%f', '%d'),
                array('%d')
            );
            
            // Update packing store from_sales
            CFI_Packing::update_from_sales($product_id, $to_packing, $date);
        }
        
        return true;
    }
    
    /**
     * Update cash supply from order
     */
    public static function update_cash_supply($product_id, $quantity, $date) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        
        self::initialize_product($product_id, $date);
        
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $date
            )
        );
        
        $new_cash_supply = $current->cash_supply + $quantity;
        $closing = $current->opening + $current->import_qty - $new_cash_supply - 
                   $current->credit_supply + $current->not_supplied - $current->supplied_today - 
                   $current->to_packing_store + $current->from_packing_store;
        
        $wpdb->update(
            $table,
            array(
                'cash_supply' => $new_cash_supply,
                'closing' => $closing,
            ),
            array('id' => $current->id),
            array('%f', '%f'),
            array('%d')
        );
    }
    
    /**
     * Update credit supply from debtor order
     */
    public static function update_credit_supply($product_id, $quantity, $date) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        
        self::initialize_product($product_id, $date);
        
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $date
            )
        );
        
        $new_credit_supply = $current->credit_supply + $quantity;
        $closing = $current->opening + $current->import_qty - $current->cash_supply - 
                   $new_credit_supply + $current->not_supplied - $current->supplied_today - 
                   $current->to_packing_store + $current->from_packing_store;
        
        $wpdb->update(
            $table,
            array(
                'credit_supply' => $new_credit_supply,
                'closing' => $closing,
            ),
            array('id' => $current->id),
            array('%f', '%f'),
            array('%d')
        );
    }
    
    /**
     * Update import quantity
     */
    public static function update_import($product_id, $quantity, $date) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        
        self::initialize_product($product_id, $date);
        
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $date
            )
        );
        
        $new_import = $current->import_qty + $quantity;
        $closing = $current->opening + $new_import - $current->cash_supply - 
                   $current->credit_supply + $current->not_supplied - $current->supplied_today - 
                   $current->to_packing_store + $current->from_packing_store;
        
        $wpdb->update(
            $table,
            array(
                'import_qty' => $new_import,
                'closing' => $closing,
            ),
            array('id' => $current->id),
            array('%f', '%f'),
            array('%d')
        );
    }
    
    /**
     * Update from packing store
     */
    public static function update_from_packing($product_id, $quantity, $date) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        
        self::initialize_product($product_id, $date);
        
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $date
            )
        );
        
        $new_from_packing = $current->from_packing_store + $quantity;
        $closing = $current->opening + $current->import_qty - $current->cash_supply - 
                   $current->credit_supply + $current->not_supplied - $current->supplied_today - 
                   $current->to_packing_store + $new_from_packing;
        
        $wpdb->update(
            $table,
            array(
                'from_packing_store' => $new_from_packing,
                'closing' => $closing,
            ),
            array('id' => $current->id),
            array('%f', '%f'),
            array('%d')
        );
    }
    
    /**
     * Update not supplied quantity
     */
    public static function update_not_supplied_qty($product_id, $quantity, $date) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        
        self::initialize_product($product_id, $date);
        
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $date
            )
        );
        
        $new_not_supplied = $current->not_supplied + $quantity;
        $closing = $current->opening + $current->import_qty - $current->cash_supply - 
                   $current->credit_supply + $new_not_supplied - $current->supplied_today - 
                   $current->to_packing_store + $current->from_packing_store;
        
        $wpdb->update(
            $table,
            array(
                'not_supplied' => $new_not_supplied,
                'closing' => $closing,
            ),
            array('id' => $current->id),
            array('%f', '%f'),
            array('%d')
        );
    }
    
    /**
     * Update supplied today quantity
     */
    public static function update_supplied_today_qty($product_id, $quantity, $date) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        
        self::initialize_product($product_id, $date);
        
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d AND record_date = %s",
                $product_id,
                $date
            )
        );
        
        $new_supplied_today = $current->supplied_today + $quantity;
        $closing = $current->opening + $current->import_qty - $current->cash_supply - 
                   $current->credit_supply + $current->not_supplied - $new_supplied_today - 
                   $current->to_packing_store + $current->from_packing_store;
        
        $wpdb->update(
            $table,
            array(
                'supplied_today' => $new_supplied_today,
                'closing' => $closing,
            ),
            array('id' => $current->id),
            array('%f', '%f'),
            array('%d')
        );
    }
    
    /**
     * Get stock history
     */
    public static function get_history($start_date = '', $end_date = '', $product_id = 0) {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        $table_products = CFI_Database::get_table('products');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 's.record_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 's.record_date <= %s';
            $params[] = $end_date;
        }
        
        if ($product_id) {
            $where[] = 's.product_id = %d';
            $params[] = $product_id;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT s.*, p.name as product_name, u.display_name as staff_name 
                  FROM $table s 
                  JOIN $table_products p ON s.product_id = p.id 
                  LEFT JOIN {$wpdb->users} u ON s.staff_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY s.record_date DESC, p.name";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }
    
    /**
     * Add not supplied record
     */
    public static function add_not_supplied($records) {
        global $wpdb;
        $table = CFI_Database::get_table('not_supplied');
        
        $staff_id = get_current_user_id();
        $date = current_time('Y-m-d');
        $time = current_time('H:i:s');
        
        foreach ($records as $record) {
            $product_id = intval($record['product_id']);
            $quantity = floatval($record['quantity']);
            $customer_name = sanitize_text_field($record['customer_name'] ?? '');
            $remark = sanitize_textarea_field($record['remark'] ?? '');
            
            $wpdb->insert(
                $table,
                array(
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'customer_name' => $customer_name,
                    'remark' => $remark,
                    'is_supplied' => 0,
                    'record_date' => $date,
                    'record_time' => $time,
                    'staff_id' => $staff_id,
                ),
                array('%d', '%f', '%s', '%s', '%d', '%s', '%s', '%d')
            );
            
            // Update stock not_supplied column
            self::update_not_supplied_qty($product_id, $quantity, $date);
        }
        
        return true;
    }
    
    /**
     * Get not supplied records
     */
    public static function get_not_supplied($date) {
        global $wpdb;
        $table = CFI_Database::get_table('not_supplied');
        $table_products = CFI_Database::get_table('products');
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ns.*, p.name as product_name, u.display_name as staff_name 
                FROM $table ns 
                JOIN $table_products p ON ns.product_id = p.id 
                LEFT JOIN {$wpdb->users} u ON ns.staff_id = u.ID 
                WHERE ns.record_date = %s 
                ORDER BY ns.record_time DESC",
                $date
            )
        );
    }
    
    /**
     * Mark as supplied
     */
    public static function mark_as_supplied($id) {
        global $wpdb;
        $table = CFI_Database::get_table('not_supplied');
        
        return $wpdb->update(
            $table,
            array(
                'is_supplied' => 1,
                'supplied_date' => current_time('Y-m-d'),
                'supplied_by' => get_current_user_id(),
            ),
            array('id' => $id),
            array('%d', '%s', '%d'),
            array('%d')
        );
    }
    
    /**
     * Get not supplied history
     */
    public static function get_not_supplied_history($start_date = '', $end_date = '') {
        global $wpdb;
        $table = CFI_Database::get_table('not_supplied');
        $table_products = CFI_Database::get_table('products');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'ns.record_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'ns.record_date <= %s';
            $params[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT ns.*, p.name as product_name, u.display_name as staff_name 
                  FROM $table ns 
                  JOIN $table_products p ON ns.product_id = p.id 
                  LEFT JOIN {$wpdb->users} u ON ns.staff_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY ns.record_date DESC, ns.record_time DESC";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }
    
    /**
     * Add supplied today record
     */
    public static function add_supplied_today($records) {
        global $wpdb;
        $table = CFI_Database::get_table('supplied_today');
        
        $staff_id = get_current_user_id();
        $date = current_time('Y-m-d');
        $time = current_time('H:i:s');
        
        foreach ($records as $record) {
            $product_id = intval($record['product_id']);
            $quantity = floatval($record['quantity']);
            $customer_name = sanitize_text_field($record['customer_name'] ?? '');
            $remark = sanitize_textarea_field($record['remark'] ?? '');
            
            $wpdb->insert(
                $table,
                array(
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'customer_name' => $customer_name,
                    'remark' => $remark,
                    'record_date' => $date,
                    'record_time' => $time,
                    'staff_id' => $staff_id,
                ),
                array('%d', '%f', '%s', '%s', '%s', '%s', '%d')
            );
            
            // Update stock supplied_today column
            self::update_supplied_today_qty($product_id, $quantity, $date);
        }
        
        return true;
    }
    
    /**
     * Get supplied today records
     */
    public static function get_supplied_today($date) {
        global $wpdb;
        $table = CFI_Database::get_table('supplied_today');
        $table_products = CFI_Database::get_table('products');
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT st.*, p.name as product_name, u.display_name as staff_name 
                FROM $table st 
                JOIN $table_products p ON st.product_id = p.id 
                LEFT JOIN {$wpdb->users} u ON st.staff_id = u.ID 
                WHERE st.record_date = %s 
                ORDER BY st.record_time DESC",
                $date
            )
        );
    }
    
    /**
     * Get supplied today history
     */
    public static function get_supplied_today_history($start_date = '', $end_date = '') {
        global $wpdb;
        $table = CFI_Database::get_table('supplied_today');
        $table_products = CFI_Database::get_table('products');
        
        $where = array('1=1');
        $params = array();
        
        if ($start_date) {
            $where[] = 'st.record_date >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'st.record_date <= %s';
            $params[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT st.*, p.name as product_name, u.display_name as staff_name 
                  FROM $table st 
                  JOIN $table_products p ON st.product_id = p.id 
                  LEFT JOIN {$wpdb->users} u ON st.staff_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY st.record_date DESC, st.record_time DESC";
        
        return $wpdb->get_results($params ? $wpdb->prepare($query, $params) : $query);
    }
    
    /**
     * Daily reset - Carry forward closing to next day's opening
     */
    public static function daily_reset() {
        global $wpdb;
        $table = CFI_Database::get_table('stock');
        
        $today = current_time('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
        
        // Get all products
        $products = CFI_Products::get_all();
        
        foreach ($products as $product) {
            // Get today's closing
            $closing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT closing FROM $table WHERE product_id = %d AND record_date = %s",
                    $product->id,
                    $today
                )
            );
            
            // Initialize tomorrow's record with today's closing as opening
            self::initialize_product($product->id, $tomorrow);
            
            if ($closing !== null) {
                $wpdb->update(
                    $table,
                    array('opening' => $closing, 'closing' => $closing),
                    array('product_id' => $product->id, 'record_date' => $tomorrow),
                    array('%f', '%f'),
                    array('%d', '%s')
                );
            }
        }
    }
}
