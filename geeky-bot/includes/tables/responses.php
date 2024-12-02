<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTresponsesTable extends GEEKYBOTtable {

    public $id = '';
    public $name = '';
    public $response_type = '';
    public $bot_response = '';
    public $response_button = '';
    public $form_id = '';
    public $action_id = '';
    public $function_id = '';
    public $story_id = '';
    public $created = '';


    function __construct() {
        parent::__construct('responses', 'id'); // tablename, primarykey
    }

}

?>