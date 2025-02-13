<?php
    if(!is_admin() && geekybot::$_configuration['offline'] == '2'){
        if (geekybot::$_configuration['title'] != '') {
            $title = geekybot::$_configuration['title'];
        } else {
            $title = __('GeekyBot', 'geeky-bot');
        }
        $botImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getBotImagePath();
        $chatpopupcode = wp_enqueue_style('geekybot-fontawesome', GEEKYBOT_PLUGIN_URL . 'includes/css/font-awesome.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
        if (geekybot::$_configuration['welcome_message'] != '') {
            $chatpopupcode .='
            <div class="chat-open-outer-popup-dialog" style="display: none;">';
                if(geekybot::$_configuration['welcome_message_img'] != '0'){
                    $msgImgPath =GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getWelcomeMessageImagePath();
                    $chatpopupcode .='
                    <div class="chat-open-outer-popup-dialog-image"><img src="'.esc_url($msgImgPath).'" alt="'.esc_html(__('Logo', 'geeky-bot')).'" title="'.esc_html(__('Logo', 'geeky-bot')).'"/></div>';
                }
                $chatpopupcode .='
                <p onclick="geekybotChatOpenDialog();" class="chat-open-outer-popup-dialog-text">'.wp_kses(geekybot::$_configuration['welcome_message'], GEEKYBOT_ALLOWED_TAGS).'</p>
                <span onclick="geekybotHideSmartPopup();" id="hideSmartPopup" class="chat-open-outer-popup-dialog-top-cross-button">
                    <img src="'.esc_url(GEEKYBOT_PLUGIN_URL).'/includes/images/control_panel/close-icon.png" alt="'.esc_html(__('Close', 'geeky-bot')).'" title="'.esc_html(__('Close', 'geeky-bot')).'"/>
                </span>
                <span class="chat-open-outer-popup-dialog-btmborderwrp"></apan>
            </div>';
        }
        $chatpopupcode .='
        <div class="chat-open-dialog-main">
            <div class="chat-open-dialog-main-inner">
                <button class="chat-open-dialog">
                  <div class="chat-open-dialog-img">
                   <img class="wp-chat-image" alt="screen tag" src="'. esc_url($botImgScr) .'" /></button>
                  </div>
            </div>
        </div>
        <div class="chat-button-destroy-main">
            <div class="chat-button-destroy-main-inner">
                <button class="chat-button-destroy"></button>
            </div>
        </div>
        <div class="chat-popup">
            <div class="chat-windows chat-main">
                <div id="main-messages" class="chat-window-two">
                    <div class="geekybot-title-main-overlay">
                        <div id="window-two-title" class="window-two-top">
                            <div class="window-two-top-inner">
                                <div class="window-two-top-inner-left">
                                    <div class="window-two-profile">
                                        <div class="window-two-profile-img">
                                            <img src="'.esc_url($botImgScr).'" alt="'.esc_html(__('Bot', 'geeky-bot')).'" />
                                        </div>
                                    </div>
                                    <i class="fa fa-circle"></i>
                                    <div class="window-two-profile-text">
                                        <div class="window-two-text">
                                            <span>'.esc_html($title).'</span>
                                            <span>'.esc_html(__('online', 'geeky-bot')).'</span>
                                        </div>
                                    </div>
                                    <div class="geekybot-title-overlay"></div>
                                </div>
                                <div class="window-two-top-inner-right">
                                    <div class="window-two-top-dot-img" onclick="myFunction()" id="dna">
                                        <img src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'/includes/images/chat-img/menu.png" alt="'.esc_html(__('Menu', 'geeky-bot')).'" />
                                    </div>
                                </div>
                            </div>
                            <div id="myDropdown" class="dropdown-content">
                                <div class="geekybot-main-overlay" id="jsendchat">
                                    <div>'.esc_html(__('End Chat', 'geeky-bot')).'</div>
                                    <div class="geekybot-overlay">'.esc_html(__('End Chat', 'geeky-bot')).'</div>
                                </div>
                                <div class="geekybot-main-overlay" id="restartchat">
                                    <div>'.esc_html(__('Restart Chat', 'geeky-bot')).'</div>
                                    <div class="geekybot-overlay">'.esc_html(__('Restart Chat', 'geeky-bot')).'</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="previouschatbox" class="chat-content"></div>
                    <div class="geekbot-actualmsg-main-section">';
                        if (geekybot::$_configuration['welcome_message'] != '') {
                            $chatpopupcode .= '
                            <div class="chat-content welcome-message">
                                <li class="actual_msg actual_msg_adm">
                                    <section class="actual_msg_adm-img">
                                        <img src="'.esc_url($botImgScr).'" alt="'.esc_html(__('Image', 'geeky-bot')).'">
                                    </section>
                                    <section class="actual_msg_text">
                                        '.geekybot::$_configuration['welcome_message'].'
                                    </section>
                                </li>
                            </div>';
                        }
                        $chatpopupcode .= '
                        <div id="chatbox" class="chat-content">';
                            if(isset($_COOKIE['geekybot_chat_id'])){
                                $chatId = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();  
                                $query = "SELECT sessionmsgvalue  FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata` WHERE usersessionid = '".esc_sql($chatId)."' and sessionmsgkey = 'chathistory'";
                                $conversion = geekybotdb::GEEKYBOT_get_var($query);
                                if ($conversion != null) {
                                    $chatpopupcode .= html_entity_decode($conversion);
                                }
                            }
                        $chatpopupcode .='
                        </div>
                    </div>
                    <div id="send-message" class="col-md-12 p-2 msg-box window-two-btm">';
                        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
                        $chatpopupcode .='
                        <input type="hidden" id="chatsession"  value="'.$chat_id.'">
                        <input type="hidden" id="response_id"  value="">
                        <div class="window-two-btm-inner">
                            <div class="window-two-btm-inner-left">
                                <input id="msg_box" type="text" class="border-0 msg_box" placeholder="'.esc_html(__('Send message', 'geeky-bot')).'" autocomplete="off" />
                            </div>
                            <div class="window-two-btm-inner-right">
                                <div class="window-two-btm-send-img">
                                    <img id="snd-btn" src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'/includes/images/chat-img/send-icon.png" alt="'.esc_html(__('Send Icon', 'geeky-bot')).'" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        require_once(GEEKYBOT_PLUGIN_PATH . 'modules/chatserver/tmpl/chatpopup.inc.php');
        if (class_exists('WooCommerce')) {
            require_once(GEEKYBOT_PLUGIN_PATH . 'modules/woocommerce/tmpl/wc_chatpopup.inc.php');
        }
        echo wp_kses($chatpopupcode, GEEKYBOT_ALLOWED_TAGS);
    }
?>


