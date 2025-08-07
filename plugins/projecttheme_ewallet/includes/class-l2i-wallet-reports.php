<?php
/**
 * Wallet Reports Class
 * Basic reporting functionality
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Reports {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Basic reports setup
    }
    
    /**
     * Get basic wallet statistics
     */
    public function get_wallet_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(DISTINCT user_id) as total_users,
                SUM(balance) as total_balance,
                COUNT(*) as total_wallets
            FROM {$wpdb->prefix}l2i_wallet_balances",
            ARRAY_A
        );
        
        return $stats;
    }
}