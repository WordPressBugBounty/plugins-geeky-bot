<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * This repository intentionally reads and writes Geeky Bot's own custom event
 * tables. Table identifiers come only from the trusted WordPress table prefix;
 * request values remain parameterized. Short-lived analytics reads are not
 * cached because the admin dashboard must reflect newly recorded events.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Stores low-risk storefront interaction events used by Free analytics.
 *
 * Events are deliberately allowlisted and do not store IP addresses, browser
 * fingerprints, API data, or arbitrary frontend payloads.
 */
class AnalyticsEventService {
    const TABLE_SUFFIX = 'geekybot_events';

    /**
     * Create or upgrade the analytics event table.
     */
    public static function create_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type varchar(40) NOT NULL,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            object_label varchar(190) NOT NULL DEFAULT '',
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY event_type (event_type),
            KEY object_id (object_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Record one allowlisted interaction event.
     *
     * @param string $event_type Event type.
     * @param array  $data Event data.
     * @return bool
     */
    public function record($event_type, $data = array()) {
        if (Settings::get('chat_history_enabled', 'yes') !== 'yes') {
            return false;
        }
        if (!is_user_logged_in() && Settings::get('allow_guest_sessions', 'yes') !== 'yes') {
            return false;
        }

        $event_type = sanitize_key((string) $event_type);
        if (!in_array($event_type, self::allowed_event_types(), true)) {
            return false;
        }

        $data = is_array($data) ? $data : array();
        $session_id = !empty($data['session_id'])
            ? absint($data['session_id'])
            : $this->session_id_from_key(isset($data['session_key']) ? $data['session_key'] : '');
        if ($session_id < 1) {
            return false;
        }
        $object_id = !empty($data['object_id']) ? absint($data['object_id']) : 0;
        $object_label = '';
        if ($event_type === 'product_click') {
            if ($object_id < 1 || !CatalogVisibilityService::is_visible($object_id, 'analytics_event')) {
                return false;
            }
            $product = function_exists('wc_get_product') ? wc_get_product($object_id) : null;
            $object_label = $product && method_exists($product, 'get_name')
                ? sanitize_text_field((string) $product->get_name())
                : sanitize_text_field((string) get_the_title($object_id));
        } elseif ($event_type === 'policy_source_click') {
            $selected_pages = array_filter(array_map('absint', (array) Settings::get('policy_page_ids', array())));
            $post = $object_id > 0 ? get_post($object_id) : null;
            if (!$post || $post->post_type !== 'page' || $post->post_status !== 'publish' || !in_array($object_id, $selected_pages, true)) {
                return false;
            }
            $object_label = sanitize_text_field((string) get_the_title($object_id));
        }
        $object_label = function_exists('mb_substr') ? mb_substr($object_label, 0, 190) : substr($object_label, 0, 190);
        if ($object_label === '') {
            return false;
        }
        $context = $this->sanitize_context(isset($data['context']) ? $data['context'] : array());

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . self::TABLE_SUFFIX,
            array(
                'session_id' => $session_id,
                'event_type' => $event_type,
                'object_id' => $object_id,
                'object_label' => $object_label,
                'context' => !empty($context) ? wp_json_encode($context) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );

        return $inserted !== false;
    }

    /**
     * @return string[]
     */
    public static function allowed_event_types() {
        return array('product_click', 'policy_source_click');
    }

    /**
     * @param int $session_id Session ID.
     * @return array
     */
    public function events_for_session($session_id) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        if (!$this->table_exists($table)) {
            return array();
        }

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, event_type, object_id, object_label, context, created_at
                 FROM {$table}
                 WHERE session_id = %d
                 ORDER BY id ASC
                 LIMIT 250",
                absint($session_id)
            ),
            ARRAY_A
        );
    }

    /**
     * @param int $days Number of days.
     * @param int $limit Maximum rows.
     * @return array
     */
    public function top_clicked_products($days = 30, $limit = 8) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        if (!$this->table_exists($table)) {
            return array();
        }

        $days = max(1, min(365, absint($days)));
        $limit = max(1, min(25, absint($limit)));
        $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_id, MAX(object_label) AS object_label, COUNT(*) AS clicks
                 FROM {$table}
                 WHERE event_type = %s AND created_at >= %s
                 GROUP BY object_id
                 ORDER BY clicks DESC, object_label ASC
                 LIMIT %d",
                'product_click',
                $cutoff,
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * @param string $session_key Public session key.
     * @return int
     */
    private function session_id_from_key($session_key) {
        $session_key = sanitize_text_field((string) $session_key);
        if (!preg_match('/^[a-f0-9\-]{32,64}$/', $session_key)) {
            return 0;
        }

        global $wpdb;
        $sessions = $wpdb->prefix . 'geekybot_sessions';
        if (!$this->table_exists($sessions)) {
            return 0;
        }

        return absint($wpdb->get_var($wpdb->prepare("SELECT id FROM {$sessions} WHERE session_key = %s", $session_key)));
    }

    /**
     * @param mixed $context Raw context.
     * @return array
     */
    private function sanitize_context($context) {
        if (!is_array($context)) {
            return array();
        }

        $allowed = array('surface', 'url', 'policy_type');
        $clean = array();
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }
            $value = $context[$key];
            if ($key === 'url') {
                $clean[$key] = esc_url_raw((string) $value);
            } else {
                $clean[$key] = sanitize_text_field((string) $value);
            }
        }

        return $clean;
    }

    /**
     * @param string $table Table name.
     * @return bool
     */
    private function table_exists($table) {
        global $wpdb;
        $table = sanitize_text_field((string) $table);
        return $table !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
