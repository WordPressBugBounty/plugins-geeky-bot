<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps the first-run experience resumable without storing duplicate setup
 * data. Readiness is calculated from the live store; this service stores only
 * whether the merchant has reviewed or dismissed the guided launch.
 */
class OnboardingService {
    const STATE_OPTION = 'geekybot_onboarding_state';
    const REDIRECT_OPTION = 'geekybot_onboarding_redirect_pending';

    public function hooks() {
        add_action('admin_init', array($this, 'maybe_redirect_to_setup'), 5);
    }

    public static function schedule_first_run() {
        update_option(self::REDIRECT_OPTION, 'yes', false);
        update_option(self::STATE_OPTION, array(
            'status' => 'started',
            'updated_at' => current_time('mysql'),
        ), false);
    }

    public static function state() {
        $state = get_option(self::STATE_OPTION, array());
        if (!is_array($state)) {
            $state = array();
        }

        return wp_parse_args($state, array(
            'status' => 'not_started',
            'updated_at' => '',
        ));
    }

    public static function set_status($status) {
        $allowed = array('started', 'reviewed', 'dismissed');
        $status = sanitize_key((string) $status);
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        update_option(self::STATE_OPTION, array(
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ), false);

        return true;
    }

    public static function reset() {
        update_option(self::STATE_OPTION, array(
            'status' => 'started',
            'updated_at' => current_time('mysql'),
        ), false);
    }

    public function maybe_redirect_to_setup() {
        if (get_option(self::REDIRECT_OPTION, '') !== 'yes') {
            return;
        }

        if (!current_user_can('manage_options') || wp_doing_ajax() || is_network_admin()) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only core activation marker; no request data is persisted.
        if (!empty($_GET['activate-multi'])) {
            delete_option(self::REDIRECT_OPTION);
            return;
        }

        delete_option(self::REDIRECT_OPTION);
        wp_safe_redirect(admin_url('admin.php?page=geekybot-setup&gb_first_run=1'));
        exit;
    }

    /**
     * Return safe, local environment checks. No outbound HTTP requests are made.
     *
     * @return array
     */
    public static function system_checks() {
        global $wp_version;

        $woocommerce = class_exists('WooCommerce') && function_exists('wc_get_product');
        $rest = function_exists('rest_url') && function_exists('rest_get_server');
        $php = version_compare(PHP_VERSION, '7.4', '>=');
        $wordpress = version_compare((string) $wp_version, '6.0', '>=');
        $permalinks = (string) get_option('permalink_structure', '');

        return array(
            'woocommerce' => array(
                'ready' => $woocommerce,
                'label' => __('WooCommerce runtime', 'geeky-bot'),
                'detail' => $woocommerce ? __('Products, prices and stock are available.', 'geeky-bot') : __('Activate WooCommerce before launching product assistance.', 'geeky-bot'),
            ),
            'rest' => array(
                'ready' => $rest,
                'label' => __('WordPress REST API', 'geeky-bot'),
                'detail' => $rest ? __('The storefront assistant can use the local REST API.', 'geeky-bot') : __('The WordPress REST API is unavailable.', 'geeky-bot'),
            ),
            'php' => array(
                'ready' => $php,
                'label' => __('PHP compatibility', 'geeky-bot'),
                'detail' => sprintf(
                    /* translators: %s: installed PHP version. */
                    __('Running PHP %s.', 'geeky-bot'),
                    PHP_VERSION
                ),
            ),
            'wordpress' => array(
                'ready' => $wordpress,
                'label' => __('WordPress compatibility', 'geeky-bot'),
                'detail' => sprintf(
                    /* translators: %s: installed WordPress version. */
                    __('Running WordPress %s.', 'geeky-bot'),
                    (string) $wp_version
                ),
            ),
            'permalinks' => array(
                'ready' => true,
                'warning' => $permalinks === '',
                'label' => __('Permalink mode', 'geeky-bot'),
                'detail' => $permalinks === '' ? __('Plain permalinks work, but a readable permalink structure is recommended.', 'geeky-bot') : __('Readable permalinks are enabled.', 'geeky-bot'),
            ),
        );
    }

    public static function system_ready() {
        foreach (self::system_checks() as $check) {
            if (empty($check['ready'])) {
                return false;
            }
        }

        return true;
    }
}
