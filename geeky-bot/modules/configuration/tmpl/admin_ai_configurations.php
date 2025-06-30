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
});";
wp_add_inline_script('geekybot-main-js',$geekybot_js);

$aiproviderlist = array(
    (object) array('id' => '1', 'text' => 'GeekyBot Chat'),
    (object) array('id' => '2', 'text' => 'Dialogflow'),
    (object) array('id' => '3', 'text' => 'OpenAI'),
    (object) array('id' => '4', 'text' => 'OpenRouter')
);


?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'configuration','layouts' => 'ai_configurations')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
           <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data">
            <div class="geekybot-tab-nav">
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=ai_configurations','configuration'))?>" class="geekybot-tab-link active"><?php echo esc_html(__('AI Provider', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=dialogflow_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('Dialogflow', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=openai_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('OpenAI', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=openrouter_configurations','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('OpenRouter', 'geeky-bot')); ?></a>
            </div>
            <!-- top head -->
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('AI Provider', 'geeky-bot')); ?>
                </h1>
            </div>
            <!-- page content -->
            <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper">
                <form id="geekybot-form" class="geekybot-configurations" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_configuration&task=saveconfiguration"),"save-configuration")); ?>">
                    <div class="geekybot-config-row-wrp geekybot-aiprovider-header">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('AI Provider', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('ai_provider', $aiproviderlist, isset(geekybot::$_data[0]['ai_provider']) ? geekybot::$_data[0]['ai_provider'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("The AI service used to generate chatbot responses.", 'geeky-bot'));
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('isgeneralbuttonsubmit', 1), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('geekybotlt', 'ai_configurations'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'configuration_saveconfiguration'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <div class="geekybot-config-btn">
                        <button title="<?php echo esc_html(__('Save Settings', 'geeky-bot')); ?>" type="submit"class="button geekybot-config-save-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" alt="<?php echo esc_html(__('Add Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Save AI Provider', 'geeky-bot')); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
