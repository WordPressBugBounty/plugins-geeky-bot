<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
if (!GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/header',array('module' => 'zywraplogs'))){
    return;
}

$data = geekybot::$_data[0] ?? array();
$logs = $data['logs'] ?? array();
$summary_data = $data['summary'] ?? array(
    'runs'    => '0',
    'errors'  => '0',
    'model'   => __('N/A (Error)', 'geeky-bot'),
);
?>

<style>
    /* Modern Logs UI matching the screenshot */
    .geekybot-log-table-wrapper {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 20px;
    }
    .geekybot-log-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .geekybot-log-table th {
        background: #f9fafb;
        padding: 12px 16px;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e5e7eb;
    }
    .geekybot-log-table td {
        padding: 16px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
        font-size: 13px;
        color: #374151;
    }
    .geekybot-log-table tr:last-child td {
        border-bottom: none;
    }
    
    /* Pill Badges */
    .zy-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .zy-badge.suc { background: #dcfce7; color: #166534; }
    .zy-badge.err { background: #fee2e2; color: #991b1b; }
    .zy-badge.wrn { background: #fef08a; color: #9a3412; }

    /* Typographic Helpers */
    .zy-primary-text { font-weight: 600; color: #111827; margin-bottom: 4px; }
    .zy-secondary-text { font-size: 12px; color: #6b7280; }
    
    /* Details Button */
    .zy-action-btn {
        background: transparent;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        padding: 6px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .zy-action-btn:hover { background: #f3f4f6; color: #4f46e5; }
</style>

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
                <h1 class="geekybot-head-text"><?php echo esc_html(__('Zywrap API Logs', 'geeky-bot')); ?></h1>
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
                                <div class="geekybot-summary-value geekybot-model" style="font-size: 18px; line-height: 1.4;"><?php echo esc_html( $summary_data['model'] ); ?></div>
                            </div>
                        </div>

                        <div class="geekybot-filter-bar">
                            <input type="text" value="<?php echo isset($_GET['search']) ? esc_attr(sanitize_text_field($_GET['search'])) : ''; ?>" placeholder="<?php echo esc_attr( __('Search by Trace ID, Wrapper, or Model...', 'geeky-bot') ); ?>" class="geekybot-filter-input geekybot-search-input" id="geekybot-search-input" style="width: 300px;">

                            <select class="geekybot-filter-input geekybot-select-input" id="geekybot-status-filter">
                                <option value=""><?php echo esc_html( __('All Statuses', 'geeky-bot') ); ?></option>
                                <option value="success" <?php selected( isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'success' ); ?>><?php echo esc_html( __('Success', 'geeky-bot') ); ?></option>
                                <option value="error" <?php selected( isset($_GET['status_filter']) ? $_GET['status_filter'] : '', 'error' ); ?>><?php echo esc_html( __('Error', 'geeky-bot') ); ?></option>
                            </select>
                            
                            <button class="geekybot-filter-button" id="geekybot-apply-filter">
                                <?php echo esc_html( __('Apply Filters', 'geeky-bot') ); ?>
                            </button>

                            <button class="geekybot-filter-button geekybot-reset-button" id="geekybot-reset-filter" style="background-color: #f3f4f6; color: #374151; border: 1px solid #d1d5db;">
                                <?php echo esc_html( __('Reset', 'geeky-bot') ); ?>
                            </button>
                        </div>

                        <div class="geekybot-log-table-wrapper">
                            <table class="geekybot-log-table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px; text-align: center;"><?php echo esc_html( __('Status', 'geeky-bot') ); ?></th>
                                        <th style="width: 15%;"><?php echo esc_html( __('Action / Trace ID', 'geeky-bot') ); ?></th>
                                        <th style="width: 20%;"><?php echo esc_html( __('Wrapper / Model', 'geeky-bot') ); ?></th>
                                        <th style="width: 15%;"><?php echo esc_html( __('Performance', 'geeky-bot') ); ?></th>
                                        <th><?php echo esc_html( __('Error / Details', 'geeky-bot') ); ?></th>
                                        <th style="width: 120px;"><?php echo esc_html( __('Time', 'geeky-bot') ); ?></th>
                                        <th style="width: 60px; text-align: center;"><?php echo esc_html( __('Details', 'geeky-bot') ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ( empty( $logs ) ) : ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                                <?php echo esc_html( __('No log entries found. Generate some content in the playground to see logs.', 'geeky-bot') ); ?>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ( $logs as $log ) :
                                            // Data Mapping based on original table
                                            $http_code = absint($log['http_code'] ?? 200);
                                            $raw_status = strtolower($log['status'] ?? 'success');
                                            
                                            // Status Badge Logic
                                            if ($http_code >= 500 || $raw_status === 'error' || $raw_status === 'invalid') {
                                                $badge_class = 'err'; $badge_text = 'ERR';
                                            } elseif ($http_code >= 400 || $raw_status === 'warning') {
                                                $badge_class = 'wrn'; $badge_text = 'LIM';
                                            } else {
                                                $badge_class = 'suc'; $badge_text = 'SUC';
                                            }

                                            $action = $log['action'] ?: 'proxy_execute';
                                            $trace_id = $log['trace_id'] ?: '-';
                                            $wrapper_code = $log['wrapper_code'] ?: '-';
                                            $model_code = $log['model_code'] ?: '-';
                                            
                                            $total_tokens = number_format_i18n(absint($log['total_tokens'] ?? 0));
                                            $error_msg = $log['error_message'] ?: __('Request completed or details N/A.', 'geeky-bot');
                                            
                                            // Time Ago Calculation
                                            $time_ago = __('N/A', 'geeky-bot');
                                            if ( !empty($log['timestamp']) ) {
                                                $timestamp_unix = strtotime($log['timestamp']); 
                                                if ( $timestamp_unix && $timestamp_unix <= current_time('timestamp') ) {
                                                    $time_ago = human_time_diff($timestamp_unix, current_time('timestamp')) . ' ago';
                                                } else {
                                                    $time_ago = esc_html( date('M j, Y H:i', $timestamp_unix) );
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td style="text-align: center;">
                                                <span class="zy-badge <?php echo esc_attr( $badge_class ); ?>">
                                                    <?php echo esc_html( $badge_text ); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="zy-primary-text" title="Action"><?php echo esc_html( $action ); ?></div>
                                                <div class="zy-secondary-text" title="Trace ID: <?php echo esc_attr($trace_id); ?>">
                                                    ID: <?php echo esc_html( substr($trace_id, 0, 12) ); ?><?php echo strlen($trace_id) > 12 ? '...' : ''; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="zy-secondary-text" style="margin-bottom: 4px;" title="Wrapper: <?php echo esc_attr($wrapper_code); ?>">
                                                    <strong style="color: #4b5563;">W:</strong> <?php echo esc_html( substr($wrapper_code, 0, 20) ); ?><?php echo strlen($wrapper_code) > 20 ? '...' : ''; ?>
                                                </div>
                                                <div class="zy-secondary-text" title="Model: <?php echo esc_attr($model_code); ?>">
                                                    <strong style="color: #4b5563;">M:</strong> <?php echo esc_html( substr($model_code, 0, 20) ); ?><?php echo strlen($model_code) > 20 ? '...' : ''; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="zy-secondary-text" style="margin-bottom: 4px;">
                                                    <strong style="color: #4b5563;">HTTP:</strong> 
                                                    <span class="<?php echo $http_code >= 400 ? 'geekybot-text-error' : ''; ?>"><?php echo esc_html( $http_code ); ?></span>
                                                </div>
                                                <div class="zy-secondary-text">
                                                    <strong style="color: #4b5563;">Tokens:</strong> <?php echo esc_html( $total_tokens ); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="zy-secondary-text <?php echo ($badge_class === 'err') ? 'geekybot-text-error' : ''; ?>" title="<?php echo esc_attr( $error_msg ); ?>">
                                                    <?php echo esc_html( substr($error_msg, 0, 60) . (strlen($error_msg) > 60 ? '...' : '') ); ?>
                                                </div>
                                            </td>
                                            <td class="zy-secondary-text" title="<?php echo esc_attr( $log['timestamp'] ?? '' ); ?>">
                                                <?php echo esc_html( $time_ago ); ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <button class="zy-action-btn zy-view-details" 
                                                    data-trace="<?php echo esc_attr( $trace_id ); ?>"
                                                    data-status="<?php echo esc_attr( strtoupper($raw_status) ); ?>"
                                                    data-wrapper="<?php echo esc_attr( $wrapper_code ); ?>"
                                                    data-model="<?php echo esc_attr( $model_code ); ?>"
                                                    data-http="<?php echo esc_attr( $http_code ); ?>"
                                                    data-tokens="<?php echo esc_attr( $total_tokens ); ?> (In: <?php echo esc_attr($log['prompt_tokens']??0); ?>, Out: <?php echo esc_attr($log['completion_tokens']??0); ?>)"
                                                    data-time="<?php echo esc_attr( $log['timestamp'] ); ?>"
                                                    data-error="<?php echo esc_attr( $log['error_message'] ?: 'None' ); ?>"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
jQuery(document).ready(function($) {
    
    // Custom Alert Modal for Details
    $('.zy-view-details').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        
        const details = 
            'Trace ID:\t\t' + btn.data('trace') + '\n' +
            'Time:\t\t\t' + btn.data('time') + '\n' +
            'Status:\t\t\t' + btn.data('status') + '\n' +
            'Wrapper:\t\t' + btn.data('wrapper') + '\n' +
            'Model:\t\t\t' + btn.data('model') + '\n' +
            'HTTP Code:\t\t' + btn.data('http') + '\n' +
            'Tokens:\t\t\t' + btn.data('tokens') + '\n\n' +
            'Error / Output:\n' + btn.data('error');

        alert(details);
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
});
</script>