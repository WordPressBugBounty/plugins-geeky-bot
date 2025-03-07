<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTchathistoryModel {


    function getChatHistorySessions(){
        $searchtitle = isset(geekybot::$_search['chathistory']['searchtitle']) ? geekybot::$_search['chathistory']['searchtitle'] : '';
        $query = "SELECT chathistory.* , user.display_name as user_name FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions`  as chathistory
            LEFT JOIN `" . geekybot::$_db->prefix . "users` as user ON chathistory.user_id = user.id";

        if ($searchtitle) {
            $query .= " WHERE user.display_name LIKE '%".esc_sql($searchtitle)."%'";
        }
        $query.=" ORDER BY id  DESC  LIMIT 10";
        $rows = geekybotdb::GEEKYBOT_get_results($query);
        geekybot::$_data[0]['chathistory'] = $rows;
        geekybot::$_data['filter']['searchtitle'] = $searchtitle;
        return;
    }

    function getNextChatHistorySessions(){
        $offset = GEEKYBOTrequest::GEEKYBOT_getVar('offset');
        $searchtitle = GEEKYBOTrequest::GEEKYBOT_getVar('searchtitle');
        if(isset($offset)) {
            $currentPage = $offset;
        } else {
            $currentPage = 2;
        }
        $dataPerScroll = 10;
        $offset = ($currentPage - 1) * $dataPerScroll;
        // 
        $query = "SELECT chathistory.* , user.display_name as user_name FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions`  as chathistory
            LEFT JOIN `" . geekybot::$_db->prefix . "users` as user ON chathistory.user_id = user.id";

        if (isset($searchtitle) && $searchtitle != '') {
            $query .= " WHERE user.display_name LIKE '%".esc_sql($searchtitle)."%'";
        }
        $query.=" ORDER BY id  DESC  LIMIT ". esc_sql($dataPerScroll) ." OFFSET ".esc_sql($offset);
        $rows = geekybotdb::GEEKYBOT_get_results($query);
        geekybot::$_data[0]['chathistory'] = $rows;
        geekybot::$_data['filter']['searchtitle'] = $searchtitle;

        // 
        $html = "";
        foreach (geekybot::$_data[0] as $key => $value) {
            foreach ($value as $key3 => $value3) {
                $ctime = $value3->created;
                if ($value3->user_name != '') {
                    $user_name = $value3->user_name;
                } else {
                    $user_name = __('Guest', 'geeky-bot');
                }
                if (isset($value3->user_id) && $value3->user_id != 0) {
                    $user_id = $value3->user_id;
                } else {
                    $user_id = '';
                }
                $datet = gmdate("d M/Y H:i:s",geekybotphplib::GEEKYBOT_strtotime(gmdate($ctime)));
                $html .= "<div class=\"leftmenuuser\" data-userid =\"". esc_attr($value3->id) ."\" onclick=\"makeMeActive('". esc_js($user_name) ."', '". esc_js($user_id) ."', '". esc_js($value3->id) ."', this, '". esc_js($datet) ."', 0, 0)\">
                    <div class=\"menuImg\" style=\"\">
                        <img src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/chat-history/users.png\" alt=\"". esc_attr(__('user', 'geeky-bot')) ."\" >
                    </div>
                    <div class=\"menuUser\" style=\"\">
                        <p class=\"leftmenusetting username\">". esc_html($user_name) ."
                        </p>
                        <h4>". esc_html(__('User ID', 'geeky-bot')) ." : 
                            <span class=\"leftmenuuserid\">";
                                if (isset($value3->user_id) && $value3->user_id != 0) {
                                    $html .=  esc_html($value3->user_id);
                                }
                                $html .= "
                            </span>
                        </h4>
                    </div>
                    <div class=\"menuDateTime\" style=\"\">
                        <p class=\"leftmenusetting geekybot_datetime\">";
                            if ($value3->created != '') {
                                $html .= esc_html($value3->created); 
                            }
                            $html .= "
                        </p>
                    </div>
                </div>";
                
            }
        }
        if ($html != '') {
            $nextpage = $currentPage + 1;
            $html .= '<a id="jsjb-jm-showmorejobs" class="scrolltask" data-scrolltask="getNextChatHistorySessions" data-offset="'.esc_attr($nextpage).'" style="display:none;"></a>';
        }
        return $html;
    }

    function getUserChatHistoryMessages() {
        if (!current_user_can('manage_options')){
            // disable nonce
            // die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-user-chat-history') ) {
            die( 'Security check Failed' );
        }
        $username = GEEKYBOTrequest::GEEKYBOT_getVar('username');
        $userid = GEEKYBOTrequest::GEEKYBOT_getVar('userid');
        $chatlimit = GEEKYBOTrequest::GEEKYBOT_getVar('chatlimit',null,0);
        $chatHistoryId = GEEKYBOTrequest::GEEKYBOT_getVar('chatHistoryId');
        $htmlDiv = '123';
        $datet = GEEKYBOTrequest::GEEKYBOT_getVar('datet');

        $query2 = "SELECT COUNT(id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` where session_id = ".esc_sql($chatHistoryId);
        $total = geekybotdb::GEEKYBOT_get_var($query2);
        geekybot::$_data['total'] = $total;
        geekybot::$_data[1] = GEEKYBOTpagination::GEEKYBOT_getPagination($total);
        $maxrecorded = GEEKYBOTpagination::GEEKYBOT_getLimit();
        $limit = $chatlimit * $maxrecorded;
        if($limit >= $total){
            $limit = 0;
        }

        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` where session_id = " . esc_sql($chatHistoryId);
        $query .= " ORDER BY id, created  DESC";
        $query .= " LIMIT " . esc_sql($limit) . ", " . esc_sql($maxrecorded);
        $rows = geekybotdb::GEEKYBOT_get_results($query);
        $html = $this->makeMessageList($rows, $total, $maxrecorded, $chatlimit, $chatHistoryId, $htmlDiv, $datet, $username, $userid);
        return $html;
    }

    function makeMessageList($rows, $total, $maxrecorded, $chatlimit, $chatHistoryId, $htmlDiv, $datet, $username, $userid){
        $str = '';
        $date = '';
        $botsd = 1;
        $story_count = count(GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getStoriesForCombobox());
        if(!empty($rows)){
            foreach ($rows as $index => $value) {
                $extraclass = '';
                $botclass = '';
                $senderClass = '';
                $btn = '';
                $strs = '';
                $style='';
                $plain_message = '';
                $rightusermessage='';
                if ($value->sender == 'user') {
                    $botsd==1;
                    if($botsd==1) {
                        $date = '<div class=\'date\'>'.$datet.'</div>';
                    }
                    $extraclass = 'usertypediv';
                    $senderClass = 'usertype';
                    $senderimg   = '<img src=\"'. esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/chat-history/users.png\" alt=\"'. esc_attr(__('user', 'geeky-bot')) .'\" >';
                    $extraclass = 'usertypediv';
                    $style='';
                    $intentlink = esc_url(admin_url('admin.php?page=geekybot_intent&geekybotlt=formintent&geekybotid='));
                    $intentid = $value->intent_id;
                    $intent =  $intentlink . $intentid;

                    $btn .='<span class="geekybot-history-page-subheading">'. esc_attr(__('Action', 'geeky-bot')).' :</span>';
                    if ($story_count > 0) {
                        $btn .= '<a id=\"addToStory\" class=\"geekybot-table-act-btn\" title=\"'. esc_attr(__('Add to Story', 'geeky-bot')).'\">';
                        $btn .= esc_attr(__('Add to story', 'geeky-bot'));
                        $btn .='</a>';
                    }
                    $rightusermessage = 'right-user-message';
                    $plain_message = geekybotphplib::GEEKYBOT_wp_strip_all_tags($value->message);
                    $plain_message = geekybotphplib::GEEKYBOT_htmlspecialchars(geekybotphplib::GEEKYBOT_addslashes($plain_message), ENT_QUOTES, 'UTF-8');
                }
                if ($value->sender == 'bot') {
                    $btn = '';
                    $date = '';
                    $botsd++;
                    $senderClass = 'bottype';
                    // 
                    if (strpos($value->message, '&') !== false && strpos($value->message, ';') !== false) {
                        $value->message = html_entity_decode($value->message);
                        $needToEncode = 1;
                    }
                    $pattern = '/\s*href=["\'][^"\']*["\']/i';
                    $value->message = geekybotphplib::GEEKYBOT_preg_replace($pattern, '', $value->message);
                    // Pattern to match the onclick attribute and its value
                    $pattern = '/\s*onclick=["\'][^"\']*["\']/i';
                    $pattern = '/\s*onclick=("|\')(.*?)\1/';
                    // Use preg_replace to remove the onclick attribute
                    $value->message = geekybotphplib::GEEKYBOT_preg_replace($pattern, '', $value->message);
                    if (isset($needToEncode)) {
                        $value->message = geekybotphplib::GEEKYBOT_htmlentities($value->message);
                    }
                    // 
                    $strs = $value->message;
                    // Use strip_tags to remove all HTML tags
                    $buttonTag = '<button>'; // Define button tag

                    if (preg_match("/" . $buttonTag . "/", $strs)) {
                        $style = 'style="border: 1px solid #fffefe;"';
                        $senderimg = '';
                    } else {
                        $style = '';
                        $senderimg = '<img src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/chat-history/robot.png" alt="' . esc_attr(__('user', 'geeky-bot')) . '" >';
                    }
                    $senderClass = 'bottype';
                    $botclass = 'botdiv';
                    $rightusermessage='right-bot-message';
                }
                if ($value->sender == '') {
                    $botsd=1;
                    $style='';
                    $senderimg   = '<img src=\"'. esc_url(GEEKYBOT_PLUGIN_URL) .'includes/images/chat-history/robot.png\" alt=\"'. esc_attr(__('user', 'geeky-bot')) .'\" >';
                }
                $str .= $date;
                $str .= '<div class=\"body-content '. $rightusermessage .'\">';
                $str .= '<div class=\"user-datashow '.$botsd.'\">';
                    $str .= '<div class=\"body-content-sender\" '. $style .' > ';
                    $str .= $senderimg;
                    $str .='</div>';
                    $str .= '<div class=\"body-content-message\"><span class="geekybot-history-page-subheading"> '. esc_attr(__('Message', 'geeky-bot')).':</span>';
                    if ($value->sender == 'bot') {
                        $str .= '<span class=\"body-content-message-value \"><section class=\"actual_msg_text_wrp\"> '. $value->message .' </section></span>';
                        if ($value->buttons != '[]' && $value->buttons != '') {
                            $responseButtons = json_decode($value->buttons);
                            $str .= "<div class='actual_msg_btn'>";
                            foreach ($responseButtons as $responseButton) {
                                $str .=  "<li class='actual_msg actual_msg_btn' style=''><section><button class='wp-chat-btn'><span>".$responseButton->text."</span></button></section></li>";
                            };
                            $str .= "</div>";
                        }
                    } else {
                        $str .= '<span data-intent=\"'. $plain_message .'\" class=\"body-content-message-value \"> '. $value->message .' </span>';
                    }
                    $str .= '</div>';
                    $str .= ' <div class=\"body-content-action\">';
                    $str .= '<div class=\"header-action-img\">';
                    $str .= $btn;
                    $str .= '</div>';
                    $str .= '</div>';
                $str .= '</div>';
            $str .= '</div>';
            }
            $num_of_pages = ceil($total / $maxrecorded);
            $num_of_pages = ($num_of_pages > 0) ? ceil($num_of_pages) : floor($num_of_pages);
            if($num_of_pages > 0){
                $page_html = '';
                $prev = $chatlimit;
                if($prev > 0){
                    $page_html .= '<a class="geekybot-jsst_userlink" href="#" onclick="makeMeActive('
                        . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($username)) . '\','
                        . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($userid)) . '\','
                        . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($chatHistoryId)) . '\','
                        . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($htmlDiv)) . '\','
                        . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($datet)) . '\','
                        . esc_js(($prev - 1)) . ', 1);">'
                        . '<img class="geeky-pagnumber-previcon" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/previous.png" '
                        . 'title="' . esc_attr(__("Previous", "geeky-bot")) . '" '
                        . 'alt="' . esc_attr(__("Previous", "geeky-bot")) . '" />'
                        . '</a>';
                }
                for($i = 0; $i < $num_of_pages; $i++){
                    if($i == $chatlimit)
                        $page_html .= '<span class="geekybot-jsst_userlink selected" >'.($i + 1).'</span>';
                    else
                        $page_html .= '<a class="geekybot-jsst_userlink" href="#" onclick="makeMeActive('. '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($username)) . '\','. '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($userid)) . '\','. '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($chatHistoryId)) . '\','. '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($htmlDiv)) . '\','. '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($datet)) . '\','. esc_js($i) . ', 1);">'. esc_js(($i + 1)) . '</a>';

                }
                $next = $chatlimit + 1;
                if($next < $num_of_pages){
                    $page_html .= '<a class="geekybot-jsst_userlink" href="#" onclick="makeMeActive('
                    . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($username)) . '\','
                    . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($userid)) . '\','
                    . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($chatHistoryId)) . '\','
                    . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($htmlDiv)) . '\','
                    . '\'' . esc_js(geekybotphplib::GEEKYBOT_addslashes($datet)) . '\','
                    . esc_js($next) . ', 1);">'
                    . '<img class="geeky-pagnumber-nexticon" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/next.png" '
                    . 'title="' . esc_attr(__("Next", "geeky-bot")) . '" '
                    . 'alt="' . esc_attr(__("Next", "geeky-bot")) . '" />'
                    . '</a>';
                }
                if($page_html != ''){
                    $str .= '<div class="geekybot-jsst_userpages">'.wp_kses($page_html, GEEKYBOT_ALLOWED_TAGS).'</div>';
                }
            }
        }else{
            $msg = esc_html(__('No record found','geeky-bot'));
            $str .= GEEKYBOTlayout::GEEKYBOT_getNoRecordFound($msg);
        }
        echo wp_kses($str, GEEKYBOT_ALLOWED_TAGS);
        die();
    }

    function SaveChathistory() {
        // admin cards
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-chat-history') ) {
            // disable nonce
            // die( 'Security check Failed' );
        }
        $message = GEEKYBOTrequest::GEEKYBOT_getVar('cmessage');
        $sender = GEEKYBOTrequest::GEEKYBOT_getVar('csender');
        $session_id = $this->saveChatHistorySession();
        $this->saveChatHistoryMessage($message, $sender, $session_id);
    }

    function SaveChathistoryFromchatServer($message, $sender, $type = '', $buttons = '', $post_type = '') {
        $session_id = $this->saveChatHistorySession();
        $this->saveChatHistoryMessage($message, $sender, $session_id, $type, $buttons, $post_type);
    }

    function saveChatHistorySession(){
        $user_id = get_current_user_id();
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions` WHERE chat_id = '".esc_sql($chat_id)."'";
        $row = geekybotdb::GEEKYBOT_get_row($query);
        if (isset($row) && $row != '') {
            // if session recored already exist
            if ($row->user_id == 0 && $user_id != '') {
                $query = 'UPDATE `' . geekybot::$_db->prefix . 'geekybot_chat_history_sessions` SET `user_id` = '.esc_sql($user_id).' WHERE `chat_id`= "' . esc_sql($chat_id) . '"';
                geekybotdb::query($query);
            }
            return $row->id;
        } else {
            // if session recored not exist
            $data['user_id'] = $user_id;
            $data['chat_id'] = $chat_id;
            $data['created'] = gmdate("Y-m-d H:i:s");
            $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            $row = GEEKYBOTincluder::GEEKYBOT_getTable('chathistorysessions');
            if (!$row->bind($data)) {
                return GEEKYBOT_SAVE_ERROR;
            }
            if (!$row->store()) {
                return GEEKYBOT_SAVE_ERROR;
            }
            return $row->id;
        }
    }
    function saveChatHistoryMessage($message, $sender, $session_id, $type = '', $buttons = '', $post_type = ''){
        if ($buttons != '') {
            $buttons = wp_json_encode($buttons);
        }
        $data['response_id'] = 0;
        $data['intent_id'] = 0;
        $data['subject'] = '';
        $data['message'] = $message;
        $data['sender'] = $sender;
        $data['confidence'] = '';
        $data['type'] = $type;
        $data['post_type'] = $post_type;
        $data['buttons'] = $buttons;
        $data['created'] = gmdate("Y-m-d H:i:s");
        $data['session_id'] = $session_id;
        $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('chathistorymessages');
        if (!$row->bind($data)) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (!$row->store()) {
            return GEEKYBOT_SAVE_ERROR;
        }
    }

    function getRandomChatId() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-random-chat-id') ) {
            // disable nonce
            // die( 'Security check Failed' );
        }
        $datetime = GEEKYBOTrequest::GEEKYBOT_getVar('datetime');
        // check from cookies data
        if(isset($_COOKIE['geekybot_chat_id'])){
            $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        } else {
            // converting datetime to base 64 encode
            $chatid = geekybotphplib::GEEKYBOT_safe_encoding($datetime);
            $this->setchatidcookies($chatid);
            $activechat['chat_id'] = $chatid;
            $activechat['created'] = gmdate("Y-m-d H:i:s");
            $row = GEEKYBOTincluder::GEEKYBOT_getTable('activechat');
            if (!$row->bind($activechat)) {
                return GEEKYBOT_SAVE_ERROR;
            }
            if (!$row->store()) {
                return GEEKYBOT_SAVE_ERROR;
            }
        }
        return $chatid;
    }

    function restartUserChat() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'restart-user-chat') ) {
            // disable nonce
            // die( 'Security check Failed' );
        }
        $datetime = GEEKYBOTrequest::GEEKYBOT_getVar('datetime');
        // unset the old chat id
        $this->geekybot_removechatidcookies();
        $chatid = "";
        // converting datetime to base 64 encode
        $chatid = geekybotphplib::GEEKYBOT_safe_encoding($datetime);
        // set the new chat id
        $this->setchatidcookies($chatid);
        $activechat['chat_id'] = $chatid;
        $activechat['created'] = gmdate("Y-m-d H:i:s");
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('activechat');
        if (!$row->bind($activechat)) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (!$row->store()) {
            return GEEKYBOT_SAVE_ERROR;
        }
        return $chatid;
    }

    function endUserChat(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'end-user-chat') ) {
            // disable nonce
            // die( 'Security check Failed' );
        }
        $this->geekybot_removechatidcookies();
        // save message
        $message = GEEKYBOTrequest::GEEKYBOT_getVar('cmessage');
        $sender = GEEKYBOTrequest::GEEKYBOT_getVar('csender');
        $session_id = $this->saveChatHistorySession();
        $this->saveChatHistoryMessage($message, $sender, $session_id);
        return GEEKYBOT_SAVED;
    }

    function getMessagekey(){
        $key = 'chathistory'; if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    private function setchatidcookies($chatid){
        $data = wp_json_encode( $chatid );
        $data = geekybotphplib::GEEKYBOT_safe_encoding($data);
        geekybotphplib::GEEKYBOT_setcookie('geekybot_chat_id' , $data , 0 , COOKIEPATH);
        if ( SITECOOKIEPATH != COOKIEPATH ){
            geekybotphplib::GEEKYBOT_setcookie('geekybot_chat_id' , $data , 0 , SITECOOKIEPATH);
        }
    }

    private function geekybot_removechatidcookies(){
        if(isset($_COOKIE['geekybot_chat_id'])){
            $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
            geekybot::$_geekybotsessiondata->geekybot_getVariablesDatabySessionId($chatid, '', true);

            geekybotphplib::GEEKYBOT_setcookie('geekybot_chat_id' , '' , time() - 3600 , COOKIEPATH);
            if ( SITECOOKIEPATH != COOKIEPATH ){
                geekybotphplib::GEEKYBOT_setcookie('geekybot_chat_id' , '' , time() - 3600 , SITECOOKIEPATH);
            }
        }
        $array = array();
        $array['flag'] = 0;
        $array['nextIndex'] = '';
        $array['index'] = 0;
        $array['ranking'] = 0;
        $array = wp_json_encode( $array );
        update_option( 'geekybot_read_variable', $array );
        // update_option( 'geekybot_read_variable', 0 );
    }

    function geekybot_getchatid(){
        $chatid = '';
        if(isset($_COOKIE['geekybot_chat_id'])){
            $cid = geekybot::GEEKYBOT_sanitizeData($_COOKIE['geekybot_chat_id']);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            $chatid = json_decode( geekybotphplib::GEEKYBOT_safe_decoding($cid) , true );
        }
        return $chatid;
    }

    function getAdminChathistorySearchData($search_userfields){
        $geekybot_search_array = array();
        $geekybot_search_array['searchtitle'] = GEEKYBOTrequest::GEEKYBOT_getVar('searchtitle');
        $geekybot_search_array['search_from_chathistory'] = 1;
        return $geekybot_search_array;
    }

    function getFrontSideChathistorySearchData($search_userfields){
        $geekybot_search_array = array();
        $geekybot_search_array['chathistory'] = GEEKYBOTrequest::GEEKYBOT_getVar('chathistory', 'post');
        return $geekybot_search_array;
    }

    function getCookiesSavedSearchDataChathistory($search_userfields){
        $geekybot_search_array = array();
        $wpjp_search_cookie_data = '';
        if(isset($_COOKIE['geekybot_chatbot_search_data'])) {
            $wpjp_search_cookie_data = $_COOKIE['geekybot_chatbot_search_data'];
            $wpjp_search_cookie_data = json_decode( geekybotphplib::GEEKYBOT_safe_decoding($wpjp_search_cookie_data) , true );
        }
        if($wpjp_search_cookie_data != '' && isset($wpjp_search_cookie_data['search_from_chathistory']) && $wpjp_search_cookie_data['search_from_chathistory'] == 1) {
            $geekybot_search_array['searchtitle'] = $wpjp_search_cookie_data['searchtitle'];
        }
        return $geekybot_search_array;
    }

    function setSearchVariableForChathistory($geekybot_search_array,$search_userfields){
        geekybot::$_search['chathistory']['searchtitle'] = isset($geekybot_search_array['searchtitle']) ? $geekybot_search_array['searchtitle'] : '';
        geekybot::$_search['chathistory']['sorton'] = isset($geekybot_search_array['sorton']) ? $geekybot_search_array['sorton'] : 6;
        geekybot::$_search['chathistory']['sortby'] = isset($geekybot_search_array['sortby']) ? $geekybot_search_array['sortby'] : 2;
    }
    // nlu.yml end
}
?>
