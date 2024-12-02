<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTintents_rankingTable extends GEEKYBOTtable {

    public $id = '';
    public $story_id= '';
    public $intent_id= '';
    public $ranking = '';
    public $intent_index = '';


    function __construct() {
        parent::__construct('intents_ranking', 'id'); // tablename, primarykey
    }

}

?>
