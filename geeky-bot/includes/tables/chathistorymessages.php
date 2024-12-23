<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTChathistorymessagesTable extends GEEKYBOTtable {

    public $id = '';
    public $response_id= '';
    public $intent_id= '';
    public $subject = '';
    public $message = '';
    public $sender = '';
    public $confidence = '';
    public $type = '';
    public $post_type = '';
    public $buttons = '';
    public $created = '';
    public $session_id = '';



    function __construct() {
        parent::__construct('chat_history_messages', 'id'); // tablename, primarykey
    }

}

?>
