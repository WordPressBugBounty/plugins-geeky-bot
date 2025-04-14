<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTgeekybotsessiondata {

    function __construct( ) {

    }

    public function geekybot_addSessionVariablesDataToTable($messages){
        /*$messages belows to repsonse messages
        $msgtyp belongs to reponse type eg error or success
        $storyId belong to the id of the story
        */
        if(!array($messages) && empty($messages)){
            return false;
        }

        $update = false;
        if(isset($_COOKIE['geekybot_chat_id'])){
            $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
            foreach ($messages as $key => $value) {
                $value = addslashes($value);
                if ($key != 'chathistory') {
                    $data = $this->geekybot_getVariablesDatabySessionId($chatid, $key);
                    if($data != ""){
                        $update = true;
                    } else {
                        $update = false;
                    }
                    if(!$update){
                        $query = "INSERT INTO `" . geekybot::$_db->prefix . "geekybot_sessiondata` (`usersessionid`, `sessionmsgkey`, `sessionmsgvalue`, `sessionexpire`) VALUES ('".$chatid."', '".$key."', '".$value."', '".geekybot::$_geekybotsession->sessionexpire."');";
                        geekybot::$_db->query($query);
                    }else{
                        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_sessiondata` SET `sessionmsgvalue` = '".$value."' WHERE `usersessionid`= '" . $chatid . "' AND `sessionmsgkey`= '" . $key . "' ";
                        geekybotdb::query($query);
                    }
                }
            }
        }
        return false;
    }

    public function geekybot_addSessionAttributeDataToTable($productid, $attributekey, $attributevalue){
        if(empty($productid) || empty($attributekey) || empty($attributevalue)){
            return false;
        }

        $update = false;
        if(isset($_COOKIE['geekybot_chat_id'])){
            $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();

            $query = "INSERT INTO `" . geekybot::$_db->prefix . "geekybot_sessiondata` (`usersessionid`, `sessionmsgkey`, `sessionmsgvalue`, `productid`, `sessionexpire`) VALUES ('".$chatid."', '".$attributekey."', '".$attributevalue."', '".$productid."', '".geekybot::$_geekybotsession->sessionexpire."');";
            geekybot::$_db->query($query);
                
        }
        return false;
    }

    public function geekybot_getVariablesDatabySessionId($usersessionid, $key = '' , $deldata = false){
        $query = "SELECT sessionmsgvalue
            FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata`  WHERE sessionmsgkey = '" . $key . "' AND usersessionid = '" . $usersessionid . "' AND sessionexpire > '" . time() . "'";
        $data = geekybotdb::GEEKYBOT_get_row($query);
        if($deldata){
            $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_sessiondata` WHERE usersessionid = '".$usersessionid."'";
            geekybotdb::query($query);
        }
        return $data;
    }

    public function geekybot_isSetVariablesDatabyAttributeId($productid, $attributekey){
        $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $query = "SELECT sessionmsgvalue
            FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata` WHERE productid = '" . $productid . "' AND sessionmsgkey = '" . $attributekey . "' AND usersessionid = '" . $chatid . "' AND sessionexpire > '" . time() . "'";
        $sessionmsgvalue = geekybotdb::GEEKYBOT_get_var($query);
        if(isset($sessionmsgvalue) && $sessionmsgvalue != ''){
            return 1;
        }
        return 0;
    }

    public function geekybot_deleteVariablesDatabyProductId($productid){
        $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_sessiondata` WHERE usersessionid = '".$chatid."' AND productid = ".$productid;
        geekybotdb::query($query);
        return true;
    }

    public function geekybot_readVarFromSessionAndCallPredefinedFunction($msg, $function_id){
        // read variables data from the session
        $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $query = "SELECT sessionmsgkey,sessionmsgvalue
            FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata`  WHERE usersessionid = '" . $chatid . "' AND sessionmsgkey NOT IN ('nextIndex','ranking','flag','index','story','chathistory') ";
        $variables = geekybotdb::GEEKYBOT_get_results($query);
        $params = array();
        foreach ($variables as $key => $value) {
            $params[$value->sessionmsgkey] = $value->sessionmsgvalue;
        }
        // call the predefined function by using filter
        $function_name = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getFunctionNameById($function_id);
        // List of functions that should be blocked when 'unique_features_disabled' is enabled
        $restricted_functions = [
            'showAllSaleProducts',
            'showAllTrendingProducts',
            'showAllLatestProducts',
            'showAllHighestRatedProducts',
            'viewOrders',
            'viewAccountDetails',
            'orderTracking',
        ];
        // Check if unique features are disabled and if the function is in the restricted list
        if (get_option('unique_features_disabled') && in_array($function_name, $restricted_functions)) {
            return;
        }
        if (has_filter('wp_ajax_'.$function_name)) {
            $data = apply_filters('wp_ajax_'.$function_name,$msg,$params);
            return $data;
        } else {
            return;
        }
    }

    public function geekybot_addChatHistoryToSession($msg, $type, $search = ''){
        if(is_null($msg)){
            return false;
        }
        $chatId = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        if ($type == 'user') {
            $img_scr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getUserImagePath();
            $message = "<li class='geekybot-message geekybot-message-user'><section class='geekybot-message-user-img'><img src='".esc_url($img_scr)."' alt='' /></section><section class='geekybot-message-text'>".$msg."</section></li>";
        } else if($type == 'bot') {
            $img_scr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getBotImagePath();
            if (!empty($search)) {
                $message = "<li class='geekybot-message geekybot-message-bot'><section class='geekybot-message-bot-img'><img src='".esc_url($img_scr)."' alt='' /></section><section class='geekybot-message-text_wrp'>".$msg."</section></li>";
            } else {
                $message = "<li class='geekybot-message geekybot-message-bot'><section class='geekybot-message-bot-img'><img src='".esc_url($img_scr)."' alt='' /></section><section class='geekybot-message-text_wrp'><section class='geekybot-message-text'>".$msg."</section></section></li>";
            }
        }
        if(isset($chatId) && $chatId != ''){
            // check if the user chat histroy exist
            $query = 'SELECT sessionmsgvalue FROM `' . geekybot::$_db->prefix . 'geekybot_sessiondata` WHERE sessionmsgkey = "chathistory" AND usersessionid = "' . $chatId . '" AND sessionexpire > "' . time() . '"';
            $chathistory = geekybotdb::GEEKYBOT_get_var( $query);
            if (isset($chathistory) && $chathistory != '') {
                // check if user chat histroy exist then update the old histroy
                $sessionmsgvalue = $chathistory.$message;
                $sessionmsgvalue = addslashes($sessionmsgvalue);
                $query = 'UPDATE `' . geekybot::$_db->prefix . 'geekybot_sessiondata` SET `sessionmsgvalue` = "'.$sessionmsgvalue.'" WHERE `usersessionid`= "' . $chatId . '" AND `sessionmsgkey`= "chathistory" ';
                geekybotdb::query($query);
            } else {
                // check if user chat histroy not exist then add new record
                geekybot::$_db->insert( geekybot::$_db->prefix . "geekybot_sessiondata", array("usersessionid" => $chatId, "sessionmsgkey" => 'chathistory', "sessionmsgvalue" => $message, "sessionexpire" => geekybot::$_geekybotsession->sessionexpire) );
            }
        }
        return true;
    }

    public function geekybot_getStoryIdFromSession(){
        $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $query = "SELECT `sessionmsgvalue` FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata` WHERE `usersessionid` = '" . $chatid . "' AND `sessionmsgkey` = 'story'";
        $story = geekybotdb::GEEKYBOT_get_var($query);
        return $story;
    }

    public function geekybot_getSavedDataFromSession(){
        // get the chat id of the current user
        $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        // get all the data from the seesion table against the chat id
        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata` WHERE `usersessionid` = '" . $chatid . "'";
        $variables = geekybotdb::GEEKYBOT_get_results($query);
        $returnData = array();
        foreach ($variables as $key => $value) {
            $returnData[$value->sessionmsgkey] = $value->sessionmsgvalue;
        }
        return $returnData;
    }
    
}

?>
