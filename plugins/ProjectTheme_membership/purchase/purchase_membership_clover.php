<?php

if ($_GET['p_action'] == "purchase_membership_clover_buyer") {
    $memid = (int)$_GET['id'];
    $total = get_option('pt_project_owner_membership_cost_' . $memid);
    $name = get_option('pt_project_owner_membership_name_' . $memid);

    // Clover API credentials
    $clover_api_key = get_option('ProjectTheme_clover_api_key');
    $clover_redirect_url = get_site_url() . "/?p_action=clover_payment_success";

    var_dump($clover_api_key);

    if (empty($clover_api_key)) {
        echo 'Clover API key is not configured. Please set it in the admin panel.';
        exit;
    }

    // Redirect to Clover payment page
    $clover_payment_url = "https://www.clover.com/pay?amount=$total&api_key=$clover_api_key&redirect_url=$clover_redirect_url";

    wp_redirect($clover_payment_url);
    exit;
}

if ($_GET['p_action'] == "clover_payment_success") {
    // Verify payment with Clover API
    $payment_id = $_GET['payment_id']; // Clover will send this in the response
    $clover_api_key = get_option('ProjectTheme_clover_api_key');

    // Call Clover API to verify payment
    $response = wp_remote_get("https://www.clover.com/api/payments/$payment_id?api_key=$clover_api_key");

    if (is_wp_error($response)) {
        echo 'Payment verification failed. Please contact support.';
        exit;
    }

    $payment_data = json_decode(wp_remote_retrieve_body($response), true);

    if ($payment_data['status'] == 'PAID') {
        // Payment successful, update membership
        $memid = (int)$_GET['id'];
        $user_id = get_current_user_id();
        $membership_duration = get_option('pt_project_owner_membership_time_' . $memid);
        $membership_projects = get_option('pt_project_owner_membership_projects_' . $memid);

        $new_expiry = time() + ($membership_duration * 30 * 24 * 60 * 60); // Add membership duration in seconds
        update_user_meta($user_id, 'membership_available', $new_expiry);
        update_user_meta($user_id, 'projectTheme_monthly_nr_of_projects', $membership_projects);

        // Redirect to success page
        wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_ok=1");
        exit;
    } else {
        echo 'Payment failed. Please try again.';
        exit;
    }
}