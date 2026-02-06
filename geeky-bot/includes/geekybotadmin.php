<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class geekybotadmin {

    function __construct() {
        add_action('admin_menu', array($this, 'GEEKYBOT_mainmenu'));
    }

    function GEEKYBOT_mainmenu() {
        add_menu_page(__('Control Panel', 'geeky-bot'), // Page title
            __('GeekyBot', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot', //menu slug
            array($this, 'GEEKYBOT_showAdminPage'), // function name
            plugins_url('geeky-bot/includes/images/admin_geekybot1.png'),26
        );
        add_submenu_page('geekybot', // parent slug
            __('Content Generation', 'geeky-bot'), // Page title
            __('Content Generation', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_zywrap', //menu slug (matches our module)
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot_hide', // parent slug
            __('AI Settings', 'geeky-bot'), // Page title
            __('AI Settings', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_zywrap&geekybotlt=zywrap',
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot_hide', // parent slug
            __('AI Logs', 'geeky-bot'), // Page title
            __('AI Logs', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_zywraplogs', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );
        
        add_submenu_page('geekybot', // parent slug
            __('Stories', 'geeky-bot'), // Page title
            __('Stories', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_stories', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot', // parent slug
            __('AI Web Search', 'geeky-bot'), // Page title
            __('AI Web Search', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_websearch', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot_hide', // parent slug
            __('Keys', 'geeky-bot'), // Page title
            __('Keys', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_keycheck', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot', // parent slug
            __('Settings', 'geeky-bot'), // Page title
            __('Settings', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_configuration', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot', // parent slug
            __('Chatbot', 'geeky-bot'), // Page title
            __('Chatbot', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_themes', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot', // parent slug
            __('Install Add-ons', 'geeky-bot'), // Page title
            __('Install Add-ons', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_premiumplugin', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot', // parent slug
            __('Chat History', 'geeky-bot'), // Page title
            __('Chat History', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_chathistory', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot', // parent slug
            __('Variables', 'geeky-bot'), // Page title
            __('Variables', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_slots', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );

        add_submenu_page('geekybot_hide', // parent slug
            __('Post Installation', 'geeky-bot'), // Page title
            __('Post Installation', 'geeky-bot'), // menu title
            'geekybot', // capability
            'geekybot_postinstallation', //menu slug
            array($this, 'GEEKYBOT_showAdminPage') // function name
        );
    }

    static  function GEEKYBOT_showAdminPage() {
        geekybot::addStyleSheets();
        $page = GEEKYBOTrequest::GEEKYBOT_getVar('page');
        $page = geekybotphplib::GEEKYBOT_str_replace('geekybot_', '', $page);
        GEEKYBOTincluder::GEEKYBOT_include_file($page);
    }

    function GEEKYBOT_addMissingAddonPage($module_name){
        add_submenu_page('geekybot_hide', // parent slug
                __('Premium Addon', 'geeky-bot'), // Page title
                __('Premium Addon', 'geeky-bot'), // menu title
                'geekybot', // capability
                $module_name, //menu slug
                array($this, 'showMissingAddonPage') // function name
        );
    }

}

$geekybotAdmin = new geekybotadmin();
?>
