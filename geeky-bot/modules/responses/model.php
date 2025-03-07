<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTresponsesModel {
    function getMessagekey(){
        $key = 'responses';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

    function saveResponsesAjax() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-responses') ) {
            die( 'Security check Failed' ); 
        }
        $data['id'] = GEEKYBOTrequest::GEEKYBOT_getVar('id');
        $data['response_type'] = GEEKYBOTrequest::GEEKYBOT_getVar('response_type');
        if (empty($data))
            return false;
        if (!$data['id']) {
            $data['created'] = date_i18n('Y-m-d H:i:s');
        }
        if ($data['response_type'] == 1) {
            $data['form_id'] = '';
            $data['action_id'] = '';
            $data['bot_response'] = GEEKYBOTrequest::GEEKYBOT_getVar('bot_response');
            $data['btn_text'] = GEEKYBOTrequest::GEEKYBOT_getVar('btn_text');
            $data['btn_type'] = GEEKYBOTrequest::GEEKYBOT_getVar('btn_type');
            $data['btn_value'] = GEEKYBOTrequest::GEEKYBOT_getVar('btn_value');
            $data['btn_url'] = GEEKYBOTrequest::GEEKYBOT_getVar('btn_url');
            $data['function_id'] = '';
            $response_btn = [];
            if (is_array($data['btn_text']) && is_array($data['btn_type'])) {
                foreach ($data['btn_text'] as $index => $text) {
                    if (isset($data['btn_type'][$index]) && $text != '') {
                        $type = $data['btn_type'][$index];
                        if ($type == 1 && isset($data['btn_value'][$index]) && $data['btn_value'][$index] != '') {
                            $value = $data['btn_value'][$index];
                        } elseif ($type == 2 && isset($data['btn_url'][$index]) && $data['btn_url'][$index] != '') {
                            $value = $data['btn_url'][$index];
                        }
                        $response_btn[] = array(
                            'text' => $text,
                            'type' => $type,
                            'value' => $value
                        );
                    }
                }
            }
            $data['response_button'] = wp_json_encode($response_btn);
        } elseif ($data['response_type'] == 2) {
            $data['form_id'] = '';
            $data['action_id'] = GEEKYBOTrequest::GEEKYBOT_getVar('action_id');
            $data['bot_response'] = '';
            $data['function_id'] = '';
        } elseif ($data['response_type'] == 3) {
            $data['form_id'] = GEEKYBOTrequest::GEEKYBOT_getVar('form_id');
            $data['action_id'] = '';
            $data['bot_response'] = '';
            $data['function_id'] = '';
        } elseif ($data['response_type'] == 4) {
            $data['form_id'] = '';
            $data['action_id'] = '';
            $data['bot_response'] = '';
            $data['function_id'] = GEEKYBOTrequest::GEEKYBOT_getVar('function_id');
        }
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('responses');
        if (!$row->bind($data)) {
            return false;
        }
        if (!$row->store()) {
            return false;
        }
        return wp_json_encode($row->id);
    }
    function saveAutoBuildResponses($data) {
        if (empty($data))
            return false;
        if (!isset($data['id'])) {
            $data['created'] = date_i18n('Y-m-d H:i:s');
        }
        $data['form_id'] = '';
        $data['action_id'] = '';
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('responses');
        if (!$row->bind($data)) {
            return false;
        }
        if (!$row->store()) {
            return false;
        }
        return $row->id;
    }
}

?>
