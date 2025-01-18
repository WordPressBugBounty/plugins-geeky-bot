<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTStoryTable extends GEEKYBOTtable {

    public $id = '';
    public $name = '';
    public $intents_ordering = '';
    public $intent_ids = '';
    public $is_form = '';
    public $form_ids = '';
    public $story_mode = '';
    public $default_fallback = '';
    public $default_fallback_buttons = '';
    public $positions_array = '';
    public $story_type = '';
    public $status = '';



    function __construct() {
        parent::__construct('stories', 'id'); // tablename, primarykey
    }

}

?>
