<?php
/**
 * Chat Database Management Class
 * Handles all database operations for the chat system with optimization
 */

defined('ABSPATH') || exit;

class L2I_Chat_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Chat threads table (optimized)
        $threads_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}l2i_chat_threads (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user1_id bigint(20) NOT NULL,
            user2_id bigint(20) NOT NULL,
            last_message_id bigint(20) DEFAULT NULL,
            last_message_time datetime DEFAULT CURRENT_TIMESTAMP,
            user1_last_read bigint(20) DEFAULT 0,
            user2_last_read bigint(20) DEFAULT 0,
            user1_typing_until datetime DEFAULT NULL,
            user2_typing_until datetime DEFAULT NULL,
            status enum('active','archived','blocked') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_users (user1_id, user2_id),
            KEY idx_user1 (user1_id),
            KEY idx_user2 (user2_id),
            KEY idx_last_message_time (last_message_time),
            KEY idx_status (status)
        ) $charset_collate;";
        
        // Chat messages table (optimized)
        $messages_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}l2i_chat_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            thread_id bigint(20) NOT NULL,
            sender_id bigint(20) NOT NULL,
            recipient_id bigint(20) NOT NULL,
            message_content longtext,
            message_type enum('text','file','zoom_invite','system') DEFAULT 'text',
            attachment_id bigint(20) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            is_deleted tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_thread_id (thread_id),
            KEY idx_sender_id (sender_id),
            KEY idx_recipient_id (recipient_id),
            KEY idx_created_at (created_at),
            KEY idx_is_read (is_read),
            KEY idx_thread_created (thread_id, created_at),
            FOREIGN KEY (thread_id) REFERENCES {$wpdb->prefix}l2i_chat_threads(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // User online status table (for performance)
        $online_status_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}l2i_user_online_status (
            user_id bigint(20) NOT NULL,
            last_seen datetime DEFAULT CURRENT_TIMESTAMP,
            is_online tinyint(1) DEFAULT 1,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY idx_last_seen (last_seen),
            KEY idx_is_online (is_online)
        ) $charset_collate;";
        
        // Chat restrictions table (for membership integration)
        $restrictions_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}l2i_chat_restrictions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            restriction_type enum('message_limit','file_upload','zoom_access') NOT NULL,
            restriction_value text,
            applied_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            created_by bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_restriction_type (restriction_type),
            KEY idx_expires_at (expires_at)
        ) $charset_collate;";
        
        // Chat analytics table (for admin insights)
        $analytics_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}l2i_chat_analytics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            total_messages int(11) DEFAULT 0,
            total_threads int(11) DEFAULT 0,
            active_users int(11) DEFAULT 0,
            file_uploads int(11) DEFAULT 0,
            zoom_meetings int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_date (date),
            KEY idx_date (date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($threads_table);
        dbDelta($messages_table);
        dbDelta($online_status_table);
        dbDelta($restrictions_table);
        dbDelta($analytics_table);
        
        // Migrate existing data if needed
        self::migrate_existing_data();
        
        // Update database version
        update_option('l2i_chat_db_version', '2.0.0');
    }
    
    /**
     * Migrate existing data from old tables
     */
    private static function migrate_existing_data() {
        global $wpdb;
        
        // Check if old tables exist
        $old_threads_table = $wpdb->prefix . 'project_pm_threads';
        $old_messages_table = $wpdb->prefix . 'project_pm';
        
        $old_threads_exists = $wpdb->get_var("SHOW TABLES LIKE '$old_threads_table'") == $old_threads_table;
        $old_messages_exists = $wpdb->get_var("SHOW TABLES LIKE '$old_messages_table'") == $old_messages_table;
        
        if (!$old_threads_exists || !$old_messages_exists) {
            return; // No old data to migrate
        }
        
        // Check if migration already done
        if (get_option('l2i_chat_migration_completed')) {
            return;
        }
        
        // Migrate threads
        $wpdb->query("
            INSERT IGNORE INTO {$wpdb->prefix}l2i_chat_threads 
            (id, user1_id, user2_id, last_message_time, created_at, updated_at)
            SELECT 
                id, 
                user1, 
                user2, 
                FROM_UNIXTIME(lastupdate),
                FROM_UNIXTIME(datemade),
                FROM_UNIXTIME(lastupdate)
            FROM $old_threads_table
        ");
        
        // Migrate messages
        $wpdb->query("
            INSERT IGNORE INTO {$wpdb->prefix}l2i_chat_messages 
            (id, thread_id, sender_id, recipient_id, message_content, attachment_id, created_at)
            SELECT 
                id,
                threadid,
                initiator,
                user,
                content,
                CASE WHEN file_attached > 0 THEN file_attached ELSE NULL END,
                FROM_UNIXTIME(datemade)
            FROM $old_messages_table
            WHERE threadid IN (SELECT id FROM {$wpdb->prefix}l2i_chat_threads)
        ");
        
        // Update thread last message info
        $wpdb->query("
            UPDATE {$wpdb->prefix}l2i_chat_threads t
            SET last_message_id = (
                SELECT MAX(id) FROM {$wpdb->prefix}l2i_chat_messages m 
                WHERE m.thread_id = t.id
            ),
            last_message_time = (
                SELECT MAX(created_at) FROM {$wpdb->prefix}l2i_chat_messages m 
                WHERE m.thread_id = t.id
            )
        ");
        
        // Mark migration as completed
        update_option('l2i_chat_migration_completed', true);
    }
    
    /**
     * Get thread between two users
     */
    public function get_thread($user1_id, $user2_id, $create_if_not_exists = false) {
        global $wpdb;
        
        // Ensure consistent ordering
        if ($user1_id > $user2_id) {
            $temp = $user1_id;
            $user1_id = $user2_id;
            $user2_id = $temp;
        }
        
        $thread = wp_cache_get("chat_thread_{$user1_id}_{$user2_id}", 'l2i_chat');
        
        if (false === $thread) {
            $thread = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}l2i_chat_threads 
                 WHERE user1_id = %d AND user2_id = %d AND status = 'active'",
                $user1_id, $user2_id
            ), ARRAY_A);
            
            if ($thread) {
                wp_cache_set("chat_thread_{$user1_id}_{$user2_id}", $thread, 'l2i_chat', 300);
            }
        }
        
        if (!$thread && $create_if_not_exists) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'l2i_chat_threads',
                array(
                    'user1_id' => $user1_id,
                    'user2_id' => $user2_id,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s')
            );
            
            if ($result) {
                $thread_id = $wpdb->insert_id;
                $thread = array(
                    'id' => $thread_id,
                    'user1_id' => $user1_id,
                    'user2_id' => $user2_id,
                    'last_message_id' => null,
                    'last_message_time' => current_time('mysql'),
                    'user1_last_read' => 0,
                    'user2_last_read' => 0,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );
                
                wp_cache_set("chat_thread_{$user1_id}_{$user2_id}", $thread, 'l2i_chat', 300);
                
                // Log thread creation
                do_action('l2i_chat_thread_created', $thread_id, $user1_id, $user2_id);
            }
        }
        
        return $thread;
    }
    
    /**
     * Get user's threads with pagination and caching
     */
    public function get_user_threads($user_id, $limit = 20, $offset = 0, $search = '') {
        global $wpdb;
        
        $cache_key = "user_threads_{$user_id}_{$limit}_{$offset}_" . md5($search);
        $threads = wp_cache_get($cache_key, 'l2i_chat');
        
        if (false === $threads) {
            $search_sql = '';
            $search_params = array($user_id, $user_id);
            
            if (!empty($search)) {
                $search_sql = "AND (u1.display_name LIKE %s OR u2.display_name LIKE %s OR u1.user_login LIKE %s OR u2.user_login LIKE %s)";
                $search_term = '%' . $wpdb->esc_like($search) . '%';
                $search_params = array_merge($search_params, array($search_term, $search_term, $search_term, $search_term));
            }
            
            $search_params[] = $limit;
            $search_params[] = $offset;
            
            $threads = $wpdb->get_results($wpdb->prepare("
                SELECT t.*, 
                       u1.display_name as user1_name,
                       u1.user_login as user1_login,
                       u2.display_name as user2_name,
                       u2.user_login as user2_login,
                       m.message_content as last_message,
                       m.sender_id as last_sender_id,
                       CASE WHEN t.user1_id = %d THEN 
                           CASE WHEN t.user1_last_read < t.last_message_id THEN 1 ELSE 0 END
                       ELSE 
                           CASE WHEN t.user2_last_read < t.last_message_id THEN 1 ELSE 0 END
                       END as has_unread
                FROM {$wpdb->prefix}l2i_chat_threads t
                LEFT JOIN {$wpdb->users} u1 ON t.user1_id = u1.ID
                LEFT JOIN {$wpdb->users} u2 ON t.user2_id = u2.ID
                LEFT JOIN {$wpdb->prefix}l2i_chat_messages m ON t.last_message_id = m.id
                WHERE (t.user1_id = %d OR t.user2_id = %d) 
                AND t.status = 'active'
                $search_sql
                ORDER BY t.last_message_time DESC
                LIMIT %d OFFSET %d
            ", $search_params), ARRAY_A);
            
            wp_cache_set($cache_key, $threads, 'l2i_chat', 180); // Cache for 3 minutes
        }
        
        return $threads;
    }
    
    /**
     * Get messages for a thread with pagination
     */
    public function get_thread_messages($thread_id, $limit = 50, $before_message_id = null) {
        global $wpdb;
        
        $cache_key = "thread_messages_{$thread_id}_{$limit}_" . ($before_message_id ?: 'latest');
        $messages = wp_cache_get($cache_key, 'l2i_chat');
        
        if (false === $messages) {
            $where_sql = '';
            $params = array($thread_id);
            
            if ($before_message_id) {
                $where_sql = 'AND id < %d';
                $params[] = $before_message_id;
            }
            
            $params[] = $limit;
            
            $messages = $wpdb->get_results($wpdb->prepare("
                SELECT m.*, 
                       u.display_name as sender_name,
                       u.user_login as sender_login
                FROM {$wpdb->prefix}l2i_chat_messages m
                LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
                WHERE m.thread_id = %d 
                AND m.is_deleted = 0
                $where_sql
                ORDER BY m.created_at DESC
                LIMIT %d
            ", $params), ARRAY_A);
            
            // Reverse to get chronological order
            $messages = array_reverse($messages);
            
            wp_cache_set($cache_key, $messages, 'l2i_chat', 120); // Cache for 2 minutes
        }
        
        return $messages;
    }
    
    /**
     * Insert new message with optimized performance
     */
    public function insert_message($thread_id, $sender_id, $recipient_id, $content, $message_type = 'text', $attachment_id = null, $metadata = null) {
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Insert message
            $result = $wpdb->insert(
                $wpdb->prefix . 'l2i_chat_messages',
                array(
                    'thread_id' => $thread_id,
                    'sender_id' => $sender_id,
                    'recipient_id' => $recipient_id,
                    'message_content' => $content,
                    'message_type' => $message_type,
                    'attachment_id' => $attachment_id,
                    'metadata' => $metadata ? wp_json_encode($metadata) : null,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s')
            );
            
            if (!$result) {
                throw new Exception('Failed to insert message');
            }
            
            $message_id = $wpdb->insert_id;
            
            // Update thread
            $wpdb->update(
                $wpdb->prefix . 'l2i_chat_threads',
                array(
                    'last_message_id' => $message_id,
                    'last_message_time' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $thread_id),
                array('%d', '%s', '%s'),
                array('%d')
            );
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Clear relevant caches
            $this->clear_thread_caches($thread_id, $sender_id, $recipient_id);
            
            // Update analytics
            $this->update_daily_analytics('messages');
            
            // Trigger actions
            do_action('l2i_chat_message_sent', $message_id, $thread_id, $sender_id, $recipient_id);
            
            return $message_id;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    
    /**
     * Mark messages as read
     */
    public function mark_messages_read($thread_id, $user_id, $up_to_message_id = null) {
        global $wpdb;
        
        if (!$up_to_message_id) {
            // Get latest message ID
            $up_to_message_id = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(id) FROM {$wpdb->prefix}l2i_chat_messages WHERE thread_id = %d",
                $thread_id
            ));
        }
        
        if (!$up_to_message_id) {
            return false;
        }
        
        // Get thread to determine user position
        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_chat_threads WHERE id = %d",
            $thread_id
        ), ARRAY_A);
        
        if (!$thread) {
            return false;
        }
        
        // Update read status in thread
        $field = ($thread['user1_id'] == $user_id) ? 'user1_last_read' : 'user2_last_read';
        
        $result = $wpdb->update(
            $wpdb->prefix . 'l2i_chat_threads',
            array($field => $up_to_message_id),
            array('id' => $thread_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Clear caches
            $this->clear_thread_caches($thread_id, $thread['user1_id'], $thread['user2_id']);
            
            // Trigger action
            do_action('l2i_chat_messages_read', $thread_id, $user_id, $up_to_message_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Update typing status
     */
    public function update_typing_status($thread_id, $user_id, $is_typing = true) {
        global $wpdb;
        
        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_chat_threads WHERE id = %d",
            $thread_id
        ), ARRAY_A);
        
        if (!$thread) {
            return false;
        }
        
        $field = ($thread['user1_id'] == $user_id) ? 'user1_typing_until' : 'user2_typing_until';
        $typing_until = $is_typing ? date('Y-m-d H:i:s', strtotime('+3 seconds')) : null;
        
        return $wpdb->update(
            $wpdb->prefix . 'l2i_chat_threads',
            array($field => $typing_until),
            array('id' => $thread_id),
            array('%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Get unread message count for user
     */
    public function get_unread_count($user_id) {
        global $wpdb;
        
        $cache_key = "unread_count_{$user_id}";
        $count = wp_cache_get($cache_key, 'l2i_chat');
        
        if (false === $count) {
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}l2i_chat_threads t
                WHERE (t.user1_id = %d OR t.user2_id = %d)
                AND t.status = 'active'
                AND (
                    (t.user1_id = %d AND t.user1_last_read < t.last_message_id) OR
                    (t.user2_id = %d AND t.user2_last_read < t.last_message_id)
                )
            ", $user_id, $user_id, $user_id, $user_id));
            
            wp_cache_set($cache_key, $count, 'l2i_chat', 60); // Cache for 1 minute
        }
        
        return (int) $count;
    }
    
    /**
     * Clear thread-related caches
     */
    private function clear_thread_caches($thread_id, $user1_id, $user2_id) {
        // Ensure consistent ordering
        if ($user1_id > $user2_id) {
            $temp = $user1_id;
            $user1_id = $user2_id;
            $user2_id = $temp;
        }
        
        wp_cache_delete("chat_thread_{$user1_id}_{$user2_id}", 'l2i_chat');
        wp_cache_delete("unread_count_{$user1_id}", 'l2i_chat');
        wp_cache_delete("unread_count_{$user2_id}", 'l2i_chat');
        
        // Clear user threads cache (simplified - in production you'd be more specific)
        wp_cache_flush_group('l2i_chat');
    }
    
    /**
     * Update daily analytics
     */
    private function update_daily_analytics($metric, $increment = 1) {
        global $wpdb;
        
        $today = current_time('Y-m-d');
        
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$wpdb->prefix}l2i_chat_analytics (date, {$metric})
            VALUES (%s, %d)
            ON DUPLICATE KEY UPDATE {$metric} = {$metric} + %d
        ", $today, $increment, $increment));
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        $retention_days = get_option('l2i_chat_message_retention_days', 365);
        
        if ($retention_days > 0) {
            // Delete old messages
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->prefix}l2i_chat_messages 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            ", $retention_days));
        }
        
        // Clean up orphaned threads
        $wpdb->query("
            DELETE t FROM {$wpdb->prefix}l2i_chat_threads t
            LEFT JOIN {$wpdb->prefix}l2i_chat_messages m ON t.id = m.thread_id
            WHERE m.id IS NULL AND t.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Clean up old online status records
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}l2i_user_online_status 
            WHERE last_seen < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        // Clean up expired restrictions
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}l2i_chat_restrictions 
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
        ");
    }
}