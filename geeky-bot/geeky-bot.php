<?php

/**
 * @package Geeky Bot
 * @author Geeky Bot
 * @version 1.0.4
 */
/*
  * Plugin Name: Geeky Bot
  * Plugin URI: https://geekybot.com/
  * Description: The ultimate AI chatbot for WooCommerce lead generation, intelligent web search, and interactive customer engagement on your WordPress website.
  * Author: Geeky Bot
  * Version: 1.0.4
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
        self::$_currentversion = '104';
        self::$_addon_query = array('select'=>'','join'=>'','where'=>'');
        self::$_config = GEEKYBOTincluder::GEEKYBOT_getModel('configuration');
        self::$_isgeekybotplugin = true;
        self::$_geekybotsession = GEEKYBOTincluder::GEEKYBOT_getObjectClass('wpcbsession');
        self::$_geekybotsessiondata = GEEKYBOTincluder::GEEKYBOT_getObjectClass('geekybotsessiondata');
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
        add_filter('wpmu_drop_tables', array($this, 'geekybot_delete_site'));
        add_filter('wp_chatbot_story_intent_function_notification', array($this, 'story_intent_function_notification_function_callback'));
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('admin_init', array($this, 'geekybot_activation_redirect'));//for post installation screens
        add_action('wp_footer', array($this,'checkScreenTag') );//floating chat icon
        add_action('reset_geekybot_aadon_query', array($this,'reset_geekybot_aadon_query') );
        define( 'GEEKYBOT_IMAGE', self::$_pluginpath . 'includes/images' );

        add_action('admin_init', array($this,'geekybot_handle_search_form_data'));
        add_action('admin_init', array($this,'geekybot_handle_delete_cookies'));
        add_action('init', array($this,'geekybot_handle_search_form_data'));
        add_action( 'geekybot_delete_expire_session_data', array($this , 'geekybot_delete_expire_session_data') );
        add_filter('safe_style_css', array($this,'geekybot_safe_style_css'));
        if( !wp_next_scheduled( 'geekybot_delete_expire_session_data' ) ) {
            // Schedule the event
            wp_schedule_event( time(), 'daily', 'geekybot_delete_expire_session_data' );
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
            add_action( 'admin_notices', array($this, 'geekybot_synchronize_available_notice') );
            add_action('admin_footer', array($this, 'geekybot_add_loading_message_script'), 10, 1);
        }
        // for maintaing the post data in the custome post table
        add_action( 'wp_insert_post', array($this , 'geekyboot_update_or_create_geekybot_post'), 10, 3 );
        add_action( 'bbp_new_topic', array( $this, 'geekybot_bbpress_topic_create_and_update'), 10, 3 );
        add_action( 'bbp_edit_topic', array( $this, 'geekybot_bbpress_topic_create_and_update'), 10, 3 );
    }

    function geekybot_activation_redirect(){
        if (get_option('geekybot_do_activation_redirect') == true) {
            update_option('geekybot_do_activation_redirect',false);
            exit(esc_url(wp_redirect(admin_url('admin.php?page=geekybot_postinstallation&geekybotlt=stepone'))));
        }
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
                    GEEKYBOTupdates::GEEKYBOT_checkUpdates('104');
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
        $logdata = "\n geekybot_add_loading_message_script";
        $logdata .= "\n ------------------ \n ";
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
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

    public function my_info_notice() {
        
    }

    public function geekybot_synchronize_available_notice() {
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
            if (geekybot::$_configuration['title'] != '') {
                $title = geekybot::$_configuration['title'];
            } else {
                $title = __('GeekyBot', 'geeky-bot');
            }
            $botImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getBotImagePath();
            $userImgScr = GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getUserImagePath();
            $html = wp_enqueue_style('geekybot-fontawesome', GEEKYBOT_PLUGIN_URL . 'includes/css/font-awesome.css', array(), GEEKYBOT_PLUGIN_VERSION, 'all');
            if (geekybot::$_configuration['welcome_message'] != '') {
                $html .='
                <div class="chat-open-outer-popup-dialog" style="display: none;">';
                    if(geekybot::$_configuration['welcome_message_img'] != '0'){
                        $msgImgPath =GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->getWelcomeMessageImagePath();
                        $html .='
                        <div class="chat-open-outer-popup-dialog-image"><img src="'.esc_url($msgImgPath).'" alt="'.esc_html(__('Logo', 'geeky-bot')).'" title="'.esc_html(__('Logo', 'geeky-bot')).'"/></div>';
                    }
                    $html .='
                    <p onclick="geekybotChatOpenDialog();" class="chat-open-outer-popup-dialog-text">'.wp_kses(geekybot::$_configuration['welcome_message'], GEEKYBOT_ALLOWED_TAGS).'</p>
                    <span onclick="geekybotHideSmartPopup();" id="hideSmartPopup" class="chat-open-outer-popup-dialog-top-cross-button">
                        <img src="'.esc_url(GEEKYBOT_PLUGIN_URL).'/includes/images/control_panel/close-icon.png" alt="'.esc_html(__('Close', 'geeky-bot')).'" title="'.esc_html(__('Close', 'geeky-bot')).'"/>
                    </span>
                    <span class="chat-open-outer-popup-dialog-btmborderwrp"></apan>
                </div>';
            }
            $html .='
            <div class="chat-open-dialog-main">
                <div class="chat-open-dialog-main-inner">
                    <button class="chat-open-dialog">
                      <div class="chat-open-dialog-img">
                       <img class="wp-chat-image" alt="screen tag" src="'. esc_url($botImgScr) .'" /></button>
                      </div>
                </div>
            </div>
            <div class="chat-button-destroy-main">
                <div class="chat-button-destroy-main-inner">
                    <button class="chat-button-destroy"></button>
                </div>
            </div>
            <div class="chat-popup">
              <div class="chat-windows chat-main">
                <div class="chat-window-one">
                    <div class="chat-header">
                      <h4>Hello</h4>
                      <h4>I am ChatBot</h4>
                    </div>
                    <div class="chat-middle">
                      <div class="chat-middle-inner">
                        <div class="chat-middle-inner-border">
                            <div class="chat-middle-inner-img">
                                <img src="'.esc_url($botImgScr).'" alt="'.esc_html(__('Bot', 'geeky-bot')).'" />
                            </div>
                        </div>
                      </div>
                      <h5>How can I help you?</h5>
                    </div>
                    <div class="chat-start chat-btm" id="customerdata">
                      <div class="chat-start-inner" id="startchat">
                            <span class="chat-start-title">Start Conversation</span>
                            <span class="chat-start-img">
                                <img src="'.esc_url(GEEKYBOT_PLUGIN_URL).'/includes/images/chat-img/arrow.png" alt="'.esc_html(__('arrow', 'geeky-bot')).'" />
                            </span>
                      </div>
                    </div>
                    <div class="chat-powered-by">
                        <div class="chat-powered-by-inner">
                            <div class="chat-powered-by-inner-img">
                                <img src="'.esc_url($botImgScr).'" alt="'.esc_html(__('Bot', 'geeky-bot')).'" />
                            </div>
                            <div class="chat-powered-by-inner-cnt">
                                <a class="chat-start-title" href="#"> Powered by WPChatBot</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="main-messages" class="chat-window-two" style="display: none;">
                <div class="geekybot-title-main-overlay">
                    <div id="window-two-title" class="window-two-top">
                        <div class="window-two-top-inner">
                            <div class="window-two-top-inner-left">
                                <div class="window-two-profile">
                                    <div class="window-two-profile-img">
                                        <img src="'.esc_url($botImgScr).'" alt="'.esc_html(__('Bot', 'geeky-bot')).'" />
                                    </div>
                                </div>
                                <i class="fa fa-circle"></i>
                                <div class="window-two-profile-text">
                                    <div class="window-two-text">
                                        <span>'.esc_html($title).'</span>
                                        <span>'.esc_html(__('online', 'geeky-bot')).'</span>
                                    </div>
                                </div>
                                <div class="geekybot-title-overlay"></div>
                            </div>
                            <div class="window-two-top-inner-right">
                                <div class="window-two-top-dot-img" onclick="myFunction()" id="dna">
                                    <img src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'/includes/images/chat-img/menu.png" alt="'.esc_html(__('Menu', 'geeky-bot')).'" />
                                </div>
                            </div>
                        </div>
                        <div id="myDropdown" class="dropdown-content">
                            <div class="geekybot-main-overlay" id="jsendchat">
                                <div>'.esc_html(__('End Chat', 'geeky-bot')).'</div>
                                <div class="geekybot-overlay">'.esc_html(__('End Chat', 'geeky-bot')).'</div>
                            </div>
                            <div class="geekybot-main-overlay" id="restartchat">
                                <div>'.esc_html(__('Restart Chat', 'geeky-bot')).'</div>
                                <div class="geekybot-overlay">'.esc_html(__('Restart Chat', 'geeky-bot')).'</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="previouschatbox" class="chat-content"></div>
                <div class="geekbot-actualmsg-main-section">';
                    if (geekybot::$_configuration['welcome_message'] != '') {
                        $html .= '
                        <div class="chat-content welcome-message">
                            <li class="actual_msg actual_msg_adm">
                                <section class="actual_msg_adm-img">
                                    <img src="'.esc_url($botImgScr).'" alt="'.esc_html(__('Image', 'geeky-bot')).'">
                                </section>
                                <section class="actual_msg_text">
                                    '.geekybot::$_configuration['welcome_message'].'
                                </section>
                            </li>
                        </div>';
                    }
                    $html .= '
                    <div id="chatbox" class="chat-content">';
                        if(isset($_COOKIE['geekybot_chat_id'])){
                            $chatId = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();  
                            $query = "SELECT sessionmsgvalue  FROM `" . geekybot::$_db->prefix . "geekybot_sessiondata` WHERE usersessionid = '".esc_sql($chatId)."' and sessionmsgkey = 'chathistory'";
                            $conversion = geekybotdb::GEEKYBOT_get_var($query);
                            if ($conversion != null) {
                                $html .= html_entity_decode($conversion);
                            }
                        }
                    $html .='
                    </div>
                </div>
                    <div id="send-message" class="col-md-12 p-2 msg-box window-two-btm">';
                        $chat_id = GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->geekybot_getchatid();
                        $html .='
                        <input type="hidden" id="chatsession"  value="'.$chat_id.'">
                        <input type="hidden" id="response_id"  value="">
                        <div class="window-two-btm-inner">
                            <div class="window-two-btm-inner-left">
                                <input id="msg_box" type="text" class="border-0 msg_box" placeholder="'.esc_html(__('Send message', 'geeky-bot')).'" autocomplete="off" />
                            </div>
                            <div class="window-two-btm-inner-right">
                                <div class="window-two-btm-send-img">
                                    <img id="snd-btn" src="'. esc_url(GEEKYBOT_PLUGIN_URL) .'/includes/images/chat-img/send-icon.png" alt="'.esc_html(__('Send Icon', 'geeky-bot')).'" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
            </div>';
            $chatpopupcode = $html;
            $html = '';
            if(isset($_COOKIE['geekybot_chat_id'])){
                $html .= 'jQuery(".chat-popup").addClass("active");';
                if (geekybot::$_configuration['welcome_screen'] == '2') {
                    $html.='
                    jQuery(".chat-window-one").hide();
                    jQuery(".chat-popup").addClass("chat-init");
                    jQuery("#main-messages").show();
                    jQuery(".chat-button-destroy").addClass("active");
                    ';
                } else {
                    $html.='
                    jQuery(".chat-button-destroy").addClass("active");
                    ';
                }
                $html.='
                var scrollableDiv = jQuery("#main-messages");
                scrollableDiv.scrollTop(scrollableDiv[0].scrollHeight);
                ';
            }
            $html .= '
            function geekybot_DecodeHTML(html) {
                var txt = document.createElement("textarea");
                txt.innerHTML = html;
                return txt.value;
            }
            function geekybot_scrollToTop(difference) {
                var scrollheight = jQuery("#main-messages").get(0).scrollHeight;
                var scrollPosition = jQuery(".chat-window-two").get(0).scrollHeight;
                if(scrollheight > 600) {
                    jQuery(".chat-window-two").animate({scrollTop: scrollPosition - difference},350);
                }
            }
            jQuery(function() {
                jQuery(".chat-open-dialog").click(function() {
                    jQuery(".chat-open-outer-popup-dialog").hide();
                    jQuery(this).toggleClass("active");
                    jQuery(".chat-popup").toggleClass("active");
                    jQuery(".chat-button-destroy").toggleClass("active");
                    jQuery(".chat-popup").toggleClass("");
                    if (jQuery(".chat-popup").hasClass("active")) {
                        getRandomChatId();';
                        if (geekybot::$_configuration['welcome_screen'] == '2') {
                            $html.='
                            jQuery(".chat-window-one").hide();
                            jQuery(".chat-popup").addClass("chat-init");
                            jQuery("#main-messages").show();
                            ';
                        }
                        $html.='
                    }
                });
            });

            jQuery(function() {
                jQuery(".chat-button-destroy").click(function() {
                    jQuery(".chat-popup").removeClass("active");
                    jQuery(".chat-open-dialog").removeClass("active");
                    jQuery(this).removeClass("active");
                });
            });
            /* When the user clicks on the button,
            toggle between hiding and showing the dropdown content */
            function myFunction() {
                document.getElementById("myDropdown").classList.toggle("show");
            }
            ( function( jQuery ) {
                jQuery("#startchat").on("click",function(e){
                    e.preventDefault();
                    // var jslc_id = Cookies.get("jslc_id");
                    //var path = window.location.href;
                    //jQuery(".chat-button-destroy").addClass("active-inner");
                    //var mySecondDiv=jQuery(\'<div class="chat-open-dialog-img"><img class="wp-chat-image" alt="" src="'.esc_url($botImgScr).'"></div>\');
                    //jQuery(".chat-button-destroy").append(mySecondDiv);
                    //jQuery(this).toggleClass("active");
                    jQuery(".chat-popup").toggleClass("chat-init");
                    jQuery("#main-messages").show();
                    jQuery(".chat-window-one").hide();
                });
            } )( jQuery );';

           $html.=' jQuery("#snd-btn").click(function(event){
                var message = jQuery(".msg_box").val();
                if (!message) {
                    alert("Please enter a message to before sending");
                    return false;
                } else {
                    var sender = "user";

                    jQuery(".msg_box").val("");';
            $html .=" var sender = 'user';
                      var btnflag = 'false';
                     var chat_id = jQuery('#chatsession').val();
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_user'><section class='actual_msg_user-img'><img src='".esc_url($userImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    var response_id =  jQuery('#response_id').val();
                    // SaveChathistory(message,sender);
                    sendRequestToServer(message,message,sender,chat_id);

            }});";

            $html.=' jQuery(".msg_box").keypress(function(event){
                    if ( event.which == 13 ) {
                        var message = jQuery(".msg_box").val();
                        if (!message) {
                            alert("Please enter a message to before sending");
                            return false;
                        } else {
                            var sender = "user";

                            jQuery(".msg_box").val("");';
            $html .=" var sender = 'user';
                     var chat_id = jQuery('#chatsession').val();
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_user'><section class='actual_msg_user-img'><img src='".esc_url($userImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                     var response_id =  jQuery('#response_id').val();
                     var btnflag = 'false';
                    // SaveChathistory(message,sender);
                    sendRequestToServer(message,message,sender,chat_id);
                  }
                    }});";

            $html.='
            function getRandomChatId() {
                var x = new Date();

                var hours=x.getHours().toString();
                hours=hours.length==1 ? 0+hours : hours;

                var minutes=x.getMinutes().toString();
                minutes=minutes.length==1 ? 0+minutes : minutes;

                var seconds=x.getSeconds().toString();
                seconds=seconds.length==1 ? 0+seconds : seconds;

                var month=(x.getMonth() +1).toString();
                month=month.length==1 ? 0+month : month;

                var dt=x.getDate().toString();
                dt=dt.length==1 ? 0+dt : dt;

                var x1=  x.getFullYear() + "-" + month + "-" + dt;
                x1 = x1 + "  " +  hours + ":" +  minutes + ":" +  seconds ;
                var dt = x1;
                var user = "user";
                ';

            $html .= "var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";

            $html .= "
                jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'getRandomChatId', datetime: dt, '_wpnonce':'". esc_attr(wp_create_nonce("get-random-chat-id"))."' }, function (data) {";
            $html .= ' if (data) {
                        var chat_id = data;
                        jQuery("#chatsession").val(data);
                        // closechat(); recheck
                        // it close the chat after some time even if the user is typing message - hamza
                    }';

          $html.='});
                }
                function startDictation() {
                var sender = "user";
                var chat_id = jQuery("#chatsession").val();
                $i = jQuery(".fa-microphone");
                $i.removeClass("fa-microphone").addClass("fa-circle");
                $i.css({"color":"red",});
                console.log($i);
                if (window.hasOwnProperty("webkitSpeechRecognition")) {
                    var recognition = new webkitSpeechRecognition();
                    recognition.continuous = false;
                    recognition.interimResults = false;
                    recognition.lang = "en-US";
                    recognition.start();
                    recognition.onresult = function(e) {
                        jQuery(".msg_box").val("");
                        var message = e.results[0][0].transcript';
                    $html .="
                  jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_user'><section class='actual_msg_user-img'><img src='".esc_url($userImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                      var response_id =  jQuery('#response_id').val();
                      var btnflag = 'false';
                       // SaveChathistory(message,sender);
                       sendRequestToServer(message,message,sender,chat_id);

                        recognition.stop();
                    };";
             $html.='
                    recognition.onerror = function(e) {
                        console.log(e);
                        recognition.stop();
                    }
                }

                setTimeout(function() {
                    $i.removeClass("fa-circle").addClass("fa-microphone");
                    $i.css("color", "#bdbfc1");
                }, 2000);

            }';
            $html.='function SaveChathistory(message,sender) {

            ';
            $html.="var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            var response_id =  jQuery('#response_id').val();
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'SaveChathistory', cmessage: message,csender:sender, '_wpnonce':'".esc_attr(wp_create_nonce("save-chat-history"))."' }, function (data) {";
            $html.='if (data) {
                        if(sender=="user") {
                            jQuery("#response_id").val(data);
                        }
                    }
                });';
         $html.='}';
         $server_url = "";
         $html.='function sendRequestToServer(message,text,sender,chat_id){

            jQuery.ajax({
                ';
            if(geekybot::$_configuration['ai_search'] == 0){
                $html .= 'url: "'.esc_url(admin_url('admin-ajax.php')).'",type: "POST",async: true,
                data:( {"action": "geekybot_frontendajax", "geekybotme": "chatserver", "task": "getMessageResponse", "message": message,cmessage: message,ctext: text,csender:sender, "_wpnonce":"'.esc_attr(wp_create_nonce('get-message-response')).'"}),
                ';
            } else {
                //link removed form here
				//http://216.128.138.145:8039
				//https://bulkoff.com/test_bot8 
				
                $html .= '
				url: "http://216.128.138.145:8042/webhooks/rest/webhook",type: "POST",async: true,data:JSON.stringify( { "message": message,"senser" : "adnan",}),
                headers: {';
                  $html.="  'Content-Type':'application/json',
                            'accept': 'application/json',
                            'Access-Control-Allow-Origin':'*'
                  ";

                 $html.='    },';

            }
            $html .='
            }).done(function(data) {
                // console.log(data);
                geekybot_scrollToTop(150);
                //jQuery(\"#main-messages\").scrollTop(jQuery(\"#main-messages\")[0].scrollHeight);



                    // if(data.id==uid){
                    jQuery("#typing_message").remove();

                  ';
                if(geekybot::$_configuration['ai_search'] == 0){
                    $html.='
                    var data = JSON.parse(data);';
                } else {
                    $html.=' ';
                }
                $html .="
                if (data && Array.isArray(data) && data.length > 0) {
                    jQuery.each(data, function( index, value ) {
                        if (value.text) {
                            var sender = 'bot';
                            ";
							if(geekybot::$_configuration['ai_search'] == 0){
								$html .= "
                                var message = geekybot_DecodeHTML(value.text.bot_response);
                                if (typeof value.text.bot_articles !== 'undefined') {
                                    message += geekybot_DecodeHTML(value.text.bot_articles);
                                }";
							}else{
								$html .= " var message = geekybot_DecodeHTML(value.text);";
							}
				            $html .="
                            var btn   = value.buttons;
                            var btnhtml ='';
                            var text = value.text;
                            var btnflag = 'false';
                            var response_id =  jQuery('#response_id').val();
                            // error with woocommerce code
                            //message = message.replace( /((http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?)/g,'<a href=\"$1\">$1</a>');
                            jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section>\");
                            
                            // SaveChathistory(message,sender);
                            if(btn) {
                                btnhtml += \"<div class='actual_msg_btn'>\";
                                jQuery.each(btn, function(i,btns){
                                    var btntext = btns.text;
                                    var btnvalue = btns.value;
                                    var btntype = btns.type;
                                    var btnflag = 'true';
                                    if(btntype == 1) {
                                        btnhtml+=  \"<li class='actual_msg actual_msg_btn' style=''><section><button class='wp-chat-btn' onclick='sendbtnrsponse(this);' value='\"+btnvalue+\"'><span>\"+btntext+\"</span></section></button></li>\";
                                    } else if(btntype == 2) {
                                        btnhtml+=  \"<li class='actual_msg actual_msg_btn' style=''><section><button class='wp-chat-btn'><span><a class='wp-chat-btn-link' href='\"+btnvalue+\"'>\"+btntext+\"</a></span></section></button></li>\";
                                    }
                                });
                                btnhtml += \"</div>\"; 
                                jQuery(\"#chatbox\").append(btnhtml);
                            }
                            jQuery(\"#chatbox\").append(\"</li>\");
                        } else if (value.image) {
                            var sender = 'bot';
                            var message = value.image;
                            var btnflag = 'true';
                            // SaveChathistory(message,sender);

                            jQuery(\"#chatbox\").append(\"<li class='actual_msg_img'><img src=\"+value.image+\" alt='Girl in a jacket' width='250' height='150'></li>\");
                        } else if (value.action) {
                            var sender = 'bot';
                            var message = geekybot_DecodeHTML(value.action.text);
                            var btn   = value.buttons;
                            var btnhtml ='';
                            var btnflag = 'false';
                            var response_id =  jQuery('#response_id').val();
                            jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+ message+\"</section></li>\");
                            // SaveChathistory(message,sender);
                                if(btn) {
                                    btnhtml += \"<div class='actual_msg_btn'>\";
                                    jQuery.each(btn, function(i,btns){
                                    var btnmsg = btns.title;
                                    var btnflag = 'true';
                                    // SaveChathistory(btnmsg,sender);
                                    btnhtml+=  \"<li class='actual_msg actual_msg_btn' style=''><section><button class='wp-chat-btn' onclick='sendbtnrsponse(this);' value='\"+btns.title+\"'><span>\"+btns.title+\"</span></section></button></li>\";

                                });
                                btnhtml += \"</div>\"; 
                                jQuery(\"#chatbox\").append(btnhtml);

                            }
                        }
                    });
                } else {
                    var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
                    jQuery.post(ajaxurl, {
                        action: 'geekybot_frontendajax',
                        geekybotme: 'chatserver',
                        task: 'getDefaultFallBackFormAjax',
                        chat_id: chat_id,
                        '_wpnonce':'". esc_attr(wp_create_nonce('get-fallback')) ."'
                    }, function(fbdata) {
                        if (fbdata) {
                            console.log(fbdata);
                            jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+fbdata+\"</section></li>\");
                        } else {
                            console.error('AJAX Error:', textStatus, errorThrown);
                        }
                    });
                }
            }).fail(function(data, textStatus, xhr) {
                var configmsg = '".esc_attr(geekybot::$_configuration['default_message'])."';

                jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+configmsg+\"</section></li>\");
            });
        }";
        $html .="jQuery(document).ready(function(){
        jQuery(\"div#jsendchat\").on('click',function(){
            var sender = 'user';
            var chat_id = jQuery('#chatsession').val();
            var message = 'Chat End by user';
            var date = new Date();
            date.setTime(date.getTime());
                ";
        $html.="var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
        $html.="
        jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'endUserChat', cmessage: message,sender:sender ,chat_id:chat_id, '_wpnonce':'". esc_attr(wp_create_nonce("end-user-chat")) ."'}, function (data) {";
        $html.='
            if (data) {
                jQuery("#chatbox").empty();
                var path = window.location.href;
                jQuery(".chat-popup").toggleClass("chat-init");';
                if (geekybot::$_configuration['welcome_screen'] == '1') {
                    $html.='jQuery("#main-messages").hide();
                    jQuery(".chat-window-one").show();';
                } else {
                    $html.='jQuery(".chat-popup").toggleClass("chat-init");
                    jQuery("#main-messages").show();
                    jQuery(".chat-window-one").hide();';
                }
                $html.='
                jQuery(".chat-open-dialog").removeClass("active");
                jQuery(".chat-button-destroy").removeClass("active");
                jQuery(".chat-popup").removeClass("active");
                jQuery(".dropdown-content").removeClass("show");
                // set empty value for session on end chat
                jQuery("#chatsession").val("");
            } else {
            }
        });
    });
});';
        // code start for custom function
        $html .= "
        function geekybotAddToCart(pid) {
            var message = '".esc_html(__('Add to cart', 'geeky-bot'))."';
            SaveChathistory(message,'user');
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html .= "
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotAddToCart', productid: pid, '_wpnonce':'".esc_attr(wp_create_nonce("add-to-cart")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(120);
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+data+\"</section></li>\");
                }
            });
        }
        function getProductAttributes(pid, isnew, attr) {
            // var attributes_recheck = JSON.parse(attr);
            var attributes = attr;
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'getProductAttributes', productid: pid, isnew: isnew, attr: attributes, '_wpnonce':'".esc_attr(wp_create_nonce("product-attributes")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(100);
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+data+\"</section></li>\");
                }
            });
        }
        function saveProductAttributeToSession(productid, attributekey, attributevalue, userattributes) {
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'saveProductAttributeToSession', productid: productid, attributekey: attributekey, attributevalue: attributevalue, userattributes: userattributes, '_wpnonce':'".esc_attr(wp_create_nonce("save-product-attribute")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(100);
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+data+\"</section></li>\");
                    
                }
            });
        }
        function geekybotLoadMoreProducts(msg, next_page, model_name, function_name, dataArray) {
            var message = '".esc_html(__('Show More', 'geeky-bot'))."';
            SaveChathistory(message,'user');
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'geekybot', task: 'geekybotLoadMoreProducts', msg: msg, next_page: next_page,modelName : model_name,functionName : function_name,data : dataArray, '_wpnonce':'".esc_attr(wp_create_nonce("load-more")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(190);
                    var message = geekybot_DecodeHTML(data)
                    jQuery(\"div.geekybot_wc_product_load_more_wrp\").css(\"display\", \"none\");
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    
                }
            });
        }
        function geekybotLoadMoreCustomPosts(msg, data_array, next_page, function_name) {
            var message = '".esc_html(__('Show More', 'geeky-bot'))."';
            SaveChathistory(message,'user');
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'geekybot', task: 'geekybotLoadMoreCustomPosts', msg : msg, dataArray : data_array, next_page: next_page, functionName : function_name, '_wpnonce':'".esc_attr(wp_create_nonce("load-more")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(190);
                    var message = geekybot_DecodeHTML(data);
                    jQuery(\"div.geekybot_wc_product_load_more_wrp\").css(\"display\", \"none\");
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    
                }
            });
        }
        function geekybotRemoveCartItem(variation_id, product_id) {
            var message = '".esc_html(__('Remove Item', 'geeky-bot'))."';
            SaveChathistory(message,'user');
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotRemoveCartItem', variation_id: variation_id, product_id: product_id, '_wpnonce':'".esc_attr(wp_create_nonce("remove-item")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(100);
                    var message = geekybot_DecodeHTML(data)
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    
                }
            });
        }
        function geekybotUpdateCartItemQty(cart_item_key) {
            var message = '".esc_html(__('Change quantity', 'geeky-bot'))."';
            SaveChathistory(message,'user');
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotUpdateCartItemQty', cart_item_key: cart_item_key, '_wpnonce':'".esc_attr(wp_create_nonce("update-quantity")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(100);
                    var message = geekybot_DecodeHTML(data)
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    
                }
            });
        }
        function geekybotUpdateCartItemQuantity(cart_item_key,product_id) {
            const clickedSpan = jQuery(event.target);
            var product_quantity = clickedSpan.siblings('input#product_quantity').val();
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotUpdateCartItemQuantity', product_quantity: product_quantity, cart_item_key: cart_item_key, '_wpnonce':'".esc_attr(wp_create_nonce("update-quantity")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(100);
                    var message = geekybot_DecodeHTML(data)
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    
                }
            });
        }
        function geekybotViewCart() {
            var message = '".esc_html(__('View Cart', 'geeky-bot'))."';
            SaveChathistory(message,'user');
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'woocommerce', task: 'geekybotViewCart', '_wpnonce':'".esc_attr(wp_create_nonce("view-cart")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(100);
                    var message = geekybot_DecodeHTML(data)
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                }
            });
        }

        function showArticlesList(msg, type, highest_score, total_posts, current_page) {
            var message = '".esc_html(__('Show Articles', 'geeky-bot'))."';
            SaveChathistory(message,'user');
            var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
            $html.="
            jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'websearch', task: 'showArticlesList', msg: msg, type: type, highestScore: highest_score, totalPosts: total_posts, currentPage: current_page, '_wpnonce':'".esc_attr(wp_create_nonce("articles-list")) ."'}, function (data) {
                if (data) {
                    geekybot_scrollToTop(100);
                    var message = geekybot_DecodeHTML(data);
                    jQuery(\".geekybot_wc_post_load_more\").css(\"display\", \"none\");
                    jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_adm'><section class='actual_msg_adm-img'><img src='".esc_url($botImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+message+\"</section></li>\");
                    
                }
            });
        }
        ";
        // code end for custome function
    $html .="jQuery(document).ready(function(){
        jQuery(\"div#restartchat\").on('click',function(){
            var sender = 'user';
            var chat_id = jQuery('#chatsession').val();
            var message = 'Chat Restarted';
            var x = new Date();
            var hours=x.getHours().toString();
            hours=hours.length==1 ? 0+hours : hours;
            var minutes=x.getMinutes().toString();
            minutes=minutes.length==1 ? 0+minutes : minutes;
            var seconds=x.getSeconds().toString();
            seconds=seconds.length==1 ? 0+seconds : seconds;
            var month=(x.getMonth() +1).toString();
            month=month.length==1 ? 0+month : month;
            var dt=x.getDate().toString();
            dt=dt.length==1 ? 0+dt : dt; ";

    $html .='
            var x1=  x.getFullYear() + "-" + month + "-" + dt;
            x1 = x1 + "  " +  hours + ":" +  minutes + ":" +  seconds ;
            var dt = x1;
                ';
        $html.="var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';";
        $html.="
                   jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'restartUserChat', datetime:dt, '_wpnonce':'". esc_attr(wp_create_nonce("restart-user-chat")). "'}, function (data) {";
        $html.=' if (data) {
                        jQuery("#chatsession").val(data)
                        jQuery("#chatbox").empty();
                        jQuery(".dropdown-content").removeClass("show");
                     }else{

                      }  ';

          $html.='});';


                 $html .='
           });
           });  ';
        $html.='
        function closechat(){
        var chat_id = jQuery("#chatsession").val();
        if(chat_id!=""){
            setTimeout(function(){';
                $html.="
                var message = '".__('session time out', 'geeky-bot')."';
                var sender  = 'user';
                var ajaxurl = '".esc_url(admin_url('admin-ajax.php'))."';
                jQuery.post(ajaxurl, { action: 'geekybot_frontendajax', geekybotme: 'chathistory', task: 'endUserChat', cmessage: message,sender:sender ,chat_id:chat_id, '_wpnonce':'". esc_attr(wp_create_nonce("end-user-chat")) ."'}, function (data) {";
                        $html.='
                        if (data) {
                            jQuery("#chatbox").empty();
                            var path = window.location.href;
                            jQuery(".chat-popup").toggleClass("chat-init");
                            jQuery("#main-messages").hide();';
                            if (geekybot::$_configuration['welcome_screen'] == '2') {
                                $html.='
                                jQuery(".chat-window-one").hide();';
                            }
                            $html.='
                            jQuery(".chat-open-dialog").removeClass("active");
                            jQuery(".chat-button-destroy").removeClass("active");
                            jQuery(".chat-popup").removeClass("active");
                            jQuery(".dropdown-content").removeClass("show");
                        }else{

                        }';
                        $html.='
                    });
                }, 500000);
            }
        };

        function sendbtnrsponse(msg) {
            var sender = "user";
            var message = msg.value;
            var text = jQuery(msg).find("span").text();
            var chat_id = jQuery("#chatsession").val();
            ';
            $html.="
            jQuery(\"#chatbox\").append(\"<li class='actual_msg actual_msg_user'><section class='actual_msg_user-img'><img src='".esc_url($userImgScr)."' alt='' /></section><section class='actual_msg_text'>\"+text+\"</section></li>\");
            var ajaxurl =
                '". esc_url(admin_url("admin-ajax.php")) ."';
            jQuery.post(ajaxurl, {
                action: 'geekybot_ajax',
                geekybotme: 'slots',
                message: message,
                task: 'saveVariableFromButtonIntent',
                '_wpnonce':'". esc_attr(wp_create_nonce("button-intent")). "'
            }, function(data) {
                if (data) {
                    sendRequestToServer(data,text,sender,chat_id);
                }
            });
        }

        function geekybotHideSmartPopup(msg) {
            jQuery('.chat-open-outer-popup-dialog').fadeOut();
        }

        function geekybotChatOpenDialog() {
            jQuery('.chat-open-dialog').click();
        }

        // Code to open the chat popup
        document.addEventListener('DOMContentLoaded', function() {";
            if ( geekybot::$_configuration['auto_chat_start'] == 1 && geekybot::$_configuration['auto_chat_start_time'] != '' ) {
                $startTime = geekybot::$_configuration['auto_chat_start_time'];
                // change time from seconds to miliseconds
                $startTime = $startTime * 1000;
                $html.="
                setTimeout(function() {
                    // Code to open the chat popup if not already opened
                    if (!jQuery('.chat-popup').hasClass('active')) { ";
                        if(!isset($_COOKIE['geekybot_chat_id'])){
                            if ( geekybot::$_configuration['auto_chat_type'] == 1  ) {
                                $html.="
                                jQuery('.chat-open-outer-popup-dialog').fadeIn().css('display', 'flex');
                                ";
                            } else {
                                $html.="    
                                jQuery('.chat-open-dialog').click();
                                ";
                            }
                        }
                        $html.="
                    }
                }, ".$startTime.");";
            }
            $html.="
        });


        ";
        $geekybot_js = $html;
        wp_register_script( 'geekybot-frontend-handle', '' , array(), GEEKYBOT_PLUGIN_VERSION, 'all');
        wp_enqueue_script( 'geekybot-frontend-handle' );
        wp_add_inline_script('geekybot-frontend-handle',$geekybot_js);
        echo wp_kses($chatpopupcode, GEEKYBOT_ALLOWED_TAGS);
        // wp_add_inline_script('geekybot-main-js',$geekybot_js);
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

    function geekybot_safe_style_css(){
        $styles[] = 'display';
        $styles[] = 'color';
        $styles[] = 'width';
        $styles[] = 'max-width';
        $styles[] = 'min-width';
        $styles[] = 'height';
        $styles[] = 'min-height';
        $styles[] = 'max-height';
        $styles[] = 'background-color';
        $styles[] = 'border';
        $styles[] = 'border-bottom';
        $styles[] = 'border-top';
        $styles[] = 'border-left';
        $styles[] = 'border-right';
        $styles[] = 'border-color';
        $styles[] = 'padding';
        $styles[] = 'padding-top';
        $styles[] = 'padding-bottom';
        $styles[] = 'padding-left';
        $styles[] = 'padding-right';
        $styles[] = 'margin';
        $styles[] = 'margin-top';
        $styles[] = 'margin-bottom';
        $styles[] = 'margin-left';
        $styles[] = 'margin-right';
        $styles[] = 'background';
        $styles[] = 'font-weight';
        $styles[] = 'font-size';
        $styles[] = 'text-align';
        $styles[] = 'text-decoration';
        $styles[] = 'text-transform';
        $styles[] = 'line-height';
        $styles[] = 'visibility';
        $styles[] = 'cellspacing';
        $styles[] = 'data-id';
        $styles[] = 'cursor';
        $styles[] = 'vertical-align';
        $styles[] = 'float';
        $styles[] = 'position';
        $styles[] = 'left';
        $styles[] = 'right';
        $styles[] = 'bottom';
        $styles[] = 'top';
        $styles[] = 'z-index';
        $styles[] = 'overflow';
        return $styles;
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
                // Get the post data from WordPress
                $title = get_the_title( $post_id );
                $content = $post->post_content;
                $status = get_post_status( $post_id );
                $post_text = $title.' '.$post->post_content.' ';
                // ---------------
                // Get all taxonomies associated with the post
                $taxonomies = get_object_taxonomies(get_post_type($post_id));
                // Loop through each taxonomy to get the terms
                foreach ($taxonomies as $taxonomy) {
                    // Get terms for this taxonomy
                    $terms = get_the_terms($post_id, $taxonomy);
                    if ( ! empty($terms) && ! is_wp_error($terms) ) {
                        foreach ( $terms as $term ) {
                            $post_text .= $term->name.' ';
                        }
                    }
                }
                // ---------------
                $skip_storing_process = 0;
                if ($post->post_type == 'forum') {
                    $skip_storing_process = 1;
                    
                    $p_id = $post->ID;
                    $p_title = $post->post_title;
                    $p_content = $post->post_content;
                    $p_post_text = '';
                    $p_post_type = $post->post_type;
                    $p_post_id = $post->ID;
                    $p_status = $post->post_status;
                    $bbp_forum_id = $post->ID;
                    $store_topic = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotCheckTopicStatusForBBpress();
                    if ($store_topic == 1) {
                        // get all the topics related to this forum
                        $meta_key = '_bbp_forum_id';
                        $meta_value = $bbp_forum_id;
                        $args = array(
                            'post_type'  => 'topic',// Limit to post type 'topic'
                            'post_status'   => 'publish',// Only fetch published posts
                            'meta_key'   => $meta_key,
                            'meta_value' => $meta_value,
                            'posts_per_page' => -1, // Get all matching posts
                            'orderby' => 'ID', // Order by ID
                            'order' => 'ASC', // Order by assending
                        );
                        $topics = get_posts($args);
                        if (!empty($topics)) {
                            foreach ($topics as $topic) {
                                $post_text .= $topic->post_title . ' ' .$topic->post_content .' ';
                            }
                        }
                    }
                    $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    $post_text = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->stripslashesFull($post_text);// remove slashes with quotes.
                    $p_post_text = $post_text;
                    $batch_data[] = '("'.esc_sql($p_id).'","'.esc_sql($p_title).'","'.esc_sql($p_content).'","'.esc_sql($p_post_text).'","'.esc_sql($p_post_id).'","'.esc_sql($p_post_type).'","'.esc_sql($p_status).'")';
                    // Insert the current batch
                    $insert_query = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotPostTypeBuildQuery($batch_data);
                    geekybot::$_db->query($insert_query);
                    
                } elseif ($post->post_type == 'topic') {
                    $skip_storing_process = 1;
                    if (is_admin()) {
                        $bbp_forum_id = get_post_meta($post->ID, '_bbp_forum_id', true);
                        if (isset($bbp_forum_id) && is_numeric($bbp_forum_id) && $bbp_forum_id != 0) {
                            $post_text = '';
                            $forum = get_post( $bbp_forum_id );
                            $post_text = $forum->post_title.' ';
                            $post_text .= $forum->post_content.' ';

                            $p_id = $bbp_forum_id;
                            $p_title = $forum->post_title;
                            $p_content = $forum->post_content;
                            $p_post_type = $forum->post_type;
                            $p_post_id = $bbp_forum_id;
                            $p_status = $forum->post_status;

                            $meta_key = '_bbp_forum_id';
                            $meta_value = $bbp_forum_id;
                            $args = array(
                                'post_type'  => 'topic',// Limit to post type 'topic'
                                'post_status'   => 'publish',// Only fetch published posts
                                'meta_key'   => $meta_key,
                                'meta_value' => $meta_value,
                                'posts_per_page' => -1, // Get all matching posts
                                'orderby' => 'ID', // Order by ID
                                'order' => 'ASC', // Order by assending
                            );
                            $topics = get_posts($args);
                            if (!empty($topics)) {
                                foreach ($topics as $topic) {
                                    $post_text .= $topic->post_title . ' ' .$topic->post_content .' ';
                                }
                            }
                            $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                            $post_text = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->stripslashesFull($post_text);// remove slashes with quotes.
                            $p_post_text = $post_text;
                            $batch_data[] = '("'.esc_sql($p_id).'","'.esc_sql($p_title).'","'.esc_sql($p_content).'","'.esc_sql($p_post_text).'","'.esc_sql($p_post_id).'","'.esc_sql($p_post_type).'","'.esc_sql($p_status).'")';
                            // Insert the current batch
                            $insert_query = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotPostTypeBuildQuery($batch_data);
                            geekybot::$_db->query($insert_query);
                        }
                    }
                } else {
                    // List of meta keys to exclude
                    $exclude_meta_keys = array(
                        '_edit_lock',
                        '_edit_last',
                        '_wp_old_slug',
                        '_thumbnail_id',
                        '_wp_trash_meta_status',
                        '_wp_trash_meta_time',
                        '_pingme',
                        '_encloseme',
                        '_wp_attached_file',
                        '_wp_attachment_metadata',
                        '_wp_attachment_image_alt',
                        '_wp_page_template',
                        '_menu_item',
                        '_wpb_vc_js_status',
                        '_elementor_data'
                    );
                    $post_meta = get_post_meta($post->ID); // Get all post meta
                    // Loop through the meta and filter useful data
                    if (!empty($post_meta)) {
                        foreach ($post_meta as $meta_key => $meta_value) {
                            // Filter out empty values
                            if (!empty($meta_value) && !in_array($meta_key, $exclude_meta_keys)) {
                                $post_text .= $meta_key.' ';
                                $post_text .= $meta_value[0].' ';
                            }
                        }
                    }
                }
                if ($skip_storing_process == 0) {
                    $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    $post_text = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->stripslashesFull($post_text);// remove slashes with quotes.

                    $query = "SELECT id  FROM `" . geekybot::$_db->prefix . "geekybot_posts` WHERE post_id = ".esc_sql($post_id);
                    $query .= " ORDER BY id DESC ";
                    $bot_post_id = geekybotdb::GEEKYBOT_get_var($query);
                    if (isset($bot_post_id) && $bot_post_id != '') {
                        $post_data['id'] = $bot_post_id;
                    }
                    $post_data['ID'] = $post_id;
                    $post_data['title'] = $title;
                    $post_data['content'] = $content;
                    $post_data['post_text'] = $post_text;
                    $post_data['post_id'] = $post_id;
                    $post_data['post_type'] = $post->post_type;
                    $post_data['status'] = $status;
                    // check for duplicate record
                    $post_row = GEEKYBOTincluder::GEEKYBOT_getTable('posts');
                    $post_row->bind($post_data);
                    $post_row->store();
                }
            }
        }
    }

    function geekybot_bbpress_topic_create_and_update( $topic_id = 0, $forum_id = 0, $anonymous_data = array()) {
        // Bail early if topic is by anonymous user
        if ( ! empty( $anonymous_data ) ) {
            return;
        }
        // Bail if site is private
        if ( ! bbp_is_site_public() ) {
            return;
        }
        // Bail if topic is not published
        if ( ! bbp_is_topic_published( $topic_id ) ) {
            return;
        }
        // Check if posts are enabled for your system
        if ( geekybot::$_configuration['is_posts_enable'] == 0 ) {
            return;
        }
        $store_topic = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotCheckTopicStatusForBBpress();
        if ( $store_topic == 0 ) {
            return;
        }
        // Check if the post type matches the one you're interested in
        if (isset($forum_id) && is_numeric($forum_id) && $forum_id != 0 && $forum_id != $topic_id) {
            // get the forum data
            $forum = get_post( $forum_id );
            $post_text = $forum->post_title.' ';
            $post_text .= $forum->post_content.' ';

            $p_id = $forum_id;
            $p_title = $forum->post_title;
            $p_content = $forum->post_content;
            $p_post_type = $forum->post_type;
            $p_post_id = $forum_id;
            $p_status = $forum->post_status;
            $args = array(
                'post_type'     => 'topic',// Limit to post type 'topic'
                'post_status'   => 'publish',// Only fetch published posts
                'meta_key'      => '_bbp_forum_id',
                'meta_value'    => $forum_id,
                'posts_per_page'=> -1, // Get all matching posts
                'orderby'       => 'ID', // Order by ID
                'order'         => 'ASC', // Order by assending (optional)
            );
            $topics = get_posts($args);
            if (!empty($topics)) {
                foreach ($topics as $topic) {
                    $post_text .= $topic->post_title . ' ' .$topic->post_content .' ';
                }
            }
            $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
            $post_text = GEEKYBOTincluder::GEEKYBOT_getModel('intent')->stripslashesFull($post_text);// remove slashes with quotes.
            $p_post_text = $post_text;
            $batch_data[] = '("'.esc_sql($p_id).'","'.esc_sql($p_title).'","'.esc_sql($p_content).'","'.esc_sql($p_post_text).'","'.esc_sql($p_post_id).'","'.esc_sql($p_post_type).'","'.esc_sql($p_status).'")';
            // Insert the current batch
            $insert_query = GEEKYBOTincluder::GEEKYBOT_getModel('websearch')->geekybotPostTypeBuildQuery($batch_data);
            geekybot::$_db->query($insert_query);
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
/*
To Handle you are not allowed to manage plugins for this site Error
*/
function json_basic_auth_handler( $user ) {
    global $wp_json_basic_auth_error;
    $wp_json_basic_auth_error = null;
    // Don't authenticate twice
    if ( ! empty( $user ) ) {
        return $user;
    }
    // Check that we're trying to authenticate
    if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
        return $user;
    }
    $username = geekybot::GEEKYBOT_sanitizeData($_SERVER['PHP_AUTH_USER']);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
    $password = geekybot::GEEKYBOT_sanitizeData($_SERVER['PHP_AUTH_PW']);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
    /**
     * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
     * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
     * recursion and a stack overflow unless the current function is removed from the determine_current_user
     * filter during authentication.
     */
    remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
    $user = wp_authenticate( $username, $password );
    add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
    if ( is_wp_error( $user ) ) {
        $wp_json_basic_auth_error = $user;
        return null;
    }
    $wp_json_basic_auth_error = true;
    return $user->ID;
}
add_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );

