<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
if (!GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/header',array('module' => 'zywraplogs'))){
    return;
}

// Ensure geekybot::$_data[0] is retrieved and initialized safely
$data = geekybot::$_data[0] ?? array();

// Extract logs and summary data with safe fallbacks
$logs = $data['logs'] ?? array();
   
// Initialize summary data with default/N/A values, excluding cost.
$summary_data = $data['summary'] ?? array(
    'runs'    => '0',
    'errors'  => '0',
    'model'   => __('N/A (Error)', 'geeky-bot'),
);
?>

<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'zywraplogs','layouts' => 'logs')); ?>
    <div class="geekybotadmin-body-main">
        <div id="geekybotadmin-leftmenu-main">
            <?php GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue',array('module' => 'zywraplogs')); ?>
        </div>
        <div id="geekybotadmin-data">
            <div class="geekybot-tab-nav">
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywrap&geekybotlt=zywrap','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Settings', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywrap&geekybotlt=playground','Zywrap'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Generate Text', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywraplogs&geekybotlt=logs','configuration'))?>" class="geekybot-tab-link active"><?php echo esc_html(__('AI Logs', 'geeky-bot')); ?></a>
            </div>
            <div id="geekybot-head">
                <h1 class="geekybot-head-text"><?php echo esc_html(__('Zywrap Logs', 'geeky-bot')); ?></h1>
            </div>
            <div id="geekybot-admin-wrapper" class="p0 bg-n bs-n geekybot-admin-error-log-container">
                <div class="geekybot-admin-wrapper">
                    <div class="geekybot-admin-container">
                        <div class="geekybot-summary-grid">
                            <div class="geekybot-summary-card">
                                <div class="geekybot-summary-label"><?php echo esc_html( __('Total API Runs (24h)', 'geeky-bot') ); ?></div>
                                <div class="geekybot-summary-value"><?php echo esc_html( $summary_data['runs'] ); ?></div>
                            </div>
                            <div class="geekybot-summary-card">
                                <div class="geekybot-summary-label"><?php echo esc_html( __('API Errors (24h)', 'geeky-bot') ); ?></div>
                                <div class="geekybot-summary-value geekybot-error"><?php echo esc_html( $summary_data['errors'] ); ?></div>
                            </div>
                            <div class="geekybot-summary-card">
                                <div class="geekybot-summary-label"><?php echo esc_html( __('Top Model Used', 'geeky-bot') ); ?></div>
                                <div class="geekybot-summary-value geekybot-model"><?php echo esc_html( $summary_data['model'] ); ?></div>
                            </div>
                        </div>
                        <div class="geekybot-filter-bar">
                            <input type="text" value="<?php echo isset($_GET['search']) ? esc_attr(sanitize_text_field($_GET['search'])) : ''; ?>" placeholder="<?php echo esc_attr( __('Search by Action, User or Wrapper...', 'geeky-bot') ); ?>" class="geekybot-filter-input geekybot-search-input" id="geekybot-search-input">

                            <select class="geekybot-filter-input geekybot-select-input" id="geekybot-status-filter">
                                <option value=""><?php echo esc_html( __('Filter by Status', 'geeky-bot') ); ?></option>
                                <option value="success" <?php selected( isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'success' ); ?>><?php echo esc_html( __('Success', 'geeky-bot') ); ?></option>
                                <option value="error" <?php selected( isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'error' ); ?>><?php echo esc_html( __('Error', 'geeky-bot') ); ?></option>
                                <option value="warning" <?php selected( isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'warning' ); ?>><?php echo esc_html( __('Limited', 'geeky-bot') ); ?></option>
                                <option value="ok" <?php selected( isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'ok' ); ?>><?php echo esc_html( __('OK/Internal', 'geeky-bot') ); ?></option>
                            </select>
                            <button class="geekybot-filter-button" id="geekybot-apply-filter">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                  <polygon points="3 4 21 4 14 13 14 20 10 18 10 13 3 4"></polygon>
                                </svg>
                                <?php echo esc_html( __('Filter', 'geeky-bot') ); ?>
                            </button>

                            <button class="geekybot-filter-button geekybot-reset-button" id="geekybot-reset-filter" style="background-color: #6b7280;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                                    <path d="M3 3v5h5"></path>
                                </svg>
                                <?php echo esc_html( __('Reset', 'geeky-bot') ); ?>
                            </button>
                        </div>
                        <div class="geekybot-table-responsive-wrapper">
                            <table class="geekybot-table geekybot-table-zywrap-log">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;"><?php echo esc_html( __('Status', 'geeky-bot') ); ?></th>
                                        <th style="width: 15%;"><?php echo esc_html( __('Action & User', 'geeky-bot') ); ?></th>
                                        <th style="width: 25%;"><?php echo esc_html( __('Wrapper / Model', 'geeky-bot') ); ?></th>
                                        <th style="width: 20%;"><?php echo esc_html( __('Performance & Tokens', 'geeky-bot') ); ?></th>
                                        <th><?php echo esc_html( __('Error / Details', 'geeky-bot') ); ?></th>
                                        <th style="width: 100px;"><?php echo esc_html( __('Time', 'geeky-bot') ); ?></th>
                                        <th class="geekybot-text-center" style="width: 40px;"><?php echo esc_html( __('Details', 'geeky-bot') ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ( empty( $logs ) ) : ?>
                                        <tr>
                                            <td colspan="7" class="geekybot-text-center">
                                                <?php echo esc_html( __('No log entries found or a database error occurred.', 'geeky-bot') ); ?>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ( $logs as $log ) :
                                            // Safely retrieve data using the null coalescing operator (??)
                                            $http_status = absint($log['status'] ?? 0); // Assuming status is the HTTP status code
                                            $tokens_in = absint($log['prompt_tokens'] ?? 0);
                                            $tokens_out = absint($log['completion_tokens'] ?? 0);
                                            $total_tokens = $tokens_in + $tokens_out;

                                            // Determine status class/text
                                            $status_text = $log['status'];
                                            $status_class = 'geekybot-status-ok';
                                            if ($http_status >= 500 || $status_text === 'error') {
                                                $status_class = 'geekybot-status-error';
                                                $status_text = 'ERR';
                                            } elseif ($http_status >= 400 || $status_text === 'warning') {
                                                $status_class = 'geekybot-status-warning';
                                                $status_text = 'LIM';
                                            } elseif ($http_status === 200 || $status_text === 'success') {
                                                $status_class = 'geekybot-status-success';
                                                $status_text = 'SUC';
                                            }

                                            // Fallback/Simulated data for display strings (if not provided by $data)
                                            $log_id = $log['id'] ?? $log['log_id'] ?? 0;
                                            $action = $log['action'] ?? __('N/A', 'geeky-bot');
                                            //$user = $log['user'] ?? __('Guest', 'geeky-bot');
                                            $user_id = absint($log['user_id'] ?? 0);
                                            $user_obj = get_userdata($user_id);
                                            // If user exists, use Display Name; otherwise, fallback to "Guest"
                                            $user = $user_obj ? $user_obj->display_name : __('Guest', 'geeky-bot');
                                            $wrapper = $log['wrapper_code'] ?? '-';
                                            $model_code = $log['model_code'] ?? '-';
                                            $message = $log['response_message'] ?? $log['error_message'] ?? __('Request completed or details N/A.', 'geeky-bot');
                                            $log_timestamp = $log['timestamp'] ?? null;
                                            $time_ago = __('N/A', 'geeky-bot');

                                            if ( $log_timestamp ) {
                                                // Convert the database timestamp string to a Unix time
                                                $timestamp_unix = strtotime($log_timestamp); 
                                                
                                                // Ensure the timestamp is valid and not in the future
                                                if ( $timestamp_unix && $timestamp_unix <= current_time('timestamp') ) {
                                                    $time_ago = sprintf( 
                                                        __('%s ago', 'geeky-bot'), 
                                                        human_time_diff($timestamp_unix, current_time('timestamp'))
                                                    );
                                                } else {
                                                     // If timestamp exists but cannot be calculated (e.g., future time)
                                                    $time_ago = esc_html( date('M j, Y H:i', $timestamp_unix) );
                                                }
                                            }

                                            // We assume the log array might be missing 'status_class', 'status', 'log_id', etc. from the DB, 
                                            // so we derive them above and use the derived variables.
                                        ?>
                                    <tr>
                                        <td>
                                            <span class="geekybot-status-badge <?php echo esc_attr( $status_class ); ?>">
                                                <?php echo esc_html( $status_text ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="geekybot-font-bold geekybot-text-gray-800"><?php echo esc_html( $action ); ?></div>
                                            <div class="geekybot-text-sm geekybot-text-gray-500" title="<?php echo esc_attr( sprintf( __('User ID: %s', 'geeky-bot'), $user_id ) ); ?>">
                                                <?php echo esc_html( $user ); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="geekybot-wrapper-model-cell">
                                                <div title="<?php echo esc_attr( $wrapper ); ?>">
                                                    <span class="geekybot-text-xs geekybot-font-semibold geekybot-text-gray-500"><?php echo esc_html( __('W:', 'geeky-bot') ); ?></span>
                                                    <?php echo esc_html( $wrapper === '-' ? '-' : substr($wrapper, 0, 28) . '...' ); ?>
                                                </div>
                                                <div title="<?php echo esc_attr( $model_code ); ?>">
                                                    <span class="geekybot-text-xs geekybot-font-semibold geekybot-text-gray-500"><?php echo esc_html( __('M:', 'geeky-bot') ); ?></span>
                                                    <?php echo esc_html( $model_code === '-' ? '-' : substr($model_code, 0, 28) . '...' ); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="geekybot-performance-stats">
                                                <div>
                                                    <span class="geekybot-font-bold"><?php echo esc_html( __('HTTP:', 'geeky-bot') ); ?></span>
                                                    <span class="<?php echo ($http_status >= 400 ? 'geekybot-text-error' : 'geekybot-text-success'); ?>">
                                                        <?php echo esc_html( $http_status ); ?>
                                                    </span>
                                                </div>
                                                <?php if ($total_tokens > 0) : ?>
                                                    <div class="geekybot-token-stats">
                                                        <span class="geekybot-font-bold"><?php echo esc_html( __('Tokens:', 'geeky-bot') ); ?></span>
                                                        <span class="geekybot-text-gray-700" title="<?php echo esc_attr( sprintf( __('In: %s / Out: %s', 'geeky-bot'), number_format_i18n($tokens_in), number_format_i18n($tokens_out) ) ); ?>">
                                                            <?php echo esc_html( number_format_i18n($total_tokens) ); ?>
                                                        </span>
                                                    </div>
                                                <?php else : ?>
                                                    <span class="geekybot-text-gray-400"><?php echo esc_html( __('No tokens recorded', 'geeky-bot') ); ?></span>
                                                <?php endif; ?>

                                                </div>
                                        </td>
                                        <td>
                                            <?php if ( $http_status >= 400 ) : ?>
                                                <div class="geekybot-text-error-detail" title="<?php echo esc_attr( $message ); ?>">
                                                    <?php echo esc_html( substr($message, 0, 40) . (strlen($message) > 40 ? '...' : '') ); ?>
                                                </div>
                                            <?php else : ?>
                                                <span class="geekybot-text-sm geekybot-text-gray-400">
                                                    <?php echo esc_html( substr($message, 0, 40) . (strlen($message) > 40 ? '...' : '') ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td title="<?php echo esc_attr( $log['timestamp'] ?? 'N/A' ); ?>" class="geekybot-text-gray-600">
                                            <?php echo esc_html( $time_ago ); ?>
                                        </td>
                                        <td class="geekybot-text-center">
                                            <button class="geekybot-details-btn" 
                                                type="button"
                                                data-log-id="<?php echo esc_attr( $log_id ); ?>"
                                                data-status="<?php echo esc_attr( $status_text ); ?>"
                                                data-model="<?php echo esc_attr( $model_code ); ?>"
                                                data-wrapper="<?php echo esc_attr( $wrapper ); ?>"
                                                data-tokens-in="<?php echo esc_attr( $tokens_in ); ?>"
                                                data-tokens-out="<?php echo esc_attr( $tokens_out ); ?>"
                                                data-tokens-total="<?php echo esc_attr( $total_tokens ); ?>"
                                                data-time="<?php echo esc_attr( $log['timestamp'] ); ?>"
                                                data-user="<?php echo esc_attr( $user ); ?>"
                                                
                                                <?php 
                                                    // Decide which message to show (Response or Error)
                                                    // If it's an error, use error_message. If success, use response_message.
                                                    $full_detail = !empty($log['error_message']) ? $log['error_message'] : ($log['response_message'] ?? __('No response data.', 'geeky-bot')); 
                                                ?>
                                                data-full-message="<?php echo esc_attr( $full_detail ); ?>"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>
                                                    <circle cx="12" cy="12" r="3"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                        if (geekybot::$_data[1]) {
                            GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagination',array('module' => 'zywrap' , 'pagination' => geekybot::$_data[1]));
                        }
                        ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * jQuery implementation for log details and filtering.
 * Wrapped in the WordPress-recommended no-conflict closure.
 */
jQuery(document).ready(function($) {
    // Log data map used for the simulated detail view (matching PHP structure above)
    // IMPORTANT: This static map is purely for simulation and should ideally fetch detailed data via AJAX.
    const geekybot_log_data_map = {
        '125': { tokens: '<?php echo esc_js('24,024'); ?>', status: '<?php echo esc_js('SUCCESS'); ?>' },
        '124': { tokens: '<?php echo esc_js('1,234'); ?>', status: '<?php echo esc_js('ERROR'); ?>' },
        '123': { tokens: '<?php echo esc_js('N/A'); ?>', status: '<?php echo esc_js('LIMITED'); ?>' },
        '122': { tokens: '<?php echo esc_js('N/A'); ?>', status: '<?php echo esc_js('OK'); ?>' }
    };

    // Event listener for the Details buttons

    $('.geekybot-details-btn').on('click', function(e) {
        e.preventDefault();
        
        const btn = $(this);
        
        // Retrieve data from data-attributes
        const logId = btn.data('log-id');
        const status = btn.data('status');
        const model = btn.data('model');
        const wrapper = btn.data('wrapper');
        const time = btn.data('time');
        const user = btn.data('user');
        
        // Token breakdown
        const tokensIn = btn.data('tokens-in');
        const tokensOut = btn.data('tokens-out');
        const tokensTotal = btn.data('tokens-total');
        
        // The full message (Response or Error)
        const fullMessage = btn.data('full-message');

        // Construct a clean, detailed message string
        const detailMessage = 
            '------------------------------------------------\n' +
            'LOG DETAILS #' + logId + '\n' +
            '------------------------------------------------\n\n' +
            '📅 Time:      ' + time + '\n' +
            '👤 User:      ' + user + '\n' +
            '🤖 Model:     ' + model + ' (' + wrapper + ')\n' +
            '📊 Status:    ' + status + '\n\n' +
            
            '--- TOKEN USAGE ---\n' +
            'Input:   ' + tokensIn + '\n' +
            'Output:  ' + tokensOut + '\n' +
            'Total:   ' + tokensTotal + '\n\n' +
            
            '--- FULL RESPONSE / ERROR ---\n' +
            fullMessage;

        // Show the data
        alert(detailMessage);
    });

    // Filter Logic
    $('#geekybot-apply-filter').on('click', function(e) {
        e.preventDefault();
        const searchVal = $('#geekybot-search-input').val();
        const statusVal = $('#geekybot-status-filter').val();
        let url = 'admin.php?page=geekybot_zywraplogs&geekybotlt=logs';
        if (searchVal) url += '&search=' + encodeURIComponent(searchVal);
        if (statusVal) url += '&status_filter=' + encodeURIComponent(statusVal);
        window.location.href = url;
    });

    // Reset Logic
    $('#geekybot-reset-filter').on('click', function(e) {
        e.preventDefault();
        window.location.href = 'admin.php?page=geekybot_zywraplogs&geekybotlt=logs';
    });

    // Real filter implementation
    $('#geekybot-apply-filter').on('click', function(e) {
        e.preventDefault();
        
        const searchVal = $('#geekybot-search-input').val();
        const statusVal = $('#geekybot-status-filter').val();

        // Construct current URL base
        let url = 'admin.php?page=geekybot_zywraplogs&geekybotlt=logs';

        if (searchVal) {
            url += '&search=' + encodeURIComponent(searchVal);
        }
        if (statusVal) {
            url += '&status_filter=' + encodeURIComponent(statusVal);
        }

        // Redirect to reload the page with filters
        window.location.href = url;
    });
    // Reset Button Logic
    $('#geekybot-reset-filter').on('click', function(e) {
        e.preventDefault();

        // 1. Clear the visual inputs immediately
        $('#geekybot-search-input').val('');
        $('#geekybot-status-filter').val('');

        // 2. Reload the page with the base URL (removing all search parameters)
        window.location.href = 'admin.php?page=geekybot_zywraplogs&geekybotlt=logs';
    });
});
</script>