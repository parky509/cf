<?php
/**
 * Database Handler Class
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFI_Database {
    
    /**
     * Create all database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Products table
        $table_products = $wpdb->prefix . 'cfi_products';
        $sql_products = "CREATE TABLE IF NOT EXISTS $table_products (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            price decimal(15,2) NOT NULL DEFAULT 0.00,
            unit varchar(50) DEFAULT 'unit',
            category varchar(100) DEFAULT '',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY name (name)
        ) $charset_collate;";
        dbDelta($sql_products);
        
        // Orders table
        $table_orders = $wpdb->prefix . 'cfi_orders';
        $sql_orders = "CREATE TABLE IF NOT EXISTS $table_orders (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_number varchar(50) NOT NULL,
            order_type varchar(20) NOT NULL DEFAULT 'cash',
            customer_name varchar(255) DEFAULT '',
            total_quantity decimal(15,2) NOT NULL DEFAULT 0.00,
            total_amount decimal(15,2) NOT NULL DEFAULT 0.00,
            discount_amount decimal(15,2) NOT NULL DEFAULT 0.00,
            grand_total decimal(15,2) NOT NULL DEFAULT 0.00,
            payment_method varchar(50) NOT NULL,
            transfer_amount decimal(15,2) DEFAULT 0.00,
            cash_amount decimal(15,2) DEFAULT 0.00,
            bank_name varchar(100) DEFAULT '',
            debtor_id bigint(20) UNSIGNED DEFAULT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            order_date date NOT NULL,
            order_time time NOT NULL,
            status varchar(20) DEFAULT 'completed',
            sync_status varchar(20) DEFAULT 'synced',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number),
            KEY order_type (order_type),
            KEY order_date (order_date),
            KEY staff_id (staff_id),
            KEY debtor_id (debtor_id)
        ) $charset_collate;";
        dbDelta($sql_orders);
        
        // Add customer_name column if it doesn't exist
        $table = $wpdb->prefix . 'cfi_orders';
        $row = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'customer_name'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `customer_name` varchar(255) DEFAULT '' AFTER `order_type`");
        }
        
        // Order items table
        $table_order_items = $wpdb->prefix . 'cfi_order_items';
        $sql_order_items = "CREATE TABLE IF NOT EXISTS $table_order_items (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            quantity decimal(15,2) NOT NULL DEFAULT 0.00,
            price decimal(15,2) NOT NULL DEFAULT 0.00,
            discount decimal(15,2) NOT NULL DEFAULT 0.00,
            total decimal(15,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta($sql_order_items);
        
        // Stock records table
        $table_stock = $wpdb->prefix . 'cfi_stock';
        $sql_stock = "CREATE TABLE IF NOT EXISTS $table_stock (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            record_date date NOT NULL,
            opening decimal(15,2) NOT NULL DEFAULT 0.00,
            import_qty decimal(15,2) NOT NULL DEFAULT 0.00,
            cash_supply decimal(15,2) NOT NULL DEFAULT 0.00,
            credit_supply decimal(15,2) NOT NULL DEFAULT 0.00,
            not_supplied decimal(15,2) NOT NULL DEFAULT 0.00,
            supplied_today decimal(15,2) NOT NULL DEFAULT 0.00,
            to_packing_store decimal(15,2) NOT NULL DEFAULT 0.00,
            from_packing_store decimal(15,2) NOT NULL DEFAULT 0.00,
            closing decimal(15,2) NOT NULL DEFAULT 0.00,
            staff_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_date (product_id, record_date),
            KEY record_date (record_date)
        ) $charset_collate;";
        dbDelta($sql_stock);
        
        // Stock history table
        $table_stock_history = $wpdb->prefix . 'cfi_stock_history';
        $sql_stock_history = "CREATE TABLE IF NOT EXISTS $table_stock_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            stock_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            record_date date NOT NULL,
            field_name varchar(100) NOT NULL,
            old_value decimal(15,2) DEFAULT 0.00,
            new_value decimal(15,2) DEFAULT 0.00,
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY stock_id (stock_id),
            KEY record_date (record_date)
        ) $charset_collate;";
        dbDelta($sql_stock_history);
        
        // Packing store table
        $table_packing = $wpdb->prefix . 'cfi_packing_store';
        $sql_packing = "CREATE TABLE IF NOT EXISTS $table_packing (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            record_date date NOT NULL,
            opening decimal(15,2) NOT NULL DEFAULT 0.00,
            to_packing decimal(15,2) NOT NULL DEFAULT 0.00,
            from_packing decimal(15,2) NOT NULL DEFAULT 0.00,
            balance_in_packing decimal(15,2) NOT NULL DEFAULT 0.00,
            from_sales decimal(15,2) NOT NULL DEFAULT 0.00,
            to_sales decimal(15,2) NOT NULL DEFAULT 0.00,
            closing decimal(15,2) NOT NULL DEFAULT 0.00,
            balance_remark text DEFAULT '',
            staff_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_date (product_id, record_date),
            KEY record_date (record_date)
        ) $charset_collate;";
        dbDelta($sql_packing);
        
        // Packing store history
        $table_packing_history = $wpdb->prefix . 'cfi_packing_history';
        $sql_packing_history = "CREATE TABLE IF NOT EXISTS $table_packing_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            packing_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            record_date date NOT NULL,
            field_name varchar(100) NOT NULL,
            old_value text DEFAULT '',
            new_value text DEFAULT '',
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY packing_id (packing_id),
            KEY record_date (record_date)
        ) $charset_collate;";
        dbDelta($sql_packing_history);
        
        // Debtors table
        $table_debtors = $wpdb->prefix . 'cfi_debtors';
        $sql_debtors = "CREATE TABLE IF NOT EXISTS $table_debtors (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            phone varchar(50) DEFAULT '',
            email varchar(255) DEFAULT '',
            address text DEFAULT '',
            total_debt decimal(15,2) NOT NULL DEFAULT 0.00,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY name (name)
        ) $charset_collate;";
        dbDelta($sql_debtors);
        
        // Debtor transactions table
        $table_debtor_trans = $wpdb->prefix . 'cfi_debtor_transactions';
        $sql_debtor_trans = "CREATE TABLE IF NOT EXISTS $table_debtor_trans (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            debtor_id bigint(20) UNSIGNED NOT NULL,
            transaction_type varchar(50) NOT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            amount decimal(15,2) NOT NULL DEFAULT 0.00,
            payment_method varchar(50) DEFAULT '',
            bank_name varchar(100) DEFAULT '',
            transfer_amount decimal(15,2) DEFAULT 0.00,
            cash_amount decimal(15,2) DEFAULT 0.00,
            home_calculation_amount decimal(15,2) DEFAULT 0.00,
            balance_before decimal(15,2) NOT NULL DEFAULT 0.00,
            balance_after decimal(15,2) NOT NULL DEFAULT 0.00,
            description text DEFAULT '',
            staff_id bigint(20) UNSIGNED NOT NULL,
            transaction_date date NOT NULL,
            transaction_time time NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY debtor_id (debtor_id),
            KEY transaction_date (transaction_date),
            KEY transaction_type (transaction_type)
        ) $charset_collate;";
        dbDelta($sql_debtor_trans);
        
        // Expenses table
        $table_expenses = $wpdb->prefix . 'cfi_expenses';
        $sql_expenses = "CREATE TABLE IF NOT EXISTS $table_expenses (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            description text NOT NULL,
            amount decimal(15,2) NOT NULL DEFAULT 0.00,
            expense_date date NOT NULL,
            expense_time time NOT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY expense_date (expense_date)
        ) $charset_collate;";
        dbDelta($sql_expenses);
        
        // Import records table
        $table_imports = $wpdb->prefix . 'cfi_imports';
        $sql_imports = "CREATE TABLE IF NOT EXISTS $table_imports (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            quantity decimal(15,2) NOT NULL DEFAULT 0.00,
            sender varchar(255) DEFAULT '',
            driver_name varchar(255) DEFAULT '',
            remark text DEFAULT '',
            import_date date NOT NULL,
            import_time time NOT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY import_date (import_date)
        ) $charset_collate;";
        dbDelta($sql_imports);
        
        // Not supplied records table
        $table_not_supplied = $wpdb->prefix . 'cfi_not_supplied';
        $sql_not_supplied = "CREATE TABLE IF NOT EXISTS $table_not_supplied (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            quantity decimal(15,2) NOT NULL DEFAULT 0.00,
            customer_name varchar(255) DEFAULT '',
            remark text DEFAULT '',
            is_supplied tinyint(1) DEFAULT 0,
            supplied_date date DEFAULT NULL,
            supplied_by bigint(20) UNSIGNED DEFAULT NULL,
            record_date date NOT NULL,
            record_time time NOT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY record_date (record_date),
            KEY is_supplied (is_supplied)
        ) $charset_collate;";
        dbDelta($sql_not_supplied);
        
        // Supplied today records table
        $table_supplied_today = $wpdb->prefix . 'cfi_supplied_today';
        $sql_supplied_today = "CREATE TABLE IF NOT EXISTS $table_supplied_today (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            quantity decimal(15,2) NOT NULL DEFAULT 0.00,
            customer_name varchar(255) DEFAULT '',
            remark text DEFAULT '',
            record_date date NOT NULL,
            record_time time NOT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY record_date (record_date)
        ) $charset_collate;";
        dbDelta($sql_supplied_today);
        
        // Cash out records table
        $table_cashout = $wpdb->prefix . 'cfi_cashout';
        $sql_cashout = "CREATE TABLE IF NOT EXISTS $table_cashout (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            amount decimal(15,2) NOT NULL DEFAULT 0.00,
            bank_name varchar(100) NOT NULL,
            cashout_date date NOT NULL,
            cashout_time time NOT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cashout_date (cashout_date)
        ) $charset_collate;";
        dbDelta($sql_cashout);
        
        // Financial summary table
        $table_financial = $wpdb->prefix . 'cfi_financial_summary';
        $sql_financial = "CREATE TABLE IF NOT EXISTS $table_financial (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            record_date date NOT NULL,
            total_sales decimal(15,2) NOT NULL DEFAULT 0.00,
            transfer_from_orders decimal(15,2) NOT NULL DEFAULT 0.00,
            transfer_from_cashout decimal(15,2) NOT NULL DEFAULT 0.00,
            transfer_from_debtors decimal(15,2) NOT NULL DEFAULT 0.00,
            debtors_cash decimal(15,2) NOT NULL DEFAULT 0.00,
            expenses decimal(15,2) NOT NULL DEFAULT 0.00,
            old_cash decimal(15,2) NOT NULL DEFAULT 0.00,
            cash_to_bank decimal(15,2) NOT NULL DEFAULT 0.00,
            cash_sales decimal(15,2) NOT NULL DEFAULT 0.00,
            cash_left decimal(15,2) NOT NULL DEFAULT 0.00,
            staff_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY record_date (record_date)
        ) $charset_collate;";
        dbDelta($sql_financial);
        
        // Financial history table
        $table_financial_history = $wpdb->prefix . 'cfi_financial_history';
        $sql_financial_history = "CREATE TABLE IF NOT EXISTS $table_financial_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            financial_id bigint(20) UNSIGNED NOT NULL,
            record_date date NOT NULL,
            field_name varchar(100) NOT NULL,
            old_value decimal(15,2) DEFAULT 0.00,
            new_value decimal(15,2) DEFAULT 0.00,
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY financial_id (financial_id),
            KEY record_date (record_date)
        ) $charset_collate;";
        dbDelta($sql_financial_history);
        
        // Transfer history table
        $table_transfers = $wpdb->prefix . 'cfi_transfer_history';
        $sql_transfers = "CREATE TABLE IF NOT EXISTS $table_transfers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source varchar(50) NOT NULL,
            source_id bigint(20) UNSIGNED NOT NULL,
            customer_name varchar(255) DEFAULT '',
            amount decimal(15,2) NOT NULL DEFAULT 0.00,
            bank_name varchar(100) NOT NULL,
            transfer_date date NOT NULL,
            transfer_time time NOT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source),
            KEY transfer_date (transfer_date)
        ) $charset_collate;";
        dbDelta($sql_transfers);
        
        // Add customer_name column to transfers if it doesn't exist
        $transfers_tbl = $wpdb->prefix . 'cfi_transfer_history';
        $row = $wpdb->get_results("SHOW COLUMNS FROM `$transfers_tbl` LIKE 'customer_name'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE `$transfers_tbl` ADD COLUMN `customer_name` varchar(255) DEFAULT '' AFTER `source_id`");
        }
        
        // Reconciliation table - Updated for 3 staff
        $table_reconciliation = $wpdb->prefix . 'cfi_reconciliation';
        $sql_reconciliation = "CREATE TABLE IF NOT EXISTS $table_reconciliation (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reconcile_date date NOT NULL,
            staff1_id bigint(20) UNSIGNED DEFAULT NULL,
            staff1_time datetime DEFAULT NULL,
            staff1_remarks text DEFAULT '',
            staff2_id bigint(20) UNSIGNED DEFAULT NULL,
            staff2_time datetime DEFAULT NULL,
            staff2_remarks text DEFAULT '',
            staff3_id bigint(20) UNSIGNED DEFAULT NULL,
            staff3_time datetime DEFAULT NULL,
            staff3_remarks text DEFAULT '',
            is_complete tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY reconcile_date (reconcile_date)
        ) $charset_collate;";
        dbDelta($sql_reconciliation);
        
        // Add staff3 columns and rename admin to staff if needed
        $recon_table = $wpdb->prefix . 'cfi_reconciliation';
        
        // Check if we need to migrate from admin to staff columns
        $admin1_col = $wpdb->get_results("SHOW COLUMNS FROM `$recon_table` LIKE 'admin1_id'");
        if (!empty($admin1_col)) {
            // Rename admin columns to staff columns
            $wpdb->query("ALTER TABLE `$recon_table` CHANGE COLUMN `admin1_id` `staff1_id` bigint(20) UNSIGNED DEFAULT NULL");
            $wpdb->query("ALTER TABLE `$recon_table` CHANGE COLUMN `admin1_time` `staff1_time` datetime DEFAULT NULL");
            $wpdb->query("ALTER TABLE `$recon_table` CHANGE COLUMN `admin1_remarks` `staff1_remarks` text DEFAULT ''");
            $wpdb->query("ALTER TABLE `$recon_table` CHANGE COLUMN `admin2_id` `staff2_id` bigint(20) UNSIGNED DEFAULT NULL");
            $wpdb->query("ALTER TABLE `$recon_table` CHANGE COLUMN `admin2_time` `staff2_time` datetime DEFAULT NULL");
            $wpdb->query("ALTER TABLE `$recon_table` CHANGE COLUMN `admin2_remarks` `staff2_remarks` text DEFAULT ''");
        }
        
        // Add staff3 columns if they don't exist
        $staff3_col = $wpdb->get_results("SHOW COLUMNS FROM `$recon_table` LIKE 'staff3_id'");
        if (empty($staff3_col)) {
            $wpdb->query("ALTER TABLE `$recon_table` ADD COLUMN `staff3_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `staff2_remarks`");
            $wpdb->query("ALTER TABLE `$recon_table` ADD COLUMN `staff3_time` datetime DEFAULT NULL AFTER `staff3_id`");
            $wpdb->query("ALTER TABLE `$recon_table` ADD COLUMN `staff3_remarks` text DEFAULT '' AFTER `staff3_time`");
            // Reset is_complete for records that had 2 signatures (they need 3rd signature now)
            // Only reset records where staff2_id is set (was complete with 2 admins) but staff3_id is NULL
            $wpdb->query("UPDATE `$recon_table` SET `is_complete` = 0 WHERE `staff2_id` IS NOT NULL AND `staff3_id` IS NULL");
        }
        
        // Reconciliation history table - Updated for 3 staff
        $table_reconciliation_history = $wpdb->prefix . 'cfi_reconciliation_history';
        $sql_reconciliation_history = "CREATE TABLE IF NOT EXISTS $table_reconciliation_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reconcile_date date NOT NULL,
            staff1_id bigint(20) UNSIGNED DEFAULT NULL,
            staff1_time datetime DEFAULT NULL,
            staff1_remarks text DEFAULT '',
            staff2_id bigint(20) UNSIGNED DEFAULT NULL,
            staff2_time datetime DEFAULT NULL,
            staff2_remarks text DEFAULT '',
            staff3_id bigint(20) UNSIGNED DEFAULT NULL,
            staff3_time datetime DEFAULT NULL,
            staff3_remarks text DEFAULT '',
            status varchar(50) DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY reconcile_date (reconcile_date)
        ) $charset_collate;";
        dbDelta($sql_reconciliation_history);
        
        // Add staff3 columns to history table if needed
        $history_table = $wpdb->prefix . 'cfi_reconciliation_history';
        
        // Check if we need to migrate from admin to staff columns
        $admin1_hist = $wpdb->get_results("SHOW COLUMNS FROM `$history_table` LIKE 'admin1_id'");
        if (!empty($admin1_hist)) {
            $wpdb->query("ALTER TABLE `$history_table` CHANGE COLUMN `admin1_id` `staff1_id` bigint(20) UNSIGNED DEFAULT NULL");
            $wpdb->query("ALTER TABLE `$history_table` CHANGE COLUMN `admin1_time` `staff1_time` datetime DEFAULT NULL");
            $wpdb->query("ALTER TABLE `$history_table` CHANGE COLUMN `admin1_remarks` `staff1_remarks` text DEFAULT ''");
            $wpdb->query("ALTER TABLE `$history_table` CHANGE COLUMN `admin2_id` `staff2_id` bigint(20) UNSIGNED DEFAULT NULL");
            $wpdb->query("ALTER TABLE `$history_table` CHANGE COLUMN `admin2_time` `staff2_time` datetime DEFAULT NULL");
            $wpdb->query("ALTER TABLE `$history_table` CHANGE COLUMN `admin2_remarks` `staff2_remarks` text DEFAULT ''");
        }
        
        $staff3_hist = $wpdb->get_results("SHOW COLUMNS FROM `$history_table` LIKE 'staff3_id'");
        if (empty($staff3_hist)) {
            $wpdb->query("ALTER TABLE `$history_table` ADD COLUMN `staff3_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `staff2_remarks`");
            $wpdb->query("ALTER TABLE `$history_table` ADD COLUMN `staff3_time` datetime DEFAULT NULL AFTER `staff3_id`");
            $wpdb->query("ALTER TABLE `$history_table` ADD COLUMN `staff3_remarks` text DEFAULT '' AFTER `staff3_time`");
        }
        
        // Backup log table
        $table_backup = $wpdb->prefix . 'cfi_backup_log';
        $sql_backup = "CREATE TABLE IF NOT EXISTS $table_backup (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            backup_date date NOT NULL,
            backup_file varchar(255) NOT NULL,
            backup_size bigint(20) UNSIGNED DEFAULT 0,
            backup_type varchar(50) DEFAULT 'daily',
            status varchar(20) DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY backup_date (backup_date)
        ) $charset_collate;";
        dbDelta($sql_backup);
        
        // Offline sync queue table
        $table_sync = $wpdb->prefix . 'cfi_sync_queue';
        $sql_sync = "CREATE TABLE IF NOT EXISTS $table_sync (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_data longtext NOT NULL,
            sync_status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            synced_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY sync_status (sync_status),
            KEY entity_type (entity_type)
        ) $charset_collate;";
        dbDelta($sql_sync);
        
        // Update database version
        update_option('cfi_db_version', CFI_VERSION);
    }
    
    /**
     * Get table name with prefix
     */
    public static function get_table($name) {
        global $wpdb;
        return $wpdb->prefix . 'cfi_' . $name;
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            'cfi_products',
            'cfi_orders',
            'cfi_order_items',
            'cfi_stock',
            'cfi_stock_history',
            'cfi_packing_store',
            'cfi_packing_history',
            'cfi_debtors',
            'cfi_debtor_transactions',
            'cfi_expenses',
            'cfi_imports',
            'cfi_not_supplied',
            'cfi_supplied_today',
            'cfi_cashout',
            'cfi_financial_summary',
            'cfi_financial_history',
            'cfi_transfer_history',
            'cfi_reconciliation',
            'cfi_backup_log',
            'cfi_sync_queue',
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        
        delete_option('cfi_db_version');
    }
}
