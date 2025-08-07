<?php
/**
 * Chat AJAX Handler Class
 * Handles all AJAX requests for the chat system
 */

defined('ABSPATH') || exit;

class L2I_Chat_Ajax {
    
    private static $instance = null;
    private $core;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->core = L2I_Chat_Core::get_instance();
        $this->db = L2I_Chat_Database::get_instance();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Chat functionality AJAX actions
        add_action('wp_ajax_l2i_send_message', array($this, 'send_message'));
        add_action('wp_ajax_l2i_get_messages', array($this, 'get_messages'));
        add_action('wp_ajax_l2i_get_conversations', array($this, 'get_conversations'));
        add_action('wp_ajax_l2i_mark_read', array($this, 'mark_messages_read'));
        add_action('wp_ajax_l2i_update_typing', array($this, 'update_typing_status'));
        add_action('wp_ajax_l2i_get_typing_status', array($this, 'get_typing_status'));
        add_action('wp_ajax_l2i_start_conversation', array($this, 'start_conversation'));
        add_action('wp_ajax_l2i_upload_file', array($this, 'upload_file'));
        add_action('wp_ajax_l2i_search_conversations', array($this, 'search_conversations'));
        add_action('wp_ajax_l2i_get_unread_count', array($this, 'get_unread_count'));
        add_action('wp_ajax_l2i_block_user', array($this, 'block_user'));
        add_action('wp_ajax_l2i_send_zoom_invite', array($this, 'send_zoom_invite'));
        
        // Polling actions for real-time updates
        add_action('wp_ajax_l2i_poll_updates', array($this, 'poll_updates'));
        
