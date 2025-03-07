<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
    require_once GEEKYBOT_PLUGIN_PATH.'includes/addon-updater/geekybotupdater.php';
    $GEEKYBOT_Updater  = new GEEKYBOT_Updater();
    $cdnversiondata = $GEEKYBOT_Updater->GEEKYBOT_getPluginVersionDataFromCDN();
    $not_installed = array();

    $geekybot_addons = GEEKYBOTincluder::GEEKYBOT_getModel('premiumplugin')->geekybotGetAddonsArray();
?>
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'premiumplugin','layouts' => 'addonstatus')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
            <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data">
            <!-- top head -->
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('Add-ons Status', 'geeky-bot')); ?>
                </h1>
            </div>
            <!-- page content -->
            <div id="geekybot-admin-wrapper">
            	<div id="geekybot-admin-data-wrp" class="geekybot-admin-addons-list-data">
                    <div class="geekybot-admin-addon-status-msg geekybot-admin_success">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) ?>includes/images/addon-images/success.png" />
                        <span class="geekybot-admin-addon-status-msg-txt"></span>
                    </div>
                    <div class="geekybot-admin-addon-status-msg geekybot-admin_error">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) ?>includes/images/addon-images/error.png" />
                        <span class="geekybot-admin-addon-status-msg-txt"></span>
                    </div>
            		<!-- admin addons status -->
                    <div class="geekybot-admin-addons-list-wrp">
                        <?php
                        $installed_plugins = get_plugins();
                        ?>
                        <?php
                            foreach ($geekybot_addons as $key1 => $value1) {
                                $matched = 0;
                                $version = "";
                                foreach ($installed_plugins as $name => $value) {
                                    $install_plugin_name = geekybotphplib::GEEKYBOT_str_replace(".php","",geekybotphplib::GEEKYBOT_basename($name));
                                    if($key1 == $install_plugin_name){
                                        $matched = 1;
                                        $version = $value["Version"];
                                        $install_plugin_matched_name = $install_plugin_name;
                                    }
                                }
                                if($matched == 1){ //installed
                                    $name = $key1;
                                    $title = $value1['title'];
                                    $img = geekybotphplib::GEEKYBOT_str_replace("geeky-bot-", "", $key1).'.png';
                                    $cdnavailableversion = "";
                                    foreach ($cdnversiondata as $cdnname => $cdnversion) {
                                        $install_plugin_name_simple = geekybotphplib::GEEKYBOT_str_replace("-", "", $install_plugin_matched_name);
                                        if($cdnname == geekybotphplib::GEEKYBOT_str_replace("-", "", $install_plugin_matched_name)){
                                            if($cdnversion > $version){ // new version available
                                                $status = 'update_available';
                                                $cdnavailableversion = $cdnversion;
                                            }else{
                                                $status = 'updated';
                                            }
                                        }    
                                    }
                                    geekybot_printAddoneStatus($name, $title, $img, $version, $status, $cdnavailableversion);
                                }else{ // not installed
                                    $img = geekybotphplib::GEEKYBOT_str_replace("geeky-bot-", "", $key1).'.png';
                                    $not_installed[] = array("name" => $key1, "title" => $value1['title'], "img" => $img, "status" => 'not-installed', "version" => "---");
                                }
                            }
                            foreach ($not_installed as $notinstall_addon) {
                                geekybot_printAddoneStatus($notinstall_addon["name"], $notinstall_addon["title"], $notinstall_addon["img"], $notinstall_addon["version"], $notinstall_addon["status"]);
                            }
                        ?>
                    </div>
        		</div>
            </div>
    	</div>
    </div>
</div>

