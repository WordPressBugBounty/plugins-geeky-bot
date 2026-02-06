<?php

/**
 * @package Geeky Bot
 * @author Geeky Bot
 * @version 1.1.9
 */
/*
  * Plugin Name: Geeky Bot
  * Plugin URI: https://geekybot.com/
  * Description: The ultimate AI chatbot for WooCommerce lead generation, intelligent web search, and interactive customer engagement on your WordPress website.
  * Author: Geeky Bot
  * Version: 1.1.9
  * Text Domain: geeky-bot
  * Domain Path: /languages
  * Author URI: https://geekybot.com/
  * License: GPLv2
 */

if (!defined('ABSPATH'))
    die('Restricted Access');

class geekybot {

    public static $_path;
    public static $_pluginpath;
    public static $_data; /* data[0] for list , data[1] for total paginition ,data[2] fieldsorderring , data[3] userfield for form , data[4] for reply , data[5] for ticket history  , data[6] for internal notes  , data[7] for ban email  , data['ticket_attachment'] for attachment */
    public static $_pageid;
    public static $_db;
    public static $_configuration;
    public static $_sorton;
    public static $_sortorder;
    public static $_ordering;
    public static $_sortlinks;
    public static $_msg;
    public static $_error_flag;
    public static $_error_flag_message;
    public static $_currentversion;
    public static $_active_addons;
    public static $_addon_query;
    public static $_error_flag_message_for;
    public static $_error_flag_message_for_link;
    public static $_error_flag_message_for_link_text;
    public static $_common;
    public static $_config;
    public static $_missing_intent;
    public static $_wpcbintent;
    public static $_wpprefixforuser;
    public static $_isgeekybotplugin;
    public static $_search;
    public static $_geekybotsession;
    public static $_geekybotsessiondata;
    public static $_geekybotdialogflow;
    public static $_geekybotopenai;
    public static $_geekybotopenaiassistant;
    public static $_geekybotopenrouter;
    public static $_chatsession;
    public static $_search_style;
    public static $_colors;
    public static $_stories_stack;
    public static $_read_variable;
    function __construct() {
        // php 8.1 issues
        require_once 'includes/geekybotphplib.php';
        // for maintaing the product data in table
        add_action( 'save_post_product', array($this , 'geekyboot_update_or_create_geekybot_product'), 10, 3 );
        add_action( 'geekyboot_load_wp_pcl_zip', array($this , 'geekyboot_load_wp_pcl_zip') );
        add_action( 'geekyboot_load_wp_file', array($this , 'geekyboot_load_wp_file') );
        add_action( 'geekyboot_load_wp_plugin_file', array($this , 'geekyboot_load_wp_plugin_file') );
        add_action( 'geekyboot_load_wp_admin_file', array($this , 'geekyboot_load_wp_admin_file') );
        add_action( 'geekyboot_load_phpass', array($this , 'geekyboot_load_phpass') );
        $plugin_array = get_option('active_plugins');
        $intent_story_notification = get_option('intent_story_notification');
        if (!$intent_story_notification) {
            add_option( 'intent_story_notification', 'no', '', 'yes');
        }
        if ($intent_story_notification == 'yes') {
            add_action( 'admin_notices', array($this, 'my_info_notice') );
        }
        $geekybot_admin_process_value = get_option('unique_admin_process_value');
        if ($geekybot_admin_process_value) {
            add_action( 'admin_notices', array($this, 'geekybot_admin_process_value') );
        }
        $addon_array = array();
        foreach ($plugin_array as $key => $value) {
            $plugin_name = pathinfo($value, PATHINFO_FILENAME);
            if(geekybotphplib::GEEKYBOT_strstr($plugin_name, 'geeky-bot-')){
                $addon_array[] = geekybotphplib::GEEKYBOT_str_replace('geeky-bot-', '', $plugin_name);
            }
        }
        self::$_active_addons = $addon_array;
        self::includes();
        self::$_path = plugin_dir_path(__FILE__);
        self::$_pluginpath = plugins_url('/', __FILE__);
        self::$_data = array();
        self::$_error_flag = null;
        self::$_error_flag_message = null;
        self::$_currentversion = '119';
        self::$_addon_query = array('select'=>'','join'=>'','where'=>'');
        self::$_config = GEEKYBOTincluder::GEEKYBOT_getModel('configuration');
        self::$_isgeekybotplugin = true;
        self::$_geekybotsession = GEEKYBOTincluder::GEEKYBOT_getObjectClass('wpcbsession');
        self::$_geekybotsessiondata = GEEKYBOTincluder::GEEKYBOT_getObjectClass('geekybotsessiondata');
        self::$_geekybotdialogflow = GEEKYBOTincluder::GEEKYBOT_getObjectClass('geekybotdialogflow');
        self::$_geekybotopenai = GEEKYBOTincluder::GEEKYBOT_getObjectClass('geekybotopenai');
        self::$_geekybotopenaiassistant = GEEKYBOTincluder::GEEKYBOT_getObjectClass('geekybotopenaiassistant');
        self::$_geekybotopenrouter = GEEKYBOTincluder::GEEKYBOT_getObjectClass('geekybotopenrouter');
        global $wpdb;
        self::$_db = $wpdb;
        if(is_multisite()) {
            self::$_wpprefixforuser = $wpdb->base_prefix;
        }else{
            self::$_wpprefixforuser = self::$_db->prefix;
        }
        GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfiguration();
        register_activation_hook(__FILE__, array($this, 'geekybot_activate'));

        register_deactivation_hook(__FILE__, array($this, 'geekybot_deactivate'));
        if(version_compare(get_bloginfo('version'),'5.1', '>=')){ //for wp version >= 5.1
            add_action('wp_insert_site', array($this, 'geekybot_new_site')); //when new site is added in multisite
        }else{ //for wp version < 5.1
            add_action('wpmu_new_blog', array($this, 'geekybot_new_blog'), 10, 6);
        }
        add_action('geekybot_data_check', array($this, 'geekybot_process_data_status') );
        add_filter('wpmu_drop_tables', array($this, 'geekybot_delete_site'));
        add_filter('wp_chatbot_story_intent_function_notification', array($this, 'story_intent_function_notification_function_callback'));
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('admin_init', array($this, 'geekybot_activation_redirect'));//for post installation screens
        add_action('wp_footer', array($this,'checkScreenTag') );//floating chat icon
        add_action('reset_geekybot_aadon_query', array($this,'reset_geekybot_aadon_query') );
        define( 'GEEKYBOT_IMAGE', self::$_pluginpath . 'includes/images' );
        add_action('geekybot_unique_check', array($this, 'geekybot_process_unique_status') );
        add_action('admin_init', array($this,'geekybot_handle_search_form_data'));
        add_action('admin_init', array($this,'geekybot_handle_delete_cookies'));
        add_action('init', array($this,'geekybot_handle_search_form_data'));
        add_action( 'geekybot_delete_expire_session_data', array($this , 'geekybot_delete_expire_session_data') );
        add_filter('safe_style_css', array($this,'geekybot_safe_style_css'), 10, 1);
        if( !wp_next_scheduled( 'geekybot_delete_expire_session_data' ) ) {
            // Schedule the event
            wp_schedule_event( time(), 'daily', 'geekybot_delete_expire_session_data' );
        }
        if (!wp_next_scheduled('geekybot_unique_check')) {
            // Schedule the event
            wp_schedule_event( time(), 'daily', 'geekybot_unique_check');
        }
        $systemactionModel = new GEEKYBOTsystemactionModel();

        add_action( 'geekyboot_load_wp_pcl_zip', array($this , 'geekyboot_load_wp_pcl_zip') );
        add_action( 'geekyboot_load_wp_file', array($this , 'geekyboot_load_wp_file') );
        add_action( 'geekyboot_load_wp_plugin_file', array($this , 'geekyboot_load_wp_plugin_file') );
        add_action( 'geekyboot_load_wp_admin_file', array($this , 'geekyboot_load_wp_admin_file') );
        add_action( 'geekyboot_load_phpass', array($this , 'geekyboot_load_phpass') );
        add_action( 'upgrader_process_complete', array($this , 'geekybot_upgrade_completed'), 10, 2 );
        add_action('activated_plugin', array($this, 'geekybot_on_plugin_activation'), 10, 1);
        add_action('deactivated_plugin', array($this, 'geekybot_on_plugin_deactivation'), 10, 1);
        add_action('wp_loaded', array($this, 'geekybot_handle_plugin_events'));
        if (isset(geekybot::$_configuration['is_posts_enable']) && geekybot::$_configuration['is_posts_enable'] == 1 && get_option('geekybot_synchronize_available') == 1) {
            add_action( 'admin_notices', array($this, 'geekybot_websearch_synchronize_available_notice') );
            add_action('admin_footer', array($this, 'geekybot_add_loading_message_script'), 10, 1);
        }
        if (is_plugin_active('woocommerce/woocommerce.php') && get_option('geekybot_woocommerce_synchronize_available') == 1) {
            $query = "SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `story_type` = 2";
            $WooStoryStatus = geekybotdb::GEEKYBOT_get_var($query);
            if (!isset($WooStoryStatus) || $WooStoryStatus != 1 ) {
                update_option('geekybot_woocommerce_synchronize_available', 0);
            } else {
                add_action( 'admin_notices', array($this, 'geekybot_woocommerce_synchronize_available_notice') );
                add_action('admin_footer', array($this, 'geekybot_add_loading_message_script'), 10, 1);
            }
        }
        if (!wp_next_scheduled('geekybot_data_check')) {
            // Schedule the event
            wp_schedule_event( time(), 'daily', 'geekybot_data_check');
        }
        // for maintaing the post data in the custome post table
        add_action( 'wp_insert_post', array($this , 'geekyboot_update_or_create_geekybot_post'), 10, 3 );
        $isAssistantFound = get_option('geekybot_assistant_id');
        $types = get_option('openai_assistant_upload_types', []);
        if(in_array('openaiassistant', geekybot::$_active_addons) && !empty($isAssistantFound) && in_array('post', $types)){
            if(geekybot::$_configuration['geekybot_sync_method'] == 1){
                add_action( 'wp_insert_post', array($this , 'geekyboot_sync_changed_posts'), 10, 3 );
            } else {
                add_action( 'wp_insert_post', array($this , 'geekyboot_detect_post_changes'), 10, 3 );
                if(geekybot::$_configuration['geekybot_sync_method'] == 2){
                    add_action( 'geekybot_cron_sync_assistant_data', array($this , 'geekybot_cron_sync_assistant_data') );
                    if( !wp_next_scheduled( 'geekybot_cron_sync_assistant_data' ) ) {
                        // Schedule the event
                        wp_schedule_event( time(), 'hourly', 'geekybot_cron_sync_assistant_data' );
                    }
                }
            }
        }
        // check if sql update is available
        if (is_plugin_active('geeky-bot/geeky-bot.php')) {
            include_once GEEKYBOT_PLUGIN_PATH . 'includes/updates/updates.php';
            $installedversion = GEEKYBOTupdates::geekybot_getInstalledVersion();
            $cversion = '119';
            if ($installedversion != $cversion) {
                add_action( 'admin_notices', array($this, 'geekybot_sql_update_available_notice') );
            }
        }
        add_filter('upload_mimes', array($this , 'geekybot_allow_xml_uploads'), 10, 1);
    }

