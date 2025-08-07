<?php
/**
 * Credits Management System
 * Handles all credit operations with validation, tracking, and optimization
 */

defined('ABSPATH') || exit;

class L2I_Credits {
    
    private static $instance = null;
    private $db;
    
    // Credit types
    const CREDIT_TYPES = array(
        'bid_credits' => 'Bid Credits',
        'connection_credits' => 'Connection Credits', 
        'zoom_invites' => 'Zoom Invites'
    );
    
    // Actions
    const ACTIONS = array(
        'add' => 'Add',
        'subtract' => 'Subtract',
        'set' => 'Set'
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = L2I_Database::get_instance();
        $this->init_hooks();
        $this->schedule_cron_jobs();
    }
    
    private function init_hooks() {
        // AJAX hooks for credit operations
        add_action('wp_ajax_l2i_use_credit', array($this, 'ajax_use_credit'));
        add_action('wp_ajax_l2i_check_credits', array($this, 'ajax_check_credits'));
        add_action('wp_ajax_l2i_get_credit_history', array($this, 'ajax_get_credit_history'));
        
        // Cron hooks
        add_action('l2i_monthly_credit_renewal', array($this, 'process_monthly_renewals'));
        add_action('l2i_yearly_credit_renewal', array($this, 'process_yearly_renewals'));
        add_action('l2i_credit_cleanup', array($this, 'cleanup_expired_data'));
        
        // Integration hooks
        add_filter('projecttheme_before_bid_submission', array($this, 'check_bid_credits'), 10, 2);
        add_filter('projecttheme_before_message_send', array($this, 'check_connection_credits'), 10, 2);
        add_filter('projecttheme_before_zoom_create', array($this, 'check_zoom_credits'), 10, 2);
    }
    
