<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTformhandler {

    function __construct() {
        add_action('init', array($this, 'GEEKYBOT_checkFormRequest'));
        add_action('init', array($this, 'GEEKYBOT_checkDeleteRequest'));
    }

    /*
     * Handle Form request
     */

    function GEEKYBOT_checkFormRequest() {
        geekybot::$_data['sanitized_args']['_wpnonce'] = wp_create_nonce("VERIFY-GEEKYBOT-INTERNAL-NONCE");
        $formrequest = GEEKYBOTrequest::GEEKYBOT_getVar('form_request', 'post');
        if ($formrequest == 'geekybot') {
            //handle the request
            $modulename = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($modulename);
            $module = geekybotphplib::GEEKYBOT_str_replace('geekybot_', '', $module);
            geekybot::$_data['sanitized_args']['geekybot_nonce'] = esc_html(wp_create_nonce('geekybot_nonce'));
            GEEKYBOTincluder::GEEKYBOT_include_file($module);
            $class = 'GEEKYBOT' . $module . "Controller";
            $task = GEEKYBOTrequest::GEEKYBOT_getVar('task');
            $obj = new $class;
            $obj->$task();
        }
    }

    /*
     * Handle Form request
     */

    function GEEKYBOT_checkDeleteRequest() {
        geekybot::$_data['sanitized_args']['_wpnonce'] = wp_create_nonce("VERIFY-GEEKYBOT-INTERNAL-NONCE");
        $geekybot_action = GEEKYBOTrequest::GEEKYBOT_getVar('action', 'get');
        if ($geekybot_action == 'geekybottask') {
            //handle the request
            $modulename = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($modulename);
            $module = geekybotphplib::GEEKYBOT_str_replace('geekybot_', '', $module);
            geekybot::$_data['sanitized_args']['geekybot_nonce'] = esc_html(wp_create_nonce('geekybot_nonce'));
            GEEKYBOTincluder::GEEKYBOT_include_file($module);
            $class = 'GEEKYBOT' . $module . "Controller";
            $action = GEEKYBOTrequest::GEEKYBOT_getVar('task');
            $obj = new $class;
            $obj->$action();
        }
    }

}

$formhandler = new GEEKYBOTformhandler();
?>
