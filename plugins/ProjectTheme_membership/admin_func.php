<?php

function PT_mem_extra_profile_fields($user) {
    ?>
    <h3><?php _e("User Membership"); ?></h3>
    <?php
    $uid = $user->ID;

    // Get required data
    $membership_available = get_user_meta($uid, "membership_available", true);
    $tm = current_time("timestamp", 0);
    $ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");
    $show_this_thing = 0;

    if ($ProjectTheme_enable_membs === "yes") {
        $role = ProjectTheme_mems_get_current_user_role($uid);

        // Determine if memberships should be displayed based on role
        $role_settings = [
            "service_provider" => "ProjectTheme_free_mode_freelancers",
            "investor" => "ProjectTheme_free_mode_investor",
            "business_owner" => "ProjectTheme_free_mode_buyers",
        ];

        if (isset($role_settings[$role])) {
            $mode_option = get_option($role_settings[$role]);
            if ($mode_option === "paid") {
                $show_this_thing = 1;
            }
        }

        if ($show_this_thing === 1) { ?>
            <div class="err1">
                <?php if ($membership_available > $tm) {
                    $expiry_date = date_i18n("d-M-Y H:i:s", $membership_available);
                    echo sprintf(__("Membership expires on: %s", "ProjectTheme"), $expiry_date);
                } else {
                    $lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
                    echo '<span class="balance">' . __("Membership expired. Purchase <a href='$lnk'>here</a>", "ProjectTheme") . "</span>";
                } ?>
            </div>
        <?php }
    }

    // Display membership packages if no active membership exists
    if ($membership_available <= $tm || empty($membership_available)) {
        $k = 0;
        $role_memberships = [
            "service_provider" => "freelancer",
            "investor" => "investor",
            "business_owner" => "project_owner",
        ];

        if (isset($role_memberships[$role])) {
            $prefix = $role_memberships[$role];
            for ($i = 1; $i <= 6; $i++) {
                $name = get_option("pt_{$prefix}_membership_name_" . $i);
                $cost = get_option("pt_{$prefix}_membership_cost_" . $i);
                $time = get_option("pt_{$prefix}_membership_time_" . $i);
                $bids = get_option("pt_{$prefix}_membership_bids_" . $i);

                if (!empty($name)) {
                    $link = get_site_url() . "/?p_action=purchase_membership_{$prefix}&id=" . $i;
                    $free_link = get_site_url() . "/?get_free_membership=" . $i;
                    ?>
                    <div class="membership-box">
                        <div class="membership-box-inner">
                            <div class="membership-box-title"><?php echo $name; ?></div>
                            <div class="membership-box-price">
                                <?php echo $cost == 0 ? __("FREE", "ProjectTheme") : projectTheme_get_show_price($cost); ?>
                            </div>
                            <div class="membership-box-bids">
                                <?php echo sprintf(__("%s bid(s)", "ProjectTheme"), $bids); ?>
                            </div>
                            <div class="membership-box-duration">
                                <?php echo sprintf(__("%s month(s)", "ProjectTheme"), $time); ?>
                            </div>
                            <div class="membership-box-btn">
                                <a href="<?php echo $cost == 0 ? $free_link : $link; ?>" class="btn btn-success">
                                    <?php echo $cost == 0 ? __("Get Free Membership", "ProjectTheme") : __("Purchase", "ProjectTheme"); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php $k++;
                }
            }
        }
        ?>
        <style>
            .membership-box {
                width: <?php echo $k > 0 ? round(100 / $k) : 100; ?>%;
                float: left;
            }
        </style>
        <?php
    }

    // Display additional email fields
    ?>
    <table class="form-table">
        <tr>
            <th><label for="gmail">Gmail</label></th>
            <td>
                <input type="text" name="gmail" id="gmail" value="<?php echo esc_attr(get_the_author_meta("gmail", $user->ID)); ?>" class="regular-text" />
                <br /> <span class="description">Enter your Gmail.</span>
            </td>
        </tr>
        <tr>
            <th><label for="yahoo">Yahoo</label></th>
            <td>
                <input type="text" name="yahoo" id="yahoo" value="<?php echo esc_attr(get_the_author_meta("yahoo", $user->ID)); ?>" class="regular-text" />
                <br /> <span class="description">Enter your Yahoo email.</span>
            </td>
        </tr>
        <tr>
            <th><label for="hotmail">Hotmail</label></th>
            <td>
                <input type="text" name="hotmail" id="hotmail" value="<?php echo esc_attr(get_the_author_meta("hotmail", $user->ID)); ?>" class="regular-text" />
                <br /> <span class="description">Enter your Hotmail email.</span>
            </td>
        </tr>
    </table>
<?php
}

// Hook into user profile actions
add_action("show_user_profile", "PT_mem_extra_profile_fields", 10);
add_action("edit_user_profile", "PT_mem_extra_profile_fields", 10);
?>
