<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTStoriesController {
    private $_msgkey;
    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getMessagekey();
        $stories = GEEKYBOTincluder::GEEKYBOT_getModel('stories');
    }

    function handleRequest() {
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'stories', null);
        $uid = GEEKYBOTincluder::GEEKYBOT_getObjectClass('user')->geekybot_uid();
        $stories = GEEKYBOTincluder::GEEKYBOT_getModel('stories');
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_stories':
                    $task = GEEKYBOTrequest::GEEKYBOT_getVar('task');
                    $id = GEEKYBOTrequest::GEEKYBOT_getVar('storyid');
                    GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getAllStories();
                    break;
                case 'admin_formstory':
                    $task = GEEKYBOTrequest::GEEKYBOT_getVar('task');
                    $id = GEEKYBOTrequest::GEEKYBOT_getVar('storyid');
                    GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getStory($id);
                    break;
                default:
                    exit;
            }
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'stories');
            $module = geekybotphplib::GEEKYBOT_str_replace('geekybot_', '', $module);
            GEEKYBOTincluder::GEEKYBOT_include_file($layout, $module);
        }
    }

    function canaddfile() {
        $nonce_value = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot_nonce');
        if ( wp_verify_nonce( $nonce_value, 'geekybot_nonce') ) {
            if (isset($_POST['form_request']) && $_POST['form_request'] == 'geekybot')
                return false;
            elseif (isset($_GET['action']) && $_GET['action'] == 'geekybottask')
                return false;
            else
                return true;
        }
    }

    function removeStory() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $id = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot-cb');
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'delete-story-'.$id) ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        if (!isset($data['callfrom']) || $data['callfrom'] == null) {
            $data['callfrom'] = $callfrom = GEEKYBOTrequest::GEEKYBOT_getVar('callfrom');
        }
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->deleteStory($id);
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'story');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        $url = admin_url("admin.php?page=geekybot_stories&geekybotlt=stories");
        wp_redirect($url);
        die();
    }

    function changeStatus() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $storyid = GEEKYBOTrequest::GEEKYBOT_getVar('storyid');
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'change-status-'.$storyid) ) {
            die( 'Security check Failed' );
        }
        $status = GEEKYBOTrequest::GEEKYBOT_getVar('status');
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->changeStatus($status, $storyid);
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'story');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        $url = admin_url("admin.php?page=geekybot_stories&geekybotlt=stories");
        wp_redirect($url);
        die();
    }

    function savestories(){
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'save-story') ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        $stories = GEEKYBOTincluder::GEEKYBOT_getModel('stories');
        $result = $stories->updateStoryForm($data);
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'story');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->_msgkey);
        $url = admin_url("admin.php?page=geekybot_stories&geekybotlt=formstory&storyid=".$data['storyid']);
        wp_redirect($url);
        die();
    }

    function geekybotExportStoryToXML() {
        if (!current_user_can('manage_options')){
            die('Only Administrators can perform this action.');
        }
        $id = GEEKYBOTrequest::GEEKYBOT_getVar('geekybot-storyid');
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'export-story-'.$id) ) {
            die( 'Security check Failed' );
        }
        $data = GEEKYBOTrequest::GEEKYBOT_get('post');
        if (!isset($data['callfrom']) || $data['callfrom'] == null) {
            $data['callfrom'] = $callfrom = GEEKYBOTrequest::GEEKYBOT_getVar('callfrom');
        }
        $result = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->geekybotExportStoryToXML($id);
        
        $url = admin_url("admin.php?page=geekybot_stories&geekybotlt=stories");
        wp_redirect($url);
        die();
    }
}

$GEEKYBOTStoriesController = new GEEKYBOTStoriesController();
?>

