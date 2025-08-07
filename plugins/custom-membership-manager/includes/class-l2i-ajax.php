<?php
/**
 * AJAX Handler Class
 * Manages all AJAX requests for the membership system
 */

defined('ABSPATH') || exit;

class L2I_Ajax {
    
    private static $instance = null;
    private $credits;
    private $restrictions;
    private $zoom;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->credits = L2I_Credits::get_instance();
        $this->restrictions = L2I_Restrictions::get_instance();
        $this->zoom = L2I_Zoom::get_instance();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // General membership AJAX actions
        add_action('wp_ajax_l2i_get_user_info', array($this, 'get_user_info'));
        add_action('wp_ajax_l2i_check_credits', array($this, 'check_credits'));
        add_action('wp_ajax_l2i_get_restrictions', array($this, 'get_restrictions'));
        
        // Credit management AJAX actions
        add_action('wp_ajax_l2i_use_credits', array($this, 'use_credits'));
        add_action('wp_ajax_l2i_get_credit_history', array($this, 'get_credit_history'));
        
        // Membership upgrade AJAX actions
        add_action('wp_ajax_l2i_upgrade_membership', array($this, 'upgrade_membership'));
        add_action('wp_ajax_l2i_downgrade_membership', array($this, 'downgrade_membership'));
        
        // Admin AJAX actions
        add_action('wp_ajax_l2i_admin_add_credits', array($this, 'admin_add_credits'));
        add_action('wp_ajax_l2i_admin_change_role', array($this, 'admin_change_role'));
        add_action('wp_ajax_l2i_admin_get_stats', array($this, 'admin_get_stats'));
        
