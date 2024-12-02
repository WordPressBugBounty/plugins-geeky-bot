<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTIntentmessageTable extends GEEKYBOTtable {

    public $id = '';
    public $intent_id = '';
    public $user_message = '';


    function __construct() {
        parent::__construct('intent_user_messages', 'id'); // tablename, primarykey
    }

}

?>