<?php
/*
Plugin Name: Link2Investors Live Chat System
Plugin URI: https://link2investors.com
Description: Optimized live chat system with Zoom integration and membership restrictions for Link2Investors platform
Version: 2.0.0
Author: Link2Investors Team
Text Domain: l2i-livechat
*/

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('L2I_LIVECHAT_VERSION', '2.0.0');
define('L2I_LIVECHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('L2I_LIVECHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('L2I_LIVECHAT_PLUGIN_FILE', __FILE__);

// Main plugin class
class L2I_LiveChat_Manager {
    
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
        require_once L2I_LIVECHAT_PLUGIN_DIR . 'includes/class-l2i-chat-database.php';
        require_once L2I_LIVECHAT_PLUGIN_DIR . 'includes/class-l2i-chat-core.php';
        require_once L2I_LIVECHAT_PLUGIN_DIR . 'includes/class-l2i-chat-ajax.php';
        require_once L2I_LIVECHAT_PLUGIN_DIR . 'includes/class-l2i-chat-shortcodes.php';
        require_once L2I_LIVECHAT_PLUGIN_DIR . 'includes/class-l2i-chat-restrictions.php';
        require_once L2I_LIVECHAT_PLUGIN_DIR . 'includes/class-l2i-chat-notifications.php';
    }
    
    public function init() {
        // Initialize components
        L2I_Chat_Database::get_instance();
        L2I_Chat_Core::get_instance();
        L2I_Chat_Ajax::get_instance();
        L2I_Chat_Shortcodes::get_instance();
        L2I_Chat_Restrictions::get_instance();
        L2I_Chat_Notifications::get_instance();
        
        // Load textdomain
        load_plugin_textdomain('l2i-livechat', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Update user online status
        if (is_user_logged_in()) {
            $this->update_user_online_status();
        }
    }
    
    public function enqueue_scripts() {
        // Frontend CSS
        wp_enqueue_style(
            'l2i-livechat-style',
            L2I_LIVECHAT_PLUGIN_URL . 'assets/css/livechat.css',
            array(),
            L2I_LIVECHAT_VERSION
        );
        
        // Frontend JavaScript
        wp_enqueue_script(
            'l2i-livechat-script',
            L2I_LIVECHAT_PLUGIN_URL . 'assets/js/livechat.js',
            array('jquery'),
            L2I_LIVECHAT_VERSION,
            true
        );
        
        // File upload script (only if file exists)
        $file_upload_js = L2I_LIVECHAT_PLUGIN_DIR . 'assets/js/file-upload.js';
        if (file_exists($file_upload_js)) {
            wp_enqueue_script(
                'l2i-livechat-upload',
                L2I_LIVECHAT_PLUGIN_URL . 'assets/js/file-upload.js',
                array('jquery'),
                L2I_LIVECHAT_VERSION,
                true
            );
        }
        
        // Localize script for AJAX
        wp_localize_script('l2i-livechat-script', 'l2i_chat', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('l2i_chat_nonce'),
            'current_user_id' => get_current_user_id(),
            'messages' => array(
                'empty_message' => __('Please enter a message.', 'l2i-livechat'),
                'connection_error' => __('Connection error. Please try again.', 'l2i-livechat'),
                'file_upload_error' => __('File upload failed. Please try again.', 'l2i-livechat'),
                'insufficient_credits' => __('You need Connection Credits to start new conversations.', 'l2i-livechat'),
                'membership_required' => __('Please upgrade your membership to access messaging.', 'l2i-livechat'),
                'typing_indicator' => __('is typing...', 'l2i-livechat'),
                'online_status' => __('Online', 'l2i-livechat'),
                'offline_status' => __('Offline', 'l2i-livechat')
            ),
            'settings' => array(
                'update_interval' => 3000, // 3 seconds
                'typing_timeout' => 2000, // 2 seconds
                'max_file_size' => wp_max_upload_size(),
                'allowed_file_types' => array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip')
            )
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'l2i-livechat') === false) {
            return;
        }
        
        // Admin JavaScript (only if file exists)
        $admin_js = L2I_LIVECHAT_PLUGIN_DIR . 'assets/js/admin.js';
        if (file_exists($admin_js)) {
            wp_enqueue_script(
                'l2i-livechat-admin',
                L2I_LIVECHAT_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'wp-util'),
                L2I_LIVECHAT_VERSION,
                true
            );
        }
        
        // Admin CSS (only if file exists)
        $admin_css = L2I_LIVECHAT_PLUGIN_DIR . 'assets/css/admin.css';
        if (file_exists($admin_css)) {
            wp_enqueue_style(
                'l2i-livechat-admin',
                L2I_LIVECHAT_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                L2I_LIVECHAT_VERSION
            );
        }
    }
    
    public function activate() {
        // Create database tables
        $db = L2I_Chat_Database::get_instance();
        $db->create_tables();
        
        // Create messaging page
        $this->create_messaging_page();
        
        // Set default options
        $default_options = array(
            'l2i_chat_enable_file_upload' => true,
            'l2i_chat_max_file_size' => 5, // MB
            'l2i_chat_enable_typing_indicator' => true,
            'l2i_chat_enable_online_status' => true,
            'l2i_chat_message_retention_days' => 365,
            'l2i_chat_enable_email_notifications' => true,
            'l2i_chat_enable_zoom_integration' => true
        );
        
        foreach ($default_options as $option => $value) {
            add_option($option, $value);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('l2i_chat_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create messaging page
     */
    private function create_messaging_page() {
        $page_title = __('Messages', 'l2i-livechat');
        $page_content = '[l2i_messaging_interface]';
        
        // Check if page already exists
        $existing_page = get_page_by_title($page_title);
        
        if (!$existing_page) {
            $page_data = array(
                'post_title' => $page_title,
                'post_content' => $page_content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
                'post_slug' => 'messages'
            );
            
            $page_id = wp_insert_post($page_data);
            
            if ($page_id) {
                update_option('l2i_messaging_page_id', $page_id);
            }
        } else {
            update_option('l2i_messaging_page_id', $existing_page->ID);
        }
    }
    
    /**
     * Update user online status
     */
    private function update_user_online_status() {
        $user_id = get_current_user_id();
        if ($user_id) {
            update_user_meta($user_id, 'l2i_last_online', current_time('timestamp'));
            
            // Also update in our custom table for better performance
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}l2i_user_online_status (user_id, last_seen, updated_at) 
                 VALUES (%d, %s, %s) 
                 ON DUPLICATE KEY UPDATE last_seen = %s, updated_at = %s",
                $user_id,
                current_time('mysql'),
                current_time('mysql'),
                current_time('mysql'),
                current_time('mysql')
            ));
        }
    }
    
    /**
     * Check if user is online
     */
    public static function is_user_online($user_id) {
        $last_seen = get_user_meta($user_id, 'l2i_last_online', true);
        if (!$last_seen) {
            return false;
        }
        
        $current_time = current_time('timestamp');
        $time_diff = $current_time - $last_seen;
        
        // Consider user online if last seen within 5 minutes
        return $time_diff <= 300;
    }
    
    /**
     * Get messaging page URL
     */
    public static function get_messaging_url($thread_id = null) {
        $page_id = get_option('l2i_messaging_page_id');
        if (!$page_id) {
            return home_url('/messages/');
        }
        
        $url = get_permalink($page_id);
        
        if ($thread_id) {
            $url = add_query_arg('thread_id', $thread_id, $url);
        }
        
        return $url;
    }
    
    /**
     * Get conversation URL between two users
     */
    public static function get_conversation_url($user1_id, $user2_id) {
        $chat_core = L2I_Chat_Core::get_instance();
        $thread_id = $chat_core->get_or_create_thread($user1_id, $user2_id);
        
        return self::get_messaging_url($thread_id);
    }
}

// Helper functions for backward compatibility
function projecttheme_is_user_online($uid) {
    return L2I_LiveChat_Manager::is_user_online($uid);
}

function projecttheme_get_pm_link_from_user($current_uid, $uid2) {
    return L2I_LiveChat_Manager::get_conversation_url($current_uid, $uid2);
}

function projecttheme_get_pm_link_for_thid($thread_id) {
    return L2I_LiveChat_Manager::get_messaging_url($thread_id);
}

// Initialize the plugin
L2I_LiveChat_Manager::get_instance();
