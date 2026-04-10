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
     * Uses V1 SDK Schema column names.
     */
    function geekybot_get_logs_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'geekybot_zywrap_logs';
        $text_domain = 'geeky-bot';
        $results = array('logs' => array(), 'summary' => array());
        
        $pagenum = isset($_GET['pagenum']) ? absint( $_GET['pagenum'] ) : 1;
        $limit = 20; 
        $offset = (max(1, $pagenum) - 1) * $limit;

        $cutoff_time = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $results['summary'] = array('runs' => '0', 'errors' => '0', 'model' => __('N/A', $text_domain));
            geekybot::$_data['total'] = 0;
            geekybot::$_data[1] = null;
            geekybot::$_data[0] = $results;
            return;
        }
        
        // Stats
        $total_runs_24h = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE timestamp >= %s AND action = 'proxy_execute'", $cutoff_time));
        $api_errors_24h = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE timestamp >= %s AND (status = 'error' OR http_code >= 400)", $cutoff_time));
        $top_model_used = $wpdb->get_var($wpdb->prepare("SELECT model_code FROM $table_name WHERE timestamp >= %s AND model_code IS NOT NULL AND model_code != '' GROUP BY model_code ORDER BY COUNT(model_code) DESC LIMIT 1", $cutoff_time));
        
        $results['summary'] = array(
            'runs'    => number_format_i18n( absint($total_runs_24h) ),
            'errors'  => number_format_i18n( absint($api_errors_24h) ),
            'model'   => empty( $top_model_used ) ? __('N/A', $text_domain) : esc_html( $top_model_used ),
        );

        // Fetch Main Data
        $where_clauses = array("1=1");
        $query_args = array();

        if ( ! empty( $_GET['search'] ) ) {
            $search = sanitize_text_field( $_GET['search'] );
            $where_clauses[] = "(trace_id LIKE %s OR wrapper_code LIKE %s OR model_code LIKE %s)";
            $like_query = '%' . $wpdb->esc_like( $search ) . '%';
            array_push($query_args, $like_query, $like_query, $like_query); 
        }

        if ( ! empty( $_GET['status_filter'] ) ) {
            $status_filter = sanitize_text_field( $_GET['status_filter'] );
            if ( $status_filter === 'error' ) {
                $where_clauses[] = "(status = 'error' OR http_code >= 400)";
            } elseif ( $status_filter === 'success' ) {
                $where_clauses[] = "(status = 'success' OR status = 'ok')";
            }
        }

        $where_sql = implode( ' AND ', $where_clauses );
        $count_query = "SELECT COUNT(id) FROM $table_name WHERE $where_sql";
        $total_logs = !empty($query_args) ? $wpdb->get_var($wpdb->prepare($count_query, $query_args)) : $wpdb->get_var($count_query);

        geekybot::$_data['total'] = $total_logs;
        geekybot::$_data[1] = GEEKYBOTpagination::GEEKYBOT_getPagination($total_logs);

        // FIXED: Changed created_at to timestamp
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        array_push($query_args, $limit, $offset);

        $results['logs'] = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A );
        geekybot::$_data[0] = $results;
    }
}
?>