<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
    
    $botImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getBotImagePath();
    $geekybot_js = "

    function geekybotAddToCart(pid) {
        var message = '".esc_html(__('Add to cart', 'geeky-bot'))."';
        SaveChathistory(message,'user');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotAddToCart', productid: pid, '_wpnonce':'".esc_attr(wp_create_nonce("add-to-cart")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(120);
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+data+\"</section></li>\");
            }
        });
    }

    function getProductAttributes(pid, isnew, attr) {
        // var attributes_recheck = JSON.parse(attr);
        var attributes = attr;
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'getProductAttributes', productid: pid, isnew: isnew, attr: attributes, '_wpnonce':'".esc_attr(wp_create_nonce("product-attributes")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(100);
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+data+\"</section></li>\");
            }
        });
    }

    function saveProductAttributeToSession(productid, attributekey, attributevalue, userattributes) {
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'saveProductAttributeToSession', productid: productid, attributekey: attributekey, attributevalue: attributevalue, userattributes: userattributes, '_wpnonce':'".esc_attr(wp_create_nonce("save-product-attribute")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(100);
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+data+\"</section></li>\");
                
            }
        });
    }

    function showProductsList(msg, data, current_page) {
        var message = '".esc_html(__('Show Products', 'geeky-bot'))."';
        SaveChathistory(message,'user');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'showProductsList', msg: msg, data: data, currentPage: current_page, '_wpnonce':'".esc_attr(wp_create_nonce("products-list")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(100);
                var message = geekybot_DecodeHTML(data);
                jQuery(\".geekybot_wc_product_load_more\").css(\"display\", \"none\");
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                
            }
        });
    }

    function geekybotLoadMoreProducts(msg, next_page, function_name, dataArray) {
        var message = '".esc_html(__('Show More', 'geeky-bot'))."';
        SaveChathistory(message,'user');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'geekybot', task: 'geekybotLoadMoreProducts', msg: msg, next_page: next_page,functionName : function_name,data : dataArray, '_wpnonce':'".esc_attr(wp_create_nonce("load-more")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(190);
                var message = geekybot_DecodeHTML(data)
                jQuery(\"div.geekybot_wc_product_load_more_wrp\").css(\"display\", \"none\");
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                
            }
        });
    }

    function geekybotRemoveCartItem(variation_id, product_id) {
        var message = '".esc_html(__('Remove Item', 'geeky-bot'))."';
        SaveChathistory(message,'user');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotRemoveCartItem', variation_id: variation_id, product_id: product_id, '_wpnonce':'".esc_attr(wp_create_nonce("remove-item")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(100);
                var message = geekybot_DecodeHTML(data)
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                
            }
        });
    }

    function geekybotUpdateCartItemQty(cart_item_key) {
        var message = '".esc_html(__('Change quantity', 'geeky-bot'))."';
        SaveChathistory(message,'user');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotUpdateCartItemQty', cart_item_key: cart_item_key, '_wpnonce':'".esc_attr(wp_create_nonce("update-quantity")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(100);
                var message = geekybot_DecodeHTML(data)
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                
            }
        });
    }

    function geekybotUpdateCartItemQuantity(cart_item_key,product_id) {
        const clickedSpan = jQuery(event.target);
        var product_quantity = clickedSpan.siblings('input#product_quantity').val();
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotUpdateCartItemQuantity', product_quantity: product_quantity, cart_item_key: cart_item_key, '_wpnonce':'".esc_attr(wp_create_nonce("update-quantity")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(100);
                var message = geekybot_DecodeHTML(data)
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                
            }
        });
    }
    
    function geekybotViewCart() {
        var message = '".esc_html(__('View Cart', 'geeky-bot'))."';
        SaveChathistory(message,'user');
        var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotViewCart', '_wpnonce':'".esc_attr(wp_create_nonce("view-cart")) ."'}, function (data) {
            if (data) {
                geekybot_scrollToTop(100);
                var message = geekybot_DecodeHTML(data)
                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
            }
        });
    }";
    wp_register_script( 'geekybot-frontend-handle', '' , array(), GEEKYBOT_PLUGIN_VERSION, 'all');
    wp_enqueue_script( 'geekybot-frontend-handle' );
    wp_add_inline_script('geekybot-frontend-handle',$geekybot_js);
?>



