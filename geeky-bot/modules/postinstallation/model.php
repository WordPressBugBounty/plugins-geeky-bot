<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTpostinstallationModel {

    function updateInstallationStatusConfiguration(){
        $flag = get_option('geekybot_post_installation');
        if($flag == false){
            add_option( 'geekybot_post_installation', '1', '', 'yes' );
        }else{
            update_option( 'geekybot_post_installation', '1');
        }
        return;
    }

	function storeInstallationData($data){
        if (empty($data)){
            return false;
        }
        if (isset($data['story_type'])) {
            $query = "SELECT count(id) FROM `" . geekybot::$_db->prefix . "geekybot_stories` where story_type = ".esc_sql($data['step']);
            $storyCount = geekybotdb::GEEKYBOT_get_var($query);
        }
        if ($data['step'] == 1) {
            if (isset($data['ai_story']) && $data['ai_story'] == 1 && $storyCount == 0) {
                $result = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->geekybotBuildAIStoryFromTemplate($data['template']);
            } elseif (isset($data['ai_story']) && $data['ai_story'] == 1 && $storyCount != 0) {
                // case when the story already exist 
                // do nothing
            } else {
                GEEKYBOTincluder::GEEKYBOT_getModel('stories')->deleteStoryByType($data['story_type']);
            }
        } elseif ($data['step'] == 2) {
            if (isset($data['woo_story']) && $data['woo_story'] == 1 && $storyCount == 0) {
                $result = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->geekybotBuildWooCommerceStory("WooCommerce");
            } elseif (isset($data['woo_story']) && $data['woo_story'] == 1 && $storyCount != 0) {
                // case when the story already exist 
                // do nothing
            } else {
                GEEKYBOTincluder::GEEKYBOT_getModel('stories')->deleteStoryByType($data['story_type']);
            }
        } elseif ($data['step'] == 3) {
            if (isset($data['enable_new_post_type_serch']) && $data['enable_new_post_type_serch'] == 1) {
                GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotEnableDisableNewPostTypes(1, 0);
            } else {
                GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotEnableDisableNewPostTypes(0, 0);
            }
            if (isset($data['enable_web_serch']) && $data['enable_web_serch'] == 1) {
                update_option('geekybot_enable_websearch_flag', 1);
            } else {
                $result = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotDisableWebSearch(0);
            }
        }
        if (isset($result) && !is_numeric($result)) {
            return $result;
        }
        return;
    }

    function checkIfStoryAlreadyEnabled($story_type, $callfrom = ''){
        geekybot::$_data['geekybot_callfrom'] = $callfrom;
        if (isset($story_type)) {
            if ($story_type == 3) {
                if ( geekybot::$_configuration['is_posts_enable'] == 1 ) {
                    geekybot::$_data['storyAlreadyBuild'] = 1;
                }
            } else {
                $query = "SELECT count(id) FROM `" . geekybot::$_db->prefix . "geekybot_stories` where story_type = ".esc_sql($story_type);
                $storyCount = geekybotdb::GEEKYBOT_get_var($query);
                if ($storyCount > 0) {
                    geekybot::$_data['storyAlreadyBuild'] = 1;
                } 
            }
        }
    }

    function getMessagekey(){
        $key = 'postinstallation'; if(is_admin()){$key = 'admin_'.$key;}return $key;
    }

}?>
