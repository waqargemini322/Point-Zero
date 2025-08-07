<?php
/*
Plugin Name: ProjectTheme Service Offering (Fixed)
Plugin URI: https://sitemile.com/
Description: Adds service offering for the project theme. Fixed version to prevent fatal errors.
Author: SiteMile.com (Fixed by L2I Development)
Author URI: https://sitemile.com/
Version: 1.6.1
Text Domain: ss_project
*/

// Exit if accessed directly
defined('ABSPATH') || exit;

// Check if required theme functions exist before proceeding
add_action('after_setup_theme', 'pt_service_offering_check_dependencies');

function pt_service_offering_check_dependencies() {
    // Check if ProjectTheme functions exist
    if (!function_exists('ProjectTheme_insert_pages') || 
        !function_exists('ProjectTheme_get_currency') ||
        !function_exists('projectTheme_generate_thumb')) {
        
        add_action('admin_notices', 'pt_service_offering_dependency_notice');
        return;
    }
    
    // Initialize plugin if dependencies are met
    pt_service_offering_init();
}

function pt_service_offering_dependency_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>ProjectTheme Service Offering:</strong> This plugin requires ProjectTheme to be active and properly configured.';
    echo '</p></div>';
}

function pt_service_offering_init() {
    // Include files with proper path checking
    $plugin_dir = plugin_dir_path(__FILE__);
    
    if (file_exists($plugin_dir . 'post-service.php')) {
        include $plugin_dir . 'post-service.php';
    }
    
    if (file_exists($plugin_dir . 'my-account/my-services.php')) {
        include $plugin_dir . 'my-account/my-services.php';
    }
    
    if (file_exists($plugin_dir . 'widgets/service-widget.php')) {
        include $plugin_dir . 'widgets/service-widget.php';
    }

    // Initialize hooks
    add_action('init', 'pt_service_offering');
    add_action('template_redirect', 'pt_service_offering_template_redirect');
    add_shortcode('project_theme_my_account_my_services', 'project_theme_account_my_services');
    add_filter('ProjectTheme_my_account_main_menu', 'ProjectTheme_my_account_main_menu_serv');
    add_shortcode('project_theme_post_service', 'project_theme_post_service_fn');
}

function ProjectTheme_my_account_main_menu_serv() {
    // Check if required options exist
    $services_page_id = get_option('ProjectTheme_my_services_page_id');
    if (!$services_page_id) {
        return;
    }
    ?>
    <li class="sidebar-item"> 
        <a class="sidebar-link" href="<?php echo esc_url(get_permalink($services_page_id)); ?>" aria-expanded="false">
            <span class="hide-menu"><i class="fas fa-user-friends"></i> <?php _e("Services", 'ProjectTheme'); ?></span>
        </a>
    </li>
    <?php
}

function pt_service_offering_template_redirect() {
    $ProjectTheme_post_new_page_id = get_option('ProjectTheme_post_service');
    global $post;

    if (!$post || $post->ID != $ProjectTheme_post_new_page_id) {
        return;
    }

    if (!is_user_logged_in()) { 
        $login_url = function_exists('ProjectTheme_login_url') ? ProjectTheme_login_url() : wp_login_url();
        wp_redirect($login_url . '?redirect_to=' . urlencode(get_permalink($ProjectTheme_post_new_page_id))); 
        exit; 
    }
    
    global $current_user;
    $current_user = wp_get_current_user();

    if (!isset($_GET['projectid'])) {
        $set_ad = 1; 
    } else {
        $set_ad = 0;
    }

    if (!empty($_GET['projectid'])) {
        $my_main_post = get_post(intval($_GET['projectid']));
        $cu = wp_get_current_user();

        if ($my_main_post && ($my_main_post->post_author != $current_user->ID && $cu->user_login != 'sitemileadmin')) {
            wp_redirect(home_url()); 
            exit;
        }
    }

    if ($set_ad == 1) {
        $pid = ProjectTheme_get_auto_draft_service($current_user->ID);
        wp_redirect(ProjectTheme_post_new_with_pid_stuff_thg_service($pid));
        exit;
    }

    // Include the post new file with proper path checking
    $post_new_file = plugin_dir_path(__FILE__) . 'post_new_post_service.php';
    if (file_exists($post_new_file)) {
        include($post_new_file);
    }
}

function ProjectTheme_post_new_with_pid_stuff_thg_service($pid, $step = 1, $fin = 'no') {
    $using_perm = function_exists('ProjectTheme_using_permalinks') ? ProjectTheme_using_permalinks() : get_option('permalink_structure');
    $post_service_page = get_option('ProjectTheme_post_service');
    
    if ($using_perm) {
        return get_permalink($post_service_page) . "?post_new_step=" . $step . "&" . ($fin != "no" ? 'finalize=1&' : '') . "projectid=" . $pid;
    } else {
        return home_url() . "/?page_id=" . $post_service_page . "&" . ($fin != "no" ? 'finalize=1&' : '') . "post_new_step=" . $step . "&projectid=" . $pid;
    }
}

