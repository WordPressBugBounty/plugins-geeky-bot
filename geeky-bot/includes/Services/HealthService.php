<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reports whether Geeky Bot's search plumbing is actually working.
 *
 * This exists because of a fault that hid in plain sight. `candidate_rows()`
 * matched against eight columns while the live FULLTEXT key covered six, so
 * MySQL answered every query with error 1191, the empty result was read as "no
 * matches", and the code fell through to a LIKE scan. Search kept returning
 * plausible products, so nothing looked wrong -- but relevance ranking had been
 * gone for as long as the mismatch existed, and no store owner had any way of
 * knowing. Fresh installs built the key correctly, so it never showed up in
 * testing either.
 *
 * The lesson generalises: a degraded assistant that still answers is invisible.
 * These checks make each dependency state something explicit, and they run in WP
 * Site Health so the answer is visible without reading the database.
 */
class HealthService {
    const FULLTEXT_STATE_OPTION = 'geekybot_fulltext_state';

    /**
     * Columns the product index FULLTEXT key must cover, in order.
     *
     * @return array<int, string>
     */
    public static function expected_fulltext_columns() {
        return array('title', 'sku', 'categories', 'tags', 'attributes', 'color_terms', 'size_terms', 'search_text', 'stem_text');
    }

    public function hooks() {
        add_filter('site_status_tests', array($this, 'register_site_health_tests'));
        add_action('admin_notices', array($this, 'render_critical_notice'));
    }

    /**
     * Remember that the fulltext candidate query failed.
     *
     * @param string $error Database error text.
     * @return void
     */
    public static function record_fulltext_failure($error) {
        $state = array(
            'status' => 'failing',
            'error' => (string) $error,
            'at' => time(),
        );

        update_option(self::FULLTEXT_STATE_OPTION, $state, false);
    }

    /**
     * Remember that the fulltext candidate query returned rows.
     *
     * @return void
     */
    public static function record_fulltext_success() {
        $state = get_option(self::FULLTEXT_STATE_OPTION, array());
        if (is_array($state) && isset($state['status']) && $state['status'] === 'ok') {
            // Already known good; avoid a write on every search.
            return;
        }

        update_option(self::FULLTEXT_STATE_OPTION, array('status' => 'ok', 'error' => '', 'at' => time()), false);
    }

    /**
     * Run every check.
     *
     * @return array<int, array<string, string>>
     */
    public function checks() {
        return array(
            $this->check_product_index_table(),
            $this->check_fulltext_index(),
            $this->check_index_freshness(),
            $this->check_store_vocabulary(),
            $this->check_knowledge_sources(),
            $this->check_assistant_reachable(),
        );
    }

    /**
     * Worst status across all checks: critical, warning or ok.
     *
     * @return string
     */
    public function overall_status() {
        $status = 'ok';
        foreach ($this->checks() as $check) {
            if ($check['status'] === 'critical') {
                return 'critical';
            }
            if ($check['status'] === 'warning') {
                $status = 'warning';
            }
        }

        return $status;
    }

    private function check_product_index_table() {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_product_index';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading own plugin schema for a health report.
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $exists
            ? $this->result('product_index_table', __('Product index table', 'geeky-bot'), 'ok', __('The product index table is present.', 'geeky-bot'))
            : $this->result(
                'product_index_table',
                __('Product index table', 'geeky-bot'),
                'critical',
                __('The product index table is missing, so Geeky Bot cannot search your catalog. Deactivating and reactivating the plugin rebuilds it.', 'geeky-bot')
            );
    }

    private function check_fulltext_index() {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_product_index';
        $expected = self::expected_fulltext_columns();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reading own plugin schema.
        $rows = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'gb_fulltext'");
        if (!is_array($rows) || empty($rows)) {
            return $this->result(
                'product_index_fulltext',
                __('Product search index', 'geeky-bot'),
                'critical',
                __('The product search index is missing. Geeky Bot is falling back to slower matching with no relevance ranking. Reactivate the plugin to rebuild it.', 'geeky-bot')
            );
        }

        usort($rows, function ($a, $b) {
            return (int) $a->Seq_in_index <=> (int) $b->Seq_in_index;
        });

        $columns = array();
        foreach ($rows as $row) {
            $columns[] = (string) $row->Column_name;
        }

        if ($columns !== $expected) {
            return $this->result(
                'product_index_fulltext',
                __('Product search index', 'geeky-bot'),
                'critical',
                sprintf(
                    /* translators: 1: current column list, 2: expected column list. */
                    __('The product search index covers the wrong columns, so every search silently falls back to slower matching with no relevance ranking. Found: %1$s. Expected: %2$s. Reactivating the plugin repairs it.', 'geeky-bot'),
                    implode(', ', $columns),
                    implode(', ', $expected)
                )
            );
        }

        // The schema is right, so report what the last real query actually did.
        $state = get_option(self::FULLTEXT_STATE_OPTION, array());
        if (is_array($state) && isset($state['status']) && $state['status'] === 'failing') {
            return $this->result(
                'product_index_fulltext',
                __('Product search index', 'geeky-bot'),
                'critical',
                sprintf(
                    /* translators: %s: database error message. */
                    __('The index looks correct but the last product search failed in the database and fell back to slower matching. Error: %s', 'geeky-bot'),
                    isset($state['error']) ? (string) $state['error'] : ''
                )
            );
        }

        return $this->result(
            'product_index_fulltext',
            __('Product search index', 'geeky-bot'),
            'ok',
            __('The product search index covers the expected columns.', 'geeky-bot')
        );
    }