        // Admin AJAX actions
        add_action('wp_ajax_l2i_admin_get_chat_stats', array($this, 'admin_get_chat_stats'));
        add_action('wp_ajax_l2i_admin_moderate_message', array($this, 'admin_moderate_message'));
    }
    
    /**
     * Send a message
     */
    public function send_message() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in to send messages.', 'l2i-livechat')
            ));
        }
        
        $thread_id = (int) $_POST['thread_id'];
        $message_content = sanitize_textarea_field($_POST['message_content']);
        $message_type = sanitize_text_field($_POST['message_type'] ?? 'text');
        $attachment_id = !empty($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : null;
        
        if (empty($message_content) && !$attachment_id) {
            wp_send_json_error(array(
                'message' => __('Message content cannot be empty.', 'l2i-livechat')
            ));
        }
        
        $result = $this->core->send_message($thread_id, $user_id, $message_content, $message_type, $attachment_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ));
        }
        
        // Get the sent message data
        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name 
             FROM {$wpdb->prefix}l2i_chat_messages m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.id = %d",
            $result
        ), ARRAY_A);
        
        if ($message) {
            $message['sender_avatar'] = get_avatar_url($user_id, array('size' => 40));
            $message['formatted_time'] = date_i18n(get_option('time_format'), strtotime($message['created_at']));
            $message['is_own_message'] = true;
        }
        
        wp_send_json_success(array(
            'message' => __('Message sent successfully.', 'l2i-livechat'),
            'message_id' => $result,
            'message_data' => $message
        ));
    }
    
    /**
     * Get messages for a thread
     */
    public function get_messages() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $thread_id = (int) $_POST['thread_id'];
        $limit = (int) ($_POST['limit'] ?? 50);
        $before_message_id = !empty($_POST['before_message_id']) ? (int) $_POST['before_message_id'] : null;
        
        $messages = $this->core->get_thread_messages($thread_id, $user_id, $limit, $before_message_id);
        
        if (is_wp_error($messages)) {
            wp_send_json_error(array(
                'message' => $messages->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'messages' => $messages,
            'has_more' => count($messages) === $limit
        ));
    }
    
    /**
     * Get user's conversations
     */
    public function get_conversations() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $limit = (int) ($_POST['limit'] ?? 20);
        $offset = (int) ($_POST['offset'] ?? 0);
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $conversations = $this->core->get_user_conversations($user_id, $limit, $offset, $search);
        
        if (is_wp_error($conversations)) {
            wp_send_json_error(array(
                'message' => $conversations->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'conversations' => $conversations,
            'has_more' => count($conversations) === $limit
        ));
    }
    
    /**
     * Mark messages as read
     */
    public function mark_messages_read() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $thread_id = (int) $_POST['thread_id'];
        
        $result = $this->core->mark_messages_read($thread_id, $user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Messages marked as read.', 'l2i-livechat')
        ));
    }
    
    /**
     * Update typing status
     */
    public function update_typing_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $thread_id = (int) $_POST['thread_id'];
        $is_typing = !empty($_POST['is_typing']);
        
        $result = $this->core->update_typing_status($thread_id, $user_id, $is_typing);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'status' => 'updated'
        ));
    }
    
    /**
     * Get typing status for thread
     */
    public function get_typing_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $thread_id = (int) $_POST['thread_id'];
        
        $typing_status = $this->core->get_typing_status($thread_id, $user_id);
        
        wp_send_json_success($typing_status);
    }
    
    /**
     * Start a new conversation
     */
    public function start_conversation() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $recipient_id = (int) $_POST['recipient_id'];
        
        $thread_id = $this->core->get_or_create_thread($user_id, $recipient_id);
        
        if (is_wp_error($thread_id)) {
            wp_send_json_error(array(
                'message' => $thread_id->get_error_message(),
                'code' => $thread_id->get_error_code()
            ));
        }
        
        wp_send_json_success(array(
            'thread_id' => $thread_id,
            'redirect_url' => L2I_LiveChat_Manager::get_messaging_url($thread_id)
        ));
    }
    
    /**
     * Upload file for chat
     */
    public function upload_file() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(array(
                'message' => __('No file uploaded.', 'l2i-livechat')
            ));
        }
        
        // Check file upload permissions
        if (!get_option('l2i_chat_enable_file_upload', true)) {
            wp_send_json_error(array(
                'message' => __('File uploads are disabled.', 'l2i-livechat')
            ));
        }
        
        // Check membership restrictions
        if (class_exists('L2I_Restrictions')) {
            $restrictions = L2I_Restrictions::get_instance();
            $user_restrictions = $restrictions->get_user_restrictions($user_id);
            
            if (!$user_restrictions['permissions']['upload_portfolio']) {
                wp_send_json_error(array(
                    'message' => __('Your membership level does not allow file uploads.', 'l2i-livechat')
                ));
            }
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $uploaded_file = wp_handle_upload($_FILES['file'], array('test_form' => false));
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error(array(
                'message' => $uploaded_file['error']
            ));
        }
        
        // Create attachment
        $attachment_data = array(
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => sanitize_file_name($_FILES['file']['name']),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => $user_id
        );
        
        $attachment_id = wp_insert_attachment($attachment_data, $uploaded_file['file']);
        
        if (!$attachment_id) {
            wp_send_json_error(array(
                'message' => __('Failed to create attachment.', 'l2i-livechat')
            ));
        }
        
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        
        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'attachment_url' => wp_get_attachment_url($attachment_id),
            'attachment_title' => get_the_title($attachment_id),
            'file_size' => size_format(filesize($uploaded_file['file']))
        ));
    }
    
    /**
     * Search conversations
     */
    public function search_conversations() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $search_term = sanitize_text_field($_POST['search_term']);
        $limit = (int) ($_POST['limit'] ?? 10);
        
        $conversations = $this->core->get_user_conversations($user_id, $limit, 0, $search_term);
        
        if (is_wp_error($conversations)) {
            wp_send_json_error(array(
                'message' => $conversations->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'conversations' => $conversations
        ));
    }
    
    /**
     * Get unread message count
     */
    public function get_unread_count() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $unread_count = $this->core->get_unread_count($user_id);
        
        wp_send_json_success(array(
            'unread_count' => $unread_count
        ));
    }
    
    /**
     * Block/unblock user
     */
    public function block_user() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $thread_id = (int) $_POST['thread_id'];
        $block = !empty($_POST['block']);
        
        $result = $this->core->toggle_thread_block($thread_id, $user_id, $block);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        $message = $block ? __('User blocked successfully.', 'l2i-livechat') : __('User unblocked successfully.', 'l2i-livechat');
        
        wp_send_json_success(array(
            'message' => $message,
            'blocked' => $block
        ));
    }
    
    /**
     * Send Zoom meeting invite
     */
    public function send_zoom_invite() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $thread_id = (int) $_POST['thread_id'];
        $meeting_topic = sanitize_text_field($_POST['meeting_topic'] ?? '');
        $meeting_duration = (int) ($_POST['meeting_duration'] ?? 60);
        
        $meeting_data = array();
        if ($meeting_topic) {
            $meeting_data['topic'] = $meeting_topic;
        }
        if ($meeting_duration) {
            $meeting_data['duration'] = $meeting_duration;
        }
        
        $result = $this->core->send_zoom_invitation($thread_id, $user_id, $meeting_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Zoom meeting invitation sent!', 'l2i-livechat'),
            'meeting_data' => $result['meeting_data'],
            'message_id' => $result['message_id']
        ));
    }
    
    /**
     * Poll for real-time updates
     */
    public function poll_updates() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'l2i-livechat')
            ));
        }
        
        $thread_id = (int) $_POST['thread_id'];
        $last_message_id = (int) ($_POST['last_message_id'] ?? 0);
        
        // Get new messages
        $new_messages = array();
        if ($last_message_id > 0) {
            global $wpdb;
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT m.*, u.display_name as sender_name
                 FROM {$wpdb->prefix}l2i_chat_messages m
                 LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
                 WHERE m.thread_id = %d 
                 AND m.id > %d 
                 AND m.is_deleted = 0
                 ORDER BY m.created_at ASC",
                $thread_id, $last_message_id
            ), ARRAY_A);
            
            foreach ($messages as $message) {
                $message['sender_avatar'] = get_avatar_url($message['sender_id'], array('size' => 40));
                $message['formatted_time'] = date_i18n(get_option('time_format'), strtotime($message['created_at']));
                $message['is_own_message'] = ($message['sender_id'] == $user_id);
                $new_messages[] = $message;
            }
        }
        
        // Get typing status
        $typing_status = $this->core->get_typing_status($thread_id, $user_id);
        
        // Get unread count
        $unread_count = $this->core->get_unread_count($user_id);
        
        wp_send_json_success(array(
            'new_messages' => $new_messages,
            'typing_status' => $typing_status,
            'unread_count' => $unread_count
        ));
    }
    
    /**
     * Admin: Get chat statistics
     */
    public function admin_get_chat_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'l2i-livechat')
            ));
        }
        
        global $wpdb;
        
        // Get basic stats
        $stats = array(
            'total_threads' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}l2i_chat_threads WHERE status = 'active'"),
            'total_messages' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}l2i_chat_messages WHERE is_deleted = 0"),
            'active_users_today' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}l2i_user_online_status WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            'messages_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}l2i_chat_messages WHERE DATE(created_at) = CURDATE()"),
        );
        
        // Get daily analytics for the past 7 days
        $daily_stats = $wpdb->get_results(
            "SELECT date, total_messages, total_threads, active_users, file_uploads, zoom_meetings
             FROM {$wpdb->prefix}l2i_chat_analytics 
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             ORDER BY date DESC",
            ARRAY_A
        );
        
        wp_send_json_success(array(
            'stats' => $stats,
            'daily_stats' => $daily_stats
        ));
    }
    
    /**
     * Admin: Moderate message
     */
    public function admin_moderate_message() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_chat_nonce')) {
            wp_die(__('Security check failed.', 'l2i-livechat'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'l2i-livechat')
            ));
        }
        
        $message_id = (int) $_POST['message_id'];
        $action = sanitize_text_field($_POST['action']); // 'delete' or 'restore'
        
        global $wpdb;
        
        $is_deleted = ($action === 'delete') ? 1 : 0;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'l2i_chat_messages',
            array('is_deleted' => $is_deleted),
            array('id' => $message_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Failed to moderate message.', 'l2i-livechat')
            ));
        }
        
        $message = ($action === 'delete') ? __('Message deleted.', 'l2i-livechat') : __('Message restored.', 'l2i-livechat');
        
        wp_send_json_success(array(
            'message' => $message
        ));
    }
}