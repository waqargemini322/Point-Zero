<?php
/**
 * Database Management Class
 * Handles all database operations with optimization for Cloudways hosting
 */

defined('ABSPATH') || exit;

class L2I_Database {
    
    private static $instance = null;
    private $wpdb;
    
    // Table names
    private $credits_table;
    private $credit_history_table;
    private $membership_logs_table;
    private $zoom_meetings_table;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Initialize table names
        $this->credits_table = $wpdb->prefix . 'l2i_user_credits';
        $this->credit_history_table = $wpdb->prefix . 'l2i_credit_history';
        $this->membership_logs_table = $wpdb->prefix . 'l2i_membership_logs';
        $this->zoom_meetings_table = $wpdb->prefix . 'l2i_zoom_meetings';
    }
    
    /**
     * Create all required tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // User credits table
        $credits_table = $wpdb->prefix . 'l2i_user_credits';
        $sql_credits = "CREATE TABLE $credits_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            membership_type varchar(50) NOT NULL,
            bid_credits int(11) DEFAULT 0,
            connection_credits int(11) DEFAULT 0,
            zoom_invites int(11) DEFAULT 0,
            renewal_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY membership_type (membership_type),
            KEY renewal_date (renewal_date)
        ) $charset_collate;";
        
        // Credit history table
        $history_table = $wpdb->prefix . 'l2i_credit_history';
        $sql_history = "CREATE TABLE $history_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            credit_type enum('bid_credits', 'connection_credits', 'zoom_invites') NOT NULL,
            action enum('add', 'subtract', 'reset') NOT NULL,
            amount int(11) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY credit_type (credit_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Membership logs table
        $logs_table = $wpdb->prefix . 'l2i_membership_logs';
        $sql_logs = "CREATE TABLE $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            details longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Zoom meetings table
        $zoom_table = $wpdb->prefix . 'l2i_zoom_meetings';
        $sql_zoom = "CREATE TABLE $zoom_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            meeting_id varchar(100) NOT NULL,
            host_user_id bigint(20) NOT NULL,
            participant_user_id bigint(20) NOT NULL,
            thread_id bigint(20),
            meeting_url text NOT NULL,
            join_url text NOT NULL,
            password varchar(50),
            start_time datetime NOT NULL,
            duration int(11) DEFAULT 60,
            status enum('scheduled', 'started', 'ended', 'cancelled') DEFAULT 'scheduled',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY meeting_id (meeting_id),
            KEY host_user_id (host_user_id),
            KEY participant_user_id (participant_user_id),
            KEY thread_id (thread_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_credits);
        dbDelta($sql_history);
        dbDelta($sql_logs);
        dbDelta($sql_zoom);
        
        // Add indexes for better performance
        self::add_performance_indexes();
    }
    
    /**
     * Add performance indexes
     */
    private static function add_performance_indexes() {
        global $wpdb;
        
        // Add composite indexes for common queries
        $wpdb->query("ALTER TABLE {$wpdb->prefix}l2i_credit_history 
                     ADD INDEX user_credit_date (user_id, credit_type, created_at)");
        
        $wpdb->query("ALTER TABLE {$wpdb->prefix}l2i_zoom_meetings 
                     ADD INDEX host_participant (host_user_id, participant_user_id)");
    }
    
    /**
     * Get user credits with caching
     */
    public function get_user_credits($user_id) {
        $cache_key = 'l2i_user_credits_' . $user_id;
        $credits = wp_cache_get($cache_key, 'l2i_membership');
        
        if (false === $credits) {
            $credits = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->credits_table} WHERE user_id = %d",
                    $user_id
                ),
                ARRAY_A
            );
            
            if (!$credits) {
                // Initialize credits for new user
                $credits = $this->initialize_user_credits($user_id);
            }
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $credits, 'l2i_membership', 300);
        }
        
        return $credits;
    }
    
    /**
     * Initialize credits for a new user
     */
    private function initialize_user_credits($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $membership_type = $this->get_user_membership_type($user);
        $default_credits = $this->get_default_credits_for_membership($membership_type);
        
        $credits_data = array(
            'user_id' => $user_id,
            'membership_type' => $membership_type,
            'bid_credits' => $default_credits['bid_credits'],
            'connection_credits' => $default_credits['connection_credits'],
            'zoom_invites' => $default_credits['zoom_invites'],
            'renewal_date' => $this->calculate_renewal_date($membership_type),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert($this->credits_table, $credits_data);
        
        if ($result) {
            // Clear cache
            wp_cache_delete('l2i_user_credits_' . $user_id, 'l2i_membership');
            return $credits_data;
        }
        
        return false;
    }
    
    /**
     * Update user credits with transaction safety
     */
    public function update_user_credits($user_id, $credit_type, $amount, $action = 'subtract', $description = '') {
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Lock the row for update
            $current_credits = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->credits_table} WHERE user_id = %d FOR UPDATE",
                    $user_id
                ),
                ARRAY_A
            );
            
            if (!$current_credits) {
                throw new Exception('User credits not found');
            }
            
            // Calculate new amount
            $current_amount = (int) $current_credits[$credit_type];
            
            switch ($action) {
                case 'add':
                    $new_amount = $current_amount + $amount;
                    break;
                case 'subtract':
                    $new_amount = max(0, $current_amount - $amount);
                    break;
                case 'set':
                    $new_amount = $amount;
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            
            // Update credits
            $update_result = $this->wpdb->update(
                $this->credits_table,
                array(
                    $credit_type => $new_amount,
                    'updated_at' => current_time('mysql')
                ),
                array('user_id' => $user_id),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($update_result === false) {
                throw new Exception('Failed to update credits');
            }
            
            // Log the transaction
            $this->log_credit_transaction($user_id, $credit_type, $action, $amount, $description);
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            
            // Clear cache
            wp_cache_delete('l2i_user_credits_' . $user_id, 'l2i_membership');
            
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->wpdb->query('ROLLBACK');
            error_log('L2I Credits Update Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log credit transaction
     */
    private function log_credit_transaction($user_id, $credit_type, $action, $amount, $description) {
        $this->wpdb->insert(
            $this->credit_history_table,
            array(
                'user_id' => $user_id,
                'credit_type' => $credit_type,
                'action' => $action,
                'amount' => $amount,
                'description' => sanitize_text_field($description),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Get user membership type
     */
    private function get_user_membership_type($user) {
        $roles = $user->roles;
        
        // Priority order for roles
        $role_priority = array(
            'investor_gold_yearly' => 1,
            'investor_gold_monthly' => 2,
            'investor_basic_yearly' => 3,
            'investor_basic_monthly' => 4,
            'freelancer_gold_yearly' => 5,
            'freelancer_gold_monthly' => 6,
            'freelancer_basic_yearly' => 7,
            'freelancer_basic_monthly' => 8,
            'service_provider_gold_yearly' => 9,
            'service_provider_gold_monthly' => 10,
            'service_provider_basic_yearly' => 11,
            'service_provider_basic_monthly' => 12
        );
        
        $user_membership = 'free';
        $highest_priority = 999;
        
        foreach ($roles as $role) {
            if (isset($role_priority[$role]) && $role_priority[$role] < $highest_priority) {
                $user_membership = $role;
                $highest_priority = $role_priority[$role];
            }
        }
        
        return $user_membership;
    }
    
    /**
     * Get default credits for membership type
     */
    private function get_default_credits_for_membership($membership_type) {
        $credit_config = array(
            // Investor tiers
            'investor_basic_monthly' => array('bid_credits' => 0, 'connection_credits' => 0, 'zoom_invites' => 0),
            'investor_basic_yearly' => array('bid_credits' => 5, 'connection_credits' => 2, 'zoom_invites' => 1),
            'investor_gold_monthly' => array('bid_credits' => 20, 'connection_credits' => 10, 'zoom_invites' => 5),
            'investor_gold_yearly' => array('bid_credits' => 250, 'connection_credits' => 120, 'zoom_invites' => 60),
            
            // Freelancer tiers
            'freelancer_basic_monthly' => array('bid_credits' => 0, 'connection_credits' => 0, 'zoom_invites' => 0),
            'freelancer_basic_yearly' => array('bid_credits' => 10, 'connection_credits' => 3, 'zoom_invites' => 2),
            'freelancer_gold_monthly' => array('bid_credits' => 30, 'connection_credits' => 15, 'zoom_invites' => 8),
            'freelancer_gold_yearly' => array('bid_credits' => 360, 'connection_credits' => 180, 'zoom_invites' => 96),
            
            // Service provider tiers
            'service_provider_basic_monthly' => array('bid_credits' => 0, 'connection_credits' => 0, 'zoom_invites' => 0),
            'service_provider_basic_yearly' => array('bid_credits' => 8, 'connection_credits' => 5, 'zoom_invites' => 3),
            'service_provider_gold_monthly' => array('bid_credits' => 25, 'connection_credits' => 20, 'zoom_invites' => 12),
            'service_provider_gold_yearly' => array('bid_credits' => 300, 'connection_credits' => 240, 'zoom_invites' => 144)
        );
        
        return isset($credit_config[$membership_type]) 
            ? $credit_config[$membership_type] 
            : array('bid_credits' => 0, 'connection_credits' => 0, 'zoom_invites' => 0);
    }
    
    /**
     * Calculate renewal date
     */
    private function calculate_renewal_date($membership_type) {
        if (strpos($membership_type, 'yearly') !== false) {
            return date('Y-m-d H:i:s', strtotime('+1 year'));
        } elseif (strpos($membership_type, 'monthly') !== false) {
            return date('Y-m-d H:i:s', strtotime('+1 month'));
        }
        return null;
    }
    
    /**
     * Log membership activity
     */
    public function log_activity($user_id, $action, $details = array()) {
        $this->wpdb->insert(
            $this->membership_logs_table,
            array(
                'user_id' => $user_id,
                'action' => sanitize_text_field($action),
                'details' => wp_json_encode($details),
                'ip_address' => $this->get_user_ip(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
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
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Clean up old logs (called by cron)
     */
    public function cleanup_old_logs() {
        // Delete logs older than 90 days
        $this->wpdb->query(
            "DELETE FROM {$this->membership_logs_table} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        // Delete credit history older than 1 year
        $this->wpdb->query(
            "DELETE FROM {$this->credit_history_table} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
        );
    }
}