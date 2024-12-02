<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTIntentresponseTable extends GEEKYBOTtable {

    public $id = '';
    public $intent_id = '';
    public $bot_response = '';


    function __construct() {
        parent::__construct('intent_bot_responses', 'id'); // tablename, primarykey
    }

}

?>