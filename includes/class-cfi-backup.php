<?php
/**
 * Backup Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Backup {
    
    /**
     * Create backup for a table
     */
    public static function create_backup($table_name = '', $start_date = '', $end_date = '') {
        global $wpdb;
        
        // Create backup directory
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/cfi-backups';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
            
            // Create .htaccess to protect backups
            $htaccess = $backup_dir . '/.htaccess';
            file_put_contents($htaccess, 'deny from all');
        }
        
        $tables_to_backup = array();
        
        if ($table_name) {
            $tables_to_backup[] = $table_name;
        } else {
            // Backup all CFI tables
            $tables_to_backup = array(
                'products',
                'orders',
                'order_items',
                'stock',
                'stock_history',
                'packing_store',
                'packing_history',
                'debtors',
                'debtor_transactions',
                'expenses',
                'imports',
                'not_supplied',
                'supplied_today',
                'cashout',
                'financial_summary',
                'financial_history',
                'transfer_history',
                'reconciliation',
            );
        }
        
        $backup_data = array();
        
        foreach ($tables_to_backup as $table) {
            $full_table_name = CFI_Database::get_table($table);
            
            $query = "SELECT * FROM $full_table_name";
            
            // Add date filters if applicable
            if ($start_date || $end_date) {
                $date_column = self::get_date_column($table);
                if ($date_column) {
                    $conditions = array();
                    if ($start_date) {
                        $conditions[] = $wpdb->prepare("$date_column >= %s", $start_date);
                    }
                    if ($end_date) {
                        $conditions[] = $wpdb->prepare("$date_column <= %s", $end_date);
                    }
                    if ($conditions) {
                        $query .= " WHERE " . implode(' AND ', $conditions);
                    }
                }
            }
            
            $data = $wpdb->get_results($query, ARRAY_A);
            $backup_data[$table] = $data;
        }
        
        // Create backup file
        $filename = 'cfi-backup-' . current_time('Y-m-d-His') . '.json';
        $filepath = $backup_dir . '/' . $filename;
        
        $backup_content = array(
            'version' => CFI_VERSION,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'tables' => $backup_data,
        );
        
        $result = file_put_contents($filepath, wp_json_encode($backup_content, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            return array('success' => false, 'message' => __('Failed to create backup file', 'chinemerem-foods'));
        }
        
        // Log backup
        $table_log = CFI_Database::get_table('backup_log');
        $wpdb->insert(
            $table_log,
            array(
                'backup_date' => current_time('Y-m-d'),
                'backup_file' => $filename,
                'backup_size' => filesize($filepath),
                'backup_type' => $table_name ? 'table' : 'full',
                'status' => 'completed',
            ),
            array('%s', '%s', '%d', '%s', '%s')
        );
        
        return array(
            'success' => true,
            'file_url' => $upload_dir['baseurl'] . '/cfi-backups/' . $filename,
            'file_path' => $filepath
        );
    }
    
    /**
     * Get date column for a table
     */
    private static function get_date_column($table) {
        $date_columns = array(
            'orders' => 'order_date',
            'stock' => 'record_date',
            'stock_history' => 'record_date',
            'packing_store' => 'record_date',
            'packing_history' => 'record_date',
            'debtor_transactions' => 'transaction_date',
            'expenses' => 'expense_date',
            'imports' => 'import_date',
            'not_supplied' => 'record_date',
            'supplied_today' => 'record_date',
            'cashout' => 'cashout_date',
            'financial_summary' => 'record_date',
            'financial_history' => 'record_date',
            'transfer_history' => 'transfer_date',
            'reconciliation' => 'reconcile_date',
        );
        
        return $date_columns[$table] ?? null;
    }
    
    /**
     * Restore backup from file
     */
    public static function restore_backup($file) {
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return array('success' => false, 'message' => __('Invalid backup file', 'chinemerem-foods'));
        }
        
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['tables'])) {
            return array('success' => false, 'message' => __('Invalid backup format', 'chinemerem-foods'));
        }
        
        global $wpdb;
        
        foreach ($data['tables'] as $table => $rows) {
            $full_table_name = CFI_Database::get_table($table);
            
            // Clear existing data
            $wpdb->query("TRUNCATE TABLE $full_table_name");
            
            // Insert backup data
            foreach ($rows as $row) {
                $wpdb->insert($full_table_name, $row);
            }
        }
        
        return array('success' => true, 'message' => __('Backup restored successfully', 'chinemerem-foods'));
    }
    
    /**
     * Create daily automatic backup
     */
    public static function create_daily_backup() {
        return self::create_backup('', '', '');
    }
    
    /**
     * Get list of available backups
     */
    public static function get_list() {
        global $wpdb;
        $table = CFI_Database::get_table('backup_log');
        
        return $wpdb->get_results(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT 50"
        );
    }
    
    /**
     * Delete old backups (keep last 30 days)
     */
    public static function cleanup_old_backups() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/cfi-backups';
        
        if (!file_exists($backup_dir)) {
            return;
        }
        
        $files = glob($backup_dir . '/cfi-backup-*.json');
        $cutoff = strtotime('-30 days');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
        
        // Clean up database log
        global $wpdb;
        $table = CFI_Database::get_table('backup_log');
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE backup_date < %s",
                date('Y-m-d', $cutoff)
            )
        );
    }
}
