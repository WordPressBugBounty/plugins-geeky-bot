<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTwpcbnotification {

    function __construct( ) {

    }

    public function geekybot_addSessionNotificationDataToTable($message, $msgtype, $sessiondatafor = 'notification',$msgkey = 'captcha'){
        /*$message belows to repsonse message
        $msgtyp belongs to reponse type eg error or success
        $sessiondatafor belong to any random thing or reponse notification after saving some data
        $msgkey belong to module
        */

        if($message == ''){
            if(!is_numeric($message))
                return false;
        }

        global $wpdb;
        $data = array();
        $update = false;
        if(isset($_COOKIE['_wpgeekybot_session_']) && isset(geekybot::$_geekybotsession->sessionid)){
            if($sessiondatafor == 'notification'){
                $data = $this->geekybot_getNotificationDatabySessionId($sessiondatafor);
                if(empty($data)){
                    $data['msg'][0] = $message;
                    $data['type'][0] = $msgtype;
                }else{
                    $update = true;
                    $count = count($data['msg']);
                    $data['msg'][$count] = $message;
                    $data['type'][$count] = $msgtype;
                }
            }

            if($sessiondatafor == 'geekybot_spamcheckid'){
                $msgkey = 'captcha';
                $data = $this->geekybot_getNotificationDatabySessionId($sessiondatafor,$msgkey);
                if($data != ""){
                    $update = true;
                    $data = $message;
                }else{
                    $data = $message;
                }
            }
            if($sessiondatafor == 'geekybot_rot13'){
                $msgkey = 'captcha';
                $data = $this->geekybot_getNotificationDatabySessionId($sessiondatafor,$msgkey);
                if($data != ""){
                    $update = true;
                    $data = $message;
                }else{
                    $data = $message;
                }
            }
            if($sessiondatafor == 'geekybot_spamcheckresult'){
                $msgkey = 'captcha';
                $data = $this->geekybot_getNotificationDatabySessionId($sessiondatafor,$msgkey);
                if($data != ""){
                    $update = true;
                    $data = $message;
                }else{
                    $data = $message;
                }
            }

            $data = wp_json_encode($data , true);
            $sessionmsg = geekybotphplib::GEEKYBOT_safe_encoding($data);
            if(!$update){
                geekybot::$_db->insert( geekybot::$_db->prefix."geekybot_session", array("usersessionid" => geekybot::$_geekybotsession->sessionid, "sessionmsg" => $sessionmsg, "sessionexpire" => geekybot::$_geekybotsession->sessionexpire, "sessionfor" => $sessiondatafor , "msgkey" => $msgkey) );
            }else{
                geekybot::$_db->update( geekybot::$_db->prefix."geekybot_session", array("sessionmsg" => $sessionmsg), array("usersessionid" => geekybot::$_geekybotsession->sessionid , 'sessionfor' => $sessiondatafor) );
            }
        }
        return false;
    }

    public function geekybot_getNotificationDatabySessionId($sessionfor , $msgkey = null, $deldata = false){
        if(geekybot::$_geekybotsession->sessionid == '')
            return false;
        global $wpdb;
        $query = "SELECT sessionmsg FROM ". geekybot::$_db->prefix ."geekybot_session WHERE usersessionid = '" . geekybot::$_geekybotsession->sessionid . "' AND sessionfor = '" . $sessionfor . "' AND sessionexpire > '" . time() . "'";
        $data = geekybot::$_db->get_var($query);

        if(!empty($data)){
            $data = geekybotphplib::GEEKYBOT_safe_decoding($data);
            $data = json_decode( $data , true);
        }
        if($deldata){
            geekybot::$_db->delete( geekybot::$_db->prefix."geekybot_session", array( 'usersessionid' => geekybot::$_geekybotsession->sessionid , 'sessionfor' => $sessionfor , 'msgkey' => $msgkey) );
        }
        return $data;
    }

}

?>
