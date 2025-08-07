<?php
/**
 * Wallet Payment Gateways Class
 * Basic payment gateway management
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Gateways {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Basic gateway setup
    }
    
    /**
     * Get available gateways
     */
    public function get_available_gateways() {
        return apply_filters('l2i_wallet_available_gateways', array(
            'manual' => __('Manual/Admin', 'l2i-ewallet')
        ));
    }
}