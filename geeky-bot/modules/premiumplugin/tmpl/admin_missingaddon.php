<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="geekybotadmin-wrapper">
    <div id="geekybotadmin-leftmenu">
        <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
    </div>
    <div id="geekybotadmin-data">
        <?php GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getPageTitle('missingaddon'); ?>
        <div id="geekybotadmin-data-wrp">
            <div id="majesticsupport-content">
                <h1 class="geekybot-missing-addon-message" >
                    <?php
                    $addon_name = GEEKYBOTrequest::GEEKYBOT_getVar('page');
                    echo esc_html(geekybotphplib::GEEKYBOT_ucfirst($addon_name)).'&nbsp;';
                    echo esc_html(__('addon in no longer active','geeky-bot')).'!';
                    ?>

                </h1>
            </div>
        </div>
    </div>
</div>
