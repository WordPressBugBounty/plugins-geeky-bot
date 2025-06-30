<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
wp_enqueue_style('geekybot-select2css', GEEKYBOT_PLUGIN_URL . 'includes/css/select2.min.css');
wp_enqueue_script('geekybot-select2js', GEEKYBOT_PLUGIN_URL . 'includes/js/select2.min.js');
$msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($msgkey);

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
$geekybot_js ="
jQuery(document).ready(function() {
    jQuery('#geekybot_openrouter_model').select2({
        dropdownParent: jQuery('.geekybot-config-model-value')
    });
});";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'configuration','layouts' => 'openrouter_configurations')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
           <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data">
            <div class="geekybot-tab-nav">
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=ai_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Provider', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=dialogflow_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('Dialogflow', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=openai_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('OpenAI', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=openrouter_configurations','configuration'))?>" class="geekybot-tab-link active"><?php echo esc_html(__('OpenRouter', 'geeky-bot')); ?></a>
            </div>
            <!-- top head -->
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('OpenRouter', 'geeky-bot')); ?>
                </h1>
            </div>
            <!-- page content -->
            <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper">
                <form id="geekybot-form" class="geekybot-configurations" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_configuration&task=saveconfiguration"),"save-configuration")); ?>">
                    <div class="geekybot-config-row-wrp geekybot-aiprovider-header">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('OpenRouter API key', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('geekybot_openrouter_api_key', isset(geekybot::$_data[0]['geekybot_openrouter_api_key']) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['geekybot_openrouter_api_key']) : '', array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("The API key used to connect your chatbot to OpenRouter’s model API services.", 'geeky-bot'));
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
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('geekybot_openrouter_model', GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getOpenRouterModelList(), isset(geekybot::$_data[0]['geekybot_openrouter_model']) ? geekybot::$_data[0]['geekybot_openrouter_model'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                                </div>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("Choose which AI model to use for generating responses. Different models vary in speed, cost, and capabilities.", 'geeky-bot'));
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
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('geekybot_openrouter_temperature', $temperaturelist, isset(geekybot::$_data[0]['geekybot_openrouter_temperature']) ? geekybot::$_data[0]['geekybot_openrouter_temperature'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
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
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('geekybot_openrouter_max_tokens', isset(geekybot::$_data[0]['geekybot_openrouter_max_tokens']) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['geekybot_openrouter_max_tokens']) : '', array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("Controls the creativity of the assistant’s responses. Lower values make replies more focused, higher values make them more diverse.", 'geeky-bot'));
                                ?>
                            </div>
                            <div class="geekybot-config-description geekybot-config-description-button geekybot-api-status">
                                <h3 class="geekybot-config-auto-download-steps-heading">
                                    <?php echo esc_html(__("Check OpenRouter Status", 'geeky-bot')); ?>
                                </h3>
                                <button id="geekybot-check-status" class="geekybot-table-act-btn geekybot-delete geekybot-config-download" href="#">
                                    <?php echo esc_html(__("Check OpenRouter Status", 'geeky-bot')); ?>
                                </button>
                                <div id="geekybot-status-result"></div>
                            </div>
                        </div>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('isgeneralbuttonsubmit', 1), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('geekybotlt', 'openrouter_configurations'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'configuration_saveconfiguration'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <div class="geekybot-config-btn">
                        <button title="<?php echo esc_html(__('Save Settings', 'geeky-bot')); ?>" type="submit"class="button geekybot-config-save-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" alt="<?php echo esc_html(__('Add Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Save OpenRouter', 'geeky-bot')); ?>
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
    jQuery(document).on('click', '#geekybot-check-status', function(e) {
        e.preventDefault(); // Prevent form submission/page reload
        jQuery('#geekybot-status-result').html(\"<span class='spinner is-active'></span> ". esc_html(__('Checking API status...', 'geeky-bot')) ."\");
        var ajaxurl = '". esc_url(admin_url("admin-ajax.php")) ."';
        jQuery.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'geekybot',
            task: 'geekybotCheckOpenRouterStatus',
            '_wpnonce': '". esc_attr(wp_create_nonce("geekybot_check_openrouter_status")) ."'
        }, function(data) {
            if (data) {
                if (data.success) {
                    if (data.data) {
                        let statusClass = 'notice-' + data.data.status;
                        let html = `<div class='notice notice-success'>`;
                        html += data.data.message;
                    
                        html += `<ul>`;
                        if (data.data.data.label){
                            html += `<li><strong>". esc_html(__('Key Label: ', 'geeky-bot')) ."</strong>`;
                            html += data.data.data.label;
                            html += `</li>`;
                        }
                        if (data.data.data.usage){
                            html += `<li><strong>". esc_html(__('Usage: ', 'geeky-bot')) ."></strong>`;
                            html += data.data.data.usage;
                            html += `</li>`;
                        }
                        if (data.data.data.rate_limit.interval){
                            html += `<li><strong>". esc_html(__('Interval: ', 'geeky-bot')) ."</strong>`;
                            html += data.data.data.rate_limit.interval;
                            html += `</li>`;
                        }
                        html += `</ul>`;
                        html += `</div>`;
                        jQuery('#geekybot-status-result').html(html);
                    }  
                } else {
                    if (data.data) {
                        let statusClass = 'notice-' + data.data.status;
                        let html = `<div class='notice notice-error'>`;
                        html += data.data.message;
                        html += `</div>`;
                        jQuery('#geekybot-status-result').html(html);
                    }
                }
            } else {
                let html = `<div class='notice notice-error'>`;
                html += '".esc_html(__('Failed to check status: ', 'geeky-bot'))."'
                html += `</div>`;
                jQuery('#geekybot-status-result').html(html);
            }
        });
    });
});
";
wp_add_inline_script('geekybot-main-js', $geekybot_js);
?>