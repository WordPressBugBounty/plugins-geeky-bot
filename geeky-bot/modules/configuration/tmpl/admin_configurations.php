<?php
if (!defined('ABSPATH'))
    die('Restricted Access');
$msgkey = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getMessagekey();
GEEKYBOTMessages::GEEKYBOT_getLayoutMessage($msgkey);
$geekybot_js ="
jQuery(document).ready(function() {
    jQuery(document).on('change', 'select.response-btn-type', function() {
        jQuery('.geeky-popup-dynamic-field').removeClass('geeky-popup-dynamic-field-active');
        jQuery(this).closest('.geeky-popup-dynamic-field').addClass('geeky-popup-dynamic-field-active');

        var type = jQuery(this).val();
        if(type == '1') {
            jQuery('.geeky-popup-dynamic-field-active .response-btn-url').css('display', 'none');
            jQuery('.geeky-popup-dynamic-field-active .response-btn-value').css('display', 'block');
        } else {
            jQuery('.geeky-popup-dynamic-field-active .response-btn-value').css('display', 'none');
            jQuery('.geeky-popup-dynamic-field-active .response-btn-url').css('display', 'block');
        }
    });
    jQuery(document).on('click', '.geekybot-config-save-btn', function() {
        geekybotShowLoading();
    });
});
// Function to create fallback buttons
var fallbackButtonDivId = 1;
function addFallBackButton(param) {
    if (fallbackButtonDivId != param && fallbackButtonDivId < param) {
        fallbackButtonDivId = param;
    }
    var container = jQuery('.geekybot-fallback-config-mainwrp');
    container.append('<div id=\"div_'+fallbackButtonDivId+'\" class=\"geekybot-fallback-config\"><span class=\"geekybot-fallback-configlft-titl\">". esc_attr(__('Default Fallback Action','geeky-bot')) ."</span><div class=\"geekybot-fallback-configlft-wrp\"><div class=\"geekybot-fallback-config-fields-wrp\"><select name=\"fallback_btn_type[]\" class=\"response-btn-type inputbox geeky-popup-dynamic-field-input geeky-popup-dynamic-field-select\" data-validation=\"required\"><option value=\"1\">". esc_attr(__('User Input','geeky-bot')) ."</option><option value=\"2\">". esc_attr(__('URL','geeky-bot')) ."</option></select><input name = \"fallback_btn_text[]\" type=\"text\" value = \"\" class=\"inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Button text here','geeky-bot')) ."\" /><input name = \"fallback_btn_value[]\" type=\"text\" value = \"\" class=\"response-btn-value inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Button value here','geeky-bot')) ."\" /><input name = \"fallback_btn_url[]\" type =\"text\" value = \"\" class=\"response-btn-url inputbox geeky-popup-dynamic-field-input\" autocomplete=\"off\" placeholder=\"". esc_attr(__('Enter URL here','geeky-bot')) ."\" style=\"display: none\" /></div><a onClick=\"deleteFallBackTextBotton(div_'+fallbackButtonDivId+')\" class=\"geekybot-fallback-configright-wrp\"><img src=\"". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/control_panel/delete.png\" alt=\"". esc_attr(__('Delete', 'geeky-bot')) ."\" /></a></div></div>');
    fallbackButtonDivId++;
}
function deleteFallBackTextBotton(fallbackButtonId){
    jQuery(fallbackButtonId).slideUp(500, function() {
        // Remove the button after it slides up
        jQuery(fallbackButtonId).remove();
    });
}
";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'configuration','layouts' => 'configurations')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
           <?php  GEEKYBOTincluder::GEEKYBOT_getClassesInclude('geekybotadminsidemenu'); ?>
        </div>
        <div id="geekybotadmin-data">
            <!-- top head -->
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('Settings', 'geeky-bot')); ?>
                </h1>
            </div>
            <!-- page content -->
            <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper">
                <form id="geekybot-form" class="geekybot-configurations" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_configuration&task=saveconfiguration"),"save-configuration")); ?>">
                    <div class="geekybot-config-row-wrp">
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Title', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('title', geekybot::$_data[0]['title'], array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                echo esc_html(__("The title of your chatbot or the primary title displayed on your chat interface.", "geeky-bot"));
                                ?>
                            </div>
                        </div>
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Offline', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('offline', array((object) array('id' => '1', 'text' => esc_html(__('Offline', 'geeky-bot'))), (object) array('id' => '2', 'text' => esc_html(__('Online', 'geeky-bot')))), isset(geekybot::$_data[0]['offline']) ? geekybot::$_data[0]['offline'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php echo esc_html(__('Set your plugin offline for front end.', 'geeky-bot')); ?>
                            </div>
                        </div>
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Data Directory', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('data_directory', geekybot::$_data[0]['data_directory'], array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS);?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php
                                $maindir = wp_upload_dir();
                                $basedir = $maindir['basedir'];
                                echo esc_html(__('The directory where all chatbot data files are stored.', 'geeky-bot')); echo  wp_kses('<br/><b>"', GEEKYBOT_ALLOWED_TAGS).esc_url($basedir).'/'.esc_html(geekybot::$_data[0]['data_directory']).'"</b>';
                                ?>
                            </div>
                        </div>
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('Admin Pagination', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('pagination_default_page_size', geekybot::$_data[0]['pagination_default_page_size'], array('class' => 'inputbox', 'pattern' => '^[0-9]+$')),GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php echo esc_html(__("The number of items displayed in a paginated list in the admin panel.", 'geeky-bot')); ?>
                            </div>
                        </div>
                        <div class="geekybot-config-row">
                            <div class="geekybot-config-title">
                                <?php echo esc_html(__('User Pagination', 'geeky-bot')); ?>
                            </div>
                            <div class="geekybot-config-value">
                                <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('pagination_product_page_size', geekybot::$_data[0]['pagination_product_page_size'], array('class' => 'inputbox', 'pattern' => '^[0-9]+$')),GEEKYBOT_ALLOWED_TAGS); ?>
                            </div>
                            <div class="geekybot-config-description">
                                <?php echo esc_html(__("The number of items displayed in a paginated list on the user side.", 'geeky-bot')); ?>
                            </div>
                        </div>
                    </div>
                    <div class="geekybot-fallback-config-wrp">
                        <span class="geekybot-admin-fallback-config-title"><?php echo esc_html(__('Default Fallback Intent', 'geeky-bot')); ?></span>
                        <div class="geekybot-config-row-wrp">
                            <div class="geekybot-config-row">
                                <div class="geekybot-config-title">
                                    <?php echo esc_html(__('Default Fallback Message', 'geeky-bot')); ?>
                                </div>
                                <div class="geekybot-config-value-text">
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_textarea('default_message', isset(geekybot::$_data[0]['default_message']) ? geekybot::GEEKYBOT_getVarValue(geekybot::$_data[0]['default_message']) : '', array('class' => 'inputbox js-textarea', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS) ?>
                                </div>
                                <div class="geekybot-config-description">
                                    <?php
                                    echo esc_html(__("The message displayed when the chatbot cannot understand.", 'geeky-bot'));
                                    ?>
                                </div>
                            </div>
                            <div class="geekybot-config-row">
                                <div class="geekybot-config-title">
                                    <?php echo esc_html(__('Default Fallback Action Button', 'geeky-bot')); ?>
                                </div>
                                <?php $fallbackButtonDivId = !empty(geekybot::$_data[0]['default_message_buttons']) ? count(json_decode(geekybot::$_data[0]['default_message_buttons'])) + 1 : 1; ?>
                                <div class="geekybot-config-value">
                                    <div class="geeky-popup-dynamic-field">
                                        <div class="geeky-add-new-fallback-intentbtn">
                                            <a onclick="addFallBackButton('<?php echo $fallbackButtonDivId ?>');" href="#" title="<?php echo esc_attr(__('Add New Intent','geeky-bot')) ?>">
                                                <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/plus-white.png" alt="<?php echo esc_attr(__('Add', 'geeky-bot')); ?>" />
                                                <?php echo esc_attr(__('Add New Fallback Intent','geeky-bot')) ?>
                                            </a>
                                        </div>
                                        <div class="geekybot-fallback-config-description">
                                            <?php
                                            echo esc_html(__("Customizes the action button displayed with the fallback message when the chatbot doesn't understand user input.", 'geeky-bot'));
                                            ?>
                                        </div>
                                        <div class="geekybot-fallback-config-mainwrp">
                                            <?php
                                            if (!empty(geekybot::$_data[0]['default_message_buttons']) && geekybot::$_data[0]['default_message_buttons'] != '[]') {
                                                $buttons = json_decode(geekybot::$_data[0]['default_message_buttons']);
                                                foreach ($buttons as $fallback_button){
                                                    if (isset($fallback_button->type) && $fallback_button->type == 2) {
                                                        $optionOneSelected = '';
                                                        $optionTwoSelected = 'selected="selected"';
                                                        $optionOneStyle = 'style="display:none"';
                                                        $optionTwoStyle = 'style="display:block"';
                                                    } else {
                                                        $optionOneSelected = 'selected="selected"';
                                                        $optionTwoSelected = '';
                                                        $optionOneStyle = 'style="display:block"';
                                                        $optionTwoStyle = 'style="display:none"';
                                                    }
                                                    ?>
                                                    <div id="div_<?php echo $fallbackButtonDivId; ?>" class="geekybot-fallback-config">
                                                        <span class="geekybot-fallback-configlft-titl"><?php echo esc_attr(__('Default Fallback Action','geeky-bot')) ?></span>
                                                        <div class="geekybot-fallback-configlft-wrp">
                                                            <div class="geekybot-fallback-config-fields-wrp">
                                                                <select name="fallback_btn_type[]" class="response-btn-type inputbox geeky-popup-dynamic-field-input geeky-popup-dynamic-field-select" data-validation="required">
                                                                    <option value="1" <?php echo $optionOneSelected ?> ><?php echo esc_attr(__('User Input','geeky-bot')) ?></option>
                                                                    <option value="2" <?php echo $optionTwoSelected ?> ><?php echo esc_attr(__('URL','geeky-bot')) ?></option>
                                                                </select>
                                                                <input name = "fallback_btn_text[]" type="text" value = "<?php echo isset($fallback_button->text) ? $fallback_button->text : ''; ?>" class="inputbox geeky-popup-dynamic-field-input" autocomplete="off" placeholder="<?php echo esc_attr(__('Button text here','geeky-bot')) ?>" />
                                                                <input name = "fallback_btn_value[]" type="text" value = "<?php echo isset($fallback_button->value) ? $fallback_button->value : ''; ?>" class="response-btn-value inputbox geeky-popup-dynamic-field-input" autocomplete="off" placeholder="<?php echo esc_attr(__('Button value here','geeky-bot')) ?>" <?php echo $optionOneStyle ?> />
                                                                <input name = "fallback_btn_url[]" type="text" value = "<?php echo isset($fallback_button->value) ? $fallback_button->value : ''; ?>" class="response-btn-url inputbox geeky-popup-dynamic-field-input" autocomplete="off" placeholder="<?php echo esc_attr(__('Enter URL here','geeky-bot')) ?>" <?php echo $optionTwoStyle ?>  />
                                                            </div>
                                                            <a onClick="deleteFallBackTextBotton(div_<?php echo $fallbackButtonDivId; ?>)" class="geekybot-fallback-configright-wrp">
                                                                <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/delete.png" alt="<?php echo esc_attr(__('Delete', 'geeky-bot')); ?>" />
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    $fallbackButtonDivId++;
                                                }
                                            } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- <div> -->
                    </div>
                    <div class="geekybot-fallback-config-wrp">
                        <span class="geekybot-admin-fallback-config-title"><?php echo esc_html(__('Support Link', 'geeky-bot')); ?></span>
                        <div class="geekybot-config-row-wrp">
                            <div class="geekybot-config-row">
                                <div class="geekybot-config-title">
                                    <?php echo esc_html(__('Show Support Link', 'geeky-bot')); ?>
                                </div>
                                <div class="geekybot-config-value">
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_select('show_support_link', array((object) array('id' => '1', 'text' => esc_html(__('Show', 'geeky-bot'))), (object) array('id' => '2', 'text' => esc_html(__('Hide', 'geeky-bot')))), isset(geekybot::$_data[0]['show_support_link']) ? geekybot::$_data[0]['show_support_link'] : '', '', array('class' => 'inputbox geekybot-form-select-field', 'data-validation' => 'required')), GEEKYBOT_ALLOWED_TAGS); ?>
                                </div>
                                <div class="geekybot-config-description">
                                    <?php
                                    echo esc_html(__("Toggle to show or hide the support link for users.", 'geeky-bot'));
                                    ?>
                                </div>
                            </div>
                            <div class="geekybot-config-row">
                                <div class="geekybot-config-title">
                                    <?php echo esc_html(__('Support Link URL', 'geeky-bot')); ?>
                                </div>
                                <div class="geekybot-config-value">
                                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('support_link_url', geekybot::$_data[0]['support_link_url'], array('class' => 'inputbox')),GEEKYBOT_ALLOWED_TAGS); ?>
                                </div>
                                <div class="geekybot-config-description">
                                    <?php
                                    echo esc_html(__("Set the URL where users will be redirected when they click the support link.", 'geeky-bot'));
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('isgeneralbuttonsubmit', 1), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('geekybotlt', 'configurations'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'configuration_saveconfiguration'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    <div class="geekybot-config-btn">
                        <button title="<?php echo esc_html(__('Save Settings', 'geeky-bot')); ?>" type="submit"class="button geekybot-config-save-btn">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/story/save.png" alt="<?php echo esc_html(__('Add Icon', 'geeky-bot')); ?>" srcset="">
                            <?php echo esc_html(__('Save Settings', 'geeky-bot')); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
