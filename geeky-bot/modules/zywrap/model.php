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
        // 1. Download the ZIP file [cite: `PhpSdk.jsx`]
        $geekybot_url = 'https://api.zywrap.com/v1/sdk/export/';
        $response = wp_remote_get( $geekybot_url, array(
            'timeout' => 300, // 5 minutes
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $geekybot_error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );

            $this->log_api_call( 'sync_full', 'error', array(
                'error_message' => 'Download failed: ' . $geekybot_error_msg,
            ) );

            wp_send_json_error( array(
                'message' => __( 'Failed to download data bundle from Zywrap API.', 'geeky-bot'),
            ) );
        }

        // 2. Prepare file paths
        $geekybot_upload_dir = wp_upload_dir();
        $zip_file = trailingslashit( $geekybot_upload_dir['path'] ) . 'zywrap-data.zip';
        $geekybot_json_file = trailingslashit( $geekybot_upload_dir['path'] ) . 'zywrap-data.json';

        // 3. Initialize WordPress filesystem
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem ) {
            wp_send_json_error( array(
                'message' => __( 'Filesystem initialization failed.', 'geeky-bot'),
            ) );
        }

        // 4. Write ZIP file using WP Filesystem
        $zip_body = wp_remote_retrieve_body( $response );
        if ( empty( $zip_body ) ) {
            wp_send_json_error( array(
                'message' => __( 'Downloaded ZIP file is empty.', 'geeky-bot'),
            ) );
        }

        $wp_filesystem->put_contents($zip_file,$zip_body,FS_CHMOD_FILE);

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

        if($type == 3){ // check to empty exsisting tables.
            global $wpdb;
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 0;');
            $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_wrappers`");
            $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_categories`");
            $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_languages`");
            $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_block_templates`");
            $wpdb->query("TRUNCATE TABLE `" . $wpdb->prefix . "geekybot_zywrap_ai_models`");
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 1;');
        }

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
        // Import wrappers
        if (!empty($geekybot_data['wrappers'])) {
            $output_array['wrappers'] = count($geekybot_data['wrappers']);
            $geekybot_result = $this->importZywrapWrappersInBatches($geekybot_data['wrappers'], $output_array['wrappers']);
            set_transient('wpjp_import_counts_cache', $this->zywrap_import_counts, HOUR_IN_SECONDS);

            wp_send_json_success($geekybot_result);
        }
        
        die(' model code 827 11');
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
            $prompt .= "\n\n[IMPORTANT] Naturally integrate the following SEO keywords into the text: " . $seo_keywords;
        }

        if (!empty($negative_constraints)) {
            $prompt .= "\n\n[CONSTRAINT] Do NOT use the following words or phrases: " . $negative_constraints;
        }

        // Build the payload
        $geekybot_payloadData = array(
            'model' => $geekybot_model,
            'wrapperCodes' => array($wrapper_code),
            'prompt' => $geekybot_prompt
        );

        // === SEND CONTEXT AS VARIABLE ===
        if (!empty($geekybot_context)) {
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

        
        $geekybot_args = array(
            'method'  => 'POST',
            'timeout' => 60, // Longer timeout for generation
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body'    => $json_body
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
        $body = wp_remote_retrieve_body($response);
        $geekybot_data = json_decode($body, true);

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
}
?>
