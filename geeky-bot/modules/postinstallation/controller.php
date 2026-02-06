<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTpostinstallationController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->getMessagekey();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'stepone', null);
        if($this->canaddfile()){
            switch ($layout) {
                case 'admin_welcomescreen':
                    GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->updateInstallationStatusConfiguration();
                    $geekybot_callfrom = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot_callfrom');
                    break;  
                case 'admin_stepzero':
                    break;
                case 'admin_stepone':
                    GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->updateInstallationStatusConfiguration();
                    $geekybot_callfrom = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot_callfrom');
                    GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->checkIfStoryAlreadyEnabled(1, $geekybot_callfrom);
                    break;
                case 'admin_steptwo':
                    GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->updateInstallationStatusConfiguration();
                    $geekybot_callfrom = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot_callfrom');
                    GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->checkIfStoryAlreadyEnabled(2, $geekybot_callfrom);
                    break;
                case 'admin_stepthree':
                    GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->updateInstallationStatusConfiguration();
                    $geekybot_callfrom = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot_callfrom');
                    GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->checkIfStoryAlreadyEnabled(3, $geekybot_callfrom);
                    break;
                default:
                    exit;
            }
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'postinstallation');
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

    function save(){
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save') ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->storeInstallationData($data);
        if($data['step'] == 1) {
            if (isset($result) && $result != '') {
                GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($result, 'error',$this->_msgkey);
                $url = admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=nextlink");
            } else {
                $url = admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=steptwo&geekybot_callfrom=nextlink");
            }
        } elseif($data['step'] == 2) {
            $url = admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepthree&geekybot_callfrom=nextlink");
        } elseif($data['step'] == 3) {
            $url = admin_url("admin.php?page=geekybot");
        }
        wp_redirect($url);
        exit();
    }
}
$GEEKYBOTpostinstallationController = new GEEKYBOTpostinstallationController();
?>
