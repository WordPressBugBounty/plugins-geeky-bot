<?php
    if (!defined('ABSPATH'))
        die('Restricted Access');
    $geekybot_js ='
    jQuery(document).ready(function(){
        jQuery("#is_posts_enable").change(function() {
            if (this.checked) {
                geekybotEnableWebSearch();
            } else {
                geekybotDisableWebSearch();
            }
        });
        jQuery("#is_new_post_type_enable").change(function() {
            if (this.checked) {
                geekybotEnableDisableNewPostTypes(1);
            } else {
                geekybotEnableDisableNewPostTypes(0);
            }
        });
    });
    function geekybotEnableWebSearch() {
        geekybotShowLoading();
        var ajaxurl = "'.esc_url(admin_url("admin-ajax.php")).'";
        jQuery.post(ajaxurl, { action: "geekybot_ajax", geekybotme: "websearch", task: "geekybotEnableWebSearch", "_wpnonce":"'.esc_attr(wp_create_nonce("enable-post")) .'"}, function (data) {
            if (data) {
                geekybotHideLoading();
                if(data == 1){
                    var msg = "'. __('AI web search enabled successfully.', 'geeky-bot') .'";
                    alert(msg);
                    window.location.reload();
                } else if(data == 2){
                    var msg = "'. __('AI web search already enabled.', 'geeky-bot') .'";
                    alert(msg);
                } else {
                    var msg = "'. __('Something went wrong.', 'geeky-bot') .'";
                    alert(msg);
                }
            }
        });
    }

    function geekybotDisableWebSearch() {
        geekybotShowLoading();
        var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
        jQuery.post(ajaxurl, { action: "geekybot_ajax", geekybotme: "websearch", task: "geekybotDisableWebSearch", "_wpnonce":"'.esc_attr(wp_create_nonce("disable-post")) .'"}, function (data) {
            if (data) {
                geekybotHideLoading();
                if(data == 1){
                    var msg = "'. __('AI web search disabled successfully.', 'geeky-bot') .'";
                    alert(msg);
                    window.location.reload();
                } else {
                    var msg = "'. __('Something went wrong.', 'geeky-bot') .'";
                    alert(msg);
                }
            }
        });
    }

    function geekybotEnableDisableNewPostTypes(status) {
        geekybotShowLoading();
        var ajaxurl = "'.esc_url(admin_url('admin-ajax.php')).'";
        jQuery.post(ajaxurl, { action: "geekybot_ajax", geekybotme: "websearch", task: "geekybotEnableDisableNewPostTypes", status: status, "_wpnonce":"'.esc_attr(wp_create_nonce("post-types-status")) .'"}, function (data) {
            if (data) {
                geekybotHideLoading();
                if(data == 1){
                    if(status == 1){
                        var msg = "'. __('New post type search enabled successfully.', 'geeky-bot') .'";
                        alert(msg);
                    } else if (status == 0){
                        var msg = "'. __('New post type search disabled successfully.', 'geeky-bot') .'";
                        alert(msg);
                    }
                } else {
                    var msg = "'. __('Something went wrong.', 'geeky-bot') .'";
                    alert(msg);
                }
            }
        });
    }
    function resetFrom() {
        jQuery("input#websearchtitle").val("");
        jQuery("select#status").val("");

        jQuery("form#geekybotform").submit();
    }';
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
    if (!GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/header',array('module' => 'websearch'))){
        return;
    }
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'websearch','layouts' => 'websearch')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
            <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue',array('module' => 'websearch')); ?>
        </div>
        <div id="geekybotadmin-data" class="geekybotadmin-variable-data">
            <!-- top head -->
            <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagetitle',array('module' => 'websearch','layouts' => 'websearch')); ?>
            <!-- filter form -->
            <form class="geekybot-filter-form" name="geekybotform" id="geekybotform" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_websearch"),"websearch")); ?>">
                <div id="geekybot-searchbar" class="geekybot-searchbar-btn">
                    <div class="window-two-btm-inner geekybot-story-inner">
                        <button title="<?php echo esc_html(__('Search', 'geeky-bot')); ?>" type="submit" name="btnsubmit" id="btnsubmit" value="Search" class="button geekybot-form-search-btn"><img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/loupe.png" alt="<?php echo esc_attr(__('Search', 'geeky-bot')); ?>" class="geekybot-action-img"></button>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('websearchtitle', geekybot::$_data['filter']['websearchtitle'], array('class' => 'inputbox geekybot-form-input-field', 'placeholder' => esc_attr(__('Search', 'geeky-bot')))), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('GEEKYBOT_form_search', 'GEEKYBOT_SEARCH'), GEEKYBOT_ALLOWED_TAGS); ?>
                    </div>
                    <div id="geekybot-reset-btn-main" >
                        <a class="geekybot-Intents-reset-btn" href="javascript:resetFrom();" title="<?php echo esc_attr(__('Reset', 'geeky-bot')); ?>">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/reset.png" alt="<?php echo esc_attr(__('Reset', 'geeky-bot')); ?>" />
                        </a>
                    </div>
                </div>
            </form>
            <!-- top bar -->
            <div id="geekybot-wrapper-top">
            </div>
            <!-- page content -->
            <?php 
            if (geekybot::$_configuration['is_posts_enable'] == 1 && get_option('geekybot_synchronize_available') == 1) {?>
                <div class="geekybot-websearch-info-section">
                    <div class="geekybot-websearch-infoimage">
                        <img title="<?php echo esc_html(__('Info', 'geeky-bot')); ?>"alt="<?php echo esc_html(__('Info', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/info.png';?>" />
                    </div>
                    <div class="geekybot-websearch-inforight-wrp">
                        <p class="geekybot-websearch-info-title"><?php echo esc_html(__('Data Synchronization Needed', 'geeky-bot')); ?></p>
                        <p class="geekybot-websearch-info-dis"><?php echo esc_html(__("Your data is not updated. To ensure accurate results, please synchronize your AI web search data.", 'geeky-bot')); ?></p>
                    </div>
                    <div class="geekybot-synchronize-button-wrp">
                        <a class="geekybot_synchronize_data" title="<?php echo esc_attr(__('Synchronize Data', 'geeky-bot')); ?>" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_websearch&task=synchronizeWebSearchData&action=geekybottask'),'synchronize-data')); ?>">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/synchronize.png" alt="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                            <?php echo esc_html(__('Synchronize Data', 'geeky-bot')); ?>
                        </a>
                    </div>
                </div>
                <?php
            }  ?>
            <div id="geekybot-admin-wrapper" class="p0 bg-n bs-n">
                <div class="geekybot-websearch-header">
                    <div class="geekybot-websearch-support-section">
                        <div class="geekybot-websearch-support-imgwrp">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/problem.png"title="<?php echo esc_attr(__('Help', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Help', 'geeky-bot')); ?>" class="geekybot-websearch-support-img">
                        </div>
                        <div class="geekybot-websearch-support-content-wrp">
                            <span class="geekybot-websearch-support-content-title"><?php echo esc_html(__('Have any problem?', 'geeky-bot'));?></span>
                            <span class="geekybot-websearch-support-content-disc">
                                <?php echo esc_html(__("If you experience any difficulties, we're here to help! Simply click the", 'geeky-bot'));?>
                                <span>
                                    <?php echo ' '.esc_html(__("Create A Ticket", 'geeky-bot')).' ';?>
                                </span>
                                <?php echo esc_html(__("button to report your issue, and our dedicated support team will assist you promptly.", 'geeky-bot'));?>
                            </span>
                        </div>
                        <div class="geekybot-websearch-support-button-wrp">
                            <a href="https://geekybot.com/support/add-ticket/" target="_blank" title="<?php echo esc_html(__('Create Ticket', 'geeky-bot'));?>"><?php echo esc_html(__('Create A Ticket', 'geeky-bot'));?></a>
                        </div>
                    </div>
                   <div class="geekybot-websearch-section">
                        <div class="geekybot-websearch-section-lftclm">
                            <span class="geekybot-websearch-section-title"><?php echo esc_html(__('Enable AI web Search', 'geeky-bot'));?></span>
                            <p class="geekybot-websearch-section-disc"><?php echo esc_html(__('Enable this feature to provide users with direct AI web search results in the chat, offering them a broader context for their queries.', 'geeky-bot'));?></p>
                        </div>
                        <?php
                            if (geekybot::$_configuration['is_posts_enable'] == 1) {
                                $checked = 'checked="checked"';
                                $disable_class = '';
                            } else {
                                $checked = '';
                                $disable_class = 'geekybot-websearch-disable-listings';
                            }
                        ?>
                        <div class="geekybot-websearch-section-rightclm">
                            <label class="geeky-websearch-switch" title="<?php echo esc_html(__('Enable/Disable','geeky-bot')); ?>">
                                <input id="is_posts_enable" name="is_posts_enable" class="geeky_websearch-aiinput-checkbox geeky-websearch" type="checkbox" value="1" <?php echo esc_html($checked); ?>>
                                <span class="geeky_websearch-aiinput-slider">
                                    <span class="geeky_websearch-aiinput-slider-enable-text"><?php echo esc_html(__('Enable','geeky-bot')); ?></span>
                                    <span class="geeky_websearch-aiinput-slider-disable-text"><?php echo esc_html(__('Disable','geeky-bot')); ?></span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="geekybot-websearch-section">
                        <div class="geekybot-websearch-section-lftclm">
                            <span class="geekybot-websearch-section-title"><?php echo esc_html(__('Enable New Post Type Search', 'geeky-bot'));?></span>
                            <p class="geekybot-websearch-section-disc"><?php echo esc_html(__('Enable this option to directly include new post types in search results, enhancing user access to all content.', 'geeky-bot'));?></p>
                        </div>
                        <?php
                            if (geekybot::$_configuration['is_new_post_type_enable'] == 1) {
                                $checked = 'checked="checked"';
                            } else {
                                $checked = '';
                            }
                        ?>
                        <div class="geekybot-websearch-section-rightclm">
                            <label class="geeky-websearch-switch" title="<?php echo esc_html(__('Enable/Disable','geeky-bot')); ?>">
                                <input id="is_new_post_type_enable" name="is_new_post_type_enable" class="geeky_websearch-aiinput-checkbox geeky-websearch" type="checkbox" value="1" <?php echo esc_html($checked); ?>>
                                <span class="geeky_websearch-aiinput-slider">
                                    <span class="geeky_websearch-aiinput-slider-enable-text"><?php echo esc_html(__('Enable','geeky-bot')); ?></span>
                                    <span class="geeky_websearch-aiinput-slider-disable-text"><?php echo esc_html(__('Disable','geeky-bot')); ?></span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <span class="geekybot-websearch-section-page-title"><?php echo esc_html(__('Manage Post Types', 'geeky-bot'));?></span>
                </div>
                <!-- quick actions -->
                <?php
                if (!empty(geekybot::$_data[0])) {
                    ?>
                    <form id="geekybot-list-form" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_websearch"),"delete-websearch")); ?>">
                        <?php
                            $pagenum = GEEKYBOTrequest::GEEKYBOT_getVar('pagenum', 'get', 1);
                            $pageid = ($pagenum > 1) ? '&pagenum=' . $pagenum : '';
                            foreach (geekybot::$_data[0] AS $row) { ?>
                                <div class="geekybot-websearch-listings <?php echo esc_attr($disable_class); ?>">
                                    <div class="geekybot-listing-websearch-heading-mainwrp">
                                        <div class="geekybot-listing-websearch-heading-wrp">
                                            <div class="geekybot-listing-heading">
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot_websearch&geekybotlt=formwebsearch&geekybotid='.esc_attr($row->id))); ?>" title="<?php echo esc_attr(__('post type','geeky-bot')); ?>">
                                                    <?php echo esc_html(geekybot::GEEKYBOT_getVarValue($row->post_type)); ?>
                                                </a>
                                            </div>
                                            <div class="geekybot-websearch-heading-media">
                                                <div class="geekybot-text-left">
                                                    <span class="geekybot-listing-subheading">
                                                        <?php echo esc_html(__('Lable', 'geeky-bot')); ?>:
                                                    </span>
                                                    <span class="geekybot-listing-subheading-post-label" title="<?php echo esc_attr(__('Post lable','geeky-bot')); ?>">
                                                        <?php echo esc_html(geekybot::GEEKYBOT_getVarValue($row->post_label)); ?>
                                                    </span>
                                                </div>
                                                <div class="geekybot-listing-subheading">
                                                    <?php echo esc_html(__('Associated Plugin','geeky-bot')); ?>:
                                                    <span class="geekybot-text-left geekybot-possible-value" title="<?php echo esc_attr(geekybot::GEEKYBOT_getVarValue($row->plugin_name)); ?>">
                                                        <?php echo esc_html(geekybot::GEEKYBOT_getVarValue($row->plugin_name)); ?>
                                                    </span>
                                                </div>
                                            </div>  
                                        </div>
                                    </div>
                                    <div class="geekybot-websearch-button-wrp">
                                        <div class="geekybot-listing-websearch-heading-rightbtn-wrp">
                                            <?php
                                            if ($row->status == 1) { ?>
                                                <span class="geekybot-listing-websearch-heading-right-active-btn" title="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>">
                                                    <?php echo esc_html(__('Active', 'geeky-bot')); ?>
                                                </span>
                                                <?php 
                                            } else { ?>
                                                <span class="geekybot-listing-websearch-heading-right-disable-btn" title="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>">
                                                    <?php echo esc_html(__('Disable', 'geeky-bot')); ?>
                                                </span>
                                                <?php 
                                            } ?>
                                        </div>
                                        <div class="geekybot-websrchtable-act-btn-mainwrp">
                                            <a class="geekybot-table-act-btn geekybot-edit" href="<?php echo esc_url(admin_url('admin.php?page=geekybot_websearch&geekybotlt=formwebsearch&geekybotid='.esc_attr($row->id))); ?>" title="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>">
                                                <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/edit.png" alt="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                <?php echo esc_html(__('Edit','geeky-bot')); ?>
                                            </a>
                                            <?php
                                            if ($row->status == 1) {  ?>
                                                <a onclick = "geekybotShowLoading()" id="geekybot-websearch-action-btn" class="geekybot-table-act-btn geekybot-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_websearch&task=changeStatus&action=geekybottask&status=0&id='.esc_attr($row->id)),'change-status')); ?>" title="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/disable.png" alt="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Disable', 'geeky-bot')); ?>
                                                </a>
                                                <span class="geekybot-table-act-btn geekybot-delete" title="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/disable.png" alt="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Disable', 'geeky-bot')); ?>
                                                </span>
                                                <?php 
                                            } else {?>
                                                <a onclick = "geekybotShowLoading()" id="geekybot-websearch-action-btn" class="geekybot-table-act-btn geekybot-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_websearch&task=changeStatus&action=geekybottask&status=1&id='.esc_attr($row->id)),'change-status')); ?>" title="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/active.png" alt="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Active', 'geeky-bot')); ?>
                                                </a>
                                                <span class="geekybot-table-act-btn geekybot-delete" title="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/active.png" alt="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Active', 'geeky-bot')); ?>
                                                </span>
                                                <?php 
                                            } ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'websearch_removewebsearch'), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('pagenum', ($pagenum > 1) ? $pagenum : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('task', ''), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    </form>
                    <?php
                    if (geekybot::$_data[1]) {
                        GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagination',array('module' => 'websearch' , 'pagination' => geekybot::$_data[1]));
                    }
                } else {
                    $msg = __('No record found','geeky-bot');
                    echo wp_kses(GEEKYBOTlayout::GEEKYBOT_getNoRecordFound($msg), GEEKYBOT_ALLOWED_TAGS);
                }
                ?>
            </div>
        </div>
        <div id="geekybotadmin_black_wrapper_built_loading" style="display: none;" ></div>
        <div class="geekybotadmin-built-story-loading" id="geekybotadmin_built_loading" style="display: none;" >
            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/spinning-wheel.gif" />
            <div class="geekybotadmin-built-story-loading-text">
                <?php echo esc_html(__('Please wait a moment; this may take some time.','geeky-bot')); ?>
            </div>
        </div>
    </div>
</div>
<?php
    $geekybot_js ="
    function highlight(){
        jQuery('#geekybot-list-form').toggleClass('geekybot-intent-blue');
    }
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('is_posts_enable');
        const enableText = document.querySelector('.geeky_websearch-aiinput-slider-enable-text');
        const disableText = document.querySelector('.geeky_websearch-aiinput-slider-disable-text');
        
        // Set the delay duration (in milliseconds)
        const delayDuration = 250; // Adjust this value as needed
    
        checkbox.addEventListener('change', function() {
            // Hide both texts initially
            enableText.style.display = 'none';
            disableText.style.display = 'none';
    
            // Delay the display of the corresponding text
            setTimeout(() => {
                if (checkbox.checked) {
                    enableText.style.display = 'block';
                } else {
                    disableText.style.display = 'block';
                }
            }, delayDuration); // Use the configurable delay duration
        });
    });
    ";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
