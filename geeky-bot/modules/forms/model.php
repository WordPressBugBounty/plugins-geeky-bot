<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTformsModel {

    function storeForms($data) {
        if (empty($data))
            return false;

        $data['shortForms'] = geekybotphplib::GEEKYBOT_str_replace(' ', '-', $data['name']);
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('forms');
        if (!$row->bind($data)) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (!$row->store()) {
            return GEEKYBOT_SAVE_ERROR;
        }
        return GEEKYBOT_SAVED;
    }

    function getFormsbyId($id) {
        if (!is_numeric($id))
            return false;
        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_forms` WHERE id = " . esc_sql($id);
        geekybot::$_data[0] = geekybotdb::GEEKYBOT_get_row($query);
        geekybot::$_data['slotList'] = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getMultiSelectEdit();
        return;
    }

    function getAllForms() {
    $searchtitle = geekybot::$_search['forms']['searchtitle'];
      $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_forms` as forms" ;
         if ($searchtitle) {
            $query .= " WHERE forms.form_name LIKE '%$searchtitle%' ";
        }
        $query .= " ORDER BY forms.form_name ASC ";
        $rows = geekybotdb::GEEKYBOT_get_results($query);

        geekybot::$_data['filter']['searchtitle'] = $searchtitle;
        geekybot::$_data[0] = $rows;

        $query = "SELECT
                    ( SELECT COUNT(id) FROM `" . geekybot::$_db->prefix . "geekybot_forms` )";
                    $query .= " AS total ";

        $total = geekybotdb::GEEKYBOT_get_var($query);
        geekybot::$_data['total'] = $total;
        geekybot::$_data[1] = GEEKYBOTpagination::GEEKYBOT_getPagination($total);
        if ($total > 0)
            return false;
        else
            return true;
    }

    function getFormsForDropDown() {
        $query = "SELECT id,form_name FROM `" . geekybot::$_db->prefix . "geekybot_forms` as forms" ;
        $query .= " ORDER BY forms.form_name ASC ";
        $forms = geekybotdb::GEEKYBOT_get_results($query);
        // $formsData = array();
        // foreach ($forms as $key => $value) {
        //     $formsData[$value->id] = $value->form_name;
        // }
        return $forms;
    }

    function deleteForms($ids) {

        if (empty($ids))
            return false;
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('forms');
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

    function getFormsForCombobox() {
        $query = "SELECT id, form_name AS text FROM `" . geekybot::$_db->prefix . "geekybot_forms`";
        $list = geekybot::$_db->get_results($query);
        return $list;
    }

    function setSearchVariableForForms($geekybot_search_array,$search_userfields){
        geekybot::$_search['forms']['searchtitle'] = isset($geekybot_search_array['searchtitle']) ? $geekybot_search_array['searchtitle'] : '';
        geekybot::$_search['forms']['sorton'] = isset($geekybot_search_array['sorton']) ? $geekybot_search_array['sorton'] : 6;
        geekybot::$_search['forms']['sortby'] = isset($geekybot_search_array['sortby']) ? $geekybot_search_array['sortby'] : 2;
    }

    function getAdminFormsSearchData($search_userfields){
        $geekybot_search_array = array();
        $geekybot_search_array['searchtitle'] = GEEKYBOTrequest::GEEKYBOT_getVar('searchtitle');
        $geekybot_search_array['status'] = GEEKYBOTrequest::GEEKYBOT_getVar('status');
        $geekybot_search_array['sorton'] = GEEKYBOTrequest::GEEKYBOT_getVar('sorton' , 'post', 6);
        $geekybot_search_array['sortby'] = GEEKYBOTrequest::GEEKYBOT_getVar('sortby' , 'post', 2);
        $geekybot_search_array['search_from_intent'] = 1;
        return $geekybot_search_array;
    }
    
    function getFrontSideFormsSearchData($search_userfields){
        $geekybot_search_array = array();
        $geekybot_search_array['searchtitle'] = GEEKYBOTrequest::GEEKYBOT_getVar('searchtitle', 'post');
    }
    
    function getCookiesSavedSearchDataForms($search_userfields){
        $geekybot_search_array = array();
        $wpjp_search_cookie_data = '';
        if(isset($_COOKIE['geekybot_chatbot_search_data'])){
            $wpjp_search_cookie_data = $_COOKIE['geekybot_chatbot_search_data'];
            $wpjp_search_cookie_data = json_decode( geekybotphplib::GEEKYBOT_safe_decoding($wpjp_search_cookie_data) , true );
        }
        if($wpjp_search_cookie_data != '' && isset($wpjp_search_cookie_data['search_from_forms']) && $wpjp_search_cookie_data['search_from_forms'] == 1){
            $geekybot_search_array['searchtitle'] = $wpjp_search_cookie_data['searchtitle'];
            $geekybot_search_array['status'] = $wpjp_search_cookie_data['status'];
            $geekybot_search_array['description'] = $wpjp_search_cookie_data['description'];

        }
        return $geekybot_search_array;
    }

    function getMessagekey(){
        $key = 'forms';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }
    // new
    function saveCustomeFormAjax() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-form') ) {
            die( 'Security check Failed' ); 
        }
        $formData['id'] = GEEKYBOTrequest::GEEKYBOT_getVar('custome_form_id');
        $formData['form_name'] = GEEKYBOTrequest::GEEKYBOT_getVar('form_name');
        $variableData['name'] = GEEKYBOTrequest::GEEKYBOT_getVar('variable_name');
        $variableData['type'] = GEEKYBOTrequest::GEEKYBOT_getVar('variable_type');
        $variableData['possible_values'] = GEEKYBOTrequest::GEEKYBOT_getVar('variable_possible_values');
        // store variables
        $index = 0;
        $variables = '';
        foreach ($variableData['name'] as $key => $value) {
            $data['name'] = $value;
            $data['type'] = $variableData['type'][$key];
            $data['possible_values'] = $variableData['possible_values'][$key];
            $index++;
            $error = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->storeSlots($data);
            // if ($error == 0) {
                if ($variables != '') {
                    $variables .= ','.$data['name'];
                } else {
                    $variables .= $data['name'];
                }
            // }
        }
        $formData['variables'] = $variables;
        // store forms
        if (empty($formData))
            return false;

        $formData['shortForms'] = geekybotphplib::GEEKYBOT_str_replace(' ', '-', $formData['form_name']);
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('forms');
        if (!$row->bind($formData)) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (!$row->store()) {
            return GEEKYBOT_SAVE_ERROR;
        }
        return $row->id;
    }

    function updateFormsValueFormAjax(){
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'update-forms-value') ) {
            die( 'Security check Failed' ); 
        }
        $formsData = GEEKYBOTincluder::GEEKYBOT_getModel('forms')->getFormsForDropDown();
        $form_ids = GEEKYBOTrequest::GEEKYBOT_getVar('form_ids');
        $valuearray = explode(", ", $form_ids);
        $i = 0;
        $html = '';
        foreach ($formsData AS $form) {
            $check = '';
            if(in_array($form->id, $valuearray)){
                $check = 'checked';
            }
            $html .= '<input type="checkbox"'. esc_attr($check).' class="radiobutton js-ticket-append-radio-btn " value="'.esc_attr($form->id).'" id="'. esc_attr($form->id).'_'.esc_attr($i) .'" name="story[form_ids][]">
            <label for="story[form_ids][]" id="foruf_checkbox1">
                '.esc_html($form->form_name).'
            </label>';
            
            $i++;
        }
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }


}

?>
