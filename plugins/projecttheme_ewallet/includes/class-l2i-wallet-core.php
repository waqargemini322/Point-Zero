<?php
/**
 * Wallet Core Functionality Class
 * Handles all core wallet operations and business logic
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Core {
    
    private static $instance = null;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = L2I_Wallet_Database::get_instance();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // User registration hook
        add_action('user_register', array($this, 'create_user_wallet'));
        
        // Cron hooks
        add_action('l2i_wallet_daily_cleanup', array($this, 'daily_cleanup'));
        add_action('l2i_wallet_process_pending_withdrawals', array($this, 'process_pending_withdrawals'));
        add_action('l2i_wallet_send_low_balance_notifications', array($this, 'send_low_balance_notifications'));
        
        // Integration hooks
        add_action('l2i_membership_purchased', array($this, 'handle_membership_payment'), 10, 3);
        add_action('l2i_credit_purchase', array($this, 'handle_credit_purchase'), 10, 3);
    }
    
    /**
     * Create wallet for new user
     */
    public function create_user_wallet($user_id) {
        L2I_Wallet_Database::ensure_user_wallet_exists($user_id);
        
        // Add welcome bonus if configured
        $welcome_bonus = get_option('l2i_wallet_welcome_bonus', 0);
        if ($welcome_bonus > 0) {
            $this->add_funds($user_id, $welcome_bonus, 'bonus', __('Welcome bonus', 'l2i-ewallet'));
        }
        
        do_action('l2i_wallet_created', $user_id);
    }
    
    /**
     * Get user wallet balance
     */
    public function get_user_balance($user_id) {
        if (!$user_id) {
            return 0.0;
        }
        
        // Ensure wallet exists
        L2I_Wallet_Database::ensure_user_wallet_exists($user_id);
        
        return $this->db->get_user_balance($user_id);
    }
    
    /**
     * Get user wallet details
     */
    public function get_user_wallet_details($user_id) {
        global $wpdb;
        
        if (!$user_id) {
            return false;
        }
        
        L2I_Wallet_Database::ensure_user_wallet_exists($user_id);
        
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_balances WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if ($wallet) {
            // Add formatted amounts
            $wallet['formatted_balance'] = L2I_EWallet_Manager::format_amount($wallet['balance']);
            $wallet['formatted_pending'] = L2I_EWallet_Manager::format_amount($wallet['pending_balance']);
            $wallet['formatted_frozen'] = L2I_EWallet_Manager::format_amount($wallet['frozen_balance']);
            $wallet['formatted_total_deposited'] = L2I_EWallet_Manager::format_amount($wallet['total_deposited']);
            $wallet['formatted_total_withdrawn'] = L2I_EWallet_Manager::format_amount($wallet['total_withdrawn']);
            
            // Add available balance (balance - frozen)
            $wallet['available_balance'] = max(0, $wallet['balance'] - $wallet['frozen_balance']);
            $wallet['formatted_available'] = L2I_EWallet_Manager::format_amount($wallet['available_balance']);
        }
        
        return $wallet;
    }
    
    /**
     * Add funds to user wallet
     */
    public function add_funds($user_id, $amount, $transaction_type = 'deposit', $description = '', $metadata = array()) {
        if (!$user_id || $amount <= 0) {
            return new WP_Error('invalid_params', __('Invalid user ID or amount.', 'l2i-ewallet'));
        }
        
        // Check if wallet is in maintenance mode
        if (L2I_EWallet_Manager::is_maintenance_mode()) {
            return new WP_Error('maintenance_mode', __('Wallet is currently under maintenance.', 'l2i-ewallet'));
        }
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get current balance
            $current_balance = $this->get_user_balance($user_id);
            $new_balance = $current_balance + $amount;
            
            // Create transaction record
            $transaction_data = array(
                'user_id' => $user_id,
                'type' => $transaction_type,
                'status' => 'completed',
                'amount' => $amount,
                'net_amount' => $amount,
                'balance_before' => $current_balance,
                'balance_after' => $new_balance,
                'description' => $description,
                'metadata' => json_encode($metadata)
            );
            
            $transaction_id = $this->db->create_transaction($transaction_data);
            
            if (!$transaction_id) {
                throw new Exception(__('Failed to create transaction record.', 'l2i-ewallet'));
            }
            
            // Update wallet balance
            $update_result = $this->db->update_user_balance($user_id, $new_balance, $transaction_id);
            
            if ($update_result === false) {
                throw new Exception(__('Failed to update wallet balance.', 'l2i-ewallet'));
            }
            
            // Update total deposited if it's a deposit
            if (in_array($transaction_type, array('deposit', 'bonus'))) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}l2i_wallet_balances 
                     SET total_deposited = total_deposited + %f 
                     WHERE user_id = %d",
                    $amount, $user_id
                ));
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Fire action hooks
            do_action('l2i_wallet_funds_added', $user_id, $amount, $transaction_type, $transaction_id);
            do_action('l2i_wallet_balance_changed', $user_id, $current_balance, $new_balance);
            
            // Send notification if enabled
            if (get_option('l2i_wallet_enable_notifications', 1)) {
                do_action('l2i_wallet_send_notification', $user_id, 'funds_added', array(
                    'amount' => $amount,
                    'new_balance' => $new_balance,
                    'transaction_id' => $transaction_id
                ));
            }
            
            return $transaction_id;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            return new WP_Error('transaction_failed', $e->getMessage());
        }
    }
    
    /**
     * Subtract funds from user wallet
     */
    public function subtract_funds($user_id, $amount, $transaction_type = 'withdrawal', $description = '', $metadata = array()) {
        if (!$user_id || $amount <= 0) {
            return new WP_Error('invalid_params', __('Invalid user ID or amount.', 'l2i-ewallet'));
        }
        
        // Check if wallet is in maintenance mode
        if (L2I_EWallet_Manager::is_maintenance_mode()) {
            return new WP_Error('maintenance_mode', __('Wallet is currently under maintenance.', 'l2i-ewallet'));
        }
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get current balance
            $current_balance = $this->get_user_balance($user_id);
            
            // Check if user has sufficient funds
            if ($current_balance < $amount) {
                throw new Exception(__('Insufficient funds in wallet.', 'l2i-ewallet'));
            }
            
            $new_balance = $current_balance - $amount;
            
            // Create transaction record
            $transaction_data = array(
                'user_id' => $user_id,
                'type' => $transaction_type,
                'status' => 'completed',
                'amount' => $amount,
                'net_amount' => $amount,
                'balance_before' => $current_balance,
                'balance_after' => $new_balance,
                'description' => $description,
                'metadata' => json_encode($metadata)
            );
            
            $transaction_id = $this->db->create_transaction($transaction_data);
            
            if (!$transaction_id) {
                throw new Exception(__('Failed to create transaction record.', 'l2i-ewallet'));
            }
            
            // Update wallet balance
            $update_result = $this->db->update_user_balance($user_id, $new_balance, $transaction_id);
            
            if ($update_result === false) {
                throw new Exception(__('Failed to update wallet balance.', 'l2i-ewallet'));
            }
            
            // Update total withdrawn if it's a withdrawal
            if (in_array($transaction_type, array('withdrawal', 'payment'))) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}l2i_wallet_balances 
                     SET total_withdrawn = total_withdrawn + %f 
                     WHERE user_id = %d",
                    $amount, $user_id
                ));
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Fire action hooks
            do_action('l2i_wallet_funds_subtracted', $user_id, $amount, $transaction_type, $transaction_id);
            do_action('l2i_wallet_balance_changed', $user_id, $current_balance, $new_balance);
            
            // Send notification if enabled
            if (get_option('l2i_wallet_enable_notifications', 1)) {
                do_action('l2i_wallet_send_notification', $user_id, 'funds_subtracted', array(
                    'amount' => $amount,
                    'new_balance' => $new_balance,
                    'transaction_id' => $transaction_id
                ));
            }
            
            // Check for low balance notification
            $low_balance_threshold = get_option('l2i_wallet_low_balance_threshold', 20);
            if ($new_balance <= $low_balance_threshold) {
                do_action('l2i_wallet_send_notification', $user_id, 'low_balance', array(
                    'balance' => $new_balance,
                    'threshold' => $low_balance_threshold
                ));
            }
            
            return $transaction_id;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            return new WP_Error('transaction_failed', $e->getMessage());
        }
    }
    
    /**
     * Transfer funds between users
     */
    public function transfer_funds($sender_id, $receiver_id, $amount, $message = '', $metadata = array()) {
        if (!$sender_id || !$receiver_id || $amount <= 0) {
            return new WP_Error('invalid_params', __('Invalid parameters for transfer.', 'l2i-ewallet'));
        }
        
        if ($sender_id == $receiver_id) {
            return new WP_Error('invalid_transfer', __('Cannot transfer funds to yourself.', 'l2i-ewallet'));
        }
        
        // Check if both users exist
        if (!get_userdata($sender_id) || !get_userdata($receiver_id)) {
            return new WP_Error('invalid_users', __('Invalid sender or receiver.', 'l2i-ewallet'));
        }
        
        // Calculate fees
        $fee = $this->calculate_transfer_fee($amount);
        $total_deduction = $amount + $fee;
        
        // Check if sender has sufficient funds
        $sender_balance = $this->get_user_balance($sender_id);
        if ($sender_balance < $total_deduction) {
            return new WP_Error('insufficient_funds', __('Insufficient funds for transfer including fees.', 'l2i-ewallet'));
        }
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Create sender transaction (outgoing)
            $sender_transaction_data = array(
                'user_id' => $sender_id,
                'type' => 'transfer_out',
                'status' => 'completed',
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $total_deduction,
                'balance_before' => $sender_balance,
                'balance_after' => $sender_balance - $total_deduction,
                'description' => sprintf(__('Transfer to %s', 'l2i-ewallet'), get_userdata($receiver_id)->display_name),
                'metadata' => json_encode(array_merge($metadata, array('receiver_id' => $receiver_id, 'message' => $message)))
            );
            
            $sender_transaction_id = $this->db->create_transaction($sender_transaction_data);
            
            if (!$sender_transaction_id) {
                throw new Exception(__('Failed to create sender transaction.', 'l2i-ewallet'));
            }
            
            // Update sender balance
            $this->db->update_user_balance($sender_id, $sender_balance - $total_deduction, $sender_transaction_id);
            
            // Create receiver transaction (incoming)
            $receiver_balance = $this->get_user_balance($receiver_id);
            
            $receiver_transaction_data = array(
                'user_id' => $receiver_id,
                'type' => 'transfer_in',
                'status' => 'completed',
                'amount' => $amount,
                'fee' => 0,
                'net_amount' => $amount,
                'balance_before' => $receiver_balance,
                'balance_after' => $receiver_balance + $amount,
                'description' => sprintf(__('Transfer from %s', 'l2i-ewallet'), get_userdata($sender_id)->display_name),
                'metadata' => json_encode(array_merge($metadata, array('sender_id' => $sender_id, 'message' => $message)))
            );
            
            $receiver_transaction_id = $this->db->create_transaction($receiver_transaction_data);
            
            if (!$receiver_transaction_id) {
                throw new Exception(__('Failed to create receiver transaction.', 'l2i-ewallet'));
            }
            
            // Update receiver balance
            $this->db->update_user_balance($receiver_id, $receiver_balance + $amount, $receiver_transaction_id);
            
            // Create transfer record
            $transfer_data = array(
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'sender_transaction_id' => $sender_transaction_id,
                'receiver_transaction_id' => $receiver_transaction_id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $amount,
                'status' => 'completed',
                'message' => $message,
                'completed_at' => current_time('mysql')
            );
            
            $transfer_result = $wpdb->insert(
                $wpdb->prefix . 'l2i_wallet_transfers',
                $transfer_data,
                array('%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s')
            );
            
            if ($transfer_result === false) {
                throw new Exception(__('Failed to create transfer record.', 'l2i-ewallet'));
            }
            
            $transfer_id = $wpdb->insert_id;
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Fire action hooks
            do_action('l2i_wallet_transfer_completed', $sender_id, $receiver_id, $amount, $transfer_id);
            
            // Send notifications
            if (get_option('l2i_wallet_enable_notifications', 1)) {
                // Notify sender
                do_action('l2i_wallet_send_notification', $sender_id, 'transfer_sent', array(
                    'amount' => $amount,
                    'fee' => $fee,
                    'receiver_name' => get_userdata($receiver_id)->display_name,
                    'transfer_id' => $transfer_id
                ));
                
                // Notify receiver
                do_action('l2i_wallet_send_notification', $receiver_id, 'transfer_received', array(
                    'amount' => $amount,
                    'sender_name' => get_userdata($sender_id)->display_name,
                    'transfer_id' => $transfer_id,
                    'message' => $message
                ));
            }
            
            return $transfer_id;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            return new WP_Error('transfer_failed', $e->getMessage());
        }
    }
    
    /**
     * Process deposit request
     */
    public function process_deposit($user_id, $amount, $gateway, $gateway_data = array()) {
        if (!$user_id || $amount <= 0) {
            return new WP_Error('invalid_params', __('Invalid deposit parameters.', 'l2i-ewallet'));
        }
        
        // Validate deposit limits
        $min_deposit = get_option('l2i_wallet_min_deposit', 10);
        $max_deposit = get_option('l2i_wallet_max_deposit', 10000);
        
        if ($amount < $min_deposit) {
            return new WP_Error('amount_too_low', sprintf(__('Minimum deposit amount is %s.', 'l2i-ewallet'), L2I_EWallet_Manager::format_amount($min_deposit)));
        }
        
        if ($amount > $max_deposit) {
            return new WP_Error('amount_too_high', sprintf(__('Maximum deposit amount is %s.', 'l2i-ewallet'), L2I_EWallet_Manager::format_amount($max_deposit)));
        }
        
        // Create pending transaction
        $transaction_data = array(
            'user_id' => $user_id,
            'type' => 'deposit',
            'status' => 'pending',
            'amount' => $amount,
            'net_amount' => $amount,
            'gateway' => $gateway,
            'description' => sprintf(__('Deposit via %s', 'l2i-ewallet'), $gateway),
            'metadata' => json_encode($gateway_data)
        );
        
        $transaction_id = $this->db->create_transaction($transaction_data);
        
        if (!$transaction_id) {
            return new WP_Error('transaction_failed', __('Failed to create deposit transaction.', 'l2i-ewallet'));
        }
        
        // Fire action for payment gateway processing
        do_action('l2i_wallet_process_deposit', $transaction_id, $user_id, $amount, $gateway, $gateway_data);
        
        return $transaction_id;
    }
    
    /**
     * Process withdrawal request
     */
    public function process_withdrawal($user_id, $amount, $payment_method_id, $notes = '') {
        if (!$user_id || $amount <= 0) {
            return new WP_Error('invalid_params', __('Invalid withdrawal parameters.', 'l2i-ewallet'));
        }
        
        // Validate withdrawal limits
        $min_withdrawal = get_option('l2i_wallet_min_withdrawal', 5);
        $max_withdrawal = get_option('l2i_wallet_max_withdrawal', 5000);
        
        if ($amount < $min_withdrawal) {
            return new WP_Error('amount_too_low', sprintf(__('Minimum withdrawal amount is %s.', 'l2i-ewallet'), L2I_EWallet_Manager::format_amount($min_withdrawal)));
        }
        
        if ($amount > $max_withdrawal) {
            return new WP_Error('amount_too_high', sprintf(__('Maximum withdrawal amount is %s.', 'l2i-ewallet'), L2I_EWallet_Manager::format_amount($max_withdrawal)));
        }
        
        // Calculate fees
        $fee = $this->calculate_withdrawal_fee($amount);
        $total_deduction = $amount + $fee;
        
        // Check if user has sufficient funds
        $current_balance = $this->get_user_balance($user_id);
        if ($current_balance < $total_deduction) {
            return new WP_Error('insufficient_funds', __('Insufficient funds for withdrawal including fees.', 'l2i-ewallet'));
        }
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Create transaction record
            $transaction_data = array(
                'user_id' => $user_id,
                'type' => 'withdrawal',
                'status' => 'pending',
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $total_deduction,
                'balance_before' => $current_balance,
                'balance_after' => $current_balance - $total_deduction,
                'description' => __('Withdrawal request', 'l2i-ewallet'),
                'metadata' => json_encode(array('payment_method_id' => $payment_method_id, 'notes' => $notes))
            );
            
            $transaction_id = $this->db->create_transaction($transaction_data);
            
            if (!$transaction_id) {
                throw new Exception(__('Failed to create withdrawal transaction.', 'l2i-ewallet'));
            }
            
            // Temporarily hold the funds (subtract from balance)
            $this->db->update_user_balance($user_id, $current_balance - $total_deduction, $transaction_id);
            
            // Create withdrawal request record
            $withdrawal_data = array(
                'user_id' => $user_id,
                'transaction_id' => $transaction_id,
                'payment_method_id' => $payment_method_id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $amount - $fee,
                'status' => 'pending'
            );
            
            $withdrawal_result = $wpdb->insert(
                $wpdb->prefix . 'l2i_wallet_withdrawals',
                $withdrawal_data,
                array('%d', '%d', '%d', '%f', '%f', '%f', '%s')
            );
            
            if ($withdrawal_result === false) {
                throw new Exception(__('Failed to create withdrawal request.', 'l2i-ewallet'));
            }
            
            $withdrawal_id = $wpdb->insert_id;
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Fire action hooks
            do_action('l2i_wallet_withdrawal_requested', $user_id, $amount, $withdrawal_id, $transaction_id);
            
            // Send notification
            if (get_option('l2i_wallet_enable_notifications', 1)) {
                do_action('l2i_wallet_send_notification', $user_id, 'withdrawal_requested', array(
                    'amount' => $amount,
                    'fee' => $fee,
                    'withdrawal_id' => $withdrawal_id
                ));
                
                // Notify admins
                do_action('l2i_wallet_send_admin_notification', 'withdrawal_requested', array(
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'withdrawal_id' => $withdrawal_id
                ));
            }
            
            return $withdrawal_id;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            return new WP_Error('withdrawal_failed', $e->getMessage());
        }
    }
    
    /**
     * Calculate transfer fee
     */
    public function calculate_transfer_fee($amount) {
        $fee_type = get_option('l2i_wallet_transfer_fee_type', 'fixed');
        $fee_value = get_option('l2i_wallet_transfer_fee_value', 0);
        
        if ($fee_type === 'percentage') {
            return ($amount * $fee_value) / 100;
        } else {
            return $fee_value;
        }
    }
    
    /**
     * Calculate withdrawal fee
     */
    public function calculate_withdrawal_fee($amount) {
        $fee_type = get_option('l2i_wallet_withdrawal_fee_type', 'percentage');
        $fee_value = get_option('l2i_wallet_withdrawal_fee_value', 2.5);
        
        if ($fee_type === 'percentage') {
            return ($amount * $fee_value) / 100;
        } else {
            return $fee_value;
        }
    }
    
    /**
     * Get user transaction history
     */
    public function get_user_transaction_history($user_id, $args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'type' => null,
            'status' => null,
            'date_from' => null,
            'date_to' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return $this->db->get_user_transactions(
            $user_id,
            $args['limit'],
            $args['offset'],
            $args['type'],
            $args['status']
        );
    }
    
    /**
     * Handle membership payment
     */
    public function handle_membership_payment($user_id, $membership_id, $amount) {
        // Subtract funds from wallet for membership purchase
        $result = $this->subtract_funds(
            $user_id,
            $amount,
            'payment',
            sprintf(__('Membership purchase: %s', 'l2i-ewallet'), $membership_id)
        );
        
        return $result;
    }
    
    /**
     * Handle credit purchase
     */
    public function handle_credit_purchase($user_id, $credit_type, $amount) {
        // Subtract funds from wallet for credit purchase
        $result = $this->subtract_funds(
            $user_id,
            $amount,
            'payment',
            sprintf(__('Credit purchase: %s', 'l2i-ewallet'), $credit_type)
        );
        
        return $result;
    }
    
    /**
     * Daily cleanup tasks
     */
    public function daily_cleanup() {
        // Clean up old data
        $this->db->cleanup_old_data();
        
        // Process expired transactions
        $this->process_expired_transactions();
        
        do_action('l2i_wallet_daily_cleanup_completed');
    }
    
    /**
     * Process pending withdrawals
     */
    public function process_pending_withdrawals() {
        if (get_option('l2i_wallet_auto_approve_withdrawals', 0)) {
            // Auto-approve small withdrawals if enabled
            $this->auto_approve_small_withdrawals();
        }
        
        do_action('l2i_wallet_pending_withdrawals_processed');
    }
    
    /**
     * Send low balance notifications
     */
    public function send_low_balance_notifications() {
        global $wpdb;
        
        $threshold = get_option('l2i_wallet_low_balance_threshold', 20);
        
        $low_balance_users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, balance FROM {$wpdb->prefix}l2i_wallet_balances 
             WHERE balance <= %f AND balance > 0",
            $threshold
        ), ARRAY_A);
        
        foreach ($low_balance_users as $user) {
            do_action('l2i_wallet_send_notification', $user['user_id'], 'low_balance', array(
                'balance' => $user['balance'],
                'threshold' => $threshold
            ));
        }
    }
    
    /**
     * Process expired transactions
     */
    private function process_expired_transactions() {
        global $wpdb;
        
        // Cancel pending transactions older than 24 hours
        $wpdb->query(
            "UPDATE {$wpdb->prefix}l2i_wallet_transactions 
             SET status = 'cancelled' 
             WHERE status = 'pending' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
    
    /**
     * Auto-approve small withdrawals
     */
    private function auto_approve_small_withdrawals() {
        global $wpdb;
        
        $auto_approve_limit = get_option('l2i_wallet_auto_approve_limit', 100);
        
        $pending_withdrawals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_withdrawals 
             WHERE status = 'pending' 
             AND amount <= %f 
             ORDER BY requested_at ASC 
             LIMIT 10",
            $auto_approve_limit
        ), ARRAY_A);
        
        foreach ($pending_withdrawals as $withdrawal) {
            // Auto-approve the withdrawal
            $this->approve_withdrawal($withdrawal['id'], 0, 'Auto-approved (small amount)');
        }
    }
    
    /**
     * Approve withdrawal
     */
    public function approve_withdrawal($withdrawal_id, $admin_id = 0, $notes = '') {
        global $wpdb;
        
        // Get withdrawal details
        $withdrawal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_withdrawals WHERE id = %d",
            $withdrawal_id
        ), ARRAY_A);
        
        if (!$withdrawal || $withdrawal['status'] !== 'pending') {
            return new WP_Error('invalid_withdrawal', __('Invalid or already processed withdrawal.', 'l2i-ewallet'));
        }
        
        // Update withdrawal status
        $wpdb->update(
            $wpdb->prefix . 'l2i_wallet_withdrawals',
            array(
                'status' => 'approved',
                'admin_notes' => $notes,
                'processed_by' => $admin_id,
                'processed_at' => current_time('mysql')
            ),
            array('id' => $withdrawal_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        // Update transaction status
        $this->db->update_transaction_status($withdrawal['transaction_id'], 'completed');
        
        // Fire action hooks
        do_action('l2i_wallet_withdrawal_approved', $withdrawal['user_id'], $withdrawal['amount'], $withdrawal_id);
        
        return true;
    }
    
    /**
     * Reject withdrawal
     */
    public function reject_withdrawal($withdrawal_id, $admin_id = 0, $reason = '') {
        global $wpdb;
        
        // Get withdrawal details
        $withdrawal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_withdrawals WHERE id = %d",
            $withdrawal_id
        ), ARRAY_A);
        
        if (!$withdrawal || $withdrawal['status'] !== 'pending') {
            return new WP_Error('invalid_withdrawal', __('Invalid or already processed withdrawal.', 'l2i-ewallet'));
        }
        
        // Refund the amount back to user's wallet
        $this->add_funds(
            $withdrawal['user_id'],
            $withdrawal['amount'] + $withdrawal['fee'],
            'refund',
            __('Withdrawal refund', 'l2i-ewallet')
        );
        
        // Update withdrawal status
        $wpdb->update(
            $wpdb->prefix . 'l2i_wallet_withdrawals',
            array(
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'processed_by' => $admin_id,
                'processed_at' => current_time('mysql')
            ),
            array('id' => $withdrawal_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        // Update transaction status
        $this->db->update_transaction_status($withdrawal['transaction_id'], 'failed');
        
        // Fire action hooks
        do_action('l2i_wallet_withdrawal_rejected', $withdrawal['user_id'], $withdrawal['amount'], $withdrawal_id, $reason);
        
        return true;
    }
}