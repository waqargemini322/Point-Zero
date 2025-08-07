<?php
/**
 * Roles Management Class
 * Handles user roles and membership tiers for Link2Investors platform
 */

defined('ABSPATH') || exit;

class L2I_Roles {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('user_register', array($this, 'assign_default_role'));
        add_filter('wp_dropdown_users_args', array($this, 'filter_users_dropdown'));
    }
    
    public function init() {
        // Hook into role changes
        add_action('set_user_role', array($this, 'handle_role_change'), 10, 3);
        add_action('add_user_role', array($this, 'handle_role_addition'), 10, 2);
        add_action('remove_user_role', array($this, 'handle_role_removal'), 10, 2);
    }
    
    /**
     * Create all membership roles
     */
    public static function create_roles() {
        // Remove existing roles first to avoid conflicts
        self::remove_roles();
        
        // Define role capabilities
        $base_capabilities = array(
            'read' => true,
            'upload_files' => true,
            'edit_posts' => false,
            'publish_posts' => false
        );
        
        $investor_capabilities = array_merge($base_capabilities, array(
            'view_projects' => true,
            'browse_freelancers' => true,
            'browse_service_providers' => true
        ));
        
        $freelancer_capabilities = array_merge($base_capabilities, array(
            'view_projects' => true,
            'submit_proposals' => true,
            'create_portfolio' => true
        ));
        
        $service_provider_capabilities = array_merge($base_capabilities, array(
            'view_projects' => true,
            'submit_proposals' => true,
            'create_services' => true,
            'manage_services' => true
        ));
        
        // Investor roles
        add_role('investor_basic_monthly', __('Investor - Basic Monthly', 'l2i-membership'), 
            array_merge($investor_capabilities, array(
                'access_basic_features' => true,
                'browse_only' => true
            ))
        );
        
        add_role('investor_basic_yearly', __('Investor - Basic Yearly', 'l2i-membership'), 
            array_merge($investor_capabilities, array(
                'access_basic_features' => true,
                'limited_bidding' => true,
                'limited_messaging' => true
            ))
        );
        
        add_role('investor_gold_monthly', __('Investor - Gold Monthly', 'l2i-membership'), 
            array_merge($investor_capabilities, array(
                'access_premium_features' => true,
                'unlimited_bidding' => true,
                'unlimited_messaging' => true,
                'zoom_meetings' => true,
                'priority_support' => true
            ))
        );
        
        add_role('investor_gold_yearly', __('Investor - Gold Yearly', 'l2i-membership'), 
            array_merge($investor_capabilities, array(
                'access_premium_features' => true,
                'unlimited_bidding' => true,
                'unlimited_messaging' => true,
                'zoom_meetings' => true,
                'priority_support' => true,
                'bulk_operations' => true,
                'advanced_analytics' => true
            ))
        );
        
        // Freelancer roles
        add_role('freelancer_basic_monthly', __('Freelancer - Basic Monthly', 'l2i-membership'), 
            array_merge($freelancer_capabilities, array(
                'access_basic_features' => true,
                'browse_only' => true
            ))
        );
        
        add_role('freelancer_basic_yearly', __('Freelancer - Basic Yearly', 'l2i-membership'), 
            array_merge($freelancer_capabilities, array(
                'access_basic_features' => true,
                'limited_proposals' => true,
                'limited_messaging' => true
            ))
        );
        
        add_role('freelancer_gold_monthly', __('Freelancer - Gold Monthly', 'l2i-membership'), 
            array_merge($freelancer_capabilities, array(
                'access_premium_features' => true,
                'unlimited_proposals' => true,
                'unlimited_messaging' => true,
                'zoom_meetings' => true,
                'featured_profile' => true
            ))
        );
        
        add_role('freelancer_gold_yearly', __('Freelancer - Gold Yearly', 'l2i-membership'), 
            array_merge($freelancer_capabilities, array(
                'access_premium_features' => true,
                'unlimited_proposals' => true,
                'unlimited_messaging' => true,
                'zoom_meetings' => true,
                'featured_profile' => true,
                'portfolio_showcase' => true,
                'advanced_analytics' => true
            ))
        );
        
        // Service Provider roles
        add_role('service_provider_basic_monthly', __('Service Provider - Basic Monthly', 'l2i-membership'), 
            array_merge($service_provider_capabilities, array(
                'access_basic_features' => true,
                'browse_only' => true
            ))
        );
        
        add_role('service_provider_basic_yearly', __('Service Provider - Basic Yearly', 'l2i-membership'), 
            array_merge($service_provider_capabilities, array(
                'access_basic_features' => true,
                'limited_services' => true,
                'limited_messaging' => true
            ))
        );
        
        add_role('service_provider_gold_monthly', __('Service Provider - Gold Monthly', 'l2i-membership'), 
            array_merge($service_provider_capabilities, array(
                'access_premium_features' => true,
                'unlimited_services' => true,
                'unlimited_messaging' => true,
                'zoom_meetings' => true,
                'featured_services' => true
            ))
        );
        
        add_role('service_provider_gold_yearly', __('Service Provider - Gold Yearly', 'l2i-membership'), 
            array_merge($service_provider_capabilities, array(
                'access_premium_features' => true,
                'unlimited_services' => true,
                'unlimited_messaging' => true,
                'zoom_meetings' => true,
                'featured_services' => true,
                'service_analytics' => true,
                'bulk_service_management' => true
            ))
        );
    }
    
    /**
     * Remove all custom roles
     */
    public static function remove_roles() {
        $custom_roles = array(
            'investor_basic_monthly',
            'investor_basic_yearly', 
            'investor_gold_monthly',
            'investor_gold_yearly',
            'freelancer_basic_monthly',
            'freelancer_basic_yearly',
            'freelancer_gold_monthly', 
            'freelancer_gold_yearly',
            'service_provider_basic_monthly',
            'service_provider_basic_yearly',
            'service_provider_gold_monthly',
            'service_provider_gold_yearly'
        );
        
        foreach ($custom_roles as $role) {
            remove_role($role);
        }
    }
    
    /**
     * Assign default role to new users
     */
    public function assign_default_role($user_id) {
        $user = get_userdata($user_id);
        
        // Remove default subscriber role
        $user->remove_role('subscriber');
        
        // Assign basic monthly role based on registration type
        $registration_type = get_user_meta($user_id, 'registration_type', true);
        
        switch ($registration_type) {
            case 'investor':
                $user->add_role('investor_basic_monthly');
                break;
            case 'freelancer':
                $user->add_role('freelancer_basic_monthly');
                break;
            case 'service_provider':
                $user->add_role('service_provider_basic_monthly');
                break;
            default:
                $user->add_role('investor_basic_monthly'); // Default fallback
        }
        
        // Initialize user credits
        $db = L2I_Database::get_instance();
        $db->get_user_credits($user_id); // This will initialize if not exists
        
        // Log the registration
        $db->log_activity($user_id, 'user_registered', array(
            'registration_type' => $registration_type,
            'assigned_role' => $user->roles[0] ?? 'unknown'
        ));
    }
    
    /**
     * Handle role changes
     */
    public function handle_role_change($user_id, $role, $old_roles) {
        $this->update_user_credits_on_role_change($user_id, $role, $old_roles);
        $this->log_role_change($user_id, $role, $old_roles, 'role_changed');
    }
    
    /**
     * Handle role addition
     */
    public function handle_role_addition($user_id, $role) {
        $user = get_userdata($user_id);
        $old_roles = $user->roles;
        
        $this->update_user_credits_on_role_change($user_id, $role, $old_roles);
        $this->log_role_change($user_id, $role, $old_roles, 'role_added');
    }
    
    /**
     * Handle role removal
     */
    public function handle_role_removal($user_id, $role) {
        $user = get_userdata($user_id);
        $current_roles = $user->roles;
        
        $this->log_role_change($user_id, $role, $current_roles, 'role_removed');
    }
    
    /**
     * Update user credits when role changes
     */
    private function update_user_credits_on_role_change($user_id, $new_role, $old_roles) {
        // Only update if it's one of our custom roles
        if (!$this->is_custom_membership_role($new_role)) {
            return;
        }
        
        $db = L2I_Database::get_instance();
        $current_credits = $db->get_user_credits($user_id);
        
        if (!$current_credits) {
            // Initialize credits for the new role
            $db->get_user_credits($user_id);
            return;
        }
        
        // Get new credit allocation
        $new_credits = $this->get_role_credit_allocation($new_role);
        
        // Update credits table
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'l2i_user_credits',
            array(
                'membership_type' => $new_role,
                'bid_credits' => $new_credits['bid_credits'],
                'connection_credits' => $new_credits['connection_credits'],
                'zoom_invites' => $new_credits['zoom_invites'],
                'renewal_date' => $this->calculate_renewal_date($new_role),
                'updated_at' => current_time('mysql')
            ),
            array('user_id' => $user_id),
            array('%s', '%d', '%d', '%d', '%s', '%s'),
            array('%d')
        );
        
        // Clear cache
        wp_cache_delete('l2i_user_credits_' . $user_id, 'l2i_membership');
        
        // Log credit update
        $db->log_activity($user_id, 'credits_updated_role_change', array(
            'new_role' => $new_role,
            'old_roles' => $old_roles,
            'new_credits' => $new_credits
        ));
    }
    
    /**
     * Log role changes
     */
    private function log_role_change($user_id, $role, $old_roles, $action) {
        $db = L2I_Database::get_instance();
        $db->log_activity($user_id, $action, array(
            'role' => $role,
            'old_roles' => $old_roles,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Check if role is a custom membership role
     */
    public function is_custom_membership_role($role) {
        $custom_roles = array(
            'investor_basic_monthly', 'investor_basic_yearly', 
            'investor_gold_monthly', 'investor_gold_yearly',
            'freelancer_basic_monthly', 'freelancer_basic_yearly',
            'freelancer_gold_monthly', 'freelancer_gold_yearly',
            'service_provider_basic_monthly', 'service_provider_basic_yearly',
            'service_provider_gold_monthly', 'service_provider_gold_yearly'
        );
        
        return in_array($role, $custom_roles);
    }
    
    /**
     * Get role credit allocation
     */
    private function get_role_credit_allocation($role) {
        $allocations = array(
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
        
        return isset($allocations[$role]) 
            ? $allocations[$role] 
            : array('bid_credits' => 0, 'connection_credits' => 0, 'zoom_invites' => 0);
    }
    
    /**
     * Calculate renewal date based on role
     */
    private function calculate_renewal_date($role) {
        if (strpos($role, 'yearly') !== false) {
            return date('Y-m-d H:i:s', strtotime('+1 year'));
        } elseif (strpos($role, 'monthly') !== false) {
            return date('Y-m-d H:i:s', strtotime('+1 month'));
        }
        return null;
    }
    
    /**
     * Get user's primary membership role
     */
    public function get_user_membership_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Priority order for roles (highest priority first)
        $role_priority = array(
            'investor_gold_yearly' => 1,
            'freelancer_gold_yearly' => 1,
            'service_provider_gold_yearly' => 1,
            'investor_gold_monthly' => 2,
            'freelancer_gold_monthly' => 2,
            'service_provider_gold_monthly' => 2,
            'investor_basic_yearly' => 3,
            'freelancer_basic_yearly' => 3,
            'service_provider_basic_yearly' => 3,
            'investor_basic_monthly' => 4,
            'freelancer_basic_monthly' => 4,
            'service_provider_basic_monthly' => 4
        );
        
        $primary_role = null;
        $highest_priority = 999;
        
        foreach ($user->roles as $role) {
            if (isset($role_priority[$role]) && $role_priority[$role] < $highest_priority) {
                $primary_role = $role;
                $highest_priority = $role_priority[$role];
            }
        }
        
        return $primary_role;
    }
    
    /**
     * Get role display name
     */
    public function get_role_display_name($role) {
        $role_names = array(
            'investor_basic_monthly' => __('Investor - Basic Monthly', 'l2i-membership'),
            'investor_basic_yearly' => __('Investor - Basic Yearly', 'l2i-membership'),
            'investor_gold_monthly' => __('Investor - Gold Monthly', 'l2i-membership'),
            'investor_gold_yearly' => __('Investor - Gold Yearly', 'l2i-membership'),
            'freelancer_basic_monthly' => __('Freelancer - Basic Monthly', 'l2i-membership'),
            'freelancer_basic_yearly' => __('Freelancer - Basic Yearly', 'l2i-membership'),
            'freelancer_gold_monthly' => __('Freelancer - Gold Monthly', 'l2i-membership'),
            'freelancer_gold_yearly' => __('Freelancer - Gold Yearly', 'l2i-membership'),
            'service_provider_basic_monthly' => __('Service Provider - Basic Monthly', 'l2i-membership'),
            'service_provider_basic_yearly' => __('Service Provider - Basic Yearly', 'l2i-membership'),
            'service_provider_gold_monthly' => __('Service Provider - Gold Monthly', 'l2i-membership'),
            'service_provider_gold_yearly' => __('Service Provider - Gold Yearly', 'l2i-membership')
        );
        
        return isset($role_names[$role]) ? $role_names[$role] : ucfirst(str_replace('_', ' ', $role));
    }
    
    /**
     * Check if user has capability
     */
    public function user_has_capability($user_id, $capability) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        return $user->has_cap($capability);
    }
    
    /**
     * Filter users dropdown for admin
     */
    public function filter_users_dropdown($args) {
        if (is_admin() && current_user_can('manage_options')) {
            // Show all users to administrators
            return $args;
        }
        
        // Filter based on current user's role and capabilities
        $current_user_id = get_current_user_id();
        $current_role = $this->get_user_membership_role($current_user_id);
        
        if (!$current_role) {
            return $args;
        }
        
        // Add role-based filtering logic here
        return $args;
    }
    
    /**
     * Get available upgrade paths for a role
     */
    public function get_upgrade_paths($current_role) {
        $upgrade_paths = array(
            'investor_basic_monthly' => array('investor_basic_yearly', 'investor_gold_monthly'),
            'investor_basic_yearly' => array('investor_gold_yearly'),
            'investor_gold_monthly' => array('investor_gold_yearly'),
            'freelancer_basic_monthly' => array('freelancer_basic_yearly', 'freelancer_gold_monthly'),
            'freelancer_basic_yearly' => array('freelancer_gold_yearly'),
            'freelancer_gold_monthly' => array('freelancer_gold_yearly'),
            'service_provider_basic_monthly' => array('service_provider_basic_yearly', 'service_provider_gold_monthly'),
            'service_provider_basic_yearly' => array('service_provider_gold_yearly'),
            'service_provider_gold_monthly' => array('service_provider_gold_yearly')
        );
        
        return isset($upgrade_paths[$current_role]) ? $upgrade_paths[$current_role] : array();
    }
}