<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * This repository intentionally maintains Geeky Bot's custom knowledge-index
 * table. Its identifier is fixed from $wpdb->prefix; generated IN lists contain
 * only integer placeholders and their values are passed through prepare().
 * Live index state is not cached so synchronization cannot read stale rows.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Builds a local, sanitized index from explicitly selected public pages.
 */
class KnowledgeIndexService {
    const MAX_PAGES = 25;
    const MAX_CONTENT_LENGTH = 60000;
    const PENDING_SYNC_OPTION = 'geekybot_knowledge_sync_pending';

    /**
     * Register selected-page synchronization hooks.
     */
    public function hooks() {
        add_action('wp_loaded', array($this, 'maybe_sync_pending'), 20);
        add_action('save_post_page', array($this, 'handle_page_save'), 20, 3);
        add_action('before_delete_post', array($this, 'handle_page_delete'));
        add_action('trashed_post', array($this, 'handle_page_delete'));
    }


    /**
     * Schedule a one-time knowledge sync after WordPress has finished loading.
     *
     * Upgrade checks run on plugins_loaded, before rewrite services are ready.
     * Deferring the sync prevents permalink generation from running too early.
     */
    public static function schedule_sync() {
        update_option(self::PENDING_SYNC_OPTION, 1, false);
    }

    /**
     * Process a pending install/upgrade sync once WordPress is fully loaded.
     */
    public function maybe_sync_pending() {
        if (!get_option(self::PENDING_SYNC_OPTION, 0)) {
            return;
        }

        // Clear first so a failing source cannot cause a fatal retry loop.
        delete_option(self::PENDING_SYNC_OPTION);

        $summary = $this->sync_selected_pages();
        update_option('geekybot_knowledge_last_sync_summary', $summary, false);
    }

    /**
     * Create/update the knowledge index table.
     */
    public static function create_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'geekybot_knowledge_index';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            page_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            source_url text NOT NULL,
            policy_types varchar(255) NOT NULL DEFAULT '',
            content longtext NOT NULL,
            content_hash char(64) NOT NULL DEFAULT '',
            post_modified_gmt datetime NULL,
            indexed_at datetime NOT NULL,
            PRIMARY KEY  (page_id),
            KEY indexed_at (indexed_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Synchronize all selected public pages.
     *
     * @param bool $force Force content replacement even when unchanged.
     * @return array
     */
    public function sync_selected_pages($force = false) {
        $selected = $this->selected_page_ids();
        $summary = array(
            'selected' => count($selected),
            'indexed' => 0,
            'unchanged' => 0,
            'removed' => 0,
            'failed' => 0,
        );

        $summary['removed'] = $this->remove_unselected($selected);

        foreach ($selected as $page_id) {
            $result = $this->index_page($page_id, $force);
            if (is_wp_error($result)) {
                $summary['failed']++;
            } elseif ($result === 'unchanged') {
                $summary['unchanged']++;
            } else {
                $summary['indexed']++;
            }
        }

        return $summary;
    }

    /**
     * Rebuild all selected sources from their current page content.
     *
     * @return array
     */
    public function refresh_selected_pages() {
        return $this->sync_selected_pages(true);
    }

    /**
     * Index one selected published page.
     *
     * @param int  $page_id Page ID.
     * @param bool $force Force replacement.
     * @return string|\WP_Error
     */
    public function index_page($page_id, $force = false) {
        global $wpdb;

        $page_id = absint($page_id);
        if ($page_id < 1 || !in_array($page_id, $this->selected_page_ids(), true)) {
            return new \WP_Error('geekybot_knowledge_not_selected', __('The page is not an approved knowledge source.', 'geeky-bot'));
        }

        $post = get_post($page_id);
        if (!$post || $post->post_type !== 'page' || $post->post_status !== 'publish') {
            $this->delete_page($page_id);
            return new \WP_Error('geekybot_knowledge_not_public', __('The selected page is not public.', 'geeky-bot'));
        }

        $content = $this->safe_page_text($post);
        if ($content === '') {
            $this->delete_page($page_id);
            return new \WP_Error('geekybot_knowledge_empty', __('The selected page has no readable public text.', 'geeky-bot'));
        }

        $title = sanitize_text_field(get_the_title($page_id));
        $url = $this->source_url($post);
        $types = (new PolicyIntentService())->document_types($title, $content);
        $hash = hash('sha256', $title . "\n" . $url . "\n" . implode(',', $types) . "\n" . $content);
        $table = $this->table_name();

        if (!$force) {
            $existing_hash = $wpdb->get_var($wpdb->prepare("SELECT content_hash FROM {$table} WHERE page_id = %d", $page_id));
            if (is_string($existing_hash) && hash_equals($existing_hash, $hash)) {
                return 'unchanged';
            }
        }

        $modified_gmt = $post->post_modified_gmt && $post->post_modified_gmt !== '0000-00-00 00:00:00'
            ? $post->post_modified_gmt
            : get_gmt_from_date($post->post_modified);

        $result = $wpdb->replace(
            $table,
            array(
                'page_id' => $page_id,
                'title' => $title,
                'source_url' => $url,
                'policy_types' => implode(',', array_map('sanitize_key', $types)),
                'content' => $content,
                'content_hash' => $hash,
                'post_modified_gmt' => $modified_gmt,
                'indexed_at' => current_time('mysql', true),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new \WP_Error('geekybot_knowledge_index_failed', __('The policy page could not be indexed.', 'geeky-bot'));
        }

        return 'indexed';
    }

    /**
     * Read indexed selected pages. No unselected page can be returned.
     *
     * @param array $types Optional policy types.
     * @return array
     */
    public function indexed_pages($types = array()) {
        global $wpdb;

        $selected = $this->selected_page_ids();
        if (empty($selected)) {
            return array();
        }

        $table = $this->table_name();
        $placeholders = implode(',', array_fill(0, count($selected), '%d'));
        $query = $wpdb->prepare(
            "SELECT page_id, title, source_url, policy_types, content, content_hash, post_modified_gmt, indexed_at FROM {$table} WHERE page_id IN ({$placeholders})",
            $selected
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The query is prepared immediately above with generated placeholders for integer page IDs.
        $rows = $wpdb->get_results($query, ARRAY_A);
        $types = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $types))));
        $pages = array();

