<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTwoocommerceModel {

    function getMessagekey(){
        $key = 'woocommerce';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    function __construct(){
        add_filter( 'wp_ajax_showAllProducts',array( $this,'geekybot_showAllProducts'), 10, 2);
        add_filter( 'wp_ajax_searchProduct',array( $this,'geekybot_searchProduct'), 10, 2);
        add_filter( 'wp_ajax_getProductsUnderPrice',array( $this,'geekybot_getProductsUnderPrice'), 10, 2);
        add_filter( 'wp_ajax_getProductsAbovePrice',array( $this,'geekybot_getProductsAbovePrice'), 10, 2);
        add_filter( 'wp_ajax_getProductsBetweenPrice',array( $this,'geekybot_getProductsBetweenPrice'), 10, 2);
        add_filter( 'wp_ajax_viewCart',array( $this,'geekybot_viewCart'), 10, 2);
        add_filter( 'wp_ajax_checkOut',array( $this,'geekybot_checkOut'), 10, 2);
    }

    function geekybot_showAllProducts($msg, $data, $currentPage = 1) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        // get the number of products to display per page
        $productsPerPage = geekybot::$_configuration['pagination_product_page_size'];
        $args = array(
            'post_type' => 'product',
            'orderby'  => 'ID',
            'order' => 'ASC',
            'posts_per_page' => $productsPerPage, // number of products per page
            'paged' => $currentPage, // The current page number
            'post_status' => 'publish', // Only return published products
        );
        $products = wc_get_products($args);
        if($products){
            $allProducts = wp_count_posts('product')->publish;
            $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($msg, $products, 'story', $allProducts, $currentPage, 'woocommerce', 'geekybot_showAllProducts', $data);
        } else {
            $html = __("No product was found.", "geeky-bot");
        }
        return $html; 
        wp_die();
    }

    function geekybot_readVariablesGetAttributes($user_attributes, $attribute, $product) {

        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        if (!is_null($user_attributes)) {
            if(!is_array($user_attributes)) {
                $user_attributes = json_decode($user_attributes,true);
            }
            if (is_array($user_attributes)) {
                foreach ($user_attributes as $key => $value) {
                    $query = "SELECT variable_for FROM `" . geekybot::$_db->prefix . "geekybot_slots` where name = '".esc_sql($attribute)."'";
                    $slot = geekybot::$_db->get_var($query);
                    // recheck
                    if ($slot == 'attribute' && $key == $attribute) {
                    // if ($key == $attribute) {
                        $pattributes = $product->get_attributes();
                        foreach ($pattributes as $pattrkey => $pattrvalue) {
                            $options = $pattrvalue->get_options();
                            if(in_array($value, $options)){
                                // store the attributes data in the session
                                $productid = $product->get_id();
                                $attributekey = $key;
                                $attributevalue = $value;
                                geekybot::$_geekybotsessiondata->geekybot_addSessionAttributeDataToTable($productid, $attributekey, $attributevalue);
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    function geekybot_readVariablesGetProduct($variables) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        $product = [];
        $comma_separated_name = implode("','", array_keys($variables));
        $query = "SELECT name FROM `" . geekybot::$_db->prefix . "geekybot_slots` where name IN ('".$comma_separated_name."') AND variable_for = 'product'";
        $slot = geekybot::$_db->get_var($query);
        if (isset($slot)) {
            $productName = geekybotphplib::GEEKYBOT_str_replace('-', ' ', $variables[$slot]);
            $product[$slot] = $productName;
        }
        return $product;
    }

    function geekybot_searchProduct($msg, $data, $currentPage = 1) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return $return['text'] = __("WooCommerce is currently inactive.", "geeky-bot");
        }
        // get the variable for product name from the given list of variable in the parameter
        $search_key = 'woo_product_name';
        if (isset($data[$search_key])) {
            $search_value = $data[$search_key];
        } else {
            return __("Please enter a valid product or category name.", "geeky-bot");
        }
        // Set the number of products to display per page
        $productsPerPage = geekybot::$_configuration['pagination_product_page_size'];
        $args = array(
            'category' => array( $search_value ),
            'post_type' => 'product',
            'orderby'  => 'ID',
            'order' => 'ASC',
            'posts_per_page' => $productsPerPage, // number of products per page
            'paged' => $currentPage, // The current page number
            'post_status' => 'publish', // Only return published products
        );
        $products = wc_get_products($args);
        $html = '';
        if($products){
            $args = array(
                'category' => array( $search_value ),
                'post_type' => 'product',
                'post_status' => 'publish', // Only return published products
                'limit'       => -1,
                'return'      => 'ids' // Fetch only the IDs reduces memory usage and query execution time.
            );
            $product_ids = wc_get_products($args);
            $allProducts = count($product_ids); // Get the total count of products
            $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($msg, $products, 'story', $allProducts, $currentPage, 'woocommerce', 'geekybot_searchProduct', $data);
        }else{
            $args = array(
                'name' => $search_value, // Search parameter for product title
                'post_type' => 'product', // Specify product post type
                'orderby' => 'ID', // Order by product ID
                'order' => 'ASC',
                'posts_per_page' => $productsPerPage, // number of products per page
                'paged' => $currentPage, // The current page number
                'post_status' => 'publish', // Only return published products
            );
            $products = wc_get_products($args);
            if($products){
                $args = array(
                    'name' => $search_value, // Search parameter for product title
                    'post_type' => 'product', // Specify product post type
                    'post_status' => 'publish', // Only return published products
                    'limit'       => -1,
                    'return'      => 'ids' // Fetch only the IDs reduces memory usage and query execution time.
                );
                $product_ids = wc_get_products($args);
                $allProducts = count($product_ids); // Get the total count of products
                $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($msg, $products, 'story', $allProducts, $currentPage, 'woocommerce', 'geekybot_searchProduct', $data);
            } else {
                // call the fallback
                $fallbackResult = $this->getProductsFromWcFirstFallback($msg, $currentPage, $productsPerPage);
                $products = $fallbackResult['products'];
                $all_products = $fallbackResult['count'];
                if($products){
                    $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($msg, $products, 'fallbackone', $all_products, $currentPage, 'woocommerce', 'geekybot_searchProduct', $data);
                } else {
                    $fallbackProducts = $this->getProductsFromWcSecondFallback($msg, $currentPage, $productsPerPage);
                    $products = $fallbackProducts['products'];
                    $all_products = $fallbackProducts['count'];;
                    if($products){
                        $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($msg, $products, 'fallbacktwo', $all_products, $currentPage, 'woocommerce', 'geekybot_searchProduct', $data);
                    } else {
                        $html = __("No product was found.", "geeky-bot");
                    }
                    // add "show Articles" option in response in the case of woocommerce fallback two
                    if (geekybot::$_configuration['is_posts_enable'] == 1) {
                        if (isset($html) && $html != '') {
                            $articleType = 1;
                        } else {
                            $articleType = 2;
                        }
                        $articleButtonHtml = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->getArticlesButton($msg, $articleType);
                        // check if some related posts found
                        if (isset($articleButtonHtml) && $articleButtonHtml != '') {
                            // Modified bot response if it exists
                            $html .= $articleButtonHtml;
                        }
                    }
                }
            }
        }
        return $html; 
        wp_die();
    }

    function geekybot_getProductsUnderPrice($msg, $data, $currentPage = 1) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return $return['text'] = __("WooCommerce is currently inactive.", "geeky-bot");
        }
        // get the variable for product name from the given list of variable in the parameter
        $search_key = 'woo_product_price';
        if (isset($data[$search_key])) {
            $max_price = $data[$search_key];
            $max_price = geekybot_normalize_price_with_wc($max_price);
        } else {
            return __("Please enter a valid price.", "geeky-bot");
        }
        // Set the number of products to display per page
        $productsPerPage = geekybot::$_configuration['pagination_product_page_size'];
        // Calculate the offset based on the current page and products per page
        $offset = ($currentPage - 1) * $productsPerPage;
        $args = array(
            'post_type' => array('product', 'product_variation'), // Include both products and variations
            'orderby'  => 'ID',
            'order' => 'ASC',
            'post_status' => 'publish', // Only return published products
            'numberposts' => -1, // Retrieve all matching products
            'meta_query' => array(
                array(
                    'key' => '_price', // Price field in WooCommerce
                    'value' => $max_price,
                    'compare' => '<=',
                    'type' => 'NUMERIC',
                ),
            ),
        );
        $allProducts = wc_get_products($args);
        $filteredProducts = array();

        foreach ($allProducts as $product) {
            // Get all variations for this variable product
            $variations = $product->get_available_variations();
            $all_variations_below_price = true;
            foreach ($variations as $variation) {
                $variation_obj = new WC_Product_Variation($variation['variation_id']);
                $variation_price = $variation_obj->get_price();

                // If any variation has a price greater than the max price, skip this product
                if ($variation_price > $max_price) {
                    $all_variations_below_price = false;
                    break;
                }
            }
            // If all variations have prices below or equal to max_price, include the parent product
            if ($all_variations_below_price) {
                $filteredProducts[] = $product;
            }
        }
        $allProducts = count($filteredProducts);
        // Slice the products array to get the products for the current page
        $products = array_slice($filteredProducts, $offset, $productsPerPage);
        $html = '';
        if($products){
            $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($msg, $products, 'story', $allProducts, $currentPage, 'woocommerce', 'geekybot_getProductsUnderPrice', $data);
        }
        return $html; 
        wp_die();
    }

    function geekybot_getProductsAbovePrice($msg, $data, $currentPage = 1) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return $return['text'] = __("WooCommerce is currently inactive.", "geeky-bot");
        }
        // get the variable for product name from the given list of variable in the parameter
        $search_key = 'woo_product_price';
        if (isset($data[$search_key])) {
            $min_price = $data[$search_key];
            $min_price = geekybot_normalize_price_with_wc($min_price);
        } else {
            return __("Please enter a valid price.", "geeky-bot");
        }
        // Set the number of products to display per page
        $productsPerPage = geekybot::$_configuration['pagination_product_page_size'];
        // Calculate the offset based on the current page and products per page
        $offset = ($currentPage - 1) * $productsPerPage;
        $args = array(
            'post_type' => array('product', 'product_variation'), // Include both products and variations
            'orderby'  => 'ID',
            'order' => 'ASC',
            'post_status' => 'publish', // Only return published products
            'numberposts' => -1, // Retrieve all matching products
            'meta_query' => array(
                array(
                    'key' => '_price', // Price field in WooCommerce
                    'value' => $min_price,
                    'compare' => '>=',
                    'type' => 'NUMERIC',
                ),
            ),
        );
        $allProducts = wc_get_products($args);
        $filteredProducts = array();

        foreach ($allProducts as $product) {
            // Get all variations for this variable product
            $variations = $product->get_available_variations();
            $all_variations_above_price = true;
            foreach ($variations as $variation) {
                $variation_obj = new WC_Product_Variation($variation['variation_id']);
                $variation_price = $variation_obj->get_price();

                // If any variation has a price greater than the max price, skip this product
                if ($variation_price < $min_price) {
                    $all_variations_above_price = false;
                    break;
                }
            }
            // If all variations have prices below or equal to min_price, include the parent product
            if ($all_variations_above_price) {
                $filteredProducts[] = $product;
            }
        }
        $allProducts = count($filteredProducts);
        // Slice the products array to get the products for the current page
        $products = array_slice($filteredProducts, $offset, $productsPerPage);
        $html = '';
        if($products){
            $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($msg, $products, 'story', $allProducts, $currentPage, 'woocommerce', 'geekybot_getProductsAbovePrice', $data);
        }
        return $html; 
        wp_die();
    }

    function geekybot_getProductsBetweenPrice($msg, $data, $currentPage = 1) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return $return['text'] = __("WooCommerce is currently inactive.", "geeky-bot");
        }
        // get the variable for product name from the given list of variable in the parameter
        if (isset($data['woo_product_price_from']) && isset($data['woo_product_price_to'])) {
            $min_price = $data['woo_product_price_from'];
            $max_price = $data['woo_product_price_to'];
            $min_price = geekybot_normalize_price_with_wc($min_price);
            $max_price = geekybot_normalize_price_with_wc($max_price);
        } else {
            return __("Please enter a valid price range.", "geeky-bot");
        }
        // Set the number of products to display per page
        $productsPerPage = geekybot::$_configuration['pagination_product_page_size'];
        // Calculate the offset based on the current page and products per page
        $offset = ($currentPage - 1) * $productsPerPage;
        $args = array(
            'post_type' => array('product', 'product_variation'), // Include both products and variations
            'orderby'  => 'ID',
            'order' => 'ASC',
            'post_status' => 'publish', // Only return published products
            'numberposts' => -1, // Retrieve all matching products
            'meta_query' => array(
                array(
                    'key' => '_price', // Price field in WooCommerce
                    'value' => array($min_price, $max_price), // Array for the range
                    'compare' => 'BETWEEN', // Use BETWEEN for price range
                    'type' => 'NUMERIC',
                ),
            ),
        );
        $allProducts = wc_get_products($args);
        $filteredProducts = array();

        foreach ($allProducts as $product) {
            // Get all variations for this variable product
            $variations = $product->get_available_variations();
            $all_variations_between_price = true;
            foreach ($variations as $variation) {
                $variation_obj = new WC_Product_Variation($variation['variation_id']);
                $variation_price = $variation_obj->get_price();

                // If any variation does not fall within the price range, exclude the product
                if ($variation_price < $min_price || $variation_price > $max_price) {
                    $all_variations_between_price = false;
                    break;
                }
            }

            // If all variations have prices below or equal to max_price, include the parent product
            if ($all_variations_between_price) {
                $filteredProducts[] = $product;
            }
        }
        $allProducts = count($filteredProducts);
        // Slice the products array to get the products for the current page
        $products = array_slice($filteredProducts, $offset, $productsPerPage);
        $html = '';
        if($products){
            $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($msg, $products, 'story', $allProducts, $currentPage, 'woocommerce', 'geekybot_getProductsBetweenPrice', $data);
        }
        return $html; 
        wp_die();
    }

    function getProductsFromWcFirstFallback($msg, $currentPage, $productsPerPage) {
        $offset = ($currentPage - 1) * $productsPerPage;
        // match the products ids from the geekybot products table
        $query = 'SELECT COUNT(`product_id`) FROM `' . geekybot::$_db->prefix . 'geekybot_products` WHERE MATCH (product_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND status = "publish"';
        $allFallbackProducts = geekybotdb::GEEKYBOT_get_var($query);
        $query = 'SELECT `product_id`, MATCH (product_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_products` WHERE MATCH (product_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND status = "publish"';
        $query .= " ORDER BY score DESC ";
        $query .= " LIMIT $productsPerPage OFFSET $offset";
        // Get paginated posts
        $fallbackProducts = geekybotdb::GEEKYBOT_get_results($query);
        // get products data against above ids
        $allProducts = array();
        foreach ( $fallbackProducts as $fallbackProduct ) {
            $product_id = $fallbackProduct->product_id;
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $allProducts[] = $product;
            }
        }
        $data['products'] = $allProducts;
        $data['count'] = $allFallbackProducts;
        return $data;
    }

    function getProductsFromWcSecondFallback($msg, $currentPage, $productsPerPage) {
        $offset = ($currentPage - 1) * $productsPerPage;
        // match the products ids from the geekybot products table
        $query = 'SELECT COUNT(`product_id`) FROM `' . geekybot::$_db->prefix . 'geekybot_products` WHERE MATCH (product_description) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND status = "publish"';
        $allFallbackProducts = geekybotdb::GEEKYBOT_get_var($query);
        $query = 'SELECT `product_id`, MATCH (product_description) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_products` WHERE MATCH (product_description) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND status = "publish"';
        $query .= " ORDER BY score DESC ";
        $query .= " LIMIT $productsPerPage OFFSET $offset";
        // Get paginated posts
        $fallbackProducts = geekybotdb::GEEKYBOT_get_results($query);
        // get products data against above ids
        $allProducts = array();
        foreach ( $fallbackProducts as $fallbackProduct ) {
            // score check
            if ($fallbackProduct->score > 0.9) {
                $product_id = $fallbackProduct->product_id;
                $product = wc_get_product( $product_id );
                if ( $product ) {
                    $allProducts[] = $product;
                }
            }
        }
        $data['products'] = $allProducts;
        $data['count'] = $allFallbackProducts;
        return $data;
    }

    function geekybot_viewCart($msg, $data) {
        if (!class_exists('WooCommerce')) {
            return $return['text'] = __("WooCommerce is currently inactive.", "geeky-bot");
        }
        global $woocommerce;

        if ( $woocommerce->cart->is_empty() ) {
            return $text = "<div>".__("Your cart is currently empty.", "geeky-bot")."</div>";
            wp_die();
            return; // Don't display popup if cart is empty
        }

        $cart_items = $woocommerce->cart->get_cart();
        $cart_subtotal = $woocommerce->cart->get_subtotal();
        $shipping_total = $woocommerce->cart->get_cart_shipping_total();
        $grand_total = $woocommerce->cart->get_total();
        // $grand_total = $woocommerce->cart->total;
        $grand_total = str_replace('"', "'", $grand_total);

        
        // Open the popup container (replace with your desired trigger/button)
        $text = "
        <div id='my-cart-popup' class='geekybot-cart-popup'>";
            $text .= "
            <div class='geekybot_wc_product_heading'>" . __('Cart Details', 'geeky-bot').':' . "</div>";
            $text .= "
            <div class='geekybot_wc_cart_item_wrp'>";
                // Loop through cart items and display details
                foreach ( $cart_items as $cart_item_key => $cart_item ) {
                    $product_id = $cart_item['product_id'];
                    $product_quantity = $cart_item['quantity'];

                    $item_subtotal = $product_quantity * $cart_item['data']->get_price();
                    $formatted_item_subtotal = wc_price( $item_subtotal );
                    $_product = wc_get_product($product_id);
                    $thumbnail = get_the_post_thumbnail_url( $product_id, 'thumbnail' ); // Adjust image size as needed
                    $product_price = $_product->get_price_html();
                    $product_price = str_replace('"', "'", $product_price);

                    $text .= "
                    <div class='geekybot_wc_cart_item'>
                        <div class='geekybot_wc_cart_item_left'>";
                            if ( $thumbnail ) {
                                $text .= "<img src='" . $thumbnail . "' alt='" . $_product->get_name() . "' class='geekybot_wc_cart_item_image'>";
                            } else {
                                $default_product_image_url = wc_placeholder_img_src();
                                if ( $default_product_image_url ) {
                                    $text .= "<img src='" . $default_product_image_url . "' alt='" . $_product->get_name() . "' class='geekybot_wc_cart_item_image'>";
                                }
                            }
                            $text .= "
                        </div>
                        <div class='geekybot_wc_cart_item_right'>
                            <div class='geekybot_wc_cart_item_title'>
                                <a href='".$_product->get_permalink()."' target='_blank'>
                                    ".$_product->get_name()."
                                </a>
                            </div>
                            <div class='geekybot_wc_cart_item_attr'>
                                <p class='geekybot_wc_cart_item_attr_title'>
                                    " . __('Price', 'geeky-bot').": " . "
                                </p>
                                <p class='geekybot_wc_cart_item_attr_value'>
                                    " . $product_price . "
                                </p>
                            </div>";
                            if ( $cart_item['variation_id'] ) {
                                $variation = new WC_Product_Variation( $cart_item['variation_id'] );
                                $variation_attributes = $variation->get_variation_attributes();
                                foreach ( $variation_attributes as $attribute_name => $attribute_value ) {
                                    $filteredKey =  explode('_', $attribute_name);
                                    $filteredKey =  end($filteredKey);
                                    $filteredKey = ucfirst($filteredKey);
                                    $text .= "
                                    <div class='geekybot_wc_cart_item_attr'>
                                        <p class='geekybot_wc_cart_item_attr_title'>
                                            " . $filteredKey.": " . "
                                        </p>
                                        <p class='geekybot_wc_cart_item_attr_value'>
                                            " . $attribute_value . "
                                        </p>
                                    </div>";
                                }
                            }
                            $text .= "
                            <div class='geekybot_wc_cart_item_attr'>
                                <p class='geekybot_wc_cart_item_attr_title'>
                                    " . __('Quantity', 'geeky-bot').": " . "
                                </p>
                                <p class='geekybot_wc_cart_item_attr_value'>
                                    " . $product_quantity . "
                                </p>
                                <span class='geekybot_wc_cart_item_qty_change' onclick=\"geekybotUpdateCartItemQty('".$cart_item_key."');\">
                                    " . __('Change', 'geeky-bot') . "
                                </span>
                            </div>";
                            $text .= " 
                        </div>";// Close right side
                        $text .= "
                        <div class='geekybot_wc_cart_item_bottom'>
                            <div class='geekybot_wc_cart_item_totalp'>
                                " . __('Total Price', 'geeky-bot').": ".$formatted_item_subtotal . "
                            </div>
                            <div class='geekybot_wc_cart_item_remove' onclick='geekybotRemoveCartItem(".$cart_item['variation_id'].",".$cart_item['product_id'].");'>
                                " . __('Remove Item', 'geeky-bot') . "
                            </div>
                        </div>";
                    $text .= "</div>"; // Close cart-item wrp
                }
            $text .= "</div>"; // Close cart-items wrp
            $text .= "
            <div class='geekybot_wc_cart_total geekybot_wc_cart_subtotal'>
                <p class='geekybot_wc_cart_total_title'>
                    " . __('Subtotal', 'geeky-bot').": " . "
                </p>
                <p class='geekybot_wc_cart_total_value'>
                    " . $cart_subtotal . "
                </p>
            </div>
            <div class='geekybot_wc_cart_total'>
                <p class='geekybot_wc_cart_total_title'>
                    " . __('Total', 'geeky-bot').": " . "
                </p>
                <p class='geekybot_wc_cart_total_value'>
                    " . $grand_total . "
                </p>
            </div>
            <a class='geekybot_wc_cart_checkout' target='_blank' href='" . wc_get_checkout_url() . "' class='button'>" . __('Proceed to Checkout', 'geeky-bot') ."</a>
        </div>";
        $text = geekybotphplib::GEEKYBOT_htmlentities($text);
        $return['text'] = $text;
        return $return['text']; 
        wp_die();
    }

    function geekybot_checkOut($msg, $data) {
        if (!class_exists('WooCommerce')) {
            return $return['text'] = __("WooCommerce is currently inactive.", "geeky-bot");
        }
        global $woocommerce;

        if ( $woocommerce->cart->is_empty() ) {
            return $text = "<div>".__("Your cart is currently empty.", "geeky-bot")."</div>";
            wp_die();
            return; // Don't display popup if cart is empty
        }        
        $text = "
        <div id='my-cart-popup' class='geekybot-cart-popup'>
            <a class='geekybot_wc_cart_checkout' target='_blank' href='" . wc_get_checkout_url() . "' class='button'>" . __('Proceed to Checkout', 'geeky-bot') ."</a>
        </div>";
        $text = geekybotphplib::GEEKYBOT_htmlentities($text);
        $return['text'] = $text;
        return $return['text']; 
        wp_die();
    }

    function geekybotAddToCart(){
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'add-to-cart') ) {
            die( 'Security check Failed' ); 
        }
        $pid = GEEKYBOTrequest::GEEKYBOT_getVar('productid');
        $buttons = array();
        $return = array();
        $product_id = $pid;
        
        if($product_id){
            $product = wc_get_product( $product_id );
            if($product){
                global $woocommerce;
                $canAddProduct_stock = true;
                $canAddProduct_sold_individuallly = true;
                $canAddProduct_quantity = true;
                $text = '';
                // Check for Product stock
                if ( !$product->is_in_stock() ) {
                    $canAddProduct_stock = false;
                    $text .= $product->get_title().' '. __("is out of stock.", "geeky-bot");
                }
                // Check for individual limit
                if ( $product->is_sold_individually() == 1 ) {
                    if ( $this->is_product_in_cart( $product_id ) ) {
                        $canAddProduct_sold_individuallly = false;
                        $text .= __('You cannot add another “', "geeky-bot").$product->get_title(). __('” to your cart.', "geeky-bot");
                    }
                }
                // Get the stock quantity of the product
                $stock_quantity = $product->get_stock_quantity();

                // Get the quantity of this product already in the cart
                $cart_item_quantities = WC()->cart->get_cart_item_quantities();
                $cart_quantity = isset( $cart_item_quantities[ $product_id ] ) ? $cart_item_quantities[ $product_id ] : 0;
                // Check if the product is in stock and if adding more than the available quantity
                $quantity = 1;
                if ( $stock_quantity && ( $cart_quantity + $quantity ) > $stock_quantity ) {
                    $canAddProduct_quantity = false;
                    $text .= __("You cannot add more than", "geeky-bot").' '.$stock_quantity.' '. __("of this product to your cart.", "geeky-bot");
                }
                if ( $canAddProduct_stock && $canAddProduct_sold_individuallly && $canAddProduct_quantity ) {
                    $added = $woocommerce->cart->add_to_cart( $product_id );
                    $product = wc_get_product( $product_id );
                    if ( $added ) {
                        $cart_message = $product->get_title()." ".__("has been added to your cart.", "geeky-bot");
                        $cart_class = "success";
                    } else {
                        $cart_message = $product->get_title()." ".__("has not been added to your cart. Please try again.", "geeky-bot");
                        $cart_class = 'error';
                    }
                    $text = "
                    <div class='geekybot_wc_product_wrp geekybot_wc_product_options_wrp'>
                        <div class='geekybot_wc_success_msg_wrp ".$cart_class."'>
                            ".$cart_message." 
                        </div>
                        <div class='geekybot_wc_product_left_wrp'>
                            ".$product->get_image('thumbnail')."
                        </div>
                        <div class='geekybot_wc_product_right_wrp'>
                            <div class='geekybot_wc_product_name'>
                                <a title='".$product->get_title()."' href='".$product->get_permalink()."' target='_blank'>".$product->get_title()."</a>
                            </div>
                            <div class='geekybot_wc_product_price'>
                                ".$product->get_price_html()."
                            </div>
                        </div>
                    </div>
                    <div class='geekybot_wc_success_action_wrp'>
                        <a class='geekybot_wc_cart' onclick='geekybotViewCart();' target='_blank'>
                            ".__('View Cart', 'geeky-bot')."
                        </a>
                        <a class='geekybot_wc_checkout wc_checkout' href='".wc_get_cart_url()."' target='_blank'>
                            <img src='". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/chat-img/new-tab.png ' alt='" . __('View Cart', 'geeky-bot') . "' class='geekybot-cart-item-image'>
                        </a>
                        <a class='geekybot_wc_checkout' href='".wc_get_checkout_url()."' target='_blank'>
                            ".__('Checkout', 'geeky-bot')."
                        </a>
                    </div>";                    
                }
                $return['text'] = $text;
                $return['buttons'] = $buttons;
            }else{ // product not found
                $return['text'] = __("No product was found.", "geeky-bot");
                $return['buttons'] = $buttons;
            }
        }else{ // product id is empty
            $return['text'] = __("Please select a product!", "geeky-bot");
            $return['buttons'] = $buttons;
        }
        // save bot response to the session and chat history
        $chatText = geekybotphplib::GEEKYBOT_htmlentities($return['text']);
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($chatText, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($chatText, 'bot');
        echo wp_kses($return['text'], GEEKYBOT_ALLOWED_TAGS);
        die();
    }

    function is_product_in_cart($product_id) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        global $woocommerce;
        // Get the WooCommerce cart instance
        $cart = $woocommerce->cart;
        // Check if the cart is empty
        if ($cart->is_empty()) {
            return false;
        }
        // Loop through cart items
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Check if product ID matches
            if ($cart_item['product_id'] == $product_id || $cart_item['variation_id'] == $product_id ) {
                return true;
            }
        }
        return false;
    }

    function getProductAttributes($productid = '', $isnew = '', $user_attributes = ''){
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        if ($productid == '') {
            $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
            if (! wp_verify_nonce( $nonce, 'product-attributes') ) {
                die( 'Security check Failed' ); 
            }
            $productid = GEEKYBOTrequest::GEEKYBOT_getVar('productid');

            $isnew = GEEKYBOTrequest::GEEKYBOT_getVar('isnew');
            $user_attributes = GEEKYBOTrequest::GEEKYBOT_getVar('attr');
        }
        $savedAttributes = $this->getSavedAttributesByProductId($productid);
        if ($isnew == 1) {
            // delete all the old saved atributes
            geekybot::$_geekybotsessiondata->geekybot_deleteVariablesDatabyProductId($productid);
        }
        $product = wc_get_product( $productid );
        if ( $product->is_type( 'variable' ) ) {
            $variation_attributes = [];
            $available_variations = $product->get_available_variations(); // This might need adjustment based on your logic
            // if ( $variation ) {
            //     $variation_attributes = $variation->get_variation_attributes();
            // }
            $pattributes = $product->get_attributes();
            // if no attribute found for the variable product
            if (empty($pattributes)) {
                $btnhtml = "
                <div class='geekybot_wc_product_wrp geekybot_wc_product_options_wrp'>
                    <div class='geekybot_wc_product_left_wrp'>
                        ".$product->get_image('thumbnail')."
                    </div>
                    <div class='geekybot_wc_product_right_wrp'>
                        <div class='geekybot_wc_product_name'>
                            <a title='".$product->get_title()."' href='".$product->get_permalink()."' target='_blank'>".$product->get_title()."</a>
                        </div>
                        <div class='geekybot_wc_product_price'>
                            ".$product->get_price_html()."
                        </div>
                    </div>
                </div>
                <div class='geekybot_wc_product_heading'>".__("There are no variations available.", 'geeky-bot')."</div>";
                // save bot response to the session and chat history
                geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($btnhtml, 'bot');
                GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($btnhtml, 'bot');
                // 
                echo wp_kses($btnhtml, GEEKYBOT_ALLOWED_TAGS);
                die();
            }
            $attributes = [];
            foreach ($pattributes as $attribute) {
                $attribute_name = $attribute->get_name();
                $is_already_set = $this->geekybot_readVariablesGetAttributes($user_attributes, $attribute_name, $product);
                if (!$is_already_set) {
                    $options = $attribute->get_options(); // Array of values for the attribute
                    // Initialize an empty array to store the option names
                    $optionNames = [];
                    // Loop through each option ID
                    foreach ($options as $optionIndex => $optionId) {
                        // Check if the options array contains IDs or names
                        if (is_int($optionId)) {
                            // Existing attribute, fetch name using ID
                            // Use the option ID to retrieve the option name
                            $optionName = wc_get_product_terms($productid, $attribute->get_name(), array('fields' => 'names'))[$optionIndex]; // Use 'slugs' argument;
                        } else {
                            $optionName = $optionId;
                        }
                        // Add the option name to the array
                        $optionNames[] = $optionName;
                    }

                    foreach ($optionNames as $value) {
                        $attributes[$attribute_name][] = $value;
                    }
                }
            }
            foreach ($attributes as $attributekey => $attributevalue) {
                $btnhtml ='';
                // check if the attribute not alredy set
                $condition = geekybot::$_geekybotsessiondata->geekybot_isSetVariablesDatabyAttributeId($productid, $attributekey, $attributevalue);
                foreach ( $product->get_available_variations() as $available_variation ) {
                    foreach ( $available_variation['attributes'] as $variationkey => $variationvalue ) {
                        // 
                        $variationkey =  explode('_', $variationkey);
                        $variationkey =  end($variationkey);
                        $variationkey = str_replace(' ', "-", $variationkey);
                        $variationkey = strtolower($variationkey);
                        // 
                        $filteredKey =  explode('_', $attributekey);
                        $filteredKey =  end($filteredKey);
                        $filteredKey = str_replace(' ', "-", $filteredKey);
                        $filteredKey = strtolower($filteredKey);
                        $jsonUserAttributes = htmlspecialchars($user_attributes, ENT_QUOTES, 'UTF-8');


                        if ($variationkey == $filteredKey && $condition == 0) {
                            $btnhtml = "
                            <div class='geekybot_wc_product_wrp geekybot_wc_product_options_wrp'>
                                <div class='geekybot_wc_product_left_wrp'>
                                    ".$product->get_image('thumbnail')."
                                </div>
                                <div class='geekybot_wc_product_right_wrp'>
                                    <div class='geekybot_wc_product_name'>
                                        <a title='".$product->get_title()."' href='".$product->get_permalink()."' target='_blank'>".$product->get_title()."</a>
                                    </div>
                                    <div class='geekybot_wc_product_price'>
                                        ".$product->get_price_html()."
                                    </div>
                                    <div class='geekybot_wc_product_action_btn_wrp'>";
                                    foreach ( $savedAttributes as $attribute => $value ) {
                                        $attribute = str_replace('attribute_', "", $attribute);
                                        $btnhtml .="
                                        <div class='geekybot_wc_product_attr'>
                                            <span class='geekybot_wc_product_attr_key'>
                                                ".$attribute.":
                                            </span>
                                            <span class='geekybot_wc_product_attr_val'>
                                                ".$value."
                                            </span>
                                        </div>
                                        ";
                                    }
                                    $btnhtml .="
                                </div>
                                </div>
                            </div>
                            <div class='geekybot_wc_product_heading'>".__('Select', 'geeky-bot')." ".$filteredKey."</div>
                                <ul class='geeky_bot_wc_msg_btn_wrp'>";
                                    foreach ($attributevalue as $value) {
                                        $btnhtml .=  "<li class='geeky_bot_wc_msg_btn'><button class='geeky_bot_wc_btn' onclick=\"saveProductAttributeToSession('".$productid."','".$attributekey."','".$value."','".$jsonUserAttributes."');\" value='".$value."'><span>".$value."</span></button></li>";
                                    }
                                    $btnhtml .= "
                                </ul>
                            </div>";
                            echo wp_kses($btnhtml, GEEKYBOT_ALLOWED_TAGS);
                            die();
                        }
                    }
                }
            }
            // get product variation by product id and attributes
            // Get the desired variation data (array of attribute => value pairs)
            $savedAttributes = $this->getSavedAttributesByProductId($productid);
            $variationResult = $this->get_variation_id_by_attributes($productid, $savedAttributes, $user_attributes);
            print_r($variationResult);
            die();
        }
    }

    function get_variation_id_by_attributes( $product_id, $variation_data, $user_attributes ) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        global $woocommerce;
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            $chatMessage = __("Product not found or not a variable product.", "geeky-bot");
            echo esc_html($chatMessage);
            // save bot response to the session and chat history
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($chatMessage, 'bot');
            GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($chatMessage, 'bot');
        }
        // new code start
        $variations = $product->get_available_variations();
        foreach ( $variations as $variation ) {
            $variation_match = 1;
            $varAttrArr = [];
            // loop through the variation and get array of attributes with value
            foreach ( $variation['attributes'] as $varAttr => $varVal ) {
                $varAttr = str_replace('attribute_', "", $varAttr);
                // $varAttr =  end($varAttr);
                $varAttrArr[$varAttr] = $varVal;
            }
            foreach ( $variation_data as $attribute => $value ) {
                $attribute = strtolower($attribute);
                $attribute = str_replace('attribute_', "", $attribute);
                // $attribute =  end($attribute);
                $attribute = str_replace(' ', "-", $attribute);
                foreach ( $variation['attributes'] as $varAttr => $varVal ) {
                    $newAttr = str_replace('attribute_', "", $varAttr);
                    // $newAttr =  end($newAttr);
                    // add the user entered values to the variation data
                    if ($newAttr == $attribute) {
                        $variation[$varAttr] =   $value;
                    }
                }
                if ( isset( $varAttrArr[$attribute] ) && $varAttrArr[$attribute] == '' ) {
                    // if the variation have attribute with no value
                    $variation_match = 1;
                } else if ( !isset( $varAttrArr[$attribute] ) || strtolower($varAttrArr[$attribute]) !== strtolower($value )) {
                    // if the atribute value entered by the user is not same as the value in the variation OR attribute not exist oin the variation
                    $variation_match = 0;
                    break;
                }
            }
            if ( $variation_match == 1 ) {
                $variation_id = $variation['variation_id'];
                $canAddProduct_stock = true;
                $canAddProduct_quantity = true;
                $canAddProduct_sold_individuallly = true;
                $text = '';
                if ( isset($variation['max_qty']) && $variation['max_qty'] != '' ) {
                    // Get the stock quantity of the product
                    $stock_quantity = $variation['max_qty'];
                    $new_quantity = $stock_quantity + 1;
                    // Check if the product is in stock and if adding more than the available quantity
                    if ( $stock_quantity && $new_quantity > $stock_quantity ) {
                        $canAddProduct_quantity = false;
                        $text .= __("You cannot add more than", "geeky-bot").' '.$stock_quantity.' '. __("of this product to your cart.", "geeky-bot");
                    }
                } else {
                    // Check for Product stock
                    if ( $variation['is_in_stock'] != 1 ) {
                        $text .= $variation['availability_html'];
                        $canAddProduct_stock = false;
                    }
                }
                // Check for individual limit
                if ( $variation['is_sold_individually'] == 'yes' ) {
                    if ( $this->is_product_in_cart( $product_id ) ) {
                        $text .= __("You can't add another “", "geeky-bot").$product->get_title(). __("” to your cart.", "geeky-bot");
                        $canAddProduct_sold_individuallly = false;
                    }
                }
                if ( $canAddProduct_stock && $canAddProduct_sold_individuallly && $canAddProduct_quantity) {
                    $quantity = 1;
                    $added_to_cart = $woocommerce->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, null);
                    // $added_to_cart = $woocommerce->cart->add_to_cart( $variation['variation_id'], 1 ); // Add to cart
                    if ($added_to_cart) {
                        $product = wc_get_product( $product_id );
                        $text = "
                        <div class='geekybot_wc_product_wrp geekybot_wc_product_options_wrp'>
                            <div class='geekybot_wc_success_msg_wrp success'>
                                ".$product->get_title(). __('has been added to your cart.', 'geeky-bot')." 
                            </div>
                            <div class='geekybot_wc_product_left_wrp'>
                                ".$product->get_image('thumbnail')."
                            </div>
                            <div class='geekybot_wc_product_right_wrp'>
                                <div class='geekybot_wc_product_name'>
                                    <a title='".$product->get_title()."' href='".$product->get_permalink()."' target='_blank'>".$product->get_title()."</a>
                                </div>
                                <div class='geekybot_wc_product_price'>
                                    ".$product->get_price_html()."
                                </div>
                                <div class='geekybot_wc_product_action_btn_wrp'>";
                                    foreach ( $variation_data as $attribute => $value ) {
                                        $attribute = str_replace('attribute_', "", $attribute);
                                        $text .="
                                        <div class='geekybot_wc_product_attr'>
                                            <span class='geekybot_wc_product_attr_key'>
                                                ".$attribute.":
                                            </span>
                                            <span class='geekybot_wc_product_attr_val'>
                                                ".$value."
                                            </span>
                                        </div>
                                        ";
                                    }
                                    $text .="
                                </div>
                            </div>
                        </div>
                        <div class='geekybot_wc_success_action_wrp'>
                            <a class='geekybot_wc_cart' onclick='geekybotViewCart();' target='_blank'>
                                ".__('View Cart', 'geeky-bot')."
                            </a>
                            <a class='geekybot_wc_checkout wc_checkout' href='".wc_get_cart_url()."' target='_blank' title'" . __('View Cart', 'geeky-bot') . "'>
                                <img src='". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/chat-img/new-tab.png ' alt='" . __('View Cart', 'geeky-bot') . "' class='geekybot-cart-item-image'>
                            </a>
                            <a class='geekybot_wc_checkout' href='".wc_get_checkout_url()."' target='_blank'>
                                ".__('Checkout', 'geeky-bot')."
                            </a>
                        </div>";
                    } else {
                        // Error adding product to cart
                        $text = __("There was an error while adding the product to the cart.", "geeky-bot");
                    }
                }
                echo wp_kses($text, GEEKYBOT_ALLOWED_TAGS);
                // save bot response to the session and chat history
                geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($text, 'bot');
                $chatHistoryMessage = __('Add to cart', 'geeky-bot');
                GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($chatHistoryMessage, 'user');
                GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($text, 'bot');
                die();
                break; // Stop after finding the matching variation
            }
        }
        if ( isset($variation_match) && $variation_match == 0 ) {
            $chatMessage = __("The requested variation with the specified attributes could not be found.", "geeky-bot");
            echo esc_html($chatMessage);
            // save bot response to the session and chat history
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($chatMessage, 'bot');
            GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($chatMessage, 'bot');
            // delete all the old saved atributes
            geekybot::$_geekybotsessiondata->geekybot_deleteVariablesDatabyProductId($product_id);
            // again call to "getProductAttributes" to check other attributes
            $this->getProductAttributes($product_id, 1, $user_attributes);

        }
    }

    function getSavedAttributesByProductId( $productid ) {
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata` WHERE productid = '" . esc_sql($productid) . "' AND usersessionid = '" . esc_sql($chatid) . "' AND sessionexpire > '" . time() . "'";
        $attributes= geekybot::$_db->get_results($query);
        $variation_data = array();
        foreach ($attributes as $attribute) {
            $variation_data[$attribute->sessionmsgkey] = $attribute->sessionmsgvalue;
        }
        return $variation_data;
    }

    function saveProductAttributeToSession(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-product-attribute') ) {
            die( 'Security check Failed' ); 
        }
        $productid = GEEKYBOTrequest::GEEKYBOT_getVar('productid');
        $attributekey = GEEKYBOTrequest::GEEKYBOT_getVar('attributekey');
        $attributevalue = GEEKYBOTrequest::GEEKYBOT_getVar('attributevalue');
        $userattributes = GEEKYBOTrequest::GEEKYBOT_getVar('userattributes');
        geekybot::$_geekybotsessiondata->geekybot_addSessionAttributeDataToTable($productid, $attributekey, $attributevalue);
        // again call to "getProductAttributes" to check other attributes
        $this->getProductAttributes($productid, 0, $userattributes);
    }

    function geekybotRemoveCartItem(){
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'remove-item') ) {
            die( 'Security check Failed' ); 
        }
        $variation_id = GEEKYBOTrequest::GEEKYBOT_getVar('variation_id');
        $product_id = GEEKYBOTrequest::GEEKYBOT_getVar('product_id');

        global $woocommerce;
        $cart = $woocommerce->cart;
        // Loop through cart items
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Check if product ID and variation ID matches
            if ($cart_item['product_id'] == $product_id) {
                $cart->remove_cart_item( $cart_item_key );
                $msg = '';
                $cartData = $this->geekybot_viewCart($msg, $cart_item_key);
                // save bot response to the session and chat history
                geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($cartData, 'bot');
                GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($cartData, 'bot');
                // 
                return $cartData;
                die();
            }
        }
        $cartData = __("No product was found.", "geeky-bot");
        // save bot response to the session and chat history
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($cartData, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($cartData, 'bot');
        //
        return $cartData;
        die();
    }

    function geekybotViewCart(){
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'view-cart') ) {
            die( 'Security check Failed' ); 
        }
        $cartData = $this->geekybot_viewCart('', 1);
        // save bot response to the session and chat history
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($cartData, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($cartData, 'bot');
        return $cartData;
        wp_die();
    }

    function geekybotUpdateCartItemQty(){
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'update-quantity') ) {
            die( 'Security check Failed' ); 
        }
        $cart_item_key = GEEKYBOTrequest::GEEKYBOT_getVar('cart_item_key');
        global $woocommerce;
        $cart_items = $woocommerce->cart->get_cart();
        $cart_item = $cart_items[$cart_item_key];
        $product_id = $cart_item['product_id'];
        $product_quantity = $cart_item['quantity'];

        $product = wc_get_product($product_id);
        $text = "
        <div class='geekybot_wc_product_wrp geekybot_wc_product_options_wrp'>
            <div class='geekybot_wc_product_left_wrp'>
                ".$product->get_image('thumbnail')."
            </div>
            <div class='geekybot_wc_product_right_wrp'>
                <div class='geekybot_wc_product_name'>
                    <a title='".$product->get_title()."' href='".$product->get_permalink()."' target='_blank'>".$product->get_title()."</a>
                </div>
                <div class='geekybot_wc_product_price'>
                    ".$product->get_price_html()."
                </div>
                <div class='geekybot_wc_product_action_btn_wrp'>";
                    if ( $cart_item['variation_id'] ) {
                        $variation = new WC_Product_Variation( $cart_item['variation_id'] );
                        $variation_attributes = $variation->get_variation_attributes();
                        foreach ( $variation_attributes as $attribute_name => $attribute_value ) {
                            $filteredKey =  explode('_', $attribute_name);
                            $filteredKey =  end($filteredKey);
                            $filteredKey = ucfirst($filteredKey);
                            $text .="
                            <div class='geekybot_wc_product_attr'>
                                <span class='geekybot_wc_product_attr_key'>
                                    ".$filteredKey.":
                                </span>
                                <span class='geekybot_wc_product_attr_val'>
                                    ".$attribute_value."
                                </span>
                            </div>
                            ";
                        }
                    }
                    $text .="
                </div>
            </div>
        </div>
        <div class='geekybot_wc_product_quantity'>
            <div class='geekybot_wc_product_heading'>".__('Change Quantity', 'geeky-bot')."</div>
            <input type='number' id='product_quantity' class='product_quantity' name='product_quantity' min='1' value='".$product_quantity."'>
            <span onclick=\"geekybotUpdateCartItemQuantity('".$cart_item_key."');\" class='product_quantity_update' href='".wc_get_cart_url()."' target='_blank'>
                ".__('Update', 'geeky-bot')."
            </span>
        </div>";
        $text = geekybotphplib::GEEKYBOT_htmlentities($text);
        // save bot response to the session
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($text, 'bot');
        // 
        $return['text'] = $text;
        return $return['text']; 
        wp_die();
    }

    function geekybotUpdateCartItemQuantity(){
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return __("WooCommerce is currently inactive.", "geeky-bot");
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'update-quantity') ) {
            die( 'Security check Failed' ); 
        }
        $item_id = GEEKYBOTrequest::GEEKYBOT_getVar('cart_item_key');
        $new_quantity = GEEKYBOTrequest::GEEKYBOT_getVar('product_quantity');
        global $woocommerce;
        $cart = WC()->cart;
        if ( $cart->is_empty() ) {
            return __("The cart is empty.", "geeky-bot");
        }
        // get all cart items
        $cart_items = $woocommerce->cart->get_cart();
        // get the required item from the cart
        if (!isset($cart_items[$item_id])) {
            return __("Cart item is not found.", "geeky-bot");
        }
        $cart_item = $cart_items[$item_id];
        // Check if the cart item has a variation
        $variation_id = isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : null;
        if ($variation_id) {
            // If it's a variation, get the variation product
            $product = wc_get_product($variation_id);
            $product_id = $variation_id;
        } else {
            // Otherwise, get the parent product
            $product_id = $cart_item['product_id'];
            $product = wc_get_product($product_id);
        }
        // 
        $canAddProduct_stock = true;
        $canAddProduct_sold_individuallly = true;
        $canAddProduct_quantity = true;
        $text = '';
        // Check for individual limit
        if ( $product->is_sold_individually() == 1 ) {
            if ( $this->is_product_in_cart( $product_id ) && $new_quantity > 1) {
                $canAddProduct_sold_individuallly = false;
                $text .= __('You cannot add another “', "geeky-bot").$product->get_title(). __('” to your cart.', "geeky-bot");
            }
        }
        if ( $product->get_manage_stock() ) {
            // Get the stock quantity of the product
            $stock_quantity = $product->get_stock_quantity();
            // Check if the product is in stock and if adding more than the available quantity
            if ( $stock_quantity && $new_quantity > $stock_quantity ) {
                $canAddProduct_quantity = false;
                $text .= __("You cannot add more than", "geeky-bot").' '.$stock_quantity.' '. __("of this product to your cart.", "geeky-bot");
            }
        } else {
            if ( !$product->is_in_stock() ) {
                $canAddProduct_stock = false;
                $text .= $product->get_title().' '. __("is out of stock.", "geeky-bot");
            }
        }
        if ( $canAddProduct_stock && $canAddProduct_sold_individuallly && $canAddProduct_quantity ) {
            $updated = $cart->get_cart_item( $item_id );
            if ( ! $updated ) {
                return __("The item was not found in the cart.", "geeky-bot");
            }
            // update the item quantity
            $cart->set_quantity( $item_id, $new_quantity );
            // get all cart items
            $cart_items = $woocommerce->cart->get_cart();
            // get the required item from the cart
            $cart_item = $cart_items[$item_id];
            $product_quantity = $cart_item['quantity'];
            // show message after item update
            $text = "
            <div class='geekybot_wc_product_wrp geekybot_wc_product_options_wrp'>
                <div class='geekybot_wc_success_msg_wrp success'>
                    ". __('Quantity updated successfully!', 'geeky-bot')." 
                </div>
                <div class='geekybot_wc_product_left_wrp'>
                    ".$product->get_image('thumbnail')."
                </div>
                <div class='geekybot_wc_product_right_wrp'>
                    <div class='geekybot_wc_product_name'>
                        <a title='".$product->get_title()."' href='".$product->get_permalink()."' target='_blank'>".$product->get_title()."</a>
                    </div>
                    <div class='geekybot_wc_product_price'>
                        ".$product->get_price_html()."
                    </div>
                    <div class='geekybot_wc_product_action_btn_wrp'>";
                        if ( $cart_item['variation_id'] ) {
                            $variation = new WC_Product_Variation( $cart_item['variation_id'] );
                            $variation_attributes = $variation->get_variation_attributes();
                            foreach ( $variation_attributes as $attribute_name => $attribute_value ) {
                                $filteredKey =  explode('_', $attribute_name);
                                $filteredKey =  end($filteredKey);
                                $filteredKey = ucfirst($filteredKey);
                                $text .="
                                <div class='geekybot_wc_product_attr'>
                                    <span class='geekybot_wc_product_attr_key'>
                                        ".$filteredKey.":
                                    </span>
                                    <span class='geekybot_wc_product_attr_val'>
                                        ".$attribute_value."
                                    </span>
                                </div>
                                ";
                            }
                        }
                        $text .="
                        <div class='geekybot_wc_product_attr'>
                            <span class='geekybot_wc_product_attr_key'>
                                ". __('Quantity', 'geeky-bot').': '."
                            </span>
                            <span class='geekybot_wc_product_attr_val'>
                                ".$product_quantity."
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class='geekybot_wc_success_action_wrp'>
                <a class='geekybot_wc_cart' onclick='geekybotViewCart();' target='_blank'>
                    ".__('View Cart', 'geeky-bot')."
                </a>
                <a class='geekybot_wc_checkout wc_checkout' href='".wc_get_cart_url()."' target='_blank'>
                    <img src='". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/chat-img/new-tab.png ' alt='" . __('View Cart', 'geeky-bot') . "' class='geekybot-cart-item-image'>
                </a>
                <a class='geekybot_wc_checkout' href='".wc_get_checkout_url()."' target='_blank'>
                    ".__('Checkout', 'geeky-bot')."
                </a>
            </div>";
            $text = geekybotphplib::GEEKYBOT_htmlentities($text);
        }
        // save bot response to the session and chat history
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($text, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($text, 'bot');
        $return['text'] = $text;
        return $return['text']; 
        wp_die();
    }

    function geekybot_normalize_price_with_wc($price_input) {
        // Remove currency symbols and format to decimal
        return wc_format_decimal($price_input);
    }
}
?>
