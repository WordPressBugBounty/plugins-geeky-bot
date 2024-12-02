<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTrequest {
    /*
     * Check Request from both the Get and post method
     */

    static function GEEKYBOT_getVar($variable_name, $method = null, $defaultvalue = null, $typecast = null) {
        // nonce varification start
        $nonce = geekybot::$_data['sanitized_args']['_wpnonce'];
        if (! wp_verify_nonce( $nonce, 'VERIFY-GEEKYBOT-INTERNAL-NONCE') ) {
            die( 'Security check Failed' );
        }
        // nonce varification end
        $value = null;
        if ($method == null) {
            if (isset($_GET[$variable_name])) {
                if(is_array($_GET[$variable_name])){
                    $value = geekybot::GEEKYBOT_sanitizeData($_GET[$variable_name]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                }else{
                    $value = geekybot::GEEKYBOT_sanitizeData($_GET[$variable_name]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                }
            } elseif (isset($_POST[$variable_name])) {
                if(is_array($_POST[$variable_name])){
                    $value = geekybot::GEEKYBOT_sanitizeData($_POST[$variable_name]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                }else{
                    $value = geekybot::GEEKYBOT_sanitizeData($_POST[$variable_name]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                }
            } elseif (get_query_var($variable_name)) {
                $value = get_query_var($variable_name);
            } elseif (isset(geekybot::$_data['sanitized_args'][$variable_name]) && geekybot::$_data['sanitized_args'][$variable_name] != '') {
                $value = geekybot::$_data['sanitized_args'][$variable_name];
            }
        } else {
            $method = geekybotphplib::GEEKYBOT_strtolower($method);
            switch ($method) {
                case 'post':
                    if (isset($_POST[$variable_name]))
                        $value = geekybot::GEEKYBOT_sanitizeData($_POST[$variable_name]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    break;
                case 'get':
                    if (isset($_GET[$variable_name]))
                        $value = geekybot::GEEKYBOT_sanitizeData($_GET[$variable_name]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    break;
            }
        }
        if ($typecast != null) {
            $typecast = geekybotphplib::GEEKYBOT_strtolower($typecast);
            switch ($typecast) {
                case "int":
                    $value = (int) $value;
                    break;
                case "string":
                    $value = (string) $value;
                    break;
            }
        }
        if ($value == null)
            $value = $defaultvalue;
        if(!is_array($value)){
            if ($value != null){
                $value = geekybotphplib::GEEKYBOT_stripslashes($value);
            }
        }
        return $value;
    }

    /*
     * Check Request from both the Get and post method
     */

    static function GEEKYBOT_get($method = null) {
        // nonce varification start
        $nonce = geekybot::$_data['sanitized_args']['_wpnonce'];
        if (! wp_verify_nonce( $nonce, 'VERIFY-GEEKYBOT-INTERNAL-NONCE') ) {
            die( 'Security check Failed' );
        }
        // nonce varification end
        $array = null;
        if ($method != null) {
            $method = geekybotphplib::GEEKYBOT_strtolower($method);
            switch ($method) {
                case 'post':
                    $array = geekybot::GEEKYBOT_sanitizeData($_POST);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    break;
                case 'get':
                    $array = geekybot::GEEKYBOT_sanitizeData($_GET);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    break;
            }
            foreach($array as $key=>$value){
                if(is_string($value)){
                    $array[$key] = geekybotphplib::GEEKYBOT_stripslashes($value);
                }
            }
        }
        return $array;
    }

    /*
     * Check Request from both the Get and post method
     */

    static function GEEKYBOT_getLayout($layout ,$defaultvalue , $method = null ) {
        // nonce varification start
        $nonce = geekybot::$_data['sanitized_args']['_wpnonce'];
        if (! wp_verify_nonce( $nonce, 'VERIFY-GEEKYBOT-INTERNAL-NONCE') ) {
            die( 'Security check Failed' );
        }
        // nonce varification end
        $layoutname = null;
        if ($method != null) {
            $method = geekybotphplib::GEEKYBOT_strtolower($method);
            switch ($method) {
                case 'post':
                    $layoutname = geekybot::GEEKYBOT_sanitizeData($_POST[$layout]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    break;
                case 'get':
                    $layoutname = geekybot::GEEKYBOT_sanitizeData($_GET[$layout]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    break;
            }
        } else {
            if (isset($_POST[$layout]))
                $layoutname = geekybot::GEEKYBOT_sanitizeData($_POST[$layout]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            elseif (isset($_GET[$layout]))
                $layoutname = geekybot::GEEKYBOT_sanitizeData($_GET[$layout]);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            elseif (get_query_var($layout))
                $layoutname = get_query_var($layout);
            elseif (isset(geekybot::$_data['sanitized_args'][$layout]) && geekybot::$_data['sanitized_args'][$layout] != '')
                $layoutname = geekybot::$_data['sanitized_args'][$layout];
        }
        if ($layoutname == null) {
            $layoutname = $defaultvalue;
        }
        if (is_admin()) {
            $layoutname = 'admin_' . $layoutname;
        }
        return $layoutname;
    }

}

?>
