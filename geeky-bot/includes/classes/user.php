<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTuser {

    private $currentuser = null;

    function __construct() {
        if (is_user_logged_in()) { // wp user logged in
            $wpuserid = get_current_user_id();
            if (!is_numeric($wpuserid))
                return false;
            $query = "SELECT * FROM `" . geekybot::$_db->prefix . "users` WHERE ID = " . $wpuserid;
            $this->currentuser = geekybot::$_db->get_row($query);
        }
    }

    function geekybot_isguest() {
        if (isset($_SESSION['geekybot-socialid']) && !empty($_SESSION['geekybot-socialid'])) {
            return false;
        } elseif ($this->currentuser == null && !is_user_logged_in()) { // current user is guest
            return true;
        } else {
            return false;
        }
    }

    function geekybot_isdisabled() {
        if ($this->currentuser != null && $this->currentuser->status == 0) { // current user is disabled
            return true;
        } else {
            return false;
        }
    }

    function geekybot_uid() {
        if ($this->currentuser != null) {
            return $this->currentuser->ID;
        }
    }

    function geekybot_emailaddress() {
        if ($this->currentuser == null) { // current user is guest
            return false;
        } else {
            return $this->currentuser->user_email;
        }
    }

    function geekybot_fullname($uid='') {
       if($uid==''){
            if ($this->currentuser == null) { // current user is guest
                return false;
            } else {
                $name = $this->currentuser->display_name;
                return $name;
            }
        }else{
            if(is_admin()){
                $query = "SELECT `user_login` FROM `" . geekybot::$_db->prefix . "users` WHERE `ID` = " . $uid;
                return geekybot::$_db->get_var($query);
            }else{
                return '';
            }
        }
    }

}

?>