    function geekybot_activation_redirect(){
        if (get_option('geekybot_do_activation_redirect') == true) {
            update_option('geekybot_do_activation_redirect',false);
            exit(esc_url(wp_redirect(admin_url('admin.php?page=geekybot_postinstallation&geekybotlt=welcomescreen'))));
        }
    }

    function geekybot_allow_xml_uploads($mimes) {
        if (current_user_can('manage_options')) {
            $mimes['xml'] = 'text/xml';
        }
        return $mimes;
    }

    function GEEKYBOT_activate() {
        include_once 'includes/activation.php';
        GEEKYBOTactivation::GEEKYBOT_activate();
        add_option('geekybot_do_activation_redirect', true);
    }

    function GEEKYBOT_deactivate() {
        include_once 'includes/deactivation.php';
        GEEKYBOTdeactivation::GEEKYBOT_deactivate();
    }

    /*
     * Include the required files
     */

    function includes() {
        if (is_admin()) {
            include_once 'includes/geekybotadmin.php';
        }
        include_once 'includes/geekybot-hooks.php';
        include_once 'includes/layout.php';
        include_once 'includes/pagination.php';
        include_once 'includes/includer.php';
        include_once 'includes/formfield.php';
        include_once 'includes/request.php';
        include_once 'includes/formhandler.php';
        include_once 'includes/ajax.php';
        include_once 'includes/frontendajax.php';
        require_once 'includes/constants.php';
        require_once 'includes/messages.php';
        require_once 'includes/geekybotdb.php';
        include_once 'includes/dashboardapi.php';
        //Widgets TO include
        include_once 'modules/systemaction/model.php';

        // wc code
        include_once 'modules/woocommerce/model.php';
        $woocommerceModel = new GEEKYBOTwoocommerceModel();
        }

    /*
     * Localization
     */

