<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * This repository intentionally queries Geeky Bot's custom conversation
 * tables. Identifiers are built only from $wpdb->prefix and fixed suffixes;
 * request values use placeholders. Review screens need current data, so their
 * bounded administrative reads are deliberately not object-cached.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Privacy-aware read/write service for conversation review and basic analytics.
 */
class ConversationInsightsService {

    /**
     * Decode structured context stored by current or earlier 2.0 preview builds.
     *
     * Some WordPress/database combinations can return JSON with one extra
     * slash or encoding layer. Accept those safe storage variants while still
     * returning only bounded scalar context used by the admin review screens.
     *
     * @param mixed $value Stored value.
     * @return array
     */
    public static function decode_stored_context($value) {
        $decoded = self::decode_stored_array($value);
        if (empty($decoded) || !is_array($decoded)) {
            return array();
        }

        $clean = array();
        foreach ($decoded as $key => $item) {
            $key = sanitize_key((string) $key);
            if ($key === '' || is_array($item) || is_object($item) || is_resource($item)) {
                continue;
            }

            if ($key === 'product_id' || substr($key, -3) === '_id') {
                $clean[$key] = absint($item);
                continue;
            }

            $item = sanitize_text_field((string) $item);
            $clean[$key] = function_exists('mb_substr') ? mb_substr($item, 0, 500) : substr($item, 0, 500);
        }

        return $clean;
    }

    /**
     * Decode one stored array from JSON, slashed JSON, nested JSON, or a legacy
     * serialized value.
     *
     * @param mixed $value Stored value.
     * @return array
     */
    private static function decode_stored_array($value) {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return get_object_vars($value);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return array();
        }

        $candidates = array($raw);
        if (function_exists('wp_unslash')) {
            $candidates[] = wp_unslash($raw);
        }
        $candidates[] = stripslashes($raw);
        $candidates[] = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        $candidates = array_values(array_unique(array_filter($candidates, 'strlen')));

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (is_string($decoded) && $decoded !== $candidate) {
                $nested = json_decode($decoded, true);
                if (is_array($nested)) {
                    return $nested;
                }
            }

