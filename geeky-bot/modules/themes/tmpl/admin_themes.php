<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
wp_enqueue_script('iris');
wp_enqueue_script('plupload');
wp_enqueue_script('plupload-all');

$msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('themes')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($msgkey);
wp_enqueue_style('geekybot-color', GEEKYBOT_PLUGIN_URL . 'includes/css/style_color.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');

$color1 = geekybot::$_data[0]['color1'];
$color2 = geekybot::$_data[0]['color2'];
$color3 = geekybot::$_data[0]['color3'];
$color4 = geekybot::$_data[0]['color4'];

if(geekybot::$_configuration['bot_custom_img'] != '0'){
    $botImgPath = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getBotImagePath();
} else {
    $botImgPath = GEEKYBOT_PLUGIN_URL."includes/images/bot.png";
}
if(geekybot::$_configuration['user_custom_img'] != '0'){
    $userImgPath =GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getUserImagePath();
} else {
    $userImgPath = GEEKYBOT_PLUGIN_URL."includes/images/product/user.png";
}
?>
<?php
$geekybot_js = '
jQuery(document).ready(function(){
    jQuery("select#auto_chat_start").change(function(){
        var isEnable = jQuery(this).val();
        if(isEnable == "1") {
            jQuery(".popup_wrp").slideDown("slow");
            jQuery(".geekybot-config-selection-popup").slideDown("slow");
        } else {
            jQuery(".popup_wrp").slideUp("slow");
            jQuery(".geekybot-config-selection-popup").slideUp("slow");
        }
    });
    jQuery("select#auto_chat_type").change(function(){
        var chat_type = jQuery(this).val();
        if(chat_type == "1") {
            jQuery(".geekybot-config-standard-popup").css("display","none");
            jQuery(".geekybot-config-smart-popup").css("display","block");
        } else {
            jQuery(".geekybot-config-standard-popup").css("display","block");
            jQuery(".geekybot-config-smart-popup").css("display","none");
        }
    });';
    if(geekybot::$_configuration['auto_chat_start'] == 2) {
        $geekybot_js .= '
        jQuery(".popup_wrp").css("display","none");
        jQuery(".geekybot-config-selection-popup").css("display","none");
        ';
    }
    if(geekybot::$_configuration['auto_chat_type'] == 0) {
        $geekybot_js .= '
        jQuery(".geekybot-config-smart-popup").css("display","none");
        ';
    } elseif(geekybot::$_configuration['auto_chat_type'] == 1) {
        $geekybot_js .= '
        jQuery(".geekybot-config-standard-popup").css("display","none");
        ';
    }
    $geekybot_js .= '
});
';
wp_add_inline_script('geekybot-main-js',$geekybot_js);
$enable_disable = array(
    (object) array('id' =>1, 'text' => __('Enable', 'geeky-bot')),
    (object) array('id' => 2, 'text' => __('Disable', 'geeky-bot'))
);
$popup_type = array(
    (object) array('id' => 1, 'text' => __('Smart Popup', 'geeky-bot')),
    (object) array('id' => 0, 'text' => __('Standard Popup', 'geeky-bot'))
);
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'themes','layouts' => 'themes'));?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
            <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data">
            <!-- top head -->
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('Appearance', 'geeky-bot')); ?>
                </h1>
                <div id="geekybot-admin-wrapper" class="geekybot-admin-appearance-wrapper p0 bg-n bs-n">
                    <form id="geekybot-form" class="geekybot-themess" method="post" enctype="multipart/form-data" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_themes&task=savetheme"),"save-theme")) ?>">
                        <div id="tabs" class="tabs">
                            <div class="geekybot-tabInner">
                                <div id="chat_setting">
                                    <div class="geekybot-theme-row">
                                        <div class="geekybot-theme-row-left">
                                            <div class="geekybot-theme-title">
                                                <?php echo esc_html(__('Bot Image', 'geeky-bot')); ?>
                                            </div>
                                            <div class="geekybot-theme-value">
                                                <label for="bot-img" class="geekybot-custom-file-upload">  
                                                    <?php echo esc_attr(__('Choose File','geeky-bot')) ?>
                                                </label>
                                                <input type="file" class="chat-custom-img geekybot-input-text" id="bot-img" name="bot-img">
                                            </div>
                                            <span class="geeky-bot-theme-img-bot">
                                                <?php 
                                                if(geekybot::$_configuration['bot_custom_img'] != '0'){ ?>
                                                    <span class="geeky-bot-theme-img-right">
                                                        <?php echo wp_kses(geekybot::$_configuration['bot_custom_img'], GEEKYBOT_ALLOWED_TAGS); ?>
                                                        <a title="<?php echo esc_attr(__('Delete','geeky-bot')) ?>" onclick="deleteBotCustomImage()">( <?php echo esc_js(__('Delete','geeky-bot')) ?> )</a>
                                                    </span>
                                                    <?php 
                                                } ?>
                                            </span>
                                        </div>
                                        <div class="geekybot-theme-row-right">
                                            <div class="geekybot-theme-title">
                                                <?php echo esc_html(__('User Default Image', 'geeky-bot')); ?>
                                            </div>
                                            <div class="geekybot-theme-value">
                                                <label for="user-img" class="geekybot-custom-file-upload">  
                                                    <?php echo esc_attr(__('Choose File','geeky-bot')) ?>
                                                </label>
                                                <input type="file" class="chat-custom-img geekybot-input-text" id="user-img" name="user-img">
                                            </div>
                                            <span class="geeky-bot-theme-img-user">
                                                <?php 
                                                if(geekybot::$_configuration['user_custom_img'] != '0'){ ?>
                                                    <span class="geeky-bot-theme-img-right">
                                                        <?php echo wp_kses(geekybot::$_configuration['user_custom_img'], GEEKYBOT_ALLOWED_TAGS); ?>
                                                        <a title="<?php echo esc_attr(__('Delete','geeky-bot')) ?>" onclick="deleteSupportUserImage()">( <?php echo esc_html(__('Delete','geeky-bot')) ?> )</a>
                                                    </span>
                                                    <?php 
                                                } ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="geekybot-theme-row">
                                        <div class="geekybot-theme-row-left geekybot-clr-picker">
                                            <div class="geekybot-theme-title">
                                                <?php echo esc_html(__('Primary Color', 'geeky-bot')); ?>
                                                <span style="color: red;">*</span>
                                            </div>
                                            <div class="geekybot-theme-value">
                                                <span style="background:<?php echo esc_attr($color1); ?>;" class="geeky-selected-color-wrp primary-selected-color"></span>
                                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('color1', $color1 , array('class' => 'inputbox one geekybot-form-input-field', 'data-validation' => 'required', 'required'=>'required', 'autocomplete'=>'off')), GEEKYBOT_ALLOWED_TAGS); ?>
                                                <label class="geekybot-dynamic-color geekybot-dynamic-color1"><?php echo esc_html(__('Choose Color', 'geeky-bot')); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="geekybot-theme-row">
                                        <div class="geekybot-theme-row-left geekybot-clr-picker">
                                            <div class="geekybot-theme-title">
                                                <?php echo esc_html(__('User Text Background', 'geeky-bot')); ?>
                                                <span style="color: red;">*</span>
                                            </div>
                                            <div class="geekybot-theme-value">
                                                <span style="background:<?php echo esc_attr($color2); ?>;" class="geeky-selected-color-wrp user-textbg-selected-color"></span>
                                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('color2', $color2 , array('class' => 'inputbox one geekybot-form-input-field', 'data-validation' => 'required', 'required'=>'required', 'autocomplete'=>'off')), GEEKYBOT_ALLOWED_TAGS); ?>
                                                <label class="geekybot-dynamic-color geekybot-dynamic-color2"><?php echo esc_html(__('Choose Color', 'geeky-bot')); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="geekybot-theme-row">
                                        <div class="geekybot-theme-row-left geekybot-clr-picker">
                                            <div class="geekybot-theme-title">
                                                <?php echo esc_html(__('User Text Color', 'geeky-bot')); ?>
                                                <span style="color: red;">*</span>
                                            </div>
                                            <div class="geekybot-theme-value">
                                                <span style="background:<?php echo esc_attr($color3); ?>;" class="geeky-selected-color-wrp user-text-selected-color"></span>
                                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('color3', $color3 , array('class' => 'inputbox one geekybot-form-input-field', 'data-validation' => 'required', 'required'=>'required', 'autocomplete'=>'off')), GEEKYBOT_ALLOWED_TAGS); ?>
                                                <label class="geekybot-dynamic-color geekybot-dynamic-color3"><?php echo esc_html(__('Choose Color', 'geeky-bot')); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="geekybot-theme-row">
                                        <div class="geekybot-theme-row-left geekybot-clr-picker">
                                            <div class="geekybot-theme-title">
                                                <?php echo esc_html(__('Bot Link Color', 'geeky-bot')); ?>
                                                <span style="color: red;">*</span>
                                            </div>
                                            <div class="geekybot-theme-value">
                                                <span style="background:<?php echo esc_attr($color4); ?>;" class="geeky-selected-color-wrp bot-link-selected-color"></span>
                                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('color4', $color4 , array('class' => 'inputbox one geekybot-form-input-field', 'data-validation' => 'required', 'required'=>'required', 'autocomplete'=>'off')), GEEKYBOT_ALLOWED_TAGS); ?>
                                                <label class="geekybot-dynamic-color geekybot-dynamic-color4"><?php echo esc_html(__('Choose Color', 'geeky-bot')); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- preset -->
                                    <div class="geekybot-theme-row">
                                        <div class="geekybot-theme-row-left geekybot-clr-picker">
                                            <div class="geekybot-theme-title">
                                                <?php echo esc_html(__('Presets', 'geeky-bot')); ?>
                                            </div>
                                            <div class="geekybot-theme-value">
                                                <div class="geekybot-appearance-presets-mainwrp">
                                                    <div class="geekybot-appearance-prset">
                                                        <img class="geekybot-appearance-image" alt="screen tag"
                                                            src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/presets/preset-color1.png"
                                                            alt="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>">
                                                            <a id="preset01" class="geekybot-appearance-color-selector" href="#"></a>
                                                    </div>
                                                    <div class="geekybot-appearance-prset">
                                                        <img class="geekybot-appearance-image" alt="screen tag"
                                                            src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/presets/preset-color2.png"
                                                            alt="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>">
                                                            <a id="preset02" class="geekybot-appearance-color-selector second-color" href="#"></a>
                                                    </div>
                                                    <div class="geekybot-appearance-prset">
                                                        <img class="geekybot-appearance-image" alt="screen tag"
                                                            src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/presets/preset-color3.png"
                                                            alt="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>">
                                                            <a id="preset03" class="geekybot-appearance-color-selector third-color" href="#"></a>
                                                    </div>
                                                    <div class="geekybot-appearance-prset">
                                                        <img class="geekybot-appearance-image" alt="screen tag"
                                                            src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/presets/preset-color4.png"
                                                            alt="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>">
                                                            <a id="preset04" class="geekybot-appearance-color-selector fourth-color" href="#"></a>
                                                    </div>
                                                    <div class="geekybot-appearance-prset">
                                                        <img class="geekybot-appearance-image" alt="screen tag"
                                                            src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/presets/preset-color5.png"
                                                            alt="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>">
                                                            <a id="preset05" class="geekybot-appearance-color-selector fifth-color" href="#"></a>
                                                    </div>
                                                    <div class="geekybot-appearance-prset">
                                                        <img class="geekybot-appearance-image" alt="screen tag"
                                                            src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/presets/preset-color6.png"
                                                            alt="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>">
                                                            <a id="preset06" class="geekybot-appearance-color-selector sixth-color" href="#"></a>
                                                    </div>
                                                    <div class="geekybot-appearance-prset">
                                                        <img class="geekybot-appearance-image" alt="screen tag"
                                                            src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/presets/preset-color7.png"
                                                            alt="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('Preset', 'geeky-bot')); ?>">
                                                            <a id="preset07" class="geekybot-appearance-color-selector seventh-color" href="#"></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="geekybot-appearance-right-section">
                                <div class="chat-open-dialog-main">
                                    <div class="chat-open-dialog-main-inner">
                                        <button class="chat-open-dialog active">
                                            <div class="chat-open-dialog-img">
                                                <img class="wp-chat-image" alt="screen tag"
                                                    src="<?php echo esc_url($botImgPath); ?>"
                                                    alt="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>"
                                                    title="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>">
                                            </div>
                                        </button>
                                    </div>
                                </div>
                                <div class="chat-button-destroy-main">
                                    <div class="chat-button-destroy-main-inner">
                                        <button class="chat-button-destroy active"></button>
                                    </div>
                                </div>
                                <div class="chat-popup active chat-init">
                                    <div class="chat-windows chat-main">
                                        <div id="main-messages" class="chat-window-two" style="float: right;">
                                            <div class="geekybot-title-main-overlay">
                                                <div id="window-two-title" class="window-two-top">
                                                    <div class="window-two-top-inner">
                                                        <div class="window-two-top-inner-left">
                                                            <div class="window-two-profile">
                                                                <div class="window-two-profile-img">
                                                                    <img src="<?php echo esc_url($botImgPath); ?>"
                                                                        alt="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>"
                                                                        title="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>">
                                                                </div>
                                                            </div>
                                                            <i class="fa fa-circle"></i>
                                                            <div class="window-two-profile-text">
                                                                <div class="window-two-text">
                                                                    <?php
                                                                    if (geekybot::$_configuration['title'] != '') {
                                                                        $title = geekybot::$_configuration['title'];
                                                                    } else {
                                                                        $title = __('GeekyBot', 'geeky-bot');
                                                                    } ?>
                                                                    <span><?php echo esc_html($title); ?></span>
                                                                    <span><?php echo esc_html(__('online', 'geeky-bot')); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="geekybot-title-overlay"></div>
                                                        </div>
                                                        <div class="window-two-top-inner-right">
                                                            <div class="window-two-top-dot-img" id="dna">
                                                                <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>/includes/images/chat-img/menu.png"
                                                                    alt="<?php echo esc_html(__('Action Icon', 'geeky-bot')); ?>"
                                                                    title="<?php echo esc_html(__('Action', 'geeky-bot')); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div id="previouschatbox" class="chat-content"></div>
                                            <div id="chatbox" class="chat-content">
                                                <li class="actual_msg actual_msg_user">
                                                    <section class="actual_msg_user-img"><img
                                                            src="<?php echo esc_url($userImgPath); ?>"
                                                            alt="<?php echo esc_html(__('User Icon', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('User Image', 'geeky-bot')); ?>">
                                                    </section>
                                                    <section class="actual_msg_text"><?php echo esc_html(__('Hi I am John', 'geeky-bot')); ?></section>
                                                </li>
                                                <li class="actual_msg actual_msg_adm">
                                                    <section class="actual_msg_adm-img">
                                                        <img src="<?php echo esc_url($botImgPath); ?>" alt="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>" title="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>">
                                                    </section>
                                                    <section class="actual_msg_text">
                                                        <div class="geekybot_wc_product_heading"><?php echo esc_html(__('Hi John! How can I help you?', 'geeky-bot')); ?></div>
                                                    </section>
                                                </li>
                                                <li class="actual_msg actual_msg_user">
                                                    <section class="actual_msg_user-img">
                                                        <img src="<?php echo esc_url($userImgPath); ?>"
                                                            alt="<?php echo esc_html(__('User Icon', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('User Image', 'geeky-bot')); ?>">
                                                    </section>
                                                    <section class="actual_msg_text"><?php echo esc_html(__('Can you help me find a new backpack?', 'geeky-bot')); ?></section>
                                                </li>
                                                <li class="actual_msg actual_msg_adm">
                                                    <section class="actual_msg_adm-img"><img
                                                            src="<?php echo esc_url($botImgPath); ?>"
                                                            alt="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>"
                                                            title="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>"></section>
                                                    <section class="actual_msg_text geekybot_wc_product_wrp">
                                                        <div class="geekybot_wc_product_heading"><?php echo esc_html(__('Here are some suggestions.', 'geeky-bot')); ?></div>
                                                        <div class="geekybot_wc_product_wrp">
                                                            <div class="geekybot_wc_product_left_wrp">
                                                                <img width="170"
                                                                    alt="<?php echo esc_html(__('Product Icon', 'geeky-bot')); ?>"
                                                                    title="<?php echo esc_html(__('Fusion Backpack', 'geeky-bot')); ?>"
                                                                    src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/product/mb02-gray-0.jpg"
                                                                    class="attachment-thumbnail size-thumbnail" decoding="async" loading="lazy">
                                                            </div>
                                                            <div class="geekybot_wc_product_right_wrp">
                                                                <div class="geekybot_wc_product_name">
                                                                    <a href="#"><?php echo esc_html(__('Fusion Backpack', 'geeky-bot')); ?></a>
                                                                </div>
                                                                <div class="geekybot_wc_product_price">
                                                                    <span class="woocommerce-Price-amount amount"><bdi><span
                                                                                class="woocommerce-Price-currencySymbol"><?php echo esc_html(__('$', 'geeky-bot')); ?></span>&nbsp;<?php echo esc_html(__('59', 'geeky-bot')); ?></bdi></span>
                                                                </div>
                                                                <div class="geekybot_wc_product_action_btn_wrp">
                                                                    <div class="geekybot_wc_product_action_btn btn-primary"
                                                                        value="">
                                                                        <?php echo esc_html(__('Add to cart', 'geeky-bot')); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </section>
                                                </li>
                                            </div>
                                            <div id="send-message" class="col-md-12 p-2 msg-box window-two-btm">
                                                <div class="window-two-btm-inner">
                                                    <div class="window-two-btm-inner-left">
                                                        <input id="msg_box" type="text" class="border-0 msg_box" placeholder="<?php echo esc_attr(__('Send message', 'geeky-bot')); ?>"
                                                            autocomplete="off">
                                                    </div>
                                                    <div class="window-two-btm-inner-right">
                                                        <div class="window-two-btm-send-img">
                                                            <img id="snd-btn"
                                                                src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/chat-img/send-icon.png"
                                                                alt="<?php echo esc_html(__('Action Icon', 'geeky-bot')); ?>"
                                                                title="<?php echo esc_html(__('Action', 'geeky-bot')); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <span class="geekybot-admin-appearance-subtitle"><?php echo esc_html(__('Welcome Message', 'geeky-bot')); ?></span>
                        <div class="tabs">
                            <div class="geekybot-tabInner">
                                <div class="geekybot-chatbot-popup-config">
                                    <div class="geekybot-chatbot-popup-config-title">
                                        <?php echo esc_html(__('Welcome Message', 'geeky-bot')); ?>
                                    </div>
                                    <div class="geekybot-chatbot-popup-config-value-text">
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_textarea('welcome_message', isset(geekybot::$_data[0]['welcome_message']) ? geekybot::$_data[0]['welcome_message'] : '', array('class' => 'inputbox js-textarea', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS) ?>
                                    </div>
                                </div>
                                <div class="geekybot-theme-row">
                                    <div class="geekybot-theme-row-left">
                                        <div class="geekybot-theme-title">
                                            <?php echo esc_html(__('Welcome Image', 'geeky-bot')); ?>
                                        </div>
                                        <div class="geekybot-theme-value">
                                            <label for="welcome-message-img" class="geekybot-custom-file-upload">  
                                                <?php echo esc_attr(__('Choose File','geeky-bot')) ?>
                                            </label>
                                            <input type="file" class="chat-custom-img geekybot-input-text" id="welcome-message-img" name="welcome-message-img">
                                        </div>
                                        <span class="geeky-bot-theme-img-message">
                                            <?php 
                                            if(geekybot::$_configuration['welcome_message_img'] != '0'){ ?>
                                                <span class="geeky-bot-theme-img-right">
                                                    <?php echo wp_kses(geekybot::$_configuration['welcome_message_img'], GEEKYBOT_ALLOWED_TAGS); ?>
                                                    <a title="<?php echo esc_attr(__('Delete','geeky-bot')) ?>" onclick="deleteWelcomeMessageImg()">( <?php echo esc_js(__('Delete','geeky-bot')) ?> )</a>
                                                </span>
                                                <?php 
                                            } ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="geekybot-appearance-right-section geekybot-opendialog-btm-mainwrp">
                                <div class="chat-open-dialog-main">
                                    <div class="chat-open-dialog-main-inner">
                                        <button class="chat-open-dialog active">
                                            <div class="chat-open-dialog-img">
                                                <img class="wp-chat-image" alt="screen tag"
                                                    src="<?php echo esc_url($botImgPath); ?>"
                                                    alt="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>"
                                                    title="<?php echo esc_html(__('Bot Icon', 'geeky-bot')); ?>">
                                            </div>
                                        </button>
                                    </div>
                                </div>
                                <div class="chat-button-destroy-main">
                                    <div class="chat-button-destroy-main-inner">
                                        <button class="chat-button-destroy chat-smart-popup-destroy active"></button>
                                    </div>
                                </div>
                                <div class="chatbot-popup-wellcome-message">
                                    <div class="chat-open-outer-popup-dialog">
                                        <div class="chat-open-outer-popup-dialog-text-mainwrp">
                                            <?php
                                            if(geekybot::$_configuration['welcome_message_img'] != '0'){
                                                $msgImgPath =GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getWelcomeMessageImagePath(); ?>
                                                <div class="chat-open-outer-popup-dialog-image">
                                                    <img src="<?php echo esc_url($msgImgPath); ?>" alt="<?php echo esc_html(__('Logo', 'geeky-bot')); ?> "title="<?php echo esc_html(__('Logo', 'geeky-bot')); ?>">
                                                </div>
                                                <?php
                                            } ?>
                                            <div class="chat-open-outer-popup-dialog-text">
                                                <p class="chat-open-outer-popup-dialog-btmtext"><?php echo esc_html(geekybot::$_data[0]['welcome_message']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <span class="geekybot-admin-appearance-subtitle"><?php echo esc_html(__('Chat Auto-Popup', 'geeky-bot')); ?></span>
                        <div class="tabs geekybot-appearance-page-chatpopuptab">
                            <div class="geekybot-tabInner geekybot-chatbot-popup-config-mainwrp">
                                <div class="geekybot-chatbot-popup-config">
                                    <div class="geekybot-chatbot-popup-config-title">
                                        <?php echo esc_html(__('Chat Auto-Popup', 'geeky-bot')); ?>
                                    </div>
                                    <div class="geekybot-chatbot-popup-config-value">
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('auto_chat_start', $enable_disable, isset(geekybot::$_data[0]['auto_chat_start']) ? geekybot::$_data[0]['auto_chat_start'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                                    </div>
                                    <div class="geekybot-chatbot-popup-config-description">
                                        <?php echo esc_html(__('Controls whether the chat popup appears automatically on your site.', 'geeky-bot')); ?>
                                    </div>
                                </div>
                                <div class="geekybot-chatbot-popup-config geekybot-selection-popup-row popup_wrp">
                                    <div class="geekybot-chatbot-popup-config-title">
                                        <?php echo esc_html(__('Chat Pop-up Style', 'geeky-bot')); ?>
                                    </div>
                                    <div class="geekybot-chatbot-popup-config-value">
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('auto_chat_type', $popup_type, isset(geekybot::$_data[0]['auto_chat_type']) ? geekybot::$_data[0]['auto_chat_type'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                                    </div>
                                    <div class="geekybot-chatbot-popup-config-description">
                                        <?php echo esc_html(__('Choose how the chat will appear: Smart or Standard pop-up.', 'geeky-bot')); ?>
                                    </div>
                                </div>
                                <div class="geekybot-chatbot-popup-config popup_wrp">
                                    <div class="geekybot-chatbot-popup-config-title">
                                        <?php echo esc_html(__('Chat Auto-Popup Timing', 'geeky-bot')); ?>
                                    </div>
                                    <div class="geekybot-chatbot-popup-config-value">
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('auto_chat_start_time', geekybot::$_data[0]['auto_chat_start_time'], array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS); ?>
                                    </div>
                                    <div class="geekybot-chatbot-popup-config-description">
                                        <?php echo esc_html(__('Define the delay time in seconds for the chatbot to auto-appear on the site.', 'geeky-bot')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="geekybot-appearance-right-section geekybot-appearance-right-popup-section">
                                <div class="geekybot-config-selection-popup">
                                    <div class="geekybot-config-standard-popup">
                                        <p class="geekybot-config-standard-popup-title"><?php echo esc_attr(__('Standard Popup', 'geeky-bot')); ?></p>
                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/standard-bot.png" alt="<?php echo esc_attr(__('Standard Popup', 'geeky-bot')); ?> "title="<?php echo esc_attr(__('Standard Popup', 'geeky-bot')); ?>">
                                    </div>
                                    <div class="geekybot-config-smart-popup">
                                        <p class="geekybot-config-standard-popup-title"><?php echo esc_attr(__('Smart Popup', 'geeky-bot')); ?></p>
                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/smart-bot.png" alt="<?php echo esc_attr(__('Smart Popup', 'geeky-bot')); ?> "title="<?php echo esc_attr(__('Smart Popup', 'geeky-bot')); ?>">
                                    </div>
                                </div>  
                            </div>
                        </div>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('isgeneralbuttonsubmit', 1), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('geekybotlt', 'themes'), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'themes_savetheme'), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                        <div class="geekybot-theme-btn">
                            <button title="<?php echo esc_html(__('Save Changes', 'geeky-bot')); ?>" type="submit" class="button geekybot-theme-save-btn"><img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" srcset=""><?php echo esc_html(__('Save Changes', 'geeky-bot')); ?></button>
                            <div class="geekybot-sugestion-alert-wrp">
                                <div class="geekybot-sugestion-alert">
                                    <strong><?php echo esc_html(__('Note','geeky-bot')).":";?></strong>
                                    <?php echo esc_html(__('If the colors have been saved but the user-side colors are still the same, it is advised to clear the cache.','geeky-bot'));?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$geekybot_js ="
    function submitAppearanceForm(){
        jQuery('form#geekybot-form').submit();
    }
    jQuery(document).ready(function () {
        jQuery.validate();
        makeColorPicker('". $color1."', '". $color2."', '". $color3."', '". $color4."');
        
    });
    function makeColorPicker(color1, color2, color3, color4) {
        jQuery('input#color1').iris({
            palettes: true,
            color: color1,
            onShow: function (colpkr) {
                jQuery(colpkr).fadeIn(500);
                return false;
            },
            onHide: function (colpkr) {
                jQuery(colpkr).fadeOut(500);
                return false;
            },
            change: function (c_event, ui) {
                hex = ui.color.toString();
                jQuery('div#send-message .window-two-btm-inner .window-two-btm-inner-right .window-two-btm-send-img,div#window-two-title,div.chat-popup .chat-windows .chat-window-two .window-two-top .window-two-top-inner .window-two-top-inner-left .window-two-profile').css( 'background-color', hex);
                jQuery('.chat-open-dialog-main').css( 'border-color', hex);
                jQuery('.actual_msg_adm-img, .chat-open-dialog-main-inner').css( 'background', hex);
                jQuery('.chat-button-destroy.active::after').css( 'border-top', hex);
                jQuery('.primary-selected-color').css( 'background', hex);
                jQuery('div.chat-open-outer-popup-dialog').css( 'border-color', hex);
                jQuery('div.chat-open-outer-popup-dialog div.chat-open-outer-popup-dialog-image').css( 'border-color', hex);
                jQuery('div.geekybot-appearance-right-section button.chat-button-destroy.chat-smart-popup-destroy.active').css( 'border-color', hex);
            }
        });
        jQuery('input#color2').iris({
            palettes: true,
            color: color2,
            onShow: function (colpkr) {
                jQuery(colpkr).fadeIn(500);
                return false;
            },
            onHide: function (colpkr) {
                jQuery(colpkr).fadeOut(500);
                return false;
            },
            change: function (c_event, ui) {
                hex = ui.color.toString();
                jQuery('li.actual_msg.actual_msg_user section.actual_msg_text').css( 'background-color', hex);
                jQuery('.user-textbg-selected-color').css( 'background', hex);
            }
        });
        jQuery('input#color3').iris({
            palettes: true,
            color: color3,
            onShow: function (colpkr) {
                jQuery(colpkr).fadeIn(500);
                return false;
            },
            onHide: function (colpkr) {
                jQuery(colpkr).fadeOut(500);
                return false;
            },
            change: function (c_event, ui) {
                hex = ui.color.toString();
                jQuery('li.actual_msg.actual_msg_user section.actual_msg_text').css( 'color', hex);
                jQuery('.user-text-selected-color').css( 'background', hex);
            }
        });
        jQuery('input#color4').iris({
            palettes: true,
            color: color4,
            onShow: function (colpkr) {
                jQuery(colpkr).fadeIn(500);
                return false;
            },
            onHide: function (colpkr) {
                jQuery(colpkr).fadeOut(500);
                return false;
            },
            change: function (c_event, ui) {
                hex = ui.color.toString();
                jQuery('.geekybot_wc_product_name a').css( 'color', hex);
                jQuery('.bot-link-selected-color').css( 'background', hex);
            }
        });
    }
    jQuery(document).ready(function () {
        jQuery(document).click(function (e) {
            if (!jQuery(e.target).is('.colour-picker, .iris-picker, .iris-picker-inner')) {
                jQuery('#color1').iris('hide');
                jQuery('#color2').iris('hide');
                jQuery('#color3').iris('hide');
                jQuery('#color4').iris('hide');
            }
        });
        jQuery('#color1').click(function (event) {
            jQuery('#color1').iris('hide');
            jQuery('#color2').iris('hide');
            jQuery('#color3').iris('hide');
            jQuery('#color4').iris('hide');
            jQuery(this).iris('show');
            return false;
        });
        jQuery('#color2').click(function (event) {
            jQuery('#color1').iris('hide');
            jQuery('#color2').iris('hide');
            jQuery('#color3').iris('hide');
            jQuery('#color4').iris('hide');
            jQuery(this).iris('show');
            return false;
        });
        jQuery('#color3').click(function (event) {
            jQuery('#color1').iris('hide');
            jQuery('#color2').iris('hide');
            jQuery('#color3').iris('hide');
            jQuery('#color4').iris('hide');
            jQuery(this).iris('show');
            return false;
        });
        jQuery('#color4').click(function (event) {
            jQuery('#color1').iris('hide');
            jQuery('#color2').iris('hide');
            jQuery('#color3').iris('hide');
            jQuery('#color4').iris('hide');
            jQuery(this).iris('show');
            return false;
        });
        jQuery('.geekybot-dynamic-color1').click(function (event) {
            jQuery('#color1').iris('show');
            return false;
        });
        jQuery('.geekybot-dynamic-color2').click(function (event) {
            jQuery('#color2').iris('show');
            return false;
        });
        jQuery('.geekybot-dynamic-color3').click(function (event) {
            jQuery('#color3').iris('show');
            return false;
        });
        jQuery('.geekybot-dynamic-color4').click(function (event) {
            jQuery('#color4').iris('show');
            return false;
        });
    });

    function deleteBotCustomImage(){
        jQuery.post(ajaxurl, {action: 'geekybot_ajax', geekybotme: 'themes', task: 'deleteBotCustomImage', '_wpnonce':'".esc_attr(wp_create_nonce('delete-bot-custom-image'))."'}, function (data) {
            if(data){
                jQuery('.geeky-bot-theme-img-bot').css('display','none');
            }
        });
    }

    function deleteWelcomeMessageImg(){
        jQuery.post(ajaxurl, {action: 'geekybot_ajax', geekybotme: 'themes', task: 'deleteWelcomeMessageImg', '_wpnonce':'".esc_attr(wp_create_nonce('delete-message-image'))."'}, function (data) {
            if(data){
                jQuery('.geeky-bot-theme-img-message').css('display','none');
                jQuery('.chat-open-outer-popup-dialog-image').css('display','none');
            }
        });
    } 
    function deleteSupportUserImage(){
        jQuery.post(ajaxurl, {action: 'geekybot_ajax', geekybotme: 'themes', task: 'deleteSupportUserImage', '_wpnonce':'". esc_attr(wp_create_nonce("delete-support-user-image"))."'}, function (data) {
            if(data){

              jQuery('.geeky-bot-theme-img-user').css('display','none');
            }
        });
    } 
    
    function setPresetColors(color1, color2, color3, color4){
        jQuery('input#color1').val(color1);
        jQuery('input#color2').val(color2);
        jQuery('input#color3').val(color3);
        jQuery('input#color4').val(color4);
        <!-- color1 -->
        jQuery('div#send-message .window-two-btm-inner .window-two-btm-inner-right .window-two-btm-send-img,div#window-two-title,div.chat-popup .chat-windows .chat-window-two .window-two-top .window-two-top-inner .window-two-top-inner-left .window-two-profile').css( 'background-color', color1);
        jQuery('.chat-open-dialog-main').css( 'border-color', color1);
        jQuery('.actual_msg_adm-img, .chat-open-dialog-main-inner').css( 'background', color1);
        jQuery('.chat-button-destroy.active::after').css( 'border-top', color1);
        jQuery('.primary-selected-color').css( 'background', color1);
        jQuery('div.chat-open-outer-popup-dialog').css( 'border-color', color1);
        jQuery('div.chat-open-outer-popup-dialog div.chat-open-outer-popup-dialog-image').css( 'border-color', color1);
        jQuery('div.geekybot-appearance-right-section button.chat-button-destroy.chat-smart-popup-destroy.active').css( 'border-color', color1);
        <!-- color2 -->
        jQuery('li.actual_msg.actual_msg_user section.actual_msg_text').css( 'background-color', color2);
        jQuery('.user-textbg-selected-color').css( 'background', color2);
        <!-- color3 -->
        jQuery('li.actual_msg.actual_msg_user section.actual_msg_text').css( 'color', color3);
        jQuery('.user-text-selected-color').css( 'background', color3);
        <!-- color4 -->
        jQuery('.geekybot_wc_product_name a').css( 'color', color4);
        jQuery('.bot-link-selected-color').css( 'background', color4);
    }

    jQuery(document).ready(function() {
        jQuery('#preset01').click(function (e) {
            e.preventDefault();
            var div = jQuery(this).parent();
            var color1 = '#E92E4D';
            var color2 = '#FFE3E8';
            var color3 = '#000000';
            var color4 = '#3E4095';
            setPresetColors(color1, color2, color3, color4);
        });
        jQuery('#preset02').click(function (e) {
            e.preventDefault();
            var div = jQuery(this).parent();
            var color1 = '#622AE8';
            var color2 = '#622AE8';
            var color3 = '#FFFFFF';
            var color4 = '#3E4095';
            setPresetColors(color1, color2, color3, color4);
        });
        jQuery('#preset03').click(function (e) {
            e.preventDefault();
            var div = jQuery(this).parent();
            var color1 = '#F99513';
            var color2 = '#E6E7E8';
            var color3 = '#282829';
            var color4 = '#3E4095';
            setPresetColors(color1, color2, color3, color4);
        });
        jQuery('#preset04').click(function (e) {
            e.preventDefault();
            var div = jQuery(this).parent();
            var color1 = '#282829';
            var color2 = '#282829';
            var color3 = '#FFFFFF';
            var color4 = '#3E4095';
            setPresetColors(color1, color2, color3, color4);
        });
        jQuery('#preset05').click(function (e) {
            e.preventDefault();
            var div = jQuery(this).parent();
            var color1 = '#57A695';
            var color2 = '#E6F4F3';
            var color3 = '#373435';
            var color4 = '#3E4095';
            setPresetColors(color1, color2, color3, color4);
        });
        jQuery('#preset06').click(function (e) {
            e.preventDefault();
            var div = jQuery(this).parent();
            var color1 = '#A8518A';
            var color2 = '#A8518A';
            var color3 = '#FFFFFF';
            var color4 = '#3E4095';
            setPresetColors(color1, color2, color3, color4);
        });
        jQuery('#preset07').click(function (e) {
            e.preventDefault();
            var div = jQuery(this).parent();
            var color1 = '#0044E9';
            var color2 = '#0044E9';
            var color3 = '#FFFFFF';
            var color4 = '#3E4095';
            setPresetColors(color1, color2, color3, color4);
        });
    });
";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
