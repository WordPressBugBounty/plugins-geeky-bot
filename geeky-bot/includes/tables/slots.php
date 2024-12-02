<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTSlotsTable extends GEEKYBOTtable {

    public $id = '';
    public $name = '';
    public $type = '';
    public $possible_values = '';
    public $variable_for = '';


    function __construct() {
        parent::__construct('slots', 'id'); // tablename, primarykey
    }

}

?>