<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTIntentTable extends GEEKYBOTtable {

    public $id = '';
    public $name = '';
    public $user_messages = '';
    public $user_messages_text = '';
    public $group_id = '';
    public $story_id = '';
    public $created = '';



    function __construct() {
        parent::__construct('intents', 'id'); // tablename, primarykey
    }

}

?>
