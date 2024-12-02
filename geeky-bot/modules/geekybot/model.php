<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTgeekybotModel {
    function checkLicense() {
        $customer_token = geekybot::$_configuration['customer_token'];
        $post_data['action'] = 'check_expiry';
        $posted_data = array(
        'customer_token'=> $customer_token,
        'action' => $post_data['action'],
        );
        // Generates a URL-encoded query string.
        $query = http_build_query($posted_data, NULL, '&', PHP_QUERY_RFC3986);//spaces will be percent encoded (%20).
        // The request URL.
        $url = '' . $query;
        $response = wp_remote_post( $url, array('body' => $posted_data  ,'sslverify'=>false));
        if( !is_wp_error($response) && $response['response']['code'] == 200 && isset($response['body']) ){
            $result = $response['body'];
            $result = json_decode($result,true);
        }else{
            $result = false;
            if(!is_wp_error($response)){
               $error = $response['response']['message'];
           }else{
                $error = $response->get_error_message();
           }
        }

        $dashboard_message = get_option('dashboard_message');
        // add_option('bot_expiry_msg', $result['msg']);
        $bot_expiry_date = get_option('bot_expiry_date');
        if (!isset($dashboard_message)) {
            add_option( 'dashboard_message', '0', '', '1');
        }

        if(!empty($result['orderid']))
        {
            update_option( 'dashboard_message', '1' );
            add_option( 'bot_expiry_date', $result['orderexpirydate']);
        }
        else
        {
         update_option( 'dashboard_message', '0' );
        }
    }

    function geekybotTimeAgo($date) {
        // Convert MySQL date format (assuming Y-m-d) to a DateTime object
        $dateTime = new DateTime($date);
        // Get the current time
        $currentTime = new DateTime();
        // Calculate the time difference between the date and current time
        $diff = $currentTime->diff($dateTime);
        // Get the difference in years, months, days, hours, minutes, and seconds
        $years = $diff->y;
        $months = $diff->m;
        $days = $diff->d;
        $hours = $diff->h;
        $minutes = $diff->i;
        $seconds = $diff->s;

        // Create a relative time string based on the difference
        if ($years > 0) {
            $timeAgo = $years . ($years > 1 ? ' '.__('years','geeky-bot') : ' '.__('year','geeky-bot')) . ' '.__('ago','geeky-bot');
        } else if ($months > 0) {
            $timeAgo = $months . ($months > 1 ? ' '.__('months','geeky-bot') : ' '.__('month','geeky-bot')) . ' '.__('ago','geeky-bot');
        } else if ($days > 0) {
            $timeAgo = $days . ($days > 1 ? ' '.__('days','geeky-bot') : ' '.__('day','geeky-bot')) .' '. __('ago','geeky-bot');
        } else if ($hours > 0) {
            $timeAgo = $hours . ($hours > 1 ? ' '.__('hours','geeky-bot') : ' '.__('hour','geeky-bot')) .' '. __('ago','geeky-bot');
        } else if ($minutes > 0) {
            $timeAgo = $minutes . ($minutes > 1 ? ' '.__('minutes','geeky-bot') : ' '.__('minute','geeky-bot')) .' '. __('ago','geeky-bot');
        } else if ($seconds > 0) {
            $timeAgo = __('Just now','geeky-bot');
        }
        return $timeAgo;
    }

    function getAdminControlPanelData() {
        // $this->checkLicense();
        $curdate = date_i18n('Y-m-d');
        geekybot::$_data['curdate'] = $curdate;
        // stats
        $ai_query = "SELECT COUNT(id) FROM `" . geekybot::$_db->prefix . "geekybot_stories` where story_type = 1";
        $ai_query .= " ORDER BY id ASC ";
        $ai_count = geekybotdb::GEEKYBOT_get_var($ai_query);
        // woo story data
        $woo_query = "SELECT COUNT(id) FROM `" . geekybot::$_db->prefix . "geekybot_stories` where story_type = 2";
        $woo_query .= " ORDER BY id ASC ";
        $woo_count = geekybotdb::GEEKYBOT_get_var($woo_query);
        $inquery =  '';
        // -----
        if ($ai_count > 0) {
            // total AI Chatbot sessions
            geekybot::$_data['stats'][0]['title'] = __('AI ChatBot Sessions', 'geeky-bot');
            $query = "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE type = 1";
            geekybot::$_data['stats'][0]['total'] = geekybot::$_db->get_var($query);
            // today AI Chatbot sessions
            $query = "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) = DATE('".esc_sql($curdate)."') AND  type = 1";
            geekybot::$_data['stats'][0]['today'] = geekybot::$_db->get_var($query);
            $inquery .=  ' 1 , ';
        }
        // -----
        if ($woo_count > 0) {
            // total WC Chatbot sessions
            geekybot::$_data['stats'][1]['title'] = __('WooCommerce Sessions', 'geeky-bot');
            $query = "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE type = 2";
            geekybot::$_data['stats'][1]['total'] = geekybot::$_db->get_var($query);
            // today WC Chatbot sessions
            $query = "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) = DATE('".esc_sql($curdate)."') AND  type = 2";
            geekybot::$_data['stats'][1]['today'] = geekybot::$_db->get_var($query);
            $inquery .=  ' 2 , ';
        }
        // -----
        if(geekybot::$_configuration['is_posts_enable'] == 1) {
            // total post Chatbot sessions
            geekybot::$_data['stats'][3]['title'] = __('AI Web Search Sessions', 'geeky-bot');
            $query = "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE type = 4";
            geekybot::$_data['stats'][3]['total'] = geekybot::$_db->get_var($query);
            // today post Chatbot sessions
            $query = "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) = DATE('".esc_sql($curdate)."') AND  type = 4";
            geekybot::$_data['stats'][3]['today'] = geekybot::$_db->get_var($query);
            $inquery .=  ' 4 , ';
        }
        // total sessions
        $query = "SELECT type, COUNT(DISTINCT session_id) AS stats FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE sender = 'bot' ";
        if ($inquery != '') {
            $inquery = rtrim($inquery, ' , ');
            $query .= ' AND type IN ( '.$inquery.' ) ';
        }
        $query .= ' GROUP BY type';
        $totalsessions = geekybot::$_db->get_results($query);
        $totalsessions_count = 0;
        foreach ($totalsessions as $value) {
            $totalsessions_count += $value->stats;
        }
        geekybot::$_data['totalsessions'] = $totalsessions_count;
        // today sessions
        $query = "SELECT type, COUNT(DISTINCT session_id) AS stats  FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) = DATE('".esc_sql($curdate)."') AND sender = 'bot' ";
        if ($inquery != '') {
            $inquery = rtrim($inquery, ' , ');
            $query .= ' AND type IN ( '.$inquery.' ) ';
        }
        $query .= ' GROUP BY type';
        $todaysessions = geekybot::$_db->get_results($query);
        $todaysessions_count = 0;
        foreach ($todaysessions as $value) {
            $todaysessions_count += $value->stats;
        }
        geekybot::$_data['todaysessions'] = $todaysessions_count;
        // 
        $query = "SELECT intents.id,intents.name
                FROM `" . geekybot::$_db->prefix . "geekybot_intents` AS intents
                ORDER By intents.created DESC LIMIT 5";
        $result = geekybot::$_db->get_results($query);
        geekybot::$_data['newest'] = $result;
        // Chat History
        $query = "SELECT chathistory.* , user.display_name as user_name FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions`  as chathistory
            LEFT JOIN `" . geekybot::$_db->prefix . "users` as user ON chathistory.user_id = user.id";
        $query.=" ORDER BY id  DESC LIMIT 7";
        $results = geekybot::$_db->get_results($query);
        foreach ($results as $result) {
            // find the count of the conversion
            $query = "SELECT count(id) as conversions FROM " . geekybot::$_db->prefix . "geekybot_chat_history_messages WHERE session_id = " . esc_sql($result->id);
            $result->conversions = geekybotdb::GEEKYBOT_get_var($query);
            // find the type of the conversion
            $query = "SELECT type FROM " . geekybot::$_db->prefix . "geekybot_chat_history_messages WHERE session_id = ".esc_sql($result->id)." AND sender = 'bot' AND type != '' ORDER BY id ASC";
            $result->type = geekybotdb::GEEKYBOT_get_var($query);
            // formate the created time of the conversion
            $result->created = $this->geekybotTimeAgo($result->created);
        }
        geekybot::$_data['chat_history'] = $results;

        return;
    }

    function checkProductExpiry() {

    }

    function widgetTotalStatsData() {
        $query = "SELECT count(id) AS totalintents
        FROM " . geekybot::$_db->prefix . "geekybot_intents ";
        $totalintents = geekybotdb::GEEKYBOT_get_row($query);
        geekybot::$_data['widget']['totalintents'] = $totalintents;

        return true;
    }

    function storeServerSerailNumber($data) {
        if (empty($data))
            return false;
        // DB class limitations
        if ($data['server_serialnumber']) {
            $query = "UPDATE  `" . geekybot::$_db->prefix . "geekybot_config` SET configvalue='" . esc_sql($data['server_serialnumber']) . "' WHERE configname='server_serial_number'";

            if (!geekybotdb::query($query))
                return GEEKYBOT_SAVE_ERROR;
            else
                return GEEKYBOT_SAVED;
        } else
            return GEEKYBOT_SAVE_ERROR;
    }

    function makeDir($path) {
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
            $creds = request_filesystem_credentials( site_url() );
            wp_filesystem( $creds );
        }
        if (!$wp_filesystem->exists($path)) { // create directory
            $wp_filesystem->mkdir($path, 0755);
            $ourFileName = $path . '/index.html';
            $ourFileHandle = $wp_filesystem->put_contents($ourFileName,'');
            if($ourFileHandle !== false){
            }else{
                die("can't open file (".esc_html($ourFileName).")");
            }
        }
    }

    function updateColorFile(){
        require(GEEKYBOT_PLUGIN_PATH . 'includes/css/style_color.php');
    }

    function updateDate($addon_name,$plugin_version){
        return GEEKYBOTincluder::GEEKYBOT_getModel('premiumplugin')->verfifyAddonActivation($addon_name);
    }

    function getAddonSqlForActivation($addon_name,$addon_version){
        return GEEKYBOTincluder::GEEKYBOT_getModel('premiumplugin')->verifyAddonSqlFile($addon_name,$addon_version);
    }

    function getUserImagePath() {//
        if (geekybot::$_configuration['user_custom_img'] == '0') {
            $imgPath = esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/users.png';
        } else {
            $maindir = wp_upload_dir();
            $baseurl = $maindir['baseurl'];
            $datadirectory = geekybot::$_configuration['data_directory'];
            $imgPath = $baseurl . '/' . $datadirectory.'/users/'.geekybot::$_configuration['user_custom_img'];
        }
        return $imgPath;
    }

    function getWelcomeMessageImagePath() {
        $maindir = wp_upload_dir();
        $baseurl = $maindir['baseurl'];
        $datadirectory = geekybot::$_configuration['data_directory'];
        $imgPath = $baseurl . '/' . $datadirectory.'/'.geekybot::$_configuration['welcome_message_img'];
        return $imgPath;
    }

    function getBotImagePath() {
        if (geekybot::$_configuration['bot_custom_img'] == '0') {
            $imgPath = esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/bot.png';
        } else {
            $maindir = wp_upload_dir();
            $baseurl = $maindir['baseurl'];
            $datadirectory = geekybot::$_configuration['data_directory'];
            $imgPath = $baseurl . '/' . $datadirectory.'/bots/'.geekybot::$_configuration['bot_custom_img'];
        }
        return $imgPath;
    }

    function geekybotLoadMoreProducts(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'load-more') ) {
            die( 'Security check Failed' ); 
        }
        $msg = GEEKYBOTrequest::GEEKYBOT_getVar('msg');
        $data = GEEKYBOTrequest::GEEKYBOT_getVar('data');
        $next_page = GEEKYBOTrequest::GEEKYBOT_getVar('next_page');
        $functionName = GEEKYBOTrequest::GEEKYBOT_getVar('functionName');
        $modelName = GEEKYBOTrequest::GEEKYBOT_getVar('modelName');
        if(!is_array($data)) {
            $data = json_decode($data,true);
        }
        $products = GEEKYBOTincluder::GEEKYBOT_getModel($modelName)->$functionName($msg, $data, $next_page);
        // save bot response to the session and chat history
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($products, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($products, 'bot');
        return $products;
    }

    function geekybotLoadMoreCustomPosts(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'load-more') ) {
            die( 'Security check Failed' ); 
        }
        $msg = GEEKYBOTrequest::GEEKYBOT_getVar('msg');
        $data = GEEKYBOTrequest::GEEKYBOT_getVar('data_array');
        $next_page = GEEKYBOTrequest::GEEKYBOT_getVar('next_page');
        $functionName = GEEKYBOTrequest::GEEKYBOT_getVar('functionName');
        $modelName = 'systemaction';
        $data = json_decode($data);
        $posts = GEEKYBOTincluder::GEEKYBOT_getModel($modelName)->$functionName($msg, $data, $next_page);
        // save bot response to the session and chat history
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($posts, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($posts, 'bot');
        return $posts;
    }

    function hideVideoPopupFromAdmin(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'hide-popup-from-admin') ) {
            die( 'Security check Failed' );
        }
        update_option( 'geekybot_hide_admin_top_banner', 1 );
    }

    function geekybotcheckPluginStatus($plugin_path = array()) {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        if (is_plugin_active($plugin_path)) {
            return true;
        }
        return false;
    }

    function getMessagekey(){
        $key = 'geekybot';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }



}

?>
