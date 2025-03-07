<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
$dashboard_message = get_option('dashboard_message');
$current_date   = gmdate('Y-m-d H:i:s');

$bot_expiry_date = get_option('bot_expiry_date');
$bot_expiry_msg = get_option('bot_expiry_msg');

wp_enqueue_script( 'geekybot-google-charts', esc_url(GEEKYBOT_PLUGIN_URL).'includes/js/google-charts.js', array(), '1.1.1', false );
wp_register_script( 'google-charts-handle', '' );
wp_enqueue_script( 'google-charts-handle' );

if ( $dashboard_message == '1') {//isset($dashboard_message) &&

    if($bot_expiry_msg){

        $html = '
        <div class="frontend updated">
            <p>'.esc_html($bot_expiry_msg).'</p>
        </div>';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }
    // if($bot_expiry_date < $current_date ){

    //      echo wp_kses('<div class="frontend updated">
    //                 <p>'.esc_html(_e( "Your Bot Is Going To Expire Soon Please Renew Your subscription!! ","", "geeky-bot")).'</p>
    //            </div>', GEEKYBOT_ALLOWED_TAGS);
    // }
    // elseif($bot_expiry_date > $current_date){

    //     echo wp_kses('<div class="frontend updated">
    //         <p>'.esc_html(_e( "Your Bot Is  Expired !! ","", "geeky-bot")).'</p>
    //         </div>', GEEKYBOT_ALLOWED_TAGS);
    // }

}
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'geeky-bot','layouts' => 'controlpanel')); ?>
    <div class="geekybotadmin-body-main">
        <div id="geekybotadmin-leftmenu-main">
            <?php  geekybotincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data" >
        <?php if (geekybot::$_data['update_avaliable_for_addons'] != 0) {?>
            <div class="geekybot-synchronize-section-mainwrp">
                <div class="geekybot-synchronize-section geekybot-addon-update-available-section">
                    <div class="geekybot-synchronize-imgwrp">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/addon_update.png"title="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                    </div>
                    <div class="geekybot-synchronize-content-wrp">
                        <span class="geekybot-synchronize-content-title"><?php echo esc_html(__('GeekyBot Add-ons Update Available!', 'geeky-bot'));?></span>
                        <span class="geekybot-synchronize-content-disc"><?php echo esc_html(__("We have recently launched a fresh update for the add-ons. Don't forget to update the add-ons to enjoy the greatest features!", 'geeky-bot'));?></span>
                    </div>
                    <div class="geekybot-synchronize-button-wrp">
                        <a class="geekybot_synchronize_data" title="<?php echo esc_attr(__('Synchronize Data', 'geeky-bot')); ?>" href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_premiumplugin&geekybotlt=addonstatus','addonstatus'))?>">
                            <?php echo esc_html(__('View Add-ons Status', 'geeky-bot')); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
        <!-- top head -->
        <div class="geekybot-dashboard-cards-wrp">
            <div class="geekybot-dashboard-card">
                <div class="geekybot-dashboard-card-inner">
                    <div class="geekybot-dashboard-card-center-counts-wrp">
                        <p class="geekybot-dashboard-card-tit"><?php echo esc_html(__('AI ChatBot Sessions', 'geeky-bot')); ?></p>
                        <p class="geekybot-dashboard-card-dis"><?php echo esc_html(geekybot::$_data['ai_chatbot_sessions']['today']); ?></p>
                    </div>
                    <div class="geekybot-dashboard-card-right-img-wrp">
                        <img class="geekyboard-cardcolor-icon" alt="<?php echo esc_html(__('Session Card', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Session Card', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/ai_card_icon.png" />
                    </div>
                    <div class="geekybot-dashboard-cardcolor-btmimage-wrp">
                        <img class="geekyboard-cardcolor-btmimage"alt="<?php echo esc_html(__('Graph', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Card', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/ai_card_graph.png" />
                    </div>
                </div>
            </div>
            <div class="geekybot-dashboard-card">
                <div class="geekybot-dashboard-card-inner">
                    <div class="geekybot-dashboard-card-center-counts-wrp">
                        <p class="geekybot-dashboard-card-tit"><?php echo esc_html(__('WooCommerce Sessions', 'geeky-bot')); ?></p>
                        <p class="geekybot-dashboard-card-dis"><?php echo esc_html(geekybot::$_data['woocommerce_sessions']['today']); ?></p>
                    </div>
                    <div class="geekybot-dashboard-card-right-img-wrp geekybot-dashboard-card-right-wc-img-wrp">
                        <img class="geekyboard-cardcolor-icon" title="<?php echo esc_html(__('Session Card', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Session Card', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/wc_card_icon.png" />
                    </div>
                    <div class="geekybot-dashboard-cardcolor-btmimage-wrp">
                        <img class="geekyboard-cardcolor-btmimage"alt="<?php echo esc_html(__('Graph', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Card', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/wc_card_graph.png" />
                    </div>
                </div>
            </div>
            <div class="geekybot-dashboard-card">
                <div class="geekybot-dashboard-card-inner">
                    <div class="geekybot-dashboard-card-center-counts-wrp">
                        <p class="geekybot-dashboard-card-tit"><?php echo esc_html(__('AI Web Search Sessions', 'geeky-bot')); ?></p>
                        <p class="geekybot-dashboard-card-dis"><?php echo esc_html(geekybot::$_data['ai_web_search_sessions']['today']); ?></p>
                    </div>
                    <div class="geekybot-dashboard-card-right-img-wrp">
                        <img class="geekyboard-cardcolor-icon"alt="<?php echo esc_html(__('Session Card', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Session Card', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/web_card_icon.png" />
                    </div>
                    <div class="geekybot-dashboard-cardcolor-btmimage-wrp">
                        <img class="geekyboard-cardcolor-btmimage"alt="<?php echo esc_html(__('Graph', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Card', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/web_card_graph.png" />
                    </div>
                </div>
            </div>
        </div>
        <?php if(get_option( 'geekybot_hide_admin_top_banner') != 1){ ?>
            <div class="geekybot-dashboard-installation-guide-wrp">
                <div class="geekybot-dashboard-installation-guide-left-image">
                  <img title="<?php echo esc_html(__('Logo', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/banner-image.png"  srcset="">
                </div>
                <div class="geekybot-dashboard-installation-guide-text-wrp">
                    <p class="geekybot-dashboard-installation-guide-heading"><?php echo esc_html(__('GeekyBot Setup and Usage Guides', 'geeky-bot')); ?></p>
                    <p class="geekybot-dashboard-installation-guide-heading-dis"><?php echo esc_html(__("Explore installation, training, and effective usage guides.", 'geeky-bot')); ?></p>
                    <div class="geekybot-dashboard-installation-guide-videos-btnwrp">
                        <a href="https://youtu.be/kbaA_l5j_9w" target="_blank" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to add Story', 'geeky-bot')); ?> 
                        </a>
                        <a href="https://youtu.be/kbaA_l5j_9w?si=76FlfYmp5iQfKg20&t=199" target="_blank" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to add Variable', 'geeky-bot')); ?> 
                        </a>
                        <a href="https://www.youtube.com/watch?v=q6gMOVlfxIE" target="_blank" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to translate', 'geeky-bot')); ?> 
                        </a>
                        <a href="https://www.youtube.com/watch?v=Z3g4fRpoZlc" target="_blank" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to use AI Web Search', 'geeky-bot')); ?> 
                        </a>
                        <a href="https://www.youtube.com/watch?v=48emHU4_VUE" target="_blank" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to install addons', 'geeky-bot')); ?> 
                        </a> 
                    </div>
                </div>
                <div class="geekybot-dashboard-installation-guide-right-crossimage">
                    <button id="geeky-close-banner"><img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/close-icon.png" title="<?php echo esc_html(__('Remove', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Cross Icon', 'geeky-bot')); ?>" srcset=""></button>
                </div>
            </div>
            <?php
        } ?>
        <div class="geekybot-dashboard-story-section">
            <div class="geekybot-dashboard-story-section-innerwrp">
                <div class="geekybot-dashboard-story-section-heading">
                    <span class="geekybot-dashboard-story-title"><?php echo esc_html(__('Stories', 'geeky-bot'));?></span>
                    <span class="geekybot-dashboard-story-disc">
                        <?php echo esc_html(__('Stories Date', 'geeky-bot').' : ');?>
                        <?php echo date_i18n('d-M-Y', geekybotphplib::GEEKYBOT_strtotime(geekybot::$_data['last_month_ai_chatbot_story_chart']['start_date'])) .' / '.date_i18n('d-M-Y', geekybotphplib::GEEKYBOT_strtotime(geekybot::$_data['last_month_ai_chatbot_story_chart']['end_date']));?>
                    </span>
                </div>
                <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_stories&geekybotlt=stories','Stories'))?>" class="geekybot-dashboard-story-section-button">
                    <?php echo esc_html(__('View Stories', 'geeky-bot'));?>
                </a>
            </div>
            <div class="geekybot-dashboard-story-section-graphcard">
                <?php if (!isset(geekybot::$_data['ai_chatbot_sessions']['error_message'])) { ?>
                    <div class="geekybot-dashboard-story-section-card" id="last_month_ai_chatbot_story_chart"></div>
                <?php } else { ?>
                    <div class="geekybot-dashboard-story-section-nodatacard geekybot-dashboard-story-section-nodata-aicard">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/ai-logo.png" title="<?php echo esc_html(__('AI Chatbot', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('AI Chatbot', 'geeky-bot')); ?>" srcset="">
                        <?php echo esc_html(geekybot::$_data['ai_chatbot_sessions']['error_message']);?>
                        <div class="geekybot-dashboard-story-section-nodatacard-action-wrp nodatacard_ai">
                            <?php 
                            echo wp_kses(geekybot::$_data['ai_chatbot_sessions']['error_message_btn'], GEEKYBOT_ALLOWED_TAGS); ?>
                        </div>
                    </div>
                <?php } ?>
                <?php if (!isset(geekybot::$_data['woocommerce_sessions']['error_message'])) { ?>
                    <div class="geekybot-dashboard-story-section-card" id="last_month_woocommerce_story_chart"></div>
                <?php } else { ?>
                    <div class="geekybot-dashboard-story-section-nodatacard">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/woo-logo.png" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" srcset="">
                        <?php echo esc_html(geekybot::$_data['woocommerce_sessions']['error_message']);?>
                        <div class="geekybot-dashboard-story-section-nodatacard-action-wrp nodatacard_wc">
                            <?php 
                            echo wp_kses(geekybot::$_data['woocommerce_sessions']['error_message_btn'], GEEKYBOT_ALLOWED_TAGS); ?>
                        </div>
                    </div>
                <?php } ?>
                <div class="geekybot-dashboard-story-section-innercolorcard">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/bot-icon.png" alt="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>" srcset="">
                    <div class="geekybot-dashboard-story-section-colorcard-textwrp">
                        <span class="geekybot-dashboard-story-section-colorcard-title">
                            <?php echo esc_html(__('AI ChatBot', 'geeky-bot'));?>
                        </span>
                        <span class="geekybot-dashboard-story-section-colorcard-disc">
                            <?php echo esc_html(__('Last Month Stats', 'geeky-bot'));?>
                        </span>
                    </div>
                    <div class="geekybot-dashboard-story-section-colorcard-counterwrp">
                        <span class="geekybot-dashboard-story-section-colorcard-counter">
                            <?php echo esc_html(geekybot::$_data['ai_chatbot_sessions']['last_month']);?>
                        </span>
                    </div>
                </div>
                <div class="geekybot-dashboard-story-section-innercolorcard geekybot-dashboard-story-section-woocard">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/woo-icon.png" alt="<?php echo esc_html(__('Woo Icon', 'geeky-bot')); ?>" srcset="">
                    <div class="geekybot-dashboard-story-section-colorcard-textwrp">
                        <span class="geekybot-dashboard-story-section-colorcard-title">
                            <?php echo esc_html(__('WooCommerce', 'geeky-bot'));?>
                        </span>
                        <span class="geekybot-dashboard-story-section-colorcard-disc">
                            <?php echo esc_html(__('Last Month Stats', 'geeky-bot'));?>
                        </span>
                    </div>
                    <div class="geekybot-dashboard-story-section-colorcard-counterwrp">
                        <span class="geekybot-dashboard-story-section-colorcard-counter">
                            <?php echo esc_html(geekybot::$_data['woocommerce_sessions']['last_month']);?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="geekybot-dashboard-ai-section">
            <div class="geekybot-dashboard-story-section-innerwrp geekybot-dashboard-websrch-section-innerwrp">
                <div class="geekybot-dashboard-story-section-heading">
                    <span class="geekybot-dashboard-story-title">
                        <span class="geekybot-dashboard-story-colored-title">
                            <?php echo esc_html(__('AI Web Search ', 'geeky-bot'));?>
                        </span>
                        <?php echo esc_html('- '.__('Manage Post Types', 'geeky-bot'));?>
                    </span>
                    <span class="geekybot-dashboard-story-disc">
                        <?php echo esc_html(__('Stories Date', 'geeky-bot').' : ');?>
                        <?php echo date_i18n('d-M-Y', geekybotphplib::GEEKYBOT_strtotime(geekybot::$_data['last_month_ai_chatbot_story_chart']['start_date'])) .' / '.date_i18n('d-M-Y', geekybotphplib::GEEKYBOT_strtotime(geekybot::$_data['last_month_ai_chatbot_story_chart']['end_date']));?>
                    </span>
                </div>
                <div class="geekybot-dashboard-story-section-buttons-wrp">
                    <?php
                    if (geekybot::$_configuration['is_posts_enable'] != 1) { ?>
                        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_websearch','websearch'))?>" class="geekybot-dashboard-story-section-button search-enable-btn">
                            <?php echo esc_html(__('Enable AI Web Search', 'geeky-bot'));?>
                        </a>
                        <?php 
                    } ?>
                    <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_websearch','websearch'))?>" class="geekybot-dashboard-story-section-button">
                        <?php echo esc_html(__('Manage Post Types', 'geeky-bot'));?>
                    </a>
                </div>
            </div>
            <div class="geekybot-dashboard-ai-section-graphcard">
                <?php if (!isset(geekybot::$_data['last_month_posttype_story_chart_error_message_0'])) { ?>
                    <div class="geekybot-dashboard-ai-section-card" id="last_month_posttype_story_chart_0"></div>
                <?php } else { ?>
                    <div class="geekybot-dashboard-story-section-nodatacard geekybot-dashboard-story-section-nodata-webcard">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/web-logo.png" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" srcset="">
                        <?php echo esc_html(geekybot::$_data['last_month_posttype_story_chart_error_message_0']);?>
                    </div>
                <?php } ?>
                <?php if (!isset(geekybot::$_data['last_month_posttype_story_chart_error_message_1'])) { ?>
                    <div class="geekybot-dashboard-ai-section-card" id="last_month_posttype_story_chart_1"></div>
                <?php } else { ?>
                    <div class="geekybot-dashboard-story-section-nodatacard geekybot-dashboard-story-section-nodata-webpstcard">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/web-logo.png" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" srcset="">
                        <?php echo esc_html(geekybot::$_data['last_month_posttype_story_chart_error_message_1']);?>
                    </div>
                <?php } ?>
                <?php if (!isset(geekybot::$_data['last_month_posttype_story_chart_error_message_2'])) { ?>
                    <div class="geekybot-dashboard-ai-section-card" id="last_month_posttype_story_chart_2"></div>
                <?php } else { ?>
                    <div class="geekybot-dashboard-story-section-nodatacard geekybot-dashboard-story-section-nodata-webjbcard">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/web-logo.png" title="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>" srcset="">
                        <?php echo esc_html(geekybot::$_data['last_month_posttype_story_chart_error_message_2']);?>
                    </div>
                <?php } ?>
                <div class="geekybot-dashboard-ai-section-innercolorcard">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/ai-web-search-icon.png" alt="<?php echo esc_html(__('Search Icon', 'geeky-bot')); ?>" srcset="">
                    <div class="geekybot-dashboard-ai-section-colorcard-textwrp">
                        <span class="geekybot-dashboard-ai-section-colorcard-title">
                            <?php 
                            if (isset(geekybot::$_data['top_ai_web_search'][0]->post_type)) {
                                echo esc_html(geekybot::$_data['top_ai_web_search'][0]->post_type);
                            } else {
                                echo esc_html(geekybot::$_data['last_month_posttype_story_chart_error_message_0']);
                            } ?>
                        </span>
                    </div>
                    <span class="geekybot-dashboard-ai-section-colorcard-counter">
                        <?php 
                        if (isset(geekybot::$_data['top_ai_web_search'][0]->session_count)) {
                            echo esc_html(geekybot::$_data['top_ai_web_search'][0]->session_count);
                        } else {
                            echo esc_html('0');
                        } ?>
                    </span>
                </div>
                <div class="geekybot-dashboard-ai-section-innercolorcard geekybot-dashboard-ai-section-postcard">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/ai-web-search-icon.png" alt="<?php echo esc_html(__('Search Icon', 'geeky-bot')); ?>" srcset="">
                    <div class="geekybot-dashboard-ai-section-colorcard-textwrp">
                        <span class="geekybot-dashboard-ai-section-colorcard-title">
                            <?php 
                            if (isset(geekybot::$_data['top_ai_web_search'][1]->post_type)) {
                                echo esc_html(geekybot::$_data['top_ai_web_search'][1]->post_type);
                            } else {
                                echo esc_html(geekybot::$_data['last_month_posttype_story_chart_error_message_1']);
                            } ?>
                        </span>
                    </div>
                    <span class="geekybot-dashboard-ai-section-colorcard-counter">
                        <?php 
                        if (isset(geekybot::$_data['top_ai_web_search'][1]->session_count)) {
                            echo esc_html(geekybot::$_data['top_ai_web_search'][1]->session_count);
                        } else {
                            echo esc_html('0');
                        } ?>
                    </span>
                </div>
                <div class="geekybot-dashboard-ai-section-innercolorcard geekybot-dashboard-ai-section-joblisting-card">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/ai-web-search-icon.png" alt="<?php echo esc_html(__('Search Icon', 'geeky-bot')); ?>" srcset="">
                    <div class="geekybot-dashboard-ai-section-colorcard-textwrp">
                        <span class="geekybot-dashboard-ai-section-colorcard-title">
                            <?php 
                            if (isset(geekybot::$_data['top_ai_web_search'][2]->post_type)) {
                                echo esc_html(geekybot::$_data['top_ai_web_search'][2]->post_type);
                            } else {
                                echo esc_html(geekybot::$_data['last_month_posttype_story_chart_error_message_2']);
                            } ?>
                        </span>
                    </div>
                    <span class="geekybot-dashboard-ai-section-colorcard-counter">
                        <?php 
                        if (isset(geekybot::$_data['top_ai_web_search'][2]->session_count)) {
                            echo esc_html(geekybot::$_data['top_ai_web_search'][2]->session_count);
                        } else {
                            echo esc_html('0');
                        } ?>
                    </span>
                </div>
            </div>
            <div class="geekybot-dashboard-ai-section-colorcard">
            </div>
        </div>
        <div class="geekybot-dashboard-history-addon-section-wrp">
            <?php
            if(count(geekybot::$_data['chat_history']) > 0){ ?>
                <div class="geebot-dashboard-history-section">
                    <div class="geekybot-dashboard-history-title">
                        <?php echo esc_html(__('History', 'geeky-bot')); ?>
                    </div>
                    <?php
                    foreach (geekybot::$_data['chat_history'] as $chatHistory) {
                        if ($chatHistory->type == 1) {
                            $chat_logo = 'bot.png';
                            $chat_session = __('AI ChatBot', 'geeky-bot');
                        } else if ($chatHistory->type == 2) {
                            $chat_logo = 'woo-logo.png';
                            $chat_session = __('WooCommerce Bot', 'geeky-bot');
                        } else if ($chatHistory->type == 4) {
                            $chat_logo = 'ai-web.png';
                            $chat_session = __('AI Web Search', 'geeky-bot');
                        } else {
                            $chat_logo = '';
                            $chat_session = '';
                        } ?>
                        <div class="geebot-dashboard-history-innersection">
                            <div class="geebot-dashboard-history-logowrp">
                                <?php 
                                if ($chat_logo != '') { ?>
                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/<?php echo esc_attr($chat_logo); ?>" alt="<?php echo esc_html(__('logo', 'geeky-bot')); ?>" srcset="">
                                    <?php 
                                } ?>
                            </div>
                            <div class="geebot-dashboard-history-right-section">
                                <span class="geebot-dashboard-history-right-section-text">
                                    <?php echo esc_html($chat_session);?>
                                </span>
                                <span class="geebot-dashboard-history-right-section-text">
                                    <?php echo esc_html(__('Sender', 'geeky-bot')).' : '; ?>
                                    <?php
                                    echo !empty($chatHistory->user_name) ? esc_html($chatHistory->user_name) : esc_html(__('Guest', 'geeky-bot')); ?>
                                </span>
                                <span class="geebot-dashboard-history-right-section-text">
                                    <?php echo esc_html(__('No of Conversation', 'geeky-bot')).' : '; ?>
                                    <?php echo esc_html($chatHistory->conversions); ?>
                                </span>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    <div class="geebot-dashboard-viewhistory-button-wrp">
                        <a class="geebot-dashboard-viewhistory-button" href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_chathistory','chat-history'))?>">
                            <?php echo esc_html(__('View History', 'geeky-bot')); ?>
                        </a>
                    </div>
                </div>
                <?php
            } else { ?>
                <div class="geekybot-dashboard-histroy-nodata-section">
                    <div class="geekybot-dashboard-history-title">
                        <?php echo esc_html(__('History', 'geeky-bot')); ?>
                    </div>
                    <div class="geekybot-dashboard-histroy-nodata-wrp">
                        <img class="geekybot-dashboard-histroy-nodata-img" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/nodata.png" title="<?php echo esc_html(__('No data', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('No data', 'geeky-bot')); ?>" srcset="">
                        <?php echo esc_html(__('No Data', 'geeky-bot')); ?>
                    </div>
                </div>
                <?php
            }
            ?>
            <div class="geebot-dashboard-addon-section">
                <div class="geekybot-dashboard-addons-title">
                    <?php echo esc_html(__('Addons', 'geeky-bot')); ?>
                </div>
                <div class="geekybot-dashboard-addons-card">
                    <a target="_blank" href="https://geekybot.com/add-ons/custom-text-style/" class="geekybot-dashboard-addons-cards-tmpwrp">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/addons/ad0n-2.png" alt="<?php echo esc_html(__('addon', 'geeky-bot')); ?>" srcset="">
                        <span class="geekybot-dashboard-addons-card-subtitle"><?php echo esc_html(__('Custom Text Style', 'geeky-bot')); ?></span>
                    </a>
                </div>
                <div class="geekybot-dashboard-addons-card">
                    <a target="_blank" href="https://geekybot.com/add-ons/custom-listing-style/" class="geekybot-dashboard-addons-cards-tmpwrp">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/addons/ad0n-1.png" alt="<?php echo esc_html(__('addon', 'geeky-bot')); ?>" srcset="">
                        <span class="geekybot-dashboard-addons-card-subtitle"><?php echo esc_html(__('Custom Listing Style', 'geeky-bot')); ?></span>
                    </a>
                </div>
                <div class="geekybot-dashboard-addons-card">
                    <a target="_blank" href="https://geekybot.com/product/woocommerce-pro-pack/" class="geekybot-dashboard-addons-cards-tmpwrp">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/addons/ad0n-3.png" alt="<?php echo esc_html(__('addon', 'geeky-bot')); ?>" srcset="">
                        <span class="geekybot-dashboard-addons-card-subtitle"><?php echo esc_html(__('WooCommerce Pro Pack', 'geeky-bot')); ?></span>
                    </a>
                </div>
                <div class="geebot-dashboard-addonslist-button-wrp">
                    <a class="geebot-dashboard-addonslist-button" href="https://geekybot.com/add-ons/" title="<?php echo esc_attr(__('Install Add-ons' , 'geeky-bot')); ?>"><?php echo esc_html(__('Addon List', 'geeky-bot')); ?></a>
                </div>
            </div>
        </div>
        <div class="geekybot-dashboard-woocommerce-section">
            <div class="geekybot-dashboard-woocommerce-leftsection">
                <span class="geekybot-dashboard-woocommerce-leftsection-title"><span class="geekybot-dashboard-woocommerce-leftsection-coloredtitle"><?php echo esc_html(__('WooCommerce ', 'geeky-bot'))." ";?></span> <?php echo esc_html(__('Lead Generation', 'geeky-bot'));?></span>
                <p class="geekybot-dashboard-woocommerce-leftsection-disc"><?php echo esc_html(__('GeekyBot simplifies the WooCommerce experience with intelligent product discovery and purchasing. It seamlessly integrates with WooCommerce for efficient product searches, detailed listings, and a smooth shopping experience.', 'geeky-bot'));?></p>
                <div class="geekybot-dashboard-woocommerce-btm-innrsection">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/find.png" alt="<?php echo esc_html(__('Find Product', 'geeky-bot')); ?>">
                    <div class="geekybot-dashboard-woocommerce-prdctwrp">
                        <span class="geekybot-dashboard-woocommerce-prdct-title"><?php echo esc_html(__('Find Products', 'geeky-bot'));?></span>
                        <span class="geekybot-dashboard-woocommerce-prdct-disc"><?php echo esc_html(__('Find products by name, category, or price range via chat.', 'geeky-bot'));?></span>
                    </div>
                </div>
                <div class="geekybot-dashboard-woocommerce-btm-innrsection">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/list.png" alt="<?php echo esc_html(__('List Product', 'geeky-bot')); ?>">
                    <div class="geekybot-dashboard-woocommerce-prdctwrp">
                        <span class="geekybot-dashboard-woocommerce-prdct-title"><?php echo esc_html(__('List Products', 'geeky-bot'));?></span>
                        <span class="geekybot-dashboard-woocommerce-prdct-disc"><?php echo esc_html(__('Show relevant products with details and prices based on preferences.', 'geeky-bot'));?></span>
                    </div>
                </div>
                <div class="geekybot-dashboard-woocommerce-btm-innrsection">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/add-cart.png" alt="<?php echo esc_html(__('Add To Cart Product', 'geeky-bot')); ?>">
                    <div class="geekybot-dashboard-woocommerce-prdctwrp">
                        <span class="geekybot-dashboard-woocommerce-prdct-title"><?php echo esc_html(__('Add to Cart', 'geeky-bot'));?></span>
                        <span class="geekybot-dashboard-woocommerce-prdct-disc"><?php echo esc_html(__('Easily add products to the cart list directly through chat.', 'geeky-bot'));?></span>
                    </div>
                </div>
                <div class="geekybot-dashboard-storywatch-videobtnwrp">
                    <a href="#" class="geekybot-dashboard-storywatch-videobtn" title="<?php echo esc_html(__('Watch Video', 'geeky-bot')); ?>">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                        <?php echo esc_html(__('Watch Video', 'geeky-bot')); ?>
                    </a>
                </div>
            </div>
            <div class="geekybot-dashboard-woocommerce-rightsection">
                <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/woo-right-image.png" alt="<?php echo esc_html(__('WooCommerce', 'geeky-bot')); ?>">
            </div>
        </div>
        <div class="geekybot-dashboard-aichat-section">
            <div class="geekybot-dashboard-aichat-leftsection">
                <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/ai-chat-right-image.png" alt="<?php echo esc_html(__('AI Chat', 'geeky-bot')); ?>">
            </div>
            <div class="geekybot-dashboard-aichat-rigntsection">
                <span class="geekybot-dashboard-aichat-rigntsection-title"><span class="geekybot-dashboard-aichat-rigntsection-coloredtitle"><?php echo esc_html(__('Custom', 'geeky-bot'));?><span class="geekybot-dashboard-aichat-rightsection-coloredtitle"><?php echo esc_html(__(' AI Chat Bot ', 'geeky-bot'));?></span> <?php echo esc_html(__('Conversation Handling', 'geeky-bot'));?></span>
                <p class="geekybot-dashboard-aichat-rigntsection-disc"><?php echo esc_html(__('AI Chat Conversation provides intelligent and context-aware interactions to enhance user engagement. Define key conversation elements to capture specific user inputs dynamically and adapt responses based on the context.', 'geeky-bot'));?></p>
                <p class="geekybot-dashboard-aichat-rigntsection-line-disc"><?php echo esc_html(__('Capture and process inputs dynamically for personalized responses.', 'geeky-bot'));?></p>
                <p class="geekybot-dashboard-aichat-rigntsection-line-disc"><?php echo esc_html(__('Adjust interactions based on the ongoing conversation for relevance.', 'geeky-bot'));?></p>
                <p class="geekybot-dashboard-aichat-rigntsection-line-disc"><?php echo esc_html(__('Use collected data to tailor responses and improve satisfaction.', 'geeky-bot'));?></p>
                <div class="geekybot-dashboard-storywatch-videobtnwrp">
                    <a href="#" class="geekybot-dashboard-storywatch-videobtn" title="<?php echo esc_html(__('Watch Video', 'geeky-bot')); ?>">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                        <?php echo esc_html(__('Watch Video', 'geeky-bot')); ?>
                    </a>
                </div>
            </div>
        </div>
        <div class="geekybot-dashboard-aiwebsearch-supported-plugin-section">
            <div class="geekybot-dashboard-aiwebsearch-section">
                <div class="geekybot-dashboard-aiwebsearch-leftsection">
                    <span class="geekybot-dashboard-aiwebsearch-leftsection-title"><span class="geekybot-dashboard-aiwebsearch-leftsection-coloredtitle"><?php echo esc_html(__('Discover Our ', 'geeky-bot'));?><br><span class="geekybot-dashboard-aiwebsearch-rightsection-coloredtitle"><?php echo esc_html(__(' AI Web Search', 'geeky-bot'));?></span> <?php echo esc_html(__('Bot', 'geeky-bot'));?></span>
                    <p class="geekybot-dashboard-aiwebsearch-leftsection-disc"><?php echo esc_html(__('AI Web Search improves user experience by delivering advanced, intuitive content discovery on your WordPress site. With powerful searches, users can quickly find relevant information, enhancing overall interest and accessibility.', 'geeky-bot'));?></p>
                    <p class="geekybot-dashboard-aiwebsearch-leftsection-line-disc"><?php echo esc_html(__('GeekyBot swiftly retrieves posts and pages based on user queries.', 'geeky-bot'));?></p>
                    <p class="geekybot-dashboard-aiwebsearch-leftsection-line-disc"><?php echo esc_html(__('Displays full content or excerpts that match search terms.', 'geeky-bot'));?></p>
                    <p class="geekybot-dashboard-aiwebsearch-leftsection-line-disc"><?php echo esc_html(__('Activate GeekyBot’s search for an AI web search with one click.', 'geeky-bot'));?></p>
                    <div class="geekybot-dashboard-storywatch-videobtnwrp">
                        <a href="#" class="geekybot-dashboard-storywatch-videobtn" title="<?php echo esc_html(__('Watch Video', 'geeky-bot')); ?>">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Play', 'geeky-bot')); ?>">
                            <?php echo esc_html(__('Watch Video', 'geeky-bot')); ?>
                        </a>
                    </div>
                </div>
                <div class="geekybot-dashboard-aiwebsearch-rightsection">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/web-search-image.png" alt="<?php echo esc_html(__('Web Search', 'geeky-bot')); ?>">
                </div>
            </div>
            <div class="geeky-dashboard-supported-plugins-section">
                <div class="geeky-dashboard-supported-plugins-section-title">
                    <?php echo esc_html(__('GeekyBot Supported Plugins', 'geeky-bot')); ?>
                </div>
                <div class="geeky-dashboard-supported-plugins-section-cardswrp">
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/jm.png" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('WP Job Manager', 'geeky-bot')); ?></span>
                    </div>
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/lms.png" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('Tutor LMS', 'geeky-bot')); ?></span>
                    </div>
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/bbPress.png" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('bbPress', 'geeky-bot')); ?></span>
                    </div>
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/lp.png" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('LearnPress', 'geeky-bot')); ?></span>
                    </div>
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/kb.png" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('Knowledge Base', 'geeky-bot')); ?></span>
                    </div>
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/docs.png" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('BetterDocs', 'geeky-bot')); ?></span>
                    </div>
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/Motors.png" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('Motors', 'geeky-bot')); ?></span>
                    </div>
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/Estatik Real Estate.png" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('Estatik Real Estate', 'geeky-bot')); ?></span>
                    </div>
                    <div class="geeky-dashboard-supported-plugins-section-card">
                        <span class="geeky-dashboard-supported-plugins-section-card-image-wrp"><img class="geeky-dashboard-supported-plugins-section-card-image" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/supported-plugins/Auto Listings.jpg" alt="<?php echo esc_html(__('Plugin', 'geeky-bot')); ?>"></span>
                        <span class="geeky-dashboard-supported-plugins-section-card-title"><?php echo esc_html(__('Auto Listings', 'geeky-bot')); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php
$geekybot_js ="
    // video banner
    jQuery('button#geeky-close-banner').click(function(){
        jQuery('.geekybot-dashboard-installation-guide-wrp').fadeOut('slow');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl ,{action: 'geekybot_ajax',geekybotme: 'geekybot',task: 'hideVideoPopupFromAdmin', '_wpnonce':'".esc_attr(wp_create_nonce("hide-popup-from-admin"))."'});
    });
    // geekybot-hide-banner

    // Ai Graph
    google.charts.load('current', { packages: ['corechart'] });";
    if (!isset(geekybot::$_data['ai_chatbot_sessions']['error_message'])) {
        $geekybot_js .=" google.charts.setOnLoadCallback(aiDrawCharts);";
        $geekybot_js .= "
        function aiDrawCharts() {
            // Chart configurations
            const chartConfigs = [
                {
                    elementId: 'last_month_ai_chatbot_story_chart',
                    data: [
                        ".
                        wp_kses(geekybot::$_data["last_month_ai_chatbot_story_chart"]['data'], GEEKYBOT_ALLOWED_TAGS)
                        ."
                    ],
                    color: '#e20f64' // pink
                }
            ];

            // Draw charts
            chartConfigs.forEach((config) => {
                const data = new google.visualization.DataTable();
                data.addColumn('date', 'Date'); // X-axis will be a date
                data.addColumn('number', 'Value');
                
                // Prepare the data to include a date object
                const dataRows = config.data.map(item => {
                    const date = new Date(item[0], item[1] - 1, item[2]); // Year, Month (zero-based), Day
                    return [date, item[3]]; // Return [Date, Value]
                });
                
                data.addRows(dataRows);

                const options = {
                    legend: 'none',
                    colors: [config.color],
                    pointSize: 6,
                    lineWidth: 2,
                    backgroundColor: { fill: 'transparent' },
                    chartArea: {
                        left: 50,
                        top: 30,
                        right: 30,
                        bottom: 40,
                        width: '60%',
                    },
                    hAxis: {
                        title: 'Date',
                        format: 'MMM dd',
                    }
                };

                const chart = new google.visualization.LineChart(
                    document.getElementById(config.elementId)
                );

                chart.draw(data, options);

                // Store the chart instance to redraw later
                config.chartInstance = chart;
                config.dataTable = data;
                config.options = options;
            });

            // Redraw charts on window resize
            window.onresize = () => {
                chartConfigs.forEach((config) => {
                    config.chartInstance.draw(config.dataTable, config.options);
                });
            };
        }";
    }
    if (!isset(geekybot::$_data['woocommerce_sessions']['error_message'])) {
        $geekybot_js .=" google.charts.setOnLoadCallback(wcDrawCharts);";
        $geekybot_js .= "
        function wcDrawCharts() {
            // Chart configurations
            const chartConfigs = [
                {
                    elementId: 'last_month_woocommerce_story_chart',
                    data: [
                        ".
                        wp_kses(geekybot::$_data["last_month_woocommerce_story_chart"]['data'], GEEKYBOT_ALLOWED_TAGS)
                        ."
                    ],
                    color: '#9b59b6' // Purple
                }
            ];

            // Draw charts
            chartConfigs.forEach((config) => {
                const data = new google.visualization.DataTable();
                data.addColumn('date', 'Date'); // X-axis will be a date
                data.addColumn('number', 'Value');
                
                // Prepare the data to include a date object
                const dataRows = config.data.map(item => {
                    const date = new Date(item[0], item[1] - 1, item[2]); // Year, Month (zero-based), Day
                    return [date, item[3]]; // Return [Date, Value]
                });
                
                data.addRows(dataRows);

                const options = {
                    legend: 'none',
                    colors: [config.color],
                    pointSize: 6,
                    lineWidth: 2,
                    backgroundColor: { fill: 'transparent' },
                    chartArea: {
                        left: 50,
                        top: 30,
                        right: 30,
                        bottom: 40,
                        width: '60%',
                    },
                    hAxis: {
                        title: 'Date',
                        format: 'MMM dd',
                    }
                };

                const chart = new google.visualization.LineChart(
                    document.getElementById(config.elementId)
                );

                chart.draw(data, options);

                // Store the chart instance to redraw later
                config.chartInstance = chart;
                config.dataTable = data;
                config.options = options;
            });

            // Redraw charts on window resize
            window.onresize = () => {
                chartConfigs.forEach((config) => {
                    config.chartInstance.draw(config.dataTable, config.options);
                });
            };
        }";
    }
    if (!isset(geekybot::$_data['last_month_posttype_story_chart_error_message_0'])) {
        $geekybot_js .=" google.charts.setOnLoadCallback(websearchDrawCharts0);";
        $geekybot_js .= "
        function websearchDrawCharts0() {
            // Chart configurations
            const chartConfigs = [
                {
                    elementId: 'last_month_posttype_story_chart_0',
                    data: [
                        ".
                        wp_kses(geekybot::$_data["last_month_posttype_story_chart_0"], GEEKYBOT_ALLOWED_TAGS)
                        ."
                    ],
                    color: '#008ce7' // Blue
                }
            ];

            // Draw charts
            chartConfigs.forEach((config) => {
                const data = new google.visualization.DataTable();
                data.addColumn('date', 'Date'); // X-axis will be a date
                data.addColumn('number', 'Value');
                
                // Prepare the data to include a date object
                const dataRows = config.data.map(item => {
                    const date = new Date(item[0], item[1] - 1, item[2]); // Year, Month (zero-based), Day
                    return [date, item[3]]; // Return [Date, Value]
                });
                
                data.addRows(dataRows);

                const options = {
                    legend: 'none',
                    colors: [config.color],
                    pointSize: 6,
                    lineWidth: 2,
                    backgroundColor: { fill: 'transparent' },
                    chartArea: {
                        left: 50,
                        top: 30,
                        right: 30,
                        bottom: 40,
                        width: '60%',
                    },
                    hAxis: {
                        title: 'Date',
                        format: 'MMM dd',
                    }
                };

                const chart = new google.visualization.LineChart(
                    document.getElementById(config.elementId)
                );

                chart.draw(data, options);

                // Store the chart instance to redraw later
                config.chartInstance = chart;
                config.dataTable = data;
                config.options = options;
            });

            // Redraw charts on window resize
            window.onresize = () => {
                chartConfigs.forEach((config) => {
                    config.chartInstance.draw(config.dataTable, config.options);
                });
            };
        }";
    }
    if (!isset(geekybot::$_data['last_month_posttype_story_chart_error_message_1'])) {
        $geekybot_js .=" google.charts.setOnLoadCallback(websearchDrawCharts1);";
        $geekybot_js .= "
        function websearchDrawCharts1() {
            // Chart configurations
            const chartConfigs = [
                {
                    elementId: 'last_month_posttype_story_chart_1',
                    data: [
                        ".
                        wp_kses(geekybot::$_data["last_month_posttype_story_chart_1"], GEEKYBOT_ALLOWED_TAGS)
                        ."
                    ],
                    color: '#e33521' // Green
                }
            ];

            // Draw charts
            chartConfigs.forEach((config) => {
                const data = new google.visualization.DataTable();
                data.addColumn('date', 'Date'); // X-axis will be a date
                data.addColumn('number', 'Value');
                
                // Prepare the data to include a date object
                const dataRows = config.data.map(item => {
                    const date = new Date(item[0], item[1] - 1, item[2]); // Year, Month (zero-based), Day
                    return [date, item[3]]; // Return [Date, Value]
                });
                
                data.addRows(dataRows);

                const options = {
                    legend: 'none',
                    colors: [config.color],
                    pointSize: 6,
                    lineWidth: 2,
                    backgroundColor: { fill: 'transparent' },
                    chartArea: {
                        left: 50,
                        top: 30,
                        right: 30,
                        bottom: 40,
                        width: '60%',
                    },
                    hAxis: {
                        title: 'Date',
                        format: 'MMM dd',
                    }
                };

                const chart = new google.visualization.LineChart(
                    document.getElementById(config.elementId)
                );

                chart.draw(data, options);

                // Store the chart instance to redraw later
                config.chartInstance = chart;
                config.dataTable = data;
                config.options = options;
            });

            // Redraw charts on window resize
            window.onresize = () => {
                chartConfigs.forEach((config) => {
                    config.chartInstance.draw(config.dataTable, config.options);
                });
            };
        }";
    }
    if (!isset(geekybot::$_data['last_month_posttype_story_chart_error_message_2'])) {
        $geekybot_js .=" google.charts.setOnLoadCallback(websearchDrawCharts2);";
        $geekybot_js .= "
        function websearchDrawCharts2() {
            // Chart configurations
            const chartConfigs = [
                {
                    elementId: 'last_month_posttype_story_chart_2',
                    data: [
                        ". 
                        wp_kses(geekybot::$_data["last_month_posttype_story_chart_2"], GEEKYBOT_ALLOWED_TAGS)
                        ."
                    ],
                    color: '#00832e' // Yellow
                }
            ];

            // Draw charts
            chartConfigs.forEach((config) => {
                const data = new google.visualization.DataTable();
                data.addColumn('date', 'Date'); // X-axis will be a date
                data.addColumn('number', 'Value');
                
                // Prepare the data to include a date object
                const dataRows = config.data.map(item => {
                    const date = new Date(item[0], item[1] - 1, item[2]); // Year, Month (zero-based), Day
                    return [date, item[3]]; // Return [Date, Value]
                });
                
                data.addRows(dataRows);

                const options = {
                    legend: 'none',
                    colors: [config.color],
                    pointSize: 6,
                    lineWidth: 2,
                    backgroundColor: { fill: 'transparent' },
                    chartArea: {
                        left: 50,
                        top: 30,
                        right: 30,
                        bottom: 40,
                        width: '60%',
                    },
                    hAxis: {
                        title: 'Date',
                        format: 'MMM dd',
                    }
                };

                const chart = new google.visualization.LineChart(
                    document.getElementById(config.elementId)
                );

                chart.draw(data, options);

                // Store the chart instance to redraw later
                config.chartInstance = chart;
                config.dataTable = data;
                config.options = options;
            });

            // Redraw charts on window resize
            window.onresize = () => {
                chartConfigs.forEach((config) => {
                    config.chartInstance.draw(config.dataTable, config.options);
                });
            };
        }";
    }
    wp_add_inline_script('google-charts-handle',$geekybot_js);
?>

