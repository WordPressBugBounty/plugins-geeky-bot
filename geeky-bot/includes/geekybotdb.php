<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class geekybotdb {

    function __construct() {

    }

    public static function GEEKYBOT_get_var($query) {
        $result = geekybot::$_db->get_var($query);

        return $result;
    }

    function GEEKYBOT_setquery($query){
        $this->_query = $this->GEEKYBOT_parsequery($query);
    }

    public static function GEEKYBOT_get_row($query) {
        $result = geekybot::$_db->get_row($query);

        return $result;
    }

    public static function GEEKYBOT_get_results($query) {
        $result = geekybot::$_db->get_results($query);

        return $result;
    }

    private function GEEKYBOT_parsequery($query){
        $query = geekybotphplib::GEEKYBOT_str_replace('#__', $this->_db->prefix, $query);
        return $query;
    }

    public static function query($query) {
        $result = true;
        geekybot::$_db->query($query);
        if (geekybot::$_db->last_error != null) {
            $result = false;
        }
        return $result;
    }

}

?>
