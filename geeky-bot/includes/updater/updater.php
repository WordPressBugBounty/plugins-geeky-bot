<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

do_action('geekyboot_load_wp_plugin_file');
// check for plugin using plugin name
if (is_plugin_active('geeky-bot/geeky-bot.php')) {
	$query = "SELECT * FROM `".geekybot::$_db->prefix."geekybot_config` WHERE configname = 'versioncode' OR configname = 'last_version' OR configname = 'last_step_updater'";
	$result = geekybot::$_db->get_results($query);
	$config = array();
	foreach($result AS $rs){
		$config[$rs->configname] = $rs->configvalue;
	}
	$config['versioncode'] = geekybotphplib::GEEKYBOT_str_replace('.', '', $config['versioncode']);
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    global $wp_filesystem;
    if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
        $creds = request_filesystem_credentials( site_url() );
        wp_filesystem( $creds );
    }
	if(!empty($config['last_version']) && $config['last_version'] != '' && $config['last_version'] < $config['versioncode']){
		$last_version = $config['last_version'] + 1; // files execute from the next version
		$currentversion = $config['versioncode'];
		for($i = $last_version; $i <= $currentversion; $i++){
			$path = GEEKYBOT_PLUGIN_PATH.'includes/updater/files/'.$i.'.php';
			if($wp_filesystem->exists($path)){
				include_once($path);
			}
		}
	}
	$mainfile = GEEKYBOT_PLUGIN_PATH.'geeky-bot.php';
	$contents_file = wp_remote_get($mainfile);
	if (is_wp_error($contents_file)) {
    	$contents = '';
    }else{
    	$contents = $contents_file['body'];
    }
	$contents = geekybotphplib::GEEKYBOT_str_replace("include_once 'includes/updater/updater.php';", '', $contents);
	if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
    if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
        $creds = request_filesystem_credentials( site_url() );
        wp_filesystem( $creds );
    }
	$wp_filesystem->put_contents($mainfile, $contents);

	function GEEKYBOT_recursiveremove($dir) {
		$structure = glob(geekybotphplib::GEEKYBOT_rtrim($dir, "/").'/*');
		if (is_array($structure)) {
			foreach($structure as $file) {
				if (is_dir($file)) GEEKYBOT_recursiveremove($file);
				elseif (is_file($file)) wp_delete_file($file);
			}
		}
		if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
            $creds = request_filesystem_credentials( site_url() );
            wp_filesystem( $creds );
        }
		$wp_filesystem->rmdir($dir);
	}
	$dir = GEEKYBOT_PLUGIN_PATH.'includes/updater';
	GEEKYBOT_recursiveremove($dir);

}



?>
