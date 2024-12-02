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
                <div class="post-installtion-content-header geeky-second-page-post-installtion-content-header">
            	    <div class="geeky-post-installtion-content-header_logo_img_section">
	    	            <img title="<?php echo esc_html(__('Logo', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/logo.png';?>" />
	                </div>
                    <ul class="update-header-img step-1">
                        <li class="header-parts first-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI ChatBot', 'geeky-bot')); ?>" class="tab_icon">
                            <span class="header-parts-number geeky-header-parts-number-active">1</span>
                                <span class="text"><?php echo esc_html(__('AI ChatBot','geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/arrow.png';?>" />
                            </a>
                        </li>
                        <li class="header-parts second-part active">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=steptwo&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('WooCommerce Integration', 'geeky-bot')); ?>" class="tab_icon">
                            <span class="header-parts-number geeky-header-parts-number-active">2</span>
                                <span class="text active"><?php echo esc_html(__('WooCommerce Integration','geeky-bot')); ?></span>
                                <img class="geeky-installation-link-arrow" title="<?php echo esc_html(__('image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/arrow.png';?>" />
                            </a>
                        </li>
                        <li class="header-parts second-part">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepthree&geekybot_callfrom=backlink")); ?>" title="<?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>" class="tab_icon">
                            <span class="header-parts-number ">3</span>
                                <span class="text active"><?php echo esc_html(__('AI Web Search','geeky-bot')); ?></span>
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
                            <div class="geekybot-installation-bodyleft-section geekybot-installation-scndbodyleft-section">
                                <?php 
                                if (!class_exists('WooCommerce')) { ?>
                                    <div class="geekybot-installation-scndpage-info-section">
                                        <div class="geekybot-installation-infoimage">
                    	    	            <img title="<?php echo esc_html(__('Info', 'geeky-bot')); ?>"alt="<?php echo esc_html(__('Info', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/info.png';?>" />
                                        </div>
                                        <div class="geekybot-installation-inforight-wrp">
                                            <p class="geekybot-installation-info-title"><?php echo esc_html(__('WooCommere is not installed', 'geeky-bot')); ?></p>
                                            <p class="geekybot-installation-info-dis"><?php echo esc_html(__("WooCommerce plugin is not installed on your site, you won't be able to integrate GeekyBot with WooCommerce.", 'geeky-bot')); ?></p>
                                        </div>
                                    </div>
                                    <?php
                                }  ?>
                                <div class="geekybot-installation-bodyhead-section">
                                    <span class="geeky_heading"><?php echo esc_html(__('WooCommerce Integration','geeky-bot')); ?></span>
                                    <form id="geekybot-form-ins" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_postinstallation&task=save&action=mstask"),"save")); ?>">
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
                                            <label class="switch" title="<?php echo esc_html(__('Enable/Disable','geeky-bot')); ?>">
                                                <input id="woo_story" name="woo_story" class="geeky_installation-aiinput-checkbox" type="checkbox" value="1" <?php echo esc_html($checked); ?> >
                                                <span class="geeky_installation-aiinput-slider"></span>
                                            </label>
                                            <?php
                                        }  ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'postinstallation_save'), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('step', 2), GEEKYBOT_ALLOWED_TAGS); ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('story_type', 2), GEEKYBOT_ALLOWED_TAGS); ?>
                                    </form>
                                </div>
                                <p class="geekybot-installation-bodydisc">
                                    <?php echo esc_html(__("Easily integrate AI with WooCommerce to boost your online store's capabilities. Leverage GeekyBot for WooCommerce lead generation, enhance customer interactions, streamline inquiries, and improve customer retention for an exceptional shopping experience.",'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-subtitle">
                                    <?php echo esc_html(__(" Edit Your Bot's WooCommerce Story",'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-subtext">
                                    <?php echo esc_html(__("You have the option to add or edit your WooCommerce story later from the story listing page.",'geeky-bot')); ?>
                                </p>
                                <p class="geekybot-installation-image">
                                    <img class="geeky-installation-story-image" title="<?php echo esc_html(__('WooCommerce Story', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/WooCommerce-story.png';?>" />
                                </p>
                                <a class="geekybot-installation-addstory-turotial"alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                                    <img class="geeky-installation-story-image" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/video.png';?>" />
                                    <?php echo esc_html(__("How To Edit Story",'geeky-bot')); ?>
                                </a>
                            </div>
                            <div class="geekybot-installation-bodyright-section">
                                <img class="geeky-installation-Botimage" title="<?php echo esc_html(__('Bot Image', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/woo-bot.gif';?>" />
                            </div>
                            <div class="geekybot-installation-next-previous-btnwrp">
                                <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_postinstallation&geekybotlt=stepone&geekybot_callfrom=backlink")); ?>" class="geekybot-installation-prevbtn ">
                                    <img class="geeky-installation-backbtn-image" title="<?php echo esc_html(__('Back', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/back-arrow.png';?>" />
                                    <img class="geeky-installation-backbtn-image backbtn-black-image" title="<?php echo esc_html(__('Back', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/black-arrow.png';?>" />
                                    <?php echo esc_html(__('Back', 'geeky-bot')); ?>
                                </a>
                                <a class="geekybot-installation-nextbtn" href="#" onclick="submitPostInstallatinForm(2);">
                                    <?php echo esc_html(__('Next', 'geeky-bot')); ?>
                                    <img class="geeky-installation-nextbtn-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/next-arrow.png';?>" />
                                    <img class="geeky-installation-nextbtn-image backbtn-blue-image" title="<?php echo esc_html(__('Next', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/blue-arrow.png';?>" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
