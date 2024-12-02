<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTintentController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        //recheck it
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->getMessagekey();
    }

    function handleRequest() {
        //recheck
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'intents', null);
        $uid = GEEKYBOTincluder::GEEKYBOT_getObjectClass('user')->geekybot_uid();
        if (self::canaddfile()) {
            if(!isset($module)){
                $module = (is_admin()) ? 'page' : 'geekybotme';
            }
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'intent');
            $module = geekybotphplib::GEEKYBOT_str_replace('geekybot_', '', $module);
            GEEKYBOTincluder::GEEKYBOT_include_file($layout, $module);
        }
    }

    function canaddfile() {
        $nonce_value = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot_nonce');
        if ( wp_verify_nonce( $nonce_value, 'geekybot_nonce') ) {
            if (isset($_POST['form_request']) && $_POST['form_request'] == 'geekybot')
                return false;
            elseif (isset($_GET['action']) && $_GET['action'] == 'geekybottask')
                return false;
            else
                return true;
        }
    }

}

$GEEKYBOTintentController = new GEEKYBOTintentController();
?>

