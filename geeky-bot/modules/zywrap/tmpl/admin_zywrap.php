<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

// This loads the "Settings Saved!" message
$geekybot_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('zywrap')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($geekybot_msgkey);

// Get the saved key from WordPress options
$geekybot_saved_key = get_option('geekybot_zywrap_api_key', '');
?>

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
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('Step 1', 'geeky-bot')); ?>
                </h1>
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
                                echo esc_html(__("To get your API key, log in to", 'geeky-bot'));
                                echo ' <a href="https://zywrap.com" target="_blank">'.esc_html('zywrap.com').'</a> ';
                                echo esc_html(__("and navigate to your Dashboard / API Keys.", 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-description geekybot-config-description-button geekybot-api-status">
                                <h3 class="geekybot-config-auto-download-steps-heading">
                                    <?php echo esc_html(__("Check API Key Status", 'geeky-bot')); ?>
                                </h3>
                                <button type="button" id="geekybot-check-zywrap-status" class="geekybot-table-act-btn geekybot-delete geekybot-config-download">
                                    <?php echo esc_html(__("Check Status", 'geeky-bot')); ?>
                                </button>
                                <div id="geekybot-zywrap-status-result"></div>
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
            <?php
            // Check if the API key is saved. If not, this section will be hidden.
            if (!empty($geekybot_saved_key)) :
                $geekybot_data_version = get_option('geekybot_zywrap_version', '');
            ?>
                <div id="geekybot-head">
                    <h1 class="geekybot-head-text">
                        <?php echo esc_html(__('Step 2', 'geeky-bot')); ?>
                    </h1>
                </div>
                <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper" style="border-top: none; padding-top: 0;">
                    <form id="geekybot-form-sync" class="geekybot-configurations" method="post" action="#">
                        <div class="geekybot-config-row-wrp">
                            <div class="geekybot-config-row">
                                <div class="geekybot-config-title">
                                    <?php echo esc_html(__('Sync Status', 'geeky-bot')); ?>
                                </div>
                                <div class="geekybot-config-value">
                                   <?php if (empty($geekybot_data_version)) : ?>
                                        <p><strong><?php echo esc_html(__('Status', 'geeky-bot')).': '.esc_html(__('Not Synced', 'geeky-bot')); ?></strong></p>
                                    <?php else : ?>
                                        <p><strong><?php echo esc_html(__('Status:', 'geeky-bot')).': '.esc_html(__('Synced', 'geeky-bot')); ?></strong><br>
                                        <?php echo esc_html(__('Local Data Version:', 'geeky-bot')); ?> <?php echo esc_html($geekybot_data_version); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="geekybot-config-description">
                                    <?php echo esc_html(__("Your local database must be synced with the Zywrap catalog to use the Playground and Editor tools.", 'geeky-bot')); ?>
                                </div>
                                <div class="geekybot-config-description geekybot-config-description-button geekybot-api-status">
                                    <?php if (empty($geekybot_data_version)) : ?>
                                        <button data-type="1" type="button" class="geekybot-delete geekybot-config-download zywrap-full-import-btn" style="margin-right: 10px;">
                                            <?php echo esc_html(__("Download & Full Import", 'geeky-bot')); ?>
                                        </button>
                                    <?php else : ?>
                                        <button data-type="2" type="button" class="geekybot-delete geekybot-config-download zywrap-full-import-btn" style="margin-right: 10px;">
                                            <?php echo esc_html(__("Sync Data Updates", 'geeky-bot')); ?>
                                        </button>
                                        <button data-type="3" type="button" class="geekybot-delete geekybot-config-download zywrap-full-import-btn">
                                            <?php echo esc_html(__("Force Full Re Sync", 'geeky-bot')); ?>
                                        </button>
                                    <?php endif; ?>
                                    <div id="geekybot-zywrap-sync-result" style="margin-top: 10px;"></div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            <?php else: ?>
                <div id="geekybot-head">
                    <h1 class="geekybot-head-text">
                        <?php echo esc_html(__('Step 2', 'geeky-bot')); ?>
                    </h1>
                </div>
                <div class="geekybot-admin-config-wrapper" style="border-top: none; padding-top: 0;">
                    <form id="geekybot-form-notice" class="geekybot-configurations" method="post" action="#">

                        <div class="geekybot-config-row-wrp">
                            <div class="geekybot-config-row">
                                <div class="geekybot-config-description">
                                    <?php echo esc_html(__("Please save the API key in Step 1.", 'geeky-bot')); ?>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Add our custom JavaScript to the page
$geekybot_js = "
jQuery(document).ready(function($) {
    function processImportBatch() {
        var current_btn = $('#zywrap-delta-sync-btn');
        var resultDiv = $('#geekybot-zywrap-sync-result');
        var ajaxurl = '" . esc_url(admin_url("admin-ajax.php")) . "';

        // --- SECONDARY / RECURSIVE CALL ---
        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',

            // 2. The SEPARATE server function (Task) you requested
            task: 'importZywrapBatchProcess',

            '_wpnonce': '" . esc_attr(wp_create_nonce("zywrap_full_import")) . "'
        })
        .done(function(response) {
            if (response.success) {
                var data = response.data;

                // CASE 1: STILL PAUSED -> RECURSIVE CALL
                if (data.status === 'paused') {
                    // Update UI with progress
                    resultDiv.html('<div class=\"notice notice-warning\"><p><span class=\"spinner is-active\"></span>' + data.message + ' (Remaining: ' + data.remaining + ')</p></div>');

                    // CALL ITSELF AGAIN to process the next batch
                    processImportBatch(current_btn);
                }

                // CASE 2: FINALLY COMPLETED
                else if (data.status === 'completed') {
                    resultDiv.html('<div class=\"notice notice-success\"><p>' + data.message + ' (Imported: ' + data.imported + ', Failed: ' + data.failed + ')</p></div>');

                    // Done! Reload page
                    // setTimeout(function() { location.reload(); }, 2000);
                }

            } else {
                // Handle Error
                resultDiv.html('<div class=\"notice notice-error\"><p>' + (response.data.message || 'Error in batch processing') + '</p></div>');
                current_btn.prop('disabled', false);
            }
        })
        .fail(function(xhr, textStatus, errorThrown) {
            resultDiv.html('<div class=\"notice notice-error\"><p>Batch Error: ' + textStatus + '</p></div>');
            current_btn.prop('disabled', false);
        });
    }
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
                // Key is valid
                var statusClass = (response.data.status === 'ok') ? 'notice-success' : 'notice-warning';
                resultDiv.html('<div class=\"notice ' + statusClass + '\"><p><strong>Status: ' + response.data.status.toUpperCase() + '</strong><br>' + response.data.message + '</p></div>');
            } else {
                // Key is invalid or API call failed
                resultDiv.html('<div class=\"notice notice-error\"><p><strong>Status: ' + (response.data.status || 'Error') + '</strong><br>' + response.data.message + '</p></div>');
            }
        })
        .fail(function(xhr, textStatus, errorThrown) {
            resultDiv.html('<div class=\"notice notice-error\"><p>Request Failed: ' + textStatus + ' - ' + errorThrown + '</p></div>');
        });
    });

    // --- FULL IMPORT AJAX ---
    $(document).on('click', '.zywrap-full-import-btn', function(e) {
        e.preventDefault();
        var resultDiv = $('#geekybot-zywrap-sync-result');
        var type = jQuery(this).attr('data-type');
        if(type == 1) {
            resultDiv.html('<span class=\"spinner is-active\"></span> " . esc_js(__('Downloading and importing... This may take several minutes.', 'geeky-bot')) . "');
        }
        if(type == 2) {
            resultDiv.html('<span class=\"spinner is-active\"></span> " . esc_js(__('Only new and changed data is being fetched and imported. This should take a few moments...', 'geeky-bot')) . "');
        }
        if(type == 3) {
            if (!confirm('" . esc_js(__('This will erase all local wrappers and perform a fresh download. This is recommended. Continue?', 'geeky-bot')) . "')) {
                return;
            }
            resultDiv.html('<span class=\"spinner is-active\"></span> " . esc_js(__('This will refresh everything from scratch and may take several minutes...', 'geeky-bot')) . "');
        }

        $(this).prop('disabled', true); // Disable button

        var ajaxurl = '" . esc_url(admin_url("admin-ajax.php")) . "';
        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'importZywrapData',
            actionType: type,
            '_wpnonce': '" . esc_attr(wp_create_nonce("zywrap_full_import")) . "'
        })
        .done(function(response) {
            /*
            if (response.success) {
                resultDiv.html('<div class=\"notice notice-success\"><p>' + response.data.message + '</p></div>');
                // Reload the page to show the 'Delta Sync' button
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                resultDiv.html('<div class=\"notice notice-error\"><p>' + response.data.message + '</p></div>');
                $('.zywrap-full-import-btn').prop('disabled', false);
            }
            */
            if (response.success) {
                var data = response.data; // Access the array returned by PHP
                console.log(data);
                // CASE 1: PAUSED - Recursive Call
                if (data.status === 'paused') {
                    // Update UI to show progress (optional but recommended)
                    resultDiv.html('<div class=\"notice notice-warning\"><p>' + data.message + ' (Remaining: ' + data.remaining + ')</p></div>');

                    // Immediately trigger the next batch
                    processImportBatch();
                }
                // CASE 2: COMPLETED - Finish Up
                else if (data.status === 'completed') {
                    resultDiv.html('<div class=\"notice notice-success\"><p>' + data.message + ' (Imported: ' + data.imported + ', Failed: ' + data.failed + ')</p></div>');

                    // Reload the page to show the 'Delta Sync' button
                    // setTimeout(function() { location.reload(); }, 2000);
                }
                // Fallback for other success cases
                else {
                    resultDiv.html('<div class=\"notice notice-info\"><p>' + (data.message || 'Action processed') + '</p></div>');
                }

            } else {
                // Handle logical error from WP (wp_send_json_error)
                resultDiv.html('<div class=\"notice notice-error\"><p>' + (response.data.message || 'Unknown error occurred') + '</p></div>');
                $('.zywrap-full-import-btn').prop('disabled', false);
            }
        })
        .fail(function(xhr, textStatus, errorThrown) {
            // This catches timeout errors and PHP fatal errors that return non-JSON
            resultDiv.html('<div class=\"notice notice-error\"><p>Error: The request failed or timed out. Please check your PHP logs. (' + textStatus + ')</p></div>');
            $('.zywrap-full-import-btn').prop('disabled', false);
        });
    });

    // --- DELTA SYNC AJAX ---
    $(document).on('click', '#zywrap-delta-sync-btn', function(e) {
        e.preventDefault();
        var resultDiv = $('#geekybot-zywrap-sync-result');

        resultDiv.html('<span class=\"spinner is-active\"></span> " . esc_js(__('Checking for updates...', 'geeky-bot')) . "');
        $(this).prop('disabled', true);

        var ajaxurl = '" . esc_url(admin_url("admin-ajax.php")) . "';
        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'sync_zywrap_delta',
            '_wpnonce': '" . esc_attr(wp_create_nonce("zywrap_delta_sync")) . "'
        })
        .done(function(response) {
            if (response.success) {
                resultDiv.html('<div class=\"notice notice-success\"><p>' + response.data.message + '</p></div>');
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                resultDiv.html('<div class=\"notice notice-error\"><p>' + response.data.message + '</p></div>');
                $('#zywrap-delta-sync-btn').prop('disabled', false);
            }
        })
        .fail(function(xhr, textStatus, errorThrown) {
            resultDiv.html('<div class=\"notice notice-error\"><p>Request Failed: ' + textStatus + ' - ' + errorThrown + '</p></div>');
            $('#zywrap-delta-sync-btn').prop('disabled', false);
        });
    });
});
";
wp_register_script( 'geekybot-inline-handle', '' );
wp_enqueue_script( 'geekybot-inline-handle' );

wp_add_inline_script('geekybot-inline-handle', $geekybot_js);
?>
