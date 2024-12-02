<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTslotsController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getMessagekey();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'slots', null);
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_slots':
                    GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getAllSlots();
                    break;
                case 'admin_formslots':
                    $id = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotid');
                    GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getSlotsbyId($id);
                    break;
                default:
                    exit;
            }

            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'slots');
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

    function remove() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'delete-slots') ) {
            die( 'Security check Failed' ); 
        }
        $ids = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot-cb');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->deleteSlots($ids);

        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'slots');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        $url = wp_nonce_url(admin_url("admin.php?page=geekybot_slots&geekybotlt=slots"),'slots');
        wp_redirect($url);
        die();
    }

    function saveslots() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-slots') ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->storeSlots($data);
        $url = wp_nonce_url(admin_url("admin.php?page=geekybot_slots&geekybotlt=slots"),'slots');
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'slots');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        wp_redirect($url);
        die();
    }

}

$GEEKYBOTslots = new GEEKYBOTslotsController();
?>
