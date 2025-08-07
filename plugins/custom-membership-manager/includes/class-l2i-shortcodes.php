<?php
/**
 * Shortcodes Class
 * Provides frontend functionality through WordPress shortcodes
 */

defined('ABSPATH') || exit;

class L2I_Shortcodes {
    
    private static $instance = null;
    private $credits;
    private $restrictions;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->credits = L2I_Credits::get_instance();
        $this->restrictions = L2I_Restrictions::get_instance();
        
        $this->init_shortcodes();
    }
    
    private function init_shortcodes() {
        // Membership info shortcodes
        add_shortcode('l2i_user_credits', array($this, 'user_credits_shortcode'));
        add_shortcode('l2i_user_tier', array($this, 'user_tier_shortcode'));
        add_shortcode('l2i_membership_status', array($this, 'membership_status_shortcode'));
        
        // Restriction shortcodes
        add_shortcode('l2i_member_only', array($this, 'member_only_shortcode'));
        add_shortcode('l2i_tier_only', array($this, 'tier_only_shortcode'));
        
        // Credit display shortcodes
        add_shortcode('l2i_credit_balance', array($this, 'credit_balance_shortcode'));
        add_shortcode('l2i_credit_history', array($this, 'credit_history_shortcode'));
        
        // Membership plans shortcodes
        add_shortcode('l2i_membership_plans', array($this, 'membership_plans_shortcode'));
        add_shortcode('l2i_upgrade_button', array($this, 'upgrade_button_shortcode'));
        
        // Dashboard shortcodes
        add_shortcode('l2i_user_dashboard', array($this, 'user_dashboard_shortcode'));
        add_shortcode('l2i_restrictions_summary', array($this, 'restrictions_summary_shortcode'));
        
        // Action shortcodes
        add_shortcode('l2i_zoom_button', array($this, 'zoom_button_shortcode'));
        add_shortcode('l2i_credit_purchase', array($this, 'credit_purchase_shortcode'));
    }
    
    /**
     * Display user credits
     */
    public function user_credits_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'all',
            'format' => 'list',
            'show_icons' => 'true'
        ), $atts, 'l2i_user_credits');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please login to view your credits.', 'l2i-membership') . '</p>';
        }
        
        $credits = $this->credits->get_user_credits($user_id);
        
        if (empty($credits)) {
            return '<p>' . __('No credits available.', 'l2i-membership') . '</p>';
        }
        
        $output = '';
        
        if ($atts['format'] === 'list') {
            $output .= '<div class="l2i-credits-list">';
            
            foreach ($credits as $type => $amount) {
                if ($atts['type'] !== 'all' && $atts['type'] !== $type) {
                    continue;
                }
                
                $icon = '';
                if ($atts['show_icons'] === 'true') {
                    $icons = array(
                        'bid_credits' => '💰',
                        'connection_credits' => '🤝',
                        'zoom_invites' => '🎥'
                    );
                    $icon = $icons[$type] ?? '⭐';
                }
                
                $label = ucwords(str_replace('_', ' ', $type));
                $output .= sprintf(
                    '<div class="l2i-credit-item"><span class="icon">%s</span><span class="label">%s:</span> <span class="amount">%d</span></div>',
                    $icon,
                    $label,
                    $amount
                );
            }
            
            $output .= '</div>';
        } else {
            // Inline format
            $credit_strings = array();
            foreach ($credits as $type => $amount) {
                if ($atts['type'] !== 'all' && $atts['type'] !== $type) {
                    continue;
                }
                $label = ucwords(str_replace('_', ' ', $type));
                $credit_strings[] = sprintf('%s: %d', $label, $amount);
            }
            $output = '<span class="l2i-credits-inline">' . implode(' | ', $credit_strings) . '</span>';
        }
        
        return $output;
    }
    
    /**
     * Display user tier
     */
    public function user_tier_shortcode($atts) {
        $atts = shortcode_atts(array(
            'format' => 'badge',
            'show_benefits' => 'false'
        ), $atts, 'l2i_user_tier');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<span class="l2i-tier-guest">' . __('Guest', 'l2i-membership') . '</span>';
        }
        
        $restrictions = $this->restrictions->get_user_restrictions($user_id);
        $tier = $restrictions['tier'];
        
        $tier_labels = array(
            'basic' => __('Basic', 'l2i-membership'),
            'gold' => __('Gold', 'l2i-membership'),
            'premium' => __('Premium', 'l2i-membership'),
            'enterprise' => __('Enterprise', 'l2i-membership')
        );
        
        $tier_label = $tier_labels[$tier] ?? ucfirst($tier);
        
        if ($atts['format'] === 'badge') {
            $output = sprintf('<span class="l2i-tier-badge l2i-tier-%s">%s</span>', $tier, $tier_label);
        } else {
            $output = $tier_label;
        }
        
        if ($atts['show_benefits'] === 'true') {
            $benefits = $this->get_tier_benefits($tier);
            if (!empty($benefits)) {
                $output .= '<div class="l2i-tier-benefits">';
                $output .= '<h4>' . __('Your Benefits:', 'l2i-membership') . '</h4>';
                $output .= '<ul>';
                foreach ($benefits as $benefit) {
                    $output .= '<li>' . esc_html($benefit) . '</li>';
                }
                $output .= '</ul></div>';
            }
        }
        
        return $output;
    }
    
    /**
     * Display membership status
     */
    public function membership_status_shortcode($atts) {
        $atts = shortcode_atts(array(
            'detailed' => 'false'
        ), $atts, 'l2i_membership_status');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<div class="l2i-membership-status">' . __('Not logged in', 'l2i-membership') . '</div>';
        }
        
        $restrictions = $this->restrictions->get_user_restrictions($user_id);
        
        $output = '<div class="l2i-membership-status">';
        $output .= '<h3>' . __('Membership Status', 'l2i-membership') . '</h3>';
        $output .= '<p><strong>' . __('Tier:', 'l2i-membership') . '</strong> ' . ucfirst($restrictions['tier']) . '</p>';
        
        if ($atts['detailed'] === 'true') {
            $output .= '<div class="l2i-status-details">';
            
            // Credits
            $output .= '<h4>' . __('Credits', 'l2i-membership') . '</h4>';
            foreach ($restrictions['credits'] as $type => $amount) {
                $label = ucwords(str_replace('_', ' ', $type));
                $output .= '<p>' . $label . ': ' . $amount . '</p>';
            }
            
            // Limits
            $output .= '<h4>' . __('Monthly Limits', 'l2i-membership') . '</h4>';
            foreach ($restrictions['limits'] as $type => $limit) {
                $label = ucwords(str_replace('_', ' ', $type));
                $limit_text = ($limit === -1) ? __('Unlimited', 'l2i-membership') : $limit;
                $usage = $restrictions['current_usage'][$type] ?? 0;
                $output .= '<p>' . $label . ': ' . $usage . '/' . $limit_text . '</p>';
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Member-only content
     */
    public function member_only_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'message' => __('This content is available to members only.', 'l2i-membership')
        ), $atts, 'l2i_member_only');
        
        if (!is_user_logged_in()) {
            return '<div class="l2i-member-only-message">' . esc_html($atts['message']) . '</div>';
        }
        
        return do_shortcode($content);
    }
    
    /**
     * Tier-specific content
     */
    public function tier_only_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'tier' => 'premium',
            'message' => ''
        ), $atts, 'l2i_tier_only');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            $message = $atts['message'] ?: __('Please login to access this content.', 'l2i-membership');
            return '<div class="l2i-tier-only-message">' . esc_html($message) . '</div>';
        }
        
        $restrictions = $this->restrictions->get_user_restrictions($user_id);
        $user_tier = $restrictions['tier'];
        
        $tier_hierarchy = array('basic', 'gold', 'premium', 'enterprise');
        $required_tier_level = array_search($atts['tier'], $tier_hierarchy);
        $user_tier_level = array_search($user_tier, $tier_hierarchy);
        
        if ($user_tier_level < $required_tier_level) {
            $message = $atts['message'] ?: sprintf(
                __('This content requires %s membership or higher.', 'l2i-membership'),
                ucfirst($atts['tier'])
            );
            return '<div class="l2i-tier-only-message">' . esc_html($message) . '</div>';
        }
        
        return do_shortcode($content);
    }
    
    /**
     * Credit balance display
     */
    public function credit_balance_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'bid_credits',
            'format' => 'number'
        ), $atts, 'l2i_credit_balance');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '0';
        }
        
        $credits = $this->credits->get_user_credits($user_id);
        $balance = $credits[$atts['type']] ?? 0;
        
        if ($atts['format'] === 'formatted') {
            $label = ucwords(str_replace('_', ' ', $atts['type']));
            return sprintf('%s: %d', $label, $balance);
        }
        
        return (string) $balance;
    }
    
    /**
     * Credit history display
     */
    public function credit_history_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => '10',
            'type' => '',
            'format' => 'table'
        ), $atts, 'l2i_credit_history');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please login to view your credit history.', 'l2i-membership') . '</p>';
        }
        
        $history = $this->credits->get_credit_history($user_id, $atts['type'], (int) $atts['limit']);
        
        if (empty($history)) {
            return '<p>' . __('No credit history found.', 'l2i-membership') . '</p>';
        }
        
        $output = '<div class="l2i-credit-history">';
        
        if ($atts['format'] === 'table') {
            $output .= '<table class="l2i-history-table">';
            $output .= '<thead><tr>';
            $output .= '<th>' . __('Date', 'l2i-membership') . '</th>';
            $output .= '<th>' . __('Type', 'l2i-membership') . '</th>';
            $output .= '<th>' . __('Amount', 'l2i-membership') . '</th>';
            $output .= '<th>' . __('Description', 'l2i-membership') . '</th>';
            $output .= '</tr></thead><tbody>';
            
            foreach ($history as $record) {
                $amount_class = $record['amount'] > 0 ? 'positive' : 'negative';
                $output .= '<tr>';
                $output .= '<td>' . date('M j, Y', strtotime($record['created_at'])) . '</td>';
                $output .= '<td>' . esc_html($record['credit_type']) . '</td>';
                $output .= '<td class="' . $amount_class . '">' . $record['amount'] . '</td>';
                $output .= '<td>' . esc_html($record['description']) . '</td>';
                $output .= '</tr>';
            }
            
            $output .= '</tbody></table>';
        } else {
            // List format
            $output .= '<ul class="l2i-history-list">';
            foreach ($history as $record) {
                $amount_text = $record['amount'] > 0 ? '+' . $record['amount'] : $record['amount'];
                $output .= '<li>';
                $output .= '<span class="date">' . date('M j', strtotime($record['created_at'])) . '</span> ';
                $output .= '<span class="amount">' . $amount_text . '</span> ';
                $output .= '<span class="type">' . esc_html($record['credit_type']) . '</span> - ';
                $output .= '<span class="description">' . esc_html($record['description']) . '</span>';
                $output .= '</li>';
            }
            $output .= '</ul>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Membership plans display
     */
    public function membership_plans_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => 'all',
            'billing' => 'monthly',
            'columns' => '3'
        ), $atts, 'l2i_membership_plans');
        
        $ajax = L2I_Ajax::get_instance();
        $plans_data = $ajax->get_membership_plans();
        
        // This is a simplified version - you'd want to style this properly
        $output = '<div class="l2i-membership-plans columns-' . $atts['columns'] . '">';
        
        foreach ($plans_data as $category => $plans) {
            if ($atts['category'] !== 'all' && $atts['category'] !== $category) {
                continue;
            }
            
            $output .= '<div class="plan-category">';
            $output .= '<h3>' . ucfirst($category) . ' Plans</h3>';
            
            foreach ($plans as $plan_key => $plan) {
                if (strpos($plan_key, $atts['billing']) === false) {
                    continue;
                }
                
                $output .= '<div class="membership-plan">';
                $output .= '<h4>' . $plan['name'] . '</h4>';
                $output .= '<div class="price">$' . $plan['price'] . '/' . $plan['billing'] . '</div>';
                $output .= '<ul class="features">';
                foreach ($plan['features'] as $feature) {
                    $output .= '<li>' . esc_html($feature) . '</li>';
                }
                $output .= '</ul>';
                $output .= '<button class="upgrade-btn" data-plan="' . $category . '_' . $plan_key . '">' . __('Choose Plan', 'l2i-membership') . '</button>';
                $output .= '</div>';
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Upgrade button
     */
    public function upgrade_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => __('Upgrade Membership', 'l2i-membership'),
            'class' => 'l2i-upgrade-btn',
            'url' => ''
        ), $atts, 'l2i_upgrade_button');
        
        $url = $atts['url'] ?: home_url('/membership-plans/');
        
        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );
    }
    
    /**
     * User dashboard
     */
    public function user_dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'sections' => 'all'
        ), $atts, 'l2i_user_dashboard');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please login to access your dashboard.', 'l2i-membership') . '</p>';
        }
        
        $restrictions = $this->restrictions->get_user_restrictions($user_id);
        
        $output = '<div class="l2i-user-dashboard">';
        
        // Welcome section
        $user = get_userdata($user_id);
        $output .= '<div class="dashboard-welcome">';
        $output .= '<h2>' . sprintf(__('Welcome, %s!', 'l2i-membership'), $user->display_name) . '</h2>';
        $output .= '<p>' . sprintf(__('You are a %s member', 'l2i-membership'), ucfirst($restrictions['tier'])) . '</p>';
        $output .= '</div>';
        
        // Credits section
        if ($atts['sections'] === 'all' || strpos($atts['sections'], 'credits') !== false) {
            $output .= '<div class="dashboard-section credits-section">';
            $output .= '<h3>' . __('Your Credits', 'l2i-membership') . '</h3>';
            $output .= do_shortcode('[l2i_user_credits format="list" show_icons="true"]');
            $output .= '</div>';
        }
        
        // Limits section
        if ($atts['sections'] === 'all' || strpos($atts['sections'], 'limits') !== false) {
            $output .= '<div class="dashboard-section limits-section">';
            $output .= '<h3>' . __('Usage & Limits', 'l2i-membership') . '</h3>';
            
            foreach ($restrictions['limits'] as $type => $limit) {
                $usage = $restrictions['current_usage'][$type] ?? 0;
                $label = ucwords(str_replace('_', ' ', $type));
                $limit_text = ($limit === -1) ? __('Unlimited', 'l2i-membership') : $limit;
                
                $percentage = ($limit > 0) ? min(100, ($usage / $limit) * 100) : 0;
                
                $output .= '<div class="limit-item">';
                $output .= '<div class="limit-label">' . $label . '</div>';
                $output .= '<div class="limit-bar">';
                $output .= '<div class="limit-progress" style="width: ' . $percentage . '%"></div>';
                $output .= '</div>';
                $output .= '<div class="limit-text">' . $usage . '/' . $limit_text . '</div>';
                $output .= '</div>';
            }
            
            $output .= '</div>';
        }
        
        // Quick actions
        if ($atts['sections'] === 'all' || strpos($atts['sections'], 'actions') !== false) {
            $output .= '<div class="dashboard-section actions-section">';
            $output .= '<h3>' . __('Quick Actions', 'l2i-membership') . '</h3>';
            $output .= '<div class="dashboard-actions">';
            
            if ($restrictions['permissions']['post_projects']) {
                $output .= '<a href="' . home_url('/post-project/') . '" class="action-btn">' . __('Post Project', 'l2i-membership') . '</a>';
            }
            
            $output .= '<a href="' . home_url('/browse-projects/') . '" class="action-btn">' . __('Browse Projects', 'l2i-membership') . '</a>';
            $output .= '<a href="' . home_url('/messages/') . '" class="action-btn">' . __('Messages', 'l2i-membership') . '</a>';
            
            if (!in_array($restrictions['tier'], array('premium', 'enterprise'))) {
                $output .= do_shortcode('[l2i_upgrade_button]');
            }
            
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Restrictions summary
     */
    public function restrictions_summary_shortcode($atts) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please login to view restrictions.', 'l2i-membership') . '</p>';
        }
        
        $restrictions = $this->restrictions->get_user_restrictions($user_id);
        
        $output = '<div class="l2i-restrictions-summary">';
        $output .= '<h3>' . __('Your Membership Permissions', 'l2i-membership') . '</h3>';
        
        $permissions = $restrictions['permissions'];
        foreach ($permissions as $permission => $allowed) {
            $label = ucwords(str_replace('_', ' ', $permission));
            $status = $allowed ? __('Allowed', 'l2i-membership') : __('Restricted', 'l2i-membership');
            $class = $allowed ? 'allowed' : 'restricted';
            
            $output .= '<div class="permission-item ' . $class . '">';
            $output .= '<span class="permission-label">' . $label . '</span>';
            $output .= '<span class="permission-status">' . $status . '</span>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Zoom button
     */
    public function zoom_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'recipient_id' => '',
            'thread_id' => '',
            'text' => __('Start Zoom Meeting', 'l2i-membership')
        ), $atts, 'l2i_zoom_button');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please login to use Zoom meetings.', 'l2i-membership') . '</p>';
        }
        
        $user = get_userdata($user_id);
        if (!$user->has_cap('zoom_meetings')) {
            return '<p>' . __('Your membership level does not include Zoom meetings.', 'l2i-membership') . '</p>';
        }
        
        if (!$this->credits->has_sufficient_credits($user_id, 'zoom_invites', 1)) {
            return '<button class="l2i-zoom-btn disabled" disabled>' . __('No Zoom Credits', 'l2i-membership') . '</button>';
        }
        
        return sprintf(
            '<button class="l2i-zoom-btn" data-recipient-id="%s" data-thread-id="%s">%s</button>',
            esc_attr($atts['recipient_id']),
            esc_attr($atts['thread_id']),
            esc_html($atts['text'])
        );
    }
    
    /**
     * Credit purchase form
     */
    public function credit_purchase_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'all',
            'packages' => 'true'
        ), $atts, 'l2i_credit_purchase');
        
        // This would integrate with your payment system
        $output = '<div class="l2i-credit-purchase">';
        $output .= '<h3>' . __('Purchase Credits', 'l2i-membership') . '</h3>';
        $output .= '<p>' . __('Credit purchasing functionality would be implemented here with your payment gateway.', 'l2i-membership') . '</p>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get tier benefits
     */
    private function get_tier_benefits($tier) {
        $benefits = array(
            'basic' => array(
                __('Limited project browsing', 'l2i-membership'),
                __('Basic messaging', 'l2i-membership'),
                __('Standard support', 'l2i-membership')
            ),
            'gold' => array(
                __('Extended project browsing', 'l2i-membership'),
                __('Enhanced messaging', 'l2i-membership'),
                __('Portfolio upload', 'l2i-membership'),
                __('Basic Zoom meetings', 'l2i-membership')
            ),
            'premium' => array(
                __('Unlimited project browsing', 'l2i-membership'),
                __('Unlimited messaging', 'l2i-membership'),
                __('Advanced portfolio', 'l2i-membership'),
                __('Unlimited Zoom meetings', 'l2i-membership'),
                __('Verified badge', 'l2i-membership'),
                __('Priority support', 'l2i-membership')
            ),
            'enterprise' => array(
                __('All premium features', 'l2i-membership'),
                __('Custom branding', 'l2i-membership'),
                __('Dedicated support', 'l2i-membership'),
                __('API access', 'l2i-membership')
            )
        );
        
        return $benefits[$tier] ?? array();
    }
}