function ProjectTheme_get_auto_draft_service($uid) {
    global $wpdb;
    
    $querystr = $wpdb->prepare("
        SELECT distinct wposts.*
        FROM {$wpdb->posts} wposts 
        WHERE wposts.post_author = %d 
        AND wposts.post_status = 'auto-draft'
        AND wposts.post_type = 'service'
        ORDER BY wposts.ID DESC 
        LIMIT 1", 
        $uid
    );

    $row = $wpdb->get_results($querystr, OBJECT);
    if (count($row) > 0) {
        $row = $row[0];
        return $row->ID;
    }

    return ProjectTheme_create_auto_draft_service($uid);
}

function ProjectTheme_create_auto_draft_service($uid) {
    $my_post = array();
    $my_post['post_title'] = 'Auto Draft';
    $my_post['post_type'] = 'service';
    $my_post['post_status'] = 'auto-draft';
    $my_post['post_author'] = $uid;
    $pid = wp_insert_post($my_post, true);

    if (!is_wp_error($pid)) {
        update_post_meta($pid, 'featured_paid', '0');
        update_post_meta($pid, 'private_bids_paid', '0');
        update_post_meta($pid, 'hide_project_paid', '0');
        update_post_meta($pid, 'base_fee_paid', '0');

        do_action('ProjectTheme_when_creating_auto_draft');
    }

    return $pid;
}

function pt_service_offering() {
    // Check if register_post_type function exists
    if (!function_exists('register_post_type')) {
        return;
    }
    
    $icn = get_template_directory_uri() . "/images/proj_icon.png";

    register_post_type('service',
        array(
            'labels' => array(
                'name' => __('Services', 'ProjectTheme'),
                'singular_name' => __('Service', 'ProjectTheme'),
                'add_new' => __('Add New Service', 'ProjectTheme'),
                'new_item' => __('New Service', 'ProjectTheme'),
                'edit_item' => __('Edit Service', 'ProjectTheme'),
                'add_new_item' => __('Add New Service', 'ProjectTheme'),
                'search_items' => __('Search Services', 'ProjectTheme'),
            ),
            'public' => true,
            'has_archive' => 'service-list',
            'menu_position' => 5,
            'register_meta_box_cb' => 'projectTheme_set_metaboxes_service',
            'rewrite' => true,
            'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'),
            '_builtin' => false,
            'menu_icon' => $icn,
            'publicly_queryable' => true,
            'hierarchical' => false
        )
    );

    // Register taxonomy with error checking
    if (function_exists('register_taxonomy')) {
        register_taxonomy('service_cat', 'service', array(
            'rewrite' => true,
            'hierarchical' => true,
            'label' => __('Service Categories', 'ProjectTheme')
        ));
    }
}

function projectTheme_set_metaboxes_service() {
    if (function_exists('add_meta_box')) {
        add_meta_box(
            'service-metaboxes',
            'Service Images',
            'projectTheme_theme_service_images',
            'service',
            'advanced',
            'high'
        );
    }
}

