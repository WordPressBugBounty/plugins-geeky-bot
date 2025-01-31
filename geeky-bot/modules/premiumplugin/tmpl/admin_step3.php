<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
    <div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
        <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'premiumplugin','layouts' => 'step1')); ?>
        <div class="geekybotadmin-body-main">
            <!-- left menu -->
            <div id="geekybotadmin-leftmenu-main">
               <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
            </div>
            <div id="geekybotadmin-data">
                <!-- top head -->
                <div id="geekybot-head">
                    <h1 class="geekybot-head-text">
                        <?php echo esc_html(__('Install Add-ons', 'geeky-bot')); ?>
                    </h1>
                </div>
                <!-- page content -->
                <div id="geekybot-admin-wrapper">
                    <div class="geekybot-addon-installer-wrapper" >
                        <div class="geekybot-addon-installer-section-wrap geekybot-addon-installer-section-wrap_step3">
                           <img alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/addon-images/main-logo.png" />
                                   <div class="geekybot-addon-installer-heading geekybot-addon-installer-heading_step3">
                                        <?php echo esc_html(__("Addons Installed Successfully",'geeky-bot')); ?>
                                    </div>
                                    <div class="geekybot-addon-installer-key-section" >
                                        <?php
                                        $error_message = '';
                                        $transactionkey = '';
                                        if(isset($_COOKIE['geekybot_addon_return_data'])){
                                            $geekybot_addon_return_data = json_decode(geekybotphplib::GEEKYBOT_safe_decoding(geekybot::GEEKYBOT_sanitizeData($_COOKIE['geekybot_addon_return_data'])),true);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                                            $ms_error_msg = $geekybot_addon_return_data;
                                            if(isset($geekybot_addon_return_data['status']) && $geekybot_addon_return_data['status'] == 0){
                                                $error_message = $geekybot_addon_return_data['message'];
                                                $transactionkey = $geekybot_addon_return_data['transactionkey'];
                                            }
                                            unset($geekybot_addon_return_data);
                                        }
                                        ?>
                                        <div class="geekybot-addon-installer-key-field" >
                                            <div class="geekybot-addon-installer-button geekybot-addon-installer-button_step3" >
                                            <a href="?page=geekybot" class="geekybot_btn geekybot-addon-installer-button_step3_dashboard_btn"><?php echo esc_html(__("Open Dashboard",'geeky-bot')); ?></a>
                                            <a href="<?php echo esc_url(admin_url('plugins.php')) ?>" class="geekybot_btn geekybot-addon-installer-button_step3_plugins_btn"><?php echo esc_html(__("Open Plugins Page",'geeky-bot')); ?></a>
                                        </div>
                                            <?php if($error_message != '' ){ ?>
                                                <div class="geekybot-addon-installer-key-field-message" > <?php echo wp_kses_post($error_message);?></div>
                                            <?php } ?>
                                    </div>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
