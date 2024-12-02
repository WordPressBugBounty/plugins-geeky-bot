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
div#window-two-title {background-color: $color1}
div#send-message .window-two-btm-inner .window-two-btm-inner-right .window-two-btm-send-img {background-color: $color1}
div.chat-popup .chat-windows .chat-window-two .window-two-top .window-two-top-inner .window-two-top-inner-left .window-two-profile {background-color: $color1}
.chat-popup .chat-windows .chat-window-one {background-color: $color1}
div.chat-open-dialog-main {border: 3px dotted $color1}
div.chat-open-dialog-main-inner {background:$color1}
section.actual_msg_adm-img{background:$color1;}
div.chat-popup .chat-windows .chat-middle .chat-middle-inner  {border: 3px dashed $color1}
div.chat-popup .chat-windows .chat-start .chat-start-inner span.chat-start-img {background-color: $color1}
.chat-popup .chat-windows .chat-window-two div.chat-content li.actual_msg.actual_msg_user section.actual_msg_text {background-color: $color2;color: $color3}
.chat-popup .chat-windows .chat-window-two div.chat-content li.actual_msg.actual_msg_btn .wp-chat-btn {color:  $color1;border: 1px solid  $color1}
.chat-popup .chat-windows .chat-window-two div.chat-content li.actual_msg.actual_msg_btn .wp-chat-btn span a.wp-chat-btn-link {color:  $color1;}
.dropdown-content.show div {background-color: $color1}
.dropdown-content{background:$color1;}
.chat-popup .chat-windows .chat-window-one {background-color: $color1}
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
 .geekybot_article_bnt_wrp .geekybot_article_bnt {border: 1px solid $color1;}
 .geekybot_wc_post_load_more{border: 1px solid $color1;}
 div.chat-open-outer-popup-dialog{border: 2px solid $color1;}
 div.chat-open-outer-popup-dialog .chat-open-outer-popup-dialog-top-cross-button{background-color: $color1;}
 div.chat-open-outer-popup-dialog  div.chat-open-outer-popup-dialog-image{border:3px dashed $color1;}
 div.geekybot-appearance-right-section button.chat-button-destroy.chat-smart-popup-destroy.active{border-color:$color1;}
 div.geekybot-appearance-right-section button.chat-button-destroy.chat-smart-popup-destroy.active::after{border-color:inherit;border-left-color: transparent !important;border-right-color:transparent !important;}
 button.chat-button-destroy.active::after{border-left-color: transparent;border-right-color:transparent;}
 .chat-open-outer-popup-dialog-btmborderwrp::after{border-color:$color1;border-left-color: transparent;border-right-color:transparent;}
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



