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
            'timeout' => 15,
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

        update_option('zywrap_bundle_version', $geekybot_data['version'] ?? 'unknown');
        $sync_time = time();
        update_option('zywrap_last_sync_time', $sync_time);
        // Import wrappers
        if (!empty($geekybot_data['wrappers'])) {
            $output_array['wrappers'] = count($geekybot_data['wrappers']);
            $geekybot_result = $this->importZywrapWrappersInBatches($geekybot_data['wrappers'], $output_array['wrappers']);
            set_transient('wpjp_import_counts_cache', $this->zywrap_import_counts, HOUR_IN_SECONDS);

            wp_send_json_success($geekybot_result);
        }
        return;
    }

    function syncZywrapData() {
        try {
            if (function_exists('set_time_limit')) { set_time_limit(300); }
            @ini_set('memory_limit', '1024M');

            // Verify Nonce & Permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Permission denied.', 'geeky-bot'));
            }
            $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
            if (!wp_verify_nonce($geekybot_nonce, 'zywrap_full_import')) {
                wp_send_json_error(array('message' => 'Security check failed.'));
            }

            $api_key = get_option('geekybot_zywrap_api_key');
            $local_version = get_option('zywrap_bundle_version', '');
            $api_url = add_query_arg('fromVersion', $local_version, 'https://api.zywrap.com/v1/sdk/export/updates');

            // 1. API Call with NEW SETTINGS (Timeout 300 & SSL Disable)
            $response = wp_remote_get($api_url, [
                'timeout'   => 300,
                'sslverify' => false,
                'headers'   => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                throw new Exception('API Connection Failed: ' . $response->get_error_message());
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $raw_body  = wp_remote_retrieve_body($response);

            // 2. NEW PARSING LOGIC: Handle Streaming/SSE Response
            $final_data = null;
            if ($http_code === 200) {
                $lines = explode("\n", $raw_body);
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Look for lines starting with "data: "
                    if (strpos($line, 'data: ') === 0) {
                        $json_str = substr($line, 6);
                        $decoded  = json_decode($json_str, true);
                        
                        // Validate if this is the actual sync payload
                        // Based on manager's logic: check for 'mode' or 'wrappers'
                        if ($decoded && (isset($decoded['mode']) || isset($decoded['wrappers']))) {
                            $final_data = $decoded;
                        }
                    }
                }
            }

            // If streaming parse failed, try a direct decode (fallback)
            if (!$final_data) {
                $final_data = json_decode($raw_body, true);
            }

            if (!$final_data) {
                throw new Exception(__('Failed to parse streaming response from Zywrap.', 'geeky-bot'));
            }

            // 3. Process Sync Mode
            $mode = $final_data['mode'] ?? '';

            if ($mode === 'FULL_RESET') {
                if (empty($final_data['wrappers']['downloadUrl'])) {
                    throw new Exception(__('Full Reset triggered but no Download URL provided.', 'geeky-bot'));
                }
                $this->handleFullResetSync($final_data['wrappers']['downloadUrl'], $final_data['wrappers']['version']);
                $msg = "Full Reset Sync Complete";
            } elseif ($mode === 'DELTA_UPDATE') {
                $this->handleDeltaUpdateSync($final_data);
                $msg = "Delta Sync Complete";
            } else {
                // No mode means no sync available
                if (ob_get_length()) ob_clean();
                wp_send_json_success(['message' => __('No sync required at this time.', 'geeky-bot')]);
                return;
            }

            // Clear buffer and send success
            if (ob_get_length()) ob_clean();
            wp_send_json_success(['message' => $msg]);
            return;

        } catch (Exception $e) {
            // If anything fails, it comes here immediately
            if (ob_get_length()) ob_clean();
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

        /**
         * Handles the FULL_RESET logic using WP_Filesystem and unzip_file
         */
    private function handleFullResetSync($download_url, $new_version) {
        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();

        $api_key = get_option('geekybot_zywrap_api_key');

        // 1. Define a temporary filter to inject the Authorization header
        $download_args_filter = function( $args, $url ) use ( $api_key, $download_url ) {
            // Only inject headers if the URL matches our download URL
            if ( $url === $download_url ) {
                $args['headers']['Authorization'] = 'Bearer ' . $api_key;
                $args['timeout']   = 300;
                $args['sslverify'] = false;
            }
            return $args;
        };

        // 2. Attach the filter
        add_filter( 'http_request_args', $download_args_filter, 10, 2 );

        // 2. Download the ZIP file
        // Note: We pass 300 here as well as a fallback for the function's own timeout logic
        $tmp_zip = download_url( $download_url, 300 );

        // 4. Remove the filter immediately so it doesn't affect other requests
        remove_filter( 'http_request_args', $download_args_filter );

        if ( is_wp_error( $tmp_zip ) ) {
            throw new Exception(__('Failed to download data bundle: ', 'geeky-bot') . $tmp_zip->get_error_message());
        }

        // 3. Unzip and Process
        $upload_dir = wp_upload_dir();
        $target_dir = trailingslashit($upload_dir['path']) . 'zywrap-temp/';
        
        // Unzip using WordPress safe unzipper
        $unzip_result = unzip_file($tmp_zip, $target_dir);
        @unlink($tmp_zip); // Always delete the temp zip file

        if (is_wp_error($unzip_result)) {
            throw new Exception(__('Failed to unzip data bundle: ', 'geeky-bot') . $unzip_result->get_error_message());
        }

        // Validate and process the JSON
        $json_path = $target_dir . 'zywrap-data.json';
        if (!$wp_filesystem->exists($json_path)) {
            throw new Exception(__('JSON file missing in bundle.', 'geeky-bot'));
        }

        // --- MEMORY OPTIMIZATION START ---
        
        // 1. Increase memory for this specific operation
        @ini_set('memory_limit', '1024M');
        
        // 2. Read content
        $json_content = $wp_filesystem->get_contents($json_path);
        
        // 3. Disable Garbage Collection temporarily to speed up the massive decode
        if (function_exists('gc_disable')) gc_disable();

        $data = json_decode($json_content, true);

        // 4. IMPORTANT: Free up the 37MB string immediately after decoding
        unset($json_content); 

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON Error: ' . json_last_error_msg());
        }

        // 5. Process and UNSET each major key as we finish to keep memory low
        if (!empty($data)) {

            // Process rest of the keys...
            $this->mirrorTableData($data);
            
            update_option('zywrap_bundle_version', $new_version);
            update_option('zywrap_last_sync_time', time());
        }

        if (function_exists('gc_enable')) gc_enable();
        // --- MEMORY OPTIMIZATION END ---

        $wp_filesystem->delete($target_dir, true);
    }
    /**
     * Processes the full data set: Upserts existing/new and deletes obsolete.
     */
    private function mirrorTableData($data) {
        global $wpdb;

        // 1. Define tables in order: Parent tables (Categories) MUST come before Child tables (Wrappers)
        $tables = [
            'categories' => 'geekybot_zywrap_categories',
            'languages'  => 'geekybot_zywrap_languages',
            'aiModels'   => 'geekybot_zywrap_ai_models',
            'wrappers'   => 'geekybot_zywrap_wrappers',
        ];

        // 2. Disable Foreign Key Checks to prevent "Child row" errors during bulk import
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 0;");

        foreach ($tables as $json_key => $table_name) {
            if (!isset($data[$json_key]) || !is_array($data[$json_key])) {
                continue;
            }

            $full_table = $wpdb->prefix . $table_name;
            
            // Remove duplicates from source
            $items = [];
            foreach ($data[$json_key] as $code => $val) {
                $items[$code] = $val; 
            }
            
            $incoming_codes = array_keys($items);
            $chunks = array_chunk($items, 500, true); 

            foreach ($chunks as $chunk) {
                $values = [];
                $placeholders = [];
                $columns = [];

                foreach ($chunk as $code => $item_data) {
                    $db_data = $this->prepare_data_for_db($json_key, $code, $item_data);
                    
                    // Fix for Wrappers: If category_code is empty, set to NULL to satisfy Foreign Key
                    if ($json_key === 'wrappers' && empty($db_data['category_code'])) {
                        $db_data['category_code'] = null;
                    }

                    if (empty($columns)) {
                        $columns = array_keys($db_data);
                    }

                    // Handle NULLs correctly in the values array
                    foreach ($db_data as $val) {
                        $values[] = $val;
                    }

                    $row_placeholders = [];
                    foreach ($db_data as $val) {
                        $row_placeholders[] = (is_null($val)) ? "NULL" : "%s";
                    }
                    $placeholders[] = "(" . implode(', ', $row_placeholders) . ")";
                }
                    error_log("Processing chunk of " . count($chunk) . " for $json_key");

                if (!empty($columns)) {
                    // Build the ON DUPLICATE KEY UPDATE string
                    $update_parts = [];
                    foreach ($columns as $column) {
                        if ($column === 'code') continue;
                        $update_parts[] = "`$column` = VALUES(`$column`)";
                    }

                    // 2. The Mega Query: One query inserts 500 rows at once
                    $sql = "INSERT INTO `$full_table` (`" . implode('`, `', $columns) . "`) VALUES " . 
                           implode(', ', $placeholders) . 
                           " ON DUPLICATE KEY UPDATE " . implode(', ', $update_parts);

                    // Filter out actual NULL values from the prepare values as we wrote "NULL" in SQL string
                    $prepare_values = array_filter($values, function($v) { return !is_null($v); });
                    
                    $wpdb->query($wpdb->prepare($sql, ...$values));
                }
                
                // Clean up memory within the loop
                unset($values, $placeholders, $sql);
            }

            // 3. Cleanup obsolete records (Process in chunks if list is too huge)

            // 1. Get all existing codes from the DB
            $existing_codes = $wpdb->get_col("SELECT code FROM $full_table");

            // 2. Identify codes that exist in DB but are NOT in your incoming list
            $to_delete = array_diff($existing_codes, $incoming_codes);

            // 3. Delete ONLY those specific obsolete records in chunks
            if (!empty($to_delete)) {
                $delete_chunks = array_chunk($to_delete, 1000);
                foreach ($delete_chunks as $chunk) {
                    $format = implode(',', array_fill(0, count($chunk), '%s'));
                    $wpdb->query($wpdb->prepare(
                        "DELETE FROM $full_table WHERE code IN ($format)", 
                        $chunk
                    ));
                }
            }





        }

        // Special handling for templates
        if (!empty($data['templates'])) {
            $this->mirrorTemplates($data['templates']);
        }

        // 3. Re-enable Foreign Key Checks
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 1;");
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
     * Handles DELTA_UPDATE logic using upserts and explicit deletes
     */
    private function handleDeltaUpdateSync($json) {
        global $wpdb;

        // 1. Categories
        if (!empty($json['metadata']['categories'])) {
            $this->wp_upsert_batch('geekybot_zywrap_categories', $json['metadata']['categories'], ['code', 'name', 'ordering']);
        }

        // 2. Languages (MISSING IN YOUR OLD CODE)
        if (!empty($json['metadata']['languages'])) {
            $this->wp_upsert_batch('geekybot_zywrap_languages', $json['metadata']['languages'], ['code', 'name', 'ordering']);
        }

        // 3. AI Models (MISSING IN YOUR OLD CODE)
        if (!empty($json['metadata']['aiModels'])) {
            $formatted_models = [];
            foreach ($json['metadata']['aiModels'] as $m) {
                $formatted_models[] = [
                    'code'        => $m['code'],
                    'name'        => $m['name'],
                    'provider_id' => $m['provider_id'] ?? ($m['provId'] ?? ''),
                    'ordering'    => $m['ordering'] ?? 0
                ];
            }
            $this->wp_upsert_batch('geekybot_zywrap_ai_models', $formatted_models, ['code', 'name', 'provider_id', 'ordering']);
        }

        // 4. Templates (MISSING IN YOUR OLD CODE)
        if (!empty($json['metadata']['templates'])) {
            // Reuse your existing mirrorTemplates logic but pass the data 
            // Note: Delta might only send a partial map, so we don't want to DELETE 
            // anything here, just update what arrived.
            $this->mirrorTemplates($json['metadata']['templates'], false); 
        }

        // 5. Wrappers: Upserts
        if (!empty($json['wrappers']['upserts'])) {
            $formatted_wrappers = [];
            foreach ($json['wrappers']['upserts'] as $w) {
                $formatted_wrappers[] = [
                    'code'          => $w['code'],
                    'name'          => $w['name'],
                    'description'   => $w['description'] ?? ($w['desc'] ?? ''),
                    'category_code' => $w['categoryCode'] ?? ($w['category_code'] ?? ''),
                    'featured'      => $w['featured'] ?? 0,
                    'base'          => $w['base'] ?? '',
                    'ordering'      => $w['ordering'] ?? 0
                ];
            }
            $this->wp_upsert_batch('geekybot_zywrap_wrappers', $formatted_wrappers, ['code', 'name', 'description', 'category_code', 'featured', 'base', 'ordering']);
        }

        // 6. Wrappers: Deletes
        if (!empty($json['wrappers']['deletes'])) {
            foreach ($json['wrappers']['deletes'] as $code) {
                $wpdb->delete($wpdb->prefix . 'geekybot_zywrap_wrappers', ['code' => $code]);
            }
        }

        // 7. Update Version
        if (!empty($json['newVersion'])) {
            update_option('zywrap_bundle_version', $json['newVersion']);
        }
    }

    /**
     * Helper: WordPress-native Upsert (Insert or Update)
     */
    private function wp_upsert_batch($table_name, $rows, $columns) {
        if (empty($rows)) return;
        global $wpdb;
        $table = $wpdb->prefix . $table_name;

        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            $values = [];
            $placeholders = [];
            
            foreach ($chunk as $row) {
                $placeholders[] = '(' . implode(',', array_fill(0, count($columns), '%s')) . ')';
                foreach ($columns as $col) {
                    // Ensure we pick the right key from the row data
                    $values[] = isset($row[$col]) ? $row[$col] : ''; 
                }
            }

            $update = [];
            foreach ($columns as $col) {
                if ($col === 'code' || $col === 'type') continue;
                $update[] = "`$col` = VALUES(`$col`)";
            }

            $sql = "INSERT INTO `$table` (`" . implode("`,`", $columns) . "`) VALUES " . 
                   implode(',', $placeholders) . " ON DUPLICATE KEY UPDATE " . implode(',', $update);

            $wpdb->query($wpdb->prepare($sql, $values));
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
            'timeout' => 60,
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
     * [cite: `PhpSdk.jsx`, `Docs.jsx`, `APIReference.jsx`]
     */
    function execute_zywrap_proxy() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $geekybot_nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (!wp_verify_nonce($geekybot_nonce, 'zywrap_execute_proxy')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        $api_key = get_option('geekybot_zywrap_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key is not set.'));
        }

        // Get data from AJAX request
        $geekybot_model = GEEKYBOTrequest::GEEKYBOT_getVar('model', 'post');
        $wrapper_code = GEEKYBOTrequest::GEEKYBOT_getVar('wrapperCode', 'post');
        $geekybot_prompt = GEEKYBOTrequest::GEEKYBOT_getVar('prompt', 'post');
        $geekybot_language = GEEKYBOTrequest::GEEKYBOT_getVar('language', 'post');
        $geekybot_overrides = GEEKYBOTrequest::GEEKYBOT_getVar('overrides', 'post');

        // === NEW FEATURES INPUT ===
        $context = GEEKYBOTrequest::GEEKYBOT_getVar('context', 'post');
        $seo_keywords = GEEKYBOTrequest::GEEKYBOT_getVar('seo_keywords', 'post');
        $negative_constraints = GEEKYBOTrequest::GEEKYBOT_getVar('negative_constraints', 'post');

        // === MODIFY PROMPT WITH NEW FEATURES ===
        if (!empty($seo_keywords)) {
            $geekybot_prompt .= "\n\n[IMPORTANT] Naturally integrate the following SEO keywords into the text: " . $seo_keywords;
        }

        if (!empty($negative_constraints)) {
            $geekybot_prompt .= "\n\n[CONSTRAINT] Do NOT use the following words or phrases: " . $negative_constraints;
        }

        // Build the payload
        $geekybot_payloadData = array(
            'model' => $geekybot_model,
            'wrapperCodes' => array($wrapper_code),
            'prompt' => $geekybot_prompt
        );

        // === SEND CONTEXT AS VARIABLE ===
        if (!empty($context)) {
            $geekybot_payloadData['variables'] = array('context' => $context);
        }

        if (!empty($geekybot_language)) {
            $geekybot_payloadData['language'] = $geekybot_language;
        }
        if (!empty($geekybot_overrides) && is_array($geekybot_overrides)) {
            // Sanitize overrides keys/values
            $clean_overrides = array();
            foreach($geekybot_overrides as $geekybot_k => $v) {
                $clean_overrides[sanitize_key($geekybot_k)] = sanitize_text_field($v);
            }
            $geekybot_payloadData = array_merge($geekybot_payloadData, $clean_overrides);
        }
        
        $geekybot_url = 'https://api.zywrap.com/v1/proxy';
        
        $json_body = json_encode($geekybot_payloadData);
        if ($json_body === false) {
            wp_send_json_error(array('message' => 'JSON Encoding failed. Check for invalid characters in prompt.'));
        }

        // REQUIRED CHANGE: Timeout 300 & SSL verify disabled
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

        $response = wp_remote_post($geekybot_url, $geekybot_args);

        if (is_wp_error($response)) {
            $geekybot_error_msg = $response->get_error_message();
            $this->log_api_call('proxy_execute', 'error', [
                'wrapper_code' => $wrapper_code,
                'model_code' => $geekybot_model,
                'error_message' => $geekybot_error_msg
            ]);
            wp_send_json_error(array('message' => 'Error: ' . $geekybot_error_msg));
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $geekybot_data = null;

        // REQUIRED CHANGE: NEW PARSE STREAM LOGIC
        if ($http_code === 200) {
            $lines = explode("\n", $raw_body);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'data: ') === 0) {
                    $json_str = substr($line, 6);
                    $decoded = json_decode($json_str, true);
                    // Check if it's the valid payload according to manager's requirement
                    if ($decoded && (isset($decoded['output']) || isset($decoded['error']))) {
                        $geekybot_data = $decoded;
                    }
                }
            }

            if (!$geekybot_data) {
                 wp_send_json_error(array('message' => 'Failed to parse streaming response from Zywrap.'));
            }
        } else {
            $geekybot_data = json_decode($raw_body, true);
        }

        // Check for non-200 status codes
        if ($http_code !== 200) {
            $geekybot_error_message = $geekybot_data['message'] ?? 'An API error occurred.';
            $this->log_api_call('proxy_execute', 'error', [
                'wrapper_code' => $wrapper_code,
                'model_code' => $geekybot_model,
                'http_code' => $http_code,
                'error_message' => $geekybot_error_message
            ]);
            wp_send_json_error(array('message' => "Error (Code $http_code): $geekybot_error_message"));
        }

        // Successfully capture usage data from proxy.js response
        $geekybot_token_data = $geekybot_data['usage'] ?? null;

        $this->log_api_call('proxy_execute', 'success', [
            'wrapper_code' => $wrapper_code,
            'model_code' => $geekybot_model,
            'http_code' => $http_code,
            'token_data' => $geekybot_token_data
        ]);

        wp_send_json_success($geekybot_data);
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
}
?>
