<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOThandlesearchcookies {
    public $_geekybot_search_array;
    public $_callfrom;
    public $_setcookies;

    function __construct( ) {
        $this->_geekybot_search_array = array();
        $this->_callfrom = 3;
        $this->_setcookies = false;
        $this->init();
    }

    function init(){
        $isadmin = is_admin();
        $geekybotlt = '';
        $page = GEEKYBOTrequest::GEEKYBOT_getVar('page');
        $geekybotlt = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotlt');
        $geekybotlay = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotlay');
        if($page != '' ){ // page is for admin case
            $geekybotlt = $page;
        }elseif($geekybotlt !=''){// for layouts
            $geekybotlt = $geekybotlt;
        }elseif($geekybotlay != ''){
            $geekybotlt = $geekybotlay;
        }

        $layoutname = geekybotphplib::GEEKYBOT_explode("geekybot_", $geekybotlt);
        if(isset($layoutname[1])){
            $geekybotlt = $layoutname[1];
        }
        $from_search = GEEKYBOTrequest::GEEKYBOT_getVar('GEEKYBOT_form_search');
        if(isset($from_search) && $from_search == 'GEEKYBOT_SEARCH'){
            $this->_callfrom = 1;
        }elseif(GEEKYBOTrequest::GEEKYBOT_getVar('pagenum', 'get', null) != null){
            $this->_callfrom = 2;
        }
        //die($geekybotlt);
        switch($geekybotlt){
            case 'chathistory':
                $this->geekybot_searchdataforChathistory();
            break;
            case 'slots':
                $this->geekybot_searchdataforSlots();
            break;
            case 'websearch':
                $this->geekybot_searchdataforWebSearch();
            break;
            case 'stories':
            case 'stories':
                $this->geekybot_searchdataforStories();
            break;
            default:
                geekybot::geekybot_removeusersearchcookies();
            break;
        }

        if($this->_setcookies){
            geekybot::setusersearchcookies($this->_setcookies,$this->_geekybot_search_array);
        }
    }

    private function geekybot_searchdataforChathistory(){
        $search_userfields = array();
        if($this->_callfrom == 1){
            if(is_admin()){
                $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->getAdminChathistorySearchData($search_userfields);
            }else{
                $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->getFrontSideChathistorySearchData($search_userfields);
            }
            $this->_setcookies = true;
        }elseif($this->_callfrom == 2){
            $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->getCookiesSavedSearchDataChathistory($search_userfields);
        }
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->setSearchVariableForChathistory($this->_geekybot_search_array,$search_userfields);

    }

    private function geekybot_searchdataforSlots(){
        $search_userfields = array();
        if($this->_callfrom == 1){
            if(is_admin()){
                $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getAdminSlotsSearchData($search_userfields);
            }
            $this->_setcookies = true;
        }elseif($this->_callfrom == 2){
            $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('slots')->getCookiesSavedSearchDataSlots($search_userfields);
        }
        GEEKYBOTincluder::GEEKYBOT_getModel('slots')->setSearchVariableForSlots($this->_geekybot_search_array,$search_userfields);

    }

    private function geekybot_searchdataforWebSearch(){
        $search_userfields = array();
        if($this->_callfrom == 1){
            if(is_admin()){
                $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->getAdminWebSearchSearchData($search_userfields);
            }
            $this->_setcookies = true;
        }elseif($this->_callfrom == 2){
            $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->getCookiesSavedSearchDataWebSearch($search_userfields);
        }
        GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->setSearchVariableForWebSearch($this->_geekybot_search_array,$search_userfields);

    }
    
    private function geekybot_searchdataforStories(){
        $search_userfields = array();
        if($this->_callfrom == 1){
            if(is_admin()){
                $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getAdminStoriesSearchData($search_userfields);
            }else{
                $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getFrontSideStoriesSearchData($search_userfields);
            }
            $this->_setcookies = true;
        }elseif($this->_callfrom == 2){
            $this->_geekybot_search_array = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getCookiesSavedSearchDataStories($search_userfields);
        }
        GEEKYBOTincluder::GEEKYBOT_getModel('stories')->setSearchVariableForStories($this->_geekybot_search_array,$search_userfields);
    }
}

?>
