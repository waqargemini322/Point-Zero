var is_at_bottom = 0;
var initialA = 0;

var Upload = function (file) {
    this.file = file;
};

Upload.prototype.getType = function() {
    return this.file.type;
};
Upload.prototype.getSize = function() {
    return this.file.size;
};
Upload.prototype.getName = function() {
    return this.file.name;
};
Upload.prototype.doUpload = function () {
    var that = this;
    var formData = new FormData();

    var chatbox_textarea      = jQuery("#text_message_box").val();
    var thid                  = jQuery("#current_thid").val();
    var to_user               = jQuery("#to_user").val();
    var no_file = 1;

    // add assoc key values, this will be posts values

    if(document.getElementById("myfile").files.length != 0 )
    {
      formData.append("file", this.file, this.getName());
      formData.append("upload_file", true);
      no_file = 2;

  } else no_file = 1;

        formData.append("chatbox_textarea", chatbox_textarea);
            formData.append("to_user", to_user);
                formData.append("thid", thid);



if(no_file == 1 && isEmpty(chatbox_textarea))
{

}
else {

  //console.log(jQuery("#last_id").val());
  jQuery("#last_id").val(parseInt(jQuery("#last_id").val()) + 1);
  //console.log(jQuery("#last_id").val());

  var avatarurl = jQuery("#my-current-avatar").val();
  var usernameofuser = jQuery("#username-of-user").val();


  var app = '<li class="sent"><img src="'+ avatarurl +'" width="30" height="30" alt=""> <p> ' + chatbox_textarea + '<br><br>              </p></li>';
  jQuery("#messages-box").append(app);




  jQuery("#text_message_box").val("");


    jQuery.ajax({
        type: "POST",
        url: SITE_URL  + "/?send_regular_chat_message=1",
        xhr: function () {
            var myXhr = jQuery.ajaxSettings.xhr();
            if (myXhr.upload) {
                myXhr.upload.addEventListener('progress', that.progressHandling, false);
            }
            return myXhr;
        },
        success: function (data) {
            // your callback here

              jQuery(".text_message_box").val("");
                jQuery("#myfile").val('');
                jQuery(".message-input-file").html(" ");

        },
        error: function (error) {
            // handle error
        },
        async: true,
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        timeout: 60000
    });

  }
};

Upload.prototype.progressHandling = function (event) {
    var percent = 0;
    var position = event.loaded || event.position;
    var total = event.total;
    var progress_bar_id = "#progress-wrp";
    if (event.lengthComputable) {
        percent = Math.ceil(position / total * 100);
    }
    // update progressbars classes so it fits your code
    jQuery(progress_bar_id + " .progress-bar").css("width", +percent + "%");
    jQuery(progress_bar_id + " .status").text(percent + "%");
};




function isEmpty(str) {
    return (!str || 0 === str.length);
}

//------------------

function updateScroll(){
  if(is_at_bottom == 1 || initialA == 0)
  {
    if(jQuery("#messages").length)
    {
      var element = document.getElementById("messages");
      element.scrollTop = element.scrollHeight;
      initialA = 1;
    }
  }


}


setInterval(updateScroll,1000);


//----------

function chat_regular_messages() {

  var thid = jQuery('#thid').val();
  var last_id = jQuery('#last_id').val();
  var text_message_box = jQuery('#text_message_box').val();
  var otherpartyid = jQuery('#otherpartyid').val();

  if(thid > 0)
  {

  jQuery.ajax({
    url: SITE_URL + "/?updatemessages_regular=1&thid=" + thid + "&last_id=" + last_id + "&otherpartymessage=" + text_message_box + "&otherpartyid=" + otherpartyid,
    success: function(data) {
      //console.log(data)

      var obj = JSON.parse(data);

      jQuery("#last_id").val(obj.last_id);
      jQuery("#messages-box").append(obj.content_messages);

      var other_user_is_typing = obj.other_user_is_typing;

      if(other_user_is_typing == "yes")
        jQuery("#is_typing").show();
      else
        jQuery("#is_typing").hide();

      if(last_id != obj.last_id)
      {
          if(is_at_bottom == 1)
          {
          jQuery('#messages-box').scrollTop(jQuery('#messages-box')[0].scrollHeight + 300);
          console.log( is_at_bottom );
        }

      // if(obj.last_user_id != CUR_UID)
      //  {  //audioElement.play();
		 //}
     }


    },
    complete: function() {
      // schedule the next request *only* when the current one is complete:
      setTimeout(chat_regular_messages, 1200);
    }
  });

}
}
//--------------------------------------------------------

