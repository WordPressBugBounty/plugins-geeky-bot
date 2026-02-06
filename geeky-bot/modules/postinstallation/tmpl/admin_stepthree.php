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
                        <li class="header-parts third-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepzero&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number">1</span>
                                <span class="text active"><?php echo esc_html(__('Generate Content', 'geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/vlue-arrow.png'; ?>" />
                            </a>
                        </li>
                        <li class="header-parts first-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" class="tab_icon">
                                <span class="header-parts-number">2</span>
                                <span class="text active"><?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?></span>
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
                        <li class="header-parts third-part active">
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
                            <span class="geeky_heading"><?php echo esc_html(__('Quick Installation', 'geeky-bot')); ?></span>
                            <span class="geeky_heading-right-section">
                                <?php echo esc_html(__('Version' , 'geeky-bot')).': '; ?><?php echo esc_html(GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('versioncode')); ?>
                            </span>
                        </div>
                        <div class="geekybot-installation-bodysection geekybot-installation-webbot-bodysection">
                            <div class="geekybot-installation-bodyleft-section">
                                <form id="geekybot-form-ins" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_postinstallation&task=save&action=geekybottask"), "save")); ?>">
                                    <div class="geekybot-installation-bodyhead-section">
                                        <div class="geekybot-installation-pge-logowrp">
                                            <img class="geekybot-installation-pge-logo" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/web-icon.png'; ?>" />
                                        </div>
                                        <span class="geeky_heading">
                                            <?php echo esc_html(__('AI Web Search Bot', 'geeky-bot')); ?>
                                        </span>
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
                                        <div class="geekybot-installation-enable-sectwrp">
                                            <label class="switch" title="<?php echo esc_html(__('Enable/Disable', 'geeky-bot')); ?>">
                                                <input id="enable_web_serch" name="enable_web_serch" class="geeky_installation-aiinput-checkbox" type="checkbox" value="1" <?php echo esc_html($checked); ?>>
                                                <span class="geeky_installation-aiinput-slider geeky-webai-slider"></span>
                                            </label>
                                            <span class="geekybot-installation-enable-secttitle">
                                                <?php echo esc_html(__('Enable/Disable Bot', 'geeky-bot')); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="geekybot-installation-bodydisc">
                                        <?php echo esc_html(__("Effortlessly search for specific posts and content across your entire site using our powerful AI web search tool. GeekyBot goes beyond standard searches, delivering results from all types of data on your site. Whether you're looking for posts, pages, custom post types, or other information, GeekyBot ensures you find what you need quickly and efficiently by displaying relevant articles, posts, and more in a single, streamlined search experience.", 'geeky-bot')); ?>
                                    </p>
                                    <div class="geekybot-installation-bodyhead-section ">
                                        <span class="geekybot-installation-websb-title"><?php echo esc_html(__('New Post Type Search', 'geeky-bot')); ?></span>
                                        <?php
                                        if (isset(geekybot::$_data['geekybot_callfrom']) && geekybot::$_data['geekybot_callfrom'] == 'backlink') {
                                            if (geekybot::$_configuration['is_new_post_type_enable'] == 1) {
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
                                                <input id="enable_new_post_type_serch" name="enable_new_post_type_serch" class="geeky_installation-aiinput-checkbox" type="checkbox" value="1" <?php echo esc_html($checked); ?>>
                                                <span class="geeky_installation-aiinput-slider geeky-webai-slider"></span>
                                            </label>
                                            <span class="geekybot-installation-enable-secttitle">
                                                <?php echo esc_html(__('Enable/Disable Bot', 'geeky-bot')); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="geekybot-installation-bodydisc">
                                        <?php echo esc_html(__("Enable this option to directly include new post types in search results, enhancing user access to all content.", 'geeky-bot')); ?>
                                    </p>
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'postinstallation_save'), GEEKYBOT_ALLOWED_TAGS); ?>
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('step', 3), GEEKYBOT_ALLOWED_TAGS); ?>
                                </form>
                            </div>
                            <div class="geekybot-installation-bodyright-section">
                                <img class="geeky-installation-Botimage" title="<?php echo esc_html(__('Bot Image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/web-image.png'; ?>" />
                                <div class="geeky-installation-plybtnwrp geeky-installation-websrch-plybtn">
                                    <a target="_blank" href="https://www.youtube.com/watch?v=Z3g4fRpoZlc" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                                        <img class="geeky-installation-plyimg" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/play-icon.png'; ?>" />
                                        <?php echo esc_html(__('Watch AI Web Search Video', 'geeky-bot')); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="geekybot-installation-next-previous-btnwrp">
                                <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=steptwo&geekybot_callfrom=backlink")); ?>" class="geekybot-installation-prevbtn">
                                    <img class="geeky-installation-backbtn-image" title="<?php echo esc_html(__('Back', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/back-arrow.png'; ?>" />
                                    <img class="geeky-installation-backbtn-image backbtn-black-image" title="<?php echo esc_html(__('Back', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/postinstallation/black-arrow.png'; ?>" />
                                    <?php echo esc_html(__('Back', 'geeky-bot')); ?>
                                </a>
                                <a class="geekybot-installation-nextbtn installation-finishbtn" href="#" onclick="submitPostInstallatinForm(3);">
                                    <?php echo esc_html(__('Finish', 'geeky-bot')); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
