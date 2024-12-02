<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
$dashboard_message = get_option('dashboard_message');
$current_date   = gmdate('Y-m-d H:i:s');

$bot_expiry_date = get_option('bot_expiry_date');
$bot_expiry_msg = get_option('bot_expiry_msg');

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
<div id="geekybotadmin-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => '','layouts' => '')); ?>
    <div class="geekybotadmin-body-main">
        <div id="geekybotadmin-leftmenu-main">
            <?php  geekybotincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data" >
        <!-- top head -->
        <div id="geekybot-head" class="geekybot-heading-wrp geekybot-dashboard-head">
            <div class="geekybot-heading-left-wrp">
                <h1 class="geekybot-head-text geekybot-dashboard-heading">
                    <?php echo esc_html(__('Dashboard', 'geeky-bot')); ?>
                </h1>
            </div>
        </div>
        <div class="geekybot-dashboard-cards-wrp">
            <div class="geekybot-dashboard-card">
                <div class="geekybot-dashboard-card-inner">
                    <div class="geekybot-dashboard-card-left-image-wrp">
                        <img class="geekyboard-cardcolor-icon" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/chat-user.png" alt="<?php echo esc_html(__('User Icon', 'geeky-bot')); ?>" title="<?php echo esc_html(__('User Chat', 'geeky-bot')); ?>" />
                    </div>
                    <div class="geekybot-dashboard-card-center-counts-wrp">
                        <p class="geekybot-dashboard-card-tit"><?php echo esc_html(__('Total Sessions', 'geeky-bot')); ?></p>
                        <p class="geekybot-dashboard-card-dis"><?php echo esc_html(geekybot::$_data['totalsessions']); ?></p>
                    </div>
                    <div class="geekybot-dashboard-card-right-img-wrp">
                        <img class="geekyboard-cardcolor-icon"alt="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" title="<?php echo esc_html(__('icon', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/question.png" />
                    </div>
                </div>
                <div class="geekybot-dashboard-card-btm-conter">
                    <div class="geekybot-dashboard-card-btm-lftconter">
                        <p class="geekybot-dashboard-card-btm-tit"><?php echo esc_html(__("Today's Sessions", 'geeky-bot')).': '; ?><span class="geekybot-dashboard-card-btm-dis"><?php echo esc_html(geekybot::$_data['todaysessions']); ?></span></p>
                    </div>
                    <div class="geekybot-dashboard-card-btm-rightconter">
                        <img class="geekyboard-cardcolor-icon"alt="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Arrow', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/arrow.png" />
                    </div>
                </div>
            </div>
            <?php 
            if (isset(geekybot::$_data['stats'])) {
                $cardNo = 0;
                foreach (geekybot::$_data['stats'] as $key => $value) {
                    if (isset($value) && $cardNo < 3) { ?>
                        <div class="geekybot-dashboard-card geekybot-dashboard-card-<?php echo esc_attr($cardNo); ?>">
                            <div class="geekybot-dashboard-card-inner">
                                <div class="geekybot-dashboard-card-left-image-wrp">
                                    <img class="geekyboard-cardcolor-icon"alt="<?php echo esc_html(__('Image', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Satisfaction Icon', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/stats-<?php echo esc_attr($cardNo); ?>.png" />
                                </div>
                                <div class="geekybot-dashboard-card-center-counts-wrp">
                                    <p class="geekybot-dashboard-card-tit"><?php echo esc_html($value['title']); ?></p>
                                    <p class="geekybot-dashboard-card-dis"><?php echo esc_html($value['total']); ?></p>
                                </div>
                                <div class="geekybot-dashboard-card-right-img-wrp">
                                    <img class="geekyboard-cardcolor-icon"title="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/question.png" />
                                </div>
                            </div>
                            <div class="geekybot-dashboard-card-btm-conter">
                                <div class="geekybot-dashboard-card-btm-lftconter">
                                    <p class="geekybot-dashboard-card-btm-tit"><?php echo esc_html(__("Today's Sessions", 'geeky-bot')).': '; ?><span class="geekybot-dashboard-card-btm-dis"><?php echo esc_html($value['today']); ?></span></p>
                                </div>
                                <div class="geekybot-dashboard-card-btm-rightconter">
                                    <img class="geekyboard-cardcolor-icon"title="<?php echo esc_html(__('Arrow', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/arrow.png" />
                                </div>
                            </div>
                        </div>
                        <?php
                        $cardNo++;
                    }
                }
            } ?>
        </div>
        <div class="geekybot-dashboard-pages-cards-wrp">
            <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_stories&geekybotlt=stories','stories'))?>"title="<?php echo esc_attr(__('Stories' , 'geeky-bot')); ?>" class="geekybot-dashboard-page-card geekybot-dashboard-page-storycard">
                <div class="geekybot-dashboard-page-card-image">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/stories.png"  srcset="">
                </div>
                <div class="geekybot-dashboard-page-card-text">
                     <?php echo esc_html(__('Stories', 'geeky-bot')); ?>
                </div>
                <div class="geekybot-dashboard-page-card-arrow">
                   <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/blue.png" srcset="">
                </div>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_websearch','slots'))?>"title="<?php echo esc_attr(__('AI Web Search' , 'geeky-bot')); ?>" class="geekybot-dashboard-page-card geekybot-dashboard-page-variablecard">
                <div class="geekybot-dashboard-page-card-image">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/websearch.png" srcset="">
                </div>
                <div class="geekybot-dashboard-page-card-text">
                     <?php echo esc_html(__('AI Web Search', 'geeky-bot')); ?>
                </div>
                <div class="geekybot-dashboard-page-card-arrow">
                   <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/dark-pink.png"srcset="">
                </div>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration','configuration'))?>"title="<?php echo esc_attr(__('Settings' , 'geeky-bot')); ?>" class="geekybot-dashboard-page-card geekybot-dashboard-page-settingcard">
                <div class="geekybot-dashboard-page-card-image">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/setting.png">
                </div>
                <div class="geekybot-dashboard-page-card-text">
                     <?php echo esc_html(__('Settings', 'geeky-bot')); ?>
                </div>
                <div class="geekybot-dashboard-page-card-arrow">
                   <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/red.png">
                </div>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_themes','appearance'))?>" title="<?php echo esc_attr(__('Chatbot' , 'geeky-bot')); ?>" class="geekybot-dashboard-page-card geekybot-dashboard-page-colorcard">
                <div class="geekybot-dashboard-page-card-image">
                    <img title="<?php echo esc_html(__('Chatbot', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/chat-bot.png" srcset="">
                </div>
                <div class="geekybot-dashboard-page-card-text">
                    <?php echo esc_html(__('Chatbot', 'geeky-bot')); ?>
                </div>
                <div class="geekybot-dashboard-page-card-arrow">
                   <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/green.png"title="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" srcset="">
                </div>
            </a> 
        </div>
        <?php if(get_option( 'geekybot_hide_admin_top_banner') != 1){ ?>
            <div class="geekybot-dashboard-installation-guide-wrp">
                <div class="geekybot-dashboard-installation-guide-left-image">
                  <img title="<?php echo esc_html(__('Logo', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Icon', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/ad-logo.png"  srcset="">
                </div>
                <div class="geekybot-dashboard-installation-guide-text-wrp">
                    <p class="geekybot-dashboard-installation-guide-heading"><?php echo esc_html(__('GeekyBot Setup and Usage Guides', 'geeky-bot')); ?></p>
                    <p class="geekybot-dashboard-installation-guide-heading-dis"><?php echo esc_html(__("Explore installation, training, and effective usage guides.", 'geeky-bot')); ?></p>
                    <div class="geekybot-dashboard-installation-guide-videos-btnwrp">
                        <a href="" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to add Story', 'geeky-bot')); ?> 
                        </a>
                        <a href="" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to add Variable', 'geeky-bot')); ?> 
                        </a>
                        <a href="" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Change Settings', 'geeky-bot')); ?> 
                        </a>
                        <a href="" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Change Color', 'geeky-bot')); ?> 
                        </a>
                        <a href="" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to add Story', 'geeky-bot')); ?> 
                        </a>
                        <a href="" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('How to add Variable', 'geeky-bot')); ?> 
                        </a>
                        <a href="" title="<?php echo esc_html(__('Play', 'geeky-bot')); ?>" class="geekybot-dashboard-installation-guide-video-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/youtube-icon.png" alt="<?php echo esc_html(__('Youtube Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Change Setting', 'geeky-bot')); ?> 
                        </a>  
                    </div>
                </div>
                <div class="geekybot-dashboard-installation-guide-right-crossimage">
                    <button id="geeky-close-banner"><img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/close-icon.png" title="<?php echo esc_html(__('Remove', 'geeky-bot')); ?>" alt="<?php echo esc_html(__('Cross Icon', 'geeky-bot')); ?>" srcset=""></button>
                </div>
            </div>
            <?php
        } ?>
        <div class="geekybot-admin-cp-wrapper">
            <!-- page content -->
            <div class="geekybot-dasdhboard-history-heading">
                <?php echo esc_html(__('Chat History', 'geeky-bot')); ?>
            </div>
            <div class="geekybot-dasdhboard-history-head">
                <p class="geekybot-dasdhboard-history-sendername"><?php echo esc_html(__('Sender', 'geeky-bot')); ?></p>
                <p class="geekybot-dasdhboard-history-sendermessage"><?php echo esc_html(__('Session Type', 'geeky-bot')); ?></p>
                <p class="geekybot-dasdhboard-history-sendermessage"><?php echo esc_html(__('Conversions', 'geeky-bot')); ?></p>
                <p class="geekybot-dasdhboard-history-senttime"><?php echo esc_html(__('Time', 'geeky-bot')); ?></p>
            </div>
            <div class="geekybot-dashboard-history-datawrp">
                <?php
                if(count(geekybot::$_data['chat_history']) > 0){
                    foreach (geekybot::$_data['chat_history'] as $chatHistory) { ?>
                        <div class="geekybot-dasdhboard-history-data">
                            <p class="geekybot-dasdhboard-history-sendername">
                                <span class="geekybot-dasdhboard-history-shot-heading">
                                    <?php echo esc_html(__('Sender', 'geeky-bot')); ?>:
                                </span>
                                <?php
                                if ($chatHistory->user_name != '') {
                                    echo esc_html($chatHistory->user_name);
                                } else {
                                    echo esc_html(__('Guest', 'geeky-bot')); 
                                }
                                ?>
                            </p>
                            <div class="geekybot-dasdhboard-history-sendermessage body-content-message-value">
                                <span class="geekybot-dasdhboard-history-shot-heading">
                                    <?php echo esc_html(__('Session Type', 'geeky-bot')); ?>:
                                </span>
                                <?php
                                if ($chatHistory->type == 1) {
                                    echo esc_html(__('AI ChatBot ', 'geeky-bot'));
                                } else if ($chatHistory->type == 2) {
                                    echo esc_html(__('WooCommerce ', 'geeky-bot'));
                                } else if ($chatHistory->type == 4) {
                                    echo esc_html(__('AI Web Search', 'geeky-bot'));
                                }
                                ?>
                            </div>
                            <div class="geekybot-dasdhboard-history-sendermessage body-content-message-value">
                                <span class="geekybot-dasdhboard-history-shot-heading">
                                    <?php echo esc_html(__('Conversions', 'geeky-bot')); ?>:
                                </span>
                                <?php echo esc_html($chatHistory->conversions); ?>
                            </div>
                            <p class="geekybot-dasdhboard-history-senttime">
                                <span class="geekybot-dasdhboard-history-shot-heading">
                                    <?php echo esc_html(__('Time', 'geeky-bot')); ?>:
                                </span>
                                <?php echo esc_html($chatHistory->created); ?>
                            </p>
                        </div>
                        <?php
                    }
                } else {
                    $msg = esc_html(__('No record found','geeky-bot'));
                    GEEKYBOTlayout::GEEKYBOT_getNoRecordFound($msg);
                }
                ?>
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
    ";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>

