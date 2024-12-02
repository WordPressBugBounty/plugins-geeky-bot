<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTPosttypesTable extends GEEKYBOTtable {

    public $id = '';
    public $post_type = '';
    public $post_label = '';
    public $plugin_name = '';
    public $status = '';



    function __construct() {
        parent::__construct('post_types', 'id'); // tablename, primarykey
    }

}

?>
