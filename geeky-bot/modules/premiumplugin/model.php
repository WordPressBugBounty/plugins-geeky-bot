<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTpremiumpluginModel {

    private static $server_url = 'https://geekybot.com/setup/index.php';

    function verfifyAddonActivation($addon_name){
        $option_name = 'env_signature_geeky-bot-'.esc_attr($addon_name);
        $transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);
        try {
            if (! $transaction_key ) {
                throw new Exception( 'License key not found' );
            }
            if ( empty( $transaction_key ) ) {
                throw new Exception( 'License key not found' );
            }
            $activate_results = $this->activate( array(
                'token'    => $transaction_key,
                'plugin_slug'    => $addon_name
            ) );
            if ( false === $activate_results ) {
                throw new Exception( 'Connection failed to the server' );
            } elseif ( isset( $activate_results['error_code'] ) ) {
                throw new Exception( $activate_results['error'] );
            } elseif(isset($activate_results['verfication_status']) && $activate_results['verfication_status'] == 1 ){
                return true;
            }
            throw new Exception( 'License could not activate. Please contact support.' );
        } catch ( Exception $e ) {
            $data = '<div class="notice notice-error is-dismissible">
                    <p>'.wp_kses_post($e->getMessage()).'.</p>
                </div>';
            echo wp_kses($data, GEEKYBOT_ALLOWED_TAGS);
            return false;
        }
    }

    function logAddonDeactivation($addon_name){
        $option_name = 'env_signature_geeky-bot-'.esc_attr($addon_name);
        $transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);

        $activate_results = $this->deactivate( array(
            'token'    => $transaction_key,
            'plugin_slug'    => $addon_name
        ) );
    }

    function logAddonDeletion($addon_name){
        $option_name = 'env_signature_geeky-bot-'.esc_attr($addon_name);
        $transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);
        $activate_results = $this->delete( array(
            'token'    => $transaction_key,
            'plugin_slug'    => $addon_name
        ) );
    }

    public static function activate( $args ) {
        $site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getSiteUrl();
        $defaults = array(
            'request'  => 'activate',
            'domain' => $site_url,
            'activation_call' => 1
        );

        $args    = wp_parse_args( $defaults, $args );
        $url = self::$server_url . '?' . http_build_query( $args, '', '&' );
        $request = wp_remote_get( self::$server_url . '?' . http_build_query( $args, '', '&' ) );
        if ( is_wp_error( $request ) ) {
            return wp_json_encode( array( 'error_code' => $request->get_error_code(), 'error' => $request->get_error_message() ) );
        }

        if ( wp_remote_retrieve_response_code( $request ) != 200 ) {
            return wp_json_encode( array( 'error_code' => wp_remote_retrieve_response_code( $request ), 'error' => 'Error code: ' . wp_remote_retrieve_response_code( $request ) ) );
        }
        $response =  wp_remote_retrieve_body( $request );
        $response = json_decode($response,true);
        return $response;
    }

    /**
     * Attempt t deactivate a license
     */
    public static function deactivate( $dargs ) {
        $site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getSiteUrl();
        $defaults = array(
            'request'  => 'deactivate',
            'domain' => $site_url
        );

        $args    = wp_parse_args( $defaults, $dargs );
        $request = wp_remote_get( self::$server_url . '?' . http_build_query( $args, '', '&' ) );
        if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
            return false;
        } else {
            return wp_remote_retrieve_body( $request );
        }
    }
    /**
     * Attempt t deactivate a license
     */
    public static function delete( $args ) {
        $site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getSiteUrl();
        $defaults = array(
            'request'  => 'delete',
            'domain' => $site_url,
        );

        $args    = wp_parse_args( $defaults, $args );
        $request = wp_remote_get( self::$server_url . '?' . http_build_query( $args, '', '&' ) );
        if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
            return false;
        } else {
            return;
        }
    }

    function verifyAddonSqlFile($addon_name,$addon_version){
        $option_name = 'env_signature_geeky-bot-'.esc_attr($addon_name);
        $transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);
        $network_site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getNetworkSiteUrl();
        $site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getSiteUrl();
        $defaults = array(
            'request'  => 'getactivatesql',
            'domain' => $network_site_url,
            'subsite' => $site_url,
            'activation_call' => 1,
            'plugin_slug' => $addon_name,
            'addonversion' => $addon_version,
            'token' => $transaction_key
        );
        $request = wp_remote_get( self::$server_url . '?' . http_build_query( $defaults, '', '&' ) );
        if ( is_wp_error( $request ) ) {
            return wp_json_encode( array( 'error_code' => $request->get_error_code(), 'error' => $request->get_error_message() ) );
        }

        if ( wp_remote_retrieve_response_code( $request ) != 200 ) {
            return wp_json_encode( array( 'error_code' => wp_remote_retrieve_response_code( $request ), 'error' => 'Error code: ' . wp_remote_retrieve_response_code( $request ) ) );
        }

        $response =  wp_remote_retrieve_body( $request );
        return $response;
    }

    function getAddonSqlForUpdation($plugin_slug,$installed_version,$new_version){
        $option_name = 'env_signature_geeky-bot-'.esc_attr($plugin_slug);
        $transaction_key = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($option_name);
        $network_site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getNetworkSiteUrl();
        $site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getSiteUrl();
        $defaults = array(
            'request'  => 'getupdatesql',
            'domain' => $network_site_url,
            'subsite' => $site_url,
            'activation_call' => 1,
            'plugin_slug' => $plugin_slug,
            'installedversion' => $installed_version,
            'newversion' => $new_version,
            'token' => $transaction_key
        );

        $request = wp_remote_get( self::$server_url . '?' . http_build_query( $defaults, '', '&' ) );
        if ( is_wp_error( $request ) ) {
            return wp_json_encode( array( 'error_code' => $request->get_error_code(), 'error' => $request->get_error_message() ) );
        }

        if ( wp_remote_retrieve_response_code( $request ) != 200 ) {
            return wp_json_encode( array( 'error_code' => wp_remote_retrieve_response_code( $request ), 'error' => 'Error code: ' . wp_remote_retrieve_response_code( $request ) ) );
        }

        $response =  wp_remote_retrieve_body( $request );
        return $response;
    }

    function getAddonUpdateSqlFromUpdateDir($installedversion,$newversion,$directory){

        if($installedversion != "" && $newversion != ""){
            for ($i = ($installedversion + 1); $i <= $newversion; $i++) {
                $installfile = $directory . '/' . $i . '.sql';
                if (file_exists($installfile)) {
                    $delimiter = ';';
                    $file = fopen($installfile, 'r');
                    if (is_resource($file) === true) {
                        $query = array();

                        while (feof($file) === false) {
                            $query[] = fgets($file);
                            if (geekybotphplib::GEEKYBOT_preg_match('~' . preg_quote($delimiter, '~') . '\s*$~iS', end($query)) === 1) {
                                $query = trim(implode('', $query));
                                if($query != ''){
                                    $query = geekybotphplib::GEEKYBOT_str_replace("#__", geekybot::$_db->prefix, $query);
                                }
                                if (!empty($query)) {
                                    geekybot::$_db->query($query);
                                }
                            }
                            if (is_string($query) === true) {
                                $query = array();
                            }
                        }
                        fclose($file);
                    }
                }
            }
        }
    }

    function getAddonUpdateSqlFromLive($installedversion,$newversion,$plugin_slug){
        if($installedversion != "" && $newversion != "" && $plugin_slug != ""){
            $addonsql = $this->getAddonSqlForUpdation($plugin_slug,$installedversion,$newversion);
            $decodedata = json_decode($addonsql,true);
            $delimiter = ';';
            if(isset($decodedata['verfication_status']) && $decodedata['update_sql'] != ""){
                $lines = geekybotphplib::GEEKYBOT_explode(PHP_EOL, $addonsql);
                if(!empty($lines)){
                    foreach($lines as $line){
                        $query[] = $line;
                        if (geekybotphplib::GEEKYBOT_preg_match('~' . preg_quote($delimiter, '~') . '\s*$~iS', end($query)) === 1) {
                            $query = trim(implode('', $query));
                            if($query != ''){
                                $query = geekybotphplib::GEEKYBOT_str_replace("#__", geekybot::$_db->prefix, $query);
                            }
                            if (!empty($query)) {
                                geekybot::$_db->query($query);
                            }
                        }
                        if (is_string($query) === true) {
                            $query = array();
                        }
                    }
                }
            }
        }
    }

    function geekybotCheckAddoneInfo($name){
        $slug = $name.'/'.$name.'.php';
        if(file_exists(WP_PLUGIN_DIR . '/'.$slug) && is_plugin_active($slug)){
            $status = esc_html(__("Activated",'geeky-bot'));
            $action = esc_html(__("Deactivate",'geeky-bot'));
            $actionClass = 'geekybot-admin-adons-status-Deactive';
            $url = "plugins.php?s=".$name."&plugin_status=active";
            $disabled = "disabled";
            $class = "geekybot-btn-activated";
            $availability = "-1";
            $version = "";
        }else if(file_exists(WP_PLUGIN_DIR . '/'.$slug) && !is_plugin_active($slug)){
            $status = esc_html(__("Deactivated",'geeky-bot'));
            $action = esc_html(__("Activate",'geeky-bot'));
            $actionClass = 'geekybot-admin-adons-status-Active';
            $url = "plugins.php?s=".$name."&plugin_status=inactive";
            $disabled = "";
            $class = "geekybot-btn-green geekybot-btn-active-now";
            $availability = "1";
            $version = "";
        }else if(!file_exists(WP_PLUGIN_DIR . '/'.$slug)){
            $status = esc_html(__("Not Installed",'geeky-bot'));
            $action = esc_html(__("Install Now",'geeky-bot'));
            $actionClass = 'geekybot-admin-adons-status-Install';
            $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step1");
            $disabled = "";
            $class = "geekybot-btn-install-now";
            $availability = "0";
            $version = "---";
        }
        return array("status" => $status, "action" => $action, "url" => $url, "disabled" => $disabled, "class" => $class, "availability" => $availability, "actionClass" => $actionClass, "version" => $version);
    }

    function downloadandinstalladdonfromAjax(){
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'download-and-install-addon') ) {
            die( 'Security check Failed' );
        }

        $key = GEEKYBOTrequest::GEEKYBOT_getVar('dataFor');
        $installedversion = GEEKYBOTrequest::GEEKYBOT_getVar('currentVersion');
        $newversion = GEEKYBOTrequest::GEEKYBOT_getVar('cdnVersion');
        $addon_json_array = array();

        if($key != ''){
            $addon_json_array[] = geekybotphplib::GEEKYBOT_str_replace('geeky-bot-', '', $key);
            $plugin_slug = geekybotphplib::GEEKYBOT_str_replace('geeky-bot-', '', $key);
        }
        $token = get_option('env_signature_'.esc_attr($key));
        $result = array();
        $result['error'] = false;
        if($token == ''){
            $result['error'] = esc_html(__('Addon Installation Failed','geeky-bot'));
            $result = wp_json_encode($result);
            return $result;
        }
        $site_url = site_url();
        if($site_url != ''){
            $site_url = geekybotphplib::GEEKYBOT_str_replace("https://","",$site_url);
            $site_url = geekybotphplib::GEEKYBOT_str_replace("http://","",$site_url);
        }
        $url = 'https://geekybot.com/setup/index.php?token='.esc_attr($token).'&productcode='. wp_json_encode($addon_json_array).'&domain='. $site_url;
        // verify token
        $verifytransactionkey = $this->verifytransactionkey($token, $url);
        if($verifytransactionkey['status'] == 0){
            $result['error'] = $verifytransactionkey['message'];
            $result = wp_json_encode($result);
            return $result;
        }
        $install_count = 0;

        $installed = $this->install_plugin($url);
        if ( !is_wp_error( $installed ) && $installed ) {
            // had to run two seprate loops to save token for all the addons even if some error is triggered by activation.
            update_option('env_signature_geeky-bot',$token);
            update_option('env_signature_geeky-bot_date',time());
            update_option('unique_grace_period_active_date', false);
            update_option('unique_features_disabled', false);
            update_option('unique_admin_process_value', false);
            update_option('gb_admin_unique_job_run',time());
            if(geekybotphplib::GEEKYBOT_strstr($key, 'geeky-bot-')){
                update_option('env_signature_'.$key,$token);
            }

            if(geekybotphplib::GEEKYBOT_strstr($key, 'geeky-bot-')){
                $activate = activate_plugin( $key.'/'.$key.'.php' );
                $install_count++;
            }

            // run update sql
            if ($installedversion != $newversion) {
                $optionname = 'geeky-bot-addon-'. $plugin_slug .'s-version';
                update_option($optionname, $newversion);
                $plugin_path = WP_CONTENT_DIR;
                $plugin_path = $plugin_path.'/plugins/'.$key.'/includes';
                if(is_dir($plugin_path . '/sql/') && is_readable($plugin_path . '/sql/')){
                    if($installedversion != ''){
                        $installedversion = geekybotphplib::GEEKYBOT_str_replace('.','', $installedversion);
                    }
                    if($newversion != ''){
                        $newversion = geekybotphplib::GEEKYBOT_str_replace('.','', $newversion);
                    }
                    $this->getAddonUpdateSqlFromUpdateDir($installedversion,$newversion,$plugin_path . '/sql/');
                    $updatesdir = $plugin_path.'/sql/';
                    if(geekybotphplib::GEEKYBOT_preg_match('/geeky-bot-[a-zA-Z]+/', $updatesdir)){
                        geekybotRemoveAddonUpdatesFolder($updatesdir);
                    }
                }else{
                    $this->getAddonUpdateSqlFromLive($installedversion,$newversion,$plugin_slug);
                }
            }

        }else{
            $result['error'] = esc_html(__('Addon Installation Failed','geeky-bot'));
            $result = wp_json_encode($result);
            return $result;
        }

        $result['success'] = esc_html(__('Addon Installed Successfully','geeky-bot'));
        $result = wp_json_encode($result);
        return $result;
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
                $result['error'] = esc_html(__('Addon installation failed','geeky-bot')).'.';
                $result['error'] .= " ".wp_kses(geekybot::GEEKYBOT_getVarValue($unzipfile->get_error_message()), GEEKYBOT_ALLOWED_TAGS);
                $result = wp_json_encode($result);
                return $result;
            } else {
                return true;
            }
        }else{
            $error_string = $tmpfile->get_error_message();
            $result['error'] = esc_html(__('Addon Installation Failed, File download error','geeky-bot')).'! '.esc_attr($error_string);
            $result = wp_json_encode($result);
            return $result;
        }
    }

    function verifytransactionkey($transactionkey, $url){
        $message = 1;
        if($transactionkey != ''){
            $response = wp_remote_post( $url );
            if( !is_wp_error($response) && $response['response']['code'] == 200 && isset($response['body']) ){
                $result = $response['body'];
                $result = json_decode($result,true);
                if(is_array($result) && isset($result[0]) && $result[0] == 0){
                    $result['status'] = 0;
                } else{
                    $result['status'] = 1;
                }
            }else{
                $result = false;
                if(!is_wp_error($response)){
                   $error = $response['response']['message'];
                }else{
                    $error = $response->get_error_message();
                }
            }
            if(is_array($result) && isset($result['status']) && $result['status'] == 1 ){ // means everthing ok
                $message = 1;
            }else{
                if(isset($result[0]) && $result[0] == 0){
                    $error = $result[1];
                }elseif(isset($result['error']) && $result['error'] != ''){
                    $error = $result['error'];
                }
                $message = 0;
            }
        }else{
            $message = 0;
            $error = esc_html(__('Please insert activation key to proceed','geeky-bot')).'!';
        }
        $array['data'] = array();
        if ($message == 0) {
            $array['status'] = 0;
            $array['message'] = $error;
        } else {
            $array['status'] = 1;
            $array['message'] = 'success';
        }
        return $array;
        
    }

    function geekybotGetAddonsArray(){
        return array(
            'geeky-bot-customtextstyle' => array('title' => esc_html(__('Custom Text Style','geeky-bot')), 'price' => 0, 'status' => 1),
            'geeky-bot-customlistingstyle' => array('title' => esc_html(__('Custom Listing Style','geeky-bot')), 'price' => 0, 'status' => 1),
            'geeky-bot-woocommercepropack' => array('title' => esc_html(__('WooCommerce Pro Pack','geeky-bot')), 'price' => 0, 'status' => 1),
        );
    }

    function geekybotCheckUpdates(){
        include_once GEEKYBOT_PLUGIN_PATH . 'includes/updates/updates.php';
        GEEKYBOTupdates::GEEKYBOT_checkUpdates(112);
        return 1;
    }

}

?>
