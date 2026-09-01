<?php
namespace GeekyBot;

if (!defined('ABSPATH')) {
    exit;
}

use GeekyBot\Admin\Menu;
use GeekyBot\Frontend\Widget;
use GeekyBot\REST\Api;
use GeekyBot\Services\HealthService;
use GeekyBot\Services\Installer;
use GeekyBot\Services\KnowledgeIndexService;
use GeekyBot\Services\LicenseService;
use GeekyBot\Services\ProductIndexService;
use GeekyBot\Services\PrivacyService;
use GeekyBot\Services\OnboardingService;
use GeekyBot\Services\ShopperOutputService;

final class Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function boot() {
        if (!Installer::maybe_upgrade()) {
            if (is_admin()) {
                add_action('admin_notices', array($this, 'database_upgrade_notice'));
            }
            return;
        }

        LicenseService::hooks();

        if (is_admin()) {
            (new Menu())->hooks();
            (new HealthService())->hooks();
        }

        (new Widget())->hooks();
        (new ShopperOutputService())->hooks();
        (new Api())->hooks();
        (new ProductIndexService())->hooks();
        (new KnowledgeIndexService())->hooks();
        (new PrivacyService())->hooks();
        (new OnboardingService())->hooks();


        /**
         * Fires after Geeky Bot core services and public hooks are loaded.
         * Paid/commercial add-ons should attach their integration points here instead of replacing core files.
         */
        do_action('geekybot_core_loaded');

        add_filter('plugin_action_links_' . GEEKYBOT_BASENAME, array($this, 'action_links'));
    }

    public function database_upgrade_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html__('Geeky Bot could not complete its database update. Please reload the page, then contact support if the notice remains.', 'geeky-bot') . '</p></div>';
    }

    public function action_links($links) {
        $settings_url = admin_url('admin.php?page=geekybot-settings');
        array_unshift($links, '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'geeky-bot') . '</a>');
        return $links;
    }
}