    /**
     * Schedule cron jobs for credit management
     */
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('l2i_monthly_credit_renewal')) {
            wp_schedule_event(time(), 'daily', 'l2i_monthly_credit_renewal');
        }
        
        if (!wp_next_scheduled('l2i_yearly_credit_renewal')) {
            wp_schedule_event(time(), 'daily', 'l2i_yearly_credit_renewal');
        }
        
        if (!wp_next_scheduled('l2i_credit_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'l2i_credit_cleanup');
        }
    }
    
    /**
     * Get user credits with caching and validation
     */
    public function get_user_credits($user_id) {
        if (!$this->validate_user_id($user_id)) {
            return false;
        }
        
        return $this->db->get_user_credits($user_id);
    }
    
    /**
     * Check if user has enough credits for an action
     */
    public function has_sufficient_credits($user_id, $credit_type, $amount = 1) {
        if (!$this->validate_credit_operation($user_id, $credit_type, $amount)) {
            return false;
        }
        
        $credits = $this->get_user_credits($user_id);
        if (!$credits) {
            return false;
        }
        
        return (int) $credits[$credit_type] >= $amount;
    }
    
    /**
     * Use credits for an action (with validation and logging)
     */
    public function use_credits($user_id, $credit_type, $amount = 1, $description = '', $context = array()) {
        // Validate the operation
        if (!$this->validate_credit_operation($user_id, $credit_type, $amount)) {
            return new WP_Error('invalid_operation', __('Invalid credit operation.', 'l2i-membership'));
        }
        
        // Check if user has sufficient credits
        if (!$this->has_sufficient_credits($user_id, $credit_type, $amount)) {
            return new WP_Error('insufficient_credits', __('Insufficient credits for this action.', 'l2i-membership'));
        }
        
        // Perform the credit deduction
        $result = $this->db->update_user_credits($user_id, $credit_type, $amount, 'subtract', $description);
        
        if ($result) {
            // Log the usage
            $this->log_credit_usage($user_id, $credit_type, $amount, $description, $context);
            
            // Trigger action for integrations
            do_action('l2i_credits_used', $user_id, $credit_type, $amount, $context);
            
            return true;
        }
        
        return new WP_Error('credit_update_failed', __('Failed to update credits.', 'l2i-membership'));
    }
    
    /**
     * Add credits to user account
     */
    public function add_credits($user_id, $credit_type, $amount, $description = '', $context = array()) {
        if (!$this->validate_credit_operation($user_id, $credit_type, $amount)) {
            return new WP_Error('invalid_operation', __('Invalid credit operation.', 'l2i-membership'));
        }
        
        $result = $this->db->update_user_credits($user_id, $credit_type, $amount, 'add', $description);
        
        if ($result) {
            $this->log_credit_usage($user_id, $credit_type, $amount, $description, $context, 'added');
            do_action('l2i_credits_added', $user_id, $credit_type, $amount, $context);
            return true;
        }
        
        return new WP_Error('credit_update_failed', __('Failed to add credits.', 'l2i-membership'));
    }
    
    /**
     * Set specific credit amount
     */
    public function set_credits($user_id, $credit_type, $amount, $description = '', $context = array()) {
        if (!$this->validate_credit_operation($user_id, $credit_type, $amount, false)) {
            return new WP_Error('invalid_operation', __('Invalid credit operation.', 'l2i-membership'));
        }
        
        $result = $this->db->update_user_credits($user_id, $credit_type, $amount, 'set', $description);
        
        if ($result) {
            $this->log_credit_usage($user_id, $credit_type, $amount, $description, $context, 'set');
            do_action('l2i_credits_set', $user_id, $credit_type, $amount, $context);
            return true;
        }
        
        return new WP_Error('credit_update_failed', __('Failed to set credits.', 'l2i-membership'));
    }
    
    /**
     * Get credit usage history for a user
     */
    public function get_credit_history($user_id, $limit = 50, $offset = 0, $credit_type = null) {
        if (!$this->validate_user_id($user_id)) {
            return false;
        }
        
        global $wpdb;
        
        $where = array("user_id = %d");
        $values = array($user_id);
        
        if ($credit_type && in_array($credit_type, array_keys(self::CREDIT_TYPES))) {
            $where[] = "credit_type = %s";
            $values[] = $credit_type;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_credit_history 
             WHERE $where_clause 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            array_merge($values, array($limit, $offset))
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get credit statistics for a user
     */
    public function get_credit_statistics($user_id, $period = '30 days') {
        if (!$this->validate_user_id($user_id)) {
            return false;
        }
        
        global $wpdb;
        
        $stats = array();
        
        foreach (self::CREDIT_TYPES as $type => $label) {
            // Total used in period
            $used = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) 
                 FROM {$wpdb->prefix}l2i_credit_history 
                 WHERE user_id = %d 
                 AND credit_type = %s 
                 AND action = 'subtract' 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $user_id, $type, $period
            ));
            
            // Total added in period
            $added = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) 
                 FROM {$wpdb->prefix}l2i_credit_history 
                 WHERE user_id = %d 
                 AND credit_type = %s 
                 AND action = 'add' 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $user_id, $type, $period
            ));
            
            $stats[$type] = array(
                'label' => $label,
                'used' => (int) $used,
                'added' => (int) $added,
                'net' => (int) $added - (int) $used
            );
        }
        
        return $stats;
    }
    
    /**
     * AJAX: Use credit
     */
    public function ajax_use_credit() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        $credit_type = sanitize_text_field($_POST['credit_type']);
        $amount = (int) $_POST['amount'];
        $description = sanitize_text_field($_POST['description'] ?? '');
        $context = $_POST['context'] ?? array();
        
        $result = $this->use_credits($user_id, $credit_type, $amount, $description, $context);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ));
        }
        
        // Get updated credits
        $updated_credits = $this->get_user_credits($user_id);
        
        wp_send_json_success(array(
            'message' => __('Credits used successfully.', 'l2i-membership'),
            'remaining_credits' => $updated_credits[$credit_type] ?? 0,
            'all_credits' => $updated_credits
        ));
    }
    
    /**
     * AJAX: Check credits
     */
    public function ajax_check_credits() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        $credit_type = sanitize_text_field($_POST['credit_type']);
        $amount = (int) ($_POST['amount'] ?? 1);
        
        $has_credits = $this->has_sufficient_credits($user_id, $credit_type, $amount);
        $current_credits = $this->get_user_credits($user_id);
        
        wp_send_json_success(array(
            'has_sufficient' => $has_credits,
            'current_amount' => $current_credits[$credit_type] ?? 0,
            'required_amount' => $amount,
            'all_credits' => $current_credits
        ));
    }
    
    /**
     * AJAX: Get credit history
     */
    public function ajax_get_credit_history() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        $limit = (int) ($_POST['limit'] ?? 20);
        $offset = (int) ($_POST['offset'] ?? 0);
        $credit_type = sanitize_text_field($_POST['credit_type'] ?? '');
        
        $history = $this->get_credit_history($user_id, $limit, $offset, $credit_type);
        $stats = $this->get_credit_statistics($user_id);
        
        wp_send_json_success(array(
            'history' => $history,
            'statistics' => $stats,
            'has_more' => count($history) === $limit
        ));
    }
    
    /**
     * Integration: Check bid credits before submission
     */
    public function check_bid_credits($allowed, $project_id) {
        $user_id = get_current_user_id();
        
        if (!$this->has_sufficient_credits($user_id, 'bid_credits', 1)) {
            return false;
        }
        
        return $allowed;
    }
    
    /**
     * Integration: Check connection credits before messaging
     */
    public function check_connection_credits($allowed, $recipient_id) {
        $user_id = get_current_user_id();
        
        // Check if this is a new conversation
        if ($this->is_new_conversation($user_id, $recipient_id)) {
            if (!$this->has_sufficient_credits($user_id, 'connection_credits', 1)) {
                return false;
            }
        }
        
        return $allowed;
    }
    
    /**
     * Integration: Check zoom credits before meeting creation
     */
    public function check_zoom_credits($allowed, $recipient_id) {
        $user_id = get_current_user_id();
        
        if (!$this->has_sufficient_credits($user_id, 'zoom_invites', 1)) {
            return false;
        }
        
        return $allowed;
    }
    
    /**
     * Process monthly credit renewals
     */
    public function process_monthly_renewals() {
        global $wpdb;
        
        // Get users with monthly memberships due for renewal
        $users_to_renew = $wpdb->get_results(
            "SELECT user_id, membership_type 
             FROM {$wpdb->prefix}l2i_user_credits 
             WHERE membership_type LIKE '%_monthly' 
             AND renewal_date <= NOW() 
             AND renewal_date IS NOT NULL"
        );
        
        foreach ($users_to_renew as $user_data) {
            $this->renew_user_credits($user_data->user_id, $user_data->membership_type);
        }
        
        $this->log_renewal_batch('monthly', count($users_to_renew));
    }
    
    /**
     * Process yearly credit renewals
     */
    public function process_yearly_renewals() {
        global $wpdb;
        
        $users_to_renew = $wpdb->get_results(
            "SELECT user_id, membership_type 
             FROM {$wpdb->prefix}l2i_user_credits 
             WHERE membership_type LIKE '%_yearly' 
             AND renewal_date <= NOW() 
             AND renewal_date IS NOT NULL"
        );
        
        foreach ($users_to_renew as $user_data) {
            $this->renew_user_credits($user_data->user_id, $user_data->membership_type);
        }
        
        $this->log_renewal_batch('yearly', count($users_to_renew));
    }
    
    /**
     * Renew credits for a specific user
     */
    private function renew_user_credits($user_id, $membership_type) {
        $roles = L2I_Roles::get_instance();
        $new_credits = $roles->get_role_credit_allocation($membership_type);
        
        if (!$new_credits) {
            return false;
        }
        
        global $wpdb;
        
        // Calculate next renewal date
        $next_renewal = strpos($membership_type, 'yearly') !== false 
            ? date('Y-m-d H:i:s', strtotime('+1 year'))
            : date('Y-m-d H:i:s', strtotime('+1 month'));
        
        // Update credits with rollover logic
        $current_credits = $this->get_user_credits($user_id);
        
        $updated_credits = array(
            'bid_credits' => $this->calculate_rollover($current_credits['bid_credits'], $new_credits['bid_credits']),
            'connection_credits' => $this->calculate_rollover($current_credits['connection_credits'], $new_credits['connection_credits']),
            'zoom_invites' => $this->calculate_rollover($current_credits['zoom_invites'], $new_credits['zoom_invites']),
            'renewal_date' => $next_renewal,
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'l2i_user_credits',
            $updated_credits,
            array('user_id' => $user_id),
            array('%d', '%d', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result) {
            // Clear cache
            wp_cache_delete('l2i_user_credits_' . $user_id, 'l2i_membership');
            
            // Log renewal
            $this->db->log_activity($user_id, 'credits_renewed', array(
                'membership_type' => $membership_type,
                'new_credits' => $updated_credits,
                'next_renewal' => $next_renewal
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Calculate rollover credits (allow up to 50% rollover)
     */
    private function calculate_rollover($current, $new_allocation) {
        $max_rollover = floor($new_allocation * 0.5); // 50% rollover limit
        $rollover = min($current, $max_rollover);
        return $new_allocation + $rollover;
    }
    
    /**
     * Validate credit operation parameters
     */
    private function validate_credit_operation($user_id, $credit_type, $amount, $check_positive = true) {
        // Validate user ID
        if (!$this->validate_user_id($user_id)) {
            return false;
        }
        
        // Validate credit type
        if (!in_array($credit_type, array_keys(self::CREDIT_TYPES))) {
            return false;
        }
        
        // Validate amount
        if (!is_numeric($amount) || ($check_positive && $amount <= 0)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate user ID
     */
    private function validate_user_id($user_id) {
        return is_numeric($user_id) && $user_id > 0 && get_userdata($user_id) !== false;
    }
    
    /**
     * Log credit usage with context
     */
    private function log_credit_usage($user_id, $credit_type, $amount, $description, $context, $action = 'used') {
        $this->db->log_activity($user_id, "credits_{$action}", array(
            'credit_type' => $credit_type,
            'amount' => $amount,
            'description' => $description,
            'context' => $context,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Check if this is a new conversation
     */
    private function is_new_conversation($user1_id, $user2_id) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}project_pm_threads 
             WHERE (user1 = %d AND user2 = %d) 
             OR (user1 = %d AND user2 = %d)",
            $user1_id, $user2_id, $user2_id, $user1_id
        ));
        
        return $existing == 0;
    }
    
    /**
     * Log renewal batch processing
     */
    private function log_renewal_batch($type, $count) {
        error_log("L2I Credits: Processed {$count} {$type} renewals at " . current_time('mysql'));
        
        // Store in options for admin dashboard
        $renewal_logs = get_option('l2i_renewal_logs', array());
        $renewal_logs[] = array(
            'type' => $type,
            'count' => $count,
            'timestamp' => current_time('timestamp')
        );
        
        // Keep only last 30 entries
        if (count($renewal_logs) > 30) {
            $renewal_logs = array_slice($renewal_logs, -30);
        }
        
        update_option('l2i_renewal_logs', $renewal_logs);
    }
    
    /**
     * Cleanup expired data
     */
    public function cleanup_expired_data() {
        $this->db->cleanup_old_logs();
        
        // Additional cleanup specific to credits
        global $wpdb;
        
        // Remove very old credit history (older than 2 years)
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}l2i_credit_history 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR)"
        );
        
        error_log('L2I Credits: Cleanup completed at ' . current_time('mysql'));
    }
    
    /**
     * Get credit type display name
     */
    public function get_credit_type_label($credit_type) {
        return self::CREDIT_TYPES[$credit_type] ?? ucfirst(str_replace('_', ' ', $credit_type));
    }
    
    /**
     * Get all credit types
     */
    public function get_credit_types() {
        return self::CREDIT_TYPES;
    }
    
    /**
     * Bulk update credits for multiple users
     */
    public function bulk_update_credits($user_ids, $credit_type, $amount, $action = 'add', $description = '') {
        $results = array();
        
        foreach ($user_ids as $user_id) {
            switch ($action) {
                case 'add':
                    $result = $this->add_credits($user_id, $credit_type, $amount, $description);
                    break;
                case 'subtract':
                    $result = $this->use_credits($user_id, $credit_type, $amount, $description);
                    break;
                case 'set':
                    $result = $this->set_credits($user_id, $credit_type, $amount, $description);
                    break;
                default:
                    $result = new WP_Error('invalid_action', 'Invalid bulk action');
            }
            
            $results[$user_id] = $result;
        }
        
        return $results;
    }
}