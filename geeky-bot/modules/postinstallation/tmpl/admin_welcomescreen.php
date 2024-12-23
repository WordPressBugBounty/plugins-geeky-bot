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
                        <li class="header-parts wellcome-part active">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=welcomescreen")); ?>" title="<?php echo esc_html(__('Welcome to GeekyBot Features', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="text active"><?php echo esc_html(__('Welcome to GeekyBot Features', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('GeekyBot Features', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('GeekyBot Features', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/welcome-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts first-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number">1</span>
                                <span class="text active"><?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/green-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts second-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=steptwo&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('WooCommerce Integration', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number geeky-header-parts-number-active">2</span>
                                <span class="text"><?php echo esc_html(__('WooCommerce Integration', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/woo-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts third-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepthree&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number geeky-header-parts-number-active">3</span>
                                <span class="text"><?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/vlue-arrow.png'; ?>" />
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="post-installtion-content_wrapper_right">
                    <div class="geekybot-admin-title-installtion">
                        <div class="geekybot-installation-bodysection">
                            <div class="geekybot-wellcomescreen-head-section">
                                <span class="geekybot-wellcomescreen-head-sectiontitle">
                                    <?php echo esc_html(__('Welcome to GeekyBot !...', 'geeky-bot')); ?>
                                </span>
                                <p class="geekybot-wellcomescreen-head-sectiondisc">
                                    <?php echo esc_html(__('The ultimate AI chatbot for WooCommerce lead generation, intelligent content search, and interactive customer engagement on your WordPress website.', 'geeky-bot')); ?>
                                </p>
                                <div class="geekybot-installation-next-previous-btnwrp geekybot-wellcome-screen-nextbtn-wrp">
                                    <a class="geekybot-installation-full-width-btn" href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone")); ?>" onclick="submitPostInstallatinForm(1);">
                                        <?php echo esc_html(__('Next', 'geeky-bot')); ?>
                                        <img class="geeky-installation-nextbtn-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/next-arrow.png'; ?>" />
                                        <img class="geeky-installation-nextbtn-image backbtn-black-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/blue-arrow.png'; ?>" />
                                    </a>
                                </div>
                            </div>
                            <div class="geekybot-wellcomescreen-body-section">
                                <div class="geekybot-wellcomescreen-body-section-aicard">
                                    <div class="geekybot-wellcomescreen-body-section-ailogowrp">
                                        <img class="geekybot-wellcomescreen-body-section-ailogo" title="<?php echo esc_html(__('AI Chat Bot', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI Chat Bot', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/aichat-icon.png'; ?>" />
                                    </div>
                                    <span class="geekybot-wellcomescreen-body-section-aititle">
                                        <?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>
                                    </span>
                                    <img class="geekybot-wellcomescreen-body-section-aiimg" title="<?php echo esc_html(__('AI Chat Bot', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI Chat Bot', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/aichat.png'; ?>" />
                                    <p class="geekybot-wellcomescreen-body-section-aidisc">
                                        <?php echo esc_html(__('Personalize interactions by dynamically processing user inputs using slots and variables.', 'geeky-bot')); ?>
                                    </p>
                                </div>
                                <div class="geekybot-wellcomescreen-body-section-woocard">
                                    <div class="geekybot-wellcomescreen-body-section-woologowrp">
                                        <img class="geekybot-wellcomescreen-body-section-woologo" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/woo-icon.png'; ?>" />
                                    </div>
                                    <span class="geekybot-wellcomescreen-body-section-wootitle">
                                        <?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>
                                    </span>
                                    <img class="geekybot-wellcomescreen-body-section-wooimg" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/woo-chat.png'; ?>" />
                                    <p class="geekybot-wellcomescreen-body-section-woodisc">
                                        <?php echo esc_html(__('Enhance shopping by enabling users to find products and add them to their cart seamlessly.', 'geeky-bot')); ?>
                                    </p>
                                </div>
                                <div class="geekybot-wellcomescreen-body-section-webcard">
                                    <div class="geekybot-wellcomescreen-body-section-weblogowrp">
                                        <img class="geekybot-wellcomescreen-body-section-weblogo" title="<?php echo esc_html(__('Website Search', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Website Search', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/web-searc-icon.png'; ?>" />
                                    </div>
                                    <span class="geekybot-wellcomescreen-body-section-webtitle">
                                        <?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>
                                    </span>
                                    <img class="geekybot-wellcomescreen-body-section-webimg" title="<?php echo esc_html(__('Website Search', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Website Search', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/websearch-chat.png'; ?>" />
                                    <p class="geekybot-wellcomescreen-body-section-webdisc">
                                        <?php echo esc_html(__('Streamline discovery by helping users find relevant information on your WordPress site.', 'geeky-bot')); ?>
                                    </p>
                                </div>
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
