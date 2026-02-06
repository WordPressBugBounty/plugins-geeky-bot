<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTzywrapController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('zywrap')->getMessagekey();
        $zywrap = GEEKYBOTincluder::GEEKYBOT_getModel('zywrap');
    }

    // Handles which template file to load (Settings vs. Playground)
    function handleRequest() {
        $geekybot_layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'playground');
        GEEKYBOTincluder::GEEKYBOT_include_file('zywrap', $geekybot_layout);
        if (self::canaddfile($geekybot_layout)) {
            switch ($geekybot_layout) {
                case 'zywrap': // zywrap
                case 'admin_zywrap': // admin_zywrap
                    break;
                case 'playground': // admin_playground
                case 'admin_playground': // admin_playground
                    // This is our new Playground page, load all the dropdown data
                    // Calls getPlaygroundData() in GEEKYBOTzywrapController
                    GEEKYBOTincluder::GEEKYBOT_getModel('zywrap')->getPlaygroundData();
                    break;
                default:
                    exit;
            }
            $geekybot_module = (is_admin()) ? 'page' : 'geekybotme';
            $geekybot_module = GEEKYBOTrequest::GEEKYBOT_getVar($geekybot_module, null, 'zywrap');
            $geekybot_module = geekybotphplib::GEEKYBOT_str_replace('geekybot_', '', $geekybot_module);
            GEEKYBOTincluder::GEEKYBOT_include_file($geekybot_layout, $geekybot_module);
        }
    }

    function canaddfile($geekybot_layout) {
        $geekybot_nonce_value = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot_nonce');
        if ( wp_verify_nonce( $geekybot_nonce_value, 'geekybot_nonce') ) {
            if (isset($_POST['form_request']) && $_POST['form_request'] == 'geekybot')
                return false;
            elseif (isset($_GET['action']) && $_GET['action'] == 'geekybottask')
                return false;
            else{
                if(!is_admin() && strpos($geekybot_layout, 'admin_') === 0){
                    return false;
                }
                return true;
            }
        }
    }

    // Handles the "Save Key" form submission
    function save_zywrap_settings() {
        if (!current_user_can('manage_options')) {
            die('Security check failed');
        }

        $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (!wp_verify_nonce($geekybot_nonce, 'save-zywrap-settings')) {
            die('Security check Failed');
        }
        $api_key = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot_zywrap_api_key', 'post');

        update_option('geekybot_zywrap_api_key', sanitize_text_field($api_key));

        // Use GeekyBot's message system to show "Saved!"
        $geekybot_msg = GEEKYBOTMessages::GEEKYBOT_getMessage(GEEKYBOT_SAVED, 'configuration');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($geekybot_msg['message'], $geekybot_msg['status'], $this->_msgkey);

        $geekybot_url = admin_url("admin.php?page=geekybot_zywrap&geekybotlt=zywrap");
        // Safe redirect
        wp_safe_redirect($geekybot_url);
        exit;
    }
}

$GEEKYBOTzywrapController = new GEEKYBOTzywrapController();
/*
add_action('add_meta_boxes', ('add_zywrap_meta_box'));
// === NEW: META BOX FUNCTIONS ===

    /**
     * Registers the meta box on posts, pages, and products.
     *
     */
 /*   function add_zywrap_meta_box() {
        // Only show the box if the API key is set
        if (!get_option('geekybot_zywrap_api_key', '')) {
            return;
        }

        $post_types = array('post', 'page', 'product'); // Target these post types
        foreach ($post_types as $post_type) {
            add_meta_box(
                'geekybot_zywrap_meta_box', // ID
                __('Zywrap Content Generator', 'geeky-bot'), // Title
                array($this, 'geekybot_render_zywrap_meta_box'), // Callback
                $post_type, // Screen
                'side', // Context (sidebar)
                'high' // Priority
            );
        }
    }
*/
?>
