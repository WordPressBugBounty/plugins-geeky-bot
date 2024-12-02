<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTcontroller {

    function __construct() {
        self::handleRequest();
    }

    function handleRequest() {
        $module = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotme', null, 'geekybot');
       GEEKYBOTincluder::GEEKYBOT_include_file($module);
    }

}

$GEEKYBOTcontroller = new GEEKYBOTcontroller();
?>
