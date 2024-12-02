<?php
if (!defined('ABSPATH')) die('Restricted Access');
wp_enqueue_style('geekybot-select2css', GEEKYBOT_PLUGIN_URL . 'includes/css/select2.min.css');
wp_enqueue_script('geekybot-select2js', GEEKYBOT_PLUGIN_URL . 'includes/js/select2.min.js');
$msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($msgkey);
?>
<div id="geekybot-spt-admin-wrapper">
    <div id="geekybotadmin_black_wrapper_built_loading"></div>
    <div class="geekybotadmin-built-story-loading" id="geekybotadmin_built_loading" style="display: none;" >
        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/spinning-wheel.gif" />
        <div class="geekybotadmin-built-story-loading-text">
            <?php echo esc_html(__('Please wait a moment; this may take some time.','geeky-bot')); ?>
        </div>
    </div>
    <div id="geekybot-spt-cparea">
        <div id="geeky-main-wrapper" class="post-installation">
            <div class="geeky-post-installtion-content-wrapper">
                <div class="post-installtion-content-header">
                    <div class="geeky-post-installtion-content-header_logo_img_section">
                        <img title="<?php echo esc_html(__('Logo', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/logo.png';?>" />
                    </div>
                    <ul class="update-header-img step-1">
                        <li class="header-parts first-part active">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number">1</span>
                                <span class="text active"><?php echo esc_html(__('AI ChatBot','geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/arrow.png';?>" />
                            </a>
                        </li>
                        <li class="header-parts second-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=steptwo&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('WooCommerce Integration', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number geeky-header-parts-number-active">2</span>
                                <span class="text"><?php echo esc_html(__('WooCommerce Integration','geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/arrow.png';?>" />
                            </a>
                        </li>
                        <li class="header-parts forth-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepthree&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number geeky-header-parts-number-active">3</span>
                                <span class="text"><?php echo esc_html(__('AI Web Search','geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/arrow.png';?>" />
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="post-installtion-content_wrapper_right">
                    <div class="geekybot-admin-title-installtion">
                        <div class="geekybot-admin-title-wrp">
                            <span class="geeky_heading"><?php echo esc_html(__('Quick Installation','geeky-bot')); ?></span>
                            <span class="geeky_heading-right-section">
                                <?php echo esc_html(__('Version: 1.0.0','geeky-bot')); ?>
                            </span>
                        </div>
                        <div class="geekybot-installation-bodysection">
                            <div class="geekybot-installation-bodyleft-section">
                                <div class="geekybot-installation-bodyhead-section">
                                    <form id="geekybot-form-ins" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_postinstallation&task=save&action=mstask"),"save")); ?>">
                                    <span class="geeky_heading"><?php echo esc_html(__('AI ChatBot','geeky-bot')); ?></span>
                                        <?php
                                        if (isset(geekybot::$_data['geekybot_callfrom']) && geekybot::$_data['geekybot_callfrom'] == 'backlink') {
                                            if (isset(geekybot::$_data['storyAlreadyBuild']) && geekybot::$_data['storyAlreadyBuild'] == 1) {
                                                $checked = 'checked="checked"';
                                            } else {
                                                $checked = '';
                                            }
                                        } else {
                                            $checked = 'checked="checked"';
                                        }
                                        ?>
                                        <label class="switch" title="<?php echo esc_html(__('Enable/Disable','geeky-bot')); ?>">
                                            <input id="ai_story" name="ai_story" class="geeky_installation-aiinput-checkbox" type="checkbox" value="1" <?php echo esc_html($checked); ?>>
                                            <span class="geeky_installation-aiinput-slider"></span>
                                        </label>
                                        <div class="geekybot-installation-row">
                                            <div class="geekybot-installation-title">
                                                <?php echo esc_html(__('Select Template', 'geeky-bot')); ?>
                                            </div>
                                            <div class="geekybot-installation-value">
                                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('template', GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getTemplatesForCombobox(), isset($action->action_id) ? $action->action_id : '', esc_html(__('Select Template','geeky-bot')), array('class' => 'inputbox geekybot-form-select-field ', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                                            </div>
                                        </div>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'postinstallation_save'), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('step', 1), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('story_type', 1), GEEKYBOT_ALLOWED_TAGS); ?>
                                    </form>
                                </div>
                                <p class="geekybot-installation-bodydisc">
                                    <?php echo esc_html(__("AI Chat in GeekyBot delivers instant, personalized responses to user queries, ensuring 24/7 support and reliability. Train GeekyBot by inputting your specific data for personalized, dynamic interactions.",'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-subtitle">
                                    <?php echo esc_html(__("Edit Your Bot's AI ChatBot Story",'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-subtext">
                                    <?php echo esc_html(__("You can add or edit your AI ChatBot story later from the story listing page.",'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-image">
                                    <img class="geeky-installation-story-image" title="<?php echo esc_html(__('Story', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/ai-story.png';?>" />
                                </p>
                                <a class="geekybot-installation-addstory-turotial"alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                                    <img class="geeky-installation-story-image" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/video.png';?>" />
                                    <?php echo esc_html(__("How To Edit Story",'geeky-bot')); ?>
                                </a>
                            </div>
                            <div class="geekybot-installation-bodyright-section">
                                <img class="geeky-installation-Botimage" title="<?php echo esc_html(__('Bot Image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/ai-bot.gif';?>" />
                            </div>
                            <div class="geekybot-installation-next-previous-btnwrp">
                                <a class="geekybot-installation-full-width-btn" href="#" onclick="submitPostInstallatinForm(1);">
                                    <?php echo esc_html(__('Next', 'geeky-bot')); ?>
                                    <img class="geeky-installation-nextbtn-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/next-arrow.png';?>" />
                                    <img class="geeky-installation-nextbtn-image backbtn-black-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/black-arrow.png';?>" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$geekybot_js ="
jQuery(document).ready(function() {
    jQuery('#template').select2({
       dropdownParent: jQuery('.geekybot-installation-value')
    });
});
function validateSelect2Field() {
    var isValid = true;
    var templateValue = jQuery('#template').val();
    if (!templateValue) {
        // Add error class to indicate an error
        if (jQuery('#ai_story').is(':checked')) {
            // Checkbox is checked
            jQuery('#template').next('.select2').addClass('select2-error');
            isValid = false;
        } else if (jQuery('#myCheckbox').prop('checked')) {
            jQuery('#template').next('.select2').addClass('select2-error');
            isValid = false;
        } else {
            // Remove error class if built option is disabled
            jQuery('#template').next('.select2').removeClass('select2-error');    
        }
    } else {
        // Remove error class if valid
        jQuery('#template').next('.select2').removeClass('select2-error');
    }

    return isValid;
}

";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
