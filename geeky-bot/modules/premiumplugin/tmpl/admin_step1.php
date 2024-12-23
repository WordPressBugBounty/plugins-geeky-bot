<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if(isset($_SESSION['ms_addon_install_data'])){
    unset($_SESSION['ms_addon_install_data']);
} ?>
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
                    <div id="geekybotadmin_black_wrapper_built_loading" style="display: none;" ></div>
                    <div class="geekybotadmin-built-story-loading" id="geekybotadmin_built_loading" style="display: none;" >
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/spinning-wheel.gif" />
                        <div class="geekybotadmin-built-story-loading-text">
                            <?php echo esc_html(__('Please wait a moment; this may take some time.','geeky-bot')); ?>
                        </div>
                    </div>
                    <div id="geekybot-lower-wrapper">
                        <div class="geekybot-addon-installer-wrapper" >
                            <form id="mjsupportfrom" action="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_premiumplugin&task=verifytransactionkey&action=geekybottask'),"verify-transaction-key")); ?>" method="post">
                                <div class="geekybot-addon-installer-section-wrap" >
                                <img alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/addon-images/main-logo.png" />
                                    <div class="geekybot-addon-installer-heading" >
                                        <?php echo esc_html(__("Please Insert Your Activation Key",'geeky-bot')); ?>
                                    </div>
                                    <div class="geekybot-addon-installer-key-section" >

                                        <?php
                                        $error_message = '';
                                        $transactionkey = '';
                                        if(isset($_COOKIE['ms_addon_return_data'])){
                                            $ms_addon_return_data = json_decode(geekybotphplib::GEEKYBOT_safe_decoding(geekybot::GEEKYBOT_sanitizeData($_COOKIE['ms_addon_return_data'])),true);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                                            $ms_error_msg = $ms_addon_return_data;
                                            if(isset($ms_addon_return_data['status']) && $ms_addon_return_data['status'] == 0){
                                                $error_message = $ms_addon_return_data['message'];
                                                $transactionkey = $ms_addon_return_data['transactionkey'];
                                            }
                                            unset($ms_addon_return_data);
                                        }
                                        ?>
                                        <div class="geekybot-addon-installer-key-field" >
                                             <?php if($error_message != '' ){ ?>
                                                <div class="geekybot-addon-installer-key-field-message" > <?php echo wp_kses_post($error_message);?></div>
                                            <?php } ?>
                                            <input type="text" name="transactionkey" id="transactionkey" class="geekybot_key_field" value="<?php echo esc_attr($transactionkey);?>" placeholder="<?php echo esc_html(__('XXXX-XXXX-XXXXX-XXXXX','geeky-bot')); ?>"/>
                
                                        
                                        <div class="geekybot-addon-installer-key-button" >
                                            <button type="submit" class="geekybot_btn" role="submit" onclick="jsShowLoading();"><?php echo esc_html(__("Proceed",'geeky-bot')); ?></button>
                                        </div>
                                       
                                            </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
$geekybot_js ="
    jQuery(document).ready(function(){
        jQuery('#mjsupportfrom').on('submit', function() {
            jsShowLoading();
        });
    });

    function jsShowLoading(){
        jQuery('div#black_wrapper_translation').show();
        jQuery('div#mstran_loading').show();
    }

";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>  
