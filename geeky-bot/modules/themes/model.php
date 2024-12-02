<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTthemesModel {

    function getMessagekey(){
        $key = 'themes';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }
    
    function storeTheme($data) {
        if (!function_exists('wp_handle_upload')) {
            do_action('geekyboot_load_wp_file');
        }
        $maindir = wp_upload_dir();
        $basedir = $maindir['basedir'];
        $datadirectory = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('data_directory');
        
        $path = $basedir . '/' . $datadirectory;
        if (!file_exists($path)) { // create user directory
            GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->makeDir($path);
        }
        $isupload = false;
        $path = $path . '/users';
        if (!file_exists($path)) { // create user directory
            GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->makeDir($path);
        }
        if ($_FILES['user-img']['size'] > 0 ) {
            $file_name = geekybotphplib::GEEKYBOT_str_replace(' ', '_', sanitize_file_name($_FILES['user-img']['name']));
            $file_tmp = geekybot::GEEKYBOT_sanitizeData($_FILES['user-img']['tmp_name']);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            // actual location
            $userpath = $path;
            $isupload = true;
        }
        /*/To UPload Users Image/*/
        if ($isupload) {
            $this->uploadfor = 'usercustomimage';
            // Register our path override.
            add_filter( 'upload_dir', array($this,'GEEKYBOT_upload_custom_logo'));
            // Do our thing. WordPress will move the file to 'uploads/mycustomdir'.
            $result = array();
            $file = array(
                'name' => sanitize_file_name($_FILES['user-img']['name']),
                'type' => geekybot::GEEKYBOT_sanitizeData($_FILES['user-img']['type']),
                'tmp_name' => geekybot::GEEKYBOT_sanitizeData($_FILES['user-img']['tmp_name']),
                'error' => geekybot::GEEKYBOT_sanitizeData($_FILES['user-img']['error']),
                'size' => geekybot::GEEKYBOT_sanitizeData($_FILES['user-img']['size']),
            ); // GEEKYBOT_sanitizeData() function uses wordpress santize functions
            $result = wp_handle_upload($file, array('test_form' => false));
            if ( $result && ! isset( $result['error'] ) ) {
                $this->storeUserLogo($file_name, $userpath);
            }
            // Set everything back to normal.
            remove_filter( 'upload_dir', array($this,'GEEKYBOT_upload_custom_logo'));
        }
        /*/To UPload Bot Image/*/
        $path1 = $basedir . '/' . $datadirectory;
        if (!file_exists($path)) { // create user directory
            GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->makeDir($path);
        }
        $isupload1 = false;
        $path1 = $path1 . '/bots';
        if (!file_exists($path1)) { // create user directory
            GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->makeDir($path1);
        }
        if ($_FILES['bot-img']['size'] > 0) {

            $file_name1 = geekybotphplib::GEEKYBOT_str_replace(' ', '_', sanitize_file_name($_FILES['bot-img']['name']));
            $file_tmp1 = geekybot::GEEKYBOT_sanitizeData($_FILES['bot-img']['tmp_name']);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            // actual location
            $userpath1 = $path1;
            $isupload1 = true;
        }
        if ($isupload1) {
            $this->uploadfor = 'botcustomimage';
            // Register our path override.
            add_filter( 'upload_dir', array($this,'GEEKYBOT_upload_custom_logo'));
            // Do our thing. WordPress will move the file to 'uploads/mycustomdir'.
            $result = array();
            $file = array(
                'name' => sanitize_file_name($_FILES['bot-img']['name']),
                'type' => geekybot::GEEKYBOT_sanitizeData($_FILES['bot-img']['type']),
                'tmp_name' => geekybot::GEEKYBOT_sanitizeData($_FILES['bot-img']['tmp_name']),
                'error' => geekybot::GEEKYBOT_sanitizeData($_FILES['bot-img']['error']),
                'size' => geekybot::GEEKYBOT_sanitizeData($_FILES['bot-img']['size']),
            ); // GEEKYBOT_sanitizeData() function uses wordpress santize functions
            $result = wp_handle_upload($file, array('test_form' => false));
            if ( $result && ! isset( $result['error'] ) ) {
                $this->storeBotLogo($file_name1, $userpath1);
            }
            // Set everything back to normal.
            remove_filter( 'upload_dir', array($this,'GEEKYBOT_upload_custom_logo'));
        }
        /*To upload welcom message image */
        $path = $basedir . '/' . $datadirectory;
        $isupload = false;
        if ($_FILES['welcome-message-img']['size'] > 0) {

            $file_name = geekybotphplib::GEEKYBOT_str_replace(' ', '_', sanitize_file_name($_FILES['welcome-message-img']['name']));
            $file_tmp = geekybot::GEEKYBOT_sanitizeData($_FILES['welcome-message-img']['tmp_name']);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            // actual location
            $userpath = $path;
            $isupload = true;
        }
        if ($isupload) {
            $this->uploadfor = 'welcomemessageimg';
            // Register our path override.
            add_filter( 'upload_dir', array($this,'GEEKYBOT_upload_custom_logo'));
            // Do our thing. WordPress will move the file to 'uploads/mycustomdir'.
            $result = array();
            $file = array(
                'name' => sanitize_file_name($_FILES['welcome-message-img']['name']),
                'type' => geekybot::GEEKYBOT_sanitizeData($_FILES['welcome-message-img']['type']),
                'tmp_name' => geekybot::GEEKYBOT_sanitizeData($_FILES['welcome-message-img']['tmp_name']),
                'error' => geekybot::GEEKYBOT_sanitizeData($_FILES['welcome-message-img']['error']),
                'size' => geekybot::GEEKYBOT_sanitizeData($_FILES['welcome-message-img']['size']),
            ); // GEEKYBOT_sanitizeData() function uses wordpress santize functions
            $result = wp_handle_upload($file, array('test_form' => false));
            if ( $result && ! isset( $result['error'] ) ) {
                $this->storeWelcomeMessageImg($file_name, $userpath);
            }
            // Set everything back to normal.
            remove_filter( 'upload_dir', array($this,'GEEKYBOT_upload_custom_logo'));
        }
        $data = geekybot::GEEKYBOT_sanitizeData($data);
        GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->storeConfig($data);
        update_option('geekybot_set_theme_colors', wp_json_encode($data));
        $return = require(GEEKYBOT_PLUGIN_PATH . 'includes/css/style_color.php');

        if ($return) {
            return GEEKYBOT_THEME_SAVED;
        } else {
            return GEEKYBOT_THEME_SAVE_ERROR;
        }
    }

    function GEEKYBOT_upload_custom_logo( $dir ) {
        $datadirectory = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('data_directory');
        if($this->uploadfor == 'usercustomimage'){
            $path = $datadirectory . '/users';
            $array = array(
                'path'   => $dir['basedir'] . '/' . $path,
                'url'    => $dir['baseurl'] . '/' . $path,
                'subdir' => '/'. $path,
            ) + $dir;
            return $array;
        }elseif($this->uploadfor == 'botcustomimage'){
            $path = $datadirectory . '/bots';
            $array = array(
                'path'   => $dir['basedir'] . '/' . $path,
                'url'    => $dir['baseurl'] . '/' . $path,
                'subdir' => '/'. $path,
            ) + $dir;
            return $array;
        }elseif($this->uploadfor == 'welcomemessageimg'){
            $path = $datadirectory;
            $array = array(
                'path'   => $dir['basedir'] . '/' . $path,
                'url'    => $dir['baseurl'] . '/' . $path,
                'subdir' => '/'. $path,
            ) + $dir;
            return $array;
        }else{
            return $dir;
        }
    }

    function storeUserLogo($filename, $userpath) {
        $query = "SELECT configvalue FROM `".geekybot::$_db->prefix . "geekybot_config` WHERE configname = 'user_custom_img'";
        $key = geekybot::$_db->get_var($query);
        if ($key) {
            $unlinkPath = $userpath.'/'.$key;
            if (is_file($unlinkPath)) {
                wp_delete_file($unlinkPath);
            }
        }
        geekybot::$_db->query("UPDATE `" . geekybot::$_db->prefix . "geekybot_config` SET configvalue = '" . esc_sql($filename) . "' WHERE configname = 'user_custom_img' ");
    }

    function storeBotLogo($filename, $userpath) {
        $query = "SELECT configvalue FROM `".geekybot::$_db->prefix . "geekybot_config` WHERE configname = 'bot_custom_img'";
        $key = geekybot::$_db->get_var($query);
        if ($key) {
            $unlinkPath = $userpath.'/'.$key;
            if (is_file($unlinkPath)) {
                wp_delete_file($unlinkPath);
            }
        }
        geekybot::$_db->query("UPDATE `" . geekybot::$_db->prefix . "geekybot_config` SET configvalue = '" . esc_sql($filename) . "' WHERE configname = 'bot_custom_img' ");
    }

    function storeWelcomeMessageImg($filename, $userpath) {
        $query = "SELECT configvalue FROM `".geekybot::$_db->prefix . "geekybot_config` WHERE configname = 'welcome_message_img'";
        $key = geekybot::$_db->get_var($query);
        if ($key) {
            $unlinkPath = $userpath.'/'.$key;
            if (is_file($unlinkPath)) {
                wp_delete_file($unlinkPath);
            }
        }
        geekybot::$_db->query("UPDATE `" . geekybot::$_db->prefix . "geekybot_config` SET configvalue = '" . esc_sql($filename) . "' WHERE configname = 'welcome_message_img' ");
    }

    function getCurrentTheme() {
        $color1 = "#E92E4D";
        $color2 = "#FFE3E8";
        $color3 = "#000000";
        $color4 = "#3E4095";

        $color_string_values = get_option("geekybot_set_theme_colors");
        if($color_string_values != ''){
            $json_values = json_decode($color_string_values,true);
            if(is_array($json_values) && !empty($json_values)){
                $color1 = esc_attr($json_values['color1']);
                $color2 = esc_attr($json_values['color2']);
                $color3 = esc_attr($json_values['color3']);
                $color4 = esc_attr($json_values['color4']);
            }
        }
        $theme['color1'] = esc_attr($color1);
        $theme['color2'] = esc_attr($color2);
        $theme['color3'] = esc_attr($color3);
        $theme['color4'] = esc_attr($color4);
        
        $theme = apply_filters('cm_theme_colors', $theme, 'geeky-bot');
        geekybot::$_data[0] = $theme;
        return;
    }

    function deleteBotCustomImage(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'delete-bot-custom-image') ) {
            die( 'Security check Failed' );
        }
        $maindir = wp_upload_dir();
        $basedir = $maindir['basedir'];
        $datadirectory = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('data_directory');
        $path = $basedir . '/' . $datadirectory . '/bots';

        $query = "SELECT configvalue FROM `".geekybot::$_db->prefix . "geekybot_config` WHERE configname = 'bot_custom_img'";
        $key = geekybot::$_db->get_var($query);
        if ($key) {
            $unlinkPath = $path.'/'.$key;
            if (is_file($unlinkPath)) {
                wp_delete_file($unlinkPath);
            }
        }
        geekybot::$_db->update(geekybot::$_db->prefix . 'geekybot_config', array('configvalue' => 0), array('configname' => 'bot_custom_img'));
        return 'success';
    }

    function deleteWelcomeMessageImg(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'delete-message-image') ) {
            die( 'Security check Failed' );
        }
        $maindir = wp_upload_dir();
        $basedir = $maindir['basedir'];
        $datadirectory = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('data_directory');
        $path = $basedir . '/' . $datadirectory;

        $query = "SELECT configvalue FROM `".geekybot::$_db->prefix . "geekybot_config` WHERE configname = 'welcome_message_img'";
        $key = geekybot::$_db->get_var($query);
        if ($key) {
            $unlinkPath = $path.'/'.$key;
            if (is_file($unlinkPath)) {
                wp_delete_file($unlinkPath);
            }
        }
        geekybot::$_db->update(geekybot::$_db->prefix . 'geekybot_config', array('configvalue' => 0), array('configname' => 'welcome_message_img'));
        return 'success';
    }

    function deleteSupportUserImage(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'delete-support-user-image') ) {
            die( 'Security check Failed' );
        }
        $maindir = wp_upload_dir();
        $basedir = $maindir['basedir'];
        $datadirectory = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('data_directory');
        $path = $basedir . '/' . $datadirectory . '/users';

        $query = "SELECT configvalue FROM `".geekybot::$_db->prefix . "geekybot_config` WHERE configname = 'user_custom_img'";
        $key = geekybot::$_db->get_var($query);
        if ($key) {
            $unlinkPath = $path.'/'.$key;
            if (is_file($unlinkPath)) {
                wp_delete_file($unlinkPath);
            }
        }
        geekybot::$_db->update(geekybot::$_db->prefix . 'geekybot_config', array('configvalue' => 0), array('configname' => 'user_custom_img'));
        return 'success';
    }
}
?>
