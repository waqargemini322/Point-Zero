<?php
/**
 * Zoom Integration System
 * Handles Zoom API integration, meeting creation, and credit management
 */

defined('ABSPATH') || exit;

class L2I_Zoom {
    
    private static $instance = null;
    private $db;
    private $credits;
    
    // Zoom API Configuration
    private $api_key;
    private $api_secret;
    private $base_url = 'https://api.zoom.us/v2/';
    private $jwt_token;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = L2I_Database::get_instance();
        $this->credits = L2I_Credits::get_instance();
        
        $this->api_key = get_option('l2i_zoom_api_key', '');
        $this->api_secret = get_option('l2i_zoom_api_secret', '');
        
        $this->init_hooks();
        $this->schedule_cleanup();
    }
    
    private function init_hooks() {
        // AJAX hooks
        add_action('wp_ajax_l2i_create_zoom_meeting', array($this, 'ajax_create_meeting'));
        add_action('wp_ajax_l2i_join_zoom_meeting', array($this, 'ajax_join_meeting'));
        add_action('wp_ajax_l2i_end_zoom_meeting', array($this, 'ajax_end_meeting'));
        add_action('wp_ajax_l2i_get_meeting_info', array($this, 'ajax_get_meeting_info'));
        
        // Integration hooks for messaging system
        add_action('wp_ajax_l2i_send_zoom_invite', array($this, 'ajax_send_zoom_invite'));
        add_filter('projecttheme_chat_actions', array($this, 'add_zoom_button_to_chat'), 10, 2);
        
        // Cron hooks
        add_action('l2i_zoom_cleanup', array($this, 'cleanup_old_meetings'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'register_zoom_settings'));
    }
    
    /**
     * Schedule cleanup of old meetings
     */
    private function schedule_cleanup() {
        if (!wp_next_scheduled('l2i_zoom_cleanup')) {
            wp_schedule_event(time(), 'daily', 'l2i_zoom_cleanup');
        }
    }
    
    /**
     * Generate JWT token for Zoom API
     */
    private function generate_jwt_token() {
        if (empty($this->api_key) || empty($this->api_secret)) {
            return false;
        }
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'iss' => $this->api_key,
            'exp' => time() + 3600 // 1 hour expiration
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->api_secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Make API request to Zoom
     */
    private function make_api_request($endpoint, $method = 'GET', $data = null) {
        $jwt_token = $this->generate_jwt_token();
        
        if (!$jwt_token) {
            return new WP_Error('api_config_error', __('Zoom API configuration is incomplete.', 'l2i-membership'));
        }
        
        $url = $this->base_url . ltrim($endpoint, '/');
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $jwt_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        
        if ($status_code >= 400) {
            $error_message = $decoded_body['message'] ?? __('Zoom API error occurred.', 'l2i-membership');
            return new WP_Error('zoom_api_error', $error_message, array('status_code' => $status_code));
        }
        
        return $decoded_body;
    }
    
    /**
     * Create a Zoom meeting
     */
    public function create_meeting($host_user_id, $participant_user_id, $thread_id = null, $options = array()) {
        // Validate users
        if (!get_userdata($host_user_id) || !get_userdata($participant_user_id)) {
            return new WP_Error('invalid_users', __('Invalid user IDs provided.', 'l2i-membership'));
        }
        
        // Check if host has zoom credits
        if (!$this->credits->has_sufficient_credits($host_user_id, 'zoom_invites', 1)) {
            return new WP_Error('insufficient_credits', __('Insufficient Zoom credits.', 'l2i-membership'));
        }
        
        // Check if host has zoom capability
        $host = get_userdata($host_user_id);
        if (!$host->has_cap('zoom_meetings')) {
            return new WP_Error('no_permission', __('Your membership level does not include Zoom meetings.', 'l2i-membership'));
        }
        
        // Default meeting settings
        $default_options = array(
            'topic' => sprintf(__('Link2Investors Meeting - %s', 'l2i-membership'), date('Y-m-d H:i')),
            'type' => 1, // Instant meeting
            'duration' => 60, // 1 hour
            'timezone' => wp_timezone_string(),
            'password' => $this->generate_meeting_password(),
            'settings' => array(
                'host_video' => true,
                'participant_video' => true,
                'cn_meeting' => false,
                'in_meeting' => false,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'watermark' => false,
                'use_pmi' => false,
                'approval_type' => 2,
                'audio' => 'both',
                'auto_recording' => 'none',
                'waiting_room' => true
            )
        );
        
        $meeting_data = wp_parse_args($options, $default_options);
        
        // Create meeting via Zoom API
        $api_response = $this->make_api_request('users/me/meetings', 'POST', $meeting_data);
        
        if (is_wp_error($api_response)) {
            return $api_response;
        }
        
        // Save meeting to database
        $meeting_record = array(
            'meeting_id' => $api_response['id'],
            'host_user_id' => $host_user_id,
            'participant_user_id' => $participant_user_id,
            'thread_id' => $thread_id,
            'meeting_url' => $api_response['start_url'],
            'join_url' => $api_response['join_url'],
            'password' => $meeting_data['password'],
            'start_time' => current_time('mysql'),
            'duration' => $meeting_data['duration'],
            'status' => 'scheduled',
            'created_at' => current_time('mysql')
        );
        
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'l2i_zoom_meetings',
            $meeting_record,
            array('%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Failed to save meeting information.', 'l2i-membership'));
        }
        
        // Deduct zoom credit
        $credit_result = $this->credits->use_credits(
            $host_user_id, 
            'zoom_invites', 
            1, 
            sprintf(__('Zoom meeting with %s', 'l2i-membership'), get_userdata($participant_user_id)->display_name),
            array(
                'meeting_id' => $api_response['id'],
                'participant_id' => $participant_user_id,
                'thread_id' => $thread_id
            )
        );
        
        if (is_wp_error($credit_result)) {
            // Rollback meeting creation if credit deduction fails
            $wpdb->delete(
                $wpdb->prefix . 'l2i_zoom_meetings',
                array('meeting_id' => $api_response['id']),
                array('%s')
            );
            return $credit_result;
        }
        
        // Log meeting creation
        $this->db->log_activity($host_user_id, 'zoom_meeting_created', array(
            'meeting_id' => $api_response['id'],
            'participant_id' => $participant_user_id,
            'thread_id' => $thread_id,
            'meeting_url' => $api_response['start_url']
        ));
        
        // Send notification to participant
        $this->send_meeting_notification($host_user_id, $participant_user_id, $meeting_record);
        
        return array(
            'meeting_id' => $api_response['id'],
            'start_url' => $api_response['start_url'],
            'join_url' => $api_response['join_url'],
            'password' => $meeting_data['password'],
            'database_id' => $wpdb->insert_id
        );
    }
    
    /**
     * Get meeting information
     */
    public function get_meeting_info($meeting_id, $user_id = null) {
        global $wpdb;
        
        $meeting = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_zoom_meetings WHERE meeting_id = %s",
            $meeting_id
        ), ARRAY_A);
        
        if (!$meeting) {
            return new WP_Error('meeting_not_found', __('Meeting not found.', 'l2i-membership'));
        }
        
        // Check if user has access to this meeting
        if ($user_id && !in_array($user_id, array($meeting['host_user_id'], $meeting['participant_user_id']))) {
            return new WP_Error('no_access', __('You do not have access to this meeting.', 'l2i-membership'));
        }
        
        // Get live meeting info from Zoom API
        $api_response = $this->make_api_request("meetings/{$meeting_id}");
        
        if (!is_wp_error($api_response)) {
            $meeting['live_status'] = $api_response['status'] ?? 'unknown';
            $meeting['actual_start_time'] = $api_response['start_time'] ?? null;
        }
        
        return $meeting;
    }
    
    /**
     * End a meeting
     */
    public function end_meeting($meeting_id, $user_id) {
        $meeting = $this->get_meeting_info($meeting_id);
        
        if (is_wp_error($meeting)) {
            return $meeting;
        }
        
        // Only host can end the meeting
        if ($user_id != $meeting['host_user_id']) {
            return new WP_Error('no_permission', __('Only the meeting host can end the meeting.', 'l2i-membership'));
        }
        
        // End meeting via API
        $api_response = $this->make_api_request("meetings/{$meeting_id}/status", 'PATCH', array(
            'action' => 'end'
        ));
        
        if (is_wp_error($api_response)) {
            return $api_response;
        }
        
        // Update database
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'l2i_zoom_meetings',
            array(
                'status' => 'ended',
                'updated_at' => current_time('mysql')
            ),
            array('meeting_id' => $meeting_id),
            array('%s', '%s'),
            array('%s')
        );
        
        // Log meeting end
        $this->db->log_activity($user_id, 'zoom_meeting_ended', array(
            'meeting_id' => $meeting_id
        ));
        
        return true;
    }
    
    /**
     * AJAX: Create meeting
     */
    public function ajax_create_meeting() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $host_user_id = get_current_user_id();
        $participant_user_id = (int) $_POST['participant_id'];
        $thread_id = (int) ($_POST['thread_id'] ?? null);
        
        $options = array();
        if (!empty($_POST['topic'])) {
            $options['topic'] = sanitize_text_field($_POST['topic']);
        }
        if (!empty($_POST['duration'])) {
            $options['duration'] = (int) $_POST['duration'];
        }
        
        $result = $this->create_meeting($host_user_id, $participant_user_id, $thread_id, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Meeting created successfully!', 'l2i-membership'),
            'meeting' => $result
        ));
    }
    
    /**
     * AJAX: Join meeting
     */
    public function ajax_join_meeting() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $meeting_id = sanitize_text_field($_POST['meeting_id']);
        $user_id = get_current_user_id();
        
        $meeting = $this->get_meeting_info($meeting_id, $user_id);
        
        if (is_wp_error($meeting)) {
            wp_send_json_error(array(
                'message' => $meeting->get_error_message()
            ));
        }
        
        // Determine join URL based on user role in meeting
        $join_url = ($user_id == $meeting['host_user_id']) 
            ? $meeting['meeting_url'] 
            : $meeting['join_url'];
        
        // Log join attempt
        $this->db->log_activity($user_id, 'zoom_meeting_joined', array(
            'meeting_id' => $meeting_id,
            'role' => ($user_id == $meeting['host_user_id']) ? 'host' : 'participant'
        ));
        
        wp_send_json_success(array(
            'join_url' => $join_url,
            'password' => $meeting['password'],
            'meeting_info' => $meeting
        ));
    }
    
    /**
     * AJAX: End meeting
     */
    public function ajax_end_meeting() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $meeting_id = sanitize_text_field($_POST['meeting_id']);
        $user_id = get_current_user_id();
        
        $result = $this->end_meeting($meeting_id, $user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Meeting ended successfully.', 'l2i-membership')
        ));
    }
    
    /**
     * AJAX: Get meeting info
     */
    public function ajax_get_meeting_info() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $meeting_id = sanitize_text_field($_POST['meeting_id']);
        $user_id = get_current_user_id();
        
        $meeting = $this->get_meeting_info($meeting_id, $user_id);
        
        if (is_wp_error($meeting)) {
            wp_send_json_error(array(
                'message' => $meeting->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'meeting' => $meeting
        ));
    }
    
    /**
     * AJAX: Send Zoom invite in chat
     */
    public function ajax_send_zoom_invite() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $host_user_id = get_current_user_id();
        $participant_user_id = (int) $_POST['recipient_id'];
        $thread_id = (int) $_POST['thread_id'];
        
        // Create the meeting
        $meeting_result = $this->create_meeting($host_user_id, $participant_user_id, $thread_id);
        
        if (is_wp_error($meeting_result)) {
            wp_send_json_error(array(
                'message' => $meeting_result->get_error_message()
            ));
        }
        
        // Send meeting info as chat message
        $this->send_meeting_as_chat_message($thread_id, $host_user_id, $participant_user_id, $meeting_result);
        
        wp_send_json_success(array(
            'message' => __('Zoom meeting invitation sent!', 'l2i-membership'),
            'meeting' => $meeting_result
        ));
    }
    
    /**
     * Add Zoom button to chat interface
     */
    public function add_zoom_button_to_chat($actions, $thread_info) {
        $current_user_id = get_current_user_id();
        
        // Check if user has zoom capability
        $user = get_userdata($current_user_id);
        if (!$user->has_cap('zoom_meetings')) {
            return $actions;
        }
        
        // Check if user has zoom credits
        if (!$this->credits->has_sufficient_credits($current_user_id, 'zoom_invites', 1)) {
            $actions[] = array(
                'type' => 'button',
                'class' => 'l2i-zoom-btn disabled',
                'text' => __('Zoom Meeting (No Credits)', 'l2i-membership'),
                'disabled' => true
            );
            return $actions;
        }
        
        $actions[] = array(
            'type' => 'button',
            'class' => 'l2i-zoom-btn',
            'text' => __('Start Zoom Meeting', 'l2i-membership'),
            'data' => array(
                'thread-id' => $thread_info['id'],
                'recipient-id' => $this->get_other_user_id($thread_info, $current_user_id)
            )
        );
        
        return $actions;
    }
    
    /**
     * Send meeting notification to participant
     */
    private function send_meeting_notification($host_user_id, $participant_user_id, $meeting_data) {
        $host = get_userdata($host_user_id);
        $participant = get_userdata($participant_user_id);
        
        // Email notification
        $subject = sprintf(__('Zoom Meeting Invitation from %s', 'l2i-membership'), $host->display_name);
        
        $message = sprintf(
            __("Hello %s,\n\n%s has invited you to a Zoom meeting on Link2Investors.\n\nMeeting Details:\n- Meeting ID: %s\n- Password: %s\n- Join URL: %s\n\nYou can join the meeting directly from your Link2Investors messages.\n\nBest regards,\nLink2Investors Team", 'l2i-membership'),
            $participant->display_name,
            $host->display_name,
            $meeting_data['meeting_id'],
            $meeting_data['password'],
            $meeting_data['join_url']
        );
        
        wp_mail($participant->user_email, $subject, $message);
        
        // In-app notification (if notification system exists)
        do_action('l2i_send_notification', $participant_user_id, 'zoom_meeting_invite', array(
            'host_name' => $host->display_name,
            'meeting_id' => $meeting_data['meeting_id'],
            'message' => sprintf(__('%s invited you to a Zoom meeting', 'l2i-membership'), $host->display_name)
        ));
    }
    
    /**
     * Send meeting info as chat message
     */
    private function send_meeting_as_chat_message($thread_id, $host_user_id, $participant_user_id, $meeting_data) {
        $host = get_userdata($host_user_id);
        
        $message_content = sprintf(
            __("🎥 Zoom Meeting Invitation\n\nI've created a Zoom meeting for us!\n\n📋 Meeting ID: %s\n🔐 Password: %s\n⏰ Duration: %d minutes\n\n👆 Click the link below to join:\n%s", 'l2i-membership'),
            $meeting_data['meeting_id'],
            $meeting_data['password'],
            60, // Default duration
            $meeting_data['join_url']
        );
        
        // Insert message into chat system
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'project_pm',
            array(
                'threadid' => $thread_id,
                'initiator' => $host_user_id,
                'user' => $participant_user_id,
                'content' => $message_content,
                'datemade' => current_time('timestamp'),
                'file_attached' => 'zoom_meeting:' . $meeting_data['meeting_id']
            ),
            array('%d', '%d', '%d', '%s', '%d', '%s')
        );
        
        // Update thread last activity
        $wpdb->update(
            $wpdb->prefix . 'project_pm_threads',
            array('lastupdate' => current_time('timestamp')),
            array('id' => $thread_id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Generate secure meeting password
     */
    private function generate_meeting_password($length = 8) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[wp_rand(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Get the other user ID in a thread
     */
    private function get_other_user_id($thread_info, $current_user_id) {
        return ($thread_info['user1'] == $current_user_id) 
            ? $thread_info['user2'] 
            : $thread_info['user1'];
    }
    
    /**
     * Cleanup old meetings
     */
    public function cleanup_old_meetings() {
        global $wpdb;
        
        // Delete meetings older than 30 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}l2i_zoom_meetings 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        error_log('L2I Zoom: Cleanup completed at ' . current_time('mysql'));
    }
    
    /**
     * Register admin settings
     */
    public function register_zoom_settings() {
        register_setting('l2i_membership_settings', 'l2i_zoom_api_key');
        register_setting('l2i_membership_settings', 'l2i_zoom_api_secret');
        
        add_settings_section(
            'l2i_zoom_settings',
            __('Zoom Integration Settings', 'l2i-membership'),
            array($this, 'zoom_settings_section_callback'),
            'l2i_membership_settings'
        );
        
        add_settings_field(
            'l2i_zoom_api_key',
            __('Zoom API Key', 'l2i-membership'),
            array($this, 'zoom_api_key_callback'),
            'l2i_membership_settings',
            'l2i_zoom_settings'
        );
        
        add_settings_field(
            'l2i_zoom_api_secret',
            __('Zoom API Secret', 'l2i-membership'),
            array($this, 'zoom_api_secret_callback'),
            'l2i_membership_settings',
            'l2i_zoom_settings'
        );
    }
    
    /**
     * Zoom settings section callback
     */
    public function zoom_settings_section_callback() {
        echo '<p>' . __('Configure your Zoom API credentials to enable video meetings.', 'l2i-membership') . '</p>';
    }
    
    /**
     * Zoom API key callback
     */
    public function zoom_api_key_callback() {
        $value = get_option('l2i_zoom_api_key', '');
        echo '<input type="text" id="l2i_zoom_api_key" name="l2i_zoom_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    /**
     * Zoom API secret callback
     */
    public function zoom_api_secret_callback() {
        $value = get_option('l2i_zoom_api_secret', '');
        echo '<input type="password" id="l2i_zoom_api_secret" name="l2i_zoom_api_secret" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    /**
     * Test Zoom API connection
     */
    public function test_api_connection() {
        $response = $this->make_api_request('users/me');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return array(
            'status' => 'success',
            'message' => __('Zoom API connection successful!', 'l2i-membership'),
            'user_info' => $response
        );
    }
    
    /**
     * Get user's meeting history
     */
    public function get_user_meeting_history($user_id, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_zoom_meetings 
             WHERE host_user_id = %d OR participant_user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id, $user_id, $limit
        ), ARRAY_A);
    }
}