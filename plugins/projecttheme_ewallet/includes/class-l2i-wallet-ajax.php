<?php
/**
 * Wallet AJAX Handler Class
 * Handles AJAX requests for wallet operations
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Ajax {
    
    private static $instance = null;
    private $core;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->core = L2I_Wallet_Core::get_instance();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Essential AJAX endpoints for system integration
        add_action('wp_ajax_l2i_get_wallet_balance', array($this, 'get_wallet_balance'));
        add_action('wp_ajax_l2i_get_wallet_details', array($this, 'get_wallet_details'));
        add_action('wp_ajax_l2i_process_membership_payment', array($this, 'process_membership_payment'));
        add_action('wp_ajax_l2i_get_transaction_history', array($this, 'get_transaction_history'));
        add_action('wp_ajax_l2i_transfer_funds', array($this, 'transfer_funds'));
        
        // Public endpoints for logged-in users
        add_action('wp_ajax_nopriv_l2i_get_wallet_balance', array($this, 'get_wallet_balance'));
    }
    
    /**
     * Get user wallet balance
     */
    public function get_wallet_balance() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_ewallet_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'l2i-ewallet')
            ));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-ewallet')
            ));
        }
        
        $balance = $this->core->get_user_balance($user_id);
        $formatted_balance = L2I_EWallet_Manager::format_amount($balance);
        
        wp_send_json_success(array(
            'balance' => $balance,
            'formatted_balance' => $formatted_balance
        ));
    }
    
    /**
     * Get detailed wallet information
     */
    public function get_wallet_details() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_ewallet_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'l2i-ewallet')
            ));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-ewallet')
            ));
        }
        
        $wallet_details = $this->core->get_user_wallet_details($user_id);
        
        if (!$wallet_details) {
            wp_send_json_error(array(
                'message' => __('Failed to retrieve wallet details.', 'l2i-ewallet')
            ));
        }
        
        wp_send_json_success($wallet_details);
    }
    
    /**
     * Process membership payment using wallet funds
     */
    public function process_membership_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_ewallet_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'l2i-ewallet')
            ));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-ewallet')
            ));
        }
        
        $membership_id = sanitize_text_field($_POST['membership_id']);
        $amount = floatval($_POST['amount']);
        
        if (!$membership_id || $amount <= 0) {
            wp_send_json_error(array(
                'message' => __('Invalid membership or amount.', 'l2i-ewallet')
            ));
        }
        
        // Check if user has sufficient funds
        $current_balance = $this->core->get_user_balance($user_id);
        if ($current_balance < $amount) {
            wp_send_json_error(array(
                'message' => __('Insufficient wallet balance.', 'l2i-ewallet')
            ));
        }
        
        // Process payment
        $result = $this->core->subtract_funds(
            $user_id,
            $amount,
            'payment',
            sprintf(__('Membership purchase: %s', 'l2i-ewallet'), $membership_id),
            array('membership_id' => $membership_id)
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        // Fire action for membership system to process
        do_action('l2i_wallet_membership_payment_processed', $user_id, $membership_id, $amount, $result);
        
        wp_send_json_success(array(
            'message' => __('Payment processed successfully.', 'l2i-ewallet'),
            'transaction_id' => $result,
            'new_balance' => $this->core->get_user_balance($user_id)
        ));
    }
    
    /**
     * Get transaction history
     */
    public function get_transaction_history() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_ewallet_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'l2i-ewallet')
            ));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-ewallet')
            ));
        }
        
        $page = intval($_POST['page']) ?: 1;
        $limit = intval($_POST['limit']) ?: 10;
        $type = sanitize_text_field($_POST['type']) ?: null;
        
        $offset = ($page - 1) * $limit;
        
        $transactions = $this->core->get_user_transaction_history($user_id, array(
            'limit' => $limit,
            'offset' => $offset,
            'type' => $type
        ));
        
        // Format transactions for display
        $formatted_transactions = array();
        foreach ($transactions as $transaction) {
            $formatted_transactions[] = array(
                'id' => $transaction['id'],
                'transaction_id' => $transaction['transaction_id'],
                'type' => $transaction['type'],
                'status' => $transaction['status'],
                'amount' => $transaction['amount'],
                'formatted_amount' => L2I_EWallet_Manager::format_amount($transaction['amount']),
                'description' => $transaction['description'],
                'created_at' => $transaction['created_at'],
                'formatted_date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction['created_at']))
            );
        }
        
        wp_send_json_success(array(
            'transactions' => $formatted_transactions,
            'page' => $page,
            'has_more' => count($transactions) === $limit
        ));
    }
    
    /**
     * Transfer funds between users
     */
    public function transfer_funds() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'l2i_ewallet_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'l2i-ewallet')
            ));
        }
        
        $sender_id = get_current_user_id();
        if (!$sender_id) {
            wp_send_json_error(array(
                'message' => __('User not logged in.', 'l2i-ewallet')
            ));
        }
        
        $receiver_username = sanitize_text_field($_POST['receiver_username']);
        $amount = floatval($_POST['amount']);
        $message = sanitize_textarea_field($_POST['message']);
        
        if (!$receiver_username || $amount <= 0) {
            wp_send_json_error(array(
                'message' => __('Invalid recipient or amount.', 'l2i-ewallet')
            ));
        }
        
        // Get receiver user
        $receiver = get_user_by('login', $receiver_username);
        if (!$receiver) {
            $receiver = get_user_by('email', $receiver_username);
        }
        
        if (!$receiver) {
            wp_send_json_error(array(
                'message' => __('Recipient user not found.', 'l2i-ewallet')
            ));
        }
        
        // Process transfer
        $result = $this->core->transfer_funds($sender_id, $receiver->ID, $amount, $message);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Transfer completed successfully.', 'l2i-ewallet'),
            'transfer_id' => $result,
            'new_balance' => $this->core->get_user_balance($sender_id)
        ));
    }
}