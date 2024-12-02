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
        $layout = GEEKYBOTrequest::GEEKYBOT_getLayout('geekybotlt', 'woocommerce', null);
        if (self::canaddfile()) {
            switch ($layout) {
                case 'admin_woocommerce':
                    
                    break;
                default:
                    exit;
            }
            $module = (is_admin()) ? 'page' : 'geekybotme';
            $module = GEEKYBOTrequest::GEEKYBOT_getVar($module, null, 'woocommerce');
            $module = geekybotphplib::GEEKYBOT_str_replace('geekybot_', '', $module);
            GEEKYBOTincluder::GEEKYBOT_include_file($layout, $module);
        }
    }


}

$GEEKYBOTWoocommerceController = new GEEKYBOTWoocommerceController();
?>
