<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTslotsModel {

    function storeSlots($data) {
        if (empty($data))
            return 1;
        $data['shortSlots'] = geekybotphplib::GEEKYBOT_str_replace(' ', '-', $data['name']);
        $inquery = '';
        if ($data['id'] != '') {
            $inquery .= " AND id != " . esc_sql($data['id']);
        }
        // check for duplicate variable name
        $query = "SELECT count(id) FROM `" . geekybot::$_db->prefix . "geekybot_slots` WHERE name = '" . esc_sql($data['name'])."'";
        $query .= $inquery;
        $count = geekybotdb::GEEKYBOT_get_var($query);
        if ($count > 0) {
            return GEEKYBOT_ALREADY_EXIST;
        }
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('slots');
        $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
        $data = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->stripslashesFull($data);// remove slashes with quotes.
        if (!$row->bind($data)) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (!$row->store()) {
            return GEEKYBOT_SAVE_ERROR;
        }
        return GEEKYBOT_SAVED;
    }

    function getSlotsbyId($id) {
        if (!is_numeric($id))
            return false;
        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_slots` WHERE id = " . esc_sql($id);
        geekybot::$_data[0] = geekybotdb::GEEKYBOT_get_row($query);

        return;
    }

    function stripslashesFull($input){// testing this function/.
        if (is_array($input)) {
            $input = array_map(array($this,'stripslashesFull'), $input);
        } elseif (is_object($input)) {
            $vars = get_object_vars($input);
            foreach ($vars as $k=>$v) {
                $input->{$k} = stripslashesFull($v);
            }
        } else {
            $input = geekybotphplib::GEEKYBOT_stripslashes($input);
        }
        return $input;
    }

    function getAllSlots() {
        $slotsname = geekybot::$_search['slots']['slotsname'];
        $inquery = '';
        $clause = ' WHERE ';
        if ($slotsname) {
            $inquery .= $clause . "  slots.name LIKE '%" . esc_sql($slotsname) . "%' ";
            $clause = " AND ";
        }
        geekybot::$_data['filter']['slotsname'] = $slotsname;
        // Pagination
        $query = "SELECT COUNT(slots.id)
            FROM `" . geekybot::$_db->prefix . "geekybot_slots` AS slots";
        $query .= $inquery;
        $total = geekybotdb::GEEKYBOT_get_var($query);
        geekybot::$_data['total'] = $total;
        geekybot::$_data[1] = GEEKYBOTpagination::GEEKYBOT_getPagination($total);
        // Data
        $query = "SELECT slots.* FROM `" . geekybot::$_db->prefix . "geekybot_slots` AS slots";
        $query .= $inquery;
        $query .= " ORDER BY slots.name ASC LIMIT " . GEEKYBOTpagination::$_offset . ", " . GEEKYBOTpagination::$_limit;
        geekybot::$_data[0] = geekybotdb::GEEKYBOT_get_results($query);
        return;
    }

    function deleteSlots($ids) {
        if (empty($ids))
            return false;
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('slots');
        $notdeleted = 0;
        foreach ($ids as $id) {
            if (!$row->delete($id)) {
                $notdeleted += 1;
            }
        }
        if ($notdeleted == 0) {
            GEEKYBOTMessages::$counter = false;
            return GEEKYBOT_DELETED;
        } else {
            GEEKYBOTMessages::$counter = $notdeleted;
            return GEEKYBOT_DELETE_ERROR;
        }
    }

    function getMultiSelectEdit() {
        $query = "SELECT name as name, id as id,type as type FROM `" . geekybot::$_db->prefix . "geekybot_slots` ";
        $rows = geekybotdb::GEEKYBOT_get_results($query);
        $list = [];
        foreach ($rows AS $slots) {
            $list[] = array('id' => $slots->id, 'value' => $slots->name, 'type' => $slots->type);
        }
        return wp_json_encode($list);
    }
    
    function setSearchVariableForSlots($geekybot_search_array,$search_userfields){
        geekybot::$_search['slots']['slotsname'] = isset($geekybot_search_array['slotsname']) ? $geekybot_search_array['slotsname'] : '';
        geekybot::$_search['slots']['sorton'] = isset($geekybot_search_array['sorton']) ? $geekybot_search_array['sorton'] : 6;
        geekybot::$_search['slots']['sortby'] = isset($geekybot_search_array['sortby']) ? $geekybot_search_array['sortby'] : 2;
    }

    function getAdminSlotsSearchData($search_userfields){
        $geekybot_search_array = array();
        $geekybot_search_array['slotsname'] = GEEKYBOTrequest::GEEKYBOT_getVar('slotsname');
        $geekybot_search_array['status'] = GEEKYBOTrequest::GEEKYBOT_getVar('status');
        $geekybot_search_array['sorton'] = GEEKYBOTrequest::GEEKYBOT_getVar('sorton' , 'post', 6);
        $geekybot_search_array['sortby'] = GEEKYBOTrequest::GEEKYBOT_getVar('sortby' , 'post', 2);
        $geekybot_search_array['search_from_intent'] = 1;
        return $geekybot_search_array;
    }

    function getCookiesSavedSearchDataSlots($search_userfields){
        $geekybot_search_array = array();
        $wpjp_search_cookie_data = '';
        if(isset($_COOKIE['geekybot_chatbot_search_data'])){
            $wpjp_search_cookie_data = $_COOKIE['geekybot_chatbot_search_data'];
            $wpjp_search_cookie_data = json_decode( geekybotphplib::GEEKYBOT_safe_decoding($wpjp_search_cookie_data) , true );
        }
        if($wpjp_search_cookie_data != '' && isset($wpjp_search_cookie_data['search_from_slots']) && $wpjp_search_cookie_data['search_from_slots'] == 1){
            $geekybot_search_array['slotsname'] = $wpjp_search_cookie_data['slotsname'];
            $geekybot_search_array['status'] = $wpjp_search_cookie_data['status'];
            $geekybot_search_array['description'] = $wpjp_search_cookie_data['description'];
        }
        return $geekybot_search_array;
    }

    function saveVariableFromIntent($data, $data_variables, $score){
        // recheck in the case of form
        // GEEKYBOTincluder::GEEKYBOT_getModel('chatserver')->changeStatusOfSavedVariables();
        $sessionData = geekybot::$_geekybotsessiondata->geekybot_getSavedDataFromSession();
        if (!empty($sessionData)) {
            $array = $sessionData;
        }
        $liveCall = 1;
        // read variables from the intent
        $intentVariables = $this->read_variables($data, $data_variables, $liveCall);
        // variable from response
        foreach ($intentVariables as $key => $value) {
            if ($score < 0.05) {
                $array[$key] = $data;
            } else {
                $array[$key] = $value;
            }
            $newVariables = 1;
        }
        if (isset($newVariables) && $newVariables == 1) {
            $array['flag'] = 0;
            geekybot::$_geekybotsessiondata->geekybot_addSessionVariablesDataToTable($array);
            return;
        }
        if ($score < 0.05) {
            // for handling console error
            if (isset($array['nextIndex'])) {
                $key = $array['nextIndex'];
                $array[$key] = $data;
            }
            $array['flag'] = 0;
            $array['nextIndex'] = '';
            geekybot::$_geekybotsessiondata->geekybot_addSessionVariablesDataToTable($array);
                return;
        }
        return;
    }

    function saveVariableFromButtonIntent(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'button-intent') ) {
            die( 'Security check Failed' );
        }
        $buttonIntent = GEEKYBOTrequest::GEEKYBOT_getVar('message');

        $variables = $this->getVariables($buttonIntent);
        $data_variables = $buttonIntent;
        foreach ($variables as $slot_name => $slot_value) {
            $buttonIntent = geekybotphplib::GEEKYBOT_str_replace('['.$slot_value.']('.$slot_name.')', $slot_value, $buttonIntent);
        }
        $data = $buttonIntent;
        if (is_array($variables) && !empty($variables)) {
            $sessionData = geekybot::$_geekybotsessiondata->geekybot_getSavedDataFromSession();
            if (!empty($sessionData)) {
                $array = $sessionData;
            }
            foreach ($variables as $key => $value) {
                $array[$key] = $value;
            }
            $array['flag'] = 0;
            geekybot::$_geekybotsessiondata->geekybot_addSessionVariablesDataToTable($array);
        }
        return $buttonIntent;
    }

    function saveVariableFromFallBack($message, $data, $data_variables){
        // get saved variables from the session
        $sessionData = geekybot::$_geekybotsessiondata->geekybot_getSavedDataFromSession();
        // read variables from intent
        $liveCall = 1;
        $intentVariables = $this->read_variables($data, $data_variables, $liveCall);
        $newVariables = [];
        // add the variables from the intent to the session data
        foreach ($intentVariables as $key => $value) {
            $sessionData[$key] = $message;
            $newVariables[$key] = $message;
        }
        if (!empty($intentVariables)) {
            // if find variables from the intent then update session data
            geekybot::$_geekybotsessiondata->geekybot_addSessionVariablesDataToTable($sessionData);
        }
        // return intent variables
        return $intentVariables;
    }

    function readVariablesInResponse($bot_response, $intent_index, $intent_rank, $story_id, $chathistory, $savedData){
        $variables = $this->getVariables($bot_response);
        $user_variables_data = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getVariablesValuesFromUserMessage($bot_response, $variables);
        $usermessagestext = '';
        foreach ($user_variables_data as $slot_name => $slot_value) {
            $message = geekybotphplib::GEEKYBOT_str_replace('['.$slot_value.']('.$slot_name.')', $slot_value, $bot_response);
            $usermessagestext .= $message.' ';
        }
        $data = $usermessagestext;
        $data_variables = $bot_response;
        $liveCall = 0;
        $variables = $this->read_variables($data, $data_variables, $liveCall);
        if (!empty($variables)) {
            // if response contains variables
            foreach ($variables as $key => $value) {
                if (isset($key)) {
                    // start from here
                    $geekybot_read_variable = geekybot::$_geekybotsessiondata->geekybot_getSavedDataFromSession();
                    if ( !empty($geekybot_read_variable) ) {
                        // if options already set
                        $array = $geekybot_read_variable;
                        if (isset($array['ranking'])) {
                            $old_rank = $array['ranking'];
                            $expected_rank = $old_rank;
                            $expected_rank = $expected_rank+1;
                            // $expected_rank++;
                            if ($expected_rank != $intent_rank) {
                                // $intent_rank = $expected_rank;
                                $return  = $old_rank;
                            }
                        }
                    } else {
                        // if options not already set
                        if (isset($array) && !empty($array)) {
                            // if options not already set
                            // but it is not the first ittrartion of loop
                        } else {
                            // if options not already set
                            // and it is the first loop ittrartion
                            $array = array();
                        }
                    }
                    // recheck
                    // if ($chathistory == 0 && 1 != 1) {
                    if ($chathistory == 0 ) {
                        $newValueFound = 1;
                        // start from here
                        if (isset($array['nextIndex']) && $array['nextIndex'] != '') {
                            // To prevent duplicate records
                            // if ($array->nextIndex != $key) {
                                // temp remove
                                // $array->nextIndex = $array->nextIndex.','.$key;
                                $array['nextIndex'] = $key;
                            // }
                        } else {
                            $array['nextIndex'] = $key;
                        }
                        // recheck
                        if (isset($return)) {
                            $array['ranking'] = $return;
                        } else{
                            $array['ranking'] = $intent_rank;
                        }
                        // recheck this
                    }
                }
            }
            // 
            if (isset($newValueFound) && $newValueFound == 1) {
                $array['flag'] = 1;
                // $array->nextIndex = $key;
                $array['index'] = $intent_index;
                $array['story'] = $story_id;
                // check if story change
                $old_story = $savedData;
                $current_story = $story_id;
                if ($old_story != $current_story) {
                    // check story mode
                    $story_mode = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getStoryModeById($old_story);
                    if ($story_mode == 1) {
                        // discard the new story
                        // bouble recheck
                        if (isset($return)) {
                            $return  = $intent_rank;
                        }
                    }if ($story_mode == 2) {
                        // discard the new story
                        // bouble recheck
                        if (isset($return)) {
                            $return  = $intent_rank;
                        }
                    } else {
                        geekybot::$_geekybotsessiondata->geekybot_addSessionVariablesDataToTable($array);
                    }
                }
            }
        } else {
            // if variables not set in the response
            $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();

            $query = "SELECT `sessionmsgvalue` FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata` WHERE `usersessionid` = '" . esc_sql($chatid) . "' AND `sessionmsgkey` = 'ranking'";
            $ranking = geekybotdb::GEEKYBOT_get_var($query);

            if (isset($ranking) && $ranking != '') {
                // get the last saved rank
                $old_rank = $ranking;
                $expected_rank = $old_rank;
                $expected_rank = $expected_rank+1;
                if ($expected_rank != $intent_rank) {
                    // Return the next intent from the saved intents
                    $return  = $old_rank;
                }
            }
        }
        if (isset($return)) {
            return $return;
        } else {
            return $intent_rank;
        }
    }

    function readResponseVariableValueFromSessionData($bot_response){
        $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $query = "SELECT sessionmsgkey,sessionmsgvalue
            FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata`  WHERE usersessionid = '" . esc_sql($chatid) . "' AND sessionmsgkey NOT IN ('nextIndex','ranking','flag','index','story','chathistory') ";
        $savedVariables = geekybotdb::GEEKYBOT_get_results($query);
        $saveParams = array();
        foreach ($savedVariables as $key => $value) {
            $saveParams[$value->sessionmsgkey] = $value->sessionmsgvalue;
        }
        $variables = $this->getVariables($bot_response);
        foreach ($variables as $key => $value) {
            $valueFound = false;
            foreach ($saveParams as $savedKey => $savedValue) {
                if ($key == $savedKey) {
                    // if variable value found from session data
                    $responseVar = '['.$value.']('.$key.')';
                    $bot_response = geekybotphplib::GEEKYBOT_str_replace($responseVar, $savedValue, $bot_response);
                    $valueFound = true;
                }
            }
            if (!$valueFound) {
                // if variable value not found from session data then show his possible value
                $responseVar = '['.$value.']('.$key.')';
                $bot_response = geekybotphplib::GEEKYBOT_str_replace($responseVar, $value, $bot_response);
            }
        }
        return $bot_response;
    }
    function read_variables($data, $data_variables, $liveCall){
        // recheck
        $user_message = $data;
        
        $variables = $this->getVariables($data_variables);
        $user_variables_data = $this->getVariablesValuesFromUserMessage($user_message, $variables);
        $user_variables_data_withoutsp = $this->getVariablesValuesFromUserMessage_stopwords($user_message, $data_variables);
        $user_message_sw = $this->removeStopWords($data);
        $data_variables_sw = $this->removeStopWords($data_variables);
        $intent = $data_variables_sw;

        $user_variables_data_sw = $this->getVariablesValuesFromUserMessage_stopwords($user_message_sw, $intent);
        $user_variables_data_new = array();
        $user_variables_data_new = $user_variables_data;
        $user_variables_data_new2 = $user_variables_data_new;
        foreach($user_variables_data_sw as $key => $value){
            $found = 0;
            foreach($user_variables_data_new as $key1 => $value1){
                if($key == $key1){
                    // this if is added by hamza
                    // the variable value is more reliable in case without stop words so if the both values are different then we prefer this value
                    if($value != $value1){
                        $user_variables_data_new[$key] = $value;
                    }
                    $found = 1;
                }
            }
            if($found == 0){ // add variable to new
                $user_variables_data_new[$key] = $value;
            }
        }
		
		// read variable without stopwords
		foreach($user_variables_data_withoutsp as $key => $value){
			$found = 0;
            foreach($user_variables_data_new2 as $key1 => $value1){
                if($key == $key1){
                    $found = 1;
                }
            }
            if($found == 0){ // add variable to new
                $user_variables_data_new2[$key] = $value;
            }
			
		}
		// $user_variables_data_new = $user_variables_data_new2;
        // read variable from live call and validate variables value
        $readVariablesFromLive = $this->readVariablesFromLive($data, $data_variables, $variables, $liveCall);
        $finalVariablesValues = $this->validateVariables($user_variables_data_new, $readVariablesFromLive);
        if (empty($finalVariablesValues)) {
            // added by boss
            $intent = $data_variables;
            $variables = $this->getVariables($data_variables);
            if (count($variables) == 1) {
                $user_message_explode = explode(" ",$user_message);
                $intent_explode = explode(" ",$intent);
                
                $user_message_matched = array();
                $user_message_notmatched = array();
                $intent_matched = array();
                $intent_notmatched = array();

                //get user meesage matched and unmatched
                foreach ($user_message_explode as $key => $value) {
                    $value_matched = 0;
                    foreach ($intent_explode as $intent_key => $intent_value) {
                        if(strcmp(strtolower($value), strtolower($intent_value)) == 0){
                            $user_message_matched[] = $value;
                            $value_matched = 1;
                        }
                    }
                    if($value_matched == 0){
                        $user_message_notmatched[] = $value;
                    }
                }

                //get intent matched and unmatched
                foreach ($intent_explode as $key => $value) {
                    $value_matched = 0;
                    foreach ($user_message_explode as $user_message_key => $user_message_value) {
                        if(strcmp(strtolower($value), strtolower($user_message_value)) == 0){
                            $intent_matched[] = $value;
                            $value_matched = 1;
                        }
                    }
                    if($value_matched == 0){
                        $intent_notmatched[] = $value;
                    }
                }

                $user_message_lev_matched = array();
                $user_message_lev_notmatched = array();

                foreach ($user_message_notmatched as $key => $value) {
                    $value_matched = 0;
                    foreach ($intent_notmatched as $intent_key => $intent_value) {
                        if(!is_numeric($value)){ // lev not good result for int
                            $lev = levenshtein($value, $intent_value);        
                            if($lev < 3){ //consider as matched
                                $user_message_lev_matched[] = $value;
                                $value_matched = 1;    
                            }
                        }
                    }
                    if($value_matched == 0){
                        $user_message_lev_notmatched[] = $value;
                    }
                }
                if(!empty($user_message_lev_notmatched)){
                    $finalVariablesValues[array_keys($variables)[0]] = implode(' ', $user_message_lev_notmatched);
                }
                // $lev = levenshtein("consist", "contain");
                // $lev = similar_text("consist", "contain");
                // end
            }
        }
        return $finalVariablesValues;
        return $user_variables_data_new;
    }

    function readVariablesFromLive($user_message, $intent, $variables, $liveCall){
        return;
		$log_text = "readVariablesFromLive";
		$log_text .= "\n user_message: ".$user_message;
		$log_text .= "\n intent: ".$intent;
        
		if ($liveCall == 1) {
            if (!empty($variables)) {
                foreach ($variables as $key => $value) {
                    $query = "SELECT type FROM `" . geekybot::$_db->prefix . "geekybot_slots` WHERE name = '".esc_sql($key)."'";
                    $type = geekybotdb::GEEKYBOT_get_var($query);
                    if (isset($type) && $type != '') {
                        $variable_with_types[$key] = $type;
                		$log_text .= "\n key: ".$key;
                		$log_text .= "\n type: ".$type;
                    } else {
                        $variable_with_types[$key] = "none";
                    }
                }
                $variable_with_types_json = wp_json_encode($variable_with_types);
                // live call
                $url = "http://194.163.183.46:8008/";
                $body = array(
                    'user_message' => $user_message,
                    'intent' => $intent,
                    'variable_with_types' => $variable_with_types_json
                );

                $response = wp_remote_post($url, array(
                    'body' => $body,
                ));

                // Check for error
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    return  "Something went wrong: $error_message";
                } else {
                    $server_output = wp_remote_retrieve_body($response);
                    return $server_output; // Or handle the server output as needed
                }
            }
        }
        return;
    }

    function validateVariables($local_variables, $live_variables){
        if ($live_variables == '') {
            return $local_variables;
        } else {
            $live_variables = json_decode($live_variables, true );
        }
        $all_keys = array_unique(array_merge(array_keys($local_variables), array_keys($live_variables)));
        // Build the final array with prioritized values
        $final_variables = array_fill_keys($all_keys, null); // Initialize with null for missing values
        foreach ($all_keys as $key) {
            $final_variables[$key] = $local_variables[$key] ?? $live_variables[$key] ?? null; // Prioritize local_variables
        }
        return $final_variables;
    }

    function getVariablesValuesFromUserMessage_stopwords01($user_message, $intent){
        $variables = array();
		$log_text = "getVariablesValuesFromUserMessage_stopwords";
		$log_text .= "\n user_message: ".$user_message;
		$log_text .= "\n intent: ".$intent;
        $start = 0;
        do{
            $user_variable_value = "";
            $start_braket = strpos($intent,"[", $start);
            if($start_braket){
                $end_braket = strpos($intent,"]", $start);
                $start = $end_braket+1; //for next variable
                if($end_braket){
                    $variable_value = substr($intent,$start_braket+1,($end_braket-$start_braket-1));
                    $start_variable_braket = strpos($intent,"(", $start);
                    if(($start_variable_braket - $end_braket) < 3){ //same value variable i.e [Lahore](city_name)
                        $end_variable_braket = strpos($intent,")", $start);
                        if($end_variable_braket){
                            $variable_name = substr($intent,$start_variable_braket+1,($end_variable_braket-$start_variable_braket-1));
                            
							$log_text .= "\n variable_name: ".$variable_name;
                            // get word before the variable
                            $intent_text_before_variable = substr($intent,0,$start_braket);
                            $pieces = explode(' ', trim($intent_text_before_variable));
                            $intent_word_before_variable = array_pop($pieces);
							$log_text .= "\n intent_word_before_variable: ".$intent_word_before_variable;
                            //echo "<br>last word ".$intent_word_before_variable;
                            
                            // get the next word of the variable
                            $intent_text_after_variable = substr($intent,$end_variable_braket+1);
							$log_text .= "\n intent_text_after_variable: ".$intent_text_after_variable;
                            if($intent_text_after_variable){
                                $pieces = explode(' ', trim($intent_text_after_variable));
                                $intent_word_after_variable = $pieces[0];
								$log_text .= "\n2 intent_word_after_variable: ".$intent_word_after_variable;
                                //echo "<br>after word ".$intent_word_after_variable;
                                $str = 'before-str-after';
                                if (preg_match('/'.$intent_word_before_variable.'(.*?)'.$intent_word_after_variable.'/', $user_message, $match) == 1) {
                                    $user_variable_value = $match[1];
                                    // echo "<br> user_variable_value 1 ".$user_variable_value;
                                }
                            }else{ // variable at end of string
                                $total_intent_word_before_variable = substr_count($user_message,$intent_word_before_variable);
                                if($total_intent_word_before_variable > 1){ // mulitple word in the string
                                    $is_intent_word_before_variable = strrpos($user_message,$intent_word_before_variable); // read last occurrence
                                    if($is_intent_word_before_variable){
                                        $user_variable_value = substr($user_message,$is_intent_word_before_variable);
                                        $user_variable_value = str_replace($intent_word_before_variable,"",$user_variable_value); // remove before word
										$log_text .= "\n 1 user_variable_value: ".$user_variable_value;
                                        // echo "<br> user_variable_value 2 ".$user_variable_value;
                                    }
                                    
                                    
                                }else{ // only once
                                    $is_intent_word_before_variable = strpos($user_message,$intent_word_before_variable); 
                                    if($is_intent_word_before_variable){
                                        $user_variable_value = substr($user_message,$is_intent_word_before_variable);
                                        $user_variable_value = str_replace($intent_word_before_variable,"",$user_variable_value); // remove before word
										$log_text .= "\n 2 user_variable_value: ".$user_variable_value;
                                        // echo "<br> user_variable_value 3 ".$user_variable_value;
                                    }
                                    
                                }
                            }
                            if($user_variable_value){
                                $variables[$variable_name] = $user_variable_value;
                            }
                            
                        }else{
                            $start_braket = 0; //exit the loop
                        }
                    }
                }else{
                    $start_braket = 0; //exit the loop
                }
            }
        }while($start_braket >= 1);
        return $variables;
    }

    function getVariablesValuesFromUserMessage_stopwords($user_message, $intent) {
        $variables = array();
        $log_text = "getVariablesValuesFromUserMessage_stopwords";
        $log_text .= "\n user_message: ".$user_message;
        $log_text .= "\n intent: ".$intent;
        $start = 0;
        $variable_one_position = 0;
        do {
            $user_variable_value = "";
            $start_bracket = strpos($intent, "[", $start);
            if ($start_bracket !== false) {
                $end_bracket = strpos($intent, "]", $start_bracket);
                if ($end_bracket !== false) {
                    $start = $end_bracket + 1; // for next variable
                    $variable_value = substr($intent, $start_bracket + 1, ($end_bracket - $start_bracket - 1));
                    $start_variable_bracket = strpos($intent, "(", $end_bracket);
                    if (($start_variable_bracket - $end_bracket) < 3) { // same value variable i.e [Lahore](city_name)
                        $end_variable_bracket = strpos($intent, ")", $start_variable_bracket);
                        if ($end_variable_bracket !== false) {
                            $variable_name = substr($intent, $start_variable_bracket + 1, ($end_variable_bracket - $start_variable_bracket - 1));
                            
                            $log_text .= "\n variable_name: ".$variable_name;
                            // get word before the variable
                            $intent_text_before_variable = substr($intent, 0, $start_bracket);
                            $pieces = explode(' ', trim($intent_text_before_variable));
                            $intent_word_before_variable = array_pop($pieces);
                            $log_text .= "\n intent_word_before_variable: ".$intent_word_before_variable;
                            //echo "<br>last word ".$intent_word_before_variable;
                            
                            // get the next word of the variable
                            $intent_text_after_variable = substr($intent, $end_variable_bracket + 1);
                            $log_text .= "\n intent_text_after_variable: ".$intent_text_after_variable;
                            if ($intent_text_after_variable) {
                                $pieces = explode(' ', trim($intent_text_after_variable));
                                $intent_word_after_variable = $pieces[0];
                                $log_text .= "\n2 intent_word_after_variable: ".$intent_word_after_variable;
                                //echo "<br>after word ".$intent_word_after_variable;
                                $variablePattern = '/^\[\w+\]\(\w+\)$/';
                                // if next word of the variable is a variable itself--change
                                if(preg_match($variablePattern, $intent_word_after_variable) == 1){
                                    $is_intent_word_before_variable = strpos($user_message, $intent_word_before_variable);
                                    if ($is_intent_word_before_variable !== false) {
                                        $variable_one_position = $is_intent_word_before_variable + strlen($intent_word_before_variable);
                                        $user_variable_value = substr($user_message, $variable_one_position );
                                        if (preg_match('/\b(\w+)\b/', $user_variable_value, $match)) {
                                            $user_variable_value = $match[1];
                                            $variable_one_position = strlen($user_variable_value) + strpos($user_message, $user_variable_value);
                                        }
                                    }
                                } else if(preg_match($variablePattern, $intent_word_before_variable) == 1){
                                    // if previous word of the variable is a variable itself--change
                                    $is_intent_word_after_variable = strpos($user_message, $intent_word_after_variable);
                                    if ($is_intent_word_after_variable !== false) {
                                        $user_variable_value = substr($user_message, $variable_one_position, $is_intent_word_after_variable - $variable_one_position);
                                    }
                                } else {
                                    // --change
                                    $pattern = '/' . preg_quote($intent_word_before_variable, '/') . '\s*(.*?)\s*' . preg_quote($intent_word_after_variable, '/') . '/i';
                                    if (preg_match($pattern, $user_message, $match) == 1) {
                                        $user_variable_value = $match[1];
                                    }
                                    // echo "<br> User Variable Value 1 ".$user_variable_value;
                                }
                            } else { // variable at end of string
                                if ($intent_word_before_variable) {
                                    $total_intent_word_before_variable = substr_count($user_message, $intent_word_before_variable);
                                        if ($total_intent_word_before_variable > 1) { // multiple word in the string
                                        $is_intent_word_before_variable = strrpos($user_message, $intent_word_before_variable); // read last occurrence
                                        if ($is_intent_word_before_variable !== false) {
                                            $user_variable_value = substr($user_message, $is_intent_word_before_variable + strlen($intent_word_before_variable));
                                        $log_text .= "\n 1 user_variable_value: ".$user_variable_value;
                                           // echo "<br> user_variable_value 2 ".$user_variable_value;
                                        }
                                    } else { // only once
                                        // if previous word of the variable is a variable itself--change
                                        $variablePattern = '/^\[\w+\]\(\w+\)$/';
                                        if(preg_match($variablePattern, $intent_word_before_variable) == 1){
                                            $user_variable_value = substr($user_message, $variable_one_position, strlen($user_message) - $variable_one_position);
                                        } else {
                                            $is_intent_word_before_variable = strpos($user_message, $intent_word_before_variable);
                                            if ($is_intent_word_before_variable !== false) {
                                                $user_variable_value = substr($user_message, $is_intent_word_before_variable + strlen($intent_word_before_variable));
                                    $log_text .= "\n 2 user_variable_value: ".$user_variable_value;
                                                // echo "<br> user_variable_value 3 ".$user_variable_value;
                                            }
                                        }
                                    }
                                } else {
                                    // if before and after of variable is empty--change
                                    $user_variable_value = $user_message;
                                }
                            }

                            if ($user_variable_value) {
                                // --change
                                $variables[$variable_name] = trim($user_variable_value, " .,");
                            }

                        } else {
                            $start_bracket = 0; // exit the loop
                        }
                    }
                } else {
                    $start_bracket = 0; // exit the loop
                }
            }
        } while ($start_bracket >= 1);
        return $variables;
    }

    function getVariablesValuesFromUserMessage($user_message, $variables){
        $new_variables = array();
        foreach($variables as $key => $value){
            if(strpos($user_message,$value)!== false){
                $new_variables[$key] = $value;
            }
        }
        return $new_variables;
    }

    function getVariables($data){
        $variables = array();
        $start = 0;
        do{
            $start_braket = strpos($data,"[", $start);
            if($start_braket){
                $end_braket = strpos($data,"]", $start);
                $start = $end_braket+1; //for next variable
                if($end_braket){
                    $variable_value = substr($data,$start_braket+1,($end_braket-$start_braket-1));
                    $start_variable_braket = strpos($data,"(", $start);
                    if(($start_variable_braket - $end_braket) < 3){ //same value variable i.e [Lahore](city_name)
                        $end_variable_braket = strpos($data,")", $start);
                        if($end_variable_braket){
                            $variable_name = substr($data,$start_variable_braket+1,($end_variable_braket-$start_variable_braket-1));
                            $variables[$variable_name] = $variable_value;
                        }else{
                            $start_braket = 0; //exit the loop
                        }
                    }
                }else{
                    $start_braket = 0; //exit the loop
                }
            }
        }while($start_braket >= 1);
        
        return $variables;
    }

    function removeStopWords($input){
        $stopwords = $this->getStopWords();
        $input = geekybotphplib::GEEKYBOT_preg_replace('/\b('.implode('|',$stopwords).')\b/','',$input);
        $input = trim(geekybotphplib::GEEKYBOT_preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $input)));    
        return $input;
    }

    function getStopWords(){
        static $data = [
            'a',
            'about',
            'above',
            'above',
            'across',
            'after',
            'afterwards',
            'again',
            'against',
            'all',
            'almost',
            'alone',
            'along',
            'already',
            'also',
            'although',
            'always',
            'am',
            'among',
            'amongst',
            'amoungst',
            'amount',
            'an',
            'and',
            'another',
            'any',
            'anyhow',
            'anyone',
            'anything',
            'anyway',
            'anywhere',
            'are',
            'around',
            'as',
            'at',
            'back',
            'be',
            'became',
            'because',
            'become',
            'becomes',
            'becoming',
            'been',
            'before',
            'beforehand',
            'behind',
            'being',
            'below',
            'beside',
            'besides',
            'between',
            'beyond',
            'bill',
            'both',
            'bottom',
            'but',
            'by',
            'call',
            'can',
            'cannot',
            'cant',
            'co',
            'con',
            'could',
            'couldnt',
            'cry',
            'de',
            'describe',
            'detail',
            'do',
            'done',
            'down',
            'due',
            'during',
            'each',
            'eg',
            'eight',
            'either',
            'eleven',
            'else',
            'elsewhere',
            'empty',
            'enough',
            'etc',
            'even',
            'ever',
            'every',
            'everyone',
            'everything',
            'everywhere',
            'except',
            'few',
            'fifteen',
            'fify',
            'fill',
            'find',
            'fire',
            'first',
            'five',
            'for',
            'former',
            'formerly',
            'forty',
            'found',
            'four',
            'from',
            'front',
            'full',
            'further',
            'get',
            'give',
            'go',
            'had',
            'has',
            'hasnt',
            'have',
            'he',
            'hence',
            'her',
            'here',
            'hereafter',
            'hereby',
            'herein',
            'hereupon',
            'hers',
            'herself',
            'him',
            'himself',
            'his',
            'how',
            'however',
            'hundred',
            'ie',
            'if',
            'in',
            'inc',
            'indeed',
            'interest',
            'into',
            'is',
            'it',
            'its',
            'itself',
            'keep',
            'last',
            'latter',
            'latterly',
            'least',
            'less',
            'ltd',
            'made',
            'many',
            'may',
            'me',
            'meanwhile',
            'might',
            'mill',
            'mine',
            'more',
            'moreover',
            'most',
            'mostly',
            'move',
            'much',
            'must',
            'my',
            'myself',
            'name',
            'namely',
            'neither',
            'never',
            'nevertheless',
            'next',
            'nine',
            'no',
            'nobody',
            'none',
            'noone',
            'nor',
            'not',
            'nothing',
            'now',
            'nowhere',
            'of',
            'off',
            'often',
            'on',
            'once',
            'one',
            'only',
            'onto',
            'or',
            'other',
            'others',
            'otherwise',
            'our',
            'ours',
            'ourselves',
            'out',
            'over',
            'own',
            'part',
            'per',
            'perhaps',
            'please',
            'put',
            'rather',
            're',
            'same',
            'see',
            'seem',
            'seemed',
            'seeming',
            'seems',
            'serious',
            'several',
            'she',
            'should',
            'show',
            'side',
            'since',
            'sincere',
            'six',
            'sixty',
            'so',
            'some',
            'somehow',
            'someone',
            'something',
            'sometime',
            'sometimes',
            'somewhere',
            'still',
            'such',
            'system',
            'take',
            'ten',
            'than',
            'that',
            'the',
            'their',
            'them',
            'themselves',
            'then',
            'thence',
            'there',
            'thereafter',
            'thereby',
            'therefore',
            'therein',
            'thereupon',
            'these',
            'they',
            'thickv',
            'thin',
            'third',
            'this',
            'those',
            'though',
            'three',
            'through',
            'throughout',
            'thru',
            'thus',
            'to',
            'together',
            'too',
            'top',
            'toward',
            'towards',
            'twelve',
            'twenty',
            'two',
            'un',
            'under',
            'until',
            'up',
            'upon',
            'us',
            'very',
            'via',
            'was',
            'we',
            'well',
            'were',
            'what',
            'whatever',
            'when',
            'whence',
            'whenever',
            'where',
            'whereafter',
            'whereas',
            'whereby',
            'wherein',
            'whereupon',
            'wherever',
            'whether',
            'which',
            'while',
            'whither',
            'who',
            'whoever',
            'whole',
            'whom',
            'whose',
            'why',
            'will',
            'with',
            'within',
            'without',
            'would',
            'yet',
            'you',
            'your',
            'yours',
            'yourself',
            'yourselves',
            'the',
            'theirs',
            'does',
            'did',
            'shall',
            'yes',
            'just',
            'i',
        ];
        return $data;
    }

    function getMessagekey(){
        $key = 'slots';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }   

    // new
    function getVariablesValuesForSelect(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-variables') ) {
            die( 'Security check Failed' ); 
        }
        $variablename = GEEKYBOTrequest::GEEKYBOT_getVar('term');
        $query = "SELECT variables.id,variables.name
            FROM `" . geekybot::$_db->prefix . "geekybot_slots` AS variables WHERE variables.name LIKE '%" . esc_sql($variablename) . "%'";
        $variables = geekybotdb::GEEKYBOT_get_results($query);
        $suggestions = array();
        $suggestions = "<select class='suggestions-for-autocomplete' multiple>";
        foreach ($variables as $key => $value) {
            $suggestions .= "<option value='".$value->id."' data-value='".$value->id."' class='geekybot-intent-usr-msg'>".$value->name."</option>";
        }
        $suggestions .= "</select>";
        echo wp_json_encode($suggestions);

    }

    function bindValuesOnSelectAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-variable-attributes') ) {
            die( 'Security check Failed' ); 
        }
        $id = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $query = "SELECT variable.*
            FROM `" . geekybot::$_db->prefix . "geekybot_slots` AS variable WHERE variable.id = ".esc_sql($id);
        $variable_data = geekybotdb::GEEKYBOT_get_row($query);
        return wp_json_encode($variable_data);
        // return $suggestions;
    }
}

?>
