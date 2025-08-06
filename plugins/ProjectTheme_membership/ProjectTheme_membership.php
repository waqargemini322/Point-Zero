<?php
/*
Plugin Name: ProjectTheme Memberships
Plugin URI: http://sitemile.com/
Description: Adds a membership/subscription feature to the Project Bidding Theme from sitemile
Author: SiteMile.com
Author URI: http://sitemile.com/
Version: 2.2.0
Text Domain: pt_mem
*/

include "membership-shortcodes.php";
include "admin_func.php";

//add_filter( 'ProjectTheme_general_settings_main_details_options', 'ProjectTheme_general_settings_main_details_options_memss' );
add_filter(
	"ProjectTheme_general_settings_main_details_options_save",
	"ProjectTheme_general_settings_main_details_options_save_memms"
);
add_filter("ProjectTheme_is_it_allowed_place_bids", "ProjectTheme_is_it_allowed_place_bids_memms");
add_filter(
	"ProjectTheme_is_it_not_allowed_place_bids_action",
	"ProjectTheme_is_it_not_allowed_place_bids_action_meeems"
);
add_filter("ProjectTheme_before_payments_in_payments", "ProjectTheme_before_payments_in_payments_meemss");
add_filter("template_redirect", "ProjectTheme_template_redirect_meemmms");
add_filter("ProjectTheme_post_bid_ok_action", "ProjectTheme_post_bid_ok_action_mem_fncs");
add_filter("ProjectTheme_display_bidding_panel", "ProjectTheme_display_bidding_panel_mms");
add_filter("ProjectTheme_when_creating_auto_draft", "ProjectTheme_when_creating_auto_draft_ff");

add_action("pt_at_account_dash_top", "pt_mem_show_expiry");
register_activation_hook(__FILE__, "PT_mem_my_plugin_activate");

//************************************************************************
//
//	function
//
//************************************************************************

