<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Runs Geeky Bot database migrations independently from the plugin version.
 *
 * Each migration must be idempotent and must verify its final schema before
 * the stored database version is advanced.
 */
class DatabaseMigrator {
    const VERSION_OPTION = 'geekybot_db_version';
    const LOCK_OPTION = 'geekybot_db_migration_lock';
    const LOCK_TTL = 300;

    /**
     * Run any missing database migrations.
     *
     * @return bool True when the database is current or another request is
     *              already performing the same migration safely.
     */
    public static function maybe_migrate() {
        $installed = (string) get_option(self::VERSION_OPTION, '0');
        if (version_compare($installed, GEEKYBOT_DB_VERSION, '>=')) {
            return true;
        }

        return self::migrate();
    }

    /**
     * Run migrations in version order.
     *
     * @return bool
     */
    public static function migrate() {
        if (!self::acquire_lock()) {
            $installed = (string) get_option(self::VERSION_OPTION, '0');
            return version_compare($installed, GEEKYBOT_DB_VERSION, '>=');
        }

        try {
            $installed = (string) get_option(self::VERSION_OPTION, '0');
            $migrations = self::migrations();

            foreach ($migrations as $version => $callback) {
                if (version_compare($installed, $version, '>=')) {
                    continue;
                }

                $success = (bool) call_user_func($callback);
                if (!$success) {
                    /**
                     * Fires when a Geeky Bot database migration cannot verify
                     * its final schema.
                     *
                     * @param string $version Migration version that failed.
                     */
                    do_action('geekybot_database_migration_failed', $version);
                    return false;
                }

                update_option(self::VERSION_OPTION, $version, false);
                $installed = $version;

                /**
                 * Fires after a Geeky Bot database migration succeeds.
                 *
                 * @param string $version Completed migration version.
                 */
                do_action('geekybot_database_migrated', $version);
            }

            return version_compare($installed, GEEKYBOT_DB_VERSION, '>=');
        } finally {
            self::release_lock();
        }
    }

    /**
     * @return array<string, callable>
     */
    private static function migrations() {
        return array(
            '2.0.0' => array(__CLASS__, 'migrate_200_base_schema'),
            '2.0.2' => array(__CLASS__, 'migrate_202_product_index_fulltext'),
            '2.0.3' => array(__CLASS__, 'migrate_203_product_index_stem_text'),
        );
    }

    /**
     * Ensure all existing Core tables are present for fresh and upgraded sites.
     *
     * @return bool
     */
    public static function migrate_200_base_schema() {
        return Installer::create_tables();
    }

    /**
     * Rebuild the product index FULLTEXT key when its column list is stale.
     *
     * `dbDelta()` creates a FULLTEXT key on a new table but will not alter one
     * that already exists. Sites that installed Geeky Bot before `color_terms`
     * and `size_terms` joined `gb_fulltext` therefore kept the original
     * six-column key, while `ProductIndexService::candidate_rows()` matches
     * against all eight columns. MySQL answers that mismatch with error 1191,
     * "Can't find FULLTEXT index matching the column list", and because the
     * result is read with `get_results()` and only checked for emptiness, the
     * failure is silent: every product search quietly degrades to the LIKE
     * fallback with no relevance score at all. Fresh installs build the key
     * correctly, so the fault only ever appeared on upgraded stores.
     *
     * @return bool
     */
    public static function migrate_202_product_index_fulltext() {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_product_index';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table, name built from the WordPress prefix.
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            // Nothing to repair yet; Installer::create_tables() builds it correctly.
            return true;
        }

        // Frozen on purpose. A migration records what the schema looked like at
        // one point in time, so this list must not follow later releases: a site
        // already past 2.0.2 never runs it again, and quietly widening it here
        // would mean the newer columns were never actually indexed anywhere.
        return self::ensure_fulltext_key(
            array('title', 'sku', 'categories', 'tags', 'attributes', 'color_terms', 'size_terms', 'search_text')
        );
    }

    /**
     * Add `stem_text` to the product index and put it in the search key.
     *
     * Shopper queries were stemmed while the index kept raw catalog wording, so
     * the two sides disagreed on any word whose singular and plural differ by
     * more than an `s`: "beanies" reduced to `beany` and never met the indexed
     * "Beanie". Storing a stemmed copy of each product's text lets both halves be
     * reduced by the same function, which is what makes the match possible.
     *
     * @return bool
     */
    public static function migrate_203_product_index_stem_text() {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_product_index';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return true;
        }

        if (!self::ensure_fulltext_key(HealthService::expected_fulltext_columns())) {
            return false;
        }

        // Existing rows have an empty stem_text until they are rebuilt. Queue it
        // rather than reindexing the whole catalog inside a migration request.
        (new ProductIndexService())->schedule_rebuild();

        return true;
    }

    /**
     * Make `gb_fulltext` cover exactly the given columns.
     *
     * @param array $expected Column names, in index order.
     * @return bool
     */
    private static function ensure_fulltext_key($expected) {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_product_index';

        // Every column has to exist before it can be indexed. An older table
        // may predate the facet columns entirely.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        if (!is_array($columns)) {
            return false;
        }

        $missing = array_diff($expected, $columns);
        if (!empty($missing)) {
            // dbDelta adds the column; the key is rebuilt on the pass below.
            Installer::create_tables();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
            if (!is_array($columns) || array_diff($expected, $columns)) {
                return false;
            }
        }

        if (self::fulltext_columns($table) === $expected) {
            return true;
        }

        // Rebuilding is one statement so the key is never missing between the
        // drop and the create.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL on an own plugin table with an internally built column list.
        $wpdb->query(
            "ALTER TABLE {$table} DROP INDEX gb_fulltext, ADD FULLTEXT KEY gb_fulltext (" . implode(', ', $expected) . ')'
        );

        if (self::fulltext_columns($table) === $expected) {
            return true;
        }

        // A site whose key was already missing cannot be repaired by a DROP.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL on an own plugin table.
        $wpdb->query("ALTER TABLE {$table} ADD FULLTEXT KEY gb_fulltext (" . implode(', ', $expected) . ')');

        return self::fulltext_columns($table) === $expected;
    }

    /**
     * Column list backing the product index FULLTEXT key, in index order.
     *
     * @param string $table Fully prefixed table name.
     * @return array<int, string>
     */
    private static function fulltext_columns($table) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table; reading index metadata.
        $rows = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'gb_fulltext'");
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        usort($rows, function ($a, $b) {
            return (int) $a->Seq_in_index <=> (int) $b->Seq_in_index;
        });

        $columns = array();
        foreach ($rows as $row) {
            $columns[] = (string) $row->Column_name;
        }

        return $columns;
    }

    /**
     * Prevent two requests from running DDL at the same time.
     *
     * @return bool
     */
    private static function acquire_lock() {
        $now = time();
        if (add_option(self::LOCK_OPTION, $now, '', 'no')) {
            return true;
        }

        $locked_at = absint(get_option(self::LOCK_OPTION, 0));
        if ($locked_at > 0 && ($now - $locked_at) < self::LOCK_TTL) {
            return false;
        }

        delete_option(self::LOCK_OPTION);
        return add_option(self::LOCK_OPTION, $now, '', 'no');
    }

    private static function release_lock() {
        delete_option(self::LOCK_OPTION);
    }

}
