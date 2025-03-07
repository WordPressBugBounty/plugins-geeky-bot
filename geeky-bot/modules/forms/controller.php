<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTformsController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('forms')->getMessagekey();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'forms' , null);
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_forms':
                    GEEKYBOTincluder::GEEKYBOT_getModel('forms')->getAllForms();
                    break;
                case 'admin_formforms':
                    $id = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotid');
                    GEEKYBOTincluder::GEEKYBOT_getModel('forms')->getFormsbyId($id);
                    break;
                default:
                    exit;
            }

            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'forms');
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
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'delete-forms') ) {
            die( 'Security check Failed' ); 
        }
        $ids = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot-cb');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('forms')->deleteForms($ids);

        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'forms');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        $url = wp_nonce_url(admin_url("admin.php?page=geekybot_forms&geekybotlt=forms"),'forms');
        wp_redirect($url);
        die();
    }

    function saveforms() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-forms') ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('forms')->storeForms($data);
        $url = wp_nonce_url(admin_url("admin.php?page=geekybot_forms&geekybotlt=forms"),'forms');
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'forms');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        wp_redirect($url);
        die();
    }

}

$GEEKYBOTforms = new GEEKYBOTformsController();
?>
