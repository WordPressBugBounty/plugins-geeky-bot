<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Installation, schema repair, and retention cleanup operate on fixed
 * plugin-owned tables derived from $wpdb->prefix. Dynamic IN placeholders are
 * generated from integer ID arrays and all external values remain prepared.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter

class Installer {
    public static function activate() {
        $is_new_install = !get_option(Settings::OPTION);
        if (!DatabaseMigrator::migrate()) {
            return;
        }

        if ($is_new_install) {
            update_option(Settings::OPTION, Settings::defaults(), false);
            if (class_exists('GeekyBot\Services\OnboardingService')) {
                OnboardingService::schedule_first_run();
            }
        }

        if (class_exists('GeekyBot\\Services\\KnowledgeIndexService')) {
            KnowledgeIndexService::schedule_sync();
        }

        if (class_exists('GeekyBot\\Services\\ProductIndexService')) {
            ProductIndexService::request_rebuild(30);
        }

        update_option('geekybot_v2_version', GEEKYBOT_VERSION, false);
    }

    public static function maybe_upgrade() {
        if (!DatabaseMigrator::maybe_migrate()) {
            return false;
        }

        $stored = get_option('geekybot_v2_version', '');
        if ($stored !== GEEKYBOT_VERSION) {
            $settings = wp_parse_args(Settings::all(), Settings::defaults());

            /**
             * Earlier 2.0 preview builds used a very low default limit. Raise old
             * saved values once so normal product search is not interrupted.
             */
            if (!get_option('geekybot_v2_rate_limit_dev19_upgraded')) {
                if (absint($settings['rate_limit_messages']) < 120) {
                    $settings['rate_limit_messages'] = 120;
                }
                if (absint($settings['rate_limit_window_minutes']) > 5) {
                    $settings['rate_limit_window_minutes'] = 5;
                }
                update_option('geekybot_v2_rate_limit_dev19_upgraded', 1, false);
            }

            update_option(Settings::OPTION, $settings, false);
            if (class_exists('GeekyBot\Services\KnowledgeIndexService')) {
                KnowledgeIndexService::schedule_sync();
            }
            update_option('geekybot_v2_version', GEEKYBOT_VERSION, false);
        }

        return true;
    }

    public static function deactivate() {
        // Keep data by default. Store owners should not lose chat history/settings on deactivate.
        if (class_exists('GeekyBot\\Services\\LicenseService')) {
            LicenseService::clear_scheduled_license_check();
        }
    }

    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sessions = $wpdb->prefix . 'geekybot_sessions';
        $messages = $wpdb->prefix . 'geekybot_messages';
        $unanswered = $wpdb->prefix . 'geekybot_unanswered';

