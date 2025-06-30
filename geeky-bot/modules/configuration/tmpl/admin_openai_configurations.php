<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
wp_enqueue_style('geekybot-select2css', GEEKYBOT_PLUGIN_URL . 'includes/css/select2.min.css');
wp_enqueue_script('geekybot-select2js', GEEKYBOT_PLUGIN_URL . 'includes/js/select2.min.js');
$msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($msgkey);
$geekybot_js = "
jQuery(document).ready(function() {
    jQuery('#geekybot_openai_model').select2({
       dropdownParent: jQuery('.geekybot-config-model-value')
    });
    jQuery(document).on('click', '.geekybot-config-save-btn', function() {
        geekybotShowLoading();
    });
    jQuery(document).on('click', '#download-openai-assistant', function() {
        const button = this;
        button.disabled = true;
        button.textContent = '".esc_html(__('Downloading...', 'geeky-bot'))."';
        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'openaiassistant',
            task: 'geekybotDownloadOpenAiAssistantLibrary',
            '_wpnonce':'". esc_attr(wp_create_nonce("download_openai_assistant")) ."'
        }, function(data) {
            if (data) {
                if (data.success) {
                    jQuery('div#geekybot-assistant-setup').show();
                    jQuery('div.geekybot-autod-download-seprater').hide();
                    jQuery('div#download-steps').hide();
                    jQuery('div#google-client-response').html(`
                      <div class='notice notice-success is-dismissible geekybot-config-success-msg'>
                        <p>".esc_html(__('OpenAI Assistant PHP Client Library downloaded successfully.', 'geeky-bot'))."</p>
                      </div>
                    `);
                } else {
                    jQuery('div#google-client-response').html(`
                      <div class='notice notice-error is-dismissible geekybot-config-error-msg'>
                        <p>".esc_html(__('Failed to download OpenAI Assistant PHP Client Library.', 'geeky-bot'))."</p>
                      </div>
                    `);
                }
            } else {
                jQuery('div#google-client-response').html(`
                  <div class='notice notice-error is-dismissible  geekybot-config-error-msg'>
                    <p>".esc_html(__('Failed to download OpenAI Assistant PHP Client Library.', 'geeky-bot'))."</p>
                  </div>
                `);
            }
            button.disabled = false;
            button.textContent = '".esc_html(__('Download OpenAI Assistant PHP Client Library', 'geeky-bot'))."';
        });
    });
});";
wp_add_inline_script('geekybot-main-js',$geekybot_js);

