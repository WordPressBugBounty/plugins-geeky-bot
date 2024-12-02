<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTformsTable extends GEEKYBOTtable {

    public $id = '';
    public $form_name = '';
    public $variables = '';


    function __construct() {
        parent::__construct('forms', 'id'); // tablename, primarykey
    }

}

?>