function isScrolledToBottom(el) {
    var $el = $(el);
    return el.scrollHeight - $el.scrollTop() - $el.outerHeight() < 1;
}



jQuery(function() {


jQuery("#openfile").click(function()
{
  document.getElementById("myfile").click();
});


jQuery( "#messages" ).scroll(function() {

  var node = jQuery('#messages')[0]; // gets the html element
    if(node) {
      var isBottom = node.scrollTop + node.offsetHeight === node.scrollHeight;

      if(isBottom == true)
      {
          is_at_bottom = 1;
      }
      else
      {
        is_at_bottom = 0;

      }

    }

});


jQuery('#searchbar_search').on("keyup", function() {

    var valu = jQuery("#searchbar_search").val();

    jQuery.get( SITE_URL + "/?search_through_chats=1&get_chat_search=" + valu , function( data ) {


        jQuery('#contacts-ul').html(data);


    });

});



jQuery('#myfile').change(function(e){

    jQuery(".message-input-file").html(jQuery(this).val());
});

   //--------------------------------------------------



   //this is chat for messages in orders
   jQuery("#send_chat_button").click(function (){

          var chatbox_textarea = jQuery("#chatbox_textarea").val();
          var oid = jQuery("#oid").val();
          var currend_id = jQuery("#currend_id").val();
          var toid = jQuery("#toid").val();

          if(1) //!isEmpty(chatbox_textarea))
          {
            jQuery.post( SITE_URL  + "/?send_order_chat_message=1", { chatbox_textarea: chatbox_textarea, oid: oid, current_user_id: currend_id, toid: toid })
              .done(function( data ) {
                //alert( "Data Loaded: " + data );
                jQuery("#chatbox_textarea").val("");
              });
          }
          else {
            alert(MESSAGE_EMPTY_STRING);
          }


   });

   //-----------------------------------
   // this is regular chats

   jQuery("#send_me_a_message").click(function (){


        send_regular_chat_message_fn();
        jQuery("#chatbox_textarea").val("");

   });



   jQuery('#text_message_box').keypress(function (e) {
          if (e.which == 13) {
                send_regular_chat_message_fn();
                jQuery("#chatbox_textarea").val("");
          }
        });




   setTimeout(chat_regular_messages, 900);

});


function send_regular_chat_message_fn()
{


         var chatbox_textarea      = jQuery("#text_message_box").val();
         var thid                  = jQuery("#current_thid").val();
         var to_user = jQuery("#current_thid").val();



         if(1) //!isEmpty(chatbox_textarea))
         {
           /*jQuery.post( SITE_URL  + "/?send_regular_chat_message=1", { chatbox_textarea: chatbox_textarea, thid: thid , to_user: to_user  })
             .done(function( data ) {
               //alert( "Data Loaded: " + data );
               jQuery("#text_message_box").val("");
             });*/
             var file = jQuery("#myfile")[0].files[0];
             var upload = new Upload(file);
             upload.doUpload();



         }
         else {
           alert(MESSAGE_EMPTY_STRING);
         }
}


jQuery(document).ready(function()
{
  if(document.getElementById("messages") !== null)
  {

      jQuery('#messages').scrollTop(jQuery('#messages')[0].scrollHeight);
      console.log(jQuery('#messages')[0].scrollHeight);

    }

  });
