<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTstackModel {

    function getMessagekey(){
        $key = 'stack'; if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    function storeStack($data) {
        if (empty($data))
            return false;
        if ($data['chat_id'] == '') {
            $data['chat_id'] = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        }
        $query = "SELECT count(id) FROM `" . geekybot::$_db->prefix . "geekybot_stack` WHERE chat_id = '" . esc_sql($data['chat_id'])."' AND intent_id = " . esc_sql($data['intent_id'])." AND response_id = " . esc_sql($data['response_id'])." AND story_id = " . esc_sql($data['story_id']);
        $count = geekybot::$_db->get_var($query);
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('stack');
        if (!$row->bind($data)) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (!$row->store()) {
            return GEEKYBOT_SAVE_ERROR;
        }
        return GEEKYBOT_SAVED;
    }

    function isUserStackEmpty() {
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $user_id = get_current_user_id();
        $inquery = '';
        if (is_numeric($user_id)){
            $inquery = " AND user_id =".esc_sql($user_id);
        }
        $query = "SELECT COUNT(id) FROM `" . geekybot::$_db->prefix . "geekybot_stack` where chat_id = '".esc_sql($chat_id)."'";
        $query .= $inquery;
        $query .= " ORDER BY id  DESC;  ";
        return geekybotdb::GEEKYBOT_get_var($query);
    }

    function isStackStoryIsForm($savedData) {
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $stackData = array();
        // recheck
        // get the latest form's record form stack 
        // get record based on saved story data
        $query = "SELECT stack.intent_id, stack.response_id,stack.story_id FROM `" . geekybot::$_db->prefix . "geekybot_stack` AS stack
        LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_stories` as story ON story.id = stack.story_id
        WHERE story.is_form = 1 AND stack.chat_id = '".esc_sql($chat_id)."'";

        if (isset($savedData) && $savedData != '') {
            $query .= " AND story.id = ". esc_sql($savedData);
        }
        $query .= " ORDER BY stack.id DESC ";
        $stackPreviousData = geekybotdb::GEEKYBOT_get_row($query);
        // 
        if (isset($stackPreviousData)) {
            $query = "SELECT MAX(ranking) FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `story_id` = ".esc_sql($stackPreviousData->story_id);
            $maxRanking = geekybotdb::GEEKYBOT_get_var($query);
            $query = "SELECT `ranking` FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` = ".esc_sql($stackPreviousData->intent_id)." AND `story_id` = ".esc_sql($stackPreviousData->story_id);
            $ranking = geekybotdb::GEEKYBOT_get_var($query);
            if (isset($ranking)) {
                $ranking++;
                $query = "SELECT `story_id`, `intent_id`, `intent_index`, `ranking` FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `story_id` = ".esc_sql($stackPreviousData->story_id)." AND `ranking` = ".esc_sql($ranking);
                $stackNextData = geekybotdb::GEEKYBOT_get_row($query);
                $stackData['stackNextData'] = $stackNextData;
                if (isset($stackNextData)) {
                    $query = "SELECT `intent_ids` FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `id` = ".esc_sql($stackNextData->story_id)." AND `is_form` = 1";
                    $storyData = geekybotdb::GEEKYBOT_get_var($query);
                    if (isset($storyData)) {
                        $storyData = json_decode($storyData);
                        $intent_index = $stackNextData->intent_index;
                        $intent_index++;
                        $intent_index = 'responseid_'.$intent_index.'id';
                        $responseId = $storyData->$intent_index;
                        if (isset($responseId)) {
                            $stackData['responseId'] = $responseId;
                        }
                    }
                    $stackData['stackNextData'] = $stackNextData;
                }
            }
            $stackData['maxRanking'] = $maxRanking;
            $stackData['stackPreviousData'] = $stackPreviousData;
        }
        return $stackData;
        print_r($stackData);
        die('123');
    }

    function findIntentInTheUserStack($chat_id) {
        $user_id = get_current_user_id();
        $inquery = '';
        if (is_numeric($user_id)){
            $inquery = " AND user_id =".esc_sql($user_id);
        }
        // recheck this
        // get the top 5 stories
        $query = "SELECT distinct(story_id) FROM `" . geekybot::$_db->prefix . "geekybot_stack` where chat_id = '".esc_sql($chat_id)."'";
        $query .= $inquery;
        $query .= " ORDER BY id  DESC LIMIT 5;";
        $data = geekybotdb::GEEKYBOT_get_results($query);
        $story_ids = array_column($data, 'story_id');
        $intent_ids = array();
        if (count($story_ids) > 0) {
            $comma_separated_story_ids = implode(",", $story_ids);
            // get intents ids by story ids
            $query = "SELECT intent_id FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` AS intent_rank where intent_rank.story_id IN (".$comma_separated_story_ids.")";
            $query .= " ORDER BY intent_rank.id DESC, intent_rank.ranking ASC ";

            $data = geekybotdb::GEEKYBOT_get_results($query);
            return $data;
            // $intent_ids = array_column($data, 'intent_id');
        }
        return $intent_ids;
    }

    function getLastActiveStoryFromStack() {
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        // get type of the latest story of the user form stack
        $query = "SELECT story.story_type, stack.story_id, stack.intent_id, stack.response_id FROM `" . geekybot::$_db->prefix . "geekybot_stack` as stack
                  LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_stories` as story ON stack.story_id = story.id WHERE stack.chat_id = '".esc_sql($chat_id)."' AND stack.chat_id != ''";
        $query .= " ORDER BY stack.id DESC;";
        return geekybotdb::GEEKYBOT_get_row($query);
    }

    function getFallbackFromLastActiveIntent() {
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        // get type of the latest story of the user form stack
        $query = "SELECT fallback.default_fallback FROM `" . geekybot::$_db->prefix . "geekybot_stack` as stack
                  LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_intents_fallback` as fallback ON stack.intent_id = fallback.group_id WHERE stack.chat_id = '".esc_sql($chat_id)."' AND stack.chat_id != ''";
        $query .= " ORDER BY stack.id DESC;";
        return geekybotdb::GEEKYBOT_get_var($query);
    }
    
    function addResponseInStack($stackData){
        if (empty($stackData))
            return false;
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        if ($chat_id != '') {
            $stackData['chat_id'] = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        } else {
            return false;
        }
        $user_id = get_current_user_id();
        if (is_numeric($user_id)){
            $stackData['user_id'] = $user_id;
        }
        $query = "SELECT count(id) FROM `" . geekybot::$_db->prefix . "geekybot_stack` WHERE chat_id = '" . esc_sql($stackData['chat_id'])."' AND intent_id = " . esc_sql($stackData['intent_id'])." AND response_id = " . esc_sql($stackData['response_id'])." AND story_id = " . esc_sql($stackData['story_id']);
        $count = geekybot::$_db->get_var($query);
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('stack');
        if (!$row->bind($stackData)) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (!$row->store()) {
            return GEEKYBOT_SAVE_ERROR;
        }
        return GEEKYBOT_SAVED;
    }
}
?>
