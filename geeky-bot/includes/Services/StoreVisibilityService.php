<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether WooCommerce is hiding the store from the current visitor.
 *
 * WooCommerce "Coming soon" mode shows guests a holding page, but the chat
 * widget still loaded on it and answered with product names and prices -- a
 * catalog the merchant had not launched yet. This mirrors WooCommerce's own
 * rule (ComingSoonRequestHandler::should_show_coming_soon) so the assistant is
 * hidden from exactly the visitors the store is hidden from.
 */
class StoreVisibilityService {
    /**
     * @return bool True when this visitor must not see the store.
     */
    public static function store_hidden_from_visitor() {
        // Covers both "entire site" and "store pages only": either way the
        // catalog is not public yet, and the assistant is a catalog.
        if (get_option('woocommerce_coming_soon') !== 'yes') {
            return false;
        }

        // Administrators and shop managers preview the store, as in WooCommerce.
        if (current_user_can('manage_woocommerce')) {
            return false;
        }

        // Visitors WooCommerce lets through: the private preview link, which
        // WooCommerce keeps in a cookie, and its site-specific exclusion filter.
        // The link itself may be in this request only, before the cookie is set.
        if (get_option('woocommerce_private_link') === 'yes') {
            $share_key = (string) get_option('woocommerce_share_key', '');
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only comparison with WooCommerce's own share key.
            $from_link = isset($_GET['woo-share']) ? sanitize_text_field(wp_unslash($_GET['woo-share'])) : '';
            $from_cookie = isset($_COOKIE['woo-share']) ? sanitize_text_field(wp_unslash($_COOKIE['woo-share'])) : '';
            if ($share_key !== '' && ($from_link === $share_key || $from_cookie === $share_key)) {
                return false;
            }
        }

        /** This filter is documented in WooCommerce's ComingSoonRequestHandler. */
        if (apply_filters('woocommerce_coming_soon_exclude', false)) {
            return false;
        }

        /**
         * Whether Geeky Bot treats the store as hidden from this visitor.
         *
         * @param bool $hidden True while WooCommerce Coming soon mode hides the store.
         */
        return (bool) apply_filters('geekybot_store_hidden_from_visitor', true);
    }

    /**
     * REST error for visitors the store is hidden from, or null.
     *
     * @return \WP_Error|null
     */
    public static function rest_error() {
        if (!self::store_hidden_from_visitor()) {
            return null;
        }

        return new \WP_Error(
            'geekybot_store_not_open',
            __('This store is not open yet.', 'geeky-bot'),
            array('status' => 403)
        );
    }
}
