<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTresponsesController {
    private $_msgkey;
    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('responses')->getMessagekey();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'responses' , null);
        if (self::canaddfile()) {
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'responses');
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

$GEEKYBOTresponses = new GEEKYBOTresponsesController();
?>
