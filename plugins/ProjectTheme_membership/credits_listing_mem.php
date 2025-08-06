<?php
/***************************************************************************
 *
 *	ProjectTheme - copyright ( c ) - sitemile.com
 *	The only project theme for wordpress on the world wide web.
 *
 *	Coder: Andrei Dragos Saioc
 *	Email: sitemile[at]sitemile.com | andreisaioc[at]gmail.com
 *	More info about the theme here: http://sitemile.com/products/wordpress-project-freelancer-theme/
 *	since v1.2.5.3
 *
 ***************************************************************************/

global $current_user, $wp_query;
$pid = $wp_query->query_vars["pid"];
$uid = $current_user->ID;

//-----------------------------------

function ProjectTheme_filter_ttl($title)
{
	return __("Pay by Virtual Currency", "ProjectTheme") . " - ";
}
add_filter("wp_title", "ProjectTheme_filter_ttl", 10, 3);

if (!is_user_logged_in()) {
	wp_redirect(get_bloginfo("siteurl") . "/wp-login.php");
	exit();
}

$role = ProjectTheme_mems_get_current_user_role($uid);
if ($role == "service_provider") {
	$cost = get_option("projectTheme_monthly_service_provider");
} else {
	$cost = get_option("projectTheme_monthly_service_contractor");
}

$total = $cost;

//----------------

get_header();
?>
    <div class="page_heading_me">
        <div class="page_heading_me_inner">
            <div class="mm_inn">
                <?php _e("Pay Membership by Virtual Currency", "ProjectTheme"); ?>
            </div>
        </div>
    </div>
    <div id="main_wrapper">
        <div id="main" class="wrapper">
            <div class="padd10">
                <div id="content">
                    <div class="my_box3">
                        <div class="box_content">
                            <div class="post no_border_btm" id="post-<?php the_ID(); ?>">
                                <?php if (isset($_GET["pay"])):

                                	echo '<div class="details_holder sk_sk_class">';

                                	$post_pr = get_post($pid);
                                	$cr = projectTheme_get_credits($uid);
                                	$amount = $total;

                                	if ($cr < $amount) {
                                		echo '<div class="error2">';
                                		echo __(
                                			"You do not have enough credits to pay for membership.",
                                			"ProjectTheme"
                                		);
                                		echo '</div><div class="clear10 flt_lft"></div>';

                                		$dep_dep = true;
                                		$dep_dep = apply_filters("ProjectTheme_credits_listing_add_more", $dep_dep);
                                		if ($dep_dep == true): ?>
                                    <div class="tripp">
                                        <a class="post_bid_btn" href="<?php echo ProjectTheme_get_payments_page_url(
                                        	" deposit "
                                        ); ?>">
                                            <?php echo __("Add More Credits", "ProjectTheme"); ?>
                                        </a>
                                    </div>
                                    <?php endif;
                                	} else {
                                		$paid_mem_date = get_user_meta($uid, "paid_mem_date" . $tm . $uid, true);

                                		//if ( empty( $paid_mem_date ) )
                                		// {
                                		$tm = current_time("timestamp", 0);

                                		update_user_meta($uid, "membership_available", $tm + 24 * 30 * 3600);
                                		update_user_meta($uid, "paid_mem_date" . $tm . $uid, "1");

                                		projectTheme_update_credits($uid, $cr - $total);
                                		$reason = sprintf(__("Payment for membership", "ProjectTheme"));

                                		projectTheme_add_history_log("0", $reason, $amount, $uid);

                                		//-----------------------

                                		$projectTheme_monthly_nr_of_bids = get_option(
                                			"projectTheme_monthly_nr_of_bids"
                                		);
                                		if (empty($projectTheme_monthly_nr_of_bids)) {
                                			$projectTheme_monthly_nr_of_bids = 10;
                                		}

                                		update_user_meta(
                                			$uid,
                                			"projectTheme_monthly_nr_of_bids",
                                			$projectTheme_monthly_nr_of_bids
                                		);

                                		//--------------------------------------------

                                		$projectTheme_monthly_nr_of_projects = get_option(
                                			"projectTheme_monthly_nr_of_projects"
                                		);
                                		if (empty($projectTheme_monthly_nr_of_projects)) {
                                			$projectTheme_monthly_nr_of_projects = 10;
                                		}

                                		update_user_meta(
                                			$uid,
                                			"projectTheme_monthly_nr_of_projects",
                                			$projectTheme_monthly_nr_of_projects
                                		);

                                		//}

                                		//---------------------

                                		echo sprintf(
                                			__(
                                				'Your payment has been sent. Return to <a href="%s">your account</a>.',
                                				"ProjectTheme"
                                			),
                                			get_permalink(get_option("ProjectTheme_my_account_payments_id"))
                                		);
                                	}
                                	echo "</div>";
                                	?>
                                        <?php
                                else:
                                	 ?>
                                            <div class="details_holder sk_sk_class">
                                                <?php
                                                echo '<table style="margin-top:25px">';
                                                echo "<tr>";
                                                echo "<td><strong>" .
                                                	__("Total to Pay", "ProjectTheme") .
                                                	"</strong></td>";
                                                echo "<td><strong>" .
                                                	ProjectTheme_get_show_price($total, 2) .
                                                	"</strong></td>";
                                                echo "<tr>";

                                                echo "</table>";
                                                ?>
                                                    <?php _e("Your credits amount", "ProjectTheme"); ?>:
                                                        <?php echo projecttheme_get_show_price(
                                                        	projectTheme_get_credits($uid)
                                                        ); ?>
                                                            <br />
                                                            <br />
                                                            <a class="post_bid_btn" href="<?php echo get_bloginfo(
                                                            	" siteurl "
                                                            ); ?>/?p_action=credits_listing_mem&pid=<?php echo $pid; ?>&pay=yes">
                                                                <?php echo __("Pay Now", "ProjectTheme"); ?>
                                                            </a>
                                                            <?php
                                                            $dep_dep = true;
                                                            $dep_dep = apply_filters(
                                                            	"ProjectTheme_credits_listing_add_more",
                                                            	$dep_dep
                                                            );
                                                            if ($dep_dep == true): ?>
                                                                <a class="post_bid_btn" href="<?php echo ProjectTheme_get_payments_page_url(
                                                                	" deposit "
                                                                ); ?>">
                                                                    <?php echo __(
                                                                    	"Add More Credits",
                                                                    	"ProjectTheme"
                                                                    ); ?>
                                                                </a>
                                                                <?php endif;
                                                            ?>
                                            </div>
                                            <?php
                                endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php get_footer();
?>