<?php
/**
 * Wallet Notifications Class
 * Basic notification functionality
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Notifications {
    
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
        add_action('l2i_wallet_send_notification', array($this, 'send_notification'), 10, 3);
    }
    
    /**
     * Send notification
     */
    public function send_notification($user_id, $type, $data) {
        // Basic notification logging
        do_action('l2i_wallet_notification_sent', $user_id, $type, $data);
    }
}