<?php
/**
 * Chat Notifications Class
 * Handles email notifications and alerts for the chat system
 */

defined('ABSPATH') || exit;

class L2I_Chat_Notifications {
    
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
        // Hook into chat events
        add_action('l2i_message_sent', array($this, 'handle_message_notification'), 10, 3);
        add_action('l2i_conversation_started', array($this, 'handle_conversation_notification'), 10, 2);
        add_action('l2i_zoom_meeting_created', array($this, 'handle_zoom_notification'), 10, 3);
        
        // Admin notifications
        add_action('l2i_chat_report_created', array($this, 'handle_report_notification'), 10, 2);
        
        // Scheduled notifications
        add_action('l2i_send_digest_notifications', array($this, 'send_digest_notifications'));
        
        // Initialize cron jobs
        add_action('init', array($this, 'schedule_cron_jobs'));
    }
    
    /**
     * Handle new message notifications
     */
    public function handle_message_notification($message_id, $thread_id, $sender_id) {
        // Get thread info
        $thread = $this->get_thread_info($thread_id);
        if (!$thread) {
            return;
        }
        
        // Determine recipient
        $recipient_id = ($thread['user1_id'] == $sender_id) ? $thread['user2_id'] : $thread['user1_id'];
        
        // Check if recipient wants notifications
        if (!$this->user_wants_notifications($recipient_id, 'new_message')) {
            return;
        }
        
        // Check if sender is online (don't send notification if they're actively chatting)
        if ($this->is_user_recently_active($recipient_id)) {
            return;
        }
        
        // Get message details
        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name, u.user_email as sender_email
             FROM {$wpdb->prefix}l2i_chat_messages m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.id = %d",
            $message_id
        ), ARRAY_A);
        
        if (!$message) {
            return;
        }
        
        // Send notification
        $this->send_message_notification($recipient_id, $message, $thread);
    }
    
    /**
     * Handle new conversation notifications
     */
    public function handle_conversation_notification($thread_id, $initiator_id) {
        $thread = $this->get_thread_info($thread_id);
        if (!$thread) {
            return;
        }
        
        $recipient_id = ($thread['user1_id'] == $initiator_id) ? $thread['user2_id'] : $thread['user1_id'];
        
        if (!$this->user_wants_notifications($recipient_id, 'new_conversation')) {
            return;
        }
        
        $initiator = get_userdata($initiator_id);
        if (!$initiator) {
            return;
        }
        
        $this->send_conversation_notification($recipient_id, $initiator, $thread);
    }
    
    /**
     * Handle Zoom meeting notifications
     */
    public function handle_zoom_notification($meeting_id, $thread_id, $creator_id) {
        $thread = $this->get_thread_info($thread_id);
        if (!$thread) {
            return;
        }
        
        $recipient_id = ($thread['user1_id'] == $creator_id) ? $thread['user2_id'] : $thread['user1_id'];
        
        if (!$this->user_wants_notifications($recipient_id, 'zoom_meeting')) {
            return;
        }
        
        // Get meeting details from the message
        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_chat_messages 
             WHERE thread_id = %d AND message_type = 'zoom_invite' 
             ORDER BY created_at DESC LIMIT 1",
            $thread_id
        ), ARRAY_A);
        
        if (!$message) {
            return;
        }
        
        $creator = get_userdata($creator_id);
        $meeting_data = json_decode($message['metadata'], true);
        
        $this->send_zoom_notification($recipient_id, $creator, $meeting_data, $thread);
    }
    
    /**
     * Handle report notifications for admins
     */
    public function handle_report_notification($report_id, $reporter_id) {
        // Get admin users
        $admins = get_users(array(
            'role' => 'administrator',
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        $reporter = get_userdata($reporter_id);
        
        foreach ($admins as $admin) {
            $this->send_report_notification($admin->ID, $reporter, $report_id);
        }
    }
    
    /**
     * Send message notification email
     */
    private function send_message_notification($recipient_id, $message, $thread) {
        $recipient = get_userdata($recipient_id);
        if (!$recipient) {
            return;
        }
        
        $sender_name = $message['sender_name'];
        $message_preview = $this->get_message_preview($message);
        
        $subject = sprintf(
            __('New message from %s - Link2Investors', 'l2i-livechat'),
            $sender_name
        );
        
        $message_body = $this->get_email_template('new_message', array(
            'recipient_name' => $recipient->display_name,
            'sender_name' => $sender_name,
            'message_preview' => $message_preview,
            'conversation_url' => $this->get_conversation_url($thread['id']),
            'unsubscribe_url' => $this->get_unsubscribe_url($recipient_id, 'new_message')
        ));
        
        $this->send_email($recipient->user_email, $subject, $message_body);
        
        // Log notification
        $this->log_notification($recipient_id, 'new_message', array(
            'sender_id' => $message['sender_id'],
            'message_id' => $message['id'],
            'thread_id' => $thread['id']
        ));
    }
    
    /**
     * Send conversation notification email
     */
    private function send_conversation_notification($recipient_id, $initiator, $thread) {
        $recipient = get_userdata($recipient_id);
        if (!$recipient) {
            return;
        }
        
        $subject = sprintf(
            __('%s started a conversation with you - Link2Investors', 'l2i-livechat'),
            $initiator->display_name
        );
        
        $message_body = $this->get_email_template('new_conversation', array(
            'recipient_name' => $recipient->display_name,
            'initiator_name' => $initiator->display_name,
            'conversation_url' => $this->get_conversation_url($thread['id']),
            'profile_url' => $this->get_user_profile_url($initiator->ID),
            'unsubscribe_url' => $this->get_unsubscribe_url($recipient_id, 'new_conversation')
        ));
        
        $this->send_email($recipient->user_email, $subject, $message_body);
        
        $this->log_notification($recipient_id, 'new_conversation', array(
            'initiator_id' => $initiator->ID,
            'thread_id' => $thread['id']
        ));
    }
    
    /**
     * Send Zoom meeting notification email
     */
    private function send_zoom_notification($recipient_id, $creator, $meeting_data, $thread) {
        $recipient = get_userdata($recipient_id);
        if (!$recipient) {
            return;
        }
        
        $subject = sprintf(
            __('Zoom meeting invitation from %s - Link2Investors', 'l2i-livechat'),
            $creator->display_name
        );
        
        $message_body = $this->get_email_template('zoom_meeting', array(
            'recipient_name' => $recipient->display_name,
            'creator_name' => $creator->display_name,
            'meeting_topic' => $meeting_data['topic'] ?? 'Video Meeting',
            'meeting_url' => $meeting_data['zoom_join_url'] ?? '',
            'meeting_id' => $meeting_data['zoom_meeting_id'] ?? '',
            'meeting_password' => $meeting_data['zoom_password'] ?? '',
            'conversation_url' => $this->get_conversation_url($thread['id']),
            'unsubscribe_url' => $this->get_unsubscribe_url($recipient_id, 'zoom_meeting')
        ));
        
        $this->send_email($recipient->user_email, $subject, $message_body);
        
        $this->log_notification($recipient_id, 'zoom_meeting', array(
            'creator_id' => $creator->ID,
            'meeting_data' => $meeting_data,
            'thread_id' => $thread['id']
        ));
    }
    
    /**
     * Send report notification to admins
     */
    private function send_report_notification($admin_id, $reporter, $report_id) {
        $admin = get_userdata($admin_id);
        if (!$admin) {
            return;
        }
        
        $subject = __('New chat report submitted - Link2Investors', 'l2i-livechat');
        
        $message_body = $this->get_email_template('chat_report', array(
            'admin_name' => $admin->display_name,
            'reporter_name' => $reporter->display_name,
            'report_id' => $report_id,
            'admin_url' => admin_url('admin.php?page=l2i-chat-reports&report_id=' . $report_id)
        ));
        
        $this->send_email($admin->user_email, $subject, $message_body);
    }
    
    /**
     * Send digest notifications (daily/weekly summaries)
     */
    public function send_digest_notifications() {
        $users = get_users(array(
            'meta_key' => 'l2i_chat_digest_frequency',
            'meta_value' => 'daily',
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        foreach ($users as $user) {
            $this->send_digest_notification($user->ID, 'daily');
        }
        
        // Weekly digest (only on Mondays)
        if (date('N') == 1) {
            $weekly_users = get_users(array(
                'meta_key' => 'l2i_chat_digest_frequency',
                'meta_value' => 'weekly',
                'fields' => array('ID', 'display_name', 'user_email')
            ));
            
            foreach ($weekly_users as $user) {
                $this->send_digest_notification($user->ID, 'weekly');
            }
        }
    }
    
    /**
     * Send digest notification
     */
    private function send_digest_notification($user_id, $frequency) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $digest_data = $this->get_user_digest_data($user_id, $frequency);
        
        if (empty($digest_data['total_messages']) && empty($digest_data['new_conversations'])) {
            return; // No activity to report
        }
        
        $subject = sprintf(
            __('Your %s chat summary - Link2Investors', 'l2i-livechat'),
            $frequency
        );
        
        $message_body = $this->get_email_template('digest', array(
            'user_name' => $user->display_name,
            'frequency' => $frequency,
            'total_messages' => $digest_data['total_messages'],
            'new_conversations' => $digest_data['new_conversations'],
            'active_conversations' => $digest_data['active_conversations'],
            'unread_count' => $digest_data['unread_count'],
            'messages_url' => $this->get_messages_url(),
            'unsubscribe_url' => $this->get_unsubscribe_url($user_id, 'digest')
        ));
        
        $this->send_email($user->user_email, $subject, $message_body);
    }
    
    /**
     * Get email template
     */
    private function get_email_template($template_name, $variables) {
        $template_path = L2I_LIVECHAT_PLUGIN_DIR . "templates/email/{$template_name}.php";
        
        if (file_exists($template_path)) {
            ob_start();
            extract($variables);
            include $template_path;
            return ob_get_clean();
        }
        
        // Fallback to inline templates
        return $this->get_inline_email_template($template_name, $variables);
    }
    
    /**
     * Get inline email template (fallback)
     */
    private function get_inline_email_template($template_name, $variables) {
        extract($variables);
        
        switch ($template_name) {
            case 'new_message':
                return "
                    <h2>New Message from {$sender_name}</h2>
                    <p>Hi {$recipient_name},</p>
                    <p>You have received a new message from <strong>{$sender_name}</strong>:</p>
                    <blockquote style='background: #f8f9fa; padding: 15px; border-left: 4px solid #3498db; margin: 20px 0;'>
                        {$message_preview}
                    </blockquote>
                    <p><a href='{$conversation_url}' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reply Now</a></p>
                    <hr>
                    <p><small><a href='{$unsubscribe_url}'>Unsubscribe from message notifications</a></small></p>
                ";
                
            case 'new_conversation':
                return "
                    <h2>New Conversation Started</h2>
                    <p>Hi {$recipient_name},</p>
                    <p><strong>{$initiator_name}</strong> has started a conversation with you on Link2Investors.</p>
                    <p><a href='{$conversation_url}' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Conversation</a></p>
                    <p><a href='{$profile_url}'>View {$initiator_name}'s Profile</a></p>
                    <hr>
                    <p><small><a href='{$unsubscribe_url}'>Unsubscribe from conversation notifications</a></small></p>
                ";
                
            case 'zoom_meeting':
                return "
                    <h2>Zoom Meeting Invitation</h2>
                    <p>Hi {$recipient_name},</p>
                    <p><strong>{$creator_name}</strong> has invited you to a Zoom meeting:</p>
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3>{$meeting_topic}</h3>
                        <p><strong>Meeting ID:</strong> {$meeting_id}</p>
                        " . (!empty($meeting_password) ? "<p><strong>Password:</strong> {$meeting_password}</p>" : "") . "
                        <p><a href='{$meeting_url}' style='background: #0e71eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Join Meeting</a></p>
                    </div>
                    <p><a href='{$conversation_url}'>View Conversation</a></p>
                    <hr>
                    <p><small><a href='{$unsubscribe_url}'>Unsubscribe from meeting notifications</a></small></p>
                ";
                
            case 'digest':
                return "
                    <h2>Your {$frequency} Chat Summary</h2>
                    <p>Hi {$user_name},</p>
                    <p>Here's your chat activity summary:</p>
                    <ul>
                        <li><strong>{$total_messages}</strong> messages received</li>
                        <li><strong>{$new_conversations}</strong> new conversations</li>
                        <li><strong>{$active_conversations}</strong> active conversations</li>
                        <li><strong>{$unread_count}</strong> unread messages</li>
                    </ul>
                    <p><a href='{$messages_url}' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Messages</a></p>
                    <hr>
                    <p><small><a href='{$unsubscribe_url}'>Unsubscribe from digest notifications</a></small></p>
                ";
                
            default:
                return "<p>Notification from Link2Investors Chat System</p>";
        }
    }
    
    /**
     * Send email using WordPress mail system
     */
    private function send_email($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Link2Investors <' . get_option('admin_email') . '>'
        );
        
        $full_message = $this->wrap_email_template($message);
        
        wp_mail($to, $subject, $full_message, $headers);
    }
    
    /**
     * Wrap email content in template
     */
    private function wrap_email_template($content) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3498db; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Link2Investors</h1>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Link2Investors. All rights reserved.</p>
                    <p>This email was sent from the Link2Investors chat system.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Helper methods
     */
    private function get_thread_info($thread_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_chat_threads WHERE id = %d",
            $thread_id
        ), ARRAY_A);
    }
    
    private function user_wants_notifications($user_id, $notification_type) {
        return get_user_meta($user_id, "l2i_chat_notify_{$notification_type}", true) !== 'no';
    }
    
    private function is_user_recently_active($user_id) {
        $last_seen = get_user_meta($user_id, 'l2i_chat_last_seen', true);
        return $last_seen && (time() - strtotime($last_seen)) < 300; // 5 minutes
    }
    
    private function get_message_preview($message) {
        if ($message['message_type'] === 'zoom_invite') {
            return 'Zoom meeting invitation';
        } elseif ($message['attachment_id']) {
            return 'Shared a file: ' . ($message['message_content'] ?: 'File attachment');
        } else {
            return wp_trim_words($message['message_content'], 15);
        }
    }
    
    private function get_conversation_url($thread_id) {
        return home_url('/messages/?thread_id=' . $thread_id);
    }
    
    private function get_messages_url() {
        return home_url('/messages/');
    }
    
    private function get_user_profile_url($user_id) {
        return home_url('/profile/?user_id=' . $user_id);
    }
    
    private function get_unsubscribe_url($user_id, $notification_type) {
        return home_url('/unsubscribe/?user_id=' . $user_id . '&type=' . $notification_type . '&token=' . wp_hash($user_id . $notification_type));
    }
    
    private function get_user_digest_data($user_id, $frequency) {
        global $wpdb;
        
        $interval = ($frequency === 'daily') ? '1 DAY' : '7 DAY';
        
        $data = array(
            'total_messages' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}l2i_chat_messages m
                 INNER JOIN {$wpdb->prefix}l2i_chat_threads t ON m.thread_id = t.id
                 WHERE (t.user1_id = %d OR t.user2_id = %d)
                 AND m.sender_id != %d
                 AND m.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})",
                $user_id, $user_id, $user_id
            )),
            
            'new_conversations' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}l2i_chat_threads t
                 WHERE (t.user1_id = %d OR t.user2_id = %d)
                 AND t.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})",
                $user_id, $user_id
            )),
            
            'active_conversations' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT t.id) FROM {$wpdb->prefix}l2i_chat_threads t
                 INNER JOIN {$wpdb->prefix}l2i_chat_messages m ON t.id = m.thread_id
                 WHERE (t.user1_id = %d OR t.user2_id = %d)
                 AND m.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})",
                $user_id, $user_id
            )),
            
            'unread_count' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}l2i_chat_messages m
                 INNER JOIN {$wpdb->prefix}l2i_chat_threads t ON m.thread_id = t.id
                 WHERE (t.user1_id = %d OR t.user2_id = %d)
                 AND m.sender_id != %d
                 AND m.is_read = 0",
                $user_id, $user_id, $user_id
            ))
        );
        
        return $data;
    }
    
    private function log_notification($user_id, $type, $data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'l2i_notification_log',
            array(
                'user_id' => $user_id,
                'notification_type' => $type,
                'data' => json_encode($data),
                'sent_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    public function schedule_cron_jobs() {
        if (!wp_next_scheduled('l2i_send_digest_notifications')) {
            wp_schedule_event(time(), 'daily', 'l2i_send_digest_notifications');
        }
    }
}