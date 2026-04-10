<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTzywrapModel {

    public $zywrap_import_counts =  [
                'categories' => [
                    'imported' => 0,
                    'skipped'  => 0,
                    'failed'   => 0,
                ],
                'languages' => [
                    'imported' => 0,
                    'skipped'  => 0,
                    'failed'   => 0,
                ],'aimodels' => [
                    'imported' => 0,
                    'skipped'  => 0,
                    'failed'   => 0,
                ],'blocktemplates' => [
                    'imported' => 0,
                    'skipped'  => 0,
                    'failed'   => 0,
                ],'wrappers' => [
                    'imported' => 0,
                    'skipped'  => 0,
                    'failed'   => 0,
                ],
            ];

    private $max_per_run = 10000;

    // Gets the message key for admin notices
    function getMessagekey() {
        $geekybot_key = 'zywrap';
        if (is_admin()) {
            $geekybot_key = 'admin_' . $geekybot_key;
        }
        return $geekybot_key;
    }

    /**
     * Helper function to record all API calls
     */
    private function log_api_call($geekybot_action, $geekybot_status, $geekybot_args = array()) {
        global $wpdb;
        $geekybot_table_name = $wpdb->prefix . 'geekybot_zywrap_logs';

        // Get the token/usage array passed from the calling function
        // This comes from response.data.usage in proxy.js
        $usage_data = isset($geekybot_args['token_data']) ? $geekybot_args['token_data'] : null;

        $geekybot_data = array(
            'timestamp'     => current_time('mysql'),
            'user_id'       => get_current_user_id(),
            'action'        => $geekybot_action,
            'status'        => $geekybot_status,
            'wrapper_code'  => $geekybot_args['wrapper_code'] ?? null,
            'model_code'    => $geekybot_args['model_code'] ?? null,
            'http_code'     => $geekybot_args['http_code'] ?? null,
            'error_message' => $geekybot_args['error_message'] ?? null,

            // Read from the passed $usage_data array
            'prompt_tokens'     => isset($usage_data['prompt_tokens']) ? (int)$usage_data['prompt_tokens'] : 0,
            'completion_tokens' => isset($usage_data['completion_tokens']) ? (int)$usage_data['completion_tokens'] : 0,
            'total_tokens'      => isset($usage_data['total_tokens']) ? (int)$usage_data['total_tokens'] : 0,

            // Store the full usage JSON for debugging/history
            'token_data'    => $usage_data ? json_encode($usage_data) : null,
        );

        $wpdb->insert($geekybot_table_name, $geekybot_data);
    }

    /**
     * AJAX Function: Calls the /v1/key/check endpoint
     */
    function checkZywrapApiKey() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (!wp_verify_nonce($geekybot_nonce, 'check-zywrap-key')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        $api_key = GEEKYBOTrequest::GEEKYBOT_getVar('api_key', 'post');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key cannot be empty.'));
        }

        $geekybot_url = 'https://api.zywrap.com/v1/key/check';
        $geekybot_args = array(
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body'    => json_encode(array('apiKey' => $api_key))
        );

        // Use WordPress's built-in function to make the API call
        $response = wp_remote_post($geekybot_url, $geekybot_args);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Error: ' . $response->get_error_message()));
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $geekybot_data = json_decode($body, true);

        if ($http_code == 200) {
            // Log the successful or limited key check
            $this->log_api_call('key_check', $geekybot_data['status'], ['http_code' => $http_code]);
            wp_send_json_success(array(
                'status'  => $geekybot_data['status'],
                'message' => $geekybot_data['message']
            ));
        } else {
            // Log the invalid key check
            $this->log_api_call('key_check', $geekybot_data['status'] ?? 'error', [
                'http_code' => $http_code,
                'error_message' => $geekybot_data['message'] ?? 'Invalid key'
            ]);
            wp_send_json_error(array(
                'status'  => $geekybot_data['status'] ?? 'error',
                'message' => $geekybot_data['message'] ?? 'An unknown error occurred.'
            ));
        }
    }

    /**
     * AJAX Function: Performs a FULL data import.
     */

    function cleanStringForCompare($geekybot_string){
        if($geekybot_string == ''){
            return $geekybot_string;
        }
        // already null checked so no need for         geekybotphplib::wpJP_ functions
        $geekybot_string = str_replace(' ', '', $geekybot_string);
        $geekybot_string = str_replace('-', '', $geekybot_string);
        $geekybot_string = str_replace('_', '', $geekybot_string);
        $geekybot_string = trim($geekybot_string);
        $geekybot_string = strtolower($geekybot_string);
        return $geekybot_string;
    }

    // functino to imporo zywrap categories
    function importZywrapCategories( $geekybot_data_categories ) {

        if ( empty( $geekybot_data_categories ) ) {
            return;
        }
        // Get max ordering
        $query = "SELECT MAX(cat.ordering)
                    FROM `" . geekybot::$_db->prefix . "geekybot_zywrap_categories` AS cat";
        $ordering = (int) geekybot::$_db->get_var( $query );
        $ordering = $ordering + 1;
        $ordering_check = $ordering;

        // Loop categories from input
        foreach ( $geekybot_data_categories as $code => $geekybot_category ) {
            $geekybot_name = $geekybot_category['name'];

            // Build dataset same as job type function
            $geekybot_data = [];
            //$geekybot_data['id']       = '';
            $geekybot_data['code']     = $code;
            $geekybot_data['name']     = $geekybot_name;
            $geekybot_data['ordering'] = $ordering;
            $geekybot_data['status']   = 1;

            // Store into DB
            // Suppress duplicate-key insert warnings during bulk import
            geekybot::$_db->suppress_errors( true );
            $response = geekybot::$_db->insert(geekybot::$_db->prefix.'geekybot_zywrap_categories',$geekybot_data);
            geekybot::$_db->suppress_errors( false );

            if ($response) {
                $this->zywrap_import_counts['categories']['imported'] += 1;
            } else {
                $this->zywrap_import_counts['categories']['failed'] += 1;
                continue;
            }

            $ordering++;
        }
    }

    // function to import zywrap languages
    function importZywrapLanguages( $geekybot_data_languages ) {

        if ( empty( $geekybot_data_languages ) ) {
            return;
        }

        // Get max ordering
        $query = "SELECT MAX(lang.ordering)
                    FROM `" . geekybot::$_db->prefix . "geekybot_zywrap_languages` AS lang";
        $ordering = (int) geekybot::$_db->get_var( $query );
        $ordering = $ordering + 1;
        $ordering_check = $ordering;

        // Loop languages from input
        foreach ( $geekybot_data_languages as $code => $geekybot_name ) {
            // Build dataset
            $geekybot_data = [];
            $geekybot_data['code']     = $code;
            $geekybot_data['name']     = $geekybot_name;
            $geekybot_data['ordering'] = $ordering;
            $geekybot_data['status'] = 1;

            // Store into DB
            // Suppress duplicate-key insert warnings during bulk import
            geekybot::$_db->suppress_errors( true );
            $response = geekybot::$_db->insert(geekybot::$_db->prefix.'geekybot_zywrap_languages',$geekybot_data);
            geekybot::$_db->suppress_errors( false );
            if ( $response ) {
                $this->zywrap_import_counts['languages']['imported'] += 1;
            } else {
                $this->zywrap_import_counts['languages']['failed'] += 1;
                continue;
            }

            $ordering++;
        }
    }

    // function to import zywrap AI models
    function importZywrapAiModels( $geekybot_data_ai_models ) {

        if ( empty( $geekybot_data_ai_models ) ) {
            return;
        }

        // Get max ordering
        $query = "SELECT MAX(model.ordering)
                    FROM `" . geekybot::$_db->prefix . "geekybot_zywrap_ai_models` AS model";
        $ordering = (int) geekybot::$_db->get_var( $query );
        $ordering  = $ordering + 1;
        $ordering_check = $ordering;
        /*
        // Prepare existing codes if ordering exists
        if ( $ordering_check > 0 ) {

            $geekybot_existing = [];
            $query = "SELECT model.code
                        FROM `" . geekybot::$_db->prefix . "geekybot_zywrap_ai_models` AS model";
            $geekybot_results = geekybot::$_db->get_results( $query );

            if ( ! empty( $geekybot_results ) ) {
                foreach ( $geekybot_results as $geekybot_row ) {
                    $geekybot_existing[] = $this->cleanStringForCompare( $geekybot_row->code );
                }
            }
        }
        */

        // Loop AI models from input
        foreach ( $geekybot_data_ai_models as $code => $geekybot_model ) {
            $geekybot_name       = $geekybot_model['name'];
            $geekybot_providerId = $geekybot_model['provId'];
            /*
            if ( $ordering_check > 0 ) {
                $geekybot_compare_code = $this->cleanStringForCompare( $code );

                // Skip duplicates
                if ( in_array( $geekybot_compare_code, $geekybot_existing ) ) {
                    $this->zywrap_import_counts['aimodels']['skipped'] += 1;
                    continue;
                }
            }
            */

            // Prepare data to bind
            $geekybot_data = [];
            $geekybot_data['code']        = $code;
            $geekybot_data['name']        = $geekybot_name;
            $geekybot_data['provider_id'] = $geekybot_providerId;
            $geekybot_data['ordering']    = $ordering;
            $geekybot_data['status']    = 1;

            // Store into DB
            // Suppress duplicate-key insert warnings during bulk import
            geekybot::$_db->suppress_errors( true );
            $response = geekybot::$_db->insert(geekybot::$_db->prefix.'geekybot_zywrap_ai_models',$geekybot_data);
            geekybot::$_db->suppress_errors( false );
            if ( $response ) {
                $this->zywrap_import_counts['aimodels']['imported'] += 1;
            } else {
                $this->zywrap_import_counts['aimodels']['failed'] += 1;
                continue;
            }

            $ordering++;
        }
    }

        // function to import zywrap block templates
    function importZywrapBlockTemplates( $geekybot_data_templates ) {

        if ( empty( $geekybot_data_templates ) ) {
            return;
        }
        /*
        // Load existing entries to avoid duplicates
        $geekybot_existing = [];
        $query = "SELECT tpl.type, tpl.code
                    FROM `" . geekybot::$_db->prefix . "geekybot_zywrap_block_templates` AS tpl";
        $geekybot_results = geekybot::$_db->get_results( $query );

        if ( ! empty( $geekybot_results ) ) {
            foreach ( $geekybot_results as $geekybot_row ) {
                $geekybot_key = $this->cleanStringForCompare( $geekybot_row->type . '_' . $geekybot_row->code );
                $geekybot_existing[] = $geekybot_key;
            }
        }
        */

        // Loop through all template groups (types)
        foreach ( $geekybot_data_templates as $type => $geekybot_templates ) {

            if ( ! is_array( $geekybot_templates ) ) {
                continue;
            }

            foreach ( $geekybot_templates as $code => $geekybot_name ) {

                $geekybot_compare_key = $this->cleanStringForCompare( $type . '_' . $code );
                
                // Prepare DB row object
                //$geekybot_row = WPJOBPORTALincluder::getJSTable('zywrapblocktemplate');

                // Prepare bind data (no ordering or timestamps in your original)
                $geekybot_data = [];
                $geekybot_data['type'] = $type;
                $geekybot_data['code'] = $code;
                $geekybot_data['name'] = $geekybot_name;
                $geekybot_data['status'] = 1;

                // Suppress duplicate-key insert warnings during bulk import
                geekybot::$_db->suppress_errors( true );
                $response = geekybot::$_db->insert(geekybot::$_db->prefix.'geekybot_zywrap_block_templates',$geekybot_data);
                geekybot::$_db->suppress_errors( false );

                // Attempt DB store
                if ( $response ) {
                    $this->zywrap_import_counts['blocktemplates']['imported'] += 1;
                } else {
                    $this->zywrap_import_counts['blocktemplates']['failed'] += 1;
                    continue;
                }
            }
        }
    }

    function importZywrapWrappersInBatches($geekybot_data_wrappers, $geekybot_total_count) {
        if (empty($geekybot_data_wrappers)) {
            return [
                'status'  => 'error',
                'message' => __('No data to import.', 'geeky-bot'),
            ];
        }

        // Determine batch size
        $batch_data = array_slice($geekybot_data_wrappers, 0, $this->max_per_run, true);
        $remaining  = array_slice($geekybot_data_wrappers, $this->max_per_run, null, true);

        $this->importZywrapWrappers($batch_data);

        // Handle remaining records or paused datasets
        $pending = [];
        if (!empty($remaining)) {
            // Save remaining items
            set_transient('wpjp_import_wrappers_pending', $remaining, HOUR_IN_SECONDS);
            return [
                'status'    => 'paused',
                'message'   => __('Batch processed successfully.', 'geeky-bot'),
                'counts'    => $this->zywrap_import_counts,
                'remaining' => count($remaining),
            ];
        }

        // All data processed successfully
        delete_transient('wpjp_import_wrappers_pending');

        // Return completion status
        return [
            'status'    => 'completed',
            'message'   => __('Import completed successfully.', 'geeky-bot'),
            'counts'    => $this->zywrap_import_counts,
            'imported'  => $this->zywrap_import_counts['wrappers']['imported'] ?? 0,
            'skipped'   => $this->zywrap_import_counts['wrappers']['skipped'] ?? 0,
            'failed'    => $this->zywrap_import_counts['wrappers']['failed'] ?? 0,
        ];
    }


    function importZywrapBatchProcess() {

        if (function_exists('set_time_limit')) {
            set_time_limit(0); // Unlimited execution time
        }
        @ini_set('memory_limit', '512M'); // Increase memory limit if possible


        //  Security Checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // Verify the same nonce used in the main import
        $geekybot_nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_full_import')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        // 3. Retrieve Pending Data
        // This transient is created in your 'importZywrapWrappersInBatches' function
        $pending_wrappers = get_transient('wpjp_import_wrappers_pending');

        if (empty($pending_wrappers)) {
            // Safety: If no data is found, we assume completion or expiration
            delete_transient('wpjp_import_counts_cache');
            wp_send_json_success([
                'status'  => 'completed',
                'message' => __('Import process finished (No pending data).', 'geeky-bot'),
                'counts'  => $this->zywrap_import_counts,
                'imported'=> 0,
                'failed'  => 0
            ]);
        }

        // 4. Restore Previous Counts (Cumulative Statistics)
        // We retrieve the counts from the previous run so the numbers increase (e.g., 50, 100, 150)
        // instead of resetting to 0 on every batch.
        $geekybot_saved_counts = get_transient('wpjp_import_counts_cache');
        if ($geekybot_saved_counts) {
            $this->zywrap_import_counts = $geekybot_saved_counts;
        } else {
            // Initialize defaults if missing
            if (!isset($this->zywrap_import_counts['wrappers'])) {
                $this->zywrap_import_counts['wrappers'] = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
            }
        }

        // 5. Process the Next Batch
        // We reuse your existing helper. It will:
        // - Process 'max_per_run' items
        // - Update the 'wpjp_import_wrappers_pending' transient automatically
        // - Return the 'paused' or 'completed' status array
        $geekybot_result = $this->importZywrapWrappersInBatches($pending_wrappers, count($pending_wrappers));

        // 6. Persist Counts for the Next Run
        if ($geekybot_result['status'] === 'paused') {
            set_transient('wpjp_import_counts_cache', $this->zywrap_import_counts, HOUR_IN_SECONDS);
        } else {
            // If completed, clean up the stats cache
            delete_transient('wpjp_import_counts_cache');
        }

        // 7. Return JSON response matching your JS structure
        wp_send_json_success($geekybot_result);
    }

    function importZywrapWrappers($geekybot_data_wrappers) {
        if (empty($geekybot_data_wrappers)) {
            return;
        }

        // Get max ordering
        $query = "SELECT MAX(wrap.ordering)
                  FROM `" . geekybot::$_db->prefix . "geekybot_zywrap_wrappers` AS wrap";
        $ordering = (int) geekybot::$_db->get_var($query);
        $ordering_check = $ordering;

        // Initialize counters if not set
        if (!isset($this->zywrap_import_counts['wrappers'])) {
            $this->zywrap_import_counts['wrappers'] = [
                'imported' => 0,
                'skipped'  => 0,
                'failed'   => 0
            ];
        }

        $ordering = $ordering + 1;

        $batch_size   = 100;
        $batch_values = [];
        $batch_count  = 0;

        $table = geekybot::$_db->prefix . 'geekybot_zywrap_wrappers';

        foreach ($geekybot_data_wrappers as $code => $wrapper) {

            // Validate required fields
            if (empty($code) || empty($wrapper['name'])) {
                $this->zywrap_import_counts['wrappers']['failed']++;
                continue;
            }

            $geekybot_name = $wrapper['name'] ?? '';
            $geekybot_desc = $wrapper['desc'] ?? '';
            $cat              = $wrapper['cat'] ?? '';
            $featured         = (int) ($wrapper['featured'] ?? 0);
            $base             = (int) ($wrapper['base'] ?? 0);

            // Prepare escaped row
            $batch_values[] = geekybot::$_db->prepare(
                "(%s, %s, %s, %s, %d, %d, %d)",
                $code,
                $geekybot_name,
                $geekybot_desc,
                $cat,
                $featured,
                $base,
                $ordering
            );

            $ordering++;
            $batch_count++;

            // Execute batch when limit reached
            if ($batch_count === $batch_size) {

                // Suppress duplicate-key insert warnings during bulk import
                geekybot::$_db->suppress_errors( true );
                $sql = "
                    INSERT INTO {$table}
                    (code, name, description, category_code, featured, base, ordering)
                    VALUES " . implode(',', $batch_values);

                $result = geekybot::$_db->query($sql);
                geekybot::$_db->suppress_errors( false );

                if ($result !== false) {
                    $this->zywrap_import_counts['wrappers']['imported'] += $batch_count;
                } else {
                    $this->zywrap_import_counts['wrappers']['failed'] += $batch_count;
                }

                // Reset batch
                $batch_values = [];
                $batch_count  = 0;
            }
        }

        /**
         * Insert remaining records
         */
        if (!empty($batch_values)) {

            // Suppress duplicate-key insert warnings during bulk import
            geekybot::$_db->suppress_errors( true );
            $sql = "
                INSERT INTO {$table}
                (code, name, description, category_code, featured, base, ordering)
                VALUES " . implode(',', $batch_values);

            $result = geekybot::$_db->query($sql);
            geekybot::$_db->suppress_errors( false );

            if ($result !== false) {
                $this->zywrap_import_counts['wrappers']['imported'] += count($batch_values);
            } else {
                $this->zywrap_import_counts['wrappers']['failed'] += count($batch_values);
            }
        }
    }

    function importZywrapData() {
        if (function_exists('set_time_limit')) {
            set_time_limit(0); // Unlimited execution time
        }
        @ini_set('memory_limit', '512M'); // Increase memory limit if possible

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_full_import')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        $api_key = get_option('geekybot_zywrap_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key is not set.'));
        }

        $type = GEEKYBOTrequest::GEEKYBOT_getVar('actionType');

        // 1. Initialize WordPress filesystem
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            if ( ! $wp_filesystem ) {
                wp_send_json_error( array(
                    'message' => __( 'Failed to initialize filesystem. Please check permissions or set FS_METHOD to "direct" in wp-config.php.', 'geeky-bot'),
                ),500 );
            }
        }

        // 2. Download the ZIP file [cite: `PhpSdk.jsx`]
        $geekybot_url = 'https://api.zywrap.com/v1/sdk/export/';

        $response = wp_remote_get( $geekybot_url, array(
            'timeout' => 300, // 5 minutes
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            $message = __('Failed to download bundle: ', 'geeky-bot') . $response->get_error_message();

            $this->log_api_call( 'sync_full', 'error', array(
                'error_message' => 'Sync Wrappers: ' . $message,
            ) );

            wp_send_json_error( array(
                'message' => $message,
            ), 500);
        }

        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200) {
            // --- UPDATED: Use Smart Error Handling ---
            $friendly_msg = $this->get_api_error_message($http_code);

            $this->log_api_call( 'sync_full', 'error', array(
                'error_message' => 'Sync Wrappers: ' . $friendly_msg,
            ) );

            wp_send_json_error( array(
                'message' => __( 'Download Failed: ', 'geeky-bot').$friendly_msg,
            ), $http_code);
        }

        $zip_data = wp_remote_retrieve_body( $response );
        $temp_file = wp_tempnam();
        if (!$temp_file) {
            $message = __('Could not create temporary file.', 'geeky-bot');
            $this->log_api_call( 'sync_full', 'error', array(
                'error_message' => 'Sync Wrappers: ' . $message,
            ) );
            wp_send_json_error(['message' => $message], 500);
        }

        if (false === $wp_filesystem->put_contents($temp_file, $zip_data)) {
            $wp_filesystem->delete($temp_file);
            $message = __('Could not write to temporary file.', 'geeky-bot');
            $this->log_api_call( 'sync_full', 'error', array(
                'error_message' => 'Sync Wrappers: ' . $message,
            ) );
            wp_send_json_error(['message' => $message], 500);
        }

        // 2. Prepare file paths
        $geekybot_upload_dir = wp_upload_dir();
        $zip_file = trailingslashit( $geekybot_upload_dir['path'] ) . 'zywrap-data.zip';
        $geekybot_json_file = trailingslashit( $geekybot_upload_dir['path'] ) . 'zywrap-data.json';

        

        // 4. Write ZIP file using WP Filesystem
        if ( empty( $zip_data ) ) {
            wp_send_json_error( array(
                'message' => __( 'Downloaded ZIP file is empty.', 'geeky-bot'),
            ) );
        }

        $wp_filesystem->put_contents($zip_file,$zip_data,FS_CHMOD_FILE);

        // 5. Unzip using WordPress built-in safe unzipper
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $unzip_result = unzip_file( $zip_file, $geekybot_upload_dir['path'] );

        if ( is_wp_error( $unzip_result ) ) {
            wp_send_json_error( array(
                //'message' => ,$unzip_result->get_error_message(),
                'message' => __( 'Failed to unzip data bundle', 'geeky-bot'),
            ) );
        }

        // 6. Validate JSON file exists
        if ( ! $wp_filesystem->exists( $geekybot_json_file ) ) {
            wp_send_json_error( array(
                'message' => __( 'Error: zywrap-data.json not found in ZIP.', 'geeky-bot'),
            ) );
        }

        // 7. Read JSON safely
        $geekybot_json_data = $wp_filesystem->get_contents( $geekybot_json_file );
        if ( empty( $geekybot_json_data ) ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to read zywrap-data.json.', 'geeky-bot'),
            ) );
        }
        $geekybot_data = json_decode($geekybot_json_data, true);

        
        global $wpdb;
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0;');
        $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_wrappers`");
        $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_categories`");
        $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_languages`");
        $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_block_templates`");
        $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_ai_models`");
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1;');

        if (!$geekybot_data) {
            $this->log_api_call('sync_full', 'error', ['error_message' => 'Could not parse JSON data.']);
            wp_send_json_error(array('message' => 'Error: Could not parse JSON data.'));
        }

        $output_array = [];
        // Import Categories
        if (!empty($geekybot_data['categories'])) {
            $output_array['categories'] = count($geekybot_data['categories']);
            $this->importZywrapCategories($geekybot_data['categories']);
        }

        // Import Languages
        if (!empty($geekybot_data['languages'])) {
            $output_array['languages'] = count($geekybot_data['languages']);
            $this->importZywrapLanguages($geekybot_data['languages']);
        }

        // Import aiModels
        if (!empty($geekybot_data['aiModels'])) {
            $output_array['aiModels'] = count($geekybot_data['aiModels']);
            $this->importZywrapAiModels($geekybot_data['aiModels']);
        }
        // Import aiModels
        if (!empty($geekybot_data['templates'])) {
            $output_array['templates'] = count($geekybot_data['templates']);
            $this->importZywrapBlockTemplates($geekybot_data['templates']);
        }

        update_option('geekybot_zywrap_bundle_version', $geekybot_data['version'] ?? 'unknown');
        $sync_time = time();
        update_option('geekybot_zywrap_last_sync_time', $sync_time);
        // Import wrappers
        if (!empty($geekybot_data['wrappers'])) {
            $output_array['wrappers'] = count($geekybot_data['wrappers']);
            $geekybot_result = $this->importZywrapWrappersInBatches($geekybot_data['wrappers'], $output_array['wrappers']);
            set_transient('wpjp_import_counts_cache', $this->zywrap_import_counts, HOUR_IN_SECONDS);

            wp_send_json_success($geekybot_result);
        }
        return;
    }

    

    
    
    /**
     * Helper to format JSON data into DB columns
     */
    private function prepare_data_for_db($type, $code, $data) {
        switch ($type) {
            case 'categories':
                return ['code' => $code, 'name' => $data['name'], 'ordering' => $data['ordering'] ?? 0];
            case 'languages':
                return ['code' => $code, 'name' => is_array($data) ? $data['name'] : $data];
            case 'aiModels':
                return [
                    'code' => $code, 
                    'name' => $data['name'], 
                    'provider_id' => $data['provId'] ?? '', 
                    'ordering' => $data['ordering'] ?? 0
                ];
            case 'wrappers':
                return [
                    'code' => $code,
                    'name' => $data['name'],
                    'description' => $data['desc'] ?? '',
                    'category_code' => $data['cat'] ?? '',
                    'featured' => $data['featured'] ?? 0,
                    'base' => $data['base'] ?? '',
                    'ordering' => $data['ordering'] ?? 0
                ];
            default:
                return [];
        }
    }

    /**
     * Specific mirror logic for Block Templates (Type + Code composite key)
     */
    private function mirrorTemplates($templates) {
        global $wpdb;
        $table = $wpdb->prefix . 'geekybot_zywrap_block_templates';
        $keep_ids = [];

        foreach ($templates as $type => $items) {
            if (!is_array($items)) continue;
            
            foreach ($items as $code => $name) {
                // 1. Check if the record already exists
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE type = %s AND code = %s",
                    $type,
                    $code
                ));

                $data = [
                    'type' => $type,
                    'code' => $code,
                    'name' => $name
                ];

                if ($exists) {
                    // 2. Update only if necessary
                    $wpdb->update($table, $data, ['type' => $type, 'code' => $code]);
                } else {
                    // 3. Insert if new
                    $wpdb->insert($table, $data);
                }

                // Track this ID so we don't delete it later
                $keep_ids[] = $wpdb->prepare("(type = %s AND code = %s)", $type, $code);
            }
        }

        // 4. Cleanup obsolete templates
        if (!empty($keep_ids)) {
            $where_clause = implode(' OR ', $keep_ids);
            $wpdb->query("DELETE FROM $table WHERE NOT ($where_clause)");
        }
    }

    

    
    /**
     * AJAX Function: Performs a DELTA data sync.
     */
    function sync_zywrap_delta() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_delta_sync')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        $api_key = get_option('geekybot_zywrap_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key is not set.'));
        }

        // 1. Get current local version
        $current_version = get_option('geekybot_zywrap_version');
        if (empty($current_version)) {
            wp_send_json_error(array('message' => 'No local version found. Please run a Full Import first.'));
        }

        // 2. Call the sync endpoint
        $geekybot_url = 'https://api.zywrap.com/v1/sdk/export/updates?fromVersion=' . urlencode($current_version);
        $response = wp_remote_get($geekybot_url, array(
            'timeout' => 300,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $geekybot_error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
            $this->log_api_call('sync_data', 'error', ['error_message' => 'Fetch failed: ' . $geekybot_error_msg]);
            wp_send_json_error(array('message' => 'Failed to fetch updates from Zywrap API.'));
        }

        $patch = json_decode(wp_remote_retrieve_body($response), true);
        if (!$patch || empty($patch['newVersion'])) {
            wp_send_json_error(array('message' => 'Could not decode a valid patch from the API.'));
        }

        // 3. Apply the patch
        global $wpdb;
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0;');

        try {
            // Process Updates/Creations (UPSERT)
            if (!empty($patch['updates'])) {
                // --- Process Wrappers ---
                if (!empty($patch['updates']['wrappers'])) {
                    foreach ($patch['updates']['wrappers'] as $geekybot_item) $wpdb->replace($wpdb->prefix . 'geekybot_zywrap_wrappers', $geekybot_item);
                }
                // --- Process Categories ---
                if (!empty($patch['updates']['categories'])) {
                    foreach ($patch['updates']['categories'] as $geekybot_item) $wpdb->replace($wpdb->prefix . 'geekybot_zywrap_categories', $geekybot_item);
                }
                if (!empty($patch['updates']['languages'])) {
                     foreach ($patch['updates']['languages'] as $geekybot_item) $wpdb->replace($wpdb->prefix . 'geekybot_zywrap_languages', $geekybot_item);
                }
                if (!empty($patch['updates']['aiModels'])) {
                     foreach ($patch['updates']['aiModels'] as $geekybot_item) $wpdb->replace($wpdb->prefix . 'geekybot_zywrap_ai_models', $geekybot_item);
                }

                // Block Templates
                $blockTypes = ['tones', 'styles', 'formattings', 'complexities', 'lengths', 'outputTypes', 'responseGoals', 'audienceLevels'];
                foreach ($blockTypes as $type) {
                    if (!empty($patch['updates'][$type])) {
                        foreach ($patch['updates'][$type] as $geekybot_item) {
                            $geekybot_item['type'] = $type;
                            $wpdb->replace($wpdb->prefix . 'geekybot_zywrap_block_templates', $geekybot_item);
                        }
                    }
                }
            }

            // Process Deletions
            if (!empty($patch['deletions'])) {
                foreach ($patch['deletions'] as $geekybot_item) {
                    $geekybot_table_name = '';
                    if ($geekybot_item['type'] == 'Wrapper') $geekybot_table_name = $wpdb->prefix . 'geekybot_zywrap_wrappers';
                    if ($geekybot_item['type'] == 'Category') $geekybot_table_name = $wpdb->prefix . 'geekybot_zywrap_categories';
                    if ($geekybot_item['type'] == 'Language') $geekybot_table_name = $wpdb->prefix . 'geekybot_zywrap_languages';
                    if ($geekybot_item['type'] == 'AIModel') $geekybot_table_name = $wpdb->prefix . 'geekybot_zywrap_ai_models';

                    if ($geekybot_table_name) {
                        $wpdb->delete($geekybot_table_name, array('code' => $geekybot_item['code']));
                    }

                    if (str_ends_with($geekybot_item['type'], 'BlockTemplate')) {
                         $wpdb->delete($wpdb->prefix . 'geekybot_zywrap_block_templates', array('code' => $geekybot_item['code']));
                    }
                }
            }

            $wpdb->query('SET FOREIGN_KEY_CHECKS = 1;');

        } catch (Exception $e) {
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 1;');
            wp_send_json_error(array('message' => 'Database error applying patch: ' . $e->getMessage()));
        }

        // 4. Save the new version
        update_option('geekybot_zywrap_version', $patch['newVersion']);
        $this->log_api_call('sync_data', 'success', ['error_message' => 'Synced to: ' . $patch['newVersion']]);
        wp_send_json_success(array('message' => 'Sync complete. New version: ' . $patch['newVersion']));
    }

    /**
     * Loads all data from local DB tables for the playground UI.
     */
    function getPlaygroundData() {
        global $wpdb;
        $geekybot_data = array();

        // Get Categories
        $geekybot_data['categories'] = $wpdb->get_results("SELECT code, name FROM `" . $wpdb->prefix . "geekybot_zywrap_categories` ORDER BY ordering ASC");

        // Get AI Models
        $geekybot_data['models'] = $wpdb->get_results("SELECT code, name FROM `" . $wpdb->prefix . "geekybot_zywrap_ai_models` ORDER BY ordering ASC");

        // Get Languages
        $geekybot_data['languages'] = $wpdb->get_results("SELECT code, name FROM `" . $wpdb->prefix . "geekybot_zywrap_languages` ORDER BY ordering ASC");

        // Get Block Templates (Overrides)
        $geekybot_templates_raw = $wpdb->get_results("SELECT type, code, name FROM `" . $wpdb->prefix . "geekybot_zywrap_block_templates` ORDER BY type, name ASC");

        // Group templates by type
        $geekybot_grouped_templates = [];
        foreach ($geekybot_templates_raw as $geekybot_row) {
            $geekybot_grouped_templates[$geekybot_row->type][] = array('code' => $geekybot_row->code, 'name' => $geekybot_row->name);
        }
        $geekybot_data['templates'] = $geekybot_grouped_templates;

        // Store in the main class data if needed, matching usage in controller
        geekybot::$_data['playground_data'] = $geekybot_data;
        return $geekybot_data;
    }

    /**
     * AJAX Function: Gets wrappers for a specific category.
     */
    function get_wrappers_by_category() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_get_wrappers')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        global $wpdb;
        $geekybot_category_code = GEEKYBOTrequest::GEEKYBOT_getVar('category_code', 'post');
        $geekybot_show_featured = GEEKYBOTrequest::GEEKYBOT_getVar('show_featured', 'post') === 'true';
        $geekybot_show_base = GEEKYBOTrequest::GEEKYBOT_getVar('show_base', 'post') === 'true';

        if (empty($geekybot_category_code)) {
            wp_send_json_success(array());
        }

        $query = $wpdb->prepare("SELECT code, name, featured, base FROM `" . $wpdb->prefix . "geekybot_zywrap_wrappers` WHERE category_code = %s", $geekybot_category_code);

        $wrappers = $wpdb->get_results($query);

        // Apply filters in PHP
        if ($geekybot_show_featured) {
            $wrappers = array_filter($wrappers, function($w) { return $w->featured; });
        }
        if ($geekybot_show_base) {
            $wrappers = array_filter($wrappers, function($w) { return $w->base; });
        }
        //wp_send_json_success(array("ok 64"));

        wp_send_json_success(array_values($wrappers)); // Re-index array
    }

    /**
     * AJAX Function: Executes the live API proxy call.
     */
    /**
     * AJAX Function: Executes the live API proxy call.
     */
    function execute_zywrap_proxy() {
        global $wpdb; // Added $wpdb to allow direct database insertion

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $geekybot_nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_execute_proxy')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        $api_key = get_option('geekybot_zywrap_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key is not set.'));
        }

        $geekybot_model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $wrapper_code = isset($_POST['wrapperCode']) ? sanitize_text_field($_POST['wrapperCode']) : '';
        $geekybot_prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        $geekybot_language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
        
        $geekybot_overrides = isset($_POST['overrides']) && is_array($_POST['overrides']) ? wp_unslash($_POST['overrides']) : array();
        $variables = isset($_POST['variables']) && is_array($_POST['variables']) ? wp_unslash($_POST['variables']) : array();

        $geekybot_payloadData = array(
            'model' => $geekybot_model,
            'wrapperCodes' => array($wrapper_code),
            'prompt' => $geekybot_prompt,
            'source' => 'geekybot_v1_plugin'
        );

        // FIXED: Do not use sanitize_key() as it destroys camelCase (e.g. productName -> productname)
        if (!empty($variables)) {
            $clean_vars = array();
            foreach($variables as $k => $v) {
                $clean_key = sanitize_text_field($k);
                $clean_vars[$clean_key] = sanitize_textarea_field($v);
            }
            $geekybot_payloadData['variables'] = $clean_vars;
        }

        if (!empty($geekybot_language)) {
            $geekybot_payloadData['language'] = $geekybot_language;
        }
        
        // FIXED OVERRIDES: Map directly using a strict whitelist to preserve camelCase
        $allowed_overrides = [
            'toneCode', 'styleCode', 'formatCode', 'complexityCode', 
            'lengthCode', 'audienceCode', 'responseGoalCode', 'outputCode'
        ];
        
        if (!empty($geekybot_overrides)) {
            foreach($geekybot_overrides as $k => $v) {
                if (in_array($k, $allowed_overrides)) {
                    $geekybot_payloadData[$k] = sanitize_text_field($v);
                }
            }
        }
        
        $geekybot_url = 'https://api.zywrap.com/v1/proxy';
        
        $json_body = json_encode($geekybot_payloadData);
        if ($json_body === false) {
            wp_send_json_error(array('message' => 'JSON Encoding failed. Check for invalid characters in prompt.'));
        }

        $geekybot_args = array(
            'method'    => 'POST',
            'timeout'   => 300, 
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body'      => $json_body
        );

        // --- EXECUTE REQUEST ---
        $response = wp_remote_post($geekybot_url, $geekybot_args);

        // --- PREPARE LOGGING VARIABLES ---
        $log_table = $wpdb->prefix . 'geekybot_zywrap_logs';
        $http_code = is_wp_error($response) ? 500 : wp_remote_retrieve_response_code($response);
        $status = ($http_code === 200) ? 'success' : 'error';
        $error_message = is_wp_error($response) ? $response->get_error_message() : null;
        
        $geekybot_data = null;
        $trace_id = null;
        $prompt_tokens = 0;
        $completion_tokens = 0;
        $total_tokens = 0;

        // --- PARSE RESPONSE ---
        if (!is_wp_error($response)) {
            $raw_body = wp_remote_retrieve_body($response);
            
            if ($http_code === 200) {
                $lines = explode("\n", $raw_body);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'data: ') === 0) {
                        $json_str = substr($line, 6);
                        $decoded = json_decode($json_str, true);
                        if ($decoded && (isset($decoded['output']) || isset($decoded['error']))) {
                            $geekybot_data = $decoded;
                        }
                    }
                }

                if (!$geekybot_data) {
                     $status = 'error';
                     $error_message = 'Failed to parse streaming response from Zywrap.';
                }
            } else {
                $geekybot_data = json_decode($raw_body, true);
                if (!$error_message) {
                    $error_message = isset($geekybot_data['message']) ? $geekybot_data['message'] : substr($raw_body, 0, 255);
                }
            }
        }

        // --- EXTRACT METADATA ---
        if ($geekybot_data) {
            $trace_id = $geekybot_data['id'] ?? null;
            if (isset($geekybot_data['usage'])) {
                $prompt_tokens = $geekybot_data['usage']['prompt_tokens'] ?? 0;
                $completion_tokens = $geekybot_data['usage']['completion_tokens'] ?? 0;
                $total_tokens = $geekybot_data['usage']['total_tokens'] ?? 0;
            }
            if ($status === 'error' && empty($error_message)) {
                $error_message = $geekybot_data['error'] ?? 'Unknown Error';
            }
        }

        // --- DIRECT DATABASE INSERT (Bypassing old log_api_call) ---
        $wpdb->insert($log_table, array(
            'trace_id'          => $trace_id,
            'timestamp'         => current_time('mysql'),
            'user_id'           => get_current_user_id(),
            'status'            => $status,
            'action'            => 'proxy_execute',
            'wrapper_code'      => $wrapper_code,
            'model_code'        => $geekybot_model,
            'http_code'         => $http_code,
            'error_message'     => $error_message,
            'prompt_tokens'     => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'total_tokens'      => $total_tokens
        ));

        // --- RETURN RESPONSE TO UI ---
        if ($status === 'error' || is_wp_error($response) || $http_code !== 200) {
             wp_send_json_error(array('message' => "Error (Code $http_code): $error_message"));
        } else {
             wp_send_json_success($geekybot_data);
        }
    }

    function get_api_error_message($code, $fallback_msg = '') {
        $code = (int) $code;
        
        switch ($code) {
            case 400:
                return "Bad Request: The request was unacceptable (e.g., missing a required parameter).";
            case 401:
                return "Unauthorized: Your API key is invalid or missing. Please check your settings.";
            case 402: // Critical for user awareness
                return "Payment Required: You have run out of credits. Please upgrade your plan to continue.";
            case 403:
                return "Forbidden: You do not have permission to perform this request.";
            case 404:
                return "Not Found: The requested resource (wrapper or model) does not exist.";
            case 429:
                return "Too Many Requests: You are generating too fast! Please wait a moment and try again.";
            case 500:
            case 502:
            case 503:
            case 504:
                return "Server Error: Something went wrong on Zywrap's side. Please try again later.";
            default:
                return !empty($fallback_msg) ? $fallback_msg : "An unknown error occurred (Code: $code).";
        }
    }
    /**
     * Helper to expand V1 Tabular JSON data
     */
    private function extractTabular($tabularData) {
        if (empty($tabularData['cols']) || empty($tabularData['data'])) return [];
        $cols = $tabularData['cols'];
        $result = [];
        foreach ($tabularData['data'] as $row) {
            $result[] = array_combine($cols, $row);
        }
        return $result;
    }

    /**
     * AJAX Function: Primary V1 Sync Endpoint (Optimized Streaming & Chunking)
     */
    function syncZywrapData() {
        // NONCE SECURITY CHECK
        $geekybot_nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_full_import')) {
            wp_send_json_error(array('message' => 'Security check Failed'));
        }

        // SECURITY: ONLY ADMINISTRATORS CAN ACCESS
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Security Error: Unauthorized access. Administrators only.'));
            return;
        }

        $api_key = get_option('geekybot_zywrap_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Please save your API key first.', 'geeky-bot')));
        }

        @ini_set('memory_limit', '768M');
        @set_time_limit(700);

        // Fetch the local version to see if we qualify for a Delta Update
        $local_version = get_option('geekybot_zywrap_bundle_version', '');
        
        $sync_url = 'https://api.zywrap.com/v1/sdk/v1/sync?fromVersion=' . urlencode($local_version);
        $response = wp_remote_get($sync_url, array(
            'timeout' => 600,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Sync failed: ', 'geeky-bot') . $response->get_error_message()));
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            wp_send_json_error(array('message' => __('API Error: Invalid response code ', 'geeky-bot') . $http_code));
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (!$json) {
            wp_send_json_error(array('message' => __('Failed to parse Sync JSON data.', 'geeky-bot')));
        }

        $mode = isset($json['mode']) ? $json['mode'] : 'UNKNOWN';

        if ($mode === 'FULL_RESET') {
            // --- SCENARIO A: FULL RESET (Streaming Download & Replace All) ---
            $download_url = isset($json['wrappers']['downloadUrl']) ? $json['wrappers']['downloadUrl'] : 'https://api.zywrap.com/v1/sdk/v1/download';
            
            // 1. Define safe paths in the uploads directory (Guaranteed write permissions)
            $upload_dir = wp_upload_dir();
            $temp_file = trailingslashit($upload_dir['basedir']) . 'zywrap_bundle_' . time() . '.zip';
            $extract_path = trailingslashit($upload_dir['basedir']) . 'geekybot-zywrap-temp';

            // 2. Stream the download directly to the disk (Bypasses RAM limits)
            $zip_response = wp_remote_get($download_url, array(
                'timeout' => 600, // Generous timeout for large files
                'sslverify' => false,
                'headers' => array('Authorization' => 'Bearer ' . $api_key),
                'stream' => true,
                'filename' => $temp_file
            ));

            if (is_wp_error($zip_response)) {
                @unlink($temp_file);
                wp_send_json_error(array('message' => __('Download failed: ', 'geeky-bot') . $zip_response->get_error_message()));
            }

            $response_code = wp_remote_retrieve_response_code($zip_response);
            if ($response_code !== 200) {
                @unlink($temp_file);
                wp_send_json_error(array('message' => __('Download rejected. HTTP Code: ', 'geeky-bot') . $response_code));
            }

            // 3. Initialize WordPress Filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                WP_Filesystem();
            }

            // 4. Prepare extraction folder
            if ($wp_filesystem->exists($extract_path)) {
                $wp_filesystem->rmdir($extract_path, true);
            }
            wp_mkdir_p($extract_path);

            // 5. Unzip the file
            $unzip_result = unzip_file($temp_file, $extract_path);
            
            // Always clean up the temp zip file immediately after extracting
            @unlink($temp_file);

            if (is_wp_error($unzip_result)) {
                wp_send_json_error(array('message' => __('Unzip failed: ', 'geeky-bot') . $unzip_result->get_error_message()));
            }

            // 6. Process the JSON data
            $json_file = trailingslashit($extract_path) . 'zywrap-data.json';
            if (!file_exists($json_file)) {
                 wp_send_json_error(array('message' => __('zywrap-data.json not found in bundle.', 'geeky-bot')));
            }

            $json_data = file_get_contents($json_file);
            $data = json_decode($json_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array('message' => __('Failed to parse bundle JSON data.', 'geeky-bot')));
            }

            // 7. Save to Database
            $this->process_full_sync($data);
            if (isset($data['version'])) {
                update_option('geekybot_zywrap_bundle_version', sanitize_text_field($data['version']));
            }
            
            // Clean up the extraction folder
            $wp_filesystem->rmdir($extract_path, true);

        } elseif ($mode === 'DELTA_UPDATE') {
            // --- SCENARIO B: DELTA UPDATE (Fast Upsert & Reconcile) ---
            $this->process_delta_sync($json);
            if (!empty($json['newVersion'])) {
                update_option('geekybot_zywrap_bundle_version', sanitize_text_field($json['newVersion']));
            }
        } else {
             wp_send_json_success(array('message' => __('No sync required. Data is already up to date.', 'geeky-bot')));
             return;
        }

        update_option('geekybot_zywrap_last_sync_time', time());

        $clean_mode = str_replace('_', ' ', $mode);
        wp_send_json_success(array('message' => __('AI Data Synced Successfully! (Mode: ' . $clean_mode . ')', 'geeky-bot')));
    }

    private function process_full_sync($data) {
        global $wpdb;
        $prefix = $wpdb->prefix . "geekybot_";

        $wpdb->query("SET FOREIGN_KEY_CHECKS = 0;");
        $wpdb->query("TRUNCATE TABLE `" . $prefix . "zywrap_categories`");
        $wpdb->query("TRUNCATE TABLE `" . $prefix . "zywrap_use_cases`");
        $wpdb->query("TRUNCATE TABLE `" . $prefix . "zywrap_wrappers`");
        $wpdb->query("TRUNCATE TABLE `" . $prefix . "zywrap_ai_models`");
        $wpdb->query("TRUNCATE TABLE `" . $prefix . "zywrap_languages`");
        $wpdb->query("TRUNCATE TABLE `" . $prefix . "zywrap_block_templates`");
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 1;");

        if (!empty($data['categories'])) {
            $cats = $this->extract_tabular($data['categories']);
            foreach ($cats as $c) {
                $query = "INSERT INTO `" . $prefix . "zywrap_categories` (`code`, `name`, `ordering`) VALUES (
                    '" . esc_sql($c['code']) . "', '" . esc_sql($c['name']) . "', " . (int)($c['ordering'] ?? 9999) . "
                )";
                $wpdb->query($query);
            }
        }

        // ---------------------------------------------------------
        // BATCH INSERT: USE CASES
        // ---------------------------------------------------------
        if (!empty($data['useCases'])) {
            $ucs = $this->extract_tabular($data['useCases']);
            $chunk_size = 500; // Safe chunk size to respect MySQL max_allowed_packet
            $chunks = array_chunk($ucs, $chunk_size);
            
            foreach ($chunks as $chunk) {
                $values = array();
                foreach ($chunk as $uc) {
                    $schemaJson = !empty($uc['schema']) ? wp_json_encode($uc['schema']) : null;
                    $values[] = "(
                        '" . esc_sql($uc['code']) . "', 
                        '" . esc_sql($uc['name']) . "', 
                        '" . esc_sql($uc['desc'] ?? '') . "', 
                        '" . esc_sql($uc['cat'] ?? '') . "', 
                        '" . esc_sql($schemaJson) . "', 
                        " . (int)($uc['ordering'] ?? 9999) . "
                    )";
                }
                $query = "INSERT INTO `" . $prefix . "zywrap_use_cases` (`code`, `name`, `description`, `category_code`, `schema_data`, `ordering`) VALUES " . implode(', ', $values);
                $wpdb->query($query);
            }
        }

        // ---------------------------------------------------------
        // BATCH INSERT: WRAPPERS (Massive Dataset Optimization)
        // ---------------------------------------------------------
        if (!empty($data['wrappers'])) {
            $wrappers = $this->extract_tabular($data['wrappers']);
            $chunk_size = 1000; // Grouping 1000 wrappers per query
            $chunks = array_chunk($wrappers, $chunk_size);
            
            foreach ($chunks as $chunk) {
                $values = array();
                foreach ($chunk as $w) {
                    $values[] = "(
                        '" . esc_sql($w['code']) . "', 
                        '" . esc_sql($w['name']) . "', 
                        '" . esc_sql($w['desc'] ?? '') . "', 
                        '" . esc_sql($w['usecase'] ?? '') . "', 
                        " . (!empty($w['featured']) ? 1 : 0) . ", 
                        " . (!empty($w['base']) ? 1 : 0) . ", 
                        " . (int)($w['ordering'] ?? 9999) . "
                    )";
                }
                $query = "INSERT INTO `" . $prefix . "zywrap_wrappers` (`code`, `name`, `description`, `use_case_code`, `featured`, `base`, `ordering`) VALUES " . implode(', ', $values);
                $wpdb->query($query);
            }
        }

        if (!empty($data['aiModels'])) {
            $models = $this->extract_tabular($data['aiModels']);
            foreach ($models as $m) {
                $query = "INSERT INTO `" . $prefix . "zywrap_ai_models` (`code`, `name`, `ordering`) VALUES (
                    '" . esc_sql($m['code']) . "', '" . esc_sql($m['name']) . "', " . (int)($m['ordering'] ?? 9999) . "
                )";
                $wpdb->query($query);
            }
        }

        if (!empty($data['languages'])) {
            $langs = $this->extract_tabular($data['languages']);
            foreach ($langs as $l) {
                $query = "INSERT INTO `" . $prefix . "zywrap_languages` (`code`, `name`, `ordering`) VALUES (
                    '" . esc_sql($l['code']) . "', '" . esc_sql($l['name']) . "', " . (int)($l['ordering'] ?? 9999) . "
                )";
                $wpdb->query($query);
            }
        }

        if (!empty($data['templates'])) {
            foreach ($data['templates'] as $type => $tabular) {
                $templates = $this->extract_tabular($tabular);
                foreach ($templates as $t) {
                    $query = "INSERT INTO `" . $prefix . "zywrap_block_templates` (`type`, `code`, `name`) VALUES (
                        '" . esc_sql($type) . "', '" . esc_sql($t['code']) . "', '" . esc_sql($t['name']) . "'
                    )";
                    $wpdb->query($query);
                }
            }
        }
    }

    private function process_delta_sync($json) {
        global $wpdb;
        $prefix = $wpdb->prefix . "geekybot_";

        if (!empty($json['metadata']['categories'])) {
            foreach ($json['metadata']['categories'] as $r) {
                $status = (!isset($r['status']) || $r['status']) ? 1 : 0;
                $ordering = $r['position'] ?? $r['displayOrder'] ?? $r['ordering'] ?? 9999;
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO `{$prefix}zywrap_categories` (`code`, `name`, `status`, `ordering`) VALUES (%s, %s, %d, %d) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `status`=VALUES(`status`), `ordering`=VALUES(`ordering`)",
                    $r['code'], $r['name'], $status, $ordering
                ));
            }
        }

        if (!empty($json['metadata']['languages'])) {
            foreach ($json['metadata']['languages'] as $r) {
                $status = (!isset($r['status']) || $r['status']) ? 1 : 0;
                $ordering = $r['ordering'] ?? 9999;
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO `{$prefix}zywrap_languages` (`code`, `name`, `status`, `ordering`) VALUES (%s, %s, %d, %d) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `status`=VALUES(`status`), `ordering`=VALUES(`ordering`)",
                    $r['code'], $r['name'], $status, $ordering
                ));
            }
        }

        if (!empty($json['metadata']['aiModels'])) {
            foreach ($json['metadata']['aiModels'] as $r) {
                $status = (!isset($r['status']) || $r['status']) ? 1 : 0;
                $ordering = $r['displayOrder'] ?? $r['ordering'] ?? 9999;
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO `{$prefix}zywrap_ai_models` (`code`, `name`, `status`, `ordering`) VALUES (%s, %s, %d, %d) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `status`=VALUES(`status`), `ordering`=VALUES(`ordering`)",
                    $r['code'], $r['name'], $status, $ordering
                ));
            }
        }

        if (!empty($json['metadata']['templates'])) {
            foreach ($json['metadata']['templates'] as $type => $items) {
                foreach ($items as $item) {
                    $status = (!isset($item['status']) || $item['status']) ? 1 : 0;
                    $name = $item['label'] ?? $item['name'] ?? '';
                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO `{$prefix}zywrap_block_templates` (`type`, `code`, `name`, `status`) VALUES (%s, %s, %s, %d) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `status`=VALUES(`status`)",
                        $type, $item['code'], $name, $status
                    ));
                }
            }
        }

        if (!empty($json['useCases']['upserts'])) {
            foreach ($json['useCases']['upserts'] as $uc) {
                $schemaJson = !empty($uc['schema']) ? wp_json_encode($uc['schema']) : null;
                $status = (!isset($uc['status']) || $uc['status']) ? 1 : 0;
                $ordering = $uc['displayOrder'] ?? $uc['ordering'] ?? 9999;
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO `{$prefix}zywrap_use_cases` (`code`, `name`, `description`, `category_code`, `schema_data`, `status`, `ordering`) VALUES (%s, %s, %s, %s, %s, %d, %d) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`), `category_code`=VALUES(`category_code`), `schema_data`=VALUES(`schema_data`), `status`=VALUES(`status`), `ordering`=VALUES(`ordering`)",
                    $uc['code'], $uc['name'], $uc['description'] ?? '', $uc['categoryCode'] ?? '', $schemaJson, $status, $ordering
                ));
            }
        }

        if (!empty($json['useCases']['deletes'])) {
            foreach ($json['useCases']['deletes'] as $code) {
                $wpdb->query($wpdb->prepare("DELETE FROM `{$prefix}zywrap_use_cases` WHERE `code` = %s", $code));
            }
        }

        if (!empty($json['wrappers']['upserts'])) {
            foreach ($json['wrappers']['upserts'] as $w) {
                $featured = !empty($w['featured'] ?? $w['isFeatured']) ? 1 : 0;
                $base = !empty($w['base'] ?? $w['isBaseWrapper']) ? 1 : 0;
                $status = (!isset($w['status']) || $w['status']) ? 1 : 0;
                $ordering = $w['displayOrder'] ?? $w['ordering'] ?? 9999;
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO `{$prefix}zywrap_wrappers` (`code`, `name`, `description`, `use_case_code`, `featured`, `base`, `status`, `ordering`) VALUES (%s, %s, %s, %s, %d, %d, %d, %d) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`), `use_case_code`=VALUES(`use_case_code`), `featured`=VALUES(`featured`), `base`=VALUES(`base`), `status`=VALUES(`status`), `ordering`=VALUES(`ordering`)",
                    $w['code'], $w['name'], $w['description'] ?? '', $w['useCaseCode'] ?? $w['categoryCode'] ?? '', $featured, $base, $status, $ordering
                ));
            }
        }

        if (!empty($json['wrappers']['deletes'])) {
            foreach ($json['wrappers']['deletes'] as $code) {
                $wpdb->query($wpdb->prepare("DELETE FROM `{$prefix}zywrap_wrappers` WHERE `code` = %s", $code));
            }
        }
    }

    private function extract_tabular($tabularData) {
        if (empty($tabularData['cols']) || empty($tabularData['data'])) return array();
        $cols = $tabularData['cols'];
        $result = array();
        foreach ($tabularData['data'] as $row) {
            $result[] = array_combine($cols, $row);
        }
        return $result;
    }

    /**
     * V1 DELTA_UPDATE: Smart reconciliation based on sync API response.
     */
    private function handleDeltaUpdateSync($json) {
        // 1. Categories
        if (!empty($json['metadata']['categories'])) {
            $rows = [];
            foreach($json['metadata']['categories'] as $r) {
                $status = (!isset($r['status']) || $r['status']) ? 1 : 0;
                $rows[] = [$r['code'], $r['name'], $status, $r['position'] ?? $r['displayOrder'] ?? $r['ordering'] ?? null];
            }
            $this->wp_upsert_batch('geekybot_zywrap_categories', $rows, ['code', 'name', 'status', 'ordering']);
        }

        // 2. Languages
        if (!empty($json['metadata']['languages'])) {
            $rows = [];
            foreach($json['metadata']['languages'] as $r) {
                $status = (!isset($r['status']) || $r['status']) ? 1 : 0;
                $rows[] = [$r['code'], $r['name'], $status, $r['ordering'] ?? null];
            }
            $this->wp_upsert_batch('geekybot_zywrap_languages', $rows, ['code', 'name', 'status', 'ordering']);
        }

        // 3. AI Models
        if (!empty($json['metadata']['aiModels'])) {
            $rows = [];
            foreach($json['metadata']['aiModels'] as $r) {
                $status = (!isset($r['status']) || $r['status']) ? 1 : 0;
                $rows[] = [$r['code'], $r['name'], $status, $r['displayOrder'] ?? $r['ordering'] ?? null];
            }
            $this->wp_upsert_batch('geekybot_zywrap_ai_models', $rows, ['code', 'name', 'status', 'ordering']);
        }

        // 4. Templates
        if (!empty($json['metadata']['templates'])) {
            $rows = [];
            foreach ($json['metadata']['templates'] as $type => $items) {
                foreach ($items as $item) {
                    $status = (!isset($item['status']) || $item['status']) ? 1 : 0;
                    $rows[] = [$type, $item['code'], $item['label'] ?? $item['name'] ?? null, $status];
                }
            }
            $this->wp_upsert_batch('geekybot_zywrap_block_templates', $rows, ['type', 'code', 'name', 'status']);
        }

        // 5. Use Cases (NEW V1)
        if (!empty($json['useCases']['upserts'])) {
            $rows = [];
            foreach($json['useCases']['upserts'] as $uc) {
                $schemaJson = !empty($uc['schema']) ? json_encode($uc['schema']) : null;
                $status = (!isset($uc['status']) || $uc['status']) ? 1 : 0;
                $rows[] = [$uc['code'], $uc['name'], $uc['description'] ?? null, $uc['categoryCode'] ?? null, $schemaJson, $status, $uc['displayOrder'] ?? $uc['ordering'] ?? null];
            }
            $this->wp_upsert_batch('geekybot_zywrap_use_cases', $rows, ['code', 'name', 'description', 'category_code', 'schema_data', 'status', 'ordering']);
        }

        // 6. Wrappers
        if (!empty($json['wrappers']['upserts'])) {
            $rows = [];
            foreach($json['wrappers']['upserts'] as $w) {
                $featured = !empty($w['featured'] ?? $w['isFeatured']) ? 1 : 0;
                $base = !empty($w['base'] ?? $w['isBaseWrapper']) ? 1 : 0;
                $status = (!isset($w['status']) || $w['status']) ? 1 : 0;
                $rows[] = [$w['code'], $w['name'], $w['description'] ?? null, $w['useCaseCode'] ?? $w['categoryCode'] ?? null, $featured, $base, $status, $w['displayOrder'] ?? $w['ordering'] ?? null];
            }
            $this->wp_upsert_batch('geekybot_zywrap_wrappers', $rows, ['code', 'name', 'description', 'use_case_code', 'featured', 'base', 'status', 'ordering']);
        }

        // 7. Deletes
        if (!empty($json['wrappers']['deletes'])) {
            $this->wp_delete_batch('geekybot_zywrap_wrappers', $json['wrappers']['deletes']);
        }
        if (!empty($json['useCases']['deletes'])) {
            $this->wp_delete_batch('geekybot_zywrap_use_cases', $json['useCases']['deletes']);
        }

        if (!empty($json['newVersion'])) {
            update_option('geekybot_zywrap_bundle_version', $json['newVersion']);
        }
    }

    /**
     * Helper: Secure WordPress Upsert Batching mapped to V1 SDK logic
     */
    private function wp_upsert_batch($table_name, $rows, $columns, $pk = 'code') {
        if (empty($rows)) return;
        global $wpdb;
        $table = $wpdb->prefix . $table_name;

        $colList = implode(", ", array_map(function($c) { return "`$c`"; }, $columns));
        $placeholders = "(" . implode(", ", array_fill(0, count($columns), "%s")) . ")";

        $updateClause = [];
        foreach ($columns as $col) {
            if ($col !== $pk && $col !== 'type') {
                $updateClause[] = "`$col` = VALUES(`$col`)";
            }
        }
        $updateSql = implode(", ", $updateClause);

        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            $values = [];
            $chunkPlaceholders = [];
            foreach ($chunk as $row) {
                $chunkPlaceholders[] = $placeholders;
                foreach ($row as $val) $values[] = $val;
            }

            $sql = "INSERT INTO $table ($colList) VALUES " . implode(", ", $chunkPlaceholders) . " ON DUPLICATE KEY UPDATE $updateSql";
            $wpdb->query($wpdb->prepare($sql, ...$values));
        }
    }

    /**
     * Helper: Batch Delete
     */
    private function wp_delete_batch($table_name, $ids, $pk = 'code') {
        if (empty($ids)) return;
        global $wpdb;
        $table = $wpdb->prefix . $table_name;
        
        $chunks = array_chunk($ids, 500);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%s'));
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE $pk IN ($placeholders)", ...$chunk));
        }
    }
    /**
     * AJAX Function: Gets Use Cases for a specific Category. Supports Global Featured filtering.
     */
    function get_use_cases_by_category() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_get_wrappers')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        global $wpdb;
        $geekybot_category_code = GEEKYBOTrequest::GEEKYBOT_getVar('category_code', 'post');
        $geekybot_show_featured = isset($_POST['show_featured']) && $_POST['show_featured'] === 'true';

        if (empty($geekybot_category_code)) {
            wp_send_json_success(array());
        }

        // Apply JOIN filter if user requests featured only
        if ($geekybot_show_featured) {
            $query = $wpdb->prepare("
                SELECT DISTINCT uc.code, uc.name 
                FROM `" . $wpdb->prefix . "geekybot_zywrap_use_cases` uc
                JOIN `" . $wpdb->prefix . "geekybot_zywrap_wrappers` w ON w.use_case_code = uc.code
                WHERE uc.category_code = %s AND uc.status = 1 AND w.status = 1 AND w.featured = 1
                ORDER BY uc.ordering ASC
            ", $geekybot_category_code);
        } else {
            $query = $wpdb->prepare("
                SELECT code, name 
                FROM `" . $wpdb->prefix . "geekybot_zywrap_use_cases` 
                WHERE category_code = %s AND status = 1 
                ORDER BY ordering ASC
            ", $geekybot_category_code);
        }
        
        $use_cases = $wpdb->get_results($query);
        wp_send_json_success(array_values($use_cases));
    }

    /**
     * AJAX Function: Gets wrappers for a specific Use Case.
     */
    function get_wrappers_by_usecase() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_get_wrappers')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        global $wpdb;
        $geekybot_usecase_code = GEEKYBOTrequest::GEEKYBOT_getVar('usecase_code', 'post');
        $geekybot_show_featured = isset($_POST['show_featured']) && $_POST['show_featured'] === 'true';

        if (empty($geekybot_usecase_code)) {
            wp_send_json_success(array());
        }

        $query = $wpdb->prepare("SELECT code, name, featured, base FROM `" . $wpdb->prefix . "geekybot_zywrap_wrappers` WHERE use_case_code = %s AND status = 1 ORDER BY ordering ASC", $geekybot_usecase_code);
        $wrappers = $wpdb->get_results($query);

        if ($geekybot_show_featured) {
            $wrappers = array_filter($wrappers, function($w) { return $w->featured; });
        }

        wp_send_json_success(array_values($wrappers)); 
    }

    /**
     * AJAX Function: Gets dynamic schema for a specific wrapper (Cascade 3).
     */
    function get_wrapper_schema() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        
        global $wpdb;
        $wrapper_code = isset($_POST['wrapper_code']) ? sanitize_text_field($_POST['wrapper_code']) : '';
        
        if (empty($wrapper_code)) wp_send_json_success(null);

        // Fetch schema from the joined use_cases table
        $query = $wpdb->prepare("
            SELECT uc.schema_data 
            FROM `" . $wpdb->prefix . "geekybot_zywrap_use_cases` uc 
            JOIN `" . $wpdb->prefix . "geekybot_zywrap_wrappers` w ON w.use_case_code = uc.code 
            WHERE w.code = %s
        ", $wrapper_code);
        
        $result = $wpdb->get_var($query);
        $schema = $result ? json_decode($result, true) : null;
        
        wp_send_json_success($schema);
    }
    /**
     * get Total Wrappers for main file
     */
    function getTotalWrappers() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        
        global $wpdb;

        $total_wrappers = $wpdb->get_var("SELECT COUNT(code) FROM `".$wpdb->prefix."geekybot_zywrap_wrappers`");
        $total_wrappers = $total_wrappers ? (int) $total_wrappers : 0;

        return $total_wrappers;
    }

    /**
     * Fetches all initial data required for the Editor Drawer.
     * Returns false if the database isn't synced or missing.
     */
    public function get_editor_drawer_data() {
        if (!current_user_can('edit_posts')) return false; // ADD THIS
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'geekybot_zywrap_';

        // 1. Check if synced (Security & Health Check)
        $cat_table = $prefix . 'categories';
        if ($wpdb->get_var("SHOW TABLES LIKE '$cat_table'") !== $cat_table) {
            return false;
        }
        
        $is_synced = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$cat_table`");
        if ($is_synced === 0) {
            return false;
        }

        // 2. Fetch Data securely
        $categories = $wpdb->get_results("SELECT code, name FROM `$cat_table` WHERE status = 1 ORDER BY ordering ASC");
        
        $models_table = $prefix . 'ai_models';
        $models = $wpdb->get_results("SELECT code, name FROM `$models_table` WHERE status = 1 ORDER BY ordering ASC");
        
        $languages_table = $prefix . 'languages';
        $languages = $wpdb->get_results("SELECT code, name FROM `$languages_table` WHERE status = 1 ORDER BY ordering ASC");
        
        $templates_table = $prefix . 'block_templates';
        $templates_raw = $wpdb->get_results("SELECT type, code, name FROM `$templates_table` WHERE status = 1 ORDER BY type, name ASC");
        
        $templates = [];
        if ($templates_raw) {
            foreach ($templates_raw as $tpl) {
                $templates[$tpl->type][] = $tpl;
            }
        }

        return [
            'categories' => $categories,
            'models'     => $models,
            'languages'  => $languages,
            'templates'  => $templates
        ];
    }

}
?>
