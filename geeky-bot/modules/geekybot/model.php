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
        $curdate = date_i18n('Y-m-d');
        $last_month = date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime("now -1 month"));
        geekybot::$_data['curdate'] = $curdate;

        // Stats start
        // -------- AI Chatbot sessions ---------
        $ai_chatbot_story = geekybotdb::GEEKYBOT_get_var("SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `story_type` = 1");
        geekybot::$_data['ai_chatbot_sessions']['today'] = 0;
        geekybot::$_data['ai_chatbot_sessions']['last_month'] = 0;
        
        if ($ai_chatbot_story == 1) {
            // Consolidate queries for session counts
            $queries = [
                'total' => "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE type = 1",
                'today' => "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) = DATE('" . esc_sql($curdate) . "') AND type = 1",
                'last_month' => "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) BETWEEN DATE('" . esc_sql($last_month) . "') AND DATE('" . esc_sql($curdate) . "') AND type = 1"
            ];
            
            foreach ($queries as $key => $query) {
                geekybot::$_data['ai_chatbot_sessions'][$key] = geekybot::$_db->get_var($query);
            }
        } else {
            if (isset($ai_chatbot_story) && $ai_chatbot_story == 0) {
                geekybot::$_data['ai_chatbot_sessions']['error_message'] = __('AI ChatBot is disabled.', 'geeky-bot');
                $text = __('Active Now', 'geeky-bot');
            } else {
                geekybot::$_data['ai_chatbot_sessions']['error_message'] = __('AI ChatBot story not built.', 'geeky-bot');
                $text = __('Built Now', 'geeky-bot');
            }
            geekybot::$_data['ai_chatbot_sessions']['error_message_btn'] = "<a href = '" . esc_url(wp_nonce_url('admin.php?page=geekybot_stories&geekybotlt=stories','Stories')) ."'> " . esc_html($text) . "</a>";
        }

        // -------- WooCommerce sessions ---------
        geekybot::$_data['woocommerce_sessions']['today'] = 0;
        geekybot::$_data['woocommerce_sessions']['last_month'] = 0;

        if ( !file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
            // Check if WooCommerce is installed and not activated
            geekybot::$_data['woocommerce_sessions']['error_message'] = __('WooCommerce plugin is not installed.', 'geeky-bot');
            geekybot::$_data['woocommerce_sessions']['error_message_btn'] = "<a href='". esc_url(admin_url( 'plugin-install.php?tab=search&s=woocommerce' )) ."'> ". esc_html(__("Install Now","geeky-bot")) ."</a>";
        } elseif ( is_plugin_inactive( 'woocommerce/woocommerce.php' ) ) {
            // WooCommerce is installed but not activated
            geekybot::$_data['woocommerce_sessions']['error_message'] = __('WooCommerce plugin is not activated.', 'geeky-bot');
            geekybot::$_data['woocommerce_sessions']['error_message_btn'] = "<a href='". esc_url(admin_url( 'plugins.php?s=woocommerce&plugin_status=all' )) ."'> ". esc_html(__('Active Now','geeky-bot'))."</a>";
        } else {
            $wc_story = geekybotdb::GEEKYBOT_get_var("SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `story_type` = 2");
            if ($wc_story == 1) {
                $queries = [
                    'total' => "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE type = 2",
                    'today' => "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) = DATE('" . esc_sql($curdate) . "') AND type = 2",
                    'last_month' => "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) BETWEEN DATE('" . esc_sql($last_month) . "') AND DATE('" . esc_sql($curdate) . "') AND type = 2"
                ];

                foreach ($queries as $key => $query) {
                    geekybot::$_data['woocommerce_sessions'][$key] = geekybot::$_db->get_var($query);
                }
            } else {
                if (isset($wc_story) && $wc_story == 0) {
                    geekybot::$_data['woocommerce_sessions']['error_message'] = __('WooCommerce Bot is disabled.', 'geeky-bot');
                    $text = __('Active Now', 'geeky-bot');
                } else {
                    geekybot::$_data['woocommerce_sessions']['error_message'] = __('WooCommerce story not built.', 'geeky-bot');
                    $text = __('Built Now', 'geeky-bot');
                }
                geekybot::$_data['woocommerce_sessions']['error_message_btn'] = "<a href = '" . esc_url(wp_nonce_url('admin.php?page=geekybot_stories&geekybotlt=stories','Stories')) ."'> " . esc_html($text) . "</a>";
            }
        }
        
        // -------- AI Web Search sessions ---------
        if (geekybot::$_configuration['is_posts_enable'] == 1) {
            $queries = [
                'total' => "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE type = 4",
                'today' => "SELECT COUNT(DISTINCT session_id) FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` WHERE DATE(created) = DATE('" . esc_sql($curdate) . "') AND type = 4"
            ];

            foreach ($queries as $key => $query) {
                geekybot::$_data['ai_web_search_sessions'][$key] = geekybot::$_db->get_var($query);
            }
        } else {
            geekybot::$_data['ai_web_search_sessions']['total'] = geekybot::$_data['ai_web_search_sessions']['today'] = 0;
        }
        // stats end
        // ------- Last Month AI Chatbot sessions ----------

        $query = "SELECT session.created FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions` AS  session
            LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` AS message ON session.id = message.session_id
            WHERE DATE(session.created) >= DATE('".esc_sql($last_month)."')
            AND DATE(session.created) <= DATE('".esc_sql($curdate)."')
            AND message.type = 1
            GROUP BY session.id";
        $ai_chatbot_last_month_sessions = geekybot::$_db->get_results($query);
        
        $date_ai_chatbot_last_month_session = array();
        foreach ($ai_chatbot_last_month_sessions AS $ai_chatbot_last_month_session) {
            if (!isset($date_ai_chatbot_last_month_session[date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($ai_chatbot_last_month_session->created))]))
                $date_ai_chatbot_last_month_session[date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($ai_chatbot_last_month_session->created))] = 0;
            $date_ai_chatbot_last_month_session[date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($ai_chatbot_last_month_session->created))] = $date_ai_chatbot_last_month_session[date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($ai_chatbot_last_month_session->created))] + 1;
        }
        geekybot::$_data['last_month_ai_chatbot_story_chart']['start_date'] = $last_month;
        geekybot::$_data['last_month_ai_chatbot_story_chart']['end_date'] = $curdate;
        $ai_chatbot_story = 0;
        $json_array = "";
        $nextdate = $last_month;
        do{
            $year = date_i18n('Y',strtotime($nextdate));
            $month = date_i18n('m',strtotime($nextdate));
            $day = date_i18n('d',strtotime($nextdate));
            $ai_chatbot_story_tmp = isset($date_ai_chatbot_last_month_session[$nextdate]) ? $date_ai_chatbot_last_month_session[$nextdate]  : 0;
            $json_array .= "[$year, $month, $day, $ai_chatbot_story_tmp],";
            $ai_chatbot_story += $ai_chatbot_story_tmp;
            if($nextdate == $curdate){
                break;
            }
            $nextdate = date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($nextdate . " +1 days"));
        } while($nextdate <= $curdate);

        geekybot::$_data['last_month_ai_chatbot_story_chart']['data'] = $json_array;

        // ------- Last Month Woocommerce sessions ----------

        $query = "SELECT session.created FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions` AS  session
            LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` AS message ON session.id = message.session_id
            WHERE DATE(session.created) >= DATE('".esc_sql($last_month)."')
            AND DATE(session.created) <= DATE('".esc_sql($curdate)."')
            AND message.type = 2
            GROUP BY session.id";
        $ai_chatbot_last_month_sessions = geekybot::$_db->get_results($query);
        
        $date_ai_chatbot_last_month_session = array();
        foreach ($ai_chatbot_last_month_sessions AS $ai_chatbot_last_month_session) {
            if (!isset($date_ai_chatbot_last_month_session[date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($ai_chatbot_last_month_session->created))]))
                $date_ai_chatbot_last_month_session[date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($ai_chatbot_last_month_session->created))] = 0;
            $date_ai_chatbot_last_month_session[date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($ai_chatbot_last_month_session->created))] = $date_ai_chatbot_last_month_session[date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($ai_chatbot_last_month_session->created))] + 1;
        }
        $ai_chatbot_story = 0;
        $json_array = "";
        $nextdate = $last_month;
        do{
            $year = date_i18n('Y',strtotime($nextdate));
            $month = date_i18n('m',strtotime($nextdate));
            $day = date_i18n('d',strtotime($nextdate));
            $ai_chatbot_story_tmp = isset($date_ai_chatbot_last_month_session[$nextdate]) ? $date_ai_chatbot_last_month_session[$nextdate]  : 0;
            $json_array .= "[$year, $month, $day, $ai_chatbot_story_tmp],";
            $ai_chatbot_story += $ai_chatbot_story_tmp;
            if($nextdate == $curdate){
                break;
            }
            $nextdate = date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($nextdate . " +1 days"));
        } while($nextdate <= $curdate);
        geekybot::$_data['last_month_woocommerce_story_chart']['data'] = $json_array;

        // -------- Top AI Web Search Post Types ---------
        if ( geekybot::$_configuration['is_posts_enable'] == 1 ) {
            $query = "SELECT type.post_type, COALESCE(COUNT(session.id), 0) AS session_count 
                FROM `" . geekybot::$_db->prefix . "geekybot_post_types` AS  type
                LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` AS message ON type.post_type = message.post_type
                LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions` AS session ON session.id = message.session_id
                
                AND DATE(session.created) BETWEEN DATE('" . esc_sql($last_month) . "') AND DATE('" . esc_sql($curdate) . "')
                AND message.type = 4
                WHERE type.status = 1
                GROUP BY type.post_type
                ORDER BY session_count DESC
                LIMIT 3";
            
            $top_searches = geekybot::$_db->get_results($query);
            geekybot::$_data['top_ai_web_search'] = $top_searches;
            // -------- Last Month Post Type Sessions ---------
            if ($top_searches) {
                foreach ($top_searches as $index => $web_Search) {
                    $query = "SELECT created FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_messages` 
                    WHERE DATE(created) BETWEEN DATE('" . esc_sql($last_month) . "') AND DATE('" . esc_sql($curdate) . "')
                    AND type = 4
                    AND post_type = '".esc_sql($web_Search->post_type)."'";
                    $sessions = geekybot::$_db->get_results($query);
                    
                    $date_sessions = [];
                    foreach ($sessions as $session) {
                        $date_key = date_i18n('Y-m-d', strtotime($session->created));
                        $date_sessions[$date_key] = isset($date_sessions[$date_key]) ? $date_sessions[$date_key] + 1 : 1;
                    }
                    $ai_chatbot_story = 0;
                    $json_array = "";
                    $nextdate = $last_month;
                    do {
                        $year = date_i18n('Y',strtotime($nextdate));
                        $month = date_i18n('m',strtotime($nextdate));
                        $day = date_i18n('d',strtotime($nextdate));
                        $ai_chatbot_story_tmp = isset($date_sessions[$nextdate]) ? $date_sessions[$nextdate]  : 0;
                        $json_array .= "[$year, $month, $day, $ai_chatbot_story_tmp],";
                        $ai_chatbot_story += $ai_chatbot_story_tmp;
                        if($nextdate == $curdate){
                            break;
                        }
                        $nextdate = date_i18n('Y-m-d', geekybotphplib::GEEKYBOT_strtotime($nextdate . " +1 days"));
                    } while ($nextdate <= $curdate);

                    geekybot::$_data['last_month_posttype_story_chart_' . $index] = rtrim($json_array, ',');
                }
            }
            if (!isset(geekybot::$_data['last_month_posttype_story_chart_0'])) {
                geekybot::$_data['last_month_posttype_story_chart_error_message_0'] = __('Post Type not found.','geeky-bot');
            }
            if (!isset(geekybot::$_data['last_month_posttype_story_chart_1'])) {
                geekybot::$_data['last_month_posttype_story_chart_error_message_1'] = __('Post Type not found.','geeky-bot');
            }
            if (!isset(geekybot::$_data['last_month_posttype_story_chart_2'])) {
                geekybot::$_data['last_month_posttype_story_chart_error_message_2'] = __('Post Type not found.','geeky-bot');
            }
        } else {
            geekybot::$_data['last_month_posttype_story_chart_error_message_0'] = __('AI Web Search is disabled!','geeky-bot');
            geekybot::$_data['last_month_posttype_story_chart_error_message_1'] = __('AI Web Search is disabled!','geeky-bot');
            geekybot::$_data['last_month_posttype_story_chart_error_message_2'] = __('AI Web Search is disabled!','geeky-bot');
        }
        
        // Chat History
        $query = "SELECT chathistory.* , user.display_name as user_name FROM `" . geekybot::$_db->prefix . "geekybot_chat_history_sessions`  as chathistory
            LEFT JOIN `" . geekybot::$_db->prefix . "users` as user ON chathistory.user_id = user.id";
        $query.=" ORDER BY id  DESC LIMIT 4";
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

        // update available alert
        geekybot::$_data['update_avaliable_for_addons'] = $this->showUpdateAvaliableAlert();
        // Returning the prepared data
        return geekybot::$_data;
    }

    function showUpdateAvaliableAlert(){
        require_once GEEKYBOT_PLUGIN_PATH .'includes/addon-updater/geekybotupdater.php';
        $GEEKYBOT_Updater = new GEEKYBOT_Updater();
        $cdnversiondata = $GEEKYBOT_Updater->GEEKYBOT_getPluginVersionDataFromCDN();
        $not_installed = array();

        $geekybot_addons = GEEKYBOTincluder::GEEKYBOT_getModel('premiumplugin')->geekybotGetAddonsArray();
        $installed_plugins = get_plugins();
        $count = 0;
        foreach ($geekybot_addons as $key1 => $value1) {
            $matched = 0;
            $version = "";
            foreach ($installed_plugins as $name => $value) {
                $install_plugin_name = str_replace(".php","",basename($name));
                if($key1 == $install_plugin_name){
                    $matched = 1;
                    $version = $value["Version"];
                    $install_plugin_matched_name = $install_plugin_name;
                }
            }
            if($matched == 1){ //installed
                foreach ($cdnversiondata as $cdnname => $cdnversion) {
                    $install_plugin_name_simple = str_replace("-", "", $install_plugin_matched_name);
                    if($cdnname == str_replace("-", "", $install_plugin_matched_name)){
                        if($cdnversion > $version){ // new version available
                            $count++;
                        }
                    }    
                }
            }
        }
        return $count;
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

    function geekybotSendGracePeriodNotification($errorno) {
        update_option('unique_admin_process_value', $errorno);
    }

    function getUserImagePath() {
        $uid = get_current_user_id();
        // Ensure the UID is valid and numeric
        if (!is_numeric($uid) || !$uid) {
            return $this->getUserCustomOrDefaultImage();
        }

        // Get the avatar URL
        $avatar_url = get_avatar_url($uid, array('size' => 96));

        // Check if the avatar URL is valid
        if (!empty($avatar_url) && @getimagesize($avatar_url)) {
            // Use WordPress's get_avatar function to generate the avatar HTML
            return $avatar_url;
        } else {
            // Fallback to the default image if the avatar URL is invalid
            return $this->getUserCustomOrDefaultImage();
        }
    }

    function getUserCustomOrDefaultImage() {
        if (!empty(geekybot::$_configuration['user_custom_img'])) {
            $maindir = wp_upload_dir();
            return trailingslashit($maindir['baseurl']) . '/' . geekybot::$_configuration['data_directory'] . '/users/'.geekybot::$_configuration['user_custom_img'];
        }
        return esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/users.png';
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
            // disable nonce
            // die( 'Security check Failed' ); 
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
            // disable nonce
            // die( 'Security check Failed' ); 
        }
        $msg = GEEKYBOTrequest::GEEKYBOT_getVar('msg');
        $data = GEEKYBOTrequest::GEEKYBOT_getVar('data_array');
        $next_page = GEEKYBOTrequest::GEEKYBOT_getVar('next_page');
        $functionName = GEEKYBOTrequest::GEEKYBOT_getVar('functionName');
        $data = json_decode($data);
        $posts = GEEKYBOTincluder::GEEKYBOT_getModel('systemaction')->$functionName($msg, $data, $next_page);
        // save bot response to the session and chat history
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($posts, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($posts, 'bot');
        return $posts;
    }

    function hideVideoPopupFromAdmin(){
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
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

    function geekybotGetAddonTransationKey($option_name){
        $query = "SELECT `option_value` FROM " . geekybot::$_db->prefix . "options WHERE option_name = '".esc_sql($option_name)."'";
        $transactionKey = GEEKYBOTrequest::GEEKYBOT_getVar($query);
        if($transactionKey == ""){
            $transactionKey = get_option($option_name);
        }
        return $transactionKey;
    }

    function getSiteUrl(){
        $site_url = site_url();
        $site_url = geekybotphplib::GEEKYBOT_str_replace("https://","",$site_url);
        $site_url = geekybotphplib::GEEKYBOT_str_replace("http://","",$site_url);
        return $site_url;
    }

    function getNetworkSiteUrl(){
        $network_site_url = network_site_url();
        $network_site_url = geekybotphplib::GEEKYBOT_str_replace("https://","",$network_site_url);
        $network_site_url = geekybotphplib::GEEKYBOT_str_replace("http://","",$network_site_url);
        return $network_site_url;
    }

    function getMessagekey(){
        $key = 'geekybot';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }



}

?>
