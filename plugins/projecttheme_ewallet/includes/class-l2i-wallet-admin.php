<?php
/**
 * Wallet Admin Class
 * Basic admin functionality
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Admin {
    
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
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('E-Wallet', 'l2i-ewallet'),
            __('E-Wallet', 'l2i-ewallet'),
            'manage_options',
            'l2i-wallet',
            array($this, 'admin_page'),
            'dashicons-money-alt',
            30
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('E-Wallet Management', 'l2i-ewallet') . '</h1>';
        echo '<p>' . __('E-Wallet system is active and ready for use.', 'l2i-ewallet') . '</p>';
        echo '</div>';
    }
}