<?php
if (!defined('ABSPATH')) die('Restricted Access');
$typelist = array(
    (object) array('id' => '', 'text' => __('Select Type', 'geeky-bot')),
    (object) array('id' => 'PersonName', 'text' => __('PersonName', 'geeky-bot')),
    (object) array('id' => 'Facility', 'text' => __('Facility', 'geeky-bot')),
    (object) array('id' => 'Organization', 'text' => __('Organization', 'geeky-bot')),
    (object) array('id' => 'Location', 'text' => __('Location', 'geeky-bot')),
    (object) array('id' => 'Location_NON_GPE', 'text' => __('Location_NON_GPE', 'geeky-bot')),
    (object) array('id' => 'Product', 'text' => __('Product', 'geeky-bot')),
    (object) array('id' => 'Event', 'text' => __('Event', 'geeky-bot')),
    (object) array('id' => 'ArtWork', 'text' => __('ArtWork', 'geeky-bot')),
    (object) array('id' => 'LawDocument', 'text' => __('LawDocument', 'geeky-bot')),
    (object) array('id' => 'Language', 'text' => __('Language', 'geeky-bot')),
    (object) array('id' => 'Date', 'text' => __('Date', 'geeky-bot')),
    (object) array('id' => 'Time', 'text' => __('Time', 'geeky-bot')),
    (object) array('id' => 'Percentage', 'text' => __('Percentage', 'geeky-bot')),
    (object) array('id' => 'Money', 'text' => __('Money', 'geeky-bot')),
    (object) array('id' => 'Quantity', 'text' => __('Quantity', 'geeky-bot')),
    (object) array('id' => 'Ordinal', 'text' => __('Ordinal', 'geeky-bot')),
    (object) array('id' => 'Cardinal', 'text' => __('Cardinal', 'geeky-bot')),
);
$forlist = array(
    (object) array('id' => '', 'text' => __('Select Variable For', 'geeky-bot')),
    (object) array('id' => 'product', 'text' => __('Product', 'geeky-bot')),
    (object) array('id' => 'attribute', 'text' => __('Attribute', 'geeky-bot'))
);
$geekybot_js ="
    jQuery(document).ready(function ($) {
        $.validate();
    });";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  wp_kses(GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'slots','layouts' => 'formslots')), GEEKYBOT_ALLOWED_TAGS); ?>
    <div class="geekybotadmin-body-main">
    	<!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
    	 	<?php  wp_kses(GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue',array('module' => 'slots')), GEEKYBOT_ALLOWED_TAGS); ?>
     	</div>
        <div id="geekybotadmin-data" class="geekybotadmin-addVariable-data">
            <!-- top head -->
         <?php  wp_kses(GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagetitle',array('module' => 'slots','layouts' => 'formslots')), GEEKYBOT_ALLOWED_TAGS); ?>
         	<!-- page content -->
            <div id="geekybot-admin-wrapper">
    		    <form id="geekybot-form" class="geekybot-form" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_slots&task=saveslots"),"save-slots")); ?>">
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-title">
                            <?php echo esc_html(__('Name', 'geeky-bot')); ?>
                            <font class="required-notifier" style="color: red;">*</font>
                        </div>
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('name', isset(geekybot::$_data[0]->name) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->name) : '', array('class' => 'inputbox one geekybot-form-input-field', 'data-validation' => 'required', 'placeholder' => __('Varibale Name', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS) ?>
                        </div>
                    </div>
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-title">
                            <?php echo esc_html(__('Type', 'geeky-bot')); ?>
                            <font class="required-notifier" style="color: red;">*</font>
                        </div>
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('type', $typelist, isset(geekybot::$_data[0]->type) ? geekybot::$_data[0]->type : '', null, array('class' => 'inputbox geekybot-form-select-field','data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                        </div>
                    </div>
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-title">
                            <?php echo esc_html(__('Possible Field Values', 'geeky-bot')); ?>
                        </div>
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('possible_values', isset(geekybot::$_data[0]->possible_values) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]->possible_values) : '', array('class' => 'inputbox two geekybot-form-input-field', 'data-validation' => '', 'placeholder' => __('Add Comma Seprated Values', 'geeky-bot'))), GEEKYBOT_ALLOWED_TAGS) ?>
                        </div>
                    </div>
                    <div class="geekybot-form-wrapper">
                        <div class="geekybot-form-title">
                            <?php echo esc_html(__('Variable For', 'geeky-bot')); ?>
                        </div>
                        <div class="geekybot-form-value">
                            <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('variable_for', $forlist, isset(geekybot::$_data[0]->variable_for) ? geekybot::$_data[0]->variable_for : '', null, array('class' => 'inputbox geekybot-form-select-field')), GEEKYBOT_ALLOWED_TAGS); ?>
                            <div class="geekybot-slot-form-description">
                                <?php echo esc_html(__('Select Variable For in case of Woocommerce Variable', 'geeky-bot')); ?>
                            </div>
                        </div>
                    </div>
                    <div class="geekybot-form-button">
                        <a id="form-cancel-button" class="geekybot-form-cancel-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_slots'),'slots')); ?>" title="<?php echo esc_attr(__('cancel', 'geeky-bot')); ?>">
                            <?php echo esc_html(__('Cancel', 'geeky-bot')); ?>
                        </a>
                        <button title="<?php echo esc_html(__('Save Changes', 'geeky-bot')); ?>" type="submit" class="geekybot-form-savevar-btnwrp">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" srcset="">
                            <?php echo  __('Save Variable', 'geeky-bot'); ?>
                        </button>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('id', isset(geekybot::$_data[0]->id) ? geekybot::$_data[0]->id : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'slots_saveslots'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
    		    </form>
    		</div>
    	</div>
    </div>
</div>
