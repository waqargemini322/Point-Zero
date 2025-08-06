<?php

add_shortcode("pt_display_freelancer_mem_packs", "pt_display_freelancer_mem_packs_fnc");
add_shortcode("pt_display_buyer_mem_packs", "pt_display_buyer_mem_packs");
add_shortcode("pt_display_investor_mem_packs", "pt_display_investor_mem_packs");

function pt_display_buyer_mem_packs()
{
  ob_start();

  $k = 0;

  for ($i = 1; $i <= 6; $i++) {
    $name = get_option("pt_project_owner_membership_name_" . $i);
    $cost = get_option("pt_project_owner_membership_cost_" . $i);
    $time = get_option("pt_project_owner_membership_time_" . $i);
    $bids = get_option("pt_project_owner_membership_projects_" . $i);

    if (!empty($name)) {
      $free_link = get_site_url() . "/?get_free_membership=" . $i;
      $buyer_lnk = get_site_url() . "?p_action=purchase_membership_buyer&id=" . $i;
      $showIt = true;

      if ($cost == 0) {
        $free_membership_exhausted = get_user_meta(get_current_user_id(), "free_membership_exhausted", true);
        if ($free_membership_exhausted == "yes") {
          $showIt = false;
        }
      }

      if ($showIt) { ?>
                <div class = "membership-box"><div class = "membership-box-inner card ">
                <div class = "membership-box-title"><?php echo $name; ?></div>
                <div class = "membership-box-price"><?php if ($cost == 0) {
                  _e("FREE", "ProjectTheme");
                } else {
                  echo projectTheme_get_show_price($cost);
                } ?></div>
                <div class = "membership-box-bids"><?php echo sprintf(
                  __("%s projects(s)", "ProjectTheme"),
                  $bids
                ); ?></div>
                <div class = "membership-box-duration"><?php echo sprintf(
                  __("%s month(s)", "ProjectTheme"),
                  $time
                ); ?></div>

                <div class = "membership-box-btn "><a href = "<?php if ($cost == 0) {
                  echo $free_link;
                } else {
                  echo $buyer_lnk;
                } ?>" class = "btn btn-success"><?php _e("Purchase This", "ProjectTheme"); ?></a></div>

                </div></div>

                <?php $k++;}
    }
  }
  ?>

    <style>
    .membership-box {
        width: <?php echo round(100 / $k); ?>%;
        float:left;
    }

    @media only screen and ( max-width: 600px ) {
        .membership-box2 {
            width: 100%;
        }
    }

    </style>

    <?php
    $page = ob_get_contents();
    ob_end_clean();

    return $page;
}

//------------------------------

function pt_display_freelancer_mem_packs_fnc()
{
  ob_start();

  $k = 0;

  for ($i = 1; $i <= 6; $i++) {
    $name = get_option("pt_freelancer_membership_name_" . $i);
    $cost = get_option("pt_freelancer_membership_cost_" . $i);
    $time = get_option("pt_freelancer_membership_time_" . $i);
    $bids = get_option("pt_freelancer_membership_bids_" . $i);

    $free_link = get_site_url() . "/?get_free_membership=" . $i;
    $sp_lnk = get_site_url() . "?p_action=purchase_membership_service_provider&id=" . $i;
    $showIt = true;

    if (!empty($name)) {
      if ($cost == 0) {
        $free_membership_exhausted = get_user_meta(get_current_user_id(), "free_membership_exhausted", true);
        if ($free_membership_exhausted == "yes") {
          $showIt = false;
        }
      }

      if ($showIt) { ?>
                <div class = "membership-box2"><div class = "membership-box-inner card ">
                <div class = "membership-box-title"><?php echo $name; ?></div>
                <div class = "membership-box-price"><?php if ($cost == 0) {
                  _e("FREE", "ProjectTheme");
                } else {
                  echo projectTheme_get_show_price($cost);
                } ?></div>
                <div class = "membership-box-bids"><?php echo sprintf(__("%s bid(s)", "ProjectTheme"), $bids); ?></div>
                <div class = "membership-box-duration"><?php echo sprintf(
                  __("%s month(s)", "ProjectTheme"),
                  $time
                ); ?></div>

                <div class = "membership-box-btn "><a href = "<?php if ($cost == 0) {
                  echo $free_link;
                } else {
                  echo $sp_lnk;
                } ?>" class = "btn btn-success"><?php _e("Purchase This", "ProjectTheme"); ?></a></div>

                </div></div>

                <?php $k++;}
    }
  }
  ?>

    <style>
    .membership-box2 {
        width: <?php echo round(100 / $k); ?>%;
        float:left;
    }

    @media only screen and ( max-width: 600px ) {
        .membership-box2 {
            width: 100%;
        }
        </style>

        <?php
        $page = ob_get_contents();
        ob_end_clean();

        return $page;
}

//Investor member pack

function pt_display_investor_mem_packs()
{
  ob_start();

  $k = 0;

  for ($i = 1; $i <= 6; $i++) {
    $name = get_option("pt_investor_membership_name_" . $i);
    $cost = get_option("pt_investor_membership_cost_" . $i);
    $time = get_option("pt_investor_membership_time_" . $i);
    $bids = get_option("pt_investor_membership_bids_" . $i);

    $free_link = get_site_url() . "/?get_free_membership=" . $i;
    $sp_lnk = get_site_url() . "?p_action=purchase_membership_investor&id=" . $i;
    $showIt = true;

    if (!empty($name)) {
      if ($cost == 0) {
        $free_membership_exhausted = get_user_meta(get_current_user_id(), "free_membership_exhausted", true);
        if ($free_membership_exhausted == "yes") {
          $showIt = false;
        }
      }

      if ($showIt) { ?>
                    <div class = "membership-box2"><div class = "membership-box-inner card ">
                    <div class = "membership-box-title"><?php echo $name; ?></div>
                    <div class = "membership-box-price"><?php if ($cost == 0) {
                      _e("FREE", "ProjectTheme");
                    } else {
                      echo projectTheme_get_show_price($cost);
                    } ?></div>
                    <div class = "membership-box-bids"><?php echo sprintf(
                      __("%s bid(s)", "ProjectTheme"),
                      $bids
                    ); ?></div>
                    <div class = "membership-box-duration"><?php echo sprintf(
                      __("%s month(s)", "ProjectTheme"),
                      $time
                    ); ?></div>

                    <div class = "membership-box-btn "><a href = "<?php if ($cost == 0) {
                      echo $free_link;
                    } else {
                      echo $sp_lnk;
                    } ?>" class = "btn btn-success"><?php _e("Purchase This", "ProjectTheme"); ?></a></div>

                    </div></div>

                    <?php $k++;}
    }
  }
  ?>

        <style>
        .membership-box2 {
            width: <?php echo round(100 / $k); ?>%;
            float:left;
        }

        @media only screen and ( max-width: 600px ) {
            .membership-box2 {
                width: 100%;
            }
            </style>

            <?php
            $page = ob_get_contents();
            ob_end_clean();

            return $page;
}

?>