<?php


class project_chat
{
    private $threadid; // order id

    public function __construct($thid = '')
    {
        // Constructor's functionality here, if you have any.

        if($thid > 0)
        $this->set_thid($thid);

    }

      //***********************************************************
      //
      //FUNCTION HERE
      //
      //***********************************************************

      public function insert_message($from_user, $to_user, $content, $attached = '')
      {
        global $wpdb;

        $date_made = current_time('timestamp');
        $oid_1 = $this->threadid;

        $s = "insert into ".$wpdb->prefix."project_pm (threadid, initiator, user, content, datemade, file_attached) values('$oid_1','$from_user','$to_user','$content','$date_made','$attached')";
        $wpdb->query($s);

        $is_online = projecttheme_is_user_online($to_user);

        if(!$is_online)
        ProjectTheme_send_email_on_priv_mess_received($from_user, $to_user);


      }

      public function update_typing_of_user_time($user_number, $is_ok)
      {
        if($is_ok == 1)
        {
          global $wpdb;
          $tm = current_time('timestamp');
          $s = "update ".$wpdb->prefix."project_pm_threads set user".$user_number."_last_type='$tm' where id='{$this->threadid}'";
          $wpdb->query($s);
        }
      }


      public function get_thread_content( )
      {
          global $wpdb;
          $s = "select * from ".$wpdb->prefix."project_pm_threads where id='{$this->threadid}' ";
          $r = $wpdb->get_results($s);

          if(count($r) > 0)
          return $r[0];


      }


    //***********************************************************
    //
    //FUNCTION HERE
    //
    //***********************************************************

    public function get_thread_id($user1, $user2)
    {
        global $wpdb;
        $s = "select id from ".$wpdb->prefix."project_pm_threads where (user1='$user1' and user2='$user2') or (user1='$user2' and user2='$user1') ";
        $r = $wpdb->get_results($s);



        if(count($r) > 0) return $r[0]->id;
        $tm = current_time('timestamp');

        $s = "insert into ".$wpdb->prefix."project_pm_threads (user1, user2, lastupdate, datemade) values('$user1','$user2','$tm','$tm') " ;
        $wpdb->query($s);

        //--------

        $s = "select id from ".$wpdb->prefix."project_pm_threads where (user1='$user1' and user2='$user2') or (user1='$user2' and user2='$user1') ";
        $r = $wpdb->get_results($s);

        return $r[0]->id;
    }

    public function get_threadid($user1, $user2)
    {
        global $wpdb;
        $s = "select id from ".$wpdb->prefix."project_pm_threads where (user1='$user1' and user2='$user2') or (user1='$user2' and user2='$user1') ";
        $r = $wpdb->get_results($s);

        if(count($r) > 0) return $r[0]->id;
        $tm = current_time('timestamp');

        $s = "insert into ".$wpdb->prefix."project_pm_threads (user1, user2, lastupdate, datemade) values('$user1','$user2','$tm','$tm') " ;
        $wpdb->query($s);

        //--------

        $s = "select id from ".$wpdb->prefix."project_pm_threads where (user1='$user1' and user2='$user2') or (user1='$user2' and user2='$user1') ";
        $r = $wpdb->get_results($s);

        return $r[0]->id;
    }

    //***********************************************************
    //
    //FUNCTION HERE
    //
    //***********************************************************


    public function get_all_thread_ids($uid)
    {
      global $wpdb;
      $s = "select * from ".$wpdb->prefix."project_pm_threads where (user1='$uid' or user2='$uid') order by lastupdate desc ";
      $r = $wpdb->get_results($s);



      if(count($r) > 0) return $r;
      return false;

    }


    public function get_all_thread_ids_by_search($uid, $valu = '')
    {
      global $wpdb; $user_term = $_GET['search_contact'];
      if(!empty($valu)) $user_term = $valu;

      $s = "select * from ".$wpdb->prefix."project_pm_threads threads, ".$wpdb->prefix."users users where (threads.user1='$uid' or threads.user2='$uid') and
      ((users.ID=threads.user1 and users.user_login like '%$user_term%') or (users.ID=threads.user2 and users.user_login like '%$user_term%')) order by threads.lastupdate desc ";
      $r = $wpdb->get_results($s);




      if(count($r) > 0) return $r;
      return false;

    }


    //***********************************************************
    //
    //FUNCTION HERE
    //
    //***********************************************************


    public function get_last_message_of_thread($thid)
    {
      global $wpdb;
      $s = "select * from ".$wpdb->prefix."project_pm where threadid='$thid' order by datemade desc limit 1 ";
      $r = $wpdb->get_results($s);

      if(count($r) > 0) return $r[0];
      return false;

    }


        //***********************************************************
        //
        //FUNCTION HERE
        //
        //***********************************************************



    public function get_all_messages_from_thread()
    {
      global $wpdb;
      $s = "select * from ".$wpdb->prefix."project_pm where threadid='{$this->threadid}' order by datemade asc ";
      $r = $wpdb->get_results($s);

      if(count($r) > 0) return $r;
      return false;

    }


        public function get_messages_from_order_higher_than_id($asc, $higher)
        {
            global $wpdb; if(empty($higher)) $higher = 0;

            if($asc == 1) $fgc = 'asc';
            else $fgc = 'desc';

            $s = "select * from ".$wpdb->prefix."project_pm where id>'$higher' and threadid='".$this->threadid."' order by id " . $fgc;
            $r = $wpdb->get_results($s);



            return $r;
        }


    //***********************************************************
    //
    //FUNCTION HERE
    //
    //***********************************************************

    public function get_single_thread_info($thid)
    {
      global $wpdb;
      $s = "select * from ".$wpdb->prefix."project_pm_threads where id='$thid'";
      $r = $wpdb->get_results($s);

      if(count($r) > 0) return $r[0];
      return false;

    }

    //***********************************************************
    //
    //FUNCTION HERE
    //
    //***********************************************************


    public function set_thid($thid)
    {
        $this->threadid = $thid;
    }


}



 ?>
