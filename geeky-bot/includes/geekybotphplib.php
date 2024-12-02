<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class geekybotphplib {

    function __construct() {
    }

    static function GEEKYBOT_str_replace($search,$replace,$content){
        if($content == ''){
            return $content;
        }
        if($replace === null){
            return $content;
        }

        $content = str_replace($search, $replace, $content);
        return $content;
    }

    static function GEEKYBOT_safe_encoding($string){
        if($string == ''){
            return $string;
        }
        $string = base64_encode($string);
        //return mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string));
        return $string;
    }

    static function GEEKYBOT_safe_decoding($string){
        if($string == ''){
            return $string;
        }
        $string = base64_decode($string);
        return $string;
    }


    public static function GEEKYBOT_strstr($haystack, $needle) {
        if($haystack == '' || $needle == ''){
            return false;
        }
        return strstr($haystack, $needle);
    }

    public static function GEEKYBOT_explode($separator, $haystack) {
        if($separator == ''){
            return array();
        }
        if($haystack == ''){
            return array();
        }
        return explode($separator, $haystack);
    }

    public static function GEEKYBOT_wp_strip_all_tags($string, $allowable_tags = NULL) {
      if (!is_null($string)) {
        return wp_strip_all_tags($string, $allowable_tags);
      }
      return $string;
    }


    public static function GEEKYBOT_htmlentities($string) {
        if($string == ''){
            return '';
        }
        return htmlentities($string);
    }

    public static function GEEKYBOT_strtoupper($string) {
        if($string == ''){
            return '';
        }
        return strtoupper($string);
    }

    public static function GEEKYBOT_basename($string,$suffix = '') {
        $basename = '';
        if($string !== ''){
           $basename = basename($string,$suffix);
        }
        return $basename;
    }

    public static function GEEKYBOT_dirname($string,$lvls = 1) {
        $dirname = '';
        if($string !== ''){
           $dirname = dirname($string,$lvls);
        }
        return $dirname;
    }

    public static function GEEKYBOT_substr($str, $start, $length = null) {
        $output = null;
        if ($str !== null) {
            if ($length !== null) {
                $output = substr($str, $start, $length);
            } else {
                $output = substr($str, $start);
            }
        }
        return $output;
    }

    public static function GEEKYBOT_ucwords($str, $delimiters = "") {
        $output = null;
        if ($str !== null) {
            $output = ucwords($str, $delimiters);
        }
        return $output;
    }

    public static function GEEKYBOT_preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null){
        $output = null;
        if ($pattern !== null && $replacement !== null && $subject !== null) {
            $output = preg_replace($pattern, $replacement, $subject, $limit, $count);
        }
        return $output;
    }

    public static function GEEKYBOT_strlen($str){
        $output = null;
        if ($str !== null) {
            $output = strlen($str);
        }
        return $output;
    }

    public static function GEEKYBOT_md5($str, $raw_output = false){
        $output = null;
        if ($str !== null) {
            $output = md5($str, $raw_output);
        }
        return $output;
    }

    public static function GEEKYBOT_preg_match($pattern, $subject, &$matches = null, $flags = 0, $offset = 0){
        $output = null;
        if ($pattern !== null && $subject !== null) {
            $output = preg_match($pattern, $subject, $matches, $flags, $offset);
        }
        return $output;
    }

    public static function GEEKYBOT_strtolower($str){
        $output = null;
        if ($str !== null) {
            $output = strtolower($str);
        }
        return $output;
    }

    public static function GEEKYBOT_strpos($haystack, $needle, $offset = 0){
        $output = null;
        if ($haystack !== null && $needle !== null) {
            $output = strpos($haystack, $needle, $offset);
        }
        return $output;
    }

    public static function GEEKYBOT_str_repeat($input, $multiplier){
        $output = null;
        if ($input !== null && $multiplier !== null) {
            $output = str_repeat($input, $multiplier);
        }
        return $output;
    }

    public static function GEEKYBOT_stripslashes($str){
        $output = null;
        if ($str !== null) {
            $output = stripslashes($str);
        }
        return $output;
    }

    public static function GEEKYBOT_htmlspecialchars($string, $flags = ENT_COMPAT | ENT_HTML401, $encoding = 'UTF-8', $double_encode = true){
        $output = null;
        if ($string !== null) {
            $output = htmlspecialchars($string, $flags, $encoding, $double_encode);
        }
        return $output;
    }

    public static function GEEKYBOT_setcookie($name, $value = "", $expires = 0, $path = "", $domain = "", $secure = false, $httponly = false){
        $output = null;
        if ($name != null && $domain !== null) {
            if (!headers_sent()) {
          	    $output = setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
            }
        }
        return $output;
    }

    public static function GEEKYBOT_urlencode($str){
        $output = null;
        if ($str !== null) {
            $output = urlencode($str);
        }
        return $output;
    }

    public static function GEEKYBOT_crypt($str, $salt = null)
    {
        $output = null;
        if ($str !== null) {
            if ($salt !== null) {
                $output = crypt($str, $salt);
            } else {
                $output = crypt($str);
            }
        }
        return $output;
    }

    public static function GEEKYBOT_urldecode($str)
    {
        $output = null;
        if ($str !== null) {
            $output = urldecode($str);
        }
        return $output;
    }

    public static function GEEKYBOT_trim($str, $charlist = ""){
        $output = null;
        if ($str !== null) {
            $output = trim($str, $charlist);
        }
        return $output;
    }

    public static function GEEKYBOT_rtrim($str, $chars = null){
        $output = null;
        if ($str !== null) {
            if ($chars !== null) {
                $output = rtrim($str, $chars);
            } else {
                $output = rtrim($str);
            }
        }
        return $output;
    }

    public static function GEEKYBOT_addslashes($str){
        $output = null;
        if ($str !== null) {
            $output = addslashes($str);
        }
        return $output;
    }

    public static function GEEKYBOT_stristr($haystack, $needle, $before_needle = false) {
        $output = null;
        if ($haystack !== null && $needle !== null) {
            $output = stristr($haystack, $needle, $before_needle);
        }
        return $output;
    }

    public static function GEEKYBOT_ucfirst($str) {
        $output = null;
        if ($str !== null) {
            $output = ucfirst($str);
        }
        return $output;
    }

    public static function GEEKYBOT_parse_str($str, &$output){
        if ($str !== null) {
            parse_str($str, $output);
        }
    }


    public static function GEEKYBOT_preg_split($pattern, $subject, $limit = -1, $flags = 0){
        $output = null;
        if ($pattern !== null && $subject !== null) {
            $output = preg_split($pattern, $subject, $limit, $flags);
        }
        return $output;
    }

    public static function GEEKYBOT_number_format($num,$decimals = 0,$decimal_separator = ".",$thousands_separator = ","){
        $output = null;
        if ($num !== null) {
            $output = number_format($num,$decimals,$decimal_separator,$thousands_separator);
        }
        return $output;
    }

    public static function GEEKYBOT_strtotime($datetime, $baseTimestamp = null){
        $output = null;
        if ($datetime !== null) {
            $output = strtotime($datetime, $baseTimestamp);
        }
        return $output;
    }


}
?>
