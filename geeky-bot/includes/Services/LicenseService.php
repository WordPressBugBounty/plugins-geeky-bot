<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;

class LicenseService {
    const OPTION = 'geekybot_commerce_pro_license';
    const UPDATE_OPTION = 'geekybot_commerce_pro_update_cache';
    const CDN_UPDATE_OPTION = 'geekybot_commerce_pro_cdn_update_cache';
    const UPDATE_SETTINGS_OPTION = 'geekybot_commerce_pro_update_settings';
    const PRODUCT = 'commerce-pro';
    const PRO_BASENAME = 'geeky-bot-commerce-pro/geeky-bot-commerce-pro.php';
    const DEFAULT_SERVER = 'https://geekybot.com/wp-json/geekybot-license/v1';
    const DEFAULT_CDN_METADATA_URL = 'https://cdn.geekybot.com/releases/commerce-pro/stable.json';
    const CHECK_HOOK = 'geekybot_commerce_pro_license_check';
    const UPDATE_CHECK_HOOK = 'geekybot_commerce_pro_version_check';
    const INSTALLATION_ID_OPTION = 'geekybot_installation_id_v2';
    const BINDING_VERSION = '2';
    const LEGACY_BINDING_ATTEMPT_TRANSIENT = 'geekybot_license_binding_upgrade_attempt';

    private static $data_cache = null;

    public static function hooks() {
        add_filter('geekybot_commerce_pro_license_active', array(__CLASS__, 'filter_license_active'));
        add_filter('geekybot_commerce_pro_license_status', array(__CLASS__, 'filter_license_status'));
        add_action(self::CHECK_HOOK, array(__CLASS__, 'scheduled_refresh'));
        add_action(self::UPDATE_CHECK_HOOK, array(__CLASS__, 'scheduled_update_refresh'));
        add_action('admin_notices', array(__CLASS__, 'admin_grace_notice'));
        add_action('admin_notices', array(__CLASS__, 'admin_update_notice'));
        add_action('admin_init', array(__CLASS__, 'maybe_upgrade_legacy_binding'), 5);
        self::maybe_schedule_license_check();
        self::maybe_schedule_update_check();
    }

    public static function defaults() {
        return array(
            'license_key' => '',
            'license_key_encrypted' => '',
            'license_key_masked' => '',
            'license_key_hash' => '',
            'status' => 'inactive',
            'message' => '',
            'plan' => '',
            'customer_email' => '',
            'site_id' => '',
            'site_url' => self::normalized_site_url(),
            'domain' => self::site_domain(),
            'installation_id' => '',
            'site_fingerprint' => '',
            'binding_version' => '',
            'is_staging' => self::is_staging_site() ? 'yes' : 'no',
            'allowed_sites' => 0,
            'allowed_staging_sites' => 0,
            'activation_count' => 0,
            'expires_at' => '',
            'valid_until' => '',
            'grace_until' => '',
            'last_checked_at' => '',
            'last_successful_check_at' => '',
            'last_error' => '',
            'runtime_allowed' => 'no',
            'updates_allowed' => 'no',
            'downloads_allowed' => 'no',
            'new_activations_allowed' => 'no',
            'support_allowed' => 'no',
            'notice_level' => '',
            'signed_payload' => '',
            'signed_payload_signature' => '',
            'signature_verified' => 'no',
            'server_url' => self::server_url(),
        );
    }

    public static function data() {
        if (is_array(self::$data_cache)) {
            return self::$data_cache;
        }

        $saved = get_option(self::OPTION, array());
        if (!is_array($saved)) {
            $saved = array();
        }

        $data = wp_parse_args($saved, self::defaults());
        $legacy_key = self::sanitize_license_key(isset($saved['license_key']) ? $saved['license_key'] : '');
        $encrypted = isset($saved['license_key_encrypted']) ? (string) $saved['license_key_encrypted'] : '';
        $decrypted = $encrypted !== '' ? self::sanitize_license_key(LicenseVault::decrypt($encrypted)) : '';
        $data['license_key'] = $decrypted !== '' ? $decrypted : $legacy_key;

        if ($legacy_key !== '' && $encrypted === '' && LicenseVault::available()) {
            $encrypted = LicenseVault::encrypt($legacy_key);
            if ($encrypted !== '') {
                $saved['license_key'] = '';
                $saved['license_key_encrypted'] = $encrypted;
                update_option(self::OPTION, $saved, false);
                $data['license_key_encrypted'] = $encrypted;
            }
        }

        self::$data_cache = $data;
        return $data;
    }

    public static function save($data) {
        $data = wp_parse_args((array) $data, self::defaults());
        $plain_key = self::sanitize_license_key(isset($data['license_key']) ? $data['license_key'] : '');
        $clean = self::sanitize_license_data($data);

        if ($plain_key !== '') {
            $encrypted = LicenseVault::encrypt($plain_key);
            if ($encrypted !== '') {
                $clean['license_key_encrypted'] = $encrypted;
                $clean['license_key'] = '';
            } else {
                // Existing installations without OpenSSL/Sodium must not lose
                // their key during an update. New activations are blocked
                // earlier until secure storage is available.
                $clean['license_key'] = $plain_key;
            }
        } else {
            $clean['license_key'] = '';
        }
        update_option(self::OPTION, $clean, false);

        $runtime = $clean;
        $runtime['license_key'] = $plain_key;
        self::$data_cache = $runtime;
        return $runtime;
    }

    public static function update_settings_defaults() {
        return array(
            'auto_update_mode' => 'manual',
            'update_channel' => 'stable',
            'auto_update_critical' => 'yes',
            'notify_expiring' => 'yes',
        );
    }

    public static function update_settings() {
        $saved = get_option(self::UPDATE_SETTINGS_OPTION, array());
        if (!is_array($saved)) {
            $saved = array();
        }
        return wp_parse_args($saved, self::update_settings_defaults());
    }

    public static function save_update_settings($data) {
        $data = is_array($data) ? $data : array();
        $mode = isset($data['auto_update_mode']) ? sanitize_key((string) $data['auto_update_mode']) : 'manual';
        if (!in_array($mode, array('manual', 'critical', 'patch', 'minor', 'stable'), true)) {
            $mode = 'manual';
        }

        $channel = isset($data['update_channel']) ? sanitize_key((string) $data['update_channel']) : 'stable';
        if (!in_array($channel, array('stable', 'beta', 'dev'), true)) {
            $channel = 'stable';
        }

        $clean = array(
            'auto_update_mode' => $mode,
            'update_channel' => $channel,
            'auto_update_critical' => !empty($data['auto_update_critical']) && $data['auto_update_critical'] === 'yes' ? 'yes' : 'no',
            'notify_expiring' => !empty($data['notify_expiring']) && $data['notify_expiring'] === 'yes' ? 'yes' : 'no',
        );

        update_option(self::UPDATE_SETTINGS_OPTION, $clean, false);
        delete_option(self::UPDATE_OPTION);
        delete_option(self::CDN_UPDATE_OPTION);
        return $clean;
    }

    public static function maybe_schedule_license_check() {
        if (!wp_next_scheduled(self::CHECK_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CHECK_HOOK);
        }
    }

    public static function maybe_schedule_update_check() {
        if (!wp_next_scheduled(self::UPDATE_CHECK_HOOK)) {
            wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', self::UPDATE_CHECK_HOOK);
        }
    }

    public static function clear_scheduled_license_check() {
        wp_clear_scheduled_hook(self::CHECK_HOOK);
        wp_clear_scheduled_hook(self::UPDATE_CHECK_HOOK);
    }

    public static function scheduled_refresh() {
        $data = self::data();
        $license_key = self::sanitize_license_key($data['license_key']);
        $status = sanitize_key((string) $data['status']);

        if ($license_key === '' || !in_array($status, array('active', 'trial', 'expired', 'cancelled'), true)) {
            return;
        }

        self::refresh();
    }

    public static function scheduled_update_refresh() {
        delete_option(self::UPDATE_OPTION);
        delete_option(self::CDN_UPDATE_OPTION);
        self::cached_update_info(true);
    }

