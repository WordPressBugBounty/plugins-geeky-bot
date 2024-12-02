<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTdeactivation {

    static function GEEKYBOT_deactivate() {
        $id = geekybot::getPageid();
        geekybot::$_db->get_var("UPDATE `" . geekybot::$_db->prefix . "posts` SET post_status = 'draft' WHERE ID = $id");

        //Delete capabilities
        $role = get_role( 'administrator' );
        $role->remove_cap( 'geekybot' );

    }

    static function GEEKYBOT_tables_to_drop() {
        global $wpdb;
        $tables = array(
            $wpdb->prefix."geekybot_config",
            $wpdb->prefix."geekybot_intents",
            $wpdb->prefix."geekybot_intents_ranking",
            $wpdb->prefix."geekybot_slots",
            $wpdb->prefix."geekybot_actions",
            $wpdb->prefix."geekybot_chat_history_sessions",
            $wpdb->prefix."geekybot_chat_history_messages",
            $wpdb->prefix."geekybot_stories",
            $wpdb->prefix."geekybot_active_chat",
            $wpdb->prefix."geekybot_forms",
            $wpdb->prefix."geekybot_session",
            $wpdb->prefix."geekybot_sessiondata",
            $wpdb->prefix."geekybot_responses",
            $wpdb->prefix."geekybot_stack",
            $wpdb->prefix."geekybot_posts",
            $wpdb->prefix."geekybot_products",
            $wpdb->prefix."geekybot_post_types",
        );
        return $tables;
    }

}

?>
