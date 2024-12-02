<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTpostsTable extends GEEKYBOTtable {

    public $id = '';
    public $title = '';
    public $content = '';
    public $post_text = '';
    public $post_id = '';
    public $post_type = '';
    public $status = '';

    function __construct() {
        parent::__construct('posts', 'id'); // tablename, primarykey
    }

}

?>
