<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTChathistorysessionsTable extends GEEKYBOTtable {

    public $id = '';
    public $user_id = '';
    public $chat_id= '';
    public $created = '';



    function __construct() {
        parent::__construct('chat_history_sessions', 'id'); // tablename, primarykey
    }

}

?>