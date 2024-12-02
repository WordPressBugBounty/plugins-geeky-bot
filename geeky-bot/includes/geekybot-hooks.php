<?php
if (!defined('ABSPATH'))
    die('Restricted Access');


// Updates authentication to return an error when one field or both are blank
add_filter( 'authenticate', 'geekybot_authenticate_username_password', 30, 3);

/**
* Commit For Zub
**/
function geekybot_authenticate_username_password( $user, $username, $password ){
    if ( is_a($user, 'WP_User') ) {
        return $user;
    }
    $wp_submit = GEEKYBOTrequest::GEEKYBOT_getVar('wp-submit','post','');
    $pwd = GEEKYBOTrequest::GEEKYBOT_getVar('pwd','post','');
    $log = GEEKYBOTrequest::GEEKYBOT_getVar('log','post','');
    if ($wp_submit != '' && $pwd != '' && $log != ''){
        return false;
    }
    return $user;
}



add_action('admin_head', 'geekybot_custom_css_add');

function geekybot_custom_css_add() {
    
}

// used for tracking error messages
function geekybot_errors() {
    static $wp_error; // Will hold global variable safely
    return isset($wp_error) ? $wp_error : ($wp_error = new WP_Error(null, null, null));
}

// displays error messages from form submissions
function geekybot_show_error_messages() {
    if ($codes = geekybot_errors()->get_error_codes()) {
        $html = '<div class="geekybot_errors">';
        // Loop error codes and display errors
        $alert_class = 'danger';
        foreach ($codes as $code) {
            $message = geekybot_errors()->get_error_message($code);
            $html .= '<div class="frontend error"><p>' . esc_html($message) . '</p></div>';
        }
        $html .= '</div>';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }
}
?>
