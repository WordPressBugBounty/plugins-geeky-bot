<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTEncoder {

    private $securekey, $iv;

    function __construct($textkey) {
        $this->securekey = hash('sha256', $textkey, TRUE);
        $this->iv = mcrypt_create_iv(32);
    }

    function encrypt($input) {
        return geekybotphplib::GEEKYBOT_safe_encoding(mcrypt_engeekybotphplib::GEEKYBOT_crypt(MCRYPT_RIJNDAEL_256, $this->securekey, $input, MCRYPT_MODE_ECB, $this->iv));
    }

    function decrypt($input) {
        return geekybotphplib::GEEKYBOT_trim(mcrypt_degeekybotphplib::GEEKYBOT_crypt(MCRYPT_RIJNDAEL_256, $this->securekey, geekybotphplib::GEEKYBOT_safe_decoding($input), MCRYPT_MODE_ECB, $this->iv));
    }

}

?>