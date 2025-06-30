<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTchatserverModel {

    function getMessagekey(){
        $key = 'chatserver'; if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    function getMessageResponse(){
        $logdata = '';
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        $retVal = [];
        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();

        // Verify nonce for security
        if (!wp_verify_nonce($nonce, 'get-message-response')) {
            // disable nonce
            /*$errorMessage = new stdClass();
            $errorMessage->bot_response = esc_html(
                __("Security verification Failed, Please refresh your chat to continue.", "geeky-bot")
            );
            $retVal[] = ["recipient_id" => $chat_id, "text" => $errorMessage];
            return wp_json_encode($retVal);*/
        }

        // Check if the chat session has expired
        if (empty($chat_id)) {
            $errorMessage = new stdClass();
            $errorMessage->bot_response = esc_html(
                __("Your session has expired; please restart your chat.", "geeky-bot")
            );
            $retVal[] = ["recipient_id" => $chat_id, "text" => $errorMessage];
            return wp_json_encode($retVal);
        }

        // Retrieve user inputs
        $message = GEEKYBOTrequest::GEEKYBOT_getVar('cmessage');
        $text = GEEKYBOTrequest::GEEKYBOT_getVar('ctext');
        $sender = GEEKYBOTrequest::GEEKYBOT_getVar('csender');
        $response_id = GEEKYBOTrequest::GEEKYBOT_getVar('response_id');
        $btnflag = GEEKYBOTrequest::GEEKYBOT_getVar('btnflag');
        $session_type = '';

		$logdata = "\n chatserver->getMessageResponse";
		$logdata .= "\n message: ".$message;
        // Save user message to session and chat history
        if (!empty($text)) {
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($text, 'user');
            GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($text, 'user');
        }
        if (geekybot::$_configuration['ai_provider'] == 1) {
            // Get session story ID
            $sessionStoryId = geekybot::$_geekybotsessiondata->geekybot_getStoryIdFromSession();

            // Determine user intent and retrieve intent details
            $intentIdAndScore = $this->getIntentIdAndScoreFromUserMessage($message);
            $intentGroupId = '';
            if (!empty($intentIdAndScore['id'])) {
                // get intent data from intent id
                $query = "SELECT `id`, `user_messages`, `user_messages_text`, `group_id` FROM `" . geekybot::$_db->prefix . "geekybot_intents` WHERE `id` = " . esc_sql($intentIdAndScore['id']);
    			$logdata .= "\n query: ".$query;
                $intentData = geekybotdb::GEEKYBOT_get_row($query);
                $intentGroupId = $intentData->group_id;

                // Save intent variables
                GEEKYBOTincluder::GEEKYBOT_getModel('slots')->saveVariableFromIntent(
                    $message, 
                    $intentData->user_messages, 
                    $intentIdAndScore['score']
                );
            }

            // Get bot responses based on intent
            $responses = $this->getResponseData($message, $intentGroupId, $sessionStoryId);
            foreach ($responses as $data) {
                $session_type = $data->story_type;
                if ($data->response_type == '1') { // Text response
                    $buttons = [];
                    $data->story_type = 1;
                    // In case of buttons in response
                    if (!empty($data->response_button) && $data->response_button != '[]') {
                        $responseButtons = json_decode($data->response_button);
                        foreach ($responseButtons as $responseButton) {
                            $buttons[] = [
                                "text" => $responseButton->text,
                                "type" => $responseButton->type,
                                "value" => $responseButton->value
                            ];
                        }
                        $retVal[] = ["recipient_id" => $chat_id, "text" => $data, "buttons" => $buttons];
                    } else {
                        $retVal[] = ["recipient_id" => $chat_id, "text" => $data];
                    }
                } elseif ($data->response_type == '4') { // Predefined function
                    $functionResult = geekybot::$_geekybotsessiondata->geekybot_readVarFromSessionAndCallPredefinedFunction($message, $data->function_id);
                    if (!empty($functionResult)) {
                        $data->bot_response = $functionResult;
                        $retVal[] = ["recipient_id" => $chat_id, "text" => $data];
                    }
                }
            }
        } elseif (geekybot::$_configuration['ai_provider'] == 2) {
            $uploadDir = wp_upload_dir();
            if (!empty(geekybot::$_configuration['geekybot_dialogflow_project_id']) && !empty(get_option('geekybot_dialogflow_json')) && file_exists($uploadDir['basedir'] . '/geekybotLibraries/dialogFlow/geekybot_google_client-main/autoload.php')) {
                $res = geekybot::$_geekybotdialogflow->geekybot_dialogflow($text, $chat_id);

                $dialogflowResponse = new stdClass();
                $dialogflowResponse->bot_response = $res;
                $retVal[] = ["recipient_id" => $chat_id, "text" => $dialogflowResponse];
            }
        } elseif (geekybot::$_configuration['ai_provider'] == 3) {
            $uploadDir = wp_upload_dir();
            $isAssistantFound = get_option('geekybot_assistant_id');
            if (
                in_array('openaiassistant', geekybot::$_active_addons) &&
                !empty($isAssistantFound) && 
                file_exists($uploadDir['basedir'] . '/geekybotLibraries/openAI/geekybot_openai_assistant_client_library-main/autoload.php')
            ) {
                $res = geekybot::$_geekybotopenaiassistant->geekybot_queryAssistant($text);

            } else {
                $res = geekybot::$_geekybotopenai->geekybot_openai($text);
            }
            $dialogflowResponse = new stdClass();
            $dialogflowResponse->bot_response = $res;
            $retVal[] = ["recipient_id" => $chat_id, "text" => $dialogflowResponse];
        } elseif (geekybot::$_configuration['ai_provider'] == 4) {
            $res = geekybot::$_geekybotopenrouter->geekybot_openrouter($text);

            $dialogflowResponse = new stdClass();
            $dialogflowResponse->bot_response = $res;
            $retVal[] = ["recipient_id" => $chat_id, "text" => $dialogflowResponse];
        }

        // if the indent found and bot response is not empty
        if (isset($retVal[0]['text']->bot_response)) {
            $isIndentFound = true;
            $isIndentFallback = false;
            // If user intent found in the story
            $logdata .= "\n isIndentFound:true";
        } else {
            // indent fallback on the base of last story
            $isIndentFound = false;
            $isIndentFallback = false;

            // intent base fallback
            $intentFallback = GEEKYBOTincluder::GEEKYBOT_getModel('stack')->getFallbackFromLastActiveIntent();
            if (!empty($intentFallback->default_fallback)) {
                $fallbackData = new stdClass();
                $fallbackData->bot_response = $intentFallback->default_fallback;
                $buttons = [];
                if (!empty($intentFallback->default_fallback_buttons) && $intentFallback->default_fallback_buttons != '[]') {
                    $intentFallbackButtons = json_decode($intentFallback->default_fallback_buttons);
                    foreach ($intentFallbackButtons as $intentFallbackButton) {
                        $buttons[] = [
                            "text" => $intentFallbackButton->text,
                            "type" => $intentFallbackButton->type,
                            "value" => $intentFallbackButton->value
                        ];
                    }
                    $retVal[] = ["recipient_id" => $chat_id, "text" => $fallbackData, "buttons" => $buttons];
                } else {
                    $retVal[] = ["recipient_id" => $chat_id, "text" => $fallbackData];
                }
            }

            $stackStory = GEEKYBOTincluder::GEEKYBOT_getModel('stack')->getLastActiveStoryFromStack();
            if ($stackStory && $stackStory->story_type == 1) {
                $fallback = $this->getFallbackForAIStory($message, $stackStory);
                if (!empty($fallback)) {
                    $session_type = $stackStory->story_type;
                    // If user intent not found in the story
                    // but the story fallback found
                    $isIndentFallback = true;
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
                }
            }
        }

        // WooCommerce fallback if the user intent is not found
        $responseLinks = '';
        if (class_exists('WooCommerce') && empty($intentIdAndScore['exact_match'])) {
            $headerType = !empty($retVal) ? 1 : 2;
            $logdata .= "\n WooCommerce fallback: Yes, attempting fallback.";

            $products = GEEKYBOTincluder::GEEKYBOT_getModel('woocommerce')->getProductsButton($message, $headerType);
            if (!empty($products)) {
                $responseLinks .= $products['productsBtn'];
            }
        }

        // Add "show Articles" option in the response
        $logdata .= "\n is_posts_enable: " . geekybot::$_configuration['is_posts_enable'];
        if (
            geekybot::$_configuration['is_posts_enable'] == 1 &&
            empty($products['exact_match']) &&
            empty($intentIdAndScore['exact_match'])
        ) {
            if (!empty($retVal) && !empty($responseLinks)) {
                $articleType = 1;
            } elseif (!empty($retVal) && empty($responseLinks)) {
                $articleType = 2;
            } elseif (empty($retVal) && !empty($responseLinks)) {
                $articleType = 3;
            } else {
                $articleType = 4;
            }
            // echo $articleType;
            // die('here');
            $session_type = !empty($responseLinks) || !empty($retVal)  ? $session_type : '';
            $articleButton = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->getArticlesButton($message, $articleType);

            // Append articles if related posts are found
            if (!empty($articleButton)) {
                $responseLinks .= $articleButton;
                $logdata .= "\n Show articles: true";
            }
        }

        // Create fallback response if additional links are available
        if (!empty($responseLinks)) {
            $fallbackData = new stdClass();
            $fallbackData->bot_response = geekybotphplib::GEEKYBOT_htmlentities($responseLinks);
            $retVal[] = [
                "recipient_id" => $chat_id,
                "text" => $fallbackData
            ];
        }

        // Save bot response to session and chat history
        if (isset($retVal[0]['text']->bot_response)) {
            $botResponse = '';

            // Construct the bot response
            foreach ($retVal as $retValue) {
                $botResponse .= '<section class="geekybot-message-text">';
                $botResponse .= html_entity_decode($retValue['text']->bot_response);
                $botResponse .= '</section>';

                // Include articles if present
                if (isset($retValue['text']->bot_articles)) {
                    $botResponse .= $retValue['text']->bot_articles;
                }

                // Add buttons to the response
                if (isset($retValue['buttons'])) {
                    // Convert each sub-array to an object
                    $botButtons = array_map(function($item) {
                        return (object) $item;
                    }, $retValue['buttons']);
                    $botResponse .= "<div class='geekybot-message-button'>";
                    foreach ($botButtons as $responseButton) {
                        if ($responseButton->type == 1) {
                            $botResponse .= "<li class='geekybot-message geekybot-message-button'>";
                            $botResponse .= "<section><button class='wp-chat-btn' onclick='sendbtnrsponse(this);' value='".$responseButton->value."'>";
                            $botResponse .= "<span>" . esc_html($responseButton->text) . "</span></button></section></li>";
                        } elseif ($responseButton->type == 2) {
                            $botResponse .= "<li class='geekybot-message geekybot-message-button'>";
                            $botResponse .= "<section><button class='wp-chat-btn'><span><a class='wp-chat-btn-link' href='".$responseButton->value."'>";
                            $botResponse .= esc_html($responseButton->text) . "</a></span></button></section></li>";
                        }
                    }
                    $botResponse .= "</div>";
                }
            }

            // Save chat history to session and server
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($botResponse, 'bot', 1);
            GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer(geekybotphplib::GEEKYBOT_htmlentities($botResponse), 'bot', $session_type);
        }

        return wp_json_encode($retVal);
    }

    function getIntentIdAndScoreFromUserMessage($msg) {
        $intent = [
            'id' => 0,
            'score' => 0,
            'exact_match' => 0
        ];

        $stackCount = GEEKYBOTincluder::GEEKYBOT_getModel('stack')->isUserStackEmpty();

        if ($stackCount == 0) {
            // Stack is empty, proceed with intent groups from a single story
            $intentData = $this->getIntentFromSingleStory($msg);
        } else {
            // Stack is not empty, check multiple stories and stack story
            $intentData = $this->getIntentFromMultipleStories($msg);
        }

        if (isset($intentData['intent']['id'])) {
            $intent['id'] = $intentData['intent']['id'];
            $intent['score'] = $intentData['intent']['score'];
            $intent['exact_match'] = $intentData['exact_match'];
        }

        return $intent;
    }

    private function getIntentFromSingleStory($msg) {
        // Get all intent groups from a single story based on full text search
        $intents = $this->getIntentsForSingleStoryByFullTextSearch($msg);
        $intent = $this->getSuitableIntentFromMultipleIntents($intents['intents']);
        return ['exact_match' => $intents['exact_match'], 'intent' => $intent];
    }

    private function getIntentFromMultipleStories($msg) {
        // Get all top stories with intent groups with same score
        $intentsData = $this->getIntentsForMultipleStoryByFullTextSearch($msg);
        
        // Get last active story from stack
        $stackStory = GEEKYBOTincluder::GEEKYBOT_getModel('stack')->getLastActiveStoryFromStack();
        
        // Select intents for active story from stack
        $activeStoryIntents = $this->getActiveStoryIntents($intentsData['intents'], $stackStory);

        if (empty($activeStoryIntents)) {
            $final_inents = $this->getIntentFromTopStory($intentsData['intents']);
        } else {
            $final_inents = $this->getIntentFromStack($stackStory, $activeStoryIntents, $msg);
        }
        $exact_match = $intentsData['exact_match'];
        return ['exact_match' => $exact_match, 'intent' => $final_inents];
    }

    private function getActiveStoryIntents($storiesIntents, $stackStory) {
        $activeStoryIntents = [];
        foreach ($storiesIntents as $storyIntents) {
            if ($storyIntents->story_id == $stackStory->story_id) {
                $activeStoryIntents[] = $storyIntents;
            }
        }
        return $activeStoryIntents;
    }

    private function getIntentFromTopStory($storiesIntents) {
        // If no active story intents found, get the top intent from the top search story
        $topStoryIntents = $this->getAllIntentsFromTopSearchStory($storiesIntents);
        return $this->getSuitableIntentFromMultipleIntents($topStoryIntents);
    }

    private function getIntentFromStack($stackStory, $activeStoryIntents, $msg) {
        // Get suitable intent from stack-based stories
        return $this->getSuitableIntentFromMultipleIntentsUsingStack($stackStory, $activeStoryIntents, $msg);
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
        $query = "(SELECT intent.id, intent.user_messages_text, intent.group_id, intent.story_id, '999' AS score FROM `" . geekybot::$_db->prefix . "geekybot_intents` as intent
                    INNER JOIN `". geekybot::$_db->prefix . "geekybot_stories` as story ON intent.story_id = story.id 
                    WHERE intent.user_messages_text LIKE '%".esc_sql($msg)."%' AND story.status = 1";
        $query .= " ORDER BY score DESC LIMIT 100)";
        $query .= ' UNION ';
        $query .= '(SELECT intent.id, intent.user_messages_text, intent.group_id, intent.story_id, MATCH (intent.user_messages_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_intents` as intent
        INNER JOIN `'. geekybot::$_db->prefix .'geekybot_stories` as story ON intent.story_id = story.id
        WHERE MATCH (intent.user_messages_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND story.status = 1';
        $query .= " ORDER BY score DESC LIMIT 100)";
        $logdata .= "\n".$query;
        $intents = geekybotdb::GEEKYBOT_get_results($query);

        // Process intents
        $story_count = [];
        $highest_score = 0;
        $intent_ids = [];

        foreach ($intents as $intent) {
            // Update highest score for non-static intents
            if ($intent->score != 999 && $intent->score > $highest_score) {
                $highest_score = $intent->score;
            }

            // Track story count
            $story_count[$intent->story_id] = ($story_count[$intent->story_id] ?? 0) + 1;
        }
        
        foreach ($intents as &$intent) {
            // Avoid duplicate IDs and adjust scores
            if (!in_array($intent->id, $intent_ids)) {
                $intent_ids[] = $intent->id;
                if ($intent->score == 999) {
                    $intent->score = $highest_score + 1; // set score to make intent relevant
                }
            }
        }
        unset($intent);

        // Find the closest match using Levenshtein distance if multiple stories are present
        $closest_intent = null;
        $exact_match = false;
        // if (count($story_count) > 1) {
            $shortest_distance = -1;
            foreach ($intents as $intent) {
                $lev_distance = levenshtein(strtolower($msg), strtolower(trim($intent->user_messages_text)));
                $logdata .= "\n lev ".$lev_distance;
                if ($lev_distance == 0) {
                    // closest word is this one (exact match)
                    $closest_intent = $intent;
                    $exact_match = true;
                    // break out of the loop; we've found an exact match
                    break;
                }
                // if this distance is less than the next found shortest
                // distance, OR if a next shortest word has not yet been found
                if ($lev_distance <= $shortest_distance || $shortest_distance < 0) {
                    // set the closest match, and shortest distance
                    $lev_distance_percentage = $this->calculateLevenshteinSimilarity($msg, $intent->user_messages_text, $lev_distance);
                    if($lev_distance_percentage > 50){
                        $closest_intent = $intent;
                        $shortest_distance = $lev_distance;
                    }
                }
            }
        // }

        if ($closest_intent) {
            $final_inents = $this->getAllIntentsFromTopSearchStory([$closest_intent]);
        } else {
            // $final_inents = $this->getAllIntentsFromTopSearchStory($intents);
            $final_inents = [];
        }
        return ['exact_match' => $exact_match, 'intents' => $final_inents];
    }

    function getAllIntentsFromTopSearchStory($allIntents) {
        if (empty($allIntents)) {
            return [];
        }

        // Identify the top story ID and max score
        $top_story_id = $allIntents[0]->story_id;
        $max_score = round($allIntents[0]->score, 2);

        // Filter and return relevant intents using a single loop
        $storyIntents = [];
        foreach ($allIntents as $intent) {
            if ($intent->story_id == $top_story_id && round($intent->score, 2) >= $max_score && $intent->score > 0) {
                $storyIntents[] = $intent;
            }
        }
        return $storyIntents;
    }

    function getIntentsForMultipleStoryByFullTextSearch($msg) {
        $msg = addslashes($msg);
        // intent search
        $query = "(SELECT intent.id, intent.user_messages_text, intent.group_id, intent.story_id, '999' AS score FROM `" . geekybot::$_db->prefix . "geekybot_intents` as intent
            INNER JOIN `". geekybot::$_db->prefix . "geekybot_stories` as story ON intent.story_id = story.id 
            WHERE intent.user_messages_text LIKE '%".esc_sql($msg)."%' AND story.status = 1";
        $query .= " ORDER BY score DESC LIMIT 100) ";
        $query .= ' UNION ';
        $query .= '(SELECT intent.id, intent.user_messages_text, intent.group_id, intent.story_id, MATCH (intent.user_messages_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_intents` as intent
            INNER JOIN `'. geekybot::$_db->prefix . 'geekybot_stories` as story ON intent.story_id = story.id 
            WHERE MATCH (intent.user_messages_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND story.status = 1';
        $query .= " ORDER BY score DESC LIMIT 100 ) ";
        $intents = geekybotdb::GEEKYBOT_get_results($query);

        // Process intents
        $story_count = [];
        $highest_score = 0;
        $intent_ids = [];

        foreach ($intents as $intent) {
            // Update highest score for non-static intents
            if ($intent->score != 999 && $intent->score > $highest_score) {
                $highest_score = $intent->score;
            }

            // Track story count
            $story_count[$intent->story_id] = ($story_count[$intent->story_id] ?? 0) + 1;
        }
        
        foreach ($intents as &$intent) {
            // Avoid duplicate IDs and adjust scores
            if (!in_array($intent->id, $intent_ids)) {
                $intent_ids[] = $intent->id;
                if ($intent->score == 999) {
                    $intent->score = $highest_score + 1; // set score to make intent relevant
                }
            }
        }
        unset($intent);
        
        // Find the closest match using Levenshtein distance if multiple stories are present
        $closest_intent = null;
        $exact_match = false;
        // if (count($story_count) > 1) {
            $shortest_distance = -1;
            foreach ($intents as $intent) {
                $lev_distance = levenshtein(strtolower($msg), strtolower(trim($intent->user_messages_text)));
                if ($lev_distance == 0) {
                    // closest word is this one (exact match)
                    $closest_intent = $intent;
                    $exact_match = true;
                    // break out of the loop; we've found an exact match
                    break;
                }
                // if this distance is less than the next found shortest
                // distance, OR if a next shortest word has not yet been found
                if ($lev_distance <= $shortest_distance || $shortest_distance < 0) {
                    // set the closest match, and shortest distance
                    $lev_distance_percentage = $this->calculateLevenshteinSimilarity($msg, $intent->user_messages_text, $lev_distance);
                    if($lev_distance_percentage > 70){
                        $closest_intent = $intent;
                        $shortest_distance = $lev_distance;
                    }
                }
            }
            if($closest_intent){
                $result[] = $closest_intent;
                return ['exact_match' => $exact_match, 'intents' => $result];
            }
        // }
        // Identify the top story ID and max score
        $max_score = isset($intents[0]->score) ? round($intents[0]->score, 2) : 0;
        $top_intents = [];
        foreach ($intents as $intent) {
            $score = round($intent->score, 2);
            // get multiple stories with intents having same score
            if ($score == $max_score) {
                $top_intents[] = $intent;
            }
        }
        $unique_combinations = [];
        $result = [];
        // get multiple stories with intents having same score with unique intent group
        foreach ($top_intents as $intent) {
            $key = $intent->story_id . '-' . $intent->group_id;
            if (!isset($unique_combinations[$key])) {
                $unique_combinations[$key] = true;
                $lev_distance_percentage = $this->calculateLevenshteinSimilarity($msg, $intent->user_messages_text, $lev_distance);
                if($lev_distance_percentage > 30){
                    $result[] = $intent;
                }
            }
        }
        // $final_inents = empty($result) && isset($intents[0]) ? [$intents[0]] : $result;
        // above line set first record, which may inrelevant.        
        $final_inents = $result;
        return ['exact_match' => $exact_match, 'intents' => $final_inents];
    }

    function getSuitableIntentFromMultipleIntents($intentGroups) {
        if (empty($intentGroups)) {
            return false;
        }

        // Initialize top search result details
        $topIntent = $intentGroups[0];
        $id = $topIntent->id;
        $userMessage = $topIntent->user_messages_text;
        $storyId = $topIntent->story_id;
        $score = $topIntent->score;

        // Track similar messages and scores
        $sameMessageGroups = [];
        $sameScoreGroups = [];

        foreach ($intentGroups as $intent) {
            if ($intent->user_messages_text === $userMessage) {
                $sameMessageGroups[] = $intent->group_id;
            }
            if ($intent->score === $score) {
                $sameScoreGroups[] = $intent->group_id;
            }
        }

        // Determine applicable group IDs
        $groupIds = [];
        if (count($sameMessageGroups) > 1) {
            $groupIds = $sameMessageGroups;
        } elseif (count($sameScoreGroups) > 1) {
            $groupIds = $sameScoreGroups;
        }

        if (!empty($groupIds)) {
            // Fetch the intent with the lowest rank from the matching groups
            $query = "SELECT ranking, intent_id AS group_id 
                      FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` 
                      WHERE `intent_id` IN (" . implode(',', array_map('esc_sql', $groupIds)) . ") 
                      AND `story_id` = " . esc_sql($storyId) . " 
                      ORDER BY ranking ASC LIMIT 1;";
            $nextRanking = geekybotdb::GEEKYBOT_get_row($query);

            if (!empty($nextRanking->group_id)) {
                foreach ($intentGroups as $intent) {
                    if ($intent->group_id == $nextRanking->group_id) {
                        return ['id' => $intent->id, 'score' => $intent->score];
                    }
                }
            }
        }

        // Fallback to the top intent
        return ['id' => $id, 'score' => $score];
    }

    function getSuitableIntentFromMultipleIntentsUsingStack($stackStory, $storiesData) {
        if (empty($storiesData)) {
            return false;
        }
        
        // Initialize top search result details
        $topIntent = $storiesData[0];
        $id = $topIntent->id;
        $userMessage = $topIntent->user_messages_text;
        $storyId = $topIntent->story_id;
        $score = $topIntent->score;

        // Track similar messages and scores
        $sameMessageGroups = [];
        $sameScoreGroups = [];
        
        foreach ($storiesData as $storieData) {
            if ($storieData->user_messages_text === $userMessage) {
                $sameMessageGroups[] = $storieData->group_id;
            }
            if ($storieData->score === $storiesData[0]->score) {
                $sameScoreGroups[] = $storieData->group_id;
            }
        }
        if (count($sameMessageGroups) > 1) {
            $intentIdAccordingToRank = $this->getIntentIdAccordingToRank($storyId, $stackStory, $sameMessageGroups, $storiesData);
        } elseif (count($sameScoreGroups) > 1) {
            $intentIdAccordingToRank = $this->getIntentIdAccordingToRank($storyId, $stackStory, $sameScoreGroups, $storiesData);
        }
        if (!empty($intentIdAccordingToRank)) {
            $id = $intentIdAccordingToRank;
        }

        // Fallback to the top intent
        return ['id' => $id, 'score' => $score];

    }

    function getIntentIdAccordingToRank($storyId, $stackStory, $intentIds, $storiesData) {
        // find the rank of stack story
        $query = "SELECT ranking FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` = ".esc_sql($stackStory->intent_id)." AND `story_id` = ".esc_sql($stackStory->story_id);
        $stackRanking = geekybotdb::GEEKYBOT_get_var($query);
        // get next rank to the stack intent
        $query = "SELECT ranking, intent_id AS group_id FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` IN (" . implode(',', array_map('esc_sql', $intentIds)) . ") AND `ranking` > ".esc_sql($stackRanking)." AND `story_id` = ".esc_sql($storyId);
        $query .= " ORDER BY ranking  ASC LIMIT 1;";
        $nextRanking = geekybotdb::GEEKYBOT_get_row($query);
        // if the next rank not found
        if (!isset($nextRanking->group_id)) {
            // get message with lowest rank from all user message
            $query = "SELECT ranking, intent_id AS group_id FROM `" . geekybot::$_db->prefix . "geekybot_intents_ranking` WHERE `intent_id` IN (" . implode(',', array_map('esc_sql', $intentIds)) . ") AND `story_id` = ".esc_sql($storyId);
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
            // disable nonce
            // die( 'Security check Failed' );
        }
        // get last 2 stories from the stack
        $chat_id = GEEKYBOTrequest::GEEKYBOT_getVar('chat_id');
        $query = "SELECT distinct(story_id) FROM `" . geekybot::$_db->prefix . "geekybot_stack` where chat_id = '".esc_sql($chat_id)."'";
        $query .= " ORDER BY id  DESC LIMIT 2;";
        $stackData = geekybotdb::GEEKYBOT_get_results($query);
        foreach ($stackData as $key => $value) {
            // get fallback from the stack story
            $query = "SELECT default_fallback, default_fallback_buttons FROM `" . geekybot::$_db->prefix . "geekybot_stories` where id = ".esc_sql($value->story_id);
            $fallbackMessage = geekybotdb::GEEKYBOT_get_row($query);
            if (!empty($fallbackMessage->default_fallback)) {
                $buttons = [];
                if (!empty($fallbackMessage->default_fallback_buttons) && $fallbackMessage->default_fallback_buttons != '[]') {
                    $fallbackButtons = json_decode($fallbackMessage->default_fallback_buttons);
                    foreach ($fallbackButtons as $fallbackButton) {
                        $buttons[] = [
                            "text" => $fallbackButton->text,
                            "type" => $fallbackButton->type,
                            "value" => $fallbackButton->value
                        ];
                    }
                }
                $retVal = ["text" => $fallbackMessage->default_fallback, "buttons" => $buttons];
                
                $botFallBack = '<section class="geekybot-message-text">';
                $botFallBack .= $fallbackMessage->default_fallback;
                $botFallBack .= '</section>';

                // Add buttons to the response
                if (!empty($buttons)) {
                    // Convert each sub-array to an object
                    $botButtons = array_map(function($item) {
                        return (object) $item;
                    }, $buttons);
                    $botFallBack .= "<div class='geekybot-message-button'>";
                    foreach ($botButtons as $fbButton) {
                        if ($fbButton->type == 1) {
                            $botFallBack .= "<li class='geekybot-message geekybot-message-button'>";
                            $botFallBack .= "<section><button class='wp-chat-btn' onclick='sendbtnrsponse(this);' value='".$fbButton->value."'>";
                            $botFallBack .= "<span>" . esc_html($fbButton->text) . "</span></button></section></li>";
                        } elseif ($fbButton->type == 2) {
                            $botFallBack .= "<li class='geekybot-message geekybot-message-button'>";
                            $botFallBack .= "<section><button class='wp-chat-btn'><span><a class='wp-chat-btn-link' href='".$fbButton->value."'>";
                            $botFallBack .= esc_html($fbButton->text) . "</a></span></button></section></li>";
                        }
                    }
                    $botFallBack .= "</div>";
                }
                
                geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($botFallBack, 'bot', 1);
                GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer(geekybotphplib::GEEKYBOT_htmlentities($botFallBack), 'bot');
                return wp_json_encode($retVal);
            }
        }
        // new get fallback from the configurations
        $buttons = [];
        $configMsg = geekybot::$_configuration['default_message'];
        if (isset($configMsg) && $configMsg != '' ) {
            $fallbackMessage = geekybot::$_configuration['default_message'];
            $default_message_buttons = geekybot::$_configuration['default_message_buttons'];
            if (!empty($default_message_buttons) && $default_message_buttons != '[]') {
                $fallbackButtons = json_decode($default_message_buttons);
                foreach ($fallbackButtons as $fallbackButton) {
                    $buttons[] = [
                        "text" => $fallbackButton->text,
                        "type" => $fallbackButton->type,
                        "value" => $fallbackButton->value
                    ];
                }
            }
        } else {
            $fallbackMessage =  __("Hi, I am Chatbot. I do not have specific knowledge.", "geeky-bot");
        }
        $retVal = ["text" => $fallbackMessage, "buttons" => $buttons];

        $botFallBack = '<section class="geekybot-message-text">';
        $botFallBack .= $fallbackMessage;
        $botFallBack .= '</section>';
        // Add buttons to the response
        if (!empty($buttons)) {
            // Convert each sub-array to an object
            $botButtons = array_map(function($item) {
                return (object) $item;
            }, $buttons);
            $botFallBack .= "<div class='geekybot-message-button'>";
            foreach ($botButtons as $fbButton) {
                if ($fbButton->type == 1) {
                    $botFallBack .= "<li class='geekybot-message geekybot-message-button'>";
                    $botFallBack .= "<section><button class='wp-chat-btn' onclick='sendbtnrsponse(this);' value='".$fbButton->value."'>";
                    $botFallBack .= "<span>" . esc_html($fbButton->text) . "</span></button></section></li>";
                } elseif ($fbButton->type == 2) {
                    $botFallBack .= "<li class='geekybot-message geekybot-message-button'>";
                    $botFallBack .= "<section><button class='wp-chat-btn'><span><a class='wp-chat-btn-link' href='".$fbButton->value."'>";
                    $botFallBack .= esc_html($fbButton->text) . "</a></span></button></section></li>";
                }
            }
            $botFallBack .= "</div>";
        }
        if (!empty($buttons)) {
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($botFallBack, 'bot', 1);
        } else {
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($botFallBack, 'bot');
        }
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer(geekybotphplib::GEEKYBOT_htmlentities($botFallBack), 'bot');
        return wp_json_encode($retVal);
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
                $buttons = [];
                // In case of buttons in response
                if (!empty($data->response_button) && $data->response_button != '[]') {
                    $responseButtons = json_decode($data->response_button);
                    foreach ($responseButtons as $responseButton) {
                        $buttons[] = [
                            "text" => $responseButton->text,
                            "type" => $responseButton->type,
                            "value" => $responseButton->value
                        ];
                    }
                    $retVal[] = ["bot_response" => $data->bot_response, "buttons" => $buttons];
                } else {
                    $retVal[] = $data->bot_response;
                }
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

    function calculateLevenshteinSimilarity($msg, $user_messages_text, $lev_distance) {

        // If either message is longer than 255 characters, return 0%
        if (strlen($msg) > 255 || strlen($user_messages_text) > 255) {
            return 0.0;
        }

        $msg_len = strlen($msg);    
        $user_messages_text_len = strlen($user_messages_text);    

        $larger_len = ($msg_len > $user_messages_text_len) ? $msg_len : $user_messages_text_len;

        if ($larger_len === 0) {
            return 100.0;
        }

        $lev_distance_percentage = 100 - (($lev_distance / $larger_len) * 100);

        return round($lev_distance_percentage, 2);
    }
}
?>
