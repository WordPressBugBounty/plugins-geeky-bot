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
            /**
             * Store staff testing the assistant. Deliberately excludes
             * 'edit_posts': the default Contributor and Author roles hold it,
             * so including it turned "trusted store staff" into "anyone who can
             * draft a blog post" -- and every bypassed message is a paid call.
             */
            $trusted_caps = array(
                'manage_options',
                'manage_woocommerce',
                'edit_shop_orders',
                'edit_products',
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

        $ip = self::visitor_ip();
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown';
        return hash_hmac('sha256', $ip . '|' . $ua, wp_salt('auth'));
    }

    /**
     * Best available client address.
     *
     * REMOTE_ADDR is the only address the server observes directly, so it is
     * the default. X-Forwarded-For is attacker-controlled unless a proxy is
     * genuinely in front of the site, and is therefore believed only when the
     * merchant has declared how many proxies to trust -- the entry that many
     * hops from the right is the one those proxies actually appended.
     *
     * Public and static because the add-on limits its own REST endpoints and
     * has to reach the same answer. Two definitions of "who is this visitor"
     * across one product is worse than none: with the proxy setting honoured
     * here and ignored there, core counts each shopper while the add-on counts
     * the proxy, and one shared bucket 429s an entire storefront.
     *
     * @return string
     */
    public static function visitor_ip() {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $remote_addr = filter_var($remote_addr, FILTER_VALIDATE_IP) ? $remote_addr : '';

        $trusted_proxies = min(10, absint(Settings::get('trusted_proxy_count', 0)));
        if ($trusted_proxies < 1 || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $remote_addr !== '' ? $remote_addr : 'unknown';
        }

        $forwarded = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        $hops = array_values(array_filter(array_map('trim', explode(',', $forwarded))));
        if (empty($hops)) {
            return $remote_addr !== '' ? $remote_addr : 'unknown';
        }

        // Anything further left than the declared proxy count was written by
        // the client and is not evidence of anything.
        $index = count($hops) - $trusted_proxies;
        if ($index < 0) {
            $index = 0;
        }
        if ($index > count($hops) - 1) {
            $index = count($hops) - 1;
        }

        $candidate = trim((string) $hops[$index]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }

        // "1.2.3.4:5678" and "[2001:db8::1]:5678" both appear in the wild.
        $unbracketed = preg_replace('/^\[(.+)\](?::\d+)?$/', '$1', $candidate);
        if ($unbracketed !== $candidate && filter_var($unbracketed, FILTER_VALIDATE_IP)) {
            return $unbracketed;
        }

        $unported = preg_replace('/:\d+$/', '', $candidate);
        if (filter_var($unported, FILTER_VALIDATE_IP)) {
            return $unported;
        }

        return $remote_addr !== '' ? $remote_addr : 'unknown';
    }
}
