<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTajax {

    function __construct() {
        add_action("wp_ajax_geekybot_ajax", array($this, "GEEKYBOT_ajaxhandler")); // when user is login
        add_action("wp_ajax_nopriv_geekybot_ajax", array($this, "GEEKYBOT_ajaxhandler")); // when user is not login
    }

    function GEEKYBOT_ajaxhandler() {
        $fucntin_allowed = array( 'getUserChatHistoryMessages','deleteBotCustomImage','deleteWelcomeMessageImg','deleteSupportUserImage', 'saveUserInputAjax', 'saveResponsesAjax', 'saveCustomeActionAjax', 'saveCustomeFormAjax', 'updateFormsValueFormAjax', 'updateActionValueOnPopupFormAjax', 'savedefaultFallbackFormAjax', 'savedefaultIntentFallbackFormAjax', 'updateStoryAjax', 'getVariablesValuesForSelect', 'bindValuesOnSelectAjax', 'getUserInputFormBodyHTMLAjax', 'getResponseTextFormBodyHTMLAjax', 'getResponseFunctionFormBodyHTMLAjax', 'getResponseActionFormBodyHTMLAjax', 'getResponseFormFormBodyHTMLAjax', 'resetStory', 'getDefaultFallbackFormBodyHTMLAjax', 'getDefaultIntentFallbackFormBodyHTMLAjax', 'geekybotBuildAIStoryFromTemplate', 'geekybotBuildWooCommerceStory', 'geekybotEnableWebSearch', 'geekybotDisableWebSearch', 'geekybotEnableDisableNewPostTypes', 'hideVideoPopupFromAdmin', 'getTextForTooltip', 'addIntentToStory', 'getNextChatHistorySessions', 'geekybotEditStoryName', 'saveVariableFromButtonIntent', 'deleteDefaultFallback', 'deleteIntentFallback','getCustomListingStylePopupValueFields', 'downloadandinstalladdonfromAjax','getSearchResults', 'geekybotCheckUpdates', 'getCustomHeadingForFunction', 'geekybotFreshMessages', 'geekybotDownloadGoogleClientLibrary', 'geekybotDownloadOpenAiAssistantLibrary', 'geekybotCheckOpenRouterStatus', 'geekybotCheckDialogflowStatus', 'geekybotCheckOpenAIStatus');
        $task = GEEKYBOTrequest::GEEKYBOT_getVar('task');
        if($task != '' && in_array($task, $fucntin_allowed)){
            $module = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotme');
            $module = geekybotphplib::GEEKYBOT_clean_file_path($module);
            $result = GEEKYBOTincluder::GEEKYBOT_getModel($module)->$task();
            echo wp_kses($result, GEEKYBOT_ALLOWED_TAGS);
            die();
        }else{
            die('Not Allowed!');
        }
    }

}

$jsajax = new GEEKYBOTajax();
?>
