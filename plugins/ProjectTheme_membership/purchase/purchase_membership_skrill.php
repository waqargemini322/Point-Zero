<?php

  $title_post = __('Membership Fee','ProjectTheme');
  $mem_id = $_GET['id'];

  if($_GET['tp'] == "freelancer") $slug = "freelancer"; else  $slug = "project_owner";
  $cost = get_option('pt_'.$slug.'_membership_cost_' . $mem_id);

//------------------

  $business = get_option('ProjectTheme_moneybookers_email');
  if(empty($business)) die('ERROR. Please input your Moneybookers email.');

//------------------

  $tm 			= current_time('timestamp',0);
  $cancel_url 	   = ProjectTheme_get_payments_page_url('deposit');
  $response_url 	 = home_url().'/?p_action=skrill_membership_payment_response';
  $ccnt_url		     = ProjectTheme_get_payments_page_url();
  $currency 		   = get_option('ProjectTheme_currency');

  $uid = get_current_user_id();



  if(ProjectTheme_using_permalinks())
  {
    $return_url = get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_done=1";
  }
  else
  {
    $return_url = get_permalink(get_option('ProjectTheme_my_account_page_id')) . "&payment_done=1";
  }

 ?>



 <html>
 <head><title>Processing Skrill Payment...</title></head>
 <body onLoad="document.form_mb.submit();">
 <center><h3><?php _e('Please wait, your order is being processed...', 'ProjectTheme'); ?></h3></center>


     <form name="form_mb" action="https://www.skrill.com/app/payment.pl">
     <input type="hidden" name="pay_to_email" value="<?php echo $business; ?>">
     <input type="hidden" name="payment_methods" value="ACC,OBT,GIR,DID,SFT,ENT,EBT,SO2,IDL,PLI,NPY,EPY">

     <input type="hidden" name="recipient_description" value="<?php bloginfo('name'); ?>">

     <input type="hidden" name="cancel_url" value="<?php echo get_permalink(get_option('ProjectTheme_my_account_page_id')); ?>">
     <input type="hidden" name="status_url" value="<?php echo $response_url; ?>">

     <input type="hidden" name="language" value="EN">

     <input type="hidden" name="merchant_fields" value="field1">
     <input type="hidden" name="field1" value="<?php echo $mem_id.'|'.$uid.'|'.current_time('timestamp',0)."|" . $slug ?>">

     <input type="hidden" name="amount" value="<?php echo $cost; ?>">
     <input type="hidden" name="currency" value="<?php echo $currency ?>">

     <input type="hidden" name="detail1_description" value="Product: ">
     <input type="hidden" name="detail1_text" value="<?php echo $title_post; ?>">

     <input type="hidden" name="return_url" value="<?php echo $return_url; ?>">


     </form>


 </body>
 </html>
