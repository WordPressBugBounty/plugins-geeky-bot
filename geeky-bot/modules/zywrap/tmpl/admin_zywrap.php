<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

// This loads the "Settings Saved!" message
$geekybot_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('zywrap')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($geekybot_msgkey);

// Get the saved key from WordPress options
$geekybot_saved_key = get_option('geekybot_zywrap_api_key', '');
$geekybot_data_version = get_option('geekybot_zywrap_bundle_version', '');
$geekybot_last_sync = get_option('geekybot_zywrap_last_sync_time', '');

// Get Total Wrappers Count
global $wpdb;
$total_wrappers = GEEKYBOTincluder::GEEKYBOT_getModel('zywrap')->getTotalWrappers();
?>

<style>
    .geekybot-stats-grid { display: flex; gap: 20px; margin-bottom: 25px; }
    .geekybot-stat-box { flex: 1; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .geekybot-stat-title { font-size: 12px; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 8px; }
    .geekybot-stat-value { font-size: 24px; font-weight: 700; color: #111827; }
    .geekybot-stat-value.status-active { color: #10b981; }
    .geekybot-stat-value.status-missing { color: #ef4444; }
    .geekybot-section-disabled { opacity: 0.6; pointer-events: none; filter: grayscale(20%); }
    .geekybot-progress-container { display: none; margin-top: 15px; width: 100%; max-width: 400px; }
    .geekybot-progress-track { width: 100%; height: 8px; background-color: #e5e7eb; border-radius: 4px; overflow: hidden; }
    .geekybot-progress-fill { width: 100%; height: 100%; background-color: #6366f1; border-radius: 4px; transform-origin: left; animation: geekybot-indeterminate 1.5s infinite linear; }
    .geekybot-progress-text { font-size: 12px; color: #6b7280; margin-top: 8px; font-weight: 500; }
    @keyframes geekybot-indeterminate {
        0% { transform: translateX(-100%) scaleX(0.2); }
        50% { transform: translateX(0%) scaleX(0.5); }
        100% { transform: translateX(100%) scaleX(0.2); }
    }
</style>

<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav', array('module' => 'zywrap', 'layouts' => 'zywrap')); ?>
    <div class="geekybotadmin-body-main">
        <div id="geekybotadmin-leftmenu-main">
            <?php GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue', array('module' => 'zywrap')); ?>
        </div>
        <div id="geekybotadmin-data">
            <div class="geekybot-tab-nav">
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywrap&geekybotlt=zywrap','configuration'))?>" class="geekybot-tab-link active"><?php echo esc_html(__('AI Settings', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywrap&geekybotlt=playground','Zywrap'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Generate Text', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywraplogs&geekybotlt=logs','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Logs', 'geeky-bot')); ?></a>
            </div>

            <div class="geekybot-stats-grid">
                <div class="geekybot-stat-box">
                    <div class="geekybot-stat-title"><?php echo esc_html(__('API Status', 'geeky-bot')); ?></div>
                    <div class="geekybot-stat-value <?php echo $geekybot_saved_key ? 'status-active' : 'status-missing'; ?>">
                        <?php echo $geekybot_saved_key ? esc_html(__('Active', 'geeky-bot')) : esc_html(__('Missing Key', 'geeky-bot')); ?>
                    </div>
                </div>
                <div class="geekybot-stat-box">
                    <div class="geekybot-stat-title"><?php echo esc_html(__('Wrappers Synced', 'geeky-bot')); ?></div>
                    <div class="geekybot-stat-value"><?php echo number_format($total_wrappers); ?></div>
                </div>
                <div class="geekybot-stat-box">
                    <div class="geekybot-stat-title"><?php echo esc_html(__('Last Synced', 'geeky-bot')); ?></div>
                    <div class="geekybot-stat-value" style="font-size: 16px; margin-top: 6px;">
                        <?php 
                        if (!empty($geekybot_last_sync)) {
                            echo esc_html(date_i18n(get_option('date_format') . ' - ' . get_option('time_format'), $geekybot_last_sync));
                        } else {
                            echo esc_html(__('Never', 'geeky-bot'));
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div id="geekybot-head">
                <h1 class="geekybot-head-text"><?php echo esc_html(__('Step 1: API Authentication', 'geeky-bot')); ?></h1>
            </div>
            <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper">
                <form id="geekybot-form" class="geekybot-configurations" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_zywrap&task=save_zywrap_settings&action=geekybottask"), "save-zywrap-settings")); ?>">
                    <div class="geekybot-config-row-wrp">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Zywrap API Key', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('geekybot_zywrap_api_key', $geekybot_saved_key, array('class' => 'inputbox')), GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php 
                                echo esc_html(__("Create a free account at", 'geeky-bot'));
                                echo ' <a href="https://zywrap.com/register?utm_source=wordpress-plugin&utm_medium=geeky-bot&utm_campaign=onboarding" target="_blank">'.esc_html('zywrap.com').'</a> ';
                                echo esc_html(__("to receive 10,000 Free Credits instantly. Navigate to API Keys in your dashboard to generate a secret key.", 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-description geekybot-config-description-button geekybot-api-status">
                                <button type="button" id="geekybot-check-zywrap-status" class="geekybot-table-act-btn geekybot-delete geekybot-config-download">
                                    <?php echo esc_html(__("Check Status", 'geeky-bot')); ?>
                                </button>
                                <div id="geekybot-zywrap-status-result" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'zywrap_save_zywrap_settings'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    
                    <div class="geekybot-config-btn">
                        <button title="<?php echo esc_html(__('Save Key', 'geeky-bot')); ?>" type="submit" class="button geekybot-config-save-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" alt="<?php echo esc_html(__('Save Setting', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Save Key', 'geeky-bot')); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div id="geekybot-head">
                <h1 class="geekybot-head-text"><?php echo esc_html(__('Step 2: Database Synchronization', 'geeky-bot')); ?></h1>
            </div>
            
            <?php $is_disabled = empty($geekybot_saved_key) ? 'geekybot-section-disabled' : ''; ?>
            
            <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper <?php echo esc_attr($is_disabled); ?>" style="border-top: none; padding-top: 0;">
                <form id="geekybot-form-sync" class="geekybot-configurations" method="post" action="#">
                    <div class="geekybot-config-row-wrp">
                        <div class="geekybot-config-row" style="display: block; text-align: left; width: 100%;">
                            
                            <div class="geekybot-config-description" style="margin-top: 0; padding-top: 10px; font-size: 14px; text-align: left; width: 100%;">
                                <?php echo esc_html(__("To use the Co-Pilot and Playground, you must sync your local database with the Zywrap Cloud. This downloads the latest Prompts, Scenarios, and Configuration Schemas.", 'geeky-bot')); ?>
                            </div>

                            <div class="notice notice-warning" style="margin-left: 0; margin-top: 15px; text-align: left; display: block;">
                                <p><strong><?php echo esc_html(__("Important:", 'geeky-bot')); ?></strong> <?php echo esc_html(__("The initial sync processes a large data bundle and may take 3 to 5 minutes to complete. Please do not close or refresh this page while the sync is running.", 'geeky-bot')); ?></p>
                            </div>

                            <div class="geekybot-config-description geekybot-config-description-button geekybot-api-status" style="margin-top: 20px; text-align: left;">
                                
                                <button type="button" class="geekybot-delete geekybot-config-download zywrap-smart-sync-btn" <?php echo empty($geekybot_saved_key) ? 'disabled' : ''; ?>>
                                    <?php 
                                    if (empty($geekybot_data_version)) {
                                        echo esc_html(__("Download & Sync Data", 'geeky-bot'));
                                    } else {
                                        echo esc_html(__("Check for Updates (Smart Sync)", 'geeky-bot'));
                                    }
                                    ?>
                                </button>
                                
                                <div id="geekybot-sync-progress" class="geekybot-progress-container" style="text-align: left;">
                                    <div class="geekybot-progress-track">
                                        <div class="geekybot-progress-fill"></div>
                                    </div>
                                    <div class="geekybot-progress-text">
                                        <?php echo esc_html(__("Downloading and processing data bundle... Please do not close or refresh this page.", 'geeky-bot')); ?>
                                    </div>
                                </div>

                                <div id="geekybot-zywrap-sync-result" style="margin-top: 10px; text-align: left;"></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
        </div>
    </div>
</div>

<?php
// Add our custom JavaScript to the page
$geekybot_js = "
jQuery(document).ready(function($) {

    // --- API KEY CHECK AJAX ---
    $(document).on('click', '#geekybot-check-zywrap-status', function(e) {
        e.preventDefault();
        var apiKey = $('input[name=\"geekybot_zywrap_api_key\"]').val();
        var resultDiv = $('#geekybot-zywrap-status-result');

        if (!apiKey) {
            resultDiv.html('<div class=\"notice notice-error\"><p>Please enter an API key first.</p></div>');
            return;
        }

        resultDiv.html('<span class=\"spinner is-active\"></span> " . esc_js(__('Checking API status...', 'geeky-bot')) . "');

        var ajaxurl = '" . esc_url(admin_url("admin-ajax.php")) . "';
        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'checkZywrapApiKey',
            api_key: apiKey,
            '_wpnonce': '" . esc_attr(wp_create_nonce("check-zywrap-key")) . "'
        })
        .done(function(response) {
            if (response.success) {
                var statusClass = (response.data.status === 'ok') ? 'notice-success' : 'notice-warning';
                resultDiv.html('<div class=\"notice ' + statusClass + '\"><p><strong>Status: ' + response.data.status.toUpperCase() + '</strong><br>' + response.data.message + '</p></div>');
            } else {
                resultDiv.html('<div class=\"notice notice-error\"><p><strong>Status: ' + (response.data.status || 'Error') + '</strong><br>' + response.data.message + '</p></div>');
            }
        })
        .fail(function(xhr, textStatus, errorThrown) {
            resultDiv.html('<div class=\"notice notice-error\"><p>Request Failed: ' + textStatus + ' - ' + errorThrown + '</p></div>');
        });
    });

    // --- V1 SMART SYNC AJAX ---
    $(document).on('click', '.zywrap-smart-sync-btn', function(e) {
        e.preventDefault();
        var resultDiv = $('#geekybot-zywrap-sync-result');
        var progressContainer = $('#geekybot-sync-progress');
        var btn = $(this);

        // UI Updates: Hide previous text, show progress bar, disable button
        resultDiv.empty();
        progressContainer.show();
        btn.prop('disabled', true); 

        var ajaxurl = '" . esc_url(admin_url("admin-ajax.php")) . "';
        
        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'syncZywrapData', // Points to the new unified method in model.php
            '_wpnonce': '" . esc_attr(wp_create_nonce("zywrap_full_import")) . "'
        })
        .done(function(response) {
            progressContainer.hide(); // Hide progress bar on completion

            if (response.success) {
                var data = response.data;
                resultDiv.html('<div class=\"notice notice-success\"><p>' + (data.message || 'Sync processed successfully!') + '</p></div>');
                
                // Reload page after a brief delay to update the Top Stats
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                resultDiv.html('<div class=\"notice notice-error\"><p>' + (response.data.message || 'An unknown error occurred') + '</p></div>');
                btn.prop('disabled', false);
            }
        })
        .fail(function(xhr, textStatus, errorThrown) {
            progressContainer.hide();
            resultDiv.html('<div class=\"notice notice-error\"><p>Error: The request failed or timed out. Please check your PHP logs. (' + textStatus + ')</p></div>');
            btn.prop('disabled', false);
        });
    });

});
";

wp_register_script( 'geekybot-inline-handle', '' );
wp_enqueue_script( 'geekybot-inline-handle' );
wp_add_inline_script('geekybot-inline-handle', $geekybot_js);
?>
