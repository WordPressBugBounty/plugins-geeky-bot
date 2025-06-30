<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
$msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($msgkey);
$geekybot_js ="
jQuery(document).ready(function() {
    jQuery(document).on('click', '.geekybot-config-save-btn', function() {
        geekybotShowLoading();
    });
    jQuery(document).on('click', '#download-google-client', function() {
        const button = this;
        button.disabled = true;
        button.textContent = '".esc_html(__('Downloading...', 'geeky-bot'))."';
        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'geekybot',
            task: 'geekybotDownloadGoogleClientLibrary',
            '_wpnonce':'". esc_attr(wp_create_nonce("download_google_client")) ."'
        }, function(data) {
            if (data) {
                if (data.success) {
                    console.log(data.data.message);
                    jQuery('div#google-client-response').html(`
                      <div class='notice notice-success is-dismissible geekybot-config-success-msg'>
                        <p>".esc_html(__('Google Client Library downloaded successfully.', 'geeky-bot'))."</p>
                      </div>
                    `);
                } else {
                    jQuery('div#google-client-response').html(`
                      <div class='notice notice-error is-dismissible geekybot-config-error-msg'>
                        <p>".esc_html(__('Failed to download Google Client Library.', 'geeky-bot'))."</p>
                      </div>
                    `);
                }
            } else {
                jQuery('div#google-client-response').html(`
                  <div class='notice notice-error is-dismissible  geekybot-config-error-msg'>
                    <p>".esc_html(__('Failed to download Google Client Library.', 'geeky-bot'))."</p>
                  </div>
                `);
            }
            button.disabled = false;
            button.textContent = '".esc_html(__('Download Google Client Library', 'geeky-bot'))."';
        });
    });
});";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
$json_placeholder = '{
    "type": "service_account",
    "project_id": "dialogflow-XXXXXX",
    "private_key_id": "REDACTED_PRIVATE_KEY_ID",
    "private_key": "-----BEGIN PRIVATE KEY-----\\nREDACTED_PRIVATE_KEY_CONTENT\\n-----END PRIVATE KEY-----\\n",
    "client_email": "REDACTED_SERVICE_ACCOUNT_EMAIL",
    "client_id": "REDACTED_CLIENT_ID",
    "auth_uri": "https://accounts.google.com/o/oauth2/auth",
    "token_uri": "https://oauth2.googleapis.com/token",
    "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
    "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/REDACTED_SERVICE_ACCOUNT_EMAIL",
    "universe_domain": "googleapis.com"
  }';

