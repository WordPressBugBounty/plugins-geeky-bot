<?php
if (!defined('ABSPATH')) die('Restricted Access');
$c = GEEKYBOTrequest::GEEKYBOT_getVar('page',null,'geekybot');
$layout = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotlt');
?>
<div class="geekybot-chat-nav-toogle">
  <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/menu.png" alt="<?php echo esc_attr(__('user', 'geeky-bot')); ?>" >
    <span class="geekybotchat_text">
</span>
</div>
<ul class="geekybotadmin-sidebar-menu tree" data-widget="tree" role="tablist">
    <li class="treeview <?php if( ($c == 'geekybot' && $layout != 'themes' ) ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot','geeky-bot'))?>" title="<?php echo esc_attr(__('Dashboard' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Dashboard' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/dashboard.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Dashboard' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/dashboard-colored.png'; ?>" />
        </a>
    </li>
    <li class="geeky_hide treeview <?php if($layout == 'step1' ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_build&geekybotlt=step1','step1'))?>" title="<?php echo esc_attr(__('key check' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('keys' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/key.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('keys' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/key-colored.png'; ?>" />
        </a>
    </li>
    <!-- zywrap -->
    <li class="treeview <?php if($c == 'geekybot_zywrap' || $c == 'geekybot_zywraplogs' ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywrap&geekybotlt=playground','Zywrap'))?>" title="<?php echo esc_attr(__('Content Generation' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Content Generation' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/respose.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Content Generation' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/respose-colored.png'; ?>" />
        </a>
    </li>
    <!-- stories -->
    <li class="treeview <?php if(($c == 'geekybot_stories') ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_stories&geekybotlt=stories','Stories'))?>" title="<?php echo esc_attr(__('Stories' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Stories' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/story.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Stories' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/story-colored.png'; ?>" />
        </a>
    </li>
    <li class="treeview <?php if(($c == 'geekybot_websearch') ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_websearch','websearch'))?>" title="<?php echo esc_attr(__('AI Web Search' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('AI Web Search' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/search.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('AI Web Search' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/search-colored.png'; ?>" />
        </a>
    </li>
    <li class="treeview <?php if($c == 'geekybot_configuration' && $layout == 'configurations') echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=configurations','configuration'))?>" title="<?php echo esc_attr(__('Settings' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Settings' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/setting.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Settings' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/setting-colored.png'; ?>" />
        </a>
    </li>
    <li class="treeview <?php if($c == 'geekybot_configuration' && ($layout == 'ai_configurations' || $layout == 'dialogflow_configurations' || $layout == 'openai_configurations')) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_configuration&geekybotlt=ai_configurations','configuration'))?>" title="<?php echo esc_attr(__('AI Settings' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('AI' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/ai-setting.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Settings' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/ai-setting-colored.png'; ?>" />
        </a>
    </li>
    <li class="treeview <?php if($c == 'geekybot_themes' || ($c == 'geekybot' && $layout == 'themes') ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_themes','Chatbot'))?>" title="<?php echo esc_attr(__('Chatbot' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Chatbot' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/chatbot-white.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Chatbot' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/chatbot-blue.png'; ?>" />
        </a>
    </li>
    <li class="treeview <?php if($c == 'geekybot_premiumplugin' && $layout != 'addonstatus') echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_premiumplugin&geekybotlt=step1','PremiumAddons'))?>" title="<?php echo esc_attr(__('Install Add-ons' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Install Add-ons' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/adon-white.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Install Add-ons' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/adon-color.png'; ?>" />
        </a>
    </li>
    <li class="treeview <?php if($c == 'geekybot_premiumplugin' && $layout == 'addonstatus') echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_premiumplugin&geekybotlt=addonstatus','addonstatus'))?>" title="<?php echo esc_attr(__('Add-ons Status' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Add-ons Status' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/status-white.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Add-ons Status' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/status-color.png'; ?>" />
        </a>
    </li>
    <li class="geeky_hide treeview <?php if($c == 'geekybot_export' || ($c == 'geekybot' && $layout == 'export') ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_export','export'))?>" title="<?php echo esc_attr(__('export' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('export' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/export.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('export' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/export-colored.png'; ?>" />
        </a>
    </li>
    <li class="geeky_hide treeview <?php if($c == 'geekybot_import' || ($c == 'geekybot' && $layout == 'import') ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_import','import'))?>" title="<?php echo esc_attr(__('import' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('import' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/import.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('import' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/import-colored.png'; ?>" />
        </a>
    </li>
    <li class="treeview <?php if(($c == 'geekybot_chathistory') ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_chathistory','chat-history'))?>" title="<?php echo esc_attr(__('Chat History' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Chat History' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/history.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Chat History' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/history-colored.png'; ?>" />
        </a>
    </li>
    <li class="geeky_hide treeview <?php if($c == 'geekybot_build' && ($layout=='' || $layout!='step1')) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_build','build'))?>" title="<?php echo esc_attr(__('build' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Build' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/bulid.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Build' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/bulid-colored.png'; ?>" />
        </a>
    </li>

    <li class="treeview <?php if(($c == 'geekybot_slots') ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_slots','slots'))?>" title="<?php echo esc_attr(__('Variables' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Variables' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/variable.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Variables' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/variable-colored.png'; ?>" />
        </a>
    </li>
    <li class="geeky_hide treeview <?php if(($c == 'geekybot_forms')) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_forms','forms'))?>" title="<?php echo esc_attr(__('forms' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Forms' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/action.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Forms' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/action-colored.png'; ?>" />
        </a>
    </li>
    <li class="geeky_hide treeview <?php if(($c == 'geekybot_action') ) echo esc_attr('active'); ?>">
        <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_action','actions'))?>" title="<?php echo esc_attr(__('actions' , 'geeky-bot')); ?>">
            <img class="geekybotadmin-menu-icon" alt="<?php echo esc_attr(__('Actions' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/action.png'; ?>" />
            <img class="geekybotadmin-menu-icon geekybotadmin-menu-icon-active" alt="<?php echo esc_attr(__('Actions' , 'geeky-bot')); ?>" src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL).'includes/images/control_panel/admin-left-menu/action-colored.png'; ?>" />
        </a>
    </li>
</ul>
<div id="geekybotadmin_black_wrapper_built_loading" style="display: none;" ></div>
<div class="geekybotadmin-built-story-loading" id="geekybotadmin_built_loading" style="display: none;" >
    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/spinning-wheel.gif" />
    <div class="geekybotadmin-built-story-loading-text">
        <?php echo esc_html(__('Please wait a moment; this may take some time.','geeky-bot')); ?>
    </div>
</div>
<?php
$geekybot_js ="
    jQuery(document).ready(function () {
        jQuery('.geekybot-chat-nav-toogle').click(function(){
            jQuery('.geekybotadmin-sidebar-menu').toggle();
        });
    });
";
wp_add_inline_script('geekybot-main-js',$geekybot_js);
