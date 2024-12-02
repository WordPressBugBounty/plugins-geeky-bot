<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTwpcbsession {

    public $sessionid;
    public $sessionexpire;
    public $nextsessionexpire;
    private $sessiondata;
    private $datafor;

    function __construct( ) {
        $this->init();
    }

    function geekybot_getSessionId(){
        return $this->sessionid;
    }

    function init(){
        if (isset($_COOKIE['_wpgeekybot_session_'])) {
            $cookie = geekybotphplib::GEEKYBOT_stripslashes($_COOKIE['_wpgeekybot_session_']);
            $user_cookie = geekybotphplib::GEEKYBOT_explode('/', $cookie);
            $this->sessionid = geekybotphplib::GEEKYBOT_preg_replace("/[^A-Za-z0-9_]/", '', $user_cookie[0]);
            $this->sessionexpire = absint($user_cookie[1]);
            $this->nextsessionexpire = absint($user_cookie[2]);
            // Update options session expiration
            if (time() > $this->nextsessionexpire) {
                $this->geekybot_set_cookies_expiration();
            }
        } else {
            $sessionid = $this->geekybot_generate_id();
            $this->sessionid = $sessionid . get_option( '_wpgeekybot_session_', 0 );
            $this->geekybot_set_cookies_expiration();
        }
        $this->geekybot_set_user_cookies();
        return $this->sessionid;
    }

    private function geekybot_set_cookies_expiration(){
        $this->sessionexpire = time() + (int)(30*60);
        $this->nextsessionexpire = time() + (int)(60*60);
    }

    private function geekybot_generate_id(){
        do_action('geekyboot_load_phpass');
        $hash = new PasswordHash( 16, false );

        return geekybotphplib::GEEKYBOT_md5( $hash->get_random_bytes( 32 ) );
    }

    private function geekybot_set_user_cookies(){
        geekybotphplib::GEEKYBOT_setcookie( '_wpgeekybot_session_', $this->sessionid . '/' . $this->sessionexpire . '/' . $this->nextsessionexpire , $this->sessionexpire, COOKIEPATH, COOKIE_DOMAIN);
        $count = get_option( '_wpgeekybot_session_', 0 );
        update_option( '_wpgeekybot_session_', ++$count);
    }

}

?>
