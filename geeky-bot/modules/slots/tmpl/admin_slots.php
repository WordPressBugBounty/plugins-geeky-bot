<?php
    if (!defined('ABSPATH'))
        die('Restricted Access');
    $geekybot_js ='
    function resetFrom() {
        jQuery("input#slotsname").val("");
        jQuery("select#status").val("");

        jQuery("form#geekybotform").submit();
    }';
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
    if (!GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/header',array('module' => 'slots'))){
        return;
    }
?>
<!-- main wrapper -->
<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav',array('module' => 'slots','layouts' => 'slots')); ?>
    <div class="geekybotadmin-body-main">
        <!-- left menu -->
        <div id="geekybotadmin-leftmenu-main">
            <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue',array('module' => 'slots')); ?>
        </div>
        <div id="geekybotadmin-data" class="geekybotadmin-variable-data">
             <!-- top head -->
             <?php  GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagetitle',array('module' => 'slots','layouts' => 'slots')); ?>
            <!-- filter form -->
            <form class="geekybot-filter-form" name="geekybotform" id="geekybotform" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_slots"),"slots")); ?>">
                <div id="geekybot-searchbar" class="geekybot-searchbar-btn">
                    <div class="window-two-btm-inner geekybot-story-inner">
                        <button title="<?php echo esc_html(__('Search', 'geeky-bot')); ?>" type="submit" name="btnsubmit" id="btnsubmit" value="Search" class="button geekybot-form-search-btn"><img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/loupe.png" alt="<?php echo esc_attr(__('Search', 'geeky-bot')); ?>" class="geekybot-action-img"></button>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_text('slotsname', geekybot::$_data['filter']['slotsname'], array('class' => 'inputbox geekybot-form-input-field', 'placeholder' => esc_attr(__('Search', 'geeky-bot')))), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('GEEKYBOT_form_search', 'GEEKYBOT_SEARCH'), GEEKYBOT_ALLOWED_TAGS); ?>
                    </div>
                    <div id="geekybot-reset-btn-main" >
                        <a class="geekybot-Intents-reset-btn" href="javascript:resetFrom();" title="<?php echo esc_attr(__('Reset', 'geeky-bot')); ?>">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/reset.png" alt="<?php echo esc_attr(__('Reset', 'geeky-bot')); ?>" />
                        </a>
                    </div>
                </div>
                <div class="geekybot-head-btn geekybot-variable-head-btn">
                    <div id="geekybot-page-quick-actions-delete" >
                        <a class="geekybot-Intents-btn multioperation" message="<?php echo esc_attr(GEEKYBOTMessages::GEEKYBOT_getMSelectionEMessage()); ?>" confirmmessage="<?php echo esc_attr(__("Are you sure to delete it?",'geeky-bot')); ?>" data-for="remove" href="#" title="<?php echo esc_attr(__('Delete Variable', 'geeky-bot')); ?>">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/delete.png" alt="<?php echo esc_attr(__('Delete', 'geeky-bot')); ?>" />
                            <?php echo esc_html(__('Delete Variable','geeky-bot')) ?>
                        </a>
                    </div>
                    <div id="geekybot-page-quick-actions-add">
                        <a class="geekybot-Intents-btn" href="<?php echo esc_url(admin_url('admin.php?page=geekybot_slots&geekybotlt=formslots')) ?>" title="<?php echo esc_attr(__('Add Variable', 'geeky-bot')); ?>">
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/plus-white.png" alt="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" />
                            <?php echo esc_html(__('Add Variable','geeky-bot')) ?>
                        </a>
                    </div>
                </div>
            </form>
            <!-- top bar -->
            <div id="geekybot-wrapper-top">
            </div>
            <!-- page content -->
            <div id="geekybot-admin-wrapper" class="p0 bg-n bs-n">
                <!-- quick actions -->
                <?php
                if (!empty(geekybot::$_data[0])) {
                    ?>
                    <form id="geekybot-list-form" method="post" action="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=geekybot_slots"),"delete-slots")); ?>">
                        <?php
                            $pagenum = GEEKYBOTrequest::GEEKYBOT_getVar('pagenum', 'get', 1);
                            $pageid = ($pagenum > 1) ? '&pagenum=' . $pagenum : '';
                            foreach (geekybot::$_data[0] AS $row) { ?>
                                <div class="geekybot-listing-section">
                                    <div class="geekybot-listing-heading">
                                        <input type="checkbox" class="geekybot-cb" id="geekybot-cb" name="geekybot-cb[]" value="<?php echo esc_attr($row->id); ?>" />
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot_slots&geekybotlt=formslots&geekybotid='.esc_attr($row->id))); ?>" title="<?php echo esc_attr(__('name','geeky-bot')); ?>">
                                            <?php echo esc_html(geekybot::GEEKYBOT_getVarValue($row->name)); ?>
                                        </a>
                                    </div>
                                    <div class="geekybot-text-left">
                                        <span class="geekybot-listing-subheading">
                                            <?php echo esc_html(__('Type', 'geeky-bot')); ?>:
                                        </span>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot_slots&geekybotlt=formslots&geekybotid='.esc_attr($row->id))); ?>" title="<?php echo esc_attr(__('type','geeky-bot')); ?>">
                                            <?php echo esc_html(geekybot::GEEKYBOT_getVarValue($row->type)); ?>
                                        </a>
                                    </div>
                                    <div class="geekybot-listing-subheading">
                                        <?php echo esc_html(__('Possible Values Here','geeky-bot')); ?>:
                                        <span class="geekybot-text-left geekybot-possible-value">
                                        <?php echo esc_html(geekybot::GEEKYBOT_getVarValue($row->possible_values)); ?>
                                    </span>
                                    </div>

                                    <div class="geekybot-listing-button-wrp">
                                        <a class="geekybot-table-act-btn geekybot-edit" href="<?php echo esc_url(admin_url('admin.php?page=geekybot_slots&geekybotlt=formslots&geekybotid='.esc_attr($row->id))); ?>" title="<?php echo esc_attr(__('Edit Variable', 'geeky-bot')); ?>">
                                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/edit.png" alt="<?php echo esc_attr(__('Edit', 'geeky-bot')); ?>" class="geekybot-action-img">
                                            <?php echo esc_html(__('Edit Variable','geeky-bot')); ?>
                                        </a>
                                        <a class="geekybot-table-act-btn geekybot-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_slots&task=remove&action=geekybottask&geekybot-cb[]='.esc_attr($row->id)),'delete-slots')); ?>" onclick="return confirmdelete('<?php echo esc_attr(__("Are you sure to delete it", 'geeky-bot')) .'?'; ?>');" title="<?php echo esc_attr(__('Delete Variable', 'geeky-bot')); ?>">
                                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/control_panel/delete.png" alt="<?php echo esc_attr(__('Delete', 'geeky-bot')); ?>" class="geekybot-action-img">
                                            <?php echo esc_html(__('Delete Variable','geeky-bot')); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php
                            }
                        ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('action', 'slots_removeslots'), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('pagenum', ($pagenum > 1) ? $pagenum : ''), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('task', ''), GEEKYBOT_ALLOWED_TAGS); ?>
                        <?php echo wp_kses(GEEKYBOTformfield::GEEKYBOT_hidden('form_request', 'geekybot'), GEEKYBOT_ALLOWED_TAGS); ?>
                    </form>
                    <?php
                    if (geekybot::$_data[1]) {
                        GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/pagination',array('module' => 'slots' , 'pagination' => geekybot::$_data[1]));
                    }
                } else {
                    $msg = __('No record found','geeky-bot');
                    $link[] = array(
                                'link' => 'admin.php?page=geekybot_slots&geekybotlt=formslots',
                            'text' => __('Add New','geeky-bot') .'&nbsp;'. __('Variable','geeky-bot')
                        );
                    echo wp_kses(GEEKYBOTlayout::GEEKYBOT_getNoRecordFound($msg,$link), GEEKYBOT_ALLOWED_TAGS);
                }
                ?>
            </div>
        </div>
</div>
</div>
<?php
    $geekybot_js ="
    function highlight(){
        jQuery('#geekybot-list-form').toggleClass('geekybot-intent-blue');
    }";
    wp_add_inline_script('geekybot-main-js',$geekybot_js);
?>
