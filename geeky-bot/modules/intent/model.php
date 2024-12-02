<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTintentModel {

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

    function getMessagekey(){
        $key = 'intent';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }
    // new

    function saveUserInputAjax(){
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-intent') ) {
            die( 'Security check Failed' ); 
        }
        $data['group_id'] = GEEKYBOTrequest::GEEKYBOT_getVar('group_id');
        if (is_null($data['group_id']) || !isset($data['group_id']) || $data['group_id'] == '' || $data['group_id'] == 'NaN') {
            $data['group_id'] = $this->generateNewGroupId();
        }
        $data['user_messages'] = GEEKYBOTrequest::GEEKYBOT_getVar('user_messages');
        // store data in table
        $intentIds = $this->storeUserInput($data);

        $commaSeparatedIntentIds = implode("','", $intentIds);
        $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_intents` WHERE group_id = '".esc_sql($data['group_id'])."' AND id  NOT IN ('".$commaSeparatedIntentIds."')";
        geekybotdb::query($query);
        return $data['group_id'];
    }

    function saveAutoBuildUserInput($data){
        $nonce = $data['_wpnonce'];
        if (! wp_verify_nonce( $nonce, 'save-intent') ) {
            die( 'Security check Failed' ); 
        }
        $data['group_id'] = $this->generateNewGroupId();
        // store data in table
        $intentIds = $this->storeUserInput($data);
        return $data['group_id'];
    }

    function storeUserInput($data){
        if (empty($data))
            return false;

        $row = GEEKYBOTincluder::GEEKYBOT_getTable('intent');
        $cols = array();
        $data = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->stripslashesFull($data);// remove slashes with quotes
        $intentIds = [];
        foreach ($data['user_messages'] as $messages) {
            $usermessages = '';
            $usermessagestext = '';
            if($messages['message'] != '') {
                $usermessages = $messages['message'];
                $variables = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getVariables($messages['message']);
                $user_variables_data = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getVariablesValuesFromUserMessage($messages['message'], $variables);
                if (!empty($user_variables_data)) {
                    foreach ($user_variables_data as $slot_name => $slot_value) {
                        $messages['message'] = geekybotphplib::GEEKYBOT_str_replace('['.$slot_value.']('.$slot_name.')', $slot_value, $messages['message']);
                    }
                    $usermessagestext .= $messages['message'].' ';
                } else {
                    $usermessagestext .= $messages['message'].' ';
                }
                // 
                $cols = array();
                $cols['id'] = "";
                if (isset($messages['id']) && $messages['id'] != '') {
                    $cols['id'] = $messages['id'];
                }
                $cols['user_messages'] = $usermessages;
                $cols['user_messages_text'] = $usermessagestext;
                $cols['group_id'] = $data['group_id'];
                $cols['created'] = current_time('mysql');
                
                if (!$row->bind($cols)) {
                    $err = geekybot::$_db->last_error;
                    $error[] = $err;
                }
                if (!$row->store()) {
                    $err = geekybot::$_db->last_error;
                    $error[] = $err;
                }
                $intentIds[] = $row->id;
                update_option( 'intent_story_notification', 'yes' );
            }
        }
        return $intentIds;
    }

    function generateNewGroupId(){
        $query = "SELECT MAX(`group_id`) FROM `" . geekybot::$_db->prefix . "geekybot_intents`";
        $group_id = geekybotdb::GEEKYBOT_get_var($query);
        $group_id++;
        return $group_id;
    }

}
?>
