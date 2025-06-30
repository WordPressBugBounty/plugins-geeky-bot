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
        geekybot::$_data[0]['geekybot_dialogflow_json'] = get_option('geekybot_dialogflow_json');
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
                $key == 'function_custom_heading' || 
                $key == 'geekybot_dialogflow_json') {
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
            if ($key == 'ai_provider' && $value == 3) {
                $uploadDir = wp_upload_dir();
                $isAssistantFound = get_option('geekybot_assistant_id');
                if (in_array('openaiassistant', geekybot::$_active_addons) && !file_exists($uploadDir['basedir'] . '/geekybotLibraries/openAI/geekybot_openai_assistant_client_library-main/autoload.php')) {
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(esc_html(__('OpenAI Assistant PHP Client Library is Missing. You can get it from the OpenAI Settings page.', 'geeky-bot')), 'error',$this->getMessagekey());
                    $error = true;
                    continue;
                }
            }
            if ($key == 'ai_provider' && $value == 2) {
                $uploadDir = wp_upload_dir();
                if (!file_exists($uploadDir['basedir'] . '/geekybotLibraries/dialogFlow/geekybot_google_client-main/autoload.php')) {
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(esc_html(__('Google Client Library is Missing. You can get it from the Dialogflow Settings page.', 'geeky-bot')), 'error',$this->getMessagekey());
                    $error = true;
                    continue;
                }
            }
            $query = 'UPDATE `' . geekybot::$_db->prefix . 'geekybot_config` SET `configvalue` = "'.esc_sql($value).'" WHERE `configname`= "' . esc_sql($key) . '"';
            if (false === geekybotdb::query($query)) {
                $error = true;
            }
        }
        if (!empty($data['geekybot_dialogflow_json'])) {
            update_option('geekybot_dialogflow_json', $data['geekybot_dialogflow_json']);
        } elseif(isset($data['geekybot_dialogflow_json']) && $data['geekybot_dialogflow_json'] == '') {
            update_option('geekybot_dialogflow_json', '');
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

    function getOpenaiModelList() {
        // 1. Try with API key if provided
        $api_key = geekybot::$_configuration['geekybot_openai_api_key'] ?? '';
        if (!empty($api_key)) {
            $response = wp_remote_get('https://api.openai.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['data'])) {
                    return $this->processOpenaiModels($data['data']);
                }
            }
        }

        // 3. Fall back to fixed models
        return $this->getFixedOpenaiModels();
    }

    private function processOpenaiModels($models) {
        $filtered = array_filter($models, function($model) {
            $id = $model['id'] ?? '';
            $owner = $model['owned_by'] ?? '';
            return strpos($id, 'gpt') !== false && 
                   $owner !== 'openai-internal' &&
                   !str_contains($id, 'instruct') &&
                   !str_contains($id, 'vision');
        });

        return array_map(function($model) {
            return (object) [
                'id' => $model['id'],
                'text' => $model['id']
            ];
        }, $filtered);
    }

    private function getFixedOpenaiModels() {
        return [
            (object) ['id' => 'gpt-4o', 'text' => 'GPT-4o (Default)', 'type' => 'fixed'],
            (object) ['id' => 'gpt-4-turbo', 'text' => 'GPT-4 Turbo', 'type' => 'fixed'],
            (object) ['id' => 'gpt-3.5-turbo', 'text' => 'GPT-3.5 Turbo', 'type' => 'fixed'],
            (object) ['id' => 'gpt-4', 'text' => 'GPT-4', 'type' => 'fixed']
        ];
    }

    function getOpenRouterModelList() {
        // First try to get models without API key (public endpoint)
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
            'headers' => [
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name'),
            ],
            'timeout' => 15
        ]);

        // If unauthorized (401), try with API key as fallback
        if (wp_remote_retrieve_response_code($response) === 401) {
            $apiKey = geekybot::$_configuration['geekybot_openrouter_api_key'] ?? '';
            if (!empty($apiKey)) {
                die();
                $response = wp_remote_get('https://openrouter.ai/api/v1/models', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'HTTP-Referer' => home_url(),
                        'X-Title' => get_bloginfo('name'),
                    ],
                    'timeout' => 15
                ]);
            }
        }

        // Handle errors
        if (is_wp_error($response)) {
            error_log('OpenRouter Models Error: ' . $response->get_error_message());
            return $this->getDefaultOpenRouterModels();
        }

        // Process successful response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data['data'])) {
            $models = [];
            foreach ($data['data'] as $model) {
                $models[] = (object) [
                    'id' => $model['id'],
                    'text' => $model['name'],
                    'free' => true
                ];

            }
            return $models;
        }

        // Fallback to default models if empty
        return $this->getDefaultOpenRouterModels();
    }

    private function getDefaultOpenRouterModels() {
        return [
            (object) ['id' => 'deepseek/deepseek-r1:free', 'text' => 'DeepSeek: R1 (free)', 'free' => true],
            (object) ['id' => 'openai/gpt-3.5-turbo', 'text' => 'OpenAI: GPT-3.5 Turbo', 'free' => true],
            (object) ['id' => 'anthropic/claude-3-haiku', 'text' => 'Anthropic: Claude 3 Haiku', 'free' => true],
            (object) ['id' => 'google/gemini-pro', 'text' => 'Google: Gemini Pro', 'free' => true],
            (object) ['id' => 'mistralai/mistral-7b-instruct:free', 'text' => 'Mistral: Mistral 7B Instruct (free)', 'free' => true]
        ];
    }

    function geekybotPrepareManualOpenAILibraryPath() {
        $uploadDir = wp_upload_dir();
        $targetDir = $uploadDir['basedir'] . '/geekybotLibraries/openAI/';

        if (!file_exists($targetDir)) {
            wp_mkdir_p($targetDir);
        }

        // Add an index.html file to prevent directory listing
        $indexFile = $targetDir . 'index.html';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<!-- Silence is golden -->');
        }
        $indexFile = $uploadDir['basedir'] . '/geekybotLibraries/' . 'index.html';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<!-- Silence is golden -->');
        }
    }

    function geekybotPrepareManualDialogFlowLibraryPath() {
        $uploadDir = wp_upload_dir();
        $targetDir = $uploadDir['basedir'] . '/geekybotLibraries/dialogFlow/';

        if (!file_exists($targetDir)) {
            wp_mkdir_p($targetDir);
        }

        // Add an index.html file to prevent directory listing
        $indexFile = $targetDir . 'index.html';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<!-- Silence is golden -->');
        }
        $indexFile = $uploadDir['basedir'] . '/geekybotLibraries/' . 'index.html';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<!-- Silence is golden -->');
        }
    }

}

?>
