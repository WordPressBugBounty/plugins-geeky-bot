<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTconfigTable extends GEEKYBOTtable {

    public $configname = '';
    public $configvalue = '';
    public $configfor = '';

    function __construct() {
        parent::__construct('config', 'configname'); // tablename, primarykey
    }

}

?>