$syncmethodslist = array(
    (object) array('id' => '1', 'text' => __('Auto Sync on Post Save (Recommended)', 'geeky-bot')),
    (object) array('id' => '2', 'text' => __('Sync via Scheduled Cron Job (hourly)', 'geeky-bot')),
    (object) array('id' => '3', 'text' => __('Manual Sync Only', 'geeky-bot'))
);
$temperaturelist = array(
    (object) ['id' => '0.0', 'text' => __('Precise (0.0)', 'geeky-bot')],
    (object) ['id' => '0.1', 'text' => __('Ultra Analytical (0.1)', 'geeky-bot')],
    (object) ['id' => '0.2', 'text' => __('Highly Factual (0.2)', 'geeky-bot')],
    (object) ['id' => '0.3', 'text' => __('Slightly Creative (0.3)', 'geeky-bot')],
    (object) ['id' => '0.4', 'text' => __('Mostly Factual (0.4)', 'geeky-bot')],
    (object) ['id' => '0.5', 'text' => __('Neutral Blend (0.5)', 'geeky-bot')],
    (object) ['id' => '0.6', 'text' => __('Mildly Inventive (0.6)', 'geeky-bot')],
    (object) ['id' => '0.7', 'text' => __('Balanced (0.7)', 'geeky-bot')],
    (object) ['id' => '0.8', 'text' => __('Balanced + (0.8)', 'geeky-bot')],
    (object) ['id' => '0.9', 'text' => __('Almost Creative (0.9)', 'geeky-bot')],
    (object) ['id' => '1.0', 'text' => __('Creative (1.0)', 'geeky-bot')],
    (object) ['id' => '1.1', 'text' => __('Creative + (1.1)', 'geeky-bot')],
    (object) ['id' => '1.2', 'text' => __('Playful (1.2)', 'geeky-bot')],
    (object) ['id' => '1.3', 'text' => __('Highly Imaginative (1.3)', 'geeky-bot')],
    (object) ['id' => '1.4', 'text' => __('Loose Thinker (1.4)', 'geeky-bot')],
    (object) ['id' => '1.5', 'text' => __('Very Creative (1.5)', 'geeky-bot')],
    (object) ['id' => '1.6', 'text' => __('Free Flow (1.6)', 'geeky-bot')],
    (object) ['id' => '1.7', 'text' => __('Inventor Mode (1.7)', 'geeky-bot')],
    (object) ['id' => '1.8', 'text' => __('Wildly Creative (1.8)', 'geeky-bot')],
    (object) ['id' => '1.9', 'text' => __('Unpredictable (1.9)', 'geeky-bot')],
    (object) ['id' => '2.0', 'text' => __('Maximum Imagination (2.0)', 'geeky-bot')],
);
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'configuration','layouts' => 'openai_configurations')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
           <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data">
            <div class="geekybot-tab-nav">
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=ai_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Provider', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=dialogflow_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('Dialogflow', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=openai_configurations','configuration'))?>" class="geekybot-tab-link active"><?php echo esc_html(__('OpenAI', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=openrouter_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('OpenRouter', 'geeky-bot')); ?></a>
            </div>
            <!-- top head -->
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('OpenAI', 'geeky-bot')); ?>
                </h1>
            </div>
            <!-- page content -->
            <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper">
                <form id="geekybot-form" class="geekybot-configurations" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_configuration&task=saveconfiguration"),"save-configuration")); ?>">
                    <div class="geekybot-config-row-wrp geekybot-aiprovider-header">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('OpenAI API key', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('geekybot_openai_api_key', isset(geekybot::$_data[0]['geekybot_openai_api_key']) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['geekybot_openai_api_key']) : '', array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("The API key used to connect your chatbot to OpenAI's services.", 'geeky-bot'));
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="geekybot-config-row-wrp geekybot-aiprovider-header">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Select Model', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <div class="geekybot-config-model-value">
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('geekybot_openai_model', GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getOpenaiModelList(), isset(geekybot::$_data[0]['geekybot_openai_model']) ? geekybot::$_data[0]['geekybot_openai_model'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                                </div>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("Select the OpenAI model your chatbot will use for generating responses.", 'geeky-bot'));
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="geekybot-config-row-wrp geekybot-aiprovider-header">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Select Temperature', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('geekybot_openai_temperature', $temperaturelist, isset(geekybot::$_data[0]['geekybot_openai_temperature']) ? geekybot::$_data[0]['geekybot_openai_temperature'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("Controls the creativity of the responses. Lower values make replies more focused, higher values make them more diverse.", 'geeky-bot'));
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="geekybot-config-row-wrp geekybot-aiprovider-header">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Max Tokens', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('geekybot_openai_max_tokens', isset(geekybot::$_data[0]['geekybot_openai_max_tokens']) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['geekybot_openai_max_tokens']) : '', array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("Controls the creativity of the assistant’s responses. Lower values make replies more focused, higher values make them more diverse.", 'geeky-bot'));
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="geekybot-config-row-wrp geekybot-aiprovider-header">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Choose Sync Method', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('geekybot_sync_method', $syncmethodslist, isset(geekybot::$_data[0]['geekybot_sync_method']) ? geekybot::$_data[0]['geekybot_sync_method'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'onchange' => 'getProsAndConsOfMethod(this.value);', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description  geekybot-config-description-method">
                                <h3 class="geekybot-config-download-steps-heading">
                                    <?php echo esc_html(__("Here are some pros and cons of this method.", 'geeky-bot')); ?>
                                </h3>
                                <div class="geekybot-config-download-steps-wrp" id="geekybot_auto">
                                    <ul>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ✔ <?php echo esc_html(__("Always up-to-date", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ✔ <?php echo esc_html(__("No manual work needed", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ❌ <?php echo esc_html(__("Regenerates file on every change", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ❌ <?php echo esc_html(__("Slight increase in server load on frequent edits", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                <div class="geekybot-config-download-steps-wrp" id="geekybot_cron">
                                    <ul>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ✔ <?php echo esc_html(__("Scheduled updates hourly", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ✔ <?php echo esc_html(__("More efficient for high-edit sites", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ❌ <?php echo esc_html(__("Slight delay in content updates", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ❌ <?php echo esc_html(__("Requires working WordPress cron system", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                <div class="geekybot-config-download-steps-wrp" id="geekybot_manual">
                                    <ul>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ✔ <?php echo esc_html(__("Full control of sync timing", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ✔ <?php echo esc_html(__("No impact on performance", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ❌ <?php echo esc_html(__("Easy to forget to sync", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="geekybot-config-download-steps-text">
                                                ❌ <?php echo esc_html(__("Assistant may use outdated data", 'geeky-bot')); ?>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <?php
                            $isAssistantFound = get_option('geekybot_assistant_id');
                            if(in_array('openaiassistant', geekybot::$_active_addons) && !empty($isAssistantFound) == 3 && geekybot::$_configuration['geekybot_sync_method'] == 3){
                                $changed_posts = get_option( 'geekyboot_changed_posts', [] );
                                if ( ! empty( $changed_posts ) && is_array( $changed_posts ) ) {
                                    $count = count( $changed_posts );
                                    ?>
                                    <div class="geekybot-config-description geekybot-config-description-button">
                                        <div class="geekybot-sync-link-wrp">
                                            <div class="geekybot-sync-message">
                                                <strong><?php echo esc_html( $count ).' '.esc_html(__('post(s) need to be synced.', 'geeky-bot')); ?></strong>
                                            </div>
                                            <a href="#" id="geekybot-sync-link" class="button button-primary"><?php echo esc_html(__('Sync Now', 'geeky-bot')); ?></a>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            if(in_array('openaiassistant', geekybot::$_active_addons)){
                                $style = 'display:none';
                                $uploadDir = wp_upload_dir();
                                // Convert full path to relative: remove ABSPATH from basedir
                                $relativePath = str_replace(ABSPATH, '', $uploadDir['basedir']);

                                if (!file_exists($uploadDir['basedir'] . '/geekybotLibraries/openAI/geekybot_openai_assistant_client_library-main/autoload.php')) {
                                    GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->geekybotPrepareManualOpenAILibraryPath(); ?>
                                    <div class="geekybot-config-description geekybot-config-description-button">
                                        <h3 class="geekybot-config-auto-download-steps-heading">
                                            <?php echo esc_html(__("Auto Download OpenAI Assistant PHP Client Library", 'geeky-bot')); ?>
                                        </h3>
                                        <span id="download-openai-assistant" class="geekybot-config-download">
                                            <?php echo esc_html(__("Auto Download OpenAI Assistant PHP Client Library", 'geeky-bot')); ?>
                                        </span>
                                        <div id="google-client-response"></div>
                                        <div class="geekybot-autod-download-seprater">
                                            <span class="geekybot-seprate-line"></span>
                                            <span class="geekybot-seprate-line-text"><?php echo esc_html(__("OR", 'geeky-bot')); ?></span>
                                            <span class="geekybot-seprate-line"></span>
                                        </div>
                                        <div id="download-steps" class="geekybot-config-download-steps-wrp">
                                            <h3 class="geekybot-config-download-steps-heading">
                                                <?php echo esc_html(__("Manual Steps to Download OpenAI Assistant PHP Client Library", 'geeky-bot')); ?>
                                            </h3>
                                            <div class="geekybot-config-download-steps-text">
                                                <?php echo esc_html(__("Here are the manual steps to download and install the OpenAI Assistant PHP Client Library in your WordPress.", 'geeky-bot')); ?>
                                            </div>
                                            <h4 class="geekybot-config-download-steps-subheading">
                                                <?php echo esc_html(__("Step 1: Download the Library ZIP", 'geeky-bot')); ?>
                                            </h4>
                                            <ul>
                                                <li>
                                                    <div class="geekybot-config-download-steps-text">
                                                        <?php echo esc_html(__("Use this direct link:", 'geeky-bot')); ?>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="geekybot-config-download-steps-text">
                                                        <a href="https://github.com/geekybotai/geekybot_openai_assistant/blob/main/geekybot_openai_assistant_client_library-main.zip"><?php echo esc_html("https://github.com/geekybotai/geekybot_openai_assistant/blob/main/geekybot_openai_assistant_client_library-main.zip"); ?></a>
                                                    </div>
                                                </li>
                                            </ul>
                                            <h4 class="geekybot-config-download-steps-subheading">
                                                <?php echo esc_html(__("Step 2: Extract the ZIP File", 'geeky-bot')); ?>
                                            </h4>
                                            <ul>
                                                <li>
                                                    <div class="geekybot-config-download-steps-text">
                                                        <?php echo esc_html(__("Extract the downloaded ZIP on your computer.", 'geeky-bot')); ?>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="geekybot-config-download-steps-text">
                                                        <?php echo esc_html(__("After extracting, it will create the actual", 'geeky-bot')).' "'. esc_html("geekybot_openai_assistant_client_library-main").'" '.esc_html(__("folder you need to place inside your WordPress:", 'geeky-bot')); ?>
                                                    </div>
                                                </li>
                                            </ul>
                                            <h4 class="geekybot-config-download-steps-subheading">
                                                <?php echo esc_html(__("Step 3: Upload to Your WordPress Plugin Directory", 'geeky-bot')); ?>
                                            </h4>
                                            <ul>
                                                <li>
                                                    <div class="geekybot-config-download-steps-text">
                                                        <?php echo esc_html(__("Upload the final", 'geeky-bot')).' "'.esc_html(__("geekybot_openai_assistant_client_library-main", 'geeky-bot')).'" '.esc_html(__("folder to your WordPress plugin:", 'geeky-bot')); ?>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="geekybot-config-download-steps-text">
                                                        <?php echo esc_html($relativePath."/geekybotLibraries/openAI/"); ?>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php 
                                } else {
                                    $style = 'display:block';
                                } ?>
                                <div style="<?php echo $style; ?>" id="geekybot-assistant-setup" class="geekybot-config-description geekybot-config-description-button">
                                    <h3 class="geekybot-config-auto-download-steps-heading">
                                        <?php echo esc_html(__("OpenAI Assistant Setup", 'geeky-bot')); ?>
                                    </h3>
                                    <?php
                                    if(in_array('openaiassistant', geekybot::$_active_addons)) {
                                        $types = get_option('openai_assistant_upload_types', []); ?>
                                        <label>
                                            <input type="checkbox" id="upload_story" name="upload_story" value="1" <?php checked(in_array('story', $types)); ?>>
                                            <?php echo esc_html(__('Upload AI ChatBot Data', 'geeky-bot')); ?>
                                        </label><br>

                                        <label>
                                            <input type="checkbox" id="upload_post" name="upload_post" value="1" <?php checked(in_array('post', $types)); ?>>
                                            <?php echo esc_html(__('Upload Web Search Data', 'geeky-bot')); ?>
                                        </label><br>
                                        <?php 
                                    } ?>
                                    <a id="geekybot-export-link" class="geekybot-table-act-btn geekybot-delete geekybot-config-download" href="#">
                                        <?php echo esc_html(__("Prepare Assistant Data", 'geeky-bot')); ?>
                                    </a>
                                    <div id="openai-assistant-response"></div>
                                    <div class="geekybot-autod-download-seprater">
                                        
                                    </div>
                                </div>
                                <?php 
                            } ?>
                            <div class="geekybot-config-description geekybot-config-description-button geekybot-api-status">
                                <h3 class="geekybot-config-auto-download-steps-heading">
                                    <?php echo esc_html(__("Check OpenAI Status", 'geeky-bot')); ?>
                                </h3>
                                <button id="geekybot-check-openai-status" class="geekybot-table-act-btn geekybot-delete geekybot-config-download" href="#">
                                    <?php echo esc_html(__("Check OpenAI Status", 'geeky-bot')); ?>
                                </button>
                                <div id="geekybot-openai-status-result"></div>
                            </div>
                        </div>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('isgeneralbuttonsubmit', 1), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('geekybotlt', 'openai_configurations'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'configuration_saveconfiguration'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <div class="geekybot-config-btn">
                        <button title="<?php echo esc_html(__('Save Settings', 'geeky-bot')); ?>" type="submit"class="button geekybot-config-save-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" alt="<?php echo esc_html(__('Add Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Save OpenAI', 'geeky-bot')); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$method = geekybot::$_configuration['geekybot_sync_method'];
$raw_url = wp_nonce_url(admin_url('admin.php?page=geekybot_configuration&task=geekybotExportStoryAndPost&action=geekybottask&geekybotlt=openai_configurations'), 'export-story-post');
$geekybot_js ="
jQuery(document).ready(function() {
    jQuery(document).on('click', '#geekybot-check-openai-status', function(e) {
        e.preventDefault();
        jQuery('#geekybot-openai-status-result').html(\"<span class='spinner is-active'></span> ". esc_html(__('Checking OpenAI status...', 'geeky-bot')) ."\");
        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'geekybot',
            task: 'geekybotCheckOpenAIStatus',
            '_wpnonce': '". esc_attr(wp_create_nonce("geekybot_check_openai_status")) ."'
        }, function(data) {
            if (data) {
                if (data.success) {
                    let statusClass = 'notice-' + data.data.status;
                    let html = `<div class='notice notice-success'>`+data.data.message+`<ul>`;

                    // Show additional info api type
                    if (data.data.data.api_type !== 'N/A') {
                        html += `<li><strong>". esc_html(__('API Type: ', 'geeky-bot')) ."</strong>`+data.data.data.api_type+`</li>`;
                    }

                    if (data.data.data.assistant_id) {
                        html += `<li><strong>". esc_html(__('Assistant Id: ', 'geeky-bot')) ."</strong>`+data.data.data.assistant_id+`</li>`;
                    }

                    if (data.data.data.assistants_found) {
                        html += `<li><strong>". esc_html(__('Assistants Found: ', 'geeky-bot')) ."</strong>`+data.data.data.assistants_found+`</li>`;
                    }
                    
                    // Show api_status
                    if (data.data.data.api_status !== 'N/A') {
                        html += `<li><strong>". esc_html(__('API Status: ', 'geeky-bot')) ."</strong>`+data.data.data.api_status+`</li>`;
                    }
                    
                    // Show response_time
                    if (data.data.data.response_time !== 'N/A') {
                        html += `<li><strong>". esc_html(__('Response Time: ', 'geeky-bot')) ."</strong>`+data.data.data.response_time+`</li>`;
                    }
                    
                    if (data.data.data.models_available) {
                        html += `<li><strong>". esc_html(__('Models Available: ', 'geeky-bot')) ."</strong>`+data.data.data.models_available+`</li>`;
                    }
                    
                    if (data.data.data.model_example) {
                        html += `<li><strong>". esc_html(__('Model Example: ', 'geeky-bot')) ."</strong>`+data.data.data.model_example+`</li>`;
                    }
                    
                    html += `</ul></div>`;
                    jQuery('#geekybot-openai-status-result').html(html);
                } else {
                    let errorMsg = (data && data.data && data.data.message) ? data.data.message : '". esc_html(__('Unknown error occurred', 'geeky-bot')) ."';
                    jQuery('#geekybot-openai-status-result').html(`<div class='notice notice-error'>`+errorMsg+`</div>`);
                }
            } else {
                jQuery('#geekybot-openai-status-result').html(
                    `<div class='notice notice-error'>". esc_html(__('Request failed: ', 'geeky-bot')) ."'}</div>`
                );
            }
        });
    });";
    if($method == 1) {
        $geekybot_js .= "
        jQuery('#geekybot_auto').show();
        jQuery('#geekybot_cron').hide();
        jQuery('#geekybot_manual').hide();";
    } elseif($method == 2) {
        $geekybot_js .= "
        jQuery('#geekybot_auto').hide();
        jQuery('#geekybot_cron').show();
        jQuery('#geekybot_manual').hide();";
    } elseif($method == 3) {
        $geekybot_js .= "
        jQuery('#geekybot_auto').hide();
        jQuery('#geekybot_cron').hide();
        jQuery('#geekybot_manual').show();";
    }
    $geekybot_js .= "
    let baseUrl = ".json_encode(htmlspecialchars_decode($raw_url)).";
    function updateExportURL() {
        
        let params = [];

        if (jQuery('#upload_story').is(':checked')) {
            params.push('upload_story=1');
        }
        if (jQuery('#upload_post').is(':checked')) {
            params.push('upload_post=1');
        }

        let finalUrl = baseUrl;
        if (params.length > 0) {
            finalUrl += '&' + params.join('&');
        }

        jQuery('#geekybot-export-link').prop('href', finalUrl);
        jQuery('#geekybot-sync-link').prop('href', finalUrl);
    }

    // Attach event listeners
    jQuery('#upload_story, #upload_post').on('change', updateExportURL);

    // Update on page load
    updateExportURL();
});
function getProsAndConsOfMethod(val) {
    if(val == 1) {
        jQuery('#geekybot_auto').show();
        jQuery('#geekybot_cron').hide();
        jQuery('#geekybot_manual').hide();
    } else if(val == 2) {
        jQuery('#geekybot_auto').hide();
        jQuery('#geekybot_cron').show();
        jQuery('#geekybot_manual').hide();
    } else if(val == 3) {
        jQuery('#geekybot_auto').hide();
        jQuery('#geekybot_cron').hide();
        jQuery('#geekybot_manual').show();
    }
}
";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>