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
        // The plugin header declares `Domain Path: /languages` and now ships a
        // translation there. Modern WordPress finds it on its own -- verified on
        // 7.1 -- but the header also declares `Requires at least: 6.0`, and the
        // just-in-time loader did not always scan a plugin's own directory on
        // those releases. Registering the path keeps the bundled translation
        // working across every version the plugin claims to support, and is a
        // no-op where WordPress already loaded it.
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled translations must load on the oldest supported release, not only where just-in-time loading covers them.
        load_plugin_textdomain('geeky-bot', false, dirname(GEEKYBOT_BASENAME) . '/languages');

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
