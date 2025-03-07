<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTThemesController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('themes')->getMessagekey();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'themes', null);
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_themes':
                    GEEKYBOTincluder::GEEKYBOT_getModel('themes')->getCurrentTheme();
                    GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigurationsForForm();
                    break;
                default:
                    exit;
            }
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'themes');
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
    
    function savetheme() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-theme') ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('themes')->storeTheme($data);
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, "themes");
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        $url = wp_nonce_url(admin_url("admin.php?page=geekybot_themes&geekybotlt=themes"),'themes');
        wp_redirect($url);
        exit;
    }


}

$GEEKYBOTThemesController = new GEEKYBOTThemesController();
?>
