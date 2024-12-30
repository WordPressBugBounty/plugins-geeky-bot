<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

    $botImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getBotImagePath();
    $userImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getUserImagePath();
    $geekybot_js = '';
    if(isset($_COOKIE['geekybot_chat_id'])){
        $geekybot_js .= 'jQuery(".chat-popup").addClass("active");';
        if (geekybot::$_configuration['welcome_screen'] == '2') {
            $geekybot_js.='
            jQuery(".chat-window-one").hide();
            jQuery(".chat-popup").addClass("chat-init");
            jQuery("#main-messages").show();
            jQuery(".chat-button-destroy").addClass("active");
            ';
        } else {
            $geekybot_js.='
            jQuery(".chat-button-destroy").addClass("active");
            ';
        }
        $geekybot_js.='
        var scrollableDiv = jQuery("#main-messages");
        scrollableDiv.scrollTop(scrollableDiv[0].scrollHeight);
        ';
    }
    $geekybot_js .= '
    function geekybot_DecodeHTML(html) {
        var txt = document.createElement("textarea");
        txt.innerHTML = html;
        return txt.value;
    }
    function geekybot_scrollToTop(difference) {
        var scrollheight = jQuery("#main-messages").get(0).scrollHeight;
        var scrollPosition = jQuery(".chat-window-two").get(0).scrollHeight;
        if(scrollheight > 600) {
            jQuery(".chat-window-two").animate({scrollTop: scrollPosition - difference},350);
        }
    }
    jQuery(function() {
        jQuery(".chat-open-dialog").click(function() {
            jQuery(".chat-open-outer-popup-dialog").hide();
            jQuery(this).toggleClass("active");
            jQuery(".chat-popup").toggleClass("active");
            jQuery(".chat-button-destroy").toggleClass("active");
            jQuery(".chat-popup").toggleClass("");
            if (jQuery(".chat-popup").hasClass("active")) {
                getRandomChatId();';
                if (geekybot::$_configuration['welcome_screen'] == '2') {
                    $geekybot_js.='
                    jQuery(".chat-window-one").hide();
                    jQuery(".chat-popup").addClass("chat-init");
                    jQuery("#main-messages").show();
                    ';
                }
                $geekybot_js.='
            }
        });
    });

    jQuery(function() {
        jQuery(".chat-button-destroy").click(function() {
            jQuery(".chat-popup").removeClass("active");
            jQuery(".chat-open-dialog").removeClass("active");
            jQuery(this).removeClass("active");
        });
    });
    /* When the user clicks on the button,
    toggle between hiding and showing the dropdown content */
    function myFunction() {
        document.getElementById("myDropdown").classList.toggle("show");
    }
    ( function( jQuery ) {
        jQuery("#startchat").on("click",function(e){
            e.preventDefault();
            jQuery(".chat-popup").toggleClass("chat-init");
            jQuery("#main-messages").show();
            jQuery(".chat-window-one").hide();
        });
    } )( jQuery );

    jQuery("#snd-btn").click(function(event){
        var message = jQuery(".msg_box").val();
        if (!message) {
            alert("Please enter a message to before sending");
            return false;
        } else {
            var sender = "user";
            jQuery(".msg_box").val("");';
            $geekybot_js .= " 
            var sender = 'user';
            var btnflag = 'false';
            var chat_id = jQuery('#chatsession').val();
            jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_user'><section class='actual_msg_user-img'><img src='".esc_url($userImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
            var response_id =  jQuery('#response_id').val();
            // SaveChathistory(message,sender);
            sendRequestToServer(message,message,sender,chat_id);
        }
    });";
    $geekybot_js.=' 
    jQuery(".msg_box").keypress(function(event){
        if ( event.which == 13 ) {
            var message = jQuery(".msg_box").val();
            if (!message) {
                alert("Please enter a message to before sending");
                return false;
            } else {
                var sender = "user";
                jQuery(".msg_box").val("");';
                $geekybot_js .=" 
                var sender = 'user';
                var chat_id = jQuery('#chatsession').val();
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_user'><section class='actual_msg_user-img'><img src='".esc_url($userImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    var response_id =  jQuery('#response_id').val();
                    var btnflag = 'false';
                    // SaveChathistory(message,sender);
                    sendRequestToServer(message,message,sender,chat_id);
            }
        }
    });";
    $geekybot_js.='
    function getRandomChatId() {
        var x = new Date();

        var hours=x.getHours().toString();
        hours=hours.length==1 ? 0+hours : hours;

        var minutes=x.getMinutes().toString();
        minutes=minutes.length==1 ? 0+minutes : minutes;

        var seconds=x.getSeconds().toString();
        seconds=seconds.length==1 ? 0+seconds : seconds;

        var month=(x.getMonth() +1).toString();
        month=month.length==1 ? 0+month : month;

        var dt=x.getDate().toString();
        dt=dt.length==1 ? 0+dt : dt;

        var x1=  x.getFullYear() + "-" + month + "-" + dt;
        x1 = x1 + "  " +  hours + ":" +  minutes + ":" +  seconds ;
        var dt = x1;
        var user = "user";';
        $geekybot_js .= "
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'getRandomChatId', datetime: dt, '_wpnonce':'". esc_attr(wp_create_nonce("get-random-chat-id"))."' }, function (data) {";
                $geekybot_js .= ' 
                if (data) {
                    var chat_id = data;
                    jQuery("#chatsession").val(data);
                    // closechat(); recheck
                    // it close the chat after some time even if the user is typing message - hamza
                }
            });
        }
        function startDictation() {
            var sender = "user";
            var chat_id = jQuery("#chatsession").val();
            $i = jQuery(".fa-microphone");
            $i.removeClass("fa-microphone").addClass("fa-circle");
            $i.css({"color":"red",});
            console.log($i);
            if (window.hasOwnProperty("webkitSpeechRecognition")) {
                var recognition = new webkitSpeechRecognition();
                recognition.continuous = false;
                recognition.interimResults = false;
                recognition.lang = "en-US";
                recognition.start();
                recognition.onresult = function(e) {
                    jQuery(".msg_box").val("");
                    var message = e.results[0][0].transcript';
                    $geekybot_js .="
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_user'><section class='actual_msg_user-img'><img src='".esc_url($userImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    var response_id =  jQuery('#response_id').val();
                    var btnflag = 'false';
                    sendRequestToServer(message,message,sender,chat_id);
                    recognition.stop();
                };";
                $geekybot_js.='
                recognition.onerror = function(e) {
                    console.log(e);
                    recognition.stop();
                }
            }

            setTimeout(function() {
                $i.removeClass("fa-circle").addClass("fa-microphone");
                $i.css("color", "#bdbfc1");
            }, 2000);

        }
        function SaveChathistory(message,sender) { ';
            $geekybot_js.="
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
            var response_id =  jQuery('#response_id').val();
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'SaveChathistory', cmessage: message,csender:sender, '_wpnonce':'".esc_attr(wp_create_nonce("save-chat-history"))."' }, function (data) {
                    if (data) {
                        if(sender=='user') {
                            jQuery('#response_id').val(data);
                        }
                    }
                }
            );
        }";
        $server_url = "";
        $geekybot_js.='function sendRequestToServer(message,text,sender,chat_id){

        jQuery.ajax({ ';
            if(geekybot::$_configuration['ai_search'] == 0){
                $geekybot_js .= 'url: "'.esc_url(admin_url('admin-ajax.php')).'",type: "POST",async: true,
                data:( {"action": "geekybot_frontendajax", "geekybotme": "chatserver", "task": "getMessageResponse", "message": message,cmessage: message,ctext: text,csender:sender, "_wpnonce":"'.esc_attr(wp_create_nonce('get-message-response')).'"}),
                ';
            } else {
                //link removed form here
                //http://216.128.138.145:8039
                //https://bulkoff.com/test_bot8 
                
                $geekybot_js .= '
                url: "http://216.128.138.145:8042/webhooks/rest/webhook",type: "POST",async: true,data:JSON.stringify( { "message": message,"senser" : "adnan",}),
                headers: {';
                    $geekybot_js.="  
                    'Content-Type':'application/json',
                    'accept': 'application/json',
                    'Access-Control-Allow-Origin':'*' ";
                    $geekybot_js.='    
                },';
            }
            $geekybot_js .='
            }).done(function(data) {
                geekybot_scrollToTop(150);
                jQuery("#typing_message").remove();';
                if(geekybot::$_configuration['ai_search'] == 0){
                    $geekybot_js.='
                    var data = JSON.parse(data);';
                } else {
                    $geekybot_js.=' ';
                }
                $geekybot_js .="
                if (data && Array.isArray(data) && data.length > 0) {
                    jQuery.each(data, function( index, value ) {
                        if (value.text) {
                            var sender = 'bot'; ";
                            if(geekybot::$_configuration['ai_search'] == 0){
                                $geekybot_js .= "
                                var message = geekybot_DecodeHTML(value.text.bot_response);
                                if (typeof value.text.bot_articles !== 'undefined') {
                                    message += geekybot_DecodeHTML(value.text.bot_articles);
                                }";
                            }else{
                                $geekybot_js .= " var message = geekybot_DecodeHTML(value.text);";
                            }
                            $geekybot_js .= "
                            var btn   = value.buttons;
                            var btnhtml ='';
                            var text = value.text;
                            var btnflag = 'false';
                            var response_id =  jQuery('#response_id').val();
                            // error with woocommerce code
                            //message = message.replace( /((http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?)/g,'<a href=\"$1\">$1</a>');
                            jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section>\");
                            
                            // SaveChathistory(message,sender);
                            if(btn) {
                                btnhtml += \"<div class='actual_msg_btn'>\";
                                jQuery.each(btn, function(i,btns){
                                    var btntext = btns.text;
                                    var btnvalue = btns.value;
                                    var btntype = btns.type;
                                    var btnflag = 'true';
                                    if(btntype == 1) {
                                        btnhtml+=  \"<li class='actual_msg actual_msg_btn' style=''><section><button class='wp-chat-btn' onclick='sendbtnrsponse(this);' value='\"+btnvalue+\"'><span>\"+btntext+\"</span></section></button></li>\";
                                    } else if(btntype == 2) {
                                        btnhtml+=  \"<li class='actual_msg actual_msg_btn' style=''><section><button class='wp-chat-btn'><span><a class='wp-chat-btn-link' href='\"+btnvalue+\"'>\"+btntext+\"</a></span></section></button></li>\";
                                    }
                                });
                                btnhtml += \"</div>\"; 
                                jQuery(\"#chatbox\").append(btnhtml);
                            }
                            jQuery(\"#chatbox\").append(\"</li>\");
                        } else if (value.image) {
                            var sender = 'bot';
                            var message = value.image;
                            var btnflag = 'true';
                            // SaveChathistory(message,sender);

                            jQuery(\"#chatbox\").append(\"<li class='actual_msg_img'><img src=\"+value.image+\" alt='Girl in a jacket' width='250' height='150'></li>\");
                        } else if (value.action) {
                            var sender = 'bot';
                            var message = geekybot_DecodeHTML(value.action.text);
                            var btn   = value.buttons;
                            var btnhtml ='';
                            var btnflag = 'false';
                            var response_id =  jQuery('#response_id').val();
                            jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+ message+\"</section></li>\");
                            // SaveChathistory(message,sender);
                                if(btn) {
                                    btnhtml += \"<div class='actual_msg_btn'>\";
                                    jQuery.each(btn, function(i,btns){
                                    var btnmsg = btns.title;
                                    var btnflag = 'true';
                                    // SaveChathistory(btnmsg,sender);
                                    btnhtml+=  \"<li class='actual_msg actual_msg_btn' style=''><section><button class='wp-chat-btn' onclick='sendbtnrsponse(this);' value='\"+btns.title+\"'><span>\"+btns.title+\"</span></section></button></li>\";
                                });
                                btnhtml += \"</div>\"; 
                                jQuery(\"#chatbox\").append(btnhtml);
                            }
                        }
                    });
                } else {
                    var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
                    jQuery.post(ajaxurl, {
                        action: 'geekybot_frontendajax',
                        geekybotme: 'chatserver',
                        task: 'getDefaultFallBackFormAjax',
                        chat_id: chat_id,
                        '_wpnonce':'". esc_attr(wp_create_nonce('get-fallback')) ."'
                    }, function(fbdata) {
                        if (fbdata) {
                            console.log(fbdata);
                            jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+fbdata+\"</section></li>\");
                        } else {
                            console.error('AJAX Error:', textStatus, errorThrown);
                        }
                    });
                }
            }).fail(function(data, textStatus, xhr) {
                var configmsg = '".esc_attr(geekybot::$_configuration['default_message'])."';

                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+configmsg+\"</section></li>\");
            });
        }
        jQuery(document).ready(function(){
            jQuery(\"div#jsendchat\").on('click',function(){
                var sender = 'user';
                var chat_id = jQuery('#chatsession').val();
                var message = 'Chat End by user';
                var date = new Date();
                date.setTime(date.getTime());
                var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
                jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'endUserChat', cmessage: message,sender:sender ,chat_id:chat_id, '_wpnonce':'". esc_attr(wp_create_nonce("end-user-chat")) ."'}, function (data) {";
                $geekybot_js.='
                if (data) {
                    jQuery("#chatbox").empty();
                    var path = window.location.href;
                    jQuery(".chat-popup").toggleClass("chat-init");';
                    if (geekybot::$_configuration['welcome_screen'] == '1') {
                        $geekybot_js.='jQuery("#main-messages").hide();
                        jQuery(".chat-window-one").show();';
                    } else {
                        $geekybot_js.='jQuery(".chat-popup").toggleClass("chat-init");
                        jQuery("#main-messages").show();
                        jQuery(".chat-window-one").hide();';
                    }
                    $geekybot_js.='
                    jQuery(".chat-open-dialog").removeClass("active");
                    jQuery(".chat-button-destroy").removeClass("active");
                    jQuery(".chat-popup").removeClass("active");
                    jQuery(".dropdown-content").removeClass("show");
                    // set empty value for session on end chat
                    jQuery("#chatsession").val("");
                } else {
                }
            });
        });
    });';
    $geekybot_js .= "
    function geekybotLoadMoreCustomPosts(msg, data_array, next_page, function_name) {
        var message = '".esc_html(__('Show More', 'geeky-bot'))."';
        SaveChathistory(message,'user');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'geekybot', task: 'geekybotLoadMoreCustomPosts', msg : msg, dataArray : data_array, next_page: next_page, functionName : function_name, '_wpnonce':'".esc_attr(wp_create_nonce("load-more")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(190);
                var message = geekybot_DecodeHTML(data);
                jQuery(\"div.geekybot_wc_product_load_more_wrp\").css(\"display\", \"none\");
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                
            }
        });
    }

    function showArticlesList(post_ids, msg, type, label, total_posts, current_page) {
        var message = '".esc_html(__('Show Articles', 'geeky-bot'))."';
        SaveChathistory(message,'user');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'websearch', task: 'showArticlesList', post_ids: post_ids, msg: msg, type: type, label: label, totalPosts: total_posts, currentPage: current_page, '_wpnonce':'".esc_attr(wp_create_nonce("articles-list")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(100);
                var message = geekybot_DecodeHTML(data);
                jQuery(\".geekybot_wc_post_load_more\").css(\"display\", \"none\");
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                
            }
        });
    }
    jQuery(document).ready(function(){
        jQuery(\"div#restartchat\").on('click',function(){
            var sender = 'user';
            var chat_id = jQuery('#chatsession').val();
            var message = 'Chat Restarted';
            var x = new Date();
            var hours=x.getHours().toString();
            hours=hours.length==1 ? 0+hours : hours;
            var minutes=x.getMinutes().toString();
            minutes=minutes.length==1 ? 0+minutes : minutes;
            var seconds=x.getSeconds().toString();
            seconds=seconds.length==1 ? 0+seconds : seconds;
            var month=(x.getMonth() +1).toString();
            month=month.length==1 ? 0+month : month;
            var dt=x.getDate().toString();
            dt=dt.length==1 ? 0+dt : dt; ";
            $geekybot_js .='
            var x1=  x.getFullYear() + "-" + month + "-" + dt;
            x1 = x1 + "  " +  hours + ":" +  minutes + ":" +  seconds ;
            var dt = x1; ';
            $geekybot_js.="
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'restartUserChat', datetime:dt, '_wpnonce':'". esc_attr(wp_create_nonce("restart-user-chat")). "'}, function (data) {";
                $geekybot_js.=' 
                if (data) {
                    jQuery("#chatsession").val(data)
                    jQuery("#chatbox").empty();
                    jQuery(".dropdown-content").removeClass("show");
                }else{

                }
            });
        });
    });

    function closechat(){
        var chat_id = jQuery("#chatsession").val();
        if(chat_id!=""){
            setTimeout(function(){';
                $geekybot_js.="
                var message = \"".__('session time out', 'geeky-bot')."\";
                var sender  = 'user';
                var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
                jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'endUserChat', cmessage: message,sender:sender ,chat_id:chat_id, '_wpnonce':'". esc_attr(wp_create_nonce("end-user-chat")) ."'}, function (data) {";
                    $geekybot_js.='
                    if (data) {
                        jQuery("#chatbox").empty();
                        var path = window.location.href;
                        jQuery(".chat-popup").toggleClass("chat-init");
                        jQuery("#main-messages").hide();';
                        if (geekybot::$_configuration['welcome_screen'] == '2') {
                            $geekybot_js.='
                            jQuery(".chat-window-one").hide();';
                        }
                        $geekybot_js.='
                        jQuery(".chat-open-dialog").removeClass("active");
                        jQuery(".chat-button-destroy").removeClass("active");
                        jQuery(".chat-popup").removeClass("active");
                        jQuery(".dropdown-content").removeClass("show");
                    }else{

                    }
                });
            }, 500000);
        }
    };

    function sendbtnrsponse(msg) {
        var sender = "user";
        var message = msg.value;
        var text = jQuery(msg).find("span").text();
        var chat_id = jQuery("#chatsession").val();
        ';
        $geekybot_js.="
        jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_user'><section class='actual_msg_user-img'><img src='".esc_url($userImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+text+\"</section></li>\");
        var ajaxurl =
            '". esc_url(admin_url("admin-ajax.php")) ."';
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'slots',
            message: message,
            task: 'saveVariableFromButtonIntent',
            '_wpnonce':'". esc_attr(wp_create_nonce("button-intent")). "'
        }, function(data) {
            if (data) {
                sendRequestToServer(data,text,sender,chat_id);
            }
        });
    }

    function geekybotHideSmartPopup(msg) {
        jQuery('.chat-open-outer-popup-dialog').fadeOut();
    }

    function geekybotChatOpenDialog() {
        jQuery('.chat-open-dialog').click();
    }

    // Code to open the chat popup
    document.addEventListener('DOMContentLoaded', function() {";
        if ( geekybot::$_configuration['auto_chat_start'] == 1 && geekybot::$_configuration['auto_chat_start_time'] != '' ) {
            $startTime = geekybot::$_configuration['auto_chat_start_time'];
            // change time from seconds to miliseconds
            $startTime = $startTime * 1000;
            $geekybot_js.="
            setTimeout(function() {
                // Code to open the chat popup if not already opened
                if (!jQuery('.chat-popup').hasClass('active')) { ";
                    if(!isset($_COOKIE['geekybot_chat_id'])){
                        if ( geekybot::$_configuration['auto_chat_type'] == 1  ) {
                            $geekybot_js.="
                            jQuery('.chat-open-outer-popup-dialog').fadeIn().css('display', 'flex');
                                ";
                        } else {
                            $geekybot_js.="    
                            jQuery('.chat-open-dialog').click();";
                        }
                    }
                    $geekybot_js.="
                }
            }, ".$startTime.");";
        }
        $geekybot_js.="
    });";
    wp_register_script( 'geekybot-frontend-handle', '' , array(), GEEKYBOT_PLUGIN_VERSION, 'all');
    wp_enqueue_script( 'geekybot-frontend-handle' );
    wp_add_inline_script('geekybot-frontend-handle',$geekybot_js);

?>



