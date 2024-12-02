<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTlayout {

    static function GEEKYBOT_getNoRecordFound($message = null, $linkarray = array()) {
        if($message == null){
            $message = __('Could not find any matching results', 'geeky-bot');
        }
        $html = '
                <div class="geekybot-error-messages-wrp">
                    <div class="geekybot-error-msg-image-wrp">
                        <img class="geekybot-error-msg-image" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/errors/no-record.png" alt="'.esc_attr(__("no record", "geeky-bot")).'" />
                    </div>
                    <div class="geekybot-error-msg-txt">
                        ' . esc_html($message) . ' !...
                    </div>
                    <div class="geekybot-error-msg-actions-wrp">';
                        if(!empty($linkarray)){
                            foreach($linkarray AS $link){
                                if( isset($link['text']) && $link['text'] != ''){
                                    $html .= '<a class="geekybot-error-msg-act-btn geekybot-error-msg-act-login-btn" href="' .esc_url($link['link']) . '">' . esc_html($link['text']) . '</a>';
                                }
                            }
                        }
        $html .=    '</div>
                </div>
        ';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }

    static function GEEKYBOT_getAdminPopupNoRecordFound() {
        $html = '
                <div class="geekybot-error-messages-wrp">
                    <div class="geekybot-error-msg-image-wrp">
                        <img class="geekybot-error-msg-image" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/errors/no-record.png" alt="'.esc_attr(__("no record", "geeky-bot")).'" />
                    </div>
                    <div class="geekybot-error-msg-txt">
                        '.esc_html(__("No record found !...","geeky-bot")).'
                    </div>
                </div>
        ';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }

    static function GEEKYBOT_getNoRecordFoundInSpecialCase() {
        if (is_admin()) {
            $link = 'admin.php?page=geekybot_geekybot';
        } else {
            $link = get_the_permalink();
        }
        $html = '
                <div class="geekybot-error-messages-wrp">
                    <div class="geekybot-error-msg-image-wrp">
                        <img class="geekybot-error-msg-image" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/errors/no-record.png" alt="'.esc_attr(__("no record", "geeky-bot")).'" />
                    </div>
                    <div class="geekybot-error-msg-txt">
                        ' . esc_html(__('No record found !...', 'geeky-bot')) . '
                    </div>
                </div>
        ';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }

    static function GEEKYBOT_getSystemOffline() {
        $offline_text = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigurationByConfigName('offline_text');
        $html = '
                <div class="geekybot-error-messages-wrp geekybot-error-messages-style2">
                    <div class="geekybot-error-msg-image-wrp">
                        <img class="geekybot-error-msg-image" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/errors/system-offline.png" alt="'.esc_attr(__("system offline", "geeky-bot")).'" />
                    </div>
                    <div class="geekybot-error-msg-txt">
                        ' . wp_kses($offline_text, GEEKYBOT_ALLOWED_TAGS) . '
                    </div>
                    <div class="geekybot-error-msg-txt2">
                        '.esc_html(__('Unfortunately sytem is offline for a bit of maintenance right now. But soon we will be up.','geeky-bot')).'
                    </div>
                </div>
        ';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }

    static function GEEKYBOT_getUserDisabledMsg() {
        $html = '
                <div class="geekybot-error-messages-wrp">
                    <div class="geekybot-error-msg-image-wrp">
                        <img class="geekybot-error-msg-image" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/errors/user-ban.png" alt="'.esc_attr(__("user ban", "geeky-bot")).'" />
                    </div>
                    <div class="geekybot-error-msg-txt">
                        ' . esc_html(__('Your account is disabled, please contact system administrator !...', 'geeky-bot')) . '
                    </div>
                </div>
        ';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }

    static function GEEKYBOT_getUserGuest() {
        $html = '<div class="geekybot-error-messages-wrp">
                    <div class="geekybot-error-msg-image-wrp">
                        <img class="geekybot-error-msg-image" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/errors/login.png" alt="'.esc_attr(__("login", "geeky-bot")).'" />
                    </div>
                    <div class="geekybot-error-msg-txt">
                        ' . esc_html(__('To Access This Page Please Login !...', 'geeky-bot')) . '
                    </div>
                    <div class="geekybot-error-msg-actions-wrp">
                        <a class="geekybot-error-msg-act-btn geekybot-error-msg-act-login-btn" href="' . esc_url(get_the_permalink()) . '">' . esc_html(__('Back to control panel', 'geeky-bot')) . '</a>
                    </div>
                </div>
        ';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }

    static function GEEKYBOT_setMessageFor($for, $link = null, $linktext = null, $return = 0) {
        $image = null;
        $description = '';
        switch ($for) {
            case '1': // User is guest
                $description = __('You are not logged in', 'geeky-bot');
                break;
            case '5': // When user is disabled from configuration
                $description = __('User Is Disabled By Admin', 'geeky-bot');
                break;
            case '6': // When intent is not approved or expired
                $description = __('The page you are looking for no longer exists', 'geeky-bot');
                break;
            case '8': // Already loged in
                $description = __('You are already logged in', 'geeky-bot');
                break;
            case '9': // User have no role
                $description = __('Please select your role', 'geeky-bot');
                break;
            case '10': // User have no role
                $description = __('You are not allowed', 'geeky-bot');
                break;
            case '16':
                $description = __('You are Not Allowed To add More than One','geeky-bot').' '.esc_html($linktext).' '.__('Contact Adminstrator','geeky-bot');
                break;
        }
        $html = GEEKYBOTlayout::GEEKYBOT_getUserNotAllowed($description, $link, $linktext, $image, $return);
        if ($return == 1) {
            return $html;
        }
    }

    static function GEEKYBOT_getUserNotAllowed($description, $link, $linktext, $image, $return = 0) {
        $html = '<div class="geekybot-error-messages-wrp">
                    <div class="geekybot-error-msg-image-wrp">
                        <img class="geekybot-error-msg-image" src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/errors/not-allowed.png" alt="'.esc_attr(__("not allowed", "geeky-bot")).'" />
                    </div>
                    <div class="geekybot-error-msg-txt">
                        ' . wp_kses($description, GEEKYBOT_ALLOWED_TAGS) . ' !...
                    </div>
                </div>
        ';
        if ($return == 0) {
            echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
        } else {
            return $html;
        }
    }

    static function GEEKYBOT_getUserAlreadyLoggedin( $link ) {
        $html = '<div class="geekybot-error-messages-wrp">
                    <div class="geekybot-error-msg-txt">
                        ' . esc_html(__('You are already logged in !...', 'geeky-bot')) . '
                    </div>
                    <div class="geekybot-error-msg-actions-wrp">
                    ';
        $html .= '<a class="geekybot-error-msg-act-btn geekybot-error-msg-act-login-btn" href="' . esc_url($link). '">' . esc_html(__('Logout','geeky-bot')) . '</a>';
        $html .= '</div>
                </div>
        ';
        echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
    }

}

?>