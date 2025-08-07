<?php
/**
 * Wallet Shortcodes Class
 * Provides shortcodes for displaying wallet information
 */

defined('ABSPATH') || exit;

class L2I_Wallet_Shortcodes {
    
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
        $this->init_shortcodes();
    }
    
    private function init_shortcodes() {
        // Essential shortcodes for system integration
        add_shortcode('l2i_wallet_balance', array($this, 'wallet_balance_shortcode'));
        add_shortcode('l2i_wallet_dashboard', array($this, 'wallet_dashboard_shortcode'));
        add_shortcode('l2i_wallet_transaction_history', array($this, 'transaction_history_shortcode'));
        add_shortcode('l2i_wallet_deposit_form', array($this, 'deposit_form_shortcode'));
        add_shortcode('l2i_wallet_withdraw_form', array($this, 'withdraw_form_shortcode'));
        add_shortcode('l2i_wallet_transfer_form', array($this, 'transfer_form_shortcode'));
        
        // Legacy compatibility shortcodes
        add_shortcode('project_theme_wallet_balance', array($this, 'wallet_balance_shortcode'));
    }
    
    /**
     * Display wallet balance
     */
    public function wallet_balance_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'format' => 'formatted', // 'formatted' or 'raw'
            'show_currency' => 'yes'
        ), $atts, 'l2i_wallet_balance');
        
        if (!$atts['user_id']) {
            return '<span class="wallet-balance-error">' . __('Please log in to view wallet balance.', 'l2i-ewallet') . '</span>';
        }
        
        $balance = $this->core->get_user_balance($atts['user_id']);
        
        if ($atts['format'] === 'raw') {
            return $balance;
        }
        
        $formatted_balance = L2I_EWallet_Manager::format_amount($balance);
        
        return '<span class="wallet-balance" data-balance="' . esc_attr($balance) . '">' . esc_html($formatted_balance) . '</span>';
    }
    
    /**
     * Display wallet dashboard
     */
    public function wallet_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="wallet-login-required">' . __('Please log in to access your wallet.', 'l2i-ewallet') . '</div>';
        }
        
        $user_id = get_current_user_id();
        $wallet_details = $this->core->get_user_wallet_details($user_id);
        
        if (!$wallet_details) {
            return '<div class="wallet-error">' . __('Unable to load wallet information.', 'l2i-ewallet') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="l2i-wallet-dashboard">
            <div class="wallet-overview">
                <h3><?php _e('Wallet Overview', 'l2i-ewallet'); ?></h3>
                
                <div class="wallet-stats row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h4><?php _e('Available Balance', 'l2i-ewallet'); ?></h4>
                            <div class="stat-value"><?php echo esc_html($wallet_details['formatted_available']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h4><?php _e('Total Balance', 'l2i-ewallet'); ?></h4>
                            <div class="stat-value"><?php echo esc_html($wallet_details['formatted_balance']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h4><?php _e('Total Deposited', 'l2i-ewallet'); ?></h4>
                            <div class="stat-value"><?php echo esc_html($wallet_details['formatted_total_deposited']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h4><?php _e('Total Withdrawn', 'l2i-ewallet'); ?></h4>
                            <div class="stat-value"><?php echo esc_html($wallet_details['formatted_total_withdrawn']); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if ($wallet_details['pending_balance'] > 0): ?>
                <div class="wallet-pending">
                    <strong><?php _e('Pending Balance:', 'l2i-ewallet'); ?></strong> 
                    <?php echo esc_html($wallet_details['formatted_pending']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($wallet_details['frozen_balance'] > 0): ?>
                <div class="wallet-frozen">
                    <strong><?php _e('Frozen Balance:', 'l2i-ewallet'); ?></strong> 
                    <?php echo esc_html($wallet_details['formatted_frozen']); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="wallet-actions">
                <h4><?php _e('Quick Actions', 'l2i-ewallet'); ?></h4>
                <div class="action-buttons">
                    <a href="<?php echo get_permalink(get_option('l2i_wallet_deposit_page')); ?>" class="btn btn-success">
                        <?php _e('Deposit Funds', 'l2i-ewallet'); ?>
                    </a>
                    <a href="<?php echo get_permalink(get_option('l2i_wallet_withdraw_page')); ?>" class="btn btn-warning">
                        <?php _e('Withdraw Funds', 'l2i-ewallet'); ?>
                    </a>
                    <a href="<?php echo get_permalink(get_option('l2i_wallet_transfer_page')); ?>" class="btn btn-info">
                        <?php _e('Transfer Funds', 'l2i-ewallet'); ?>
                    </a>
                    <a href="<?php echo get_permalink(get_option('l2i_wallet_history_page')); ?>" class="btn btn-secondary">
                        <?php _e('View History', 'l2i-ewallet'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <style>
        .l2i-wallet-dashboard .wallet-stats { margin: 20px 0; }
        .l2i-wallet-dashboard .stat-card { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            text-align: center; 
            margin-bottom: 15px;
        }
        .l2i-wallet-dashboard .stat-card h4 { 
            margin: 0 0 10px 0; 
            font-size: 14px; 
            color: #666; 
        }
        .l2i-wallet-dashboard .stat-value { 
            font-size: 24px; 
            font-weight: bold; 
            color: #28a745; 
        }
        .l2i-wallet-dashboard .wallet-pending,
        .l2i-wallet-dashboard .wallet-frozen { 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 4px; 
        }
        .l2i-wallet-dashboard .wallet-pending { background: #fff3cd; border: 1px solid #ffeaa7; }
        .l2i-wallet-dashboard .wallet-frozen { background: #f8d7da; border: 1px solid #f5c6cb; }
        .l2i-wallet-dashboard .action-buttons { margin-top: 15px; }
        .l2i-wallet-dashboard .action-buttons .btn { margin-right: 10px; margin-bottom: 10px; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Display transaction history
     */
    public function transaction_history_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="wallet-login-required">' . __('Please log in to view transaction history.', 'l2i-ewallet') . '</div>';
        }
        
        $atts = shortcode_atts(array(
            'limit' => 20,
            'type' => null,
            'show_pagination' => 'yes'
        ), $atts, 'l2i_wallet_transaction_history');
        
        $user_id = get_current_user_id();
        $page = get_query_var('paged') ? get_query_var('paged') : 1;
        $offset = ($page - 1) * $atts['limit'];
        
        $transactions = $this->core->get_user_transaction_history($user_id, array(
            'limit' => $atts['limit'],
            'offset' => $offset,
            'type' => $atts['type']
        ));
        
        ob_start();
        ?>
        <div class="l2i-wallet-transactions">
            <h3><?php _e('Transaction History', 'l2i-ewallet'); ?></h3>
            
            <?php if (empty($transactions)): ?>
                <div class="no-transactions">
                    <p><?php _e('No transactions found.', 'l2i-ewallet'); ?></p>
                </div>
            <?php else: ?>
                <div class="transactions-table">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'l2i-ewallet'); ?></th>
                                <th><?php _e('Type', 'l2i-ewallet'); ?></th>
                                <th><?php _e('Description', 'l2i-ewallet'); ?></th>
                                <th><?php _e('Amount', 'l2i-ewallet'); ?></th>
                                <th><?php _e('Status', 'l2i-ewallet'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($transaction['created_at'])); ?></td>
                                <td>
                                    <span class="transaction-type type-<?php echo esc_attr($transaction['type']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($transaction['description']); ?></td>
                                <td>
                                    <span class="transaction-amount <?php echo in_array($transaction['type'], array('deposit', 'transfer_in', 'refund', 'bonus')) ? 'positive' : 'negative'; ?>">
                                        <?php echo in_array($transaction['type'], array('deposit', 'transfer_in', 'refund', 'bonus')) ? '+' : '-'; ?>
                                        <?php echo L2I_EWallet_Manager::format_amount($transaction['amount']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="transaction-status status-<?php echo esc_attr($transaction['status']); ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .l2i-wallet-transactions .transactions-table { margin-top: 20px; }
        .l2i-wallet-transactions .transaction-amount.positive { color: #28a745; }
        .l2i-wallet-transactions .transaction-amount.negative { color: #dc3545; }
        .l2i-wallet-transactions .transaction-status.status-completed { color: #28a745; }
        .l2i-wallet-transactions .transaction-status.status-pending { color: #ffc107; }
        .l2i-wallet-transactions .transaction-status.status-failed { color: #dc3545; }
        .l2i-wallet-transactions .transaction-type { 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-size: 12px; 
            background: #e9ecef; 
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Basic deposit form (placeholder)
     */
    public function deposit_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="wallet-login-required">' . __('Please log in to deposit funds.', 'l2i-ewallet') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="l2i-wallet-deposit-form">
            <h3><?php _e('Deposit Funds', 'l2i-ewallet'); ?></h3>
            <div class="alert alert-info">
                <?php _e('Deposit functionality will be integrated with your payment gateways. Contact your administrator to set up payment methods.', 'l2i-ewallet'); ?>
            </div>
            
            <div class="current-balance">
                <strong><?php _e('Current Balance:', 'l2i-ewallet'); ?></strong> 
                <?php echo $this->wallet_balance_shortcode(array()); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Basic withdraw form (placeholder)
     */
    public function withdraw_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="wallet-login-required">' . __('Please log in to withdraw funds.', 'l2i-ewallet') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="l2i-wallet-withdraw-form">
            <h3><?php _e('Withdraw Funds', 'l2i-ewallet'); ?></h3>
            <div class="alert alert-info">
                <?php _e('Withdrawal functionality will be integrated with your payment methods. Contact your administrator to set up withdrawal options.', 'l2i-ewallet'); ?>
            </div>
            
            <div class="current-balance">
                <strong><?php _e('Available Balance:', 'l2i-ewallet'); ?></strong> 
                <?php echo $this->wallet_balance_shortcode(array()); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Basic transfer form
     */
    public function transfer_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="wallet-login-required">' . __('Please log in to transfer funds.', 'l2i-ewallet') . '</div>';
        }
        
        $user_id = get_current_user_id();
        $balance = $this->core->get_user_balance($user_id);
        
        ob_start();
        ?>
        <div class="l2i-wallet-transfer-form">
            <h3><?php _e('Transfer Funds', 'l2i-ewallet'); ?></h3>
            
            <div class="current-balance">
                <strong><?php _e('Available Balance:', 'l2i-ewallet'); ?></strong> 
                <?php echo L2I_EWallet_Manager::format_amount($balance); ?>
            </div>
            
            <form id="wallet-transfer-form" method="post">
                <?php wp_nonce_field('l2i_ewallet_transfer', 'transfer_nonce'); ?>
                
                <div class="form-group">
                    <label for="receiver_username"><?php _e('Recipient (Username or Email)', 'l2i-ewallet'); ?></label>
                    <input type="text" id="receiver_username" name="receiver_username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="transfer_amount"><?php _e('Amount', 'l2i-ewallet'); ?></label>
                    <input type="number" id="transfer_amount" name="amount" class="form-control" step="0.01" min="0.01" max="<?php echo esc_attr($balance); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="transfer_message"><?php _e('Message (Optional)', 'l2i-ewallet'); ?></label>
                    <textarea id="transfer_message" name="message" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" <?php echo $balance <= 0 ? 'disabled' : ''; ?>>
                    <?php _e('Transfer Funds', 'l2i-ewallet'); ?>
                </button>
            </form>
            
            <div id="transfer-result" style="margin-top: 15px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wallet-transfer-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'l2i_transfer_funds',
                    nonce: '<?php echo wp_create_nonce('l2i_ewallet_nonce'); ?>',
                    receiver_username: $('#receiver_username').val(),
                    amount: $('#transfer_amount').val(),
                    message: $('#transfer_message').val()
                };
                
                $('#transfer-result').html('<div class="alert alert-info"><?php _e('Processing transfer...', 'l2i-ewallet'); ?></div>');
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', formData, function(response) {
                    if (response.success) {
                        $('#transfer-result').html('<div class="alert alert-success">' + response.data.message + '</div>');
                        $('#wallet-transfer-form')[0].reset();
                        // Update balance display if it exists
                        $('.wallet-balance').text(response.data.new_balance);
                    } else {
                        $('#transfer-result').html('<div class="alert alert-danger">' + response.data.message + '</div>');
                    }
                }).fail(function() {
                    $('#transfer-result').html('<div class="alert alert-danger"><?php _e('Transfer failed. Please try again.', 'l2i-ewallet'); ?></div>');
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}