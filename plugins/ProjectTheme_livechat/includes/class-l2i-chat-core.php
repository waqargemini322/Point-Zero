<?php
/**
 * Chat Core Functionality Class
 * Handles main chat business logic and integrations
 */

defined('ABSPATH') || exit;

class L2I_Chat_Core {
    
    private static $instance = null;
    private $db;
    private $restrictions;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = L2I_Chat_Database::get_instance();
        
        // Initialize after all plugins loaded to ensure L2I Membership is available
        add_action('plugins_loaded', array($this, 'init_restrictions'), 20);
        
        $this->init_hooks();
    }
    
    public function init_restrictions() {
        if (class_exists('L2I_Restrictions')) {
            $this->restrictions = L2I_Restrictions::get_instance();
        }
    }
    
    private function init_hooks() {
        // Integration with L2I Membership
        add_action('l2i_chat_message_sent', array($this, 'handle_message_sent'), 10, 4);
        add_action('l2i_chat_thread_created', array($this, 'handle_thread_created'), 10, 3);
        
        // Scheduled cleanup
        if (!wp_next_scheduled('l2i_chat_cleanup')) {
            wp_schedule_event(time(), 'daily', 'l2i_chat_cleanup');
        }
        add_action('l2i_chat_cleanup', array($this, 'daily_cleanup'));
    }
    
    /**
     * Get or create thread between two users
     */
    public function get_or_create_thread($user1_id, $user2_id) {
        // Validate users
        if (!get_userdata($user1_id) || !get_userdata($user2_id)) {
            return new WP_Error('invalid_users', __('Invalid user IDs provided.', 'l2i-livechat'));
        }
        
        // Users can't chat with themselves
        if ($user1_id == $user2_id) {
            return new WP_Error('same_user', __('You cannot start a conversation with yourself.', 'l2i-livechat'));
        }
        
        // Check if users can start conversation (membership restrictions)
        $can_start = $this->can_users_start_conversation($user1_id, $user2_id);
        if (is_wp_error($can_start)) {
            return $can_start;
        }
        
        // Get or create thread
        $thread = $this->db->get_thread($user1_id, $user2_id, true);
        
        return $thread ? $thread['id'] : false;
    }
    
    /**
     * Send a message
     */
    public function send_message($thread_id, $sender_id, $message_content, $message_type = 'text', $attachment_id = null, $metadata = null) {
        // Validate thread
        $thread = $this->get_thread_info($thread_id);
        if (is_wp_error($thread)) {
            return $thread;
        }
        
        // Check if sender is part of the thread
        if (!in_array($sender_id, array($thread['user1_id'], $thread['user2_id']))) {
            return new WP_Error('not_authorized', __('You are not authorized to send messages in this thread.', 'l2i-livechat'));
        }
        
        // Determine recipient
        $recipient_id = ($thread['user1_id'] == $sender_id) ? $thread['user2_id'] : $thread['user1_id'];
        
        // Check messaging permissions
        $can_send = $this->can_user_send_message($sender_id, $recipient_id, $thread_id);
        if (is_wp_error($can_send)) {
            return $can_send;
        }
        
        // Sanitize content
        $message_content = $this->sanitize_message_content($message_content);
        
        // Handle file attachment
        if ($attachment_id && !$this->validate_attachment($attachment_id, $sender_id)) {
            return new WP_Error('invalid_attachment', __('Invalid file attachment.', 'l2i-livechat'));
        }
        
        // Insert message
        $message_id = $this->db->insert_message(
            $thread_id,
            $sender_id,
            $recipient_id,
            $message_content,
            $message_type,
            $attachment_id,
            $metadata
        );
        
        if (!$message_id) {
            return new WP_Error('message_failed', __('Failed to send message.', 'l2i-livechat'));
        }
        
        return $message_id;
    }
    
    /**
     * Get thread information
     */
    public function get_thread_info($thread_id, $user_id = null) {
        global $wpdb;
        
        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, 
                    u1.display_name as user1_name,
                    u1.user_login as user1_login,
                    u2.display_name as user2_name,
                    u2.user_login as user2_login
             FROM {$wpdb->prefix}l2i_chat_threads t
             LEFT JOIN {$wpdb->users} u1 ON t.user1_id = u1.ID
             LEFT JOIN {$wpdb->users} u2 ON t.user2_id = u2.ID
             WHERE t.id = %d AND t.status = 'active'",
            $thread_id
        ), ARRAY_A);
        
        if (!$thread) {
            return new WP_Error('thread_not_found', __('Conversation not found.', 'l2i-livechat'));
        }
        
        // Check if user has access to this thread
        if ($user_id && !in_array($user_id, array($thread['user1_id'], $thread['user2_id']))) {
            return new WP_Error('no_access', __('You do not have access to this conversation.', 'l2i-livechat'));
        }
        
        return $thread;
    }
    
    /**
     * Get thread messages with pagination
     */
    public function get_thread_messages($thread_id, $user_id, $limit = 50, $before_message_id = null) {
        // Check thread access
        $thread = $this->get_thread_info($thread_id, $user_id);
        if (is_wp_error($thread)) {
            return $thread;
        }
        
        // Get messages
        $messages = $this->db->get_thread_messages($thread_id, $limit, $before_message_id);
        
        // Process messages (add avatars, format content, etc.)
        $processed_messages = array();
        foreach ($messages as $message) {
            $processed_message = $this->process_message($message, $user_id);
            $processed_messages[] = $processed_message;
        }
        
        return $processed_messages;
    }
    
    /**
     * Get user's conversations
     */
    public function get_user_conversations($user_id, $limit = 20, $offset = 0, $search = '') {
        if (!get_userdata($user_id)) {
            return new WP_Error('invalid_user', __('Invalid user ID.', 'l2i-livechat'));
        }
        
        $threads = $this->db->get_user_threads($user_id, $limit, $offset, $search);
        
        // Process threads (add additional info, check online status, etc.)
        $processed_threads = array();
        foreach ($threads as $thread) {
            $processed_thread = $this->process_thread($thread, $user_id);
            $processed_threads[] = $processed_thread;
        }
        
        return $processed_threads;
    }
    
    /**
     * Mark messages as read
     */
    public function mark_messages_read($thread_id, $user_id) {
        // Check thread access
        $thread = $this->get_thread_info($thread_id, $user_id);
        if (is_wp_error($thread)) {
            return $thread;
        }
        
        return $this->db->mark_messages_read($thread_id, $user_id);
    }
    
    /**
     * Update typing status
     */
    public function update_typing_status($thread_id, $user_id, $is_typing = true) {
        // Check thread access
        $thread = $this->get_thread_info($thread_id, $user_id);
        if (is_wp_error($thread)) {
            return $thread;
        }
        
        return $this->db->update_typing_status($thread_id, $user_id, $is_typing);
    }
    
    /**
     * Get typing status for thread
     */
    public function get_typing_status($thread_id, $user_id) {
        global $wpdb;
        
        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_chat_threads WHERE id = %d",
            $thread_id
        ), ARRAY_A);
        
        if (!$thread) {
            return false;
        }
        
        // Check if the other user is typing
        $now = current_time('mysql');
        $other_user_typing_field = ($thread['user1_id'] == $user_id) ? 'user2_typing_until' : 'user1_typing_until';
        $other_user_typing_until = $thread[$other_user_typing_field];
        
        if ($other_user_typing_until && $other_user_typing_until > $now) {
            $other_user_id = ($thread['user1_id'] == $user_id) ? $thread['user2_id'] : $thread['user1_id'];
            $other_user = get_userdata($other_user_id);
            
            return array(
                'is_typing' => true,
                'user_id' => $other_user_id,
                'user_name' => $other_user->display_name
            );
        }
        
        return array('is_typing' => false);
    }
    
    /**
     * Send Zoom meeting invitation
     */
    public function send_zoom_invitation($thread_id, $host_user_id, $meeting_data = array()) {
        // Check if L2I Zoom is available
        if (!class_exists('L2I_Zoom')) {
            return new WP_Error('zoom_not_available', __('Zoom integration is not available.', 'l2i-livechat'));
        }
        
        // Get thread info
        $thread = $this->get_thread_info($thread_id, $host_user_id);
        if (is_wp_error($thread)) {
            return $thread;
        }
        
        $participant_id = ($thread['user1_id'] == $host_user_id) ? $thread['user2_id'] : $thread['user1_id'];
        
        // Create Zoom meeting
        $zoom = L2I_Zoom::get_instance();
        $meeting_result = $zoom->create_meeting($host_user_id, $participant_id, $thread_id, $meeting_data);
        
        if (is_wp_error($meeting_result)) {
            return $meeting_result;
        }
        
        // Send meeting invitation as message
        $host_user = get_userdata($host_user_id);
        $message_content = sprintf(
            __("🎥 %s has invited you to a Zoom meeting!\n\nMeeting ID: %s\nPassword: %s\n\nClick to join: %s", 'l2i-livechat'),
            $host_user->display_name,
            $meeting_result['meeting_id'],
            $meeting_result['password'],
            $meeting_result['join_url']
        );
        
        $message_metadata = array(
            'zoom_meeting_id' => $meeting_result['meeting_id'],
            'zoom_start_url' => $meeting_result['start_url'],
            'zoom_join_url' => $meeting_result['join_url'],
            'zoom_password' => $meeting_result['password']
        );
        
        $message_id = $this->send_message(
            $thread_id,
            $host_user_id,
            $message_content,
            'zoom_invite',
            null,
            $message_metadata
        );
        
        if (is_wp_error($message_id)) {
            return $message_id;
        }
        
        return array(
            'message_id' => $message_id,
            'meeting_data' => $meeting_result
        );
    }
    
    /**
     * Check if users can start conversation (membership restrictions)
     */
    private function can_users_start_conversation($user1_id, $user2_id) {
        // Check if L2I Restrictions is available
        if (!$this->restrictions) {
            return true; // Allow if membership system is not available
        }
        
        // Check if user1 can start conversations
        $can_start = $this->restrictions->check_messaging_permission(true, $user1_id, $user2_id);
        if (!$can_start) {
            return new WP_Error('messaging_restricted', __('Your membership level does not allow starting new conversations.', 'l2i-livechat'));
        }
        
        return true;
    }
    
    /**
     * Check if user can send message
     */
    private function can_user_send_message($sender_id, $recipient_id, $thread_id) {
        // Check basic user validity
        if (!get_userdata($sender_id) || !get_userdata($recipient_id)) {
            return new WP_Error('invalid_users', __('Invalid user data.', 'l2i-livechat'));
        }
        
        // Check if L2I Restrictions is available
        if (!$this->restrictions) {
            return true; // Allow if membership system is not available
        }
        
        // Check messaging permissions
        $can_message = $this->restrictions->check_messaging_permission(true, $sender_id, $recipient_id);
        if (!$can_message) {
            return new WP_Error('messaging_restricted', __('You have reached your messaging limit for this month.', 'l2i-livechat'));
        }
        
        return true;
    }
    
    /**
     * Sanitize message content
     */
    private function sanitize_message_content($content) {
        // Remove potentially dangerous content
        $content = wp_strip_all_tags($content);
        
        // Remove email addresses (privacy protection)
        $content = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[email removed]', $content);
        
        // Remove phone numbers (privacy protection)
        $content = preg_replace('/\+?[0-9][0-9()\-\s+]{4,20}[0-9]/', '[phone removed]', $content);
        
        // Remove URLs (security)
        $content = preg_replace('/(https?|ftp):\/\/[^\s/$.?#].[^\s]*/', '[link removed]', $content);
        
        // Trim and limit length
        $content = trim($content);
        $max_length = apply_filters('l2i_chat_max_message_length', 2000);
        if (strlen($content) > $max_length) {
            $content = substr($content, 0, $max_length) . '...';
        }
        
        return $content;
    }
    
    /**
     * Validate file attachment
     */
    private function validate_attachment($attachment_id, $user_id) {
        $attachment = get_post($attachment_id);
        
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return false;
        }
        
        // Check if user owns the attachment or has permission
        if ($attachment->post_author != $user_id && !current_user_can('manage_options')) {
            return false;
        }
        
        // Check file type
        $allowed_types = apply_filters('l2i_chat_allowed_file_types', array(
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip'
        ));
        
        if (!in_array($attachment->post_mime_type, $allowed_types)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Process message for display
     */
    private function process_message($message, $current_user_id) {
        // Add avatar
        $message['sender_avatar'] = get_avatar_url($message['sender_id'], array('size' => 40));
        
        // Format timestamp
        $message['time_ago'] = human_time_diff(strtotime($message['created_at']), current_time('timestamp'));
        $message['formatted_time'] = date_i18n(get_option('time_format'), strtotime($message['created_at']));
        
        // Determine if message is from current user
        $message['is_own_message'] = ($message['sender_id'] == $current_user_id);
        
        // Process metadata
        if ($message['metadata']) {
            $message['metadata'] = json_decode($message['metadata'], true);
        }
        
        // Process attachment
        if ($message['attachment_id']) {
            $attachment = get_post($message['attachment_id']);
            if ($attachment) {
                $message['attachment'] = array(
                    'id' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'url' => wp_get_attachment_url($attachment->ID),
                    'mime_type' => $attachment->post_mime_type,
                    'file_size' => size_format(filesize(get_attached_file($attachment->ID)))
                );
            }
        }
        
        return $message;
    }
    
    /**
     * Process thread for display
     */
    private function process_thread($thread, $current_user_id) {
        // Determine the other user
        $other_user_id = ($thread['user1_id'] == $current_user_id) ? $thread['user2_id'] : $thread['user1_id'];
        $other_user_name = ($thread['user1_id'] == $current_user_id) ? $thread['user2_name'] : $thread['user1_name'];
        $other_user_login = ($thread['user1_id'] == $current_user_id) ? $thread['user2_login'] : $thread['user1_login'];
        
        // Add other user info
        $thread['other_user_id'] = $other_user_id;
        $thread['other_user_name'] = $other_user_name;
        $thread['other_user_login'] = $other_user_login;
        $thread['other_user_avatar'] = get_avatar_url($other_user_id, array('size' => 50));
        
        // Check online status
        $thread['other_user_online'] = L2I_LiveChat_Manager::is_user_online($other_user_id);
        
        // Format last message time
        if ($thread['last_message_time']) {
            $thread['last_message_time_ago'] = human_time_diff(strtotime($thread['last_message_time']), current_time('timestamp'));
        }
        
        // Truncate last message
        if ($thread['last_message']) {
            $thread['last_message_preview'] = wp_trim_words($thread['last_message'], 8, '...');
        }
        
        return $thread;
    }
    
    /**
     * Handle message sent event
     */
    public function handle_message_sent($message_id, $thread_id, $sender_id, $recipient_id) {
        // Use connection credit if this is a new conversation
        if ($this->is_first_message_in_thread($thread_id)) {
            if (class_exists('L2I_Credits')) {
                $credits = L2I_Credits::get_instance();
                $credits->use_credits(
                    $sender_id,
                    'connection_credits',
                    1,
                    sprintf(__('Started conversation with %s', 'l2i-livechat'), get_userdata($recipient_id)->display_name),
                    array('thread_id' => $thread_id, 'recipient_id' => $recipient_id)
                );
            }
        }
        
        // Send email notification if recipient is offline
        if (!L2I_LiveChat_Manager::is_user_online($recipient_id)) {
            $this->send_message_notification($sender_id, $recipient_id, $message_id);
        }
    }
    
    /**
     * Handle thread created event
     */
    public function handle_thread_created($thread_id, $user1_id, $user2_id) {
        // Log thread creation for analytics
        do_action('l2i_analytics_track', 'chat_thread_created', array(
            'thread_id' => $thread_id,
            'user1_id' => $user1_id,
            'user2_id' => $user2_id
        ));
    }
    
    /**
     * Check if this is the first message in thread
     */
    private function is_first_message_in_thread($thread_id) {
        global $wpdb;
        
        $message_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}l2i_chat_messages WHERE thread_id = %d",
            $thread_id
        ));
        
        return $message_count <= 1;
    }
    
    /**
     * Send message notification email
     */
    private function send_message_notification($sender_id, $recipient_id, $message_id) {
        if (!get_option('l2i_chat_enable_email_notifications', true)) {
            return;
        }
        
        $sender = get_userdata($sender_id);
        $recipient = get_userdata($recipient_id);
        
        if (!$sender || !$recipient) {
            return;
        }
        
        $subject = sprintf(__('New message from %s on Link2Investors', 'l2i-livechat'), $sender->display_name);
        
        $message = sprintf(
            __("Hello %s,\n\nYou have received a new message from %s on Link2Investors.\n\nTo read and reply to the message, please visit: %s\n\nBest regards,\nLink2Investors Team", 'l2i-livechat'),
            $recipient->display_name,
            $sender->display_name,
            L2I_LiveChat_Manager::get_messaging_url()
        );
        
        wp_mail($recipient->user_email, $subject, $message);
    }
    
    /**
     * Daily cleanup task
     */
    public function daily_cleanup() {
        $this->db->cleanup_old_data();
        
        // Clear expired caches
        wp_cache_flush_group('l2i_chat');
        
        // Log cleanup
        error_log('L2I Chat: Daily cleanup completed at ' . current_time('mysql'));
    }
    
    /**
     * Get unread message count for user
     */
    public function get_unread_count($user_id) {
        return $this->db->get_unread_count($user_id);
    }
    
    /**
     * Block/unblock thread
     */
    public function toggle_thread_block($thread_id, $user_id, $block = true) {
        // Check thread access
        $thread = $this->get_thread_info($thread_id, $user_id);
        if (is_wp_error($thread)) {
            return $thread;
        }
        
        global $wpdb;
        
        $status = $block ? 'blocked' : 'active';
        
        $result = $wpdb->update(
            $wpdb->prefix . 'l2i_chat_threads',
            array('status' => $status),
            array('id' => $thread_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Clear caches
            wp_cache_flush_group('l2i_chat');
            
            // Log action
            do_action('l2i_chat_thread_' . ($block ? 'blocked' : 'unblocked'), $thread_id, $user_id);
        }
        
        return $result !== false;
    }
}