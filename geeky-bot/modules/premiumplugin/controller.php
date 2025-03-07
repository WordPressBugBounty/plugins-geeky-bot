<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class GEEKYBOTPremiumpluginController {

    function __construct() {
        self::handleRequest();
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'step1', null);
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_step1':
                    geekybot::$_data['versioncode'] = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigurationByConfigName('versioncode');
                    geekybot::$_data['productcode'] = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigurationByConfigName('productcode');
                    geekybot::$_data['producttype'] = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigurationByConfigName('producttype');
                break;
                case 'admin_step2':
                break;
                case 'admin_step3':
                break;
                case 'admin_addonfeatures':
                break;
                case 'admin_addonstatus':
                break;
                case 'admin_missingaddon':
                break;
                default:
                    exit;
            }
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'premiumplugin');
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

    function verifytransactionkey(){
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'verify-transaction-key') ) {
            die( 'Security check Failed' );
        }
        $post_data['transactionkey'] = GEEKYBOTrequest::GEEKYBOT_getVar('transactionkey','','');
        if($post_data['transactionkey'] != ''){


            $post_data['domain'] = site_url();
            $post_data['step'] = 'one';
            $post_data['myown'] = 1;

            $url = 'https://geekybot.com/setup/index.php';

            $response = wp_remote_post( $url, array('body' => $post_data,'timeout'=>7,'sslverify'=>false));
            if( !is_wp_error($response) && $response['response']['code'] == 200 && isset($response['body']) ){
                $result = $response['body'];
                $result = json_decode($result,true);
            }else{
                $result = false;
                if(!is_wp_error($response)){
                   $error = $response['response']['message'];
                }else{
                    $error = $response->get_error_message();
                }
            }
            if(is_array($result) && isset($result['status']) && $result['status'] == 1 ){ // means everthing ok
                $resultaddon = wp_json_encode($result);
                $resultaddon = geekybotphplib::GEEKYBOT_safe_encoding( $resultaddon );
                $result['actual_transaction_key'] = $post_data['transactionkey'];
                // in case of session not working
                add_option('geekybot_addon_install_data',wp_json_encode($result));
                $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step2");
                wp_redirect($url);
                return;
            }else{
                if(isset($result[0]) && $result[0] == 0){
                    $error = $result[1];
                }elseif(isset($result['error']) && $result['error'] != ''){
                    $error = $result['error'];
                }
            }
        }else{
            $error = esc_html(__('Please insert activation key to proceed','geeky-bot')).'!';
        }
        $array['data'] = array();
        $array['status'] = 0;
        $array['message'] = $error;
        $array['transactionkey'] = $post_data['transactionkey'];
        $array = wp_json_encode( $array );
        $array = geekybotphplib::GEEKYBOT_safe_encoding($array);
        geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, COOKIEPATH);
        if ( SITECOOKIEPATH != COOKIEPATH ){
            geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, SITECOOKIEPATH);
        }
        $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step1");
        wp_redirect($url);
        return;
    }

    function downloadandinstalladdons(){
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'download-and-install-addons') ) {
            die( 'Security check Failed' );
        }
        $post_data = GEEKYBOTrequest::GEEKYBOT_get('post');

        $addons_array = $post_data;
        if(isset($addons_array['token'])){
            unset($addons_array['token']);
        }
        $addon_json_array = array();

        foreach ($addons_array as $key => $value) {
            if($key != ''){
                $addon_json_array[] = geekybotphplib::GEEKYBOT_str_replace('geeky-bot-', '', $key);
            }
        }
        $token = $post_data['token'];
        if($token == ''){
            $array['data'] = array();
            $array['status'] = 0;
            $array['message'] = esc_html(__('Addon Installation Failed','geeky-bot')).'!';
            $array['transactionkey'] = $post_data['transactionkey'];
            $array = wp_json_encode( $array );
            $array = geekybotphplib::GEEKYBOT_safe_encoding($array);
            geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, COOKIEPATH);
            if ( SITECOOKIEPATH != COOKIEPATH ){
                geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, SITECOOKIEPATH);
            }
            $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step1");
            wp_redirect($url);
            exit;
        }
        $site_url = site_url();
        if($site_url != ''){
		    $site_url = geekybotphplib::GEEKYBOT_str_replace("https://","",$site_url);
            $site_url = geekybotphplib::GEEKYBOT_str_replace("http://","",$site_url);
        }
        $url = 'https://geekybot.com/setup/index.php?token='.esc_attr($token).'&productcode='. wp_json_encode($addon_json_array).'&domain='. esc_attr($site_url);

        $install_count = 0;

        $installed = $this->install_plugin($url);
        if ( !is_wp_error( $installed ) && $installed ) {
            // had to run two seprate loops to save token for all the addons even if some error is triggered by activation.
            foreach ($post_data as $key => $value) {
                if(geekybotphplib::GEEKYBOT_strstr($key, 'geeky-bot-')){
                    update_option('transaction_key_for_'.$key,$token);
                }
            }

            foreach ($post_data as $key => $value) {
                if(geekybotphplib::GEEKYBOT_strstr($key, 'geeky-bot-')){
                    $activate = activate_plugin( $key.'/'.$key.'.php' );
                    $install_count++;
                }
            }

        }else{
            $array['data'] = array();
            $array['status'] = 0;
            $array['message'] = esc_html(__('Addon Installation Failed','geeky-bot')).'!';
            $array['transactionkey'] = $post_data['transactionkey'];
            $array = wp_json_encode( $array );
            $array = geekybotphplib::GEEKYBOT_safe_encoding($array);
            geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, COOKIEPATH);
            if ( SITECOOKIEPATH != COOKIEPATH ){
                geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, SITECOOKIEPATH);
            }

            $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step1");
            wp_redirect($url);
            exit;
        }
        $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step3");
        wp_redirect($url);
    }

    function install_plugin( $plugin_zip ) {

        do_action('geekyboot_load_wp_admin_file');
        WP_Filesystem();
        $tmpfile = download_url( $plugin_zip);

        if ( !is_wp_error( $tmpfile ) && $tmpfile ) {
            $plugin_path = WP_CONTENT_DIR;
            $plugin_path = $plugin_path.'/plugins/';
            $path = GEEKYBOT_PLUGIN_PATH.'addon.zip';
            copy( $tmpfile, $path );
            $unzipfile = unzip_file( $path, $plugin_path);

            if ( file_exists( $path ) ) {
                wp_delete_file( $path ); // must unlink afterwards
            }
            if ( file_exists( $tmpfile ) ) {
                wp_delete_file( $tmpfile ); // must unlink afterwards
            }

            if ( is_wp_error( $unzipfile ) ) {
                $array['data'] = array();
                $array['status'] = 0;
                $array['message'] = esc_html(__('Addon installation failed','geeky-bot')).'.';
                $array['message'] .= " ".wp_kses(geekybot::GEEKYBOT_getVarValue($unzipfile->get_error_message(), GEEKYBOT_ALLOWED_TAGS));
                $array['transactionkey'] = $post_data['transactionkey'];
                $array = wp_json_encode( $array );
                $array = geekybotphplib::GEEKYBOT_safe_encoding($array);
                geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, COOKIEPATH);
                if ( SITECOOKIEPATH != COOKIEPATH ){
                    geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, SITECOOKIEPATH);
                }

                $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step1");
                wp_redirect($url);
                exit;
            } else {
                return true;
            }
        }else{
            $array['data'] = array();
            $array['status'] = 0;
            $error_string = $tmpfile->get_error_message();
            $array['message'] = esc_html(__('Addon Installation Failed, File download error','geeky-bot')).'! '.$error_string;
            $array['transactionkey'] = $post_data['transactionkey'];
            $array = wp_json_encode( $array );
            $array = geekybotphplib::GEEKYBOT_safe_encoding($array);
            geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, COOKIEPATH);
            if ( SITECOOKIEPATH != COOKIEPATH ){
                geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , $array , 0, SITECOOKIEPATH);
            }
            $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step1");
            wp_redirect($url);
            exit;
        }
    }
}
$GEEKYBOTPremiumpluginController = new GEEKYBOTPremiumpluginController();
?>