    public static function admin_grace_notice() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $data = self::data();
        if (!LicenseVault::available()) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Commerce Pro license storage requires the PHP OpenSSL or Sodium extension. Commerce Pro remains locked until secure local storage is available and the license is refreshed.', 'geeky-bot') . '</p></div>';
            return;
        }
        $status = sanitize_key((string) $data['status']);

        if (!empty($data['license_key']) && !self::runtime_binding_valid($data)) {
            $url = admin_url('admin.php?page=geekybot-addons');
            echo '<div class="notice notice-warning"><p>'
                . esc_html__('Commerce Pro needs a fresh license verification before it can run on this site.', 'geeky-bot')
                . ' <a href="' . esc_url($url) . '">' . esc_html__('Open license screen', 'geeky-bot') . '</a></p></div>';
            return;
        }

        if ($status === 'expired' && self::truthy($data['runtime_allowed'])) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Your Commerce Pro license has expired. Buying actions continue on this activated site, but updates, protected downloads, support, and new activations require renewal.', 'geeky-bot') . '</p></div>';
            return;
        }

        if ($status === 'cancelled' && self::truthy($data['runtime_allowed'])) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Your Commerce Pro subscription is cancelled but remains active until the paid period ends. Renew to keep updates, downloads, and support active after expiry.', 'geeky-bot') . '</p></div>';
            return;
        }

        if (!self::is_running_on_grace($data)) {
            return;
        }

        echo '<div class="notice notice-warning"><p>' . esc_html__('Could not refresh the Commerce Pro license recently. Commerce Pro is running on cached verification until the grace period expires.', 'geeky-bot') . '</p></div>';
    }

    public static function filter_license_active($active) {
        return self::is_commerce_pro_active();
    }

    public static function filter_license_status($status) {
        return self::status_summary();
    }

    public static function status_summary() {
        $data = self::data();
        $status = sanitize_key((string) $data['status']);
        $label = self::status_label($status);
        if ($status === 'activation_limit_reached') {
            $label = self::truthy($data['is_staging'])
                ? __('Staging limit reached', 'geeky-bot')
                : __('Production limit reached', 'geeky-bot');
        }

        return array(
            'active' => self::is_commerce_pro_active(),
            'status' => $status,
            'label' => $label,
            'message' => (string) $data['message'],
            'maskedKey' => (string) $data['license_key_masked'],
            'plan' => (string) $data['plan'],
            'expiresAt' => (string) $data['expires_at'],
            'validUntil' => (string) $data['valid_until'],
            'graceUntil' => (string) $data['grace_until'],
            'lastCheckedAt' => (string) $data['last_checked_at'],
            'lastSuccessfulCheckAt' => (string) $data['last_successful_check_at'],
            'bindingValid' => self::runtime_binding_valid($data),
            'signatureVerified' => self::truthy($data['signature_verified']),
            'signatureRequired' => self::public_key_configured(),
            'lastError' => (string) $data['last_error'],
            'isGrace' => self::is_running_on_grace($data),
            'siteUrl' => (string) $data['site_url'],
            'domain' => (string) $data['domain'],
            'isStaging' => (string) $data['is_staging'],
            'allowedSites' => absint($data['allowed_sites']),
            'allowedStagingSites' => absint($data['allowed_staging_sites']),
            'activationCount' => absint($data['activation_count']),
            'runtimeAllowed' => self::truthy($data['runtime_allowed']),
            'updatesAllowed' => self::truthy($data['updates_allowed']),
            'downloadsAllowed' => self::truthy($data['downloads_allowed']),
            'newActivationsAllowed' => self::truthy($data['new_activations_allowed']),
            'supportAllowed' => self::truthy($data['support_allowed']),
            'noticeLevel' => sanitize_key((string) $data['notice_level']),
        );
    }

    public static function is_commerce_pro_active() {
        $data = self::data();

        if (!self::runtime_binding_valid($data)) {
            return false;
        }

        $entitlement = self::entitlement_data($data);
        if (!$entitlement) {
            return false;
        }

        $status = sanitize_key((string) $entitlement['status']);

        if (in_array($status, array('refunded', 'disabled', 'domain_mismatch', 'activation_limit_reached', 'inactive'), true)) {
            return false;
        }

        $runtime_allowed = self::truthy(isset($entitlement['runtime_allowed']) ? $entitlement['runtime_allowed'] : 'no');
        if (!$runtime_allowed) {
            return false;
        }

        // Expired licenses may intentionally retain runtime access on a site
        // that was already verified, while updates/downloads/support remain
        // disabled by separate entitlements. All other temporary states must
        // stay inside the server-issued verification/grace window.
        if ($status === 'expired') {
            return true;
        }

        if (!in_array($status, array('active', 'trial', 'cancelled'), true)) {
            return false;
        }

        $now = time();
        $valid_until = self::datetime_to_timestamp(isset($entitlement['valid_until']) ? $entitlement['valid_until'] : '');
        $grace_until = self::datetime_to_timestamp(isset($entitlement['grace_until']) ? $entitlement['grace_until'] : '');

        return ($valid_until && $valid_until >= $now) || ($grace_until && $grace_until >= $now);
    }

    public static function can_receive_updates() {
        $data = self::data();
        $entitlement = self::entitlement_data($data);
        return self::is_commerce_pro_active() && !empty($entitlement) && self::truthy(isset($entitlement['updates_allowed']) ? $entitlement['updates_allowed'] : 'no');
    }

    public static function can_download_packages() {
        $data = self::data();
        $entitlement = self::entitlement_data($data);
        return self::is_commerce_pro_active() && !empty($entitlement) && self::truthy(isset($entitlement['downloads_allowed']) ? $entitlement['downloads_allowed'] : 'no');
    }

    public static function is_running_on_grace($data = null) {
        $data = is_array($data) ? $data : self::data();
        $entitlement = self::entitlement_data($data);
        if (!$entitlement) {
            return false;
        }
        $now = time();
        $valid_until = self::datetime_to_timestamp(isset($entitlement['valid_until']) ? $entitlement['valid_until'] : '');
        $grace_until = self::datetime_to_timestamp(isset($entitlement['grace_until']) ? $entitlement['grace_until'] : '');

        return $grace_until && $grace_until >= $now && (!$valid_until || $valid_until < $now);
    }

    public static function activate($license_key) {
        if (!LicenseVault::available()) {
            return new WP_Error('geekybot_license_crypto_unavailable', __('This server cannot securely store the Commerce Pro license key. Enable the PHP OpenSSL or Sodium extension, then try again.', 'geeky-bot'));
        }

        $license_key = self::sanitize_license_key($license_key);
        if ($license_key === '') {
            return new WP_Error('geekybot_license_missing', __('Please enter a license key.', 'geeky-bot'));
        }

        $response = self::request('activate', array('license_key' => $license_key));
        if (is_wp_error($response)) {
            return $response;
        }

        $result = self::apply_server_response($response, $license_key, __('Commerce Pro license activated for this site.', 'geeky-bot'));
        if (is_wp_error($result)) {
            return $result;
        }

        return self::authorization_result($result);
    }

    public static function refresh() {
        $data = self::data();
        $license_key = self::sanitize_license_key($data['license_key']);
        if ($license_key === '') {
            return new WP_Error('geekybot_license_missing', __('No Commerce Pro license key is saved yet.', 'geeky-bot'));
        }

        $response = self::request('check', array('license_key' => $license_key));
        if (is_wp_error($response)) {
            $data['last_error'] = $response->get_error_message();
            $data['last_checked_at'] = current_time('mysql');
            self::save($data);
            return $response;
        }

        $result = self::apply_server_response($response, $license_key, __('Commerce Pro license status refreshed.', 'geeky-bot'));
        if (is_wp_error($result)) {
            return $result;
        }

        return self::authorization_result($result);
    }

    public static function deactivate() {
        $data = self::data();
        $license_key = self::sanitize_license_key($data['license_key']);
        if ($license_key !== '') {
            self::request('deactivate', array('license_key' => $license_key));
        }

        $clean = self::defaults();
        $clean['status'] = 'inactive';
        $clean['message'] = __('License deactivated on this site.', 'geeky-bot');
        $clean['last_checked_at'] = current_time('mysql');
        self::save($clean);
        delete_option(self::UPDATE_OPTION);
        do_action('geekybot_commerce_pro_license_deactivated');
        return $clean;
    }

    public static function download_package_url() {
        $data = self::data();
        $license_key = self::sanitize_license_key($data['license_key']);
        if ($license_key === '' || !self::can_download_packages()) {
            return new WP_Error('geekybot_license_required', __('A valid Commerce Pro license with download access is required before installing the add-on.', 'geeky-bot'));
        }

        $response = self::request('download', array('license_key' => $license_key));
        if (is_wp_error($response)) {
            return $response;
        }

        $url = isset($response['download_url']) ? esc_url_raw((string) $response['download_url'], array('https')) : '';
        if ($url === '' || !self::is_allowed_package_url($url)) {
            return new WP_Error('geekybot_bad_package_url', __('The license server did not return a safe Commerce Pro package URL.', 'geeky-bot'));
        }

        return $url;
    }

    public static function install_commerce_pro() {
        if (!current_user_can('install_plugins')) {
            return new WP_Error('geekybot_no_install_capability', __('You do not have permission to install plugins.', 'geeky-bot'));
        }

        $package_url = self::download_package_url();
        if (is_wp_error($package_url)) {
            return $package_url;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->install($package_url);

        if (is_wp_error($result)) {
            return $result;
        }

        if (!$result) {
            $messages = method_exists($skin, 'get_upgrade_messages') ? implode(' ', (array) $skin->get_upgrade_messages()) : '';
            return new WP_Error('geekybot_install_failed', $messages ? wp_strip_all_tags($messages) : __('Commerce Pro could not be installed.', 'geeky-bot'));
        }

        if (current_user_can('activate_plugins') && file_exists(WP_PLUGIN_DIR . '/' . self::PRO_BASENAME)) {
            $activate = activate_plugin(self::PRO_BASENAME, admin_url('admin.php?page=geekybot-addons'), false, true);
            if (is_wp_error($activate)) {
                return $activate;
            }
        }

        return true;
    }

    public static function activate_commerce_pro_plugin() {
        if (!current_user_can('activate_plugins')) {
            return new WP_Error('geekybot_no_activate_capability', __('You do not have permission to activate plugins.', 'geeky-bot'));
        }
        if (!file_exists(WP_PLUGIN_DIR . '/' . self::PRO_BASENAME)) {
            return new WP_Error('geekybot_pro_not_installed', __('Commerce Pro is not installed yet.', 'geeky-bot'));
        }
        if (!self::is_commerce_pro_active()) {
            return new WP_Error('geekybot_license_required', __('Activate a valid Commerce Pro license before activating the add-on.', 'geeky-bot'));
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $result = activate_plugin(self::PRO_BASENAME, admin_url('admin.php?page=geekybot-addons'), false, true);
        return is_wp_error($result) ? $result : true;
    }

    public static function update_commerce_pro() {
        if (!current_user_can('update_plugins')) {
            return new WP_Error('geekybot_no_update_capability', __('You do not have permission to update plugins.', 'geeky-bot'));
        }

        if (!file_exists(WP_PLUGIN_DIR . '/' . self::PRO_BASENAME)) {
            return new WP_Error('geekybot_pro_not_installed', __('Commerce Pro is not installed yet.', 'geeky-bot'));
        }

        if (!self::can_receive_updates()) {
            return new WP_Error('geekybot_license_required', __('Your Commerce Pro license must include update access before updating the add-on.', 'geeky-bot'));
        }

        $summary = self::update_summary(true);
        if (empty($summary['update_available'])) {
            return new WP_Error('geekybot_no_update_available', __('Commerce Pro is already on the latest available version.', 'geeky-bot'));
        }

        $update = isset($summary['update']) && is_array($summary['update']) ? $summary['update'] : array();
        if (empty($update['package']) || empty($update['latest_version'])) {
            return new WP_Error('geekybot_update_package_missing', __('The license server did not return a valid Commerce Pro update package.', 'geeky-bot'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';

        $was_active = is_plugin_active(self::PRO_BASENAME);

        $transient = get_site_transient('update_plugins');
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }
        if (empty($transient->checked) || !is_array($transient->checked)) {
            $transient->checked = array();
        }
        $transient->checked[self::PRO_BASENAME] = (string) $summary['installed_version'];
        if (empty($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }
        $transient->response[self::PRO_BASENAME] = self::update_transient_item($update);
        set_site_transient('update_plugins', $transient);

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->upgrade(self::PRO_BASENAME);

        delete_option(self::UPDATE_OPTION);
        delete_option(self::CDN_UPDATE_OPTION);
        delete_site_transient('update_plugins');
        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(true);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        if (!$result) {
            $messages = method_exists($skin, 'get_upgrade_messages') ? implode(' ', (array) $skin->get_upgrade_messages()) : '';
            return new WP_Error('geekybot_update_failed', $messages ? wp_strip_all_tags($messages) : __('Commerce Pro could not be updated.', 'geeky-bot'));
        }

        $reactivated = false;
        if ($was_active && !is_plugin_active(self::PRO_BASENAME) && current_user_can('activate_plugins')) {
            $activate = activate_plugin(self::PRO_BASENAME, admin_url('admin.php?page=geekybot-addons'), false, true);
            if (is_wp_error($activate)) {
                return $activate;
            }
            $reactivated = true;
        }

        delete_option(self::UPDATE_OPTION);
        delete_option(self::CDN_UPDATE_OPTION);
        delete_site_transient('update_plugins');
        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(true);
        }

        return array(
            'updated' => true,
            'reactivated' => $reactivated,
            'was_active' => $was_active,
            'version' => self::commerce_pro_plugin_status()['version'],
        );
    }

    public static function commerce_pro_plugin_status() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $file = WP_PLUGIN_DIR . '/' . self::PRO_BASENAME;
        $installed = file_exists($file);
        $active = $installed && is_plugin_active(self::PRO_BASENAME);
        $version = '';
        if ($installed) {
            $data = get_plugin_data($file, false, false);
            $version = isset($data['Version']) ? sanitize_text_field((string) $data['Version']) : '';
        }

        return array(
            'installed' => $installed,
            'active' => $active,
            'version' => $version,
            'basename' => self::PRO_BASENAME,
        );
    }

    public static function fresh_pro_package_for_update($reply, $package, $upgrader, $hook_extra) {
        if ($reply !== false) {
            return $reply;
        }

        $plugins = array();
        if (is_array($hook_extra)) {
            if (!empty($hook_extra['plugin'])) {
                $plugins[] = (string) $hook_extra['plugin'];
            }
            if (!empty($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
                $plugins = array_merge($plugins, array_map('strval', $hook_extra['plugins']));
            }
        }

        if (!in_array(self::PRO_BASENAME, $plugins, true)) {
            return $reply;
        }

        if (!self::can_receive_updates()) {
            return new WP_Error('geekybot_license_required', __('Your Commerce Pro license must include update access before updating the add-on.', 'geeky-bot'));
        }

        $fresh_url = self::download_package_url();
        if (is_wp_error($fresh_url)) {
            return $fresh_url;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $download = download_url($fresh_url, 300);
        if (is_wp_error($download)) {
            return $download;
        }

        $summary = self::update_summary(false);
        $update = isset($summary['update']) && is_array($summary['update']) ? $summary['update'] : array();
        $checksum = isset($update['checksum']) ? (string) $update['checksum'] : '';
        $valid = self::validate_downloaded_zip($download, $checksum);
        if (is_wp_error($valid)) {
            wp_delete_file($download);
            return $valid;
        }

        return $download;
    }

    private static function validate_downloaded_zip($path, $checksum = '') {
        if (!is_string($path) || $path === '' || !file_exists($path) || !is_readable($path)) {
            return new WP_Error('geekybot_update_zip_missing', __('The Commerce Pro update package could not be downloaded.', 'geeky-bot'));
        }

        $size = filesize($path);
        if (!$size || $size < 22) {
            return new WP_Error('geekybot_update_zip_empty', __('The Commerce Pro update package is empty or incomplete.', 'geeky-bot'));
        }

        // Direct streaming avoids loading a potentially large ZIP into memory; WP_Filesystem does not expose a partial-read API.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen($path, 'rb');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
        $signature = $handle ? fread($handle, 4) : '';
        if ($handle) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            fclose($handle);
        }

        if (!in_array($signature, array("PK\x03\x04", "PK\x05\x06", "PK\x07\x08"), true)) {
            /**
             * Fires when a downloaded Commerce Pro package is not a ZIP file.
             *
             * Only the binary signature is provided to trusted server-side
             * logging integrations. Response-body samples are never displayed
             * or stored by Geeky Bot because they may contain private server
             * details.
             */
            do_action('geekybot_internal_update_package_error', 'invalid_zip_signature', bin2hex((string) $signature));

            return new WP_Error(
                'geekybot_update_zip_invalid',
                __('The Commerce Pro update package is invalid. Please try again or contact Geeky Bot support.', 'geeky-bot')
            );
        }

        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $opened = $zip->open($path, \ZipArchive::CHECKCONS);
            if ($opened !== true) {
                return new WP_Error('geekybot_update_zip_corrupt', __('The downloaded Commerce Pro package is not a valid ZIP archive.', 'geeky-bot'));
            }
            $zip->close();
        }

        $checksum = sanitize_text_field((string) $checksum);
        if ($checksum !== '' && strlen($checksum) === 64) {
            $actual = hash_file('sha256', $path);
            if (!hash_equals(strtolower($checksum), strtolower($actual))) {
                return new WP_Error('geekybot_update_checksum_failed', __('The downloaded Commerce Pro package did not match the release checksum.', 'geeky-bot'));
            }
        }

        return true;
    }


    public static function inject_pro_update($transient) {
        if (!is_object($transient) || empty($transient->checked) || !isset($transient->checked[self::PRO_BASENAME])) {
            return $transient;
        }
        $update = self::cached_update_info();
        if (!$update || empty($update['latest_version']) || empty($update['package'])) {
            return $transient;
        }

        $installed = isset($transient->checked[self::PRO_BASENAME]) ? (string) $transient->checked[self::PRO_BASENAME] : '';
        if ($installed === '' || version_compare($update['latest_version'], $installed, '<=')) {
            return $transient;
        }

        $transient->response[self::PRO_BASENAME] = self::update_transient_item($update);
        return $transient;
    }

    public static function update_summary($force = false) {
        $plugin = self::commerce_pro_plugin_status();
        $metadata = self::cached_cdn_metadata($force);
        $update = self::cached_update_info($force);
        $installed = isset($plugin['version']) ? (string) $plugin['version'] : '';
        $latest = '';
        if (is_array($metadata) && !empty($metadata['latest_version'])) {
            $latest = (string) $metadata['latest_version'];
        } elseif (is_array($update) && !empty($update['latest_version'])) {
            $latest = (string) $update['latest_version'];
        }

        return array(
            'installed_version' => $installed,
            'latest_version' => $latest,
            'update_available' => $installed !== '' && $latest !== '' && version_compare($latest, $installed, '>'),
            'metadata' => is_array($metadata) ? $metadata : array(),
            'update' => is_array($update) ? $update : array(),
            'settings' => self::update_settings(),
            'cdn_url' => self::cdn_metadata_url(),
            'can_update' => self::can_receive_updates(),
        );
    }

    public static function refresh_update_status() {
        delete_option(self::UPDATE_OPTION);
        delete_option(self::CDN_UPDATE_OPTION);
        return self::update_summary(true);
    }

    public static function admin_update_notice() {
        if (!is_admin() || !current_user_can('update_plugins')) {
            return;
        }

        $summary = self::update_summary(false);
        if (empty($summary['update_available'])) {
            return;
        }

        $metadata = isset($summary['metadata']) && is_array($summary['metadata']) ? $summary['metadata'] : array();
        $critical = !empty($metadata['critical']) && $metadata['critical'] === 'yes';
        $security = !empty($metadata['security']) && $metadata['security'] === 'yes';
        $is_geekybot_page = self::is_geekybot_admin_page();

        if (!$critical && !$security && !$is_geekybot_page) {
            return;
        }

        if (!$critical && !$security && self::is_geekybot_addons_page()) {
            return;
        }

        $class = ($critical || $security) ? 'notice notice-error geekybot-admin-update-notice' : 'notice notice-info geekybot-admin-update-notice';
        $message = ($critical || $security)
            ? sprintf(
                /* translators: 1: available Commerce Pro version, 2: installed Commerce Pro version. */
                __('Important Commerce Pro security update %1$s is available. Installed version: %2$s.', 'geeky-bot'),
                $summary['latest_version'],
                $summary['installed_version']
            )
            : sprintf(
                /* translators: 1: available Commerce Pro version, 2: installed Commerce Pro version. */
                __('Commerce Pro %1$s is available. Installed version: %2$s.', 'geeky-bot'),
                $summary['latest_version'],
                $summary['installed_version']
            );
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <p>
                <strong><?php echo esc_html($message); ?></strong>
                <?php if (!empty($summary['can_update'])) : ?>
                    <form class="geekybot-inline-notice-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('geekybot_license_action'); ?>
                        <input type="hidden" name="action" value="geekybot_update_commerce_pro" />
                        <?php submit_button(__('Update Commerce Pro', 'geeky-bot'), 'primary small', 'submit', false); ?>
                    </form>
                <?php else : ?>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-addons')); ?>"><?php esc_html_e('Review license', 'geeky-bot'); ?></a>
                <?php endif; ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-addons')); ?>"><?php esc_html_e('Open Add-ons', 'geeky-bot'); ?></a>
            </p>
        </div>
        <?php
    }

    public static function maybe_auto_update_pro($update, $item) {
        $plugin = '';
        if (is_object($item) && !empty($item->plugin)) {
            $plugin = (string) $item->plugin;
        } elseif (is_array($item) && !empty($item['plugin'])) {
            $plugin = (string) $item['plugin'];
        }

        if ($plugin !== self::PRO_BASENAME) {
            return $update;
        }

        $settings = self::update_settings();
        $mode = isset($settings['auto_update_mode']) ? sanitize_key($settings['auto_update_mode']) : 'manual';
        if ($mode === 'manual') {
            return false;
        }

        if (!self::can_receive_updates()) {
            return false;
        }

        $summary = self::update_summary(false);
        if (empty($summary['update_available'])) {
            return false;
        }

        $metadata = isset($summary['metadata']) && is_array($summary['metadata']) ? $summary['metadata'] : array();
        $critical = !empty($metadata['critical']) || !empty($metadata['security']);
        if ($critical && !empty($settings['auto_update_critical']) && $settings['auto_update_critical'] === 'yes') {
            return true;
        }

        if ($mode === 'critical') {
            return false;
        }

        $release_type = self::classify_update($summary['installed_version'], $summary['latest_version']);
        if ($mode === 'patch' && $release_type === 'patch') {
            return true;
        }
        if ($mode === 'minor' && in_array($release_type, array('patch', 'minor'), true)) {
            return true;
        }
        if ($mode === 'stable') {
            $channel = isset($metadata['channel']) ? sanitize_key((string) $metadata['channel']) : 'stable';
            return $channel === 'stable';
        }

        return false;
    }

    private static function update_transient_item($update) {
        return (object) array(
            'id' => self::PRO_BASENAME,
            'slug' => 'geeky-bot-commerce-pro',
            'plugin' => self::PRO_BASENAME,
            'new_version' => isset($update['latest_version']) ? (string) $update['latest_version'] : '',
            'url' => 'https://geekybot.com/',
            'package' => isset($update['package']) ? (string) $update['package'] : '',
            'tested' => isset($update['tested']) ? (string) $update['tested'] : '',
            'requires' => isset($update['requires']) ? (string) $update['requires'] : '',
            'requires_php' => isset($update['requires_php']) ? (string) $update['requires_php'] : '',
        );
    }

    private static function is_geekybot_admin_page() {
        if (empty($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }
        $page = sanitize_key(wp_unslash($_GET['page'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return strpos($page, 'geekybot') === 0;
    }

    private static function is_geekybot_addons_page() {
        if (empty($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }
        $page = sanitize_key(wp_unslash($_GET['page'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return $page === 'geekybot-addons';
    }

    private static function cached_update_info($force = false) {
        $cache = get_option(self::UPDATE_OPTION, array());
        if (!$force && is_array($cache) && !empty($cache['checked_at']) && (time() - absint($cache['checked_at'])) < 6 * HOUR_IN_SECONDS) {
            return isset($cache['update']) && is_array($cache['update']) ? $cache['update'] : array();
        }

        $plugin = self::commerce_pro_plugin_status();
        $installed = isset($plugin['version']) ? (string) $plugin['version'] : '';
        $metadata = self::cached_cdn_metadata($force);
        $latest = is_array($metadata) && !empty($metadata['latest_version']) ? (string) $metadata['latest_version'] : '';

        if ($installed !== '' && $latest !== '' && version_compare($latest, $installed, '<=')) {
            update_option(self::UPDATE_OPTION, array('checked_at' => time(), 'update' => array(), 'metadata' => $metadata), false);
            return array();
        }

        $data = self::data();
        $license_key = self::sanitize_license_key($data['license_key']);
        if ($license_key === '' || !self::can_receive_updates()) {
            update_option(self::UPDATE_OPTION, array('checked_at' => time(), 'update' => array(), 'metadata' => $metadata), false);
            return array();
        }

        $request_body = array(
            'license_key' => $license_key,
            'update_channel' => isset(self::update_settings()['update_channel']) ? self::update_settings()['update_channel'] : 'stable',
            'current_version' => $installed,
            'cdn_latest_version' => $latest,
        );

        $response = self::request('update-check', $request_body, 10);
        if (is_wp_error($response)) {
            update_option(self::UPDATE_OPTION, array('checked_at' => time(), 'update' => array(), 'metadata' => $metadata, 'error' => $response->get_error_message()), false);
            return array();
        }

        $update = isset($response['update']) && is_array($response['update']) ? $response['update'] : $response;
        $clean = array(
            'latest_version' => isset($update['latest_version']) ? sanitize_text_field((string) $update['latest_version']) : '',
            'package' => isset($update['package']) && self::is_allowed_package_url((string) $update['package']) ? esc_url_raw((string) $update['package'], array('https')) : '',
            'tested' => isset($update['tested']) ? sanitize_text_field((string) $update['tested']) : '',
            'requires' => isset($update['requires']) ? sanitize_text_field((string) $update['requires']) : '',
            'requires_php' => isset($update['requires_php']) ? sanitize_text_field((string) $update['requires_php']) : '',
            'requires_woocommerce' => isset($update['requires_woocommerce']) ? sanitize_text_field((string) $update['requires_woocommerce']) : '',
            'requires_core' => isset($update['requires_core']) ? sanitize_text_field((string) $update['requires_core']) : '',
            'checksum' => isset($update['checksum']) ? sanitize_text_field((string) $update['checksum']) : '',
            'critical' => !empty($update['critical']) ? 'yes' : 'no',
            'security' => !empty($update['security']) ? 'yes' : 'no',
            'changelog' => isset($update['changelog']) ? wp_kses_post((string) $update['changelog']) : '',
        );

        update_option(self::UPDATE_OPTION, array('checked_at' => time(), 'update' => $clean, 'metadata' => $metadata), false);
        return $clean;
    }

    private static function cached_cdn_metadata($force = false) {
        $cache = get_option(self::CDN_UPDATE_OPTION, array());
        if (!$force && is_array($cache) && !empty($cache['checked_at']) && (time() - absint($cache['checked_at'])) < 6 * HOUR_IN_SECONDS) {
            return isset($cache['metadata']) && is_array($cache['metadata']) ? $cache['metadata'] : array();
        }

        $url = self::cdn_metadata_url();
        if ($url === '') {
            return array();
        }

        $response = wp_remote_get($url, array(
            'timeout' => 8,
            'redirection' => 0,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 256 * 1024,
            'headers' => array('Accept' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            update_option(self::CDN_UPDATE_OPTION, array('checked_at' => time(), 'metadata' => array(), 'error' => $response->get_error_message()), false);
            return array();
        }

        $code = absint(wp_remote_retrieve_response_code($response));
        $raw = (string) wp_remote_retrieve_body($response);
        if (strlen($raw) > 256 * 1024) {
            update_option(self::CDN_UPDATE_OPTION, array('checked_at' => time(), 'metadata' => array(), 'error' => __('CDN release metadata was too large.', 'geeky-bot')), false);
            return array();
        }
        $json = json_decode($raw, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            update_option(self::CDN_UPDATE_OPTION, array('checked_at' => time(), 'metadata' => array(), 'error' => __('CDN release metadata was not readable.', 'geeky-bot')), false);
            return array();
        }

        $product = isset($json['product']) ? sanitize_key((string) $json['product']) : '';
        if ($product !== self::PRODUCT) {
            update_option(self::CDN_UPDATE_OPTION, array('checked_at' => time(), 'metadata' => array(), 'error' => __('CDN release metadata was for a different product.', 'geeky-bot')), false);
            return array();
        }

        $metadata = array(
            'product' => self::PRODUCT,
            'latest_version' => isset($json['latest_version']) ? sanitize_text_field((string) $json['latest_version']) : '',
            'channel' => isset($json['channel']) ? sanitize_key((string) $json['channel']) : 'stable',
            'released_at' => isset($json['released_at']) ? self::sanitize_datetime((string) $json['released_at']) : '',
            'requires_core' => isset($json['requires_core']) ? sanitize_text_field((string) $json['requires_core']) : '',
            'requires_wp' => isset($json['requires_wp']) ? sanitize_text_field((string) $json['requires_wp']) : '',
            'requires_php' => isset($json['requires_php']) ? sanitize_text_field((string) $json['requires_php']) : '',
            'requires_woocommerce' => isset($json['requires_woocommerce']) ? sanitize_text_field((string) $json['requires_woocommerce']) : '',
            'checksum' => isset($json['checksum']) ? sanitize_text_field((string) $json['checksum']) : '',
            'critical' => !empty($json['critical']) ? 'yes' : 'no',
            'security' => !empty($json['security']) ? 'yes' : 'no',
            'changelog' => isset($json['changelog']) ? wp_kses_post((string) $json['changelog']) : '',
        );

        update_option(self::CDN_UPDATE_OPTION, array('checked_at' => time(), 'metadata' => $metadata), false);
        return $metadata;
    }

    public static function cdn_metadata_url() {
        if (defined('GEEKYBOT_COMMERCE_PRO_VERSION_CDN_URL') && GEEKYBOT_COMMERCE_PRO_VERSION_CDN_URL) {
            $url = (string) GEEKYBOT_COMMERCE_PRO_VERSION_CDN_URL;
        } else {
            $settings = self::update_settings();
            $channel = isset($settings['update_channel']) ? sanitize_key($settings['update_channel']) : 'stable';
            if (!in_array($channel, array('stable', 'beta', 'dev'), true)) {
                $channel = 'stable';
            }
            $url = str_replace('/stable.json', '/' . $channel . '.json', self::DEFAULT_CDN_METADATA_URL);
        }

        return esc_url_raw((string) $url, array('https'));
    }

    private static function classify_update($installed, $latest) {
        $from = self::version_parts($installed);
        $to = self::version_parts($latest);
        if ($to[0] > $from[0]) {
            return 'major';
        }
        if ($to[1] > $from[1]) {
            return 'minor';
        }
        return 'patch';
    }

    private static function version_parts($version) {
        $version = preg_replace('/[^0-9.].*$/', '', (string) $version);
        $parts = array_map('absint', explode('.', $version));
        return array_pad(array_slice($parts, 0, 3), 3, 0);
    }

    private static function request($endpoint, $body, $timeout = 15) {
        $endpoint = sanitize_key($endpoint);
        $allowed = array('activate', 'check', 'deactivate', 'download', 'update-check', 'staging-request');
        if (!in_array($endpoint, $allowed, true)) {
            return new WP_Error('geekybot_license_endpoint_invalid', __('The requested license action is not allowed.', 'geeky-bot'));
        }

        $url = trailingslashit(self::server_url()) . $endpoint;
        $payload = wp_parse_args((array) $body, self::site_payload());
        $payload['request_id'] = wp_generate_uuid4();
        $payload['request_time'] = time();

        $response = wp_remote_post($url, array(
            'timeout' => max(5, min(30, absint($timeout))),
            'redirection' => 0,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 1024 * 1024,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'GeekyBot/' . (defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : 'unknown') . '; ' . self::site_domain(),
            ),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = absint(wp_remote_retrieve_response_code($response));
        $raw = (string) wp_remote_retrieve_body($response);
        if (strlen($raw) > 1024 * 1024) {
            return new WP_Error('geekybot_license_response_too_large', __('The license server response was too large.', 'geeky-bot'));
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return new WP_Error('geekybot_license_bad_response', __('The license server returned an invalid response.', 'geeky-bot'), array('status' => $code));
        }

        if ($code < 200 || $code >= 300 || (isset($data['success']) && !$data['success'])) {
            $message = isset($data['message']) ? sanitize_text_field((string) $data['message']) : __('The license server rejected the request.', 'geeky-bot');
            return new WP_Error('geekybot_license_server_rejected', $message, array('status' => $code));
        }

        // Internal correlation value used only while verifying an optional
        // signed response. It is never persisted or exposed publicly.
        $data['_request_id'] = $payload['request_id'];
        return $data;
    }

    private static function apply_server_response($response, $license_key, $default_message) {
        $signed = self::verified_signed_response($response);
        if (is_wp_error($signed)) {
            return $signed;
        }

        $data = self::data();
        if (is_array($signed) && !empty($signed['_signature_verified'])) {
            $license = !empty($signed['license']) && is_array($signed['license']) ? $signed['license'] : $signed;
        } else {
            $license = isset($response['license']) && is_array($response['license']) ? $response['license'] : $response;
        }
        $status = isset($license['status']) ? sanitize_key((string) $license['status']) : 'inactive';
        $message = isset($response['message']) ? sanitize_text_field((string) $response['message']) : $default_message;

        $data['license_key'] = self::sanitize_license_key($license_key);
        $data['license_key_masked'] = self::mask_license_key($data['license_key']);
        $data['license_key_hash'] = hash('sha256', $data['license_key'] . '|' . wp_salt('auth'));
        $data['status'] = $status;
        $data['message'] = $message;
        $data['plan'] = isset($license['plan']) ? sanitize_text_field((string) $license['plan']) : '';
        $data['customer_email'] = isset($license['customer_email']) ? sanitize_email((string) $license['customer_email']) : '';
        $data['site_id'] = isset($license['site_id']) ? sanitize_text_field((string) $license['site_id']) : '';
        $data['site_url'] = self::normalized_site_url();
        $data['domain'] = self::site_domain();
        $data['installation_id'] = self::installation_id();
        $data['site_fingerprint'] = self::current_site_fingerprint();
        $data['binding_version'] = self::BINDING_VERSION;
        $data['is_staging'] = self::is_staging_site() ? 'yes' : 'no';
        $data['allowed_sites'] = isset($license['allowed_sites']) ? absint($license['allowed_sites']) : 0;
        $data['allowed_staging_sites'] = isset($license['allowed_staging_sites']) ? absint($license['allowed_staging_sites']) : 0;
        $data['activation_count'] = isset($license['activation_count']) ? absint($license['activation_count']) : 0;
        $data['expires_at'] = isset($license['expires_at']) ? self::sanitize_datetime((string) $license['expires_at']) : '';
        $data['valid_until'] = isset($license['valid_until']) ? self::sanitize_datetime((string) $license['valid_until']) : gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS);
        $data['grace_until'] = isset($license['grace_until']) ? self::sanitize_datetime((string) $license['grace_until']) : gmdate('Y-m-d H:i:s', time() + 14 * DAY_IN_SECONDS);
        $data['runtime_allowed'] = self::entitlement_value($license, 'runtime_allowed', in_array($status, array('active', 'trial'), true));
        $data['updates_allowed'] = self::entitlement_value($license, 'updates_allowed', in_array($status, array('active', 'trial'), true));
        $data['downloads_allowed'] = self::entitlement_value($license, 'downloads_allowed', in_array($status, array('active', 'trial'), true));
        $data['new_activations_allowed'] = self::entitlement_value($license, 'new_activations_allowed', in_array($status, array('active', 'trial'), true));
        $data['support_allowed'] = self::entitlement_value($license, 'support_allowed', in_array($status, array('active', 'trial'), true));
        $data['notice_level'] = isset($license['notice_level']) ? sanitize_key((string) $license['notice_level']) : '';
        $data['last_checked_at'] = current_time('mysql');
        $data['last_successful_check_at'] = current_time('mysql');
        $data['last_error'] = '';
        $data['server_url'] = self::server_url();
        $data['signed_payload'] = isset($response['signed_payload']) ? sanitize_textarea_field((string) $response['signed_payload']) : '';
        $data['signed_payload_signature'] = isset($response['signature']) ? sanitize_text_field((string) $response['signature']) : '';
        $data['signature_verified'] = is_array($signed) && !empty($signed['_signature_verified']) ? 'yes' : 'no';

        delete_option(self::UPDATE_OPTION);
        $saved = self::save($data);
        if (self::is_commerce_pro_active()) {
            do_action('geekybot_commerce_pro_license_verified', $saved);
        } else {
            do_action('geekybot_commerce_pro_license_deactivated', $saved);
        }
        return $saved;
    }

    /**
     * Convert a verified but denied entitlement into an admin-facing error.
     *
     * The license server intentionally signs denied entitlement states and may
     * return them over HTTP 200. A valid response transport must not be treated
     * as a successful site authorization.
     *
     * @param array $data Saved license data.
     * @return array|WP_Error
     */
    private static function authorization_result($data) {
        if (self::is_commerce_pro_active()) {
            return $data;
        }

        $status = isset($data['status']) ? sanitize_key((string) $data['status']) : 'inactive';
        if ($status === 'activation_limit_reached') {
            if (self::truthy(isset($data['is_staging']) ? $data['is_staging'] : 'no')) {
                $message = __('Staging activation limit reached. This license already uses all included staging-site activations. Commerce Pro was not authorized for this site and cannot be installed or enabled from Geeky Bot. Deactivate an unused staging site at geekybot.com or contact support for another staging activation.', 'geeky-bot');
            } else {
                $message = __('Production activation limit reached. This license already uses all included production-site activations. Commerce Pro was not authorized for this site and cannot be installed or enabled from Geeky Bot. Deactivate an unused production site at geekybot.com or upgrade the license.', 'geeky-bot');
            }
        } else {
            $message = isset($data['message']) ? sanitize_text_field((string) $data['message']) : '';
            if ($message === '' || $message === __('License active.', 'geeky-bot')) {
                $message = __('Commerce Pro was not authorized for this site. Review the license status and try again.', 'geeky-bot');
            }
        }

        $data['message'] = $message;
        self::save($data);

        return new WP_Error('geekybot_license_' . $status, $message);
    }

    private static function site_payload() {
        $pro = self::commerce_pro_plugin_status();
        return array(
            'product' => self::PRODUCT,
            'site_url' => self::normalized_site_url(),
            'home_url' => esc_url_raw(home_url('/')),
            'domain' => self::site_domain(),
            'site_hash' => self::current_site_fingerprint(),
            'installation_id' => self::installation_id(),
            'site_fingerprint' => self::current_site_fingerprint(),
            'binding_version' => self::BINDING_VERSION,
            'is_staging' => self::is_staging_site(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'core_version' => defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '',
            'pro_version' => isset($pro['version']) ? $pro['version'] : '',
            'locale' => get_locale(),
            'update_channel' => isset(self::update_settings()['update_channel']) ? self::update_settings()['update_channel'] : 'stable',
        );
    }

    public static function server_url() {
        $url = defined('GEEKYBOT_LICENSE_SERVER_URL') ? GEEKYBOT_LICENSE_SERVER_URL : self::DEFAULT_SERVER;
        $url = esc_url_raw((string) $url, array('https'));
        return $url ? untrailingslashit($url) : self::DEFAULT_SERVER;
    }

    public static function installation_id() {
        $id = sanitize_text_field((string) get_option(self::INSTALLATION_ID_OPTION, ''));
        if ($id === '') {
            $id = wp_generate_uuid4();
            add_option(self::INSTALLATION_ID_OPTION, $id, '', false);
            $id = sanitize_text_field((string) get_option(self::INSTALLATION_ID_OPTION, $id));
        }
        return substr($id, 0, 64);
    }

    public static function current_site_fingerprint() {
        $parts = array(
            self::normalized_site_url(),
            esc_url_raw(home_url('/')),
            self::site_domain(),
            self::installation_id(),
            wp_salt('auth'),
        );
        return hash('sha256', implode('|', $parts));
    }

    public static function runtime_binding_valid($data = null) {
        $data = is_array($data) ? $data : self::data();

        if (empty($data['license_key'])) {
            return false;
        }

        // Never self-bind a legacy license from local database values. A copied
        // database may have had its URLs search-replaced. Legacy installations
        // must receive a fresh server verification before the current site is
        // trusted and bound.
        if (empty($data['installation_id']) || empty($data['site_fingerprint']) || (string) $data['binding_version'] !== self::BINDING_VERSION) {
            return false;
        }

        if (!hash_equals((string) $data['installation_id'], self::installation_id())) {
            return false;
        }

        if (untrailingslashit((string) $data['site_url']) !== untrailingslashit(self::normalized_site_url())) {
            return false;
        }

        if (strtolower((string) $data['domain']) !== self::site_domain()) {
            return false;
        }

        if (!hash_equals((string) $data['site_fingerprint'], self::current_site_fingerprint())) {
            return false;
        }

        $expected_key_hash = hash('sha256', self::sanitize_license_key($data['license_key']) . '|' . wp_salt('auth'));
        if (empty($data['license_key_hash']) || !hash_equals((string) $data['license_key_hash'], $expected_key_hash)) {
            return false;
        }

        if (self::public_key_configured()) {
            $claims = self::stored_signed_claims($data);
            if (is_wp_error($claims) || empty($claims['_signature_verified'])) {
                return false;
            }
        }

        return true;
    }

    private static function normalized_site_url() {
        return esc_url_raw(home_url('/'));
    }

    private static function site_domain() {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        return sanitize_text_field(strtolower((string) $host));
    }

    private static function is_staging_site() {
        $host = self::site_domain();
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
            return true;
        }

        $patterns = array(
            '/(^|\.)staging\./',
            '/(^|\.)stage\./',
            '/(^|\.)dev\./',
            '/(^|\.)test\./',
            '/\.test$/',
            '/\.local$/',
            '/\.localhost$/',
            '/\.dev$/',
            '/\.wpengine\.com$/',
            '/\.kinsta\.cloud$/',
            '/\.cloudwaysapps\.com$/',
            '/\.pantheonsite\.io$/',
            '/\.flywheelsites\.com$/',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }

        return false;
    }

    private static function is_allowed_package_url($url) {
        $url = esc_url_raw((string) $url, array('https'));
        if ($url === '') {
            return false;
        }
        $host = wp_parse_url($url, PHP_URL_HOST);
        $server_host = wp_parse_url(self::server_url(), PHP_URL_HOST);
        $allowed_hosts = array($server_host, 'geekybot.com', 'www.geekybot.com');
        if (defined('GEEKYBOT_LICENSE_PACKAGE_HOSTS') && GEEKYBOT_LICENSE_PACKAGE_HOSTS) {
            $allowed_hosts = array_merge($allowed_hosts, preg_split('/[\s,]+/', (string) GEEKYBOT_LICENSE_PACKAGE_HOSTS));
        }
        $allowed_hosts = array_filter(array_map('strtolower', array_map('sanitize_text_field', (array) $allowed_hosts)));
        return $host && in_array(strtolower((string) $host), $allowed_hosts, true);
    }

    private static function entitlement_data($data) {
        $data = is_array($data) ? $data : array();
        if (!self::public_key_configured()) {
            return $data;
        }

        $claims = self::stored_signed_claims($data);
        if (is_wp_error($claims) || !is_array($claims)) {
            return array();
        }

        return !empty($claims['license']) && is_array($claims['license']) ? $claims['license'] : $claims;
    }

    private static function stored_signed_claims($data) {
        return self::verified_signed_response(array(
            'signed_payload' => isset($data['signed_payload']) ? $data['signed_payload'] : '',
            'signature' => isset($data['signed_payload_signature']) ? $data['signed_payload_signature'] : '',
        ), false);
    }

    /**
     * Upgrade a pre-binding license only after the license server verifies the
     * current site. This prevents database search/replace tools from turning a
     * copied legacy entitlement into a locally trusted activation.
     */
    public static function maybe_upgrade_legacy_binding() {
        if (wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }

        $data = self::data();
        if (empty($data['license_key'])) {
            return;
        }

        if (!empty($data['installation_id'])
            && !empty($data['site_fingerprint'])
            && (string) $data['binding_version'] === self::BINDING_VERSION) {
            return;
        }

        if (get_transient(self::LEGACY_BINDING_ATTEMPT_TRANSIENT)) {
            return;
        }

        set_transient(self::LEGACY_BINDING_ATTEMPT_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS);
        self::refresh();
    }

    private static function verified_signed_response($response, $require_fresh_response = true) {
        if (!self::public_key_configured()) {
            return array('_signature_verified' => false);
        }

        $payload = isset($response['signed_payload']) ? trim((string) $response['signed_payload']) : '';
        $signature = isset($response['signature']) ? trim((string) $response['signature']) : '';
        if ($payload === '' || $signature === '') {
            return new WP_Error('geekybot_license_signature_missing', __('The license server response could not be verified.', 'geeky-bot'));
        }

        if (!function_exists('openssl_verify')) {
            return new WP_Error('geekybot_license_signature_runtime_missing', __('This server cannot verify the Commerce Pro entitlement signature. Enable the PHP OpenSSL extension.', 'geeky-bot'));
        }

        $decoded_signature = self::base64url_decode($signature);
        if (!is_string($decoded_signature) || $decoded_signature === '') {
            return new WP_Error('geekybot_license_signature_invalid', __('The Commerce Pro entitlement signature is invalid.', 'geeky-bot'));
        }

        $verified = false;
        foreach (self::license_public_keys() as $public_key_pem) {
            $public_key = openssl_pkey_get_public($public_key_pem);
            if (!$public_key) {
                continue;
            }
            if (openssl_verify($payload, $decoded_signature, $public_key, OPENSSL_ALGO_SHA256) === 1) {
                $verified = true;
                break;
            }
        }
        if (!$verified) {
            return new WP_Error('geekybot_license_signature_invalid', __('The Commerce Pro entitlement signature is invalid.', 'geeky-bot'));
        }

        $json = self::base64url_decode($payload);
        if ($json === '') {
            $json = $payload;
        }
        $claims = json_decode($json, true);
        if (!is_array($claims)) {
            return new WP_Error('geekybot_license_claims_invalid', __('The Commerce Pro entitlement claims are invalid.', 'geeky-bot'));
        }

        $claim_product = isset($claims['product']) ? sanitize_key((string) $claims['product']) : '';
        $claim_url = isset($claims['site_url']) ? untrailingslashit(esc_url_raw((string) $claims['site_url'])) : '';
        $claim_domain = isset($claims['domain']) ? strtolower(sanitize_text_field((string) $claims['domain'])) : '';
        $claim_installation = isset($claims['installation_id']) ? sanitize_text_field((string) $claims['installation_id']) : '';
        $claim_fingerprint = isset($claims['site_fingerprint']) ? sanitize_text_field((string) $claims['site_fingerprint']) : '';
        $claim_binding_version = isset($claims['binding_version']) ? sanitize_text_field((string) $claims['binding_version']) : '';

        if ($claim_product !== self::PRODUCT
            || $claim_url !== untrailingslashit(self::normalized_site_url())
            || $claim_domain !== self::site_domain()
            || $claim_binding_version !== self::BINDING_VERSION
            || !hash_equals(self::installation_id(), $claim_installation)
            || !hash_equals(self::current_site_fingerprint(), $claim_fingerprint)) {
            return new WP_Error('geekybot_license_claims_mismatch', __('This Commerce Pro entitlement belongs to a different site.', 'geeky-bot'));
        }

        if ($require_fresh_response) {
            $issued_at = !empty($claims['issued_at']) ? absint($claims['issued_at']) : 0;
            $response_expires_at = !empty($claims['response_expires_at']) ? absint($claims['response_expires_at']) : 0;
            $claim_request_id = !empty($claims['request_id']) ? sanitize_text_field((string) $claims['request_id']) : '';
            $expected_request_id = !empty($response['_request_id']) ? sanitize_text_field((string) $response['_request_id']) : '';

            if (!$issued_at || !$response_expires_at || !$expected_request_id || !$claim_request_id) {
                return new WP_Error('geekybot_license_claims_incomplete', __('The Commerce Pro entitlement response is incomplete.', 'geeky-bot'));
            }
            if (abs(time() - $issued_at) > 10 * MINUTE_IN_SECONDS) {
                return new WP_Error('geekybot_license_claims_stale', __('The Commerce Pro entitlement response is stale. Please refresh the license.', 'geeky-bot'));
            }
            if (!hash_equals($expected_request_id, $claim_request_id)) {
                return new WP_Error('geekybot_license_request_mismatch', __('The Commerce Pro entitlement response does not match this request.', 'geeky-bot'));
            }
        }

        if (empty($claims['response_expires_at']) || absint($claims['response_expires_at']) < time()) {
            return new WP_Error('geekybot_license_claims_expired', __('The Commerce Pro entitlement response has expired. Please refresh the license.', 'geeky-bot'));
        }

        $claims['_signature_verified'] = true;
        return $claims;
    }

    private static function public_key_configured() {
        return !empty(self::license_public_keys());
    }

    /**
     * Return a bounded constant/file-based public-key set. Supporting more than
     * one public key allows a controlled signing-key rotation without a period
     * where legitimate customer sites cannot verify responses.
     *
     * @return string[]
     */
    private static function license_public_keys() {
        $keys = array();

        if (defined('GEEKYBOT_LICENSE_PUBLIC_KEY_FILE') && GEEKYBOT_LICENSE_PUBLIC_KEY_FILE) {
            $path = (string) GEEKYBOT_LICENSE_PUBLIC_KEY_FILE;
            if (is_readable($path) && filesize($path) <= 65536) {
                $keys[] = (string) file_get_contents($path);
            }
        }

        if (defined('GEEKYBOT_LICENSE_PUBLIC_KEY') && GEEKYBOT_LICENSE_PUBLIC_KEY) {
            $keys[] = (string) GEEKYBOT_LICENSE_PUBLIC_KEY;
        }

        if (defined('GEEKYBOT_LICENSE_PUBLIC_KEYS') && is_array(GEEKYBOT_LICENSE_PUBLIC_KEYS)) {
            foreach (array_slice(GEEKYBOT_LICENSE_PUBLIC_KEYS, 0, 5) as $key) {
                if (is_string($key)) {
                    $keys[] = $key;
                }
            }
        }

        $clean = array();
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key !== '' && strlen($key) <= 65536 && strpos($key, 'BEGIN PUBLIC KEY') !== false) {
                $clean[] = $key;
            }
        }

        return array_values(array_unique($clean));
    }

    private static function base64url_decode($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($value, true);
        return is_string($decoded) ? $decoded : '';
    }

    private static function sanitize_license_data($data) {
        $clean = self::defaults();
        foreach ($clean as $key => $value) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if (in_array($key, array('allowed_sites', 'allowed_staging_sites', 'activation_count'), true)) {
                $clean[$key] = absint($data[$key]);
            } elseif (in_array($key, array('expires_at', 'valid_until', 'grace_until', 'last_checked_at', 'last_successful_check_at'), true)) {
                $clean[$key] = self::sanitize_datetime((string) $data[$key]);
            } elseif ($key === 'license_key') {
                $clean[$key] = self::sanitize_license_key($data[$key]);
            } elseif ($key === 'license_key_encrypted') {
                $clean[$key] = substr(preg_replace('/[^A-Za-z0-9:+\/=_-]/', '', (string) $data[$key]), 0, 2048);
            } elseif ($key === 'signed_payload') {
                $clean[$key] = substr(trim((string) $data[$key]), 0, 16384);
            } elseif ($key === 'signed_payload_signature') {
                $clean[$key] = substr(preg_replace('/[^A-Za-z0-9+\/=_.-]/', '', (string) $data[$key]), 0, 4096);
            } elseif ($key === 'server_url' || $key === 'site_url') {
                $clean[$key] = esc_url_raw((string) $data[$key]);
            } elseif ($key === 'customer_email') {
                $clean[$key] = sanitize_email((string) $data[$key]);
            } elseif ($key === 'is_staging' || in_array($key, array('runtime_allowed', 'updates_allowed', 'downloads_allowed', 'new_activations_allowed', 'support_allowed', 'signature_verified'), true)) {
                $clean[$key] = self::truthy($data[$key]) ? 'yes' : 'no';
            } else {
                $clean[$key] = sanitize_text_field((string) $data[$key]);
            }
        }
        return $clean;
    }

    private static function sanitize_license_key($key) {
        $key = strtoupper(trim((string) $key));
        $key = preg_replace('/[\r\n\t\s]+/', '', $key);
        $key = preg_replace('/[^A-Z0-9_\-.]/', '', $key);
        return substr($key, 0, 120);
    }

    private static function mask_license_key($key) {
        $key = self::sanitize_license_key($key);
        if ($key === '') {
            return '';
        }
        $last = substr($key, -4);
        return 'GB-****-****-' . $last;
    }

    private static function truthy($value) {
        return $value === true || $value === 1 || $value === '1' || $value === 'yes' || $value === 'true';
    }

    private static function entitlement_value($license, $key, $default) {
        if (isset($license[$key])) {
            return self::truthy($license[$key]) ? 'yes' : 'no';
        }
        return $default ? 'yes' : 'no';
    }

    private static function sanitize_datetime($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : '';
    }

    private static function datetime_to_timestamp($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $timestamp = strtotime($value . ' UTC');
        return $timestamp ? absint($timestamp) : 0;
    }

    public static function status_label($status) {
        $labels = array(
            'active' => __('Active', 'geeky-bot'),
            'trial' => __('Trial', 'geeky-bot'),
            'inactive' => __('Not activated', 'geeky-bot'),
            'expired' => __('Expired', 'geeky-bot'),
            'cancelled' => __('Cancelled', 'geeky-bot'),
            'refunded' => __('Refunded', 'geeky-bot'),
            'disabled' => __('Disabled', 'geeky-bot'),
            'activation_limit_reached' => __('Activation limit reached', 'geeky-bot'),
            'domain_mismatch' => __('Domain mismatch', 'geeky-bot'),
            'grace' => __('Grace period', 'geeky-bot'),
        );
        $status = sanitize_key((string) $status);
        return isset($labels[$status]) ? $labels[$status] : ucfirst(str_replace('_', ' ', $status));
    }
}
