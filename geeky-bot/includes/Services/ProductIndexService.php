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
    const AUTO_INDEX_VERSION = '1';

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
            FULLTEXT KEY gb_fulltext (title, sku, categories, tags, attributes, color_terms, size_terms, search_text)
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

    public function sync_product_by_id($product_id) {
        $product = function_exists('wc_get_product') ? wc_get_product(absint($product_id)) : null;
        if (!$product) {
            return false;
        }
        return $this->sync_wc_product($product);
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
        if (!get_option(self::REBUILD_PENDING_OPTION)) {
            return 'current';
        }

        if (!self::woocommerce_ready()) {
            return 'waiting_for_woocommerce';
        }

        return wp_next_scheduled(self::REBUILD_HOOK) ? 'scheduled' : 'pending';
    }

    private static function woocommerce_ready() {
        return function_exists('wc_get_product');
    }

    public function scheduled_rebuild() {
        if (!get_option(self::REBUILD_PENDING_OPTION) || !self::woocommerce_ready()) {
            return;
        }

        $this->rebuild();
    }

    public function delete_product($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        global $wpdb;
        $wpdb->delete(self::table_name(), array('product_id' => absint($post_id)), array('%d'));
    }

    public function rebuild($limit = 0) {
        if (!self::woocommerce_ready()) {
            return array('indexed' => 0, 'skipped' => 0, 'complete' => false);
        }

        $pending_marker = get_option(self::REBUILD_PENDING_OPTION, '');
        global $wpdb;
        self::create_table();
        $table = self::table_name();
        $wpdb->query("TRUNCATE TABLE {$table}");

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => $limit ? max(1, absint($limit)) : -1,
            'no_found_rows' => true,
        );

        $ids = get_posts($args);
        $indexed = 0;
        $skipped = 0;

        foreach ($ids as $product_id) {
            $ok = $this->sync_product_by_id($product_id);
            $ok ? $indexed++ : $skipped++;
        }

        $complete = !$limit;
        if ($complete) {
            update_option(self::LAST_REBUILD_OPTION, current_time('mysql'), false);
            if (get_option(self::REBUILD_PENDING_OPTION, '') === $pending_marker) {
                delete_option(self::REBUILD_PENDING_OPTION);
            }
        }

        return array('indexed' => $indexed, 'skipped' => $skipped, 'complete' => $complete);
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
        $candidate_limit = min(120, max(60, $limit * 12));
        $rows = $this->candidate_rows($analysis, $candidate_limit);
        if (empty($rows)) {
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

        return CatalogVisibilityService::filter_ids(
            array_values(array_unique(array_map('absint', $ids))),
            $limit,
            'product_index_search'
        );
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

        $searchable = $language->strip_commerce_phrases($language->strip_price_filters($lower));
        $searchable = $language->strip_negative_facets($searchable);
        $searchable = $language->strip_buyer_modifier_phrases($searchable);
        $base_terms = $this->remove_weak_shopper_terms($language->remove_intent_terms($language->query_terms($searchable), $intent));
        $base_terms = $language->remove_buyer_intent_tokens($base_terms, $buyer_profile);
        $expanded = $language->expand_synonyms($searchable);
        $terms = $this->remove_weak_shopper_terms($language->query_terms($expanded));
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
            $modifier_terms
        );
        $core_terms = array_values(array_unique(array_filter(array_merge(
            (array) ($product_phrase['search_terms'] ?? array()),
            $this->core_product_terms($terms, $color_terms, $size_terms, $negative_color_terms, $negative_size_terms, $modifier_terms)
        ))));
        $display_core_terms = array_values(array_unique(array_filter(array_merge(
            (array) ($product_phrase['terms'] ?? array()),
            $this->core_product_terms($base_terms, $color_terms, $size_terms, $negative_color_terms, $negative_size_terms, $modifier_terms)
        ))));
        $boolean_terms = array_values(array_unique(array_filter(array_merge($core_terms, $color_terms, $size_terms))));
        $budget_sort = $language->contains_budget_signal($lower);
        $value_sort = $language->contains_value_signal($lower);

        return array(
            'raw' => $clean,
            'lower' => $lower,
            'searchable' => $searchable,
            'expanded' => $expanded,
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
            'boolean' => $this->boolean_query($boolean_terms),
            'phrase' => !empty($product_phrase['phrase']) ? $product_phrase['phrase'] : $this->phrase_for_like($searchable),
            'product_phrase' => !empty($product_phrase['phrase']) ? $product_phrase['phrase'] : '',
            'product_phrase_terms' => !empty($product_phrase['terms']) ? (array) $product_phrase['terms'] : array(),
            'product_family_term' => !empty($product_phrase['family']) ? (string) $product_phrase['family'] : '',
            'product_family_source' => !empty($product_phrase['family_source']) ? (string) $product_phrase['family_source'] : '',
            'product_family_aliases' => !empty($product_phrase['family_aliases']) ? (array) $product_phrase['family_aliases'] : array(),
            'required_family_aliases' => !empty($product_phrase['required_family_aliases']) ? (array) $product_phrase['required_family_aliases'] : array(),
            'family_gate_aliases' => !empty($product_phrase['family_gate_aliases']) ? (array) $product_phrase['family_gate_aliases'] : array(),
            'product_qualifier_terms' => !empty($product_phrase['qualifier_terms']) ? (array) $product_phrase['qualifier_terms'] : array(),
            'price_range' => $price_range,
            'intent' => $sale_required ? 'sale' : $intent,
            'sale_required' => $sale_required,
            'budget_sort' => $budget_sort,
            'value_sort' => $value_sort,
            'language' => $language->language_code($lower),
            'in_stock_only' => $language->is_in_stock_query($lower),
        );
    }

    private function sync_wc_product($product) {
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
        $table = self::table_name();
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
            'product_url' => get_permalink($product_id),
            'image_url' => $image ? esc_url_raw($image) : '',
            'created_at' => get_post_time('Y-m-d H:i:s', false, $product_id) ?: $now,
            'updated_at' => $now,
        );

        $formats = array('%d', '%s', '%s', '%s', '%f', '%f', '%f', '%d', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE product_id = %d", $product_id));
        if ($exists) {
            return false !== $wpdb->update($table, $data, array('product_id' => $product_id), $formats, array('%d'));
        }
        return false !== $wpdb->insert($table, $data, $formats);
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
            $sql = "SELECT *, MATCH(title, sku, categories, tags, attributes, color_terms, size_terms, search_text) AGAINST (%s IN BOOLEAN MODE) AS ft_score FROM {$table} WHERE {$where_sql} AND MATCH(title, sku, categories, tags, attributes, color_terms, size_terms, search_text) AGAINST (%s IN BOOLEAN MODE) ORDER BY {$order_sql} LIMIT %d";
            $params_for_fulltext = array_merge(array($boolean), $params, array($boolean, absint($limit)));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic clauses are selected from internal allowlists and all shopper values use placeholders.
            $rows = $wpdb->get_results($wpdb->prepare($sql, $params_for_fulltext));
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
            if ($this->row_text_has_term((string) $row->title . ' ' . (string) $row->sku . ' ' . (string) $row->categories . ' ' . (string) $row->tags . ' ' . (string) $row->attributes, $core_term)) {
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
            $core_clauses[] = '(title LIKE %s OR sku LIKE %s OR categories LIKE %s OR tags LIKE %s OR attributes LIKE %s)';
            array_push($core_params, $like, $like, $like, $like, $like);
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
            $color_clauses[] = '(color_terms LIKE %s OR attributes LIKE %s OR title LIKE %s OR tags LIKE %s)';
            array_push($color_params, $like, $like, $like, $like);
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
            $size_clauses[] = '(size_terms LIKE %s OR attributes LIKE %s OR title LIKE %s OR tags LIKE %s)';
            array_push($size_params, $like, $like, $like, $like);
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
            $where[] = '(color_terms NOT LIKE %s AND attributes NOT LIKE %s AND title NOT LIKE %s AND tags NOT LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        foreach (array_slice((array) ($analysis['negative_size_terms'] ?? array()), 0, 6) as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '') {
                continue;
            }
            $like = '%' . $wpdb->esc_like($term) . '%';
            $where[] = '(size_terms NOT LIKE %s AND attributes NOT LIKE %s AND title NOT LIKE %s AND tags NOT LIKE %s)';
            array_push($params, $like, $like, $like, $like);
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
            if ($this->row_text_has_term($product_text, $term)) {
                return true;
            }
        }

        return false;
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
        // Family identity must come from the product's identifying catalog
        // fields. Descriptive copy such as "bottle pocket" on a backpack must
        // never make that backpack eligible for a water-bottle search.
        $family_identity_text = (string) $row->title . ' ' . (string) $row->sku . ' ' . (string) $row->categories;
        $product_text = $family_identity_text . ' ' . (string) $row->tags . ' ' . (string) $row->attributes . ' ' . (string) $row->search_text;
        if (!$this->row_matches_any_term($family_identity_text, $required_aliases)) {
            return false;
        }

        $qualifiers = !empty($analysis['product_qualifier_terms'])
            ? (array) $analysis['product_qualifier_terms']
            : (array) ($analysis['product_phrase_terms'] ?? array());
        foreach ($qualifiers as $term) {
            $term = $this->normalize_index_text($term);
            if ($term === '' || in_array($term, $family_aliases, true) || in_array($term, $required_aliases, true) || $term === $family) {
                continue;
            }
            if (!$this->row_text_has_term($product_text, $term)) {
                return false;
            }
        }

        return true;
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

    private function product_phrase_profile($query, $color_terms, $size_terms, $negative_color_terms, $negative_size_terms, $modifier_terms) {
        $language = $this->search_language();
        $normalized = $language->normalize_text($query);
        $terms = $language->query_terms($normalized);

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

        $family = '';
        $family_index = -1;
        foreach ($clean as $index => $term) {
            $canonical = $this->canonical_product_family($term);
            if ($canonical !== '') {
                $family = $canonical;
                $family_index = $index;
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
            'search_terms' => $search_terms,
        );
    }

    private function canonical_product_family($term) {
        $term = $this->normalize_index_text($term);
        $map = array(
            'accessory' => 'accessory', 'accessories' => 'accessory',
            'adapter' => 'adapter', 'adapters' => 'adapter',
            'bag' => 'bag', 'bags' => 'bag', 'backpack' => 'bag', 'backpacks' => 'bag', 'tote' => 'bag', 'totes' => 'bag',
            'belt' => 'belt', 'belts' => 'belt',
            'bottle' => 'bottle', 'bottles' => 'bottle', 'flask' => 'bottle', 'flasks' => 'bottle',
            'case' => 'case', 'cases' => 'case', 'cover' => 'case', 'covers' => 'case',
            'charger' => 'charger', 'chargers' => 'charger',
            'clock' => 'clock', 'clocks' => 'clock',
            'coat' => 'jacket', 'coats' => 'jacket', 'jacket' => 'jacket', 'jackets' => 'jacket',
            'cup' => 'drinkware', 'cups' => 'drinkware', 'mug' => 'drinkware', 'mugs' => 'drinkware', 'tumbler' => 'drinkware', 'tumblers' => 'drinkware', 'drinkware' => 'drinkware',
            'dress' => 'dress', 'dresses' => 'dress',
            'earbud' => 'earbud', 'earbuds' => 'earbud', 'headphone' => 'earbud', 'headphones' => 'earbud',
            'footrest' => 'footrest', 'footrests' => 'footrest',
            'hoodie' => 'hoodie', 'hoodies' => 'hoodie',
            'keyboard' => 'keyboard', 'keyboards' => 'keyboard',
            'lamp' => 'lamp', 'lamps' => 'lamp',
            'mat' => 'mat', 'mats' => 'mat',
            'organiser' => 'organiser', 'organisers' => 'organiser', 'organizer' => 'organiser', 'organizers' => 'organiser', 'pouch' => 'organiser', 'pouches' => 'organiser',
            'pillow' => 'pillow', 'pillows' => 'pillow',
            'powerbank' => 'powerbank', 'powerbanks' => 'powerbank',
            'scarf' => 'scarf', 'scarves' => 'scarf',
            'shirt' => 'shirt', 'shirts' => 'shirt',
            'shoe' => 'shoe', 'shoes' => 'shoe', 'sneaker' => 'shoe', 'sneakers' => 'shoe', 'trainer' => 'shoe', 'trainers' => 'shoe',
            'sleeve' => 'sleeve', 'sleeves' => 'sleeve',
            'speaker' => 'speaker', 'speakers' => 'speaker',
            'wallet' => 'wallet', 'wallets' => 'wallet',
            'watch' => 'watch', 'watches' => 'watch',
        );
        return isset($map[$term]) ? $map[$term] : '';
    }

    /**
     * Returns the hard family gate for the exact family noun used by the shopper.
     *
     * Broad catalog families remain useful for ranking and fallback, but a
     * specific noun such as `backpack` must not silently widen to every bag,
     * sleeve, tote, organiser, or pouch.
     */
    private function required_product_family_aliases($family, $family_source = '') {
        $family = $this->normalize_index_text($family);
        $source = $this->normalize_index_text($family_source);
        $specific = array(
            'backpack' => array('backpack', 'rucksack'),
            'backpacks' => array('backpack', 'rucksack'),
            'tote' => array('tote'),
            'totes' => array('tote'),
            'sleeve' => array('sleeve'),
            'sleeves' => array('sleeve'),
            'pouch' => array('pouch', 'organiser', 'organizer'),
            'pouches' => array('pouch', 'organiser', 'organizer'),
            'organiser' => array('organiser', 'organizer', 'pouch'),
            'organisers' => array('organiser', 'organizer', 'pouch'),
            'organizer' => array('organiser', 'organizer', 'pouch'),
            'organizers' => array('organiser', 'organizer', 'pouch'),
            'mug' => array('mug'),
            'mugs' => array('mug'),
            'tumbler' => array('tumbler'),
            'tumblers' => array('tumbler'),
            'cup' => array('cup', 'mug'),
            'cups' => array('cup', 'mug'),
            'headphone' => array('headphone'),
            'headphones' => array('headphone'),
            'earbud' => array('earbud'),
            'earbuds' => array('earbud'),
        );

        if ($source !== '' && isset($specific[$source])) {
            return array_values(array_unique(array_filter($specific[$source])));
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

    private function product_family_aliases($family) {
        $family = $this->normalize_index_text($family);
        $map = array(
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
        $aliases = isset($map[$family]) ? $map[$family] : array($family);
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

    private function row_matches_any_term($text, $terms) {
        foreach ((array) $terms as $term) {
            if ($this->row_text_has_term($text, $term)) {
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

    private function boolean_query($terms) {
        $items = array();
        foreach (array_slice((array) $terms, 0, 8) as $term) {
            $term = preg_replace('/[^\pL\pN_\-]/u', '', $term);
            if ($term === '' || strlen($term) < 2) {
                continue;
            }
            $items[] = '+' . $term . '*';
        }
        return implode(' ', $items);
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

    private function search_language() {
        static $language = null;
        if ($language === null) {
            $language = new SearchLanguageService();
        }
        return $language;
    }
}
