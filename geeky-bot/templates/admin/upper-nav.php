<?php
/**
* @param  geeky-bot Optional
*Upper navigation
*/
if (!defined('ABSPATH'))
    die('Restricted Access');
?>
<div class="geekybotadmin-wrapper-inner">
    <div id="geekybotadmin-wrapper-left">
        <div class="geekybotadmin-navbar">
            <a title="<?php echo esc_html(__('Dashboard','geeky-bot')); ?>" href="<?php echo esc_url(admin_url("admin.php?page=geekybot")); ?>">
                <div class="geekybot-navbar-img">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/chatbot.png" />
                </div>
            </a>
        </div>
    </div>
    <?php
    if ($module && $layouts) {
        GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/breadcrumbs',array('module' => $module,'layouts' => $layouts));
    }
    ?>
    <div id="geekybotadmin-wrapper-right" class="geekybotadmin-right-sectionwrp">
        <div class="geekybotadmin-navbar-right">
            <div class="geekybot-navbar-img-right">
                <a class="geeky_hide geekybot-navebar-right-imprtbtn" href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_import','import'))?>" title="<?php echo esc_attr(__('import' , 'geeky-bot')); ?>">
                    <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('import' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/import.png'; ?>" />
                    <span class="geekybotadmin-text">
                        <?php echo esc_html(__('Import' , 'geeky-bot')); ?>
                    </span>
                </a>
                <a class="geeky_hide geekybot-navebar-right-exprtbtn" href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_export','export'))?>" title="<?php echo esc_attr(__('export' , 'geeky-bot')); ?>">
                    <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('export' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/export.png'; ?>" />
                    <span class="geeky_hide geekybotadmin-text">
                        <?php echo esc_html(__('Export' , 'geeky-bot')); ?>
                    </span>
                </a>
                <a class="geekybot-navebar-right-settingbtn" title="<?php echo esc_html(__('Settings','geeky-bot')); ?>" href="<?php echo esc_url(admin_url("admin.php?page=geekybot_configuration")); ?>">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/admin-left-menu/setting.png" />
                    <span class="geekybotadmin-text">
                            <?php echo esc_html(__('Settings' , 'geeky-bot')); ?>
                    </span>
                </a>
                <span class="geekybot-navebar-right-version" title="<?php echo esc_html(__('Version','geeky-bot')); ?>">
                    <?php echo esc_html(__('Version' , 'geeky-bot')).': '; ?><?php echo esc_html(GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('versioncode')); ?>
                </span>
            </div>
        </div>
    </div>
</div>
