<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTzywraplogsModel {

    function getMessagekey(){
        $key = 'zywraplogs';
        if(is_admin()){
            $key = 'admin_'.$key;
        }
        return $key;
    }
    /**
     * Fetches log data from the database and calculates summary statistics for the dashboard.
     *
     * It uses the 'model_code' column for model data and completely excludes 'cost' data.
     *
     * @return array An array containing 'logs' (the main table data) and 'summary' (dashboard cards data).
     */
    function geekybot_get_logs_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'geekybot_zywrap_logs';
        $text_domain = 'geeky-bot';
        $results = array(
            'logs'    => array(),
            'summary' => array(),
        );
        
        // --- 1. Security and Pagination Setup ---
        
        // Sanitize the page number input
        $pagenum = isset($_GET['pagenum']) ? absint( $_GET['pagenum'] ) : 1;
        if ( $pagenum < 1 ) {
            $pagenum = 1;
        }

        // You must load configuration dynamically for production, using a fallback here.
        // Assuming your custom methods return the correct values:
        $limit = 20; // Fallback page size, replace with your config model call if necessary.
        // $limit = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('pagination_default_page_size'); 
        
        $offset = ( $pagenum - 1 ) * $limit;

        // --- 2. Calculate Summary Data (Last 24 Hours) ---
        
        $cutoff_time = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        // Count total runs in the last 24 hours
        $total_runs_24h = $wpdb->get_var( $wpdb->prepare( 
            "SELECT COUNT(id) FROM $table_name WHERE timestamp >= %s", 
            $cutoff_time 
        ) );
        
        // Count errors for both HTTP error codes AND explicit 'error' text status in the last 24 hours
        $api_errors_24h = $wpdb->get_var( $wpdb->prepare( 
            "SELECT COUNT(id) FROM $table_name WHERE timestamp >= %s AND (status >= 400 OR status = 'error')",
            $cutoff_time 
        ) );

        // Find the most frequent model used, using the existing 'model_code' column
        $top_model_used = $wpdb->get_var( $wpdb->prepare(
            "SELECT model_code FROM $table_name WHERE timestamp >= %s GROUP BY model_code ORDER BY COUNT(model_code) DESC LIMIT 1",
            $cutoff_time
        ) );
        
        $results['summary'] = array(
            'runs'    => number_format_i18n( absint($total_runs_24h) ),
            'errors'  => number_format_i18n( absint($api_errors_24h) ),
            'model'   => empty( $top_model_used ) ? __('N/A', $text_domain) : esc_html( $top_model_used ),
        );

        // --- 3. Fetch Main Log Data (with Pagination & Filtering) ---

        // Build the WHERE clause
        $where_clauses = array("1=1");
        $query_args = array();

        // 1. Search Filter
        // We search 'action' (varchar), 'wrapper_code' (varchar), and 'user_id' (bigint)
        if ( ! empty( $_GET['search'] ) ) {
            $search = sanitize_text_field( $_GET['search'] );
            
            // STRICT COLUMN MATCH: using 'user_id' instead of 'user'
            $where_clauses[] = "(action LIKE %s OR user_id LIKE %s OR wrapper_code LIKE %s)";

            $like_query = '%' . $wpdb->esc_like( $search ) . '%';
            $query_args[] = $like_query; // for action
            $query_args[] = $like_query; // for user_id
            $query_args[] = $like_query; // for wrapper_code
        }

        // 2. Status Filter
        // We now check 'http_code' for numbers and 'status' for text strings
        if ( ! empty( $_GET['status_filter'] ) ) {
            $status_filter = sanitize_text_field( $_GET['status_filter'] );
            
            if ( $status_filter === 'error' ) {
                // Matches HTTP 500+ OR status text "error"
                $where_clauses[] = "(http_code >= 500 OR status = 'error')";

            } elseif ( $status_filter === 'warning' ) {
                // Matches HTTP 400-499 OR status text "warning"
                $where_clauses[] = "( (http_code >= 400 AND http_code < 500) OR status = 'warning' )";

            } elseif ( $status_filter === 'success' ) {
                // Matches HTTP 200 OR status text "success"
                $where_clauses[] = "(http_code = 200 OR status = 'success')";

            } elseif ( $status_filter === 'ok' ) {
                // Matches "OK/Internal" (Gray)
                // Logic: Anything that is NOT Error (500/err), NOT Warning (400/warn), and NOT Success (200/suc)
                $where_clauses[] = "(status = 'ok')";
            }
        }

        $where_sql = implode( ' AND ', $where_clauses );

        // Get Total Count with Filters
        $count_query = "SELECT COUNT(id) FROM $table_name WHERE $where_sql";
        if ( ! empty( $query_args ) ) {
            $total_logs = $wpdb->get_var( $wpdb->prepare( $count_query, $query_args ) );
        } else {
            $total_logs = $wpdb->get_var( $count_query );
        }

        geekybot::$_data['total'] = $total_logs;
        geekybot::$_data[1] = GEEKYBOTpagination::GEEKYBOT_getPagination($total_logs);

        // Fetch Data with Filters
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY timestamp DESC LIMIT %d OFFSET %d";

        // Add limit/offset to args
        $query_args[] = GEEKYBOTpagination::$_limit;
        $query_args[] = GEEKYBOTpagination::$_offset;

        $results['logs'] = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A );
        
        geekybot::$_data[0] = $results;
        return;
    }
}
?>