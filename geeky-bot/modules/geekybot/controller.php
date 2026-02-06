<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTgeekybotController {

    function __construct() {
        self::handleRequest();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'controlpanel', null);
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_controlpanel':
                    include_once GEEKYBOT_PLUGIN_PATH . 'includes/updates/updates.php';
                    GEEKYBOTupdates::GEEKYBOT_checkUpdates(119);
                    GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getAdminControlPanelData();
                    // remove this code in 1.1.7
                    $uploadDir = wp_upload_dir();
                    if (geekybot::$_configuration['ai_provider'] == 2 && !file_exists($uploadDir['basedir'] . '/geekybotLibraries/dialogFlow/geekybot_google_client-main/autoload.php')) {
                        GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotDownloadGoogleClientLibrary(0);
                    }
                    // remove this code in 1.1.7
                    break;
                default:
                    exit;
            }
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'geekybot');
            $module = geekybotphplib::GEEKYBOT_str_replace('geekybot_', '', $module);
            if($layout=="thankyou"){
                if($module=="" || $module!="geekybot") $module="geekybot";
            }
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

$GEEKYBOTgeekybotController = new GEEKYBOTgeekybotController();
?>
