<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

    $botImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getBotImagePath();
    $userImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getUserImagePath();
    $geekybot_js = '
    jQuery(document).ready(function(){
        var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
        jQuery.post(ajaxurl, {
            action: "geekybot_ajax",
            geekybotme: "geekybot",
            task: "geekybotFreshMessages",
            "_wpnonce": "' . esc_attr(wp_create_nonce("geekybot_fresh_messages")) . '"
        }, function(response) {
            if (response) {
                jQuery(".geekbotMessageWrapper").html(response);
            } else {
                jQuery(".geekbotMessageWrapper").html("");
            }
        });
        var cookielist = document.cookie.split(";");
        for (var i=0; i<cookielist.length; i++) {
            if (cookielist[i].trim() == "geekybot_collapse_chat_popup=1") {
                jQuery(".geekybot-chat-popup").addClass("active");
                jQuery(".geekybot-chat-open-dialog").addClass("active");
                jQuery(".geekybot-chat-close-button").addClass("active");
                break;
            }
        }';
        
        if(!isset($_COOKIE['geekybot_chat_id'])) {
            $geekybot_js .= '
            if (jQuery(".geekybot-chat-popup").hasClass("active")) {
                enforceRestartUserChat();
            }';
        } else {
            // $geekybot_js .= 'jQuery(".geekybot-chat-popup").addClass("active");';
            if (geekybot::$_configuration['welcome_screen'] == '2') {
                $geekybot_js.='
                jQuery(".geekybot-chat-popup").addClass("geekybot-chat-init");
                
                ';
            }
            $geekybot_js.='
            var scrollableDiv = jQuery("#geekybot-main-messages");
            scrollableDiv.scrollTop(scrollableDiv[0].scrollHeight);
            ';
        }
        $geekybot_js .= '
    
        jQuery(".geekybot-chat-open-dialog").click(function() {
            jQuery(".geekybot-chat-open-outer-popup-mainwrp").hide();
            jQuery(this).toggleClass("active");
            jQuery(".geekybot-chat-popup").toggleClass("active");
            jQuery(".geekybot-chat-close-button").toggleClass("active");
            document.cookie = "geekybot_collapse_chat_popup=1; expires=Sat, 01 Jan 2050 00:00:00 UTC; path=/";
            if (jQuery(".geekybot-chat-popup").hasClass("active")) {
                getRandomChatId();';
                if (geekybot::$_configuration['welcome_screen'] == '2') {
                    $geekybot_js.='
                    jQuery(".geekybot-chat-popup").addClass("geekybot-chat-init");
                    ';
                }
                $geekybot_js.='
            }
        });

        jQuery(".geekybot-chat-close-button").click(function() {
            if (jQuery(".geekybot-chat-popup").hasClass("active")) {
                document.cookie = "geekybot_collapse_chat_popup=0; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";
            }
            jQuery(".geekybot-chat-popup").removeClass("active");
            jQuery(".geekybot-chat-open-dialog").removeClass("active");
            jQuery(this).removeClass("active");
        });
    
        jQuery("#startchat").on("click",function(e){
            e.preventDefault();
            jQuery(".geekybot-chat-popup").toggleClass("geekybot-chat-init");
        });

        jQuery("#geekybot-send-button").click(function(event){
            var message = jQuery(".geekybot-message-box").val();
            if (!message) {
                alert("Please enter a message to before sending");
                return false;
            } else {
                var sender = "user";
                jQuery(".geekybot-message-box").val("");
                var sender = "user";
                var btnflag = "false";
                var chat_id = jQuery("#chatsession").val();
                jQuery(\'#geekybotChatBox\').append(\'<li class="geekybot-message geekybot-message-user"><section class="geekybot-message-user-img 01"><img src="'.esc_url($userImgScr).'" alt="" /></section><section class="geekybot-message-text">\'+message+\'</section></li>\');
                var response_id =  jQuery("#response_id").val();
                // SaveChathistory(message,sender);
                sendRequestToServer(message,message,sender,chat_id);
            }
        });

        jQuery(".geekybot-message-box").keypress(function(event){
            if ( event.which == 13 ) {
                var message = jQuery(".geekybot-message-box").val();
                if (!message) {
                    alert("Please enter a message to before sending");
                    return false;
                } else {
                    var sender = "user";
                    jQuery(".geekybot-message-box").val("");
                    var sender = "user";
                    var chat_id = jQuery("#chatsession").val();
                    jQuery(\'#geekybotChatBox\').append(\'<li class="geekybot-message geekybot-message-user"><section class="geekybot-message-user-img 02"><img src="'.esc_url($userImgScr).'" alt="" /></section><section class="geekybot-message-text">\'+message+\'</section></li>\');
                        var response_id =  jQuery("#response_id").val();
                        var btnflag = "false";
                        // SaveChathistory(message,sender);
                        sendRequestToServer(message,message,sender,chat_id);
                }
            }
        });

        jQuery(\'div#geekybotEndChat\').on("click",function(){
            var sender = "user";
            var chat_id = jQuery("#chatsession").val();
            var message = "Chat End by user";
            var date = new Date();
            date.setTime(date.getTime());
            var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
            jQuery.post(ajaxurl, { action: "geekybot_frontendajax", geekybotme: "chathistory", task: "endUserChat", cmessage: message,sender:sender ,chat_id:chat_id, "_wpnonce":"'. esc_attr(wp_create_nonce("end-user-chat")) .'"}, function (data) {
                if (data) {
                    jQuery("#geekybotChatBox").empty();
                    var path = window.location.href;
                    jQuery(".geekybot-chat-popup").toggleClass("geekybot-chat-init");';
                    if (geekybot::$_configuration['welcome_screen'] == '2') {
                        $geekybot_js.='jQuery(".geekybot-chat-popup").toggleClass("geekybot-chat-init");';
                    }
                    $geekybot_js.='
                    jQuery(".geekybot-chat-open-dialog").removeClass("active");
                    jQuery(".geekybot-chat-close-button").removeClass("active");
                    document.cookie = "geekybot_collapse_chat_popup=0; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";
                    jQuery(".geekybot-chat-popup").removeClass("active");
                    jQuery(".geekybot-dropdown-content").removeClass("show");
                    // set empty value for session on end chat
                    jQuery("#chatsession").val("");
                } else {
                }
            });
        });

        jQuery(\'div#geekybotRestartChat\').on("click",function(){
            var sender = "user";
            var chat_id = jQuery("#chatsession").val();
            var message = "Chat Restarted";
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
            dt=dt.length==1 ? 0+dt : dt; ';
            $geekybot_js .='
            var x1=  x.getFullYear() + "-" + month + "-" + dt;
            x1 = x1 + "  " +  hours + ":" +  minutes + ":" +  seconds ;
            var dt = x1; ';
            $geekybot_js.='
            var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
            jQuery.post(ajaxurl, { action: "geekybot_frontendajax", geekybotme: "chathistory", task: "restartUserChat", datetime:dt, "_wpnonce":"'. esc_attr(wp_create_nonce("restart-user-chat")). '"}, function (data) {
                if (data) {
                    jQuery("#chatsession").val(data)
                    jQuery("#geekybotChatBox").empty();
                    jQuery(".geekybot-dropdown-content").removeClass("show");
                }else{

                }
            });
        });
    });
    // Code to open the chat popup
    document.addEventListener("DOMContentLoaded", function() {';
        if ( geekybot::$_configuration['auto_chat_start'] == 1 && geekybot::$_configuration['auto_chat_start_time'] != '' ) {
            $startTime = geekybot::$_configuration['auto_chat_start_time'];
            // change time from seconds to miliseconds
            $startTime = $startTime * 1000;
            $geekybot_js.='
            setTimeout(function() {
                // Code to open the chat popup if not already opened
                if (!jQuery(".geekybot-chat-popup").hasClass("active")) { ';
                    if(!isset($_COOKIE['geekybot_chat_id'])){
                        if ( geekybot::$_configuration['auto_chat_type'] == 1  ) {
                            $geekybot_js.='
                            // 
                            var hide_smart_popup = 0;
                            var cookielist = document.cookie.split(";");
                            for (var i=0; i<cookielist.length; i++) {
                                if (cookielist[i].trim() == "geekybot_hide_smart_popup=1") {
                                    hide_smart_popup = 1;
                                }
                            }
                            // 
                            if(hide_smart_popup == 0) {
                                jQuery(".geekybot-chat-open-outer-popup-mainwrp").fadeIn().css("display", "flex");
                            }
                            ';
                        } else {
                            $geekybot_js.='    
                            jQuery(".geekybot-chat-open-dialog").click();';
                        }
                    }
                    $geekybot_js.='
                }
            }, '.$startTime.');';
        }
        $geekybot_js.='
    });

    function enforceRestartUserChat(callback) {
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

        var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
        jQuery.post(ajaxurl, { action: "geekybot_frontendajax", geekybotme: "chathistory", task: "restartUserChat", datetime:dt, "_wpnonce":"'. esc_attr(wp_create_nonce("restart-user-chat")). '"}, function (data) {
            if (data) {
                jQuery("#chatsession").val(data);
                // Wait a moment to ensure cookie is set and browser acknowledges it
                setTimeout(function () {
                    if (typeof callback === "function") callback();
                }, 100); // 100ms delay
            }
        });
    }

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
        $geekybot_js .= '
        var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
        jQuery.post(ajaxurl, { action: "geekybot_frontendajax", geekybotme: "chathistory", task: "getRandomChatId", datetime: dt, "_wpnonce":"'. esc_attr(wp_create_nonce("get-random-chat-id")).'" }, function (data) {
            if (data) {
                var chat_id = data;
                jQuery("#chatsession").val(data);
                // closechat(); recheck
                // it close the chat after some time even if the user is typing message - hamza
            }
        });
    }

    /* When the user clicks on the button,
    toggle between hiding and showing the dropdown content */
    function geekybotMyFunction() {
        document.getElementById("geekybotMyDropdown").classList.toggle("show");
    }

    function geekybot_DecodeHTML(html) {
        var txt = document.createElement("textarea");
        txt.innerHTML = html;
        return txt.value;
    }

    function geekybot_scrollToTop(difference) {
        var scrollheight = jQuery("#geekybot-main-messages").get(0).scrollHeight;
        var scrollPosition = jQuery(".geekybot-chat-window").get(0).scrollHeight;
        if(scrollheight > 600) {
            jQuery(".geekybot-chat-window").animate({scrollTop: scrollPosition - difference},1000);
        }
    }

    function geekybotLoadMoreCustomPosts(msg, data_array, next_page, function_name) {
        var message = "'.esc_html(__('Show More', 'geeky-bot')).'";
        SaveChathistory(message,"user");
        var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
        jQuery.post(ajaxurl, { action: "geekybot_frontendajax", geekybotme: "geekybot", task: "geekybotLoadMoreCustomPosts", msg : msg, dataArray : data_array, next_page: next_page, functionName : function_name, "_wpnonce":"'.esc_attr(wp_create_nonce("load-more")) .'"}, function (data) {
            if (data) {
                geekybot_scrollToTop(190);
                var message = geekybot_DecodeHTML(data);
                jQuery(\'div.geekybot_wc_product_load_more_wrp\').css(\'display\', \'none\');
                jQuery(\'#geekybotChatBox\').append(\'<li class="geekybot-message geekybot-message-bot"><section class="geekybot-message-bot-img 07"><img src="'.esc_url($botImgScr).'" alt="" /></section><section class="geekybot-message-text">\'+message+\'</section></li>\');
            }
        });
    }

    function showArticlesList(post_ids, msg, type, label, total_posts, current_page) {
        var message = "'.esc_html(__('Show Articles', 'geeky-bot')).'";
        SaveChathistory(message,"user");
        var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
        jQuery.post(ajaxurl, { action: "geekybot_frontendajax", geekybotme: "websearch", task: "showArticlesList", post_ids: post_ids, msg: msg, type: type, label: label, totalPosts: total_posts, currentPage: current_page, "_wpnonce":"'.esc_attr(wp_create_nonce("articles-list")) .'"}, function (data) {
            if (data) {
                geekybot_scrollToTop(340);
                var message = geekybot_DecodeHTML(data);
                jQuery(\'.geekybot_wc_post_load_more\').css(\'display\', \'none\');
                jQuery(\'#geekybotChatBox\').append(\'<li class="geekybot-message geekybot-message-bot"><section class="geekybot-message-bot-img 08"><img src="'.esc_url($botImgScr).'" alt="" /></section><section class="geekybot-message-text">\'+message+\'</section></li>\');
            }
        });
    }

    function closechat(){
        var chat_id = jQuery("#chatsession").val();
        if(chat_id!=""){
            setTimeout(function(){';
                $geekybot_js.='
                var message = "'.__('session time out', 'geeky-bot').'";
                var sender  = "user";
                var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
                jQuery.post(ajaxurl, { action: "geekybot_frontendajax", geekybotme: "chathistory", task: "endUserChat", cmessage: message,sender:sender ,chat_id:chat_id, "_wpnonce":"'. esc_attr(wp_create_nonce("end-user-chat")) .'"}, function (data) {
                    if (data) {
                        jQuery("#geekybotChatBox").empty();
                        var path = window.location.href;
                        jQuery(".geekybot-chat-popup").toggleClass("geekybot-chat-init");
                        jQuery(".geekybot-chat-open-dialog").removeClass("active");
                        jQuery(".geekybot-chat-close-button").removeClass("active");
                        document.cookie = "geekybot_collapse_chat_popup=0; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";
                        jQuery(".geekybot-chat-popup").removeClass("active");
                        jQuery(".geekybot-dropdown-content").removeClass("show");
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
        $geekybot_js.='
        jQuery(\'#geekybotChatBox\').append(\'<li class="geekybot-message geekybot-message-user"><section class="geekybot-message-user-img 04"><img src="'.esc_url($userImgScr).'" alt="" /></section><section class="geekybot-message-text">\'+text+\'</section></li>\');
        var ajaxurl =
            "'. esc_url(admin_url("admin-ajax.php")) .'";
        jQuery.post(ajaxurl, {
            action: "geekybot_ajax",
            geekybotme: "slots",
            message: message,
            task: "saveVariableFromButtonIntent",
            "_wpnonce":"'. esc_attr(wp_create_nonce("variable-from-button-intent")). '"
        }, function(data) {
            if (data) {
                sendRequestToServer(data,text,sender,chat_id);
            }
        });
    }

    function geekybotHideSmartPopup(msg) {
        jQuery(".geekybot-chat-open-outer-popup-mainwrp").fadeOut();
        document.cookie = "geekybot_hide_smart_popup=1; expires=Sat, 01 Jan 2050 00:00:00 UTC; path=/";
    }

    function geekybotChatOpenDialog() {
        jQuery(".geekybot-chat-open-dialog").click();
    }

    function SaveChathistory(message,sender) { ';
        $geekybot_js.='
        var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
        var response_id =  jQuery("#response_id").val();
        jQuery.post(ajaxurl, { action: "geekybot_frontendajax", geekybotme: "chathistory", task: "SaveChathistory", cmessage: message,csender:sender, "_wpnonce":"'.esc_attr(wp_create_nonce("save-chat-history")).'" }, function (data) {
                if (data) {
                    if(sender=="user") {
                        jQuery("#response_id").val(data);
                    }
                }
            }
        );
    }

    function sendRequestToServer(message,text,sender,chat_id){
        if (!document.cookie.split(";").some(cookie => cookie.trim().startsWith("geekybot_chat_id="))) {
            if (jQuery(".geekybot-chat-popup").hasClass("active")) {
                enforceRestartUserChat(function () {
                    // Now call getMessageResponse only AFTER the cookie is set
                    sendMessageAjax(message,text,sender,chat_id);
                });
                return; // prevent the call from executing immediately
            }
        }

        // If cookie is already set, just call it
        sendMessageAjax(message,text,sender,chat_id);

    }

    function sendMessageAjax(message,text,sender,chat_id){
            //geekybot_scrollToTop(1);
        jQuery(\'#geekybotChatBox\').append(\'<li class="geekybot-message geekybot-message-bot geekybot_loading"><section class="geekybot-message-bot-img 05"><img src="' . esc_url($botImgScr) . '" alt="" /></section><section class="geekybot-message-text_wrp"></section></li>\');
        var listItem = jQuery(\'#geekybotChatBox\').find(\'li.geekybot-message-bot\').last(); // Get the last inserted <li>
        
        listItem.find(\'section.geekybot-message-text_wrp\').append(\'<section class="geekybot-message-loading"><img src="'.esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/bot-typing.gif" alt="" /></section>\');
        jQuery.ajax({
            url: "'.esc_url(admin_url('admin-ajax.php')).'",
            type: "POST",
            async: true,
            data: {
                "action": "geekybot_frontendajax", 
                "geekybotme": "chatserver", 
                "task": "getMessageResponse", 
                "message": message, 
                cmessage: message, 
                ctext: text, 
                csender:sender, 
                "_wpnonce":"'.esc_attr(wp_create_nonce('get-message-response')).'"
            },
        }).done(function(data) {
            //geekybot_scrollToTop(150);
            jQuery(".geekybot-message-loading").remove();
            jQuery("#typing_message").remove();
            var data = JSON.parse(data);
            if (data && Array.isArray(data) && data.length > 0) {
                jQuery.each(data, function( index, value ) {
                    if (value.text) {
                        setTimeout(function() {
                            geekybot_scrollToTop(335);
                            var sender = "bot";
                            var message = geekybot_DecodeHTML(value.text.bot_response);
                            if (typeof value.text.bot_articles !== "undefined") {
                                message += geekybot_DecodeHTML(value.text.bot_articles);
                            }

                            var btn = value.buttons;
                            // error with woocommerce code
                            //message = message.replace( /((http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?)/g,"<a href=\'$1\'>$1</a>");
                            listItem.find(\'section.geekybot-message-text_wrp\').append(\'<section class="geekybot-message-text">\' + message + \'</section>\'); // Append text inside the <section>

                            // Handle buttons (if any)
                            if (btn) {
                                var btnhtml = \'<div class="geekybot-message-button">\';
                                jQuery.each(btn, function(i, btns) {
                                    var btntext = btns.text;
                                    var btnvalue = btns.value;
                                    var btntype = btns.type;
                                    var btnflag = "true";
                                    if (btntype == 1) {
                                        btnhtml += \'<li class="geekybot-message geekybot-message-button"><section><button class="wp-chat-btn" onclick="sendbtnrsponse(this);" value="\' + btnvalue + \'"><span>\' + btntext + \'</span></section></button></li>\';
                                    } else if (btntype == 2) {
                                        btnhtml += \'<li class="geekybot-message geekybot-message-button"><section><button class="wp-chat-btn"><span><a class="wp-chat-btn-link" href="\' + btnvalue + \'">\' + btntext + \'</a></span></button></section></li>\';
                                    }
                                });
                                btnhtml += \'</div>\';
                                jQuery(btnhtml).hide().appendTo(listItem.find(\'section.geekybot-message-text_wrp\')).fadeIn(1000);
                                // listItem.find(\'section.geekybot-message-text_wrp\').append(btnhtml); // Append buttons inside the same <section>
                            }
                        }, index * 1500); // Delay based on index (1s per message)
                    }
                });
            } else {
                geekybot_scrollToTop(150);
                var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
                jQuery.post(ajaxurl, {
                    action: "geekybot_frontendajax",
                    geekybotme: "chatserver",
                    task: "getDefaultFallBackFormAjax",
                    chat_id: chat_id,
                    "_wpnonce": "' . esc_attr(wp_create_nonce('get-fallback')) . '"
                }, function(fbdata) {
                    if (fbdata) {
                        var fbdata = JSON.parse(fbdata);
                        if (fbdata.text) {
                            var btnhtml = "";
                            var btn = fbdata.buttons;
                            if (btn) {
                                btnhtml = \'<div class="geekybot-message-button">\';
                                jQuery.each(btn, function(i, btns) {
                                    var btntext = btns.text;
                                    var btnvalue = btns.value;
                                    var btntype = btns.type;
                                    if (btntype == 1) {
                                        btnhtml += \'<li class="geekybot-message geekybot-message-button" style=""><section><button class="wp-chat-btn" onclick="sendbtnrsponse(this);" value="\' + btnvalue + \'"><span>\' + btntext + \'</span></section></button></li>\';
                                    } else if (btntype == 2) {
                                        btnhtml += \'<li class="geekybot-message geekybot-message-button" style=""><section><button class="wp-chat-btn"><span><a class="wp-chat-btn-link" href="\' + btnvalue + \'">\' + btntext + \'</a></span></button></section></li>\';
                                    }
                                });
                                btnhtml += \'</div>\';
                            }

                            // Create a container for the typing effect
                            var responseContainer = jQuery(\'<section class="geekybot-message-text"></section>\');
                            listItem.find(\'section.geekybot-message-text_wrp\').append(responseContainer); 

                            // Function to display text word by word
                            function typeText(text, container, delay = 200) {
                                let words = text.split(\' \');
                                let index = 0;
                                let typingInterval = setInterval(function() {
                                    if (index < words.length) {
                                        container.append(words[index] + " ");
                                        index++;
                                    } else {
                                        clearInterval(typingInterval);
                                        // Append buttons after text animation completes
                                        listItem.find("section.geekybot-message-text_wrp").append(btnhtml);
                                    }
                                }, delay);
                            }

                            // Start typing effect
                            typeText(fbdata.text, responseContainer);
                        }
                        
                    } else {
                        console.error("AJAX Error:", textStatus, errorThrown);
                    }
                });
            }
        }).fail(function(data, textStatus, xhr) {
            jQuery(".geekybot-message-loading").remove();
            var configmsg = "'.esc_attr(geekybot::$_configuration['default_message']).'";

            jQuery(\'#geekybotChatBox\').append(\'<li class="geekybot-message geekybot-message-bot"><section class="geekybot-message-bot-img 06"><img src="'.esc_url($botImgScr).'" alt="" /></section><section class="geekybot-message-text">\'+configmsg+\'</section></li>\');
        });
        jQuery(".geekybot-message-bot").removeClass("geekybot_loading");
    }

    ';
    wp_register_script( 'geekybot-frontend-handle', '' , array(), GEEKYBOT_PLUGIN_VERSION, 'all');
    wp_enqueue_script( 'geekybot-frontend-handle' );
    wp_add_inline_script('geekybot-frontend-handle',$geekybot_js);

?>



