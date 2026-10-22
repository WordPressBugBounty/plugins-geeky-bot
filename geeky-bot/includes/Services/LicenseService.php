<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;

/**
 * Commerce Pro licence, install and update client.
 *
 * Talks to the License Manager on geekybot.com (REST namespace geekybot/v1):
 * - POST /activate      claim this site's slot, or confirm it (idempotent); the
 *                       answer carries a statement signed with Ed25519;
 * - POST /deactivate    free the slot;
 * - POST /install       a one-time download link for Commerce Pro, for a site
 *                       holding a current activation token;
 * - POST /update-check  the newest build, its SHA-256 and, when the licence is
 *                       current, a one-time download link.
 *
 * Commerce Pro runs only on a statement geekybot.com signed for this key and
 * this site, checked against includes/license-public-key.php. What this site
 * stores itself (status, dates) is shown on the License page but decides
 * nothing: it could be written by anybody, or copied in with the database.
 *
 * Network calls happen only from WP-Cron or when an administrator presses a
 * button. A shopper's page load never waits on geekybot.com.
 */
class LicenseService {
    const OPTION = 'geekybot_commerce_pro_license';
    const UPDATE_OPTION = 'geekybot_commerce_pro_update_cache';
    /** Release metadata from the old CDN file; no longer used, deleted on sight. */
    const CDN_UPDATE_OPTION = 'geekybot_commerce_pro_cdn_update_cache';
    const UPDATE_SETTINGS_OPTION = 'geekybot_commerce_pro_update_settings';
    /** The product slug on geekybot.com: Commerce Pro's plugin folder. */
    const PRODUCT = 'geeky-bot-commerce-pro';
    const PRO_BASENAME = 'geeky-bot-commerce-pro/geeky-bot-commerce-pro.php';
    const DEFAULT_SERVER = 'https://geekybot.com/wp-json/geekybot/v1';
    const PROTOCOL = 1;
    const CHECK_HOOK = 'geekybot_commerce_pro_license_check';
    const UPDATE_CHECK_HOOK = 'geekybot_commerce_pro_version_check';
    const INSTALLATION_ID_OPTION = 'geekybot_installation_id_v2';
    /** 3 = License Manager statements (Geeky Bot 2.1.2). 2 and older were the old server. */
    const BINDING_VERSION = '3';
    const LEGACY_BINDING_ATTEMPT_TRANSIENT = 'geekybot_license_binding_upgrade_attempt';
    /** How often an active licence is confirmed. A signed statement lasts 30 days. */
    const CHECK_INTERVAL = WEEK_IN_SECONDS;
    /** A renewal is charged on the expiry date; give the payment time to land. */
    const RENEWAL_TOLERANCE = 3 * DAY_IN_SECONDS;

