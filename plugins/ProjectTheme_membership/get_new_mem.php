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

function ProjectTheme_filter_ttl( $title ) {
    return __( "Purchase Membership", "ProjectTheme" ) . " - ";
}
add_filter( "wp_title", "ProjectTheme_filter_ttl", 10, 3 );
if ( !is_user_logged_in() ) {
    wp_redirect( get_bloginfo( "siteurl" ) . "/wp-login.php" );
    exit();
}
$current_user = wp_get_current_user();
$uid = $current_user->ID;
$cid = $current_user->ID;
$tm = current_time( "timestamp" );
/ /=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=  -=
global $wpdb, $wp_rewrite, $wp_query;
//-------------------------------------
get_header( "account" );
get_template_part( "lib/my_account/aside-menu" );
?>
<div class="page-wrapper" style="display:block">
    <div class="container-fluid">
        <?php do_action( "pt_for_demo_work_3_0" );
?>
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-lg-8">
                    <div class="page-header">
                        <h1 class="page-title">
                            <?php _e( "Purchase Membership/Subscription", "ProjectTheme" );
?>
                        </h1>
                    </div>
                </div>
            </div>
            <!-- ########## -->
            <div class="row">
                <div class="account-main-area col-xs-12 col-sm-12 col-md-12 col-lg-12">
                    <?php
$role = ProjectTheme_mems_get_current_user_role( $uid );
if ( $role == "service_provider" ) {
    $cost = get_option( "projectTheme_monthly_service_provider" );
} else {
    $cost = get_option( "projectTheme_monthly_service_contractor" );
}
if ( ProjectTheme_2_user_types() == true ) {
    if ( $role == "service_provider" ) {
        $membership_available = get_user_meta( $uid, "membership_available", true );
        $projectTheme_monthly_nr_of_bids = get_user_meta( $uid, "projectTheme_monthly_nr_of_bids", true );
        if ( $membership_available > $tm and $projectTheme_monthly_nr_of_bids > 0 ) {
            echo '<div class="card"><div class="p-3">';
            echo sprintf(
                __( "You have %s membership. Your membership is valid until: %s" ),
                $mem,
                date_i18n( "d-M-Y", $membership_available )
            );
            echo "</div></div>";
        } else {

            $k = 0;
            for ( $i = 1; $i <= 6; $i++ ) {
                $name = get_option( "pt_freelancer_membership_name_" . $i );
                $cost = get_option( "pt_freelancer_membership_cost_" . $i );
                $time = get_option( "pt_freelancer_membership_time_" . $i );
                $bids = get_option( "pt_freelancer_membership_bids_" . $i );
                $showIt = true;
                if ( $cost == 0 ) {
                    $free_membership_exhausted = get_user_meta(
                        get_current_user_id(),
                        "free_membership_exhausted",
                        true
                    );
                    if ( $free_membership_exhausted == "yes" ) {
                        $showIt = false;
                    }
                }
                if ( !empty( $name ) and $showIt ) {

                    $link = get_site_url() . "/?p_action=purchase_membership_service_provider&id=" . $i;
                    $free_link = get_site_url() . "/?get_free_membership=" . $i;
                    ?>
                    <div class="membership-box ">
                        <div class="membership-box-inner card ">
                            <div class="membership-box-title">
                                <?php echo $name;
                    ?>
                            </div>
                            <div class="membership-box-price">
                                <?php if ( $cost == 0 ) {
                        _e( "FREE", "ProjectTheme" );
                    } else {
                        echo projectTheme_get_show_price( $cost );
                    }
                    ?>
                            </div>
                            <div class="membership-box-bids">
                                <?php echo sprintf( __( "%s bid(s)", "ProjectTheme" ), $bids );
                    ?>
                            </div>
                            <div class="membership-box-duration">
                                <?php echo sprintf( __( "%s month(s)", "ProjectTheme" ), $time );
                    ?>
                            </div>
                            <div class="membership-box-btn ">
                                <a href="<?php echo $cost == 0 ? $free_link : $link; ?>" class="btn btn-success">
                                    <?php _e( "Purchase This", "ProjectTheme" );
                    ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php $k++;
                }
            }
            ?>
                    <style>
                        .membership-box {
                            width: <?php echo round(100 / $k);
                            ?>%;
                            float: left;
                        }
                    </style>
                    <?php
        }
    }

    //Investor
    elseif ( $role == "investor" ) {
        $membership_available = get_user_meta( $uid, "membership_available", true );
        $projectTheme_monthly_nr_of_bids = get_user_meta( $uid, "projectTheme_monthly_nr_of_bids", true );
        if ( $membership_available > $tm and $projectTheme_monthly_nr_of_bids > 0 ) {
            echo '<div class="card"><div class="p-3">';
            echo sprintf(
                __( "You have %s membership. Your membership is valid until: %s" ),
                $mem,
                date_i18n( "d-M-Y", $membership_available )
            );
            echo "</div></div>";
        } else {

            $k = 0;
            for ( $i = 1; $i <= 6; $i++ ) {
                $name = get_option( "pt_investor_membership_name_" . $i );
                $cost = get_option( "pt_investor_membership_cost_" . $i );
                $time = get_option( "pt_investor_membership_time_" . $i );
                $bids = get_option( "pt_investor_membership_bids_" . $i );
                $showIt = true;
                if ( $cost == 0 ) {
                    $free_membership_exhausted = get_user_meta(
                        get_current_user_id(),
                        "free_membership_exhausted",
                        true
                    );
                    if ( $free_membership_exhausted == "yes" ) {
                        $showIt = false;
                    }
                }
                if ( !empty( $name ) and $showIt ) {

                    $link = get_site_url() . "/?p_action=purchase_membership_investor&id=" . $i;
                    $free_link = get_site_url() . "/?get_free_membership=" . $i;
                    ?>
                    <div class="membership-box ">
                        <div class="membership-box-inner card ">
                            <div class="membership-box-title">
                                <?php echo $name;
                    ?>
                            </div>
                            <div class="membership-box-price">
                                <?php if ( $cost == 0 ) {
                        _e( "FREE", "ProjectTheme" );
                    } else {
                        echo projectTheme_get_show_price( $cost );
                    }
                    ?>
                            </div>
                            <div class="membership-box-bids">
                                <?php echo sprintf( __( "%s bid(s)", "ProjectTheme" ), $bids );
                    ?>
                            </div>
                            <div class="membership-box-duration">
                                <?php echo sprintf( __( "%s month(s)", "ProjectTheme" ), $time );
                    ?>
                            </div>
                            <div class="membership-box-btn ">
                                <a href="<?php echo $cost == 0 ? $free_link : $link; ?>" class="btn btn-success">
                                    <?php _e( "Purchase This", "ProjectTheme" );
                    ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php $k++;
                }
            }
            ?>
                    <style>
                        .membership-box {
                            width: <?php echo round(100 / $k);
                            ?>%;
                            float: left;
                        }
                    </style>
                    <?php
        }
    } else {
        //service owner, project Owner
        $membership_available = get_user_meta( $uid, "membership_available", true );
        if ( $membership_available > $tm ) {
            echo '<div class="card"><div class="p-3">';
            echo sprintf(
                __( "You have %s membership. Your membership is valid until: %s" ),
                $mem,
                date_i18n( "d-M-Y", $membership_available )
            );
            echo "</div></div>";
        } else {

            $k = 0;
            for ( $i = 1; $i <= 6; $i++ ) {
                $name = get_option( "pt_project_owner_membership_name_" . $i );
                $cost = get_option( "pt_project_owner_membership_cost_" . $i );
                $time = get_option( "pt_project_owner_membership_time_" . $i );
                $bids = get_option( "pt_project_owner_membership_projects_" . $i );
                $showIt = true;
                if ( $cost == 0 ) {
                    $free_membership_exhausted = get_user_meta(
                        get_current_user_id(),
                        "free_membership_exhausted",
                        true
                    );
                    if ( $free_membership_exhausted == "yes" ) {
                        $showIt = false;
                    }
                }
                if ( !empty( $name ) and $showIt ) {

                    $link = get_site_url() . "/?p_action=purchase_membership_buyer&id=" . $i;
                    $free_link = get_site_url() . "/?get_free_membership=" . $i;
                    ?>
                    <div class="membership-box ">
                        <div class="membership-box-inner card ">
                            <div class="membership-box-title">
                                <?php echo $name;
                    ?>
                            </div>
                            <div class="membership-box-price">
                                <?php if ( $cost == 0 ) {
                        _e( "FREE", "ProjectTheme" );
                    } else {
                        echo projectTheme_get_show_price( $cost );
                    }
                    ?>
                            </div>
                            <div class="membership-box-bids">
                                <?php echo sprintf( __( "%s project(s)", "ProjectTheme" ), $bids );
                    ?>
                            </div>
                            <div class="membership-box-duration">
                                <?php echo sprintf( __( "%s month(s)", "ProjectTheme" ), $time );
                    ?>
                            </div>
                            <div class="membership-box-btn ">
                                <a href="<?php echo $cost == 0 ? $free_link : $link; ?>" class="btn btn-success">
                                    <?php _e( "Purchase This", "ProjectTheme" );
                    ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php $k++;
                }
            }
            ?>
                    <style>
                        .membership-box {
                            width: <?php echo round(100 / $k);
                            ?>%;
                            float: left;
                        }
                    </style>
                    <?php
        }
    }
} else {
}

