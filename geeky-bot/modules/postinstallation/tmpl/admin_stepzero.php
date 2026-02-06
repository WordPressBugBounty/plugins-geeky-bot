<?php
if (!defined('ABSPATH')) die('Restricted Access');
wp_enqueue_style('geekybot-select2css', GEEKYBOT_PLUGIN_URL . 'includes/css/select2.min.css');
wp_enqueue_script('geekybot-select2js', GEEKYBOT_PLUGIN_URL . 'includes/js/select2.min.js');
$msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('postinstallation')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($msgkey);
?>
<div id="geekybot-spt-admin-wrapper">
    <div id="geekybotadmin_black_wrapper_built_loading"></div>
    <div class="geekybotadmin-built-story-loading" id="geekybotadmin_built_loading" style="display: none;">
        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/spinning-wheel.gif" />
        <div class="geekybotadmin-built-story-loading-text">
            <?php echo esc_html(__('Please wait a moment; this may take some time.', 'geeky-bot')); ?>
        </div>
    </div>
    <div id="geekybot-spt-cparea">
        <div id="geeky-main-wrapper" class="post-installation">
            <div class="geeky-post-installtion-content-wrapper">
                <div class="post-installtion-content-header">
                    <div class="geeky-post-installtion-content-header_logo_img_section">
                        <img title="<?php echo esc_html(__('GeekyBot', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('GeekyBot', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/logo.png'; ?>" />
                    </div>
                    <ul class="update-header-img step-1">
                        <li class="header-parts wellcome-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=welcomescreen")); ?>" title="<?php echo esc_html(__('Welcome to GeekyBot Features', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="text active"><?php echo esc_html(__('Welcome to GeekyBot Features', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('GeekyBot Features', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('GeekyBot Features', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/welcome-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts third-part active">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepzero&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number">1</span>
                                <span class="text active"><?php echo esc_html(__('Generate Content', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/vlue-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts first-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number">2</span>
                                <span class="text"><?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/green-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts second-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=steptwo&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('WooCommerce Integration', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number geeky-header-parts-number-active">3</span>
                                <span class="text"><?php echo esc_html(__('WooCommerce Integration', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/woo-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts third-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepthree&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number geeky-header-parts-number-active">4</span>
                                <span class="text"><?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/vlue-arrow.png'; ?>" />
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="post-installtion-content_wrapper_right">
                    <div class="geekybot-admin-title-installtion">
                        <div class="geekybot-admin-title-wrp">
                            <span class="geeky_heading">
                                <?php echo esc_html(__('Quick Installation', 'geeky-bot')); ?>
                            </span>
                            <span class="geeky_heading-right-section">
                                <?php echo esc_html(__('Version' , 'geeky-bot')).': '; ?><?php echo esc_html(GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('versioncode')); ?>
                            </span>
                        </div>
                        <div class="geekybot-installation-bodysection geekybot-installation-aibot-bodysection">
                            <div class="geekybot-installation-bodyleft-section">
                                <div class="geekybot-installation-bodyhead-section">
                                        <div class="geekybot-installation-pge-logowrp">
                                            <img class="geekybot-installation-pge-logo" title="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/ai-icon.png'; ?>" />
                                        </div>
                                        <span class="geeky_heading">
                                            <?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>
                                        </span>
                                        
                                        
                                        

                                </div>
                                <p class="geekybot-installation-bodydisc">
                                    <?php echo esc_html(__("Generate expert-level content with ease, even without technical knowledge. The advanced prompt engineering is seamlessly handled in the background, letting you rely on smart defaults to produce high-quality, ready-to-use content in just seconds.", 'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-subtext">
                                    <?php echo esc_html(__("With access to some of the most advanced AI models available, the tool produces content that’s fast, polished, and highly relevant—ensuring you maintain a strong professional presence.", 'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-subtext">
                                    <?php echo esc_html(__("Generate professionally written content in 70+ languages instantly. With fluent multilingual support, you can expand your content output and connect with global markets more easily than ever.", 'geeky-bot')); ?>
                                </p>
                            </div>
                            <div class="geekybot-installation-bodyright-section">
                                <img class="geeky-installation-Botimage" title="<?php echo esc_html(__('Bot Image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/generate_content.png'; ?>" />
                                        <?php /*
                                <div class="geeky-installation-plybtnwrp">
                                    <a target="_blank" href="https://www.youtube.com/watch?v=kbaA_l5j_9w" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                                        <img class="geeky-installation-plyimg" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/play-icon.png'; ?>" />
                                        <?php echo esc_html(__('Watch AI ChatBot Video', 'geeky-bot')); ?>
                                    </a>
                                </div>
                                        */ ?>
                            </div>
                            <div class="geekybot-installation-next-previous-btnwrp geekybot-wellcome-screen-nextbtn-wrp">
                                    <a class="geekybot-installation-full-width-btn" href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone")); ?>" >
                                        <?php echo esc_html(__('Next', 'geeky-bot')); ?>
                                        <img class="geeky-installation-nextbtn-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/next-arrow.png'; ?>" />
                                        <img class="geeky-installation-nextbtn-image backbtn-black-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/blue-arrow.png'; ?>" />
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
$geekybot_js = "
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
wp_add_inline_script('geekybot-main-js', $geekybot_js);
?>
