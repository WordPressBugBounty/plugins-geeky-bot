<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/* Update for custom plugins by joomsky */
class GEEKYBOT_Updater {

	private $api_key = '';
	private $addon_update_data = array();
	private $addon_update_data_errors = array();
	public $addon_installed_array = '';// it is public static bcz it is being used in extended class

	public $addon_installed_version_data = '';// it is public static bcz it is being used in extended class

	public function __construct() {
		$this->GEEKYBOT_updateIntilized();

		$transaction_key_array = array();
		$addon_installed_array = array();
		foreach (geekybot::$_active_addons AS $addon) {
			$addon_installed_array[] = 'geeky-bot-'.$addon;
			$option_name = 'transaction_key_for_geeky-bot-'.$addon;
			$transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);
			if(!in_array($transaction_key, $transaction_key_array)){
				$transaction_key_array[] = $transaction_key;
			}
		}
		$this->addon_installed_array = $addon_installed_array;
		$this->api_key = wp_json_encode($transaction_key_array);
	}

	// class constructor triggers this function. sets up intail hooks and filters to be used.
	public function GEEKYBOT_updateIntilized(  ) {
		add_action( 'admin_init', array( $this, 'GEEKYBOT_adminIntilization' ) );
		include_once( 'class-geekybot-server-calls.php' );
	}

	// admin init hook triggers this fuction. sets up admin specific hooks and filter
	public function GEEKYBOT_adminIntilization() {

		add_filter( 'plugins_api', array( $this, 'GEEKYBOT_pluginsAPI' ), 10, 3 );

		if ( current_user_can( 'update_plugins' ) ) {
			$this->GEEKYBOT_checkTriggers();
			add_action( 'admin_notices', array( $this, 'GEEKYBOT_checkUpdateNotice' ) );
			add_action( 'after_plugin_row', array( $this, 'GEEKYBOT_keyInput' ) );
		}
	}

	public function GEEKYBOT_keyInput( $file ) {
		$file_array = geekybotphplib::GEEKYBOT_explode('/', $file);
		$addon_slug = $file_array[0];
		if(geekybotphplib::GEEKYBOT_strstr($addon_slug, 'geeky-bot-')){
			$addon_name = geekybotphplib::GEEKYBOT_str_replace('geeky-bot-', '', $addon_slug);
			if(isset($this->addon_update_data[$file]) || !in_array($addon_name, geekybot::$_active_addons)){ // Only checking which addon have update version
				$option_name = 'transaction_key_for_geeky-bot-'.$addon_name;
				$transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);
				$verify_results = GEEKYBOTincluder::GEEKYBOT_getModel('premiumplugin')->activate( array(
		            'token'    => $transaction_key,
		            'plugin_slug'    => $addon_name
		        ) );
		        if(isset($verify_results['verfication_status']) && $verify_results['verfication_status'] == 0){
		        	$updateaddon_slug = geekybotphplib::GEEKYBOT_str_replace("-", " ", $addon_slug);
		        	$message = geekybotphplib::GEEKYBOT_strtoupper( geekybotphplib::GEEKYBOT_substr( $updateaddon_slug, 0, 2 ) ).geekybotphplib::GEEKYBOT_substr(  geekybotphplib::GEEKYBOT_ucwords($updateaddon_slug), 2 ) .' authentication failed. Please insert valid key for authentication.';
		        	if(isset($this->addon_update_data[$file])){
		        		$message = 'There is new version of '. wp_kses(geekybotphplib::GEEKYBOT_strtoupper( geekybotphplib::GEEKYBOT_substr( $updateaddon_slug, 0, 2 ) ), GEEKYBOT_ALLOWED_TAGS).wp_kses(geekybotphplib::GEEKYBOT_substr(  geekybotphplib::GEEKYBOT_ucwords($updateaddon_slug), 2 ), GEEKYBOT_ALLOWED_TAGS) .' avaible. Please insert valid activation key for updation.';
		        		remove_action('after_plugin_row_'.$file,'wp_plugin_update_row');
					}
		        	include( 'views/html-key-input.php' );
		        	$html = '
					<tr>
						<td class="plugin-update plugin-update colspanchange" colspan="3">
							<div class="update-message notice inline notice-error notice-alt"><p>'. esc_html($message) .'</p></div>
						</td>
					</tr>';
					echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS) ;
		        }
			}
		}
	}

	public function GEEKYBOT_checkVersionUpdate( $update_data ) {
		if ( empty( $update_data->checked ) ) {
			return $update_data;
		}
		$response_version_data = get_transient('geekybot_addon_update_temp_data');
		$response_version_data_cdn = get_transient('geekybot_addon_update_temp_data_cdn');

		if(isset($_SERVER) &&  $_SERVER['REQUEST_URI'] !=''){
            if(geekybotphplib::GEEKYBOT_strstr( $_SERVER['REQUEST_URI'], 'plugins.php')) {
				$response_version_data = get_transient('geekybot_addon_update_temp_data_plugins');
				$response_version_data_cdn = get_transient('geekybot_addon_update_temp_data_plugins_cdn');
			}
        }

		if($response_version_data_cdn === false){
			$cdnversiondata = $this->GEEKYBOT_getPluginVersionDataFromCDN();
			set_transient('geekybot_addon_update_temp_data_cdn', $cdnversiondata, HOUR_IN_SECONDS * 6);
			set_transient('geekybot_addon_update_temp_data_plugins_cdn', $cdnversiondata, 15);
		}else{
			$cdnversiondata = $response_version_data_cdn;
		}
		$newversionfound = 0;
		if ( $cdnversiondata) {
			if(is_object($cdnversiondata) ){
				foreach ($update_data->checked AS $key => $value) {
					$c_key_array = geekybotphplib::GEEKYBOT_explode('/', $key);
					$c_key = $c_key_array[0];
					if($c_key != ''){
						$c_key = geekybotphplib::GEEKYBOT_str_replace("-","",$c_key);
					}
					$newversion = $this->GEEKYBOT_getVersionFromLiveData($cdnversiondata, $c_key);
					if($newversion){
						if(version_compare( $newversion, $value, '>' )){
							$newversionfound = 1;
						}
					}
				}
			}
		}

		if($newversionfound == 1){
			if($response_version_data === false){
				$response = $this->GEEKYBOT_getPluginVersionData();
				set_transient('geekybot_addon_update_temp_data', $response, HOUR_IN_SECONDS * 6);
				set_transient('geekybot_addon_update_temp_data_plugins', $response, 15);
			}else{
				$response = $response_version_data;
			}
			if ( $response) {
				if(is_object($response) ){
					if(isset($response->addon_response_type) && $response->addon_response_type == 'no_key'){
						foreach ($update_data->checked AS $key => $value) {
							$c_key_array = geekybotphplib::GEEKYBOT_explode('/', $key);
							$c_key = $c_key_array[0];
							if(isset($response->addon_version_data->{$c_key})){
								if(version_compare( $response->addon_version_data->{$c_key}, $value, '>' )){
									$transient_val = get_transient('geekybot_addon_hide_update_notice');
									if($transient_val === false){
										set_transient('geekybot_addon_hide_update_notice', 1, DAY_IN_SECONDS );
									}
									$this->addon_update_data[$key] = $response->addon_version_data->{$c_key};
								}
							}
						}
					}else{// addon_response_type other than no_key
						foreach ($update_data->checked AS $key => $value) {
							$c_key_array = geekybotphplib::GEEKYBOT_explode('/', $key);
							$c_key = $c_key_array[0];
							if(isset($response->addon_update_data) && !empty($response->addon_update_data) && isset( $response->addon_update_data->{$c_key})){
								if(version_compare( $response->addon_update_data->{$c_key}->new_version, $value, '>' )){
									$update_data->response[ $key ] = $response->addon_update_data->{$c_key};
									$this->addon_update_data[$key] = $response->addon_update_data->{$c_key};
								}
							}elseif(isset($response->addon_version_data->{$c_key})){
								if(version_compare( $response->addon_version_data->{$c_key}, $value, '>' )){
									$transient_val = get_transient('geekybot_addon_hide_update_expired_key_notice');
									if($transient_val === false){
										set_transient('geekybot_addon_hide_update_expired_key_notice', 1, DAY_IN_SECONDS );
									}
									$this->addon_update_data_errors[$key] = $response->addon_version_data->{$c_key};
									$this->addon_update_data[$key] = $response->addon_version_data->{$c_key};
								}
							}else{ // set latest version from cdn data
								if ( $cdnversiondata) {
									if(is_object($cdnversiondata) ){
										$c_key_plain = geekybotphplib::GEEKYBOT_str_replace("-","",$c_key);
										$newversion = $this->GEEKYBOT_getVersionFromLiveData($cdnversiondata, $c_key_plain);
										if($newversion){
											if(version_compare( $newversion, $value, '>' )){

												$option_name = 'transaction_key_for_'.$c_key;
												$transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);
												$addon_json_array = array();
												$addon_json_array[] = geekybotphplib::GEEKYBOT_str_replace('geeky-bot-', '', $c_key);
												$url = 'https://geekybot.com/setup/index.php?token='.$transaction_key.'&productcode='. wp_json_encode($addon_json_array).'&domain='. site_url();

												// prepping data for seamless update of allowed addons
												$plugin = new stdClass();
												$plugin->id = 'w.org/plugins/geeky-bot';
												$addon_slug = $c_key;
												$plugin->name = $addon_slug;
												$plugin->plugin = $addon_slug.'/'.$addon_slug.'.php';
												$plugin->slug = $addon_slug;
												$plugin->version = '1.0.1';
												$addonwithoutslash = geekybotphplib::GEEKYBOT_str_replace('-', '', $addon_slug);
												$plugin->new_version = $newversion; 
												$plugin->url = 'https://www.geekybot.com/';
												$plugin->download_url = $url;
												$plugin->package = $url;
												$plugin->trunk = $url;
												
												$update_data->response[ $key ] = $plugin;
												$this->addon_update_data[$key] = $plugin;
											}
										}

									}
								}
							}
						}
					}
				}
			}
		}// new version found	
		if(isset($update_data->checked)){
			$this->addon_installed_version_data = $update_data->checked;
		}
		return $update_data;
	}

	public function GEEKYBOT_pluginsAPI( $false, $action, $args ) {

		if (!isset( $args->slug )) {
			return false;
		}

		if(geekybotphplib::GEEKYBOT_strstr($args->slug, 'geeky-bot-')){
			$response = $this->GEEKYBOT_getPluginInfo($args->slug);
			if ($response) {
				$response->sections = json_decode(wp_json_encode($response->sections),true);
				$response->banners = json_decode(wp_json_encode($response->banners),true);
				$response->contributors = json_decode(wp_json_encode($response->contributors),true);
				return $response;
			}
		}else{
			return false;// to handle the case of plugins that need to check version data from wordpress repositry.
		}
	}

	public function GEEKYBOT_getPluginInfo($addon_slug) {

		$option_name = 'transaction_key_for_'.$addon_slug;
		$transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);

		if(!$transaction_key){
			die('transient');
			return false;
		}

		$plugin_file_path = content_url().'/plugins/'.$addon_slug.'/'.$addon_slug.'.php';
		$plugin_data = get_plugin_data($plugin_file_path);

		$response = GEEKYBOT_SupportTicketServerCalls::GEEKYBOT_PluginInformation( array(
			'plugin_slug'    => $addon_slug,
			'version'        => $plugin_data['Version'],
			'token'    => $transaction_key,
			'domain'          => site_url()
		) );
		if ( isset( $response->errors ) ) {
			$this->handle_errors( $response->errors );
		}

		// If everything is okay return the $response
		if ( isset( $response ) && is_object( $response ) && $response !== false ) {
			return $response;
		}

		return false;
	}

	// does changes according to admin triggers.
	private function GEEKYBOT_checkTriggers() {
		// $nonce = $_POST['_wpnonce'];
        // if (! wp_verify_nonce( $nonce, 'update-plugins') ) {
        //     die( 'Security check Failed' );
        // }
		if ( isset($_POST['geekybot_addon_array_for_token']) && ! empty( $_POST[ 'geekybot_addon_array_for_token' ])){
			$transaction_key = '';
			$addon_name = '';
			foreach ($_POST['geekybot_addon_array_for_token'] as $key => $value) {
				if(isset($_POST[$value.'_transaction_key']) && $_POST[$value.'_transaction_key'] != ''){
					$transaction_key = geekybot::GEEKYBOT_sanitizeData($_POST[$value.'_transaction_key']);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
					$addon_name = $value;
					break;
				}
			}

			if($transaction_key != ''){
				$token = $this->GEEKYBOT_getTokenFromTransactionKey( $transaction_key,$addon_name);
				if($token){
					foreach ($_POST['geekybot_addon_array_for_token'] as $key => $value) {
						update_option('transaction_key_for_'.$value,$token);
					}
				}else{
					update_option( 'geekybot-addon-key-error-message','Something went wrong');
				}
			}
		}else{
			foreach ($this->addon_installed_array as $key) {
				if ( ! empty( $_GET[ 'dismiss-geekybot-addon-update-notice-'.$key] ) ) {
					set_transient('dismiss-geekybot-addon-update-notice-'.$key, 1, DAY_IN_SECONDS );
				}
			}
		}
	}

	public function GEEKYBOT_checkUpdateNotice( ) {
		include_once( 'views/html-update-availble.php' );
	}

	public function GEEKYBOT_getPluginVersionData() {
			$response = GEEKYBOT_SupportTicketServerCalls::GEEKYBOT_PluginUpdateCheck($this->api_key);
			if ( isset( $response->errors ) ) {
				$this->geekybotHandleErrors( $response->errors );
			}

			// Set version variables
			if ( isset( $response ) && is_object( $response ) && $response !== false ) {
				return $response;
			}
		return false;
	}

	public function GEEKYBOT_getPluginVersionDataFromCDN() {
			$response = GEEKYBOT_SupportTicketServerCalls::GEEKYBOT_PluginUpdateCheckFromCDN();
			if ( isset( $response->errors ) ) {
				$this->geekybotHandleErrors( $response->errors );
			}

			// Set version variables
			if ( isset( $response ) && is_object( $response ) && $response !== false ) {
				return $response;
			}
		return false;
	}


	private function GEEKYBOT_getVersionFromLiveData($data, $addon_name){
		foreach ($data as $key => $value) {
			if($key == $addon_name){
				return $value;
			}
		}
		return;
	}
	public function GEEKYBOT_getPluginLatestVersionData() {
		$response = GEEKYBOT_SupportTicketServerCalls::GEEKYBOT_GetLatestVersions();
		// Set version variables
		if ( isset( $response ) && is_array( $response ) && $response !== false ) {
			return $response;
		}
		return false;
	}

	public function GEEKYBOT_getTokenFromTransactionKey($transaction_key,$addon_name) {
		$response = GEEKYBOT_SupportTicketServerCalls::GEEKYBOT_GenerateToken($transaction_key,$addon_name);
		// Set version variables
		if (is_array($response) && isset($response['verfication_status']) && $response['verfication_status'] == 1 ) {
			return $response['token'];
		}else{
			$error_message = esc_html(__('Something went wrong','geeky-bot'));
			if(is_array($response) && isset($response['error'])){
				$error_message = $response['error'];
			}
			update_option( 'geekybot-addon-key-error-message',$error_message);
		}
		return false;
	}
}
?>
