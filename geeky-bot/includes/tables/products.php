<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTproductsTable extends GEEKYBOTtable {

    public $id = '';
    public $product_text = '';
    public $product_description = '';
    public $product_id = '';
    public $status = '';

    function __construct() {
        parent::__construct('products', 'id'); // tablename, primarykey
    }

}

?>