function PT_mem_my_plugin_activate()
{
	global $wpdb;
	$ss =
		"CREATE TABLE `" .
		$wpdb->prefix .
		"project_membership_coupons` (
				`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`coupon_code` varchar(255) NOT NULL DEFAULT '0',
				`discount_amount` varchar(255) NOT NULL DEFAULT '0'
				) ENGINE = MYISAM ;
				";
	$wpdb->query($ss);
}

//************************************************************************
//
// function
//
//************************************************************************

function projecttheme_membership($uid)
{
	//mem_type
	$mem_type = get_user_meta($uid, "mem_type", true);

	if (empty($mem_type)) {
		return __("n/a", "ProjectTheme");
	}

	if ($mem_type == "free") {
		return __("Free", "ProjectTheme");
	}

	return $mem_type;
}

function pt_mem_show_expiry()
{
	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");
	$tm = current_time("timestamp");
	$uid = get_current_user_id();
	$show_it = 0;
	$role = ProjectTheme_mems_get_current_user_role($uid);

	// Check role conditions to set $show_it
	if ($role == "service_provider") {
		$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");
		if ($ProjectTheme_free_mode_freelancers == "paid") {
			$show_it = 1;
		}
	} elseif ($role == "investor") {
		$ProjectTheme_free_mode_investors = get_option("ProjectTheme_free_mode_investors");
		if ($ProjectTheme_free_mode_investors == "paid") {
			$show_it = 1;
		}
	} elseif ($role == "business_owner") {
		$ProjectTheme_free_mode_buyers = get_option("ProjectTheme_free_mode_buyers");
		if ($ProjectTheme_free_mode_buyers == "paid") {
			$show_it = 1;
		}
	} else {
		$show_it = 1;
		// For other roles, we set $show_it to 1
	}

	// Display membership information only if conditions are met
	if ($ProjectTheme_enable_membs == "yes" && !current_user_can("edit_others_pages") && $show_it == 1) {
		$membership_available = get_user_meta($uid, "membership_available", true);

		if ($membership_available < $tm) {
			echo '<div class="alert alert-danger">' .
				sprintf(
					__(
						'Your membership is expired, <a href="%s">click here</a> to renew your membership.',
						"ProjectTheme"
					),
					get_site_url() . "/?p_action=get_new_mem"
				) .
				"</div>";
		} else {
			$membership_type = projecttheme_membership($uid);
			echo '<div class="alert alert-success">';
			echo sprintf(
				__("Your membership is active and will expire on %s.", "ProjectTheme"),
				date_i18n("d-M-Y H:i:s", $membership_available)
			);
			echo "<br/>";

			if ($role == "service_provider" || $role == "investor") {
				$monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
				echo sprintf(__("You have %s invites left.", "ProjectTheme"), $monthly_nr_of_bids);
			} elseif ($role == "business_owner") {
				$monthly_nr_of_projects = get_user_meta($uid, "projectTheme_monthly_nr_of_projects", true);
				echo sprintf(__("You have %s projects left.", "ProjectTheme"), $monthly_nr_of_projects);
			} else {
				echo __("Membership Type:", "ProjectTheme") . " " . $membership_type;
			}

			echo "<br/>";
			echo sprintf(__("Membership Type: %s", "ProjectTheme"), $membership_type);
			echo "</div>";
		}
	} else {
		echo "Conditions for displaying membership info not met.<br>";
	}
}

function projecttheme_is_user_able_to_access($uid, $pid)
{
	if (!is_user_logged_in()) {
		return false;
	}
	$post = get_post($pid);

	if ($post->post_author != $uid) {
		$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");
		if ($ProjectTheme_free_mode_freelancers == "paid") {
			$membership_available = get_user_meta($uid, "membership_available", true);
			$tm = current_time("timestamp");
			if ($membership_available < $tm) {
				return false;
			}
		}
	}

	return true;
}

/********************************************************
 *
 *			function
 *
 ********************************************************/
add_filter("ProjectTheme_is_it_allowed_post_projects", "ProjectTheme_is_it_allowed_post_projects_fn");

function ProjectTheme_is_it_allowed_post_projects_fn($al)
{
	$current_user = wp_get_current_user();
	$uid = $current_user->ID;

	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");

	if ($ProjectTheme_enable_membs == "yes") {
		$ProjectTheme_free_mode_buyers = get_option("ProjectTheme_free_mode_buyers");
		$projectTheme_monthly_nr_of_projects = get_user_meta($uid, "projectTheme_monthly_nr_of_projects", true);
		$membership_available = get_user_meta($uid, "membership_available", true);

		$tm = current_time("timestamp");

		if ($ProjectTheme_free_mode_buyers != "free") {
			if ($membership_available < $tm or $projectTheme_monthly_nr_of_projects == 0) {
				return false;
			}
		}
	}

	return true;
}
/********************************************************
 *
 *			function
 *
 ********************************************************/
add_filter("ProjectTheme_post_project_not_allowed_message", "pt_post_projects_err");

function pt_post_projects_err()
{
	$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
	echo '<div class="padd10"><div class="padd10">';
	echo sprintf(
		__(
			'Your membership does not have anymore projects left. You need to renew your subscription. <a href="%s">Click here</a>.',
			"pt_mem"
		),
		$lnk
	);
	echo "</div></div>";
}

/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_when_creating_auto_draft_ff()
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

	$projectTheme_monthly_nr_of_projects = get_user_meta($uid, "projectTheme_monthly_nr_of_projects", true);
	if (empty($projectTheme_monthly_nr_of_projects)) {
		$new = 0;
	} else {
		$new = $projectTheme_monthly_nr_of_projects - 1;
	}
	update_user_meta($uid, "projectTheme_monthly_nr_of_projects", $new);
}
/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_display_bidding_panel_mms($pid)
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

	$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");

	$projectTheme_monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");

	if (
		$projectTheme_monthly_nr_of_bids <= 0 and
		$ProjectTheme_enable_membs == "yes" and
		$ProjectTheme_free_mode_freelancers == "paid"
	) {
		$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
		echo '<div class="alert alert-danger"> ';
		echo sprintf(
			__(
				'Your membership does not have anymore bids left. You need to renew your subscription. <a href="%s">Click here</a>.',
				"pt_mem"
			),
			$lnk
		);
		echo "</div> ";
	}
}

function ProjectTheme_can_post_bids_anymore($pid = "")
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

	$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");

	$projectTheme_monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");

	if (
		$projectTheme_monthly_nr_of_bids <= 0 and
		$ProjectTheme_enable_membs == "yes" and
		$ProjectTheme_free_mode_freelancers == "paid"
	) {
		return "no";
	}

	return "yes";
}
/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_post_bid_ok_action_mem_fncs()
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

	$projectTheme_monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
	update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $projectTheme_monthly_nr_of_bids - 1);
}
/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_mems_get_current_user_role($uid)
{
	$current_user = get_userdata($uid);
	$user_roles = $current_user->roles;
	$user_role = array_shift($user_roles);

	return $user_role;
}
/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_template_redirect_meemmms()
{
	//get_free_membership

	if ($_GET["p_action"] == "purchase_membership_skrill") {
		include "purchase/purchase_membership_skrill.php";
		exit();
	}

	if ($_GET["p_action"] == "purchase_membership_paypal") {
		include "purchase/purchase_membership_paypal.php";
		exit();
	}

	if ($_GET["p_action"] == "purchase_membership_service_provider") {
		include "purchase/purchase_membership_service_provider.php";
		exit();
	}

	if ($_GET["p_action"] == "purchase_membership_investor") {
		include "purchase/purchase_membership_investor.php";
		exit();
	}

	if ($_GET["p_action"] == "purchase_membership_buyer") {
		include "purchase/purchase_membership_buyer.php";
		exit();
	}

	if ($_GET["p_action"] == "skrill_membership_payment_response") {
		$custom = $_POST["field1"];
		//2|30|1584977101|freelancer
		$exp = explode("|", $custom);

		$memid = $exp[0];
		$uid = $exp[1];
		$tm = $exp[2];
		$memtp = $exp[3];

		if ("freelancer" == $memtp) {
			$tm = current_time("timestamp") + 30 * 24 * 3600 * get_option("pt_freelancer_membership_time_" . $memid);
			update_user_meta($uid, "membership_available", $tm);
			$name = get_option("pt_freelancer_membership_name_" . $memid);
			update_user_meta(get_current_user_id(), "mem_type", $name);

			update_user_meta(
				$uid,
				"projectTheme_monthly_nr_of_bids",
				get_option("pt_freelancer_membership_bids_" . $memid)
			);
		}

		if ("project_owner" == $memtp) {
			$tm = current_time("timestamp") + 30 * 24 * 3600 * get_option("pt_project_owner_membership_time_" . $memid);
			update_user_meta($uid, "membership_available", $tm);

			$name = get_option("pt_project_owner_membership_name_" . $memid);
			update_user_meta(get_current_user_id(), "mem_type", $name);
			update_user_meta(
				$uid,
				"projectTheme_monthly_nr_of_projects",
				get_option("pt_project_owner_membership_projects_" . $memid)
			);
		}
	}

	if ($_GET["p_action"] == "purchase_membership_paypal_response") {
		$custom = $_POST["custom"];
		//2|30|1584977101|freelancer
		$exp = explode("|", $custom);

		$memid = $exp[0];
		$uid = $exp[1];
		$tm = $exp[2];
		$memtp = $exp[3];

		if ("freelancer" == $memtp) {
			$tm = current_time("timestamp") + 30 * 24 * 3600 * get_option("pt_freelancer_membership_time_" . $memid);
			update_user_meta($uid, "membership_available", $tm);

			update_user_meta(
				$uid,
				"projectTheme_monthly_nr_of_bids",
				get_option("pt_freelancer_membership_bids_" . $memid)
			);

			$name = get_option("pt_freelancer_membership_name_" . $memid);
			update_user_meta($uid, "mem_type", $name);
		}

		if ("project_owner" == $memtp) {
			$tm = current_time("timestamp") + 30 * 24 * 3600 * get_option("pt_project_owner_membership_time_" . $memid);
			update_user_meta($uid, "membership_available", $tm);

			update_user_meta(
				$uid,
				"projectTheme_monthly_nr_of_projects",
				get_option("pt_project_owner_membership_projects_" . $memid)
			);

			$name = get_option("pt_project_owner_membership_name_" . $memid);
			update_user_meta($uid, "mem_type", $name);
		}

		//-------------

		wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")));
		exit();
	}

	if (isset($_GET["get_free_membership"])) {
		if (is_user_logged_in()) {
			//--------------
			$uid = get_current_user_id();

			if (ProjectTheme_mems_get_current_user_role($uid) == "service_provider") {
				$i = $_GET["get_free_membership"];
				if ($i == 1 or $i == 2 or $i == 3 or $i == 4) {
					$pt_freelancer_membership_cost_ = get_option("pt_freelancer_membership_cost_" . $i);
					if ($pt_freelancer_membership_cost_ == 0) {
						$tm =
							current_time("timestamp") +
							30 * 24 * 3600 * get_option("pt_freelancer_membership_time_" . $i);
						update_user_meta($uid, "membership_available", $tm);
						update_user_meta($uid, "mem_type", "free");
						update_user_meta(
							$uid,
							"projectTheme_monthly_nr_of_bids",
							get_option("pt_freelancer_membership_bids_" . $i)
						);
					}

					if (ProjectTheme_using_permalinks()) {
						$tz = "?";
					} else {
						$tz = "&";
					}

					update_user_meta($uid, "free_membership_exhausted", "yes");
					wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . $tz . "success=1");
					exit();
				}
			}

			if (ProjectTheme_mems_get_current_user_role($uid) == "business_owner") {
				$i = $_GET["get_free_membership"];
				if ($i == 1 or $i == 2 or $i == 3 or $i == 4) {
					$pt_freelancer_membership_cost_ = get_option("pt_project_owner_membership_cost_" . $i);
					if ($pt_freelancer_membership_cost_ == 0) {
						$tm =
							current_time("timestamp") +
							30 * 24 * 3600 * get_option("pt_project_owner_membership_time_" . $i);
						update_user_meta($uid, "membership_available", $tm);
						update_user_meta($uid, "mem_type", "free");
						update_user_meta(
							$uid,
							"projectTheme_monthly_nr_of_projects",
							get_option("pt_project_owner_membership_projects_" . $i)
						);
					}

					if (ProjectTheme_using_permalinks()) {
						$tz = "?";
					} else {
						$tz = "&";
					}

					update_user_meta($uid, "free_membership_exhausted", "yes");
					wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . $tz . "success=1");
					exit();
				}
			}

			//--------
		} else {
			wp_redirect(get_site_url() . "/wp-login.php");
			exit();
		}
	}

	if (isset($_GET["p_action"]) and $_GET["p_action"] == "get_new_mem") {
		include "get_new_mem.php";
		die();
	}

	if (isset($_GET["p_action"]) and $_GET["p_action"] == "get_new_trial_mem") {
		include "get_new_trial_mem.php";
		die();
	}

	if (isset($_GET["p_action"]) and $_GET["p_action"] == "paypal_membership_mem") {
		include "paypal_membership_mem.php";
		die();
	}

	if (isset($_GET["p_action"]) and $_GET["p_action"] == "mb_membership_mem") {
		include "mb_membership_mem.php";
		die();
	}

	if (isset($_GET["p_action"]) and $_GET["p_action"] == "mb_deposit_response_mem") {
		include "mb_deposit_response_mem.php";
		die();
	}

	if (isset($_GET["p_action"]) and $_GET["p_action"] == "credits_listing_mem") {
		include "credits_listing_mem.php";
		die();
	}

	if (isset($_GET["p_action"]) and $_GET["p_action"] == "activate_membership_trial") {
		$uid = get_current_user_id();
		$tm = current_time("timestamp", 0);

		update_user_meta($uid, "trial_used", "1");
		$trial = get_option("projectTheme_monthly_trial_period");
		update_user_meta($uid, "membership_available", $tm + $trial * 3600 * 24 * 30.5);

		//------------------------

		$projectTheme_monthly_nr_of_bids = get_option("projectTheme_monthly_nr_of_bids");
		if (empty($projectTheme_monthly_nr_of_bids)) {
			$projectTheme_monthly_nr_of_bids = 10;
		}

		//-----------

		$projectTheme_monthly_nr_of_projects = get_option("projectTheme_monthly_nr_of_projects");
		if (empty($projectTheme_monthly_nr_of_projects)) {
			$projectTheme_monthly_nr_of_projects = 10;
		}

		//------------

		update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $projectTheme_monthly_nr_of_bids);
		update_user_meta($uid, "projectTheme_monthly_nr_of_projects", $projectTheme_monthly_nr_of_projects);

		wp_redirect(get_permalink(get_option("ProjectTheme_my_account_payments_id")));
		die();
	}
}
/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_before_payments_in_payments_meemss()
{
	$uid = get_current_user_id();

	$membership_available = get_user_meta($uid, "membership_available", true);
	$tm = current_time("timestamp", 0);
	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");

	if ($ProjectTheme_enable_membs == "yes"):
		$ProjectTheme_free_mode_buyers = get_option("ProjectTheme_free_mode_buyers");
		$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");

		if (ProjectTheme_mems_get_current_user_role($uid) == "service_provider") {
			if ($ProjectTheme_free_mode_freelancers == "paid") {
				$show_this_thing = 1;
			}
		}

		if (ProjectTheme_mems_get_current_user_role($uid) == "business_owner") {
			if ($ProjectTheme_free_mode_buyers == "paid") {
				$show_this_thing = 1;
			}
		}

		if ($show_this_thing == 1): ?>

<h3 class="my-account-headline-1"><?php _e("Membership/Subscription", "ProjectTheme"); ?></h3>
<div class="card">

	<div class="padd20">

		<?php if ($membership_available > $tm) {
    	if (ProjectTheme_is_user_provider($uid)) {
    		$projectTheme_monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);

    		if ($projectTheme_monthly_nr_of_bids <= 0) {
    			$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
    			echo '<span class="balance">' .
    				sprintf(
    					__(
    						"Your freelancer membership has expired. Purchase from <a href='%s'>here</a>.",
    						"ProjectTheme"
    					),
    					$lnk
    				) .
    				"</span>";
    		} else {
    			echo sprintf(
    				__("Your membership will expire on: %s", "ProjectTheme"),
    				date_i18n("d-M-Y H:i:s", $membership_available)
    			);
    			echo "<br/>";
    			echo sprintf(__("Your have: %s bids left.", "ProjectTheme"), $projectTheme_monthly_nr_of_bids);
    		}
    	}

    	if (ProjectTheme_is_user_business($uid)) {
    		$projectTheme_monthly_nr_of_projects = get_user_meta($uid, "projectTheme_monthly_nr_of_projects", true);

    		if ($projectTheme_monthly_nr_of_projects <= 0) {
    			$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
    			echo '<br/><span class="balance">' .
    				sprintf(
    					__(
    						"Your project owner membership has expired. Purchase from <a href='%s'>here</a>.",
    						"ProjectTheme"
    					),
    					$lnk
    				) .
    				"</span>";
    		} else {
    			echo sprintf(
    				__("Your membership will expire on: %s", "ProjectTheme"),
    				date_i18n("d-M-Y H:i:s", $membership_available)
    			);
    			echo "<br/>";
    			echo sprintf(__("Your have: %s projects left.", "ProjectTheme"), $projectTheme_monthly_nr_of_projects);
    		}
    	}
    } else {
    	$trial_used = get_user_meta($uid, "trial_used", true);
    	$projectTheme_monthly_trial_period = get_option("projectTheme_monthly_trial_period");

    	if ($trial_used != 1 and $projectTheme_monthly_trial_period > 0) {
    		$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_trial_mem";
    		echo '<span class="balance">' .
    			sprintf(
    				__(
    					"You do not have a membership, but you can <b><a href='%s'>activate</a></b> a trial membership.",
    					"ProjectTheme"
    				),
    				$lnk
    			) .
    			"</span>";
    	} else {
    		$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
    		echo '<span class="balance">' .
    			sprintf(__("Your membership has expired. Purchase from <a href='%s'>here</a>.", "ProjectTheme"), $lnk) .
    			"</span>";
    	}
    } ?>

	</div>
</div>

<div class="clear10"></div>
<?php endif;
	endif;?>

<?php
}
/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_is_it_not_allowed_place_bids_action_meeems()
{
	$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
	echo '<div class="padd10">';
	echo sprintf(
		__(
			'In order to post or bid, you need a valid membership. You need to renew your subscription. <a href="%s">Click here</a>.',
			"pt_mem"
		),
		$lnk
	);
	echo "</div>";
}

/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_is_it_not_allowed_place_bids_action_meeems2()
{
	$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
	echo '<div class="padd10"><div class="padd10">';
	echo sprintf(
		__(
			'Your membership does not have anymore bids left. You need to renew your subscription. <a href="%s">Click here</a>.',
			"pt_mem"
		),
		$lnk
	);
	echo "</div></div>";
}
/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_is_it_allowed_place_bids_memms($as)
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");

	if ($ProjectTheme_enable_membs == "yes") {
		$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");
		echo $ProjectTheme_free_mode_freelancers;
		exit();

		if ($ProjectTheme_free_mode_freelancers == "free") {
			return true;
		}

		$trial = get_option("projectTheme_monthly_trial_period");
		if (empty($trial)) {
			$membership_available = get_user_meta($uid, "membership_available", true);
			$tm = current_time("timestamp", 0);

			if ($tm > $membership_available) {
				add_filter(
					"ProjectTheme_is_it_not_allowed_place_bids_action",
					"ProjectTheme_is_it_not_allowed_place_bids_action_meeems"
				);
				return false;
			}
		} else {
			$trial_used = get_user_meta($uid, "trial_used", true);
			if (empty($trial_used) and 0) {
				$tm = current_time("timestamp", 0);
				update_user_meta($uid, "trial_used", "1");
				update_user_meta($uid, "membership_available", $tm + $trial * 3600 * 24);

				//------------------------

				$projectTheme_monthly_nr_of_bids = get_option("projectTheme_monthly_nr_of_bids");
				if (empty($projectTheme_monthly_nr_of_bids)) {
					$projectTheme_monthly_nr_of_bids = 10;
				}

				update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $projectTheme_monthly_nr_of_bids);

				return true;
			} else {
				$membership_available = get_user_meta($uid, "membership_available", true);
				$tm = current_time("timestamp", 0);

				if ($tm > $membership_available) {
					add_filter(
						"ProjectTheme_is_it_not_allowed_place_bids_action",
						"ProjectTheme_is_it_not_allowed_place_bids_action_meeems"
					);
					return false;
				} else {
					if (ProjectTheme_is_user_business($uid)) {
						$projectTheme_monthly_nr_of_projects = get_user_meta(
							$uid,
							"projectTheme_monthly_nr_of_projects",
							true
						);
						if ($projectTheme_monthly_nr_of_projects <= -1) {
							add_filter(
								"ProjectTheme_is_it_not_allowed_place_bids_action",
								"ProjectTheme_is_it_not_allowed_place_bids_action_meeems"
							);
							return false;
						}
					}

					if (ProjectTheme_is_user_provider($uid)) {
						$projectTheme_monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
						if ($projectTheme_monthly_nr_of_bids <= 0) {
							add_filter(
								"ProjectTheme_is_it_not_allowed_place_bids_action",
								"ProjectTheme_is_it_not_allowed_place_bids_action_meeems2"
							);
							return false;
						}
					}
				}
			}
		}
	}

	return true;
}
/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_general_settings_main_details_options_save_memms()
{
	update_option("ProjectTheme_enable_membs", $_POST["ProjectTheme_enable_membs"]);
	update_option("projectTheme_monthly_service_provider", $_POST["projectTheme_monthly_service_provider"]);
	update_option("projectTheme_monthly_service_contractor", $_POST["projectTheme_monthly_service_contractor"]);
	update_option("projectTheme_monthly_trial_period", $_POST["projectTheme_monthly_trial_period"]);

	update_option("projectTheme_monthly_nr_of_bids", $_POST["projectTheme_monthly_nr_of_bids"]);
	update_option("projectTheme_monthly_nr_of_projects", $_POST["projectTheme_monthly_nr_of_projects"]);
}

/********************************************************
 *
 *			function
 *
 ********************************************************/

add_filter("ProjectTheme_admin_menu_add_item", "ProjectTheme_admin_menu_add_item_memb");

function ProjectTheme_admin_menu_add_item_memb()
{
	$capability = 10;
	global $projecthememnupg;
	$advs = "add" . "_" . "menu" . "_" . "page";

	$projecthememnupg(
		"project_theme_mnu",
		__("Memberships", "ProjectTheme"),
		'<i class="fas fa-university"></i> ' . __("Memberships", "ProjectTheme"),
		$capability,
		"Memberships",
		"projectTheme_theme_memberships"
	);
	$projecthememnupg(
		"project_theme_mnu",
		__("Coupons", "ProjectTheme"),
		'<i class="fas fa-university"></i> ' . __("Coupons", "ProjectTheme"),
		$capability,
		"Coupons",
		"projectTheme_theme_membership_coupons"
	);
}

// COUPONS HERE

function projectTheme_theme_membership_coupons()
{
	$id_icon = "icon-options-general-layout";
	$ttl_of_stuff = "ProjectTheme - " . __("Coupons", "ProjectTheme");
	global $menu_admin_project_theme_bull;

	$arr = ["yes" => __("Yes", "ProjectTheme"), "no" => __("No", "ProjectTheme")];
	$arr3 = ["free" => __("FREE", "ProjectTheme"), "paid" => __("PAID", "ProjectTheme")];

	echo '<div class="wrap">';
	echo '<div class="icon32" id="' . $id_icon . '"><br/></div>';
	echo '<h2 class="my_title_class_sitemile">' . $ttl_of_stuff . "</h2>";

	global $wpdb;

	if (isset($_GET["deletecp"])) {
		$id = sanitize_text_field($_GET["deletecp"]);

		$s = "delete from " . $wpdb->prefix . "project_membership_coupons where id='$id'";
		$r = $wpdb->query($s);

		echo '<div class="saved_thing">Your coupon was deleted.</div>';
	}

	if (isset($_POST["add_coupon_mem"])) {
		//project_membership_coupons
		$s = "select * from " . $wpdb->prefix . "project_membership_coupons where coupon_code='$coupon_code'";
		$r = $wpdb->get_results($s);

		$coupon_code = sanitize_text_field($_POST["coupon_code"]);
		$discount_amount = sanitize_text_field($_POST["discount_amount"]);

		if (count($r) == 0) {
			$s =
				"insert into " .
				$wpdb->prefix .
				"project_membership_coupons (coupon_code, discount_amount) values('$coupon_code','$discount_amount')";
			$wpdb->query($s);
		}

		echo '<div class="saved_thing">Your coupon was added.</div>';
	}
	?>

<div id="usual2" class="usual">
	<ul>
		<li>
			<a href="#tabs1"><?php _e("Add new coupon", "ProjectTheme"); ?></a>
		</li>
		<li>
			<a href="#tabs2"><?php _e("Current Active Coupons", "ProjectTheme"); ?></a>
		</li>

	</ul>

	<div id="tabs1">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Coupons&active_tab=tabs1">
			<table width="100%" class="sitemile-table">

				<tr>
					<td width="160">Coupon Code:
					</td>
					<td><input required="required" type="text" size="26" name="coupon_code" /></td>
				</tr>

				<tr>
					<td>Discount Amount %:
					</td>
					<td><input required="required" type="number" size="26" name="discount_amount" /></td>
				</tr>

				<tr>
					<td></td>
					<td><input type="submit" size="26" name="add_coupon_mem" value="Add Coupon" /></td>
				</tr>

			</table>
		</form>
	</div>

	<div id="tabs2">
		<?php
    $s = "select * from " . $wpdb->prefix . "project_membership_coupons  ";
    $r = $wpdb->get_results($s);

    if (count($r) > 0) { ?>

		<style>
			.mytable td {
				border-bottom: 1px solid #ddd;
				padding: 10px;
				background-color: #fefefe;
			}
		</style>

		<table class="mytable" style="width: 50%">
			<thead>
				<tr>
					<td>
						<b>Coupon Code</b>
					</td>
					<td>
						Discount Amount
					</td>
					<td></td>

				</tr>
			</thead>

			<?php foreach ($r as $row) { ?>

			<tr>
				<td>
					<b><?php echo $row->coupon_code; ?></b>
				</td>
				<td>
					<?php echo $row->discount_amount; ?>%
				</td>
				<td>
					<a
						href="<?php echo admin_url(); ?>/admin.php?page=Coupons&active_tab=tabs1&deletecp=<?php echo $row->id; ?>">Delete
						Coupon</a>
				</td>
			</tr>
			<?php } ?>

		</table>

		<?php } else {echo "<p>No coupons added</p>";}
    ?>

	</div>
</div>
<?php echo "</div>";
}

/********************************************************
 *
 *			My function
 *
 ********************************************************/

add_filter("ProjectTheme_on_success_registration", "ProjectTheme_on_success_registration_redirect");

function ProjectTheme_on_success_registration_redirect($user_login)
{
	$opt = get_option("projectTheme_admin_approves_each_user");
	//if ( $opt == "yes" ) return;

	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");
	$ProjectTheme_redirect_mems = get_option("ProjectTheme_redirect_mems");
	$ProjectTheme_free_mode_buyers = get_option("ProjectTheme_free_mode_buyers");
	$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");

	$usr = get_user_by("login", $user_login);
	$uid = $usr->ID;

	$creds = [
		"user_login" => $user_login,
		"user_password" => $_POST["password"],
		"remember" => true,
	];

	wp_signon($creds, false);

	if ($ProjectTheme_redirect_mems == "yes" and $ProjectTheme_enable_membs == "yes") {
		if (pt_get_user_role_membership($uid) == "service_provider" and $ProjectTheme_free_mode_freelancers == "paid") {
			wp_redirect(get_permalink(get_option("ProjectTheme_page_to_redirect_mems_freelancer")));
			exit();
		}

		if (pt_get_user_role_membership($uid) == "business_owner" and $ProjectTheme_free_mode_buyers == "paid") {
			wp_redirect(get_permalink(get_option("ProjectTheme_page_to_redirect_mems_buyer")));
			exit();
		}
	}
}

/********************************************************
 *
 *			function
 *
 ********************************************************/

function pt_get_user_role_membership($uid)
{
	$user_data = get_userdata($uid);
	$user_roles = $user_data->roles;

	if (is_array($user_roles)) {
		$user_role = array_shift($user_roles);
	}

	return $user_role;
}

/********************************************************
 *
 *			function
 *
 ********************************************************/

function projectTheme_theme_memberships()
{
	$id_icon = "icon-options-general-layout";
	$ttl_of_stuff = "ProjectTheme - " . __("Memberships", "ProjectTheme");
	global $menu_admin_project_theme_bull;

	$arr = ["yes" => __("Yes", "ProjectTheme"), "no" => __("No", "ProjectTheme")];
	$arr3 = ["free" => __("FREE", "ProjectTheme"), "paid" => __("PAID", "ProjectTheme")];

	echo '<div class="wrap">';
	echo '<div class="icon32" id="' . $id_icon . '"><br/></div>';
	echo '<h2 class="my_title_class_sitemile">' . $ttl_of_stuff . "</h2>";

	if (isset($_POST["my_submit1"])) {
		update_option("ProjectTheme_enable_membs", $_POST["ProjectTheme_enable_membs"]);
		update_option("ProjectTheme_redirect_mems", $_POST["ProjectTheme_redirect_mems"]);
		update_option(
			"ProjectTheme_page_to_redirect_mems_freelancer",
			$_POST["ProjectTheme_page_to_redirect_mems_freelancer"]
		);
		update_option("ProjectTheme_page_to_redirect_mems_buyer", $_POST["ProjectTheme_page_to_redirect_mems_buyer"]);

		update_option("ProjectTheme_free_mode_buyers", $_POST["ProjectTheme_free_mode_buyers"]);
		update_option("ProjectTheme_free_mode_freelancers", $_POST["ProjectTheme_free_mode_freelancers"]);
		update_option("ProjectTheme_free_mode_investors", $_POST["ProjectTheme_free_mode_investors"]);

		echo '<div class="saved_thing">Settings were saved!</div>';
	}

	if (isset($_POST["my_submit2"])) {
		for ($i = 1; $i <= 6; $i++) {
			update_option("pt_freelancer_membership_name_" . $i, $_POST["pt_freelancer_membership_name_" . $i]);
			update_option("pt_freelancer_membership_cost_" . $i, $_POST["pt_freelancer_membership_cost_" . $i]);
			update_option("pt_freelancer_membership_time_" . $i, $_POST["pt_freelancer_membership_time_" . $i]);
			update_option("pt_freelancer_membership_bids_" . $i, $_POST["pt_freelancer_membership_bids_" . $i]);
		}

		echo '<div class="saved_thing">Settings were saved!</div>';
	}

	if (isset($_POST["my_submit4"])) {
		for ($i = 1; $i <= 6; $i++) {
			update_option("pt_investor_membership_name_" . $i, $_POST["pt_investor_membership_name_" . $i]);
			update_option("pt_investor_membership_cost_" . $i, $_POST["pt_investor_membership_cost_" . $i]);
			update_option("pt_investor_membership_time_" . $i, $_POST["pt_investor_membership_time_" . $i]);
			update_option("pt_investor_membership_bids_" . $i, $_POST["pt_investor_membership_bids_" . $i]);
		}

		echo '<div class="saved_thing">Settings were saved!</div>';
	}

	if (isset($_POST["my_submit3"])) {
		for ($i = 1; $i <= 6; $i++) {
			update_option("pt_project_owner_membership_name_" . $i, $_POST["pt_project_owner_membership_name_" . $i]);
			update_option("pt_project_owner_membership_cost_" . $i, $_POST["pt_project_owner_membership_cost_" . $i]);
			update_option("pt_project_owner_membership_time_" . $i, $_POST["pt_project_owner_membership_time_" . $i]);
			update_option(
				"pt_project_owner_membership_projects_" . $i,
				$_POST["pt_project_owner_membership_projects_" . $i]
			);
		}

		echo '<div class="saved_thing">Settings were saved!</div>';
	}
	?>

<div id="usual2" class="usual">
	<ul>
		<li>
			<a href="#tabs1"><?php _e("Options", "ProjectTheme"); ?></a>
		</li>
		<li>
			<a href="#tabs2"><?php _e("Professional Service Provide", "ProjectTheme"); ?></a>
		</li>
		<li>
			<a href="#tabs3"><?php _e("Entrepreneur", "ProjectTheme"); ?></a>
		</li>
		<li>
			<a href="#tabs4" <?php echo $_GET["activate_tab"] == "tabs4" ? "class='selected'" : ""; ?>><?php _e(
	"Investor",
	"ProjectTheme"
); ?></a>
		</li>

	</ul>

	<div id="tabs1">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Memberships&active_tab=tabs1">
			<table width="100%" class="sitemile-table">
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Enable Memberships:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr, "ProjectTheme_enable_membs"); ?></td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Customers use the site:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr3, "ProjectTheme_free_mode_buyers"); ?>
						- makes so the buyer/customers can use the site for free</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Freelancers use the site:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr3, "ProjectTheme_free_mode_freelancers"); ?>
						- makes so the freelancers can use the site for free</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Freelancers use the site:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr3, "ProjectTheme_free_mode_investors"); ?>
						- makes so the investors can use the site for free</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Redirect on register:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr, "ProjectTheme_redirect_mems"); ?>
						- redirect the user to membership page after register.</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Page to redirect( freelancer ):</td>
					<td>
						<select name="ProjectTheme_page_to_redirect_mems_freelancer">
							<option value="">Select</option>
							<?php
    $args = [
    	"post_type" => "page",

    	"posts_per_page" => "-1",
    	"orderby" => "name",
    	"order" => "asc",
    	"post_status" => "publish",
    ];
    $pages = get_posts($args);

    $red = get_option("ProjectTheme_page_to_redirect_mems_freelancer");

    foreach ($pages as $page) {
    	echo "<option " .
    		($page->ID == $red ? "selected='selected'" : "") .
    		' value="' .
    		$page->ID .
    		'">' .
    		$page->post_title .
    		"</option>";
    }
    ?>
						</select>
					</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Page to redirect( buyer ):</td>
					<td>
						<select name="ProjectTheme_page_to_redirect_mems_buyer">
							<option value="">Select</option>
							<?php
    $args = [
    	"post_type" => "page",

    	"posts_per_page" => "-1",
    	"orderby" => "name",
    	"order" => "asc",
    	"post_status" => "publish",
    ];
    $pages = get_posts($args);

    $red = get_option("ProjectTheme_page_to_redirect_mems_buyer");

    foreach ($pages as $page) {
    	echo "<option " .
    		($page->ID == $red ? "selected='selected'" : "") .
    		' value="' .
    		$page->ID .
    		'">' .
    		$page->post_title .
    		"</option>";
    }
    ?>
						</select>
					</td>
				</tr>
				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" class="button button-primary button-large" name="my_submit1"
							value="Save these Settings!"></td>
				</tr>

			</table>
		</form>

	</div>

	<div id="tabs3">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Memberships&active_tab=tabs3">
			<table width="100%" class="sitemile-table">

				<tr>
					<td colspan="3">Shortcode to use on a page to display the packages:
						<b>[pt_display_buyer_mem_packs]</b>
					</td>
				</tr>

				<tr>
					<td colspan="3">To set the membership FREE, set the price 0</td>
				</tr>

				<?php for ($i = 1; $i <= 6; $i++) { ?>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width='260'>
						Project Owner Membership #<?php echo $i; ?>
						Name:</td>
					<td><input type="text" name='pt_project_owner_membership_name_<?php echo $i; ?>' size="24"
							value="<?php echo get_option("pt_project_owner_membership_name_" . $i); ?>" />
					</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Project Owner Membership #<?php echo $i; ?>
						Cost:</td>
					<td><input type="text" name='pt_project_owner_membership_cost_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_project_owner_membership_cost_" . $i); ?>" />
						<?php echo projecttheme_get_currency(); ?></td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Project Owner Membership #<?php echo $i; ?>
						Time:</td>
					<td><input type="text" name='pt_project_owner_membership_time_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_project_owner_membership_time_" . $i); ?>" />
						months</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Project Owner Membership #<?php echo $i; ?>
						Projects:</td>
					<td><input type="text" name='pt_project_owner_membership_projects_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_project_owner_membership_projects_" . $i); ?>" />
						projects
					</td>
				</tr>

				<tr>
					<td colspan="3">&nbsp;
					</td>
				</tr>

				<?php } ?>

				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" class="button button-primary button-large" name="my_submit3"
							value="Save these Settings!"></td>
				</tr>

			</table>
		</form>
	</div>

	<div id="tabs2">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Memberships&active_tab=tabs2">
			<table width="100%" class="sitemile-table">

				<tr>
					<td colspan="3">Shortcode to use on a page to display the packages:
						<b>[pt_display_freelancer_mem_packs]</b>
					</td>
				</tr>

				<tr>
					<td colspan="3">To set the membership FREE, set the price 0</td>
				</tr>

				<?php for ($i = 1; $i <= 6; $i++) { ?>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width='240'>
						Freelancer Membership #<?php echo $i; ?>
						Name:</td>
					<td><input type="text" name='pt_freelancer_membership_name_<?php echo $i; ?>' size="24"
							value="<?php echo get_option("pt_freelancer_membership_name_" . $i); ?>" />
					</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Freelancer Membership #<?php echo $i; ?>
						Cost:</td>
					<td><input type="text" name='pt_freelancer_membership_cost_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_freelancer_membership_cost_" . $i); ?>" />
						<?php echo projecttheme_get_currency(); ?></td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Freelancer Membership #<?php echo $i; ?>
						Time:</td>
					<td><input type="text" name='pt_freelancer_membership_time_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_freelancer_membership_time_" . $i); ?>" />
						months</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Freelancer Membership #<?php echo $i; ?>
						Bids:</td>
					<td><input type="text" name='pt_freelancer_membership_bids_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_freelancer_membership_bids_" . $i); ?>" />
						bids</td>
				</tr>

				<tr>
					<td colspan="3">&nbsp;
					</td>
				</tr>

				<?php } ?>

				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" class="button button-primary button-large" name="my_submit2"
							value="Save these Settings!"></td>
				</tr>

			</table>
		</form>
	</div>

	<div id="tabs4">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Memberships&active_tab=tabs4">
			<table width="100%" class="sitemile-table">

				<tr>
					<td colspan="3">Shortcode to use on a page to display the packages:
						<b>[pt_display_investor_mem_packs]</b>
					</td>
				</tr>
				<tr>
					<td colspan="3">To set the membership FREE, set the price 0</td>
				</tr>

				<?php for ($i = 1; $i <= 6; $i++) { ?>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width='240'>
						Investor Membership #<?php echo $i; ?>
						Name:</td>
					<td><input type="text" name='pt_investor_membership_name_<?php echo $i; ?>' size="24"
							value="<?php echo get_option("pt_investor_membership_name_" . $i); ?>" />
					</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Investor Membership #<?php echo $i; ?>
						Cost:</td>
					<td><input type="text" name='pt_investor_membership_cost_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_investor_membership_cost_" . $i); ?>" />
						<?php echo projecttheme_get_currency(); ?></td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Investor Membership #<?php echo $i; ?>
						Time:</td>
					<td><input type="text" name='pt_investor_membership_time_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_investor_membership_time_" . $i); ?>" />
						months</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Investor Membership #<?php echo $i; ?>
						Bids:</td>
					<td><input type="text" name='pt_investor_membership_bids_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_investor_membership_bids_" . $i); ?>" />
						bids</td>
				</tr>

				<tr>
					<td colspan="3">&nbsp;
					</td>
				</tr>

				<?php } ?>

				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" class="button button-primary button-large" name="my_submit4"
							value="Save these Settings!"></td>
				</tr>

			</table>
		</form>
	</div>

</div>

<?php
}
?>