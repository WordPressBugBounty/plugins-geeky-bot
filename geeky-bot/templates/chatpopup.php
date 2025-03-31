<?php
    if(!is_admin() && geekybot::$_configuration['offline'] == '2'){
        if (geekybot::$_configuration['title'] != '') {
            $title = geekybot::$_configuration['title'];
        } else {
            $title = __('GeekyBot', 'geeky-bot');
        }
        $botImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getBotImagePath();
        $closeImgScr = esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/chat_close.png';
        $chatpopupcode = wp_enqueue_style('geekybot-fontawesome', GEEKYBOT_PLUGIN_URL . 'includes/css/font-awesome.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
        if (geekybot::$_configuration['welcome_message'] != '') {
            $chatpopupcode .='
            <div class="geekybot-chat-open-outer-popup-dialog" style="display: none;">';
                if(geekybot::$_configuration['welcome_message_img'] != '0'){
                    $msgImgPath =GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getWelcomeMessageImagePath();
                    $chatpopupcode .='
                    <div class="geekybot-chat-open-outer-popup-dialog-image"><img src="'.esc_url($msgImgPath).'" alt="'.esc_html(__('Logo', 'geeky-bot')).'" title="'.esc_html(__('Logo', 'geeky-bot')).'"/></div>';
                }
                $chatpopupcode .='
                <p onclick="geekybotChatOpenDialog();" class="geekybot-chat-open-outer-popup-dialog-text">'.wp_kses(geekybot::$_configuration['welcome_message'], GEEKYBOT_ALLOWED_TAGS).'</p>
                <span onclick="geekybotHideSmartPopup();" id="geekybotHideSmartPopup" class="geekybot-chat-open-outer-popup-dialog-top-cross-button">
                    <img src="'.esc_url(GEEKYBOT_PLUGIN_URL).'/includes/images/control_panel/close-icon.png" alt="'.esc_html(__('Close', 'geeky-bot')).'" title="'.esc_html(__('Close', 'geeky-bot')).'"/>
                </span>
                <span class="geekybot-chat-open-outer-popup-dialog-btmborderwrp"></apan>
            </div>';
        }
        $chatpopupcode .='
        <div class="geekybot-chat-open-dialog-main">
            <div class="geekybot-chat-open-dialog-main-inner">
                <button class="geekybot-chat-open-dialog">
                    <div class="geekybot-chat-open-dialog-img">
                        <img class="geekybot-chat-image geekybot-chat-logo" alt="screen tag" src="'. esc_url($botImgScr) .'" />
                        <img class="geekybot-chat-image geekybot-chat-close-img" alt="screen tag" src="'. esc_url($closeImgScr) .'" />
                    </div>
                </button>
            </div>
        </div>
        <div class="geekybot-chat-close-button-wrp">
            <div class="geekybot-chat-close-button-inner">
                <button class="geekybot-chat-close-button"></button>
            </div>
        </div>
        <div class="geekybot-chat-popup">
            <div class="geekybot-chat-windows geekybot-chat-main">
                <div id="geekybot-main-messages" class="geekybot-chat-window">
                    <div class="geekybot-title-main-overlay">
                        <div id="geekybot-window-title" class="geekybot-window-top">
                            <div class="geekybot-window-top-inner">
                                <div class="geekybot-window-top-inner-left">
                                    <div class="geekybot-window-profile">
                                        <div class="geekybot-window-profile-img">
                                            <img src="'.esc_url($botImgScr).'" alt="'.esc_html(__('Bot', 'geeky-bot')).'" />
                                        </div>
                                    </div>
                                    <i class="fa fa-circle"></i>
                                    <div class="geekybot-window-profile-text">
                                        <div class="geekybot-window-text">
                                            <span>'.esc_html($title).'</span>
                                            <span>'.esc_html(__('online', 'geeky-bot')).'</span>
                                        </div>
                                    </div>
                                    <div class="geekybot-title-overlay"></div>
                                </div>
                                <div class="geekybot-window-top-inner-right">
                                    <div class="geekybot-window-top-dot-img" onclick="geekybotMyFunction()">
                                        <img src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'/includes/images/chat-img/menu.png" alt="'.esc_html(__('Menu', 'geeky-bot')).'" />
                                    </div>
                                </div>
                            </div>
                            <div id="geekybotMyDropdown" class="geekybot-dropdown-content">
                                <div class="geekybot-main-overlay" id="geekybotEndChat">
                                    <div>'.esc_html(__('End Chat', 'geeky-bot')).'</div>
                                    <div class="geekybot-overlay">'.esc_html(__('End Chat', 'geeky-bot')).'</div>
                                </div>
                                <div class="geekybot-main-overlay" id="geekybotRestartChat">
                                    <div>'.esc_html(__('Restart Chat', 'geeky-bot')).'</div>
                                    <div class="geekybot-overlay">'.esc_html(__('Restart Chat', 'geeky-bot')).'</div>
                                </div>';
                                if (geekybot::$_configuration['show_support_link'] == 1) {
                                    $support_link_url = !empty(geekybot::$_configuration['support_link_url']) ? geekybot::$_configuration['support_link_url'] : '#';
                                    $chatpopupcode .= '
                                    <div class="geekybot-main-overlay" id="geekybotSupportLink">
                                        <a target="_blank" href="'.esc_url($support_link_url).'" >
                                            <div>'.esc_html(__('Support', 'geeky-bot')).'</div>
                                            <div class="geekybot-overlay">'.esc_html(__('Support', 'geeky-bot')).'</div>
                                        </a>
                                    </div>';
                                }
                                $chatpopupcode .= '
                            </div>
                        </div>
                    </div>
                    <div id="geekybotPreviousChatBox" class="geekybot-chat-content"></div>
                    <div class="geekbot-actualmsg-main-section">';
                        if (geekybot::$_configuration['welcome_message'] != '') {
                            $chatpopupcode .= '
                            <div class="geekybot-chat-content geekybot-welcome-message">
                                <li class="geekybot-message geekybot-message-bot">
                                    <section class="geekybot-message-bot-img">
                                        <img src="'.esc_url($botImgScr).'" alt="'.esc_html(__('Image', 'geeky-bot')).'">
                                    </section>
                                    <section class="geekybot-message-text">
                                        '.geekybot::$_configuration['welcome_message'].'
                                    </section>
                                </li>
                            </div>';
                        }
                        $chatpopupcode .= '
                        <div id="geekybotChatBox" class="geekybot-chat-content">';
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
                    <div id="geekybot-send-message" class="geekybot-window-bottom">';
                        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
                        $chatpopupcode .='
                        <input type="hidden" id="chatsession"  value="'.$chat_id.'">
                        <input type="hidden" id="response_id"  value="">
                        <div class="geekybot-window-bottom-inner">
                            <div class="geekybot-window-bottom-inner-left">
                                <input id="geekybot-message-box" type="text" class="border-0 geekybot-message-box" placeholder="'.esc_html(__('Send message', 'geeky-bot')).'" autocomplete="off" />
                            </div>
                            <div class="geekybot-window-bottom-inner-right">
                                <div class="geekybot-window-bottom-send-img">
                                    <img id="geekybot-send-button" src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'/includes/images/chat-img/send-icon.png" alt="'.esc_html(__('Send Icon', 'geeky-bot')).'" />
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


