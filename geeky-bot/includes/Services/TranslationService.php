<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;

/**
 * Translations, delivered from geekybot.com. (Geeky Bot 2.1.2)
 *
 * The plugin no longer carries its languages: nineteen of them were most of
 * the download, every site paid for all of them to use one, and the list can
 * only grow. Instead this installs the one a site actually uses, the way
 * WordPress installs its own: into wp-content/languages/plugins/, where
 * WordPress looks first and where a plugin update cannot delete it. The same
 * design as JS Help Desk 5.
 *
 * Nobody has to click anything. It runs when Geeky Bot is installed or
 * updated, when the site language changes, when WordPress adds a language (an
 * admin choosing one for their profile), and once a day for improved
 * translations. One file per language covers Geeky Bot and Commerce Pro: they
 * share the geeky-bot text domain.
 *
 * What it downloads is data: a .mo (what WordPress reads) and the .po it was
 * made from (what a person edits). Never .l10n.php - that is PHP, and a
 * plugin may not fetch code from its own server. Every package is checked
 * against the SHA-256 the list gives for it before anything is written.
 *
 * Nothing about the site is sent: a plain GET for a static list and a static
 * file, with a user agent that does not name the site.
 */
class TranslationService {
    const DOMAIN = 'geeky-bot';
    const SOURCE = 'https://geekybot.com/translations/v2/index.json';
    const OPT_STATE = 'geekybot_translations';
    const T_MANIFEST = 'geekybot_translations_manifest';
    const T_LOCK = 'geekybot_translations_lock';
    const CRON = 'geekybot_translations_daily';
    const CRON_SOON = 'geekybot_translations_soon';
    const RETRY_AFTER = 21600; // six hours between automatic retries of a failed language
    const MAX_FILE = 15728640; // 15 MB; a complete translation is about 0.5 MB
    const PAGE = 'geekybot-languages';

    public static function hooks() {
        add_action(self::CRON, array(__CLASS__, 'cron'));
        add_action(self::CRON_SOON, array(__CLASS__, 'cron'));
        add_action('admin_init', array(__CLASS__, 'maybe_sync'));
        // The site language changed, or WordPress just installed something -
        // possibly a new language, possibly a new version of this plugin.
        // Forgetting the signature makes the next admin page check again.
        add_action('update_option_WPLANG', array(__CLASS__, 'forget'));
        add_action('add_option_WPLANG', array(__CLASS__, 'forget'));
        add_action('upgrader_process_complete', array(__CLASS__, 'after_upgrade'), 30, 2);
        add_action('admin_notices', array(__CLASS__, 'notice'));
        add_action('admin_post_geekybot_translations', array(__CLASS__, 'handle_post'));
        add_action('init', array(__CLASS__, 'ensure_scheduled'));
    }