//-----------------------------------------
?>
                    <div class="monthly_mem">
                        <?php /*	echo '<a href="'.get_bloginfo( 'siteurl' ).'/?p_action=credits_listing_mem" class="green_btn">'.__( 'Pay by Credits', 'ProjectTheme' ).'</a>';
//-------------------
$ProjectTheme_paypal_enable 		 = get_option( 'ProjectTheme_paypal_enable' );
$ProjectTheme_alertpay_enable 		 = get_option( 'ProjectTheme_alertpay_enable' );
$ProjectTheme_moneybookers_enable 	 = get_option( 'ProjectTheme_moneybookers_enable' );
if ( $ProjectTheme_paypal_enable == "yes" )
echo '<a href="'.get_bloginfo( 'siteurl' ).'/?p_action=paypal_membership_mem" class="green_btn">'.__( 'Pay by PayPal', 'ProjectTheme' ).'</a>';
if ( $ProjectTheme_moneybookers_enable == "yes" )
echo '<a href="'.get_bloginfo( 'siteurl' ).'/?p_action=mb_membership_mem" class="green_btn">'.__( 'Pay by MoneyBookers/Skrill', 'ProjectTheme' ).'</a>';
if ( $ProjectTheme_alertpay_enable == "yes" )
echo '<a href="'.get_bloginfo( 'siteurl' ).'/?p_action=payza_membership_mem" class="green_btn">'.__( 'Pay by Payza', 'ProjectTheme' ).'</a>';
*/
do_action( "ProjectTheme_add_payment_options_to_membership", $pid );
?> </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php get_footer( "account" );
?>