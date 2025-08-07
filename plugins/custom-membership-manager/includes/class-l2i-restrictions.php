<?php
/**
 * Restrictions and Permissions System
 * Enforces membership tier limitations across the platform
 */

defined('ABSPATH') || exit;

class L2I_Restrictions {
    
    private static $instance = null;
    private $credits;
    private $roles;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->credits = L2I_Credits::get_instance();
        $this->roles = L2I_Roles::get_instance();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Project/Job posting restrictions
        add_filter('projecttheme_can_post_project', array($this, 'check_project_posting_permission'), 10, 2);
        add_filter('projecttheme_project_post_limit', array($this, 'get_project_posting_limit'), 10, 2);
        
        // Bidding restrictions
        add_filter('projecttheme_can_bid', array($this, 'check_bidding_permission'), 10, 3);
        add_action('projecttheme_before_bid_submit', array($this, 'validate_bid_submission'), 10, 2);
        
        // Messaging restrictions
        add_filter('projecttheme_can_start_chat', array($this, 'check_messaging_permission'), 10, 3);
        add_filter('projecttheme_chat_message_limit', array($this, 'get_messaging_limit'), 10, 2);
        
        // Profile restrictions
        add_filter('projecttheme_profile_features', array($this, 'filter_profile_features'), 10, 2);
        add_filter('projecttheme_can_upload_portfolio', array($this, 'check_portfolio_permission'), 10, 2);
        
        // Search and browse restrictions
        add_filter('projecttheme_search_results', array($this, 'filter_search_results'), 10, 2);
        add_filter('projecttheme_browse_limit', array($this, 'get_browse_limit'), 10, 2);
        
        // Premium feature restrictions
        add_filter('projecttheme_premium_features', array($this, 'filter_premium_features'), 10, 2);
        
        // Admin restrictions
        add_action('admin_init', array($this, 'restrict_admin_access'));
        
        // AJAX restrictions
        add_action('wp_ajax_l2i_check_permission', array($this, 'ajax_check_permission'));
        
        // Shortcode restrictions
        add_filter('do_shortcode_tag', array($this, 'restrict_shortcodes'), 10, 4);
        
        // Content restrictions
        add_filter('the_content', array($this, 'restrict_content_access'), 999);
        
        // Dashboard restrictions
        add_action('template_redirect', array($this, 'restrict_page_access'));
        
