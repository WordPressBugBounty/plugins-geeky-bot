<?php if (!defined('ABSPATH')) die('Restricted Access'); ?>
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
                <div class="post-installtion-content-header geeky-second-page-post-installtion-content-header">
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
                        <li class="header-parts third-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepzero&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number">1</span>
                                <span class="text active"><?php echo esc_html(__('Generate Content', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/vlue-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts first-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number geeky-header-parts-number-active">2</span>
                                <span class="text"><?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/green-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts second-part active">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=steptwo&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('WooCommerce Integration', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number geeky-header-parts-number-active">3</span>
                                <span class="text active"><?php echo esc_html(__('WooCommerce Integration', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/woo-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts third-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepthree&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number ">4</span>
                                <span class="text active"><?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/vlue-arrow.png'; ?>" />
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="post-installtion-content_wrapper_right">
                    <div class="geekybot-admin-title-installtion">
                        <div class="geekybot-admin-title-wrp">
                            <span class="geeky_heading"><?php echo esc_html(__('Quick Installation', 'geeky-bot')); ?></span>
                            <span class="geeky_heading-right-section">
                                <?php echo esc_html(__('Version' , 'geeky-bot')).': '; ?><?php echo esc_html(GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('versioncode')); ?>
                            </span>
                        </div>
                        <div class="geekybot-installation-bodysection geekybot-installation-wcbot-bodysection">
                            <div class="geekybot-installation-bodyleft-section geekybot-installation-scndbodyleft-section">
                                <div class="geekybot-installation-bodyhead-section">
                                    <div class="geekybot-installation-pge-wclogowrp">
                                        <img class="geekybot-installation-pge-logo" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/wo-logo.png'; ?>" />
                                    </div>
                                    <span class="geeky_heading">
                                        <?php echo esc_html(__('WooCommerce Bot', 'geeky-bot')); ?>
                                    </span>
                                    <?php
                                    if (!class_exists('WooCommerce')) { ?>
                                        <div class="geekybot-installation-scndpage-info-section">
                                            <div class="geekybot-installation-infoimage">
                                                <img title="<?php echo esc_html(__('Info', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Info', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/redinfo.png'; ?>" />
                                            </div>
                                            <div class="geekybot-installation-inforight-wrp">
                                                <p class="geekybot-installation-info-title"><?php echo esc_html(__('WooCommerce is not installed', 'geeky-bot')); ?></p>
                                                <p class="geekybot-installation-info-dis"><?php echo esc_html(__("WooCommerce plugin is not installed on your site, you won't be able to integrate GeekyBot with WooCommerce.", 'geeky-bot')); ?></p>
                                            </div>
                                        </div>
                                    <?php
                                    }  ?>
                                    <form id="geekybot-form-ins" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_postinstallation&task=save&action=geekybottask"), "save")); ?>">
                                        <?php
                                        if (class_exists('WooCommerce')) {
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
                                            <div class="geekybot-installation-enable-sectwrp">
                                                <label class="switch" title="<?php echo esc_html(__('Enable/Disable', 'geeky-bot')); ?>">
                                                    <input id="woo_story" name="woo_story" class="geeky_installation-aiinput-checkbox" type="checkbox" value="1" <?php echo esc_html($checked); ?>>
                                                    <span class="geeky_installation-aiinput-slider geeky-wcai-slider"></span>
                                                </label>
                                                <span class="geekybot-installation-enable-secttitle">
                                                    <?php echo esc_html(__('Enable/Disable Bot', 'geeky-bot')); ?>
                                                </span>
                                            </div>
                                        <?php
                                        }  ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'postinstallation_save'), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('step', 2), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('story_type', 2), GEEKYBOT_ALLOWED_TAGS); ?>
                                    </form>
                                </div>
                                <p class="geekybot-installation-bodydisc">
                                    <?php echo esc_html(__("Easily integrate AI with WooCommerce to enhance your online store’s performance. GeekyBot boosts lead generation and customer interactions, automating key processes for a more effective and engaging shopping experience.", 'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-subtext">
                                    <?php echo esc_html(__("GeekyBot also streamlines customer inquiries and provides real-time, intelligent responses. By improving retention and customer satisfaction, it ensures your store delivers an exceptional experience that keeps shoppers coming back.", 'geeky-bot')); ?>
                                </p>
                            </div>
                            <div class="geekybot-installation-bodyright-section">
                                <img class="geeky-installation-Botimage" title="<?php echo esc_html(__('Bot Image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/woo-image.png'; ?>" />
                                <div class="geeky-installation-plybtnwrp geeky-installation-wcplybtn">
                                    <a href="#" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                                        <img class="geeky-installation-plyimage" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/play-icon.png'; ?>" />
                                        <?php echo esc_html(__('Watch WooCommerce Video', 'geeky-bot')); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="geekybot-installation-next-previous-btnwrp">
                                <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=backlink")); ?>" class="geekybot-installation-prevbtn ">
                                    <img class="geeky-installation-backbtn-image" title="<?php echo esc_html(__('Back', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/back-arrow.png'; ?>" />
                                    <img class="geeky-installation-backbtn-image backbtn-black-image" title="<?php echo esc_html(__('Back', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/black-arrow.png'; ?>" />
                                    <?php echo esc_html(__('Back', 'geeky-bot')); ?>
                                </a>
                                <a class="geekybot-installation-nextbtn" href="#" onclick="submitPostInstallatinForm(2);">
                                    <?php echo esc_html(__('Next', 'geeky-bot')); ?>
                                    <img class="geeky-installation-nextbtn-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/next-arrow.png'; ?>" />
                                    <img class="geeky-installation-nextbtn-image backbtn-blue-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/blue-arrow.png'; ?>" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
