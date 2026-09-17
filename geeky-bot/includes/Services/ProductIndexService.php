<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * This repository intentionally owns a custom product-search index. Its table
 * name is fixed from $wpdb->prefix; all shopper-controlled values are prepared.
 * Index reads/writes bypass object caching so catalog changes are immediately
 * visible and synchronization never overwrites newer rows with stale state.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class ProductIndexService {
    const REBUILD_HOOK = 'geekybot_product_index_scheduled_rebuild';
    const REBUILD_PENDING_OPTION = 'geekybot_product_index_needs_rebuild';
    const LAST_REBUILD_OPTION = 'geekybot_product_index_last_rebuild';
    const AUTO_INDEX_VERSION_OPTION = 'geekybot_product_index_auto_index_version';
    /**
     * Bumped whenever indexed text changes shape, which forces one rebuild on
     * upgrade. '2': StemmerService now stems Russian, so stem_text written by
     * an earlier version holds raw case forms while the query side sends stems,
     * and the two no longer meet.
     */
    const AUTO_INDEX_VERSION = '2';
    const REBUILD_STATE_OPTION = 'geekybot_product_index_rebuild_state';
    const REBUILD_BATCH_HOOK = 'geekybot_product_index_rebuild_batch';

    /**
     * Products indexed per batch. Small enough to finish inside a normal cron
     * request on shared hosting, large enough that a big catalog still
     * completes in a sensible number of passes.
     */
    const REBUILD_BATCH_SIZE = 200;

    /**
     * A running rebuild that has not written a batch in this long is assumed
     * dead -- a killed cron worker or a fatal mid-run -- and may be replaced.
     * Comfortably longer than any single batch, short enough that a merchant
     * is not locked out of rebuilding for the rest of the day.
     */
    const STALE_REBUILD_SECONDS = 900;
    const REBUILD_BATCH_LOCK_OPTION = 'geekybot_product_index_batch_lock';
    const REBUILD_BATCH_LOCK_TTL = 300;
    const SEARCH_CACHE_VERSION_OPTION = 'geekybot_search_cache_version';
    const SEARCH_CACHE_PREFIX = 'geekybot_rank_';
    const SEARCH_CACHE_TTL = 900;

    /**
     * Catalog hooks.
     *
     * Multilingual note: on WPML and Polylang each translation of a product is
     * its own post, so these per-post hooks index every translation separately
     * with no language-specific handling required, and each translation is
     * matched in its own language. This is verified behaviour, not an
     * assumption -- it is written down here because "translated products are
     * probably not indexed" is an easy and wrong conclusion to draw from the
     * absence of any translation code.
     *
     * Multisite note: the index table name is derived from $wpdb->prefix, and
     * the database version is a per-site option, so each site in a network
     * creates and migrates its own tables the first time it boots. A
     * network-wide activation therefore needs no per-site loop.
     *
     * @return void
     */
    public function hooks() {
        add_action('save_post_product', array($this, 'sync_product'), 20, 2);
        add_action('transition_post_status', array($this, 'sync_product_status_change'), 20, 3);
        add_action('woocommerce_new_product', array($this, 'sync_product_by_id'), 20, 1);
        add_action('woocommerce_update_product', array($this, 'sync_product_by_id'), 20, 1);
        add_action('woocommerce_product_set_stock', array($this, 'sync_stock_product'), 20, 1);
        add_action('woocommerce_variation_set_stock', array($this, 'sync_stock_product'), 20, 1);
        add_action('woocommerce_new_product_variation', array($this, 'sync_parent_from_variation'), 20, 1);
        add_action('woocommerce_update_product_variation', array($this, 'sync_parent_from_variation'), 20, 1);
        add_action('woocommerce_delete_product_variation', array($this, 'sync_parent_from_variation'), 20, 1);
        add_action('set_object_terms', array($this, 'sync_product_terms'), 20, 6);
        add_action('edited_product_cat', array($this, 'schedule_rebuild'), 20, 0);
        add_action('edited_product_tag', array($this, 'schedule_rebuild'), 20, 0);
        add_action('delete_product_cat', array($this, 'schedule_rebuild'), 20, 0);
        add_action('delete_product_tag', array($this, 'schedule_rebuild'), 20, 0);
        add_action('before_delete_post', array($this, 'delete_product'), 20, 1);
        add_action('wp_trash_post', array($this, 'delete_product'), 20, 1);
        add_action('untrashed_post', array($this, 'sync_untrashed_product'), 20, 1);
        add_action(self::REBUILD_HOOK, array($this, 'scheduled_rebuild'), 20, 0);
        add_action(self::REBUILD_BATCH_HOOK, array($this, 'run_rebuild_batch'), 20, 0);
        add_action('activated_plugin', array($this, 'handle_plugin_activation'), 20, 2);
        add_action('woocommerce_init', array($this, 'maybe_schedule_initial_rebuild'), 20, 0);
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'geekybot_product_index';
    }

    public static function create_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            title text NOT NULL,
            sku varchar(190) NOT NULL DEFAULT '',
            product_type varchar(40) NOT NULL DEFAULT '',
            price decimal(19,4) NULL,
            regular_price decimal(19,4) NULL,
            sale_price decimal(19,4) NULL,
            is_on_sale tinyint(1) unsigned NOT NULL DEFAULT 0,
            stock_status varchar(40) NOT NULL DEFAULT '',
            rating decimal(4,2) NOT NULL DEFAULT 0,
            total_sales bigint(20) unsigned NOT NULL DEFAULT 0,
            categories text NULL,
            tags text NULL,
            attributes text NULL,
            color_terms text NULL,
            size_terms text NULL,
            short_description text NULL,
            full_description longtext NULL,
            search_text longtext NOT NULL,
            semantic_text longtext NOT NULL,
            stem_text longtext NULL,
            facet_text longtext NULL,
            product_url text NULL,
            image_url text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_id (product_id),
            KEY stock_status (stock_status),
            KEY is_on_sale (is_on_sale),
            KEY product_type (product_type),
            KEY price (price),
            KEY rating (rating),
            KEY total_sales (total_sales),
            FULLTEXT KEY gb_fulltext (title, sku, categories, tags, attributes, color_terms, size_terms, search_text, stem_text)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public function is_ready() {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public function count_indexed() {
        global $wpdb;
        $table = self::table_name();
        if (!$this->is_ready()) {
            return 0;
        }
        return absint($wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
    }

    /**
     * @param int    $product_id Product to index.
     * @param string $table Target table; defaults to the live index. A batched
     *                      rebuild passes the shadow table so live search keeps
     *                      serving the current index until the swap.
     * @return bool
     */
    public function sync_product_by_id($product_id, $table = '') {
        $product = function_exists('wc_get_product') ? wc_get_product(absint($product_id)) : null;
        if (!$product) {
            return false;
        }
        return $this->sync_wc_product($product, $table);
    }

    public function sync_product($post_id, $post = null) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return false;
        }
        return $this->sync_product_by_id($post_id);
    }

    public function sync_product_status_change($new_status, $old_status, $post) {
        if (!is_object($post) || empty($post->ID) || get_post_type($post) !== 'product') {
            return;
        }

        if ($new_status === 'publish') {
            $this->sync_product_by_id($post->ID);
            return;
        }

        if ($old_status === 'publish' && $new_status !== 'publish') {
            $this->delete_product($post->ID);
        }
    }

    public function sync_stock_product($product) {
        if (is_numeric($product)) {
            return $this->sync_product_by_id(absint($product));
        }

        if (!is_object($product) || !method_exists($product, 'get_id')) {
            return false;
        }

        if (method_exists($product, 'is_type') && $product->is_type('variation')) {
            return $this->sync_parent_from_variation($product->get_id());
        }

        return $this->sync_product_by_id($product->get_id());
    }

    public function sync_parent_from_variation($variation_id) {
        $variation_id = absint($variation_id);
        if ($variation_id < 1) {
            return false;
        }

        $parent_id = 0;
        if (function_exists('wc_get_product')) {
            $variation = wc_get_product($variation_id);
            if ($variation && method_exists($variation, 'get_parent_id')) {
                $parent_id = absint($variation->get_parent_id());
            }
        }
        if (!$parent_id) {
            $parent_id = absint(wp_get_post_parent_id($variation_id));
        }

        return $parent_id ? $this->sync_product_by_id($parent_id) : false;
    }

    public function sync_product_terms($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        if (!in_array((string) $taxonomy, array('product_cat', 'product_tag', 'product_visibility'), true)) {
            return;
        }
        if (get_post_type($object_id) !== 'product') {
            return;
        }
        $this->sync_product_by_id(absint($object_id));
    }

    public function sync_untrashed_product($post_id) {
        if (get_post_type($post_id) === 'product') {
            $this->sync_product_by_id(absint($post_id));
        }
    }

    public function schedule_rebuild(...$args) {
        self::request_rebuild();
    }

    /**
     * Mark the catalog for a full rebuild and schedule it when WooCommerce is ready.
     *
     * Keeping the pending marker when WooCommerce is unavailable lets Geeky Bot
     * recover automatically after WooCommerce is installed or reactivated.
     */
    public static function request_rebuild($delay = 90) {
        $pending_marker = current_time('mysql') . '|' . microtime(true);
        update_option(self::REBUILD_PENDING_OPTION, $pending_marker, false);
        self::flush_search_cache();

        if (!self::woocommerce_ready() || wp_next_scheduled(self::REBUILD_HOOK)) {
            return false;
        }

        return (bool) wp_schedule_single_event(time() + max(10, absint($delay)), self::REBUILD_HOOK);
    }

    public function handle_plugin_activation($plugin, $network_wide = false) {
        unset($network_wide);

        if ((string) $plugin === 'woocommerce/woocommerce.php') {
            self::request_rebuild(30);
        }
    }

    public function maybe_schedule_initial_rebuild() {
        $auto_index_version = (string) get_option(self::AUTO_INDEX_VERSION_OPTION, '');
        if ($auto_index_version !== self::AUTO_INDEX_VERSION) {
            update_option(self::AUTO_INDEX_VERSION_OPTION, self::AUTO_INDEX_VERSION, false);
            self::request_rebuild(30);
            return;
        }

        if (get_option(self::REBUILD_PENDING_OPTION) && !wp_next_scheduled(self::REBUILD_HOOK)) {
            self::request_rebuild(30);
        }
    }

    public static function rebuild_status() {
        $state = self::rebuild_state();
        if ($state !== null && $state['status'] === 'running') {
            return 'running';
        }

        if (!get_option(self::REBUILD_PENDING_OPTION)) {
            return 'current';
        }

        if (!self::woocommerce_ready()) {
            return 'waiting_for_woocommerce';
        }

        return wp_next_scheduled(self::REBUILD_HOOK) ? 'scheduled' : 'pending';
    }

    /**
     * Human-facing progress for a batched rebuild.
     *
     * @return array{running: bool, processed: int, total: int, percent: int}
     */
    public static function rebuild_progress() {
        $state = self::rebuild_state();
        if ($state === null || $state['status'] !== 'running') {
            return array('running' => false, 'processed' => 0, 'total' => 0, 'percent' => 0);
        }

        $processed = absint($state['processed']);
        $total = absint($state['total']);

        return array(
            'running' => true,
            'processed' => $processed,
            'total' => $total,
            'percent' => $total > 0 ? min(100, (int) round(($processed / $total) * 100)) : 0,
        );
    }

    private static function woocommerce_ready() {
        return function_exists('wc_get_product');
    }

    public function scheduled_rebuild() {
        if (!get_option(self::REBUILD_PENDING_OPTION) || !self::woocommerce_ready()) {
            return;
        }

        // Start the batched run and let the batch hook carry it to completion,
        // so no single cron request has to index the whole catalog.
        $state = self::rebuild_state();
        if ($state !== null && $state['status'] === 'running') {
            $this->run_rebuild_batch();
            return;
        }

        $this->start_batched_rebuild();
        $this->run_rebuild_batch();
    }

    public function delete_product($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        global $wpdb;
        $wpdb->delete(self::table_name(), array('product_id' => absint($post_id)), array('%d'));
        self::flush_search_cache();
    }

    /**
     * Relearn the store's family vocabulary after the catalog is reindexed.
     *
     * @return array
     */
    /**
     * Relearn the store's own searchable word list.
     *
     * Kept beside rebuild_vocabulary() and driven by the same completed-rebuild
     * event, so typo recovery always reflects the catalog that is searchable.
     *
     * @return array
     */
    public function rebuild_search_vocabulary() {
        return (new SearchVocabularyService())->rebuild();
    }

    public function rebuild_vocabulary() {
        return $this->vocabulary()->rebuild();
    }

    /**
     * Shadow table a batched rebuild populates before the atomic swap.
     *
     * @return string
     */
    public static function shadow_table_name() {
        return self::table_name() . '_new';
    }

    /**
     * Build the shadow table with the live table's schema.
     *
     * @return bool
     */
    private static function create_shadow_table() {
        global $wpdb;

        $live = self::table_name();
        $shadow = self::shadow_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table names.
        $wpdb->query("DROP TABLE IF EXISTS {$shadow}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- LIKE copies the live schema, including the FULLTEXT key.
        $wpdb->query("CREATE TABLE {$shadow} LIKE {$live}");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Existence check on a plugin-owned table.
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $shadow)) === $shadow;
    }

    /**
     * Current batched-rebuild state, or null when none is running.
     *
     * @return array|null
     */
    public static function rebuild_state() {
        $state = get_option(self::REBUILD_STATE_OPTION, null);

        return is_array($state) && !empty($state['status']) ? $state : null;
    }

    /**
     * @param array $state State to persist.
     * @return void
     */
    private static function save_rebuild_state($state) {
        // Heartbeat, so a run that dies mid-flight can be told apart from one
        // that is simply still working.
        $state['updated_gmt'] = current_time('mysql', true);
        update_option(self::REBUILD_STATE_OPTION, $state, false);
    }

    /**
     * Whether a running rebuild has stopped making progress.
     *
     * @param array $state Rebuild state.
     * @return bool
     */
    private static function rebuild_is_stale($state) {
        $stamp = '';
        foreach (array('updated_gmt', 'started_gmt') as $key) {
            if (!empty($state[$key])) {
                $stamp = (string) $state[$key];
                break;
            }
        }

        if ($stamp === '') {
            // No heartbeat at all: written by an older version, so do not let
            // it block a rebuild.
            return true;
        }

        $last = strtotime($stamp . ' UTC');

        return $last === false || (time() - $last) > self::STALE_REBUILD_SECONDS;
    }

    /**
     * Begin a batched rebuild into the shadow table.
     *
     * Nothing is destroyed here. Live search keeps serving the existing table
     * for the whole run, which is the entire point: the previous implementation
     * truncated the live table first, so every concurrent shopper search
     * returned nothing until the rebuild finished.
     *
     * A run already in progress is never restarted. Two overlapping runs would
     * share one cursor: each reads the state, advances it, and writes it back,
     * so the slower writer silently rewinds the faster one and the products in
     * between never reach the shadow table -- which is then swapped in as if it
     * were complete. That is reachable in practice, because an admin can press
     * Rebuild while a cron batch is mid-flight. A run that has not progressed
     * for STALE_REBUILD_SECONDS is treated as dead and may be replaced, so a
     * process killed mid-run cannot block rebuilds forever.
     *
     * @return array
     */
    public function start_batched_rebuild() {
        if (!self::woocommerce_ready()) {
            return array('started' => false, 'reason' => 'woocommerce_unavailable');
        }

        $running = self::rebuild_state();
        if ($running !== null && $running['status'] === 'running' && !self::rebuild_is_stale($running)) {
            return array(
                'started' => false,
                'reason' => 'already_running',
                'total' => absint($running['total']),
            );
        }

        global $wpdb;
        self::create_table();

        if (!self::create_shadow_table()) {
            return array('started' => false, 'reason' => 'shadow_table_failed');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One counting query to report progress.
        $total = absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"));

        self::save_rebuild_state(array(
            'status' => 'running',
            'cursor' => 0,
            'processed' => 0,
            'indexed' => 0,
            'skipped' => 0,
            'total' => $total,
            'started_at' => current_time('mysql'),
            'started_gmt' => current_time('mysql', true),
            'pending_marker' => get_option(self::REBUILD_PENDING_OPTION, ''),
        ));

        if (!wp_next_scheduled(self::REBUILD_BATCH_HOOK)) {
            wp_schedule_single_event(time() + 5, self::REBUILD_BATCH_HOOK);
        }

        return array('started' => true, 'total' => $total);
    }

    /**
     * Cron entry point: index one batch, then queue the next.
     *
     * @return void
     */
    public function run_rebuild_batch() {
        $result = $this->rebuild_batch();

        if (!empty($result['complete']) || empty($result['running'])) {
            return;
        }

        if (!wp_next_scheduled(self::REBUILD_BATCH_HOOK)) {
            wp_schedule_single_event(time() + 5, self::REBUILD_BATCH_HOOK);
        }
    }

    /**
     * Claim the right to run one rebuild batch.
     *
     * Batch workers share a single cursor, so two of them running at once read
     * the same position, index the same products and write the position back
     * over each other. WordPress reaches this easily: run_rebuild_batch()
     * re-queues itself every few seconds, and an admin pressing Rebuild while a
     * cron batch is mid-flight adds a second worker. add_option() is a single
     * INSERT on a unique key, so exactly one caller can create the row.
     *
     * @return bool True when this process owns the batch slot.
     */
    private static function acquire_batch_lock() {
        $now = time();
        if (add_option(self::REBUILD_BATCH_LOCK_OPTION, $now, '', 'no')) {
            return true;
        }

        $locked_at = absint(get_option(self::REBUILD_BATCH_LOCK_OPTION, 0));
        if ($locked_at > 0 && ($now - $locked_at) < self::REBUILD_BATCH_LOCK_TTL) {
            return false;
        }

        // The holder died without releasing. Reclaim rather than stall forever.
        delete_option(self::REBUILD_BATCH_LOCK_OPTION);

        return (bool) add_option(self::REBUILD_BATCH_LOCK_OPTION, $now, '', 'no');
    }

    /**
     * @return void
     */
    private static function release_batch_lock() {
        delete_option(self::REBUILD_BATCH_LOCK_OPTION);
    }

    /**
     * Index one batch of products into the shadow table.
     *
     * The cursor is a product ID, persisted after every batch, so an
     * interrupted run resumes from where it stopped instead of restarting from
     * zero.
     *
     * @param int $batch_size Products to process, 0 for the default.
     * @return array
     */
    public function rebuild_batch($batch_size = 0) {
        // Serialised against other workers: without this, two cron requests
        // read the same cursor, index the same products twice and write the
        // cursor back over each other -- and the swap can land while the other
        // worker is still writing rows that then never reach the live table.
        if (!self::acquire_batch_lock()) {
            $running = self::rebuild_state();

            return array(
                'running' => $running !== null && $running['status'] === 'running',
                'complete' => false,
                'locked' => true,
            );
        }

        try {
            return $this->index_one_batch($batch_size);
        } finally {
            self::release_batch_lock();
        }
    }

    /**
     * Index one batch. Callers must hold the batch lock.
     *
     * @param int $batch_size Products to process, 0 for the default.
     * @return array
     */
    private function index_one_batch($batch_size = 0) {
        $state = self::rebuild_state();
        if ($state === null || $state['status'] !== 'running') {
            return array('running' => false, 'complete' => false);
        }

        if (!self::woocommerce_ready()) {
            return array('running' => true, 'complete' => false);
        }

        global $wpdb;
        $shadow = self::shadow_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Existence check on a plugin-owned table.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $shadow)) !== $shadow) {
            // The shadow table vanished under us. Start over rather than
            // swapping a partial index into place.
            self::save_rebuild_state(array_merge($state, array('status' => 'failed')));
            return array('running' => false, 'complete' => false);
        }

        $batch_size = $batch_size > 0 ? absint($batch_size) : self::REBUILD_BATCH_SIZE;
        $cursor = absint($state['cursor']);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cursor paging over published products; ID order makes the run resumable.
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT %d",
            $cursor,
            $batch_size
        ));

        if (empty($ids)) {
            return $this->finish_batched_rebuild($state);
        }

        foreach ($ids as $product_id) {
            $product_id = absint($product_id);
            $ok = $this->sync_product_by_id($product_id, $shadow);
            $ok ? $state['indexed']++ : $state['skipped']++;
            $state['processed']++;
            $state['cursor'] = $product_id;
        }

        self::save_rebuild_state($state);

        return array(
            'running' => true,
            'complete' => false,
            'processed' => absint($state['processed']),
            'total' => absint($state['total']),
        );
    }

    /**
     * Swap the finished shadow table into place.
     *
     * RENAME TABLE of both tables in one statement is atomic, so no request
     * ever sees a missing or half-populated index.
     *
     * @param array $state Rebuild state.
     * @return array
     */
    private function finish_batched_rebuild($state) {
        global $wpdb;

        $live = self::table_name();
        $shadow = self::shadow_table_name();
        $retired = $live . '_old';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned tables.
        $wpdb->query("DROP TABLE IF EXISTS {$retired}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Single-statement atomic swap.
        $swapped = false !== $wpdb->query("RENAME TABLE {$live} TO {$retired}, {$shadow} TO {$live}");

        if (!$swapped) {
            self::save_rebuild_state(array_merge($state, array('status' => 'failed')));
            return array('running' => false, 'complete' => false, 'swapped' => false);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table.
        $wpdb->query("DROP TABLE IF EXISTS {$retired}");

        // Product edits made while the rebuild was running went to the live
        // table, which has just been replaced. Re-sync anything touched since
        // the run started so no edit is lost to the swap.
        $this->resync_products_modified_since(isset($state['started_gmt']) ? (string) $state['started_gmt'] : '');

        update_option(self::LAST_REBUILD_OPTION, current_time('mysql'), false);
        if (get_option(self::REBUILD_PENDING_OPTION, '') === (isset($state['pending_marker']) ? $state['pending_marker'] : '')) {
            delete_option(self::REBUILD_PENDING_OPTION);
        }

        $this->rebuild_vocabulary();
        $this->rebuild_search_vocabulary();
        self::flush_search_cache();

        self::save_rebuild_state(array_merge($state, array(
            'status' => 'complete',
            'finished_at' => current_time('mysql'),
        )));

        return array(
            'running' => false,
            'complete' => true,
            'swapped' => true,
            'indexed' => absint($state['indexed']),
            'skipped' => absint($state['skipped']),
        );
    }

    /**
     * Re-index products edited while a batched rebuild was in flight.
     *
     * @param string $since_gmt MySQL GMT datetime.
     * @return int Products re-synced.
     */
    private function resync_products_modified_since($since_gmt) {
        if ($since_gmt === '') {
            return 0;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded catch-up query for edits made during the rebuild.
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND post_modified_gmt >= %s ORDER BY ID ASC LIMIT 500",
            $since_gmt
        ));

        $count = 0;
        foreach ((array) $ids as $product_id) {
            if ($this->sync_product_by_id(absint($product_id))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Run a whole batched rebuild in this request.
     *
     * Reached only by a direct rebuild() call with no limit -- WP-CLI, an
     * add-on, or a developer. The admin action deliberately does not use this:
     * it runs a few batches within a time and memory budget and hands the rest
     * to cron, because a full synchronous run measured 175s and 211MB on a 20k
     * catalog. It still goes through the shadow table and the atomic swap, so
     * even a long synchronous run never takes live search offline, and an
     * interrupted one resumes from its saved cursor rather than restarting.
     *
     * @return array
     */
    private function rebuild_synchronously() {
        $start = $this->start_batched_rebuild();
        if (empty($start['started'])) {
            // "Already running" is not a failure: a cron batch got there
            // first, so carry that run to completion rather than starting a
            // competing one or reporting nothing happened.
            $reason = isset($start['reason']) ? $start['reason'] : '';
            if ($reason !== 'already_running') {
                return array('indexed' => 0, 'skipped' => 0, 'complete' => false);
            }
        }

        // Bounded so a pathological catalog cannot spin forever in one request.
        $max_batches = 10000;
        $result = array('running' => true, 'complete' => false);

        for ($i = 0; $i < $max_batches; $i++) {
            $result = $this->rebuild_batch();
            if (empty($result['running']) || !empty($result['complete'])) {
                break;
            }
        }

        $state = self::rebuild_state();
        $indexed = is_array($state) ? absint($state['indexed']) : 0;
        $skipped = is_array($state) ? absint($state['skipped']) : 0;

        return array(
            'indexed' => $indexed,
            'skipped' => $skipped,
            'complete' => !empty($result['complete']),
        );
    }

    public function rebuild($limit = 0) {
        if (!self::woocommerce_ready()) {
            return array('indexed' => 0, 'skipped' => 0, 'complete' => false);
        }

        self::create_table();

        // A full rebuild goes through the shadow table and an atomic swap, so
        // live search is never interrupted.
        if (!$limit) {
            return $this->rebuild_synchronously();
        }

        // Bounded warm-up, used only when the index is empty and there is
        // nothing to protect. It tops the live table up rather than truncating
        // it, so it can never remove rows a shopper is about to search.
        $ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => max(1, absint($limit)),
            'no_found_rows' => true,
        ));

        $indexed = 0;
        $skipped = 0;
        foreach ($ids as $product_id) {
            $this->sync_product_by_id($product_id) ? $indexed++ : $skipped++;
        }

        // A partial pass is never "the catalog is now indexed", so it does not
        // clear the pending marker or relearn the vocabulary.
        return array('indexed' => $indexed, 'skipped' => $skipped, 'complete' => false);
    }

    public function search_ids($query, $limit = 8, $analysis = null) {
        if (!$this->is_ready()) {
            self::create_table();
        }

        if (!$this->count_indexed()) {
            $this->rebuild(300);
        }

        $query = $this->clean_query($query);
        $limit = max(1, min(16, absint($limit)));
        if ($query === '') {
            return array();
        }

        if (!is_array($analysis) || empty($analysis)) {
            $analysis = $this->analyze_query($query);
        }

        // Ranking is ~200 lines of additive weights run across every candidate,
        // and the same handful of phrases are typed by hundreds of shoppers.
        // The cache is keyed on the analysed query, so an identical key really
        // is an identical ranking problem, and it is namespaced by a version
        // counter that every catalog write bumps -- so a stale list cannot
        // outlive the product edit that invalidated it.
        $cache_key = $this->search_cache_key($query, $limit, $analysis);
        if ($cache_key !== '') {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return CatalogVisibilityService::filter_ids(
                    array_values(array_unique(array_map('absint', $cached))),
                    $limit,
                    'product_index_search'
                );
            }
        }

        $candidate_limit = min(120, max(60, $limit * 12));
        $rows = $this->candidate_rows($analysis, $candidate_limit);
        if (empty($rows)) {
            if ($cache_key !== '') {
                set_transient($cache_key, array(), self::SEARCH_CACHE_TTL);
            }
            return array();
        }

        $scored = array();
        foreach ($rows as $row) {
            $score = $this->score_row($row, $analysis);
            if ($score <= 0) {
                continue;
            }
            $scored[] = array(
                'id' => absint($row->product_id),
                'score' => $score,
                'stockRank' => isset($row->stock_status) && (string) $row->stock_status === 'instock' ? 0 : 1,
            );
        }

        usort($scored, function ($a, $b) {
            if ($a['stockRank'] !== $b['stockRank']) {
                return $a['stockRank'] <=> $b['stockRank'];
            }
            if ($a['score'] === $b['score']) {
                return $a['id'] <=> $b['id'];
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });

        $ids = array();
        foreach ($scored as $item) {
            $ids[] = $item['id'];
            if (count($ids) >= $limit) {
                break;
            }
        }

        $ids = array_values(array_unique(array_map('absint', $ids)));

        if ($cache_key !== '') {
            // Visibility is deliberately applied after the cache, never baked
            // into it, so a catalog-visibility change is respected immediately.
            set_transient($cache_key, $ids, self::SEARCH_CACHE_TTL);
        }

        return CatalogVisibilityService::filter_ids($ids, $limit, 'product_index_search');
    }

    /**
     * Cache key for a ranked result list.
     *
     * @param string $query Cleaned query.
     * @param int    $limit Result count.
     * @param array  $analysis Query analysis.
     * @return string Empty when this query must not be cached.
     */
    private function search_cache_key($query, $limit, $analysis) {
        if (!is_array($analysis) || empty($analysis)) {
            return '';
        }

        // Only the parts of the analysis that can change the ordering.
        $signature = array(
            'q' => (string) $query,
            // The same words rank differently per language now that buyer
            // intent is loaded from a per-language pack, and the language is
            // resolved from the WPML/Polylang page rather than from the query
            // text -- so it is not implied by 'q' and has to be keyed
            // explicitly. Without this, the same phrase typed on /es/ and /en/
            // shares one cached ranking.
            'lang' => $this->search_language()->language_code($query),
            'limit' => absint($limit),
            'boolean' => isset($analysis['boolean']) ? (string) $analysis['boolean'] : '',
            'terms' => isset($analysis['core_terms']) ? (array) $analysis['core_terms'] : array(),
            'colors' => isset($analysis['color_terms']) ? (array) $analysis['color_terms'] : array(),
            'sizes' => isset($analysis['size_terms']) ? (array) $analysis['size_terms'] : array(),
            'price' => isset($analysis['price_range']) ? (array) $analysis['price_range'] : array(),
            'intent' => isset($analysis['intent']) ? (string) $analysis['intent'] : '',
            'stock' => !empty($analysis['in_stock_only']) ? 1 : 0,
            'sale' => $this->analysis_requires_sale($analysis) ? 1 : 0,
            'gift' => !empty($analysis['is_gift_request']) ? 1 : 0,
            'budget' => !empty($analysis['budget_sort']) ? 1 : 0,
            'value' => !empty($analysis['value_sort']) ? 1 : 0,
            'modes' => isset($analysis['decision_modes']) ? (array) $analysis['decision_modes'] : array(),
            // Ranking boosts are merchant settings, so they belong in the key.
            'boosts' => array(
                Settings::get('search_boost_in_stock', 'yes'),
                Settings::get('search_boost_sale', 'yes'),
                Settings::get('search_boost_rating', 'yes'),
                Settings::get('search_boost_popularity', 'yes'),
                Settings::get('search_min_score', 1),
                Settings::get('search_close_match_mode', 'smart'),
            ),
        );

        $encoded = wp_json_encode($signature);
        if (!is_string($encoded)) {
            return '';
        }

        return self::SEARCH_CACHE_PREFIX . self::search_cache_version() . '_' . md5($encoded);
    }

    /**
     * Namespace counter for the ranking cache.
     *
     * @return int
     */
    public static function search_cache_version() {
        return absint(get_option(self::SEARCH_CACHE_VERSION_OPTION, 1));
    }

    /**
     * Invalidate every cached ranking.
     *
     * Bumping a namespace beats deleting keys: it is one write, it cannot miss
     * a key, and the orphaned transients expire on their own.
     *
     * @return void
     */
    public static function flush_search_cache() {
        update_option(self::SEARCH_CACHE_VERSION_OPTION, self::search_cache_version() + 1, false);
    }

    public function analyze_query($query) {
        $language = $this->search_language();
        $clean = $this->clean_query($query);
        $lower = $language->normalize_text($clean);
        $price_range = $language->price_range_from_query($lower);
        $intent = $language->catalog_intent($lower);
        // Keep the sale requirement as an explicit commerce constraint instead
        // of relying only on the generic intent value. This survives family
        // parsing, context reconstruction, and presentation layers without
        // allowing sale words to become product-identity terms.
        $sale_required = ($intent === 'sale');
        $negative_facets = $language->negative_facets_from_query($lower);
        $negative_color_terms = isset($negative_facets['colors']) ? (array) $negative_facets['colors'] : array();
        $negative_size_terms = isset($negative_facets['sizes']) ? (array) $negative_facets['sizes'] : array();
        $buyer_profile = $language->buyer_intent_profile($lower);
        $modifier_terms = $language->buyer_modifier_terms($lower);
        $modifier_labels = $language->buyer_modifier_labels($lower);

        // Detected once, from the shopper's own wording, and then threaded
        // through every tokenising call below. The stripping that follows
        // removes filler and modifier phrases, which is where a Latin-script
        // language is recognisable -- re-detecting on the remains classified a
        // French question as English and kept `des` as a required product term.
        $query_language = $language->language_code($lower);

        $searchable = $language->strip_commerce_phrases($language->strip_price_filters($lower));
        $searchable = $language->strip_negative_facets($searchable);
        $searchable = $language->strip_buyer_modifier_phrases($searchable, $buyer_profile);
        $base_terms = $this->remove_weak_shopper_terms($language->remove_intent_terms($language->query_terms($searchable, $query_language), $intent));
        $base_terms = $language->remove_buyer_intent_tokens($base_terms, $buyer_profile);
        $expansion = method_exists($language, 'expand_synonyms_map')
            ? (array) $language->expand_synonyms_map($searchable)
            : array('text' => $language->expand_synonyms($searchable), 'sources' => array(), 'alternates' => array());
        $expanded = isset($expansion['text']) ? (string) $expansion['text'] : '';
        $expansion_sources = isset($expansion['sources']) ? (array) $expansion['sources'] : array();
        $expansion_alternates = isset($expansion['alternates']) ? (array) $expansion['alternates'] : array();
        $terms = $this->remove_weak_shopper_terms($language->query_terms($expanded, $query_language));
        $terms = $this->remove_weak_shopper_terms($language->remove_intent_terms($terms, $intent));
        $terms = $language->remove_buyer_intent_tokens($terms, $buyer_profile);
        $negated_price_terms = method_exists($language, 'negated_price_terms_from_query') ? $language->negated_price_terms_from_query($lower) : array();
        if (!empty($negated_price_terms)) {
            $base_terms = $this->terms_without($base_terms, $negated_price_terms);
            $terms = $this->terms_without($terms, $negated_price_terms);
        }
        $facets = $language->product_facets_from_query($lower, $terms);
        $color_terms = isset($facets['colors']) ? (array) $facets['colors'] : array();
        $size_terms = isset($facets['sizes']) ? (array) $facets['sizes'] : array();
        $color_terms = $this->terms_without($color_terms, $negative_color_terms);
        $size_terms = $this->terms_without($size_terms, $negative_size_terms);
        $requested_color_labels = $this->display_facet_labels($color_terms, 'color');
        $requested_size_labels = $this->display_facet_labels($size_terms, 'size');
        $negative_color_labels = $this->display_facet_labels($negative_color_terms, 'color');
        $negative_size_labels = $this->display_facet_labels($negative_size_terms, 'size');
        // Product identity must come from the shopper's remaining catalog
        // words, not from soft decision language such as "affordable" or
        // "good value". Those phrases still influence ranking through the
        // buyer profile, but they must never become a hard product phrase.
        $product_phrase = $this->product_phrase_profile(
            $searchable,
            $color_terms,
            $size_terms,
            $negative_color_terms,
            $negative_size_terms,
            $modifier_terms,
            $lower,
            $query_language
        );
        // A family and its gate are read out of an English vocabulary, so an
        // Arabic product could satisfy the fulltext pass and still be rejected
        // by row_matches_product_phrase(): "mug" is not in طقم أكواب سيراميك.
        // Folding the family's own translations into its alias lists keeps the
        // gate exactly as strict -- it is an OR over surface forms of one
        // concept, and a translation is another such surface form.
        $product_phrase = $this->translate_family_aliases($product_phrase, $expansion_alternates);
        $core_terms = array_values(array_unique(array_filter(array_merge(
            (array) ($product_phrase['search_terms'] ?? array()),
            $this->core_product_terms($terms, $color_terms, $size_terms, $negative_color_terms, $negative_size_terms, $modifier_terms)
        ))));
        $display_core_terms = array_values(array_unique(array_filter(array_merge(
            (array) ($product_phrase['terms'] ?? array()),
            $this->core_product_terms($base_terms, $color_terms, $size_terms, $negative_color_terms, $negative_size_terms, $modifier_terms)
        ))));
        $boolean_terms = array_values(array_unique(array_filter(array_merge($core_terms, $color_terms, $size_terms))));
        $boolean_groups = $this->boolean_term_groups($product_phrase, $core_terms, $display_core_terms, $color_terms, $size_terms, $expansion_sources);
        $budget_sort = $language->contains_budget_signal($lower);
        $value_sort = $language->contains_value_signal($lower);

        return array(
            'raw' => $clean,
            'lower' => $lower,
            'searchable' => $searchable,
            'expanded' => $expanded,
            // Shopper token => the alternates synonym expansion produced for it.
            // Read by the phrase gate so a qualifier can be satisfied in the
            // language the catalog is actually written in. Absent from an
            // analysis saved by an earlier release, which simply gates on the
            // typed wording as it did then.
            'expansion_alternates' => $expansion_alternates,
            'terms' => $terms,
            'core_terms' => $core_terms,
            'display_core_terms' => $display_core_terms,
            'facets' => $facets,
            'color_terms' => $color_terms,
            'size_terms' => $size_terms,
            'negative_color_terms' => $negative_color_terms,
            'negative_size_terms' => $negative_size_terms,
            'requested_color_labels' => $requested_color_labels,
            'requested_size_labels' => $requested_size_labels,
            'negative_color_labels' => $negative_color_labels,
            'negative_size_labels' => $negative_size_labels,
            'modifier_terms' => $modifier_terms,
            'modifier_labels' => $modifier_labels,
            'modifier_weights' => !empty($buyer_profile['ranking_weights']) ? (array) $buyer_profile['ranking_weights'] : array(),
            'decision_modes' => !empty($buyer_profile['decision_modes']) ? (array) $buyer_profile['decision_modes'] : array(),
            'is_gift_request' => !empty($buyer_profile['is_gift_request']),
            'gift_signals' => !empty($buyer_profile['gift_signals']) ? (array) $buyer_profile['gift_signals'] : array(),
            'buyer_profile' => $buyer_profile,
            'audience' => !empty($buyer_profile['audience']) ? (array) $buyer_profile['audience'] : array(),
            'boolean' => $this->boolean_query($boolean_groups),
            // Kept so a session analysis saved by an earlier release still has
            // the flat list its scoring helpers expect.
            'boolean_terms' => $boolean_terms,
            'boolean_groups' => $boolean_groups,
            'phrase' => !empty($product_phrase['phrase']) ? $product_phrase['phrase'] : $this->phrase_for_like($searchable),
            'product_phrase' => !empty($product_phrase['phrase']) ? $product_phrase['phrase'] : '',
            'product_phrase_terms' => !empty($product_phrase['terms']) ? (array) $product_phrase['terms'] : array(),
            'product_family_term' => !empty($product_phrase['family']) ? (string) $product_phrase['family'] : '',
            'product_family_source' => !empty($product_phrase['family_source']) ? (string) $product_phrase['family_source'] : '',
            'product_family_aliases' => !empty($product_phrase['family_aliases']) ? (array) $product_phrase['family_aliases'] : array(),
            'required_family_aliases' => !empty($product_phrase['required_family_aliases']) ? (array) $product_phrase['required_family_aliases'] : array(),
            'family_gate_aliases' => !empty($product_phrase['family_gate_aliases']) ? (array) $product_phrase['family_gate_aliases'] : array(),
            'product_qualifier_terms' => !empty($product_phrase['qualifier_terms']) ? (array) $product_phrase['qualifier_terms'] : array(),
            'product_declared_qualifier_terms' => !empty($product_phrase['declared_qualifier_terms']) ? (array) $product_phrase['declared_qualifier_terms'] : array(),
            'price_range' => $price_range,
            'intent' => $sale_required ? 'sale' : $intent,
            'sale_required' => $sale_required,
            'budget_sort' => $budget_sort,
            'value_sort' => $value_sort,
            'language' => $query_language,
            'in_stock_only' => $language->is_in_stock_query($lower),
        );
    }

    private function sync_wc_product($product, $target_table = '') {
        if (!$product || !CatalogVisibilityService::is_visible($product, 'product_index')) {
            if ($product && $product->get_id()) {
                $this->delete_product($product->get_id());
            }
            return false;
        }

        $product_id = $product->get_id();
        $categories = $this->term_names($product_id, 'product_cat');
        $tags = $this->term_names($product_id, 'product_tag');
        $attribute_data = $this->attribute_data($product);
        $attributes = $attribute_data['text'];
        $color_terms = $attribute_data['color_terms'];
        $size_terms = $attribute_data['size_terms'];
        $title = wp_strip_all_tags($product->get_name());
        $short = wp_strip_all_tags($product->get_short_description());
        $full = wp_strip_all_tags($product->get_description());
        $sku = (string) $product->get_sku();
        $image_id = $product->get_image_id();
        $image = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : '';
        $now = current_time('mysql');

        $search_text = $this->normalize_index_text(implode(' ', array(
            $title,
            $sku,
            implode(' ', $categories),
            implode(' ', $tags),
            $attributes,
            $color_terms,
            $size_terms,
            $short,
            $full,
        )));

        // A stemmed copy of the identity fields, so a shopper's plural can reach a
        // catalog singular. Both sides run through StemmerService, which is the
        // whole point: `beanies` and `Beanie` only meet if the same function
        // reduces them. Descriptions are left out -- stemming prose adds noise
        // without helping a product be found by name.
        $stem_text = $this->stemmer()->unique_stem_text($this->normalize_index_text(implode(' ', array(
            $title,
            implode(' ', $categories),
            implode(' ', $tags),
            $attributes,
        ))));

        // The identity fields again, normalized this time. `title`, `categories`,
        // `tags`, `attributes`, `color_terms` and `size_terms` are all stored as
        // the merchant typed them, but every shopper term reaching the WHERE
        // clause has been through normalize_text() -- so for any script the
        // normalizer actually rewrites, the two sides could never meet. Arabic
        // is where it bites: a query for أسود is folded to اسود and matched
        // against a raw اسود/أسود column that still holds the hamza form, so
        // colour and size filters returned nothing at all. Latin scripts hid it
        // because utf8mb4 collation already folds case and accents.
        //
        // Descriptions are deliberately excluded. This column is the haystack
        // for the core-term gate, whose whole job is to require a hit in the
        // product's identity rather than somewhere in its prose; search_text
        // already covers the wider match.
        $facet_text = $this->normalize_index_text(implode(' ', array(
            $title,
            $sku,
            implode(' ', $categories),
            implode(' ', $tags),
            $attributes,
            $color_terms,
            $size_terms,
        )));

        $semantic_text = $this->normalize_index_text(implode('. ', array_filter(array(
            'Product: ' . $title,
            $sku ? 'SKU: ' . $sku : '',
            $categories ? 'Categories: ' . implode(', ', $categories) : '',
            $tags ? 'Tags: ' . implode(', ', $tags) : '',
            $attributes ? 'Attributes: ' . $attributes : '',
            $color_terms ? 'Colors: ' . $color_terms : '',
            $size_terms ? 'Sizes: ' . $size_terms : '',
            $short ? 'Summary: ' . $short : '',
            $full ? 'Description: ' . wp_trim_words($full, 90) : '',
        ))));

        global $wpdb;
        $table = $target_table !== '' ? $target_table : self::table_name();
        $data = array(
            'product_id' => $product_id,
            'title' => $title,
            'sku' => $sku,
            'product_type' => $product->get_type(),
            'price' => $this->decimal_or_null($product->get_price()),
            'regular_price' => $this->decimal_or_null($product->get_regular_price()),
            'sale_price' => $this->decimal_or_null($product->get_sale_price()),
            'is_on_sale' => $product->is_on_sale() ? 1 : 0,
            'stock_status' => CatalogAvailabilityService::stock_status($product),
            'rating' => (float) $product->get_average_rating(),
            'total_sales' => absint($product->get_total_sales()),
            'categories' => implode(' ', $categories),
            'tags' => implode(' ', $tags),
            'attributes' => $attributes,
            'color_terms' => $color_terms,
            'size_terms' => $size_terms,
            'short_description' => $short,
            'full_description' => $full,
            'search_text' => $search_text,
            'semantic_text' => $semantic_text,
            'stem_text' => $stem_text,
            'facet_text' => $facet_text,
            'product_url' => get_permalink($product_id),
            'image_url' => $image ? esc_url_raw($image) : '',
            'created_at' => get_post_time('Y-m-d H:i:s', false, $product_id) ?: $now,
            'updated_at' => $now,
        );

        // One format per column, in $data order. wpdb does not pad a short
        // list -- it silently reuses the first format for every column past
        // the end, so a missing entry here writes the wrong type rather than
        // failing. Keep this in step with $data above when adding a column.
        $formats = array(
            '%d', // product_id
            '%s', // title
            '%s', // sku
            '%s', // product_type
            '%f', // price
            '%f', // regular_price
            '%f', // sale_price
            '%d', // is_on_sale
            '%s', // stock_status
            '%f', // rating
            '%d', // total_sales
            '%s', // categories
            '%s', // tags
            '%s', // attributes
            '%s', // color_terms
            '%s', // size_terms
            '%s', // short_description
            '%s', // full_description
            '%s', // search_text
            '%s', // semantic_text
            '%s', // stem_text
            '%s', // facet_text
            '%s', // product_url
            '%s', // image_url
            '%s', // created_at
            '%s', // updated_at
        );
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE product_id = %d", $product_id));
        if ($exists) {
            $written = false !== $wpdb->update($table, $data, array('product_id' => $product_id), $formats, array('%d'));
        } else {
            $written = false !== $wpdb->insert($table, $data, $formats);
        }

        // A write to the shadow table changes nothing a shopper can see yet,
        // so only live writes invalidate rankings. The swap flushes once.
        if ($written && $target_table === '') {
            self::flush_search_cache();
        }

        return $written;
    }

    private function candidate_rows($analysis, $limit) {
        global $wpdb;
        $table = self::table_name();
        $where = array('1=1');
        $params = array();

        if (!empty($analysis['price_range'])) {
            if ($analysis['price_range']['min'] !== null) {
                $where[] = 'price >= %f';
                $params[] = (float) $analysis['price_range']['min'];
            }
            if ($analysis['price_range']['max'] !== null) {
                $where[] = 'price <= %f';
                $params[] = (float) $analysis['price_range']['max'];
            }
        }

        if (!empty($analysis['in_stock_only'])) {
            $where[] = "stock_status = 'instock'";
        }

        if ($this->analysis_requires_sale($analysis)) {
            $where[] = '(is_on_sale = 1 OR (sale_price IS NOT NULL AND sale_price > 0) OR (regular_price IS NOT NULL AND regular_price > 0 AND price IS NOT NULL AND price > 0 AND price < regular_price))';
        }

        $structured_where = $this->structured_query_where($analysis);
        foreach ($structured_where['where'] as $clause) {
            $where[] = $clause;
        }
        foreach ($structured_where['params'] as $param) {
            $params[] = $param;
        }

        $where_sql = implode(' AND ', $where);
        $boolean = $analysis['boolean'];

        if ($boolean !== '') {
            $order_sql = $this->candidate_order_sql($analysis, true);
            $sql = "SELECT *, MATCH(title, sku, categories, tags, attributes, color_terms, size_terms, search_text, stem_text) AGAINST (%s IN BOOLEAN MODE) AS ft_score FROM {$table} WHERE {$where_sql} AND MATCH(title, sku, categories, tags, attributes, color_terms, size_terms, search_text, stem_text) AGAINST (%s IN BOOLEAN MODE) ORDER BY {$order_sql} LIMIT %d";
            $params_for_fulltext = array_merge(array($boolean), $params, array($boolean, absint($limit)));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic clauses are selected from internal allowlists and all shopper values use placeholders.
            $rows = $wpdb->get_results($wpdb->prepare($sql, $params_for_fulltext));

            // An empty result and a failed query are not the same thing, and
            // treating them the same is how a broken FULLTEXT index hid here
            // indefinitely: MySQL error 1191 made every match fail, the empty
            // array fell through to the LIKE pass below, and search silently ran
            // with no relevance ranking at all. Record the fault so HealthService
            // can report it instead of leaving it to be discovered by accident.
            if (!empty($wpdb->last_error)) {
                HealthService::record_fulltext_failure($wpdb->last_error);
            } elseif (!empty($rows)) {
                HealthService::record_fulltext_success();
            }

            if (!empty($rows)) {
                return $rows;
            }
        }

        $core_terms = array_values(array_filter((array) ($analysis['core_terms'] ?? array())));
        $has_structured_intent = !empty($analysis['price_range'])
            || $analysis['intent'] !== 'search'
            || !empty($analysis['in_stock_only'])
            || !empty($analysis['color_terms'])
            || !empty($analysis['size_terms'])
            || !empty($analysis['modifier_terms'])
            || !empty($analysis['is_gift_request'])
            || !empty($analysis['decision_modes']);

        if (empty($core_terms) && $has_structured_intent) {
            $params_for_browse = $params;
            $params_for_browse[] = absint($limit);
            $order_sql = $this->candidate_order_sql($analysis, false);
            $sql = "SELECT *, 0 AS ft_score FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic clauses are selected from internal allowlists and values use placeholders.
            return $wpdb->get_results($wpdb->prepare($sql, $params_for_browse));
        }

        $like_groups = array();
        $like_params = array();

        if (!empty($analysis['phrase'])) {
            $like = '%' . $wpdb->esc_like($analysis['phrase']) . '%';
            $like_groups[] = '(title LIKE %s OR sku LIKE %s OR categories LIKE %s OR tags LIKE %s OR attributes LIKE %s OR color_terms LIKE %s OR size_terms LIKE %s OR search_text LIKE %s)';
            array_push($like_params, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        $like_terms = !empty($analysis['core_terms']) ? (array) $analysis['core_terms'] : (array) $analysis['terms'];
        foreach (array_slice($like_terms, 0, 6) as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';
            $like_groups[] = '(title LIKE %s OR sku LIKE %s OR categories LIKE %s OR tags LIKE %s OR attributes LIKE %s OR color_terms LIKE %s OR size_terms LIKE %s OR search_text LIKE %s)';
            array_push($like_params, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        if (empty($like_groups)) {
            return array();
        }

        $where[] = '(' . implode(' OR ', $like_groups) . ')';
        $where_sql = implode(' AND ', $where);
        $params_for_like = array_merge($params, $like_params, array(absint($limit)));
        $order_sql = $this->candidate_order_sql($analysis, false);
        $sql = "SELECT *, 0 AS ft_score FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic clauses are selected from internal allowlists and all LIKE values use placeholders.
        return $wpdb->get_results($wpdb->prepare($sql, $params_for_like));
    }

    private function candidate_order_sql($analysis, $with_fulltext = false) {
        $prefix = $with_fulltext ? 'ft_score DESC, ' : '';
        $stock = "CASE WHEN stock_status = 'instock' THEN 0 ELSE 1 END";

        if (!empty($analysis['value_sort'])) {
            return $prefix . $stock . ", is_on_sale DESC, rating DESC, total_sales DESC, CASE WHEN price BETWEEN 20 AND 90 THEN 0 ELSE 1 END, updated_at DESC";
        }

        if (!empty($analysis['is_gift_request'])) {
            return $prefix . $stock . ", rating DESC, total_sales DESC, is_on_sale DESC, CASE WHEN price BETWEEN 12 AND 120 THEN 0 ELSE 1 END, updated_at DESC";
        }

        if (!empty($analysis['budget_sort'])) {
            return $prefix . $stock . ", is_on_sale DESC, rating DESC, total_sales DESC, CASE WHEN price BETWEEN 10 AND 80 THEN 0 ELSE 1 END, CASE WHEN price IS NULL OR price <= 0 THEN 1 ELSE 0 END, price ASC, updated_at DESC";
        }

        if (!empty($analysis['intent']) && $analysis['intent'] === 'popular') {
            return $prefix . $stock . ", total_sales DESC, rating DESC, is_on_sale DESC, updated_at DESC";
        }

        if (!empty($analysis['intent']) && $analysis['intent'] === 'top_rated') {
            return $prefix . $stock . ", rating DESC, total_sales DESC, updated_at DESC";
        }

        return $prefix . $stock . ", rating DESC, total_sales DESC, is_on_sale DESC, updated_at DESC";
    }

    private function score_row($row, $analysis) {
        if (!$this->row_matches_requested_facets($row, $analysis)) {
            return 0;
        }

        if (!$this->row_matches_core_product_terms($row, $analysis)) {
            return 0;
        }

        $score = isset($row->ft_score) ? ((float) $row->ft_score * 15) : 0;
        $haystack = ' ' . $this->normalize_index_text($row->title . ' ' . $row->sku . ' ' . $row->categories . ' ' . $row->tags . ' ' . $row->attributes . ' ' . $row->color_terms . ' ' . $row->size_terms . ' ' . $row->search_text) . ' ';
        $title = function_exists('mb_strtolower') ? mb_strtolower((string) $row->title) : strtolower((string) $row->title);
        $sku = function_exists('mb_strtolower') ? mb_strtolower((string) $row->sku) : strtolower((string) $row->sku);

        // Natural product phrases such as "running shoes" should outrank a
        // generic shoe that only matches the size. This remains a ranking bonus,
        // not another hard filter, so small catalogs still return useful options.
        $query_text = !empty($analysis['lower']) ? (string) $analysis['lower'] : (!empty($analysis['raw']) ? (string) $analysis['raw'] : '');
        $score += $this->shared_query_phrase_bonus($query_text, (string) $row->title, (string) $row->categories);
        $score += $this->product_family_relevance_score($row, $analysis);

        $matched_core_count = 0;
        foreach ((array) ($analysis['core_terms'] ?? array()) as $core_term) {
            $identity_text = (string) $row->title . ' ' . (string) $row->sku . ' ' . (string) $row->categories . ' ' . (string) $row->tags . ' ' . (string) $row->attributes;
            if ($this->row_has_term_or_stem($row, $identity_text, $core_term)) {
                $matched_core_count++;
            }
        }
        if ($matched_core_count > 1) {
            $score += min(36, $matched_core_count * 12);
        }

        if ($analysis['phrase'] && strpos($title, $analysis['phrase']) !== false) {
            $score += 80;
        }
        if ($analysis['phrase'] && $sku !== '' && strpos($sku, $analysis['phrase']) !== false) {
            $score += 120;
        }

        $score_terms = !empty($analysis['core_terms']) ? (array) $analysis['core_terms'] : (array) $analysis['terms'];
        foreach ($score_terms as $term) {
            if ($term === '') {
                continue;
            }
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', $title)) {
                $score += 22;
            }
            if ($sku !== '' && strpos($sku, $term) !== false) {
                $score += 36;
            }
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', (string) $row->categories)) {
                $score += 18;
            }
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', (string) $row->tags)) {
                $score += 14;
            }
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', (string) $row->attributes)) {
                $score += 16;
            }
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', (string) $row->color_terms)) {
                $score += 34;
            }
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', (string) $row->size_terms)) {
                $score += 34;
            }
            if (strpos($haystack, ' ' . $term . ' ') !== false) {
                $score += 5;
            }
        }

        foreach ((array) $analysis['color_terms'] as $color_term) {
            if ($this->row_text_has_term((string) $row->color_terms . ' ' . (string) $row->attributes . ' ' . (string) $row->title . ' ' . (string) $row->tags, $color_term)) {
                $score += 70;
            }
        }
        foreach ((array) $analysis['size_terms'] as $size_term) {
            if ($this->row_text_has_term((string) $row->size_terms . ' ' . (string) $row->attributes . ' ' . (string) $row->title . ' ' . (string) $row->tags, $size_term)) {
                $score += 70;
            }
        }

        $matched_color_count = 0;
        foreach ((array) $analysis['color_terms'] as $color_term) {
            if ($this->row_text_has_term((string) $row->color_terms, $color_term)) {
                $matched_color_count++;
            }
        }
        if ($matched_color_count > 1) {
            $score += min(60, $matched_color_count * 18);
        }

        $matched_size_count = 0;
        foreach ((array) $analysis['size_terms'] as $size_term) {
            if ($this->row_text_has_term((string) $row->size_terms, $size_term)) {
                $matched_size_count++;
            }
        }
        if ($matched_size_count > 0) {
            $score += min(40, $matched_size_count * 16);
        }

        $buyer_text = (string) $row->title . ' ' . (string) $row->categories . ' ' . (string) $row->tags . ' ' . (string) $row->attributes . ' ' . (string) $row->short_description . ' ' . (string) $row->full_description;
        $modifier_weights = !empty($analysis['modifier_weights']) && is_array($analysis['modifier_weights']) ? $analysis['modifier_weights'] : array();

        foreach ((array) ($analysis['modifier_terms'] ?? array()) as $modifier_term) {
            if (!$this->row_text_has_term($buyer_text, $modifier_term)) {
                continue;
            }

            $weight = isset($modifier_weights[$modifier_term]) ? absint($modifier_weights[$modifier_term]) : 14;
            $score += max(4, min(30, $weight));
        }

        // Gift suitability stays generic. We prefer products explicitly described
        // as giftable, useful, popular, or easy to choose, instead of hardcoding
        // a list of product names that would only fit one catalog.
        if (!empty($analysis['is_gift_request'])) {
            $gift_signals = !empty($analysis['gift_signals']) && is_array($analysis['gift_signals']) ? $analysis['gift_signals'] : array();

            $gift_hits = $this->count_matching_terms($buyer_text, isset($gift_signals['gift_terms']) ? $gift_signals['gift_terms'] : array());
            if ($gift_hits > 0) {
                $score += min(28, $gift_hits * 20);
            }

            $useful_hits = $this->count_matching_terms($buyer_text, isset($gift_signals['useful_terms']) ? $gift_signals['useful_terms'] : array());
            if ($useful_hits > 0) {
                $score += min(20, $useful_hits * 14);
            }

            $easy_choice_hits = $this->count_matching_terms($buyer_text, isset($gift_signals['easy_choice_terms']) ? $gift_signals['easy_choice_terms'] : array());
            if ($easy_choice_hits > 0) {
                $score += min(10, $easy_choice_hits * 6);
            } elseif (trim((string) $row->size_terms) === '') {
                $score += 3;
            }

            $score += min(14, log(max(1, (int) $row->total_sales) + 1) * 3);
            $score += min(8, (float) $row->rating * 2);
        }

        // Recipient information is only a tie-breaker. Explicit audience data can
        // help, while missing audience data remains neutral and never excludes a
        // useful product from a small catalog.
        $audience = !empty($analysis['audience']) && is_array($analysis['audience']) ? $analysis['audience'] : array();
        if (!empty($audience)) {
            $positive_hits = $this->count_matching_terms($buyer_text, isset($audience['positive_terms']) ? $audience['positive_terms'] : array());
            if ($positive_hits > 0) {
                $score += min(24, $positive_hits * 16);
            }

            $negative_hits = $this->count_matching_terms($buyer_text, isset($audience['negative_terms']) ? $audience['negative_terms'] : array());
            if ($negative_hits > 0) {
                $score -= min(50, $negative_hits * 28);
            }
        }

        $decision_modes = !empty($analysis['decision_modes']) ? (array) $analysis['decision_modes'] : array();
        if (in_array('popular', $decision_modes, true)) {
            $score += min(24, log(max(1, (int) $row->total_sales) + 1) * 5);
            $score += min(10, (float) $row->rating * 2);
        }
        if (in_array('top_rated', $decision_modes, true)) {
            $score += min(30, (float) $row->rating * 6);
        }

        if ($analysis['intent'] === 'top_rated' && Settings::get('search_boost_rating', 'yes') === 'yes') {
            $score += ((float) $row->rating * 12);
        }
        if ($analysis['intent'] === 'popular' && Settings::get('search_boost_popularity', 'yes') === 'yes') {
            $score += min(40, log(max(1, (int) $row->total_sales) + 1) * 8);
        }
        if ($this->analysis_requires_sale($analysis) && Settings::get('search_boost_sale', 'yes') === 'yes' && (!empty($row->is_on_sale) || (float) $row->sale_price > 0)) {
            $score += 30;
        }
        if ($analysis['intent'] === 'latest') {
            $age = strtotime((string) $row->created_at);
            if ($age) {
                $score += max(0, 30 - ((time() - $age) / DAY_IN_SECONDS));
            }
        }

        if (!empty($analysis['budget_sort']) && (float) $row->price > 0) {
            $price = (float) $row->price;
            if ($price <= 25) {
                $score += 14;
            } elseif ($price <= 50) {
                $score += 18;
            } elseif ($price <= 80) {
                $score += 12;
            } elseif ($price <= 120) {
                $score += 5;
            } else {
                $score -= 4;
            }
        }

        if (!empty($analysis['value_sort']) && (float) $row->price > 0) {
            if ((float) $row->price >= 20 && (float) $row->price <= 90) {
                $score += 18;
            }
            if ((float) $row->price < 15) {
                $score -= 12;
            }
            if (!empty($row->is_on_sale) || (float) $row->sale_price > 0) {
                $score += 16;
            }
            if ($row->stock_status === 'instock') {
                $score += 10;
            }
        }

        if (Settings::get('search_boost_in_stock', 'yes') === 'yes' && $row->stock_status === 'instock') {
            $score += 8;
        }
        if (Settings::get('search_boost_rating', 'yes') === 'yes') {
            $score += min(18, (float) $row->rating * 3);
        }
        if (Settings::get('search_boost_popularity', 'yes') === 'yes') {
            $score += min(12, log(max(1, (int) $row->total_sales) + 1) * 2);
        }
        if (Settings::get('search_boost_sale', 'yes') === 'yes' && (!empty($row->is_on_sale) || (float) $row->sale_price > 0)) {
            $score += 4;
        }

        $minimum_score = max(1, absint(Settings::get('search_min_score', 1)));
        return $score >= $minimum_score ? $score : 0;
    }

    /**
     * Makes exact product-family evidence stronger than generic tags or buyer
     * modifiers. This keeps a travel organiser ahead of a cap tagged `travel`,
     * and a laptop backpack ahead of a generic tote.
     */
    private function product_family_relevance_score($row, $analysis) {
        $family = !empty($analysis['product_family_term']) ? $this->normalize_index_text($analysis['product_family_term']) : '';
        if ($family === '') {
            return 0;
        }

        $aliases = !empty($analysis['family_gate_aliases'])
            ? (array) $analysis['family_gate_aliases']
            : (!empty($analysis['required_family_aliases'])
                ? (array) $analysis['required_family_aliases']
                : (!empty($analysis['product_family_aliases']) ? (array) $analysis['product_family_aliases'] : $this->product_family_aliases($family)));
        $qualifiers = !empty($analysis['product_qualifier_terms']) ? (array) $analysis['product_qualifier_terms'] : array();
        $title = (string) $row->title;
        $categories = (string) $row->categories;
        $tags = (string) $row->tags;
        $attributes = (string) $row->attributes;
        $score = 0;

        foreach ($aliases as $alias) {
            if ($this->row_text_has_term($title, $alias)) {
                $score = max($score, 150);
            } elseif ($this->row_text_has_term($categories, $alias)) {
                $score = max($score, 115);
            } elseif ($this->row_text_has_term($tags, $alias)) {
                $score = max($score, 55);
            } elseif ($this->row_text_has_term($attributes, $alias)) {
                $score = max($score, 35);
            }
        }

        foreach ($qualifiers as $qualifier) {
            if ($this->row_text_has_term($title, $qualifier)) {
                $score += 55;
            } elseif ($this->row_text_has_term($categories, $qualifier)) {
                $score += 40;
            } elseif ($this->row_text_has_term($tags, $qualifier)) {
                $score += 18;
            } elseif ($this->row_text_has_term($attributes, $qualifier)) {
                $score += 12;
            }
        }

        return min(260, $score);
    }

    private function shared_query_phrase_bonus($query, $title, $categories = '') {
        $query = $this->normalize_index_text($query);
        $title = $this->normalize_index_text($title);
        $categories = $this->normalize_index_text($categories);
        if ($query === '' || $title === '') {
            return 0;
        }

        $stop = array(
            'show', 'find', 'give', 'need', 'want', 'looking', 'products', 'product',
            'something', 'anything', 'please', 'only', 'with', 'without', 'under',
            'below', 'between', 'available', 'stock', 'size', 'color', 'colour',
            'the', 'a', 'an', 'me', 'my', 'for', 'in', 'of', 'and', 'or', 'to',
        );
        $tokens = array();
        foreach (preg_split('/\s+/u', $query) as $token) {
            $token = trim((string) $token);
            if ($token === '' || is_numeric($token) || strlen($token) < 2 || in_array($token, $stop, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        $count = count($tokens);
        if ($count < 2) {
            return 0;
        }

        $best = 0;
        $max_words = min(4, $count);
        for ($length = $max_words; $length >= 2; $length--) {
            for ($start = 0; $start <= $count - $length; $start++) {
                $phrase = implode(' ', array_slice($tokens, $start, $length));
                if (strpos(' ' . $title . ' ', ' ' . $phrase . ' ') !== false) {
                    $best = max($best, 170 + (($length - 2) * 30));
                } elseif ($categories !== '' && strpos(' ' . $categories . ' ', ' ' . $phrase . ' ') !== false) {
                    $best = max($best, 105 + (($length - 2) * 24));
                }
            }
        }

        return $best;
    }

    public function debug_scores($query, $limit = 20) {
        if (!$this->is_ready()) {
            self::create_table();
        }

        if (!$this->count_indexed()) {
            $this->rebuild(300);
        }

        $query = $this->clean_query($query);
        if ($query === '') {
            return array();
        }

        $analysis = $this->analyze_query($query);
        $rows = $this->candidate_rows($analysis, max(20, absint($limit) * 4));
        if (empty($rows)) {
            return array();
        }

        $debug = array();
        foreach ($rows as $row) {
            $score = $this->score_row($row, $analysis);
            if ($score <= 0) {
                continue;
            }
            $debug[absint($row->product_id)] = array(
                'score' => round((float) $score, 1),
                'reasons' => $this->debug_reasons_for_row($row, $analysis),
            );
        }

        return $debug;
    }

    private function debug_reasons_for_row($row, $analysis) {
        $reasons = array();
        $product_text = (string) $row->title . ' ' . (string) $row->sku . ' ' . (string) $row->categories . ' ' . (string) $row->tags . ' ' . (string) $row->attributes;
        $all_text = $product_text . ' ' . (string) $row->color_terms . ' ' . (string) $row->size_terms . ' ' . (string) $row->short_description . ' ' . (string) $row->full_description . ' ' . (string) $row->search_text;

        $matched_core = array();
        $core_display = !empty($analysis['display_core_terms']) ? (array) $analysis['display_core_terms'] : (array) ($analysis['core_terms'] ?? array());
        foreach ($core_display as $term) {
            if ($this->row_text_has_term($product_text, $term)) {
                $matched_core[] = $term;
            }
        }
        if (!empty($matched_core)) {
            $reasons[] = sprintf(
                /* translators: %s: comma-separated matched product or category terms. */
                __('Product/category matched: %s', 'geeky-bot'),
                implode(', ', array_slice(array_values(array_unique($matched_core)), 0, 4))
            );
        }

        $matched_colors = array();
        foreach ((array) ($analysis['color_terms'] ?? array()) as $term) {
            if ($this->row_text_has_term((string) $row->color_terms . ' ' . (string) $row->attributes . ' ' . (string) $row->title . ' ' . (string) $row->tags, $term)) {
                $matched_colors[] = $term;
            }
        }
        if (!empty($matched_colors)) {
            $labels = !empty($analysis['requested_color_labels']) ? (array) $analysis['requested_color_labels'] : $matched_colors;
            $reasons[] = sprintf(
                /* translators: %s: comma-separated matched product colors. */
                __('Color matched: %s', 'geeky-bot'),
                implode(', ', array_slice(array_values(array_unique($labels)), 0, 4))
            );
        }

        $matched_sizes = array();
        foreach ((array) ($analysis['size_terms'] ?? array()) as $term) {
            if ($this->row_text_has_term((string) $row->size_terms . ' ' . (string) $row->attributes . ' ' . (string) $row->title . ' ' . (string) $row->tags, $term)) {
                $matched_sizes[] = $term;
            }
        }
        if (!empty($matched_sizes)) {
            $labels = !empty($analysis['requested_size_labels']) ? (array) $analysis['requested_size_labels'] : $matched_sizes;
            $reasons[] = sprintf(
                /* translators: %s: comma-separated matched product sizes. */
                __('Size matched: %s', 'geeky-bot'),
                strtoupper(implode(', ', array_slice(array_values(array_unique($labels)), 0, 4)))
            );
        }

        if (!empty($analysis['price_range']) && is_array($analysis['price_range'])) {
            $price = isset($row->price) ? (float) $row->price : 0;
            $in_range = $price > 0;
            if (isset($analysis['price_range']['min']) && $analysis['price_range']['min'] !== null && $price < (float) $analysis['price_range']['min']) {
                $in_range = false;
            }
            if (isset($analysis['price_range']['max']) && $analysis['price_range']['max'] !== null && $price > (float) $analysis['price_range']['max']) {
                $in_range = false;
            }
            if ($in_range) {
                $reasons[] = __('Price filter matched', 'geeky-bot');
            }
        }

        $matched_modifiers = array();
        foreach ((array) ($analysis['modifier_terms'] ?? array()) as $term) {
            if ($this->row_text_has_term($all_text, $term)) {
                $matched_modifiers[] = $term;
            }
        }
        if (!empty($matched_modifiers)) {
            $labels = !empty($analysis['modifier_labels']) ? (array) $analysis['modifier_labels'] : $matched_modifiers;
            $reasons[] = sprintf(
                /* translators: %s: comma-separated matched shopper preferences. */
                __('Soft preference matched: %s', 'geeky-bot'),
                implode(', ', array_slice(array_values(array_unique($labels)), 0, 3))
            );
        } elseif (!empty($analysis['modifier_labels'])) {
            $reasons[] = sprintf(
                /* translators: %s: comma-separated requested shopper preferences. */
                __('Soft preference requested: %s', 'geeky-bot'),
                implode(', ', array_slice(array_values(array_unique((array) $analysis['modifier_labels'])), 0, 3))
            );
        }

        if (!empty($analysis['audience']) && is_array($analysis['audience'])) {
            $audience = $analysis['audience'];
            $audience_text = (string) $row->title . ' ' . (string) $row->categories . ' ' . (string) $row->tags . ' ' . (string) $row->attributes . ' ' . (string) $row->short_description . ' ' . (string) $row->full_description;
            $matched_audience = array();
            foreach ((array) ($audience['positive_terms'] ?? array()) as $term) {
                if ($this->row_text_has_term($audience_text, $term)) {
                    $matched_audience[] = $term;
                }
            }
            if (!empty($matched_audience)) {
                $reasons[] = sprintf(
                    /* translators: %s: comma-separated matched recipient preferences. */
                    __('Recipient preference boost: %s', 'geeky-bot'),
                    implode(', ', array_slice(array_values(array_unique($matched_audience)), 0, 3))
                );
            }
        }

        if (!empty($analysis['value_sort'])) {
            $reasons[] = __('Best-value ranking applied', 'geeky-bot');
        }
        if (Settings::get('search_boost_in_stock', 'yes') === 'yes' && (string) $row->stock_status === 'instock') {
            $reasons[] = __('In-stock boost applied', 'geeky-bot');
        }
        if (Settings::get('search_boost_sale', 'yes') === 'yes' && (!empty($row->is_on_sale) || (float) $row->sale_price > 0)) {
            $reasons[] = __('Sale boost applied', 'geeky-bot');
        }
        if (Settings::get('search_boost_rating', 'yes') === 'yes' && (float) $row->rating > 0) {
            $reasons[] = sprintf(
                /* translators: %s: product rating. */
                __('Rating boost: %s', 'geeky-bot'),
                number_format_i18n((float) $row->rating, 1)
            );
        }
        if (Settings::get('search_boost_popularity', 'yes') === 'yes' && (int) $row->total_sales > 0) {
            $reasons[] = sprintf(
                /* translators: %d: product sales count. */
                __('Popularity boost: %d sales', 'geeky-bot'),
                absint($row->total_sales)
            );
        }

        return array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $reasons))));
    }

    private function term_names($product_id, $taxonomy) {
        $terms = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'names'));
        return is_wp_error($terms) ? array() : array_map('wp_strip_all_tags', (array) $terms);
    }

    private function attribute_data($product) {
        $chunks = array();
        $color_terms = array();
        $size_terms = array();
        $all_attribute_text = array();
        $language = $this->search_language();

        foreach ((array) $product->get_attributes() as $attribute) {
            if (!is_object($attribute)) {
                continue;
            }

            $attribute_name = method_exists($attribute, 'get_name') ? (string) $attribute->get_name() : '';
            $label = wc_attribute_label($attribute_name);
            $values = array();
            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product->get_id(), $attribute_name, array('fields' => 'names'));
                if (!is_wp_error($terms)) {
                    $values = $terms;
                }
            } else {
                $values = $attribute->get_options();
            }

            $values = array_filter(array_map('wp_strip_all_tags', (array) $values));
            if (!empty($values)) {
                $chunks[] = trim($label . ': ' . implode(', ', $values));
                $all_attribute_text[] = $label . ' ' . $attribute_name . ' ' . implode(' ', $values);

                if ($language->is_color_attribute_label($label . ' ' . $attribute_name)) {
                    $color_terms = array_merge($color_terms, $language->facet_terms_for_index($values, 'color'));
                }
                if ($language->is_size_attribute_label($label . ' ' . $attribute_name)) {
                    $size_terms = array_merge($size_terms, $language->facet_terms_for_index($values, 'size'));
                }
            }
        }

        if ($product->is_type('variable')) {
            foreach ((array) $product->get_children() as $variation_id) {
                $variation = function_exists('wc_get_product') ? wc_get_product($variation_id) : null;
                if (!$variation) {
                    continue;
                }
                foreach ((array) $variation->get_attributes() as $attribute_name => $value) {
                    $label = wc_attribute_label((string) $attribute_name);
                    $value = wp_strip_all_tags((string) $value);
                    if ($value === '') {
                        continue;
                    }
                    $chunks[] = trim($label . ': ' . $value);
                    $all_attribute_text[] = $label . ' ' . $attribute_name . ' ' . $value;
                    if ($language->is_color_attribute_label($label . ' ' . $attribute_name)) {
                        $color_terms = array_merge($color_terms, $language->facet_terms_for_index(array($value), 'color'));
                    }
                    if ($language->is_size_attribute_label($label . ' ' . $attribute_name)) {
                        $size_terms = array_merge($size_terms, $language->facet_terms_for_index(array($value), 'size'));
                    }
                }
            }
        }

        $detected = $language->product_facets_from_query(implode(' ', $all_attribute_text), array());
        $color_terms = array_merge($color_terms, isset($detected['colors']) ? (array) $detected['colors'] : array());
        $size_terms = array_merge($size_terms, isset($detected['sizes']) ? (array) $detected['sizes'] : array());

        return array(
            'text' => implode(' | ', array_values(array_unique(array_filter($chunks)))),
            'color_terms' => implode(' ', array_values(array_unique(array_filter(array_map(array($language, 'normalize_text'), $color_terms))))),
            'size_terms' => implode(' ', array_values(array_unique(array_filter(array_map(array($language, 'normalize_text'), $size_terms))))),
        );
    }

    private function structured_query_where($analysis) {
        global $wpdb;

        $where = array();
        $params = array();

        $core_clauses = array();
        $core_params = array();
        foreach (array_slice((array) ($analysis['core_terms'] ?? array()), 0, 5) as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '') {
                continue;
            }
            $like = '%' . $wpdb->esc_like($term) . '%';

            // stem_text carries a stemmed copy of the identity fields, so this
            // clause has to be stem-aware too. Without it the fulltext match can
            // find a product by its stem while this gate rejects it on the raw
            // wording, which is exactly how "beanies" -- singularised upstream to
            // the non-word "beany" -- matched nothing at all.
            $stem = $this->stemmer()->stem($term);
            $stem_like = '%' . $wpdb->esc_like($stem) . '%';

            // facet_text is the normalized copy of title, sku, categories,
            // tags and the attribute/colour/size terms, so one clause now
            // covers what five raw-column comparisons used to -- and, unlike
            // them, it is in the same normalized form as $term. sku stays as a
            // separate raw comparison because normalization splits a SKU on its
            // punctuation, and a shopper pasting a SKU types it verbatim.
            $core_clauses[] = '(facet_text LIKE %s OR sku LIKE %s OR stem_text LIKE %s)';
            array_push($core_params, $like, $like, $stem_like);
        }
        if (!empty($core_clauses)) {
            $where[] = '(' . implode(' OR ', $core_clauses) . ')';
            foreach ($core_params as $param) {
                $params[] = $param;
            }
        }

        $color_clauses = array();
        $color_params = array();
        foreach (array_slice((array) ($analysis['color_terms'] ?? array()), 0, 8) as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '') {
                continue;
            }
            $like = '%' . $wpdb->esc_like($term) . '%';
            $color_clauses[] = '(facet_text LIKE %s)';
            $color_params[] = $like;
        }
        if (!empty($color_clauses)) {
            $where[] = '(' . implode(' OR ', $color_clauses) . ')';
            foreach ($color_params as $param) {
                $params[] = $param;
            }
        }

        $size_clauses = array();
        $size_params = array();
        foreach (array_slice((array) ($analysis['size_terms'] ?? array()), 0, 8) as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '') {
                continue;
            }
            $like = '%' . $wpdb->esc_like($term) . '%';
            $size_clauses[] = '(facet_text LIKE %s)';
            $size_params[] = $like;
        }
        if (!empty($size_clauses)) {
            $where[] = '(' . implode(' OR ', $size_clauses) . ')';
            foreach ($size_params as $param) {
                $params[] = $param;
            }
        }

        foreach (array_slice((array) ($analysis['negative_color_terms'] ?? array()), 0, 6) as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '') {
                continue;
            }
            $like = '%' . $wpdb->esc_like($term) . '%';
            $where[] = '(facet_text NOT LIKE %s)';
            $params[] = $like;
        }

        foreach (array_slice((array) ($analysis['negative_size_terms'] ?? array()), 0, 6) as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '') {
                continue;
            }
            $like = '%' . $wpdb->esc_like($term) . '%';
            $where[] = '(facet_text NOT LIKE %s)';
            $params[] = $like;
        }

        return array('where' => $where, 'params' => $params);
    }

    private function row_matches_requested_facets($row, $analysis) {
        $colors = isset($analysis['color_terms']) ? array_filter((array) $analysis['color_terms']) : array();
        $sizes = isset($analysis['size_terms']) ? array_filter((array) $analysis['size_terms']) : array();
        $negative_colors = isset($analysis['negative_color_terms']) ? array_filter((array) $analysis['negative_color_terms']) : array();
        $negative_sizes = isset($analysis['negative_size_terms']) ? array_filter((array) $analysis['negative_size_terms']) : array();

        $color_text = (string) $row->color_terms . ' ' . (string) $row->attributes . ' ' . (string) $row->title . ' ' . (string) $row->tags;
        $size_text = (string) $row->size_terms . ' ' . (string) $row->attributes . ' ' . (string) $row->title . ' ' . (string) $row->tags;

        if (!empty($negative_colors) && $this->row_matches_any_term($color_text, $negative_colors)) {
            return false;
        }
        if (!empty($negative_sizes) && $this->row_matches_any_term($size_text, $negative_sizes)) {
            return false;
        }

        if (!empty($colors)) {
            $strict_color_text = (string) $row->color_terms;
            $fallback_color_text = (string) $row->attributes . ' ' . (string) $row->title . ' ' . (string) $row->tags;
            if (!$this->row_matches_any_term($strict_color_text, $colors) && !$this->row_matches_any_term($fallback_color_text, $colors)) {
                return false;
            }
        }

        if (!empty($sizes)) {
            $strict_size_text = (string) $row->size_terms;
            $fallback_size_text = (string) $row->attributes . ' ' . (string) $row->title . ' ' . (string) $row->tags;
            if (!$this->row_matches_any_term($strict_size_text, $sizes) && !$this->row_matches_any_term($fallback_size_text, $sizes)) {
                return false;
            }
        }

        return true;
    }

    private function row_matches_core_product_terms($row, $analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        if (!empty($analysis['product_family_term'])) {
            return $this->row_matches_product_phrase($row, $analysis);
        }

        $core_terms = isset($analysis['core_terms']) ? array_filter((array) $analysis['core_terms']) : array();
        if (empty($core_terms)) {
            return true;
        }

        $product_text = (string) $row->title . ' ' . (string) $row->sku . ' ' . (string) $row->categories . ' ' . (string) $row->tags . ' ' . (string) $row->attributes . ' ' . (string) $row->search_text;
        foreach ($core_terms as $term) {
            if ($this->row_has_term_or_stem($row, $product_text, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a row carries a term in its own wording, or the term's stem in the
     * stemmed copy of that wording.
     *
     * Comparing a stem against raw catalog text, or a raw term against stem_text,
     * proves nothing -- the two sides have to be reduced by the same function
     * before they can be compared. Query tokens are singularised upstream, so a
     * shopper typing "beanies" arrives here as the non-word "beany"; only its
     * stem, `beani`, can meet the indexed "Beanie".
     *
     * @param object $row          Product index row.
     * @param string $product_text Raw text already assembled by the caller.
     * @param string $term         Query term.
     * @return bool
     */
    private function row_has_term_or_stem($row, $product_text, $term) {
        if ($this->row_text_has_term($product_text, $term)) {
            return true;
        }

        $stem = $this->stemmer()->stem($this->normalize_index_text($term));
        if ($stem === '' || $stem === $term) {
            return false;
        }

        return $this->row_text_has_term((string) ($row->stem_text ?? ''), $stem);
    }

    private function row_matches_product_phrase($row, $analysis) {
        $family = !empty($analysis['product_family_term']) ? $this->normalize_index_text($analysis['product_family_term']) : '';
        if ($family === '') {
            return true;
        }

        $family_aliases = !empty($analysis['product_family_aliases'])
            ? (array) $analysis['product_family_aliases']
            : $this->product_family_aliases($family);
        $required_aliases = !empty($analysis['family_gate_aliases'])
            ? (array) $analysis['family_gate_aliases']
            : (!empty($analysis['required_family_aliases'])
                ? (array) $analysis['required_family_aliases']
                : $family_aliases);
        // Family identity must come from the fields that say what a product
        // *is*: its name, SKU, categories and tags. Tags belong here because a
        // tag is a label the merchant chose for that product, which is how the
        // gate reads the store's own vocabulary -- a black heel filed under
        // "Footwear" but tagged "Party Shoes" is still a shoe. Descriptive copy
        // does not: "bottle pocket" on a backpack must never make that backpack
        // eligible for a water-bottle search, which is why attributes and
        // search_text widen $product_text below but never the gate.
        $family_identity_text = (string) $row->title . ' ' . (string) $row->sku . ' ' . (string) $row->categories . ' ' . (string) $row->tags;
        $product_text = $family_identity_text . ' ' . (string) $row->attributes . ' ' . (string) $row->search_text;
        if (!$this->row_matches_any_term($family_identity_text, $required_aliases)) {
            return false;
        }

        // A qualifier the merchant declared as half of a category name is always
        // required, even when it doubles as a neighbour of the family. "Tote
        // Bags" makes `tote` a bag alias AND the word that distinguishes a tote
        // from every other bag; skipping it returned pouches for "tote bag".
        foreach ((array) ($analysis['product_declared_qualifier_terms'] ?? array()) as $declared) {
            $declared = $this->normalize_index_text($declared);
            if ($declared === '' || $declared === $family) {
                continue;
            }
            if (!$this->row_text_has_qualifier($product_text, $declared, $analysis)) {
                return false;
            }
        }

        $qualifiers = !empty($analysis['product_qualifier_terms'])
            ? (array) $analysis['product_qualifier_terms']
            : (array) ($analysis['product_phrase_terms'] ?? array());
        foreach ($qualifiers as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '' || in_array($term, $family_aliases, true) || in_array($term, $required_aliases, true) || $term === $family) {
                continue;
            }
            if (!$this->row_text_has_qualifier($product_text, $term, $analysis)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a row satisfies a required qualifier, in the shopper's wording or
     * in any wording synonym expansion offered for that same word.
     *
     * A qualifier is a hard requirement, and it has to stay one -- "tote bag"
     * must not return a pouch. But requiring the shopper's literal token also
     * requires their language: "ceramic mug" demanded the English `ceramic` of a
     * catalog whose cups say سيراميك, so the phrase gate threw the row away
     * after the fulltext pass had correctly found it. Accepting the alternates
     * of THIS word only -- never of some other word in the query -- keeps the
     * requirement exactly as narrow while letting it be met in the language the
     * products are written in.
     *
     * @param string $product_text Row text the qualifier may appear in.
     * @param string $term         Normalised qualifier.
     * @param array  $analysis     Query analysis, for expansion_alternates.
     * @return bool
     */
    private function row_text_has_qualifier($product_text, $term, $analysis) {
        if ($this->row_text_has_term($product_text, $term)) {
            return true;
        }

        $alternates = isset($analysis['expansion_alternates']) ? (array) $analysis['expansion_alternates'] : array();
        if (empty($alternates[$term])) {
            return false;
        }

        foreach ((array) $alternates[$term] as $alternate) {
            $alternate = $this->normalize_index_text($alternate);
            if ($alternate !== '' && $this->row_text_has_term($product_text, $alternate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds the family's own translations to its alias lists.
     *
     * `canonical_product_family()` and the learned vocabulary are both written
     * in the merchant's language, so `family_gate_aliases` for a query about a
     * mug is `mug | cup` and nothing else. row_matches_product_phrase() then
     * gates on title/sku/categories/tags, which for طقم أكواب سيراميك contains
     * neither -- the row passed the fulltext pass and was dropped here. The
     * alias lists are already OR sets of surface forms for one concept, so a
     * translation is exactly what they are for; widening them changes no
     * same-language behaviour because a translation of an English noun is not a
     * word an English catalog carries.
     *
     * @param array $product_phrase Result of product_phrase_profile().
     * @param array $alternates     Shopper token => alternates, from expansion.
     * @return array Product phrase with translated aliases folded in.
     */
    private function translate_family_aliases($product_phrase, $alternates) {
        $product_phrase = is_array($product_phrase) ? $product_phrase : array();
        $alternates = is_array($alternates) ? $alternates : array();
        if (empty($product_phrase['family']) || empty($alternates)) {
            return $product_phrase;
        }

        $seeds = array_values(array_unique(array_filter(array_map(
            array($this, 'normalize_index_text'),
            array_merge(
                array((string) $product_phrase['family']),
                array((string) ($product_phrase['family_source'] ?? '')),
                (array) ($product_phrase['required_family_aliases'] ?? array()),
                (array) ($product_phrase['family_aliases'] ?? array()),
                (array) ($product_phrase['family_gate_aliases'] ?? array())
            )
        ))));

        $translations = array();
        foreach ($seeds as $seed) {
            foreach ((array) ($alternates[$seed] ?? array()) as $alternate) {
                $alternate = $this->normalize_index_text($alternate);
                if ($alternate !== '' && !in_array($alternate, $seeds, true) && !in_array($alternate, $translations, true)) {
                    $translations[] = $alternate;
                }
            }
        }

        if (empty($translations)) {
            return $product_phrase;
        }

        foreach (array('family_aliases', 'required_family_aliases', 'family_gate_aliases') as $key) {
            $product_phrase[$key] = array_values(array_unique(array_filter(array_merge(
                (array) ($product_phrase[$key] ?? array()),
                $translations
            ))));
        }

        return $product_phrase;
    }

    private function count_matching_terms($text, $terms) {
        $count = 0;
        foreach ((array) $terms as $term) {
            if ($this->row_text_has_term($text, $term)) {
                $count++;
            }
        }
        return $count;
    }

    private function core_product_terms($terms, $color_terms, $size_terms, $negative_color_terms = array(), $negative_size_terms = array(), $modifier_terms = array()) {
        $ignored_terms = array();
        foreach (array_merge((array) $color_terms, (array) $size_terms, (array) $negative_color_terms, (array) $negative_size_terms, (array) $modifier_terms) as $term) {
            $term = $this->normalize_index_text($term);
            if ($term !== '') {
                $ignored_terms[$term] = true;
            }
        }

        $core = array();
        foreach ((array) $terms as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '' || isset($ignored_terms[$term]) || $this->remove_weak_shopper_terms(array($term)) === array()) {
                continue;
            }
            $core[] = $term;
        }

        return array_values(array_unique($core));
    }

    private function product_phrase_profile($query, $color_terms, $size_terms, $negative_color_terms, $negative_size_terms, $modifier_terms, $raw_query = '', $query_language = '') {
        $language = $this->search_language();
        $normalized = $language->normalize_text($query);
        // $query is the stripped text; $query_language was detected from the
        // shopper's original wording. See analyze_query().
        $terms = $language->query_terms($normalized, $query_language);

        // Sale words describe a commerce constraint, not product identity.
        // Without removing them here, `sale keyboards` becomes a hard phrase
        // requiring the catalog text itself to contain the word `sale`, which
        // can hide a genuinely discounted keyboard and trigger broad fallback.
        $intent = $language->catalog_intent($normalized);
        if ($intent === 'sale') {
            $terms = $language->remove_intent_terms($terms, $intent);
        }

        $excluded = array();
        foreach (array_merge(
            (array) $color_terms,
            (array) $size_terms,
            (array) $negative_color_terms,
            (array) $negative_size_terms
        ) as $term) {
            $term = $this->normalize_index_text($term);
            if ($term !== '') {
                $excluded[$term] = true;
            }
        }

        $clean = array();
        foreach ($terms as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '' || isset($excluded[$term]) || is_numeric($term)) {
                continue;
            }
            $clean[] = $term;
        }
        $clean = array_values(array_unique($clean));

        // A compound family the merchant declared, such as "Running Shoes" or
        // "T-Shirts", is matched against the normalised query rather than the
        // tokenised one. query_terms() drops anything too short to be a search
        // term, which discards the `t` in "t-shirt" -- and `t` is the entire
        // difference between a t-shirt and an office shirt.
        // Matched against the query BEFORE buyer-modifier stripping. "running"
        // is normally a lifestyle signal and is stripped from $query as such,
        // but a merchant with a "Running Shoes" category has said it is part of
        // the product's name in their shop. A declared compound outranks a
        // generic reading of the same word.
        $phrase_family = null;
        if (!empty($clean)) {
            $phrase_source = trim((string) $raw_query) !== '' ? $language->normalize_text($raw_query) : $normalized;
            $raw_tokens = preg_split('/\s+/u', $phrase_source, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($raw_tokens) && count($raw_tokens) > 1) {
                $raw_stems = array();
                foreach ($raw_tokens as $raw_token) {
                    $raw_stems[] = $this->stemmer()->stem($raw_token);
                }
                $phrase_family = $this->vocabulary()->match_phrase($raw_stems);
            }
        }

        $family = '';
        $family_index = -1;
        foreach ($clean as $index => $term) {
            $canonical = $this->canonical_product_family($term);
            if ($canonical !== '') {
                $family = $canonical;
                $family_index = $index;
            }
        }

        // The declared compound wins over the bare head noun it contains, but
        // only when the head noun is what the single-token scan already found.
        // Anything else means the shopper asked for something different.
        $forced_qualifiers = array();
        if ($phrase_family !== null && $phrase_family['canonical'] !== '') {
            $matches_head = $family === $phrase_family['canonical']
                || $family === '' 
                || in_array($phrase_family['canonical'], (array) $this->product_family_aliases($family), true);

            if ($matches_head) {
                $family = $phrase_family['canonical'];
                $forced_qualifiers = (array) $phrase_family['qualifiers'];
                if ($family_index < 0) {
                    $family_index = count($clean) - 1;
                }
            }
        }

        if ($family === '' || $family_index < 0) {
            return array('phrase' => '', 'terms' => array(), 'family' => '', 'family_source' => '', 'family_aliases' => array(), 'required_family_aliases' => array(), 'family_gate_aliases' => array(), 'qualifier_terms' => array(), 'search_terms' => array());
        }

        $modifier_map = array_fill_keys(array_map(array($this, 'normalize_index_text'), (array) $modifier_terms), true);
        $qualifier_allow = array_fill_keys(array(
            'running', 'walking', 'trail', 'water', 'resistant', 'waterproof', 'rain',
            'wireless', 'bluetooth', 'laptop', 'phone', 'travel', 'insulated', 'thermal',
            'yoga', 'resistance', 'cable', 'desk', 'office', 'gaming', 'sports', 'leather',
            'memory', 'foam', 'usb-c', 'usb', 'rfid', 'noise', 'anc', 'fleece'
        ), true);

        $phrase_terms = array($family);
        $prefix = array_slice($clean, max(0, $family_index - 2), min(2, $family_index));
        foreach ($prefix as $term) {
            if (isset($modifier_map[$term]) && !isset($qualifier_allow[$term])) {
                continue;
            }
            if ($this->canonical_product_family($term) !== '') {
                continue;
            }
            array_unshift($phrase_terms, $term);
        }
        $phrase_terms = array_values(array_unique(array_filter($phrase_terms)));
        $family_source = isset($clean[$family_index]) ? $this->normalize_index_text($clean[$family_index]) : $family;
        $aliases = $this->product_family_aliases($family);
        $required_aliases = $this->required_product_family_aliases($family, $family_source);
        $family_gate_aliases = $this->refinement_product_family_aliases($family, $family_source, $required_aliases);
        $qualifiers = array();
        foreach ($phrase_terms as $phrase_term) {
            $phrase_term = $this->normalize_index_text($phrase_term);
            if ($phrase_term === '' || $phrase_term === $family || in_array($phrase_term, $required_aliases, true) || in_array($phrase_term, $aliases, true)) {
                continue;
            }
            $qualifiers[] = $phrase_term;
        }
        foreach ($forced_qualifiers as $forced) {
            $forced = $this->normalize_index_text($forced);
            if ($forced !== '' && $forced !== $family && !in_array($forced, $qualifiers, true)) {
                $qualifiers[] = $forced;
            }
        }

        $qualifiers = array_values(array_unique($qualifiers));
        $search_terms = array_values(array_unique(array_filter(array_merge($phrase_terms, $required_aliases))));

        return array(
            'phrase' => implode(' ', $phrase_terms),
            'terms' => $phrase_terms,
            'family' => $family,
            'family_source' => $family_source,
            'family_aliases' => $aliases,
            'required_family_aliases' => $required_aliases,
            'family_gate_aliases' => $family_gate_aliases,
            'qualifier_terms' => $qualifiers,
            'declared_qualifier_terms' => array_values(array_unique(array_filter(array_map(
                array($this, 'normalize_index_text'),
                $forced_qualifiers
            )))),
            'search_terms' => $search_terms,
        );
    }

    /**
     * Maps a shopper's product noun onto a canonical family stem.
     *
     * Two rules govern every entry, and breaking either one silently empties
     * result sets:
     *
     * 1. The canonical value must be a SUBSTRING of every surface form it has
     *    to match, because `row_text_has_term()` matches by substring on the
     *    product's identity text (title + sku + categories). That is why the
     *    stems below are `accessor` (accessory / accessories) and `jean`
     *    (jean / jeans) rather than the tidy dictionary singular.
     *
     * 2. The canonical value must equal the single token used as this noun's
     *    hard gate in `required_product_family_aliases()`. `product_phrase()`
     *    seeds `phrase_terms` with the canonical and merges the gate list into
     *    `search_terms`, which `boolean_query()` turns into `+term*` for every
     *    entry -- a fulltext AND. A canonical that disagrees with its own gate
     *    compiles to a query no product can satisfy. The old
     *    `headphones => earbud` mapping produced `+earbud* +headphone*` and
     *    returned nothing while three headphones sat in the catalog;
     *    `pouch => organiser` and `cover => case` failed the same way.
     *
     * Rule 2 removes the canonical-vs-gate contradiction but does NOT make
     * every query single-term, and it is worth being honest about why.
     * `analyze_query()` builds `core_terms` from `product_phrase['search_terms']`
     * PLUS `core_product_terms()`, and the latter contributes the shopper's own
     * literal token. So a cross-word synonym still compiles to two required
     * terms: typing "trousers" yields `+pant* +trouser*`, and "cover" yields
     * `+case* +cover*`. Those survive only because `candidate_rows()` falls back
     * to a LIKE pass on the canonical phrase when the fulltext match returns
     * nothing -- correct results, but without fulltext ranking.
     *
     * The synonyms below are kept regardless, because the alternative is worse:
     * mapping `trousers` to a `trouser` family of its own would gate on a token
     * no product carries and return nothing at all. The real repair is teaching
     * `boolean_query()` to emit an OR group, `+(pant trouser)`, after which
     * these become both satisfiable and properly ranked.
     */
    private function canonical_product_family($term) {
        $term = $this->normalize_index_text($term);
        $map = array(
            // -- audio ------------------------------------------------------
            'earbud' => 'earbud', 'earbuds' => 'earbud',
            'earphone' => 'earbud', 'earphones' => 'earbud',
            'headphone' => 'headphone', 'headphones' => 'headphone',
            'headset' => 'headset', 'headsets' => 'headset',
            'speaker' => 'speaker', 'speakers' => 'speaker',
            'soundbar' => 'soundbar', 'soundbars' => 'soundbar',

            // -- computing and mobile --------------------------------------
            'adapter' => 'adapter', 'adapters' => 'adapter',
            'adaptor' => 'adapter', 'adaptors' => 'adapter',
            'cable' => 'cable', 'cables' => 'cable',
            'charger' => 'charger', 'chargers' => 'charger',
            'dock' => 'dock', 'docks' => 'dock',
            'hub' => 'hub', 'hubs' => 'hub',
            'keyboard' => 'keyboard', 'keyboards' => 'keyboard',
            'laptop' => 'laptop', 'laptops' => 'laptop',
            'monitor' => 'monitor', 'monitors' => 'monitor',
            'mouse' => 'mouse', 'mice' => 'mouse',
            'mousepad' => 'mousepad', 'mousepads' => 'mousepad',
            'powerbank' => 'powerbank', 'powerbanks' => 'powerbank',
            'tablet' => 'tablet', 'tablets' => 'tablet',
            'webcam' => 'webcam', 'webcams' => 'webcam',

            // -- wearables and timepieces ----------------------------------
            'smartwatch' => 'smartwatch', 'smartwatches' => 'smartwatch',
            'watch' => 'watch', 'watches' => 'watch',
            'tracker' => 'tracker', 'trackers' => 'tracker',

            // -- protective goods ------------------------------------------
            // `case` and `cover` are one family, but the gate can only carry a
            // single token, so both resolve to `case`.
            'case' => 'case', 'cases' => 'case',
            'cover' => 'case', 'covers' => 'case',
            'sleeve' => 'sleeve', 'sleeves' => 'sleeve',
            'screenprotector' => 'screenprotector',

            // -- bags, carry and travel ------------------------------------
            'bag' => 'bag', 'bags' => 'bag',
            'backpack' => 'backpack', 'backpacks' => 'backpack',
            'rucksack' => 'backpack', 'rucksacks' => 'backpack',
            'duffel' => 'duffel', 'duffels' => 'duffel', 'duffle' => 'duffel',
            'holdall' => 'duffel',
            'luggage' => 'luggage', 'suitcase' => 'luggage', 'suitcases' => 'luggage',
            'tote' => 'tote', 'totes' => 'tote',
            'organiser' => 'organiser', 'organisers' => 'organiser',
            'organizer' => 'organizer', 'organizers' => 'organizer',
            'pouch' => 'pouch', 'pouches' => 'pouch',
            'umbrella' => 'umbrella', 'umbrellas' => 'umbrella',
            'wallet' => 'wallet', 'wallets' => 'wallet',

            // -- drinkware -------------------------------------------------
            'bottle' => 'bottle', 'bottles' => 'bottle',
            'flask' => 'bottle', 'flasks' => 'bottle',
            'cup' => 'cup', 'cups' => 'cup',
            'drinkware' => 'drinkware',
            'mug' => 'mug', 'mugs' => 'mug',
            'tumbler' => 'tumbler', 'tumblers' => 'tumbler',

            // -- tops ------------------------------------------------------
            'blouse' => 'blouse', 'blouses' => 'blouse',
            'hoodie' => 'hoodie', 'hoodies' => 'hoodie',
            'jacket' => 'jacket', 'jackets' => 'jacket',
            'coat' => 'jacket', 'coats' => 'jacket',
            'polo' => 'polo', 'polos' => 'polo',
            'shirt' => 'shirt', 'shirts' => 'shirt',
            'sweater' => 'sweater', 'sweaters' => 'sweater',
            'jumper' => 'sweater', 'jumpers' => 'sweater',
            'sweatshirt' => 'sweatshirt', 'sweatshirts' => 'sweatshirt',
            'cardigan' => 'cardigan', 'cardigans' => 'cardigan',

            // -- bottoms and dresses ---------------------------------------
            'dress' => 'dress', 'dresses' => 'dress',
            'jean' => 'jean', 'jeans' => 'jean',
            'legging' => 'legging', 'leggings' => 'legging',
            'pant' => 'pant', 'pants' => 'pant',
            'trouser' => 'pant', 'trousers' => 'pant',
            'chino' => 'chino', 'chinos' => 'chino',
            'jogger' => 'jogger', 'joggers' => 'jogger',
            'shorts' => 'shorts',
            'skirt' => 'skirt', 'skirts' => 'skirt',

            // -- footwear --------------------------------------------------
            'shoe' => 'shoe', 'shoes' => 'shoe',
            'sneaker' => 'sneaker', 'sneakers' => 'sneaker',
            'trainer' => 'sneaker', 'trainers' => 'sneaker',
            'boot' => 'boot', 'boots' => 'boot',
            'loafer' => 'loafer', 'loafers' => 'loafer',
            'sandal' => 'sandal', 'sandals' => 'sandal',
            'slipper' => 'slipper', 'slippers' => 'slipper',

            // -- worn accessories ------------------------------------------
            // These are plain words again. `beany`, `scarve` and `sunglass` used
            // to be keyed here too, because the singulariser mangled plurals
            // before the lookup ever ran. StemmerService now reduces the query
            // and the index with the same function, so the mangled forms no
            // longer need a home in the vocabulary.
            'beanie' => 'beanie', 'beanies' => 'beanie',
            'belt' => 'belt', 'belts' => 'belt',
            'cap' => 'cap', 'caps' => 'cap',
            'glove' => 'glove', 'gloves' => 'glove',
            'hat' => 'hat', 'hats' => 'hat',
            'scarf' => 'scarf', 'scarves' => 'scarf',
            'sock' => 'sock', 'socks' => 'sock',
            'sunglass' => 'sunglass', 'sunglasses' => 'sunglass',

            // -- home and desk ---------------------------------------------
            'candle' => 'candle', 'candles' => 'candle',
            'clock' => 'clock', 'clocks' => 'clock',
            'cushion' => 'cushion', 'cushions' => 'cushion',
            'footrest' => 'footrest', 'footrests' => 'footrest',
            'lamp' => 'lamp', 'lamps' => 'lamp',
            'mat' => 'mat', 'mats' => 'mat',
            'pillow' => 'pillow', 'pillows' => 'pillow',
            'stand' => 'stand', 'stands' => 'stand',
            'towel' => 'towel', 'towels' => 'towel',
            'vase' => 'vase', 'vases' => 'vase',

            // -- fitness ---------------------------------------------------
            'dumbbell' => 'dumbbell', 'dumbbells' => 'dumbbell',
            'kettlebell' => 'kettlebell', 'kettlebells' => 'kettlebell',

            // -- stationery ------------------------------------------------
            'notebook' => 'notebook', 'notebooks' => 'notebook',
            'pen' => 'pen', 'pens' => 'pen',
            'pencil' => 'pencil', 'pencils' => 'pencil',

            // -- catch-all -------------------------------------------------
            // `accessor` is the shared stem of accessory and accessories.
            'accessory' => 'accessor', 'accessories' => 'accessor',
        );
        if (isset($map[$term])) {
            return $map[$term];
        }

        // Nothing curated covers this word, so fall back to what the store's own
        // category tree teaches. Curated entries deliberately win: a mis-learned
        // term can then never displace a deliberate one.
        return $this->vocabulary()->canonical($term);
    }

    /**
     * Returns the hard family gate for the exact family noun used by the shopper.
     *
     * Broad catalog families remain useful for ranking and fallback, but a
     * specific noun such as `backpack` must not silently widen to every bag,
     * sleeve, tote, organiser, or pouch.
     *
     * Every gate below is deliberately ONE token, and that token is the same
     * value `canonical_product_family()` returns for the same noun. The reason
     * is structural rather than stylistic: `product_phrase()` merges this list
     * into `search_terms`, and `boolean_query()` renders each entry as `+term*`
     * in a MySQL BOOLEAN MODE match. A two-token gate is therefore not "either
     * of these" but "both of these at once". The previous
     * `pouch => (pouch, organiser, organizer)` gate compiled to
     * `+pouch* +organiser* +organizer*` and matched nothing, even with three
     * organiser pouches indexed; `cup => (cup, mug)` and
     * `backpack => (backpack, rucksack)` carried the same defect.
     *
     * Multi-token gates only become safe once `boolean_query()` can emit an OR
     * group -- `+(pouch organiser organizer)` -- at which point the alternates
     * can move back in here. Until then, spelling variants and cross-word
     * synonyms are handled by pointing them at one shared canonical stem in
     * `canonical_product_family()`, and neighbouring families for ranking live
     * in `product_family_aliases()`.
     */
    private function required_product_family_aliases($family, $family_source = '') {
        $family = $this->normalize_index_text($family);
        $source = $this->normalize_index_text($family_source);

        // Nouns that must stay narrow. Anything absent falls through to the
        // broad alias list, which is the right default for umbrella nouns such
        // as `bag` or `drinkware` where widening is what the shopper wants.
        $specific = array(
            // audio
            'earbud' => array('earbud', 'earphone'), 'earbuds' => array('earbud', 'earphone'),
            'earphone' => array('earbud', 'earphone'), 'earphones' => array('earbud', 'earphone'),
            'headphone' => 'headphone', 'headphones' => 'headphone',
            'headset' => 'headset', 'headsets' => 'headset',
            'speaker' => 'speaker', 'speakers' => 'speaker',
            'soundbar' => 'soundbar', 'soundbars' => 'soundbar',

            // computing and mobile
            'adapter' => 'adapter', 'adapters' => 'adapter',
            'adaptor' => 'adapter', 'adaptors' => 'adapter',
            'cable' => 'cable', 'cables' => 'cable',
            'charger' => 'charger', 'chargers' => 'charger',
            'dock' => 'dock', 'docks' => 'dock',
            'hub' => 'hub', 'hubs' => 'hub',
            'keyboard' => 'keyboard', 'keyboards' => 'keyboard',
            'laptop' => 'laptop', 'laptops' => 'laptop',
            'monitor' => 'monitor', 'monitors' => 'monitor',
            'mouse' => 'mouse', 'mice' => 'mouse',
            'mousepad' => 'mousepad', 'mousepads' => 'mousepad',
            'powerbank' => 'powerbank', 'powerbanks' => 'powerbank',
            'tablet' => 'tablet', 'tablets' => 'tablet',
            'webcam' => 'webcam', 'webcams' => 'webcam',

            // wearables and timepieces
            'smartwatch' => 'smartwatch', 'smartwatches' => 'smartwatch',
            'watch' => 'watch', 'watches' => 'watch',
            'tracker' => 'tracker', 'trackers' => 'tracker',

            // protective goods
            'case' => array('case', 'cover'), 'cases' => array('case', 'cover'),
            'cover' => array('case', 'cover'), 'covers' => array('case', 'cover'),
            'sleeve' => 'sleeve', 'sleeves' => 'sleeve',
            'screenprotector' => 'screenprotector',

            // bags, carry and travel
            'backpack' => array('backpack', 'rucksack'), 'backpacks' => array('backpack', 'rucksack'),
            'rucksack' => array('backpack', 'rucksack'), 'rucksacks' => array('backpack', 'rucksack'),
            'duffel' => 'duffel', 'duffels' => 'duffel', 'duffle' => 'duffel',
            'holdall' => 'duffel',
            'luggage' => 'luggage', 'suitcase' => 'luggage', 'suitcases' => 'luggage',
            'tote' => 'tote', 'totes' => 'tote',
            'organiser' => array('organiser', 'organizer', 'pouch'), 'organisers' => array('organiser', 'organizer', 'pouch'),
            'organizer' => array('organizer', 'organiser', 'pouch'), 'organizers' => array('organizer', 'organiser', 'pouch'),
            'pouch' => array('pouch', 'organiser', 'organizer'), 'pouches' => array('pouch', 'organiser', 'organizer'),
            'umbrella' => 'umbrella', 'umbrellas' => 'umbrella',
            'wallet' => 'wallet', 'wallets' => 'wallet',

            // drinkware
            'bottle' => 'bottle', 'bottles' => 'bottle',
            'flask' => 'bottle', 'flasks' => 'bottle',
            'cup' => array('cup', 'mug'), 'cups' => array('cup', 'mug'),
            'mug' => array('mug', 'cup'), 'mugs' => array('mug', 'cup'),
            'tumbler' => 'tumbler', 'tumblers' => 'tumbler',

            // tops
            'blouse' => 'blouse', 'blouses' => 'blouse',
            'hoodie' => 'hoodie', 'hoodies' => 'hoodie',
            'jacket' => 'jacket', 'jackets' => 'jacket',
            'coat' => 'jacket', 'coats' => 'jacket',
            'polo' => 'polo', 'polos' => 'polo',
            'sweater' => 'sweater', 'sweaters' => 'sweater',
            'jumper' => 'sweater', 'jumpers' => 'sweater',
            'sweatshirt' => 'sweatshirt', 'sweatshirts' => 'sweatshirt',
            'cardigan' => 'cardigan', 'cardigans' => 'cardigan',

            // bottoms and dresses
            'dress' => 'dress', 'dresses' => 'dress',
            'jean' => 'jean', 'jeans' => 'jean',
            'legging' => 'legging', 'leggings' => 'legging',
            'chino' => 'chino', 'chinos' => 'chino',
            'jogger' => 'jogger', 'joggers' => 'jogger',
            'shorts' => 'shorts',
            'skirt' => 'skirt', 'skirts' => 'skirt',

            // footwear
            'sneaker' => array('sneaker', 'trainer'), 'sneakers' => array('sneaker', 'trainer'),
            'trainer' => array('sneaker', 'trainer'), 'trainers' => array('sneaker', 'trainer'),
            'boot' => 'boot', 'boots' => 'boot',
            'loafer' => 'loafer', 'loafers' => 'loafer',
            'sandal' => 'sandal', 'sandals' => 'sandal',
            'slipper' => 'slipper', 'slippers' => 'slipper',

            // worn accessories
            'beanie' => 'beanie', 'beanies' => 'beanie',
            'belt' => 'belt', 'belts' => 'belt',
            'cap' => 'cap', 'caps' => 'cap',
            'glove' => 'glove', 'gloves' => 'glove',
            'hat' => 'hat', 'hats' => 'hat',
            'scarf' => 'scarf', 'scarves' => 'scarf',
            'sock' => 'sock', 'socks' => 'sock',
            'sunglass' => 'sunglass', 'sunglasses' => 'sunglass',

            // home and desk
            'candle' => 'candle', 'candles' => 'candle',
            'clock' => 'clock', 'clocks' => 'clock',
            'cushion' => 'cushion', 'cushions' => 'cushion',
            'footrest' => 'footrest', 'footrests' => 'footrest',
            'lamp' => 'lamp', 'lamps' => 'lamp',
            'pillow' => 'pillow', 'pillows' => 'pillow',
            'towel' => 'towel', 'towels' => 'towel',
            'vase' => 'vase', 'vases' => 'vase',

            // fitness
            'dumbbell' => 'dumbbell', 'dumbbells' => 'dumbbell',
            'kettlebell' => 'kettlebell', 'kettlebells' => 'kettlebell',

            // stationery
            'notebook' => 'notebook', 'notebooks' => 'notebook',
            'pencil' => 'pencil', 'pencils' => 'pencil',
        );

        if ($source !== '' && isset($specific[$source])) {
            // A string is the common case: one noun, one gate token. An array
            // widens to genuine retail synonyms, and its first entry is the
            // canonical, so the compiled OR group always contains it.
            //
            // Arrays were impossible until boolean_query() learned to emit OR
            // groups. Before that every gate token became another `+term*` in a
            // fulltext AND, so `pouch => (pouch, organiser, organizer)` demanded
            // a product be all three at once and matched nothing.
            return array_values(array_unique(array_filter(array_map(
                array($this, 'normalize_index_text'),
                (array) $specific[$source]
            ))));
        }

        // Curated breadth outranks derived breadth. `drinkware` is the example:
        // the curated alias list deliberately reaches mugs, cups, tumblers and
        // bottles, while the store's own tree only knows the one category of that
        // name. Letting derivation answer first quietly narrowed the query.
        // Derivation exists to cover families nobody hardcoded, not to second-
        // guess the ones somebody did.
        if ($this->curated_family_aliases($family) !== null) {
            return $this->product_family_aliases($family);
        }

        $derived = $this->vocabulary()->gate($source);
        if (!empty($derived)) {
            return $derived;
        }

        return $this->product_family_aliases($family);
    }

    /**
     * Returns the immutable family gate carried into follow-up refinements.
     *
     * Broad aliases remain useful for initial ranking, but a short refinement
     * such as "Only in stock" must not reopen the catalog to products that only
     * share a lifestyle tag. The gate therefore uses direct family nouns and
     * confirmed subtypes, not broad neighboring families.
     */
    private function refinement_product_family_aliases($family, $family_source = '', $required_aliases = array()) {
        $family = $this->normalize_index_text($family);
        $source = $this->normalize_index_text($family_source);
        $required_aliases = array_values(array_unique(array_filter((array) $required_aliases)));

        $strict = array(
            'accessory' => array('accessory', 'adapter', 'organiser', 'organizer', 'pouch', 'case', 'sleeve'),
        );

        if ($family === 'accessory' && in_array($source, array('accessory', 'accessories'), true)) {
            return $strict['accessory'];
        }

        return !empty($required_aliases) ? $required_aliases : $this->product_family_aliases($family);
    }

    /**
     * Curated neighbours for a family, or null when nothing is hardcoded for it.
     *
     * The null is the point: it separates "hardcoded as exactly itself" from
     * "never hardcoded at all", which is what lets derived data fill only the
     * genuine gaps.
     *
     * @param string $family Canonical family.
     * @return array<int, string>|null
     */
    private function curated_family_aliases($family) {
        $map = $this->curated_family_alias_map();
        $family = $this->normalize_index_text($family);

        return isset($map[$family]) ? $map[$family] : null;
    }

    /**
     * Hardcoded neighbouring families, used for ranking breadth.
     *
     * @return array<string, array<int, string>>
     */
    private function curated_family_alias_map() {
        return array(
            'accessory' => array('accessory', 'adapter', 'organiser', 'organizer', 'pouch', 'case', 'sleeve', 'pillow', 'bag'),
            'adapter' => array('adapter'),
            'bag' => array('bag', 'backpack', 'tote', 'sleeve', 'organiser', 'organizer', 'pouch'),
            'belt' => array('belt'),
            'bottle' => array('bottle', 'flask'),
            'case' => array('case', 'cover'),
            'charger' => array('charger'),
            'clock' => array('clock'),
            'jacket' => array('jacket', 'coat'),
            'drinkware' => array('drinkware', 'mug', 'cup', 'tumbler', 'bottle', 'flask'),
            'dress' => array('dress'),
            'earbud' => array('earbud', 'headphone'),
            'headphone' => array('headphone', 'earbud'),
            'footrest' => array('footrest'),
            'hoodie' => array('hoodie'),
            'keyboard' => array('keyboard'),
            'lamp' => array('lamp'),
            'mat' => array('mat'),
            'organiser' => array('organiser', 'organizer', 'pouch'),
            'pillow' => array('pillow'),
            'powerbank' => array('powerbank', 'power bank'),
            'scarf' => array('scarf'),
            'shirt' => array('shirt'),
            'shoe' => array('shoe', 'sneaker', 'trainer'),
            'sleeve' => array('sleeve', 'case'),
            'speaker' => array('speaker'),
            'wallet' => array('wallet', 'card holder'),
            'watch' => array('watch'),
        );
    }

    private function product_family_aliases($family) {
        $family = $this->normalize_index_text($family);
        $map = $this->curated_family_alias_map();

        if (isset($map[$family])) {
            $aliases = $map[$family];
        } else {
            $derived = $this->vocabulary()->neighbours($family);
            $aliases = !empty($derived) ? $derived : array($family);
        }

        return array_values(array_unique(array_filter((array) apply_filters('geekybot_product_family_aliases', $aliases, $family))));
    }

    private function display_facet_labels($terms, $type) {
        $terms = array_values(array_unique(array_filter((array) $terms)));
        if (empty($terms)) {
            return array();
        }

        $canonical = $type === 'size'
            ? array('xxxs','xxs','xs','s','m','l','xl','xxl','xxxl','one size')
            : array('black','white','blue','green','red','yellow','pink','purple','orange','brown','gray','beige','gold','silver','multi');

        $labels = array();
        foreach ($canonical as $label) {
            if (in_array($label, $terms, true)) {
                $labels[] = $label;
            }
        }

        if ($type === 'size') {
            foreach ($terms as $term) {
                if (preg_match('/^[0-9]{1,3}(?:\.[0-9])?$/', (string) $term)) {
                    $labels[] = (string) $term;
                }
            }
        }

        if (empty($labels)) {
            foreach ($terms as $term) {
                $term = trim((string) $term);
                if ($term !== '' && strlen($term) > 1) {
                    $labels[] = $term;
                }
            }
        }

        return array_values(array_unique(array_slice($labels, 0, 4)));
    }

    private function remove_weak_shopper_terms($terms) {
        $language = $this->search_language();
        $clean = array();
        foreach ((array) $terms as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '') {
                continue;
            }
            if (method_exists($language, 'is_weak_shopper_token') && $language->is_weak_shopper_token($term)) {
                continue;
            }
            $clean[] = $term;
        }
        return array_values(array_unique($clean));
    }

    private function terms_without($terms, $remove_terms) {
        $remove = array();
        foreach ((array) $remove_terms as $term) {
            $term = $this->normalize_index_text($term);
            if ($term !== '') {
                $remove[$term] = true;
            }
        }

        $clean = array();
        foreach ((array) $terms as $term) {
            $normalized = $this->normalize_index_text($term);
            if ($normalized === '' || isset($remove[$normalized])) {
                continue;
            }
            $clean[] = $normalized;
        }

        return array_values(array_unique($clean));
    }

    /**
     * Family-gate matching: does the row's identity carry any of these nouns?
     *
     * Anchored to the start of a word, unlike the loose substring test used for
     * ranking. The gate decides whether a product IS the thing being asked for,
     * and a noun buried mid-word is a different thing entirely: "hardcover"
     * contains "cover" but a hardcover notebook is not a phone cover. That false
     * positive appeared the moment the `cover` gate was widened, and matching a
     * word start keeps the synonym without the noise. Suffixes still match, so
     * "covers" and "covered" continue to count.
     */
    private function row_matches_any_term($text, $terms) {
        $haystack = ' ' . $this->normalize_index_text($text) . ' ';

        foreach ((array) $terms as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '') {
                continue;
            }

            if (strpos($haystack, ' ' . $term) !== false) {
                return true;
            }
        }

        return false;
    }

    private function row_text_has_term($text, $term) {
        $text = ' ' . $this->normalize_index_text($text) . ' ';
        $term = $this->normalize_index_text($term);
        if ($term === '') {
            return false;
        }

        if (strpos($text, ' ' . $term . ' ') !== false) {
            return true;
        }

        // Do not allow one/two-character facets like S, M, L, XL to match
        // arbitrary substrings inside unrelated words. Longer terms may still
        // use controlled partial matching for shopper typos/compound text.
        if (function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') <= 2 : strlen($term) <= 2) {
            return false;
        }

        if (preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $term)) {
            return strpos($text, $term) !== false;
        }

        return strpos($text, $term) !== false;
    }

    private function expand_synonyms($query) {
        return $this->search_language()->expand_synonyms($query);
    }

    /**
     * Split analysis terms into OR groups for the fulltext candidate query.
     *
     * Group 1 collects every surface form of the product family the shopper
     * named: the canonical stem, the word they actually typed, the hard gate
     * tokens and the neighbouring families. Widening here is deliberate and
     * safe, because `candidate_rows()` only proposes candidates --
     * `row_matches_product_phrase()` then re-applies the narrow gate in PHP with
     * OR semantics, so precision is decided there, not here. Fetching a
     * neighbour and discarding it costs one row; missing the shopper's wording
     * costs the answer.
     *
     * Colours and sizes each become a single OR group, so "blue or grey hoodie"
     * asks for either colour instead of demanding both at once.
     *
     * Only words the shopper actually typed stay individually required. Tokens
     * that appear solely because `expand_synonyms()` added them are alternates,
     * never requirements -- the difference between `$core_terms` (expanded) and
     * `$display_core_terms` (as typed) is exactly that provenance. Treating an
     * expansion as required inverts its purpose: "walking shoes" expanded to
     * include `footwear` and then demanded `+(shoe* sneaker* trainer*)
     * +footwear*`, so a product had to be described as footwear as well as a
     * shoe. A synonym must widen a search, never narrow it.
     *
     * @param array $product_phrase      Result of product_phrase_profile().
     * @param array $core_terms          Required terms after synonym expansion.
     * @param array $display_core_terms  The same list before expansion.
     * @param array $color_terms         Normalised colour facets.
     * @param array $size_terms          Normalised size facets.
     * @param array $expansion_sources   Alternate token => shopper tokens that
     *                                   produced it, from expand_synonyms_map().
     * @return array<int, array<int, string>>
     */
    private function boolean_term_groups($product_phrase, $core_terms, $display_core_terms, $color_terms, $size_terms, $expansion_sources = array()) {
        $product_phrase = is_array($product_phrase) ? $product_phrase : array();

        $expansion_sources = is_array($expansion_sources) ? $expansion_sources : array();

        $typed = array_fill_keys(array_filter(array_map(
            array($this, 'normalize_index_text'),
            (array) $display_core_terms
        )), true);

        $family_group = array();
        if (!empty($product_phrase['family'])) {
            $family_group = array_merge(
                array((string) $product_phrase['family']),
                array((string) ($product_phrase['family_source'] ?? '')),
                (array) ($product_phrase['required_family_aliases'] ?? array()),
                (array) ($product_phrase['family_aliases'] ?? array())
            );
            $family_group = array_values(array_unique(array_filter(array_map(
                array($this, 'normalize_index_text'),
                $family_group
            ))));
        }

        $facets = array_map(array($this, 'normalize_index_text'), array_merge((array) $color_terms, (array) $size_terms));
        $claimed = array_fill_keys(array_merge($family_group, array_filter($facets)), true);

        $groups = array();
        // Which group each typed word owns, so an alternate can be filed against
        // the word it is an alternate OF. Every family surface form points at
        // the family group: a translation of `mug` belongs there whether the
        // shopper typed `mug` or `cup`.
        $group_of_term = array();
        if (!empty($family_group)) {
            $groups[] = $family_group;
            foreach ($family_group as $family_term) {
                $group_of_term[$family_term] = 0;
            }
        }

        // Qualifiers the shopper typed stay individually required. Anything only
        // a synonym expansion contributed joins the alternates of the group for
        // the word it came from -- an expansion is another way of saying a word
        // the shopper already typed, so it must widen that requirement, never
        // add one, and never widen a different one.
        //
        // That distinction is invisible in English, where a family is almost
        // always detected and the expansions landed in the family group anyway.
        // It is the whole story across languages. A query for حذاء finds no
        // family, and the English translation `shoe` that expand_synonyms()
        // contributes used to become a second required group: `+حذاء* +shoe*`
        // asks for a product containing both an Arabic and an English word for
        // shoe, which nothing does. Filing every expansion in the FIRST group
        // instead then broke the mirror image, an English query against an
        // Arabic catalog: "ceramic mug" compiled to
        // `+(mug* کوب* اکواب* سیرامیک*) +ceramic*`, where the translation of the
        // qualifier widened the noun -- which needed no widening -- and left
        // `+ceramic*` demanding an English word the catalog does not contain.
        // Provenance is what tells the two cases apart.
        $expanded_only = array();
        foreach ((array) $core_terms as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '' || isset($claimed[$term])) {
                continue;
            }
            $claimed[$term] = true;

            if (isset($typed[$term])) {
                $groups[] = array($term);
                $group_of_term[$term] = count($groups) - 1;
                continue;
            }

            $expanded_only[] = $term;
        }

        foreach ($expanded_only as $term) {
            $target = null;
            foreach ((array) ($expansion_sources[$term] ?? array()) as $source) {
                $source = $this->normalize_index_text($source);
                if ($source !== '' && isset($group_of_term[$source])) {
                    $target = $group_of_term[$source];
                    break;
                }
            }

            if ($target === null) {
                // No provenance -- an alternate from a saved analysis, or a
                // token the per-language rules spelled differently on the two
                // sides. The first group is where these have always gone.
                if (empty($groups)) {
                    $groups[] = array();
                }
                $target = 0;
            }

            $groups[$target][] = $term;
        }

        foreach ($groups as $index => $group) {
            $groups[$index] = array_values(array_unique(array_filter((array) $group)));
        }
        $groups = array_values(array_filter($groups));

        foreach (array((array) $color_terms, (array) $size_terms) as $facet_group) {
            $facet_group = array_values(array_unique(array_filter(array_map(
                array($this, 'normalize_index_text'),
                $facet_group
            ))));
            if (!empty($facet_group)) {
                $groups[] = $facet_group;
            }
        }

        return $groups;
    }

    /**
     * Compile analysis terms into a MySQL BOOLEAN MODE expression.
     *
     * Accepts either a flat list of terms, which stays strictly required, or a
     * list of groups. A group is a set of ALTERNATIVE surface forms for one
     * concept and compiles to `+(a* b* c*)` -- the row must carry at least one
     * of them. Separate groups remain ANDed, so precision is unchanged.
     *
     * This distinction is the whole point. A shopper noun routinely has several
     * valid spellings in one catalog: trousers/pants, cover/case, organiser/
     * organizer, trainers/sneakers, earphones/earbuds. Emitting those as
     * `+pant* +trouser*` demanded a product be both at once and matched nothing,
     * which is the opposite of understanding the question. `+(pant* trouser*)`
     * accepts either wording while a genuine qualifier such as `walking` stays a
     * separate required group.
     *
     * @param array $terms Flat term list, or a list of arrays to OR internally.
     * @return string
     */
    private function boolean_query($terms) {
        $groups = array();

        foreach (array_slice((array) $terms, 0, 8) as $group) {
            $alternates = array();

            foreach ((array) $group as $term) {
                $term = preg_replace('/[^\pL\pN_\-]/u', '', (string) $term);
                // InnoDB will not index tokens below innodb_ft_min_token_size,
                // so a one or two character alternate can never match here. The
                // LIKE pass and the PHP facet filters still handle those.
                if ($term === '' || strlen($term) < 2) {
                    continue;
                }
                $alternates[$term] = $term . '*';

                // The stem is an extra way to match, never a replacement: the
                // raw term still has to be offered because `stem_text` only
                // covers identity fields while the raw columns cover
                // descriptions too. Adding it to the same OR group is what lets
                // "beanies" reach a product titled "Beanie".
                $stem = $this->stemmer()->stem($term);
                if ($stem !== '' && $stem !== $term && strlen($stem) >= 2) {
                    $alternates[$stem] = $stem . '*';
                }
            }

            if (empty($alternates)) {
                continue;
            }

            $groups[] = count($alternates) === 1
                ? '+' . reset($alternates)
                : '+(' . implode(' ', $alternates) . ')';
        }

        return implode(' ', $groups);
    }

    private function query_terms($query) {
        return $this->search_language()->query_terms($query);
    }

    private function catalog_intent($query) {
        return $this->search_language()->catalog_intent($query);
    }

    private function price_range_from_query($query) {
        return $this->search_language()->price_range_from_query($query);
    }

    private function strip_price_filters($query) {
        return $this->search_language()->strip_price_filters($query);
    }

    private function parse_price_number($value) {
        return (float) str_replace(',', '', (string) $value);
    }

    private function decimal_or_null($value) {
        return $value === '' || $value === null ? null : (float) $value;
    }

    private function phrase_for_like($query) {
        $query = $this->normalize_index_text($query);
        return trim($query);
    }

    private function clean_query($query) {
        $query = wp_strip_all_tags((string) $query);
        $query = preg_replace('/\s+/', ' ', $query);
        return trim($query);
    }

    private function normalize_index_text($text) {
        return $this->search_language()->normalize_text($text);
    }

    /**
     * Sale is a hard commerce constraint. Keep compatibility with older saved
     * analyses that only stored intent=sale while preferring the explicit flag
     * introduced by Product Discovery V1.7.
     */
    private function analysis_requires_sale($analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        return !empty($analysis['sale_required'])
            || (!empty($analysis['intent']) && $analysis['intent'] === 'sale');
    }

    private function vocabulary() {
        static $vocabulary = null;
        if ($vocabulary === null) {
            $vocabulary = new FamilyVocabularyService();
        }
        return $vocabulary;
    }

    private function stemmer() {
        static $stemmer = null;
        if ($stemmer === null) {
            $stemmer = new StemmerService();
        }
        return $stemmer;
    }

    private function search_language() {
        static $language = null;
        if ($language === null) {
            $language = new SearchLanguageService();
        }
        return $language;
    }
}