    /**
     * On every request, one autoloaded option read: when this version has not
     * checked yet, or the language changed, check within a minute.
     *
     * Needed because the update from 2.1.1 - which still carried its
     * languages - runs inside the old code, so after_upgrade() never fires for
     * it, and a store whose admin is rarely opened would show shoppers English
     * until someone visited wp-admin.
     */
    public static function ensure_scheduled() {
        $state = get_option(self::OPT_STATE, array());
        $done = is_array($state) && !empty($state['sig'])
            && isset($state['ver']) && $state['ver'] === (defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '');
        if (!$done && !wp_next_scheduled(self::CRON_SOON)) {
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::CRON_SOON);
        }
        if ($done && !wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON);
        }
    }

    /**
     * Whether to install translations without being asked. On unless a site
     * says otherwise: define('GEEKYBOT_TRANSLATIONS_AUTO', false) in
     * wp-config.php, or the geekybot_translations_auto filter. The Languages
     * screen still works either way.
     */
    public static function automatic() {
        $on = !defined('GEEKYBOT_TRANSLATIONS_AUTO') || GEEKYBOT_TRANSLATIONS_AUTO;
        return (bool) apply_filters('geekybot_translations_auto', $on);
    }

    /** Whether this site lets plugins write language files at all. */
    public static function can_write() {
        return !function_exists('wp_is_file_mod_allowed') || wp_is_file_mod_allowed('download_language_pack');
    }

    /** Where the list of translations lives. A constant for testing against another server. */
    public static function source_url() {
        $url = defined('GEEKYBOT_TRANSLATIONS_URL') ? GEEKYBOT_TRANSLATIONS_URL : self::SOURCE;
        return (string) apply_filters('geekybot_translations_source', $url);
    }

    /* ------------------------------------------------------------------
     * State
     * ---------------------------------------------------------------- */

    public static function state() {
        $state = get_option(self::OPT_STATE, array());
        return wp_parse_args(is_array($state) ? $state : array(), array(
            'sig' => '',
            'ver' => '', // the Geeky Bot version that last checked
            'checked' => 0,
            'installed' => array(), // locale => array(version, from, at)
            'failed' => array(), // locale => array(at, error)
        ));
    }

    private static function save($state) {
        update_option(self::OPT_STATE, $state, true);
    }

    /** Make the next admin page check again. Hooked; takes whatever the hook passes. */
    public static function forget() {
        $state = self::state();
        $state['sig'] = '';
        self::save($state);
    }

    /**
     * After WordPress installed or updated something: check again, and soon,
     * not only on the next admin page - a store whose admin is rarely opened
     * would otherwise show shoppers English after updating from 2.1.1, which
     * still carried its languages.
     */
    public static function after_upgrade($upgrader = null, $extra = array()) {
        self::forget();
        if (!wp_next_scheduled(self::CRON_SOON)) {
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::CRON_SOON);
        }
    }

    /**
     * The languages this site needs: its own, and every language WordPress has
     * installed, because an admin can choose any of those for their profile.
     * English needs nothing.
     */
    public static function wanted_locales() {
        $locales = array(get_locale());
        if (function_exists('get_available_languages')) {
            $locales = array_merge($locales, get_available_languages());
        }
        $locales = array_values(array_unique(array_filter($locales, function ($locale) {
            return is_string($locale) && $locale !== '' && $locale !== 'en_US' && self::valid_locale($locale);
        })));
        return array_slice($locales, 0, 20);
    }

    private static function valid_locale($locale) {
        return (bool) preg_match('/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', (string) $locale);
    }

    private static function signature() {
        return md5(implode(',', self::wanted_locales()) . '|' . (defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '') . '|' . self::source_url());
    }

    /* ------------------------------------------------------------------
     * When to look
     * ---------------------------------------------------------------- */

    /**
     * On an admin page: check when something changed since the last look, or
     * when a failed language is due another try. Usually nothing to do - one
     * option read.
     */
    public static function maybe_sync() {
        if (wp_doing_ajax() || !current_user_can('install_languages')) {
            return;
        }
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
        }
        $state = self::state();
        $due = $state['sig'] !== self::signature();
        foreach ($state['failed'] as $failed) {
            if ((int) $failed['at'] < time() - self::RETRY_AFTER) {
                $due = true;
            }
        }
        if ($due) {
            self::sync(false);
        }
    }

    public static function cron() {
        self::sync(false, true);
    }

    /**
     * Bring every language this site needs up to date.
     *
     * @param bool $force   Asked for by a person: ask the server again, retry failures now.
     * @param bool $refresh Ask the server again even if the list is cached.
     * @return array The new state.
     */
    public static function sync($force = false, $refresh = false) {
        $state = self::state();
        $state['sig'] = self::signature();
        $state['ver'] = defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '';
        if ((!$force && !self::automatic()) || !self::can_write()) {
            self::save($state);
            return $state;
        }
        $wanted = self::wanted_locales();
        if (empty($wanted)) {
            $state['failed'] = array();
            self::save($state);
            return $state;
        }
        if (!$force && get_transient(self::T_LOCK)) {
            return $state;
        }
        set_transient(self::T_LOCK, 1, 120);

        $manifest = self::manifest($force || $refresh);
        $state['checked'] = time();
        foreach ($wanted as $locale) {
            if (is_wp_error($manifest)) {
                $state['failed'][$locale] = array('at' => time(), 'error' => $manifest->get_error_message());
                continue;
            }
            $entry = self::entry_for($locale, $manifest);
            if (!$entry || self::is_current($locale, $entry, $state)) {
                unset($state['failed'][$locale]);
                continue;
            }
            if (!$force && isset($state['failed'][$locale])
                && (int) $state['failed'][$locale]['at'] > time() - self::RETRY_AFTER) {
                continue;
            }
            $done = self::install($locale, $entry, $state);
            if (is_wp_error($done)) {
                $state['failed'][$locale] = array('at' => time(), 'error' => $done->get_error_message());
            } else {
                unset($state['failed'][$locale]);
            }
        }
        // A language the site no longer uses is no longer a failure worth showing.
        $state['failed'] = array_intersect_key($state['failed'], array_flip($wanted));
        self::save($state);
        delete_transient(self::T_LOCK);
        return $state;
    }

    /* ------------------------------------------------------------------
     * The list on geekybot.com
     * ---------------------------------------------------------------- */

    /** Plain requests that do not name the site; a local test server is allowed. */
    private static function http_args($timeout, $url) {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $local = in_array($host, array('localhost', '127.0.0.1', '::1'), true) || (bool) preg_match('/\.(test|local|localhost)$/', $host);
        return array(
            'timeout' => $timeout,
            'redirection' => 2,
            'user-agent' => 'Geeky Bot/' . (defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : ''),
            'reject_unsafe_urls' => !$local,
            'limit_response_size' => self::MAX_FILE,
        );
    }

    /**
     * The list of translations, cached for twelve hours.
     *
     * @return array|WP_Error locale => entry
     */
    public static function manifest($refresh = false) {
        if (!$refresh) {
            $cached = get_site_transient(self::T_MANIFEST);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $url = self::source_url();
        $response = wp_remote_get($url, self::http_args(15, $url));
        if (is_wp_error($response)) {
            return new WP_Error('geekybot_tr_unreachable', sprintf(
                /* translators: %s: the reason the connection failed */
                __('geekybot.com could not be reached (%s).', 'geeky-bot'),
                $response->get_error_message()
            ));
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($data) || !isset($data['translations']) || !is_array($data['translations'])) {
            return new WP_Error('geekybot_tr_badlist', sprintf(
                /* translators: %d: an HTTP status code */
                __('The list of translations could not be read (HTTP %d).', 'geeky-bot'),
                $code
            ));
        }
        $out = array();
        foreach ($data['translations'] as $entry) {
            $entry = self::clean_entry($entry, $url);
            if ($entry) {
                $out[$entry['locale']] = $entry;
            }
        }
        set_site_transient(self::T_MANIFEST, $out, 12 * HOUR_IN_SECONDS);
        return $out;
    }

    /** One entry of the list, checked; links resolved against the list's own address. */
    private static function clean_entry($entry, $base) {
        if (!is_array($entry) || empty($entry['locale']) || !self::valid_locale($entry['locale'])
            || empty($entry['version']) || empty($entry['package'])
            || empty($entry['sha256']) || !preg_match('/^[a-f0-9]{64}$/', (string) $entry['sha256'])) {
            return null;
        }
        $clean = array(
            'locale' => (string) $entry['locale'],
            'name' => isset($entry['name']) ? sanitize_text_field($entry['name']) : (string) $entry['locale'],
            'native' => isset($entry['native']) ? sanitize_text_field($entry['native']) : '',
            'version' => sanitize_text_field($entry['version']),
            'percent' => isset($entry['percent']) ? max(0, min(100, (int) $entry['percent'])) : 0,
            'sha256' => (string) $entry['sha256'],
        );
        foreach (array('package', 'po', 'mo') as $key) {
            $clean[$key] = isset($entry[$key]) ? self::resolve_url((string) $entry[$key], $base) : '';
        }
        return $clean['package'] === '' ? null : $clean;
    }

    private static function resolve_url($url, $base) {
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = trailingslashit(dirname($base)) . ltrim($url, '/');
        }
        // Files come only from where the list came from: same host, and https
        // unless the list itself was fetched over http (a local test server).
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        $base_scheme = strtolower((string) wp_parse_url($base, PHP_URL_SCHEME));
        if ($scheme !== 'https' && !($scheme === 'http' && $base_scheme === 'http')) {
            return '';
        }
        if (strtolower((string) wp_parse_url($url, PHP_URL_HOST)) !== strtolower((string) wp_parse_url($base, PHP_URL_HOST))) {
            return '';
        }
        return esc_url_raw($url);
    }

    /**
     * The translation for a locale: its own, or the nearest one. Swiss German
     * gets German, Mexican Spanish gets Spanish, Portugal gets Brazilian
     * Portuguese - closer than English. Not Chinese: Traditional readers are
     * not served by Simplified.
     */
    public static function entry_for($locale, $manifest) {
        if (!is_array($manifest)) {
            return null;
        }
        if (isset($manifest[$locale])) {
            return $manifest[$locale];
        }
        $lang = strtok($locale, '_');
        if ($lang === 'zh') {
            return null;
        }
        foreach ($manifest as $entry) {
            if (strtok($entry['locale'], '_') === $lang) {
                return $entry;
            }
        }
        return null;
    }

    private static function is_current($locale, $entry, $state) {
        $have = isset($state['installed'][$locale]) ? $state['installed'][$locale] : null;
        return $have
            && $have['version'] === $entry['version']
            && $have['from'] === $entry['locale']
            && file_exists(self::file($locale, 'mo'));
    }

    public static function file($locale, $ext) {
        return WP_LANG_DIR . '/plugins/' . self::DOMAIN . '-' . $locale . '.' . $ext;
    }

    /* ------------------------------------------------------------------
     * Installing
     * ---------------------------------------------------------------- */

    /**
     * Download, check and install one language.
     *
     * @param string $locale The locale it is installed as.
     * @param array  $entry  The list's entry (maybe a nearby locale's).
     * @param array  $state  Updated in place.
     * @return true|WP_Error
     */
    public static function install($locale, $entry, &$state) {
        if (!self::can_write()) {
            return new WP_Error('geekybot_tr_nowrite', __('This site does not allow plugins to install language files (DISALLOW_FILE_MODS).', 'geeky-bot'));
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp = wp_tempnam('geeky-bot-' . $entry['locale'] . '.zip');
        if (!$tmp) {
            return new WP_Error('geekybot_tr_download', __('The download failed (no temporary file).', 'geeky-bot'));
        }
        $args = self::http_args(60, $entry['package']);
        $args['stream'] = true;
        $args['filename'] = $tmp;
        $response = wp_remote_get($entry['package'], $args);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            wp_delete_file($tmp);
            return new WP_Error('geekybot_tr_download', sprintf(
                /* translators: %s: the reason the download failed */
                __('The download failed (%s).', 'geeky-bot'),
                is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . (int) wp_remote_retrieve_response_code($response)
            ));
        }
        if (!hash_equals($entry['sha256'], (string) hash_file('sha256', $tmp))) {
            wp_delete_file($tmp);
            return new WP_Error('geekybot_tr_checksum', __('The downloaded file did not match its checksum, so it was not installed.', 'geeky-bot'));
        }
        $base = self::DOMAIN . '-' . $entry['locale'];
        $files = self::read_zip($tmp, array($base . '.mo', $base . '.po'));
        wp_delete_file($tmp);
        if (is_wp_error($files)) {
            return $files;
        }
        $mo = $files[$base . '.mo'];
        $magic = substr($mo, 0, 4);
        if ($magic !== "\xde\x12\x04\x95" && $magic !== "\x95\x04\x12\xde") {
            return new WP_Error('geekybot_tr_badfile', __('The downloaded translation is not a valid .mo file.', 'geeky-bot'));
        }

        global $wp_filesystem;
        if (!WP_Filesystem() || !$wp_filesystem) {
            return new WP_Error('geekybot_tr_fs', __('WordPress could not open its files for writing.', 'geeky-bot'));
        }
        $dir = WP_LANG_DIR . '/plugins';
        if (!$wp_filesystem->is_dir($dir) && !wp_mkdir_p($dir)) {
            return new WP_Error('geekybot_tr_dir', __('The folder wp-content/languages/plugins could not be created.', 'geeky-bot'));
        }
        // A file this did not put there is someone's: an older download, or
        // wording an admin changed by hand. Kept beside it rather than lost.
        if (!isset($state['installed'][$locale])) {
            foreach (array('po', 'mo') as $ext) {
                if (file_exists(self::file($locale, $ext))) {
                    $wp_filesystem->move(self::file($locale, $ext), self::file($locale, $ext) . '.bak-' . gmdate('Ymd'), true);
                }
            }
        }
        $po = isset($files[$base . '.po']) ? $files[$base . '.po'] : '';
        if ($po !== '' && !$wp_filesystem->put_contents(self::file($locale, 'po'), $po, FS_CHMOD_FILE)) {
            return new WP_Error('geekybot_tr_write', __('The folder wp-content/languages/plugins is not writable.', 'geeky-bot'));
        }
        if (!$wp_filesystem->put_contents(self::file($locale, 'mo'), $mo, FS_CHMOD_FILE)) {
            return new WP_Error('geekybot_tr_write', __('The folder wp-content/languages/plugins is not writable.', 'geeky-bot'));
        }
        // WordPress prefers a .l10n.php to a .mo of the same name, so an older
        // one would hide what was just installed.
        if (file_exists(self::file($locale, 'l10n.php'))) {
            $wp_filesystem->delete(self::file($locale, 'l10n.php'));
        }
        // The same cache WordPress clears after installing a language pack.
        wp_cache_delete(md5(WP_LANG_DIR . '/plugins/'), 'translation_files');

        $state['installed'][$locale] = array(
            'version' => $entry['version'],
            'from' => $entry['locale'],
            'at' => time(),
        );
        return true;
    }

    /**
     * The named files from a zip, as strings.
     *
     * @return array|WP_Error name => contents; the .mo is required.
     */
    private static function read_zip($zip, $names) {
        $out = array();
        if (class_exists('ZipArchive')) {
            $archive = new \ZipArchive();
            if ($archive->open($zip) !== true) {
                return new WP_Error('geekybot_tr_zip', __('The downloaded translation could not be opened.', 'geeky-bot'));
            }
            foreach ($names as $name) {
                $stat = $archive->statName($name);
                if ($stat && $stat['size'] <= self::MAX_FILE) {
                    $out[$name] = (string) $archive->getFromName($name);
                }
            }
            $archive->close();
        } else {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            $archive = new \PclZip($zip);
            $list = $archive->extract(PCLZIP_OPT_BY_NAME, $names, PCLZIP_OPT_EXTRACT_AS_STRING);
            if (!is_array($list)) {
                return new WP_Error('geekybot_tr_zip', __('The downloaded translation could not be opened.', 'geeky-bot'));
            }
            foreach ($list as $file) {
                if (isset($file['stored_filename'], $file['content']) && strlen($file['content']) <= self::MAX_FILE) {
                    $out[$file['stored_filename']] = (string) $file['content'];
                }
            }
        }
        if (empty($out[$names[0]])) {
            return new WP_Error('geekybot_tr_zip', __('The downloaded translation could not be opened.', 'geeky-bot'));
        }
        return $out;
    }

    /* ------------------------------------------------------------------
     * What the screen shows
     * ---------------------------------------------------------------- */

    /**
     * One row per language: this site's languages first, then every other
     * translation on offer.
     */
    public static function rows() {
        $state = self::state();
        $manifest = get_site_transient(self::T_MANIFEST);
        if (!is_array($manifest)) {
            $manifest = self::manifest();
        }
        $list_error = is_wp_error($manifest) ? $manifest->get_error_message() : '';
        $manifest = is_array($manifest) ? $manifest : array();
        $wanted = self::wanted_locales();
        $rows = array();
        foreach (array_unique(array_merge($wanted, array_keys($manifest))) as $locale) {
            $site = in_array($locale, $wanted, true);
            $entry = $site ? self::entry_for($locale, $manifest) : $manifest[$locale];
            $have = isset($state['installed'][$locale]) ? $state['installed'][$locale] : null;
            $present = file_exists(self::file($locale, 'mo'));
            if (!$site) {
                $status = 'offered';
            } elseif (isset($state['failed'][$locale]) && !$present) {
                $status = 'failed';
            } elseif (!$entry) {
                $status = $present ? 'own' : 'none';
            } elseif ($present && $have && self::is_current($locale, $entry, $state)) {
                $status = 'current';
            } elseif ($present) {
                $status = 'update';
            } else {
                $status = 'missing';
            }
            $rows[] = array(
                'locale' => $locale,
                'name' => self::language_name($locale, $entry),
                'site' => $site,
                'entry' => $entry,
                'have' => $have,
                'status' => $status,
                'error' => isset($state['failed'][$locale]) ? $state['failed'][$locale]['error'] : '',
            );
        }
        return array('rows' => $rows, 'list_error' => $list_error, 'checked' => (int) $state['checked']);
    }

    /**
     * A language's name. In a list, "French (France) - Français"; inside a
     * sentence ($own), the language's own name alone, because an English
     * name reads wrongly in the middle of a translated sentence.
     */
    public static function language_name($locale, $entry = null, $own = false) {
        if ($entry && $entry['locale'] === $locale) {
            if ($own && $entry['native'] !== '') {
                return $entry['native'];
            }
            return $entry['native'] && $entry['native'] !== $entry['name']
                ? $entry['name'] . ' - ' . $entry['native'] : $entry['name'];
        }
        $known = get_site_transient('available_translations'); // WordPress's own list, if it has fetched it
        if (is_array($known) && isset($known[$locale])) {
            if ($own && !empty($known[$locale]['native_name'])) {
                return $known[$locale]['native_name'];
            }
            if (!empty($known[$locale]['english_name'])) {
                return $known[$locale]['english_name'];
            }
        }
        return $locale;
    }

    /** Where Install, Update now and Check for updates post to. */
    public static function action_url($do, $locale = '') {
        $args = array('action' => 'geekybot_translations', 'do' => $do);
        if ($locale !== '') {
            $args['locale'] = $locale;
        }
        return wp_nonce_url(add_query_arg($args, admin_url('admin-post.php')), 'geekybot-translations');
    }

    public static function screen_url() {
        return admin_url('admin.php?page=' . self::PAGE);
    }

    /** Install, Update now, Use it for me, Check for updates. */
    public static function handle_post() {
        if (!current_user_can('install_languages')) {
            wp_die(esc_html__('You are not allowed to install languages on this site.', 'geeky-bot'));
        }
        check_admin_referer('geekybot-translations');
        $do = isset($_GET['do']) ? sanitize_key(wp_unslash($_GET['do'])) : 'sync';
        $locale = isset($_GET['locale']) ? sanitize_text_field(wp_unslash($_GET['locale'])) : '';
        $result = 'done';
        $extra = array();
        if ($do === 'install' && self::valid_locale($locale)) {
            $result = is_wp_error(self::install_one($locale, true)) ? 'failed' : 'done';
        } elseif ($do === 'add' && self::valid_locale($locale)) {
            // A language this site does not have yet: WordPress's own language
            // pack first, so the language can be chosen under Settings > General
            // and in a profile, then Geeky Bot's.
            $wp = self::install_wordpress_language($locale);
            $done = self::install_one($locale, true);
            $result = is_wp_error($done) ? 'failed' : ($wp ? 'added' : 'addednowp');
            $extra['gb_trl'] = $locale;
        } elseif ($do === 'useme' && ($locale === 'en_US'
            || (self::valid_locale($locale) && in_array($locale, get_available_languages(), true)))) {
            // Just this admin: the rest of the site keeps its language.
            update_user_meta(get_current_user_id(), 'locale', $locale);
            $result = 'useme';
        } else {
            $state = self::sync(true, true);
            $result = empty($state['failed']) ? 'done' : 'failed';
        }
        wp_safe_redirect(add_query_arg(array_merge(array('gb_tr' => $result), $extra), self::screen_url()));
        exit;
    }

    /**
     * WordPress's own language pack for a locale, from WordPress.org - the same
     * download Settings > General makes when a language is chosen there.
     *
     * @return bool Whether WordPress now has the language.
     */
    public static function install_wordpress_language($locale) {
        if (in_array($locale, get_available_languages(), true)) {
            return true;
        }
        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        if (!wp_can_install_language_pack()) {
            return false;
        }
        return (bool) wp_download_language_pack($locale);
    }

    /**
     * Install one language now, asked for by a person.
     *
     * @return true|WP_Error
     */
    public static function install_one($locale, $refresh = false) {
        $state = self::state();
        $manifest = self::manifest($refresh);
        if (is_wp_error($manifest)) {
            $result = $manifest;
        } else {
            $entry = self::entry_for($locale, $manifest);
            if (!$entry) {
                return new WP_Error('geekybot_tr_none', __('There is no translation for this language yet.', 'geeky-bot'));
            }
            $result = self::install($locale, $entry, $state);
        }
        if (is_wp_error($result)) {
            $state['failed'][$locale] = array('at' => time(), 'error' => $result->get_error_message());
        } else {
            unset($state['failed'][$locale]);
        }
        self::save($state);
        return $result;
    }

    /**
     * The one banner, on Geeky Bot's own screens: Geeky Bot is in English on a
     * site that is not, because its translation could not be installed.
     * Updates that fail stay quiet - the installed translation still works -
     * and show on the Languages screen.
     */
    public static function notice() {
        if (!current_user_can('install_languages')) {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen, nothing acted on
        if (strpos($page, 'geekybot') !== 0 || $page === self::PAGE) {
            return;
        }
        $state = self::state();
        $missing = array();
        foreach ($state['failed'] as $locale => $failed) {
            if (!file_exists(self::file($locale, 'mo'))) {
                $missing[$locale] = $failed;
            }
        }
        if (empty($missing)) {
            return;
        }
        $locale = isset($missing[get_user_locale()]) ? get_user_locale() : key($missing);
        $name = self::language_name($locale, self::entry_for($locale, get_site_transient(self::T_MANIFEST)), true);
        echo '<div class="notice notice-warning"><p><strong>' . esc_html(sprintf(
            /* translators: %s: a language name, e.g. Français */
            __('Geeky Bot could not install its %s translation.', 'geeky-bot'),
            $name
        )) . '</strong> ' . esc_html(sprintf(
            /* translators: %s: the reason, e.g. "The download failed (...)." */
            __('Geeky Bot is shown in English until it is installed. %s', 'geeky-bot'),
            $missing[$locale]['error']
        )) . ' <a href="' . esc_url(self::action_url('sync')) . '">' . esc_html__('Try again', 'geeky-bot') . '</a> | <a href="' . esc_url(self::screen_url()) . '">' . esc_html__('Languages', 'geeky-bot') . '</a></p></div>';
    }
}
