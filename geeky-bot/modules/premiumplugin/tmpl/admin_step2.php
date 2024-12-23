<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$allPlugins = get_plugins(); // associative array of all installed plugins

$addon_array = array();
foreach ($allPlugins as $key => $value) {
    $addon_index = geekybotphplib::GEEKYBOT_explode('/', $key);
    $addon_array[] = $addon_index[0];
}
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
                    <div id="geekybotadmin_black_wrapper_built_loading" style="display: none;" ></div>
                    <div class="geekybotadmin-built-story-loading" id="geekybotadmin_built_loading" style="display: none;" >
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/spinning-wheel.gif" />
                        <div class="geekybotadmin-built-story-loading-text">
                            <?php echo esc_html(__('Please wait a moment; this may take some time.','geeky-bot')); ?>
                        </div>
                    </div>
                    <div id="geekybot-lower-wrapper">
                        <div class="geekybot-addon-installer-wrapper-addon-card">
                            <div class="geekybot-addon-installer-wrapper no_bg geekybot-addon-installer-wrapper-overall-wrapper" >
                            <form id="mjsupportfrom" action="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_premiumplugin&task=downloadandinstalladdons&action=geekybottask'),"download-and-install-addons")); ?>" method="post">
                                <div class="geekybot-addon-installer-section-wrap step2 geekybot-addon-installer-section-wrap-step2-no_bg">
                                    <div class="geekybot-addon-installer-heading" style = "display:none;">
                                        <?php echo esc_html(__("GeekyBot Addon Installer",'geeky-bot')); ?>
                                    </div>
                                    <div class="geekybot-addon-installer-addon-wrapper" >
                                        <?php
                                        if(isset($_COOKIE['ms_addon_install_data'])){
                                            $ms_addon_install_data = geekybotphplib::GEEKYBOT_safe_decoding(geekybot::GEEKYBOT_sanitizeData($_COOKIE['ms_addon_install_data']));// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                                            $ms_addon_install_data = json_decode( $ms_addon_install_data , true);
                                        }else{
                                            $ms_addon_install_data = json_decode(get_option('ms_addon_install_data'), true);
                                        }
                                        $error_message = '';
                                        if($ms_addon_install_data){
                                            $result = $ms_addon_install_data;
                                            if(isset($result['status']) && $result['status'] == 1){?>
                                                <div class="geekybot-addon-installer-addon-section" >
                                                    <div class="geekybot-addon-installer-addon-section-select_all_div">
                                                <label for="geekybot-addon-installer-addon-checkall-checkbox"><input type="checkbox" class="geekybot-addon-installer-addon-checkall-checkbox" id="geekybot-addon-installer-addon-checkall-checkbox"><?php echo esc_html(__("Select All Addons",'geeky-bot')); ?></label>
                                                </div>
                                                    <?php
                                                    if(!empty($result['data'])){
                                                        $addon_availble_count = 0;
                                                        foreach ($result['data'] as $key => $value) {
                                                            if(!in_array($key, $addon_array)){
                                                                $addon_availble_count++;
                                                                $addon_slug_array = geekybotphplib::GEEKYBOT_explode('-', $key);
                                                                $addon_image_name = $addon_slug_array[count($addon_slug_array) - 1];
                                                                $addon_slug = geekybotphplib::GEEKYBOT_str_replace('-', '', $key);

                                                                $addon_img_path = '';
                                                                $addon_img_path = GEEKYBOT_PLUGIN_URL.'includes/images/addon-images/addons/';
                                                                if($value['status'] == 1){ ?>
                                                                    <div class="geekybot-addon-installer-addon-single" >
                                                                        <img class="geekybot-addon-installer-addon-image" data-addon-name="<?php echo esc_attr($key); ?>" src="<?php echo esc_url($addon_img_path.$addon_image_name.'.png');?>" />
                                                                        <div class="geekybot-addon-installer-addon-name">
                                                                            <input type="checkbox" class="geekybot-addon-installer-addon-single-checkbox" id="addon-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="1">
                                                                            <?php echo esc_html($value['title']);?>
                                                                        </div>
                                                                    </div>
                                                                    <?php
                                                                }
                                                            }
                                                        }
                                                        if($addon_availble_count == 0){ // all allowed addon are already installed
                                                            $error_message = esc_html(__('All allowed add ons are already installed','geeky-bot')).'.';
                                                        }
                                                    }else{ // no addon returend
                                                        $error_message = esc_html(__('You are not allowed to install any add on','geeky-bot')).'.';
                                                    }
                                                    if($error_message != ''){
                                                        $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step1");

                                                        $data = '<div class="geekybot-addon-go-back-messsage-wrap">';
                                                        $data .= '<h1>';
                                                        $data .= wp_kses_post($error_message);
                                                        $data .= '</h1>';

                                                        $data .= '<a class="geekybot-addon-go-back-link" href="'.esc_url($url).'">';
                                                        $data .= esc_html(__('Back','geeky-bot'));
                                                        $data .= '</a>';
                                                        $data .= '</div>';
                                                        echo wp_kses($data, GEEKYBOT_ALLOWED_TAGS);
                                                    }
                                                     ?>
                                                      <div class="geekybot-addon-installer-addon-section-select_all_div">
                                                <label for="geekybot-addon-installer-addon-checkall-checkbox"><input type="checkbox" class="geekybot-addon-installer-addon-checkall-checkbox" id="geekybot-addon-installer-addon-checkall-checkbox"><?php echo esc_html(__("Select All Addons",'geeky-bot')); ?></label>
                                                </div>
                                                </div>
                                                <?php if($error_message == ''){ ?>
                                                    <div class="geekybot-addon-installer-addon-bottom" >
                                                        <div class="hr"></div>
                                                    </div>
                                                    <div class="geekybot-addon-installer-button" >
                                            <button type="submit" class="geekybot_btn" role="submit" onclick="jsShowLoading();"><?php echo esc_html(__("Proceed",'geeky-bot')); ?></button>
                                        </div>
                                                <?php
                                                }
                                            }
                                        }else{
                                            $error_message = esc_html(__('Something went wrong','geeky-bot')).'!';
                                            $url = admin_url("admin.php?page=geekybot_premiumplugin&geekybotlt=step1"); ?>
                                            <div class="geekybot-addon-installer-wrapper" >
                                                <div class="geekybot-addon-installer-section-wrap geekybot-addon-installer-section-wrap_something_wrong">
                                                    <img alt="<?php echo esc_html(__('image', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/addon-images/main-logo.png" />
                                                    <div class="geekybot-addon-installer-heading geekybot-addon-installer-heading_something_wrong">
                                                        <?php echo wp_kses_post($error_message); ?>
                                                    </div>
                                                    <div class="geekybot-addon-installer-key-section" >
                                                        <div class="geekybot-addon-installer-key-field" >
                                                            <div class="geekybot-addon-installer-key-button2 geekybot-addon-go-back-messsage-wrap" >
                                                                <a class="geekybot-addon-go-back-link" href="<?php echo esc_url($url); ?>" >
                                                                    <?php echo esc_html(__('Back','geeky-bot')); ?>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                        if($error_message == ''){ ?>
                                        <?php } ?>
                                    </div>
                                </div>
                                <input type="hidden" name="token" value="<?php echo esc_attr(isset($result['token']) ? $result['token'] : ''); ?>"/>
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

        jQuery('.geekybot-addon-installer-addon-image').on('click', function() {
            var addon_name = jQuery(this).attr('data-addon-name')
            var prop_checked = jQuery('#addon-'+addon_name).prop('checked');
            if(prop_checked){
                jQuery('#addon-'+addon_name).prop('checked', false);
            }else{
                jQuery('#addon-'+addon_name).prop('checked', true);
            }
        });
        // to handle select all check box.
        jQuery('.geekybot-addon-installer-addon-checkall-checkbox').change(function() {
           jQuery('.geekybot-addon-installer-addon-single-checkbox').prop('checked', this.checked);
       });


    });

    function jsShowLoading(){
        jQuery('div#black_wrapper_translation').show();
        jQuery('div#mstran_loading').show();
    }

";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>  
<?php
if(isset($_SESSION['ms_addon_install_data'])){// to avoid to show data on refresh
    unset($_SESSION['ms_addon_install_data']);
}
delete_option('ms_addon_install_data');

?>
