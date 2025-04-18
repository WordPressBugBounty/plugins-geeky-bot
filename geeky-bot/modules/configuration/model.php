<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTconfigurationModel {

    var $_data_directory = null;
    var $_config = null;
    function __construct() {

    }

    function getConfiguration() {
        do_action('geekyboot_load_wp_plugin_file');
        // check for plugin using plugin name
        if (is_plugin_active('geeky-bot/geeky-bot.php')) {
            $query = "SELECT config.* FROM `" . geekybot::$_db->prefix . "geekybot_config` AS config WHERE config.configfor = 'default'";
            $config = geekybotdb::GEEKYBOT_get_results($query);
            foreach ($config as $conf) {
                geekybot::$_configuration[$conf->configname] = $conf->configvalue;
            }
            geekybot::$_configuration['config_count'] = COUNT($config);
        }
    }

    function getConfigurationsForForm() {
        $query = "SELECT config.* FROM `" . geekybot::$_db->prefix . "geekybot_config` AS config";
        $config = geekybotdb::GEEKYBOT_get_results($query);
        foreach ($config as $conf) {
            geekybot::$_data[0][$conf->configname] = $conf->configvalue;
        }
        geekybot::$_data[0]['config_count'] = COUNT($config);
    }



    function storeConfig($data) {
        if (empty($data))
            return false;

        $error = false;
        //DB class limitations
        foreach ($data as $key => $value) {
            if ($key == 'fallback_btn_text' || 
                $key == 'fallback_btn_type' || 
                $key == 'fallback_btn_value' || 
                $key == 'fallback_btn_url' || 
                $key == 'predefined_fnction' || 
                $key == 'function_custom_heading') {
                continue;
            }
            if ($key == 'data_directory') {
                $data_directory = $value;
                if(empty($data_directory)){
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(__('Data directory can not empty.', 'geeky-bot'), 'error',$this->getMessagekey());
                    continue;
                }
                if(geekybotphplib::GEEKYBOT_strpos($data_directory, '/') !== false){
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(__('Data directory is not proper.', 'geeky-bot'), 'error',$this->getMessagekey());
                    continue;
                }
                $maindir = wp_upload_dir();
                $basedir = $maindir['basedir'];
                $path = $basedir.'/'.$data_directory;
                if ( ! function_exists( 'WP_Filesystem' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }
                global $wp_filesystem;
                if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
                    $creds = request_filesystem_credentials( site_url() );
                    wp_filesystem( $creds );
                }
                if ( ! $wp_filesystem->exists($path)) {
                   $wp_filesystem->mkdir($path, 0755);
                }
                if( ! $wp_filesystem->is_writable($path)){
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(__('Data directory is not writable.', 'geeky-bot'), 'error',$this->getMessagekey());
                    continue;
                }
            }
            if ($key == 'default_message') {
                if ($value == '') {
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(esc_html(__('Default fallback message can not be empty.', 'geeky-bot')), 'error',$this->getMessagekey());
                    continue;
                }
            }
            if ($key == 'pagination_default_page_size') {
                if ($value < 3) {
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(esc_html(__('Admin pagination not saved.', 'geeky-bot')), 'error',$this->getMessagekey());
                    continue;
                }
            }
            if ($key == 'pagination_product_page_size') {
                if ($value < 2) {
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(esc_html(__('User pagination not saved.', 'geeky-bot')), 'error',$this->getMessagekey());
                    continue;
                }
            }
            $query = 'UPDATE `' . geekybot::$_db->prefix . 'geekybot_config` SET `configvalue` = "'.esc_sql($value).'" WHERE `configname`= "' . esc_sql($key) . '"';
            if (false === geekybotdb::query($query)) {
                $error = true;
            }
        }
        // 
        if (!empty($data['predefined_fnction'])) {
            $query = 'UPDATE `' . geekybot::$_db->prefix . 'geekybot_functions` SET `custom_heading` = "'.esc_sql($data['function_custom_heading']).'" WHERE `name`= "'.esc_sql($data['predefined_fnction']).'"';
            if (false === geekybotdb::query($query)) {
                $error = true;
            }
        }
        // 
        $fallback_btn = [];
        if (!empty($data['fallback_btn_text']) && is_array($data['fallback_btn_text']) && is_array($data['fallback_btn_type'])) {
            foreach ($data['fallback_btn_text'] as $index => $text) {
                if (isset($data['fallback_btn_type'][$index]) && $text != '') {
                    $type = $data['fallback_btn_type'][$index];
                    if ($type == 1 && isset($data['fallback_btn_value'][$index]) && $data['fallback_btn_value'][$index] != '') {
                        $value = $data['fallback_btn_value'][$index];
                    } elseif ($type == 2 && isset($data['fallback_btn_url'][$index]) && $data['fallback_btn_url'][$index] != '') {
                        $value = $data['fallback_btn_url'][$index];
                    }
                    $fallback_btn[] = array(
                        'text' => $text,
                        'type' => $type,
                        'value' => $value
                    );
                }
            }
            $default_message_buttons = wp_json_encode($fallback_btn);
        } else {
            $default_message_buttons = '';
        }
        $query = 'UPDATE `' . geekybot::$_db->prefix . 'geekybot_config` SET `configvalue` = "'.esc_sql($default_message_buttons).'" WHERE `configname`= "default_message_buttons"';
        if (false === geekybotdb::query($query)) {
            $error = true;
        }
        if ($error) {
            return GEEKYBOT_CONFIGURATION_SAVE_ERROR;
        } else {
            return GEEKYBOT_CONFIGURATION_SAVED;
        }
    }

    function getConfigByFor($configfor) {
        if (!$configfor)
            return;
        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_config` WHERE configfor = '" . esc_sql($configfor) . "'";
        $config = geekybotdb::GEEKYBOT_get_results($query);
        $configs = array();
        foreach ($config as $conf) {
            $configs[$conf->configname] = $conf->configvalue;
        }
        return $configs;
    }

    function getConfigValue($configname) {
        $query = "SELECT configvalue FROM `" . geekybot::$_db->prefix . "geekybot_config` WHERE configname = '" . esc_sql($configname) . "'";
		return geekybot::$_db->get_var($query);
    }

    function getConfigurationByConfigName($configname){
        $query = "SELECT configvalue
            FROM `".geekybot::$_db->prefix."geekybot_config` WHERE configname ='" . esc_sql($configname) . "'";
        $result = geekybotdb::GEEKYBOT_get_var($query);
        return $result;
    }

    function getMessagekey(){
        $key = 'configuration';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    function getCustomHeadingForFunction() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'custom-heading-for-function') ) {
            die( 'Security check Failed' ); 
        }
        $name = GEEKYBOTrequest::GEEKYBOT_getVar('val');
        $query = "SELECT custom_heading FROM `" . geekybot::$_db->prefix . "geekybot_functions` WHERE name = '".esc_sql($name)."'";
        $heading = geekybotdb::GEEKYBOT_get_var($query);
        return $heading;
    }
}

?>
