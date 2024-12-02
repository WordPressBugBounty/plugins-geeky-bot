<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTsystemactionController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        //recheck it
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('systemaction')->getMessagekey();
    }

    function handleRequest() {

    }

}

$GEEKYBOTsystemactionController = new GEEKYBOTsystemactionController();
?>

