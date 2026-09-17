<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Site-wide ceiling on paid AI provider calls.
 *
 * RateLimiter bounds a single visitor. Because a visitor is only ever
 * identified by IP plus User-Agent, rotating either one buys a fresh bucket,
 * so per-visitor limiting alone never bounded total spend. This counter is
 * keyed on the site and the calendar bucket instead of on the caller, so no
 * amount of rotation moves it, and it is enforced at the one place a provider
 * request is actually issued -- including for logged-in store staff, who
 * bypass the per-visitor limits by design.
 *
 * Counters live in wp_options and are incremented with a single atomic SQL
 * statement rather than a read-then-write, so concurrent chat requests cannot
 * each read the same value and overshoot the cap.
 */
class AiBudgetService {
    const DAILY_PREFIX = 'geekybot_ai_calls_d_';
    const MONTHLY_PREFIX = 'geekybot_ai_calls_m_';
    const BLOCKED_OPTION = 'geekybot_ai_budget_blocked';
    const ERROR_CODE = 'geekybot_ai_budget_exhausted';

    const DAILY_MIN = 1;
    const DAILY_MAX = 1000000;
    const MONTHLY_MIN = 1;
    const MONTHLY_MAX = 30000000;

    /** Buckets to keep before pruning. */
    const DAILY_RETENTION_DAYS = 60;
    const MONTHLY_RETENTION_MONTHS = 24;

    /**
     * Merchant-configured daily call budget.
     *
     * Clamped rather than allowed to be unlimited: an "unlimited" option would
     * restore exactly the exposure this counter exists to close.
     *
     * @return int
     */
    public static function daily_cap() {
        return self::clamp(Settings::get('ai_daily_call_cap', 1000), self::DAILY_MIN, self::DAILY_MAX);
    }

    /**
     * Merchant-configured hard monthly cap.
     *
     * @return int
     */
    public static function monthly_cap() {
        return self::clamp(Settings::get('ai_monthly_call_cap', 20000), self::MONTHLY_MIN, self::MONTHLY_MAX);
    }

    /**
     * Claim one provider call against both budgets.
     *
     * @return true|\WP_Error True when the call may proceed.
     */
    public static function reserve() {
        $daily_cap = self::daily_cap();
        $monthly_cap = self::monthly_cap();

        $daily_key = self::daily_key();
        $monthly_key = self::monthly_key();

        $daily_used = self::increment($daily_key);
        if ($daily_used > $daily_cap) {
            self::decrement($daily_key);
            return self::exhausted('daily', $daily_cap);
        }

        $monthly_used = self::increment($monthly_key);
        if ($monthly_used > $monthly_cap) {
            self::decrement($monthly_key);
            self::decrement($daily_key);
            return self::exhausted('monthly', $monthly_cap);
        }

        return true;
    }

    /**
     * Return a reserved call to both budgets when the request was not sent.
     *
     * @return void
     */
    public static function release() {
        self::decrement(self::daily_key());
        self::decrement(self::monthly_key());
    }

    /**
     * Budget state for the admin screens.
     *
     * @return array
     */
    public static function status() {
        $daily_cap = self::daily_cap();
        $monthly_cap = self::monthly_cap();
        $daily_used = self::read(self::daily_key());
        $monthly_used = self::read(self::monthly_key());
        $blocked = get_option(self::BLOCKED_OPTION, array());
        if (!is_array($blocked)) {
            $blocked = array();
        }

        return array(
            'daily' => array(
                'used' => $daily_used,
                'cap' => $daily_cap,
                'remaining' => max(0, $daily_cap - $daily_used),
                'percent' => $daily_cap > 0 ? min(100, (int) round(($daily_used / $daily_cap) * 100)) : 0,
            ),
            'monthly' => array(
                'used' => $monthly_used,
                'cap' => $monthly_cap,
                'remaining' => max(0, $monthly_cap - $monthly_used),
                'percent' => $monthly_cap > 0 ? min(100, (int) round(($monthly_used / $monthly_cap) * 100)) : 0,
            ),
            'blocked_count' => isset($blocked['count']) ? absint($blocked['count']) : 0,
            'blocked_scope' => isset($blocked['scope']) ? sanitize_key((string) $blocked['scope']) : '',
            'blocked_at' => isset($blocked['at']) ? (string) $blocked['at'] : '',
        );
    }

