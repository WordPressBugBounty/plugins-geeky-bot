<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * WordPress privacy export and erasure callbacks must query the plugin's custom
 * conversation tables directly. Table names are fixed from $wpdb->prefix;
 * generated IN lists contain only integer placeholders and prepared values.
 * Privacy operations intentionally bypass caches to avoid stale exports/deletes.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

class PrivacyService {
    const EXPORTER_ID = 'geeky-bot-conversations';
    const ERASER_ID = 'geeky-bot-conversations';

    public function hooks() {
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_eraser'));
    }

    public function register_exporter($exporters) {
        $exporters[self::EXPORTER_ID] = array(
            'exporter_friendly_name' => __('Geeky Bot conversations', 'geeky-bot'),
            'callback' => array($this, 'export_personal_data'),
        );
        return $exporters;
    }

    public function register_eraser($erasers) {
        $erasers[self::ERASER_ID] = array(
            'eraser_friendly_name' => __('Geeky Bot conversations', 'geeky-bot'),
            'callback' => array($this, 'erase_personal_data'),
        );
        return $erasers;
    }

    public function export_personal_data($email_address, $page = 1) {
        global $wpdb;

        $page = max(1, absint($page));
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        $email_address = sanitize_email((string) $email_address);
        $user = $email_address ? get_user_by('email', $email_address) : false;
        $user_id = $user ? absint($user->ID) : 0;

        if ($email_address === '' && !$user_id) {
            return array('data' => array(), 'done' => true);
        }

        $sessions_table = $wpdb->prefix . 'geekybot_sessions';
        $messages_table = $wpdb->prefix . 'geekybot_messages';
        $events_table = $wpdb->prefix . AnalyticsEventService::TABLE_SUFFIX;
        if (!$this->table_exists($sessions_table) || !$this->table_exists($messages_table)) {
            return array('data' => array(), 'done' => true);
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$sessions_table} WHERE customer_email = %s OR user_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $email_address,
                $user_id,
                $per_page + 1,
                $offset
            ),
            ARRAY_A
        );

        $done = count((array) $rows) <= $per_page;
        $rows = array_slice((array) $rows, 0, $per_page);
        $data = array();

        foreach ($rows as $session) {
            $session_id = absint($session['id']);
            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT direction, message, created_at FROM {$messages_table} WHERE session_id = %d ORDER BY id ASC LIMIT 100",
                    $session_id
                ),
                ARRAY_A
            );

            $conversation = array();
            foreach ((array) $messages as $message) {
                $conversation[] = sprintf(
                    '[%1$s] %2$s: %3$s',
                    sanitize_text_field((string) $message['created_at']),
                    sanitize_text_field((string) $message['direction']),
                    wp_strip_all_tags((string) $message['message'])
                );
            }

            $event_summary = '';
            if ($this->table_exists($events_table)) {
                $event_rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT event_type, object_label, created_at FROM {$events_table} WHERE session_id = %d ORDER BY id ASC LIMIT 100",
                        $session_id
                    ),
                    ARRAY_A
                );
                $event_lines = array();
                foreach ((array) $event_rows as $event_row) {
                    $event_lines[] = sprintf(
                        '[%1$s] %2$s: %3$s',
                        sanitize_text_field((string) $event_row['created_at']),
                        sanitize_key((string) $event_row['event_type']),
                        sanitize_text_field((string) $event_row['object_label'])
                    );
                }
                $event_summary = implode("
", $event_lines);
            }

            $data[] = array(
                'group_id' => 'geeky-bot-conversations',
                'group_label' => __('Geeky Bot conversations', 'geeky-bot'),
                'item_id' => 'geeky-bot-session-' . $session_id,
                'data' => array(
                    array('name' => __('Session key', 'geeky-bot'), 'value' => sanitize_text_field((string) $session['session_key'])),
                    array('name' => __('User ID', 'geeky-bot'), 'value' => absint($session['user_id'])),
                    array('name' => __('Created', 'geeky-bot'), 'value' => sanitize_text_field((string) $session['created_at'])),
                    array('name' => __('Updated', 'geeky-bot'), 'value' => sanitize_text_field((string) $session['updated_at'])),
                    array('name' => __('Conversation', 'geeky-bot'), 'value' => implode("\n", $conversation)),
                    array('name' => __('Product and source clicks', 'geeky-bot'), 'value' => $event_summary),
                ),
            );
        }

        return array('data' => $data, 'done' => $done);
    }

    public function erase_personal_data($email_address, $page = 1) {
        global $wpdb;

        $email_address = sanitize_email((string) $email_address);
        $user = $email_address ? get_user_by('email', $email_address) : false;
        $user_id = $user ? absint($user->ID) : 0;

        if ($email_address === '' && !$user_id) {
            return array('items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true);
        }

        $sessions_table = $wpdb->prefix . 'geekybot_sessions';
        $messages_table = $wpdb->prefix . 'geekybot_messages';
        $unanswered_table = $wpdb->prefix . 'geekybot_unanswered';
        $events_table = $wpdb->prefix . AnalyticsEventService::TABLE_SUFFIX;
        if (!$this->table_exists($sessions_table)) {
            return array('items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true);
        }

        $session_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$sessions_table} WHERE customer_email = %s OR user_id = %d",
                $email_address,
                $user_id
            )
        );

        $removed = false;
        if (!empty($session_ids)) {
            $session_ids = array_map('absint', $session_ids);
            $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));

            if ($this->table_exists($messages_table)) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$messages_table} WHERE session_id IN ({$placeholders})", $session_ids));
            }
            if ($this->table_exists($unanswered_table)) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$unanswered_table} WHERE session_id IN ({$placeholders})", $session_ids));
            }
            if ($this->table_exists($events_table)) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$events_table} WHERE session_id IN ({$placeholders})", $session_ids));
            }
            $wpdb->query($wpdb->prepare("DELETE FROM {$sessions_table} WHERE id IN ({$placeholders})", $session_ids));
            $removed = true;
        }

        return array(
            'items_removed' => $removed,
            'items_retained' => false,
            'messages' => $removed ? array(__('Geeky Bot conversation data was removed.', 'geeky-bot')) : array(),
            'done' => true,
        );
    }

    private function table_exists($table) {
        global $wpdb;
        $table = sanitize_text_field((string) $table);
        return $table !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
