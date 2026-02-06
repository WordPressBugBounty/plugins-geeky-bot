<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTzywraplogsController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('zywraplogs')->getMessagekey();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'logs', null);
        
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_logs':
                    GEEKYBOTincluder::GEEKYBOT_getModel('zywraplogs')->geekybot_get_logs_data();
                    break;
                default:
                    exit;
            }
            GEEKYBOTincluder::GEEKYBOT_include_file($layout, 'zywraplogs');
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
        return true;
    }
}
$GEEKYBOTzywraplogsController = new GEEKYBOTzywraplogsController();
?>