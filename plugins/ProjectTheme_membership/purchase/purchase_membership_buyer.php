<?php
if(!is_user_logged_in())
{
  wp_redirect(home_url().'/wp-login.php?action=register');
  exit;
}

//------



global $current_user, $wp_query;
$memid 	=  $_GET['id'];

if($memid != 1 and $memid != 2 and $memid != 3 and $memid != 4 and $memid != 5 and $memid != 6) { echo 'oops, error, membership'; exit; }

function ProjectTheme_filter_ttl($title){return __("Purchase Membership",'ProjectTheme')." - ";}
add_filter( 'wp_title', 'ProjectTheme_filter_ttl', 10, 3 );




$current_user=wp_get_current_user();

$post = get_post($pid);

$uid 	= $current_user->ID;
$title 	= $post->post_title;
$cid 	= $current_user->ID;

$pt_project_owner_membership_cost_ = get_option('pt_project_owner_membership_cost_' . $memid);
if($pt_project_owner_membership_cost_ == 0) { echo 'oops, error, membership empty'; exit; }
//-------------------------------------

get_header('account');
get_template_part ( 'lib/my_account/aside-menu'  );

$nm = get_option('pt_project_owner_membership_name_' .  $memid);

 ?>

 <div class="page-wrapper" style="display:block">
 	<div class="container-fluid"  >


 	<?php



 	do_action('pt_for_demo_work_3_0');



 ?>

 <div class="container">

 <div class="row">
 <div class="col-sm-12 col-lg-12">




 <div class="page-header">
 			<h1 class="page-title">
 	<?php printf(__("Purchase Membership - %s", "ProjectTheme"), $nm); ?>
 			</h1>
 		</div></div></div>