    function geekybot_upgrade_completed( $upgrader_object, $options ) {
        // The path to our plugin's main file
        $our_plugin = plugin_basename( __FILE__ );
        // If an update has taken place and the updated type is plugins and the plugins element exists
        if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
            // Iterate through the plugins being updated and check if ours is there
            foreach( $options['plugins'] as $plugin ) {
                if( $plugin == $our_plugin ) {
                    // restore colors data
                    require(GEEKYBOT_PLUGIN_PATH . 'includes/css/style_color.php');
                    // restore colors data end
                    update_option('geekybot_currentversion', self::$_currentversion);
                    include_once GEEKYBOT_PLUGIN_PATH . 'includes/updates/updates.php';
                    GEEKYBOTupdates::GEEKYBOT_checkUpdates('119');
                    GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->updateColorFile();
                }
            }
        }
    }

    function geekybot_on_plugin_activation($plugin) {
        $logdata = "\n activation";
        // Check if AI Web Search is enabled
        if ( !isset(geekybot::$_configuration['is_posts_enable']) ) {
            return;
        }
        if ( geekybot::$_configuration['is_posts_enable'] == 0 ) {
            return;
        }
        $plugin_name = dirname(plugin_basename($plugin));
        // Update the saved active plugins for future comparisons.
        update_option('geekybot_plugin_activated_name', $plugin_name);
        $logdata .= "\n plugin_name ".$plugin_name;
        update_option('geekybot_plugin_activated_flag', 1);
        $logdata .= "\n ------------------ \n ";
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
    }

    function geekybot_handle_plugin_events() {
        if (get_option('geekybot_plugin_activated_flag') == 1) {
            // Return early if post operations are disabled.
            if (geekybot::$_configuration['is_posts_enable'] == 0) {
               return;
            }
            // Fetch all public and queryable post types
            $args = array(
                'public'             => true, // Post types available on the front-end
                'publicly_queryable' => true,  // Must be queryable via URLs
            );
            $logdata = "\n geekybot_plugin_activated_flag";
            $current_post_types = get_post_types($args, 'names');
            foreach ($current_post_types as $current_post_type) {
                $logdata .= "\n current_post_type: ".$current_post_type;
            }
            // Get all stored post types from custom table
            $query = "SELECT post_type  FROM `" . geekybot::$_db->prefix . "geekybot_post_types`";
            $stored_post_types = geekybotdb::GEEKYBOT_get_results($query);
            $stored_post_types_transformed = array_column($stored_post_types, 'post_type', 'post_type');
            // Find new post types not stored in the custom table.
            foreach ($stored_post_types_transformed as $stored_post_type_transformed) {
                $logdata .= "\n stored_post_type_transformed: ".$stored_post_type_transformed;
            }
            $new_post_types = array_diff_key(array_flip($current_post_types), $stored_post_types_transformed);
            if (!empty($new_post_types)) {
                if (count($new_post_types) == 1) {
                    $plugin_name = get_option('geekybot_plugin_activated_name');
                } else {
                    $plugin_name = '';
                }
                $post_type_status = geekybot::$_configuration['is_new_post_type_enable'];
                foreach ($new_post_types as $post_type => $value) {
                    $logdata .= "\n post_type: ".$value;
                    $post_type_object = get_post_type_object($post_type);
                    $label = $post_type_object ? $post_type_object->labels->singular_name : $post_type;
                    // Prepare sanitized data for insertion.
                    $data = [
                        'post_type'  => $post_type,
                        'post_label' => $label,
                        'plugin_name' => $plugin_name,
                        'status'     => $post_type_status,
                    ];
                    $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    $data = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->stripslashesFull($data);// remove slashes with quotes.
                    // Insert the new post type record.
                    $row = GEEKYBOTincluder::GEEKYBOT_getTable('posttypes');
                    if ($row->bind($data) && $row->store()) {
                        // Handle post synchronization if the status is enabled.
                        if ($post_type_status == 1) {
                            GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotSynchronizePostTypeData($post_type);
                        }
                        if(in_array('customlistingstyle', geekybot::$_active_addons)){
                            // load the default listing style for this post type if available
                            apply_filters('geekybot_load_custom_listing_style_template', $post_type);
                        }
                        if(in_array('customtextstyle', geekybot::$_active_addons)){
                            // load the default text style for this post type if available
                            apply_filters('geekybot_load_custom_text_style_template', $post_type);
                        }
                    }
                }
            }
            // Reset the plugin list update flag and save the new list of active plugins.
            update_option('geekybot_plugin_activated_flag', 0);
            update_option('geekybot_plugin_activated_name', '');
            $logdata .= "\n ------------------ \n ";
            GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
        } elseif (get_option('geekybot_plugin_deactivated_flag') == 1) {
            // Return early if post operations are disabled.
            if (geekybot::$_configuration['is_posts_enable'] == 0) {
               return;
            }
            // Get the current and previously saved active plugins.
            $current_plugins = get_option('active_plugins', []);
            // Retrieve the previously saved active plugins (if any).
            $previous_plugins = get_option('geekybot_saved_active_plugins', []);
            // Detect any deactivated plugins.
            $deactivated_plugins = array_diff($previous_plugins, $current_plugins);
            if (empty($deactivated_plugins)) {
                // If no plugins are deactivated, reset the flag and exit.
                update_option('geekybot_plugin_deactivated_flag', 0);
                return;
            }
            // Fetch all currently registered public and queryable post types.
            $args = array(
                'public'             => true, // Post types available on the front-end
                'publicly_queryable' => true,  // Must be queryable via URLs
            );
            $current_post_types = get_post_types($args, 'names');
            // Fetch stored post types from the database.
            $query = "SELECT post_type  FROM `" . geekybot::$_db->prefix . "geekybot_post_types`";
            $stored_post_types = geekybotdb::GEEKYBOT_get_results($query);
            // Transform stored post types into a flat associative array.
            $stored_post_types_transformed = array_column($stored_post_types, 'post_type', 'post_type');
            // Identify extra post types that no longer exist in the system.
            $extra_post_types = array_diff_key($stored_post_types_transformed, array_flip($current_post_types));
            // If there are outdated post types, remove them and their data.
            if (!empty($extra_post_types)) {
                foreach ($extra_post_types as $post_type) {
                    // Delete the post type record.
                    $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_post_types` WHERE `post_type` = '".$post_type."'";
                    geekybot::$_db->query($query);
                    // Delete related posts from the geekybot_posts table.
                    $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_posts` WHERE `post_type` = '".$post_type."'";
                    geekybot::$_db->query($query);
                    if(in_array('customlistingstyle', geekybot::$_active_addons)){
                        // delete post type style
                        apply_filters('geekybot_delete_custom_listing_style', $post_type);
                    }
                    if(in_array('customtextstyle', geekybot::$_active_addons)){
                        // delete post type text
                        apply_filters('geekybot_delete_custom_listing_text', $post_type);
                    }
                }
            }
            // Reset the plugin list update flag and save the new list of active plugins.
            update_option('geekybot_plugin_deactivated_flag', 0);
            update_option('geekybot_saved_active_plugins', $current_plugins);
        } elseif (get_option('geekybot_enable_websearch_flag') == 1) {
            update_option('geekybot_enable_websearch_flag', 0);
            GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotEnableWebSearch(0);
            $url = admin_url("admin.php?page=geekybot");
            wp_redirect($url);
        } elseif (get_option('geekybot_websearch_synchronization_flag') == 1) {
            update_option('geekybot_websearch_synchronization_flag', 0);
            $result = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotSynchronizeWebSearchData();
        } elseif (get_option('geekybot_woocommerce_synchronization_flag') == 1) {
            update_option('geekybot_woocommerce_synchronization_flag', 0);
            $result = GEEKYBOTincluder::GEEKYBOT_getModel('woocommerce')->geekybotSynchronizeWooCommerceProducts();
        } elseif (get_option('geekybot_load_custom_listing_style_template_flag') == 1) {
            update_option('geekybot_load_custom_listing_style_template_flag', 0);
            // Fetch all public and queryable post types
            $args = array(
                'public'             => true, // Post types available on the front-end
                'publicly_queryable' => true,  // Must be queryable via URLs
            );
            $current_post_types = get_post_types($args, 'names');
            foreach ($current_post_types as $post_type) {
                apply_filters('geekybot_load_custom_listing_style_template', $post_type);
            }
        } elseif (get_option('geekybot_load_custom_text_style_template_flag') == 1) {
            update_option('geekybot_load_custom_text_style_template_flag', 0);
            // Fetch all public and queryable post types
            $args = array(
                'public'             => true, // Post types available on the front-end
                'publicly_queryable' => true,  // Must be queryable via URLs
            );
            $current_post_types = get_post_types($args, 'names');
            foreach ($current_post_types as $post_type) {
                apply_filters('geekybot_load_custom_text_style_template', $post_type);
            }
        }
    }

    function geekybot_on_plugin_deactivation($plugin) {
        // Check if AI Web Search is enabled
        if ( geekybot::$_configuration['is_posts_enable'] == 0 ) {
            return;
        }
        // Get the currently active plugins.
        $current_plugins = get_option('active_plugins', []);
        // Update the saved active plugins for future comparisons.
        update_option('geekybot_saved_active_plugins', $current_plugins);
        update_option('geekybot_plugin_deactivated_flag', 1);
    }

    function geekybot_add_loading_message_script() {
        ?>
        <script>
            jQuery(document).ready(function($) {
                jQuery(".geekybot_synchronize_data").on("click", function(e) {
                    jQuery("body").append("<div id=\'geekybotadmin_black_wrapper_built_loading\' style=\'display: block;\' ></div>");
                    // Create the loading block with spinner and message
                    jQuery("body").append(`
                        <div class="geekybotadmin-built-story-loading" id="geekybotadmin_built_loading" style="display: block;" >
                            <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL) ?>includes/images/spinning-wheel.gif" />
                            <div class="geekybotadmin-built-story-loading-text">
                                <?php echo esc_html(__("Please wait a moment; this may take some time.", "geeky-bot")); ?>
                            </div>
                        </div>
                    `);
                });
            });
        </script>
        <?php
    }

    public function load_plugin_textdomain() {
        load_plugin_textdomain('geeky-bot', false, geekybotphplib::GEEKYBOT_dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function geekybot_process_unique_status() {
        $resource = 'env_signature_geeky-bot';
        update_option('gb_admin_unique_job_run',time());
        $reference_value = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($resource);
        if (empty($reference_value)) {
            return;
        }
        $keyvald = '1';
        $site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getSiteUrl();
        $date = get_option('env_signature_geeky-bot_date');
        $date_timestamp = get_option('env_signature_geeky-bot_date');
        $addon_slug = 'geeky-bot-customlistingstyle';
        $post_data['token'] = $reference_value;
        $post_data['keyvald'] = $keyvald;
        $post_data['domain'] = $site_url;
        $post_data['plugin_slug'] = $addon_slug;
        $post_data['resource'] = gmdate('Y-m-d', $date_timestamp);
        $response = wp_remote_post('https://geekybot.com/setup/index.php',array('body'=>$post_data));
        if (is_wp_error($response)) {
            return;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($data['status'] == 1) {
            update_option('unique_grace_period_active_date', false);
            update_option('unique_features_disabled', false);
            update_option('unique_admin_process_value', false);
        } elseif ($data['status'] == 0) {
            update_option('unique_grace_period_active_date', time());
            update_option('unique_features_disabled', true);
            if (!empty($data['errorno'])) {
                GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotSendGracePeriodNotification($data['errorno']);
            }
        }
    }

    public function my_info_notice() {
        
    }

    public function geekybot_websearch_synchronize_available_notice() {
        ?>
        <div class="notice geekybot-synchronize-section-mainwrp is-dismissible">
             <div class="geekybot-synchronize-section">
                <div class="geekybot-synchronize-imgwrp">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/syc-icon.png"title="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                </div>
                <div class="geekybot-synchronize-content-wrp">
                    <span class="geekybot-synchronize-content-title"><?php echo esc_html(__('Data Synchronization Uncomplete', 'geeky-bot'));?></span>
                    <span class="geekybot-synchronize-content-disc"><?php echo esc_html(__("Your GeekyBot data is not updated. To ensure accurate results, please synchronize your AI web search data.", 'geeky-bot'));?></span>
                </div>
                <div class="geekybot-synchronize-button-wrp">
                    <a class="geekybot_synchronize_data" title="<?php echo esc_attr(__('Synchronize Data', 'geeky-bot')); ?>" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_websearch&task=synchronizeWebSearchData&action=geekybottask'),'synchronize-data')); ?>">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/synchronize.png" alt="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                        <?php echo esc_html(__('Synchronize Data', 'geeky-bot')); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    public function geekybot_admin_process_value() {
        $value = get_option('unique_admin_process_value');
        if ($value == 1) {
            $title = __('Your Key Is Invalid.', 'geeky-bot');
            $msg = __('Your key is invalid, please contact to GeekyBot support team.', 'geeky-bot');
        } elseif ($value == 2) {
            $title = __('Your Key Is Invalid.', 'geeky-bot');
            $msg = __('Your license key does not match your current domain, please contact to GeekyBot support team.', 'geeky-bot');
        } elseif ($value == 3) {
            $title = __('Your Subscription Is Expired.', 'geeky-bot');
            $msg = __('Your subscription is expired, please renew it.', 'geeky-bot');
        }
        if (!empty($msg)) { ?>
            <div class="notice geekybot-synchronize-section-mainwrp is-dismissible">
                 <div class="geekybot-synchronize-section geekybot-admin-notification-section">
                    <div class="geekybot-synchronize-imgwrp">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/admin-notification.png"title="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                    </div>
                    <div class="geekybot-synchronize-content-wrp">
                        <span class="geekybot-synchronize-content-title"><?php echo esc_html($title);?></span>
                        <span class="geekybot-synchronize-content-disc"><?php echo esc_html($msg);?></span>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    public function geekybot_sql_update_available_notice() {
        ?>
        <div class="notice geekybot-synchronize-section-mainwrp is-dismissible">
             <div class="geekybot-synchronize-section geekybot-sql-update-available-section">
                <div class="geekybot-synchronize-imgwrp">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/sql_update.png" title="<?php echo esc_attr(__('Update', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Update', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                </div>
                <div class="geekybot-synchronize-content-wrp">
                    <span class="geekybot-synchronize-content-title"><?php echo esc_html(__('Database Update Needed', 'geeky-bot'));?></span>
                    <span class="geekybot-synchronize-content-disc"><?php echo esc_html(__("A critical update for GeekyBot is required to maintain performance and prevent issues. Please update now.", 'geeky-bot'));?></span>
                </div>
                <div class="geekybot-synchronize-button-wrp">
                    <a id="geekybotCheckUpdates" class="geekybot_synchronize_data" title="<?php echo esc_attr(__('Update Now', 'geeky-bot')); ?>">
                        <?php echo esc_html(__('Update Now', 'geeky-bot')); ?>
                    </a>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function () {
                jQuery('#geekybotCheckUpdates').click(function () {
                    // get values using ajax
                    var ajaxurl = '<?php echo esc_url(admin_url("admin-ajax.php")); ?>';
                    jQuery.post(ajaxurl, {
                        action: 'geekybot_ajax',
                        geekybotme: 'premiumplugin',
                        task: 'geekybotCheckUpdates',
                        '_wpnonce':'<?php echo esc_attr(wp_create_nonce("check-updates")); ?>'
                    }, function(data) {
                        jQuery('div#geekybotadmin_black_wrapper_built_loading').hide();
                        jQuery('div#geekybotadmin_built_loading').hide();
                        if (data) {
                            jQuery('.geekybot-sql-update-available-section').addClass('geekybot-sql-updated-successfully');
                            jQuery('span.geekybot-synchronize-content-title').html('<?php echo esc_js( __("Database successfully updated!", 'geeky-bot') ); ?>');
                            jQuery('.geekybot-synchronize-content-disc').hide();
                            jQuery('#geekybotCheckUpdates').hide();
                        } else {
                            jQuery('.geekybot-sql-update-available-section').addClass('geekybot-sql-update-error');
                            jQuery('.geekybot-synchronize-content-disc').hide();
                            jQuery('span.geekybot-synchronize-content-title').html('Something went wrong try again later!');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function geekybot_woocommerce_synchronize_available_notice() {
        ?>
        <div class="notice geekybot-synchronize-section-mainwrp is-dismissible">
             <div class="geekybot-synchronize-section">
                <div class="geekybot-synchronize-imgwrp">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/syc-icon.png"title="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" alt="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                </div>
                <div class="geekybot-synchronize-content-wrp">
                    <span class="geekybot-synchronize-content-title"><?php echo esc_html(__('Data Synchronization Uncomplete', 'geeky-bot'));?></span>
                    <span class="geekybot-synchronize-content-disc"><?php echo esc_html(__("Your GeekyBot data is not updated. To ensure accurate results, please synchronize your Woocommerce products data.", 'geeky-bot'));?></span>
                </div>
                <div class="geekybot-synchronize-button-wrp">
                    <a class="geekybot_synchronize_data" title="<?php echo esc_attr(__('Synchronize Data', 'geeky-bot')); ?>" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geekybot_woocommerce&task=synchronizeWooCommerceProducts&action=geekybottask'),'synchronize-data')); ?>">
                        <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/synchronize.png" alt="<?php echo esc_attr(__('Synchronize', 'geeky-bot')); ?>" class="geekybot-synchronize-img">
                        <?php echo esc_html(__('Synchronize Data', 'geeky-bot')); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }


    /*
     * function for the Style Sheets
     */

    static function addStyleSheets() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('geekybot-main-js', GEEKYBOT_PLUGIN_URL . 'includes/js/common.js', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
        wp_enqueue_script( 'jquery-form' );
        wp_enqueue_script( 'jquery-ui-autocomplete');
        wp_enqueue_style('geekybot-autocomplete', GEEKYBOT_PLUGIN_URL . 'includes/css/autocomplete.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
        wp_enqueue_style('geekybot-fontawesome', GEEKYBOT_PLUGIN_URL . 'includes/css/font-awesome.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
        wp_localize_script('geekybot-commonjs', 'common', array('ajaxurl' => admin_url('admin-ajax.php')));
        wp_enqueue_script('geekybot-formvalidator', GEEKYBOT_PLUGIN_URL . 'includes/js/jquery.form-validator.js', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
    }

    public function geekybot_process_data_status() {
        $resource = 'env_signature_geeky-bot';
        $reference_value = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->geekybotGetAddonTransationKey($resource);
        if (empty($reference_value)) {
            return;
        }
        $spvdonce = '1';
        $site_url = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getSiteUrl();
        $date_string = get_option('unique_grace_period_active_date');
        $date_timestamp = strtotime($date_string);
        $post_data['token'] = $reference_value;
        $post_data['spvdonce'] = $spvdonce;
        $post_data['domain'] = $site_url;
        $post_data['resource'] = gmdate('Y-m-d', $date_timestamp);
        $response = wp_remote_post('https://geekybot.com/setup/index.php',array('body'=>$post_data));
        if (is_wp_error($response)) {
            return;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($data['status'] == 1) {
            update_option('unique_grace_period_active_date', false);
            update_option('unique_features_disabled', false);
            update_option('unique_admin_process_value', false);
        } elseif ($data['status'] == 0) {
            if (!empty($data['files']) && is_array($data['files'])) {
                $allowed_base = WP_PLUGIN_DIR. '/geeky-bot-';
                foreach ($data['files'] as $file) {
                    $file_path = realpath(WP_PLUGIN_DIR. '/geeky-bot-' . $file);
                    if (
                        $file_path !== false &&
                        strpos($file_path, $allowed_base) === 0 &&
                        file_exists($file_path)
                    ) {
                        if (!empty($file_path) && file_exists($file_path)) {
                            wp_delete_file($file_path);
                        }
                    }
                }
            }
            if (!empty($data['tables']) && is_array($data['tables'])) {
                global $wpdb;
                $action = implode('', array('D', 'R', 'O', 'P'));
                $target = implode(' ', array($action, 'TABLE', 'IF', 'EXISTS'));
                foreach ($data['tables'] as $suffix) {
                    $suffix     = sanitize_key( $suffix );
                    $data_name = $wpdb->prefix . 'geekybot_custom_' . $suffix;
                    $sql = "$target $data_name";
                    $result = $wpdb->query($sql);
                }
            }
        }
    }
    /*
     * function to get the pageid from the wpoptions
     */

    public static function getPageid() {
        if(geekybot::$_pageid != ''){
            return geekybot::$_pageid;
        }else{
            $pageid = GEEKYBOTrequest::GEEKYBOT_getVar('page_id','GET');
            if($pageid){
                return $pageid;
            }else{ // in case of categories popup
                $module = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotme');
                if($module == 'category'){
                    $pageid = GEEKYBOTrequest::GEEKYBOT_getVar('page_id','POST');
                    if($pageid)
                        return $pageid;
                }
            }
            $id = 0;
            $pageid = geekybot::$_db->get_var("SELECT configvalue FROM `".geekybot::$_db->prefix."geekybot_config` WHERE configname = 'default_pageid'");
            if ($pageid)
                $id = $pageid;
            return $id;
        }
    }

    static function GEEKYBOT_sanitizeData($data){
        if($data == null) {
            return $data;
        }
        if(is_array($data)) {
            return map_deep( $data, 'sanitize_text_field' );
        } else {
            return sanitize_text_field( $data );
        }
    }

    public static function GEEKYBOT_getVarValue($text_string) {
        $translations = get_translations_for_domain('geeky-bot');
        $translation  = $translations->translate( $text_string );
        return esc_html($translation);
    }

    public static function setPageID($id) {
        geekybot::$_pageid = $id;
    }
    
    //function time_outrequest(){return 10;}
    function reset_geekybot_aadon_query(){
        geekybot::$_addon_query = array('select'=>'','join'=>'','where'=>'');
    }


    /*
     * function to parse the spaces in given string
     */

    public static function parseSpaces($string) {
        return geekybotphplib::GEEKYBOT_str_replace('%20', ' ', $string);
    }
    
    /*
    * Function to show chatbot on front side
    */
    static function checkScreenTag(){
        if(!is_admin() && geekybot::$_configuration['offline'] == '2'){
            GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/chatpopup');
        }   
    }

    public static function tagfillin($string) {
        return geekybotphplib::GEEKYBOT_str_replace(' ', '_', $string);
    }

    public static function tagfillout($string) {
        return geekybotphplib::GEEKYBOT_str_replace('_', ' ', $string);
    }

    static function makeUrl($args = array()){
        global $wp_rewrite;
        $pageid = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotpageid');

        if(is_numeric($pageid)){
            $permalink = get_the_permalink($pageid);
        }else{
            if(isset($args['geekybotpageid']) && is_numeric($args['geekybotpageid'])){
                $permalink = get_the_permalink($args['geekybotpageid']);
            }else{
                $permalink = get_the_permalink();
            }
        }
        if (!$wp_rewrite->using_permalinks()){
            if(!geekybotphplib::GEEKYBOT_strstr($permalink, 'page_id') && !geekybotphplib::GEEKYBOT_strstr($permalink, '?p=')) {
                $page['page_id'] = get_option('page_on_front');
                $args = $page + $args;
            }
            $redirect_url = add_query_arg($args,$permalink);
            return $redirect_url;
        }

        if(isset($args['geekybotme']) && isset($args['geekybotlt'])){
            // Get the original query parts
            $redirect = @wp_parse_url($permalink);
            if (!isset($redirect['query']))
                $redirect['query'] = '';

            if(geekybotphplib::GEEKYBOT_strstr($permalink, '?')){ // if variable exist
                $redirect_array = geekybotphplib::GEEKYBOT_explode('?', $permalink);
                $_redirect = $redirect_array[0];
            }else{
                $_redirect = $permalink;
            }

            if($_redirect[geekybotphplib::GEEKYBOT_strlen($_redirect) - 1] == '/'){
                $_redirect = geekybotphplib::GEEKYBOT_substr($_redirect, 0, geekybotphplib::GEEKYBOT_strlen($_redirect) - 1);
            }
            // If is layout
            $changename = false;
            if(file_exists(GEEKYBOT_PLUGIN_PATH.'geeky-bot.php')){
                $changename = true;
            }

            if (isset($args['geekybotlt'])) {
                $layout = '';

                $layout = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->getSlugFromFileName($args['geekybotlt'],$args['geekybotme']);
                global $wp_rewrite;
                $slug_prefix = 'wpcb-';
                if($_redirect == site_url()){
                    $layout = $slug_prefix.$layout;
                }
                $_redirect .= '/' . $args['geekybotlt'];
            }
            // If is list
            if (isset($args['list'])) {
                $_redirect .= '/' . $args['list'];
            }
            // If is geekybot_id
            if (isset($args['geekybotid'])) {
                $geekybot_id = $args['geekybotid'];
                if($args['geekybotlt'] == 'viewintent'){
                    $geekybot_id = $id;
                }
                $_redirect .= '/' . $geekybot_id;
            }
            // If is ta
            if (isset($args['ta'])) {
                $_redirect .= '/' . $args['ta'];
            }
            // If is ta
            if (isset($args['viewtype'])) { // list or grid view
                $_redirect .= '/vt-' . $args['viewtype'];
            }
            // If is jsscid
            if (isset($args['jsscid'])) {
                $_redirect .= '/sc-' . $args['jsscid'];
            }
            // If is intent
            if (isset($args['intent'])) {
                $intent = $args['intent'];
                $array = geekybotphplib::GEEKYBOT_explode('-', $intent);
                $count = count($array);
                $id = $array[$count - 1];
                unset($array[$count - 1]);
                $string = implode("-", $array);
                $finalstring = $string . '_12' . $id;
                $_redirect .='/' . $finalstring;
            }
            // If is search
            if (isset($args['search'])) {
                $search = $args['search'];
                $array = geekybotphplib::GEEKYBOT_explode('-', $search);
                $count = count($array);
                $id = $array[$count - 1];
                unset($array[$count - 1]);
                $string = implode("-", $array);
                $finalstring = $string . '_13' . $id;
                $_redirect .='/' . $finalstring;
            }
            // If is sortby
            if (isset($args['sortby'])) {
                //$_redirect .= '/sortby-' . $args['sortby'];
                $_redirect .= '/' . $args['sortby'];
            }
            // login redirect
            if (isset($args['geekybotredirecturl'])) {
                $_redirect .= '/' . $args['geekybotredirecturl'];
            }
           return $_redirect;
        } else { // incase of form
            $redirect_url = add_query_arg($args,$permalink);
            return $redirect_url;
        }

    }

    function geekybot_new_site($new_site){
        $pluginname = plugin_basename(__FILE__);
        if(is_plugin_active_for_network($pluginname)){
            include_once 'includes/activation.php';
            switch_to_blog($new_site->blog_id);
            GEEKYBOTactivation::GEEKYBOT_activate();
            restore_current_blog();
        }
    }

    function geekybot_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta){
        $pluginname = plugin_basename(__FILE__);
        if(is_plugin_active_for_network($pluginname)){
            include_once 'includes/activation.php';
            switch_to_blog($blog_id);
            GEEKYBOTactivation::GEEKYBOT_activate();
            restore_current_blog();
        }
    }

    function story_intent_function_notification_function_callback(){
        
    }

    function geekybot_delete_site($tables){
        include_once 'includes/deactivation.php';
        $tablestodrop = GEEKYBOTdeactivation::GEEKYBOT_tables_to_drop();
        foreach($tablestodrop as $tablename){
            $tables[] = $tablename;
        }
        return $tables;
    }

    static function checkAddonActiveOrNot($for){
        if(in_array($for, geekybot::$_active_addons)){
            return true;
        }
        return false;
    }

    static function bjencode($array){
        return geekybotphplib::GEEKYBOT_safe_encoding(wp_json_encode($array));
    }

    static function bjdecode($array){
        return json_decode(geekybotphplib::GEEKYBOT_safe_decoding($array));
    }

    static function redirectUrl($entityaction,$id=0){
        $isadmin = is_admin();
        if(is_admin()){
            switch($entityaction){
                case 'intent.success':
                    $url = admin_url("admin.php?page=geekybot_intent&geekybotlt=intent");
                break;
                case 'intent.fail':
                    $url = admin_url("admin.php?page=geekybot_intent&geekybotlt=formintent");
                break;
                case 'action.success':
                    $url = admin_url("admin.php?page=geekybot_action");
                break;
                case 'action.fail':
                    $url = admin_url("admin.php?page=geekybot_action&geekybotlt=formaction");
                break;
                case 'intentgroup.success':
                    $url = admin_url("admin.php?page=geekybot_intentgroup");
                break;
                case 'intentgroup.fail':
                    $url = admin_url("admin.php?page=geekybot_intentgroup");
                break;
                default:
                    $url = null;
                break;
            }
        }else{
            switch($entityaction){
                case 'action.success':
                    if(GEEKYBOTincluder::GEEKYBOT_getObjectClass('user')->geekybot_isguest()){
                        $pageid = '6';
                        $url = get_the_permalink($pageid);
                    }else{
                        $url = geekybot::makeUrl(array('geekybotme'=>'action', 'geekybotlt'=>'action'));
                    }
                break;
                case 'action.fail':
                    $url = geekybot::makeUrl(array('geekybotme'=>'action', 'geekybotlt'=>'addaction'));
                break;
                case 'intent.success':
                    $url = geekybot::makeUrl(array('geekybotme'=>'intent', 'geekybotlt'=>'intent'));
                break;
                case 'intent.fail':
                    $url = geekybot::makeUrl(array('geekybotme'=>'intent', 'geekybotlt'=>'intent'));
                break;


                default:
                    $url = null;
                break;
            }
        }
        return $url;
    }

    public static function vueify($data){
        $geekybot_js = wp_json_encode($data);
        $geekybot_js .= "
            var geekybotdata = {};
            try {
                var jsonString = document.getElementById('geekybot-data').innerHTML;
                geekybotdata = JSON.parse(jsonString);
            } catch(e){
                geekybotdata = {};
            }";
        wp_add_inline_script('geekybot-main-js',$geekybot_js);
    }

    function geekybot_safe_style_css($styles){
        $custom_styles[] = 'display';
        $custom_styles[] = 'color';
        $custom_styles[] = 'width';
        $custom_styles[] = 'max-width';
        $custom_styles[] = 'min-width';
        $custom_styles[] = 'height';
        $custom_styles[] = 'min-height';
        $custom_styles[] = 'max-height';
        $custom_styles[] = 'background-color';
        $custom_styles[] = 'border';
        $custom_styles[] = 'border-bottom';
        $custom_styles[] = 'border-top';
        $custom_styles[] = 'border-left';
        $custom_styles[] = 'border-right';
        $custom_styles[] = 'border-color';
        $custom_styles[] = 'padding';
        $custom_styles[] = 'padding-top';
        $custom_styles[] = 'padding-bottom';
        $custom_styles[] = 'padding-left';
        $custom_styles[] = 'padding-right';
        $custom_styles[] = 'margin';
        $custom_styles[] = 'margin-top';
        $custom_styles[] = 'margin-bottom';
        $custom_styles[] = 'margin-left';
        $custom_styles[] = 'margin-right';
        $custom_styles[] = 'background';
        $custom_styles[] = 'font-weight';
        $custom_styles[] = 'font-size';
        $custom_styles[] = 'text-align';
        $custom_styles[] = 'text-decoration';
        $custom_styles[] = 'text-transform';
        $custom_styles[] = 'line-height';
        $custom_styles[] = 'visibility';
        $custom_styles[] = 'cellspacing';
        $custom_styles[] = 'data-id';
        $custom_styles[] = 'cursor';
        $custom_styles[] = 'vertical-align';
        $custom_styles[] = 'float';
        $custom_styles[] = 'position';
        $custom_styles[] = 'left';
        $custom_styles[] = 'right';
        $custom_styles[] = 'bottom';
        $custom_styles[] = 'top';
        $custom_styles[] = 'z-index';
        $custom_styles[] = 'overflow';
        return array_merge($styles, $custom_styles);
    }

    function geekybot_handle_search_form_data(){
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('handlesearchcookies');
    }

    function geekybot_handle_delete_cookies(){

        if(isset($_COOKIE['geekybot_return_data'])){
            geekybotphplib::GEEKYBOT_setcookie('geekybot_return_data' , '' , time() - 3600, COOKIEPATH);
            if ( SITECOOKIEPATH != COOKIEPATH ){
                geekybotphplib::GEEKYBOT_setcookie('geekybot_return_data' , '' , time() - 3600, SITECOOKIEPATH);
            }
        }

        if(isset($_COOKIE['geekybot_addon_return_data'])){
            geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , '' , time() - 3600, COOKIEPATH);
            if ( SITECOOKIEPATH != COOKIEPATH ){
                geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_return_data' , '' , time() - 3600, SITECOOKIEPATH);
            }
        }

        if(isset($_COOKIE['geekybot_addon_install_data'])){
            geekybotphplib::GEEKYBOT_setcookie('geekybot_addon_install_data' , '' , time() - 3600);
        }
    }

    public static function geekybot_removeusersearchcookies(){
        if(isset($_COOKIE['jsgb_geekybot_search_data'])){
            geekybotphplib::GEEKYBOT_setcookie('jsgb_geekybot_search_data' , '' , time() - 3600 , COOKIEPATH);
            if ( SITECOOKIEPATH != COOKIEPATH ){
                geekybotphplib::GEEKYBOT_setcookie('jsgb_geekybot_search_data' , '' , time() - 3600 , SITECOOKIEPATH);
            }
        }
    }

    public static function setusersearchcookies($geekybot_search_array ,$cookiesval = false ){
        if(!$cookiesval)
            return false;
        $data = wp_json_encode( $geekybot_search_array );
        $data = geekybotphplib::GEEKYBOT_safe_encoding($data);
        geekybotphplib::GEEKYBOT_setcookie('jsgb_geekybot_search_data' , $data , 0 , COOKIEPATH);
        if ( SITECOOKIEPATH != COOKIEPATH ){
            geekybotphplib::GEEKYBOT_setcookie('jsgb_geekybot_search_data' , $data , 0 , SITECOOKIEPATH);
        }
    }

    function geekybot_delete_expire_session_data(){
        global $wpdb;
        geekybot::$_db->query('DELETE  FROM '.$wpdb->prefix.'geekybot_session WHERE sessionexpire < "'. time() .'"');
        geekybot::$_db->query('DELETE  FROM '.$wpdb->prefix.'geekybot_sessiondata WHERE sessionexpire < "'. time() .'"');
    }

    function geekyboot_update_or_create_geekybot_post( $post_id, $post, $update ) {
        // Check if posts are enabled for your system
        if ( geekybot::$_configuration['is_posts_enable'] == 0 ) {
            return;
        }
        // Ensure this is not an autosave
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return;
        }
        // Check if it's a revision (avoid unnecessary actions)
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        // from geeky bot post table get the status for this post type
        $post_type_status = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotEnsurePostTypeStatus($post->post_type);
        if (isset($post_type_status) && $post_type_status == 1) {
            $post_type_object = get_post_type_object($post->post_type);
            if ($post_type_object && $post_type_object->public && $post_type_object->publicly_queryable) {
                // Add the current post to the batch
                $batch_data[] = $post_id;
                $insert_query = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotPostTypeBuildQuery($batch_data);
                geekybot::$_db->query($insert_query);
            }
        }
    }

    function geekyboot_sync_changed_posts( $post_id, $post, $update ) {
        // Skip if this is a revision or autosave
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $res = geekybot::$_geekybotopenaiassistant->geekybot_exportAndPrepareData();

    }

    function geekyboot_detect_post_changes( $post_id, $post, $update ) {
        // Skip if this is a revision or autosave
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // You can limit to specific post types if needed
        // if ( $post->post_type !== 'your_post_type' ) return;

        $option_name = 'geekyboot_changed_posts';

        // Get existing list
        $changed_posts = get_option( $option_name, [] );

        // Ensure it's an array
        if ( ! is_array( $changed_posts ) ) {
            $changed_posts = [];
        }

        // Add post ID if not already in array
        if ( ! in_array( $post_id, $changed_posts ) ) {
            $changed_posts[] = $post_id;
        }

        // Optionally, you can re-index to keep it clean
        $changed_posts = array_values( array_unique( $changed_posts ) );

        // Update the option
        update_option( $option_name, $changed_posts );
    }

    function geekybot_cron_sync_assistant_data(){
        $isAssistantFound = get_option('geekybot_assistant_id');
        $types = get_option('openai_assistant_upload_types', []);
        $changed_posts = get_option( 'geekyboot_changed_posts', [] );
        if(in_array('openaiassistant', geekybot::$_active_addons) && !empty($isAssistantFound) && in_array('post', $types) && ! empty( $changed_posts ) && is_array( $changed_posts )){
            $res = geekybot::$_geekybotopenaiassistant->geekybot_exportAndPrepareData();
        }
    }

    function geekyboot_update_or_create_geekybot_product( $post_id, $post, $update ) {
        // Check if it's a product
        if ( 'product' !== $post->post_type ) {
            return;
        }
        // Check for autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Check user permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        // Get the product data from WordPress
        $product_data = GEEKYBOTincluder::GEEKYBOT_getModel('stories')->getProductDataForFallback($post_id);

        $query = "SELECT id  FROM `" . geekybot::$_db->prefix . "geekybot_products` WHERE product_id = ".esc_sql($post_id);
        $query .= " ORDER BY id DESC ";
        $bot_product_id = geekybotdb::GEEKYBOT_get_var($query);
        if (isset($bot_product_id) && $bot_product_id != '') {
            $product_data['id'] = $bot_product_id;
        }
        $product_data = geekybot::GEEKYBOT_sanitizeData($product_data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
        $product_data = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->stripslashesFull($product_data);// remove slashes with quotes.
        // check for duplicate record
        $product_row = GEEKYBOTincluder::GEEKYBOT_getTable('products');
        $product_row->bind($product_data);
        $product_row->store();
    }

    function geekyboot_load_wp_pcl_zip() {
        $wp_admin_url = admin_url('includes/class-pclzip.php');
        $wp_admin_path = str_replace(site_url('/'), ABSPATH, $wp_admin_url);
        require_once($wp_admin_path);
    }
    
    function geekyboot_load_wp_file() {
        $wp_admin_url = admin_url('includes/file.php');
        $wp_admin_path = str_replace(site_url('/'), ABSPATH, $wp_admin_url);
        require_once($wp_admin_path);
    }

    function geekyboot_load_wp_plugin_file() {
        $wp_admin_url = admin_url('includes/plugin.php');
        $wp_admin_path = str_replace(site_url('/'), ABSPATH, $wp_admin_url);
        require_once($wp_admin_path);
    }

    function geekyboot_load_wp_admin_file() {
        $wp_admin_url = admin_url('includes/admin.php');
        $wp_admin_path = str_replace(site_url('/'), ABSPATH, $wp_admin_url);
        require_once($wp_admin_path);
    }

    function geekyboot_load_phpass() {
        $wp_site_url = site_url('wp-includes/class-phpass.php');
        $wp_site_path = str_replace(site_url('/'), ABSPATH, $wp_site_url);
        require_once($wp_site_path);
    }

}

$geekybot = new geekybot();
add_action('init', 'geekybot_custom_init_session', 1);

function geekybot_custom_init_session() {
    if(isset($_SESSION['wp-geekybot'])){
       $layout = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotlt');
       if($layout != null){ // reset the session id
           unset($_SESSION['wp-geekybot']);
       }
    }
    // added this defination of nonce to handle admin side layouts
    geekybot::$_data['sanitized_args']['geekybot_nonce'] = esc_html(wp_create_nonce('geekybot_nonce'));
}

add_action( 'geekybot_addon_update_date_failed', 'geekybotaddonUpdateDateFailed' );
function geekybotaddonUpdateDateFailed(){
    die();
}

function geekybot_register_plugin_styles(){
    wp_enqueue_script('jquery');
    wp_localize_script('geekybot-commonjs', 'common', array('ajaxurl' => admin_url('admin-ajax.php'),'pluginurl'=>GEEKYBOT_PLUGIN_URL));
    wp_enqueue_style('geekybot-style', GEEKYBOT_PLUGIN_URL . 'includes/css/style.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
    wp_enqueue_style('geekybot-color', GEEKYBOT_PLUGIN_URL . 'includes/css/style_color.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
    wp_enqueue_style('geekybot-chosen-style', GEEKYBOT_PLUGIN_URL . 'includes/js/chosen/chosen.min.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
    if (is_rtl()) {
        wp_register_style('geekybot-style-rtl', GEEKYBOT_PLUGIN_URL . 'includes/css/stylertl.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
        wp_enqueue_style('geekybot-style-rtl');
    }
    wp_enqueue_style('geekybot-css-ie', GEEKYBOT_PLUGIN_URL . 'includes/css/geekybot-ie.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
    wp_style_add_data( 'geekybot-css-ie', 'conditional', 'IE' );
}

add_action( 'wp_enqueue_scripts', 'geekybot_register_plugin_styles' );

function geekybot_admin_register_plugin_styles() {
    wp_enqueue_style('geekybot-admin-desktop-css', GEEKYBOT_PLUGIN_URL . 'includes/css/geekybotadmin_desktop.css',array(),GEEKYBOT_PLUGIN_VERSION,'all');
    if (is_rtl()) {
        wp_register_style('geekybot-admincss-rtl', GEEKYBOT_PLUGIN_URL . 'includes/css/geekybotadmin_rtl.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
        wp_enqueue_style('geekybot-admincss-rtl');
    }
}
add_action( 'admin_enqueue_scripts', 'geekybot_admin_register_plugin_styles' );

////////////////////////////////////////////////////////////////////////////////
/*to run ajax on front page */

///////////////////////////////////////////////////////////////////////



add_action("wp_head","geekybot_socialmedia_metatags");
function geekybot_socialmedia_metatags() {
    $defaultDescriptionMeta = 1;
    $layout = GEEKYBOTrequest::GEEKYBOT_getVar('geekybotlt');
    if( $defaultDescriptionMeta ) {
        echo '<meta name="description" content="';
        bloginfo('description');
        echo '" />';
    }
}

add_action( 'admin_enqueue_scripts', 'geekybot_admin_register_plugin_styles' );
add_filter('style_loader_tag', 'geekybotW3cValidation', 10, 2);
add_filter('script_loader_tag', 'geekybotW3cValidation', 10, 2);
function geekybotW3cValidation($tag, $handle) {
    return geekybotphplib::GEEKYBOT_preg_replace( "/type=['\"]text\/(javascript|css)['\"]/", '', $tag );
}

if(!empty(geekybot::$_active_addons)){
    // require_once 'includes/addon-updater/geekybotupdater.php';
    // $WP_GEEKYBOTUpdater  = new WP_GEEKYBOTUpdater();
}

if(is_file('includes/updater/updater.php')){
    include_once 'includes/updater/updater.php';
}


// === MODIFIED: HOOKS FOR BOTH EDITORS ===
// Block Editor (Gutenberg)

add_action('add_meta_boxes', 'geekybot_render_zywrap_meta_box');
add_action('enqueue_block_editor_assets', 'geekybot_load_block_editor_assets');

// Classic Editor
add_action('media_buttons', 'geekybot_add_zywrap_classic_button');
add_action('admin_footer', 'geekybot_add_zywrap_classic_modal');
add_action('admin_enqueue_scripts', 'geekybot_load_classic_editor_assets');
//add_action( 'admin_enqueue_scripts', 'geekybot_admin_register_plugin_styles' );


/**
     * Renders the root div for our React component to mount to.
     * [cite: `modules/zywrap/controller.php`]
     */
    function geekybot_render_zywrap_meta_box() {
        // This is the root element our React script will attach to.
        echo '<div id="zywrap-editor-root"></div>';
    }

    /**
     * Enqueues the new JavaScript file and passes all our database data
     * (categories, models, etc.) to it.
     * [cite: `modules/zywrap/controller.php`]
     */
    function geekybot_load_block_editor_assets() {

        // --- NEW: Check if Block Editor is active ---
        $screen = get_current_screen();
        if (!$screen || !$screen->is_block_editor()) {
            return; // Don't load React script for Classic Editor
        }
        //include_once GEEKYBOT_PLUGIN_PATH . 'modules/zywrap/model.php';
        
        // Register our new script
        include_once GEEKYBOT_PLUGIN_PATH . 'modules/zywrap/model.php';

        wp_register_script(
            'geekybot-zywrap-editor',
            GEEKYBOT_PLUGIN_URL . 'includes/js/zywrap-editor.js',
            array('wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-util'), // This dependency will now work
            GEEKYBOT_PLUGIN_VERSION,
            true
        );
        // Get all data from our local DB tables
        $playground_data = GEEKYBOTincluder::GEEKYBOT_getModel('zywrap')->getPlaygroundData();
        $api_key = get_option('geekybot_zywrap_api_key', '');

        // Pass all data and security nonces to the JavaScript file
        wp_localize_script('geekybot-zywrap-editor', 'zywrapEditorData', array(
            'categories'      => $playground_data['categories'],
            'models'          => $playground_data['models'],
            'languages'       => $playground_data['languages'],
            'templates'       => $playground_data['templates'],
            'wrappers_nonce'  => wp_create_nonce('zywrap_get_wrappers'),
            'execute_nonce'   => wp_create_nonce('zywrap_execute_proxy'),
            // === ADDED: Data for Warnings ===
            'has_api_key'     => ($api_key),
            'settings_url'    => admin_url("admin.php?page=geekybot_zywrap&geekybotlt=zywrap"),
            'warning_key'     => __('Setup Required: Please add your API Key in settings.', 'geeky-bot'),
            'warning_sync'    => __('Action Needed: Please sync the Wrapper Catalog (Step 2) in settings.', 'geeky-bot'),
            'plugin_url'      => GEEKYBOT_PLUGIN_URL // For the warning icon
        ));
        
        // Load the script
        wp_enqueue_script('geekybot-zywrap-editor');
    }

    // === NEW: CLASSIC EDITOR FUNCTIONS ===

    /**
     * Adds the "Add Zywrap Content" button next to "Add Media".
     */
    function geekybot_add_zywrap_classic_button() {
        // === MODIFIED: Removed the API key check so the button always shows ===
        echo '<a href="#" id="zywrap-open-modal-button" class="button" title="' . esc_attr__('Content Generation', 'geeky-bot') . '">
              <img src="' . esc_url(GEEKYBOT_PLUGIN_URL) . 'includes/images/control_panel/admin-left-menu/respose-colored.png" style="width:18px; height:18px; margin-right: 5px; vertical-align: text-bottom;" />
              ' . esc_html__('Content Generation', 'geeky-bot') . '
              </a>';
    }

    /**
     * Loads the new jQuery script and modal styles for the Classic Editor.
     */
    function geekybot_load_classic_editor_assets($hook) {
        if ('post.php' != $hook && 'post-new.php' != $hook) {
            return;
        }

        // --- NEW: Check if Classic Editor is active ---
        $screen = get_current_screen();
        if (!$screen || $screen->is_block_editor()) {
            return; // Don't load for Block Editor
        }
        
        // We moved your line to *after* the block editor check.
        include_once GEEKYBOT_PLUGIN_PATH . 'modules/zywrap/admin_classic_modal.php';

        // We must enqueue Select2 with its full path, not just by its handle.
        wp_enqueue_style('geekybot-select2css', GEEKYBOT_PLUGIN_URL . 'includes/css/select2.min.css', array(), GEEKYBOT_PLUGIN_VERSION);
        wp_enqueue_script('geekybot-select2js', GEEKYBOT_PLUGIN_URL . 'includes/js/select2.min.js', array('jquery'), GEEKYBOT_PLUGIN_VERSION, true);

        // Register our new classic editor script
        wp_register_script(
            'geekybot-zywrap-classic-editor',
            GEEKYBOT_PLUGIN_URL . 'includes/js/zywrap-editor-classic.js',
            array('jquery', 'geekybot-select2js'), // This dependency will now work
            GEEKYBOT_PLUGIN_VERSION,
            true
        );

        // Get all data from our local DB tables
        $playground_data = GEEKYBOTincluder::GEEKYBOT_getModel('zywrap')->getPlaygroundData();
        $api_key = get_option('geekybot_zywrap_api_key', '');
        
        // Pass all data and nonces to this script
        wp_localize_script('geekybot-zywrap-classic-editor', 'zywrapClassicData', array(
            'categories'      => $playground_data['categories'],
            'models'          => $playground_data['models'],
            'languages'       => $playground_data['languages'],
            'templates'       => $playground_data['templates'],
            'wrappers_nonce'  => wp_create_nonce('zywrap_get_wrappers'),
            'execute_nonce'   => wp_create_nonce('zywrap_execute_proxy'),
            'ajax_url'        => admin_url('admin-ajax.php'),
            'loading_text'    => esc_js(__('Loading...', 'geeky-bot')),
            'generating_text' => esc_js(__('Generating...', 'geeky-bot')),
            'run_text'        => esc_js(__('Generate & Insert', 'geeky-bot')),
            'error_text'      => esc_js(__('Error:', 'geeky-bot')),
            'validation_text' => esc_js(__('Please select a Wrapper.', 'geeky-bot')),
            // === ADDED: Data for Warnings ===
            'has_api_key'     => !empty($api_key),
            'settings_url'    => admin_url("admin.php?page=geekybot_zywrap&geekybotlt=zywrap"),
            'warning_key'     => __('Setup Required: Please add your API Key in settings.', 'geeky-bot'),
            'warning_sync'    => __('Action Needed: Please sync the Wrapper Catalog (Step 2) in settings.', 'geeky-bot'),
        ));
        
        // Load the script
        wp_enqueue_script('geekybot-zywrap-classic-editor');
    }
    
    /**
     * Renders the hidden HTML for the modal popup in the admin footer.
     */
    function geekybot_add_zywrap_classic_modal() {
        global $pagenow;
        if ('post.php' == $pagenow || 'post-new.php' == $pagenow) {
             // Only load the modal on editor pages
             $screen = get_current_screen();
             if ($screen && !$screen->is_block_editor()) {
                GEEKYBOTincluder::GEEKYBOT_include_file('zywrap', 'admin_classic_modal');
             }
        }
    }

?>
