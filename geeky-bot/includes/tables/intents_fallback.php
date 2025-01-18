<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTintents_fallbackTable extends GEEKYBOTtable {

    public $id = '';
    public $group_id= '';
    public $story_id= '';
    public $default_fallback = '';
    public $default_fallback_buttons = '';


    function __construct() {
        parent::__construct('intents_fallback', 'id'); // tablename, primarykey
    }

}

?>
