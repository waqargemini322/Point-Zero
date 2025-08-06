<?php

include "paypal.class.php";

global $wp_query, $wpdb, $current_user;
$pid = $wp_query->query_vars["pid"];
get_currentuserinfo();
$uid = $current_user->ID;
$post = get_post($pid);

$action = $_GET["action"];
$business = trim(get_option("ProjectTheme_paypal_email"));
if (empty($business)) {
	die("Error. Admin, please add your paypal email in backend!");
}

$p = new paypal_class(); // initiate an instance of the class
$p->paypal_url = "https://www.paypal.com/cgi-bin/webscr"; // testing paypal url

//--------------

$ProjectTheme_paypal_enable_sdbx = get_option("ProjectTheme_paypal_enable_sdbx");
if ($ProjectTheme_paypal_enable_sdbx == "yes") {
	$p->paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
} // paypal url

//--------------

$this_script = get_bloginfo("siteurl") . "/?p_action=paypal_membership_mem";

if (empty($action)) {
	$action = "process";
}

switch ($action) {
	case "process": // Process and order...
		$title_post = __("Membership Subscription", "ProjectTheme");

		$role = ProjectTheme_mems_get_current_user_role($uid);
		if ($role == "service_provider") {
			$cost = get_option("projectTheme_monthly_service_provider");
		} else {
			$cost = get_option("projectTheme_monthly_service_contractor");
		}

		//---------------------------------------------

		//$p->add_field('business', 'sitemile@sitemile.com');
		$p->add_field("business", $business);

		$p->add_field("currency_code", get_option("ProjectTheme_currency"));
		$p->add_field("return", $this_script . "&action=success");
		$p->add_field("cancel_return", $this_script . "&action=cancel");
		$p->add_field("notify_url", $this_script . "&action=ipn");
		$p->add_field("item_name", $title_post);
		$p->add_field("custom", $uid . "|" . current_time("timestamp", 0));
		$p->add_field("amount", ProjectTheme_formats_special($cost, 2));

		$p->submit_paypal_post(); // submit the fields to paypal

		break;

	case "success": // Order was successful...
	case "ipn":
		if (isset($_POST["custom"])) {
			$cust = $_POST["custom"];
			$cust = explode("|", $cust);

			$uid = $cust[0];
			$tm = $cust[1];

			//--------------------------------------------

			$paid_mem_date = get_user_meta($uid, "paid_mem_date" . $tm . $uid, true);

			if (empty($paid_mem_date)) {
				//$tm = current_time('timestamp',0);

				update_user_meta($uid, "membership_available", $tm + 24 * 30 * 3600);
				update_user_meta($uid, "paid_mem_date" . $tm . $uid, "1");

				$projectTheme_monthly_nr_of_bids = get_option("projectTheme_monthly_nr_of_bids");
				if (empty($projectTheme_monthly_nr_of_bids)) {
					$projectTheme_monthly_nr_of_bids = 10;
				}

				update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $projectTheme_monthly_nr_of_bids);

				//--------------------------------------------

				$projectTheme_monthly_nr_of_projects = get_option("projectTheme_monthly_nr_of_projects");
				if (empty($projectTheme_monthly_nr_of_projects)) {
					$projectTheme_monthly_nr_of_projects = 10;
				}

				update_user_meta($uid, "projectTheme_monthly_nr_of_projects", $projectTheme_monthly_nr_of_projects);
			}
		}

		wp_redirect(get_permalink(get_option("ProjectTheme_my_account_payments_id")));
		break;

	case "cancel": // Order was canceled...
		wp_redirect(get_bloginfo("siteurl"));

		break;
}

?>