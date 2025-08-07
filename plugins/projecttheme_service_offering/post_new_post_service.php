<?php
/**
 * Service Post Processing (Fixed)
 * Fixed version to prevent fatal errors and improve security
 */

defined('ABSPATH') || exit;

global $projectOK, $MYerror, $class_errors;
$projectOK = 0;

global $wp_query;
$pid = isset($wp_query->query_vars['projectid']) ? intval($wp_query->query_vars['projectid']) : 0;
$MYerror = array();

// Initialize class_errors if not set
if (!isset($class_errors)) {
    $class_errors = array();
}

// Fire action hook
do_action('ProjectTheme_post_new_post_post', $pid);

// Process form submission
if (isset($_POST['project_submit1'])) {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['service_nonce'], 'pt_service_post_' . $pid)) {
        wp_die(__('Security check failed. Please try again.', 'ProjectTheme'));
    }

    // Sanitize and validate input
    $project_title = isset($_POST['project_title']) ? sanitize_text_field(trim($_POST['project_title'])) : '';
    $project_description = isset($_POST['project_description']) ? wp_kses_post(nl2br($_POST['project_description'])) : '';
    $project_category = isset($_POST['project_cat_cat']) ? intval($_POST['project_cat_cat']) : 0;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $project_location_addr = isset($_POST['project_location_addr']) ? sanitize_text_field(trim($_POST['project_location_addr'])) : '';

    update_post_meta($pid, 'finalised_posted', '0');

    // Validation
    $projectOK = 1;

    if (empty($project_title)) {
        $projectOK = 0;
        $MYerror['project_title'] = __('You cannot leave the project title blank!', 'ProjectTheme');
        $class_errors['project_title'] = 'error_class_post_new';
    }

    if (empty($project_description)) {
        $projectOK = 0;
        $MYerror['project_description'] = __('You cannot leave the project description blank!', 'ProjectTheme');
        $class_errors['project_description'] = 'error_class_post_new';
    }

    if ($price <= 0) {
        $projectOK = 0;
        $MYerror['price'] = __('Please enter a valid price for your service.', 'ProjectTheme');
        $class_errors['price'] = 'error_class_post_new';
    }

    // If validation passes, update the post
    if ($projectOK == 1) {
        $my_post = array();
        $my_post['post_title'] = $project_title;
        $my_post['post_status'] = 'publish';
        $my_post['ID'] = $pid;
        $my_post['post_content'] = $project_description;
        $my_post['post_type'] = 'service';

        $post_id = wp_update_post($my_post);

        if (!is_wp_error($post_id)) {
            // Update post meta
            update_post_meta($pid, 'price', $price);
            update_post_meta($pid, 'closed', '0');
            update_post_meta($pid, 'paid', '1');
            
            if (!empty($project_location_addr)) {
                update_post_meta($pid, 'Location', $project_location_addr);
            }

            // Set category if provided and taxonomy exists
            if ($project_category > 0 && taxonomy_exists('service_cat')) {
                wp_set_post_terms($pid, array($project_category), 'service_cat');
            }

            // Fire action hook for successful post
            do_action('ProjectTheme_service_posted_successfully', $pid);

            // Redirect to step 2
            $redirect_url = ProjectTheme_post_new_with_pid_stuff_thg_service($pid, '2');
            wp_redirect($redirect_url);
            exit;
        } else {
            $projectOK = 0;
            $MYerror['general'] = __('Failed to update the service. Please try again.', 'ProjectTheme');
        }
    }
}

?>
