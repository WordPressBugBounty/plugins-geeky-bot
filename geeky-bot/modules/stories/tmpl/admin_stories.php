<?php
wp_enqueue_script('jquery');
if (!defined('ABSPATH')){
    die('Restricted Access');
}
wp_enqueue_style('geekybot-select2css', GEEKYBOT_PLUGIN_URL . 'includes/css/select2.min.css');
wp_enqueue_script('geekybot-select2js', GEEKYBOT_PLUGIN_URL . 'includes/js/select2.min.js');
?>
<?php
$geekybot_js ="
jQuery(document).ready(function() {
    jQuery(document).on('click', '.addStory', function() {
        var type = jQuery(this).attr('data-type');
        if(type == 1) {
            jQuery('#template').select2({
                dropdownParent: jQuery('#userStoryForm')
            });
            jQuery('form#userStoryForm #template').removeClass('geeky_hide');
            jQuery('form#userStoryForm #name').addClass('geeky_mb_20');
        } else {
            if (jQuery('#template').hasClass('select2-hidden-accessible')) {
                jQuery('#template').select2('destroy');
            }
            jQuery('form#userStoryForm #template').addClass('geeky_hide');
            jQuery('form#userStoryForm #name').removeClass('geeky_mb_20');
        }
        jQuery('div#userinput-popup').slideDown('slow');
        jQuery('div#userpopupblack').show();
        jQuery('form#userStoryForm #type').val(type);
    });

    jQuery(document).on('click', '.importStoryBtn', function() {
        var type = jQuery(this).attr('data-type');
        jQuery('div#importStoryPopup').slideDown('slow');
        jQuery('div#userpopupblack').show();
        jQuery('form#importStoryForm #type').val(type);
    });

    jQuery(document).on('click', '.geekybotEditName', function() {
        var id = jQuery(this).attr('data-id');
        var name = jQuery(this).attr('data-name');
        jQuery('div#editStoryName').slideDown('slow');
        jQuery('div#userpopupblack').show();
        jQuery('form#editStoryNameForm #id').val(id);
        jQuery('form#editStoryNameForm #name').val(name);
    });

    jQuery(document).on('click', '.geekybot-delete-btn', function(event) {
        // Call the confirmation function and store the result
        var isConfirmed = confirmdelete('". esc_attr(__('Are you sure to delete', 'geeky-bot')).' ?'."');
        
        if (!isConfirmed) {
            event.preventDefault(); // Prevents the default anchor action
            return false; // Stops the function execution
        }

        // Only run this function if the user clicks 'OK'
        geekybotShowLoading();
    });

    jQuery(document).on('click', '.geekybot-status-btn', function() {
        geekybotShowLoading();
    });
    
    jQuery('form#userStoryForm').submit(function (e) {
        e.preventDefault();
        geekybotShowLoading();
        var id = jQuery('input#id').val();
        var name = jQuery('input#name').val();
        var type = jQuery('input#type').val();
        var template = '';
        var emptyTemplate = 0;
        if(type == 1) {
            template = jQuery('Select#template').val();
            if (template != '') {
                var task = 'geekybotBuildAIStoryFromTemplate';
            } else {
                emptyTemplate = 1;
            }
        } else if(type == 2) {
            var task = 'geekybotBuildWooCommerceStory';
        }
        if (name != '' && emptyTemplate == 0) {
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'stories',
                task: task,
                name: name,
                template: template,
                '_wpnonce':'". esc_attr(wp_create_nonce("save-story")). "'
            }, function(data) {
                geekybotHideLoading();
                if (data == 1) {
                    jQuery('#user-input-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Story has been successfully saved.', 'geeky-bot')) ."</div></div>');
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                    clearNotifications();
                } else if(data == 3) {
                    jQuery('#user-input-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"".  esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('WooCommerce is not active!', 'geeky-bot')) ."</div></div>');
                    clearNotifications();
                } else if(data != '') {
                    jQuery('#user-input-msg').html('');
                    jQuery('#user-input-msg').append('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"".  esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Error encountered in template.', 'geeky-bot')) ."</div></div>');
                    jQuery('#user-input-msg').append('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"".  esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" /><p>'+data+'</p></div></div>');
                } else {
                    jQuery('#user-input-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg \"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"".  esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                    clearNotifications();
                }
            });
        } else {
            geekybotHideLoading();
            jQuery('#user-input-msg').html('');
            if (name == '') {
                jQuery('#user-input-msg').append('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('An empty story name.', 'geeky-bot')) ."</div></div>');
            }
            if (emptyTemplate == 1) {
                jQuery('#user-input-msg').append('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('An empty story template.', 'geeky-bot')) ."</div></div>');
            }
        }
    });
    
    jQuery('form#editStoryNameForm').submit(function (e) {
        e.preventDefault();
        geekybotShowLoading();
        var id = jQuery('form#editStoryNameForm input#id').val();
        var name = jQuery('form#editStoryNameForm input#name').val();
        if (name != '' && id != '') {
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'stories',
                task: 'geekybotEditStoryName',
                id: id,
                name: name,
                '_wpnonce':'". esc_attr(wp_create_nonce("edit-story-name")). "'
            }, function(data) {
                geekybotHideLoading();
                if (data == 1) {
                    jQuery('#edit-story-name-form-msg').html('<div class=\"geeky-bot-popop-save-success-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-green.png\" />". esc_attr(__('Story has been successfully saved.', 'geeky-bot')) ."</div></div>');
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                } else {
                    jQuery('#edit-story-name-form-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg \"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"".  esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('Something went wrong try again later!', 'geeky-bot')) ."</div></div>');
                }
                clearNotifications();
            });
        } else {
            geekybotHideLoading();
            jQuery('#edit-story-name-form-msg').html('<div class=\"geeky-bot-popop-save-success-msg geeky-error-msg\"><div class=\"geeky-infoicon-image-text-wraper\"><img alt=\"". esc_html(__('Info','geeky-bot')) ."\" title=\"". esc_html(__('Info','geeky-bot')) ."\" class=\"userpopup-plus-icon\" src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/story/info-red.png\" />". esc_attr(__('An empty story name.', 'geeky-bot')) ."</div></div>');
        }
    });

    jQuery('img#userinputPopupCloseBtn').click(function (e) {
        jQuery('div#userinput-popup').slideUp('slow');
        jQuery('div#userpopupblack').hide();
    });

    jQuery('img#editStoryNamePopupCloseBtn').click(function (e) {
        jQuery('div#editStoryName').slideUp('slow');
        jQuery('div#userpopupblack').hide();
    });

    jQuery('img#importStoryPopupCloseBtn').click(function (e) {
        jQuery('div#importStoryPopup').slideUp('slow');
        jQuery('div#userpopupblack').hide();
    });
    
    jQuery('.geekybot-synchronize-products').on('click', function(e) {
        geekybotShowLoading();
    });
});
function clearNotifications(){
    setTimeout(function(){
        jQuery('.geeky-bot-popop-save-success-msg').slideUp();
    }, 1500);
    setTimeout(function(){
        jQuery('.geeky-bot-popop-save-success-msg').remove()
    }, 2000);
}
";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
if (!GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/header',array('module' => 'stories'))){
    return;
}
?>
<div class="chat-bot-col-xl-8">
<!-- main wrapper -->
    <div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
        <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'stories','layouts' => 'stories')); ?>
        <div class="geekybotadmin-body-main">
            <!-- left menu -->
            <div id="geekybotadmin-leftmenu-main">
                <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue',array('module' => 'intent')); ?>
            </div>
            <div id="geekybotadmin-data" class="geekybotadmin-action-data">
                <!-- top head -->
                <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagetitle',array('module' => 'stories','layouts' => 'stories')); ?>
                <!-- page content -->
                <div id="geekybot-admin-wrapper" class="geekybot-story-wrapper p0 bg-n bs-n">
                    <?php
                    if (!empty(geekybot::$_data[0])) { ?>
                        <form id="geekybot-list-form" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_action"),"action")); ?>">
                            <table id="geekybot-table" class="geekybot-table" cellpadding="0" cellspacing="0">
                                <tbody>
                                    <?php 
                                    if (isset(geekybot::$_data[0]['ai_story'])) { ?>
                                        <div class="geekybot-listing-section">
                                            <div class="geekybot-listing-stories-heading-mainwrp">
                                                <div class="geekybot-listing-stories-heading-wrp">
                                                    <div class="geekybot-listing-heading">
                                                        <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot_stories&geekybotlt=formstory&storyid='.esc_attr(geekybot::$_data[0]['ai_story']->id))); ?>" title="<?php echo esc_attr(__('title','geeky-bot')); ?>">
                                                            <?php echo esc_html(geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['ai_story']->name)); ?>
                                                        </a>
                                                        <a class="geekybot-listing-heading-button geekybotEditName" href="#" title="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" data-id ="<?php echo esc_html(geekybot::$_data[0]['ai_story']->id); ?>" data-name ="<?php echo esc_html(geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['ai_story']->name)); ?>">
                                                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/edit.png" alt="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                        </a>
                                                    </div>
                                                    <div class="geekybot-text-left">
                                                        <span class="geekybot-listing-subheading">
                                                            <?php echo esc_html(__('Type', 'geeky-bot')); ?>:
                                                        </span>
                                                        <?php echo esc_html(__('AI ChatBot','geeky-bot'));?>
                                                    </div>
                                                </div>
                                                <div class="geekybot-listing-stories-heading-rightbtn-wrp">
                                                    <?php
                                                    if (isset(geekybot::$_data[0]['ai_story']->status) && geekybot::$_data[0]['ai_story']->status == 1) { ?>
                                                        <span class="geekybot-listing-stories-heading-right-active-btn" title="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>">
                                                            <?php echo esc_html(__('Active', 'geeky-bot')); ?>
                                                        </span>
                                                        <?php 
                                                    } else { ?>
                                                        <span class="geekybot-listing-stories-heading-right-disable-btn" title="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>">
                                                            <?php echo esc_html(__('Disable', 'geeky-bot')); ?>
                                                        </span>
                                                        <?php 
                                                    } ?>
                                                </div>
                                            </div>
                                            <div class="geekybot-listing-button-wrp">
                                                <a class="geekybot-table-act-btn geekybot-edit" href="<?php echo esc_url(admin_url('admin.php?page=geekybot_stories&geekybotlt=formstory&storyid='.esc_attr(geekybot::$_data[0]['ai_story']->id))); ?>" title="<?php echo esc_attr(__('Edit Story', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/edit.png" alt="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Edit Story', 'geeky-bot')); ?>
                                                </a>
                                                <?php
                                                if (isset(geekybot::$_data[0]['ai_story']->status) && geekybot::$_data[0]['ai_story']->status == 1) {  ?>
                                                    <a class="geekybot-table-act-btn geekybot-delete geekybot-status-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_stories&task=changeStatus&action=geekybottask&status=0&storyid='.esc_attr(geekybot::$_data[0]['ai_story']->id)),'change-status-'.geekybot::$_data[0]['ai_story']->id)); ?>" title="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>">
                                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/disable.png" alt="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                        <?php echo esc_html(__('Disable', 'geeky-bot')); ?>
                                                    </a>
                                                    <?php 
                                                } else {?>
                                                    <a class="geekybot-table-act-btn geekybot-delete geekybot-status-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_stories&task=changeStatus&action=geekybottask&status=1&storyid='.esc_attr(geekybot::$_data[0]['ai_story']->id)),'change-status-'.geekybot::$_data[0]['ai_story']->id)); ?>" title="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>">
                                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/active.png" alt="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                        <?php echo esc_html(__('Active', 'geeky-bot')); ?>
                                                    </a>
                                                    <?php 
                                                } ?>
                                                <a class="geekybot-table-act-btn geekybot-delete geekybot-delete-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_stories&task=removeStory&action=geekybottask&geekybot-cb='.esc_attr(geekybot::$_data[0]['ai_story']->id)),'delete-story-'.geekybot::$_data[0]['ai_story']->id)); ?>" title="<?php echo esc_attr(__('Delete Story', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/delete.png" alt="<?php echo esc_attr(__('Delete', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Delete Story', 'geeky-bot')); ?>
                                                </a>
                                                <a class="geekybot-table-act-btn geekybot-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_stories&task=geekybotExportStoryToXML&action=geekybottask&geekybot-storyid='.esc_attr(geekybot::$_data[0]['ai_story']->id)),'export-story-'.geekybot::$_data[0]['ai_story']->id)); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/export.png" alt="<?php echo esc_attr(__('Export Story', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                                                    <?php echo esc_html(__('Export Story', 'geeky-bot')); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <?php 
                                    } else { ?>
                                        <div class="geekybot-listing-section">
                                            <div class="geekybot-listing-heading">
                                                <a href="#" title="<?php echo esc_attr(__('title','geeky-bot')); ?>">
                                                    <?php echo esc_html(__("AI ChatBot", "geeky-bot")); ?>
                                                </a>
                                            </div>
                                            <div class="geekybot-listing-button-wrp">
                                                <a class="geekybot-table-act-btn addStory geekybot-ai-story" data-type = "1" href="#" title="<?php echo esc_attr(__('Built Story', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/plus-white.png" alt="<?php echo esc_attr(__('Built', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Built Story','geeky-bot')); ?>
                                                </a>
                                                <a class="geekybot-table-act-btn importStoryBtn geekybot-ai-story" data-type = "1" href="#" title="<?php echo esc_attr(__('Import Story', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/import.png" alt="<?php echo esc_attr(__('Built', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Import Story','geeky-bot')); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    if (isset(geekybot::$_data[0]['woo_story'])) { ?>
                                        <div class="geekybot-listing-section">
                                            <div class="geekybot-listing-stories-heading-mainwrp">
                                                <div class="geekybot-listing-stories-heading-wrp">
                                                    <div class="geekybot-listing-heading">
                                                        <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot_stories&geekybotlt=formstory&storyid='.esc_attr(geekybot::$_data[0]['woo_story']->id))); ?>" title="<?php echo esc_attr(__('title','geeky-bot')); ?>">
                                                            <?php echo esc_html(geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['woo_story']->name)); ?>
                                                        </a>
                                                        <a class="geekybot-listing-heading-button geekybotEditName" href="#" title="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" data-id ="<?php echo esc_html(geekybot::$_data[0]['woo_story']->id); ?>" data-name ="<?php echo esc_html(geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['woo_story']->name)); ?>">
                                                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/edit.png" alt="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                        </a>
                                                    </div>
                                                    <div class="geekybot-text-left">
                                                        <span class="geekybot-listing-subheading">
                                                            <?php echo esc_html(__('Type', 'geeky-bot')); ?>:
                                                        </span>
                                                        <?php echo esc_html(__('WooCommerce Story','geeky-bot'));?>
                                                    </div>
                                                </div>
                                                <div class="geekybot-listing-stories-heading-rightbtn-wrp">
                                                    <?php
                                                    if (isset(geekybot::$_data[0]['woo_story']->status) && geekybot::$_data[0]['woo_story']->status == 1) { ?>
                                                        <span class="geekybot-listing-stories-heading-right-active-btn" title="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>">
                                                            <?php echo esc_html(__('Active', 'geeky-bot')); ?>
                                                        </span>
                                                        <?php 
                                                    } else { ?>
                                                        <span class="geekybot-listing-stories-heading-right-disable-btn" title="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>">
                                                            <?php echo esc_html(__('Disable', 'geeky-bot')); ?>
                                                        </span>
                                                        <?php 
                                                    } ?>
                                                </div>
                                            </div>
                                            <div class="geekybot-listing-button-wrp">
                                                <a class="geekybot-table-act-btn geekybot-edit" href="<?php echo esc_url(admin_url('admin.php?page=geekybot_stories&geekybotlt=formstory&storyid='.esc_attr(geekybot::$_data[0]['woo_story']->id))); ?>" title="<?php echo esc_attr(__('Edit Story', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/edit.png" alt="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Edit Story', 'geeky-bot')); ?>
                                                </a>
                                                <?php
                                                if (isset(geekybot::$_data[0]['woo_story']->status) && geekybot::$_data[0]['woo_story']->status == 1) { ?>
                                                    <a class="geekybot-table-act-btn geekybot-delete geekybot-status-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_stories&task=changeStatus&action=geekybottask&status=0&storyid='.esc_attr(geekybot::$_data[0]['woo_story']->id)),'change-status-'.geekybot::$_data[0]['woo_story']->id)); ?>" title="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>">
                                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/disable.png" alt="<?php echo esc_attr(__('Disable', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                        <?php echo esc_html(__('Disable', 'geeky-bot')); ?>
                                                    </a>
                                                    <?php 
                                                } else { ?>
                                                    <a class="geekybot-table-act-btn geekybot-delete geekybot-status-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_stories&task=changeStatus&action=geekybottask&status=1&storyid='.esc_attr(geekybot::$_data[0]['woo_story']->id)),'change-status-'.geekybot::$_data[0]['woo_story']->id)); ?>" title="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>">
                                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/active.png" alt="<?php echo esc_attr(__('Active', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                        <?php echo esc_html(__('Active', 'geeky-bot')); ?>
                                                    </a>
                                                    <?php 
                                                } ?>
                                                <a class="geekybot-table-act-btn geekybot-delete geekybot-delete-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_stories&task=removeStory&action=geekybottask&geekybot-cb='.esc_attr(geekybot::$_data[0]['woo_story']->id)),'delete-story-'.geekybot::$_data[0]['woo_story']->id)); ?>" title="<?php echo esc_attr(__('Delete Story', 'geeky-bot')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/delete.png" alt="<?php echo esc_attr(__('Delete', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                    <?php echo esc_html(__('Delete Story', 'geeky-bot')); ?>
                                                </a>
                                                <a class="geekybot-table-act-btn geekybot-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_stories&task=geekybotExportStoryToXML&action=geekybottask&geekybot-storyid='.esc_attr(geekybot::$_data[0]['woo_story']->id)),'export-story-'.geekybot::$_data[0]['woo_story']->id)); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/export.png" alt="<?php echo esc_attr(__('Export Story', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                                                    <?php echo esc_html(__('Export Story', 'geeky-bot')); ?>
                                                </a>
                                                <a class="geekybot-table-act-btn geekybot-delete geekybot-synchronize-products" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_woocommerce&task=synchronizeWooCommerceProducts&action=geekybottask'),'synchronize-data')); ?>">
                                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/syn-icon.png" alt="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                                                    <?php echo esc_html(__('Synchronize Products', 'geeky-bot')); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <?php 
                                    } else { ?>
                                        <div class="geekybot-listing-section">
                                            <div class="geekybot-listing-heading">
                                                <a href="#" title="<?php echo esc_attr(__('title','geeky-bot')); ?>">
                                                    <?php echo esc_html(__("WooCommerce", "geeky-bot")); ?>
                                                </a>
                                            </div>
                                            <div class="geekybot-listing-button-wrp">
                                                <?php 
                                                if (!class_exists('WooCommerce')) { ?>
                                                    <div class="geekybot-installation-scndpage-info-section">
                                                        <div class="geekybot-installation-infoimage">
                                                            <img title="<?php echo esc_html(__('Info', 'geeky-bot')); ?>"alt="<?php echo esc_html(__('Info', 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/postinstallation/info.png';?>" />
                                                        </div>
                                                        <div class="geekybot-installation-inforight-wrp">
                                                            <p class="geekybot-installation-info-title"><?php echo esc_html(__('WooCommerce is not installed', 'geeky-bot')); ?></p>
                                                            <p class="geekybot-installation-info-dis"><?php echo esc_html(__("WooCommerce plugin is not installed on your site, you won't be able to integrate GeekyBot with WooCommerce.", 'geeky-bot')); ?></p>
                                                        </div>
                                                    </div>
                                                    <?php
                                                } else { ?>
                                                    <a class="geekybot-table-act-btn addStory geekybot-woocommerce-story" data-type = "2" href="#" title="<?php echo esc_attr(__('Built Story', 'geeky-bot')); ?>">
                                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/plus-white.png" alt="<?php echo esc_attr(__('Built', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                        <?php echo esc_html(__('Built Story','geeky-bot')); ?>
                                                    </a>
                                                    <a class="geekybot-table-act-btn importStoryBtn geekybot-woocommerce-story" data-type = "2" href="#" title="<?php echo esc_attr(__('Import Story', 'geeky-bot')); ?>">
                                                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/import.png" alt="<?php echo esc_attr(__('Built', 'geeky-bot')); ?>" class="geekybot-action-img">
                                                        <?php echo esc_html(__('Import Story','geeky-bot')); ?>
                                                    </a>
                                                    <?php
                                                }  ?>
                                            </div>
                                        </div>
                                        <?php
                                    } ?>
                                </tbody>
                            </table>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'action_removeaction'), GEEKYBOT_ALLOWED_TAGS); ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('task', ''), GEEKYBOT_ALLOWED_TAGS); ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('_wpnonce', wp_create_nonce('delete-action')), GEEKYBOT_ALLOWED_TAGS); ?>
                        </form>
                        <?php
                        if (isset(geekybot::$_data[1])) {
                            GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagination',array('module' => 'intent' , 'pagination' => geekybot::$_data[1]));
                        }
                    } else {
                        $msg = __('No record found','geeky-bot');
                        $link[] = array(
                                'link' => 'admin.php?page=geekybot_intent&geekybotlt=formintent',
                                'text' => __('Add New','geeky-bot') .'&nbsp;'. __('Intent','geeky-bot')
                        );
                        echo wp_kses(GEEKYBOTlayout::GEEKYBOT_getNoRecordFound($msg,$link), GEEKYBOT_ALLOWED_TAGS);
                    }
                    ?>
                </div>
            </div>
        </div>
        <!-- build story form -->
        <div id="userpopupblack" style="display: none;"></div>
        <div id="userinput-popup" class="geekybot-popup-wrapper geekybot-built-story-popup geekybot-add-storypge-main-wrapper" style="display: none;">
            <div class="userpopup-top">
                <div class="userpopup-heading">
                    <?php echo esc_html(__('Built Story','geeky-bot')); ?>
                </div>
                <img title="<?php echo esc_html(__('Close','geeky-bot')); ?>" alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="userinputPopupCloseBtn" title="<?php echo esc_attr(__('Close', 'geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
            </div>
            <div class="geekybot-admin-popup-cnt">
                <form id="userStoryForm" class="geekybot-popup-form" method="post" enctype="multipart/form-data" action="#">
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-value">
                            <?php
                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('name', isset($data->name) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->name) : '', array('class' => 'inputbox geekybot-form-input-field', 'data-validation' => 'required', 'placeholder' => __('Story Name *', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS);
                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('type', 'ai_story'), GEEKYBOT_ALLOWED_TAGS);
                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('template', GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getTemplatesForCombobox(), isset($action->action_id) ? $action->action_id : '', esc_html(__('Select Template','geeky-bot')), array('class' => 'inputbox geekybot-form-select-field geeky_hide', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS);
                            ?>
                        </div>
                    </div>
                    <div class="geekybot-form-button">
                        <?php
                        echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Built Story','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block')),GEEKYBOT_ALLOWED_TAGS);
                        ?>
                    </div>
                    <div id="user-input-msg"></div>
                </form>
            </div>
        </div>
        <!-- import Story Popup -->
        <div id="importStoryPopup" class="geekybot-popup-wrapper geekybot-add-storypge-main-wrapper" style="display: none;">
            <div class="userpopup-top">
                <div class="userpopup-heading">
                    <?php echo esc_html(__('Import Story','geeky-bot')); ?>
                </div>
                <img title="<?php echo esc_html(__('Close','geeky-bot')); ?>" alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="importStoryPopupCloseBtn" title="<?php echo esc_attr(__('Close', 'geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
            </div>
            <div class="geekybot-admin-popup-cnt">
                <form enctype="multipart/form-data" id="importStoryForm" class="geekybot-popup-form geekybot-story-import" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_stories&task=geekybotImportStory"),"upload-xml-story")); ?>">
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('name', '', array('class' => 'inputbox geekybot-form-input-field', 'required' => 'required', 'placeholder' => __('Story Name *', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS) ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('type', 'ai_story'), GEEKYBOT_ALLOWED_TAGS); ?>
                        </div>
                    </div>
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-value">
                            <label for="xml_file" class="geekybot-custom-file-upload">
                                <?php echo esc_html(__('Choose File','geeky-bot')); ?>
                            </label>
                            <input type="file" name="xml_file" id="xml_file" accept=".xml" class="inputbox geekybot-form-input-field" required>
                        </div>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'stories_geekybotImportStory'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('task', ''), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <div class="geekybot-form-button">
                        <?php
                        echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Import Story','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block')),GEEKYBOT_ALLOWED_TAGS);
                        ?>
                    </div>
                </form>
            </div>
        </div>
        <!-- edit story form -->
        <div id="editStoryName" class="geekybot-popup-wrapper geekybot-add-storypge-main-wrapper" style="display: none;">
            <div class="userpopup-top">
                <div class="userpopup-heading">
                    <?php echo esc_html(__('Edit Story Name','geeky-bot')); ?>
                </div>
                <img title="<?php echo esc_html(__('Close','geeky-bot')); ?>" alt="<?php echo esc_html(__('Close','geeky-bot')); ?>" id="editStoryNamePopupCloseBtn" title="<?php echo esc_attr(__('Close', 'geeky-bot')); ?>" class="userpopup-close" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/close.png" />
            </div>
            <div class="geekybot-admin-popup-cnt">
                <form id="editStoryNameForm" class="geekybot-popup-form" method="post" enctype="multipart/form-data" action="#">
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('name', '', array('class' => 'inputbox geekybot-form-input-field', 'data-validation' => 'required', 'placeholder' => __('Story Name *', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS) ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', '0'), GEEKYBOT_ALLOWED_TAGS); ?>
                        </div>
                    </div>
                    <div class="geekybot-form-button">
                        <?php
                        echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot'), array('class' => 'button  geekybot-admin-pop-btn-block')),GEEKYBOT_ALLOWED_TAGS);
                        ?>
                    </div>
                    <div id="edit-story-name-form-msg"></div>
                </form>
            </div>
        </div>
    </div>
</div>
