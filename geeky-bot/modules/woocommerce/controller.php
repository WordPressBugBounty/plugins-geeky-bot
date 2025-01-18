<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTWoocommerceController {

    private $_msgkey;

    function __construct() {
        self::handleRequest();
        $this->_msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('woocommerce')->getMessagekey();
    }

    function handleRequest() {
        
    }

    function synchronizeWooCommerceProducts() {
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'synchronize-data') ) {
            die( 'Security check Failed' );
        }
        update_option('geekybot_woocommerce_synchronization_flag', 1);
    }


}

$GEEKYBOTWoocommerceController = new GEEKYBOTWoocommerceController();
?>