function json_basic_auth_error( $error ) {
    // Passthrough other errors
    if ( ! empty( $error ) ) {
        return $error;
    }

    global $wp_json_basic_auth_error;

    return $wp_json_basic_auth_error;
}
add_filter( 'rest_authentication_errors', 'json_basic_auth_error' );
/*
 * Rest API to connect with geekybot , we will use it to check status etc
 */
// recheck
// add_action('rest_api_init', 'GeekyBotCustomRoutes');
// add_action( 'rest_api_init', array( 'Geekybot_REST_API', 'init' ) );

function GeekyBotCustomRoutes() {
    register_rest_route('geekybot', '/createRig', array(
        // 'methods' => 'POST',  //\WP_REST_Server::CREATABLE,//
        'callback' => 'createbotRig',
    ));
    register_rest_route('geekybot', '/updateActivationKeyStatus', array(
        // 'methods' => 'POST',  //\WP_REST_Server::CREATABLE,//
        'callback' => 'updateActivationKeyStatus',
    ));
}

function createbotRig($data) {
    $response = 'This is test API';
    /*   $query = "SELECT name  FROM `" . geekybot::$_db->prefix . "geekybot_intents` ";
    $rows = geekybotdb::GEEKYBOT_get_results($query);*/
    return rest_ensure_response($response);
}

function updateActivationKeyStatus($data) {
    // GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->checkLicense();
    $response = 'Status Updated Successfully!';
    return rest_ensure_response($response);
}
?>