    /**
     * @param string $scope daily|monthly
     * @param int    $cap Cap that was reached.
     * @return \WP_Error
     */
    private static function exhausted($scope, $cap) {
        self::record_block($scope);

        return new \WP_Error(
            self::ERROR_CODE,
            $scope === 'monthly'
                ? __('The monthly AI answer budget for this store has been reached.', 'geeky-bot')
                : __('The daily AI answer budget for this store has been reached.', 'geeky-bot'),
            array('scope' => $scope, 'cap' => absint($cap))
        );
    }

    /**
     * Keeps the ceiling a visible dial in the admin instead of a silent failure.
     *
     * @param string $scope daily|monthly
     * @return void
     */
    private static function record_block($scope) {
        $blocked = get_option(self::BLOCKED_OPTION, array());
        if (!is_array($blocked)) {
            $blocked = array();
        }

        $bucket = self::daily_key();
        if (!isset($blocked['bucket']) || $blocked['bucket'] !== $bucket) {
            $blocked = array('bucket' => $bucket, 'count' => 0);
        }

        $blocked['count'] = absint(isset($blocked['count']) ? $blocked['count'] : 0) + 1;
        $blocked['scope'] = sanitize_key((string) $scope);
        $blocked['at'] = current_time('mysql');

        update_option(self::BLOCKED_OPTION, $blocked, false);
    }

    /**
     * @return string
     */
    private static function daily_key() {
        return self::DAILY_PREFIX . wp_date('Ymd');
    }

    /**
     * @return string
     */
    private static function monthly_key() {
        return self::MONTHLY_PREFIX . wp_date('Ym');
    }

    /**
     * Uncached read. Counters are only read on a provider call or in the
     * admin, so correctness matters more than the object cache here.
     *
     * @param string $option_name Counter option.
     * @return int
     */
    private static function read($option_name) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A cost ceiling must read the committed value, not a cached one.
        $value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option_name));

        return null === $value ? 0 : absint($value);
    }

    /**
     * Atomic +1 that returns the value this caller claimed.
     *
     * @param string $option_name Counter option.
     * @return int
     */
    private static function increment($option_name) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Single-statement atomic increment; the option name is a placeholder.
        $updated = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $option_name));

        if (!$updated) {
            // First call in this bucket. add_option is an INSERT, so a losing
            // racer simply falls through to the increment below.
            if (add_option($option_name, '1', '', 'no')) {
                self::prune();
                return 1;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- See above.
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $option_name));
        }

        wp_cache_delete($option_name, 'options');

        return self::read($option_name);
    }

    /**
     * Atomic -1, floored at zero. Used to hand back a reservation that was
     * claimed but never spent.
     *
     * @param string $option_name Counter option.
     * @return void
     */
    private static function decrement($option_name) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Single-statement atomic decrement; the option name is a placeholder.
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = GREATEST(CAST(option_value AS SIGNED) - 1, 0) WHERE option_name = %s", $option_name));
        wp_cache_delete($option_name, 'options');
    }

    /**
     * Drop expired counter rows. Runs once per new daily bucket.
     *
     * @return void
     */
    private static function prune() {
        global $wpdb;

        $daily_cutoff = self::DAILY_PREFIX . wp_date('Ymd', time() - (self::DAILY_RETENTION_DAYS * DAY_IN_SECONDS));
        $monthly_cutoff = self::MONTHLY_PREFIX . wp_date('Ym', time() - (self::MONTHLY_RETENTION_MONTHS * 31 * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Fixed-width date suffixes sort as strings; both values use placeholders.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s", $wpdb->esc_like(self::DAILY_PREFIX) . '%', $daily_cutoff));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- See above.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s", $wpdb->esc_like(self::MONTHLY_PREFIX) . '%', $monthly_cutoff));
    }

    /**
     * @param mixed $value Raw setting value.
     * @param int   $min Lower bound.
     * @param int   $max Upper bound.
     * @return int
     */
    private static function clamp($value, $min, $max) {
        return max($min, min($max, absint($value)));
    }
}