        $sql_sessions = "CREATE TABLE {$sessions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_key varchar(64) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            customer_email varchar(190) NOT NULL DEFAULT '',
            ip_hash varchar(64) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            ended_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_key (session_key),
            KEY user_id (user_id),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $sql_messages = "CREATE TABLE {$messages} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL DEFAULT 0,
            direction varchar(12) NOT NULL,
            message longtext NOT NULL,
            payload longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $sql_unanswered = "CREATE TABLE {$unanswered} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL DEFAULT 0,
            question text NOT NULL,
            reason varchar(80) NOT NULL DEFAULT '',
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY reason (reason),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql_sessions);
        dbDelta($sql_messages);
        dbDelta($sql_unanswered);
        $context_ready = self::ensure_unanswered_context_column();

        ProductIndexService::create_table();
        KnowledgeIndexService::create_table();
        AnalyticsEventService::create_table();

        if (!$context_ready) {
            return false;
        }

        $required = array(
            $sessions,
            $messages,
            $unanswered,
            $wpdb->prefix . 'geekybot_product_index',
            $wpdb->prefix . 'geekybot_knowledge_index',
            $wpdb->prefix . 'geekybot_events',
        );

        foreach ($required as $table) {
            if (!self::table_exists($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Confirm that a plugin-owned table exists after dbDelta runs.
     *
     * @param string $table Full table name built from the WordPress prefix.
     * @return bool
     */
    private static function table_exists($table) {
        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like((string) $table))
        );

        return (string) $found === (string) $table;
    }

    /**
     * Confirm that the unanswered table can store structured review context.
     *
     * dbDelta normally adds the column. The explicit guarded migration covers
     * existing installations where dbDelta did not apply the development
     * schema change during an interrupted or partial upgrade.
     *
     * @return bool
     */
    public static function unanswered_context_column_exists() {
        $column = self::unanswered_context_column_info();

        return !empty($column) && isset($column['Field']) && $column['Field'] === 'context';
    }

    /**
     * Confirm that the structured context column can safely hold JSON data.
     *
     * @return bool
     */
    public static function unanswered_context_column_is_usable() {
        $column = self::unanswered_context_column_info();
        if (empty($column['Type'])) {
            return false;
        }

        $type = strtolower((string) $column['Type']);
        if (preg_match('/^(text|mediumtext|longtext|json)(\b|$)/', $type)) {
            return true;
        }

        if (preg_match('/^varchar\((\d+)\)/', $type, $matches)) {
            return absint($matches[1]) >= 1024;
        }

        return false;
    }

    /**
     * Return the current unanswered-context column definition.
     *
     * @return array
     */
    public static function unanswered_context_column_info() {
        global $wpdb;

        $table = self::safe_table_name($wpdb->prefix . 'geekybot_unanswered');
        if ($table === '') {
            return array();
        }

        $column = $wpdb->get_row(
            $wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'context'),
            ARRAY_A
        );

        return is_array($column) ? $column : array();
    }

    /**
     * Add or repair the structured unanswered-context column on older or
     * partially upgraded installations.
     *
     * @return bool
     */
    private static function ensure_unanswered_context_column() {
        global $wpdb;

        $table = self::safe_table_name($wpdb->prefix . 'geekybot_unanswered');
        if ($table === '') {
            return false;
        }

        if (!self::unanswered_context_column_exists()) {
            $wpdb->query(
                "ALTER TABLE `{$table}` ADD COLUMN `context` longtext NULL AFTER `reason`"
            );
        } elseif (!self::unanswered_context_column_is_usable()) {
            $wpdb->query(
                "ALTER TABLE `{$table}` MODIFY COLUMN `context` longtext NULL"
            );
        }

        // A concurrent upgrader may have changed the schema after the first
        // check. Verify the final shape rather than trusting one ALTER result.
        return self::unanswered_context_column_is_usable();
    }

    /**
     * @param string $table Database table name generated from the WP prefix.
     * @return string
     */
    private static function safe_table_name($table) {
        $table = (string) $table;
        return preg_match('/^[A-Za-z0-9_]+$/', $table) ? $table : '';
    }

    public static function maybe_cleanup_history() {
        if (Settings::get('chat_history_enabled', 'yes') !== 'yes') {
            return;
        }

        $last = get_option('geekybot_last_cleanup', 0);
        if ($last && (time() - absint($last)) < DAY_IN_SECONDS) {
            return;
        }

        global $wpdb;
        $days = max(1, absint(Settings::get('retention_days', 30)));
        $sessions = $wpdb->prefix . 'geekybot_sessions';
        $messages = $wpdb->prefix . 'geekybot_messages';
        $unanswered = $wpdb->prefix . 'geekybot_unanswered';
        $events = $wpdb->prefix . AnalyticsEventService::TABLE_SUFFIX;
        $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));

        $old_session_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$sessions} WHERE updated_at < %s", $cutoff));
        if (!empty($old_session_ids)) {
            $old_session_ids = array_map('absint', $old_session_ids);
            $placeholders = implode(',', array_fill(0, count($old_session_ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$messages} WHERE session_id IN ({$placeholders})", $old_session_ids));
            $wpdb->query($wpdb->prepare("DELETE FROM {$unanswered} WHERE session_id IN ({$placeholders})", $old_session_ids));
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events)) === $events) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$events} WHERE session_id IN ({$placeholders})", $old_session_ids));
            }
            $wpdb->query($wpdb->prepare("DELETE FROM {$sessions} WHERE id IN ({$placeholders})", $old_session_ids));
        }

        $wpdb->query($wpdb->prepare("DELETE FROM {$unanswered} WHERE created_at < %s", $cutoff));
        update_option('geekybot_last_cleanup', time(), false);
    }
}