<div class="row">


     	<div class="account-main-area col-xs-12 col-sm-12 col-md-12 col-lg-12">
                 <div class="card">
                 	<div class="padd10">


                     <div class="box_content">
                        <?php


                      if($_GET['confirm_cred'] == "1")
                      {
                              // confirm paying with credits last step

                              $ctm = current_time('timestamp');

                              $val1 = get_user_meta(get_current_user_id(), 'membership_available', true);
                              $val2 = get_user_meta(get_current_user_id(), 'projectTheme_monthly_nr_of_projects', true);

                              if($val1 < $ctm or $val2 >= 0 )
                              {
                                  $cr = projectTheme_get_credits(get_current_user_id());
                                  projectTheme_update_credits(get_current_user_id(), $cr - $pt_project_owner_membership_cost_);

                                  $uprof 	= ProjectTheme_get_user_profile_link($receiver_user->ID); //home_url()."/user-profile/".$receiver_user->user_login;
                                  $reason = sprintf(__('Payment for purchasing membership %s','ProjectTheme'), $nm);
                                  projectTheme_add_history_log('0', $reason, $pt_project_owner_membership_cost_, get_current_user_id());

                                  //---- adding the membership to the buyer user ----
                                  $newtm = $ctm + get_option('pt_project_owner_membership_time_'.$memid) * 3600 * 24 *30.5;
                                  update_user_meta( get_current_user_id(), 'membership_available', $newtm );

                                  $name = get_option('pt_project_owner_membership_name_' . $memid);
                                  update_user_meta( get_current_user_id(), 'mem_type',  $name );
                                  update_user_meta( get_current_user_id(), 'projectTheme_monthly_nr_of_projects', get_option('pt_project_owner_membership_projects_' . $memid) );

                              }

                              ?>


                              <div class="alert alert-success" role="alert">
                                    <div class=""><?php echo sprintf(__('You have successfully upgraded your membership. <a href="%s">Return to your account</a>.','ProjectTheme'),
                                    get_permalink( get_option('ProjectTheme_my_account_page_id') )); ?></div>



                              </div>


                              <?php

                              //------------------------------------------------------


                      }
                      else {


                        ?>

                       <div class="row"><div class=" col-lg-12 mb-4">
                       <?php

                              _e('You are about to purchase your membership. You can see the details of your membership down below: ','ProjectTheme');
                                $nm = get_option('pt_project_owner_membership_name_' . $memid);

                          ?>
                          </div></div>


                                      <table class="table">

                                          <tbody>
                                            <tr>
                                              <th scope="row"><?php _e('Membership name: ','ProjectTheme') ?></th>
                                              <th><?php echo $nm ?></th>
                                            </tr>

                                            <tr>
                                              <th scope="row"><?php _e('Membership cost: ','ProjectTheme') ?></th>
                                              <th><?php echo projectTheme_get_show_price($pt_project_owner_membership_cost_) ?></th>
                                            </tr>


                                            <?php

                                            if($_GET['agreewith'] == "cred")
                                            {
                                                  $bal = projectTheme_get_credits(get_current_user_id());
                                                  if($bal < $pt_project_owner_membership_cost_) $class = "font-weight-bold text-danger"

                                                ?>


                                                <tr>
                                                  <th scope="row" class="<?php echo $class ?>"><?php _e('Your e-wallet Balance: ','ProjectTheme') ?></th>
                                                  <th class="<?php echo $class ?>"><?php echo projectTheme_get_show_price($bal) ?></th>
                                                </tr>

                                                <?php
                                            }


                                             ?>


                                            <tr>
                                              <th scope="row"><?php _e('Valid for: ','ProjectTheme') ?></th>
                                              <th><?php echo sprintf(__("%s month(s)","ProjectTheme"), get_option('pt_project_owner_membership_time_'.$memid)) ?></th>
                                            </tr>


                                            <tr>
                                              <th scope="row"><?php _e('Projects included: ','ProjectTheme') ?></th>
                                              <th><?php echo get_option('pt_project_owner_membership_projects_' . $memid) ?></th>
                                            </tr>




                                          </tbody></table>


<div class="row"><div class=" col-lg-12 mb-4 align-right-thing">
<?php


if($_GET['agreewith'] == "cred")
{
      // pay the membership with the balance

      if($bal < $pt_project_owner_membership_cost_)
      {
          ?>

          <div class="alert alert-danger" role="alert">
                <div class=""><?php _e('You do not have enough balance in your e-wallet. Please deposit money or use another payment method.','ProjectTheme'); ?></div>

                <div class="mt-4">
                    <a href="<?php echo get_site_url() . "?p_action=purchase_membership_buyer&id=" . $_GET['id']; ?>" class="btn btn-secondary"><?php _e('Go back','ProjectTheme') ?><a/>
                        <a href="<?php echo get_permalink( get_option('ProjectTheme_my_account_payments_id') ) ?>" class="btn btn-primary"><?php _e('Go to Finances','ProjectTheme') ?><a/>
                </div>

          </div>

          <?php
      }
      else {

      ?>

            <div class="alert alert-secondary" role="alert">
                <div class="">  <?php _e('You are about to pay for your membership using your e-wallet balance.','ProjectTheme'); ?></div>

                  <div class="mt-4">
                          <a href="<?php echo get_site_url() . "?p_action=purchase_membership_buyer&id=" . $_GET['id']; ?>" class="btn btn-secondary"><?php _e('Go back','ProjectTheme') ?><a/>
                          <a href="<?php echo get_site_url() . "?p_action=purchase_membership_buyer&id=" . $_GET['id']; ?>&agreewith=cred&confirm_cred=1" class="btn btn-success"><?php _e('Confirm Payment','ProjectTheme') ?><a/>
                  </div>
            </div>


      <?php
    }

}
else
{


    $ProjectTheme_enable_credits_wallet = get_option('ProjectTheme_enable_credits_wallet');
    if($ProjectTheme_enable_credits_wallet != "no")
    {

 ?>
            <button type="button" onclick="location.href='<?php echo get_site_url() ?>/?p_action=purchase_membership_buyer&id=<?php echo $_GET['id'] ?>&agreewith=cred'" class="btn btn-secondary"><?php _e('Pay by eWallet','ProjectTheme') ?></button>

          <?php } ?>

<?php

$ProjectTheme_paypal_enable = get_option('ProjectTheme_paypal_enable');
if($ProjectTheme_paypal_enable != "no")
{


 ?>
          <button type="button" onclick="location.href='<?php echo get_site_url() ?>/?p_action=purchase_membership_paypal&tp=project_owner&id=<?php echo $_GET['id'] ?>'" class="btn btn-primary"><?php _e('Pay by PayPal','ProjectTheme') ?></button>
<?php }



$ProjectTheme_enable_credits_wallet = get_option('ProjectTheme_moneybookers_enable');
if($ProjectTheme_enable_credits_wallet != "no")
{

 ?>
          <button type="button" onclick="location.href='<?php echo get_site_url() ?>/?p_action=purchase_membership_skrill&tp=project_owner&id=<?php echo $_GET['id'] ?>'" class="btn btn-primary"><?php _e('Pay by Skrill','ProjectTheme') ?></button>

<?php }

// Add Clover payment button in the membership purchase page

$ProjectTheme_clover_enable = get_option('ProjectTheme_clover_enable');
if ($ProjectTheme_clover_enable == "yes") {
    ?>
    <button type="button" onclick="location.href='<?php echo get_site_url() ?>/?p_action=purchase_membership_clover_buyer&id=<?php echo $_GET['id'] ?>'" class="btn btn-primary">
        <?php _e('Pay by Clover', 'ProjectTheme'); ?>
    </button>
    <?php
}


do_action('project_theme_membership_purchase_buyer');


 } // end   if($_GET['agreewith'] == "cred") ?>


</div></div>

<?php } ?>
                     </div>
                     </div>





     </div></div></div></div></div>
<?php get_footer('footer'); ?>
   </div>
