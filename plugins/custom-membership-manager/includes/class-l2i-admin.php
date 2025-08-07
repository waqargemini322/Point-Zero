<?php
/**
 * Admin Class
 * Provides backend management interface for the membership system
 */

defined('ABSPATH') || exit;

class L2I_Admin {
    
    private static $instance = null;
    private $credits;
    private $roles;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->credits = L2I_Credits::get_instance();
        $this->roles = L2I_Roles::get_instance();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // User profile fields
        add_action('show_user_profile', array($this, 'user_profile_fields'));
        add_action('edit_user_profile', array($this, 'user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
        
        // Users list customization
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'user_column_content'), 10, 3);
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            __('L2I Membership', 'l2i-membership'),
            __('L2I Membership', 'l2i-membership'),
            'manage_options',
            'l2i-membership',
            array($this, 'dashboard_page'),
            'dashicons-groups',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'l2i-membership',
            __('Dashboard', 'l2i-membership'),
            __('Dashboard', 'l2i-membership'),
            'manage_options',
            'l2i-membership',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'l2i-membership',
            __('Members', 'l2i-membership'),
            __('Members', 'l2i-membership'),
            'manage_options',
            'l2i-members',
            array($this, 'members_page')
        );
        
        add_submenu_page(
            'l2i-membership',
            __('Credits Management', 'l2i-membership'),
            __('Credits', 'l2i-membership'),
            'manage_options',
            'l2i-credits',
            array($this, 'credits_page')
        );
        
        add_submenu_page(
            'l2i-membership',
            __('Zoom Settings', 'l2i-membership'),
            __('Zoom Settings', 'l2i-membership'),
            'manage_options',
            'l2i-zoom',
            array($this, 'zoom_page')
        );
        
        add_submenu_page(
            'l2i-membership',
            __('Settings', 'l2i-membership'),
            __('Settings', 'l2i-membership'),
            'manage_options',
            'l2i-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        global $wpdb;
        
        // Get statistics
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        
        $membership_roles = L2I_Roles::get_membership_roles();
        $role_stats = array();
        
        foreach ($membership_roles as $role => $label) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                 WHERE meta_key = %s 
                 AND meta_value LIKE %s",
                $wpdb->prefix . 'capabilities',
                '%' . $role . '%'
            ));
            $role_stats[$role] = (int) $count;
        }
        
        // Credit statistics
        $credit_stats = $wpdb->get_results(
            "SELECT credit_type, 
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_added,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_used,
                    COUNT(*) as total_transactions
             FROM {$wpdb->prefix}l2i_credit_history 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY credit_type",
            ARRAY_A
        );
        
        include L2I_MEMBERSHIP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Members page
     */
    public function members_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'edit':
                $this->edit_member_page();
                break;
            case 'view':
                $this->view_member_page();
                break;
            default:
                $this->list_members_page();
                break;
        }
    }
    
    /**
     * List members page
     */
    private function list_members_page() {
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            $this->handle_bulk_actions();
        }
        
        // Get users with membership roles
        $args = array(
            'role__in' => array_keys(L2I_Roles::get_membership_roles()),
            'number' => 20,
            'paged' => $_GET['paged'] ?? 1
        );
        
        if (!empty($_GET['role'])) {
            $args['role'] = sanitize_text_field($_GET['role']);
        }
        
        if (!empty($_GET['search'])) {
            $args['search'] = '*' . sanitize_text_field($_GET['search']) . '*';
        }
        
        $users_query = new WP_User_Query($args);
        $users = $users_query->get_results();
        $total_users = $users_query->get_total();
        
        include L2I_MEMBERSHIP_PLUGIN_DIR . 'admin/views/members-list.php';
    }
    
    /**
     * Edit member page
     */
    private function edit_member_page() {
        $user_id = (int) ($_GET['user_id'] ?? 0);
        $user = get_userdata($user_id);
        
        if (!$user) {
            wp_die(__('Invalid user ID.', 'l2i-membership'));
        }
        
        // Handle form submission
        if (isset($_POST['update_member'])) {
            $this->update_member($user_id);
        }
        
        $restrictions = L2I_Restrictions::get_instance();
        $user_restrictions = $restrictions->get_user_restrictions($user_id);
        $user_credits = $this->credits->get_user_credits($user_id);
        $credit_history = $this->credits->get_credit_history($user_id, '', 20);
        
        include L2I_MEMBERSHIP_PLUGIN_DIR . 'admin/views/member-edit.php';
    }
    
    /**
     * Credits management page
     */
    public function credits_page() {
        $action = $_GET['action'] ?? 'overview';
        
        switch ($action) {
            case 'history':
                $this->credits_history_page();
                break;
            case 'bulk':
                $this->bulk_credits_page();
                break;
            default:
                $this->credits_overview_page();
                break;
        }
    }
    
    /**
     * Credits overview page
     */
    private function credits_overview_page() {
        global $wpdb;
        
        // Get credit statistics
        $credit_stats = $wpdb->get_results(
            "SELECT credit_type,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_issued,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_used,
                    COUNT(DISTINCT user_id) as unique_users
             FROM {$wpdb->prefix}l2i_credit_history
             GROUP BY credit_type",
            ARRAY_A
        );
        
        // Get recent transactions
        $recent_transactions = $wpdb->get_results(
            "SELECT ch.*, u.display_name 
             FROM {$wpdb->prefix}l2i_credit_history ch
             LEFT JOIN {$wpdb->users} u ON ch.user_id = u.ID
             ORDER BY ch.created_at DESC
             LIMIT 50",
            ARRAY_A
        );
        
        include L2I_MEMBERSHIP_PLUGIN_DIR . 'admin/views/credits-overview.php';
    }
    
    /**
     * Zoom settings page
     */
    public function zoom_page() {
        // Handle form submission
        if (isset($_POST['save_zoom_settings'])) {
            $this->save_zoom_settings();
        }
        
        // Test connection if requested
        $test_result = null;
        if (isset($_POST['test_connection'])) {
            $zoom = L2I_Zoom::get_instance();
            $test_result = $zoom->test_api_connection();
        }
        
        $api_key = get_option('l2i_zoom_api_key', '');
        $api_secret = get_option('l2i_zoom_api_secret', '');
        
        include L2I_MEMBERSHIP_PLUGIN_DIR . 'admin/views/zoom-settings.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['save_settings'])) {
            $this->save_settings();
        }
        
        $settings = array(
            'enable_debug' => get_option('l2i_enable_debug', false),
            'default_credits' => get_option('l2i_default_credits', array(
                'bid_credits' => 0,
                'connection_credits' => 0,
                'zoom_invites' => 0
            )),
            'credit_renewal_day' => get_option('l2i_credit_renewal_day', 1),
            'email_notifications' => get_option('l2i_email_notifications', true)
        );
        
        include L2I_MEMBERSHIP_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Zoom settings
        register_setting('l2i_zoom_settings', 'l2i_zoom_api_key');
        register_setting('l2i_zoom_settings', 'l2i_zoom_api_secret');
        
        // General settings
        register_setting('l2i_general_settings', 'l2i_enable_debug');
        register_setting('l2i_general_settings', 'l2i_default_credits');
        register_setting('l2i_general_settings', 'l2i_credit_renewal_day');
        register_setting('l2i_general_settings', 'l2i_email_notifications');
    }
    
    /**
     * Add user profile fields
     */
    public function user_profile_fields($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $user_credits = $this->credits->get_user_credits($user->ID);
        $restrictions = L2I_Restrictions::get_instance();
        $user_restrictions = $restrictions->get_user_restrictions($user->ID);
        
        include L2I_MEMBERSHIP_PLUGIN_DIR . 'admin/views/user-profile-fields.php';
    }
    
    /**
     * Save user profile fields
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Update credits
        if (isset($_POST['l2i_credits'])) {
            foreach ($_POST['l2i_credits'] as $type => $amount) {
                $amount = (int) $amount;
                $current_credits = $this->credits->get_user_credits($user_id);
                $current_amount = $current_credits[$type] ?? 0;
                
                if ($amount !== $current_amount) {
                    $difference = $amount - $current_amount;
                    if ($difference > 0) {
                        $this->credits->add_credits($user_id, $type, $difference, 'Admin adjustment');
                    } else {
                        $this->credits->subtract_credits($user_id, $type, abs($difference), 'Admin adjustment');
                    }
                }
            }
        }
    }
    
    /**
     * Add user columns
     */
    public function add_user_columns($columns) {
        $columns['membership_tier'] = __('Membership Tier', 'l2i-membership');
        $columns['credits'] = __('Credits', 'l2i-membership');
        return $columns;
    }
    
    /**
     * User column content
     */
    public function user_column_content($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'membership_tier':
                $restrictions = L2I_Restrictions::get_instance();
                $user_restrictions = $restrictions->get_user_restrictions($user_id);
                return ucfirst($user_restrictions['tier']);
                
            case 'credits':
                $credits = $this->credits->get_user_credits($user_id);
                $credit_strings = array();
                foreach ($credits as $type => $amount) {
                    $short_type = substr($type, 0, 3);
                    $credit_strings[] = $short_type . ':' . $amount;
                }
                return implode(' | ', $credit_strings);
        }
        
        return $value;
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'l2i_membership_stats',
            __('Membership Statistics', 'l2i-membership'),
            array($this, 'dashboard_widget_stats')
        );
        
        wp_add_dashboard_widget(
            'l2i_recent_activity',
            __('Recent Membership Activity', 'l2i-membership'),
            array($this, 'dashboard_widget_activity')
        );
    }
    
    /**
     * Dashboard widget: Statistics
     */
    public function dashboard_widget_stats() {
        global $wpdb;
        
        $total_members = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = '{$wpdb->prefix}capabilities' 
             AND (meta_value LIKE '%investor%' OR meta_value LIKE '%freelancer%' OR meta_value LIKE '%professional%')"
        );
        
        $active_members = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}l2i_credit_history 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $total_credits_used = $wpdb->get_var(
            "SELECT SUM(ABS(amount)) FROM {$wpdb->prefix}l2i_credit_history 
             WHERE amount < 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        echo '<div class="l2i-dashboard-stats">';
        echo '<div class="stat-item"><strong>' . $total_members . '</strong><br>' . __('Total Members', 'l2i-membership') . '</div>';
        echo '<div class="stat-item"><strong>' . $active_members . '</strong><br>' . __('Active This Month', 'l2i-membership') . '</div>';
        echo '<div class="stat-item"><strong>' . $total_credits_used . '</strong><br>' . __('Credits Used (30d)', 'l2i-membership') . '</div>';
        echo '</div>';
        
        echo '<p><a href="' . admin_url('admin.php?page=l2i-membership') . '">' . __('View Full Dashboard', 'l2i-membership') . '</a></p>';
    }
    
    /**
     * Dashboard widget: Recent activity
     */
    public function dashboard_widget_activity() {
        global $wpdb;
        
        $recent_activity = $wpdb->get_results(
            "SELECT al.*, u.display_name 
             FROM {$wpdb->prefix}l2i_activity_logs al
             LEFT JOIN {$wpdb->users} u ON al.user_id = u.ID
             ORDER BY al.created_at DESC
             LIMIT 10",
            ARRAY_A
        );
        
        if (empty($recent_activity)) {
            echo '<p>' . __('No recent activity.', 'l2i-membership') . '</p>';
            return;
        }
        
        echo '<ul class="l2i-recent-activity">';
        foreach ($recent_activity as $activity) {
            $time_diff = human_time_diff(strtotime($activity['created_at']), current_time('timestamp'));
            echo '<li>';
            echo '<strong>' . esc_html($activity['display_name']) . '</strong> ';
            echo esc_html($activity['action']) . ' ';
            echo '<span class="time">(' . $time_diff . ' ago)</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if Zoom API is configured
        $api_key = get_option('l2i_zoom_api_key', '');
        $api_secret = get_option('l2i_zoom_api_secret', '');
        
        if (empty($api_key) || empty($api_secret)) {
            $current_screen = get_current_screen();
            if (strpos($current_screen->id, 'l2i-') !== false) {
                echo '<div class="notice notice-warning">';
                echo '<p>' . sprintf(
                    __('Zoom integration is not configured. <a href="%s">Configure it now</a> to enable video meetings.', 'l2i-membership'),
                    admin_url('admin.php?page=l2i-zoom')
                ) . '</p>';
                echo '</div>';
            }
        }
        
        // Check database tables
        global $wpdb;
        $tables_exist = true;
        $required_tables = array(
            $wpdb->prefix . 'l2i_user_credits',
            $wpdb->prefix . 'l2i_credit_history',
            $wpdb->prefix . 'l2i_zoom_meetings',
            $wpdb->prefix . 'l2i_activity_logs'
        );
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                $tables_exist = false;
                break;
            }
        }
        
        if (!$tables_exist) {
            echo '<div class="notice notice-error">';
            echo '<p>' . __('Some L2I Membership database tables are missing. Please deactivate and reactivate the plugin to create them.', 'l2i-membership') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        $action = $_POST['action'];
        $users = $_POST['users'] ?? array();
        
        if (empty($users)) {
            return;
        }
        
        switch ($action) {
            case 'add_credits':
                $credit_type = sanitize_text_field($_POST['credit_type']);
                $amount = (int) $_POST['credit_amount'];
                
                foreach ($users as $user_id) {
                    $this->credits->add_credits($user_id, $credit_type, $amount, 'Bulk admin action');
                }
                
                $message = sprintf(__('Added %d %s to %d users.', 'l2i-membership'), $amount, $credit_type, count($users));
                break;
                
            case 'change_role':
                $new_role = sanitize_text_field($_POST['new_role']);
                
                foreach ($users as $user_id) {
                    $user = get_userdata($user_id);
                    if ($user) {
                        $user->set_role($new_role);
                        $this->roles->assign_tier_credits($user_id, $new_role);
                    }
                }
                
                $message = sprintf(__('Changed role for %d users.', 'l2i-membership'), count($users));
                break;
        }
        
        if (isset($message)) {
            add_settings_error('l2i_membership', 'bulk_action', $message, 'success');
        }
    }
    
    /**
     * Update member
     */
    private function update_member($user_id) {
        // Update role if changed
        if (isset($_POST['user_role'])) {
            $new_role = sanitize_text_field($_POST['user_role']);
            $user = get_userdata($user_id);
            if ($user && array_key_exists($new_role, L2I_Roles::get_membership_roles())) {
                $user->set_role($new_role);
                $this->roles->assign_tier_credits($user_id, $new_role);
            }
        }
        
        // Update credits
        if (isset($_POST['credits'])) {
            foreach ($_POST['credits'] as $type => $amount) {
                $amount = (int) $amount;
                $this->credits->set_credits($user_id, $type, $amount, 'Admin update');
            }
        }
        
        add_settings_error('l2i_membership', 'member_updated', __('Member updated successfully.', 'l2i-membership'), 'success');
    }
    
    /**
     * Save Zoom settings
     */
    private function save_zoom_settings() {
        update_option('l2i_zoom_api_key', sanitize_text_field($_POST['l2i_zoom_api_key']));
        update_option('l2i_zoom_api_secret', sanitize_text_field($_POST['l2i_zoom_api_secret']));
        
        add_settings_error('l2i_membership', 'zoom_settings_saved', __('Zoom settings saved successfully.', 'l2i-membership'), 'success');
    }
    
    /**
     * Save general settings
     */
    private function save_settings() {
        update_option('l2i_enable_debug', isset($_POST['l2i_enable_debug']));
        update_option('l2i_email_notifications', isset($_POST['l2i_email_notifications']));
        update_option('l2i_credit_renewal_day', (int) $_POST['l2i_credit_renewal_day']);
        
        $default_credits = array();
        foreach ($_POST['l2i_default_credits'] as $type => $amount) {
            $default_credits[$type] = (int) $amount;
        }
        update_option('l2i_default_credits', $default_credits);
        
        add_settings_error('l2i_membership', 'settings_saved', __('Settings saved successfully.', 'l2i-membership'), 'success');
    }
}