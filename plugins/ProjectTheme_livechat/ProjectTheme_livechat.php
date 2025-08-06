<?php
/*
Plugin Name: ProjectTheme LiveChat Users
Plugin URI: https://sitemile.com
Description: Adds live chat between users for your project theme from sitemile.com
Version: 1.5.2
Author: sitemile.com
Author URI: https://sitemile.com
Text Domain: pt_livechat
*/


include 'messaging.php';

function pt_live_chat_thing_style() {


  wp_enqueue_script( 'live-chat-js2', plugin_dir_url( __FILE__ )  . '/bootstrap-filestyle.min.js', array(), '1.0.1', true );
    wp_enqueue_style( 'live-chat', plugin_dir_url( __FILE__ ) . "/messages.css?v=123.1" );
    wp_enqueue_script( 'live-chat-js', plugin_dir_url( __FILE__ )  . '/messages.js', array(), '1.0.2', true );
}
add_action( 'wp_enqueue_scripts', 'pt_live_chat_thing_style' );



add_filter('wp_head','z1z1pplv');

function z1z1pplv()
{
  echo  '<script> var SITE_URL="'.site_url().'"; var MESSAGE_EMPTY_STRING = "'.__('You need to type in a message','ProjectTheme').'"; </script>';
}

add_filter('template_redirect','lv_pt_temp_redir');

function lv_pp_myplugin_activate() {

    ProjectTheme_insert_pages_account('ProjectTheme_my_account_livechat_id', 		"Messaging", 		'[project_theme_my_account_livechat]', 	get_option('ProjectTheme_my_account_page_id') );
}
register_activation_hook( __FILE__, 'lv_pp_myplugin_activate' );


