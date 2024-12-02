<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTIntentgroupTable extends GEEKYBOTtable {

    public $id = '';
    public $name = '';
    public $description = '';


    function __construct() {
        parent::__construct('intentgroups', 'id'); // tablename, primarykey
    }

}

?>