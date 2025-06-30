<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTConfigurationController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getMessagekey();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'configurations', null);
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_configurations':
                case 'admin_ai_configurations':
                case 'admin_dialogflow_configurations':
                case 'admin_openai_configurations':
                case 'admin_openrouter_configurations':
                    GEEKYBOTincluder::GEEKYBOT_getModel('themes')->getCurrentTheme();
                    GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigurationsForForm();
                    break;
                default:
                    exit;
            }
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'configurations');
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

    function saveconfiguration() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-configuration') ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $layout = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotlt');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->storeConfig($data);
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, "configuration");
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        // $url = wp_nonce_url(admin_url("admin.php?page=geekybot_configuration&geekybotlt=" . $layout),'configuration');
        $url = admin_url("admin.php?page=geekybot_configuration&geekybotlt=" . $layout);
        wp_redirect($url);
        die();
    }

    function geekybotExportStoryAndPost() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $id = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot-storyid');
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'export-story-post') ) {
            die( 'Security check Failed' );
        }

        $selected_types = [];
        $upload_story = GEEKYBOTrequest::GEEKYBOT_getVar('upload_story');
        $upload_post = GEEKYBOTrequest::GEEKYBOT_getVar('upload_post');

        if (!empty($upload_story)) {
            $selected_types[] = 'story';
        }

        if (!empty($upload_post)) {
            $selected_types[] = 'post';
        }

        update_option('openai_assistant_upload_types', $selected_types);


        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $layout = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotlt');
        if (!isset($data['callfrom']) || $data['callfrom'] == null) {
            $data['callfrom'] = $callfrom = GEEKYBOTrequest::GEEKYBOT_getVar('callfrom');
        }
        $result = geekybot::$_geekybotopenaiassistant->geekybot_exportAndPrepareData();
        if ($result === true) {
            // Handle success
            GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(__('OpenAI Assistant setup completed successfully.', 'geeky-bot'), 'updated', $this->_msgkey);
        } elseif ($result === false) {
            // Handle failure
            GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(__('OpenAI Assistant setup failed. Please try again.', 'geeky-bot'), 'error', $this->_msgkey);
        } else {
            // Handle unexpected return values (optional)
            GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($result, 'error', $this->_msgkey);
        }
        
        
        $url = admin_url("admin.php?page=geekybot_configuration&geekybotlt=" . $layout);
        wp_redirect($url);
        die();
    }

}

$GEEKYBOTConfigurationController = new GEEKYBOTConfigurationController();
?>
