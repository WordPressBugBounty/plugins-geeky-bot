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
     * Caches "the tables are all there" so the check costs one option read.
     *
     * Short enough that a site which loses a table repairs itself within the
     * hour, long enough that the SHOW TABLES sweep is not on every request.
     */
    const SCHEMA_OK_TRANSIENT = 'geekybot_schema_verified';
    const SCHEMA_OK_TTL = 3600;

    /**
     * Run any missing database migrations.
     *
     * @return bool True when the database is current or another request is
     *              already performing the same migration safely.
     */
    public static function maybe_migrate() {
        $installed = (string) get_option(self::VERSION_OPTION, '0');
        if (version_compare($installed, GEEKYBOT_DB_VERSION, '>=')) {
            return self::ensure_schema_present();
        }

        return self::migrate();
    }

    /**
     * Rebuild tables that have gone missing although the version says current.
     *
     * The stored version was the only gate on table creation, so a database
     * where the option row survived and the tables did not could never repair
     * itself: every request read "2.1.0", skipped the migrations, and every
     * query then failed against a table that was not there. It is not a
     * theoretical state -- a site restored from a backup that dumped only the
     * core tables, or cloned by copying wp_options, lands in exactly it, and
     * the only symptom is "Table 'wp_geekybot_knowledge_index' doesn't exist"
     * repeating in the log while the admin screens look installed.
     *
     * Creating them is safe to repeat: dbDelta is idempotent, and a table
     * created now is created at the current schema, which is what the later
     * migrations exist to bring an OLD table up to.
     *
     * @return bool
     */
    private static function ensure_schema_present() {
        if (get_transient(self::SCHEMA_OK_TRANSIENT)) {
            return true;
        }

        $missing = Installer::missing_tables();
        if (empty($missing)) {
            set_transient(self::SCHEMA_OK_TRANSIENT, 1, self::SCHEMA_OK_TTL);
            return true;
        }

        // Without the lock two concurrent requests would both rebuild, and the
        // loser would schedule a second full product index rebuild.
        if (!self::acquire_lock()) {
            return true;
        }

        try {
            $created = Installer::create_tables();
            if (!$created) {
                /**
                 * Fires when tables are missing and could not be recreated.
                 *
                 * @param string[] $missing Tables still absent.
                 */
                do_action('geekybot_database_repair_failed', Installer::missing_tables());
                return false;
            }

            set_transient(self::SCHEMA_OK_TRANSIENT, 1, self::SCHEMA_OK_TTL);

            // The tables came back empty, so the content that lived in them has
            // to be rebuilt or the store answers every shopper with nothing.
            if (class_exists('GeekyBot\\Services\\ProductIndexService')) {
                ProductIndexService::request_rebuild(30);
            }
            if (class_exists('GeekyBot\\Services\\KnowledgeIndexService')) {
                KnowledgeIndexService::schedule_sync();
            }

            /**
             * Fires after missing tables have been recreated.
             *
             * @param string[] $missing Tables that had been absent.
             */
            do_action('geekybot_database_repaired', $missing);

            return true;
        } finally {
            self::release_lock();
        }
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
            '2.0.4' => array(__CLASS__, 'migrate_204_encrypt_provider_secrets'),
            '2.1.0' => array(__CLASS__, 'migrate_210_product_index_facet_text'),
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
     * Add `facet_text` to the product index.
     *
     * The facet and core-term WHERE clauses compared a normalized shopper term
     * against `title`, `categories`, `tags`, `attributes`, `color_terms` and
     * `size_terms`, all of which store the merchant's raw wording. Latin scripts
     * never noticed -- utf8mb4 collation already folds case and accents -- but
     * `normalize_text()` rewrites the Arabic letter family, so a query for أسود
     * became اسود and could not match a column still holding أسود. Colour and
     * size filters therefore matched nothing at all in Arabic, Persian and Urdu.
     *
     * `facet_text` holds those same identity fields in normalized form, which is
     * the form the query side has always been in.
     *
     * @return bool
     */
    public static function migrate_210_product_index_facet_text() {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_product_index';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            // Nothing to alter yet; ProductIndexService::create_table() builds
            // the column into a fresh table.
            return true;
        }

        ProductIndexService::create_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'facet_text'));
        if ($column !== 'facet_text') {
            return false;
        }

        // Existing rows carry an empty facet_text until they are rebuilt, and an
        // empty one matches nothing. Queue the rebuild rather than reindexing the
        // whole catalog inside a migration request.
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
    /**
     * Encrypt provider API keys that were stored in plaintext before 2.0.3.
     *
     * Sites upgrading with a live key keep working: Settings::secret() reads a
     * legacy plaintext value, and this pass rewrites it as vault ciphertext so
     * the value in wp_options stops being usable on its own.
     *
     * @return bool
     */
    public static function migrate_204_encrypt_provider_secrets() {
        $settings = get_option(Settings::OPTION, array());
        if (!is_array($settings)) {
            return true;
        }

        $changed = false;
        foreach (Settings::secret_keys() as $key) {
            $value = isset($settings[$key]) ? (string) $settings[$key] : '';
            if ($value === '' || LicenseVault::is_encrypted($value)) {
                continue;
            }

            if (!LicenseVault::available()) {
                // Nothing on this installation can encrypt. Leaving the key in
                // place keeps the store answering; the admin reports the state.
                continue;
            }

            $encrypted = LicenseVault::encrypt($value);
            if (is_string($encrypted) && $encrypted !== '' && LicenseVault::decrypt($encrypted) === $value) {
                $settings[$key] = $encrypted;
                $changed = true;
            }
        }

        if ($changed) {
            update_option(Settings::OPTION, $settings, false);
        }

        return true;
    }

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
