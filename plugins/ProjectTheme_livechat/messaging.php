<?php


function projecttheme_is_user_online($uid)
{
    $tm = current_time('timestamp');
    $last = get_user_meta($uid,'lastonline',  true );
    $diff = 300;
    $xx1 = $diff + $last;

    if($tm < ($xx1)) return true;
    return false;
}


function projecttheme_get_pm_link_for_thid($thid)
{
	$pm_page = get_option('ProjectTheme_my_account_livechat_id');



	if(projecttheme_using_permalinks())
	{
			return get_permalink($pm_page) . "?thid=" . $thid;
	}
	else {
			return get_permalink($pm_page) . "&thid=" . $thid;
	}
}




add_shortcode( 'project_theme_my_account_livechat', 'pt_live_chat_messaging' );

include 'chat-regular.class.php';

function pt_live_chat_messaging()
{

ob_start();
  ?>





  <?php

	global $current_user;
	$current_user = wp_get_current_user();
	$uid = $current_user->ID;

	//-------------------------------------

    $pg = $_GET['pg'];
	if(!isset($pg)) $pg = 'home';


			global $current_user;
			$current_user = wp_get_current_user();
			$uid = $current_user->ID;

			global $wpdb; $prefix = $wpdb->prefix;


        $current_thid = $_GET['thid'];



        $date_format =  get_option( 'date_format' );
				get_template_part ( 'lib/my_account/aside-menu'  );


?>

        <div class="page-wrapper" style="display:block">
          <div class="container-fluid"  >


          <?php



          do_action('pt_for_demo_work_3_0');

          do_action('pt_at_account_dash_top');

?>



	<div class="row row-no-margin">





     <div class="col-xs-12 col-sm-12 col-md-12">
		<!-- page content here -->



             <div class="card   nopadding mb-4">

               <input type="hidden" value="<?php echo projectTheme_get_avatar(get_current_user_id(),40,40) ?>" id="my-current-avatar" />
               <input type="hidden" value="<?php echo $current_user->user_login ?>" id="username-of-user" />

               <div id="frame">
               	<div id="sidepanel">
               		<div id="profile">
               			<div class="wrap">
               				<img id="profile-img" src="<?php echo projectTheme_get_avatar($uid,50,50) ?>" width="50" height="50" class="online" alt="" />
               				<p><?php echo $current_user->user_login ?></p>

               			</div>
               		</div>
               		<div id="search">
                    <form method="get">
               			    <label for=""><i class="fa fa-search" aria-hidden="true"></i></label>
               			      <input type="text" class="bar" autocomplete="off" placeholder="<?php _e('Search contacts...','ProjectTheme'); ?>" id='searchbar_search' value="<?php echo $_GET['search_contact']; ?>" name="search_contact" />
                          <input type="submit" class="btn" value="<?php echo __('Search','ProjectTheme') ?>" />
                    </form>
               		</div>
               		<div id="contacts">
               			<ul id='contacts-ul'>
                      <?php

                              $project_chat = new project_chat();
                              $all_threads  = $project_chat->get_all_thread_ids(get_current_user_id());

                              if(!empty($_GET['search_contact'])) $all_threads  = $project_chat->get_all_thread_ids_by_search(get_current_user_id());

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

                                      <li class="contact">
                                        <div class="wrap">
                                          <span class="contact-status <?php if(projecttheme_is_user_online($userToShow)) echo 'online'; ?>"></span>
                                          <img src="<?php echo projectTheme_get_avatar($userToShow,40,40) ?>" width=40 height=40 alt="" />
                                          <div class="meta">
                                            <p class="name"><a href="<?php echo projecttheme_get_pm_link_for_thid($thread->id); ?>"><?php echo $usrUsr->user_login;

                                                  $unread = projectTheme_unread_messages_by_thread($thread->id, get_current_user_id());
                                                  if($unread > 0) echo ' <span class="badge badge-primary" id="unread-thid-notification-'.$thread->id.'">'.$unread.'</span>';
                                             ?></a></p>
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
                                      </li>

                                      <?php
                                    }
                                  }
                              }


                       ?>


               			</ul>
               		</div>
               	<!--	<div id="bottom-bar">
               			<button id="addcontact"><i class="fa fa-user-plus fa-fw" aria-hidden="true"></i> <span>Add contact</span></button>
               			<button id="settings"><i class="fa fa-cog fa-fw" aria-hidden="true"></i> <span>Settings</span></button>
               		</div> -->
               	</div>
               	<div class="content">
                  <?php

                        if($current_thid > 0)
                        {
                              $cht = new project_chat();
                                  $thobj = $cht->get_single_thread_info($current_thid);




                                  if($thobj->user1 == get_current_user_id()) $show_this_usr = $thobj->user2;
                                  else $show_this_usr = $thobj->user1;

                                  $objs = get_userdata($show_this_usr);
                                  $to_user = $show_this_usr;

                                  //---------------

                   ?>
               		<div class="contact-profile">
               			<img src="<?php echo projectTheme_get_avatar($show_this_usr,40,40) ?>" width=40 height=40 alt="" />
               			<p><?php echo $objs->user_login  ?></p>
               			<div class="social-media" id="is_typing">
               		     is typing a message...
               			</div>
               		</div>
                <?php } ?>

               		<div class="messages" id='messages' >
                    <?php

                          if($current_thid > 0)
                          {

                                  $project_chat->set_thid($current_thid);
                                  $messages = $project_chat->get_all_messages_from_thread();

                                  if(is_array($messages))
                                  if(count($messages) > 0)
                                  {
                                     ?>

                                     <ul id="messages-box">
                                        <?php
                                              	if(is_array($messages))
                                              foreach($messages as $message)
                                              {
                                                      $last_id = $message->id;
                                                      if($message->initiator == get_current_user_id())
                                                      {
                                                        ?>

                                                        <li class="sent">
                                                          <img src="<?php echo projectTheme_get_avatar($message->initiator,30,30) ?>" width=30 height=30 alt="" />
                                                          <p><?php



                                                          $pattern = "/[^@\s]*@[^@\s]*\.[^@\s]*/";
                                                          $replacement = "[removed]";
                                                          $content = stripslashes($message->content);

                                                          $content = preg_replace($pattern, $replacement, $content);


                                                          echo  empty($content) ? "" : strip_tags($content). "<br/><br/>" ?>                                                      <?php

                                                                $file_attached = $message->file_attached;
                                                                if($file_attached > 0)
                                                                {
                                                                    $pppp = get_post($file_attached);
                                                                      echo "<a class='semaf' href='".wp_get_attachment_url($file_attached)."' target='_blank'><i class='fa fa-paperclip attachment'></i> ".$pppp->post_title."</a>";

                                                                }

                                                           ?></p>
                                                        </li>

                                                        <?php
                                                      }
                                                      else {

                                                                $wpdb->query("update ".$wpdb->prefix."project_pm set rd='1' where id='{$message->id}'");

                                                        ?>


                                                        <li class="replies">
                                                          <img src="<?php echo projectTheme_get_avatar($message->initiator,30,30) ?>" width=30 height=30 alt="" />
                                                          <p><?php

                                                          $pattern = "/[^@\s]*@[^@\s]*\.[^@\s]*/";
                                                          $replacement = "[removed]";
                                                          $content = stripslashes($message->content);

                                                          $content = preg_replace($pattern, $replacement, $content);


                                                          echo  empty($content) ? "" : strip_tags($content) . "<br/><br/>" ?>                                                    <?php

                                                                $file_attached = $message->file_attached;
                                                                if($file_attached > 0)
                                                                {
                                                                    $pppp = get_post($file_attached);
                                                                      echo " <a class='semaf' href='".wp_get_attachment_url($file_attached)."' target='_blank'><i class='fa fa-paperclip attachment'></i> ".$pppp->post_title."</a>";

                                                                }

                                                           ?></p>
                                                        </li>



                                                        <?php
                                                      }
                                        } ?>



                                     </ul>



                                     <?php
                                  }

                             ?>




                             <?php
                          }



                     ?>

                                          <input type="hidden" value="<?php echo $last_id ?>" id="last_id" />
                                          <input type="hidden" value="<?php echo $current_thid ?>" id="thid" />
                                          <input type="hidden" value="<?php echo get_current_user_id() ?>" id="otherpartyid" />

               		</div>
               		<div class="message-input">
               			<div class="wrap">

<input type="file" name="myfile" id="myfile" style="display:none" />

                      <input type="hidden" value="<?php echo  $current_thid ?>" id="current_thid" />
                      <input type="hidden" value="<?php echo  $to_user ?>" id="to_user" />
               			<input type="text" placeholder="Write your message..." class="text_message_box" id="text_message_box" />
               			<i class="fa fa-paperclip attachment" id='openfile' aria-hidden="true"></i>
               			<button class="submit" id="send_me_a_message"><i class="far fa-paper-plane"></i></button>
               			</div>


                    <div class="message-input-file">
                    </div>

               		</div>




               	</div>
               </div>



        </div>
        </div>
        </div>
        </div>
        </div>








  <?php

  $page = ob_get_contents();
 ob_end_clean();

 return $page;


}


 ?>
