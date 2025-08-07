<?php
/**
 * Wallet Transactions Management Class
 * Handles transaction operations and history management
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Transactions {
    
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
        // Transaction status update hooks
        add_action('l2i_wallet_transaction_status_changed', array($this, 'handle_status_change'), 10, 3);
        
        // Cleanup hooks
        add_action('l2i_wallet_daily_cleanup', array($this, 'cleanup_old_transactions'));
        
        // Integration hooks for membership and chat systems
        add_action('l2i_membership_purchased', array($this, 'record_membership_transaction'), 10, 3);
        add_action('l2i_credit_used', array($this, 'record_credit_usage'), 10, 4);
        add_action('l2i_zoom_meeting_created', array($this, 'record_zoom_transaction'), 10, 3);
    }
    
    /**
     * Get transaction by ID
     */
    public function get_transaction($transaction_id) {
        global $wpdb;
        
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_transactions WHERE id = %d",
            $transaction_id
        ), ARRAY_A);
        
        if ($transaction && !empty($transaction['metadata'])) {
            $transaction['metadata'] = json_decode($transaction['metadata'], true);
        }
        
        return $transaction;
    }
    
    /**
     * Get transaction by transaction ID string
     */
    public function get_transaction_by_id($transaction_id) {
        global $wpdb;
        
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_transactions WHERE transaction_id = %s",
            $transaction_id
        ), ARRAY_A);
        
        if ($transaction && !empty($transaction['metadata'])) {
            $transaction['metadata'] = json_decode($transaction['metadata'], true);
        }
        
        return $transaction;
    }
    
    /**
     * Update transaction status
     */
    public function update_transaction_status($transaction_id, $status, $additional_data = array()) {
        $result = $this->db->update_transaction_status($transaction_id, $status, $additional_data);
        
        if ($result !== false) {
            do_action('l2i_wallet_transaction_status_changed', $transaction_id, $status, $additional_data);
        }
        
        return $result;
    }
    
    /**
     * Handle transaction status changes
     */
    public function handle_status_change($transaction_id, $new_status, $additional_data) {
        $transaction = $this->get_transaction($transaction_id);
        
        if (!$transaction) {
            return;
        }
        
        // Handle different status changes
        switch ($new_status) {
            case 'completed':
                $this->handle_completed_transaction($transaction);
                break;
                
            case 'failed':
                $this->handle_failed_transaction($transaction);
                break;
                
            case 'cancelled':
                $this->handle_cancelled_transaction($transaction);
                break;
        }
        
        // Log the status change
        $this->log_status_change($transaction_id, $transaction['status'], $new_status, $additional_data);
    }
    
    /**
     * Handle completed transactions
     */
    private function handle_completed_transaction($transaction) {
        // Send notification if enabled
        if (get_option('l2i_wallet_enable_notifications', 1)) {
            do_action('l2i_wallet_send_notification', $transaction['user_id'], 'transaction_completed', array(
                'transaction_id' => $transaction['transaction_id'],
                'type' => $transaction['type'],
                'amount' => $transaction['amount']
            ));
        }
        
        // Handle specific transaction types
        switch ($transaction['type']) {
            case 'deposit':
                $this->handle_completed_deposit($transaction);
                break;
                
            case 'withdrawal':
                $this->handle_completed_withdrawal($transaction);
                break;
        }
    }
    
    /**
     * Handle failed transactions
     */
    private function handle_failed_transaction($transaction) {
        // Send notification
        if (get_option('l2i_wallet_enable_notifications', 1)) {
            do_action('l2i_wallet_send_notification', $transaction['user_id'], 'transaction_failed', array(
                'transaction_id' => $transaction['transaction_id'],
                'type' => $transaction['type'],
                'amount' => $transaction['amount']
            ));
        }
        
        // Handle refunds if necessary
        if (in_array($transaction['type'], array('withdrawal', 'payment'))) {
            $this->process_refund($transaction);
        }
    }
    
    /**
     * Handle cancelled transactions
     */
    private function handle_cancelled_transaction($transaction) {
        // Process refund for cancelled payments/withdrawals
        if (in_array($transaction['type'], array('withdrawal', 'payment')) && $transaction['status'] === 'pending') {
            $this->process_refund($transaction);
        }
    }
    
    /**
     * Process refund for failed/cancelled transactions
     */
    private function process_refund($transaction) {
        $wallet_core = L2I_Wallet_Core::get_instance();
        
        $refund_result = $wallet_core->add_funds(
            $transaction['user_id'],
            $transaction['amount'],
            'refund',
            sprintf(__('Refund for %s transaction %s', 'l2i-ewallet'), $transaction['type'], $transaction['transaction_id']),
            array(
                'original_transaction_id' => $transaction['id'],
                'refund_reason' => 'Transaction ' . $transaction['status']
            )
        );
        
        if (!is_wp_error($refund_result)) {
            // Log the refund
            do_action('l2i_wallet_refund_processed', $transaction['user_id'], $transaction['amount'], $refund_result, $transaction['id']);
        }
    }
    
    /**
     * Handle completed deposits
     */
    private function handle_completed_deposit($transaction) {
        // Update user's wallet balance (already handled in core, but we can add additional logic here)
        
        // Fire action for other systems to hook into
        do_action('l2i_wallet_deposit_completed', $transaction['user_id'], $transaction['amount'], $transaction['id']);
    }
    
    /**
     * Handle completed withdrawals
     */
    private function handle_completed_withdrawal($transaction) {
        // Fire action for other systems to hook into
        do_action('l2i_wallet_withdrawal_completed', $transaction['user_id'], $transaction['amount'], $transaction['id']);
    }
    
    /**
     * Get transaction statistics for a user
     */
    public function get_user_transaction_stats($user_id, $period = '30 days') {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN type IN ('deposit', 'transfer_in', 'refund', 'bonus') THEN amount ELSE 0 END) as total_incoming,
                SUM(CASE WHEN type IN ('withdrawal', 'transfer_out', 'payment', 'fee') THEN amount ELSE 0 END) as total_outgoing,
                SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
                SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals,
                SUM(CASE WHEN type LIKE 'transfer_%' THEN amount ELSE 0 END) as total_transfers,
                SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as total_payments
            FROM {$wpdb->prefix}l2i_wallet_transactions 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %s)
            AND status = 'completed'",
            $user_id,
            $period
        ), ARRAY_A);
        
        // Format the stats
        if ($stats) {
            $stats['formatted_total_incoming'] = L2I_EWallet_Manager::format_amount($stats['total_incoming']);
            $stats['formatted_total_outgoing'] = L2I_EWallet_Manager::format_amount($stats['total_outgoing']);
            $stats['formatted_total_deposits'] = L2I_EWallet_Manager::format_amount($stats['total_deposits']);
            $stats['formatted_total_withdrawals'] = L2I_EWallet_Manager::format_amount($stats['total_withdrawals']);
            $stats['formatted_total_transfers'] = L2I_EWallet_Manager::format_amount($stats['total_transfers']);
            $stats['formatted_total_payments'] = L2I_EWallet_Manager::format_amount($stats['total_payments']);
            
            $stats['net_change'] = $stats['total_incoming'] - $stats['total_outgoing'];
            $stats['formatted_net_change'] = L2I_EWallet_Manager::format_amount($stats['net_change']);
        }
        
        return $stats;
    }
    
    /**
     * Get transaction summary by type
     */
    public function get_transaction_summary_by_type($user_id, $period = '30 days') {
        global $wpdb;
        
        $summary = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                type,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount,
                MIN(amount) as min_amount,
                MAX(amount) as max_amount
            FROM {$wpdb->prefix}l2i_wallet_transactions 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %s)
            AND status = 'completed'
            GROUP BY type
            ORDER BY total_amount DESC",
            $user_id,
            $period
        ), ARRAY_A);
        
        // Format the amounts
        foreach ($summary as &$item) {
            $item['formatted_total_amount'] = L2I_EWallet_Manager::format_amount($item['total_amount']);
            $item['formatted_avg_amount'] = L2I_EWallet_Manager::format_amount($item['avg_amount']);
            $item['formatted_min_amount'] = L2I_EWallet_Manager::format_amount($item['min_amount']);
            $item['formatted_max_amount'] = L2I_EWallet_Manager::format_amount($item['max_amount']);
        }
        
        return $summary;
    }
    
    /**
     * Record membership transaction
     */
    public function record_membership_transaction($user_id, $membership_type, $amount) {
        $wallet_core = L2I_Wallet_Core::get_instance();
        
        $transaction_result = $wallet_core->subtract_funds(
            $user_id,
            $amount,
            'payment',
            sprintf(__('Membership upgrade to %s', 'l2i-ewallet'), $membership_type),
            array(
                'membership_type' => $membership_type,
                'source' => 'membership_system'
            )
        );
        
        return $transaction_result;
    }
    
    /**
     * Record credit usage transaction
     */
    public function record_credit_usage($user_id, $credit_type, $amount, $description) {
        // This is typically handled by the credit system, but we can log it for reference
        do_action('l2i_wallet_credit_used_logged', $user_id, $credit_type, $amount, $description);
    }
    
    /**
     * Record Zoom transaction
     */
    public function record_zoom_transaction($user_id, $meeting_id, $cost) {
        // This is typically handled by the Zoom system, but we can log it for reference
        do_action('l2i_wallet_zoom_transaction_logged', $user_id, $meeting_id, $cost);
    }
    
    /**
     * Log status change
     */
    private function log_status_change($transaction_id, $old_status, $new_status, $additional_data) {
        global $wpdb;
        
        // Simple logging - could be expanded to a separate table if needed
        $log_entry = array(
            'transaction_id' => $transaction_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'changed_at' => current_time('mysql'),
            'additional_data' => json_encode($additional_data)
        );
        
        // Fire action for other systems to log this change
        do_action('l2i_wallet_transaction_status_logged', $log_entry);
    }
    
    /**
     * Search transactions
     */
    public function search_transactions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => null,
            'type' => null,
            'status' => null,
            'date_from' => null,
            'date_to' => null,
            'amount_min' => null,
            'amount_max' => null,
            'search_term' => null,
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        // Build WHERE conditions
        if ($args['user_id']) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if ($args['type']) {
            $where_conditions[] = 'type = %s';
            $where_values[] = $args['type'];
        }
        
        if ($args['status']) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if ($args['date_from']) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if ($args['amount_min']) {
            $where_conditions[] = 'amount >= %f';
            $where_values[] = $args['amount_min'];
        }
        
        if ($args['amount_max']) {
            $where_conditions[] = 'amount <= %f';
            $where_values[] = $args['amount_max'];
        }
        
        if ($args['search_term']) {
            $where_conditions[] = '(description LIKE %s OR transaction_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search_term']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Build ORDER BY
        $allowed_order_by = array('created_at', 'amount', 'type', 'status');
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Execute query
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_transactions 
             WHERE $where_clause 
             ORDER BY $order_by $order 
             LIMIT %d OFFSET %d",
            array_merge($where_values, array($args['limit'], $args['offset']))
        );
        
        $transactions = $wpdb->get_results($query, ARRAY_A);
        
        // Decode metadata for each transaction
        foreach ($transactions as &$transaction) {
            if (!empty($transaction['metadata'])) {
                $transaction['metadata'] = json_decode($transaction['metadata'], true);
            }
        }
        
        return $transactions;
    }
    
    /**
     * Get pending transactions that need processing
     */
    public function get_pending_transactions($limit = 100) {
        global $wpdb;
        
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}l2i_wallet_transactions 
             WHERE status = 'pending' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at ASC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
        
        return $transactions;
    }
    
    /**
     * Clean up old transactions
     */
    public function cleanup_old_transactions() {
        global $wpdb;
        
        // Archive very old completed transactions (older than 2 years)
        $archive_date = date('Y-m-d H:i:s', strtotime('-2 years'));
        
        // For now, we'll just mark them for potential archiving
        // In a full implementation, you might move them to an archive table
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}l2i_wallet_transactions 
             SET metadata = JSON_SET(COALESCE(metadata, '{}'), '$.archived', true)
             WHERE created_at < %s 
             AND status = 'completed'
             AND JSON_EXTRACT(metadata, '$.archived') IS NULL",
            $archive_date
        ));
        
        // Delete very old failed/cancelled transactions (older than 1 year)
        $delete_date = date('Y-m-d H:i:s', strtotime('-1 year'));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}l2i_wallet_transactions 
             WHERE created_at < %s 
             AND status IN ('failed', 'cancelled')",
            $delete_date
        ));
        
        do_action('l2i_wallet_transactions_cleaned_up');
    }
}