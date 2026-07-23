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