        // Public AJAX actions (for non-logged-in users)
        add_action('wp_ajax_nopriv_l2i_get_membership_plans', array($this, 'get_membership_plans'));
    }
    
    /**
     * Get current user info with membership details
     */
    public function get_user_info() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-membership')
            ));
        }
        
        $user = get_userdata($user_id);
        $credits = $this->credits->get_user_credits($user_id);
        $restrictions = $this->restrictions->get_user_restrictions($user_id);
        
        wp_send_json_success(array(
            'user' => array(
                'id' => $user_id,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles,
                'tier' => $restrictions['tier']
            ),
            'credits' => $credits,
            'restrictions' => $restrictions
        ));
    }
    
    /**
     * Check user credits
     */
    public function check_credits() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-membership')
            ));
        }
        
        $credit_type = sanitize_text_field($_POST['credit_type'] ?? '');
        $amount = (int) ($_POST['amount'] ?? 1);
        
        if (empty($credit_type)) {
            wp_send_json_error(array(
                'message' => __('Credit type is required.', 'l2i-membership')
            ));
        }
        
        $has_credits = $this->credits->has_sufficient_credits($user_id, $credit_type, $amount);
        $current_credits = $this->credits->get_user_credits($user_id);
        
        wp_send_json_success(array(
            'has_sufficient' => $has_credits,
            'current_credits' => $current_credits,
            'requested' => array(
                'type' => $credit_type,
                'amount' => $amount
            )
        ));
    }
    
    /**
     * Get user restrictions
     */
    public function get_restrictions() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-membership')
            ));
        }
        
        $restrictions = $this->restrictions->get_user_restrictions($user_id);
        
        wp_send_json_success($restrictions);
    }
    
    /**
     * Use credits (for frontend actions)
     */
    public function use_credits() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-membership')
            ));
        }
        
        $credit_type = sanitize_text_field($_POST['credit_type']);
        $amount = (int) $_POST['amount'];
        $description = sanitize_text_field($_POST['description'] ?? '');
        $metadata = $_POST['metadata'] ?? array();
        
        $result = $this->credits->use_credits($user_id, $credit_type, $amount, $description, $metadata);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d %s used successfully.', 'l2i-membership'), $amount, $credit_type),
            'remaining_credits' => $this->credits->get_user_credits($user_id)
        ));
    }
    
    /**
     * Get credit history
     */
    public function get_credit_history() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-membership')
            ));
        }
        
        $limit = (int) ($_POST['limit'] ?? 20);
        $offset = (int) ($_POST['offset'] ?? 0);
        $credit_type = sanitize_text_field($_POST['credit_type'] ?? '');
        
        $history = $this->credits->get_credit_history($user_id, $credit_type, $limit, $offset);
        
        wp_send_json_success(array(
            'history' => $history,
            'pagination' => array(
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => count($history) === $limit
            )
        ));
    }
    
    /**
     * Upgrade membership
     */
    public function upgrade_membership() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-membership')
            ));
        }
        
        $new_role = sanitize_text_field($_POST['new_role']);
        
        // Validate the new role
        $valid_roles = L2I_Roles::get_membership_roles();
        if (!array_key_exists($new_role, $valid_roles)) {
            wp_send_json_error(array(
                'message' => __('Invalid membership role.', 'l2i-membership')
            ));
        }
        
        // This would typically integrate with payment processing
        // For now, we'll just simulate the upgrade
        $user = get_userdata($user_id);
        $user->set_role($new_role);
        
        // Add credits for the new tier
        $roles_instance = L2I_Roles::get_instance();
        $roles_instance->assign_tier_credits($user_id, $new_role);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully upgraded to %s membership!', 'l2i-membership'), $valid_roles[$new_role]),
            'new_role' => $new_role,
            'new_restrictions' => $this->restrictions->get_user_restrictions($user_id)
        ));
    }
    
    /**
     * Downgrade membership
     */
    public function downgrade_membership() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-membership')
            ));
        }
        
        $new_role = sanitize_text_field($_POST['new_role']);
        
        // Validate the new role
        $valid_roles = L2I_Roles::get_membership_roles();
        if (!array_key_exists($new_role, $valid_roles)) {
            wp_send_json_error(array(
                'message' => __('Invalid membership role.', 'l2i-membership')
            ));
        }
        
        // Change user role
        $user = get_userdata($user_id);
        $user->set_role($new_role);
        
        // Reset credits for the new tier
        $roles_instance = L2I_Roles::get_instance();
        $roles_instance->assign_tier_credits($user_id, $new_role);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Membership changed to %s.', 'l2i-membership'), $valid_roles[$new_role]),
            'new_role' => $new_role,
            'new_restrictions' => $this->restrictions->get_user_restrictions($user_id)
        ));
    }
    
    /**
     * Admin: Add credits to user
     */
    public function admin_add_credits() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'l2i-membership')
            ));
        }
        
        $user_id = (int) $_POST['user_id'];
        $credit_type = sanitize_text_field($_POST['credit_type']);
        $amount = (int) $_POST['amount'];
        $reason = sanitize_text_field($_POST['reason'] ?? 'Admin adjustment');
        
        if (!get_userdata($user_id)) {
            wp_send_json_error(array(
                'message' => __('Invalid user ID.', 'l2i-membership')
            ));
        }
        
        $result = $this->credits->add_credits($user_id, $credit_type, $amount, $reason);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Added %d %s to user.', 'l2i-membership'), $amount, $credit_type),
            'new_credits' => $this->credits->get_user_credits($user_id)
        ));
    }
    
    /**
     * Admin: Change user role
     */
    public function admin_change_role() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'l2i-membership')
            ));
        }
        
        $user_id = (int) $_POST['user_id'];
        $new_role = sanitize_text_field($_POST['new_role']);
        
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array(
                'message' => __('Invalid user ID.', 'l2i-membership')
            ));
        }
        
        // Validate the new role
        $valid_roles = L2I_Roles::get_membership_roles();
        if (!array_key_exists($new_role, $valid_roles)) {
            wp_send_json_error(array(
                'message' => __('Invalid membership role.', 'l2i-membership')
            ));
        }
        
        // Change user role
        $user->set_role($new_role);
        
        // Assign tier credits
        $roles_instance = L2I_Roles::get_instance();
        $roles_instance->assign_tier_credits($user_id, $new_role);
        
        wp_send_json_success(array(
            'message' => sprintf(__('User role changed to %s.', 'l2i-membership'), $valid_roles[$new_role]),
            'new_role' => $new_role,
            'user_info' => array(
                'id' => $user_id,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles
            )
        ));
    }
    
    /**
     * Admin: Get membership statistics
     */
    public function admin_get_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'l2i-membership')
            ));
        }
        
        global $wpdb;
        
        // Get user counts by role
        $role_counts = array();
        $membership_roles = L2I_Roles::get_membership_roles();
        
        foreach ($membership_roles as $role => $label) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                 WHERE meta_key = %s 
                 AND meta_value LIKE %s",
                $wpdb->prefix . 'capabilities',
                '%' . $role . '%'
            ));
            $role_counts[$role] = (int) $count;
        }
        
        // Get credit statistics
        $credit_stats = $wpdb->get_results(
            "SELECT credit_type, 
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_added,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_used,
                    COUNT(*) as total_transactions
             FROM {$wpdb->prefix}l2i_credit_history 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY credit_type",
            ARRAY_A
        );
        
        // Get recent activity
        $recent_activity = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}l2i_activity_logs 
             ORDER BY created_at DESC 
             LIMIT 20",
            ARRAY_A
        );
        
        wp_send_json_success(array(
            'role_counts' => $role_counts,
            'credit_stats' => $credit_stats,
            'recent_activity' => $recent_activity,
            'total_users' => array_sum($role_counts)
        ));
    }
    
    /**
     * Get membership plans (public)
     */
    public function get_membership_plans() {
        $plans = array(
            'investor' => array(
                'basic_monthly' => array(
                    'name' => __('Investor Basic Monthly', 'l2i-membership'),
                    'price' => 0,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Browse projects (limited)', 'l2i-membership'),
                        __('Basic messaging (3/month)', 'l2i-membership'),
                        __('Post 2 projects/month', 'l2i-membership')
                    )
                ),
                'gold_monthly' => array(
                    'name' => __('Investor Gold Monthly', 'l2i-membership'),
                    'price' => 29,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Browse projects (extended)', 'l2i-membership'),
                        __('Enhanced messaging (10/month)', 'l2i-membership'),
                        __('Post 5 projects/month', 'l2i-membership'),
                        __('Basic Zoom meetings', 'l2i-membership')
                    )
                ),
                'premium_monthly' => array(
                    'name' => __('Investor Premium Monthly', 'l2i-membership'),
                    'price' => 79,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Unlimited project browsing', 'l2i-membership'),
                        __('Unlimited messaging', 'l2i-membership'),
                        __('Post 15 projects/month', 'l2i-membership'),
                        __('Unlimited Zoom meetings', 'l2i-membership'),
                        __('Priority support', 'l2i-membership')
                    )
                )
            ),
            'freelancer' => array(
                'basic_monthly' => array(
                    'name' => __('Freelancer Basic Monthly', 'l2i-membership'),
                    'price' => 0,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Browse projects (limited)', 'l2i-membership'),
                        __('Basic messaging (3/month)', 'l2i-membership'),
                        __('Bid on 5 projects/month', 'l2i-membership')
                    )
                ),
                'gold_monthly' => array(
                    'name' => __('Freelancer Gold Monthly', 'l2i-membership'),
                    'price' => 19,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Browse projects (extended)', 'l2i-membership'),
                        __('Enhanced messaging (10/month)', 'l2i-membership'),
                        __('Bid on 15 projects/month', 'l2i-membership'),
                        __('Portfolio upload', 'l2i-membership'),
                        __('Basic Zoom meetings', 'l2i-membership')
                    )
                ),
                'premium_monthly' => array(
                    'name' => __('Freelancer Premium Monthly', 'l2i-membership'),
                    'price' => 49,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Unlimited project browsing', 'l2i-membership'),
                        __('Unlimited messaging', 'l2i-membership'),
                        __('Bid on 50 projects/month', 'l2i-membership'),
                        __('Advanced portfolio', 'l2i-membership'),
                        __('Unlimited Zoom meetings', 'l2i-membership'),
                        __('Verified badge', 'l2i-membership')
                    )
                )
            ),
            'professional' => array(
                'basic_monthly' => array(
                    'name' => __('Professional Basic Monthly', 'l2i-membership'),
                    'price' => 0,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Browse projects (limited)', 'l2i-membership'),
                        __('Basic messaging (3/month)', 'l2i-membership'),
                        __('Post 1 service/month', 'l2i-membership'),
                        __('Bid on 3 projects/month', 'l2i-membership')
                    )
                ),
                'gold_monthly' => array(
                    'name' => __('Professional Gold Monthly', 'l2i-membership'),
                    'price' => 39,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Browse projects (extended)', 'l2i-membership'),
                        __('Enhanced messaging (10/month)', 'l2i-membership'),
                        __('Post 3 services/month', 'l2i-membership'),
                        __('Bid on 10 projects/month', 'l2i-membership'),
                        __('Portfolio upload', 'l2i-membership'),
                        __('Basic Zoom meetings', 'l2i-membership')
                    )
                ),
                'premium_monthly' => array(
                    'name' => __('Professional Premium Monthly', 'l2i-membership'),
                    'price' => 99,
                    'billing' => 'monthly',
                    'features' => array(
                        __('Unlimited project browsing', 'l2i-membership'),
                        __('Unlimited messaging', 'l2i-membership'),
                        __('Post 10 services/month', 'l2i-membership'),
                        __('Bid on 30 projects/month', 'l2i-membership'),
                        __('Advanced portfolio', 'l2i-membership'),
                        __('Unlimited Zoom meetings', 'l2i-membership'),
                        __('Verified badge', 'l2i-membership'),
                        __('Priority listing', 'l2i-membership')
                    )
                )
            )
        );
        
        wp_send_json_success($plans);
    }
}