function lv_pt_temp_redir()
{


  if(!empty($_GET['search_through_chats']))
  {

    $valu = trim($_GET['get_chat_search']);




              $project_chat = new project_chat();

              if(empty($valu))
              {
                     $all_threads  = $project_chat->get_all_thread_ids(get_current_user_id());
              }
              else {

               $all_threads  = $project_chat->get_all_thread_ids_by_search(get_current_user_id(), $valu);

             }


              if( $all_threads  == false)
              {
                      ?>

                      <li><div class="wrap"><div class="meta">
                                <p class="preview z1x2x3c4 padd10">
                                <?php _e('There are no chats for you yet.','ProjectTheme') ?>
                              </p>
                      </div></div></li>


                      <?php
              }
              else {

                  foreach($all_threads as $thread)
                  {



                      if(empty($current_thid)) $current_thid = $thread->id;
                      //-------

                        $uid1 = $thread->user1;
                        $uid2 = $thread->user2;

                        //---------------

                        if($uid1 == get_current_user_id()) $userToShow = $uid2;
                        else $userToShow = $uid1;



                        $usrUsr = get_userdata($userToShow);

                        if(!empty($usrUsr->user_login))
                        {

                      ?>
<a href="<?php echo projecttheme_get_pm_link_for_thid($thread->id); ?>"><?php echo $usrUsr->user_login; ?>
                      <li class="contact">
                        <div class="wrap">
                          <span class="contact-status <?php if(projecttheme_is_user_online($userToShow)) echo 'online'; ?>"></span>
                          <img src="<?php echo projectTheme_get_avatar($userToShow,40,40) ?>" width=40 height=40 alt="" />
                          <div class="meta">
                            <p class="name"></p>
                            <p class="preview"><?php

                                    $zk = new project_chat();
                                    $get_last_message_of_thread = $zk->get_last_message_of_thread($thread->id);


                                    $pattern = "/[^@\s]*@[^@\s]*\.[^@\s]*/";
    																$replacement = "[removed]";
    																$content = stripslashes($get_last_message_of_thread->content);

    																$content = preg_replace($pattern, $replacement, $content);

    																$text = preg_replace('/\+?[0-9][0-9()\-\s+]{4,20}[0-9]/', '[blocked]', $content);


                                    echo substr($text,0 ,38);

                            ?>                                </p>
                          </div>
                        </div>
                      </li></a>

                      <?php
                    }
                  }





    }

    die();
  }

  if(is_user_logged_in())
  {
      update_user_meta(get_current_user_id(),'lastonline', current_time('timestamp') );
  }


      if(isset($_GET['updatemessages_regular']))
   		{
   			$last_id = $_GET['last_id'];
   			$thid 			= $_GET['thid'];
   			$current_user_id = get_current_user_id();
        $otherpartyid       = $_GET['otherpartyid'];
        $otherpartymessage  = $_GET['otherpartymessage'];

        //----------

        $array_obj = array();

        if(empty($_GET['otherpartymessage'])) $is_this_typing = 0; else $is_this_typing = 1;
   			$array_obj['last_id'] = $last_id;
   			$array_obj['content_messages'] = '';

   			global $wpdb;



   			$chat_orders = new project_chat($thid);
        $thread_content = $chat_orders->get_thread_content();


        $array_obj['is_this_typing'] = $otherpartyid;
        //--------
        // mark as typing
        if($is_this_typing == 1)
        {
              if($thread_content->user1 == $otherpartyid)
              {
                 //update user 1
                 $chat_orders->update_typing_of_user_time(1, $is_this_typing);
              }
              elseif($thread_content->user2 == $otherpartyid) {
                // code...
                  $chat_orders->update_typing_of_user_time(2, $is_this_typing);
                  $array_obj['great'] = $otherpartyid;
                   //wp_mail("andreisaioc@gmail.com","test", "message is: " . $_GET['otherpartymessage']);
              }
        }

        //-----------

        $now = current_time('timestamp');

        if($thread_content->user1 == $otherpartyid)
        {
            $last_typed = $thread_content->user2_last_type + 2;
          }
          elseif($thread_content->user2 == $otherpartyid) {
            // code...
            $last_typed = $thread_content->user1_last_type + 2;
          }

            if($last_typed > $now)
            {
                	$array_obj['other_user_is_typing'] = "yes";
            }
            else {
                  $array_obj['other_user_is_typing'] = "no";
            }



        //-----------

        if(empty($last_id)) $last_id = 0;

   			$messages = $chat_orders->get_messages_from_order_higher_than_id(1, $last_id);

   			if(count($messages) > 0)
   			{
   				foreach($messages as $message)
   				{
   						$array_obj['last_id'] = $message->id;

   							if($message->initiator == $current_user_id)
   							{
   								$usr = get_userdata($message->initiator);

   								ob_start();


   						?>

   						<li class="sent">
   							<img src="<?php echo projecttheme_get_avatar($message->initiator,30,30) ?>" width=30 height=30 alt="" />
   							<p><?php echo   empty($message->content) ? "" : strip_tags($message->content) . "<br/><br/>" ?> <?php

                    $file_attached = $message->file_attached;
                    if($file_attached > 0)
                    {
                        $pppp = get_post($file_attached);
                          echo "<a class='semaf' href='".wp_get_attachment_url($file_attached)."' target='_blank'><i class='fa fa-paperclip attachment'></i> ".$pppp->post_title."</a>";

                    }

               ?></p>
   						</li>




   						<?php

   							 $conts = ob_get_contents();
   							 ob_end_clean();

   							}
   							else {
   								$usr = get_userdata($message->initiator);
   								ob_start();

   								?>

   								<li class="replies">
   									<img src="<?php echo projectTheme_get_avatar($message->initiator,30,30) ?>" width=30 height=30 alt="" />
   									<p><?php echo  empty($message->content) ? "" : strip_tags($message->content). "<br/><br/>"  ?> <?php

                        $file_attached = $message->file_attached;
                        if($file_attached > 0)
                        {
                            $pppp = get_post($file_attached);
                              echo "<a class='semaf' href='".wp_get_attachment_url($file_attached)."' target='_blank'><i class='fa fa-paperclip attachment'></i> ".$pppp->post_title."</a>";

                        }

                   ?></p>
   								</li>


   						<?php

   							$conts = ob_get_contents();
   							ob_end_clean();

   							}

   						$array_obj['content_messages'] .= $conts;
   				}
   			}

   			echo json_encode($array_obj);
   			die();
   		}



      if(isset($_GET['send_regular_chat_message']))
  		{
  				$message_content 		= $_POST['chatbox_textarea'];
  				$thid 							= $_POST['thid'];
  				$current_user_id 		= get_current_user_id();
  				$to_user						= $_POST['to_user'];


  			//---------

  			$message = new project_chat($thid);


        																		require_once(ABSPATH . "wp-admin" . '/includes/file.php');
        																		require_once(ABSPATH . "wp-admin" . '/includes/image.php');


        if(!empty($_FILES['file']['name'])):

          $pids = 0;
          $upload_overrides 	= array( 'test_form' => false );
          $uploaded_file 		= wp_handle_upload($_FILES['file'], $upload_overrides);

          $file_name_and_location = $uploaded_file['file'];
          $file_title_for_media_library = $_FILES['file']['name'];

          $arr_file_type 		= wp_check_filetype(basename($_FILES['file']['name']));
          $uploaded_file_type = $arr_file_type['type'];



          if($uploaded_file_type == "application/zip" or $uploaded_file_type == "application/pdf" or $uploaded_file_type == "application/msword" or $uploaded_file_type == "application/msexcel" or
          $uploaded_file_type == "application/doc" or $uploaded_file_type == "application/docx" or
          $uploaded_file_type == "application/xls" or $uploaded_file_type == "application/xlsx" or $uploaded_file_type == "application/csv" or $uploaded_file_type == "application/ppt" or
          $uploaded_file_type == "application/pptx" or $uploaded_file_type == "application/vnd.ms-excel"
          or $uploaded_file_type == "application/vnd.ms-powerpoint" or $uploaded_file_type == "application/vnd.openxmlformats-officedocument.presentationml.presentation"

          or $uploaded_file_type == "application/octet-stream"
          or $uploaded_file_type == "image/png"
          or $uploaded_file_type == "image/jpg"  or $uploaded_file_type == "image/jpeg"

            or $uploaded_file_type == "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            or $uploaded_file_type == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"  )
          {



            $attachment = array(
                    'post_mime_type' => $uploaded_file_type,
                    'post_title' => addslashes($file_title_for_media_library),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_parent' =>  0,

                    'post_author' => $uid,
                  );

            $attach_id 		= wp_insert_attachment( $attachment, $file_name_and_location, $pids );
            $attach_data 	= wp_generate_attachment_metadata( $attach_id, $file_name_and_location );
            wp_update_attachment_metadata($attach_id,  $attach_data);




          } else $error_mm = '1';

        endif;

        //----------

        $pattern = "/[^@\s]*@[^@\s]*\.[^@\s]*/";
        $replacement = "[removed]";
        preg_replace($pattern, $replacement, $message_content);

        //---------

        $pattern = "/[a-zA-Z]*[:\/\/]*[A-Za-z0-9\-_]+\.+[A-Za-z0-9\.\/%&=\?\-_]+/i";
        $replacement = "[removed]";
        preg_replace($pattern, $replacement, $message_content);

  			$message->insert_message($current_user_id, $to_user, $message_content, $attach_id); //, $attached = '', $message_type = '1');



  			echo "inserted ok";

  					die();
  		}

}


function projecttheme_get_pm_link_from_user($current_uid, $uid2)
{
	$pm_page = get_option('ProjectTheme_my_account_livechat_id');

	if(!is_user_logged_in()) return get_permalink( $pm_page );
	if($current_uid == $uid2 ) return get_permalink( $pm_page );


	$pricerr_chat = new project_chat();
	$thid = $pricerr_chat->get_thread_id($current_uid, $uid2);

	if(projectTheme_using_permalinks())
	{
			return get_permalink($pm_page) . "?thid=" . $thid;
	}
	else {
			return get_permalink($pm_page) . "&thid=" . $thid;
	}
}


?>
