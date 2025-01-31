<?php
if (!defined('ABSPATH')) die('Restricted Access');
$geekybot_js ="
    jQuery(document).ready(function ($) {
        $.validate();
    });";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  wp_kses(GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'websearch','layouts' => 'formwebsearch')), GEEKYBOT_ALLOWED_TAGS); ?>
    <div class="geekybotadmin-body-main">
    	<!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
    	 	<?php  wp_kses(GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue',array('module' => 'websearch')), GEEKYBOT_ALLOWED_TAGS); ?>
     	</div>
        <div id="geekybotadmin-data" class="geekybotadmin-addVariable-data">
            <!-- top head -->
            <?php  wp_kses(GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagetitle',array('module' => 'websearch','layouts' => 'formwebsearch')), GEEKYBOT_ALLOWED_TAGS); ?>
         	<!-- page content -->
            <div id="geekybot-admin-wrapper">
    		    <form id="geekybot-form" class="geekybot-form" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_websearch&task=savewebsearch"),"save-websearch")); ?>">
                    <div class="geekybot-websearch-support-section">
                        <div class="geekybot-websearch-support-imgwrp">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/problem.png" title="<?php echo esc_attr(__('Help', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Help', 'geeky-bot')); ?>" class="geekybot-websearch-support-img">
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
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-title">
                            <?php echo esc_html(__('Post Type', 'geeky-bot')); ?>
                            <font class="required-notifier" style="color: red;">*</font>
                        </div>
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('post_type', isset(geekybot::$_data[0]->post_type) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->post_type) : '', array('class' => 'inputbox one geekybot-form-input-field', 'data-validation' => 'required', 'placeholder' => __('post type', 'geeky-bot'), 'readonly' => 'true')), GEEKYBOT_ALLOWED_TAGS) ?>
                        </div>
                    </div>
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-title">
                            <?php echo esc_html(__('Post Label', 'geeky-bot')); ?>
                            <font class="required-notifier" style="color: red;">*</font>
                        </div>
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('post_label', isset(geekybot::$_data[0]->post_label) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->post_label) : '', array('class' => 'inputbox one geekybot-form-input-field', 'data-validation' => 'required', 'placeholder' => __('post type', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS) ?>
                        </div>
                    </div>
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-title">
                            <?php echo esc_html(__('Associated Plugin', 'geeky-bot')); ?>
                        </div>
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('plugin_name', isset(geekybot::$_data[0]->plugin_name) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->plugin_name) : '', array('class' => 'inputbox two geekybot-form-input-field', 'data-validation' => '', 'placeholder' => __("Add associated plugin's name", 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS) ?>
                            <div class="geekybot-form-description">
                                <?php echo esc_html(__("For better data consistency, please provide a associated plugin's name.", 'geeky-bot')); ?>
                            </div>
                        </div>
                    </div>
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-title">
                            <?php echo esc_html(__('Status', 'geeky-bot')); ?>
                        </div>
                        <div class="geekybot-form-value">
                            <?php
                            if(geekybot::$_data[0]->status == 1){
                                $status = __('Active', 'geeky-bot');
                            } else {
                                $status = __('Disable', 'geeky-bot');
                            }
                            echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('type_status', $status, array('class' => 'inputbox one geekybot-form-input-field', 'data-validation' => 'required', 'placeholder' => __('post type', 'geeky-bot'), 'readonly' => 'true')), GEEKYBOT_ALLOWED_TAGS); ?>
                            <div class="geekybot-form-description">
                                <?php echo esc_html(__('For status changes, please use the options available in the listing.', 'geeky-bot')); ?>
                            </div>
                        </div>
                    </div>
                    <div class="geekybot-form-button">
                        <a id="form-cancel-button" class="geekybot-form-cancel-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_websearch'),'websearch')); ?>" title="<?php echo esc_attr(__('cancel', 'geeky-bot')); ?>">
                            <?php echo esc_html(__('Cancel', 'geeky-bot')); ?>
                        </a>
                        <button title="<?php echo esc_html(__('Save Changes', 'geeky-bot')); ?>" type="submit" class="geekybot-form-savevar-btnwrp">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" srcset="">
                            <?php echo __('Save','geeky-bot'); ?>
                        </button>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset(geekybot::$_data[0]->id) ? geekybot::$_data[0]->id : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('status', isset(geekybot::$_data[0]->status) ? geekybot::$_data[0]->status : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'websearch_savewebsearch'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
    		    </form>
    		</div>
            <?php 
            $custom_listing = '';
            if(in_array('customlistingstyle', geekybot::$_active_addons) || in_array('customtextstyle', geekybot::$_active_addons)){
                $all_meta_keys = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotGetAllMetaKeys(geekybot::$_data[0]->post_type);
            }
            if(in_array('customlistingstyle', geekybot::$_active_addons)){
                $custom_listing .= apply_filters('geekybot_custom_listing_style_form', geekybot::$_data[0], $all_meta_keys);
            }
            if(in_array('customtextstyle', geekybot::$_active_addons)){
                $custom_listing .= apply_filters('geekybot_custom_listing_text_form', geekybot::$_data[0], $all_meta_keys);
            }
            if ($custom_listing != '') {
                if (!empty(geekybot::$_data[0]->action_data)) {
                    $action_data_array = json_decode(geekybot::$_data[0]->action_data, true);
                } else {
                    $action_data_array = array();
                } ?>
                <div class="geekybot-template-section-heading"><?php echo esc_html(__('Template Section', 'geeky-bot')); ?></div>
                <div class="geekybot-template-section">
                    <?php
                    if (!empty($all_meta_keys)) { ?>
                        <form id="geekybot-custom-listing" class="geekybot-form" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_websearch&task=savecustomlisting"), "save-custom-listing")); ?>">
                            <?php
                            if (empty(geekybot::$_data[0]->style_id) && empty(geekybot::$_data[0]->text_id)) { ?>
                                <div class="geekybot-custom-listing-tmplate-infowrp">
                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/postinstallation/info.png" title="<?php echo esc_attr(__('Info', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Info', 'geeky-bot')); ?>" class="geekybot-custom-listing-tmplate-infoimg">
                                    <?php echo esc_html(__('Fields are currently auto-filled for preview purposes only. Please save your changes to apply them.', 'geeky-bot')); ?>
                                </div>
                                <?php
                            } ?>
                                <div class="geekybot-template-section-subtitle">
                                    <?php echo esc_html(__('Choose one template style from the given below', 'geeky-bot')); ?>
                               </div>
                            <?php
                            echo wp_kses($custom_listing, GEEKYBOT_ALLOWED_TAGS);
                            ?>
                            <div class="geekybot-template-section-title"><?php echo esc_html(__('Add Button /Link', 'geeky-bot')); ?></div>
                            <div class="geekybot-template-section-field geekybot-tmptform-button-section-fields">
                                <div class="geekybot-form-title">
                                    <?php echo esc_html(__('Add New Button / Link', 'geeky-bot')); ?> :
                                </div>
                                <div class="geekybot-tmptform-field-valuewrp geekybot-tmptform-button-valuewrp">
                                    <div class="geekybot-form-field-value">
                                        <div class="geekybot-form-field-enbledsable-wrp">
                                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_checkbox('action_enable', array('1' => esc_html(__('Show on Listing', 'geeky-bot'))), isset($action_data_array['action_enable']) ? $action_data_array['action_enable'] : 1, array('class' => 'radiobutton geekybot-form-field-enabled')), GEEKYBOT_ALLOWED_TAGS); ?>
                                        </div>
                                        <?php 
                                        $actionType = array(
                                            (object) array('id' => '1', 'text' => __('Button', 'geeky-bot')),
                                            (object) array('id' => '2', 'text' => __('Link', 'geeky-bot'))
                                        );
                                        ?>
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('action_type', $actionType, isset($action_data_array['action_type']) ? $action_data_array['action_type'] : 1, esc_html(__('Select Type', 'geeky-bot')), array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS); ?>
                                    </div>
                                    <div class="geekybot-form-label-value">
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('action_title', isset($action_data_array['action_title']) ? geekybot::GEEKYBOT_getVarValue($action_data_array['action_title']) :  esc_html(__( 'View Details', 'geeky-bot' )), array('class' => 'inputbox two geekybot-form-input-field', 'data-validation' => '', 'placeholder' => __('Label', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS) ?>
                                    </div>
                                    <div class="geekybot-form-field-value">
                                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('action_link', $all_meta_keys, isset($action_data_array['action_link']) ? $action_data_array['action_link'] : '', esc_html(__('detail page link', 'geeky-bot')), array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="geekybot-template-form-buttons-wrp">
                                <button title="<?php echo esc_html(__('Save Changes', 'geeky-bot')); ?>" type="submit" class="geekybot-form-savetem-btnwrp">
                                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" srcset="">
                                    <?php echo __('Save Template', 'geeky-bot'); ?>
                                </button>
                                <a id="form-cancel-button" class="geekybot-form-cancel-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_websearch'), 'websearch')); ?>" title="<?php echo esc_attr(__('cancel', 'geeky-bot')); ?>">
                                    <?php echo esc_html(__('Cancel', 'geeky-bot')); ?>
                                </a>
                            </div>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset(geekybot::$_data[0]->style_id) ? geekybot::$_data[0]->style_id : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('text_id', isset(geekybot::$_data[0]->text_id) ? geekybot::$_data[0]->text_id : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('template_id', isset(geekybot::$_data[0]->template_id) ? geekybot::$_data[0]->template_id : 1), GEEKYBOT_ALLOWED_TAGS); ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('post_type', isset(geekybot::$_data[0]->post_type) ? geekybot::$_data[0]->post_type : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'websearch_savewebsearch'), GEEKYBOT_ALLOWED_TAGS); ?>
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                        </form>
                        <?php
                        if(in_array('customlistingstyle', geekybot::$_active_addons)){
                            $custom_listing_style_popup = apply_filters('geekybot_custom_listing_style_popup', geekybot::$_data[0]->post_type);
                            echo wp_kses($custom_listing_style_popup, GEEKYBOT_ALLOWED_TAGS);
                        }
                    } else { ?>
                        <div class="geekybot-custom-listing-nodata-wrp">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/websearch/nodata.png" title="<?php echo esc_attr(__('Create a Post', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Create a Post', 'geeky-bot')); ?>" class="geekybot-websearch-creat-postimg">
                            <span class="geekybot-websearch-creat-posttitle">
                                <?php
                                if (isset(geekybot::$_data[0]->post_label)) {
                                    echo esc_html(__('Please create a', 'geeky-bot')) . " " .esc_html(geekybot::$_data[0]->post_label) . " " . esc_html(__('to use template section.', 'geeky-bot'));
                                } else {
                                    echo esc_html(__('Please create a post to use template section.', 'geeky-bot'));
                                } ?>        
                            </span>
                        </div>
                        <?php
                    }
                    $geekybot_js ="
                    jQuery(document).ready(function() {";

                        if (isset(geekybot::$_data[0]->template_id)){
                            if (in_array(geekybot::$_data[0]->template_id, [1, 2, 3])) {
                                $geekybot_js .= "
                                jQuery('.geekybot-template-section-block-two').hide();";
                            } else if ( in_array(geekybot::$_data[0]->template_id, [4, 5, 6]) ) {
                                $geekybot_js .= "
                                jQuery('.geekybot-template-section-block-one').hide();";
                            }
                        }
                        $geekybot_js .="
                        let selectedOption = null;
                        jQuery('.geekybot-template-section-tmpcard').click(function() {
                            jQuery('.geekybot-template-section-tmpcard').removeClass('geeky-bot-selectedtemp');
                            jQuery(this).addClass('geeky-bot-selectedtemp');
                            var template_id = jQuery(this).attr('data-templateid');
                            jQuery('#template_id').val(template_id);
                            if ( template_id == 1 || template_id == 2 || template_id == 3 ) {
                                jQuery('.geekybot-template-section-block-two').slideUp(1000);
                                jQuery('.geekybot-template-section-block-one').slideDown(1000);
                            } else if ( template_id == 4 || template_id == 5 || template_id == 6 ) {
                                jQuery('.geekybot-template-section-block-one').slideUp(1000);
                                jQuery('.geekybot-template-section-block-two').slideDown(1000);
                            }
                            selectedOption = jQuery(this).data('option');
                        });
                    });
                    ";
                    wp_add_inline_script('geekybot-main-js',$geekybot_js);
                    ?>
                </div>
                <?php
            } ?>
    	</div>
    </div>
</div>
