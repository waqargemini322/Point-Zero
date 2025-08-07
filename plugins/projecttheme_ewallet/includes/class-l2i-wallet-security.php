<?php
/**
 * Wallet Security Class
 * Basic security functionality
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Security {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Basic security setup
    }
    
    /**
     * Log security event
     */
    public function log_security_event($user_id, $event_type, $description) {
        // Basic security logging
        do_action('l2i_wallet_security_event', $user_id, $event_type, $description);
    }
}