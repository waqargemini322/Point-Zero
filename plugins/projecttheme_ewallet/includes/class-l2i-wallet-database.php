<?php
/**
 * Wallet Database Management Class
 * Handles all database operations for the e-wallet system
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor is private for singleton pattern
    }
    
    /**
     * Create database tables for wallet system
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Wallet balances table
        $table_name = $wpdb->prefix . 'l2i_wallet_balances';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            balance decimal(15,2) NOT NULL DEFAULT '0.00',
            pending_balance decimal(15,2) NOT NULL DEFAULT '0.00',
            frozen_balance decimal(15,2) NOT NULL DEFAULT '0.00',
            total_deposited decimal(15,2) NOT NULL DEFAULT '0.00',
            total_withdrawn decimal(15,2) NOT NULL DEFAULT '0.00',
            last_transaction_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY idx_balance (balance),
            KEY idx_updated (updated_at),
            CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Transactions table
        $table_name = $wpdb->prefix . 'l2i_wallet_transactions';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            transaction_id varchar(100) NOT NULL,
            type enum('deposit','withdrawal','transfer_in','transfer_out','payment','refund','fee','bonus','penalty','adjustment') NOT NULL,
            status enum('pending','completed','failed','cancelled','processing','on_hold') NOT NULL DEFAULT 'pending',
            amount decimal(15,2) NOT NULL,
            fee decimal(15,2) NOT NULL DEFAULT '0.00',
            net_amount decimal(15,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            balance_before decimal(15,2) NOT NULL DEFAULT '0.00',
            balance_after decimal(15,2) NOT NULL DEFAULT '0.00',
            gateway varchar(50) DEFAULT NULL,
            gateway_transaction_id varchar(255) DEFAULT NULL,
            reference_id varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY transaction_id (transaction_id),
            KEY idx_user_id (user_id),
            KEY idx_type (type),
            KEY idx_status (status),
            KEY idx_gateway (gateway),
            KEY idx_reference (reference_id),
            KEY idx_created (created_at),
            KEY idx_user_type_status (user_id, type, status),
            CONSTRAINT fk_transaction_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Payment methods table
        $table_name = $wpdb->prefix . 'l2i_wallet_payment_methods';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            method_type enum('bank_account','credit_card','paypal','stripe','crypto','other') NOT NULL,
            method_name varchar(100) NOT NULL,
            method_details longtext NOT NULL,
            is_verified tinyint(1) NOT NULL DEFAULT 0,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            last_used_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_method_type (method_type),
            KEY idx_is_verified (is_verified),
            KEY idx_is_default (is_default),
            CONSTRAINT fk_payment_method_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Withdrawal requests table
        $table_name = $wpdb->prefix . 'l2i_wallet_withdrawals';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            transaction_id bigint(20) unsigned NOT NULL,
            payment_method_id bigint(20) unsigned NOT NULL,
            amount decimal(15,2) NOT NULL,
            fee decimal(15,2) NOT NULL DEFAULT '0.00',
            net_amount decimal(15,2) NOT NULL,
            status enum('pending','approved','processing','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
            admin_notes text DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            processed_by bigint(20) unsigned DEFAULT NULL,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_transaction_id (transaction_id),
            KEY idx_payment_method_id (payment_method_id),
            KEY idx_status (status),
            KEY idx_requested (requested_at),
            CONSTRAINT fk_withdrawal_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_withdrawal_transaction FOREIGN KEY (transaction_id) REFERENCES {$wpdb->prefix}l2i_wallet_transactions (id) ON DELETE CASCADE,
            CONSTRAINT fk_withdrawal_payment_method FOREIGN KEY (payment_method_id) REFERENCES {$wpdb->prefix}l2i_wallet_payment_methods (id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Transfers table
        $table_name = $wpdb->prefix . 'l2i_wallet_transfers';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) unsigned NOT NULL,
            receiver_id bigint(20) unsigned NOT NULL,
            sender_transaction_id bigint(20) unsigned NOT NULL,
            receiver_transaction_id bigint(20) unsigned NOT NULL,
            amount decimal(15,2) NOT NULL,
            fee decimal(15,2) NOT NULL DEFAULT '0.00',
            net_amount decimal(15,2) NOT NULL,
            status enum('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
            message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_sender_id (sender_id),
            KEY idx_receiver_id (receiver_id),
            KEY idx_status (status),
            KEY idx_created (created_at),
            CONSTRAINT fk_transfer_sender FOREIGN KEY (sender_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_transfer_receiver FOREIGN KEY (receiver_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_transfer_sender_transaction FOREIGN KEY (sender_transaction_id) REFERENCES {$wpdb->prefix}l2i_wallet_transactions (id) ON DELETE CASCADE,
            CONSTRAINT fk_transfer_receiver_transaction FOREIGN KEY (receiver_transaction_id) REFERENCES {$wpdb->prefix}l2i_wallet_transactions (id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Security logs table
        $table_name = $wpdb->prefix . 'l2i_wallet_security_logs';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            event_type enum('login_attempt','password_change','2fa_setup','2fa_disable','suspicious_activity','failed_transaction','account_locked','kyc_verification') NOT NULL,
            severity enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            description text NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_event_type (event_type),
            KEY idx_severity (severity),
            KEY idx_created (created_at),
            KEY idx_ip_address (ip_address),
            CONSTRAINT fk_security_log_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE SET NULL
        ) $charset_collate;";
        
        // KYC documents table
        $table_name = $wpdb->prefix . 'l2i_wallet_kyc_documents';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            document_type enum('id_card','passport','drivers_license','utility_bill','bank_statement','other') NOT NULL,
            document_number varchar(100) DEFAULT NULL,
            document_path varchar(500) NOT NULL,
            verification_status enum('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
            verified_by bigint(20) unsigned DEFAULT NULL,
            verification_notes text DEFAULT NULL,
            expiry_date date DEFAULT NULL,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            verified_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_document_type (document_type),
            KEY idx_verification_status (verification_status),
            KEY idx_uploaded (uploaded_at),
            CONSTRAINT fk_kyc_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Wallet settings table
        $table_name = $wpdb->prefix . 'l2i_wallet_user_settings';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            setting_name varchar(100) NOT NULL,
            setting_value longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_setting (user_id, setting_name),
            KEY idx_user_id (user_id),
            KEY idx_setting_name (setting_name),
            CONSTRAINT fk_wallet_settings_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Execute the SQL
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create indexes for better performance
        self::create_additional_indexes();
        
        // Migrate existing data if needed
        self::migrate_existing_data();
    }
    
    /**
     * Create additional indexes for performance
     */
    private static function create_additional_indexes() {
        global $wpdb;
        
        // Add composite indexes for common queries
        $indexes = array(
            $wpdb->prefix . 'l2i_wallet_transactions' => array(
                'idx_user_status_created' => 'ADD INDEX idx_user_status_created (user_id, status, created_at)',
                'idx_type_status_created' => 'ADD INDEX idx_type_status_created (type, status, created_at)',
                'idx_gateway_status' => 'ADD INDEX idx_gateway_status (gateway, status)'
            ),
            $wpdb->prefix . 'l2i_wallet_balances' => array(
                'idx_balance_updated' => 'ADD INDEX idx_balance_updated (balance, updated_at)'
            )
        );
        
        foreach ($indexes as $table => $table_indexes) {
            foreach ($table_indexes as $index_name => $index_sql) {
                $wpdb->query("ALTER TABLE $table $index_sql");
            }
        }
    }
    
    /**
     * Migrate existing wallet data from old system
     */
    private static function migrate_existing_data() {
        global $wpdb;
        
        // Check if old wallet data exists
        $old_table = $wpdb->prefix . 'project_withdraw';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$old_table'") === $old_table;
        
        if ($table_exists) {
            // Migrate old withdrawal data
            $old_withdrawals = $wpdb->get_results(
                "SELECT * FROM $old_table ORDER BY id ASC",
                ARRAY_A
            );
            
            foreach ($old_withdrawals as $withdrawal) {
                self::migrate_withdrawal_record($withdrawal);
            }
        }
        
        // Migrate user credits from user meta
        $users_with_credits = $wpdb->get_results(
            "SELECT user_id, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'project_theme_credits' 
             AND meta_value > 0",
            ARRAY_A
        );
        
        foreach ($users_with_credits as $user_credit) {
            self::migrate_user_balance($user_credit['user_id'], $user_credit['meta_value']);
        }
    }
    
    /**
     * Migrate a single withdrawal record
     */
    private static function migrate_withdrawal_record($withdrawal) {
        global $wpdb;
        
        // Create wallet balance record if not exists
        self::ensure_user_wallet_exists($withdrawal['uid']);
        
        // Create transaction record
        $transaction_id = 'MIGRATED_' . $withdrawal['id'] . '_' . time();
        
        $wpdb->insert(
            $wpdb->prefix . 'l2i_wallet_transactions',
            array(
                'user_id' => $withdrawal['uid'],
                'transaction_id' => $transaction_id,
                'type' => 'withdrawal',
                'status' => $withdrawal['done'] == 1 ? 'completed' : 'pending',
                'amount' => $withdrawal['amount'],
                'fee' => 0,
                'net_amount' => $withdrawal['amount'],
                'gateway' => $withdrawal['methods'],
                'description' => 'Migrated withdrawal request',
                'created_at' => $withdrawal['datemade']
            ),
            array('%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s')
        );
    }
    
    /**
     * Migrate user balance
     */
    private static function migrate_user_balance($user_id, $balance) {
        global $wpdb;
        
        // Check if user already has a wallet record
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}l2i_wallet_balances WHERE user_id = %d",
            $user_id
        ));
        
        if (!$existing) {
            $wpdb->insert(
                $wpdb->prefix . 'l2i_wallet_balances',
                array(
                    'user_id' => $user_id,
                    'balance' => $balance,
                    'total_deposited' => $balance
                ),
                array('%d', '%f', '%f')
            );
            
            // Create initial transaction record
            $transaction_id = 'MIGRATED_BALANCE_' . $user_id . '_' . time();
            
            $wpdb->insert(
                $wpdb->prefix . 'l2i_wallet_transactions',
                array(
                    'user_id' => $user_id,
                    'transaction_id' => $transaction_id,
                    'type' => 'deposit',
                    'status' => 'completed',
                    'amount' => $balance,
                    'fee' => 0,
                    'net_amount' => $balance,
                    'balance_before' => 0,
                    'balance_after' => $balance,
                    'description' => 'Migrated balance from old system'
                ),
                array('%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s')
            );
        }
    }
    
    /**
     * Ensure user wallet exists
     */
    public static function ensure_user_wallet_exists($user_id) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}l2i_wallet_balances WHERE user_id = %d",
            $user_id
        ));
        
        if (!$existing) {
            $wpdb->insert(
                $wpdb->prefix . 'l2i_wallet_balances',
                array(
                    'user_id' => $user_id,
                    'balance' => 0,
                    'pending_balance' => 0,
                    'frozen_balance' => 0,
                    'total_deposited' => 0,
                    'total_withdrawn' => 0
                ),
                array('%d', '%f', '%f', '%f', '%f', '%f')
            );
        }
        
        return true;
    }
    
    /**
     * Get user wallet balance
     */
    public function get_user_balance($user_id) {
        global $wpdb;
        
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT balance FROM {$wpdb->prefix}l2i_wallet_balances WHERE user_id = %d",
            $user_id
        ));
        
        return $balance !== null ? (float) $balance : 0.0;
    }
    
    /**
     * Update user balance
     */
    public function update_user_balance($user_id, $new_balance, $transaction_id = null) {
        global $wpdb;
        
        $update_data = array(
            'balance' => $new_balance,
            'updated_at' => current_time('mysql')
        );
        
        $update_format = array('%f', '%s');
        
        if ($transaction_id) {
            $update_data['last_transaction_id'] = $transaction_id;
            $update_format[] = '%d';
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'l2i_wallet_balances',
            $update_data,
            array('user_id' => $user_id),
            $update_format,
            array('%d')
        );
    }
    
    /**
     * Get user transactions
     */
    public function get_user_transactions($user_id, $limit = 20, $offset = 0, $type = null, $status = null) {
        global $wpdb;
        
        $where_conditions = array('user_id = %d');
        $where_values = array($user_id);
        
        if ($type) {
            $where_conditions[] = 'type = %s';
            $where_values[] = $type;
        }
        
        if ($status) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $status;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_transactions 
             WHERE $where_clause 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            array_merge($where_values, array($limit, $offset))
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Create transaction record
     */
    public function create_transaction($data) {
        global $wpdb;
        
        // Generate unique transaction ID if not provided
        if (!isset($data['transaction_id'])) {
            $data['transaction_id'] = 'TXN_' . $data['user_id'] . '_' . time() . '_' . wp_rand(1000, 9999);
        }
        
        // Set default values
        $defaults = array(
            'status' => 'pending',
            'fee' => 0,
            'currency' => 'USD',
            'ip_address' => self::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Calculate net amount if not provided
        if (!isset($data['net_amount'])) {
            $data['net_amount'] = $data['amount'] - $data['fee'];
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'l2i_wallet_transactions',
            $data,
            array('%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update transaction status
     */
    public function update_transaction_status($transaction_id, $status, $additional_data = array()) {
        global $wpdb;
        
        $update_data = array_merge(
            array('status' => $status),
            $additional_data
        );
        
        if ($status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'l2i_wallet_transactions',
            $update_data,
            array('id' => $transaction_id),
            null,
            array('%d')
        );
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Delete old security logs (older than 1 year)
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}l2i_wallet_security_logs 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
        );
        
        // Archive old completed transactions (older than 2 years)
        // This could be moved to a separate archive table in the future
        
        return true;
    }
}