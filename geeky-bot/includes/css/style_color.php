<?php
if (!defined('ABSPATH')){
    die('Restricted Access');
}
$color1 = "#E92E4D";
$color2 = "#FFE3E8";
$color3 = "#000000";
$color4 = "#3E4095";

$color_string_values = get_option("geekybot_set_theme_colors");
if($color_string_values != ''){
    $json_values = json_decode($color_string_values,true);
    if(is_array($json_values) && !empty($json_values)){
        $color1 = esc_attr($json_values['color1']);
        $color2 = esc_attr($json_values['color2']);
        $color3 = esc_attr($json_values['color3']);
        $color4 = esc_attr($json_values['color4']);
    }
}

geekybot::$_colors['color1'] = $color1;
geekybot::$_colors['color2'] = $color2;
geekybot::$_colors['color3'] = $color3;
geekybot::$_colors['color4'] = $color4;

$result = "
div#geekybot-window-title {background-color: $color1}
div#geekybot-send-message .geekybot-window-bottom-inner .geekybot-window-bottom-inner-right .geekybot-window-bottom-send-img {background-color: $color1}
div.geekybot-chat-popup .geekybot-chat-windows .geekybot-chat-window .geekybot-window-top .geekybot-window-top-inner .geekybot-window-top-inner-left .geekybot-window-profile {background-color: $color1}
.geekybot-chat-popup .geekybot-chat-windows .chat-window-one {background-color: $color1}
div.geekybot-chat-open-dialog-main {border: 3px dotted $color1}
div.geekybot-chat-open-dialog-main-inner {background:$color1}
section.geekybot-message-bot-img{background:$color1;}
div.geekybot-chat-popup .geekybot-chat-windows .chat-middle .chat-middle-inner  {border: 3px dashed $color1}
div.geekybot-chat-popup .geekybot-chat-windows .chat-start .chat-start-inner span.chat-start-img {background-color: $color1}
.geekybot-chat-popup .geekybot-chat-windows .geekybot-chat-window div.geekybot-chat-content li.geekybot-message.geekybot-message-user section.geekybot-message-text {background-color: $color2;color: $color3}
.geekybot-chat-popup .geekybot-chat-windows .geekybot-chat-window div.geekybot-chat-content li.geekybot-message.geekybot-message-button .wp-chat-btn {color:  $color1;border: 1px solid  $color1}
.geekybot-chat-popup .geekybot-chat-windows .geekybot-chat-window div.geekybot-chat-content li.geekybot-message.geekybot-message-button .wp-chat-btn span a.wp-chat-btn-link {color:  $color1;}
.geekybot-dropdown-content.show div {background-color: $color1}
.geekybot-dropdown-content{background:$color1;}
.geekybot-chat-popup .geekybot-chat-windows .chat-window-one {background-color: $color1}
.geekybot_wc_product_wrp .geekybot_wc_product_right_wrp .geekybot_wc_product_name a{color: $color4;}
.geekybot_wc_product_wrp .geekybot_wc_product_right_wrp .geekybot_wc_product_action_btn {color: #FFF;border: 1px solid #373435;}
.geekybot_wc_product_wrp .geeky_bot_wc_msg_btn .geeky_bot_wc_btn{border: 1px solid $color1;}
.geekybot_wc_success_action_wrp a{border: 1px solid $color1;}
li.geeky_bot_wc_msg_btn .geeky_bot_wc_btn{border: 1px solid $color1}
.geekybot_wc_cart_item_wrp .geekybot_wc_cart_item .geekybot_wc_cart_item_right .geekybot_wc_cart_item_title a{color: $color4;}
.geekybot_wc_cart_item_wrp .geekybot_wc_cart_item .geekybot_wc_cart_item_qty_change {border: 1px solid $color1;}
.geekybot_wc_cart_item_wrp .geekybot_wc_cart_item .geekybot_wc_cart_item_remove {border: 1px solid $color1;}
.geekybot_wc_cart_checkout {border: 1px solid $color1;}
.geekybot_wc_product_quantity .product_quantity {border: 1px solid $color1;}
 .geekybot_wc_article_header.geekybot_wc_article_title a{color:$color4;}
 .geekybot_wc_product_load_more {border: 1px solid $color1;}
 .geekybot_article_bnt_wrp .geekybot_article_bnt {color: $color1;}
 .geekybot_wc_post_load_more{border: 1px solid $color1;}
 div.geekybot-chat-open-outer-popup-dialog{border: 2px solid $color1;}
 div.geekybot-chat-open-outer-popup-dialog .geekybot-chat-open-outer-popup-dialog-top-cross-button{background-color: $color1;}
 div.geekybot-chat-open-outer-popup-dialog  div.geekybot-chat-open-outer-popup-dialog-image{border:3px dashed $color1;}
 div.geekybot-appearance-right-section button.geekybot-chat-close-button.chat-smart-popup-destroy.active{border-color:$color1;}
 div.geekybot-appearance-right-section button.geekybot-chat-close-button.chat-smart-popup-destroy.active::after{border-color:inherit;border-left-color: transparent !important;border-right-color:transparent !important;}
 button.geekybot-chat-close-button.active::after{border-left-color: transparent;border-right-color:transparent;}
 .geekybot-chat-open-outer-popup-dialog-btmborderwrp::after{border-color:$color1;border-left-color: transparent;border-right-color:transparent;}
 div.geekybot-chat-open-outer-popup-mainwrp .geekybot-chat-open-outer-popup-abandonment{border: 2px solid $color1;}
 div.geekybot-chat-open-outer-popup-mainwrp .geekybot-chat-open-outer-popup-abandonment:hover{background-color:$color1;color:#fff;}
";
$color_string_css = $result;
if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
global $wp_filesystem;
if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ) {
    $creds = request_filesystem_credentials( site_url() );
    wp_filesystem( $creds );
}

$file = GEEKYBOT_PLUGIN_PATH . 'includes/css/style_color.css';
$response = $wp_filesystem->put_contents( $file, $color_string_css );
return 1;



