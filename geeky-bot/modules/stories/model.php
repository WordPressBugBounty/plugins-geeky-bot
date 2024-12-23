<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTstoriesModel {

    function getAllStories(){
        // ai story data
        $ai_query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_stories` where story_type = 1";
        $ai_query .= " ORDER BY id ASC ";
        $ai_row = geekybotdb::GEEKYBOT_get_row($ai_query);
        // woo story data
        $woo_query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_stories` where story_type = 2";
        $woo_query .= " ORDER BY id ASC ";
        $woo_row = geekybotdb::GEEKYBOT_get_row($woo_query);
        // Post data
        $post_query = "SELECT count(id) FROM `" . geekybot::$_db->prefix . "geekybot_posts` ";
        $post_count = geekybotdb::GEEKYBOT_get_var($post_query);
        geekybot::$_data[0]['ai_story'] = $ai_row;
        geekybot::$_data[0]['woo_story'] = $woo_row;
        geekybot::$_data[0]['post_count'] = $post_count;
        return;
    }

    function saveStories($data){
        $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_intents_ranking`";
        geekybot::$_db->query($query);
        $errorCount = 0;
        foreach ($data['story'] as $key => $story) {
            $story['name'] = 'test name'; // recheck
            $row = GEEKYBOTincluder::GEEKYBOT_getTable('story');
            $cols = array();
            $cols['id'] = "";
            if ($story['storyid']) {
                $cols['id'] = $story['storyid'];
            }
            if (!$story['name']) {
                $error['name'] = "name";
                $errorCount++;
                continue;
            }
            $count = 0;
            if (isset($story['ids'])) {
                $count = count($story['ids']);
            }
            if (!$count) {
                $error['intent'] = "intentarray";
                $errorCount++;
                continue;
            }
            $cols['name'] = $story['name'];
            $storyids = [];
            $intents_ordering = [];
            $uniqueIndex = 0;
            foreach ($story['ids'] as $key => $value) {
                $index = explode("_",$value);
                if (isset($index[0]) && isset($index[1])) {
                    if ($index[0] == 'intentid') {
                        $intents_ordering[$uniqueIndex]['id'] = $index[1];
                        $intents_ordering[$uniqueIndex]['index'] = $key;
                        $uniqueIndex++;
                    }
                    $storyids[$index[0].'_'.$key.'id'] = $index[1];
                }
            }
            $cols['intent_ids'] = wp_json_encode($storyids);
            // save form ids
            if (isset($story['form_ids'])) {
                $cols['is_form'] = 1;
                $cols['form_ids'] = implode(", ", $story['form_ids']);
            } else {
                $cols['is_form'] = 0;
                $cols['form_ids'] = '';
            }
            $cols['story_mode'] = $story['story_mode'];

            if (!$row->bind($cols)) {
                $err = geekybot::$_db->last_error;
                $error[] = $err;
            }

            if (!$row->store()) {
                $err = geekybot::$_db->last_error;
                $error[] = $err;
            }
            // store intents ranking
            foreach ($intents_ordering as $key => $intent) {
                $intents_ranking['story_id'] = $row->id;
                $intents_ranking['intent_id'] = $intent['id'];
                $intents_ranking['ranking'] = $key;
                $intents_ranking['intent_index'] = $intent['index'];
                $ranking_row = GEEKYBOTincluder::GEEKYBOT_getTable('intents_ranking');
                if (!$ranking_row->bind($intents_ranking)) {
                    $err = geekybot::$_db->last_error;
                    $error[] = $err;
                }

                if (!$ranking_row->store()) {
                    $err = geekybot::$_db->last_error;
                    $error[] = $err;
                }
            }
        }
        $result['bol'] = true;
        if ($errorCount) {
            $result['bol'] = false;
            $result['err'] = $error;
        }

        $result = wp_json_encode($result);
        // $this->writeToFile();
        update_option( 'intent_story_notification', 'yes' );
        return $result;
    }

    function deleteStoryByType($type) {
        if (empty($type))
            return false;
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('story');
        $query = "SELECT id, intent_ids, story_type FROM `" . geekybot::$_db->prefix . "geekybot_stories` where story_type = ".esc_sql($type);
        $storyData = geekybotdb::GEEKYBOT_get_row($query);
        if (isset($storyData->id)) {
            if ($row->delete($storyData->id)) {
                $this->deleteStoryData($storyData->id, $storyData);
            }
        }
    }

    function deleteStory($id) {
        if (empty($id))
            return false;
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('story');
        $notdeleted = 0;
        $query = "SELECT intent_ids, story_type FROM `" . geekybot::$_db->prefix . "geekybot_stories` where id = ".esc_sql($id);
        $storyData = geekybotdb::GEEKYBOT_get_row($query);
        if (!$row->delete($id)) {
            $notdeleted += 1;
        }
        if ($notdeleted == 0) {
            $this->deleteStoryData($id, $storyData);
            return GEEKYBOT_DELETED;
        } else {
            return GEEKYBOT_DELETE_ERROR;
        }
    }

    function changeStatus($status, $storyid) {
        // 0 -> disable
        // 1 -> active
        if (!is_numeric($status))
            return false;
        if (!is_numeric($storyid))
            return false;
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('story');
        if (!$row->update(array('id' => $storyid, 'status' => $status))) {
            return GEEKYBOT_SAVE_ERROR;
        } else {
            return GEEKYBOT_STATUS_CHANGED;
        }
    }

    function geekybotEditStoryName() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'edit-story-name') ) {
            die( 'Security check Failed' ); 
        }
        $storyid = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $name = GEEKYBOTrequest::GEEKYBOT_getVar('name');
        if ($name  == '')
            return;
        if (!is_numeric($storyid))
            return;
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('story');
        if (!$row->update(array('id' => $storyid, 'name' => $name))) {
            return;
        } else {
            return 1;
        }
    }

    function deleteStoryData($id, $storyData) {
        if (empty($id)){
            return false;
        }
        // revome the story data from the intents and responses
        $intentsData = json_decode($storyData->intent_ids, true);
        $this->deleteIntentsAndResponsesOfStory($intentsData);
        // revome the story data from the stack
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('stack');
        $query = "SELECT id FROM `" . geekybot::$_db->prefix . "geekybot_stack` where story_id = ".esc_sql($id);
        $stackData = geekybotdb::GEEKYBOT_get_results($query);
        foreach ($stackData as $stack) {
            $row->delete($stack->id);
        }
        // revome the story data from the intents ranking
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('intents_ranking');
        $query = "SELECT id FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` where story_id = ".esc_sql($id);
        $rankingData = geekybotdb::GEEKYBOT_get_results($query);
        foreach ($rankingData as $ranking) {
            $row->delete($ranking->id);
        }
        // revome the product data in case of woocommerce story
        if ($storyData->story_type == 2) {
            $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_products`";
            geekybot::$_db->query($query);
        }
    }

    function deleteIntentsAndResponsesOfStory($intentsData){
        if (!is_null($intentsData)) {
            foreach ($intentsData as $intentKey => $intentValue) {
                if (strpos($intentKey, 'intentid_') !== false) {
                    $row = GEEKYBOTincluder::GEEKYBOT_getTable('intent');
                    $query = "SELECT id FROM `" . geekybot::$_db->prefix . "geekybot_intents` where group_id = ".esc_sql($intentValue);
                    $intentsData = geekybotdb::GEEKYBOT_get_results($query);
                    foreach ($intentsData as $intentData) {
                        $row->delete($intentData->id);
                    }
                } else if (strpos($intentKey, 'responseid_') !== false) {
                    $row = GEEKYBOTincluder::GEEKYBOT_getTable('responses');
                    $row->delete($intentValue);
                }
            }
        }
        // remove all intents having no story
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('intent');
        $query = "SELECT id FROM `" . geekybot::$_db->prefix . "geekybot_intents` where story_id = 0 ";
        $intentsData = geekybotdb::GEEKYBOT_get_results($query);
        foreach ($intentsData as $intentData) {
            $row->delete($intentData->id);
        }
        // remove all responses having no story
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('responses');
        $query = "SELECT id FROM `" . geekybot::$_db->prefix . "geekybot_responses` where story_id = 0 ";
        $responsesData = geekybotdb::GEEKYBOT_get_results($query);
        foreach ($responsesData as $responseData) {
            $row->delete($responseData->id);
        }
    }

    function getMessagekey(){
        $key = 'stories'; if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    function getAdminStoriesSearchData($search_userfields){
        $geekybot_search_array = array();
        $geekybot_search_array['searchtitle'] = GEEKYBOTrequest::GEEKYBOT_getVar('searchtitle');
        $geekybot_search_array['status'] = GEEKYBOTrequest::GEEKYBOT_getVar('status');
        $geekybot_search_array['sorton'] = GEEKYBOTrequest::GEEKYBOT_getVar('sorton' , 'post', 6);
        $geekybot_search_array['sortby'] = GEEKYBOTrequest::GEEKYBOT_getVar('sortby' , 'post', 2);
        $geekybot_search_array['search_from_stories'] = 1;
        return $geekybot_search_array;
    }

    function getFrontSideStoriesSearchData($search_userfields){
        $geekybot_search_array = array();
        $geekybot_search_array['stories'] = GEEKYBOTrequest::GEEKYBOT_getVar('stories', 'post');
        return $geekybot_search_array;
    }

    function getStoryModeById($storyid){
        if ($storyid) {
            if (!is_numeric($storyid))
                return 0;
            $query = "SELECT story_mode FROM `" . geekybot::$_db->prefix . "geekybot_stories` where id = ".esc_sql($storyid);
            $mode = geekybotdb::GEEKYBOT_get_var($query);
            return $mode;
        }
        return 0;
    }

    function getCookiesSavedSearchDataStories($search_userfields){
       $geekybot_search_array = array();
        $wpjp_search_cookie_data = '';
        if(isset($_COOKIE['geekybot_chatbot_search_data'])){
            $wpjp_search_cookie_data = $_COOKIE['geekybot_chatbot_search_data'];
            $wpjp_search_cookie_data = json_decode( geekybotphplib::GEEKYBOT_safe_decoding($wpjp_search_cookie_data) , true );
        }
        if($wpjp_search_cookie_data != '' && isset($wpjp_search_cookie_data['search_from_stories']) && $wpjp_search_cookie_data['search_from_stories'] == 1){
            $geekybot_search_array['searchtitle'] = $wpjp_search_cookie_data['searchtitle'];
            $geekybot_search_array['status'] = $wpjp_search_cookie_data['status'];
            $geekybot_search_array['sorton'] = $wpjp_search_cookie_data['sorton'];
            $geekybot_search_array['sortby'] = $wpjp_search_cookie_data['sortby'];
        }
        return $geekybot_search_array;
    }

    function setSearchVariableForStories($geekybot_search_array,$search_userfields){
        geekybot::$_search['stories']['searchtitle'] = isset($geekybot_search_array['searchtitle']) ? $geekybot_search_array['searchtitle'] : '';
        geekybot::$_search['stories']['sorton'] = isset($geekybot_search_array['sorton']) ? $geekybot_search_array['sorton'] : 6;
        geekybot::$_search['stories']['sortby'] = isset($geekybot_search_array['sortby']) ? $geekybot_search_array['sortby'] : 2;
    }
    // nlu.yml end
    // new

    function savedefaultFallbackFormAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-default-fallback') ) {
            die( 'Security check Failed' ); 
        }
        $story_id = GEEKYBOTrequest::GEEKYBOT_getVar('story_id');
        $default_fallback = GEEKYBOTrequest::GEEKYBOT_getVar('default_fallback');
        if (!is_numeric($story_id))
            return false;

        // default_fallback
        $query = 'UPDATE `' . geekybot::$_db->prefix . 'geekybot_stories` SET `default_fallback` = "'.esc_sql($default_fallback).'" WHERE `id`= "' . esc_sql($story_id) . '"';
        geekybotdb::query($query);
        return 1;
    }

    function deleteDefaultFallback(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'delete-default-fallback') ) {
            die( 'Security check Failed' ); 
        }
        $story_id = GEEKYBOTrequest::GEEKYBOT_getVar('story_id');
        // delete the default fallback
        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_stories` SET `default_fallback` = '' WHERE `id`= " . esc_sql($story_id);
        geekybotdb::query($query);
        return;
    }

    function saveStoryAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-story') ) {
            die( 'Security check Failed' ); 
        }
        
        $cols = array();
        $cols['id'] = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $cols['name'] = GEEKYBOTrequest::GEEKYBOT_getVar('name');
        $cols['story_type'] = GEEKYBOTrequest::GEEKYBOT_getVar('type');
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('story');
        if (!$row->bind($cols)) {
            $err = geekybot::$_db->last_error;
            $error[] = $err;
        }
        if (!$row->store()) {
            $err = geekybot::$_db->last_error;
            $error[] = $err;
        }
        return 1;
    }

    function updateStoryAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-story') ) {
            die( 'Security check Failed' ); 
        }
        $story['ids'] = GEEKYBOTrequest::GEEKYBOT_getVar('ids');
        $story['storyid'] = GEEKYBOTrequest::GEEKYBOT_getVar('storyid');
        $story['positionsarray'] = GEEKYBOTrequest::GEEKYBOT_getVar('positionsarray');
        // recheck
        $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE story_id = ".esc_sql($story['storyid']);
        geekybot::$_db->query($query);
        // check
        $errorCount = 0;
        // foreach ($data['story'] as $key => $story) {
        // because now we are dealing with the single story only_._
            $row = GEEKYBOTincluder::GEEKYBOT_getTable('story');
            $cols = array();
            $cols['id'] = "";
            if ($story['storyid']) {
                $cols['id'] = $story['storyid'];
            }
            $count = 0;
            if (isset($story['ids'])) {
                $count = count($story['ids']);
            }
            if (!$count) {
                $error['intent'] = "intentarray";
                $errorCount++;
                // continue;
            }
            // $cols['name'] = $story['name'];
            $storyids = [];
            $intents_ordering = [];
            $uniqueIndex = 0;
            foreach ($story['ids'] as $key => $value) {
                $index = explode("_",$value);
                if (isset($index[0]) && isset($index[1])) {
                    if ($index[0] == 'intentid') {
                        $intents_ordering[$uniqueIndex]['id'] = $index[1];
                        $intents_ordering[$uniqueIndex]['index'] = $key;
                        $uniqueIndex++;
                    }
                    $storyids[$index[0].'_'.$key.'id'] = $index[1];
                }
            }
            $cols['intent_ids'] = wp_json_encode($storyids);
            // save form ids
            if (isset($story['form_ids'])) {
                $cols['is_form'] = 1;
                $cols['form_ids'] = implode(", ", $story['form_ids']);
            } else {
                $cols['is_form'] = 0;
                $cols['form_ids'] = '';
            }
            // $cols['story_mode'] = $story['story_mode'];
            $cols['story_mode'] = 1;
            $cols['positions_array'] = (GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->stripslashesFull($story['positionsarray']));
            // $cols['positions_array'] = $story['positionsarray'];

            if (!$row->bind($cols)) {
                $err = geekybot::$_db->last_error;
                $error[] = $err;
            }

            if (!$row->store()) {
                $err = geekybot::$_db->last_error;
                $error[] = $err;
            }
            // store intents ranking
            foreach ($intents_ordering as $key => $intent) {
                $intents_ranking['story_id'] = $row->id;
                $intents_ranking['intent_id'] = $intent['id'];
                $intents_ranking['ranking'] = $key;
                $intents_ranking['intent_index'] = $intent['index'];
                // check for duplicate record
                $query = "SELECT count(id) FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` where story_id = ".esc_sql($intents_ranking['story_id'])." and intent_id = ".esc_sql($intents_ranking['intent_id'])." and ranking = ".esc_sql($intents_ranking['ranking'])." and intent_index = ".esc_sql($intents_ranking['intent_index']);
                $rowCount = geekybotdb::GEEKYBOT_get_var($query);
                if($rowCount == 0) {
                    $ranking_row = GEEKYBOTincluder::GEEKYBOT_getTable('intents_ranking');
                    if (!$ranking_row->bind($intents_ranking)) {
                        $err = geekybot::$_db->last_error;
                        $error[] = $err;
                    }

                    if (!$ranking_row->store()) {
                        $err = geekybot::$_db->last_error;
                        $error[] = $err;
                    }
                }
            }
        // }
        $result['bol'] = true;
        if ($errorCount) {
            $result['bol'] = false;
            $result['err'] = $error;
        }

        $result = wp_json_encode($result);
        // $this->writeToFile();
        update_option( 'intent_story_notification', 'yes' );
        return $result;
    }

    function updateStoryForm($data){
        $storyid = $data['storyid'];
        $story_mode = $data['story']['story_mode'];
        if (isset($data['story']['form_ids'])) {
            $form_ids = implode(", ", $data['story']['form_ids']);
            $is_form = 1;
        } else {
            $form_ids = '';
            $is_form = 0;
        }
        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_stories` SET `is_form` = ".esc_sql($is_form).", `form_ids` = '".esc_sql($form_ids)."', `story_mode` = '".esc_sql($story_mode)."'  WHERE `id`= " . esc_sql($storyid);
        geekybotdb::query($query);
        $story_ids = $data['story']['ids'];
        $intentsArray = [];
        $responsesArray = [];
        foreach ($story_ids as $key => $value) {
            if (strpos($value, 'intentid_') !== false) {
                $filteredValue =  explode('_', $value);
                $intentid =  end($filteredValue);
                $intentsArray[] = $intentid;
                if (isset($intentid) && is_numeric($intentid) ) {
                    // add story id in the new added intents
                    $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_intents` SET `story_id` = ".esc_sql($storyid)."  WHERE `group_id`= " . esc_sql($intentid);
                    geekybotdb::query($query);
                    // add story id in the newly added fallback
                    $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_intents_fallback` SET `story_id` = ".esc_sql($storyid)."  WHERE `group_id`= " . esc_sql($intentid);
                    geekybotdb::query($query);
                }
            } else if (strpos($value, 'responseid_') !== false) {
                $filteredValue =  explode('_', $value);
                $responseid =  end($filteredValue);
                $responsesArray[] = $responseid;
                if (isset($responseid) && is_numeric($responseid) ) {
                    // add story id in the new added responses
                    $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_responses` SET `story_id` = ".esc_sql($storyid)."  WHERE `id`= " . esc_sql($responseid);
                    geekybotdb::query($query);
                }
            }
        }
        if (!empty($intentsArray)) {
            // delete the intents that are now removed from the stroy
            $commaSeparatedIntentIds = implode("','", $intentsArray);
            $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_intents` WHERE story_id = ".esc_sql($storyid)." AND group_id  NOT IN ('".$commaSeparatedIntentIds."')";
            geekybotdb::query($query);
            // delete the intents fallback that are now removed from the stroy
            $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_intents_fallback` WHERE story_id = ".esc_sql($storyid)." AND group_id  NOT IN ('".$commaSeparatedIntentIds."')";
            geekybotdb::query($query);
        }
        if (!empty($responsesArray)) {
            // delete the responses that are now removed from the stroy
            $commaSeparatedResponseIds = implode("','", $responsesArray);
            $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_responses` WHERE story_id = ".esc_sql($storyid)." AND id  NOT IN ('".$commaSeparatedResponseIds."')";
            geekybotdb::query($query);
        }
        return GEEKYBOT_SAVED;
    }

    function getStory($id){
        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_stories` where id = ".esc_sql($id);
        $row = geekybotdb::GEEKYBOT_get_row($query);
        if (isset($row) && $row != '') {
            if (isset($row->positions_array) && $row->positions_array != '' && !is_null($row->positions_array)) {
                $data_array = json_decode($row->positions_array, true); // Decode as associative array
                if(!is_null($data_array)) {
                    $row->number_of_objects = count($data_array);
                } else {
                    $row->number_of_objects = 0;
                }
            } else{
                $row->number_of_objects = 0;
            }
            geekybot::$_data[0]['story'] = $row;
        }
        return;

    }

    function getUserInputFormBodyHTMLAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-form-html') ) {
            die( 'Security check Failed' ); 
        }
        $group_id = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $html = '';
        $user_intents = [];
        if (isset($group_id) && is_numeric($group_id)) {
            $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_intents` where group_id = ".$group_id;
            $user_intents = geekybotdb::GEEKYBOT_get_results($query);
        }
        $html .= '
        <div class="geekybot-form-wrapper">
            '.wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('group_id', isset($group_id) ? $group_id : ''),GEEKYBOT_ALLOWED_TAGS);
            if(empty($user_intents)){
                $html .= '
                <div class="geekybot-form-value">
                    '.wp_kses(GEEKYBOTformfield::GEEKYBOT_text('user_messages[]', '', array('class' => 'inputbox geekybot-form-input-field', 'placeholder' => 'User Input', 'autocomplete' => 'off')),GEEKYBOT_ALLOWED_TAGS).'
                </div>';
            }
        $html .= '
        </div>
        <div class="geekybot-form-add-newfield-button">
            <div id="user-popup-inputs">';
                $userInputDivId = 1;
                foreach ($user_intents as $key => $user_intent){
                    if(isset($user_intent)){
                        $user_messages = geekybotphplib::GEEKYBOT_str_replace("\'","'",$user_intent->user_messages);
                        $user_message = $user_messages;
                        $user_message_id = $user_intent->id;
                        $html .= '
                        <div class="geeky-popup-dynamic-field" id="div_'.$userInputDivId.'">
                            <input name = "user_messages[]" type="text" value = "'.$user_message.'" data-id="'.$user_message_id.'" class="inputbox geeky-popup-dynamic-field-input" autocomplete="off" placeholder="'. esc_attr(__('User Input','geeky-bot')).'" />';
                            if ($userInputDivId > 1) {
                                $html .= '<span class="geeky-popup-dynamic-remov-image remove-btn" title="'. esc_attr(__('Delete','geeky-bot')) .'" onClick="deleteUserInputText(div_'.$userInputDivId.')">
                                    <img title="'. esc_html(__('Delete','geeky-bot')).'" alt="'. esc_html(__('Close','geeky-bot')) .'" class="userpopup-close" src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'includes/images/close.png" />
                                </span>';
                            }
                        $html .= '
                        </div>';
                        $userInputDivId++;
                    }
                }
                $html .= '
            </div>
            <div id="create-user-input">
                <span class="geekybot-frm-add-field-button" onclick="addUserInputText('.$userInputDivId.');">
                    <span class="geekybot-frm-add-field-add-iconbtn-wrp" title="'. esc_html(__('Add','geeky-bot')) .'">
                        <img alt="'. esc_html(__('Add Icon','geeky-bot')) .'" title="'. esc_html(__('Add','geeky-bot')) .'" class="userpopup-plus-icon" src="'. esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/add-icon.png" />
                    </span>
                    '. esc_attr(__('Add More','geeky-bot')) .'
                </span>
                <a id="geekybot-avlble-varbtn" href="#" class="geekybot-availble-variablebtn" title="'. esc_attr(__('Available Variables','geeky-bot')) .'">
                    '. esc_attr(__('Available Variables','geeky-bot')) .'
                </a>
            </div>
        </div>';
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }

    function getResponseTextFormBodyHTMLAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-form-html') ) {
            die( 'Security check Failed' ); 
        }
        $response_id = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $html = '';
        if (isset($response_id) && is_numeric($response_id)) {
            $responseData = $this->getBotResponseForPopup($response_id);
        }
        $response_text = '';
        $response_buttons = [];
        if (isset($responseData->bot_response)) {
            $response_text = $responseData->bot_response;
        }
        if (isset($responseData->response_button) && $responseData->response_button != '') {
            $response_buttons = json_decode($responseData->response_button);
        }
        $html .= '
        <div class="geekybot-form-wrapper">
            '.wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset($responseData) ? $responseData->id : ''), GEEKYBOT_ALLOWED_TAGS).'
            <div class="geekybot-popup-textarea-text">
                '.wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('response_type_text', 1), GEEKYBOT_ALLOWED_TAGS).'
                <textarea name="bot_response" class="text-area-popuptxt" id="bot_response" placeholder="'. esc_html(__('Enter bot response here...','geeky-bot')).'">'. $response_text .'</textarea>
            </div>
        </div>
        <div class="geekybot-form-add-newfield-button">
            <div id="response-popup-text">';
                $responseButtonDivId = 1;
                foreach ($response_buttons as $response_button){
                    if ($response_button->type == 1) {
                        $optionOneSelected = 'selected="selected"';
                        $optionTwoSelected = '';
                        $optionOneStyle = 'style="display:block"';
                        $optionTwoStyle = 'style="display:none"';
                    } else if ($response_button->type == 2) {
                        $optionOneSelected = '';
                        $optionTwoSelected = 'selected="selected"';
                        $optionOneStyle = 'style="display:none"';
                        $optionTwoStyle = 'style="display:block"';
                    }
                    $html .= '
                    <div class="geeky-popup-dynamic-field" id="div_'.$responseButtonDivId.'">
                        <input name = "response_btn_text[]" type="text" value = "'.$response_button->text.'" class="inputbox geeky-popup-dynamic-field-input" autocomplete="off" placeholder="'. esc_attr(__('Button text here','geeky-bot')).'" />
                        <select name="response_btn_type[]" id="response_btn_type[]" class="response-btn-type inputbox geeky-popup-dynamic-field-input geeky-popup-dynamic-field-select" data-validation="required">
                            <option value="1" '.$optionOneSelected.' >'. esc_attr(__('User Input','geeky-bot')).'</option>
                            <option value="2" '.$optionTwoSelected.' >'. esc_attr(__('URL','geeky-bot')).'</option>
                        </select>
                        <input name = "response_btn_value[]" type="text" value = "'.$response_button->value.'" class="response-btn-value inputbox geeky-popup-dynamic-field-input" autocomplete="off" placeholder="'. esc_attr(__('Button value here','geeky-bot')).'" '.$optionOneStyle.' />
                        <input name = "response_btn_url[]" type="text" value = "'.$response_button->value.'" class="response-btn-url inputbox geeky-popup-dynamic-field-input" autocomplete="off" placeholder="'. esc_attr(__('Enter URL here','geeky-bot')).'" '.$optionTwoStyle.' />
                        <span class="geeky-popup-dynamic-remov-image remove-btn" title="'. esc_attr(__('Delete','geeky-bot')) .'" onClick="deleteResponseTextBotton(div_'.$responseButtonDivId.')">
                            '. esc_html(__('Delete','geeky-bot')) .'
                        </span>
                    </div>';
                    $responseButtonDivId++;
                }
                $html .= '
            </div>
            <div id="create-textarea-input">
                <span class="geekybot-frm-add-field-button" title="'. esc_attr(__('Add New Button','geeky-bot')) .'" onclick="addResponseButton('.$responseButtonDivId.');">
                    <span class="geekybot-frm-add-field-add-iconbtn-wrp"><img alt="'. esc_html(__('Add Icon','geeky-bot')) .'"title="'. esc_html(__('Add','geeky-bot')) .'" class="userpopup-plus-icon" src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'includes/images/add-icon.png" /></span>
                    '. esc_attr(__('Add New Button','geeky-bot')) .'
                </span>
            </div>
        </div>';
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }

    function getResponseFunctionFormBodyHTMLAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-form-html') ) {
            die( 'Security check Failed' ); 
        }
        $function_id = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $html = '';
        if (isset($function_id) && is_numeric($function_id)) {
            $query = "SELECT id, function_id FROM `" . geekybot::$_db->prefix . "geekybot_responses` where id = ".esc_sql($function_id);
            $function = geekybotdb::GEEKYBOT_get_row($query);
        }
        $html .= '
            <div class="geekybot-form-wrapper">
                '. wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('response_type_function', 4), GEEKYBOT_ALLOWED_TAGS).'
                <div class="geekybot-form-value" id="visibleFunction">
                    '. wp_kses(GEEKYBOTformfield::GEEKYBOT_select('function_id', GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getPredefinedFunctionsForCombobox(), isset($function->function_id) ? $function->function_id : '', __('Select Predefined Function', 'geeky-bot'), array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS).'
                </div>
                '.wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset($function->id) ? $function->id : ''),GEEKYBOT_ALLOWED_TAGS).'
            </div>';
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }

    function getResponseActionFormBodyHTMLAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-form-html') ) {
            die( 'Security check Failed' ); 
        }
        $action_id = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $html = '';
        if (isset($action_id) && is_numeric($action_id)) {
            $query = "SELECT id, action_id FROM `" . geekybot::$_db->prefix . "geekybot_responses` where id = ".esc_sql($action_id);
            $action = geekybotdb::GEEKYBOT_get_row($query);
        }
        $html .= '
            <div class="geekybot-form-wrapper">
                '. wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('response_type_action', 2), GEEKYBOT_ALLOWED_TAGS).'
                <div class="geekybot-form-value" id="visibleAction">
                    '. wp_kses(GEEKYBOTformfield::GEEKYBOT_select('action_id', GEEKYBOTincluder::GEEKYBOT_getModel('action')->getActionsForCombobox(), isset($action->action_id) ? $action->action_id : '', __('Select Action', 'geeky-bot'), array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS).'
                </div>
                '.wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset($action->id) ? $action->id : ''),GEEKYBOT_ALLOWED_TAGS).'
            </div>
            <div class="geekybot-form-add-newfield-button">
                <div id="create-new-form">
                    <span id="create-form" class="geekybot-frm-add-field-button" onclick="addCustomeActions();">
                        <span class="geekybot-frm-add-field-add-iconbtn-wrp"><img alt="'. esc_html(__('Add Icon','geeky-bot')) .'" class="userpopup-plus-icon" title="'. esc_html(__('Add','geeky-bot')) .'" src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'includes/images/add-icon.png" /></span>
                        '. esc_attr(__('Add New Action','geeky-bot')) .'
                    </span>
                </div>
            </div>';
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }

    function getResponseFormFormBodyHTMLAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-form-html') ) {
            die( 'Security check Failed' ); 
        }
        $form_id = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $html = '';
        if (isset($form_id) && is_numeric($form_id)) {
            $query = "SELECT id, form_id FROM `" . geekybot::$_db->prefix . "geekybot_responses` where id = ".esc_sql($form_id);
            $form = geekybotdb::GEEKYBOT_get_row($query);
        } 
        $html .= '
            <div class="geekybot-form-wrapper">
                '.wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('response_type_form', 3), GEEKYBOT_ALLOWED_TAGS).'
                <div class="geekybot-form-value" id = "visibleForm">
                    '.wp_kses(GEEKYBOTformfield::GEEKYBOT_select('form_id', GEEKYBOTincluder::GEEKYBOT_getModel('forms')->getFormsForCombobox(), isset($form->form_id) ? $form->form_id : '','', array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS).'
                </div>
                '.wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset($form->id) ? $form->id : ''),GEEKYBOT_ALLOWED_TAGS).'
            </div>
            <div class="geekybot-form-add-newfield-button">
                <div id="create-new-form">
                    <span id="create-form"class="geekybot-frm-add-field-button" onclick="addNewForms();">
                        <span class="geekybot-frm-add-field-add-iconbtn-wrp"><img alt="'. esc_html(__('Add Icon','geeky-bot')) .'"title="'. esc_html(__('Add','geeky-bot')) .'" class="userpopup-plus-icon" src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'includes/images/add-icon.png" /></span>
                        '. esc_attr(__('Add New Form','geeky-bot')) .'
                    </span>
                </div>
            </div>';
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }

    function getDefaultFallbackFormBodyHTMLAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-form-html') ) {
            die( 'Security check Failed' ); 
        }
        $storyId = GEEKYBOTrequest::GEEKYBOT_getVar('storyId');
        $html = '';
        $fallback = '';
        if (isset($storyId) && is_numeric($storyId)) {
            $query = "SELECT default_fallback FROM `" . geekybot::$_db->prefix . "geekybot_stories` where id = ".esc_sql($storyId);
            $fallback = geekybotdb::GEEKYBOT_get_var($query);
        } 
        $html .= '
            <div class="geekybot-form-wrapper">
                <div class="geekybot-popup-textarea-text">
                    <textarea name="default_fallback_text" class="text-area-popuptxt" id="default_fallback_text" placeholder="'. esc_html(__('Enter default fallback here...','geeky-bot')) .'">'.$fallback.'</textarea>
                </div>
            </div>';
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }

    function getDefaultIntentFallbackFormBodyHTMLAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-form-html') ) {
            die( 'Security check Failed' ); 
        }
        $groupId = GEEKYBOTrequest::GEEKYBOT_getVar('groupId');
        $html = '';
        if (!empty($groupId) && is_numeric($groupId)) {
            $query = "SELECT id, default_fallback FROM `" . geekybot::$_db->prefix . "geekybot_intents_fallback` where group_id = ".esc_sql($groupId);
            $fallback = geekybotdb::GEEKYBOT_get_row($query);
        }
        $default_fallback = isset($fallback->default_fallback) ? $fallback->default_fallback : "";
        $html .= '
            <div class="geekybot-form-wrapper">
                <div class="geekybot-popup-textarea-text">
                    <textarea name="default_intent_fallback_text" class="text-area-popuptxt" id="default_intent_fallback_text" placeholder="'. esc_html(__('Enter default fallback for intent here...','geeky-bot')) .'">'. $default_fallback .'</textarea>
                </div>
            </div>';
        $html .= wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset($fallback->id) ? $fallback->id : ''), GEEKYBOT_ALLOWED_TAGS);
        $html .= wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('group_id', $groupId), GEEKYBOT_ALLOWED_TAGS);
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }

    function resetStory() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'reset-story') ) {
            die( 'Security check Failed' ); 
        }
        $storyid = GEEKYBOTrequest::GEEKYBOT_getVar('storyid');
        // revome the story data from the intents and responses
        $query = "SELECT intent_ids FROM `" . geekybot::$_db->prefix . "geekybot_stories` where id = ".esc_sql($storyid);
        $intent_ids = geekybotdb::GEEKYBOT_get_var($query);
        $intentsData = json_decode($intent_ids, true);
        $this->deleteIntentsAndResponsesOfStory($intentsData);
        // reset story values
        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_stories` SET `intent_ids` = '', `positions_array` = NULL  WHERE `id`= " . esc_sql($storyid);
        geekybotdb::query($query);
        // delete ranking data
        $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE story_id = ".esc_sql($storyid);
        geekybot::$_db->query($query);
        // delete intent base fallback data
        $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_intents_fallback` WHERE story_id = ".esc_sql($storyid);
        geekybot::$_db->query($query);
        $result = GEEKYBOT_RESET;
        $_msgkey = $this->getMessagekey();
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'story');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$_msgkey);
        return 1;
    }

    function getStoriesForCombobox() {
        $query = "SELECT id, name AS text FROM `" . geekybot::$_db->prefix . "geekybot_stories`";
        $list = geekybot::$_db->get_results($query);
        return $list;
    }

    function geekybotBuildStory1() {
        // $data = $this->findMatchingAttributes('XL');
        $args = array(
            's' => 'currentlyUsed', // Search parameter for product title
            'post_type' => 'product', // Specify product post type
            'orderby' => 'name', // Order by product name
            'numberposts' => -1, // Retrieve all matching products
        );
        $products = wc_get_products($args);
        if($products){
            // loop throught the products
            foreach($products as $product){
                $possibleCombinations = [];
                $productName = geekybotphplib::GEEKYBOT_str_replace(' ', '-', $product->get_title());
                $slotData['name'] = 'woo_product_name';
                $slotData['type'] = 'Product';
                $slotData['variable_for'] = 'product';
                $slotData['possible_values'] = 'shirt';
                // save the product variable in table
                GEEKYBOTincluder::GEEKYBOT_getModel('slots')->storeSlots($slotData);
                $productVariableName = 'product_'.$product->get_id();
                $possibleCombinations[$productVariableName] = explode(",", $productName);
                if ( $product->is_type( 'variable' ) ) {
                    // get all attributes of the product
                    $pattributes = $product->get_attributes();
                    $attributes = [];
                    // read attributes value
                    foreach ($pattributes as $attribute) {
                        $attribute_name = $attribute->get_name();
                        $options = $attribute->get_options(); 
                        $optionNames = [];
                        foreach ($options as $optionIndex => $optionId) {
                            if (is_int($optionId)) {
                                // read value in case of custom attribute
                                $optionName = wc_get_product_terms($productid, $attribute->get_name(), array('fields' => 'names'))[$optionIndex];
                            } else {
                                // read value in case of system attribute
                                $optionName = $optionId;
                            }
                            $optionNames[] = $optionName;
                        }

                        foreach ($optionNames as $value) {
                            $attributes[$attribute_name][] = $value;
                        }
                    }
                    // Loop through all the attribute
                    foreach ($attributes as $attributekey => $attributevalue) {
                        $variableName = geekybotphplib::GEEKYBOT_str_replace(' ', '-', $attributekey);
                        $variableName = $variableName.'_'.$product->get_id();
                        $slotData['name'] = $variableName;
                        $slotData['type'] = 'Product';
                        $slotData['variable_for'] = 'attribute';
                        $slotData['possible_values'] = implode(",", $attributevalue);
                        $result = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->storeSlots($slotData);
                        $possibleCombinations[$variableName] = $attributevalue;
                    }
                    // get all the possible combinations of the attributes
                    $combinations = $this->getAllCombinationsWithKeys($possibleCombinations);
                    $intents = [];
                    $group = 0;
                    foreach ($combinations as $combinationKey => $combinationValue) {
                        // $combinationList = implode(",", $combinationValue);
                        $combinationList = $combinationValue;
                        $all_permutations = $this->permutationsWithKeys($combinationList);
                        $group++;
                        $subgroup = 0;
                        // get all the possible permutations of the combination
                        foreach ($all_permutations as $permutations) {
                            $subgroup++;
                            $user_messages = 'I want to buy a';
                            $user_messages_text = 'I want to buy a';
                            foreach ($permutations as $permutationKey => $permutationValue) {
                                // just for test
                                $varParts = explode('_', $permutationKey);
                                $varSize = $varParts[0];
                                // just for test
                                $user_messages .= ' in '.$varParts[0].' ['.$permutationValue.']('.$permutationKey.')';
                                $user_messages_text .= ' in '.$varParts[0].' '.$permutationValue;
                            }
                            $intentData['user_messages'] = $user_messages;
                            $intentData['user_messages_text'] = $user_messages_text;
                            $intents[$group][$subgroup] = $intentData;
                        }
                    }
                    // group the related combinations of a product
                    $concatenatedFields = [];
                    foreach ($intents as $child) {
                        foreach ($child as $index => $subchild) {
                            if (!isset($concatenatedFields[$index])) {
                                $concatenatedFields[$index] = [];
                            }
                            foreach ($subchild as $field => $value) {
                                if (!isset($concatenatedFields[$index][$field])) {
                                    $concatenatedFields[$index][$field] = '';
                                }
                                if ($field == 'user_messages_text') {
                                    // to get all the possible combinations of the attributes
                                    // $concatenatedFields[$index][$field] .= $value . ' ';
                                    $concatenatedFields[$index][$field] = $value . ' ';
                                } else {
                                    $concatenatedFields[$index][$field] = $value . ' ';
                                }
                            }
                        }
                    }
                    // save the group of the data in the table
                    $intentFinalData['user_messages'] = [];
                    $intentFinalData['user_messages_text'] = '';
                    foreach ($concatenatedFields as $concatenatedField) {
                        $intentFinalData['user_messages'][] = $concatenatedField['user_messages'];
                        $intentFinalData['user_messages_text'] .= $concatenatedField['user_messages_text'];
                    }
                    $intentFinalData['_wpnonce'] = wp_create_nonce("save-intent");
                    $intentFinalData['group_id'] = $product->get_id();
                    GEEKYBOTincluder::GEEKYBOT_getModel('intent')->saveUserInput($intentFinalData);
                }
            }
        }
        die();
    }

    function generateCombinationsIterative($data, $k) {
        $results = array();
        for ($i = 0; $i < sizeof($data); $i++) {
            $current = array($data[$i]);
            for ($j = $i + 1; $j < sizeof($data); $j++) {
                $current[] = $data[$j];
                $results[] = $current;
                array_pop($current);
            }
        }
        return $results;
    }

    function permutationsWithKeys($assocArray) {
        $items = array_map(null, array_keys($assocArray), array_values($assocArray));
        
        // Get permutations of items (both keys and values)
        $itemPermutations = $this->permutations($items);

        // Convert each permutation back to an associative array
        $permutationsWithKeys = [];
        foreach ($itemPermutations as $permutedItems) {
            $newAssocArray = [];
            foreach ($permutedItems as list($key, $value)) {
                $newAssocArray[$key] = $value;
            }
            $permutationsWithKeys[] = $newAssocArray;
        }

        return $permutationsWithKeys;
    }

    function permutations($list) {
        if (count($list) === 1) {
            return [$list];
        }

        $permutations_list = [];
        foreach ($list as $key => $value) {
            $remaining_list = $list;
            unset($remaining_list[$key]);
            $permutations_of_remaining = $this->permutations(array_values($remaining_list));
            foreach ($permutations_of_remaining as $permutation) {
                array_unshift($permutation, $value);  // Prepend current value to each permutation
                $permutations_list[] = $permutation;
            }
        }
        return $permutations_list;
    }

    function getAllCombinationsWithKeys(array $arrays) {
        // If there's only one array, return it with keys preserved
        if (count($arrays) === 1) {
            $key = key($arrays);
            $array = reset($arrays);
            return array_map(function($value) use ($key) {
                return [$key => $value];
            }, $array);
        }

        $keys = array_keys($arrays);
        $firstKey = array_shift($keys);
        $firstArray = array_shift($arrays);
        $combinations = [];

        foreach ($firstArray as $element) {
            $remainingCombinations = $this->getAllCombinationsWithKeys($arrays);
            foreach ($remainingCombinations as $remainingCombination) {
                $combinations[] = array_merge([$firstKey => $element], $remainingCombination);
            }
        }

        return $combinations;
    }

    function getAllCombinations(array $arrays) {
        if (count($arrays) === 1) {
            return is_array($arrays[0]) ? $arrays[0] : [$arrays[0]]; // Ensure single element is wrapped in an array
        }
        $firstArray = array_shift($arrays);
        $combinations = [];
        foreach ($firstArray as $element) {
            $remainingCombinations = $this->getAllCombinations($arrays); // Recursively get combinations for remaining arrays
            foreach ($remainingCombinations as $remainingCombination) {
                $combinations[] = array_merge([$element], (array)$remainingCombination); // Merge elements correctly
                // Ensure both arguments for array_merge are arrays
                // if (is_array($remainingCombination)) {
                //   $combinations[] = array_merge([$element], $remainingCombination);
                //   $combinations[] = array_merge($remainingCombination, [$element]);
                // } else {
                //   $combinations[] = array_merge([$element], [$remainingCombination]); // Wrap string in array if necessary
                //   $combinations[] = array_merge([$remainingCombination], [$element]);
                // }
            }
        }
        return $combinations;
    }

    function findMatchingAttributes( $match_value ) {
        // Get all products
        // Arguments for the query
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1, // Get all products
        );

        $products = new WP_Query( $args );
        // Get the products
        $products = $products->posts;

        // Array to hold attribute names with matching value
        $matching_attribute_names = array();

        // Loop through each product
        foreach ( $products as $product_post ) {
            // Get the WC_Product object
            $product = wc_get_product( $product_post->ID );

            // Get product attributes
            $attributes = $product->get_attributes();

            // Loop through each attribute
            foreach ( $attributes as $attribute ) {
                // Check if attribute is taxonomy based (like color, size)
                if ( $attribute->is_taxonomy() ) {
                    // Get the taxonomy term values
                    $terms = wp_get_post_terms( $product_post->ID, $attribute->get_name() );

                    foreach ( $terms as $term ) {
                        if ( $term->name == $match_value ) {
                            $attribute_name = wc_attribute_label( $attribute->get_name() );

                            // Store the attribute name if it matches the value
                            if ( ! in_array( $attribute_name, $matching_attribute_names ) ) {
                                $matching_attribute_names[] = $attribute_name;
                            }
                        }
                    }
                } else {
                    // Get custom attribute value
                    $options = $attribute->get_options();

                    foreach ( $options as $option ) {
                        if ( $option == $match_value ) {
                            $attribute_name = wc_attribute_label( $attribute->get_name() );

                            // Store the attribute name if it matches the value
                            if ( ! in_array( $attribute_name, $matching_attribute_names ) ) {
                                $matching_attribute_names[] = $attribute_name;
                            }
                        }
                    }
                }
            }
        }

        // Return matching attribute names
        return $matching_attribute_names;
    }

    function geekybotBuildAIStoryFromTemplate($template_name = '') {
        if ($template_name == '') {
            $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
            if (! wp_verify_nonce( $nonce, 'save-story') ) {
                die( 'Security check Failed' ); 
            }
            $name = GEEKYBOTrequest::GEEKYBOT_getVar('name');
            $template = GEEKYBOTrequest::GEEKYBOT_getVar('template');
        } else {
            $name = $template_name;
            $template = $template_name;
        }
        $intent_ids = [];
        $intents_ordering = [];
        // add the start point and fallback node
        $startPointMsg = __('Start Point', 'geeky-bot');
        // draw start point
        $positionsarray = '{"id":"node1","top":"500","left":"0","parentId":"","type":"start_point","text":"'.$startPointMsg.'","image":"home","class":"node_start_point","category":"start"}';

        if ($template != 'geekybot_empty') {
            // Load the XML file
            $filePath = GEEKYBOT_PLUGIN_PATH . 'includes/templates/'.$template.'.xml';
            if (file_exists($filePath)) {
                $xml = simplexml_load_file($filePath) or die("Error: There was an error processing the template. Please check the template for errors and try again.");
            } else {
                return __("Error: The template file does not exist.", 'geeky-bot');
            }
            // $xml = simplexml_load_file(GEEKYBOT_PLUGIN_URL . 'includes/templates/'.$template.'.xml') or die("Error: There was an error processing the template. Please check the template for errors and try again.");
            // validate the xml file
            // Validate slots if present
            $tampData = $this->geekybotReadTemplate($xml, $positionsarray, $intent_ids, $intents_ordering);
            if (isset($tampData['error'])) {
                return $tampData['error'];
            }
            $positionsarray = $tampData['positionsarray'];
            $intent_ids = $tampData['intent_ids'];
            $intents_ordering = $tampData['intents_ordering'];
            if (isset($tampData['default_fallback'])) {
                $default_fallback = $tampData['default_fallback'];
            }

        }
        //////////////////////////////
        // save story
        // $story['storyid'] = 22;
        $story['name'] = $name;
        $story['story_mode'] = 1;
        $story['intent_ids'] = $intent_ids;
        $story['positionsarray'] = $positionsarray;
        $story['intents_ordering'] = $intents_ordering;
        if (isset($default_fallback) && $default_fallback != '') {
            $story['default_fallback'] = $default_fallback;
        }
        $story['story_type'] = 1;
        $this->saveAutoBuildStory($story);
        return 1;
    }

    function geekybotBuildWooCommerceStory($story_name = '') {
        if ($story_name == '') {
            $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
            if (! wp_verify_nonce( $nonce, 'save-story') ) {
                die( 'Security check Failed' ); 
            }
            $name = GEEKYBOTrequest::GEEKYBOT_getVar('name');
        } else {
            $name = $story_name;
        }
        // check if woocommerce is not active
        if (!class_exists('WooCommerce')) {
            return 3;
        }
        // save product data for fallback
        $this->saveProductDataForFallback();

        $intent_ids = [];
        $intents_ordering = [];
        // add the start point and fallback node
        $startPointMsg = __('Start Point', 'geeky-bot');
        // draw start point
        $positionsarray = '{"id":"node1","top":"500","left":"0","parentId":"","type":"start_point","text":"'.$startPointMsg.'","image":"home","class":"node_start_point","category":"start"}';

        // Define the XML structure as a string
        $xmlString = "<?xml version='1.0'?>
        <story>
            <slots>
                <slot>
                    <name>woo_product_name</name>
                    <type>Product</type>
                    <possible_values>shirt</possible_values>
                </slot>
                <slot>
                    <name>woo_product_min_price</name>
                    <type>Money</type>
                    <possible_values>15</possible_values>
                </slot>
                <slot>
                    <name>woo_product_max_price</name>
                    <type>Money</type>
                    <possible_values>25</possible_values>
                </slot>
            </slots>
            <intents>
                <intent_group>
                    <intent>
                        <user_inputs>
                            <user_input>Hi</user_input>
                            <user_input>Hi there</user_input>
                            <user_input>Hello</user_input>
                            <user_input>Greeting</user_input>
                            <user_input>Greetings</user_input>
                            <user_input>Hey</user_input>
                            <user_input>What's up</user_input>
                            <user_input>Good morning</user_input>
                            <user_input>Good afternoon</user_input>
                            <user_input>Good evening</user_input>
                        </user_inputs>
                    </intent>
                    <responses>
                        <response>
                            <text>Hi there! Welcome to [Your Store Name]. How can I assist you today?</text>
                        </response>
                    </responses>
                </intent_group>
                <intent_group>
                    <intent>
                        <user_inputs>
                            <user_input>Show all available products.</user_input>
                            <user_input>View the full product list.</user_input>
                            <user_input>Display all items.</user_input>
                            <user_input>Browse all products.</user_input>
                        </user_inputs>
                    </intent>
                    <responses>
                        <response>
                            <function>showAllProducts</function>
                        </response>
                    </responses>
                </intent_group>
                <intent_group>
                    <intent>
                        <user_inputs>
                            <user_input>I wish to purchase a [item](woo_product_name)</user_input>
                            <user_input>I am looking for a [item](woo_product_name)</user_input>
                            <user_input>I need a [item](woo_product_name)</user_input>
                            <user_input>I want to buy a [item](woo_product_name)</user_input>
                            <user_input>I would like to buy a [item](woo_product_name)</user_input>
                            <user_input>Can I purchase a [item](woo_product_name)?</user_input>
                            <user_input>Can I get a [item](woo_product_name)</user_input>
                            <user_input>May I purchase a [item](woo_product_name)</user_input>
                        </user_inputs>
                    </intent>
                    <responses>
                        <response>
                            <function>searchProduct</function>
                        </response>
                    </responses>
                </intent_group>
                <intent_group>
                    <intent>
                        <user_inputs>
                            <user_input>Please display items priced below [25](woo_product_max_price).</user_input>
                            <user_input>Can you provide options that are less than [25](woo_product_max_price)?</user_input>
                            <user_input>Show me products that cost under [25](woo_product_max_price).</user_input>
                            <user_input>I would like to see items available for under [25](woo_product_max_price).</user_input>
                            <user_input>Can you filter results to show items under [25](woo_product_max_price)?</user_input>
                            <user_input>Please list options that are within a [25](woo_product_max_price) budget.</user_input>
                            <user_input>Show me selections priced at [25](woo_product_max_price) or less.</user_input>
                            <user_input>Can you find items that are below the [25](woo_product_max_price) mark?</user_input>
                            <user_input>Please show me options that fall under [25](woo_product_max_price).</user_input>
                            <user_input>I am looking for items that are under [25](woo_product_max_price).</user_input>
                        </user_inputs>
                    </intent>
                    <responses>
                        <response>
                            <function>getProductsUnderPrice</function>
                        </response>
                    </responses>
                </intent_group>
                <intent_group>
                    <intent>
                        <user_inputs>
                            <user_input>Please display items priced over [15](woo_product_min_price).</user_input>
                            <user_input>Can you provide options that are more than [15](woo_product_min_price)?</user_input>
                            <user_input>Show me products that cost above [15](woo_product_min_price).</user_input>
                            <user_input>I would like to see items available for over [15](woo_product_min_price).</user_input>
                            <user_input>Can you filter results to show items above [15](woo_product_min_price)?</user_input>
                            <user_input>Please list options that exceed a [15](woo_product_min_price) budget.</user_input>
                            <user_input>Show me selections priced at [15](woo_product_min_price) or more.</user_input>
                            <user_input>Can you find items that are above the [15](woo_product_min_price) threshold?</user_input>
                            <user_input>Please show me options that fall over [15](woo_product_min_price).</user_input>
                            <user_input>I am looking for items that are above [15](woo_product_min_price).</user_input>
                        </user_inputs>
                    </intent>
                    <responses>
                        <response>
                            <function>getProductsAbovePrice</function>
                        </response>
                    </responses>
                </intent_group>
                <intent_group>
                    <intent>
                        <user_inputs>
                            <user_input>Show my cart</user_input>
                            <user_input>View Shopping cart</user_input>
                            <user_input>Display Basket</user_input>
                            <user_input>Display cart</user_input>
                        </user_inputs>
                    </intent>
                    <responses>
                        <response>
                            <function>viewCart</function>
                        </response>
                    </responses>
                </intent_group>
                <intent_group>
                    <intent>
                        <user_inputs>
                            <user_input>Proceed to Checkout</user_input>
                            <user_input>Checkout Now</user_input>
                            <user_input>Finish Purchase</user_input>
                            <user_input>Go to Checkout</user_input>
                            <user_input>Place Order</user_input>
                            <user_input>Complete Your Order</user_input>
                            <user_input>Confirm Order</user_input>
                        </user_inputs>
                    </intent>
                    <responses>
                        <response>
                            <function>checkOut</function>
                        </response>
                    </responses>
                </intent_group>
                <intent_group>
                    <intent>
                        <user_inputs>
                            <user_input>Bye</user_input>
                            <user_input>Goodbye</user_input>
                            <user_input>See you</user_input>
                            <user_input>Bye for now</user_input>
                            <user_input>Talk to you later</user_input>
                            <user_input>Catch you later</user_input>
                            <user_input>Thanks, I'm done</user_input>
                            <user_input>That's all</user_input>
                            <user_input>I'm all set</user_input>
                            <user_input>No more questions</user_input>
                            <user_input>Exit</user_input>
                        </user_inputs>
                    </intent>
                    <responses>
                        <response>
                            <text>Thank you for chatting with us! If you need any more assistance, feel free to reach out. Have a great day!</text>
                        </response>
                    </responses>
                </intent_group>
            </intents>
        </story>";

        // Load the XML string into a SimpleXMLElement object
        $xml = simplexml_load_string($xmlString);

        $tampData = $this->geekybotReadTemplate($xml, $positionsarray, $intent_ids, $intents_ordering);
        if (isset($tampData['error'])) {
            return $tampData['error'];
        }
        $positionsarray = $tampData['positionsarray'];
        $intent_ids = $tampData['intent_ids'];
        $intents_ordering = $tampData['intents_ordering'];
        if (isset($tampData['default_fallback'])) {
            $default_fallback = $tampData['default_fallback'];
        }
        //////////////////////////////
        // save story
        $story['name'] = $name;
        $story['story_mode'] = 1;
        $story['intent_ids'] = $intent_ids;
        $story['positionsarray'] = $positionsarray;
        $story['intents_ordering'] = $intents_ordering;
        if (isset($default_fallback) && $default_fallback != '') {
            $story['default_fallback'] = $default_fallback;
        }
        $story['story_type'] = 2;
        $this->saveAutoBuildStory($story);
        return 1;
    }

    function geekybotReadTemplate($xml, $positionsarray, $intent_ids, $intents_ordering) {
        $slot_no = 1;
        $textMsg = __('Text', 'geeky-bot');
        $functionMsg = __('Function', 'geeky-bot');
        if (isset($xml->slots->slot)) {
            foreach ($xml->slots->slot as $slot) {
                // Validate the presence 'name' tags
                if (!isset($slot->name)) {
                    $data['error'] = __("Error: The", "geeky-bot")." <span class='geekybot_read_template_error'>name</span> ".__("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>slot</span>" . __("at position", 'geeky-bot')." ".$slot_no." ".__("is missing.", 'geeky-bot');
                    return $data;
                }
                // Validate that 'name' is not empty
                if (empty((string)$slot->name)) {
                    $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>name</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>slot</span>" . __("at position", 'geeky-bot')." ".$slot_no." ".__("is empty.", 'geeky-bot');
                    return $data;
                }
                // Validate the presence 'type' tags
                if (!isset($slot->type)) {
                    $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>type</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>slot</span>" . __("at position", 'geeky-bot')." ".$slot_no." ".__("is missing.", 'geeky-bot');
                    return $data;
                }
                // Validate that 'type' is not empty
                if (empty((string)$slot->type)) {
                    $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>type</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>slot</span>" . __("at position", 'geeky-bot')." ".$slot_no." ".__("is empty.", 'geeky-bot');
                    return $data;
                }
                // Validate 'possible_values' tags if present
                if (isset($slot->possible_values)) {
                    // Validate that 'possible_values' is not empty
                    if (empty((string)$slot->possible_values)) {
                        $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>possible_values</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>slot</span>" . __("at position", 'geeky-bot')." ".$slot_no." ".__("is empty.", 'geeky-bot');
                        return $data;
                    }
                }
                $slot_no++;
            }
        }
        $intent_group_no = 1;
        foreach ($xml->intents->intent_group as $intent_group) {
            // Validate the presence of 'intent' tags
            if (!isset($intent_group->intent)) {
                $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>intent</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                return $data;
            }
            // Validate the presence 'user_inputs' tags
            if (!isset($intent_group->intent->user_inputs)) {
                $data['error'] =  __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>user_inputs</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                return $data;
            }
            // Validate that 'user_inputs' is not empty
            if (empty((string)$intent_group->intent->user_inputs)) {
                $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>user_inputs</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is empty.", 'geeky-bot');
                return $data;
            }
            // Validate the presence 'user_input' tags
            if (!isset($intent_group->intent->user_inputs->user_input)) {
                $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>user_input</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                return $data;
            }
            // Validate that 'user_input' is not empty
            if (empty((string)$intent_group->intent->user_inputs->user_input)) {
                $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>user_input</span>" . __("tag in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is empty.", 'geeky-bot');
                return $data;
            }
            // Validate the presence of and 'responses' tags
            if (!isset($intent_group->responses)) {
                $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>response</span>" . __("tag associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                return $data;
            }
            // Validate the presence of and 'response' tags
            if (!isset($intent_group->responses->response)) {
                $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>response</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>responses</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                return $data;
            }
            foreach ($intent_group->responses->response as $response) {
                // Validate that <response> is not empty
                // Validate that <type> in <response> is either "text" or "function"
                if (!isset($response->text) && !isset($response->function)) {
                    $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>text</span>" . __("or", "geeky-bot") . "<span class='geekybot_read_template_error'>function</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>response</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                    return $data;
                }
                if (isset($response->text)) {
                    if (empty((string)$response->text)) {
                        $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>text</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>response</span>" . __("block associated with the user input", 'geeky-bot'). "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is empty.", 'geeky-bot');
                        return $data;
                    }
                }
                if (isset($response->function)) {
                    if (empty((string)$response->function)) {
                        $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>function</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>response</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is empty.", 'geeky-bot');
                        return $data;
                    }
                    $predefinedFunctions = $this->getPredefinedFunctionsName();
                    if ( !in_array($response->function, $predefinedFunctions)) {
                        $data['error'] = __("Error: The function name", 'geeky-bot'). "<span class='geekybot_read_template_error'>".(string)$response->function. "</span>" .__("in the", "geeky-bot") . "<span class='geekybot_read_template_error'>function</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>response</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is invalid.", 'geeky-bot');
                        return $data;
                    }
                }
                // Validate and process buttons if present
                if (isset($response->buttons->button)) {
                    foreach ($response->buttons->button as $button) {
                        if (!isset($button->text)) {
                            $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>text</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>button</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                            return $data;
                        }
                        if (empty((string)$button->text)) {
                            $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>text</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>button</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is empty.", 'geeky-bot');
                            return $data;
                        }
                        if (!isset($button->type)) {
                            $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>type</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>button</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                            return $data;
                        }
                        if (empty((string)$button->type)) {
                            $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>type</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>button</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" .__("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is empty.", 'geeky-bot');
                            return $data;
                        }
                        $buttonType = (string)$button->type;
                        if (!in_array($button->type, ['intent', 'url'])) {
                            $data['error'] = __("Error: The type name", 'geeky-bot') .  "<span class='geekybot_read_template_error'>" . $buttonType. "</span>" .__("in the", "geeky-bot") . "<span class='geekybot_read_template_error'>type</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>button</span>" . __("block associated with the user input", 'geeky-bot'). "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" . __("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is invalid. This should be", "geeky-bot") . "<span class='geekybot_read_template_error'>intent</span>" . __("or", "geeky-bot") . "<span class='geekybot_read_template_error'>url</span>" . __(".", 'geeky-bot');
                            return $data;
                        }
                        if (!isset($button->value)) {
                            $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>value</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>button</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" . __("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is missing.", 'geeky-bot');
                            return $data;
                        }
                        if (empty((string)$button->value)) {
                            $data['error'] = __("Error: The", "geeky-bot") . "<span class='geekybot_read_template_error'>value</span>" . __("tag of the", "geeky-bot") . "<span class='geekybot_read_template_error'>button</span>" . __("block associated with the user input", 'geeky-bot') . "<span class='geekybot_read_template_error'>" . (string)$intent_group->intent->user_inputs->user_input. "</span>" . __("in", "geeky-bot") . "<span class='geekybot_read_template_error'>intent_group</span>" . __("at position", 'geeky-bot')." ".$intent_group_no." ".__("is empty.", 'geeky-bot');
                            return $data;
                        }
                    }
                }
            }
            $intent_group_no++;
        }
        // Loop through a list of elements
        $intents_ordering_index_value = 1;
        $intents_ordering_index = 0;
        $intent_ids_index = 0;
        $node_id = 1;
        $parent_node_id = -1;
        $direction = 'up';
        $top_position = 500;
        $left_position = 0;
        $default_fallback = '';
        // get the fallback
        if (isset($xml->fallback)) {    
            // Validate that 'fallback' is not empty
            if (!empty((string)$xml->fallback)) {
                $default_fallback = (string)$xml->fallback;
            }
        }
        // draw default fallback
        $node_id += 2;
        $positionsarray .= ',{"id":"node'.$node_id.'","top":"658","left":"220","parentId":"node1","parentType" : "start_point","type":"fallback","text":"Default Fallback","image":"fallback","class":"node_action_fallback","category":"fallback","value":"'.$default_fallback.'"}';
        // save the slots
        if (isset($xml->slots->slot)) {
            foreach ($xml->slots->slot as $slot) {
                // save variables
                $slotData['id'] = '';
                $slotData['name'] = (string)$slot->name;
                $slotData['type'] = (string)$slot->type;
                $slotData['variable_for'] = '';
                if (isset($slot->possible_values) && $slot->possible_values != '') {
                    $slotData['possible_values'] = (string)$slot->possible_values;
                } else {
                    $slotData['possible_values'] = '';
                }
                // save the product variable in table
                GEEKYBOTincluder::GEEKYBOT_getModel('slots')->storeSlots($slotData);
            }
        }
        if (isset($xml->intents->intent_group)) {
            foreach ($xml->intents->intent_group as $intent_group) {
                $index = 0;
                // save intents
                $intentData = [];
                foreach ($intent_group->intent->user_inputs->user_input as $user_input) {
                    $intentData['user_messages'][$index]['message'] = (string)$user_input;
                    $index++;
                }
                $intentData['_wpnonce'] = wp_create_nonce("save-intent");
                $group_id = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->saveAutoBuildUserInput($intentData);
                $intent_ids_index++;
                $intent_ids['intentid_'.$intent_ids_index.'id'] = $group_id;
                // draw the new intent group
                $top_position_direction = $this->geekybotGetTopPosition($top_position, $direction);
                $top_position = $top_position_direction['position'];
                $direction = $top_position_direction['direction'];
                $left_position = $this->geekybotGetLeftPosition($left_position);
                $node_id += 2;
                $parent_node_id += 2;
                $positionsarray .= ',{"id":"node'.$node_id.'","top":"'.$top_position.'","left":"'.$left_position.'","parentId":"node'.$parent_node_id.'","type":"user_input","text":"User input","image":"user-icon","class":"node_action_user_input","category":"intent","value":"intentid_'.$group_id.'"}';
                // save the intent ordering
                $intents_ordering[$intents_ordering_index]['id'] = $group_id;
                $intents_ordering[$intents_ordering_index]['index'] = $intents_ordering_index_value;

                // ----------------
                // save response
                foreach ($intent_group->responses->response as $response) {
                    $top_position_direction = $this->geekybotGetTopPosition($top_position, $direction);
                    $top_position = $top_position_direction['position'];
                    $direction = $top_position_direction['direction'];
                    $left_position = $this->geekybotGetLeftPosition($left_position);
                    if (isset($response->text)) {
                        $responseData['response_type'] = 1;
                        $responseData['bot_response'] = (string)$response->text;
                        $responseData['function_id'] = '';
                    } elseif (isset($response->function)) {
                        $responseData['response_type'] = 4;
                        $responseData['bot_response'] = '';
                        $function_id = $this->getFunctionIdByName($response->function);
                        $responseData['function_id'] = $function_id;
                    }
                    $buttons = [];
                    if (isset($response->buttons->button)) {
                        foreach ($response->buttons->button as $button) {
                            $buttonType = '';
                            if ($button->type == 'intent') {
                                $buttonType = 1;
                            } elseif ($button->type == 'url') {
                                $buttonType = 2;
                            }
                            $buttons[] = array("text" => (string)$button->text, "type" => $buttonType, "value" => (string)$button->value);
                        }
                    }
                    if (!empty($buttons)) {
                        $response_button = json_encode($buttons);
                    } else {
                        $response_button = '';
                    }
                    $responseData['response_button'] = $response_button;
                    $response_id = GEEKYBOTincluder::GEEKYBOT_getModel('responses')->saveAutoBuildResponses($responseData);
                    // draw the new response
                    $parent_node_id = $node_id;
                    $node_id = $node_id + 2;
                    if (isset($response->text)) {
                        $positionsarray .= ',{"id":"node'.$node_id.'","top":"'.$top_position.'","left":"'.$left_position.'","parentId":"node'.$parent_node_id.'","type":"response_text","text":"'.$textMsg.'","image":"bot-text","class":"node_action_text","category":"response","value":"responseid_'.$response_id.'"}';
                    } elseif (isset($response->function)) {
                        $positionsarray .= ',{"id":"node'.$node_id.'","top":"'.$top_position.'","left":"'.$left_position.'","parentId":"node'.$parent_node_id.'","type":"response_function","text":"'.$functionMsg.'","image":"bot-function","class":"node_action_function","category":"response","value":"responseid_'.$response_id.'"}';
                    }
                    $intent_ids_index++;
                    $intent_ids['responseid_'.$intent_ids_index.'id'] = $response_id;
                    $intents_ordering_index_value ++;
                }
                // save story
                $intents_ordering_index++;
                $intents_ordering_index_value ++;
            }
        }
        $data['intent_ids'] = $intent_ids;
        $data['positionsarray'] = $positionsarray;
        $data['intents_ordering'] = $intents_ordering;
        if (isset($default_fallback) && $default_fallback != '') {
            $data['default_fallback'] = $default_fallback;
        }
        return $data;
    }

    function saveAutoBuildStory($story){
        $story = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->stripslashesFull($story);// remove slashes with quotes

        $row = GEEKYBOTincluder::GEEKYBOT_getTable('story');
        $cols = array();
        $cols['id'] = "";
        if (isset($story['storyid'])) {
            $cols['id'] = $story['storyid'];
        }
        $cols['name'] = $story['name'];
        $cols['intent_ids'] = wp_json_encode($story['intent_ids']);
        $cols['is_form'] = 0;
        $cols['form_ids'] = '';
        $cols['story_mode'] = $story['story_mode'];
        $cols['positions_array'] = '['.$story['positionsarray'].']';
        $cols['story_type'] = $story['story_type'];
        if (isset($story['default_fallback']) && $story['default_fallback'] != '') {
            $cols['default_fallback'] = $story['default_fallback'];
        }
        $cols['status'] = 1;

        if (!$row->bind($cols)) {
            $err = geekybot::$_db->last_error;
            $error[] = $err;
        }

        if (!$row->store()) {
            $err = geekybot::$_db->last_error;
            $error[] = $err;
        }
        // update intents record
        $story_id = $row->id;
        $intent_ids = $story['intent_ids'];
        if (isset($story_id) && is_numeric($story_id) ) {
            foreach ($intent_ids as $key => $intentid) {
                if (isset($intentid) && is_numeric($intentid) ) {
                    if (strpos($key, 'intentid_') !== false) {
                        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_intents` SET `story_id` = ".esc_sql($story_id)."  WHERE `group_id`= " . esc_sql($intentid);
                        geekybotdb::query($query);
                    } else if (strpos($key, 'responseid_') !== false) {
                        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_responses` SET `story_id` = ".esc_sql($story_id)."  WHERE `id`= " . esc_sql($intentid);
                        geekybotdb::query($query);
                    }
                }
            }
            // store intents ranking
            $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE story_id = ".esc_sql($story_id);
            geekybot::$_db->query($query);
            foreach ($story['intents_ordering'] as $key => $intent) {
                $intents_ranking['story_id'] = $story_id;
                $intents_ranking['intent_id'] = $intent['id'];
                $intents_ranking['ranking'] = $key;
                $intents_ranking['intent_index'] = $intent['index'];
                // check for duplicate record
                $query = "SELECT count(id) FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` where story_id = ".esc_sql($intents_ranking['story_id'])." and intent_id = ".esc_sql($intents_ranking['intent_id'])." and ranking = ".esc_sql($intents_ranking['ranking'])." and intent_index = ".esc_sql($intents_ranking['intent_index']);
                $rowCount = geekybotdb::GEEKYBOT_get_var($query);
                if($rowCount == 0) {
                    $ranking_row = GEEKYBOTincluder::GEEKYBOT_getTable('intents_ranking');
                    if (!$ranking_row->bind($intents_ranking)) {
                        $err = geekybot::$_db->last_error;
                        $error[] = $err;
                    }

                    if (!$ranking_row->store()) {
                        $err = geekybot::$_db->last_error;
                        $error[] = $err;
                    }
                }
            }
        }
        update_option( 'intent_story_notification', 'yes' );
        return 1;
    }

    function saveProductDataForFallback(){
        $products = wc_get_products(
            array(
                'limit' => -1 // Get all products (set limit to -1)
            )
        );
        // Loop through each product and get its name
        foreach ($products as $product) {
            $productid = $product->get_id();
            $data = $this->getProductDataForFallback($productid);
            $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            $data = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->stripslashesFull($data);// remove slashes with quotes.
            $row = GEEKYBOTincluder::GEEKYBOT_getTable('products');
            $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            $data = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->stripslashesFull($data);// remove slashes with quotes.
            if (!$row->bind($data)) {
                return GEEKYBOT_SAVE_ERROR;
            }
            if (!$row->store()) {
                return GEEKYBOT_SAVE_ERROR;
            }
        }
        return;
    }

    function getProductDataForFallback($productid){
        $product_text = '';
        $product = wc_get_product( $productid );
        $product_text .= ' '.$product->get_name();
        $product_text .= ' '.$product->get_sku();
        $pattributes = $product->get_attributes();
        $long_description = $product->get_description();
        $short_description = $product->get_short_description();
        
        $attributes = [];
        foreach ($pattributes as $attribute) {
            $attribute_name = $attribute->get_name();
            $filteredKey =  explode('_', $attribute_name);
            $product_text .=  ' '.end($filteredKey);
            $options = $attribute->get_options(); // Array of values for the attribute
            // Initialize an empty array to store the option names
            $optionNames = [];
            // Loop through each option ID
            foreach ($options as $optionIndex => $optionId) {
                // Check if the options array contains IDs or names
                if (is_int($optionId)) {
                    // Existing attribute, fetch name using ID
                    // Use the option ID to retrieve the option name
                    $optionName = wc_get_product_terms($productid, $attribute->get_name(), array('fields' => 'names'))[$optionIndex]; // Use 'slugs' argument;
                } else {
                    $optionName = $optionId;
                }
                // Add the option name to the array
                $product_text .= ' '.$optionName;
            }
        }
        // Get categories using get_category_ids and terms
        $category_ids = $product->get_category_ids();
        $categories = array();
        foreach ( $category_ids as $category_id ) {
            $category = get_term_by( 'id', $category_id, 'product_cat' );  // Get category object
            if ( $category ) {
                $categories[] = $category->name;  // Add category name to array
            }
        }
        $categories_text = implode(' ', $categories);
        $product_text .= ' '.$categories_text;
        // Get tags using get_tag_ids and terms
        $tag_ids = $product->get_tag_ids();
        $tags = array();
        foreach ( $tag_ids as $tag_id ) {
            $tag = get_term_by( 'id', $tag_id, 'product_tag' );  // Get tag object
            if ( $tag ) {
                $tags[] = $tag->name;  // Add tag name to array
            }
        }
        $tags_text = implode(' ', $tags);
        $product_text .= ' '.$tags_text;

        $stopwords = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getStopWords();

        $filtered_product_words = array_diff(explode(' ', $product_text), $stopwords);
        $filtered_product_text = implode(' ', $filtered_product_words);

        $filtered_sdescription = array_diff(explode(' ', $short_description), $stopwords);
        $filtered_short_description = implode(' ', $filtered_sdescription);

        $filtered_ldescription = array_diff(explode(' ', $long_description), $stopwords);
        $filtered_long_description = implode(' ', $filtered_ldescription);
        $data['product_text'] = $filtered_product_text;
        $data['product_description'] = $filtered_short_description .' '.$filtered_long_description;
        $data['product_id'] = $productid;
        $data['status'] =  $product->get_status();

        return $data;
    }

    function getWcProductListingHtml($msg, $products, $type, $all_products_count, $current_page, $model_name, $function_name, $data) {
        $text = "";
        if($products){
            $products_per_page = geekybot::$_configuration['pagination_product_page_size'];
            $offset = ($current_page - 1) * $products_per_page;
            $products_count = count($products);
            $to_product = $offset + $products_count;
            $from_product = $offset + 1;

            $html = "<div class='geekybot_wc_product_heading'>".__('You might like these products.', 'geeky-bot')."</div>";
            $text = "";
            if ($type == 'fallbackone') {
                $text .= "<div class='geekybot_wc_product_heading'>".__('You might like these products.', 'geeky-bot')."</div>";
            } elseif ($type == 'fallbacktwo') {
                $text .= "<div class='geekybot_wc_product_heading'>".__("No exact match was found. Explore these similar products.", 'geeky-bot')."</div>";
            } else {
                $text .= "<div class='geekybot_wc_product_heading'>".__('Here are some suggestions.', 'geeky-bot')."</div>";
            }
            $text .= "<div class='geekybot_wc_product_heading_counts'>".__('Showing', 'geeky-bot').' '.$from_product.' to '.$to_product.' '.__('of', 'geeky-bot').' '.$all_products_count."</div>";
            foreach($products as $product){
                $text .= "
                <div class='geekybot_wc_product_wrp'>
                    <div class='geekybot_wc_product_left_wrp'>
                        ".$product->get_image('thumbnail')."
                    </div>
                    <div class='geekybot_wc_product_right_wrp'>
                        <div class='geekybot_wc_product_name'>
                            <a title='".$product->get_title()."' href='".$product->get_permalink()."' target='_blank'>".$product->get_title()."</a>
                        </div>
                        <div class='geekybot_wc_product_price'>
                            ".$product->get_price_html()."
                        </div>
                        <div class='geekybot_wc_product_action_btn_wrp'>";
                            $product_type = $product->get_type();
                            if ($product_type == 'simple') {
                                $product_price = get_post_meta($product->get_id() , '_price', true);
                                // check if the product price is not empty
                                if (isset($product_price) && $product_price != null) {
                                    // check if the product stock is not empty
                                    if ( $product && $product->is_in_stock() ) {
                                        $text .= '<button class="geekybot_wc_product_action_btn btn-primary" onclick="geekybotAddToCart('.$product->get_id().')" value="/add_to_cart{&quot;product_id&quot;:'.$product->get_id().'}">'. __('Add to cart', 'geeky-bot') .'</button>';
                                    } else  {
                                        $text .= 'Out Of Stock';
                                    }
                                }
                            } else if ($product_type == 'variable') {
                                $text .= '<button class="geekybot_wc_product_action_btn btn-primary" onclick="getProductAttributes('.$product->get_id().', 1, \'\')" value="/add_to_cart{&quot;product_id&quot;:'.$product->get_id().'}">Select Options</button>';
                            } else if ($product_type == 'external') {
                                $button_text = $product->get_button_text();
                                $button_url = $product->get_product_url();
                                $text .= '<a href="'.esc_url($button_url).'" class="geekybot_wc_product_action_btn btn-primary button product_type_external">'.esc_html($button_text).'</a>';
                            } else if ($product_type == 'grouped') {
                                $text .= '<button class="geekybot_wc_product_action_btn btn-primary" onclick="geekybotAddToCart('.$product->get_id().')" value="/add_to_cart{&quot;product_id&quot;:'.$product->get_id().'}">'. __('Add to cart', 'geeky-bot') .'</button>';
                            } else {
                                $text .= '<button class="geekybot_wc_product_action_btn btn-primary" onclick="geekybotAddToCart('.$product->get_id().')" value="/add_to_cart{&quot;product_id&quot;:'.$product->get_id().'}">'. __('Add to cart', 'geeky-bot') .'</button>';
                            }
                            $text .= "
                        </div>
                    </div>
                </div>";
                $buttons = array();
            }
            // Display the "Load More" button if there are more products to show
            $text .= "<div class='geekybot_wc_product_load_more_wrp'>";
                if ($all_products_count > ($current_page * $products_per_page)) {
                    $data = htmlspecialchars(wp_json_encode($data), ENT_QUOTES, 'UTF-8');
                    $next_page = $current_page + 1;
                    $text .= "<span class='geekybot_wc_product_load_more' onclick=\"geekybotLoadMoreProducts('".$msg."','".$next_page."','".$model_name."','".$function_name."','".$data."');\">".__('Show More', 'geeky-bot')."</span>";
                }
            $text .= "</div>";
            $text = geekybotphplib::GEEKYBOT_htmlentities($text);
        }
        return $text;
    }

    function getBotResponseForPopup($response_id) {
        $query = "SELECT id, bot_response, response_button FROM `" . geekybot::$_db->prefix . "geekybot_responses` where id = ".esc_sql($response_id);
        $response = geekybotdb::GEEKYBOT_get_row($query);

        $bot_response = '';
        if(isset($response->bot_response)){
            $bot_response = $response->bot_response;
            $variables = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getVariables($response->bot_response);
            foreach ($variables as $key => $value) {
                // if variable value found from session data
                $responseVar = '['.$value.']('.$key.')';
                $newFormate = '['.$key.']';
                $bot_response = geekybotphplib::GEEKYBOT_str_replace($responseVar, $newFormate, $bot_response);
            }
            $response->bot_response = $bot_response;
        }
        return $response;
    }

    function getTextForTooltip(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-tooltip-text') ) {
            die( 'Security check Failed' ); 
        }
        $text = '';
        $value = GEEKYBOTrequest::GEEKYBOT_getVar('value');
        if (isset($value) && $value != '') {
            $filteredValue =  explode('_', $value);
            $id =  end($filteredValue);
            if (isset($id) && is_numeric($id) ) {
                if (strpos($value, 'fallback_') !== false) {
                    $query = "SELECT default_fallback FROM `" . geekybot::$_db->prefix . "geekybot_intents_fallback` where group_id = ".esc_sql($id)." ORDER BY id ASC ";
                    $text = geekybotdb::GEEKYBOT_get_var($query);
                } else if (strpos($value, 'intentid_') !== false) {
                    $query = "SELECT user_messages_text FROM `" . geekybot::$_db->prefix . "geekybot_intents` where group_id = ".esc_sql($id)." ORDER BY id ASC ";
                    $text = geekybotdb::GEEKYBOT_get_var($query);
                } else if (strpos($value, 'responseid_') !== false) {
                    $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_responses` where id = ".esc_sql($id);
                    $responseData = geekybotdb::GEEKYBOT_get_row($query);
                    if ($responseData->bot_response != '') {
                        $text = $responseData->bot_response;
                    } else if ($responseData->function_id != 0) {
                        $text = $this->getFunctionNameById($responseData->function_id);
                    }
                }
            }
        }
        
        return $text;
    }

    function getFunctionNameById($function_id){
        if ($function_id == 1) {
            $function_name = 'showAllProducts';
        } else if ($function_id == 2) {
            $function_name = 'searchProduct';
        } else if ($function_id == 3) {
            $function_name = 'viewCart';
        } else if ($function_id == 4) {
            $function_name = 'checkOut';
        } else if ($function_id == 5) {
            $function_name = 'resetPassword';
        } else if ($function_id == 6) {
            $function_name = 'SendChatToAdmin';
        } else if ($function_id == 13) {
            $function_name = 'getProductsUnderPrice';
        } else if ($function_id == 14) {
            $function_name = 'getProductsBetweenPrice';
        } else if ($function_id == 15) {
            $function_name = 'getProductsAbovePrice';
        } else {
            $function_name = 'showAllProducts';
        }
        return $function_name;
    }

    function getFunctionIdByName($function_name){
        if ($function_name == 'showAllProducts') {
            $function_id = 1;
        } else if ($function_name == 'searchProduct') {
            $function_id = 2;
        } else if ($function_name == 'viewCart') {
            $function_id = 3;
        } else if ($function_name == 'checkOut') {
            $function_id = 4;
        } else if ($function_name == 'resetPassword') {
            $function_id = 5;
        } else if ($function_name == 'SendChatToAdmin') {
            $function_id = 6;
        } else if ($function_name == 'getProductsUnderPrice') {
            $function_id = 13;
        } else if ($function_name == 'getProductsBetweenPrice') {
            $function_id = 14;
        } else if ($function_name == 'getProductsAbovePrice') {
            $function_id = 15;
        } else {
            $function_id = 1;
        }
        return $function_id;
    }

    function geekybotGetTopPosition($previous_position, $previous_direction){
        $next_position = $previous_position;
        $next_direction = $previous_direction;
        if ($previous_direction == 'up') {
            $next_position = $previous_position - 148;
            if ($next_position < 50) {
                $next_direction = 'down';
                $next_position = $previous_position + 148;
            }
        } elseif ($previous_direction == 'down') {
            $next_position = $previous_position + 148;
            if ($next_position > 760) {
                $next_direction = 'up';
                $next_position = $previous_position - 148;
            }
        }
        $data['position'] = $next_position;
        $data['direction'] = $next_direction;
        return $data;
    }

    function geekybotGetLeftPosition($previous_position){
        $next_position = $previous_position + 241;
        return $next_position;
    }

    function addIntentToStory(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'add-intent') ) {
            die( 'Security check Failed' );
        }
        $storyid = GEEKYBOTrequest::GEEKYBOT_getVar('storyid');
        $missing_intent = GEEKYBOTrequest::GEEKYBOT_getVar('missing_intent');
        $url = admin_url("admin.php?page=geekybot_stories&geekybotlt=formstory&missing_intent=".$missing_intent."&storyid=".$storyid);
        return $url;
        wp_redirect($url);
        die();
    }

    function getTemplatesForCombobox(){
        $directory = GEEKYBOT_PLUGIN_PATH . 'includes/templates';
        // Get all .xml files in the directory
        $xmlFiles = glob($directory . '/*.xml');
        $templatesList = array();
        $templatesList[] = (object) array('id' => 'geekybot_empty', 'text' => __('Empty Story', 'geeky-bot'));
        foreach ($xmlFiles as $xmlFile) {
            $filename = pathinfo($xmlFile, PATHINFO_FILENAME); // This will return only the filename without the extension
            $templatesList[] = 
                (object) array('id' => $filename, 'text' => geekybot::GEEKYBOT_getVarValue($filename));
        }
        return $templatesList;
    }

    function getPredefinedFunctionsForCombobox(){
        $functionsList = array();
        // check if woocommerce is active
        if (class_exists('WooCommerce')) {
            $functionsList[] = (object) array('id' => '1', 'text' => __('Show All Products', 'geeky-bot'));
            $functionsList[] = (object) array('id' => '2', 'text' => __('Search Product', 'geeky-bot'));
            $functionsList[] = (object) array('id' => '13', 'text' => __('Get Products Under Price', 'geeky-bot'));
            // $functionsList[] = (object) array('id' => '14', 'text' => __('Get Products Between Price', 'geeky-bot'));
            $functionsList[] = (object) array('id' => '15', 'text' => __('Get Products Above Price', 'geeky-bot'));
            $functionsList[] = (object) array('id' => '3', 'text' => __('View Cart/Remove Items', 'geeky-bot'));
            $functionsList[] = (object) array('id' => '4', 'text' => __('Checkout', 'geeky-bot'));
        }
        $functionsList[] = (object) array('id' => '5', 'text' => __('Reset Password', 'geeky-bot'));
        $functionsList[] = (object) array('id' => '6', 'text' => __('Send Chat To Admin', 'geeky-bot'));
        return $functionsList;
    }

    function getPredefinedFunctionsName(){
        $predefinedFunctions = ['showAllProducts', 'searchProduct', 'getProductsUnderPrice', 'getProductsBetweenPrice', 'getProductsAbovePrice', 'viewCart', 'checkOut', 'resetPassword', 'SendChatToAdmin'];
        return $predefinedFunctions;
    }
}
?>