function pt_activation_service_offering() {
    // Check if required functions exist before creating pages
    if (function_exists('ProjectTheme_insert_pages')) {
        ProjectTheme_insert_pages('ProjectTheme_post_service', 'Post Service', '[project_theme_post_service]');
    }
    
    if (function_exists('ProjectTheme_insert_pages_account')) {
        ProjectTheme_insert_pages_account(
            'ProjectTheme_my_services_page_id',
            "My Services",
            '[project_theme_my_account_my_services]',
            get_option('ProjectTheme_my_account_page_id')
        );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'pt_activation_service_offering');

function projectTheme_get_service_acc() {
    $pid = get_the_ID();
    global $post, $current_user;
    $current_user = wp_get_current_user();
    $post = get_post($pid);
    $uid = $current_user->ID;

    $ending = get_post_meta(get_the_ID(), 'ending', true);
    $sec = $ending - current_time('timestamp', 0);
    $location = get_post_meta(get_the_ID(), 'Location', true);
    $closed = get_post_meta(get_the_ID(), 'closed', true);
    $featured = get_post_meta(get_the_ID(), 'featured', true);
    $private_bids = get_post_meta(get_the_ID(), 'private_bids', true);
    $paid = get_post_meta(get_the_ID(), 'paid', true);

    // Safe function calls with fallbacks
    $budget = function_exists('ProjectTheme_get_budget_name_string_fromID') ? 
        ProjectTheme_get_budget_name_string_fromID(get_post_meta($pid, 'budgets', true)) : '';
    
    $proposals = function_exists('projectTheme_number_of_bid') ? 
        sprintf(__('%s proposals', 'ProjectTheme'), projectTheme_number_of_bid($pid)) : '';

    $posted = get_the_time("jS F Y");
    $auth = get_userdata($post->post_author);
    $hide_project_p = get_post_meta($post->ID, 'private_bids', true);

    $days_left = ($closed == "0" ? ($ending - current_time('timestamp', 0)) : __("Expired/Closed", 'ProjectTheme'));

    // Initialize variables to prevent undefined variable errors
    $pay_this_me = 0;
    $pay_this_me2 = 0;
    $unpaid = 0;

    if ($days_left < 0) {
        $days_left = __('Expired/Closed', 'ProjectTheme');
    }
    ?>

    <div class="card section-vbox padd20" id="post-<?php the_ID(); ?>">
        <div class="padd10 nopadd-top">
            <h4><a href="<?php the_permalink() ?>"><?php the_title() ?></a></h4>

            <?php
            if ($post->post_status == "draft") {
                echo '<div class="alert alert-warning">' . __('This service is not approved yet.', 'ProjectTheme') . '</div>';
            }
            ?>

            <div class="d-lg-flex flex-row user-meta-data-row">
                <div class="pl-2 pr-2 flex-shrink-1"><i class="fa fa-calendar"></i> <?php echo esc_html($posted) ?></div>
                <div class="pl-2 pr-2 flex-shrink-1">
                    <p class="<?php echo (is_numeric($days_left) and $days_left > 0) ? "expiration_project_p" : "" ?>">
                        <?php echo esc_html($days_left) ?>
                    </p>
                </div>
            </div>

            <div class="excerpt-thing">
                <div class="my-deliv_2">
                    <?php if ($pay_this_me == 1 && function_exists('ProjectTheme_get_pay4project_page_url')): ?>
                        <a href="<?php echo esc_url(ProjectTheme_get_pay4project_page_url(get_the_ID())); ?>" class="post_bid_btn">
                            <?php echo __("Pay This", "ProjectTheme"); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($pay_this_me != 1): ?>
                        <a href="<?php the_permalink(); ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-book"></i> <?php echo __("Read More", "ProjectTheme"); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($post->post_author == $uid): ?>
                        <a href="<?php echo esc_url(home_url()) ?>/?p_action=edit_project&pid=<?php the_ID(); ?>" class="btn btn-light btn-sm">
                            <i class="far fa-edit"></i> <?php echo __("Edit Service", "ProjectTheme"); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($post->post_author == $uid): ?>
                        <?php $winner = get_post_meta(get_the_ID(), 'winner', true); ?>
                        <?php if (empty($winner)): ?>
                            <a href="<?php echo esc_url(home_url()) ?>/?p_action=delete_project&pid=<?php the_ID(); ?>" class="btn btn-light btn-sm">
                                <i class="far fa-trash-alt"></i> <?php echo __("Delete", "ProjectTheme"); ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-lg-flex flex-row user-meta-data-row">
                <div class="pt-2 pl-0 pr-1">
                    <?php if (function_exists('ProjectTheme_get_avatar')): ?>
                        <div class="avatar d-block" style="background-image: url(<?php echo esc_url(ProjectTheme_get_avatar($post->post_author, 25, 25)) ?>)">
                            <span class="avatar-status bg-green"></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-2 pt3-custom">
                    <?php if (function_exists('ProjectTheme_get_user_profile_link')): ?>
                        <div class="">
                            <a class="avatar-posted-by-username" href="<?php echo esc_url(ProjectTheme_get_user_profile_link($post->post_author)); ?>">
                                <?php echo esc_html($auth->user_login) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-2 pt3-custom">
                    <?php if (function_exists('ProjectTheme_project_get_star_rating')): ?>
                        <?php echo ProjectTheme_project_get_star_rating($post->post_author); ?>
                    <?php endif; ?>
                </div>
                
                <div class="p-2">
                    <?php if (function_exists('ProjectTheme_get_user_feedback_link')): ?>
                        <a class="btn btn-outline-primary btn-sm" href="<?php echo esc_url(ProjectTheme_get_user_feedback_link($post->post_author)); ?>">
                            <?php _e('View User Feedback', 'ProjectTheme'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
}

// Add deactivation hook to clean up
register_deactivation_hook(__FILE__, 'pt_service_offering_deactivate');

function pt_service_offering_deactivate() {
    flush_rewrite_rules();
}

?>
