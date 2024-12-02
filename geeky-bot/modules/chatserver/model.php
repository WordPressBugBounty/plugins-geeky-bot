<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTchatserverModel {

    function getMessagekey(){
        $key = 'chatserver'; if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    function getMessageResponse(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        $retVal = array();
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        if (! wp_verify_nonce( $nonce, 'get-message-response') ) {
            $errorMessage = new stdClass();
            $errorMessage->bot_response = esc_html(__("Security verification Failed, Please restart you chat to continue.", "geeky-bot"));
            $retVal[] = array("recipient_id"=>$chat_id, "text"=>$errorMessage);
            return wp_json_encode($retVal);
        }
        // check if the chat session is expire
        if ($chat_id == '') {
            $errorMessage = new stdClass();
            $errorMessage->bot_response = esc_html(__("Your session has expired; please restart your chat.", "geeky-bot"));
            $retVal[] = array("recipient_id"=>$chat_id, "text"=>$errorMessage);
            return wp_json_encode($retVal);
        }

        $message = GEEKYBOTrequest::GEEKYBOT_getVar('cmessage');
        $text = GEEKYBOTrequest::GEEKYBOT_getVar('ctext');
        $sender = GEEKYBOTrequest::GEEKYBOT_getVar('csender');
        $response_id = GEEKYBOTrequest::GEEKYBOT_getVar('response_id');
        $btnflag = GEEKYBOTrequest::GEEKYBOT_getVar('btnflag');
        $session_type = '';

		$logdata = "\n chatserver->getMessageResponse";
		$logdata .= "\n message: ".$message;

        // save user search to the session
        if (isset($text)) {
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($text, 'user');
            GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($text, 'user');
        }
        // get session check if story change
        $sessionStoryId = geekybot::$_geekybotsessiondata->geekybot_getStoryIdFromSession();
        
		// get intent id and score form user message
        $intentIdAndScore = $this->getIntentIdAndScoreFromUserMessage($message);
        if (isset($intentIdAndScore['id']) && $intentIdAndScore['id'] != 0) {
            // get intent data from intent id
            $query = "SELECT `id`, `user_messages`, `user_messages_text`, `group_id` FROM `" . geekybot::$_db->prefix . "geekybot_intents` WHERE `id` = " . esc_sql($intentIdAndScore['id']);
			$logdata .= "\n query: ".$query;
            $intentData = geekybotdb::GEEKYBOT_get_row($query);
            $intentGroupId = $intentData->group_id;
            $score = $intentIdAndScore['score'];
            // save variables from the intent
            GEEKYBOTincluder::GEEKYBOT_getModel('slots')->saveVariableFromIntent($message, $intentData->user_messages, $score);
        } else {
            $intentGroupId = '';
        }
        // get the response based on the found intent
        $responses = $this->getResponseData($message, $intentGroupId, $sessionStoryId);
		//$logdata .= "\n 59- response: ".print_r($responses);
        foreach ($responses as $key => $data) {
            $session_type = $data->story_type;
			$logdata .= "\n 46- response data type: ".$data->response_type;
            // response_type
            // 1-> Text
            // 2-> Custom Action
            // 3-> Form
            // 4-> Predrfine Function
            if($data->response_type == '1'){
                $data->story_type = 1;
                $str = $data->bot_response;
                $word = '<button>';
                // In case of buttons in response
                if($data->response_button != '[]' && $data->response_button != ''){
                    $responseButtons = json_decode($data->response_button);
                    foreach($responseButtons as $responseButton) {
                        $retVal2[] = array("text" => $responseButton->text, "type" => $responseButton->type, "value" => $responseButton->value);
                    }
                    $retVal[] = array("recipient_id"=>$chat_id, "text"=>$data,"buttons"=>$retVal2);
                } else {
                    $retVal[] = array("recipient_id"=>$chat_id, "text"=>$data);
                }
            } else if($data->response_type == '4'){
                $functionResult = geekybot::$_geekybotsessiondata->geekybot_readVarFromSessionAndCallPredefinedFunction($message, $data->function_id);
                if (isset($functionResult)) {
                    $data->bot_response = $functionResult;
                }
                $retVal[] = array("recipient_id"=>$chat_id, "text"=>$data);
            }
            $logdata .= "\n response data: ".$data->bot_response;
        }

        // if the indent found and bot response is not empty
        if (isset($retVal[0]['text']->bot_response)) {
            $isIndentFound = true;
            $isIndentFallback = false;
        } else {
            // indent fallback on the base of last story
            $isIndentFound = false;
            $isIndentFallback = false;
            $stackStory = GEEKYBOTincluder::GEEKYBOT_getModel('stack')->getLastActiveStoryFromStack();
            if(isset($stackStory)) {
                $session_type = $stackStory->story_type;
                // story_type
                // 1-> AI Story
                // 2-> Woocommerce Story
                // 3-> Forgot Password Story
                if($stackStory->story_type == 1){
                    $logdata .= "\n try ai fall back";
                    // if last active story is AI story
                    $fallback = $this->getFallbackForAIStory($message, $stackStory);
                } elseif ($stackStory->story_type == 2) {
                    // if last active story is Woocommerce
                    if (class_exists('WooCommerce')) {
                        $logdata .= "\n Woocommerce: Yes, try fall back";
                        $fallback = $this->getFallbackForWoocommerceStory($message);
                    }
                } elseif ($stackStory->story_type == 3) {
                    $logdata .= "\n try forgot password fall back";
                    // if last active story is forgot password
                    $fallback = $this->getFallbackForForgotPasswordStory($message);
                }
                if (isset($fallback) && $fallback != '') {
                    // $logdata .= "\n fall back is:".$fallback;
                    $isIndentFallback = true;
                }
            }
        }
        if ($isIndentFound) {
            // If user intent found in the story
            $logdata .= "\n isIndentFound:true";
        } elseif($isIndentFallback) {
            // If user intent not found in the story
            // but the story fallback found
            if (is_array($fallback)) {
                foreach ($fallback as $key => $value) {
                    $fallbackData = new stdClass();
                    $fallbackData->bot_response = $value;
                    $retVal[] = array("recipient_id"=>$chat_id, "text"=>$fallbackData);
                }
            } else {
                $fallbackData = new stdClass();
                $fallbackData->bot_response = $fallback;
                $retVal[] = array("recipient_id"=>$chat_id, "text"=>$fallbackData);
            }
            $logdata .= "\n isIndentFallback:true";
        } else {
            // If user intent not found in the story
            // if the story fallback also not found
            // call the woocommerce story fallback
            if (class_exists('WooCommerce')) {
                $session_type = 2;
                $logdata .= "\n Woocommerce fallback: Yes, try fall back";
                $fallback = $this->getFallbackForWoocommerceStory($message);
                if (isset($fallback) && $fallback != '') {
                    $fallbackData = new stdClass();
                    $fallbackData->bot_response = $fallback;
                    $retVal[] = array("recipient_id"=>$chat_id, "text"=>$fallbackData);
                }
                $logdata .= "\n fallback:".$fallback;
            }
            // add "show Articles" option in response
            $logdata .= "\n is_posts_enable:".geekybot::$_configuration['is_posts_enable'];
            if (geekybot::$_configuration['is_posts_enable'] == 1) {
                if (isset($fallbackData->bot_response) && $fallbackData->bot_response != '') {
                    $articleType = 1;
                } else {
                    $articleType = 2;
                    $session_type = '';
                }
                $articleButtonHtml = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->getArticlesButton($message, $articleType);
                // check if some related posts found
                if (isset($articleButtonHtml) && $articleButtonHtml != '') {
                    // Modified bot response if it exists
                    if (isset($retVal[0]['text']->bot_response)) {
                        $retVal[0]['text']->bot_articles = $articleButtonHtml;
                    } else{
                        // If no bot response, show post directly
                        $fallbackData = new stdClass();
                        $fallbackData->bot_response = $articleButtonHtml;
                        $retVal[] = array("recipient_id"=>$chat_id, "text"=>$fallbackData);
                    }
                    $logdata .= "\n show articles: true";
                }
            }
        }
        // save bot response to the session and chat history
        if (isset($retVal[0]['text']->bot_response)) {
            $botResponse = $retVal[0]['text']->bot_response;
            if (isset($retVal[0]['text']->bot_articles)) {
                $botResponse .= $retVal[0]['text']->bot_articles;
            }
            $botButtons = '';
            if (isset($retVal2)) {
                $botButtons = $responseButtons;
            }
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($botResponse, 'bot', $botButtons);
            GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($botResponse, 'bot', $session_type, $botButtons);
        }
        $logdata .= "\n ------------------ \n ";
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
        return wp_json_encode($retVal);
    }

    function getIntentIdAndScoreFromUserMessage($msg) {
        $stackCount = GEEKYBOTincluder::GEEKYBOT_getModel('stack')->isUserStackEmpty();
        $intent['id'] = 0;
        $intent['score'] = 0;
        // if stack is empty
        if ($stackCount == 0) {
            // Get all intent groups of the top story with same score
            $intentGroups = $this->getIntentsForSingleStoryByFullTextSearch($msg);
            $intentData = $this->getSuitableIntentFromMultipleIntents($intentGroups, $msg);
            if (isset($intentData['id'])) {
                $intent['id'] = $intentData['id'];
                $intent['score'] = $intentData['score'];
            }
        } else {
            // Get all top stories with intent groups with same score
            $storiesIntents = $this->getIntentsForMultipleStoryByFullTextSearch($msg);
            // get last active story from stack
            $stackStory = GEEKYBOTincluder::GEEKYBOT_getModel('stack')->getLastActiveStoryFromStack();
            // select the stack story as active story
            $activeStoryIntents = [];
            foreach ($storiesIntents as $storyIntents) {
                // get all intents of active/stack story
                if ($storyIntents->story_id == $stackStory->story_id) {
                    $activeStoryIntents[] = $storyIntents;
                }
            }
            if (empty($activeStoryIntents) && isset($storiesIntents[0])) {
                $topStoryIntents = $this->getAllIntentsFromTopSearchStory($storiesIntents, $msg);
                $intentData = $this->getSuitableIntentFromMultipleIntents($topStoryIntents, $msg);
                if (isset($intentData['id'])) {
                    $intent_id = $intentData['id'];
                    $intent_score = $intentData['score'];
                }
            } else {
                $intentData = $this->getSuitableIntentFromMultipleIntentsUsingStack($stackStory, $activeStoryIntents, $msg);
                if (isset($intentData['id'])) {
                    $intent_id = $intentData['id'];
                    $intent_score = $intentData['score'];
                }
            }
            // if no suitable intent found from stack
            // then get the top intent from intent search
            if (!isset($intent_id) && isset($intentsdata[0])) {
                $intent_id = $intentsdata[0]->id;
                $intent_score = $intentsdata[0]->score;
            }
            // if suitable intent found then get story
            if (isset($intent_id)) {
                $intent['id'] = $intent_id;
                $intent['score'] = $intent_score;
            }
        }
        return $intent;
    }

    function getResponseData($msg, $intentGroupId = '', $sessionStoryId = '') {
        $responses = array();
        if (isset($intentGroupId) && $intentGroupId != '') {
            // get story by intent group id with top ranking
            $query = "SELECT story.id AS story_id,story.name AS story_name,story.intent_ids AS story_intents,story.story_type ,intents_rank.intent_id AS intent_id,intents_rank.ranking AS intents_rank,intents_rank.intent_index FROM `" . geekybot::$_db->prefix . "geekybot_stories` AS story
            JOIN `" . geekybot::$_db->prefix . "geekybot_intents_ranking` AS intents_rank ON story.id = intents_rank.story_id
            WHERE intents_rank.intent_id = ".esc_sql($intentGroupId);
            $query .= " ORDER BY intents_rank.ranking ASC ";
            $story = geekybotdb::GEEKYBOT_get_row($query);
            // if story found then get response of that intent from this story
            if (isset($story)) {
                $responses = $this->getResponsesFromStory($story, $sessionStoryId);
                foreach ($responses as $responsekey => $responsevalue) {
                    if (isset($responsevalue->bot_response)) {
                        // read the variable and get value from session if the response contain variable
                        $bot_response = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->readResponseVariableValueFromSessionData($responsevalue->bot_response);
                        $responsevalue->bot_response = $bot_response;
                    }
                }
            }
        }
        return $responses;
    }

    function getIntentsForSingleStoryByFullTextSearch($msg) {
        $logdata = "\n getIntentsForSingleStoryByFullTextSearch";
        $logdata .= "\n msg ".$msg;
        $msg = addslashes($msg);
        // intent search with full text mood
        $query = 'SELECT intent.id, intent.user_messages_text, intent.group_id, intent.story_id, MATCH (intent.user_messages_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_intents` as intent
        INNER JOIN `'. geekybot::$_db->prefix .'geekybot_stories` as story ON intent.story_id = story.id
        WHERE MATCH (intent.user_messages_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND story.status = 1';
        $query .= " ORDER BY score DESC ";
        $logdata .= "\n".$query;
        $fullTextQueryResults = geekybotdb::GEEKYBOT_get_results($query);

        if(count($fullTextQueryResults) == 0){
            //search again without stop words removal
            if(str_word_count($msg) <= 3){
                $query = 'SELECT intent.id, intent.user_messages_text, intent.group_id, intent.story_id, "1" AS score FROM `' . geekybot::$_db->prefix . 'geekybot_intents` as intent
                    INNER JOIN `'. geekybot::$_db->prefix . 'geekybot_stories` as story ON intent.story_id = story.id 
                    WHERE intent.user_messages_text LIKE "%'.esc_sql($msg).'%" AND story.status = 1';
                $query .= " ORDER BY score DESC";
                $logdata .= "\n\n".$query;
                $likeQueryResults = geekybotdb::GEEKYBOT_get_results($query);
                $shortest = -1;
                if(isset($likeQueryResults)){
                    foreach ($likeQueryResults as $key => $value) {
                        $intenttext = trim($value->user_messages_text);
                        
                        $input = $msg;
                        $lev = levenshtein($msg, $intenttext);
                        $logdata .= "\n lev ".$lev;
                        if ($lev == 0) {
                            // closest word is this one (exact match)
                            $closest = $intenttext;
                            $closestintent = $value;
                            $shortest = 0;

                            // break out of the loop; we've found an exact match
                            break;
                        }

                        // if this distance is less than the next found shortest
                        // distance, OR if a next shortest word has not yet been found
                        if ($lev <= $shortest || $shortest < 0) {
                            // set the closest match, and shortest distance
                            $closest  = $intenttext;
                            $closestintent = $value;
                            $shortest = $lev;
                        }
                    }
                    if(isset($closestintent)){
                        $result[] = $closestintent;
                        $logdata .= "\n ------------------ \n ";
                        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
                        return $this->getAllIntentsFromTopSearchStory($result, $msg);
                    }
                }

            }
        }

        $logdata .= "\n ------------------ \n ";
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
        return $this->getAllIntentsFromTopSearchStory($fullTextQueryResults, $msg);
    }

    function getAllIntentsFromTopSearchStory($allIntents, $msg) {
        // recke this code
        if (empty($allIntents)) {
            return ;
        }
        $msg = addslashes($msg);
        // intent search with like query
        $query = "SELECT intent.id FROM " . geekybot::$_db->prefix . "geekybot_intents as intent
            INNER JOIN ". geekybot::$_db->prefix ."geekybot_stories as story ON intent.story_id = story.id
            WHERE intent.user_messages_text LIKE '%".esc_sql($msg)."%' AND story.status = 1";
        $likeQueryResult = geekybotdb::GEEKYBOT_get_var($query);
        // find the like query result in top 5 results
        if (isset($likeQueryResult) && $likeQueryResult != '') {
            for ($i=0; $i < 5; $i++) { 
                if (isset($allIntents[$i])) {
                    if ($allIntents[$i]->id == $likeQueryResult) {
                        $maxScore = round($allIntents[$i]->score, 2);
                        $storyId = $allIntents[$i]->story_id;
                        $i = 5;
                    }
                }
            }
        }
        // If the like query result is empty then select the top result
        if (!isset($storyId) || !isset($maxScore)) {
            $maxScore = round($allIntents[0]->score, 2);
            $storyId = $allIntents[0]->story_id;
        }
        $storyIntents = [];
        foreach ($allIntents as $key => $value) {
            $score = round($value->score, 2);
            // get all intents of top story with same score
            if ($value->story_id == $storyId && $score >= $maxScore) {
                $storyIntents[] = $value;
            }
        }
        return $storyIntents;
    }

    function getIntentsForMultipleStoryByFullTextSearch($msg) {
        $msg = addslashes($msg);
        // intent search
        $query = 'SELECT intent.id, intent.user_messages_text, intent.group_id, intent.story_id, MATCH (intent.user_messages_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_intents` as intent
            INNER JOIN `'. geekybot::$_db->prefix . 'geekybot_stories` as story ON intent.story_id = story.id 
            WHERE MATCH (intent.user_messages_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND story.status = 1';
        $query .= " ORDER BY score DESC";
        $intentsData = geekybotdb::GEEKYBOT_get_results($query);
        
        if(count($intentsData) == 0){
            //search again without stop words removal
            if(str_word_count($msg) <= 3){
                $query = 'SELECT intent.id, intent.user_messages_text, intent.group_id, intent.story_id, "1" AS score FROM `' . geekybot::$_db->prefix . 'geekybot_intents` as intent
                    INNER JOIN `'. geekybot::$_db->prefix . 'geekybot_stories` as story ON intent.story_id = story.id 
                    WHERE intent.user_messages_text LIKE "%'.esc_sql($msg).'%" AND story.status = 1';
                $query .= " ORDER BY score DESC";
                $likeQueryResults = geekybotdb::GEEKYBOT_get_results($query);
                $shortest = -1;
                if(isset($likeQueryResults)){
                    foreach ($likeQueryResults as $key => $value) {
                        $intenttext = trim($value->user_messages_text);
                        
                        $input = $msg;
                        $lev = levenshtein($msg, $intenttext);
                        if ($lev == 0) {
                            // closest word is this one (exact match)
                            $closest = $intenttext;
                            $closestintent = $value;
                            $shortest = 0;

                            // break out of the loop; we've found an exact match
                            break;
                        }

                        // if this distance is less than the next found shortest
                        // distance, OR if a next shortest word has not yet been found
                        if ($lev <= $shortest || $shortest < 0) {
                            // set the closest match, and shortest distance
                            $closest  = $intenttext;
                            $closestintent = $value;
                            $shortest = $lev;
                        }
                    }
                    if(isset($closestintent)){
                        $result[] = $closestintent;
                        return $result;
                    }
                }

            }
        }
        
        // get the maximum score
        $maxScore = isset($intentsData[0]->score) ? round($intentsData[0]->score, 2) : 0;
        $allIntents = [];
        foreach ($intentsData as $key => $value) {
            $score = round($value->score, 2);
            // get multiple stories with intents having same score
            if ($score == $maxScore) {
                $allIntents[] = $value;
            }
        }
        $uniqueCombinations = [];
        $result = [];
        // get multiple stories with intents having same score with unique intent group
        foreach ($allIntents as $intent) {
            $key = $intent->story_id . '-' . $intent->group_id;
            if (!isset($uniqueCombinations[$key])) {
                $uniqueCombinations[$key] = true;
                $result[] = $intent;
            }
        }
        if (empty($result) && isset($intentsData[0])) {
            $result[] = $intentsData[0];
        }
        return $result;
    }

    function getSuitableIntentFromMultipleIntents($intentGroups, $msg) {
        if (empty($intentGroups)) {
            return false;
        }
        $msg = addslashes($msg);
        $intent_ids = array_map(function($item) {
            return $item->id;
        }, $intentGroups);
        $intentIdsString = implode(',', $intent_ids);

        // check if the same intent found against the user search
        $query = "SELECT intent.id FROM " . geekybot::$_db->prefix . "geekybot_intents as intent
            INNER JOIN ". geekybot::$_db->prefix ."geekybot_stories as story ON intent.story_id = story.id
            WHERE intent.user_messages_text LIKE '%".esc_sql($msg)."%' AND intent.id IN (".esc_sql($intentIdsString).") AND story.status = 1 ";
        $likeQueryResult = geekybotdb::GEEKYBOT_get_var($query);
        if (isset($likeQueryResult) && is_numeric($likeQueryResult)) {
            $result = array_filter($intentGroups, function($item) use ($likeQueryResult) {
                return $item->id == $likeQueryResult;
            });
            if (!empty($result)) {
                $result = reset($result);
                $id = $result->id;
                $userMessage = $result->user_messages_text;
                $storyId = $result->story_id;
                $score = $result->score;
            }
        }
        // get data for the top search result
        if (!isset($id) || !isset($score)) {
            $id = $intentGroups[0]->id;
            $userMessage = $intentGroups[0]->user_messages_text;
            $storyId = $intentGroups[0]->story_id;
            $score = $intentGroups[0]->score;
        }
        $isSameMessage = 0;
        $isSameScore = 0;
        $sameMessageCount = 0;
        $sameScoreCount = 0;
        $sameMessageIntentIds = '';
        $sameScoreIntentIds = '';
        // Loop through the all intents with same user message
        foreach ($intentGroups as $intentGroup) {
            if ($intentGroup->user_messages_text == $userMessage) {
                $isSameMessage = 1;
                $sameMessageIntentIds .= $intentGroup->group_id . ',';
                $sameMessageCount++;
            }
            if ($intentGroup->score == $score) {
                $isSameScore = 1;
                $sameScoreIntentIds .= $intentGroup->group_id . ',';
                $sameScoreCount++;
            }
        }
        if ($isSameMessage == 1 && $sameMessageCount > 1) {
            $intentIds = rtrim($sameMessageIntentIds, ',');
            
        } else if($isSameScore == 1 && $sameScoreCount > 1) {
            $intentIds = rtrim($sameScoreIntentIds, ',');   
        }
        if (isset($intentIds))   {
            // get message with lowest rank from all user message
            $query = "SELECT ranking, intent_id AS group_id FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` IN (".esc_sql($intentIds).") AND `story_id` = ".esc_sql($storyId);
            $query .= " ORDER BY ranking  ASC LIMIT 1;";
            $nextRanking = geekybotdb::GEEKYBOT_get_row($query);
            // If rank found then get data of this rank from intents array
            if (isset($nextRanking->group_id)) {
                foreach ($intentGroups as $intentGroup) {
                    if ($intentGroup->group_id == $nextRanking->group_id) {
                        $intentData['id'] = $intentGroup->id;
                        $intentData['score'] = $intentGroup->score;
                        return $intentData;
                    }
                }
            }
        }
        $intentData['id'] = $id;
        $intentData['score'] = $score;
        return $intentData;
    }

    function getSuitableIntentFromMultipleIntentsUsingStack($stackStory, $storiesData, $msg) {
        if (empty($storiesData)) {
            return false;
        }
        $msg = addslashes($msg);
        $intent_ids = array_map(function($item) {
            return $item->id;
        }, $storiesData);
        $intentIdsString = implode(',', $intent_ids);
        // check if the same intent found against the user search
        $query = "SELECT `id` FROM `" . geekybot::$_db->prefix . "geekybot_intents` WHERE `user_messages_text` LIKE '%".esc_sql($msg)."%' AND `id` IN (".esc_sql($intentIdsString).") ";
        $likeQueryResult = geekybotdb::GEEKYBOT_get_var($query);
        if (isset($likeQueryResult) && is_numeric($likeQueryResult)) {
            $result = array_filter($storiesData, function($item) use ($likeQueryResult) {
                return $item->id == $likeQueryResult;
            });
            if (!empty($result)) {
                $result = reset($result);
                $id = $result->id;
                $userMessage = $result->user_messages_text;
                $storyId = $result->story_id;
                $score = $result->score;
            }
        }
        // get data for the top search result
        if(!isset($id) || !isset($score)) {
            $id = $storiesData[0]->id;
            $userMessage = $storiesData[0]->user_messages_text;
            $storyId = $storiesData[0]->story_id;
            $score = $storiesData[0]->score;
        }
        $isSameMessage = 0;
        $isSameScore = 0;
        $sameMessageCount = 0;
        $sameScoreCount = 0;
        $sameMessageIntentIds = '';
        $sameScoreIntentIds = '';
        // Loop through the all intents with same user message
        foreach ($storiesData as $storieData) {
            if ($storieData->user_messages_text == $userMessage) {
                $isSameMessage = 1;
                $sameMessageIntentIds .= $storieData->group_id . ',';
                $sameMessageCount++;
            }
            if ($storieData->score == $storiesData[0]->score) {
                $isSameScore = 1;
                $sameScoreIntentIds .= $storieData->group_id . ',';
                $sameScoreCount++;
            }
        }
        if ($isSameMessage == 1 && $sameMessageCount > 1) {
            $intentIdAccordingToRank = $this->getIntentIdAccordingToRank($storyId, $stackStory, $sameMessageIntentIds, $storiesData);
        } else if($isSameScore == 1 && $sameScoreCount > 1) {
            $intentIdAccordingToRank = $this->getIntentIdAccordingToRank($storyId, $stackStory, $sameScoreIntentIds, $storiesData);
        }
        if (isset($intentIdAccordingToRank) && $intentIdAccordingToRank != '') {
            $id = $intentIdAccordingToRank;
        }
        $intentData['id'] = $id;
        $intentData['score'] = $score;
        return $intentData;

    }

    function getIntentIdAccordingToRank($storyId, $stackStory, $intentIds, $storiesData) {
        // find the rank of stack story
        $query = "SELECT ranking FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` = ".esc_sql($stackStory->intent_id)." AND `story_id` = ".esc_sql($stackStory->story_id);
        $stackRanking = geekybotdb::GEEKYBOT_get_var($query);
        $intentIds = rtrim($intentIds, ',');
        // get next rank to the stack intent
        $query = "SELECT ranking, intent_id AS group_id FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` IN (".esc_sql($intentIds).") AND `ranking` > ".esc_sql($stackRanking)." AND `story_id` = ".esc_sql($storyId);
        $query .= " ORDER BY ranking  ASC LIMIT 1;";
        $nextRanking = geekybotdb::GEEKYBOT_get_row($query);
        // if the next rank not found
        if (!isset($nextRanking->group_id)) {
            // get message with lowest rank from all user message
            $query = "SELECT ranking, intent_id AS group_id FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` IN (".esc_sql($intentIds).") AND `story_id` = ".esc_sql($storyId);
            $query .= " ORDER BY ranking  ASC LIMIT 1;";
            $nextRanking = geekybotdb::GEEKYBOT_get_row($query);
        }
        // If rank found then get data of this rank from intents array
        if (isset($nextRanking->group_id)) {
            foreach ($storiesData as $storieData) {
                if ($storieData->group_id == $nextRanking->group_id) {
                    return $storieData->id;
                }
            }
        }
        return;
    }
    function getResponsesFromStory($story, $sessionStoryId) {
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
        $index = $story->intent_index;
        $ranking = $story->intents_rank;
        $story_intents_array = json_decode($story->story_intents,true);
        $responses = [];
        $addToStack = 'no';
        do {
            $index++;
            // if the given index is intent
            if (isset($story_intents_array['intentid_'.$index.'id'])) {
                if (isset($match) && $match == 'Y') {
                    // its means the intent found after showing response
                    $match = 'N';
                } else {
                    // its means the intent found before showing any response
                    $match = 'Y';
                }
            } elseif (isset($story_intents_array['responseid_'.$index.'id'])) {
                // validateVariablesValue(), checkIfVarAlreadySet() call these functions in the case of form data or random chat

                // get the response id from the ranking
                $responseId = $story_intents_array['responseid_'.$index.'id'];
                // get response data from response id
                $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_responses` WHERE `id` = ".esc_sql($responseId);
                $responseData = geekybotdb::GEEKYBOT_get_row($query);
                // if response data is not empty
                if (isset($responseData)) {
                    $responseData->story_type = $story->story_type;
                    // save variables from response to session recheck
                    // $rankFromVariable = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->readVariablesInResponse($responseData->bot_response, $index, $ranking, $story->story_id, $chathistory, $sessionStoryId);
                    $responses[] = $responseData;
                    // set the match "y" to check for the next response
                    $addToStack = 'yes';
                    $match = 'Y';
                }
            } else{
                $match = 'N';
            }
        } while ($match == 'Y');
        if ($addToStack == 'yes') {
            $stackData['chat_id'] = $chat_id;
            $stackData['intent_id'] = $story->intent_id;
            $stackData['response_id'] = $responseData->id;
            $stackData['story_id'] = $story->story_id;
            GEEKYBOTincluder::GEEKYBOT_getModel('stack')->addResponseInStack($stackData);
            // base_plugin_change
        }
        return $responses;
    }

    function changeStatusOfSavedVariables(){
        $chatid = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();

        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_sessiondata` SET `status` = '0' WHERE usersessionid = '".esc_sql($chatid)."' AND sessionmsgkey  NOT IN ('nextIndex','ranking','flag','index','story','chathistory')";
        geekybotdb::query($query);
        return true;
    }

    function getStoryIndexByRank($ranking, $story_id){
        $query = "SELECT intent_index FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `ranking` = ".esc_sql($ranking)." AND `story_id` = ".esc_sql($story_id);
        $responsesData = geekybotdb::GEEKYBOT_get_var($query);
        return $responsesData;
    }

    // Functions related to the FallBack

    function getDefaultFallBackFormAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'get-fallback') ) {
            die( 'Security check Failed' );
        }
        // get last 2 stories from the stack
        $chat_id = GEEKYBOTrequest::GEEKYBOT_getVar('chat_id');
        $query = "SELECT distinct(story_id) FROM `" . geekybot::$_db->prefix . "geekybot_stack` where chat_id = '".esc_sql($chat_id)."'";
        $query .= " ORDER BY id  DESC LIMIT 2;";
        $stackData = geekybotdb::GEEKYBOT_get_results($query);
        foreach ($stackData as $key => $value) {
            // get fallback from the stack story
            $query = "SELECT default_fallback FROM `" . geekybot::$_db->prefix . "geekybot_stories` where id = ".esc_sql($value->story_id);
            $fallbackMessage = geekybotdb::GEEKYBOT_get_var($query);
            if (isset($fallbackMessage) && $fallbackMessage != '') {
                geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($fallbackMessage, 'bot');
                GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($fallbackMessage, 'bot');
                return $fallbackMessage;
            }
        }
        // new get fallback from the configurations
        $configMsg = geekybot::$_configuration['default_message'];
        if (isset($configMsg) && $configMsg != '' ) {
            $fallbackMessage = geekybot::$_configuration['default_message'];
        } else {
            $fallbackMessage =  __("Hi, I am Chatbot. I do not have specific knowledge.", "geeky-bot");
        }
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($fallbackMessage, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($fallbackMessage, 'bot');
        return $fallbackMessage;
    }

    function getFallbackForAIStory($message, $stackStory) {
        // check if AI story is disable
        // story_type
        // 1-> AI Story
        // 2-> Woocommerce Story
        // 3-> Forgot Password Story
        $query = "SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `story_type` = 1";
        $AIStatus = geekybotdb::GEEKYBOT_get_var($query);
        if (!isset($AIStatus) || $AIStatus != 1 ) {
            return;
        }
        $stackStoryId = $stackStory->story_id;
        $StoryIntentId = $stackStory->intent_id;
        // get the rank of last intent from stack
        $query = "SELECT `ranking` FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` = ".esc_sql($StoryIntentId)." AND `story_id` = ".esc_sql($stackStoryId);
        $ranking = geekybotdb::GEEKYBOT_get_var($query);
        if (isset($ranking)) {
            $ranking++;
            // get the data of next possible intent from story
            $query = "SELECT `story_id`, `intent_id`, `intent_index`, `ranking` FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `story_id` = ".esc_sql($stackStoryId)." AND `ranking` = ".esc_sql($ranking);
            $nextIntentData = geekybotdb::GEEKYBOT_get_row($query);
            if (isset($nextIntentData)) {
                // get intents from the stack story if the story is AI story
                $query = "SELECT `intent_ids` FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `id` = ".esc_sql($stackStoryId)." AND `story_type` = 1";
                $storyData = geekybotdb::GEEKYBOT_get_var($query);
                if (isset($storyData)) {
                    $storyData = json_decode($storyData);
                    $intent_index = $nextIntentData->intent_index;
                    $intentKey = 'intentid_'.$intent_index.'id';
                    $intentGroupId = $storyData->$intentKey;
                    if (isset($intentGroupId)) {
                        $query = "SELECT `id`, `user_messages`, `user_messages_text` FROM `" . geekybot::$_db->prefix . "geekybot_intents` WHERE `group_id` = " . esc_sql($intentGroupId);
                        $intent_data = geekybotdb::GEEKYBOT_get_row($query);
                        $score = 0;
                        // save variables from the next intent
                        $intentVariables = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->saveVariableFromFallBack($message, $intent_data->user_messages_text, $intent_data->user_messages);
                        if (!empty($intentVariables)) {
                            return $this->getResponseForAiFallback($message, $intentGroupId);
                        }
                    }
                }
            }
        }
        if (!isset($intentVariables) || empty($intentVariables)) {
            // remove punctuation, periods! and questions mark? from user message
            $cleanMessage = geekybotphplib::GEEKYBOT_preg_replace("/\p{P}/u", "", $message);
            // convert user message into lower case
            $cleanMessage = strtolower($cleanMessage);
            // get the unique words from the user message
            $keywords =  GEEKYBOTincluder::GEEKYBOT_getModel('slots')->removeStopWords($cleanMessage);
            // if the user message contain only stop words and no keywords then run fall back
            if (empty($keywords)) {
                return $this->getResponseForAiStopWordsFallback($cleanMessage, $stackStoryId);
            }
        }
        return;
    }

    function getResponseForAiStopWordsFallback($message, $storyId) {
        // Tokenized the user message
        $tokenizedMessage = explode(" ", $message);
        // if the user message contain less than 10 stop woords
        if (count($tokenizedMessage) > 0 && count($tokenizedMessage) < 10) {
            $subQuery = "";
            // bind each shot words in the query
            foreach ($tokenizedMessage as $key => $value) {
                $subQuery .= " (user_messages_text LIKE '%".esc_sql($value)."%') +";
            }
            $subQuery = rtrim($subQuery, '+');
            // get the intent with high relevance
            $query = "SELECT group_id, ".$subQuery." AS relevance_score
                FROM `" . geekybot::$_db->prefix . "geekybot_intents`
                WHERE story_id = ".esc_sql($storyId);
            $query .= " ORDER BY relevance_score DESC";
            $data = geekybotdb::GEEKYBOT_get_row($query);
            // if find the group id in the intent data get response
            if (isset($data->group_id) && $data->relevance_score > 0) {
                return $this->getResponseForAiFallback($message, $data->group_id);
            }
        }
    }

    function getResponseForAiFallback($message, $intentGroupId) {
        $responses = $this->getResponseData($message, $intentGroupId);
        $retVal = [];
        foreach ($responses as $key => $data) {
            // response_type
            // 1-> Text
            // 4-> Predrfine Function
            if($data->response_type == '1'){
                $retVal[] = $data->bot_response;
            } else if($data->response_type == '4'){
                $functionResult = geekybot::$_geekybotsessiondata->geekybot_readVarFromSessionAndCallPredefinedFunction($message, $data->function_id);
                if (isset($functionResult)) {
                    $data->bot_response = $functionResult;
                }
                $retVal[] = $data->bot_response;
            }
        }
        return $retVal;
    }

    function getFallbackForWoocommerceStory($message, $data = '', $currentPage = 1) {
        // check if woocommerce story is disable
        // story_type
        // 1-> AI Story
        // 2-> Woocommerce Story
        // 3-> Forgot Password Story
        $query = "SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `story_type` = 2";
        $WooStoryStatus = geekybotdb::GEEKYBOT_get_var($query);
        if (!isset($WooStoryStatus) || $WooStoryStatus != 1 ) {
            return;
        }
        // Set the number of products to display per page
        $productsPerPage = geekybot::$_configuration['pagination_product_page_size'];
        // Calculate the offset based on the current page and products per page
        $offset = ($currentPage - 1) * $productsPerPage;

        $fallbackProducts = GEEKYBOTincluder::GEEKYBOT_getModel('woocommerce')->getProductsFromWcFirstFallback($message, $currentPage, $productsPerPage);
        $products = $fallbackProducts['products'];
        $all_products = $fallbackProducts['count'];
        $html = "";
        if($products){
            $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($message, $products, 'fallbackone', $all_products, $currentPage, 'chatserver', 'getFallbackForWoocommerceStory', $data);
        } else {
            $fallbackProducts = GEEKYBOTincluder::GEEKYBOT_getModel('woocommerce')->getProductsFromWcSecondFallback($message, $currentPage, $productsPerPage);
            $products = $fallbackProducts['products'];
            $all_products = $fallbackProducts['count'];
            if($products){
                $html = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getWcProductListingHtml($message, $products, 'fallbacktwo', $all_products, $currentPage, 'chatserver', 'getFallbackForWoocommerceStory', $data);
            }
        }
        return $html;
    }

    function getFallbackForForgotPasswordStory($message) {
        // check if Forgot Password Story story is disable
        // story_type
        // 1-> AI Story
        // 2-> Woocommerce Story
        // 3-> Forgot Password Story
        $query = "SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `story_type` = 3";
        $ForgotPasswordStatus = geekybotdb::GEEKYBOT_get_var($query);
        if (!isset($ForgotPasswordStatus) || $ForgotPasswordStatus != 1 ) {
            return;
        }
        // check if the message contain email address
        $emailFound = GEEKYBOTincluder::GEEKYBOT_getModel('systemaction')->checkIsMessageContainEmail($message);
        if (isset($emailFound) && $emailFound != '') {
            $user_data = get_user_by( 'email', $emailFound ); // Get user by email
            if ( $user_data ) {
                $returnData = GEEKYBOTincluder::GEEKYBOT_getModel('systemaction')->sendRestLinkToUserThroughEmail($user_data);
                return $returnData;
                wp_die();
            }
        } else {
            $messageArray =  explode(' ', $message);
            // check if the message contain less than 3 words
            if (count($messageArray) <= 3) {
                // Loop on all the words and try to find user
                foreach ($messageArray as $key => $value) {
                    $user_data = get_user_by( 'login', $value ); // Get user by username
                    if ( $user_data ) {
                        $returnData = GEEKYBOTincluder::GEEKYBOT_getModel('systemaction')->sendRestLinkToUserThroughEmail($user_data);
                        // if user found then send reset link
                        return $returnData;
                        wp_die();
                    }
                }
            } else {
                return __("Your message is too long. Please enter only a username or email.", "geeky-bot");
                wp_die();
            }
        }
        return __("Invalid username or email. Try again with the correct one.", "geeky-bot");
        wp_die();
    }
}
?>