?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'configuration','layouts' => 'dialogflow_configurations')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
           <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data">
            <div class="geekybot-tab-nav">
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=ai_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Provider', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=dialogflow_configurations','configuration'))?>" class="geekybot-tab-link active"><?php echo esc_html(__('Dialogflow', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=openai_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('OpenAI', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=openrouter_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('OpenRouter', 'geeky-bot')); ?></a>
            </div>
            <!-- top head -->
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('Dialogflow', 'geeky-bot')); ?>
                </h1>
            </div>
            <!-- page content -->
            <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper">
                <form id="geekybot-form" class="geekybot-configurations" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_configuration&task=saveconfiguration"),"save-configuration")); ?>">
                    <div class="geekybot-config-row-wrp geekybot-aiprovider-header">
                        <div class="geekybot-websearch-support-section geekybot-aisupport-section">
                            <div class="geekybot-websearch-support-imgwrp">
                                <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/doc-icon.png" title="<?php echo esc_attr(__('Help', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Help', 'geeky-bot')); ?>" class="geekybot-websearch-support-img">
                            </div>
                            <div class="geekybot-websearch-support-content-wrp">
                                <span class="geekybot-websearch-support-content-title"><?php echo esc_html(__('Dialogflow Guide', 'geeky-bot'));?></span>
                                <span class="geekybot-websearch-support-content-disc">
                                    <?php echo esc_html(__("Follow step-by-step instructions to connect and configure Dialogflow with your chatbot.", 'geeky-bot'));?>
                                </span>
                            </div>
                            <div class="geekybot-websearch-support-button-wrp">
                                <a href="https://docs.geekybot.com/docs/dialogflow-integration/" target="_blank" title="<?php echo esc_html(__('Check documentation', 'geeky-bot'));?>"><?php echo esc_html(__('Check Documentation', 'geeky-bot'));?></a>
                            </div>
                        </div>
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Project Id', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('geekybot_dialogflow_project_id', isset(geekybot::$_data[0]['geekybot_dialogflow_project_id']) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['geekybot_dialogflow_project_id']) : '', array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("The Project ID used to link your chatbot with your Dialogflow.", 'geeky-bot'));
                                ?>
                            </div>
                        </div>
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Service Account JSON', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value-text geekybot-config-aiprovider-textarea">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_textarea('geekybot_dialogflow_json', isset(geekybot::$_data[0]['geekybot_dialogflow_json']) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['geekybot_dialogflow_json']) : '', array('class' => 'inputbox js-textarea','placeholder' => geekybotphplib::GEEKYBOT_htmlspecialchars($json_placeholder), 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS) ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("The JSON credentials file used to authenticate with Dialogflow via a service account.", 'geeky-bot'));
                                ?>
                            </div>
                            <?php
                            $uploadDir = wp_upload_dir();
                            // Convert full path to relative: remove ABSPATH from basedir
                            $relativePath = str_replace(ABSPATH, '', $uploadDir['basedir']);
                            if (!file_exists($uploadDir['basedir'] . '/geekybotLibraries/dialogFlow/geekybot_google_client-main/autoload.php')) {
                                GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->geekybotPrepareManualDialogFlowLibraryPath(); ?>
                                <div class="geekybot-config-description geekybot-config-description-button">
                                    <h3 class="geekybot-config-auto-download-steps-heading">
                                        <?php echo esc_html(__("Auto Download Google Client Library", 'geeky-bot')); ?>
                                    </h3>
                                    <span id="download-google-client" class="geekybot-config-download">
                                        <?php echo esc_html(__("Auto Download Google Client Library", 'geeky-bot')); ?>
                                    </span>
                                    <div id="google-client-response"></div>
                                    <div class="geekybot-autod-download-seprater">
                                            <span class="geekybot-seprate-line"></span>
                                            <span class="geekybot-seprate-line-text"><?php echo esc_html(__("OR", 'geeky-bot')); ?></span>
                                            <span class="geekybot-seprate-line"></span>
                                    </div>
                                    <div class="geekybot-config-download-steps-wrp">
                                        <h3 class="geekybot-config-download-steps-heading">
                                            <?php echo esc_html(__("Manual Steps to Download Google Client Library", 'geeky-bot')); ?>
                                        </h3>
                                        <div class="geekybot-config-download-steps-text">
                                            <?php echo esc_html(__("Here are the manual steps to download and install the Google APIs Client Library in your WordPress.", 'geeky-bot')); ?>
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
                                                    <a href="https://github.com/geekybotai/geekybot_google_client/archive/main.zip"><?php echo esc_html("https://github.com/geekybotai/geekybot_google_client/archive/main.zip"); ?></a>
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
                                                    <?php echo esc_html(__("The extracted folder will likely be named:", 'geeky-bot')); ?>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="geekybot-config-download-steps-text">
                                                    <?php echo esc_html(__("geekybot_google_client-main", 'geeky-bot')); ?>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="geekybot-config-download-steps-text">
                                                    <?php echo esc_html(__("Inside this folder, you'll find another ZIP file:", 'geeky-bot')); ?>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="geekybot-config-download-steps-text">
                                                    <?php echo esc_html(__("geekybot_google_client-main.zip", 'geeky-bot')); ?>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="geekybot-config-download-steps-text">
                                                    <?php echo esc_html(__("Extract this inner ZIP file as well.", 'geeky-bot')); ?>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="geekybot-config-download-steps-text">
                                                    <?php echo esc_html(__("After extracting, it will create the actual", 'geeky-bot')).' "'. esc_html("geekybot_google_client-main").'" '.esc_html(__("folder you need to place inside your WordPress:", 'geeky-bot')); ?>
                                                </div>
                                            </li>
                                        </ul>
                                        <h4 class="geekybot-config-download-steps-subheading">
                                            <?php echo esc_html(__("Step 3: Upload to Your WordPress Plugin Directory", 'geeky-bot')); ?>
                                        </h4>
                                        <ul>
                                            <li>
                                                <div class="geekybot-config-download-steps-text">
                                                    <?php echo esc_html(__("Upload the final", 'geeky-bot')).' "'.esc_html(__("geekybot_google_client-main", 'geeky-bot')).'" '.esc_html(__("folder to your WordPress plugin:", 'geeky-bot')); ?>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="geekybot-config-download-steps-text">
                                                    <?php echo esc_html($relativePath."/geekybotLibraries/dialogFlow/"); ?>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <?php 
                            } ?>
                            <div class="geekybot-config-description geekybot-config-description-button geekybot-api-status">
                                <h3 class="geekybot-config-auto-download-steps-heading">
                                    <?php echo esc_html(__("Check Dialogflow Status", 'geeky-bot')); ?>
                                </h3>
                                <button id="geekybot-check-dialogflow-status" class="geekybot-table-act-btn geekybot-delete geekybot-config-download" href="#">
                                    <?php echo esc_html(__("Check Dialogflow Status", 'geeky-bot')); ?>
                                </button>
                                <div id="geekybot-dialogflow-status-result"></div>
                            </div>
                        </div>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('isgeneralbuttonsubmit', 1), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('geekybotlt', 'dialogflow_configurations'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'configuration_saveconfiguration'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <div class="geekybot-config-btn">
                        <button title="<?php echo esc_html(__('Save Settings', 'geeky-bot')); ?>" type="submit"class="button geekybot-config-save-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" alt="<?php echo esc_html(__('Add Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Save Dialogflow', 'geeky-bot')); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$geekybot_js = "
jQuery(document).ready(function() {
    jQuery(document).on('click', '#geekybot-check-dialogflow-status', function(e) {
        e.preventDefault(); // Prevent form submission/page reload
        jQuery('#geekybot-dialogflow-status-result').html(\"<span class='spinner is-active'></span> ". esc_html(__('Checking Dialogflow status...', 'geeky-bot')) ."\");
        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'geekybot',
            task: 'geekybotCheckDialogflowStatus',
            '_wpnonce': '". esc_attr(wp_create_nonce("geekybot_check_dialogflow_status")) ."'
        }, function(data) {
            if (data) {
                if (data.success) {
                    let statusClass = 'notice-' + data.data.status;
                    let html = `<div class='notice notice-success'>`+data.data.message+`<ul>`;
                    
                    if (data.data.data.project_id) {
                        html += `<li><strong>". esc_html(__('Project ID: ', 'geeky-bot')) ."</strong>`+data.data.data.project_id+`</li>`;
                    }
                    if (data.data.data.api_status) {
                        html += `<li><strong>". esc_html(__('API Status: ', 'geeky-bot')) ."</strong>`+data.data.data.api_status+`</li>`;
                    }
                    if (data.data.data.response_time) {
                        html += `<li><strong>". esc_html(__('Last Response: ', 'geeky-bot')) ."</strong>`+data.data.data.response_time+`</li>`;
                    }
                    
                    html += `</ul></div>`;
                    jQuery('#geekybot-dialogflow-status-result').html(html);
                } else {
                    let errorMsg = (data && data.data && data.data.message) ? data.data.message : '". esc_html(__('Unknown error occurred', 'geeky-bot')) ."';
                    jQuery('#geekybot-dialogflow-status-result').html(`<div class='notice notice-error'>`+errorMsg+`</div>`);
                }
            } else {
                jQuery('#geekybot-dialogflow-status-result').html(
                    `<div class='notice notice-error'>". esc_html(__('Request failed: ', 'geeky-bot')) ."'}</div>`
                );
            }
        });
    });
});
";
wp_add_inline_script('geekybot-main-js', $geekybot_js);
?>
