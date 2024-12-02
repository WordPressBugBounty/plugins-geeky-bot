<?php if (!defined('ABSPATH')) die('Restricted Access'); ?>
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
                        <li class="header-parts first-part">
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
                        <li class="header-parts third-part active">
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
                            <span class="geeky_heading-right-section"><?php echo esc_html(__('Version: 1.0.0','geeky-bot')); ?></span>
                        </div>
                        <div class="geekybot-installation-bodysection">
                            <div class="geekybot-installation-bodyleft-section">
                                <form id="geekybot-form-ins" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_postinstallation&task=save&action=mstask"),"save")); ?>">
                                    <div class="geekybot-installation-bodyhead-section">
                                        <span class="geeky_heading"><?php echo esc_html(__('AI Web Search','geeky-bot')); ?></span>
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
                                            <input id="enable_web_serch" name="enable_web_serch" class="geeky_installation-aiinput-checkbox" type="checkbox" value="1" <?php echo esc_html($checked); ?> >
                                            <span class="geeky_installation-aiinput-slider"></span>
                                        </label>
                                    </div>
                                    <p class="geekybot-installation-bodydisc">
                                        <?php echo esc_html(__("Effortlessly search specific posts across your site using our powerful AI search tool. When you search for content, GeekyBot also shows relevant articles and posts, helping you find what you need quickly and efficiently.",'geeky-bot')); ?>
                                    </p>
                                    <p class="geekybot-installation-subtitle">
                                        <?php echo esc_html(__("Enable or Disable AI Web Search",'geeky-bot')); ?>
                                    </p>
                                    <p class="geekybot-installation-subtext">
                                        <?php echo esc_html(__("You can enable or disable AI Web Search from the story listing page as shown below.",'geeky-bot')); ?>
                                    </p>
                                    <p class="geekybot-installation-image geekybot-websearch-image">
                                        <img class="geeky-installation-story-image" title="<?php echo esc_html(__('Story', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/post-search.png';?>" />
                                    </p>
                                    <div class="geekybot-installation-bodyhead-section ">
                                        <span class="geeky_heading"><?php echo esc_html(__('New Post Type Search','geeky-bot')); ?></span>
                                        <?php
                                        if (isset(geekybot::$_data['geekybot_callfrom']) && geekybot::$_data['geekybot_callfrom'] == 'backlink') {
                                            if ( geekybot::$_configuration['is_new_post_type_enable'] == 1 ) {
                                                $checked = 'checked="checked"';
                                            } else {
                                                $checked = '';
                                            }
                                        } else {
                                            $checked = 'checked="checked"';
                                        }
                                        ?>
                                        <label class="switch" title="<?php echo esc_html(__('Enable/Disable','geeky-bot')); ?>">
                                            <input id="enable_new_post_type_serch" name="enable_new_post_type_serch" class="geeky_installation-aiinput-checkbox" type="checkbox" value="1" <?php echo esc_html($checked); ?> >
                                            <span class="geeky_installation-aiinput-slider"></span>
                                        </label>
                                    </div>
                                    <p class="geekybot-installation-bodydisc">
                                        <?php echo esc_html(__("Enable this option to directly include new post types in search results, enhancing user access to all content.",'geeky-bot')); ?>
                                    </p>
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'postinstallation_save'), GEEKYBOT_ALLOWED_TAGS); ?>
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('step', 3), GEEKYBOT_ALLOWED_TAGS); ?>
                                </form>
                                <a class="geekybot-installation-addstory-turotial"alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                                    <img class="geeky-installation-story-image" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/video.png';?>" />
                                    <?php echo esc_html(__("How To Manage Website Search",'geeky-bot')); ?>
                                </a>
                            </div>
                            <div class="geekybot-installation-bodyright-section">
                                <img class="geeky-installation-Botimage" title="<?php echo esc_html(__('Bot Image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/post-bot.gif';?>" />
                            </div>
                            <div class="geekybot-installation-next-previous-btnwrp">
                                <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=steptwo&geekybot_callfrom=backlink")); ?>" class="geekybot-installation-prevbtn">
                                    <img class="geeky-installation-backbtn-image" title="<?php echo esc_html(__('Back', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/back-arrow.png';?>" />
                                    <img class="geeky-installation-backbtn-image backbtn-black-image" title="<?php echo esc_html(__('Back', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/black-arrow.png';?>" />
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
