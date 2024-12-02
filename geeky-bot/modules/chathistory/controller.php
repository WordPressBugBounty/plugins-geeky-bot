<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTChathistoryController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->getMessagekey();
        $chathistory = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory');
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'chathistory', null);
        $uid = GEEKYBOTincluder::GEEKYBOT_getObjectClass('user')->geekybot_uid();
        $chathistory = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory');
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_chathistory':
            		$task = GEEKYBOTrequest::GEEKYBOT_getVar('task');
                    GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->getChatHistorySessions();
                    break;
                default:
                    exit;
            }
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'chathistory');
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

$GEEKYBOTChathistoryController = new GEEKYBOTChathistoryController();
?>

