<?php
if (!defined('ABSPATH')) die('Restricted Access');
$geekybot_js ="
    jQuery(document).ready(function ($) {
        $.validate();
    });";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper">
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
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_submitbutton('save', __('Save','geeky-bot'), array('class' => 'button geekybot-form-save-btn')), GEEKYBOT_ALLOWED_TAGS); ?>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset(geekybot::$_data[0]->id) ? geekybot::$_data[0]->id : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('status', isset(geekybot::$_data[0]->status) ? geekybot::$_data[0]->status : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'websearch_savewebsearch'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
    		    </form>
    		</div>
    	</div>
    </div>
</div>
