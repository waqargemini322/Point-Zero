<?php
/**
 * Plugin Name: Link2Investors E-Wallet Pro
 * Plugin URI: https://link2investors.com/
 * Description: Comprehensive e-wallet system for Link2Investors platform with deposits, withdrawals, transfers, and payment gateway integrations.
 * Version: 2.0.0
 * Author: Link2Investors Development Team
 * Author URI: https://link2investors.com/
 * Text Domain: l2i-ewallet
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * Network: false
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('L2I_EWALLET_VERSION', '2.0.0');
define('L2I_EWALLET_PLUGIN_FILE', __FILE__);
define('L2I_EWALLET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('L2I_EWALLET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('L2I_EWALLET_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main E-Wallet Plugin Class
 */
class L2I_EWallet_Manager {
    
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
        $this->init();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('init', array($this, 'init_payment_handlers'));
        add_action('template_redirect', array($this, 'handle_payment_callbacks'));
    }
    
    private function load_dependencies() {
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-database.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-core.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-transactions.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-payments.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-gateways.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-security.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-ajax.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-shortcodes.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-admin.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-notifications.php';
        require_once L2I_EWALLET_PLUGIN_DIR . 'includes/class-l2i-wallet-reports.php';
    }
    
    public function init() {
        // Initialize components
        L2I_Wallet_Database::get_instance();
        L2I_Wallet_Core::get_instance();
        L2I_Wallet_Transactions::get_instance();
        L2I_Wallet_Payments::get_instance();
        L2I_Wallet_Gateways::get_instance();
        L2I_Wallet_Security::get_instance();
        L2I_Wallet_Ajax::get_instance();
        L2I_Wallet_Shortcodes::get_instance();
        L2I_Wallet_Admin::get_instance();
        L2I_Wallet_Notifications::get_instance();
        L2I_Wallet_Reports::get_instance();
        
        // Load textdomain
        load_plugin_textdomain('l2i-ewallet', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Initialize legacy compatibility
        $this->init_legacy_compatibility();
    }
    
    public function enqueue_scripts() {
        // Frontend CSS
        wp_enqueue_style(
            'l2i-ewallet-style',
            L2I_EWALLET_PLUGIN_URL . 'assets/css/ewallet.css',
            array(),
            L2I_EWALLET_VERSION
        );
        
        // Frontend JavaScript
        wp_enqueue_script(
            'l2i-ewallet-script',
            L2I_EWALLET_PLUGIN_URL . 'assets/js/ewallet.js',
            array('jquery'),
            L2I_EWALLET_VERSION,
            true
        );
        
        // Payment processing script
        wp_enqueue_script(
            'l2i-ewallet-payments',
            L2I_EWALLET_PLUGIN_URL . 'assets/js/payments.js',
            array('jquery'),
            L2I_EWALLET_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script(
            'l2i-ewallet-script',
            'l2i_ewallet_vars',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('l2i_ewallet_nonce'),
                'currency_symbol' => get_option('l2i_wallet_currency_symbol', '$'),
                'currency_code' => get_option('l2i_wallet_currency_code', 'USD'),
                'min_deposit' => get_option('l2i_wallet_min_deposit', 10),
                'max_deposit' => get_option('l2i_wallet_max_deposit', 10000),
                'min_withdrawal' => get_option('l2i_wallet_min_withdrawal', 5),
                'max_withdrawal' => get_option('l2i_wallet_max_withdrawal', 5000),
                'strings' => array(
                    'loading' => __('Loading...', 'l2i-ewallet'),
                    'error' => __('An error occurred. Please try again.', 'l2i-ewallet'),
                    'confirm_transaction' => __('Are you sure you want to proceed with this transaction?', 'l2i-ewallet'),
                    'insufficient_funds' => __('Insufficient funds in your wallet.', 'l2i-ewallet'),
                    'transaction_success' => __('Transaction completed successfully!', 'l2i-ewallet'),
                    'invalid_amount' => __('Please enter a valid amount.', 'l2i-ewallet')
                )
            )
        );
    }
    
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'l2i-wallet') === false && $hook !== 'toplevel_page_l2i-wallet') {
            return;
        }
        
        wp_enqueue_script(
            'l2i-ewallet-admin',
            L2I_EWALLET_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            L2I_EWALLET_VERSION,
            true
        );
        
        wp_enqueue_style(
            'l2i-ewallet-admin',
            L2I_EWALLET_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            L2I_EWALLET_VERSION
        );
        
        // Chart.js for reports
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );
    }
    
    public function init_payment_handlers() {
        // Initialize payment gateway handlers
        do_action('l2i_wallet_init_payment_handlers');
    }
    
    public function handle_payment_callbacks() {
        // Handle payment gateway callbacks
        if (isset($_GET['l2i_wallet_callback'])) {
            $gateway = sanitize_text_field($_GET['l2i_wallet_callback']);
            do_action('l2i_wallet_payment_callback_' . $gateway);
        }
        
        // Handle IPN/webhooks
        if (isset($_GET['l2i_wallet_ipn'])) {
            $gateway = sanitize_text_field($_GET['l2i_wallet_ipn']);
            do_action('l2i_wallet_payment_ipn_' . $gateway);
        }
    }
    
    public function activate() {
        // Create database tables
        L2I_Wallet_Database::create_tables();
        
        // Create wallet pages
        $this->create_wallet_pages();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled crons
        wp_clear_scheduled_hook('l2i_wallet_daily_cleanup');
        wp_clear_scheduled_hook('l2i_wallet_process_pending_withdrawals');
        wp_clear_scheduled_hook('l2i_wallet_send_low_balance_notifications');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_wallet_pages() {
        $pages = array(
            'l2i_wallet_dashboard_page' => array(
                'title' => __('My Wallet', 'l2i-ewallet'),
                'content' => '[l2i_wallet_dashboard]'
            ),
            'l2i_wallet_deposit_page' => array(
                'title' => __('Deposit Funds', 'l2i-ewallet'),
                'content' => '[l2i_wallet_deposit_form]'
            ),
            'l2i_wallet_withdraw_page' => array(
                'title' => __('Withdraw Funds', 'l2i-ewallet'),
                'content' => '[l2i_wallet_withdraw_form]'
            ),
            'l2i_wallet_transfer_page' => array(
                'title' => __('Transfer Funds', 'l2i-ewallet'),
                'content' => '[l2i_wallet_transfer_form]'
            ),
            'l2i_wallet_history_page' => array(
                'title' => __('Transaction History', 'l2i-ewallet'),
                'content' => '[l2i_wallet_transaction_history]'
            )
        );
        
        foreach ($pages as $option_name => $page_data) {
            if (!get_option($option_name)) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => 1
                ));
                
                if ($page_id) {
                    update_option($option_name, $page_id);
                }
            }
        }
    }
    
    private function set_default_options() {
        $defaults = array(
            'l2i_wallet_currency_code' => 'USD',
            'l2i_wallet_currency_symbol' => '$',
            'l2i_wallet_currency_position' => 'before',
            'l2i_wallet_decimal_places' => 2,
            'l2i_wallet_min_deposit' => 10,
            'l2i_wallet_max_deposit' => 10000,
            'l2i_wallet_min_withdrawal' => 5,
            'l2i_wallet_max_withdrawal' => 5000,
            'l2i_wallet_withdrawal_fee_type' => 'percentage',
            'l2i_wallet_withdrawal_fee_value' => 2.5,
            'l2i_wallet_transfer_fee_type' => 'fixed',
            'l2i_wallet_transfer_fee_value' => 0,
            'l2i_wallet_auto_approve_deposits' => 0,
            'l2i_wallet_auto_approve_withdrawals' => 0,
            'l2i_wallet_require_kyc' => 0,
            'l2i_wallet_enable_2fa' => 1,
            'l2i_wallet_low_balance_threshold' => 20,
            'l2i_wallet_enable_notifications' => 1,
            'l2i_wallet_enable_email_notifications' => 1,
            'l2i_wallet_enable_sms_notifications' => 0,
            'l2i_wallet_maintenance_mode' => 0,
            'l2i_wallet_version' => L2I_EWALLET_VERSION
        );
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    private function schedule_cron_jobs() {
        // Daily cleanup
        if (!wp_next_scheduled('l2i_wallet_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'l2i_wallet_daily_cleanup');
        }
        
        // Process pending withdrawals
        if (!wp_next_scheduled('l2i_wallet_process_pending_withdrawals')) {
            wp_schedule_event(time(), 'hourly', 'l2i_wallet_process_pending_withdrawals');
        }
        
        // Send low balance notifications
        if (!wp_next_scheduled('l2i_wallet_send_low_balance_notifications')) {
            wp_schedule_event(time(), 'daily', 'l2i_wallet_send_low_balance_notifications');
        }
    }
    
    /**
     * Legacy compatibility functions
     */
    private function init_legacy_compatibility() {
        // Maintain compatibility with existing projectTheme_get_credits function
        if (!function_exists('projectTheme_get_credits')) {
            function projectTheme_get_credits($user_id) {
                $wallet_core = L2I_Wallet_Core::get_instance();
                return $wallet_core->get_user_balance($user_id);
            }
        }
        
        // Legacy function for adding credits
        if (!function_exists('projectTheme_add_credits')) {
            function projectTheme_add_credits($user_id, $amount, $reason = '') {
                $wallet_core = L2I_Wallet_Core::get_instance();
                return $wallet_core->add_funds($user_id, $amount, 'system_credit', $reason);
            }
        }
        
        // Legacy function for subtracting credits
        if (!function_exists('projectTheme_subtract_credits')) {
            function projectTheme_subtract_credits($user_id, $amount, $reason = '') {
                $wallet_core = L2I_Wallet_Core::get_instance();
                return $wallet_core->subtract_funds($user_id, $amount, 'system_debit', $reason);
            }
        }
        
        // Legacy function for getting payments page URL
        if (!function_exists('ProjectTheme_get_payments_page_url')) {
            function ProjectTheme_get_payments_page_url($action = '') {
                switch ($action) {
                    case 'deposit':
                        return get_permalink(get_option('l2i_wallet_deposit_page'));
                    case 'withdraw':
                        return get_permalink(get_option('l2i_wallet_withdraw_page'));
                    case 'transfer':
                        return get_permalink(get_option('l2i_wallet_transfer_page'));
                    default:
                        return get_permalink(get_option('l2i_wallet_dashboard_page'));
                }
            }
        }
    }
    
    /**
     * Get wallet balance for display
     */
    public static function format_amount($amount, $currency_code = null) {
        if ($currency_code === null) {
            $currency_code = get_option('l2i_wallet_currency_code', 'USD');
        }
        
        $symbol = get_option('l2i_wallet_currency_symbol', '$');
        $position = get_option('l2i_wallet_currency_position', 'before');
        $decimals = get_option('l2i_wallet_decimal_places', 2);
        
        $formatted_amount = number_format($amount, $decimals);
        
        if ($position === 'before') {
            return $symbol . $formatted_amount;
        } else {
            return $formatted_amount . $symbol;
        }
    }
    
    /**
     * Check if wallet is in maintenance mode
     */
    public static function is_maintenance_mode() {
        return get_option('l2i_wallet_maintenance_mode', 0) == 1;
    }
    
    /**
     * Get wallet instance
     */
    public static function get_wallet_core() {
        return L2I_Wallet_Core::get_instance();
    }
}

// Initialize the plugin
function l2i_ewallet_init() {
    return L2I_EWallet_Manager::get_instance();
}

// Hook into WordPress
add_action('plugins_loaded', 'l2i_ewallet_init');

// Helper functions for external use
function l2i_get_user_wallet_balance($user_id) {
    $wallet = L2I_EWallet_Manager::get_wallet_core();
    return $wallet ? $wallet->get_user_balance($user_id) : 0;
}

function l2i_format_wallet_amount($amount, $currency_code = null) {
    return L2I_EWallet_Manager::format_amount($amount, $currency_code);
}

function l2i_wallet_add_funds($user_id, $amount, $transaction_type = 'deposit', $description = '') {
    $wallet = L2I_EWallet_Manager::get_wallet_core();
    return $wallet ? $wallet->add_funds($user_id, $amount, $transaction_type, $description) : false;
}

function l2i_wallet_subtract_funds($user_id, $amount, $transaction_type = 'withdrawal', $description = '') {
    $wallet = L2I_EWallet_Manager::get_wallet_core();
    return $wallet ? $wallet->subtract_funds($user_id, $amount, $transaction_type, $description) : false;
}

// Maintain the old function for backward compatibility
function sitemile_small_fnc_content() {
    echo L2I_EWALLET_VERSION;
}
?>