    private static $data_cache = null;
    private static $active_cache = null;

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
            'site_url' => self::normalized_site_url(),
            'domain' => self::site_domain(),
            'installation_id' => '',
            'site_fingerprint' => '',
            'binding_version' => '',
            'is_staging' => self::is_staging_site() ? 'yes' : 'no',
            'kind' => '',
            'allowed_sites' => 0,
            'allowed_staging_sites' => 0,
            'activation_count' => 0,
            'expires_at' => '',
            'valid_until' => '',
            'last_checked_at' => '',
            'last_successful_check_at' => '',
            'next_check' => 0,
            'last_error' => '',
            'grace' => 'no',
            'updates_allowed' => 'no',
            'token' => '',
            'token_expires' => 0,
            'signed' => array(),
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
        if (!is_array($data['signed'])) {
            $data['signed'] = array();
        }

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
            $clean['license_key_encrypted'] = '';
        }
        update_option(self::OPTION, $clean, false);

        $runtime = $clean;
        $runtime['license_key'] = $plain_key;
        self::$data_cache = $runtime;
        self::$active_cache = null;
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
        $settings = wp_parse_args($saved, self::update_settings_defaults());
        // geekybot.com publishes stable and beta builds; "dev" was never served.
        if (!in_array($settings['update_channel'], array('stable', 'beta'), true)) {
            $settings['update_channel'] = $settings['update_channel'] === 'dev' ? 'beta' : 'stable';
        }
        return $settings;
    }

    public static function save_update_settings($data) {
        $data = is_array($data) ? $data : array();
        $mode = isset($data['auto_update_mode']) ? sanitize_key((string) $data['auto_update_mode']) : 'manual';
        if (!in_array($mode, array('manual', 'critical', 'patch', 'minor', 'stable'), true)) {
            $mode = 'manual';
        }

        $channel = isset($data['update_channel']) ? sanitize_key((string) $data['update_channel']) : 'stable';
        if (!in_array($channel, array('stable', 'beta'), true)) {
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
        return $clean;
    }

    public static function maybe_schedule_license_check() {
        if (!wp_next_scheduled(self::CHECK_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CHECK_HOOK);
        }
    }

    public static function maybe_schedule_update_check() {
        // Commerce Pro release checks only matter once Commerce Pro is here.
        // Free sites make no update calls to geekybot.com at all.
        if (!self::commerce_pro_files_present()) {
            wp_clear_scheduled_hook(self::UPDATE_CHECK_HOOK);
            return;
        }

        if (!wp_next_scheduled(self::UPDATE_CHECK_HOOK)) {
            wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'twicedaily', self::UPDATE_CHECK_HOOK);
        }
    }

    public static function clear_scheduled_license_check() {
        wp_clear_scheduled_hook(self::CHECK_HOOK);
        wp_clear_scheduled_hook(self::UPDATE_CHECK_HOOK);
    }

    /**
     * Daily cron: confirm the licence weekly, and daily around the renewal date
     * or when the signed statement is about to run out.
     */
    public static function scheduled_refresh() {
        $data = self::data();
        if (self::sanitize_license_key($data['license_key']) === '') {
            return;
        }

        $now = time();
        $expires = self::datetime_to_timestamp($data['expires_at']);
        $valid_until = self::datetime_to_timestamp($data['valid_until']);
        $due = absint($data['next_check']) <= $now
            || ($expires && $expires < $now + DAY_IN_SECONDS)
            || ($valid_until && $valid_until < $now + 7 * DAY_IN_SECONDS);

        if ($due) {
            self::refresh();
        }
    }

    public static function scheduled_update_refresh() {
        delete_option(self::UPDATE_OPTION);
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
        if (empty($data['license_key'])) {
            return;
        }

        if (!self::runtime_binding_valid($data)) {
            $url = admin_url('admin.php?page=geekybot-addons');
            echo '<div class="notice notice-warning"><p>'
                . esc_html__('Commerce Pro needs a fresh license verification before it can run on this site.', 'geeky-bot')
                . ' <a href="' . esc_url($url) . '">' . esc_html__('Open license screen', 'geeky-bot') . '</a></p></div>';
            return;
        }

        $status = sanitize_key((string) $data['status']);
        if ($status === 'expired' && self::is_commerce_pro_active()) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Your Commerce Pro license has expired. Buying actions continue on this activated site, but updates, protected downloads, support, and new activations require renewal.', 'geeky-bot') . '</p></div>';
            return;
        }

        if ($status === 'suspended' && self::is_commerce_pro_active()) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Your Commerce Pro renewal payment is on hold. Buying actions keep working; update the payment method in your account on geekybot.com.', 'geeky-bot') . '</p></div>';
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

        $active = self::is_commerce_pro_active();
        $current = $active && $status === 'active';

        return array(
            'active' => $active,
            'status' => $status,
            'label' => $label,
            'message' => (string) $data['message'],
            'maskedKey' => (string) $data['license_key_masked'],
            'plan' => (string) $data['plan'],
            'expiresAt' => (string) $data['expires_at'],
            'validUntil' => (string) $data['valid_until'],
            'graceUntil' => (string) $data['valid_until'],
            'lastCheckedAt' => (string) $data['last_checked_at'],
            'lastSuccessfulCheckAt' => (string) $data['last_successful_check_at'],
            'bindingValid' => self::runtime_binding_valid($data),
            'signatureVerified' => self::verified_claims($data['signed']) !== null,
            'signatureRequired' => true,
            'lastError' => (string) $data['last_error'],
            'isGrace' => self::is_running_on_grace($data),
            'siteUrl' => (string) $data['site_url'],
            'domain' => (string) $data['domain'],
            'isStaging' => (string) $data['is_staging'],
            'kind' => (string) $data['kind'],
            'allowedSites' => absint($data['allowed_sites']),
            'allowedStagingSites' => absint($data['allowed_staging_sites']),
            'activationCount' => absint($data['activation_count']),
            'runtimeAllowed' => $active,
            'updatesAllowed' => $current && self::truthy($data['updates_allowed']),
            // Installing needs a current licence; an expired one keeps running
            // what is installed but is not handed new files.
            'downloadsAllowed' => $current && self::truthy($data['updates_allowed']),
            'newActivationsAllowed' => $current,
            'supportAllowed' => $current,
            'noticeLevel' => '',
        );
    }

    /**
     * Whether Commerce Pro may run on this site. Reads stored state only.
     *
     * Active runs Pro. Suspended (a renewal payment on hold) and expired keep
     * running on a site that was already activated: geekybot.com promises the
     * installed version keeps working, and only updates, downloads, support and
     * new activations stop. Revoked (refunded), unknown, replaced, removed or
     * never activated: off.
     *
     * @return bool
     */
    public static function is_commerce_pro_active() {
        if (self::$active_cache !== null) {
            return self::$active_cache;
        }

        $data = self::data();
        $key = self::sanitize_license_key($data['license_key']);
        if ($key === '' || !self::runtime_binding_valid($data)) {
            return self::$active_cache = false;
        }

        $claims = self::verified_claims($data['signed']);
        $now = time();

        return self::$active_cache = $claims !== null
            && (int) ($claims['v'] ?? 0) === 1
            && hash_equals((string) ($claims['key_fp'] ?? ''), self::key_fingerprint($key))
            && self::normalize_url((string) ($claims['site_url'] ?? '')) === self::normalize_url(self::normalized_site_url())
            && in_array(self::PRODUCT, (array) ($claims['products'] ?? array()), true)
            && in_array((string) ($claims['status'] ?? ''), array('active', 'suspended', 'expired'), true)
            && (int) ($claims['valid_until'] ?? 0) >= $now
            && (int) ($claims['issued_at'] ?? PHP_INT_MAX) <= $now + 600;
    }

    public static function can_receive_updates() {
        $summary = self::status_summary();
        return !empty($summary['updatesAllowed']);
    }

    public static function can_download_packages() {
        $summary = self::status_summary();
        return !empty($summary['downloadsAllowed']);
    }

    /**
     * Running on the last signed statement because geekybot.com could not be
     * reached at the last check.
     */
    public static function is_running_on_grace($data = null) {
        $data = is_array($data) ? $data : self::data();
        return self::truthy($data['grace']) && self::is_commerce_pro_active();
    }

    public static function activate($license_key) {
        if (!LicenseVault::available()) {
            return new WP_Error('geekybot_license_crypto_unavailable', __('This server cannot securely store the Commerce Pro license key. Enable the PHP OpenSSL or Sodium extension, then try again.', 'geeky-bot'));
        }

        $license_key = self::sanitize_license_key($license_key);
        if ($license_key === '') {
            return new WP_Error('geekybot_license_missing', __('Please enter a license key.', 'geeky-bot'));
        }

        $previous = self::sanitize_license_key(self::data()['license_key']);
        $result = self::check_with_server($license_key, false);

        // A different key replaced the old one, so the old one's slot is freed.
        // Not when the server could not be reached: then nothing was replaced.
        $unreachable = is_wp_error($result) && $result->get_error_code() === 'geekybot_license_unreachable';
        if ($previous !== '' && $previous !== $license_key && !$unreachable) {
            self::request('deactivate', array('license_key' => $previous, 'site_url' => self::normalized_site_url()));
        }

        return $result;
    }

    /**
     * Confirm the licence with geekybot.com. /activate is idempotent: for a
     * site already on the licence it confirms the slot and returns the licence
     * as it stands, with a fresh signed statement.
     */
    public static function refresh() {
        $data = self::data();
        $license_key = self::sanitize_license_key($data['license_key']);
        if ($license_key === '') {
            return new WP_Error('geekybot_license_missing', __('No Commerce Pro license key is saved yet.', 'geeky-bot'));
        }

        return self::check_with_server($license_key, true);
    }

    public static function deactivate() {
        $data = self::data();
        $license_key = self::sanitize_license_key($data['license_key']);
        if ($license_key !== '') {
            self::request('deactivate', array('license_key' => $license_key, 'site_url' => self::normalized_site_url()));
        }

        $clean = self::defaults();
        $clean['status'] = 'inactive';
        $clean['message'] = __('License deactivated on this site.', 'geeky-bot');
        $clean['last_checked_at'] = current_time('mysql');
        self::save($clean);
        delete_option(self::UPDATE_OPTION);
        do_action('geekybot_commerce_pro_license_deactivated', $clean);
        return $clean;
    }

    /**
     * A one-time download link for Commerce Pro, with the SHA-256 it must match.
     *
     * @return array|WP_Error ['url' => string, 'sha256' => string, 'version' => string]
     */
    private static function install_package() {
        $data = self::data();
        if (self::sanitize_license_key($data['license_key']) === '' || !self::can_download_packages()) {
            return new WP_Error('geekybot_license_required', __('A valid Commerce Pro license with download access is required before installing the add-on.', 'geeky-bot'));
        }

        // The activation token proves this site holds a slot. It lasts a while;
        // when it has run out, a fresh check issues a new one.
        if ($data['token'] === '' || absint($data['token_expires']) < time() + MINUTE_IN_SECONDS) {
            $refreshed = self::refresh();
            if (is_wp_error($refreshed)) {
                return $refreshed;
            }
            $data = self::data();
        }

        $response = self::request('install', array(
            'token' => (string) $data['token'],
            'site_url' => self::normalized_site_url(),
            'product' => self::PRODUCT,
            'channel' => self::update_settings()['update_channel'],
            'core_version' => defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '',
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        if (empty($response['answered']) || empty($response['body']['ok'])) {
            return new WP_Error('geekybot_install_refused', self::refusal_message($response['body'], $response['message']));
        }

        return self::package_from($response['body']);
    }

    /**
     * Public for compatibility: a fresh, short-lived Commerce Pro download URL.
     *
     * @return string|WP_Error
     */
    public static function download_package_url() {
        $package = self::install_package();
        return is_wp_error($package) ? $package : $package['url'];
    }

    public static function install_commerce_pro() {
        if (!current_user_can('install_plugins')) {
            return new WP_Error('geekybot_no_install_capability', __('You do not have permission to install plugins.', 'geeky-bot'));
        }

        $package = self::install_package();
        if (is_wp_error($package)) {
            return $package;
        }
        $file = self::download_verified($package);
        if (is_wp_error($file)) {
            return $file;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        // A local, already verified file: the upgrader installs it as is.
        $result = $upgrader->install($file);
        if (file_exists($file)) {
            wp_delete_file($file);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        if (!$result) {
            $messages = method_exists($skin, 'get_upgrade_messages') ? implode(' ', (array) $skin->get_upgrade_messages()) : '';
            return new WP_Error('geekybot_install_failed', $messages ? wp_strip_all_tags($messages) : __('Commerce Pro could not be installed.', 'geeky-bot'));
        }

        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(true);
        }
        self::maybe_schedule_update_check();

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

        // A fresh check: download links last minutes and are never stored.
        $answer = self::update_check();
        if (is_wp_error($answer)) {
            return $answer;
        }
        $entry = $answer['entry'];
        if (empty($entry['update_available'])) {
            return new WP_Error('geekybot_no_update_available', __('Commerce Pro is already on the latest available version.', 'geeky-bot'));
        }
        if (($entry['blocked_by'] ?? '') === 'requires_core') {
            return new WP_Error('geekybot_update_requires_core', sprintf(
                /* translators: %s: Geeky Bot version the update needs. */
                __('This Commerce Pro update needs Geeky Bot %s or newer. Update Geeky Bot first.', 'geeky-bot'),
                sanitize_text_field((string) ($entry['requires_core'] ?? ''))
            ));
        }
        if (empty($entry['package'])) {
            return new WP_Error('geekybot_update_package_missing', __('The license server did not return a valid Commerce Pro update package.', 'geeky-bot'));
        }

        $file = self::download_verified(self::package_from($entry));
        if (is_wp_error($file)) {
            return $file;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';

        $was_active = is_plugin_active(self::PRO_BASENAME);
        $installed = self::commerce_pro_plugin_status()['version'];

        // Hand the upgrader the verified local file as the package.
        $transient = get_site_transient('update_plugins');
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }
        if (empty($transient->checked) || !is_array($transient->checked)) {
            $transient->checked = array();
        }
        $transient->checked[self::PRO_BASENAME] = (string) $installed;
        if (empty($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }
        $transient->response[self::PRO_BASENAME] = self::update_transient_item(array(
            'latest_version' => (string) $entry['version'],
            'package' => $file,
            'tested' => (string) ($entry['tested_wp'] ?? ''),
            'requires' => (string) ($entry['requires_wp'] ?? ''),
            'requires_php' => (string) ($entry['requires_php'] ?? ''),
        ));
        set_site_transient('update_plugins', $transient);

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->upgrade(self::PRO_BASENAME);
        if (file_exists($file)) {
            wp_delete_file($file);
        }

        delete_option(self::UPDATE_OPTION);
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

    /**
     * For WordPress' own upgrader: swap a Commerce Pro package for a fresh,
     * verified download. Kept public for integrations that hook it.
     */
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
        // A file this class already verified (update_commerce_pro()).
        if (is_string($package) && $package !== '' && file_exists($package)) {
            return $reply;
        }

        if (!self::can_receive_updates()) {
            return new WP_Error('geekybot_license_required', __('Your Commerce Pro license must include update access before updating the add-on.', 'geeky-bot'));
        }

        $answer = self::update_check();
        if (is_wp_error($answer) || empty($answer['entry']['package'])) {
            return new WP_Error('geekybot_update_package_missing', __('The license server did not return a valid Commerce Pro update package.', 'geeky-bot'));
        }

        return self::download_verified(self::package_from($answer['entry']));
    }

    public static function inject_pro_update($transient) {
        if (!is_object($transient) || empty($transient->checked) || !isset($transient->checked[self::PRO_BASENAME])) {
            return $transient;
        }
        $update = self::cached_update_info();
        if (!$update || empty($update['latest_version'])) {
            return $transient;
        }

        $installed = (string) $transient->checked[self::PRO_BASENAME];
        if ($installed === '' || version_compare($update['latest_version'], $installed, '<=')) {
            return $transient;
        }

        $item = $update;
        // The real link is fetched at download time by fresh_pro_package_for_update().
        $item['package'] = self::can_receive_updates() ? 'geekybot-commerce-pro://' . rawurlencode($update['latest_version']) : '';
        $transient->response[self::PRO_BASENAME] = self::update_transient_item($item);
        return $transient;
    }

    public static function update_summary($force = false) {
        $plugin = self::commerce_pro_plugin_status();
        $update = self::cached_update_info($force);
        $installed = isset($plugin['version']) ? (string) $plugin['version'] : '';
        $latest = is_array($update) && !empty($update['latest_version']) ? (string) $update['latest_version'] : '';
        $blocked = is_array($update) && !empty($update['blocked_by']);

        return array(
            'installed_version' => $installed,
            'latest_version' => $latest,
            'update_available' => $installed !== '' && $latest !== '' && version_compare($latest, $installed, '>'),
            'blocked_by' => $blocked ? (string) $update['blocked_by'] : '',
            'requires_core' => is_array($update) ? (string) ($update['requires_core'] ?? '') : '',
            'metadata' => is_array($update) ? $update : array(),
            'update' => is_array($update) ? $update : array(),
            'settings' => self::update_settings(),
            'cdn_url' => self::cdn_metadata_url(),
            'can_update' => self::can_receive_updates() && !$blocked,
        );
    }

    public static function refresh_update_status() {
        delete_option(self::UPDATE_OPTION);
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
            <div class="geekybot-admin-update-notice__message">
                <span aria-hidden="true">↑</span>
                <strong><?php echo esc_html($message); ?></strong>
            </div>
            <div class="geekybot-admin-update-notice__actions">
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
            </div>
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
        if (empty($summary['update_available']) || empty($summary['can_update'])) {
            return false;
        }

        $metadata = isset($summary['metadata']) && is_array($summary['metadata']) ? $summary['metadata'] : array();
        $critical = !empty($metadata['critical']) && $metadata['critical'] === 'yes'
            || !empty($metadata['security']) && $metadata['security'] === 'yes';
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

    /**
     * Whether the Commerce Pro plugin files are installed, active or not.
     *
     * Update checks for Commerce Pro are gated on this, so a free site makes
     * no calls to geekybot.com for an add-on it does not have.
     *
     * @return bool
     */
    private static function commerce_pro_files_present() {
        return file_exists(WP_PLUGIN_DIR . '/' . self::PRO_BASENAME);
    }

    /**
     * The last update check, refreshed at most every 6 hours (or when forced).
     * Download links are never cached: they last minutes.
     *
     * @return array
     */
    private static function cached_update_info($force = false) {
        if (!self::commerce_pro_files_present()) {
            return array();
        }
        // Left behind by Geeky Bot 2.1.1 and older.
        if (get_option(self::CDN_UPDATE_OPTION) !== false) {
            delete_option(self::CDN_UPDATE_OPTION);
        }

        $cache = get_option(self::UPDATE_OPTION, array());
        if (!$force && is_array($cache) && !empty($cache['checked_at']) && (time() - absint($cache['checked_at'])) < 6 * HOUR_IN_SECONDS) {
            return isset($cache['update']) && is_array($cache['update']) ? $cache['update'] : array();
        }

        if (self::sanitize_license_key(self::data()['license_key']) === '') {
            update_option(self::UPDATE_OPTION, array('checked_at' => time(), 'update' => array()), false);
            return array();
        }

        $answer = self::update_check();
        if (is_wp_error($answer)) {
            // Keep what was known; try again in about an hour.
            $kept = is_array($cache) && isset($cache['update']) && is_array($cache['update']) ? $cache['update'] : array();
            update_option(self::UPDATE_OPTION, array('checked_at' => time() - 5 * HOUR_IN_SECONDS, 'update' => $kept, 'error' => $answer->get_error_message()), false);
            return $kept;
        }

        $entry = $answer['entry'];
        $changelog = (string) ($entry['changelog'] ?? '');
        $clean = array(
            'latest_version' => sanitize_text_field((string) ($entry['version'] ?? '')),
            'channel' => sanitize_key((string) ($entry['channel'] ?? 'stable')),
            'tested' => sanitize_text_field((string) ($entry['tested_wp'] ?? '')),
            'requires' => sanitize_text_field((string) ($entry['requires_wp'] ?? '')),
            'requires_php' => sanitize_text_field((string) ($entry['requires_php'] ?? '')),
            'requires_core' => sanitize_text_field((string) ($entry['requires_core'] ?? '')),
            'blocked_by' => sanitize_key((string) ($entry['blocked_by'] ?? '')),
            'checksum' => strtolower((string) preg_replace('/[^a-f0-9]/i', '', (string) ($entry['sha256'] ?? ''))),
            'released_at' => self::sanitize_datetime((string) ($entry['released_at'] ?? '')),
            'critical' => 'no',
            'security' => !empty($entry['security']) ? 'yes' : 'no',
            'changelog' => $changelog !== '' ? wp_kses_post(wpautop($changelog)) : '',
            'may_download' => !empty($answer['may_download']) ? 'yes' : 'no',
        );
        $manifest = isset($answer['manifest']) ? esc_url_raw((string) $answer['manifest']) : '';

        update_option(self::UPDATE_OPTION, array('checked_at' => time(), 'update' => $clean, 'manifest' => $manifest), false);
        return $clean;
    }

    /**
     * POST /update-check for Commerce Pro.
     *
     * @return array|WP_Error ['entry' => array, 'may_download' => bool, 'manifest' => string]
     */
    private static function update_check() {
        $key = self::sanitize_license_key(self::data()['license_key']);
        $plugin = self::commerce_pro_plugin_status();
        if ($key === '') {
            return new WP_Error('geekybot_license_missing', __('No Commerce Pro license key is saved yet.', 'geeky-bot'));
        }

        $response = self::request('update-check', array(
            'license_key' => $key,
            'site_url' => self::normalized_site_url(),
            'channel' => self::update_settings()['update_channel'],
            'products' => array(self::PRODUCT => $plugin['version'] !== '' ? $plugin['version'] : '0.0.0'),
            'core_version' => defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '',
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        if (empty($response['answered']) || empty($response['body']['ok'])) {
            return new WP_Error('geekybot_update_check_refused', self::refusal_message($response['body'], $response['message']));
        }

        $body = $response['body'];
        $entry = isset($body['updates'][self::PRODUCT]) && is_array($body['updates'][self::PRODUCT]) ? $body['updates'][self::PRODUCT] : array();
        return array(
            'entry' => $entry,
            'may_download' => !empty($body['license']['updates']),
            'manifest' => isset($body['manifest']) ? (string) $body['manifest'] : '',
        );
    }

    /**
     * The public version list geekybot.com last pointed this site to, if any.
     */
    public static function cdn_metadata_url() {
        $cache = get_option(self::UPDATE_OPTION, array());
        return is_array($cache) && !empty($cache['manifest']) ? esc_url_raw((string) $cache['manifest']) : '';
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

    /**
     * POST /activate and record what geekybot.com answered.
     *
     * @param string $license_key Licence key.
     * @param bool   $allow_grace Keep running on the last signed statement if the server cannot be reached.
     * @return array|WP_Error Saved licence data, or why this site is not licensed.
     */
    private static function check_with_server($license_key, $allow_grace) {
        $payload = array(
            'license_key' => $license_key,
            'site_url' => self::normalized_site_url(),
            'products' => array(self::PRODUCT),
            'environment' => array(
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'plugin_version' => defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '',
                'locale' => get_locale(),
                'multisite' => is_multisite() ? '1' : '0',
            ),
        );
        // A recognised staging host says so, so it uses a staging slot and not
        // a production one. Local and development hosts are detected by the server.
        if (self::is_staging_site() && !self::is_local_site()) {
            $payload['kind'] = 'staging';
        }

        $response = self::request('activate', $payload);
        $data = self::data();
        $now_mysql = current_time('mysql');

        if (is_wp_error($response) || empty($response['answered'])) {
            // Network error, rate limit or server error: not the server saying no.
            $message = is_wp_error($response) ? $response->get_error_message() : $response['message'];
            $data['last_checked_at'] = $now_mysql;
            $data['last_error'] = $message;
            $data['next_check'] = time() + 6 * HOUR_IN_SECONDS;
            if ($allow_grace && self::sanitize_license_key($data['license_key']) === $license_key && self::is_commerce_pro_active()) {
                $data['grace'] = 'yes';
                self::save($data);
                return new WP_Error('geekybot_license_unreachable', $message);
            }
            if (self::sanitize_license_key($data['license_key']) !== $license_key) {
                // A new key that could not be checked is not saved over a working one.
                return new WP_Error('geekybot_license_unreachable', $message);
            }
            self::save($data);
            return new WP_Error('geekybot_license_unreachable', $message);
        }

        $body = $response['body'];
        $data['license_key'] = $license_key;
        $data['license_key_masked'] = self::mask_license_key($license_key);
        $data['license_key_hash'] = hash('sha256', $license_key . '|' . wp_salt('auth'));
        $data['site_url'] = self::normalized_site_url();
        $data['domain'] = self::site_domain();
        $data['installation_id'] = self::installation_id();
        $data['site_fingerprint'] = self::current_site_fingerprint();
        $data['binding_version'] = self::BINDING_VERSION;
        $data['is_staging'] = self::is_staging_site() ? 'yes' : 'no';
        $data['last_checked_at'] = $now_mysql;
        $data['last_error'] = '';
        $data['grace'] = 'no';
        $data['server_url'] = self::server_url();

        if (empty($body['ok'])) {
            // The server answered and said no: unknown or replaced key, revoked,
            // expired or on hold for a new site, or every slot in use.
            $reason = sanitize_key((string) ($body['reason'] ?? ''));
            $map = array(
                'no_production_slots' => 'activation_limit_reached',
                'no_staging_slots' => 'activation_limit_reached',
                'no_development_slots' => 'activation_limit_reached',
                'license_expired' => 'expired',
                'license_suspended' => 'suspended',
                'license_revoked' => 'revoked',
                'license_not_found' => 'invalid',
                'key_replaced' => 'replaced',
            );
            $data['status'] = isset($map[$reason]) ? $map[$reason] : 'disabled';
            if ($reason === 'no_staging_slots') {
                $data['is_staging'] = 'yes';
            }
            $data['message'] = self::refusal_message($body, '');
            $data['signed'] = array();
            $data['token'] = '';
            $data['token_expires'] = 0;
            $data['valid_until'] = '';
            $data['updates_allowed'] = 'no';
            $data['next_check'] = time() + DAY_IN_SECONDS;
            $saved = self::save($data);
            delete_option(self::UPDATE_OPTION);
            do_action('geekybot_commerce_pro_license_deactivated', $saved);
            return self::authorization_result($saved);
        }

        $license = isset($body['license']) && is_array($body['license']) ? $body['license'] : array();
        $status = sanitize_key((string) ($license['status'] ?? ''));
        $products = array_map('sanitize_key', (array) ($body['products'] ?? array()));

        $data['status'] = in_array($status, array('active', 'suspended', 'expired'), true) ? $status : 'disabled';
        $data['plan'] = self::plan_label((string) ($license['plan'] ?? ''));
        $data['expires_at'] = self::sanitize_datetime((string) ($license['expires_at'] ?? ''));
        $data['updates_allowed'] = !empty($license['updates']) ? 'yes' : 'no';
        $data['kind'] = sanitize_key((string) ($body['activation']['kind'] ?? ''));
        if (!empty($body['slots']['production']) && is_array($body['slots']['production'])) {
            $data['allowed_sites'] = absint($body['slots']['production']['allowed'] ?? 0);
            $data['activation_count'] = absint($body['slots']['production']['used'] ?? 0);
        }
        if (!empty($body['slots']['staging']) && is_array($body['slots']['staging'])) {
            $data['allowed_staging_sites'] = absint($body['slots']['staging']['allowed'] ?? 0);
        }
        if (!empty($body['token'])) {
            $data['token'] = substr(preg_replace('/[^A-Za-z0-9._~+\/=-]/', '', (string) $body['token']), 0, 4096);
            $data['token_expires'] = time() + absint($body['token_ttl'] ?? 0);
        }
        $data['signed'] = !empty($body['signed']) && is_array($body['signed'])
            ? array_intersect_key($body['signed'], array_flip(array('alg', 'kid', 'payload', 'signature')))
            : array();
        $claims = self::verified_claims($data['signed']);
        $data['valid_until'] = $claims !== null && !empty($claims['valid_until']) ? gmdate('Y-m-d H:i:s', (int) $claims['valid_until']) : '';
        $data['last_successful_check_at'] = $now_mysql;
        $data['next_check'] = time() + ($data['status'] === 'active' ? self::CHECK_INTERVAL : DAY_IN_SECONDS);

        if (!in_array(self::PRODUCT, $products, true)) {
            $data['status'] = 'disabled';
            $data['message'] = __('This license does not include Commerce Pro.', 'geeky-bot');
        } elseif ($claims === null) {
            $data['message'] = __('The license server response could not be verified.', 'geeky-bot');
        } elseif ($data['status'] === 'expired') {
            $data['message'] = __('Your Commerce Pro license has expired. Buying actions continue on this activated site, but updates, protected downloads, support, and new activations require renewal.', 'geeky-bot');
        } elseif ($data['status'] === 'suspended') {
            $data['message'] = __('Your Commerce Pro renewal payment is on hold. Buying actions keep working; update the payment method in your account on geekybot.com.', 'geeky-bot');
        } else {
            $data['message'] = __('Commerce Pro license activated for this site.', 'geeky-bot');
        }

        delete_option(self::UPDATE_OPTION);
        $saved = self::save($data);
        if (self::is_commerce_pro_active()) {
            do_action('geekybot_commerce_pro_license_verified', $saved);
        } else {
            do_action('geekybot_commerce_pro_license_deactivated', $saved);
        }
        return self::authorization_result($saved);
    }

    /**
     * Turn a saved state that does not license this site into an admin-facing error.
     *
     * @param array $data Saved license data.
     * @return array|WP_Error
     */
    private static function authorization_result($data) {
        if (self::is_commerce_pro_active()) {
            return $data;
        }

        $status = isset($data['status']) ? sanitize_key((string) $data['status']) : 'inactive';
        $message = isset($data['message']) ? sanitize_text_field((string) $data['message']) : '';
        if ($message === '') {
            $message = __('Commerce Pro was not authorized for this site. Review the license status and try again.', 'geeky-bot');
        }

        return new WP_Error('geekybot_license_' . $status, $message);
    }

    /**
     * Download a package and check it against its published SHA-256.
     *
     * @param array|WP_Error $package ['url', 'sha256', 'version'].
     * @return string|WP_Error Local file path.
     */
    private static function download_verified($package) {
        if (is_wp_error($package)) {
            return $package;
        }
        $url = (string) ($package['url'] ?? '');
        $expected = (string) ($package['sha256'] ?? '');
        if ($url === '' || !self::is_allowed_package_url($url) || strlen($expected) !== 64) {
            return new WP_Error('geekybot_bad_package_url', __('The license server did not return a safe Commerce Pro package URL.', 'geeky-bot'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = wp_tempnam('geeky-bot-commerce-pro.zip');
        if (!$file) {
            return new WP_Error('geekybot_update_zip_missing', __('The Commerce Pro update package could not be downloaded.', 'geeky-bot'));
        }
        $response = wp_remote_get($url, array(
            'timeout' => 300,
            'redirection' => 0,
            'stream' => true,
            'filename' => $file,
            // A development copy of geekybot.com on this machine is allowed.
            'reject_unsafe_urls' => !self::is_local_host((string) wp_parse_url($url, PHP_URL_HOST)),
        ));
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            wp_delete_file($file);
            return new WP_Error('geekybot_update_zip_missing', __('The Commerce Pro update package could not be downloaded.', 'geeky-bot'));
        }

        $valid = self::validate_downloaded_zip($file, $expected);
        if (is_wp_error($valid)) {
            wp_delete_file($file);
            return $valid;
        }
        return $file;
    }

    /**
     * @param array $source An /install answer or an /update-check entry.
     * @return array|WP_Error
     */
    private static function package_from($source) {
        $url = isset($source['package']) ? (string) $source['package'] : '';
        $sha = strtolower((string) preg_replace('/[^a-f0-9]/i', '', (string) ($source['sha256'] ?? '')));
        if ($url === '' || strlen($sha) !== 64) {
            return new WP_Error('geekybot_bad_package_url', __('The license server did not return a safe Commerce Pro package URL.', 'geeky-bot'));
        }
        return array(
            'url' => $url,
            'sha256' => $sha,
            'version' => sanitize_text_field((string) ($source['version'] ?? '')),
        );
    }

    private static function validate_downloaded_zip($path, $checksum) {
        if (!is_string($path) || $path === '' || !file_exists($path) || !is_readable($path)) {
            return new WP_Error('geekybot_update_zip_missing', __('The Commerce Pro update package could not be downloaded.', 'geeky-bot'));
        }

        $size = filesize($path);
        if (!$size || $size < 22) {
            return new WP_Error('geekybot_update_zip_empty', __('The Commerce Pro update package is empty or incomplete.', 'geeky-bot'));
        }

        if (!hash_equals(strtolower((string) $checksum), strtolower((string) hash_file('sha256', $path)))) {
            return new WP_Error('geekybot_update_checksum_failed', __('The downloaded Commerce Pro package did not match the release checksum.', 'geeky-bot'));
        }

        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $opened = $zip->open($path, \ZipArchive::CHECKCONS);
            if ($opened !== true) {
                return new WP_Error('geekybot_update_zip_corrupt', __('The downloaded Commerce Pro package is not a valid ZIP archive.', 'geeky-bot'));
            }
            $zip->close();
        }

        return true;
    }

    /**
     * POST JSON to the licence server.
     *
     * 'answered' is true when the server gave a decision (2xx, or a 4xx refusal
     * with a reason); false for rate limits and server errors, which must never
     * lock a store. Network failures come back as WP_Error.
     *
     * @return array|WP_Error ['answered' => bool, 'code' => int, 'message' => string, 'body' => array]
     */
    private static function request($endpoint, $body, $timeout = 15) {
        $allowed = array('activate', 'deactivate', 'install', 'update-check');
        if (!in_array($endpoint, $allowed, true)) {
            return new WP_Error('geekybot_license_endpoint_invalid', __('The requested license action is not allowed.', 'geeky-bot'));
        }

        $payload = (array) $body;
        $payload['protocol'] = self::PROTOCOL;
        $local_server = self::is_local_host((string) wp_parse_url(self::server_url(), PHP_URL_HOST));

        $response = wp_remote_post(self::server_url() . '/' . $endpoint, array(
            'timeout' => max(5, min(30, absint($timeout))),
            'redirection' => 0,
            'sslverify' => true,
            // A development copy of geekybot.com on this machine is allowed.
            'reject_unsafe_urls' => !$local_server,
            'limit_response_size' => 1024 * 1024,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'GeekyBot/' . (defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : 'unknown'),
            ),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('geekybot_license_unreachable', __('Could not connect to the license server.', 'geeky-bot'));
        }

        $code = absint(wp_remote_retrieve_response_code($response));
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $data = is_array($decoded) ? $decoded : array();
        $refusal = $code >= 400 && $code < 500 && $code !== 429 && !empty($data['reason']);

        if (($code >= 200 && $code < 300) || $refusal) {
            return array(
                'answered' => true,
                'code' => $code,
                'message' => sanitize_text_field((string) ($data['message'] ?? '')),
                'body' => $data,
            );
        }

        return array(
            'answered' => false,
            'code' => $code,
            'message' => $code === 429
                ? __('The license server is busy. Please try again in a few minutes.', 'geeky-bot')
                : __('Could not connect to the license server.', 'geeky-bot'),
            'body' => $data,
        );
    }

    private static function refusal_message($body, $fallback) {
        $reason = is_array($body) ? (string) ($body['reason'] ?? '') : '';
        $messages = array(
            'license_not_found' => __('That license key was not recognised. Check it against your purchase email.', 'geeky-bot'),
            'key_replaced' => __('This license key has been replaced. The new key is in your account on geekybot.com.', 'geeky-bot'),
            'license_revoked' => __('This license has been withdrawn.', 'geeky-bot'),
            'license_expired' => __('This license has expired. Renew it on geekybot.com to activate a site.', 'geeky-bot'),
            'license_suspended' => __('This license is on hold, usually a failed renewal payment. Update the payment method in your account on geekybot.com.', 'geeky-bot'),
            'no_production_slots' => __('Production activation limit reached. This license already uses all included production-site activations. Commerce Pro was not authorized for this site and cannot be installed or enabled from Geeky Bot. Deactivate an unused production site at geekybot.com or upgrade the license.', 'geeky-bot'),
            'no_staging_slots' => __('Staging activation limit reached. This license already uses all included staging-site activations. Commerce Pro was not authorized for this site and cannot be installed or enabled from Geeky Bot. Deactivate an unused staging site at geekybot.com or contact support for another staging activation.', 'geeky-bot'),
            'no_development_slots' => __('Every development site on this license is in use.', 'geeky-bot'),
            'invalid_site' => __('The license server could not read this site’s address.', 'geeky-bot'),
            'invalid_token' => __('This site needs a fresh license check. Click “Check my license again”, then try again.', 'geeky-bot'),
            'not_entitled' => __('Your license does not include Commerce Pro downloads.', 'geeky-bot'),
            'not_published' => __('Commerce Pro is not available for download right now. Please try again later.', 'geeky-bot'),
            'requires_core' => __('This Commerce Pro version needs a newer Geeky Bot. Update Geeky Bot first.', 'geeky-bot'),
            'rate_limited' => __('The license server is busy. Please try again in a few minutes.', 'geeky-bot'),
        );
        if (isset($messages[$reason])) {
            return $messages[$reason];
        }
        $message = is_array($body) ? sanitize_text_field((string) ($body['message'] ?? '')) : '';
        if ($message !== '') {
            return $message;
        }
        return $fallback !== '' ? $fallback : __('Commerce Pro was not authorized for this site. Review the license status and try again.', 'geeky-bot');
    }

    /**
     * The plan as the shop names it.
     */
    private static function plan_label($slug) {
        $slug = sanitize_key((string) $slug);
        $labels = array(
            'single' => __('Commerce Pro - Single Site', 'geeky-bot'),
            'five' => __('Commerce Pro - 5 Sites', 'geeky-bot'),
            'twentyfive' => __('Commerce Pro - 25 Sites', 'geeky-bot'),
        );
        return isset($labels[$slug]) ? $labels[$slug] : ($slug !== '' ? ucwords(str_replace(array('-', '_'), ' ', $slug)) : '');
    }

    public static function server_url() {
        $url = defined('GEEKYBOT_LICENSE_SERVER_URL') ? (string) GEEKYBOT_LICENSE_SERVER_URL : self::DEFAULT_SERVER;
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        // HTTPS always, except a development copy on this machine.
        $url = esc_url_raw($url, self::is_local_host($host) ? array('http', 'https') : array('https'));
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

    /**
     * Whether the stored licence was checked on this very installation.
     *
     * A database copied to another domain, or with its URLs search-replaced,
     * fails here and needs a fresh check from geekybot.com.
     */
    public static function runtime_binding_valid($data = null) {
        $data = is_array($data) ? $data : self::data();

        if (empty($data['license_key'])) {
            return false;
        }

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
        return !empty($data['license_key_hash']) && hash_equals((string) $data['license_key_hash'], $expected_key_hash);
    }

    /**
     * Geeky Bot 2.1.1 and older stored a licence from the old licence server.
     * The key is kept and checked once against geekybot.com's License Manager.
     */
    public static function maybe_upgrade_legacy_binding() {
        if (wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }

        $data = self::data();
        if (empty($data['license_key']) || (string) $data['binding_version'] === self::BINDING_VERSION) {
            return;
        }

        if (get_transient(self::LEGACY_BINDING_ATTEMPT_TRANSIENT)) {
            return;
        }

        set_transient(self::LEGACY_BINDING_ATTEMPT_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS);
        self::refresh();
    }

    /**
     * The claims of a statement geekybot.com signed, or null.
     *
     * @param mixed $signed {alg, kid, payload (base64 JSON), signature (base64)}.
     * @return array|null
     */
    private static function verified_claims($signed) {
        if (!is_array($signed) || empty($signed['payload']) || empty($signed['signature']) || ($signed['alg'] ?? '') !== 'Ed25519') {
            return null;
        }
        $public = base64_decode((string) self::public_key(), true);
        $payload = base64_decode((string) $signed['payload'], true);
        $signature = base64_decode((string) $signed['signature'], true);
        if (!$public || !$payload || !$signature || !function_exists('sodium_crypto_sign_verify_detached')) {
            return null;
        }
        try {
            if (!sodium_crypto_sign_verify_detached($signature, $payload, $public)) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        $claims = json_decode($payload, true);
        return is_array($claims) ? $claims : null;
    }

    /**
     * geekybot.com's public signing key (base64), from includes/license-public-key.php.
     */
    private static function public_key() {
        static $key = null;
        if ($key === null) {
            $file = GEEKYBOT_PATH . 'includes/license-public-key.php';
            $data = is_readable($file) ? include $file : array();
            $key = is_array($data) ? (string) ($data['public_key'] ?? '') : '';
        }
        return $key;
    }

    private static function normalized_site_url() {
        return esc_url_raw(home_url('/'));
    }

    /**
     * Host without "www." plus path, lower case: http/https and www never matter.
     */
    private static function normalize_url($url) {
        $parts = wp_parse_url((string) $url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);
        $path = isset($parts['path']) ? untrailingslashit(strtolower((string) $parts['path'])) : '';
        return $host . $path;
    }

    /**
     * The licence key's fingerprint, as geekybot.com signs it.
     */
    private static function key_fingerprint($key) {
        return hash('sha256', strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $key)));
    }

    private static function site_domain() {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        return sanitize_text_field(strtolower((string) $host));
    }

    private static function is_local_host($host) {
        $host = strtolower((string) $host);
        return in_array($host, array('localhost', '127.0.0.1', '::1', '[::1]'), true)
            || (bool) preg_match('/\.(test|local|localhost)$/', $host);
    }

    private static function is_local_site() {
        return self::is_local_host(self::site_domain());
    }

    private static function is_staging_site() {
        $host = self::site_domain();
        if ($host === '' || self::is_local_host($host)) {
            return true;
        }

        $patterns = array(
            '/(^|\.)staging\./',
            '/(^|\.)stage\./',
            '/(^|\.)dev\./',
            '/(^|\.)test\./',
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

    /**
     * Downloads come only from the licence server itself.
     */
    private static function is_allowed_package_url($url) {
        $parts = wp_parse_url((string) $url);
        $server = wp_parse_url(self::server_url());
        if (!is_array($parts) || !is_array($server) || empty($parts['host'])) {
            return false;
        }
        return strtolower((string) $parts['host']) === strtolower((string) ($server['host'] ?? ''))
            && ($parts['scheme'] ?? '') === ($server['scheme'] ?? '');
    }

    private static function sanitize_license_data($data) {
        $clean = self::defaults();
        foreach ($clean as $key => $value) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if (in_array($key, array('allowed_sites', 'allowed_staging_sites', 'activation_count', 'next_check', 'token_expires'), true)) {
                $clean[$key] = absint($data[$key]);
            } elseif (in_array($key, array('expires_at', 'valid_until', 'last_checked_at', 'last_successful_check_at'), true)) {
                $clean[$key] = self::sanitize_datetime((string) $data[$key]);
            } elseif ($key === 'license_key') {
                $clean[$key] = self::sanitize_license_key($data[$key]);
            } elseif ($key === 'license_key_encrypted') {
                $clean[$key] = substr(preg_replace('/[^A-Za-z0-9:+\/=_-]/', '', (string) $data[$key]), 0, 2048);
            } elseif ($key === 'token') {
                $clean[$key] = substr(preg_replace('/[^A-Za-z0-9._~+\/=-]/', '', (string) $data[$key]), 0, 4096);
            } elseif ($key === 'signed') {
                $signed = is_array($data[$key]) ? $data[$key] : array();
                $clean[$key] = array();
                foreach (array('alg', 'kid', 'payload', 'signature') as $part) {
                    if (isset($signed[$part]) && is_string($signed[$part])) {
                        $clean[$key][$part] = substr(preg_replace('/[^A-Za-z0-9+\/=_-]/', '', $signed[$part]), 0, 16384);
                    }
                }
            } elseif ($key === 'server_url' || $key === 'site_url') {
                $clean[$key] = esc_url_raw((string) $data[$key]);
            } elseif (in_array($key, array('is_staging', 'grace', 'updates_allowed'), true)) {
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
        return substr($key, 0, 5) . '-•••••-•••••-•••••-' . substr($key, -5);
    }

    private static function truthy($value) {
        return $value === true || $value === 1 || $value === '1' || $value === 'yes' || $value === 'true';
    }

    private static function sanitize_datetime($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value . (preg_match('/[zZ]|[+-]\d\d:?\d\d$|UTC$/', $value) ? '' : ' UTC'));
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
            'inactive' => __('Not activated', 'geeky-bot'),
            'expired' => __('Expired', 'geeky-bot'),
            'suspended' => __('Payment on hold', 'geeky-bot'),
            'revoked' => __('Refunded', 'geeky-bot'),
            'invalid' => __('Key not recognised', 'geeky-bot'),
            'replaced' => __('Key replaced', 'geeky-bot'),
            'disabled' => __('Disabled', 'geeky-bot'),
            'activation_limit_reached' => __('Activation limit reached', 'geeky-bot'),
            'grace' => __('Grace period', 'geeky-bot'),
        );
        $status = sanitize_key((string) $status);
        return isset($labels[$status]) ? $labels[$status] : ucfirst(str_replace('_', ' ', $status));
    }
}
