<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTMessages {
    /*
     * setLayoutMessage
     * @params $message = Your message to display
     * @params $type = Messages types => 'updated','error','update-nag'
     */

    public static $counter;

    public static function GEEKYBOT_setLayoutMessage($message, $type, $msgkey) {
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('wpcbnotification')->geekybot_addSessionNotificationDataToTable($message,$type,'notification',$msgkey);
    }

    public static function GEEKYBOT_getLayoutMessage($msgkey) {

        $frontend = (is_admin()) ? '' : 'frontend';
        $divHtml = '';
        $notificationdata = GEEKYBOTincluder::GEEKYBOT_getObjectClass('wpcbnotification')->geekybot_getNotificationDatabySessionId('notification',$msgkey,true);
        if (isset($notificationdata['msg'][0]) && isset($notificationdata['type'][0])) {
            for ($i = 0; $i < COUNT($notificationdata['msg']); $i++){
                if (isset($notificationdata['msg'][$i]) && isset($notificationdata['type'][$i])) {
                    if(is_admin()){
                        $divHtml .= '<div class="frontend ' . esc_html($notificationdata['type'][$i]) . '"><p>' . esc_html($notificationdata['msg'][$i]) . '</p></div>';
                    }else{
                        $divHtml .= '<div class=" ' . esc_attr($frontend) . ' ' . esc_attr($notificationdata['type'][$i]) . '"><p>' . esc_html($notificationdata['msg'][$i]) . '</p></div>';
                    }
                }
            }
        }

	    echo wp_kses($divHtml, GEEKYBOT_ALLOWED_TAGS);
    }

    public static function GEEKYBOT_getMSelectionEMessage() { // multi selection error message
        return esc_html(__('Please first make a selection from the list', 'geeky-bot'));
    }

    public static function GEEKYBOT_getMessage($result, $entity) {
        $msg['message'] = __('Unknown', 'geeky-bot');
        $msg['status'] = "updated";
        $msg1 = GEEKYBOTMessages::GEEKYBOT_getEntityName($entity);
        switch ($result) {
            case GEEKYBOT_INVALID_REQUEST:
                $msg['message'] = __('Invalid request', 'geeky-bot');
                $msg['status'] = 'error';
                break;
            case GEEKYBOT_THEME_SAVED:
                $msg2 = __('has been successfully applied.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_THEME_SAVE_ERROR:
                $msg['status'] = "error";
                $msg2 = __('Error applying the new theme.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_CONFIGURATION_SAVED:
                $msg2 = __('has been successfully saved.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_CONFIGURATION_SAVE_ERROR:
                $msg['status'] = "error";
                $msg2 = __('has not been saved.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_SAVED:
                $msg2 = __('has been successfully saved.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_RESET:
                $msg2 = __('has been successfully reset.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_SAVE_ERROR:
                $msg['status'] = "error";
                $msg2 = __('has not been saved.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_SAVE_STORY_NAME_ERROR:
                $msg['status'] = "error";
                $msg2 = __('Every Story must have a name.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg2;
                break;
            case GEEKYBOT_SAVE_INTENT_ARRAY_ERROR:
                $msg['status'] = "error";
                $msg2 = __('Every Story must have at least 1 intent.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg2;
                break;
            case GEEKYBOT_DELETED:
                $msg2 = __('has been successfully deleted.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;

            case GEEKYBOT_EXPORT:
                $msg2 = __('has been successfully exported.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;

            case GEEKYBOT_EXPORT_ERROR:
                $msg['status'] = "error";
                $msg['message'] = __('Data has not been exported.', 'geeky-bot');
                break;

            case GEEKYBOT_NOT_EXIST:
                $msg['status'] = "error";
                $msg['message'] = __('Record not exist.', 'geeky-bot');
                break;
            case GEEKYBOT_DELETE_ERROR:
                $msg['status'] = "error";
                $msg2 = __('has not been deleted.', 'geeky-bot');
                if ($msg1) {
                    $msg['message'] = $msg1 . ' ' . $msg2;
                    if (GEEKYBOTMessages::$counter) {
                        if(GEEKYBOTMessages::$counter > 1){
                            $msg['message'] = GEEKYBOTMessages::$counter . ' ' . $msg['message'];
                        }
                    }
                }
                break;
            case GEEKYBOT_KEY_EMPTY_ERROR:
                $msg['status'] = "error";
                $msg['message'] = __('Please insert key.', 'geeky-bot');
                break;
            case GEEKYBOT_KEY_INVALID_ERROR:
                $msg['status'] = "error";
                $msg['message'] = __('key is not Valid.', 'geeky-bot');
                break;
            case GEEKYBOT_VERIFIED:
                $msg['message'] = __('transaction has been successfully verified.', 'geeky-bot');
                break;
            case GEEKYBOT_UN_VERIFIED:
                $msg['message'] = __('transaction has been successfully un-verified.', 'geeky-bot');
                break;
            case GEEKYBOT_VERIFIED_ERROR:
                $msg['message'] = __('transaction has not been successfully verified.', 'geeky-bot');
                break;
            case GEEKYBOT_UN_VERIFIED_ERROR:
                $msg['message'] = __('transaction has not been successfully un-verified.', 'geeky-bot');
                break;
            case GEEKYBOT_REQUIRED:
                $msg['message'] = __('Fields has been successfully required.', 'geeky-bot');
                break;
            case GEEKYBOT_REQUIRED_ERROR:
                $msg['status'] = "error";
                if (GEEKYBOTMessages::$counter) {
                    if (GEEKYBOTMessages::$counter == 1)
                        $msg['message'] = GEEKYBOTMessages::$counter . ' ' . __('Field has not been required.', 'geeky-bot');
                    else
                        $msg['message'] = GEEKYBOTMessages::$counter . ' ' . __('Fields has not been required.', 'geeky-bot');
                }else {
                    $msg['message'] = __('Field has not been required.', 'geeky-bot');
                }
                break;
            case GEEKYBOT_NOT_REQUIRED:
                $msg['message'] = __('Fields has been successfully not required.', 'geeky-bot');
                break;
            case GEEKYBOT_NOT_REQUIRED_ERROR:
                $msg['status'] = "error";
                if (GEEKYBOTMessages::$counter) {
                    if (GEEKYBOTMessages::$counter == 1)
                        $msg['message'] = GEEKYBOTMessages::$counter . ' ' . __('Field has not been not required.', 'geeky-bot');
                    else
                        $msg['message'] = GEEKYBOTMessages::$counter . ' ' . __('Fields has not been not required.', 'geeky-bot');
                }else {
                    $msg['message'] = __('Field has not been not required.', 'geeky-bot');
                }
                break;
            case GEEKYBOT_STATUS_CHANGED:
                $msg2 = __('status has been updated.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_STATUS_CHANGED_ERROR:
                $msg['status'] = "error";
                $msg2 = __('status has not been updated.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_DATA_SYNCHRONIZE:
                $msg2 = __('data has been synchronized.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_DATA_SYNCHRONIZE_ERROR:
                $msg['status'] = "error";
                $msg2 = __('data has not been synchronized.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_IN_USE:
                $msg['status'] = "error";
                $msg2 = __('in use cannot deleted.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_ALREADY_EXIST:
                $msg['status'] = "error";
                $msg2 = __('already exist.', 'geeky-bot');
                if ($msg1)
                    $msg['message'] = $msg1 . ' ' . $msg2;
                break;
            case GEEKYBOT_FILE_TYPE_ERROR:
                $msg['status'] = "error";
                $msg['message'] = __('File type error.', 'geeky-bot');
                break;
            case GEEKYBOT_FILE_SIZE_ERROR:
                $msg['status'] = "error";
                $msg['message'] = __('File size error.', 'geeky-bot');
                break;
        }
        return $msg;
    }

    static function GEEKYBOT_getEntityName($entity) {
        $name = "";
        $entity = geekybotphplib::GEEKYBOT_strtolower($entity);
        switch ($entity) {
            case GEEKYBOT_INTENT: $name = __('Intent', 'geeky-bot');
                if(GEEKYBOTMessages::$counter){
                    if(GEEKYBOTMessages::$counter >1){
                        $name = __('Intent', 'geeky-bot');
                    }
                }
                break;
            case 'intent':
                $name = __('Intent', 'geeky-bot');
                if(GEEKYBOTMessages::$counter){
                    if(GEEKYBOTMessages::$counter >1){
                        $name = __('Intents', 'geeky-bot');
                    }
                }
                break;
            case 'intent':$name = __('Intent', 'geeky-bot');
                break;
            case 'responses':$name = __('Response', 'geeky-bot');
                break;
            case 'forms':$name = __('Forms', 'geeky-bot');
                break;
            case 'intentgroup':$name = __('Intent Group', 'geeky-bot');
                break;
            case 'slots':$name = __('Variable', 'geeky-bot');
                break;
            case 'story':$name = __('Story', 'geeky-bot');
                break;
            case 'posttype':$name = __('Post type', 'geeky-bot');
                break;
            case 'websearch':$name = __('AI web search', 'geeky-bot');
                break;
            case 'action':$name = __('Action', 'geeky-bot');
                break;
            case 'themes':$name = __('Changes', 'geeky-bot');
                break;
            case 'configuration':$name = __('Settings', 'geeky-bot');
                break;
            case 'export':$name = __('Data', 'geeky-bot');
                break;
            case 'import':$name = __('Data', 'geeky-bot');
                break;
            case 'message':$name = __('Message', 'geeky-bot');
                break;
            case 'woocommerce':$name = __('Woocommerce', 'geeky-bot');
                break;
            case CONFIGURATION:$name = __('Configuration', 'geeky-bot');
                break;
        }
        return wp_kses($name, GEEKYBOT_ALLOWED_TAGS);
    }

    public static function GEEKYBOT_showMessage($message,$type,$return=0) {
        $divHtml = '';
        if($type == 'updated'){
            $alert_class = 'success';
            $img_name = 'bot-alert-successful.png';
        }else if($type == 'saved'){
            $alert_class = 'success';
            $img_name = 'bot-alert-successful.png';
        }else if($type == 'error'){
            $alert_class = 'danger';
            $img_name = 'bot-alert-unsuccessful.png';
        }
        $divHtml .= '<div class="alert alert-' . esc_attr($alert_class) . '" role="alert" id="autohidealert">
            <img class="leftimg" src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'includes/images/'.esc_attr($img_name).'" />
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            '. esc_html($message) . '
        </div>';
        if($return){
            return $divHtml;
        }
        echo wp_kses($divHtml, GEEKYBOT_ALLOWED_TAGS);
    }

}

?>
