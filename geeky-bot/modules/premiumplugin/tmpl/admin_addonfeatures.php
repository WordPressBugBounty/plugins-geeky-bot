<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<?php GEEKYBOTMessages::GEEKYBOT_getMessage(); ?>
<div id="geekybotadmin-wrapper" class="msadmin-add-on-page-wrapper">
    <div id="geekybotadmin-leftmenu">
        <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
    </div>
    <div id="geekybotadmin-data">
        <?php GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getPageTitle('addonslist'); ?>
        <div id="geekybotadmin-data-wrp">
            
        </div>
    </div>
</div>
