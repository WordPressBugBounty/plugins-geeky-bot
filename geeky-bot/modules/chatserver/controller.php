<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTChatserverController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->getMessagekey();
        $chathistory = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory');
    }

    function handleRequest() {

    }
}

$GEEKYBOTChatserverController = new GEEKYBOTChatserverController();
?>

