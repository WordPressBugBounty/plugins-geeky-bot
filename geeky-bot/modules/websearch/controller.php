<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTwebsearchController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->getMessagekey();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'websearch', null);
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_websearch':
                    GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->getAllWebSearch();
                    break;
                case 'admin_formwebsearch':
                    $id = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotid');
                    GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->getWebSearchbyId($id);
                    break;
                default:
                    exit;
            }

            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'websearch');
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

    function savewebsearch() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-websearch') ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->storeCustomPostType($data);
        $url = wp_nonce_url(admin_url("admin.php?page=geekybot_websearch&geekybotlt=websearch"),'websearch');
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'posttype');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        wp_redirect($url);
        die();
    }

    function savecustomlisting() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-custom-listing') ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->storeCustomListing($data);
        $url = wp_nonce_url(admin_url("admin.php?page=geekybot_websearch&geekybotlt=websearch"),'websearch');
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'posttype');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        wp_redirect($url);
        die();
    }

    function changeStatus() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'change-status') ) {
            die( 'Security check Failed' );
        }
        $status = GEEKYBOTrequest::GEEKYBOT_getVar('status');
        $id = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->changeStatus($status, $id);
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'posttype');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        $url = admin_url("admin.php?page=geekybot_websearch&geekybotlt=websearch");
        wp_redirect($url);
        die();
    }

    function synchronizeWebSearchData() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'synchronize-data') ) {
            die( 'Security check Failed' );
        }
        update_option('geekybot_websearch_synchronization_flag', 1);
    }

}

$GEEKYBOTwebsearch = new GEEKYBOTwebsearchController();
?>