            if (function_exists('is_serialized') && is_serialized($candidate)) {
                $legacy = maybe_unserialize($candidate);
                if (is_array($legacy)) {
                    return $legacy;
                }
            }
        }

        return array();
    }
    /**
     * @param int $days Reporting window.
     * @return array
     */
    public function summary($days = 30) {
        global $wpdb;

        $days = max(1, min(365, absint($days)));
        $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        $sessions = $wpdb->prefix . 'geekybot_sessions';
        $messages = $wpdb->prefix . 'geekybot_messages';
        $unanswered = $wpdb->prefix . 'geekybot_unanswered';
        $events = $wpdb->prefix . AnalyticsEventService::TABLE_SUFFIX;

        $summary = array(
            'sessions' => 0,
            'messages' => 0,
            'shopper_messages' => 0,
            'bot_messages' => 0,
            'unanswered' => 0,
            'product_clicks' => 0,
            'policy_source_clicks' => 0,
            'average_messages' => 0,
        );

        if ($this->table_exists($sessions)) {
            $summary['sessions'] = absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sessions} WHERE updated_at >= %s", $cutoff)));
        }
        if ($this->table_exists($messages)) {
            $message_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT direction, COUNT(*) AS total FROM {$messages} WHERE created_at >= %s GROUP BY direction",
                    $cutoff
                ),
                ARRAY_A
            );
            foreach ((array) $message_rows as $row) {
                $count = absint($row['total']);
                $summary['messages'] += $count;
                if ($row['direction'] === 'user') {
                    $summary['shopper_messages'] = $count;
                } elseif ($row['direction'] === 'bot') {
                    $summary['bot_messages'] = $count;
                }
            }
        }
        if ($this->table_exists($unanswered)) {
            $summary['unanswered'] = absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$unanswered} WHERE created_at >= %s", $cutoff)));
        }
        if ($this->table_exists($events)) {
            $event_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT event_type, COUNT(*) AS total FROM {$events} WHERE created_at >= %s GROUP BY event_type",
                    $cutoff
                ),
                ARRAY_A
            );
            foreach ((array) $event_rows as $row) {
                if ($row['event_type'] === 'product_click') {
                    $summary['product_clicks'] = absint($row['total']);
                } elseif ($row['event_type'] === 'policy_source_click') {
                    $summary['policy_source_clicks'] = absint($row['total']);
                }
            }
        }

        if ($summary['sessions'] > 0) {
            $summary['average_messages'] = round($summary['messages'] / $summary['sessions'], 1);
        }

        return $summary;
    }

    /**
     * @param int    $page Page number.
     * @param int    $per_page Rows per page.
     * @param string $search Optional phrase.
     * @return array
     */
    public function recent_sessions($page = 1, $per_page = 15, $search = '') {
        global $wpdb;

        $sessions = $wpdb->prefix . 'geekybot_sessions';
        $messages = $wpdb->prefix . 'geekybot_messages';
        $unanswered = $wpdb->prefix . 'geekybot_unanswered';
        $events = $wpdb->prefix . AnalyticsEventService::TABLE_SUFFIX;
        if (!$this->table_exists($sessions)) {
            return array('items' => array(), 'total' => 0, 'pages' => 0, 'page' => 1);
        }

        $page = max(1, absint($page));
        $per_page = max(5, min(50, absint($per_page)));
        $offset = ($page - 1) * $per_page;
        $search = sanitize_text_field((string) $search);
        $where = '1=1';
        $where_args = array();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            if ($this->table_exists($messages)) {
                $where = "(CAST(s.id AS CHAR) LIKE %s OR EXISTS (
                    SELECT 1 FROM {$messages} sm
                    WHERE sm.session_id = s.id AND sm.direction = 'user' AND sm.message LIKE %s
                ))";
                $where_args = array($like, $like);
            } else {
                $where = 'CAST(s.id AS CHAR) LIKE %s';
                $where_args = array($like);
            }
        }

        $count_sql = "SELECT COUNT(*) FROM {$sessions} s WHERE {$where}";
        // The query fragments above are fixed internal SQL; only search values are variable and they use placeholders.
        $total = !empty($where_args)
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Search values use placeholders in the prepared query.
            ? absint($wpdb->get_var($wpdb->prepare($count_sql, $where_args)))
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- No variable input is present when there are no WHERE arguments.
            : absint($wpdb->get_var($count_sql));

        $event_count_sql = $this->table_exists($events)
            ? "(SELECT COUNT(*) FROM {$events} e WHERE e.session_id = s.id AND e.event_type = 'product_click')"
            : '0';
        $unanswered_count_sql = $this->table_exists($unanswered)
            ? "(SELECT COUNT(*) FROM {$unanswered} u WHERE u.session_id = s.id)"
            : '0';
        $message_count_sql = $this->table_exists($messages)
            ? "(SELECT COUNT(*) FROM {$messages} m WHERE m.session_id = s.id)"
            : '0';
        $last_message_sql = $this->table_exists($messages)
            ? "(SELECT message FROM {$messages} lm WHERE lm.session_id = s.id AND lm.direction = 'user' ORDER BY lm.id DESC LIMIT 1)"
            : "''";

        $sql = "SELECT s.id, s.user_id, s.created_at, s.updated_at, s.ended_at,
                       {$message_count_sql} AS message_count,
                       {$unanswered_count_sql} AS unanswered_count,
                       {$event_count_sql} AS product_clicks,
                       {$last_message_sql} AS last_shopper_message
                FROM {$sessions} s
                WHERE {$where}
                ORDER BY s.updated_at DESC
                LIMIT %d OFFSET %d";
        $args = array_merge($where_args, array($per_page, $offset));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- All dynamic values use placeholders; SELECT fragments are fixed internal expressions.
        $items = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

        return array(
            'items' => (array) $items,
            'total' => $total,
            'pages' => $total > 0 ? (int) ceil($total / $per_page) : 0,
            'page' => $page,
        );
    }

    /**
     * @param int $session_id Session ID.
     * @return array
     */
    public function session_detail($session_id) {
        global $wpdb;

        $session_id = absint($session_id);
        $sessions = $wpdb->prefix . 'geekybot_sessions';
        $messages = $wpdb->prefix . 'geekybot_messages';
        $unanswered = $wpdb->prefix . 'geekybot_unanswered';
        if (!$session_id || !$this->table_exists($sessions)) {
            return array();
        }

        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, created_at, updated_at, ended_at FROM {$sessions} WHERE id = %d",
                $session_id
            ),
            ARRAY_A
        );
        if (empty($session)) {
            return array();
        }

        $message_rows = $this->table_exists($messages)
            ? $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, direction, message, payload, created_at FROM {$messages} WHERE session_id = %d ORDER BY id ASC LIMIT 500",
                    $session_id
                ),
                ARRAY_A
            )
            : array();
        $messages_clean = array();
        foreach ((array) $message_rows as $message) {
            $payload = self::decode_stored_array(isset($message['payload']) ? $message['payload'] : '');
            $product_names = array();
            foreach ((array) ($payload['products'] ?? array()) as $product) {
                if (!empty($product['name'])) {
                    $product_names[] = sanitize_text_field((string) $product['name']);
                }
            }
            $messages_clean[] = array(
                'id' => absint($message['id']),
                'direction' => sanitize_key((string) $message['direction']),
                'message' => wp_strip_all_tags((string) $message['message']),
                'intent' => !empty($payload['intent']) ? sanitize_key((string) $payload['intent']) : '',
                'products' => array_slice(array_values(array_unique($product_names)), 0, 8),
                'created_at' => sanitize_text_field((string) $message['created_at']),
            );
        }

        $unanswered_rows = $this->table_exists($unanswered)
            ? $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, question, reason, CAST(`context` AS CHAR) AS context_json, OCTET_LENGTH(`context`) AS context_length, created_at FROM {$unanswered} WHERE session_id = %d ORDER BY id ASC",
                    $session_id
                ),
                ARRAY_A
            )
            : array();
        $unanswered_rows = $this->hydrate_unanswered_rows($unanswered_rows, $unanswered);

        return array(
            'session' => $session,
            'messages' => $messages_clean,
            'unanswered' => (array) $unanswered_rows,
            'events' => (new AnalyticsEventService())->events_for_session($session_id),
        );
    }

    /**
     * @param int $limit Maximum rows.
     * @return array
     */
    public function recent_unanswered($limit = 100) {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_unanswered';
        if (!$this->table_exists($table)) {
            return array();
        }
        $limit = max(1, min(5000, absint($limit)));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, session_id, question, reason, CAST(`context` AS CHAR) AS context_json, OCTET_LENGTH(`context`) AS context_length, created_at FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        $rows = $this->hydrate_unanswered_rows($rows, $table);

        return array_map(
            static function ($row) {
                return (object) $row;
            },
            $rows
        );
    }

    /**
     * Normalize a shopper question for duplicate review grouping.
     *
     * The normalized form is deliberately conservative. It ignores case,
     * punctuation, repeated whitespace, and accents, while preserving product
     * words, numbers, sizes, and prices that can materially change the issue.
     *
     * @param string $question Shopper question.
     * @return string
     */
    public static function normalize_review_question($question) {
        $question = html_entity_decode(wp_strip_all_tags((string) $question), ENT_QUOTES, 'UTF-8');
        $question = remove_accents($question);
        $question = function_exists('mb_strtolower') ? mb_strtolower($question, 'UTF-8') : strtolower($question);
        $question = preg_replace('/(?<=\d)\.(?=\d)/u', 'gbdecimalpoint', $question);
        $question = preg_replace('/[^\p{L}\p{N}\s\$%]+/u', ' ', $question);
        $question = str_replace('gbdecimalpoint', '.', (string) $question);
        $question = preg_replace('/\s+/u', ' ', trim((string) $question));

        if (function_exists('mb_substr')) {
            return mb_substr((string) $question, 0, 500, 'UTF-8');
        }

        return substr((string) $question, 0, 500);
    }

    /**
     * Return duplicate unanswered questions as one store-owner issue.
     *
     * @param int    $limit  Maximum raw rows considered.
     * @param string $search Optional admin search phrase.
     * @return array
     */
    public function grouped_unanswered($limit = 5000, $search = '') {
        $groups = array();
        $rows = $this->recent_unanswered($limit);

        foreach ($rows as $row) {
            $question = sanitize_text_field((string) ($row->question ?? ''));
            $normalized = self::normalize_review_question($question);
            if ($normalized === '') {
                continue;
            }

            if (!isset($groups[$normalized])) {
                $groups[$normalized] = array(
                    'id' => absint($row->id ?? 0),
                    'session_id' => absint($row->session_id ?? 0),
                    'question' => $question,
                    'normalized_question' => $normalized,
                    'reason' => sanitize_key((string) ($row->reason ?? '')),
                    'context' => is_array($row->context ?? null) ? $row->context : array(),
                    'created_at' => sanitize_text_field((string) ($row->created_at ?? '')),
                    'first_asked_at' => sanitize_text_field((string) ($row->created_at ?? '')),
                    'last_asked_at' => sanitize_text_field((string) ($row->created_at ?? '')),
                    'occurrence_count' => 0,
                    'conversation_ids' => array(),
                    'reason_counts' => array(),
                    'product_names' => array(),
                    'policy_labels' => array(),
                );
            }

            $group = &$groups[$normalized];
            $group['occurrence_count']++;
            $session_id = absint($row->session_id ?? 0);
            if ($session_id > 0) {
                $group['conversation_ids'][$session_id] = true;
            }

            $reason = sanitize_key((string) ($row->reason ?? ''));
            if ($reason !== '') {
                $group['reason_counts'][$reason] = isset($group['reason_counts'][$reason])
                    ? $group['reason_counts'][$reason] + 1
                    : 1;
            }

            $created_at = sanitize_text_field((string) ($row->created_at ?? ''));
            if ($created_at !== '' && ($group['first_asked_at'] === '' || strcmp($created_at, $group['first_asked_at']) < 0)) {
                $group['first_asked_at'] = $created_at;
            }
            if ($created_at !== '' && ($group['last_asked_at'] === '' || strcmp($created_at, $group['last_asked_at']) > 0)) {
                $group['last_asked_at'] = $created_at;
            }

            $context = is_array($row->context ?? null) ? $row->context : array();
            if (!empty($context['product_name'])) {
                $product_name = sanitize_text_field((string) $context['product_name']);
                if ($product_name !== '') {
                    $group['product_names'][$product_name] = true;
                }
            }
            if (!empty($context['policy_label'])) {
                $policy_label = sanitize_text_field((string) $context['policy_label']);
                if ($policy_label !== '') {
                    $group['policy_labels'][$policy_label] = true;
                }
            }
            unset($group);
        }

        $search_normalized = self::normalize_review_question($search);
        $items = array();
        foreach ($groups as $group) {
            if (!empty($group['reason_counts'])) {
                arsort($group['reason_counts'], SORT_NUMERIC);
                $group['reason'] = sanitize_key((string) array_key_first($group['reason_counts']));
            }

            $group['conversation_count'] = count($group['conversation_ids']);
            $group['product_names'] = array_slice(array_keys($group['product_names']), 0, 12);
            $group['policy_labels'] = array_slice(array_keys($group['policy_labels']), 0, 12);
            unset($group['conversation_ids']);

            if ($search_normalized !== '') {
                $meta = self::reason_meta($group['reason']);
                $haystack = implode(' ', array_merge(
                    array(
                        $group['normalized_question'],
                        self::normalize_review_question((string) $group['reason']),
                        self::normalize_review_question((string) ($meta['label'] ?? '')),
                    ),
                    array_map(array(__CLASS__, 'normalize_review_question'), $group['product_names']),
                    array_map(array(__CLASS__, 'normalize_review_question'), $group['policy_labels'])
                ));
                if (strpos($haystack, $search_normalized) === false) {
                    continue;
                }
            }

            $items[] = (object) $group;
        }

        usort(
            $items,
            static function ($left, $right) {
                $count_compare = absint($right->occurrence_count ?? 0) <=> absint($left->occurrence_count ?? 0);
                if ($count_compare !== 0) {
                    return $count_compare;
                }

                return strcmp((string) ($right->last_asked_at ?? ''), (string) ($left->last_asked_at ?? ''));
            }
        );

        return $items;
    }

    /**
     * Decode context for unanswered rows using a stable scalar alias.
     *
     * A small number of MariaDB/wpdb combinations have returned an empty value
     * for the context field when it is selected as part of a wider result row,
     * even though a direct scalar read succeeds. Use the bulk value normally,
     * then fall back to one direct read only for rows whose decoded context is
     * unexpectedly empty. This keeps the normal admin path efficient while
     * making conversation detail reliable on those environments.
     *
     * @param array  $rows  Unanswered rows as associative arrays.
     * @param string $table Unanswered table name generated from the WP prefix.
     * @return array
     */
    private function hydrate_unanswered_rows($rows, $table) {
        global $wpdb;

        $rows = is_array($rows) ? $rows : array();
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                $row = (array) $row;
            }

            $raw = isset($row['context_json']) ? $row['context_json'] : '';
            $decoded = self::decode_stored_context($raw);
            $row_id = !empty($row['id']) ? absint($row['id']) : 0;
            $stored_length = isset($row['context_length']) ? absint($row['context_length']) : strlen((string) $raw);

            if (empty($decoded) && $row_id > 0 && $stored_length > 0) {
                $fallback_raw = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT CAST(`context` AS CHAR) FROM {$table} WHERE id = %d LIMIT 1",
                        $row_id
                    )
                );
                $decoded = self::decode_stored_context($fallback_raw);
            }

            $row['context'] = $decoded;
            unset($row['context_json'], $row['context_length']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param int $days Reporting window.
     * @return array
     */
    public function reason_breakdown($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_unanswered';
        if (!$this->table_exists($table)) {
            return array();
        }

        $days = max(1, min(365, absint($days)));
        $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT reason, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY reason ORDER BY total DESC, reason ASC",
                $cutoff
            ),
            ARRAY_A
        );
        foreach ((array) $rows as &$row) {
            $row['meta'] = self::reason_meta($row['reason']);
        }
        unset($row);

        return (array) $rows;
    }

    /**
     * @param int $days Reporting window.
     * @return array
     */
    public function daily_activity($days = 14) {
        global $wpdb;

        $messages = $wpdb->prefix . 'geekybot_messages';
        if (!$this->table_exists($messages)) {
            return array();
        }

        $days = max(2, min(90, absint($days)));
        $cutoff = gmdate('Y-m-d 00:00:00', current_time('timestamp') - (($days - 1) * DAY_IN_SECONDS));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS activity_date,
                        SUM(CASE WHEN direction = 'user' THEN 1 ELSE 0 END) AS shopper_messages,
                        COUNT(DISTINCT session_id) AS sessions
                 FROM {$messages}
                 WHERE created_at >= %s
                 GROUP BY DATE(created_at)
                 ORDER BY activity_date ASC",
                $cutoff
            ),
            ARRAY_A
        );
        $by_date = array();
        foreach ((array) $rows as $row) {
            $by_date[$row['activity_date']] = $row;
        }

        $result = array();
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = gmdate('Y-m-d', current_time('timestamp') - ($offset * DAY_IN_SECONDS));
            $row = isset($by_date[$date]) ? $by_date[$date] : array();
            $result[] = array(
                'date' => $date,
                'shopper_messages' => absint($row['shopper_messages'] ?? 0),
                'sessions' => absint($row['sessions'] ?? 0),
            );
        }

        return $result;
    }

    /**
     * @param int $limit Maximum exported rows.
     * @return array
     */
    public function export_rows($limit = 5000) {
        global $wpdb;

        $sessions = $wpdb->prefix . 'geekybot_sessions';
        $messages = $wpdb->prefix . 'geekybot_messages';
        if (!$this->table_exists($sessions) || !$this->table_exists($messages)) {
            return array();
        }

        $limit = max(1, min(10000, absint($limit)));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.session_id, s.created_at AS session_created_at, s.updated_at AS session_updated_at,
                        m.direction, m.message, m.payload, m.created_at
                 FROM {$messages} m
                 INNER JOIN {$sessions} s ON s.id = m.session_id
                 ORDER BY m.created_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $clean = array();
        foreach ((array) $rows as $row) {
            $payload = !empty($row['payload']) ? json_decode((string) $row['payload'], true) : array();
            $clean[] = array(
                'conversation' => 'Conversation #' . absint($row['session_id']),
                'session_created_at' => sanitize_text_field((string) $row['session_created_at']),
                'session_updated_at' => sanitize_text_field((string) $row['session_updated_at']),
                'direction' => sanitize_key((string) $row['direction']),
                'message' => wp_strip_all_tags((string) $row['message']),
                'intent' => is_array($payload) && !empty($payload['intent']) ? sanitize_key((string) $payload['intent']) : '',
                'created_at' => sanitize_text_field((string) $row['created_at']),
            );
        }

        return $clean;
    }

    /**
     * @param int $session_id Session ID.
     * @return bool
     */
    public function delete_session($session_id) {
        global $wpdb;

        $session_id = absint($session_id);
        if (!$session_id) {
            return false;
        }

        $tables = array(
            $wpdb->prefix . 'geekybot_messages',
            $wpdb->prefix . 'geekybot_unanswered',
            $wpdb->prefix . AnalyticsEventService::TABLE_SUFFIX,
        );
        foreach ($tables as $table) {
            if ($this->table_exists($table)) {
                $wpdb->delete($table, array('session_id' => $session_id), array('%d'));
            }
        }

        $sessions = $wpdb->prefix . 'geekybot_sessions';
        if (!$this->table_exists($sessions)) {
            return false;
        }

        return $wpdb->delete($sessions, array('id' => $session_id), array('%d')) !== false;
    }

    /**
     * @return bool
     */
    public function delete_all() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . AnalyticsEventService::TABLE_SUFFIX,
            $wpdb->prefix . 'geekybot_unanswered',
            $wpdb->prefix . 'geekybot_messages',
            $wpdb->prefix . 'geekybot_sessions',
        );
        $success = true;
        foreach ($tables as $table) {
            if ($this->table_exists($table) && $wpdb->query("DELETE FROM {$table}") === false) {
                $success = false;
            }
        }

        delete_option('geekybot_review_manual_handled');
        delete_option('geekybot_review_ignored');

        return $success;
    }

    /**
     * @param string $reason Stored reason key.
     * @return array
     */
    public static function reason_meta($reason) {
        $reason = sanitize_key((string) $reason);
        $map = array(
            'product_discovery_no_match' => array('bucket' => 'product', 'label' => __('No matching product', 'geeky-bot'), 'action' => __('Review product titles, categories, attributes, stock, price, and common synonyms.', 'geeky-bot')),
            'product_fact_missing' => array('bucket' => 'product', 'label' => __('Product detail missing', 'geeky-bot'), 'action' => __('Add the missing fact to the WooCommerce product description or attributes.', 'geeky-bot')),
            'product_reference_unresolved' => array('bucket' => 'product', 'label' => __('Product reference unclear', 'geeky-bot'), 'action' => __('Retest the selection flow and ensure product names are distinctive.', 'geeky-bot')),
            'policy_source_missing' => array('bucket' => 'policy', 'label' => __('Policy source missing', 'geeky-bot'), 'action' => __('Select a published page for this policy area in Store Knowledge.', 'geeky-bot')),
            'policy_detail_missing' => array('bucket' => 'policy', 'label' => __('Policy detail missing', 'geeky-bot'), 'action' => __('Add a clear answer to the selected policy page and refresh the knowledge index.', 'geeky-bot')),
            'no_catalog_or_policy_match' => array('bucket' => 'coverage', 'label' => __('No grounded answer', 'geeky-bot'), 'action' => __('Check whether the phrase belongs to products, policy content, or a Commerce Pro action.', 'geeky-bot')),
        );

        return isset($map[$reason])
            ? $map[$reason]
            : array('bucket' => 'coverage', 'label' => $reason !== '' ? ucwords(str_replace('_', ' ', $reason)) : __('Needs review', 'geeky-bot'), 'action' => __('Review the shopper phrase and improve the matching store data.', 'geeky-bot'));
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
