<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class RateLimiter {
    public function check_chat_limit() {
        /**
         * Store owners and trusted administrators can test product search without hitting
         * public visitor abuse limits. Keep this bypass server-side only.
         */
        if ($this->is_trusted_tester()) {
            return true;
        }

        $limit = max(20, min(1000, absint(Settings::get('rate_limit_messages', 120))));
        $window_minutes = max(1, min(60, absint(Settings::get('rate_limit_window_minutes', 5))));
        return $this->check_public_limit(
            'geekybot_rate_v19_',
            $limit,
            $window_minutes * MINUTE_IN_SECONDS,
            'geekybot_rate_limited',
            __('Too many assistant messages. Please wait a few minutes and try again.', 'geeky-bot')
        );
    }

    public function check_catalog_limit() {
        if ($this->is_trusted_tester()) {
            return true;
        }

        return $this->check_public_limit(
            'geekybot_catalog_rate_v1_',
            240,
            5 * MINUTE_IN_SECONDS,
            'geekybot_catalog_rate_limited',
            __('Too many product searches. Please wait a few minutes and try again.', 'geeky-bot')
        );
    }


    public function check_event_limit() {
        if ($this->is_trusted_tester()) {
            return true;
        }

        return $this->check_public_limit(
            'geekybot_event_rate_v1_',
            600,
            5 * MINUTE_IN_SECONDS,
            'geekybot_event_rate_limited',
            __('Too many assistant interaction events. Please wait a few minutes and try again.', 'geeky-bot')
        );
    }

    private function is_trusted_tester() {
        if (is_user_logged_in()) {
            $trusted_caps = array(
                'manage_options',
                'manage_woocommerce',
                'edit_shop_orders',
                'edit_products',
                'edit_posts',
            );

            foreach ($trusted_caps as $cap) {
                if (current_user_can($cap)) {
                    return true;
                }
            }
        }

        return $this->is_local_environment();
    }

    private function is_local_environment() {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $local_hosts = array('127.0.0.1', '::1');

        if (in_array($remote_addr, $local_hosts, true)) {
            return true;
        }

        if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local') {
            return true;
        }

        return false;
    }

    private function check_public_limit($prefix, $max, $window, $error_code, $message) {
        $key = sanitize_key($prefix) . $this->visitor_hash();
        $current = absint(get_transient($key));

        if ($current >= absint($max)) {
            return new \WP_Error(
                sanitize_key($error_code),
                $message,
                array('status' => 429)
            );
        }

        set_transient($key, $current + 1, absint($window));
        return true;
    }

    private function visitor_hash() {
        $user_id = get_current_user_id();
        if ($user_id) {
            return hash_hmac('sha256', 'user:' . $user_id, wp_salt('auth'));
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown';
        return hash_hmac('sha256', $ip . '|' . $ua, wp_salt('auth'));
    }
}