    private function check_index_freshness() {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_product_index';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        $indexed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $published = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", 'product', 'publish')
        );

        if ($published === 0) {
            return $this->result('product_index_freshness', __('Indexed products', 'geeky-bot'), 'ok', __('This store has no published products yet.', 'geeky-bot'));
        }

        if ($indexed === 0) {
            return $this->result(
                'product_index_freshness',
                __('Indexed products', 'geeky-bot'),
                'critical',
                sprintf(
                    /* translators: %d: published product count. */
                    __('None of your %d published products are indexed, so Geeky Bot cannot recommend anything. Rebuild the product index from the Geeky Bot settings.', 'geeky-bot'),
                    $published
                )
            );
        }

        // A small gap is normal: drafts, private products and pending syncs.
        $missing = $published - $indexed;
        if ($missing > 0 && $missing > max(5, (int) round($published * 0.1))) {
            return $this->result(
                'product_index_freshness',
                __('Indexed products', 'geeky-bot'),
                'warning',
                sprintf(
                    /* translators: 1: indexed count, 2: published count. */
                    __('Only %1$d of %2$d published products are indexed. Rebuild the product index so recommendations cover your whole catalog.', 'geeky-bot'),
                    $indexed,
                    $published
                )
            );
        }

        return $this->result(
            'product_index_freshness',
            __('Indexed products', 'geeky-bot'),
            'ok',
            sprintf(
                /* translators: 1: indexed count, 2: published count. */
                __('%1$d of %2$d published products are indexed.', 'geeky-bot'),
                $indexed,
                $published
            )
        );
    }

    private function check_store_vocabulary() {
        $learned = count((array) (new FamilyVocabularyService())->map()['terms']);

        if (!taxonomy_exists('product_cat')) {
            return $this->result('store_vocabulary', __('Store vocabulary', 'geeky-bot'), 'ok', __('WooCommerce categories are unavailable, so only the built-in vocabulary is used.', 'geeky-bot'));
        }

        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true, 'fields' => 'count'));
        $category_count = is_wp_error($categories) ? 0 : (int) $categories;

        if ($category_count === 0) {
            return $this->result(
                'store_vocabulary',
                __('Store vocabulary', 'geeky-bot'),
                'warning',
                __('No product categories have products in them, so Geeky Bot can only fall back to its built-in wording. Categorising your products teaches it the words your shoppers use.', 'geeky-bot')
            );
        }

        if ($learned === 0) {
            return $this->result(
                'store_vocabulary',
                __('Store vocabulary', 'geeky-bot'),
                'warning',
                __('Your product categories have not been read yet, so searches rely on built-in wording only. Rebuild the product index to learn them.', 'geeky-bot')
            );
        }

        return $this->result(
            'store_vocabulary',
            __('Store vocabulary', 'geeky-bot'),
            'ok',
            sprintf(
                /* translators: 1: learned word count, 2: category count. */
                __('%1$d product words learned from your %2$d categories.', 'geeky-bot'),
                $learned,
                $category_count
            )
        );
    }

    private function check_knowledge_sources() {
        global $wpdb;

        $selected = array_filter(array_map('absint', (array) Settings::get('policy_page_ids', array())));
        if (empty($selected)) {
            return $this->result(
                'knowledge_sources',
                __('Store policy answers', 'geeky-bot'),
                'warning',
                __('No policy pages are selected, so Geeky Bot cannot answer questions about shipping, returns or refunds. Choose your policy pages in the Geeky Bot settings.', 'geeky-bot')
            );
        }

        $table = $wpdb->prefix . 'geekybot_knowledge_index';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        $indexed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($indexed === 0) {
            return $this->result(
                'knowledge_sources',
                __('Store policy answers', 'geeky-bot'),
                'critical',
                __('Policy pages are selected but none are indexed, so policy questions fall back to "please contact the store". Re-sync your knowledge sources in the Geeky Bot settings.', 'geeky-bot')
            );
        }

        if ($indexed < count($selected)) {
            return $this->result(
                'knowledge_sources',
                __('Store policy answers', 'geeky-bot'),
                'warning',
                sprintf(
                    /* translators: 1: indexed count, 2: selected count. */
                    __('%1$d of %2$d selected policy pages are indexed. Re-sync your knowledge sources so every page can be quoted.', 'geeky-bot'),
                    $indexed,
                    count($selected)
                )
            );
        }

        return $this->result(
            'knowledge_sources',
            __('Store policy answers', 'geeky-bot'),
            'ok',
            sprintf(
                /* translators: %d: indexed policy page count. */
                __('%d policy pages are indexed and available for answers.', 'geeky-bot'),
                $indexed
            )
        );
    }

    private function check_assistant_reachable() {
        if (Settings::get('widget_enabled', 'yes') !== 'yes') {
            return $this->result(
                'assistant_enabled',
                __('Assistant visibility', 'geeky-bot'),
                'warning',
                __('The storefront assistant is switched off, so shoppers cannot see Geeky Bot.', 'geeky-bot')
            );
        }

        if (!class_exists('WooCommerce')) {
            return $this->result(
                'assistant_enabled',
                __('Assistant visibility', 'geeky-bot'),
                'critical',
                __('WooCommerce is not active, so Geeky Bot cannot read your catalog.', 'geeky-bot')
            );
        }

        return $this->result(
            'assistant_enabled',
            __('Assistant visibility', 'geeky-bot'),
            'ok',
            __('The storefront assistant is enabled and WooCommerce is active.', 'geeky-bot')
        );
    }

    private function result($id, $label, $status, $message) {
        return array(
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'message' => $message,
        );
    }

    /**
     * Expose the checks through WP Site Health.
     *
     * @param array $tests Registered tests.
     * @return array
     */
    public function register_site_health_tests($tests) {
        $tests['direct']['geekybot_search'] = array(
            'label' => __('Geeky Bot search health', 'geeky-bot'),
            'test' => array($this, 'site_health_report'),
        );

        return $tests;
    }

    /**
     * @return array
     */
    public function site_health_report() {
        $checks = $this->checks();
        $failures = array_values(array_filter($checks, function ($check) {
            return $check['status'] !== 'ok';
        }));

        if (empty($failures)) {
            return array(
                'label' => __('Geeky Bot search is healthy', 'geeky-bot'),
                'status' => 'good',
                'badge' => array('label' => __('Geeky Bot', 'geeky-bot'), 'color' => 'blue'),
                'description' => '<p>' . esc_html__('The product index, search index and policy sources are all working.', 'geeky-bot') . '</p>',
                'test' => 'geekybot_search',
            );
        }

        $critical = array_filter($failures, function ($check) {
            return $check['status'] === 'critical';
        });

        $items = '';
        foreach ($failures as $check) {
            $items .= '<li><strong>' . esc_html($check['label']) . ':</strong> ' . esc_html($check['message']) . '</li>';
        }

        return array(
            'label' => empty($critical)
                ? __('Geeky Bot search needs attention', 'geeky-bot')
                : __('Geeky Bot search is degraded', 'geeky-bot'),
            'status' => empty($critical) ? 'recommended' : 'critical',
            'badge' => array('label' => __('Geeky Bot', 'geeky-bot'), 'color' => empty($critical) ? 'orange' : 'red'),
            'description' => '<ul>' . $items . '</ul>',
            'test' => 'geekybot_search',
        );
    }

    /**
     * Warn in the admin when the assistant is answering from a degraded state.
     *
     * @return void
     */
    public function render_critical_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $on_geekybot_screen = $screen && isset($screen->id) && strpos((string) $screen->id, 'geekybot') !== false;
        if (!$on_geekybot_screen && !($screen && isset($screen->id) && $screen->id === 'dashboard')) {
            return;
        }

        $critical = array_values(array_filter($this->checks(), function ($check) {
            return $check['status'] === 'critical';
        }));

        if (empty($critical)) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Geeky Bot is answering from a degraded state.', 'geeky-bot') . '</strong></p><ul style="margin-left:18px;list-style:disc;">';
        foreach ($critical as $check) {
            echo '<li>' . esc_html($check['message']) . '</li>';
        }
        echo '</ul></div>';
    }
}
