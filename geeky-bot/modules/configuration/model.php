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



}

?>
