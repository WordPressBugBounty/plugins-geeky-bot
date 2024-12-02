<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTActionTable extends GEEKYBOTtable {

    public $id = '';
    public $function_name = '';
    public $parameters = '';


    function __construct() {
        parent::__construct('actions', 'id'); // tablename, primarykey
    }

}

?>