<?php
function geekybot_printAddoneStatus($name, $title, $img, $version, $status, $cdnavailableversion = ''){
    $addoneinfo = GEEKYBOTincluder::GEEKYBOT_getModel('premiumplugin')->geekybotCheckAddoneInfo($name);
    if ($status == 'update_available') {
        $wrpclass = 'geekybot-admin-addon-status geekybot-admin-addons-status-update-wrp';
        $btnclass = 'geekybot-admin-addons-update-btn';
        $btntxt = 'Update Now';
        //$btnlink = 'id="geekybot-admin-addons-update" data-for="'.esc_attr($name).'"';
		$btnlink = 'id=geekybot-admin-addons-update data-for='.esc_attr($name).'';
        $msg = '<div class="geekybot-admin-addon-status-availnew-version" id="geekybot-admin-addon-status-cdnversion">'.esc_html(__('New Update Version','geeky-bot'));
        $msg .= '<span>'." ".$cdnavailableversion." ".'</span>';
        $msg .= esc_html(__('is Available','geeky-bot')).'</div>';
    } elseif ($status == 'expired') {
        $wrpclass = 'geekybot-admin-addon-status geekybot-admin-addons-status-expired-wrp';
        $btnclass = 'geekybot-admin-addons-expired-btn';
        $btntxt = 'Expired';
        $btnlink = '';
        $msg = '';
    } elseif ($status == 'updated') {
        $wrpclass = 'geekybot-admin-addon-status';
        $btnclass = 'geekybot-admin-addon-status-updatedbtn';
        $btntxt = 'Updated';
        $btnlink = '';
        $msg = '';
    } else {
        $wrpclass = 'geekybot-admin-addon-status';
        $btnclass = 'geekybot-admin-addons-buy-btn';
        $btntxt = 'Buy Now';
        $btnlink = 'href="https://geekybot.com/add-ons/"';
        $msg = '';
    }
    $html = '
    <div class="'.esc_attr($wrpclass).'" id="'.esc_attr($name).'">
        <div class="geekybot-addon-status-card-innerwrp">
            <div class="geekybot-addon-status-image-wrp">
                <img alt="Addone image" src="'.esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/addon-images/addons/'.esc_attr($img).'" />
            </div>
            <div class="geekybot-admin-addon-status-title-wrp">
                <h2>'. esc_html(geekybot::GEEKYBOT_getVarValue($title)) .'</h2>
                <div class="geekybot-admin-addon-status-addonstatus-wrp">
                    <span>'. esc_html(__('Status: ','geeky-bot')) .'</span>
                    <span class="'. esc_attr(geekybot::GEEKYBOT_getVarValue($addoneinfo["class"])) .'" href="#">
                        '. esc_html(geekybot::GEEKYBOT_getVarValue($addoneinfo["status"])) .'
                    </span>
                </div>
                <div class="geekybot-admin-addon-status-addonsversion-wrp">
                    <span id="geekybot-admin-addon-status-cversion">
                        '. esc_html(__('Version','geeky-bot')).': 
                        <span>
                            '. esc_html($version) .'
                        </span>
                    </span>
                </div>
                '.wp_kses($msg, GEEKYBOT_ALLOWED_TAGS).'
            </div>
        </div>
        <div class="geekybot-admin-addon-status-addonstatusbtn-wrp">
            <a class="'. esc_attr($addoneinfo["actionClass"]) .'" href="'. esc_url($addoneinfo["url"]) .'">
                '. esc_html(geekybot::GEEKYBOT_getVarValue($addoneinfo["action"])) .'
            </a>
            <a '.esc_attr($btnlink).' class="'.esc_attr($btnclass).'">'.esc_html(geekybot::GEEKYBOT_getVarValue($btntxt)) .'</a>
        </div>
    </div>';
    echo wp_kses($html, GEEKYBOT_ALLOWED_TAGS);
}

?>
<?php
$geekybot_js ="
    jQuery(document).ready(function(){
        jQuery(document).on('click', 'a#geekybot-admin-addons-update', function(){
            geekybotShowLoading();
            var dataFor = jQuery(this).attr('data-for');
            var cdnVer = jQuery('#'+ dataFor +' #geekybot-admin-addon-status-cdnversion span').text();
            var currentVer = jQuery('#'+ dataFor +' #geekybot-admin-addon-status-cversion span').text();
            var cdnVersion = cdnVer.trim();
            var currentVersion = currentVer.trim();
            jQuery.post(ajaxurl, {action: 'geekybot_ajax', geekybotme: 'premiumplugin', task: 'downloadandinstalladdonfromAjax', dataFor:dataFor, currentVersion:currentVersion, cdnVersion:cdnVersion, '_wpnonce':'". esc_attr(wp_create_nonce("download-and-install-addon"))."'}, function (data) {
                if (data) {
                    geekybotHideLoading();
                    data = JSON.parse(data);
                    if(data['error']){
                        jQuery('.geekybot-admin-addon-status-msg.geekybot-admin_error span.geekybot-admin-addon-status-msg-txt').html(data['error']);
                        jQuery('.geekybot-admin-addon-status-msg.geekybot-admin_error').slideDown('slow');
                    } else if(data['success']) {
                        jQuery('#' + dataFor + ' #geekybot-admin-addon-status-cversion span').text(cdnVersion);
                        jQuery('#' + dataFor + ' #geekybot-admin-addon-status-cdnversion').hide();
                        jQuery('#' + dataFor + ' .geekybot-admin-addons-update-btn').hide();
                        jQuery('.geekybot-admin-addon-status-msg.geekybot-admin_success span.geekybot-admin-addon-status-msg-txt').html(data['success']);
                        jQuery('.geekybot-admin-addon-status-msg.geekybot-admin_success').slideDown('slow');
                    }
                    clearNotifications();
                }
            });
        });
    });
    
    function clearNotifications(){
        setTimeout(function(){
            jQuery('.geekybot-admin-addon-status-msg').slideUp('slow');
        }, 2500);
        setTimeout(function(){
            jQuery('.geekybot-admin-addon-status-msg').remove()
        }, 3000);
    }

";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>  
