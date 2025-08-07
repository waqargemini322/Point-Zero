<?php
/**
 * Chat Restrictions Class
 * Handles membership-based chat restrictions and integrations
 */

defined('ABSPATH') || exit;

class L2I_Chat_Restrictions {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Integrate with membership plugin if available
        add_filter('l2i_can_start_conversation', array($this, 'check_conversation_permission'), 10, 2);
        add_filter('l2i_can_send_message', array($this, 'check_messaging_permission'), 10, 2);
        add_filter('l2i_can_upload_file', array($this, 'check_file_upload_permission'), 10, 2);
        add_filter('l2i_can_create_zoom_meeting', array($this, 'check_zoom_permission'), 10, 2);
        
        // Hook into conversation creation to deduct credits
        add_action('l2i_conversation_started', array($this, 'deduct_conversation_credit'), 10, 2);
        add_action('l2i_zoom_meeting_created', array($this, 'deduct_zoom_credit'), 10, 2);
        
        // Content restrictions
        add_filter('l2i_message_content', array($this, 'filter_message_content'), 10, 2);
        
        // Admin restrictions
        add_filter('l2i_user_can_access_chat_admin', array($this, 'check_admin_access'), 10, 1);
    }
    
    /**
     * Check if user can start a conversation
     */
    public function check_conversation_permission($can_start, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        // Check if membership plugin is available
        if (!class_exists('L2I_Restrictions')) {
            return $can_start; // No restrictions if membership plugin not available
        }
        
        $restrictions = L2I_Restrictions::get_instance();
        $user_restrictions = $restrictions->get_user_restrictions($user_id);
        
        // Check if user can message
        if (!$user_restrictions['permissions']['message_users']) {
            return false;
        }
        
        // Check connection credits
        if (!$restrictions->check_messaging_permission(true, $user_id)) {
            return false;
        }
        
        return $can_start;
    }
    
    /**
     * Check if user can send messages
     */
    public function check_messaging_permission($can_send, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        // Check if membership plugin is available
        if (!class_exists('L2I_Restrictions')) {
            return $can_send;
        }
        
        $restrictions = L2I_Restrictions::get_instance();
        $user_restrictions = $restrictions->get_user_restrictions($user_id);
        
        return $user_restrictions['permissions']['message_users'];
    }
    
    /**
     * Check if user can upload files
     */
    public function check_file_upload_permission($can_upload, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        // Check global setting
        if (!get_option('l2i_chat_enable_file_upload', true)) {
            return false;
        }
        
        // Check if membership plugin is available
        if (!class_exists('L2I_Restrictions')) {
            return $can_upload;
        }
        
        $restrictions = L2I_Restrictions::get_instance();
        $user_restrictions = $restrictions->get_user_restrictions($user_id);
        
        return $user_restrictions['permissions']['upload_portfolio'];
    }
    
    /**
     * Check if user can create Zoom meetings
     */
    public function check_zoom_permission($can_create, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        // Check if Zoom is enabled
        if (!get_option('l2i_chat_enable_zoom_integration', false)) {
            return false;
        }
        
        // Check if membership plugin is available
        if (!class_exists('L2I_Credits')) {
            return $can_create;
        }
        
        $credits = L2I_Credits::get_instance();
        
        // Check if user has Zoom credits
        $zoom_credits = $credits->get_user_credits($user_id, 'zoom');
        
        return $zoom_credits > 0;
    }
    
    /**
     * Deduct connection credit when conversation starts
     */
    public function deduct_conversation_credit($thread_id, $initiator_id) {
        if (!class_exists('L2I_Credits')) {
            return;
        }
        
        $credits = L2I_Credits::get_instance();
        
        // Check if this is a new conversation that requires credit deduction
        global $wpdb;
        $message_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}l2i_chat_messages WHERE thread_id = %d",
            $thread_id
        ));
        
        // Only deduct credit for the first message in a conversation
        if ($message_count <= 1) {
            $credits->subtract_credits($initiator_id, 1, 'connection', 'Started new conversation');
        }
    }
    
    /**
     * Deduct Zoom credit when meeting is created
     */
    public function deduct_zoom_credit($meeting_id, $creator_id) {
        if (!class_exists('L2I_Credits')) {
            return;
        }
        
        $credits = L2I_Credits::get_instance();
        $credits->subtract_credits($creator_id, 1, 'zoom', 'Created Zoom meeting');
    }
    
    /**
     * Filter message content for privacy and restrictions
     */
    public function filter_message_content($content, $user_id) {
        // Remove/mask sensitive information
        $content = $this->mask_sensitive_info($content);
        
        // Apply content restrictions based on membership
        if (class_exists('L2I_Restrictions')) {
            $restrictions = L2I_Restrictions::get_instance();
            $user_restrictions = $restrictions->get_user_restrictions($user_id);
            
            // Basic members might have content restrictions
            if (isset($user_restrictions['tier']) && strpos($user_restrictions['tier'], 'basic') !== false) {
                // Limit message length for basic users
                if (strlen($content) > 500) {
                    $content = substr($content, 0, 500) . '... [Message truncated - upgrade to send longer messages]';
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Mask sensitive information in messages
     */
    private function mask_sensitive_info($content) {
        // Mask email addresses
        $content = preg_replace(
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '[email protected]',
            $content
        );
        
        // Mask phone numbers (various formats)
        $phone_patterns = array(
            '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/',           // 123-456-7890, 123.456.7890, 1234567890
            '/\b\(\d{3}\)\s*\d{3}[-.]?\d{4}\b/',        // (123) 456-7890
            '/\b\+\d{1,3}[-.\s]?\d{3,4}[-.\s]?\d{3,4}[-.\s]?\d{3,4}\b/' // International
        );
        
        foreach ($phone_patterns as $pattern) {
            $content = preg_replace($pattern, '[phone number]', $content);
        }
        
        // Mask URLs (but allow zoom meeting links)
        $content = preg_replace(
            '/https?:\/\/(?!.*zoom\.us\/j\/)[^\s<>"\']+/i',
            '[website link]',
            $content
        );
        
        return $content;
    }
    
    /**
     * Check admin access permissions
     */
    public function check_admin_access($user_id) {
        if (!$user_id) {
            return false;
        }
        
        // WordPress admin check
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Custom capability check
        if (current_user_can('manage_chat_system')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user's chat limitations
     */
    public function get_user_chat_limits($user_id) {
        if (!class_exists('L2I_Restrictions')) {
            return array(
                'max_conversations_per_day' => -1, // Unlimited
                'max_messages_per_conversation' => -1,
                'max_file_size' => 5 * 1024 * 1024, // 5MB
                'can_create_zoom_meetings' => true,
                'message_character_limit' => -1
            );
        }
        
        $restrictions = L2I_Restrictions::get_instance();
        $user_restrictions = $restrictions->get_user_restrictions($user_id);
        
        $limits = array(
            'max_conversations_per_day' => -1,
            'max_messages_per_conversation' => -1,
            'max_file_size' => 5 * 1024 * 1024, // 5MB default
            'can_create_zoom_meetings' => $user_restrictions['permissions']['zoom_meetings'] ?? false,
            'message_character_limit' => -1
        );
        
        // Apply tier-specific limits
        if (isset($user_restrictions['tier'])) {
            $tier = $user_restrictions['tier'];
            
            if (strpos($tier, 'basic') !== false) {
                $limits['max_conversations_per_day'] = 3;
                $limits['max_messages_per_conversation'] = 50;
                $limits['max_file_size'] = 2 * 1024 * 1024; // 2MB
                $limits['message_character_limit'] = 500;
            } elseif (strpos($tier, 'gold') !== false) {
                $limits['max_conversations_per_day'] = 10;
                $limits['max_messages_per_conversation'] = 200;
                $limits['max_file_size'] = 10 * 1024 * 1024; // 10MB
                $limits['message_character_limit'] = 2000;
            } elseif (strpos($tier, 'premium') !== false || strpos($tier, 'enterprise') !== false) {
                // Premium/Enterprise have no limits (or very high limits)
                $limits['max_file_size'] = 20 * 1024 * 1024; // 20MB
            }
        }
        
        return $limits;
    }
    
    /**
     * Check if user has reached daily conversation limit
     */
    public function check_daily_conversation_limit($user_id) {
        $limits = $this->get_user_chat_limits($user_id);
        
        if ($limits['max_conversations_per_day'] === -1) {
            return true; // No limit
        }
        
        global $wpdb;
        $today_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT t.id) 
             FROM {$wpdb->prefix}l2i_chat_threads t
             INNER JOIN {$wpdb->prefix}l2i_chat_messages m ON t.id = m.thread_id
             WHERE (t.user1_id = %d OR t.user2_id = %d)
             AND DATE(m.created_at) = CURDATE()
             AND m.sender_id = %d",
            $user_id, $user_id, $user_id
        ));
        
        return $today_conversations < $limits['max_conversations_per_day'];
    }
    
    /**
     * Get restriction error message
     */
    public function get_restriction_message($restriction_type, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $messages = array(
            'no_messaging' => __('Your membership level does not allow messaging. Please upgrade to start conversations.', 'l2i-livechat'),
            'no_credits' => __('You don\'t have enough connection credits. Please upgrade your plan to continue messaging.', 'l2i-livechat'),
            'no_zoom_credits' => __('You don\'t have enough Zoom credits. Please upgrade your plan to create video meetings.', 'l2i-livechat'),
            'no_file_upload' => __('Your membership level does not allow file uploads. Please upgrade to share files.', 'l2i-livechat'),
            'daily_limit' => __('You have reached your daily conversation limit. Please upgrade your plan for more conversations.', 'l2i-livechat'),
            'file_too_large' => __('File size exceeds your membership limit. Please upgrade for larger file uploads.', 'l2i-livechat'),
            'message_too_long' => __('Message exceeds character limit for your membership level. Please upgrade or shorten your message.', 'l2i-livechat')
        );
        
        return $messages[$restriction_type] ?? __('Access restricted. Please upgrade your membership.', 'l2i-livechat');
    }
    
    /**
     * Get upgrade URL
     */
    public function get_upgrade_url() {
        return apply_filters('l2i_chat_upgrade_url', get_option('l2i_membership_upgrade_url', home_url('/membership-plans/')));
    }
}