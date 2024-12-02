<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTStackController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('stack')->getMessagekey();
    }

    function handleRequest() {

    }
}

$GEEKYBOTStackController = new GEEKYBOTStackController();
?>

