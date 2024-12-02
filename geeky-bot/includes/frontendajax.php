<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTfrontendajax {

    function __construct() {
        //die('hereere0');
        add_action("wp_ajax_geekybot_frontendajax", array($this, "GEEKYBOT_frontendajaxhandler")); // when user is login
        add_action("wp_ajax_nopriv_geekybot_frontendajax", array($this, "GEEKYBOT_frontendajaxhandler")); // when user is not login
    }

    function GEEKYBOT_frontendajaxhandler() {
        $fucntin_allowed = array( 'getMessageResponse','getRandomChatId', 'getUserChatHistoryMessages','SaveChathistory','endUserChat','restartUserChat','geekybotAddToCart','getProductAttributes','saveProductAttributeToSession', 'getDefaultFallBackFormAjax', 'geekybotLoadMoreProducts','geekybotLoadMoreCustomPosts', 'geekybotRemoveCartItem', 'geekybotUpdateCartItemQty', 'geekybotUpdateCartItemQuantity', 'geekybotViewCart', 'showArticlesList');
        $task = GEEKYBOTrequest::GEEKYBOT_getVar('task');
        if($task != '' && in_array($task, $fucntin_allowed)){
            $module = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotme');
            $result = GEEKYBOTincluder::GEEKYBOT_getModel($module)->$task();
            echo wp_kses($result, GEEKYBOT_ALLOWED_TAGS);
            die();
        }else{
            die('Not Allowed!');
        }
    }
}

$jsfrontendajax = new GEEKYBOTfrontendajax();
?>
