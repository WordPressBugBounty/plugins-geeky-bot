<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTStackTable extends GEEKYBOTtable {

    public $id = '';
    public $user_id = '';
    public $chat_id = '';
    public $intent_id = '';
    public $response_id = '';
    public $story_id = '';


    function __construct() {
        parent::__construct('stack', 'id'); // tablename, primarykey
    }

}

?>