<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTcustomer_botTable extends GEEKYBOTtable {

    public $id = '';
    public $orderid = '';
    public $uid = '';
    public $user_email = '';
    public $bot_status = '';
    public $customer_ip  = '';
    public $customer_address  = '';
    public $customer_token  = '';
    public $server_name  = '';
    public $server_ip  = '';
    public $created = '';
    public $status = '';


    function __construct() {
        parent::__construct('customer_bot', 'id'); // tablename, primarykey
    }

}

?>