<?php
/*
Plugin Name: Link2Investors Membership Manager
Description: Advanced membership system with credit management, role restrictions, and Zoom integration for Link2Investors platform
Version: 2.0.0
Author: Link2Investors Team
Text Domain: l2i-membership
*/

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('L2I_MEMBERSHIP_VERSION', '2.0.0');
define('L2I_MEMBERSHIP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('L2I_MEMBERSHIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('L2I_MEMBERSHIP_PLUGIN_FILE', __FILE__);

// Main plugin class
class L2I_Membership_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    private function load_dependencies() {
        require_once L2I_MEMBERSHIP_PLUGIN_DIR . 'includes/class-l2i-database.php';
        require_once L2I_MEMBERSHIP_PLUGIN_DIR . 'includes/class-l2i-roles.php';
        require_once L2I_MEMBERSHIP_PLUGIN_DIR . 'includes/class-l2i-credits.php';
        require_once L2I_MEMBERSHIP_PLUGIN_DIR . 'includes/class-l2i-restrictions.php';
        require_once L2I_MEMBERSHIP_PLUGIN_DIR . 'includes/class-l2i-zoom.php';
        require_once L2I_MEMBERSHIP_PLUGIN_DIR . 'includes/class-l2i-ajax.php';
        require_once L2I_MEMBERSHIP_PLUGIN_DIR . 'includes/class-l2i-shortcodes.php';
        require_once L2I_MEMBERSHIP_PLUGIN_DIR . 'includes/class-l2i-admin.php';
    }
    
    public function init() {
        // Initialize components
        L2I_Database::get_instance();
        L2I_Roles::get_instance();
        L2I_Credits::get_instance();
        L2I_Restrictions::get_instance();
        L2I_Zoom::get_instance();
        L2I_Ajax::get_instance();
        L2I_Shortcodes::get_instance();
        
        if (is_admin()) {
            L2I_Admin::get_instance();
        }
        
        // Load textdomain
        load_plugin_textdomain('l2i-membership', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script(
            'l2i-membership-frontend',
            L2I_MEMBERSHIP_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            L2I_MEMBERSHIP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'l2i-membership-frontend',
            L2I_MEMBERSHIP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            L2I_MEMBERSHIP_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script('l2i-membership-frontend', 'l2i_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('l2i_membership_nonce'),
            'messages' => array(
                'error' => __('An error occurred. Please try again.', 'l2i-membership'),
                'success' => __('Action completed successfully.', 'l2i-membership'),
                'insufficient_credits' => __('Insufficient credits for this action.', 'l2i-membership'),
                'upgrade_required' => __('Please upgrade your membership to access this feature.', 'l2i-membership')
            )
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'l2i-membership') === false) {
            return;
        }
        
        wp_enqueue_script(
            'l2i-membership-admin',
            L2I_MEMBERSHIP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            L2I_MEMBERSHIP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'l2i-membership-admin',
            L2I_MEMBERSHIP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            L2I_MEMBERSHIP_VERSION
        );
    }
    
    public function activate() {
        L2I_Database::create_tables();
        L2I_Roles::create_roles();
        
        // Set default options
        $default_options = array(
            'l2i_enable_debug' => false,
            'l2i_zoom_api_key' => '',
            'l2i_zoom_api_secret' => '',
            'l2i_default_credits' => array(
                'bid_credits' => 0,
                'connection_credits' => 0,
                'zoom_invites' => 0
            )
        );
        
        foreach ($default_options as $option => $value) {
            add_option($option, $value);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up any scheduled events
        wp_clear_scheduled_hook('l2i_membership_daily_cleanup');
        wp_clear_scheduled_hook('l2i_membership_credit_renewal');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
L2I_Membership_Manager::get_instance();

// Helper functions for external use
function l2i_get_user_credits($user_id, $credit_type = 'all') {
    $credits = L2I_Credits::get_instance();
    return $credits ? $credits->get_user_credits($user_id, $credit_type) : array();
}

function l2i_use_credits($user_id, $credit_type, $amount, $description = '') {
    $credits = L2I_Credits::get_instance();
    return $credits ? $credits->use_credits($user_id, $credit_type, $amount, $description) : false;
}

function l2i_add_credits($user_id, $credit_type, $amount, $description = '') {
    $credits = L2I_Credits::get_instance();
    return $credits ? $credits->add_credits($user_id, $credit_type, $amount, $description) : false;
}

function l2i_get_user_tier($user_id) {
    $roles = L2I_Roles::get_instance();
    return $roles ? $roles->get_user_membership_role($user_id) : '';
}

// Link2Investors specific helper functions (integrated from standalone plugin)
function l2i_is_restricted_member($user_id = null) {
    $restrictions = L2I_Restrictions::get_instance();
    return $restrictions ? $restrictions->l2i_is_restricted_member($user_id) : false;
}

function l2i_clear_restriction_cache($user_id) {
    $restrictions = L2I_Restrictions::get_instance();
    if ($restrictions) {
        $restrictions->l2i_clear_restriction_cache($user_id);
    }
}