        // API restrictions
        add_filter('rest_authentication_errors', array($this, 'restrict_api_access'), 10, 1);
    }
    
    /**
     * Check if user can post projects
     */
    public function check_project_posting_permission($can_post, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        $user = get_userdata($user_id);
        
        // Check if user has project posting capability
        if (!$user->has_cap('post_projects')) {
            return false;
        }
        
        // Check posting limits for the user's tier
        $limit = $this->get_project_posting_limit(0, $user_id);
        if ($limit === 0) {
            return false;
        }
        
        // Check if user has reached their monthly limit
        if ($limit > 0) {
            $current_count = $this->get_monthly_project_count($user_id);
            if ($current_count >= $limit) {
                return false;
            }
        }
        
        return $can_post;
    }
    
    /**
     * Get project posting limit for user
     */
    public function get_project_posting_limit($default_limit, $user_id) {
        if (!$user_id) {
            return 0;
        }
        
        $user = get_userdata($user_id);
        $roles = $user->roles;
        
        // Define limits per role
        $limits = array(
            // Investors
            'investor_basic_monthly' => 2,
            'investor_basic_yearly' => 2,
            'investor_gold_monthly' => 5,
            'investor_gold_yearly' => 5,
            'investor_premium_monthly' => 15,
            'investor_premium_yearly' => 15,
            'investor_enterprise_monthly' => -1, // Unlimited
            'investor_enterprise_yearly' => -1,
            
            // Freelancers (can't post projects, only bid)
            'freelancer_basic_monthly' => 0,
            'freelancer_basic_yearly' => 0,
            'freelancer_gold_monthly' => 0,
            'freelancer_gold_yearly' => 0,
            'freelancer_premium_monthly' => 0,
            'freelancer_premium_yearly' => 0,
            
            // Professional Service Providers
            'professional_basic_monthly' => 1,
            'professional_basic_yearly' => 1,
            'professional_gold_monthly' => 3,
            'professional_gold_yearly' => 3,
            'professional_premium_monthly' => 10,
            'professional_premium_yearly' => 10,
        );
        
        foreach ($roles as $role) {
            if (isset($limits[$role])) {
                return $limits[$role];
            }
        }
        
        return 0;
    }
    
    /**
     * Check if user can bid on projects
     */
    public function check_bidding_permission($can_bid, $user_id, $project_id) {
        if (!$user_id) {
            return false;
        }
        
        $user = get_userdata($user_id);
        
        // Check if user has bidding capability
        if (!$user->has_cap('bid_projects')) {
            return false;
        }
        
        // Check if user has bid credits
        if (!$this->credits->has_sufficient_credits($user_id, 'bid_credits', 1)) {
            return false;
        }
        
        // Check bidding limits
        $limit = $this->get_bidding_limit($user_id);
        if ($limit === 0) {
            return false;
        }
        
        if ($limit > 0) {
            $current_count = $this->get_monthly_bid_count($user_id);
            if ($current_count >= $limit) {
                return false;
            }
        }
        
        // Check if user already bid on this project
        if ($this->user_already_bid($user_id, $project_id)) {
            return false;
        }
        
        return $can_bid;
    }
    
    /**
     * Get bidding limit for user
     */
    private function get_bidding_limit($user_id) {
        $user = get_userdata($user_id);
        $roles = $user->roles;
        
        $limits = array(
            // Freelancers
            'freelancer_basic_monthly' => 5,
            'freelancer_basic_yearly' => 5,
            'freelancer_gold_monthly' => 15,
            'freelancer_gold_yearly' => 15,
            'freelancer_premium_monthly' => 50,
            'freelancer_premium_yearly' => 50,
            
            // Professional Service Providers
            'professional_basic_monthly' => 3,
            'professional_basic_yearly' => 3,
            'professional_gold_monthly' => 10,
            'professional_gold_yearly' => 10,
            'professional_premium_monthly' => 30,
            'professional_premium_yearly' => 30,
            
            // Investors (can't bid)
            'investor_basic_monthly' => 0,
            'investor_basic_yearly' => 0,
            'investor_gold_monthly' => 0,
            'investor_gold_yearly' => 0,
        );
        
        foreach ($roles as $role) {
            if (isset($limits[$role])) {
                return $limits[$role];
            }
        }
        
        return 0;
    }
    
    /**
     * Validate bid submission
     */
    public function validate_bid_submission($user_id, $project_id) {
        // Use bid credit
        $credit_result = $this->credits->use_credits(
            $user_id,
            'bid_credits',
            1,
            sprintf(__('Bid on project #%d', 'l2i-membership'), $project_id),
            array('project_id' => $project_id)
        );
        
        if (is_wp_error($credit_result)) {
            wp_die($credit_result->get_error_message());
        }
    }
    
    /**
     * Check messaging permissions
     */
    public function check_messaging_permission($can_message, $sender_id, $recipient_id) {
        if (!$sender_id || !$recipient_id) {
            return false;
        }
        
        $sender = get_userdata($sender_id);
        
        // Check if sender has messaging capability
        if (!$sender->has_cap('send_messages')) {
            return false;
        }
        
        // Check if sender has connection credits
        if (!$this->credits->has_sufficient_credits($sender_id, 'connection_credits', 1)) {
            return false;
        }
        
        // Check messaging limits
        $limit = $this->get_messaging_limit(0, $sender_id);
        if ($limit === 0) {
            return false;
        }
        
        if ($limit > 0) {
            $current_count = $this->get_monthly_message_count($sender_id);
            if ($current_count >= $limit) {
                return false;
            }
        }
        
        return $can_message;
    }
    
    /**
     * Get messaging limit for user
     */
    public function get_messaging_limit($default_limit, $user_id) {
        if (!$user_id) {
            return 0;
        }
        
        $user = get_userdata($user_id);
        $roles = $user->roles;
        
        $limits = array(
            // Basic tiers - limited messaging
            'investor_basic_monthly' => 3,
            'investor_basic_yearly' => 3,
            'freelancer_basic_monthly' => 3,
            'freelancer_basic_yearly' => 3,
            'professional_basic_monthly' => 3,
            'professional_basic_yearly' => 3,
            
            // Gold tiers - moderate messaging
            'investor_gold_monthly' => 10,
            'investor_gold_yearly' => 10,
            'freelancer_gold_monthly' => 10,
            'freelancer_gold_yearly' => 10,
            'professional_gold_monthly' => 10,
            'professional_gold_yearly' => 10,
            
            // Premium tiers - high messaging
            'investor_premium_monthly' => 50,
            'investor_premium_yearly' => 50,
            'freelancer_premium_monthly' => 50,
            'freelancer_premium_yearly' => 50,
            'professional_premium_monthly' => 50,
            'professional_premium_yearly' => 50,
            
            // Enterprise - unlimited
            'investor_enterprise_monthly' => -1,
            'investor_enterprise_yearly' => -1,
        );
        
        foreach ($roles as $role) {
            if (isset($limits[$role])) {
                return $limits[$role];
            }
        }
        
        return 0;
    }
    
    /**
     * Filter profile features based on user tier
     */
    public function filter_profile_features($features, $user_id) {
        if (!$user_id) {
            return $features;
        }
        
        $user = get_userdata($user_id);
        $roles = $user->roles;
        
        // Define features per tier
        $tier_features = array(
            'basic' => array('basic_profile', 'contact_info'),
            'gold' => array('basic_profile', 'contact_info', 'portfolio', 'reviews'),
            'premium' => array('basic_profile', 'contact_info', 'portfolio', 'reviews', 'verified_badge', 'priority_listing'),
            'enterprise' => array('basic_profile', 'contact_info', 'portfolio', 'reviews', 'verified_badge', 'priority_listing', 'custom_branding')
        );
        
        // Determine user tier
        $user_tier = 'basic';
        foreach ($roles as $role) {
            if (strpos($role, 'premium') !== false) {
                $user_tier = 'premium';
                break;
            } elseif (strpos($role, 'gold') !== false) {
                $user_tier = 'gold';
                break;
            } elseif (strpos($role, 'enterprise') !== false) {
                $user_tier = 'enterprise';
                break;
            }
        }
        
        // Filter features
        $allowed_features = $tier_features[$user_tier] ?? $tier_features['basic'];
        return array_intersect($features, $allowed_features);
    }
    
    /**
     * Check portfolio upload permission
     */
    public function check_portfolio_permission($can_upload, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        $user = get_userdata($user_id);
        
        // Only gold+ tiers can upload portfolio
        $allowed_roles = array(
            'freelancer_gold_monthly', 'freelancer_gold_yearly',
            'freelancer_premium_monthly', 'freelancer_premium_yearly',
            'professional_gold_monthly', 'professional_gold_yearly',
            'professional_premium_monthly', 'professional_premium_yearly'
        );
        
        $user_roles = $user->roles;
        foreach ($user_roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Filter search results based on user tier
     */
    public function filter_search_results($results, $user_id) {
        if (!$user_id) {
            return array_slice($results, 0, 3); // Guest users see only 3 results
        }
        
        $user = get_userdata($user_id);
        $roles = $user->roles;
        
        // Define result limits per tier
        $limits = array(
            'basic' => 10,
            'gold' => 25,
            'premium' => 100,
            'enterprise' => -1 // Unlimited
        );
        
        // Determine user tier
        $user_tier = 'basic';
        foreach ($roles as $role) {
            if (strpos($role, 'enterprise') !== false) {
                $user_tier = 'enterprise';
                break;
            } elseif (strpos($role, 'premium') !== false) {
                $user_tier = 'premium';
                break;
            } elseif (strpos($role, 'gold') !== false) {
                $user_tier = 'gold';
                break;
            }
        }
        
        $limit = $limits[$user_tier];
        
        if ($limit === -1) {
            return $results; // Unlimited
        }
        
        return array_slice($results, 0, $limit);
    }
    
    /**
     * Get browse limit for user
     */
    public function get_browse_limit($default_limit, $user_id) {
        if (!$user_id) {
            return 5; // Guest limit
        }
        
        $user = get_userdata($user_id);
        $roles = $user->roles;
        
        $limits = array(
            // Basic tiers
            'investor_basic_monthly' => 20,
            'investor_basic_yearly' => 20,
            'freelancer_basic_monthly' => 20,
            'freelancer_basic_yearly' => 20,
            'professional_basic_monthly' => 20,
            'professional_basic_yearly' => 20,
            
            // Gold tiers
            'investor_gold_monthly' => 50,
            'investor_gold_yearly' => 50,
            'freelancer_gold_monthly' => 50,
            'freelancer_gold_yearly' => 50,
            'professional_gold_monthly' => 50,
            'professional_gold_yearly' => 50,
            
            // Premium+ tiers - unlimited
            'investor_premium_monthly' => -1,
            'investor_premium_yearly' => -1,
            'freelancer_premium_monthly' => -1,
            'freelancer_premium_yearly' => -1,
            'professional_premium_monthly' => -1,
            'professional_premium_yearly' => -1,
            'investor_enterprise_monthly' => -1,
            'investor_enterprise_yearly' => -1,
        );
        
        foreach ($roles as $role) {
            if (isset($limits[$role])) {
                return $limits[$role];
            }
        }
        
        return 10; // Default
    }
    
    /**
     * Filter premium features
     */
    public function filter_premium_features($features, $user_id) {
        if (!$user_id) {
            return array();
        }
        
        $user = get_userdata($user_id);
        
        // Premium features only for premium+ users
        if (!$user->has_cap('access_premium_features')) {
            return array();
        }
        
        return $features;
    }
    
    /**
     * Restrict admin access
     */
    public function restrict_admin_access() {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        // Allow administrators and editors
        if (current_user_can('manage_options') || current_user_can('edit_others_posts')) {
            return;
        }
        
        // Redirect non-admin users to dashboard
        wp_redirect(home_url('/dashboard/'));
        exit;
    }
    
    /**
     * AJAX: Check permission
     */
    public function ajax_check_permission() {
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_membership_nonce')) {
            wp_die(__('Security check failed.', 'l2i-membership'));
        }
        
        $user_id = get_current_user_id();
        $permission = sanitize_text_field($_POST['permission']);
        $context = $_POST['context'] ?? array();
        
        $has_permission = false;
        
        switch ($permission) {
            case 'post_project':
                $has_permission = $this->check_project_posting_permission(true, $user_id);
                break;
                
            case 'bid_project':
                $project_id = (int) ($context['project_id'] ?? 0);
                $has_permission = $this->check_bidding_permission(true, $user_id, $project_id);
                break;
                
            case 'start_chat':
                $recipient_id = (int) ($context['recipient_id'] ?? 0);
                $has_permission = $this->check_messaging_permission(true, $user_id, $recipient_id);
                break;
                
            case 'upload_portfolio':
                $has_permission = $this->check_portfolio_permission(true, $user_id);
                break;
        }
        
        wp_send_json_success(array(
            'has_permission' => $has_permission,
            'user_tier' => $this->get_user_tier($user_id),
            'credits' => $this->credits->get_user_credits($user_id)
        ));
    }
    
    /**
     * Restrict shortcodes based on user tier
     */
    public function restrict_shortcodes($output, $tag, $attr, $m) {
        $restricted_shortcodes = array(
            'premium_projects' => array('premium', 'enterprise'),
            'advanced_search' => array('gold', 'premium', 'enterprise'),
            'priority_listing' => array('premium', 'enterprise'),
            'custom_dashboard' => array('enterprise')
        );
        
        if (!isset($restricted_shortcodes[$tag])) {
            return $output;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please login to access this feature.', 'l2i-membership') . '</p>';
        }
        
        $user_tier = $this->get_user_tier($user_id);
        $required_tiers = $restricted_shortcodes[$tag];
        
        if (!in_array($user_tier, $required_tiers)) {
            return '<p>' . sprintf(
                __('This feature requires %s membership. <a href="%s">Upgrade now</a>', 'l2i-membership'),
                implode(' or ', $required_tiers),
                home_url('/membership-plans/')
            ) . '</p>';
        }
        
        return $output;
    }
    
    /**
     * Restrict content access
     */
    public function restrict_content_access($content) {
        if (!is_singular() || is_admin()) {
            return $content;
        }
        
        global $post;
        
        // Check if post has membership restrictions
        $required_tier = get_post_meta($post->ID, '_l2i_required_tier', true);
        if (!$required_tier) {
            return $content;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<div class="l2i-restricted-content">' .
                   '<h3>' . __('Members Only Content', 'l2i-membership') . '</h3>' .
                   '<p>' . __('Please login to access this content.', 'l2i-membership') . '</p>' .
                   '<a href="' . wp_login_url(get_permalink()) . '" class="btn btn-primary">' . __('Login', 'l2i-membership') . '</a>' .
                   '</div>';
        }
        
        $user_tier = $this->get_user_tier($user_id);
        $tier_hierarchy = array('basic', 'gold', 'premium', 'enterprise');
        
        $user_tier_level = array_search($user_tier, $tier_hierarchy);
        $required_tier_level = array_search($required_tier, $tier_hierarchy);
        
        if ($user_tier_level < $required_tier_level) {
            return '<div class="l2i-restricted-content">' .
                   '<h3>' . sprintf(__('%s Members Only', 'l2i-membership'), ucfirst($required_tier)) . '</h3>' .
                   '<p>' . sprintf(__('This content requires %s membership or higher.', 'l2i-membership'), $required_tier) . '</p>' .
                   '<a href="' . home_url('/membership-plans/') . '" class="btn btn-primary">' . __('Upgrade Membership', 'l2i-membership') . '</a>' .
                   '</div>';
        }
        
        return $content;
    }
    
    /**
     * Restrict page access
     */
    public function restrict_page_access() {
        if (is_admin()) {
            return;
        }
        
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Check page restrictions
        $required_tier = get_post_meta($post->ID, '_l2i_required_tier', true);
        if (!$required_tier) {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }
        
        $user_tier = $this->get_user_tier($user_id);
        $tier_hierarchy = array('basic', 'gold', 'premium', 'enterprise');
        
        $user_tier_level = array_search($user_tier, $tier_hierarchy);
        $required_tier_level = array_search($required_tier, $tier_hierarchy);
        
        if ($user_tier_level < $required_tier_level) {
            wp_redirect(home_url('/membership-plans/'));
            exit;
        }
    }
    
    /**
     * Restrict API access
     */
    public function restrict_api_access($result) {
        if (is_wp_error($result)) {
            return $result;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('rest_not_logged_in', __('You are not currently logged in.', 'l2i-membership'), array('status' => 401));
        }
        
        $user = get_userdata($user_id);
        if (!$user->has_cap('access_api')) {
            return new WP_Error('rest_forbidden', __('Your membership level does not include API access.', 'l2i-membership'), array('status' => 403));
        }
        
        return $result;
    }
    
    /**
     * Helper: Get user tier
     */
    private function get_user_tier($user_id) {
        $user = get_userdata($user_id);
        $roles = $user->roles;
        
        foreach ($roles as $role) {
            if (strpos($role, 'enterprise') !== false) {
                return 'enterprise';
            } elseif (strpos($role, 'premium') !== false) {
                return 'premium';
            } elseif (strpos($role, 'gold') !== false) {
                return 'gold';
            } elseif (strpos($role, 'basic') !== false) {
                return 'basic';
            }
        }
        
        return 'basic';
    }
    
    /**
     * Helper: Get monthly project count
     */
    private function get_monthly_project_count($user_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_author = %d 
             AND post_type = 'project' 
             AND post_status = 'publish' 
             AND post_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            $user_id
        ));
    }
    
    /**
     * Helper: Get monthly bid count
     */
    private function get_monthly_bid_count($user_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}project_bids 
             WHERE uid = %d 
             AND date_made >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            $user_id
        ));
    }
    
    /**
     * Helper: Check if user already bid
     */
    private function user_already_bid($user_id, $project_id) {
        global $wpdb;
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}project_bids 
             WHERE uid = %d AND pid = %d",
            $user_id, $project_id
        ));
    }
    
    /**
     * Helper: Get monthly message count
     */
    private function get_monthly_message_count($user_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}project_pm 
             WHERE initiator = %d 
             AND datemade >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 MONTH))",
            $user_id
        ));
    }
    
    /**
     * Get restriction summary for user
     */
    public function get_user_restrictions($user_id) {
        if (!$user_id) {
            return array();
        }
        
        $user_tier = $this->get_user_tier($user_id);
        $credits = $this->credits->get_user_credits($user_id);
        
        return array(
            'tier' => $user_tier,
            'credits' => $credits,
            'limits' => array(
                'projects' => $this->get_project_posting_limit(0, $user_id),
                'bids' => $this->get_bidding_limit($user_id),
                'messages' => $this->get_messaging_limit(0, $user_id),
                'browse' => $this->get_browse_limit(0, $user_id)
            ),
            'current_usage' => array(
                'projects' => $this->get_monthly_project_count($user_id),
                'bids' => $this->get_monthly_bid_count($user_id),
                'messages' => $this->get_monthly_message_count($user_id)
            ),
            'permissions' => array(
                'post_projects' => $this->check_project_posting_permission(true, $user_id),
                'bid_projects' => $this->check_bidding_permission(true, $user_id, 0),
                'send_messages' => $this->check_messaging_permission(true, $user_id, 0),
                'upload_portfolio' => $this->check_portfolio_permission(true, $user_id),
                'zoom_meetings' => get_userdata($user_id)->has_cap('zoom_meetings'),
                'premium_features' => get_userdata($user_id)->has_cap('access_premium_features')
            )
        );
    }
}