        foreach ((array) $rows as $row) {
            $row_types = array_values(array_filter(array_map('sanitize_key', explode(',', (string) $row['policy_types']))));
            // A page classified only as general must never answer a specific
            // shipping, refund, payment, warranty, or other policy question.
            // Incidental words on Cart, Checkout, and account pages are not
            // sufficient evidence that those pages define store policy.
            if (!empty($types) && empty(array_intersect($types, $row_types))) {
                continue;
            }

            $pages[] = array(
                'id' => absint($row['page_id']),
                'title' => sanitize_text_field($row['title']),
                'url' => esc_url_raw($row['source_url']),
                'types' => $row_types,
                'content' => (string) $row['content'],
                'indexedAt' => sanitize_text_field($row['indexed_at']),
                'postModifiedGmt' => sanitize_text_field($row['post_modified_gmt']),
            );
        }

        return $pages;
    }

    /**
     * Return admin-facing freshness information.
     *
     * @return array
     */
    public function status() {
        $selected = $this->selected_page_ids();
        $rows = $this->indexed_pages();
        $indexed_ids = array();
        $stale = 0;
        $usable = 0;
        $unclassified = 0;
        $latest = '';

        foreach ($rows as $row) {
            $page_id = absint($row['id']);
            $indexed_ids[] = $page_id;
            $row_types = !empty($row['types']) ? (array) $row['types'] : array();
            if (empty($row_types) || $row_types === array('general')) {
                $unclassified++;
            } else {
                $usable++;
            }
            $post = get_post($page_id);
            if (!$post || $post->post_status !== 'publish') {
                $stale++;
                continue;
            }

            $modified_gmt = $post->post_modified_gmt && $post->post_modified_gmt !== '0000-00-00 00:00:00'
                ? $post->post_modified_gmt
                : get_gmt_from_date($post->post_modified);
            if ((string) $row['postModifiedGmt'] !== (string) $modified_gmt) {
                $stale++;
            }

            if ($latest === '' || strcmp((string) $row['indexedAt'], $latest) > 0) {
                $latest = (string) $row['indexedAt'];
            }
        }

        $missing = count(array_diff($selected, $indexed_ids));

        return array(
            'selected' => count($selected),
            'indexed' => count($rows),
            'usable' => $usable,
            'unclassified' => $unclassified,
            'missing' => $missing,
            'stale' => $stale,
            'ready' => count($selected) > 0 && $usable > 0 && $missing === 0 && $stale === 0,
            'lastIndexedGmt' => $latest,
        );
    }

    /**
     * @param int      $post_id Post ID.
     * @param \WP_Post $post Post.
     * @param bool     $update Update flag.
     */
    public function handle_page_save($post_id, $post, $update) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!in_array(absint($post_id), $this->selected_page_ids(), true)) {
            return;
        }

        if (!$post || $post->post_status !== 'publish') {
            $this->delete_page($post_id);
            return;
        }

        $this->index_page($post_id, true);
    }

    /**
     * @param int $post_id Post ID.
     */
    public function handle_page_delete($post_id) {
        if (get_post_type($post_id) === 'page') {
            $this->delete_page($post_id);
        }
    }

    /**
     * @param int $page_id Page ID.
     * @return int|false
     */
    public function delete_page($page_id) {
        global $wpdb;

        return $wpdb->delete($this->table_name(), array('page_id' => absint($page_id)), array('%d'));
    }

    /**
     * @return array
     */
    private function selected_page_ids() {
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) Settings::get('policy_page_ids', array())))));

        return array_slice($ids, 0, self::MAX_PAGES);
    }

    /**
     * Remove rows that are no longer selected.
     *
     * @param array $selected Selected IDs.
     * @return int
     */
    private function remove_unselected($selected) {
        global $wpdb;

        $table = $this->table_name();
        if (empty($selected)) {
            $count = absint($wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
            $wpdb->query("DELETE FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static table name, no user input.
            return $count;
        }

        $placeholders = implode(',', array_fill(0, count($selected), '%d'));
        $count = absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id NOT IN ({$placeholders})", $selected)));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE page_id NOT IN ({$placeholders})", $selected));

        return $count;
    }


    /**
     * Return a safe public page URL without assuming rewrite services are ready.
     *
     * @param \WP_Post $post Page post.
     * @return string
     */
    private function source_url($post) {
        global $wp_rewrite;

        $url = '';
        if (did_action('init') && is_object($wp_rewrite)) {
            $permalink = get_permalink($post);
            if (is_string($permalink)) {
                $url = $permalink;
            }
        }

        if ($url === '') {
            $url = add_query_arg('page_id', absint($post->ID), home_url('/'));
        }

        return esc_url_raw($url);
    }

    /**
     * Extract readable text without executing shortcodes or arbitrary remote
     * content. Only the selected page's stored public content is used.
     *
     * @param \WP_Post $post Page post.
     * @return string
     */
    private function safe_page_text($post) {
        $content = (string) $post->post_content;
        $content = preg_replace('/<!--\s*\/?wp:[^>]*-->/', ' ', $content);
        $content = strip_shortcodes($content);

        // Tags are separators, not nothing. Stripping them without putting
        // whitespace back fused adjacent elements into single words -- a card
        // built as <span>01</span><strong>Processing time</strong><p>Orders are
        // prepared...</p> indexed as "01Processing timeOrders are prepared",
        // and that is what got quoted back to the shopper.
        $blocks = 'address|article|aside|blockquote|br|dd|div|dl|dt|fieldset|figcaption|figure'
            . '|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|table|tbody|td|tfoot'
            . '|th|thead|tr|ul';
        $content = preg_replace('~</?(?:' . $blocks . ')\b[^>]*>~i', ' $0 ', $content);

        // Inline tags are only separators where the join would fuse two words:
        // a letter or digit running straight into a capital or digit. Splitting
        // on every inline tag would break "un<b>believable</b>" instead.
        $content = preg_replace('~(?<=[a-z0-9])(?:<[^>]+>)+(?=[A-Z0-9])~', ' ', $content);

        $content = wp_strip_all_tags($content, true);
        $content = html_entity_decode($content, ENT_QUOTES, get_bloginfo('charset'));
        $content = preg_replace('/[\r\n\t]+/', "\n", $content);
        $content = preg_replace('/[ ]{2,}/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim((string) $content);

        if (function_exists('mb_substr')) {
            return mb_substr($content, 0, self::MAX_CONTENT_LENGTH);
        }

        return substr($content, 0, self::MAX_CONTENT_LENGTH);
    }

    /**
     * @return string
     */
    private function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'geekybot_knowledge_index';
    }
}
