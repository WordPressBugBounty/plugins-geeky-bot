<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTActivechatTable extends GEEKYBOTtable {

    public $id = '';
    public $chat_id= '';
    public $created = '';



    function __construct() {
        parent::__construct('active_chat', 'id'); // tablename, primarykey
    }

}

?>