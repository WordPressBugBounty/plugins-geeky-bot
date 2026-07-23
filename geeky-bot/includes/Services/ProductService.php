<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class ProductService {
    private $last_search_context = array();

    public function is_woocommerce_ready() {
        return class_exists('WooCommerce') && function_exists('wc_get_product');
    }

    public function search($query, $limit = 4) {
        $this->last_search_context = array('note' => '', 'analysis' => array());

        if (!$this->is_woocommerce_ready()) {
            return array();
        }

        $query = $this->clean_query($query);
        $limit = max(1, min(8, absint($limit)));

        $named_resolver = new NamedProductResolver();
        $named_request = $named_resolver->resolve_direct_request($query);
        if (empty($named_request['handled'])) {
            $named_request = $named_resolver->resolve_bare_exact_name($query);
        }
        if (!empty($named_request['handled'])) {
            $subject = !empty($named_request['subject'])
                ? wp_strip_all_tags((string) $named_request['subject'])
                : wp_strip_all_tags($query);
            $analysis = array(
                'raw' => $query,
                'display_core_terms' => array($subject),
                'core_terms' => array($subject),
            );

            if ($named_request['status'] === 'resolved' && !empty($named_request['productId'])) {
                $this->last_search_context = $this->search_context('named_product_match', $analysis);
                $this->last_search_context['requestedLabel'] = $subject;
                $this->last_search_context['commandMeta'] = array(
                    'namedProductId' => absint($named_request['productId']),
                    'namedProductName' => !empty($named_request['productName'])
                        ? wp_strip_all_tags((string) $named_request['productName'])
                        : $subject,
                );
                return $this->hydrate_products(array(absint($named_request['productId'])));
            }

            if ($named_request['status'] === 'not_visible') {
                $this->last_search_context = $this->search_context('named_product_not_visible', $analysis);
                $this->last_search_context['requestedLabel'] = $subject;
                $this->last_search_context['commandMeta'] = array(
                    'requestedProductName' => $subject,
                    'visibilityBlocked' => true,
                );
                return array();
            }
        }

        if ($query === '') {
            $this->last_search_context = array('note' => 'latest_empty_query', 'analysis' => array());
            return $this->latest($limit);
        }

        if (Settings::get('natural_search_enabled', 'yes') !== 'yes') {
            $this->last_search_context = array('note' => 'keyword_search_mode', 'analysis' => array('raw' => $query, 'display_core_terms' => array($query)));
            return $this->hydrate_products($this->simple_keyword_ids($query, $limit));
        }

        $index = new ProductIndexService();
        $analysis = $index->analyze_query($query);
        // Normalize sale into an explicit hard constraint before any browse or
        // fallback decision. Older analyses remain compatible through
        // analysis_requires_sale().
        if ($this->analysis_requires_sale($analysis)) {
            $analysis['sale_required'] = true;
            $analysis['intent'] = 'sale';
        }
        $intent = isset($analysis['intent']) ? $analysis['intent'] : $this->catalog_intent($query);
        // Product-family evidence must survive intent-token stripping. Queries
        // such as `sale keyboards` remove `sale` from normal terms but still
        // carry a mandatory keyboard family. Treat that as a product-specific
        // mission so it can never fall back to unrelated whole-catalog sale
        // results when no matching keyboard is discounted.
        $has_product_terms = !empty($analysis['terms'])
            || !empty($analysis['core_terms'])
            || !empty($analysis['product_phrase_terms'])
            || !empty($analysis['product_family_term']);
        $has_structured_facets = !empty($analysis['color_terms']) || !empty($analysis['size_terms']);

        // Broad browse requests should browse. Product-specific requests should still
        // use the search index, e.g. "belt on sale" must not return random sale items.
        if (!$has_product_terms && $intent === 'latest') {
            $this->last_search_context = array('note' => 'latest_intent', 'analysis' => $analysis);
            return $this->latest($limit);
        }
        if (!$has_product_terms && $intent === 'sale') {
            $this->last_search_context = array('note' => 'sale_intent', 'analysis' => $analysis);
            return $this->sale_products($limit);
        }
        if (!$has_product_terms && !empty($analysis['in_stock_only'])) {
            $this->last_search_context = array('note' => 'in_stock_intent', 'analysis' => $analysis);
            return $this->available_products($limit);
        }
        if (!$has_product_terms && $intent === 'top_rated') {
            $this->last_search_context = array('note' => 'top_rated_intent', 'analysis' => $analysis);
            return $this->top_rated($limit);
        }

        $this->last_search_context = $this->search_context('normal_search', $analysis);

        $product_ids = $index->search_ids($query, $limit, $analysis);
        $searchable_query = !empty($analysis['searchable']) ? $analysis['searchable'] : $query;

        if (empty($product_ids) && !$has_structured_facets && !$has_product_terms) {
            $product_ids = $this->sku_match_ids($searchable_query, $limit);
        }

        if (empty($product_ids) && !$has_structured_facets && !$has_product_terms && $searchable_query !== '') {
            $product_ids = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                's' => $searchable_query,
                'fields' => 'ids',
                'no_found_rows' => true,
            ));
        }

        if (empty($product_ids) && !$has_structured_facets && !$has_product_terms && $searchable_query !== '') {
            $product_ids = $this->taxonomy_fallback_ids($searchable_query, $limit);
        }

        if (!empty($product_ids)) {
            $product_ids = $this->diversify_by_core_terms($index, $analysis, $product_ids, $query, $limit);
            $product_ids = $this->filter_by_price_words($product_ids, $query, $limit);
            $product_ids = $this->enforce_hard_runtime_filters($product_ids, $analysis, $limit);
            $product_ids = $this->prioritize_exact_query_phrases($product_ids, $query);
        }

        if (empty($product_ids) && !$has_product_terms && $intent === 'sale' && !$has_structured_facets && empty($analysis['price_range'])) {
            $sale_ids = $this->sale_product_ids($limit);
            if (!empty($sale_ids)) {
                $this->last_search_context = $this->search_context('sale_intent', $analysis);
                return $this->hydrate_products($sale_ids);
            }
        }

        if (empty($product_ids) && $has_structured_facets && $has_product_terms && Settings::get('search_close_match_mode', 'smart') !== 'strict') {
            $alternative_ids = $this->facet_relaxed_alternative_ids($index, $analysis, $query, $limit);
            if (!empty($alternative_ids)) {
                $this->last_search_context = $this->search_context('facet_alternatives', $analysis);
                return $this->hydrate_products($alternative_ids);
            }
        }

        if (empty($product_ids) && !empty($analysis['price_range'])) {
            if (!$has_product_terms && !$has_structured_facets) {
                $alternative_ids = $this->price_only_alternative_ids($analysis['price_range'], $limit);
                if (!empty($alternative_ids)) {
                    $this->last_search_context = $this->search_context('price_only_results', $analysis);
                    return $this->hydrate_products($alternative_ids);
                }
            }

            $this->last_search_context = $this->search_context('price_no_match', $analysis);
        } elseif (empty($product_ids) && ($has_product_terms || $has_structured_facets || !empty($analysis['intent']) && $analysis['intent'] !== 'search')) {
            $this->last_search_context = $this->search_context('product_no_match', $analysis);
        }

        return $this->hydrate_products($product_ids);
    }

    public function last_search_context() {
        return (array) $this->last_search_context;
    }

    /**
     * Applies a short follow-up to an ordered result set without turning the
     * follow-up into a brand-new global catalog search.
     */
    public function context_products($product_ids, $mode, $limit = 4, $previous_analysis = array(), $modifier_query = '', $selection_index = null, $command_args = array()) {
        if (!$this->is_woocommerce_ready()) {
            return array();
        }

        $limit = max(1, min(8, absint($limit)));
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $product_ids))));
        if (empty($ids)) {
            return array();
        }

        if ($mode === 'restore') {
            $this->last_search_context = $this->search_context('context_restore', is_array($previous_analysis) ? $previous_analysis : array());
            $this->last_search_context['conversationAction'] = 'go_back';
            return $this->hydrate_products(array_slice($ids, 0, $limit));
        }

        $modifier_analysis = array();
        if ($modifier_query !== '') {
            $modifier_analysis = (new ProductIndexService())->analyze_query($modifier_query);
        }

        $analysis = $this->merge_context_analysis($previous_analysis, $modifier_analysis);
        if ($mode === 'premium') {
            $analysis = $this->premium_context_analysis($analysis);
        } elseif ($mode === 'remove_constraint') {
            $analysis = $this->remove_constraint_from_analysis($analysis, $command_args);
        }

        $filter_analysis = $analysis;
        $valid = array();

        foreach ($ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_status() !== 'publish') {
                continue;
            }

            if (!$this->product_matches_requested_variation($product, $analysis)) {
                continue;
            }

            if (!empty($analysis['in_stock_only']) && !CatalogAvailabilityService::is_available($product)) {
                continue;
            }
            if ($this->analysis_requires_sale($analysis) && !$product->is_on_sale()) {
                continue;
            }

            $category_names = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
            if (is_wp_error($category_names)) {
                $category_names = array();
            }
            $product_text = $this->product_match_text($product, $category_names);

            if (!empty($filter_analysis['color_terms']) && !$this->text_has_any_search_term($product_text, (array) $filter_analysis['color_terms'])) {
                continue;
            }
            if (!empty($filter_analysis['size_terms']) && !$this->text_has_any_search_term($product_text, (array) $filter_analysis['size_terms'])) {
                continue;
            }
            if (!empty($filter_analysis['negative_color_terms']) && $this->text_has_any_search_term($product_text, (array) $filter_analysis['negative_color_terms'])) {
                continue;
            }
            if (!empty($filter_analysis['negative_size_terms']) && $this->text_has_any_search_term($product_text, (array) $filter_analysis['negative_size_terms'])) {
                continue;
            }
            if (!empty($filter_analysis['price_range']) && !$this->product_price_overlaps_range($product, $filter_analysis['price_range'])) {
                continue;
            }

            $valid[] = $product_id;
        }

        if (empty($valid)) {
            $this->last_search_context = $this->search_context('context_' . sanitize_key($mode) . '_no_match', $analysis);
            $this->last_search_context['conversationAction'] = sanitize_key($mode);
            return array();
        }

        if ($mode === 'select' && $selection_index !== null) {
            $selection_index = absint($selection_index);
            $valid = isset($valid[$selection_index]) ? array($valid[$selection_index]) : array();
        } elseif ($mode === 'best') {
            $valid = array_slice($valid, 0, 1);
        } elseif ($mode === 'best_discount') {
            usort($valid, function ($a, $b) {
                $pa = wc_get_product($a);
                $pb = wc_get_product($b);
                $discount_a = $this->discount_percent($pa);
                $discount_b = $this->discount_percent($pb);
                if ($discount_a === $discount_b) {
                    return $a <=> $b;
                }
                return $discount_b <=> $discount_a;
            });
            $valid = array_slice($valid, 0, 1);
        } elseif ($mode === 'cheaper') {
            usort($valid, function ($a, $b) {
                $pa = wc_get_product($a);
                $pb = wc_get_product($b);
                $price_a = $pa && $pa->get_price() !== '' ? (float) $pa->get_price() : PHP_FLOAT_MAX;
                $price_b = $pb && $pb->get_price() !== '' ? (float) $pb->get_price() : PHP_FLOAT_MAX;
                if ($price_a === $price_b) {
                    return $a <=> $b;
                }
                return $price_a <=> $price_b;
            });
        } elseif ($mode === 'premium') {
            usort($valid, function ($a, $b) {
                $pa = wc_get_product($a);
                $pb = wc_get_product($b);
                $score_a = $pa ? ((float) $pa->get_price() + ((float) $pa->get_average_rating() * 8) + ($pa->is_featured() ? 20 : 0)) : 0;
                $score_b = $pb ? ((float) $pb->get_price() + ((float) $pb->get_average_rating() * 8) + ($pb->is_featured() ? 20 : 0)) : 0;
                if ($score_a === $score_b) {
                    return $b <=> $a;
                }
                return $score_b <=> $score_a;
            });
        }

        $note = 'context_' . sanitize_key($mode);
        $this->last_search_context = $this->search_context($note, $analysis);
        $this->last_search_context['conversationAction'] = sanitize_key($mode);

        if ($mode === 'best_discount' && !empty($valid)) {
            $product = wc_get_product($valid[0]);
            $this->last_search_context['commandMeta'] = array(
                'discountPercent' => $this->discount_percent($product),
            );
        }

        return $this->hydrate_products(array_slice($valid, 0, $limit));
    }


    /**
     * Removes one or more active shopping constraints and reruns the preserved mission
     * against both the earlier mission pool and a bounded live catalog scan.
     *
     * Keeping this as one operation prevents command state from being removed
     * twice or from being reintroduced by a later context pass.
     */
    public function products_after_constraint_removal($analysis, $args, $limit = 4, $preferred_product_ids = array()) {
        $original_analysis = is_array($analysis) ? $analysis : array();
        $args = is_array($args) ? $args : array();
        $types = $this->constraint_types_from_args($args);
        $type = !empty($types[0]) ? $types[0] : '';
        $removed_colors = array();

        if (in_array('color', $types, true)) {
            $color_value = $this->constraint_value_from_args($args, 'color');
            if ($color_value !== '') {
                $removed_colors[] = $color_value;
            } else {
                $removed_colors = array_merge(
                    (array) ($original_analysis['color_terms'] ?? array()),
                    (array) ($original_analysis['requested_color_labels'] ?? array())
                );
            }
            $removed_colors = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $removed_colors))));
        }

        $analysis = $this->remove_constraint_from_analysis($original_analysis, $args);
        $products = $this->products_matching_analysis(
            $analysis,
            max(1, min(8, absint($limit))),
            $preferred_product_ids,
            array(
                // Removing a color should visibly reopen the result set without
                // excluding the old color completely. Products with a confirmed
                // different color receive a modest diversity preference.
                'promoteColorsOutside' => $removed_colors,
                'forceCatalogScan' => true,
            )
        );

        $this->last_search_context = $this->search_context('context_remove_constraint', $analysis);
        $this->last_search_context['conversationAction'] = 'remove_constraint';
        $this->last_search_context['commandMeta'] = array(
            'constraintType' => $type,
            'constraintTypes' => $types,
            'constraintCount' => count($types),
            'constraintValue' => $type !== '' ? $this->constraint_value_from_args($args, $type) : '',
            'removedColors' => $removed_colors,
        );

        return $products;
    }

    /**
     * Applies a follow-up constraint to the preserved shopping mission and
     * searches the complete bounded catalog without relaxing any active rule.
     *
     * This is intentionally separate from context_products(). Filtering only
     * the last visible rows can miss valid products, while falling back to a raw
     * concatenated query can discard a color/size rule or retain an older price.
     */
    public function products_after_constraint_update($analysis, $modifier_query, $limit = 4, $preferred_product_ids = array()) {
        $analysis = is_array($analysis) ? $analysis : array();
        $modifier_query = wp_strip_all_tags((string) $modifier_query);
        $modifier_analysis = $modifier_query !== ''
            ? (new ProductIndexService())->analyze_query($modifier_query)
            : array();
        $updated_analysis = $this->merge_context_analysis($analysis, $modifier_analysis);
        $updated_analysis = $this->apply_constraint_replacement_semantics(
            $updated_analysis,
            $modifier_analysis,
            $modifier_query
        );

        $products = $this->products_matching_analysis(
            $updated_analysis,
            max(1, min(8, absint($limit))),
            $preferred_product_ids,
            array('forceCatalogScan' => true)
        );

        $note = empty($products) ? 'context_filter_current_no_match' : 'context_filter_current';
        $this->last_search_context = $this->search_context($note, $updated_analysis);
        $this->last_search_context['conversationAction'] = 'filter_current';
        $this->last_search_context['commandMeta'] = array(
            'preservedAllConstraints' => true,
            'modifierQuery' => sanitize_text_field($modifier_query),
        );

        return $products;
    }

    public function search_from_analysis($analysis, $limit = 4) {
        $query = $this->query_from_analysis($analysis);
        if ($query === '') {
            return array();
        }
        return $this->search($query, $limit);
    }

    /**
     * Finds products from a stored analysis when a context command removes a
     * constraint. Search is attempted first, then a bounded catalog scan keeps
     * the command useful even when the original result pool was very small.
     */
    public function products_matching_analysis($analysis, $limit = 4, $preferred_product_ids = array(), $options = array()) {
        $analysis = is_array($analysis) ? $analysis : array();
        $options = is_array($options) ? $options : array();
        $limit = max(1, min(8, absint($limit)));
        $preferred_ids = array_values(array_unique(array_filter(array_map('absint', (array) $preferred_product_ids))));
        $ids = $preferred_ids;
        $promote_colors_outside = !empty($options['promoteColorsOutside'])
            ? array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $options['promoteColorsOutside']))))
            : array();
        $force_catalog_scan = !empty($options['forceCatalogScan']);

        $query = $this->query_from_analysis($analysis);
        if ($query !== '') {
            $searched = $this->search($query, min(8, max($limit, 6)));
            $ids = array_merge($ids, $this->product_ids_from_payload($searched));
        }

        // Constraint removal is a full-mission reconstruction. Always add a
        // bounded catalog pool for that path; counting unfiltered preferred IDs
        // before hard filters can otherwise hide valid products outside the last
        // narrow result set.
        if (($force_catalog_scan || count(array_unique($ids)) < $limit) && function_exists('wc_get_products')) {
            $catalog_ids = wc_get_products(array(
                'status' => 'publish',
                'limit' => 120,
                'return' => 'ids',
                'orderby' => 'date',
                'order' => 'DESC',
            ));
            $ids = array_merge($ids, array_map('absint', (array) $catalog_ids));
        }

        $scored = array();
        foreach (array_values(array_unique(array_filter($ids))) as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_status() !== 'publish' || !$this->product_matches_hard_analysis($product, $analysis)) {
                continue;
            }
            $has_different_color = !empty($promote_colors_outside)
                && $this->has_color_outside_exclusions($this->confirmed_product_colors($product), $promote_colors_outside);
            $score = $this->analysis_preference_score($product, $analysis);
            if (in_array(absint($product_id), $preferred_ids, true)) {
                $score += 6;
            }
            if ($has_different_color) {
                $score += 18;
            }

            $scored[] = array(
                'id' => absint($product_id),
                'score' => $score,
                'different_color' => $has_different_color ? 1 : 0,
                'stockRank' => $product->is_in_stock() ? 0 : 1,
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

        if (!empty($promote_colors_outside)) {
            $different = array();
            $remaining = array();
            foreach ($scored as $item) {
                if (!empty($item['different_color'])) {
                    $different[] = absint($item['id']);
                } else {
                    $remaining[] = absint($item['id']);
                }
            }

            // Show a visible effect after removing a color requirement while
            // keeping the best overall matches in the same result set.
            $diverse_count = min(2, $limit, count($different));
            $result_ids = array_slice($different, 0, $diverse_count);
            foreach (array_merge(array_slice($different, $diverse_count), $remaining) as $product_id) {
                if (!in_array($product_id, $result_ids, true)) {
                    $result_ids[] = $product_id;
                }
                if (count($result_ids) >= $limit) {
                    break;
                }
            }
        } else {
            $result_ids = array_slice(wp_list_pluck($scored, 'id'), 0, $limit);
        }

        $this->last_search_context = $this->search_context('context_analysis_browse', $analysis);
        return $this->hydrate_products($result_ids);
    }

    public function query_from_analysis($analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        $parts = array();

        $core_terms = !empty($analysis['display_core_terms'])
            ? (array) $analysis['display_core_terms']
            : (!empty($analysis['core_terms']) ? (array) $analysis['core_terms'] : array());
        foreach ($core_terms as $term) {
            $term = wp_strip_all_tags((string) $term);
            if ($term !== '') {
                $parts[] = $term;
            }
        }

        foreach ((array) (!empty($analysis['requested_color_labels']) ? $analysis['requested_color_labels'] : array()) as $term) {
            $term = wp_strip_all_tags((string) $term);
            if ($term !== '') {
                $parts[] = $term;
            }
        }
        foreach ((array) (!empty($analysis['requested_size_labels']) ? $analysis['requested_size_labels'] : array()) as $term) {
            $term = wp_strip_all_tags((string) $term);
            if ($term !== '') {
                $parts[] = 'size ' . $term;
            }
        }
        foreach ((array) (!empty($analysis['negative_color_labels']) ? $analysis['negative_color_labels'] : array()) as $term) {
            $term = wp_strip_all_tags((string) $term);
            if ($term !== '') {
                $parts[] = 'not ' . $term;
            }
        }
        foreach ((array) (!empty($analysis['negative_size_labels']) ? $analysis['negative_size_labels'] : array()) as $term) {
            $term = wp_strip_all_tags((string) $term);
            if ($term !== '') {
                $parts[] = 'not size ' . $term;
            }
        }

        foreach ((array) (!empty($analysis['modifier_labels']) ? $analysis['modifier_labels'] : array()) as $label) {
            $label = wp_strip_all_tags((string) $label);
            if ($label !== '') {
                $parts[] = $label;
            }
        }

        $audience = !empty($analysis['audience']) && is_array($analysis['audience']) ? $analysis['audience'] : array();
        if (!empty($audience['matched_phrase'])) {
            $parts[] = wp_strip_all_tags((string) $audience['matched_phrase']);
        }

        if (!empty($analysis['is_gift_request'])) {
            $parts[] = 'gift';
        }
        if ($this->analysis_requires_sale($analysis)) {
            $parts[] = 'on sale';
        }
        if (!empty($analysis['in_stock_only'])) {
            $parts[] = 'in stock';
        }

        if (!empty($analysis['price_range']) && is_array($analysis['price_range'])) {
            $min = isset($analysis['price_range']['min']) && $analysis['price_range']['min'] !== null
                ? (float) $analysis['price_range']['min']
                : null;
            $max = isset($analysis['price_range']['max']) && $analysis['price_range']['max'] !== null
                ? (float) $analysis['price_range']['max']
                : null;

            if ($min !== null && $max !== null) {
                $parts[] = 'between ' . $this->plain_price_number($min) . ' and ' . $this->plain_price_number($max);
            } elseif ($min !== null) {
                $parts[] = 'at least ' . $this->plain_price_number($min);
            } elseif ($max !== null) {
                $parts[] = 'under ' . $this->plain_price_number($max);
            }
        }

        $clean = array();
        foreach ($parts as $part) {
            $normalized = $this->search_language()->normalize_text($part);
            if ($normalized === '' || in_array($normalized, $clean, true)) {
                continue;
            }
            $clean[] = $normalized;
        }

        return trim(implode(' ', array_slice($clean, 0, 14)));
    }

    public function products_by_ids($product_ids, $limit = 4, $analysis = array(), $action = '') {
        $limit = max(1, min(8, absint($limit)));
        $ids = CatalogVisibilityService::filter_ids($product_ids, $limit, 'products_by_ids');
        $this->last_search_context = $this->search_context('context_' . sanitize_key($action ? $action : 'ids'), is_array($analysis) ? $analysis : array());
        if ($action !== '') {
            $this->last_search_context['conversationAction'] = sanitize_key($action);
        }
        return $this->hydrate_products(array_slice($ids, 0, $limit));
    }

    public function similar_products($source_product_id, $limit = 4, $analysis = array(), $preferred_product_ids = array(), $options = array()) {
        $source_product_id = absint($source_product_id);
        $source = $source_product_id ? wc_get_product($source_product_id) : null;
        if (!$source || $source->get_status() !== 'publish') {
            return array();
        }

        $analysis = is_array($analysis) ? $analysis : array();
        $options = is_array($options) ? $options : array();
        $limit = max(1, min(8, absint($limit)));
        $preferred_ids = array_values(array_unique(array_filter(array_map('absint', (array) $preferred_product_ids))));
        $candidate_ids = $preferred_ids;

        if (function_exists('wc_get_related_products')) {
            $candidate_ids = array_merge($candidate_ids, (array) wc_get_related_products($source_product_id, 36, array($source_product_id)));
        }

        $source_category_ids = wp_get_post_terms($source_product_id, 'product_cat', array('fields' => 'ids'));
        $source_tag_ids = wp_get_post_terms($source_product_id, 'product_tag', array('fields' => 'ids'));
        $source_category_ids = is_wp_error($source_category_ids) ? array() : array_map('absint', (array) $source_category_ids);
        $source_tag_ids = is_wp_error($source_tag_ids) ? array() : array_map('absint', (array) $source_tag_ids);

        $tax_query = array('relation' => 'OR');
        if (!empty($source_category_ids)) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $source_category_ids,
            );
        }
        if (!empty($source_tag_ids)) {
            $tax_query[] = array(
                'taxonomy' => 'product_tag',
                'field' => 'term_id',
                'terms' => $source_tag_ids,
            );
        }
        if (count($tax_query) > 1) {
            $candidate_ids = array_merge($candidate_ids, get_posts(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => 100,
                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- One known source ID is excluded from a bounded related-product query.
                'post__not_in' => array($source_product_id),
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Category/tag similarity is the purpose of this bounded recommendation query.
                'tax_query' => $tax_query,
                'no_found_rows' => true,
            )));
        }

        // A bounded catalog pool lets staged broadening find useful alternatives
        // without turning the command into a new keyword search.
        if (function_exists('wc_get_products')) {
            $candidate_ids = array_merge($candidate_ids, (array) wc_get_products(array(
                'status' => 'publish',
                'limit' => 140,
                'return' => 'ids',
                'orderby' => 'date',
                'order' => 'DESC',
            )));
        }

        $candidate_ids = array_values(array_diff(
            array_values(array_unique(array_filter(array_map('absint', $candidate_ids)))),
            array($source_product_id)
        ));

        $source_category_names = wp_get_post_terms($source_product_id, 'product_cat', array('fields' => 'names'));
        $source_tag_names = wp_get_post_terms($source_product_id, 'product_tag', array('fields' => 'names'));
        $source_category_names = is_wp_error($source_category_names) ? array() : array_map('wp_strip_all_tags', (array) $source_category_names);
        $source_tag_names = is_wp_error($source_tag_names) ? array() : array_map('wp_strip_all_tags', (array) $source_tag_names);
        $source_text = $this->product_match_text($source, $source_category_names);
        $excluded_colors = !empty($options['excludedColors']) ? (array) $options['excludedColors'] : array();
        $require_different_color = !empty($options['requireDifferentColor']);

        $ranked = array();
        foreach ($candidate_ids as $product_id) {
            $product_id = absint($product_id);
            if (!$product_id || $product_id === $source_product_id) {
                continue;
            }

            $product = wc_get_product($product_id);
            if (!$product || $product->get_status() !== 'publish' || !$this->product_matches_hard_analysis($product, $analysis)) {
                continue;
            }

            if ($require_different_color) {
                $candidate_colors = $this->confirmed_product_colors($product);
                if (empty($candidate_colors) || !$this->has_color_outside_exclusions($candidate_colors, $excluded_colors)) {
                    continue;
                }
            }

            $relation = $this->similarity_relation(
                $source,
                $product,
                $analysis,
                $source_category_ids,
                $source_tag_names,
                $source_text,
                in_array($product_id, $preferred_ids, true)
            );
            if ($relation['stage'] > 4) {
                continue;
            }

            $ranked[] = array(
                'id' => $product_id,
                'stage' => $relation['stage'],
                'score' => $relation['score'],
                'in_stock' => $product->is_in_stock() ? 1 : 0,
            );
        }

        usort($ranked, function ($a, $b) {
            if ($a['in_stock'] !== $b['in_stock']) {
                return $b['in_stock'] <=> $a['in_stock'];
            }
            if ($a['stage'] !== $b['stage']) {
                return $a['stage'] <=> $b['stage'];
            }
            if ($a['score'] === $b['score']) {
                return $a['id'] <=> $b['id'];
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });

        $filtered = array();
        foreach ($ranked as $item) {
            if (!empty($analysis['in_stock_only']) && empty($item['in_stock'])) {
                continue;
            }
            if ($item['id'] === $source_product_id || in_array($item['id'], $filtered, true)) {
                continue;
            }
            $filtered[] = absint($item['id']);
            if (count($filtered) >= $limit) {
                break;
            }
        }

        $this->last_search_context = $this->search_context('context_similar', $analysis);
        $this->last_search_context['conversationAction'] = 'similar';
        $this->last_search_context['commandMeta'] = array(
            'sourceProductId' => $source_product_id,
            'sourceProductName' => wp_strip_all_tags($source->get_name()),
            'preservedHardConstraints' => true,
            'excludedSourceProduct' => true,
        );

        return $this->hydrate_products($filtered);
    }

    public function color_options_or_alternatives($source_product_id, $limit = 4, $analysis = array(), $preferred_product_ids = array()) {
        $source_product_id = absint($source_product_id);
        $product = $source_product_id ? wc_get_product($source_product_id) : null;
        if (!$product || $product->get_status() !== 'publish') {
            return array();
        }

        $analysis = is_array($analysis) ? $analysis : array();
        $limit = max(1, min(8, absint($limit)));
        $colors = $this->confirmed_product_colors($product);
        $active_colors = array_values(array_unique(array_filter(array_merge(
            (array) ($analysis['color_terms'] ?? array()),
            (array) ($analysis['requested_color_labels'] ?? array())
        ))));
        $excluded_colors = !empty($colors) ? $colors : $active_colors;

        $this->last_search_context = $this->search_context('context_another_color', $analysis);
        $this->last_search_context['conversationAction'] = 'another_color';
        $this->last_search_context['commandMeta'] = array(
            'sourceProductId' => $source_product_id,
            'sourceProductName' => wp_strip_all_tags($product->get_name()),
            'colors' => $colors,
            'showingAlternatives' => false,
        );

        // Only the selected product's own confirmed attributes/variations may be
        // presented as direct color choices.
        if (count($colors) > 1) {
            return $this->hydrate_products(array($source_product_id));
        }

        // When the exact product has no additional confirmed color, replace the
        // active color with "not the current color" and look for genuinely
        // related alternatives. Price, size, sale, and stock rules remain active.
        $alternative_analysis = $this->remove_constraint_from_analysis(
            $analysis,
            array('constraintType' => 'color', 'value' => '')
        );
        foreach ($excluded_colors as $color) {
            $normalized = $this->search_language()->normalize_text($color);
            if ($normalized === '') {
                continue;
            }
            $alternative_analysis['negative_color_terms'][] = $normalized;
            $alternative_analysis['negative_color_labels'][] = wp_strip_all_tags((string) $color);
        }
        $alternative_analysis['negative_color_terms'] = array_values(array_unique(array_filter(
            (array) ($alternative_analysis['negative_color_terms'] ?? array())
        )));
        $alternative_analysis['negative_color_labels'] = array_values(array_unique(array_filter(
            (array) ($alternative_analysis['negative_color_labels'] ?? array())
        )));

        $alternatives = $this->similar_products(
            $source_product_id,
            $limit,
            $alternative_analysis,
            $preferred_product_ids,
            array(
                'requireDifferentColor' => true,
                'excludedColors' => $excluded_colors,
            )
        );

        $this->last_search_context = $this->search_context('context_another_color', $alternative_analysis);
        $this->last_search_context['conversationAction'] = 'another_color';
        $this->last_search_context['commandMeta'] = array(
            'sourceProductId' => $source_product_id,
            'sourceProductName' => wp_strip_all_tags($product->get_name()),
            'colors' => $colors,
            'excludedColors' => array_values(array_unique(array_map('wp_strip_all_tags', $excluded_colors))),
            'showingAlternatives' => !empty($alternatives),
        );

        return $alternatives;
    }

    private function similarity_relation($source, $candidate, $analysis, $source_category_ids, $source_tag_names, $source_text, $preferred) {
        $candidate_category_ids = wp_get_post_terms($candidate->get_id(), 'product_cat', array('fields' => 'ids'));
        $candidate_tag_names = wp_get_post_terms($candidate->get_id(), 'product_tag', array('fields' => 'names'));
        $candidate_category_ids = is_wp_error($candidate_category_ids) ? array() : array_map('absint', (array) $candidate_category_ids);
        $candidate_tag_names = is_wp_error($candidate_tag_names) ? array() : array_map('wp_strip_all_tags', (array) $candidate_tag_names);
        $candidate_category_names = wp_get_post_terms($candidate->get_id(), 'product_cat', array('fields' => 'names'));
        $candidate_category_names = is_wp_error($candidate_category_names) ? array() : (array) $candidate_category_names;
        $candidate_text = $this->product_match_text($candidate, $candidate_category_names);

        $shared_categories = count(array_intersect($source_category_ids, $candidate_category_ids));
        $source_relation_tags = $this->meaningful_relation_tags($source_tag_names);
        $candidate_relation_tags = $this->meaningful_relation_tags($candidate_tag_names);
        $shared_relation_tags = count(array_intersect($source_relation_tags, $candidate_relation_tags));
        $shared_tokens = count(array_intersect(
            $this->meaningful_product_tokens($source_text),
            $this->meaningful_product_tokens($candidate_text)
        ));

        $source_families = $this->product_relation_families($source_text);
        $candidate_families = $this->product_relation_families($candidate_text);
        $shared_families = count(array_intersect($source_families, $candidate_families));
        $source_groups = $this->product_relation_groups($source_families);
        $candidate_groups = $this->product_relation_groups($candidate_families);
        $shared_groups = count(array_intersect($source_groups, $candidate_groups));
        $related_family_score = $this->related_product_family_score($source_families, $candidate_families);
        $preference_score = $this->analysis_preference_score($candidate, $analysis);

        // Product family is the primary meaning of "similar". Generic use-case
        // groups such as "office" are intentionally not enough: an office
        // cushion is not a useful alternative to a laptop backpack. Only the
        // same category/family or an explicit close commerce-family relation may
        // admit a candidate when the source product has a recognized family.
        if ($shared_categories > 0 || $shared_families > 0) {
            $stage = 1;
        } elseif ($related_family_score > 0) {
            $stage = 2;
        } elseif (empty($source_families) && (($shared_relation_tags > 0 && $shared_tokens > 0) || $shared_tokens >= 2)) {
            $stage = 3;
        } else {
            $stage = 5;
        }

        $score = $preferred ? 8 : 0;
        $score += min(320, $shared_categories * 160);
        $score += min(220, $shared_families * 110);
        $score += min(180, $related_family_score * 60);
        if ($related_family_score > 0) {
            $score += min(60, $shared_groups * 30);
        }
        $score += min(75, $shared_relation_tags * 25);
        $score += min(60, $shared_tokens * 12);
        if ($source->get_type() === $candidate->get_type()) {
            $score += 8;
        }

        $source_price = $this->representative_product_price($source);
        $candidate_price = $this->representative_product_price($candidate);
        if ($source_price > 0 && $candidate_price > 0) {
            $distance = abs($source_price - $candidate_price) / max($source_price, $candidate_price);
            $score += max(0, 30 - (int) round($distance * 30));
        }

        $score += min(30, $preference_score);
        $score += $candidate->is_in_stock() ? 35 : -80;

        return array('stage' => $stage, 'score' => $score);
    }

    /**
     * Lightweight commerce product families improve "similar" results without
     * turning the buyer-intent parser into a general ontology.
     */
    private function product_relation_families($text) {
        $text = ' ' . $this->search_language()->normalize_text($text) . ' ';
        $families = apply_filters('geekybot_product_relation_families', array(
            'bags' => array('backpack', 'backpacks', 'bag', 'bags', 'tote', 'totes', 'briefcase', 'briefcases', 'satchel', 'satchels', 'luggage', 'duffel', 'handbag'),
            'laptop_carry' => array('laptop sleeve', 'laptop sleeves', 'laptop case', 'laptop cases', 'notebook sleeve', 'tablet sleeve'),
            'organizers' => array('organizer', 'organizers', 'organiser', 'organisers', 'travel organizer', 'travel organiser', 'pouch', 'pouches', 'packing cube', 'packing cubes'),
            'wallets' => array('wallet', 'wallets', 'cardholder', 'card holder', 'card case'),
            'running_footwear' => array('running shoe', 'running shoes', 'running sneaker', 'running sneakers', 'trainer', 'trainers'),
            'walking_footwear' => array('walking shoe', 'walking shoes'),
            'casual_footwear' => array('sneaker', 'sneakers', 'canvas shoe', 'canvas shoes'),
            'formal_footwear' => array('loafer', 'loafers', 'office shoe', 'office shoes', 'dress shoe', 'dress shoes'),
            'sandals' => array('sandal', 'sandals', 'slipper', 'slippers'),
            'shirts' => array('shirt', 'shirts', 'tee', 'tees', 'tshirt', 't-shirt', 'blouse', 'blouses', 'polo', 'polos', 'top', 'tops'),
            'hoodies_knitwear' => array('hoodie', 'hoodies', 'sweater', 'sweaters', 'sweatshirt', 'sweatshirts'),
            'outerwear' => array('jacket', 'jackets', 'coat', 'coats', 'raincoat', 'rain jacket', 'blazer', 'vest'),
            'bottoms' => array('pants', 'trousers', 'jeans', 'shorts', 'joggers', 'leggings', 'skirt', 'skirts'),
            'dresses' => array('dress', 'dresses', 'gown', 'gowns'),
            'headwear' => array('cap', 'caps', 'beanie', 'beanies', 'hat', 'hats'),
            'belts' => array('belt', 'belts'),
            'watches' => array('watch', 'watches'),
            'scarves' => array('scarf', 'scarves'),
            'eyewear' => array('sunglasses', 'glasses', 'eyewear'),
            'socks' => array('sock', 'socks'),
            'audio' => array('speaker', 'speakers', 'earbud', 'earbuds', 'headphone', 'headphones', 'earphone', 'earphones'),
            'office_desk' => array('notebook', 'notebooks', 'mousepad', 'mouse pad', 'phone stand', 'desk stand', 'cushion'),
            'fitness' => array('yoga mat', 'exercise mat', 'sports towel', 'training towel'),
            'gift_sets' => array('gift set', 'gift sets', 'bundle', 'bundles'),
            'drinkware' => array('water bottle', 'bottle', 'bottles', 'tumbler', 'mug'),
        ));

        $matched = array();
        foreach ((array) $families as $family => $terms) {
            foreach ((array) $terms as $term) {
                $term = $this->search_language()->normalize_text($term);
                if ($term !== '' && strpos($text, ' ' . $term . ' ') !== false) {
                    $matched[] = sanitize_key($family);
                    break;
                }
            }
        }
        return array_values(array_unique(array_filter($matched)));
    }

    private function product_relation_groups($families) {
        $groups = apply_filters('geekybot_product_relation_groups', array(
            'carry_storage' => array('bags', 'laptop_carry', 'organizers'),
            'footwear' => array('running_footwear', 'walking_footwear', 'casual_footwear', 'formal_footwear', 'sandals'),
            'tops' => array('shirts', 'hoodies_knitwear'),
            'clothing' => array('shirts', 'hoodies_knitwear', 'outerwear', 'bottoms', 'dresses'),
            'wearable_accessories' => array('belts', 'watches', 'scarves', 'eyewear', 'socks', 'headwear'),
            'electronics_audio' => array('audio'),
            'office' => array('office_desk', 'laptop_carry', 'bags'),
            'fitness' => array('fitness', 'running_footwear'),
        ));

        $matched = array();
        foreach ((array) $groups as $group => $members) {
            if (array_intersect((array) $families, (array) $members)) {
                $matched[] = sanitize_key($group);
            }
        }
        return array_values(array_unique(array_filter($matched)));
    }


    /**
     * Return a conservative relationship strength between two commerce product
     * families. This is deliberately narrower than the broader merchandising
     * groups used for ranking. Shared words such as "office", "gift", or
     * "black" must never make unrelated products valid similar alternatives.
     */
    private function related_product_family_score($source_families, $candidate_families) {
        $relations = apply_filters('geekybot_related_product_families', array(
            'bags' => array('laptop_carry' => 3, 'organizers' => 2),
            'laptop_carry' => array('bags' => 3, 'organizers' => 2),
            'organizers' => array('bags' => 2, 'laptop_carry' => 2),
            'running_footwear' => array('walking_footwear' => 2, 'casual_footwear' => 1),
            'walking_footwear' => array('running_footwear' => 2, 'casual_footwear' => 1),
            'casual_footwear' => array('running_footwear' => 1, 'walking_footwear' => 1),
        ));

        $best = 0;
        foreach ((array) $source_families as $source_family) {
            $source_family = sanitize_key($source_family);
            if ($source_family === '' || empty($relations[$source_family])) {
                continue;
            }
            foreach ((array) $candidate_families as $candidate_family) {
                $candidate_family = sanitize_key($candidate_family);
                if ($candidate_family === '' || empty($relations[$source_family][$candidate_family])) {
                    continue;
                }
                $best = max($best, absint($relations[$source_family][$candidate_family]));
            }
        }

        return $best;
    }

    private function meaningful_relation_tags($tags) {
        $generic = array(
            'gift', 'useful', 'popular', 'practical', 'budget', 'budget friendly',
            'premium', 'best value', 'daily use', 'comfortable', 'comfort', 'sale',
            'office', 'work', 'travel', 'summer', 'sports', 'exercise', 'casual',
            'formal', 'school', 'student',
            'black', 'white', 'blue', 'red', 'green', 'grey', 'gray', 'brown',
            'navy', 'cream', 'in stock', 'available',
        );
        $clean = array();
        foreach ((array) $tags as $tag) {
            $tag = $this->search_language()->normalize_text($tag);
            if ($tag === '' || in_array($tag, $generic, true)) {
                continue;
            }
            $clean[] = $tag;
        }
        return array_values(array_unique($clean));
    }

    private function product_matches_hard_analysis($product, $analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        if (!$this->product_matches_requested_variation($product, $analysis)) {
            return false;
        }
        if (!empty($analysis['in_stock_only']) && !CatalogAvailabilityService::is_available($product)) {
            return false;
        }
        if ($this->analysis_requires_sale($analysis) && !$product->is_on_sale()) {
            return false;
        }
        if (!empty($analysis['price_range']) && !$this->product_price_overlaps_range($product, $analysis['price_range'])) {
            return false;
        }

        $category_names = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $category_names = is_wp_error($category_names) ? array() : (array) $category_names;
        $product_text = $this->product_match_text($product, $category_names);
        $family_identity_text = $this->product_family_identity_text($product, $category_names);
        if (!$this->product_matches_family_analysis($product_text, $analysis, $family_identity_text)) {
            return false;
        }
        if (!empty($analysis['color_terms']) && !$this->text_has_any_search_term($product_text, (array) $analysis['color_terms'])) {
            return false;
        }
        if (!empty($analysis['size_terms']) && !$this->text_has_any_search_term($product_text, (array) $analysis['size_terms'])) {
            return false;
        }
        if (!empty($analysis['negative_color_terms']) && $this->text_has_any_search_term($product_text, (array) $analysis['negative_color_terms'])) {
            return false;
        }
        if (!empty($analysis['negative_size_terms']) && $this->text_has_any_search_term($product_text, (array) $analysis['negative_size_terms'])) {
            return false;
        }
        return true;
    }

    /**
     * Applies the same mandatory family/qualifier gate during live catalog
     * scans used by context commands such as removing a filter.
     */
    private function product_matches_family_analysis($product_text, $analysis, $family_identity_text = '') {
        $analysis = is_array($analysis) ? $analysis : array();
        if (empty($analysis['product_family_term'])) {
            return true;
        }

        $aliases = !empty($analysis['family_gate_aliases'])
            ? (array) $analysis['family_gate_aliases']
            : (!empty($analysis['required_family_aliases'])
                ? (array) $analysis['required_family_aliases']
                : (array) ($analysis['product_family_aliases'] ?? array()));
        $family_identity_text = $family_identity_text !== '' ? $family_identity_text : $product_text;
        if (empty($aliases) || !$this->text_has_any_search_term($family_identity_text, $aliases)) {
            return false;
        }

        $qualifiers = !empty($analysis['product_qualifier_terms'])
            ? (array) $analysis['product_qualifier_terms']
            : array();
        foreach ($qualifiers as $qualifier) {
            if (!$this->text_has_search_term($product_text, $qualifier)) {
                return false;
            }
        }

        return true;
    }

    private function analysis_preference_score($product, $analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        $category_names = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $category_names = is_wp_error($category_names) ? array() : (array) $category_names;
        $text = $this->product_match_text($product, $category_names);
        $score = 0;
        if (!empty($analysis['product_family_term'])) {
            $family_aliases = !empty($analysis['family_gate_aliases'])
                ? (array) $analysis['family_gate_aliases']
                : (!empty($analysis['required_family_aliases'])
                    ? (array) $analysis['required_family_aliases']
                    : (array) ($analysis['product_family_aliases'] ?? array()));
            foreach ($family_aliases as $alias) {
                if ($this->text_has_search_term($text, $alias)) {
                    $score += 45;
                    break;
                }
            }
            foreach ((array) ($analysis['product_qualifier_terms'] ?? array()) as $qualifier) {
                if ($this->text_has_search_term($text, $qualifier)) {
                    $score += 12;
                }
            }
        }
        foreach ((array) ($analysis['modifier_terms'] ?? array()) as $term) {
            if ($this->text_has_search_term($text, $term)) {
                $score += 12;
            }
        }
        if (!empty($analysis['is_gift_request']) && $this->text_has_any_search_term($text, array('gift', 'useful', 'popular', 'practical', 'one size', 'unisex'))) {
            $score += 22;
        }
        if (!empty($analysis['audience']['positive_terms'])) {
            foreach ((array) $analysis['audience']['positive_terms'] as $term) {
                if ($this->text_has_search_term($text, $term)) {
                    $score += 10;
                }
            }
        }
        if ($product->is_on_sale()) {
            $score += 8;
        }
        if ($product->is_featured()) {
            $score += 8;
        }
        $score += min(12, (float) $product->get_average_rating() * 2);
        return $score;
    }

    private function confirmed_product_colors($product) {
        $colors = array_merge(
            $this->product_color_values($product),
            $this->variation_color_values($product)
        );
        $clean = array();
        foreach ($colors as $color) {
            $display = trim(wp_strip_all_tags((string) $color));
            $normalized = $this->search_language()->normalize_text($display);
            if ($normalized === '') {
                continue;
            }
            if (!isset($clean[$normalized])) {
                $clean[$normalized] = $display;
            }
        }
        return array_slice(array_values($clean), 0, 12);
    }

    private function has_color_outside_exclusions($candidate_colors, $excluded_colors) {
        $excluded = array();
        foreach ((array) $excluded_colors as $color) {
            $color = $this->search_language()->normalize_text($color);
            if ($color !== '') {
                $excluded[] = $color === 'gray' ? 'grey' : $color;
            }
        }
        $excluded = array_values(array_unique($excluded));

        foreach ((array) $candidate_colors as $color) {
            $color = $this->search_language()->normalize_text($color);
            $color = $color === 'gray' ? 'grey' : $color;
            if ($color !== '' && !in_array($color, $excluded, true)) {
                return true;
            }
        }
        return false;
    }

    private function meaningful_product_tokens($text) {
        $stop = array(
            'the', 'a', 'an', 'and', 'or', 'with', 'for', 'from', 'this', 'that',
            'product', 'products', 'simple', 'available', 'stock', 'in', 'of', 'to',
            'daily', 'use', 'good', 'option', 'options', 'size', 'color', 'colour',
            'gift', 'useful', 'popular', 'practical', 'premium', 'budget', 'friendly',
            'best', 'value', 'sale', 'discount', 'comfortable', 'comfort',
            'office', 'work', 'travel', 'summer', 'sports', 'exercise', 'casual',
            'formal', 'school', 'student',
            'black', 'white', 'blue', 'red', 'green', 'grey', 'gray', 'brown',
            'navy', 'cream', 'gold', 'silver', 'pink', 'purple', 'orange', 'yellow',
            'beige', 'tan',
        );
        $tokens = array();
        foreach (preg_split('/\s+/u', $this->search_language()->normalize_text($text)) as $token) {
            if ($token === '' || strlen($token) < 3 || in_array($token, $stop, true) || is_numeric($token)) {
                continue;
            }
            $tokens[] = $token;
        }
        return array_values(array_unique($tokens));
    }

    private function representative_product_price($product) {
        if (!$product) {
            return 0;
        }
        if ($product->is_type('variable')) {
            $min = (float) $product->get_variation_price('min', true);
            $max = (float) $product->get_variation_price('max', true);
            if ($min > 0 && $max > 0) {
                return ($min + $max) / 2;
            }
            return max($min, $max);
        }
        return $product->get_price() === '' ? 0 : (float) $product->get_price();
    }

    private function product_ids_from_payload($products) {
        $ids = array();
        foreach ((array) $products as $product) {
            if (!empty($product['id'])) {
                $ids[] = absint($product['id']);
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Normalizes one or more constraint types from command arguments while
     * keeping backward compatibility with the original single-type shape.
     */
    private function constraint_types_from_args($args) {
        $args = is_array($args) ? $args : array();
        $types = !empty($args['constraintTypes']) && is_array($args['constraintTypes'])
            ? $args['constraintTypes']
            : (!empty($args['constraintType']) ? array($args['constraintType']) : array());
        $allowed = array('color', 'size', 'price', 'sale', 'stock', 'preference');
        $clean = array();

        foreach ($types as $type) {
            $type = sanitize_key((string) $type);
            if ($type !== '' && in_array($type, $allowed, true) && !in_array($type, $clean, true)) {
                $clean[] = $type;
            }
        }

        return $clean;
    }

    /**
     * Reads a type-specific removal value from the new multi-constraint shape
     * or from the legacy single value field.
     */
    private function constraint_value_from_args($args, $type) {
        $args = is_array($args) ? $args : array();
        $type = sanitize_key((string) $type);
        if ($type === '') {
            return '';
        }

        if (!empty($args['constraintValues']) && is_array($args['constraintValues']) && array_key_exists($type, $args['constraintValues'])) {
            return sanitize_text_field((string) $args['constraintValues'][$type]);
        }

        $legacy_type = !empty($args['constraintType']) ? sanitize_key((string) $args['constraintType']) : '';
        if ($legacy_type === $type && isset($args['value'])) {
            return sanitize_text_field((string) $args['value']);
        }

        return '';
    }

    private function remove_constraint_from_analysis($analysis, $args) {
        $analysis = is_array($analysis) ? $analysis : array();
        $args = is_array($args) ? $args : array();
        $types = $this->constraint_types_from_args($args);

        // A natural correction may remove more than one explicitly named
        // constraint, for example "remove the sale $20 requirement". Apply
        // each named removal to the same preserved mission instead of routing
        // the leftover command words into catalog search.
        if (count($types) > 1) {
            foreach ($types as $remove_type) {
                $analysis = $this->remove_constraint_from_analysis(
                    $analysis,
                    array(
                        'constraintType' => $remove_type,
                        'value' => $this->constraint_value_from_args($args, $remove_type),
                    )
                );
            }
            return $analysis;
        }

        $type = !empty($types[0]) ? $types[0] : '';
        $value = $type !== ''
            ? $this->search_language()->normalize_text($this->constraint_value_from_args($args, $type))
            : '';
        $remove_values = array();

        if ($type === 'color') {
            $remove_values = $value === ''
                ? array_merge(
                    (array) ($analysis['color_terms'] ?? array()),
                    (array) ($analysis['requested_color_labels'] ?? array()),
                    (array) ($analysis['negative_color_terms'] ?? array()),
                    (array) ($analysis['negative_color_labels'] ?? array())
                )
                : array($value);

            if ($value === '') {
                $analysis['color_terms'] = array();
                $analysis['requested_color_labels'] = array();
                $analysis['negative_color_terms'] = array();
                $analysis['negative_color_labels'] = array();
            } else {
                $analysis['color_terms'] = $this->remove_normalized_term((array) ($analysis['color_terms'] ?? array()), $value);
                $analysis['requested_color_labels'] = $this->remove_normalized_term((array) ($analysis['requested_color_labels'] ?? array()), $value);
                $analysis['negative_color_terms'] = $this->remove_normalized_term((array) ($analysis['negative_color_terms'] ?? array()), $value);
                $analysis['negative_color_labels'] = $this->remove_normalized_term((array) ($analysis['negative_color_labels'] ?? array()), $value);
            }
        } elseif ($type === 'size') {
            $remove_values = array_merge(
                (array) ($analysis['size_terms'] ?? array()),
                (array) ($analysis['requested_size_labels'] ?? array()),
                (array) ($analysis['negative_size_terms'] ?? array()),
                (array) ($analysis['negative_size_labels'] ?? array())
            );
            $analysis['size_terms'] = array();
            $analysis['requested_size_labels'] = array();
            $analysis['negative_size_terms'] = array();
            $analysis['negative_size_labels'] = array();
        } elseif ($type === 'price') {
            $analysis['price_range'] = array();
            $analysis['budget_sort'] = false;
            $analysis['value_sort'] = false;
        } elseif ($type === 'sale') {
            $analysis['sale_required'] = false;
            $analysis['intent'] = 'search';
        } elseif ($type === 'stock') {
            $analysis['in_stock_only'] = false;
        } elseif ($type === 'preference' && $value !== '') {
            $remove_values = array($value);
            $analysis['modifier_terms'] = $this->remove_normalized_term((array) ($analysis['modifier_terms'] ?? array()), $value);
            $analysis['modifier_labels'] = $this->remove_normalized_term((array) ($analysis['modifier_labels'] ?? array()), $value);
            $analysis['decision_modes'] = $this->remove_normalized_term((array) ($analysis['decision_modes'] ?? array()), $value);
        }

        $remove_values = array_values(array_unique(array_filter(array_map(
            array($this->search_language(), 'normalize_text'),
            (array) $remove_values
        ))));

        // Remove the deleted value from every token/phrase field that can later
        // behave as a hard family qualifier. Clearing only color_terms leaves a
        // stale `black` inside product_qualifier_terms and can silently keep the
        // old filter active during full-catalog reconstruction.
        $derived_term_fields = array(
            'terms', 'core_terms', 'display_core_terms', 'modifier_terms', 'modifier_labels',
            'product_phrase_terms', 'product_qualifier_terms',
        );
        foreach ($remove_values as $remove_value) {
            foreach ($derived_term_fields as $field) {
                $analysis[$field] = $this->remove_normalized_term((array) ($analysis[$field] ?? array()), $remove_value);
            }
        }

        return $this->refresh_analysis_after_constraint_change($analysis);
    }

    /**
     * Rebuilds parser-derived fields from the remaining active mission.
     *
     * Search context keeps rich normalized analysis between messages. When one
     * constraint is removed, stale raw/phrase/boolean fields must not reapply it.
     * The reconstructed query is parsed again, while user-profile metadata that
     * is not represented by the compact query is retained when still relevant.
     */
    private function refresh_analysis_after_constraint_change($analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        $query = $this->query_from_analysis($analysis);
        if ($query === '') {
            return $analysis;
        }

        $fresh = (new ProductIndexService())->analyze_query($query);
        if (empty($fresh)) {
            return $analysis;
        }

        $derived_keys = array(
            'raw', 'lower', 'searchable', 'expanded', 'terms', 'core_terms', 'display_core_terms',
            'facets', 'color_terms', 'size_terms', 'negative_color_terms', 'negative_size_terms',
            'requested_color_labels', 'requested_size_labels', 'negative_color_labels',
            'negative_size_labels', 'boolean', 'phrase', 'product_phrase', 'product_phrase_terms',
            'product_family_term', 'product_family_source', 'product_family_aliases',
            'required_family_aliases', 'family_gate_aliases', 'product_qualifier_terms', 'price_range', 'intent',
            'budget_sort', 'value_sort', 'language', 'in_stock_only', 'modifier_terms',
            'modifier_labels', 'modifier_weights', 'decision_modes', 'is_gift_request',
            'gift_signals', 'buyer_profile', 'audience',
        );
        foreach ($derived_keys as $key) {
            if (array_key_exists($key, $fresh)) {
                $analysis[$key] = $fresh[$key];
            }
        }

        return $analysis;
    }

    private function remove_normalized_term($terms, $remove) {
        $remove = $this->search_language()->normalize_text($remove);
        $clean = array();
        foreach ((array) $terms as $term) {
            $normalized = $this->search_language()->normalize_text($term);
            if ($normalized === $remove || ($remove === 'gray' && $normalized === 'grey') || ($remove === 'grey' && $normalized === 'gray')) {
                continue;
            }
            $clean[] = $term;
        }
        return array_values(array_unique(array_filter($clean)));
    }

    private function product_color_values($product) {
        $colors = array();
        foreach ((array) $product->get_attributes() as $attribute) {
            if (!is_object($attribute)) {
                continue;
            }
            $name = (string) $attribute->get_name();
            $label = function_exists('wc_attribute_label') ? wc_attribute_label($name, $product) : $name;
            if (!$this->search_language()->is_color_attribute_label($label) && !$this->search_language()->is_color_attribute_label($name)) {
                continue;
            }
            if ($attribute->is_taxonomy()) {
                $values = wc_get_product_terms($product->get_id(), $name, array('fields' => 'names'));
                if (is_wp_error($values)) {
                    $values = array();
                }
            } else {
                $values = $attribute->get_options();
            }
            foreach ((array) $values as $value) {
                $value = wp_strip_all_tags((string) $value);
                if ($value !== '') {
                    $colors[] = $value;
                }
            }
        }

        if ($product->is_type('variable')) {
            foreach ((array) $product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation || !$variation->exists()) {
                    continue;
                }
                foreach ((array) $variation->get_attributes() as $attribute_name => $value) {
                    $label = function_exists('wc_attribute_label') ? wc_attribute_label($attribute_name, $product) : $attribute_name;
                    if (!$this->search_language()->is_color_attribute_label($label) && !$this->search_language()->is_color_attribute_label($attribute_name)) {
                        continue;
                    }
                    $value = wp_strip_all_tags((string) $value);
                    if ($value !== '') {
                        $colors[] = $value;
                    }
                }
            }
        }

        return array_slice(array_values(array_unique(array_filter($colors))), 0, 12);
    }

    private function variation_color_values($product) {
        if (!$product || !$product->is_type('variable')) {
            return array();
        }

        $colors = array();
        foreach ((array) $product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation->is_type('variation')) {
                continue;
            }
            foreach ((array) $variation->get_variation_attributes() as $attribute_name => $value) {
                $attribute_key = str_replace('attribute_', '', (string) $attribute_name);
                $label = function_exists('wc_attribute_label') ? wc_attribute_label($attribute_key, $product) : $attribute_key;
                if (!$this->search_language()->is_color_attribute_label($label) && !$this->search_language()->is_color_attribute_label($attribute_key)) {
                    continue;
                }
                $value = wp_strip_all_tags((string) $value);
                if ($value === '') {
                    continue;
                }
                if (taxonomy_exists($attribute_key)) {
                    $term = get_term_by('slug', $value, $attribute_key);
                    if ($term && !is_wp_error($term)) {
                        $value = $term->name;
                    }
                }
                $colors[] = str_replace(array('-', '_'), ' ', $value);
            }
        }
        return $colors;
    }

    private function plain_price_number($value) {
        $value = (float) $value;
        if (floor($value) === $value) {
            return (string) (int) $value;
        }
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function discount_percent($product) {
        if (!$product || !$product->is_on_sale()) {
            return 0;
        }

        if ($product->is_type('variable')) {
            $best = 0;
            foreach ((array) $product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation || !$variation->is_on_sale()) {
                    continue;
                }
                $best = max($best, $this->discount_percent($variation));
            }
            return $best;
        }

        $regular = (float) $product->get_regular_price();
        $sale = (float) $product->get_sale_price();
        if ($regular <= 0 || $sale <= 0 || $sale >= $regular) {
            return 0;
        }
        return (int) round((($regular - $sale) / $regular) * 100);
    }

    private function premium_context_analysis($analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        $analysis['budget_sort'] = false;
        $analysis['value_sort'] = false;

        $analysis['decision_modes'] = array_values(array_unique(array_filter(array_merge(
            array_diff(isset($analysis['decision_modes']) ? (array) $analysis['decision_modes'] : array(), array('budget', 'best_value', 'cheapest')),
            array('premium')
        ))));

        foreach (array('modifier_terms', 'modifier_labels') as $key) {
            $clean = array();
            foreach (isset($analysis[$key]) ? (array) $analysis[$key] : array() as $value) {
                $normalized = function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
                if (preg_match('/\b(?:budget|cheap|affordable|lower price|best value)\b/u', $normalized)) {
                    continue;
                }
                $clean[] = $value;
            }
            $analysis[$key] = array_values(array_unique(array_filter($clean)));
        }

        if (!in_array('premium', isset($analysis['modifier_terms']) ? (array) $analysis['modifier_terms'] : array(), true)) {
            $analysis['modifier_terms'][] = 'premium';
        }
        if (!in_array('Premium', isset($analysis['modifier_labels']) ? (array) $analysis['modifier_labels'] : array(), true)) {
            $analysis['modifier_labels'][] = 'Premium';
        }

        return $analysis;
    }

    /**
     * Replaces a constraint value only when the shopper clearly supplied a new
     * value for the same constraint. Unrelated active rules remain untouched.
     */
    private function apply_constraint_replacement_semantics($analysis, $modifier, $modifier_query) {
        $analysis = is_array($analysis) ? $analysis : array();
        $modifier = is_array($modifier) ? $modifier : array();
        $normalized_query = $this->search_language()->normalize_text($modifier_query);

        // Price min/max values are replaced independently by
        // merge_context_analysis(). A new maximum replaces only the previous
        // maximum, while an existing minimum remains active, and vice versa.

        // "Only blue", "blue instead", and similar wording replaces the old
        // positive color selection rather than creating an impossible union.
        $replace_color = !empty($modifier['color_terms']) && preg_match(
            '/(?:^|\b)(?:only|instead|rather than|change(?: it)? to|make it)\b/u',
            $normalized_query
        );
        if ($replace_color) {
            $analysis['color_terms'] = array_values(array_unique(array_filter((array) $modifier['color_terms'])));
            $analysis['requested_color_labels'] = array_values(array_unique(array_filter((array) ($modifier['requested_color_labels'] ?? array()))));
        }

        // The same replacement rule applies to a clearly requested size switch.
        $replace_size = !empty($modifier['size_terms']) && preg_match(
            '/(?:^|\b)(?:only|instead|rather than|change(?: it)? to|make it|size)\b/u',
            $normalized_query
        );
        if ($replace_size && preg_match('/\b(?:instead|rather than|change|make it|only size|size)\b/u', $normalized_query)) {
            $analysis['size_terms'] = array_values(array_unique(array_filter((array) $modifier['size_terms'])));
            $analysis['requested_size_labels'] = array_values(array_unique(array_filter((array) ($modifier['requested_size_labels'] ?? array()))));
        }

        return $analysis;
    }

    private function merge_context_analysis($previous, $modifier) {
        $previous = is_array($previous) ? $previous : array();
        $modifier = is_array($modifier) ? $modifier : array();
        $merged = $previous;

        $list_keys = array(
            'terms', 'core_terms', 'display_core_terms', 'color_terms', 'size_terms',
            'negative_color_terms', 'negative_size_terms', 'requested_color_labels',
            'requested_size_labels', 'negative_color_labels', 'negative_size_labels',
            'modifier_terms', 'modifier_labels', 'decision_modes'
        );
        foreach ($list_keys as $key) {
            $merged[$key] = array_values(array_unique(array_filter(array_merge(
                isset($previous[$key]) ? (array) $previous[$key] : array(),
                isset($modifier[$key]) ? (array) $modifier[$key] : array()
            ))));
        }

        // gift_signals is an associative map of signal groups, not a flat list.
        // Treating it as a list triggers PHP 8 array-to-string warnings inside
        // array_unique() during normal follow-up filtering.
        if (!empty($modifier['gift_signals']) && is_array($modifier['gift_signals'])) {
            $merged['gift_signals'] = array_replace(
                !empty($previous['gift_signals']) && is_array($previous['gift_signals']) ? $previous['gift_signals'] : array(),
                $modifier['gift_signals']
            );
        }

        if (!empty($modifier['price_range']) && is_array($modifier['price_range'])) {
            $previous_range = !empty($previous['price_range']) && is_array($previous['price_range'])
                ? $previous['price_range']
                : array('min' => null, 'max' => null);
            $modifier_range = $modifier['price_range'];
            $has_min = array_key_exists('min', $modifier_range) && $modifier_range['min'] !== null;
            $has_max = array_key_exists('max', $modifier_range) && $modifier_range['max'] !== null;

            $range = array(
                'min' => array_key_exists('min', $previous_range) ? $previous_range['min'] : null,
                'max' => array_key_exists('max', $previous_range) ? $previous_range['max'] : null,
            );
            if ($has_min) {
                $range['min'] = (float) $modifier_range['min'];
            }
            if ($has_max) {
                $range['max'] = (float) $modifier_range['max'];
            }
            if (isset($modifier_range['mode'])) {
                $range['mode'] = sanitize_key($modifier_range['mode']);
            }
            $merged['price_range'] = $range;
        }

        // Generic modifier defaults must not erase active shopping rules.
        if (!empty($modifier['intent']) && ($modifier['intent'] !== 'search' || empty($previous['intent']))) {
            $merged['intent'] = $modifier['intent'];
        }
        if ($this->analysis_requires_sale($previous) || $this->analysis_requires_sale($modifier)) {
            $merged['sale_required'] = true;
            $merged['intent'] = 'sale';
        }
        if (!empty($modifier['in_stock_only'])) {
            $merged['in_stock_only'] = true;
        }
        if (!empty($modifier['is_gift_request'])) {
            $merged['is_gift_request'] = true;
        }
        if (!empty($modifier['budget_sort'])) {
            $merged['budget_sort'] = true;
        }
        if (!empty($modifier['value_sort'])) {
            $merged['value_sort'] = true;
        }

        foreach (array('audience', 'recipient') as $key) {
            if (!empty($modifier[$key])) {
                $merged[$key] = $modifier[$key];
            }
        }
        if (!empty($modifier['modifier_weights']) && is_array($modifier['modifier_weights'])) {
            $merged['modifier_weights'] = array_replace(
                !empty($previous['modifier_weights']) && is_array($previous['modifier_weights']) ? $previous['modifier_weights'] : array(),
                $modifier['modifier_weights']
            );
        }


        // A short refinement must never replace or widen the original product
        // family identity. Product-family fields are immutable until a genuine
        // new discovery mission is started by SearchContextService.
        $family_state_keys = array(
            'phrase', 'product_phrase', 'product_phrase_terms', 'product_family_term',
            'product_family_source', 'product_family_aliases', 'required_family_aliases',
            'family_gate_aliases', 'product_qualifier_terms'
        );
        foreach ($family_state_keys as $key) {
            if (array_key_exists($key, $previous) && $previous[$key] !== '' && $previous[$key] !== array()) {
                $merged[$key] = $previous[$key];
            } elseif (array_key_exists($key, $modifier)) {
                $merged[$key] = $modifier[$key];
            }
        }

        // Raw/debug wording belongs to the base mission. Command text must not replace it.
        foreach (array('raw', 'lower', 'searchable') as $key) {
            if (!array_key_exists($key, $merged) && array_key_exists($key, $modifier)) {
                $merged[$key] = $modifier[$key];
            }
        }

        return $merged;
    }

    public function latest($limit = 4) {
        if (!$this->is_woocommerce_ready()) {
            return array();
        }

        $product_ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(8, absint($limit))),
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));

        return $this->hydrate_products($product_ids);
    }

    public function sale_products($limit = 4) {
        if (!$this->is_woocommerce_ready()) {
            return array();
        }

        return $this->hydrate_products($this->sale_product_ids($limit));
    }

    private function sale_product_ids($limit = 4) {
        if (!function_exists('wc_get_product_ids_on_sale')) {
            return array();
        }

        $limit = max(1, min(8, absint($limit)));
        $ids = array_values(array_unique(array_map('absint', (array) wc_get_product_ids_on_sale())));
        $ids = array_filter($ids, function ($id) {
            $product = wc_get_product($id);
            return CatalogVisibilityService::is_visible($product, 'sale_products') && CatalogAvailabilityService::is_available($product);
        });

        usort($ids, function ($a, $b) {
            $pa = wc_get_product($a);
            $pb = wc_get_product($b);
            $sa = $pa && $pa->is_on_sale() ? 1 : 0;
            $sb = $pb && $pb->is_on_sale() ? 1 : 0;
            if ($sa === $sb) {
                return $b <=> $a;
            }
            return $sb <=> $sa;
        });

        return array_slice($ids, 0, $limit);
    }

    public function top_rated($limit = 4) {
        if (!$this->is_woocommerce_ready()) {
            return array();
        }

        $limit = max(1, min(8, absint($limit)));
        $product_ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => max(20, $limit * 5),
            'fields' => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- WooCommerce stores average rating in this indexed product meta field.
            'meta_key' => '_wc_average_rating',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));

        $rated_ids = array();
        foreach ((array) $product_ids as $product_id) {
            $product = wc_get_product(absint($product_id));
            if (!CatalogVisibilityService::is_visible($product, 'top_rated_products')
                || (float) $product->get_average_rating() <= 0) {
                continue;
            }
            $rated_ids[] = absint($product_id);
            if (count($rated_ids) >= $limit) {
                break;
            }
        }

        return $this->hydrate_products($rated_ids);
    }

    private function available_products($limit = 4) {
        if (!$this->is_woocommerce_ready() || !function_exists('wc_get_products')) {
            return array();
        }

        $limit = max(1, min(8, absint($limit)));
        $product_ids = wc_get_products(array(
            'status' => 'publish',
            'limit' => 120,
            'return' => 'ids',
            'orderby' => 'popularity',
            'order' => 'DESC',
        ));

        $available_ids = array();
        foreach ((array) $product_ids as $product_id) {
            $product = wc_get_product(absint($product_id));
            if (!CatalogVisibilityService::is_visible($product, 'available_products')
                || !CatalogAvailabilityService::is_available($product)) {
                continue;
            }
            $available_ids[] = absint($product_id);
            if (count($available_ids) >= $limit) {
                break;
            }
        }

        return $this->hydrate_products($available_ids);
    }

    public function context_for_ai($products) {
        $lines = array();
        foreach ((array) $products as $product) {
            $line = sprintf(
                'Product #%d: %s | Price: %s | Stock: %s | Type: %s | Rating: %s | Categories: %s | Short description: %s | URL: %s',
                absint($product['id']),
                $product['name'],
                isset($product['priceText']) ? $product['priceText'] : wp_strip_all_tags($product['priceHtml']),
                $product['stockLabel'],
                $product['type'],
                $product['rating'] ? $product['rating'] : 'not rated',
                !empty($product['categories']) ? implode(', ', $product['categories']) : 'none',
                $product['shortDescription'] ? $product['shortDescription'] : 'not provided',
                $product['url']
            );
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    public function is_product_question($message) {
        return $this->search_language()->is_product_question($message);
    }

    private function clean_query($query) {
        $query = wp_strip_all_tags((string) $query);
        $query = preg_replace('/\s+/', ' ', $query);
        return trim($query);
    }

    private function catalog_intent($query) {
        $intent = $this->search_language()->catalog_intent($query);
        return $intent === 'popular' ? 'top_rated' : $intent;
    }

    private function sku_match_ids($query, $limit) {
        global $wpdb;

        $query = trim((string) $query);
        if ($query === '') {
            return array();
        }

        $like = '%' . $wpdb->esc_like($query) . '%';
        $sql = "SELECT post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_sku' AND pm.meta_value LIKE %s AND p.post_type = 'product' AND p.post_status = 'publish' LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted WordPress table names are internal, all variable values use placeholders, and live catalog results must not be stale.
        return array_map('absint', $wpdb->get_col($wpdb->prepare($sql, $like, absint($limit))));
    }

    private function simple_keyword_ids($query, $limit) {
        $query = trim((string) $query);
        if ($query === '') {
            return array();
        }

        $ids = array_merge(
            $this->sku_match_ids($query, $limit),
            get_posts(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => max(1, min(8, absint($limit))),
                's' => $query,
                'fields' => 'ids',
                'no_found_rows' => true,
            )),
            $this->taxonomy_fallback_ids($query, $limit)
        );

        return array_slice(array_values(array_unique(array_map('absint', $ids))), 0, max(1, min(8, absint($limit))));
    }

    private function taxonomy_fallback_ids($query, $limit) {
        $query = trim((string) $query);
        if ($query === '') {
            return array();
        }

        $terms = get_terms(array(
            'taxonomy' => array('product_cat', 'product_tag'),
            'hide_empty' => true,
            'search' => $query,
            'number' => 6,
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $tax_query = array('relation' => 'OR');
        foreach ($terms as $term) {
            $tax_query[] = array(
                'taxonomy' => $term->taxonomy,
                'field' => 'term_id',
                'terms' => array(absint($term->term_id)),
            );
        }

        return get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Matching discovered category/tag terms is the purpose of this bounded fallback query.
            'tax_query' => $tax_query,
            'no_found_rows' => true,
        ));
    }

    private function enforce_hard_runtime_filters($product_ids, $analysis, $limit) {
        $ids = array_values(array_unique(array_map('absint', (array) $product_ids)));
        if (empty($ids)) {
            return array();
        }

        $filtered = array();
        foreach ($ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_status() !== 'publish') {
                continue;
            }

            if (!$this->product_matches_requested_variation($product, $analysis)) {
                continue;
            }

            if (!empty($analysis['in_stock_only']) && !CatalogAvailabilityService::is_available($product)) {
                continue;
            }

            if ($this->analysis_requires_sale($analysis) && !$product->is_on_sale()) {
                continue;
            }

            if (!empty($analysis['price_range']) && !$this->product_price_overlaps_range($product, $analysis['price_range'])) {
                continue;
            }

            $filtered[] = $product_id;
            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Requires size/color/device/capacity constraints to match one purchasable
     * variation rather than different values scattered across the parent.
     *
     * Product discovery should never present an in-stock parent as a match when
     * the requested exact variation exists only out of stock.
     */
    private function product_matches_requested_variation($product, $analysis) {
        if (!$product || !$product->is_type('variable')) {
            return true;
        }

        $analysis = is_array($analysis) ? $analysis : array();
        $requested_colors = array_values(array_filter((array) ($analysis['color_terms'] ?? array())));
        $requested_sizes = array_values(array_filter((array) ($analysis['size_terms'] ?? array())));
        $price_range = !empty($analysis['price_range']) && is_array($analysis['price_range']) ? $analysis['price_range'] : array();
        $sale_only = $this->analysis_requires_sale($analysis);
        $has_structured_request = !empty($requested_colors) || !empty($requested_sizes);

        if (!$has_structured_request && empty($price_range) && !$sale_only && empty($analysis['in_stock_only'])) {
            return true;
        }

        $found_matching = false;
        foreach ((array) $product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation->exists() || $variation->get_status() !== 'publish') {
                continue;
            }

            $attribute_text = $this->variation_attribute_text($variation);
            if (!empty($requested_colors) && !$this->text_has_any_search_term($attribute_text, $requested_colors)) {
                continue;
            }
            if (!empty($requested_sizes) && !$this->text_has_any_search_term($attribute_text, $requested_sizes)) {
                continue;
            }

            $price = $variation->get_price();
            if (!empty($price_range)) {
                if ($price === '' || $price === null) {
                    continue;
                }
                $price = (float) $price;
                if (isset($price_range['min']) && $price_range['min'] !== null && $price < (float) $price_range['min']) {
                    continue;
                }
                if (isset($price_range['max']) && $price_range['max'] !== null && $price > (float) $price_range['max']) {
                    continue;
                }
            }
            if ($sale_only && !$variation->is_on_sale()) {
                continue;
            }

            $found_matching = true;
            if ($variation->is_in_stock() || $variation->backorders_allowed()) {
                return true;
            }
        }

        if ($has_structured_request) {
            return false;
        }

        // For broad price/sale/stock browsing, a matching available child is
        // required when the parent itself does not represent a purchasable SKU.
        return $found_matching;
    }

    private function variation_attribute_text($variation) {
        $chunks = array();
        foreach ((array) $variation->get_attributes() as $name => $value) {
            $name = $this->search_language()->normalize_text((string) $name);
            $value = $this->search_language()->normalize_text((string) $value);
            if ($name !== '') {
                $chunks[] = $name;
            }
            if ($value !== '') {
                $chunks[] = $value;
                if (taxonomy_exists($name)) {
                    $term = get_term_by('slug', $value, $name);
                    if ($term && !is_wp_error($term)) {
                        $chunks[] = $this->search_language()->normalize_text($term->name);
                    }
                }
            }
        }
        return implode(' ', array_values(array_unique(array_filter($chunks))));
    }

    private function filter_by_price_words($product_ids, $query, $limit) {
        $range = $this->price_range_from_query($query);
        if (!$range) {
            return array_slice($product_ids, 0, $limit);
        }

        $filtered = array();
        foreach ((array) $product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }
            $price = (float) $product->get_price();
            if ($price <= 0) {
                continue;
            }
            if ($range['min'] !== null && $price < $range['min']) {
                continue;
            }
            if ($range['max'] !== null && $price > $range['max']) {
                continue;
            }
            $filtered[] = absint($product_id);
        }

        return array_slice($filtered, 0, $limit);
    }

    private function price_only_alternative_ids($range, $limit) {
        global $wpdb;

        $table = ProductIndexService::table_name();
        $where = array('price IS NOT NULL', 'price > 0');
        $params = array();

        if (isset($range['min']) && $range['min'] !== null) {
            $where[] = 'price >= %f';
            $params[] = (float) $range['min'];
        }
        if (isset($range['max']) && $range['max'] !== null) {
            $where[] = 'price <= %f';
            $params[] = (float) $range['max'];
        }

        if (!(new ProductIndexService())->is_ready()) {
            return array();
        }

        $params[] = absint($limit);
        $sql = "SELECT product_id FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY CASE WHEN stock_status = 'instock' THEN 0 ELSE 1 END, rating DESC, total_sales DESC, updated_at DESC LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The WHERE clauses are fixed internal fragments, all variable values use placeholders, and live catalog results must not be stale.
        return array_map('absint', $wpdb->get_col($wpdb->prepare($sql, $params)));
    }

    private function diversify_by_core_terms(ProductIndexService $index, $analysis, $product_ids, $query, $limit) {
        // Phrase-aware discovery already has a mandatory product-family gate.
        // Searching each core token separately would reintroduce unrelated
        // products (for example a yoga mat for `running shoes` or a backpack
        // for `water bottle`). Diversification remains available only for
        // unstructured keyword searches.
        if (!empty($analysis['product_family_term'])) {
            return array_values(array_unique(array_map('absint', (array) $product_ids)));
        }

        $core_terms = !empty($analysis['core_terms']) ? array_values(array_unique(array_filter((array) $analysis['core_terms']))) : array();
        if (count($core_terms) < 2) {
            return array_values(array_unique(array_map('absint', (array) $product_ids)));
        }

        $diverse = array();
        foreach (array_slice($core_terms, 0, 4) as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }
            $term_ids = $index->search_ids($term, 2);
            foreach ((array) $term_ids as $term_id) {
                $term_id = absint($term_id);
                if ($term_id && !in_array($term_id, $diverse, true)) {
                    $diverse[] = $term_id;
                    break;
                }
            }
        }

        $merged = array_merge($diverse, (array) $product_ids);
        return array_slice(array_values(array_unique(array_map('absint', $merged))), 0, max(1, min(8, absint($limit))));
    }

    private function facet_relaxed_alternative_ids(ProductIndexService $index, $analysis, $original_query, $limit) {
        $core_terms = !empty($analysis['core_terms']) ? array_filter((array) $analysis['core_terms']) : array();
        if (empty($core_terms)) {
            return array();
        }

        $query = implode(' ', array_slice($core_terms, 0, 5));
        if ($this->analysis_requires_sale($analysis)) {
            $query .= ' on sale';
        }

        $ids = $index->search_ids($query, $limit);
        if (empty($ids)) {
            return array();
        }

        if (!empty($analysis['price_range'])) {
            $ids = $this->filter_by_price_words($ids, $original_query, $limit);
        }

        return array_slice(array_values(array_unique(array_map('absint', $ids))), 0, $limit);
    }


    private function search_context($note, $analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        return array(
            'note' => (string) $note,
            'analysis' => $analysis,
            'requestedLabel' => $this->requested_label_from_analysis($analysis),
            'constraintText' => $this->constraint_text_from_analysis($analysis),
            'priceText' => !empty($analysis['price_range']) ? $this->price_text_from_range($analysis['price_range']) : '',
            'modifierText' => $this->modifier_text_from_analysis($analysis),
            'understandingText' => $this->understanding_text_from_analysis($analysis),
        );
    }

    private function understanding_text_from_analysis($analysis) {
        $parts = array();
        $label = $this->requested_label_from_analysis($analysis);
        if ($label && $label !== __('products', 'geeky-bot')) {
            $parts[] = $label;
        }

        if ($this->analysis_requires_sale($analysis)) {
            $parts[] = __('currently on sale', 'geeky-bot');
        }

        $constraint_text = $this->constraint_text_from_analysis($analysis);
        if ($constraint_text !== '') {
            $parts[] = $constraint_text;
        }

        if (!empty($analysis['price_range'])) {
            $price_text = $this->price_text_from_range($analysis['price_range']);
            if ($price_text !== '') {
                $parts[] = $price_text;
            }
        }

        $modifier_text = $this->modifier_text_from_analysis($analysis);
        if ($modifier_text !== '') {
            $parts[] = $modifier_text;
        }

        $parts = array_values(array_unique(array_filter(array_map('trim', $parts))));
        return implode(' ', $parts);
    }

    private function modifier_text_from_analysis($analysis) {
        $labels = !empty($analysis['modifier_labels']) ? array_values(array_unique(array_filter((array) $analysis['modifier_labels']))) : array();
        if (empty($labels)) {
            return '';
        }

        $labels = array_slice($labels, 0, 3);
        return sprintf(
            /* translators: %s: soft shopper preference such as comfortable or formal. */
            __('with %s preference', 'geeky-bot'),
            $this->human_join_terms($labels)
        );
    }

    private function constraint_text_from_analysis($analysis) {
        $parts = array();
        $colors = !empty($analysis['requested_color_labels']) ? array_values(array_unique(array_filter((array) $analysis['requested_color_labels']))) : array();
        $sizes = !empty($analysis['requested_size_labels']) ? array_values(array_unique(array_filter((array) $analysis['requested_size_labels']))) : array();
        $negative_colors = !empty($analysis['negative_color_labels']) ? array_values(array_unique(array_filter((array) $analysis['negative_color_labels']))) : array();
        $negative_sizes = !empty($analysis['negative_size_labels']) ? array_values(array_unique(array_filter((array) $analysis['negative_size_labels']))) : array();
        $query_text = !empty($analysis['lower']) ? (string) $analysis['lower'] : (!empty($analysis['raw']) ? (string) $analysis['raw'] : '');
        $colors = $this->order_terms_by_query($colors, $query_text);
        $sizes = $this->order_terms_by_query($sizes, $query_text);
        $negative_colors = $this->order_terms_by_query($negative_colors, $query_text);
        $negative_sizes = $this->order_terms_by_query($negative_sizes, $query_text);

        if (!empty($colors)) {
            $parts[] = sprintf(
                /* translators: %s: requested color */
                __('in %s', 'geeky-bot'),
                $this->human_join_terms($colors)
            );
        }

        if (!empty($negative_colors)) {
            $parts[] = sprintf(
                /* translators: %s: excluded color */
                __('not in %s', 'geeky-bot'),
                $this->human_join_terms($negative_colors)
            );
        }

        if (!empty($sizes)) {
            $parts[] = sprintf(
                /* translators: %s: requested size */
                __('in size %s', 'geeky-bot'),
                strtoupper($this->human_join_terms($sizes))
            );
        }

        if (!empty($negative_sizes)) {
            $parts[] = sprintf(
                /* translators: %s: excluded size */
                __('not in size %s', 'geeky-bot'),
                strtoupper($this->human_join_terms($negative_sizes))
            );
        }

        return trim(implode(' ', $parts));
    }

    private function requested_label_from_analysis($analysis) {
        if (!empty($analysis['product_phrase'])) {
            $phrase = trim(wp_strip_all_tags((string) $analysis['product_phrase']));
            if ($phrase !== '') {
                return $this->humanize_product_label_term($phrase);
            }
        }

        $terms = !empty($analysis['display_core_terms']) ? (array) $analysis['display_core_terms'] : (!empty($analysis['core_terms']) ? (array) $analysis['core_terms'] : (isset($analysis['terms']) ? (array) $analysis['terms'] : array()));
        $clean = array();
        $ignore = array_fill_keys(array('don', 'dont', 't', 'maybe', 'perhaps', 'possibly', 'possible', 'something', 'option', 'options', 'color', 'colour', 'size', 'sizes', 'what', 'if', 'one', 'ones', 'would', 'will', 'should', 'could', 'actually', 'currently', 'still', 'look', 'looks', 'good', 'nice', 'quality', 'useful', 'popular', 'simple', 'expensive', 'costly', 'pricey', 'luxury', 'cheapest', 'lowest'), true);
        foreach ($terms as $term) {
            $term = trim((string) $term);
            if ($term === '' || isset($ignore[$term]) || preg_match('/^\d+(?:\.\d+)?$/', $term)) {
                continue;
            }
            if (preg_match('/\b(?:don|dont|if|one|possible|product|products|something|expensive|cheapest)\b/u', $term)) {
                continue;
            }
            $clean[] = $term;
        }
        if (empty($clean) && !empty($analysis['searchable']) && empty($analysis['modifier_terms']) && empty($analysis['in_stock_only']) && empty($analysis['price_range']) && (empty($analysis['intent']) || $analysis['intent'] === 'search')) {
            $fallback = trim((string) $analysis['searchable']);
            if ($fallback !== '' && !preg_match('/\b(?:don|dont|if|one|possible|product|products|something|expensive|cheapest|quality|useful|popular|simple)\b/u', $fallback)) {
                $clean[] = $fallback;
            }
        }
        $clean = array_values(array_unique($clean));
        $clean = array_map(array($this, 'humanize_product_label_term'), $clean);
        if (count($clean) === 2) {
            return sprintf(
                /* translators: 1: first product term, 2: second product term */
                __('%1$s or %2$s', 'geeky-bot'),
                $clean[0],
                $clean[1]
            );
        }
        $label = trim(implode(' ', array_slice($clean, 0, 4)));
        return $label !== '' ? $label : __('products', 'geeky-bot');
    }

    private function humanize_product_label_term($term) {
        $term = trim((string) $term);
        $map = array(
            'shoe' => __('shoes', 'geeky-bot'),
            'tshirt' => __('t-shirts', 'geeky-bot'),
            't-shirt' => __('t-shirts', 'geeky-bot'),
        );
        return isset($map[$term]) ? $map[$term] : $term;
    }

    private function order_terms_by_query($terms, $query_text) {
        $terms = array_values(array_unique(array_filter(array_map('strval', (array) $terms))));
        if (count($terms) < 2 || trim((string) $query_text) === '') {
            return $terms;
        }

        $query_text = function_exists('mb_strtolower') ? mb_strtolower((string) $query_text) : strtolower((string) $query_text);
        usort($terms, function ($a, $b) use ($query_text) {
            $pos_a = strpos($query_text, function_exists('mb_strtolower') ? mb_strtolower((string) $a) : strtolower((string) $a));
            $pos_b = strpos($query_text, function_exists('mb_strtolower') ? mb_strtolower((string) $b) : strtolower((string) $b));
            $pos_a = $pos_a === false ? PHP_INT_MAX : $pos_a;
            $pos_b = $pos_b === false ? PHP_INT_MAX : $pos_b;
            if ($pos_a === $pos_b) {
                return 0;
            }
            return $pos_a < $pos_b ? -1 : 1;
        });

        return $terms;
    }

    private function human_join_terms($terms) {
        $terms = array_values(array_unique(array_filter(array_map('strval', (array) $terms))));
        if (empty($terms)) {
            return '';
        }
        if (count($terms) === 1) {
            return $terms[0];
        }
        if (count($terms) === 2) {
            return sprintf(
                /* translators: 1: first term, 2: second term */
                __('%1$s or %2$s', 'geeky-bot'),
                $terms[0],
                $terms[1]
            );
        }

        $last = array_pop($terms);
        return sprintf(
            /* translators: 1: comma-separated terms, 2: final term */
            __('%1$s, or %2$s', 'geeky-bot'),
            implode(', ', $terms),
            $last
        );
    }

    private function match_preference_label($label) {
        $label = trim(wp_strip_all_tags((string) $label));
        if ($label === '') {
            return '';
        }

        $normalized = $this->search_language()->normalize_text($label);
        $map = array(
            'comfort' => __('Comfort', 'geeky-bot'),
            'comfortable' => __('Comfort', 'geeky-bot'),
            'budget-friendly' => __('Budget friendly', 'geeky-bot'),
            'budget friendly' => __('Budget friendly', 'geeky-bot'),
            'sports/walking' => __('Walking / sports', 'geeky-bot'),
            'sports walking' => __('Walking / sports', 'geeky-bot'),
            'party/event' => __('Party / event', 'geeky-bot'),
            'party event' => __('Party / event', 'geeky-bot'),
            'school/college' => __('School / college', 'geeky-bot'),
            'school college' => __('School / college', 'geeky-bot'),
            'premium quality' => __('Premium quality', 'geeky-bot'),
        );

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return ucwords(str_replace(array('-', '/'), array(' ', ' / '), $label));
    }

    private function price_text_from_range($range) {
        if (empty($range)) {
            return '';
        }
        if (isset($range['mode']) && $range['mode'] === 'around' && isset($range['target'])) {
            return sprintf(
                /* translators: %s: target price */
                __('around %s', 'geeky-bot'),
                $this->money_text($range['target'])
            );
        }
        if ($range['min'] !== null && $range['max'] !== null) {
            return sprintf(
                /* translators: 1: min price, 2: max price */
                __('between %1$s and %2$s', 'geeky-bot'),
                $this->money_text($range['min']),
                $this->money_text($range['max'])
            );
        }
        if ($range['max'] !== null) {
            return sprintf(
                /* translators: %s: max price */
                __('under %s', 'geeky-bot'),
                $this->money_text($range['max'])
            );
        }
        if ($range['min'] !== null) {
            return sprintf(
                /* translators: %s: min price */
                __('over %s', 'geeky-bot'),
                $this->money_text($range['min'])
            );
        }
        return '';
    }

    private function money_text($amount) {
        $price = function_exists('wc_price') ? wc_price((float) $amount) : (string) $amount;
        return html_entity_decode(wp_strip_all_tags($price), ENT_QUOTES, get_bloginfo('charset'));
    }

    private function price_range_from_query($query) {
        return $this->search_language()->price_range_from_query($query);
    }

    private function product_price_text($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return '';
        }

        $format_price = function ($amount) {
            if ($amount === '' || $amount === null) {
                return '';
            }
            return html_entity_decode(wp_strip_all_tags(wc_price((float) $amount)), ENT_QUOTES, get_bloginfo('charset'));
        };

        if ($product->is_type('variable')) {
            $min = $product->get_variation_price('min', true);
            $max = $product->get_variation_price('max', true);

            if ($min !== '' && $max !== '' && (float) $min !== (float) $max) {
                return sprintf('%s – %s', $format_price($min), $format_price($max));
            }

            if ($min !== '') {
                return $format_price($min);
            }
        }

        if ($product->is_on_sale() && $product->get_regular_price() !== '' && $product->get_sale_price() !== '') {
            return sprintf(
                /* translators: 1: regular price, 2: sale price. */
                __('Was %1$s, now %2$s', 'geeky-bot'),
                $format_price($product->get_regular_price()),
                $format_price($product->get_sale_price())
            );
        }

        if ($product->get_price() !== '') {
            return $format_price($product->get_price());
        }

        return html_entity_decode(wp_strip_all_tags($product->get_price_html()), ENT_QUOTES, get_bloginfo('charset'));
    }

    private function hydrate_products($product_ids) {
        $products = array();

        foreach ((array) $product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!CatalogVisibilityService::is_visible($product, 'product_payload')) {
                continue;
            }

            $image_id = $product->get_image_id();
            $image = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src('woocommerce_thumbnail');
            $category_names = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
            if (is_wp_error($category_names)) {
                $category_names = array();
            }

            $stock_status = $this->effective_stock_status($product);
            $product_data = array(
                'id' => $product->get_id(),
                'name' => wp_strip_all_tags($product->get_name()),
                'shortDescription' => wp_trim_words(wp_strip_all_tags($product->get_short_description()), 28),
                'priceHtml' => wp_kses_post($product->get_price_html()),
                'priceText' => $this->product_price_text($product),
                'url' => get_permalink($product->get_id()),
                'image' => esc_url_raw($image),
                'stockStatus' => $stock_status,
                'stockLabel' => $this->stock_label($stock_status),
                'type' => $product->get_type(),
                'rating' => (float) $product->get_average_rating(),
                'categories' => array_map('wp_strip_all_tags', (array) $category_names),
                'sku' => $product->get_sku(),
                'isPurchasable' => $product->is_purchasable(),
                'isInStock' => in_array($stock_status, array('instock', 'onbackorder'), true),
                'requiresOptions' => $product->is_type('variable') || $product->is_type('grouped') || $product->is_type('external'),
            );

            $product_data['searchMatch'] = $this->search_match_payload($product, $category_names, $product_data);

            /**
             * Filters the safe product payload used by the frontend widget.
             * Add-ons may add non-secret display metadata, but should not expose sensitive store data.
             */
            $products[] = apply_filters('geekybot_product_payload', $product_data, $product);
        }

        return $products;
    }


    private function search_match_payload($product, $category_names, $product_data) {
        if (!$product || empty($this->last_search_context) || empty($this->last_search_context['analysis']) || !is_array($this->last_search_context['analysis'])) {
            return array();
        }

        $analysis = $this->last_search_context['analysis'];
        $note = !empty($this->last_search_context['note']) ? (string) $this->last_search_context['note'] : '';
        $matched = array();
        $not_confirmed = array();
        $product_text = $this->product_match_text($product, $category_names);

        $requested_label = !empty($this->last_search_context['requestedLabel']) ? (string) $this->last_search_context['requestedLabel'] : '';
        if ($requested_label !== '' && $requested_label !== __('products', 'geeky-bot')) {
            $core_terms = !empty($analysis['core_terms']) ? (array) $analysis['core_terms'] : array();
            if (empty($core_terms) || $this->text_has_any_search_term($product_text, $core_terms)) {
                $matched[] = sprintf(
                    /* translators: %s: product type or search term. */
                    __('Product: %s', 'geeky-bot'),
                    $requested_label
                );
            }
        }

        $query_text = !empty($analysis['lower']) ? (string) $analysis['lower'] : (!empty($analysis['raw']) ? (string) $analysis['raw'] : '');

        $color_labels = !empty($analysis['requested_color_labels']) ? array_values(array_unique(array_filter((array) $analysis['requested_color_labels']))) : array();
        $color_labels = $this->order_terms_by_query($color_labels, $query_text);
        if (!empty($color_labels)) {
            if ($this->text_has_any_search_term($product_text, !empty($analysis['color_terms']) ? (array) $analysis['color_terms'] : $color_labels)) {
                $matched[] = sprintf(
                    /* translators: %s: requested color. */
                    __('Color: %s', 'geeky-bot'),
                    $this->human_join_terms($color_labels)
                );
            } else {
                $not_confirmed[] = sprintf(
                    /* translators: %s: requested color. */
                    __('Color: %s', 'geeky-bot'),
                    $this->human_join_terms($color_labels)
                );
            }
        }

        $size_labels = !empty($analysis['requested_size_labels']) ? array_values(array_unique(array_filter((array) $analysis['requested_size_labels']))) : array();
        $size_labels = $this->order_terms_by_query($size_labels, $query_text);
        if (!empty($size_labels)) {
            if ($this->text_has_any_search_term($product_text, !empty($analysis['size_terms']) ? (array) $analysis['size_terms'] : $size_labels)) {
                $matched[] = sprintf(
                    /* translators: %s: requested size. */
                    __('Size: %s', 'geeky-bot'),
                    strtoupper($this->human_join_terms($size_labels))
                );
            } else {
                $not_confirmed[] = sprintf(
                    /* translators: %s: requested size. */
                    __('Size: %s', 'geeky-bot'),
                    strtoupper($this->human_join_terms($size_labels))
                );
            }
        }

        if (!empty($analysis['price_range']) && is_array($analysis['price_range'])) {
            $price_text = !empty($this->last_search_context['priceText']) ? (string) $this->last_search_context['priceText'] : $this->price_text_from_range($analysis['price_range']);
            if ($price_text !== '') {
                if ($this->product_price_overlaps_range($product, $analysis['price_range'])) {
                    $matched[] = sprintf(
                        /* translators: %s: requested price phrase. */
                        __('Price: %s', 'geeky-bot'),
                        $price_text
                    );
                } else {
                    $not_confirmed[] = sprintf(
                        /* translators: %s: requested price phrase. */
                        __('Price: %s', 'geeky-bot'),
                        $price_text
                    );
                }
            }
        }

        $modifier_terms = !empty($analysis['modifier_terms']) ? array_values((array) $analysis['modifier_terms']) : array();
        $modifier_labels = !empty($analysis['modifier_labels']) ? array_values((array) $analysis['modifier_labels']) : array();
        if (!empty($modifier_terms)) {
            $matched_preferences = array();
            foreach ($modifier_terms as $index => $modifier_term) {
                if (!$this->text_has_any_search_term($product_text, array($modifier_term))) {
                    continue;
                }

                $modifier_label = isset($modifier_labels[$index]) ? $modifier_labels[$index] : $modifier_term;
                $modifier_label = $this->match_preference_label($modifier_label);
                if ($modifier_label !== '') {
                    $matched_preferences[] = $modifier_label;
                }
            }

            foreach (array_slice(array_values(array_unique($matched_preferences)), 0, 3) as $matched_preference) {
                $matched[] = $matched_preference;
            }
        }

        if ($this->analysis_requires_sale($analysis)) {
            if ($this->product_matches_sale_requirement($product, $analysis)) {
                $matched[] = __('On sale', 'geeky-bot');
            } else {
                $not_confirmed[] = __('On sale', 'geeky-bot');
            }
        }

        if (!empty($product_data['isInStock'])) {
            $matched[] = __('In stock', 'geeky-bot');
        }

        $matched = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $matched))));
        $not_confirmed = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $not_confirmed))));

        if (empty($matched) && empty($not_confirmed)) {
            return array();
        }

        $is_close = in_array($note, array('facet_alternatives', 'price_alternatives', 'price_only_results'), true) || !empty($not_confirmed);

        return array(
            'label' => $is_close ? __('Close match details', 'geeky-bot') : __('Match details', 'geeky-bot'),
            'matchedLabel' => __('Matched', 'geeky-bot'),
            'notConfirmedLabel' => __('Not confirmed', 'geeky-bot'),
            'matched' => array_slice($matched, 0, 5),
            'notConfirmed' => array_slice($not_confirmed, 0, 5),
            'type' => $is_close ? 'close' : 'match',
        );
    }

    private function prioritize_exact_query_phrases($product_ids, $query) {
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $product_ids))));
        if (count($ids) < 2) {
            return $ids;
        }

        $scored = array();
        $max_phrase_words = 0;
        foreach ($ids as $position => $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }
            $category_names = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
            $category_names = is_wp_error($category_names) ? array() : (array) $category_names;
            $phrase = $this->exact_query_phrase_score($query, $product->get_name(), implode(' ', $category_names));
            $max_phrase_words = max($max_phrase_words, $phrase['words']);
            $scored[] = array(
                'id' => $product_id,
                'position' => $position,
                'score' => $phrase['score'],
            );
        }

        if ($max_phrase_words < 2) {
            return $ids;
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['position'] <=> $b['position'];
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });
        return array_values(wp_list_pluck($scored, 'id'));
    }

    private function exact_query_phrase_score($query, $title, $categories = '') {
        $query = $this->search_language()->normalize_text($query);
        $title = $this->search_language()->normalize_text($title);
        $categories = $this->search_language()->normalize_text($categories);
        $stop = array(
            'show', 'find', 'give', 'need', 'want', 'looking', 'products', 'product',
            'something', 'anything', 'please', 'only', 'with', 'without', 'under',
            'below', 'between', 'available', 'stock', 'size', 'color', 'colour',
            'the', 'a', 'an', 'me', 'my', 'for', 'in', 'of', 'and', 'or', 'to',
        );
        $tokens = array();
        foreach (preg_split('/\s+/u', $query) as $token) {
            if ($token === '' || is_numeric($token) || strlen($token) < 2 || in_array($token, $stop, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        $best_score = 0;
        $best_words = 0;
        for ($length = min(4, count($tokens)); $length >= 2; $length--) {
            for ($start = 0; $start <= count($tokens) - $length; $start++) {
                $phrase = implode(' ', array_slice($tokens, $start, $length));
                if (strpos(' ' . $title . ' ', ' ' . $phrase . ' ') !== false) {
                    $best_score = max($best_score, 1000 + ($length * 100));
                    $best_words = max($best_words, $length);
                } elseif ($categories !== '' && strpos(' ' . $categories . ' ', ' ' . $phrase . ' ') !== false) {
                    $best_score = max($best_score, 700 + ($length * 80));
                    $best_words = max($best_words, $length);
                }
            }
        }
        return array('score' => $best_score, 'words' => $best_words);
    }

    /**
     * Returns only product-identifying fields for hard family eligibility.
     *
     * Full descriptions and attributes remain useful for qualifiers and ranking,
     * but they often mention compatible/contained items (for example a backpack
     * with a bottle pocket). Those mentions must not redefine the product family.
     */
    private function product_family_identity_text($product, $category_names) {
        // Tags are intentionally excluded from the hard identity gate. Store
        // owners frequently use broad feature/use-case tags such as `water`,
        // `travel`, or `bottle pocket`; title/category/SKU are safer identity.
        return $this->search_language()->normalize_text(implode(' ', array_filter(array(
            $product->get_name(),
            $product->get_sku(),
            implode(' ', (array) $category_names),
        ))));
    }

    private function product_match_text($product, $category_names) {
        $tag_names = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        if (is_wp_error($tag_names)) {
            $tag_names = array();
        }

        $chunks = array(
            $product->get_name(),
            $product->get_sku(),
            implode(' ', (array) $category_names),
            implode(' ', (array) $tag_names),
            wp_strip_all_tags($product->get_short_description()),
            wp_strip_all_tags(wp_trim_words($product->get_description(), 80)),
        );

        foreach ((array) $product->get_attributes() as $attribute) {
            if (!is_object($attribute)) {
                continue;
            }
            $attribute_name = method_exists($attribute, 'get_name') ? (string) $attribute->get_name() : '';
            $label = function_exists('wc_attribute_label') ? wc_attribute_label($attribute_name) : $attribute_name;
            $values = array();
            if (method_exists($attribute, 'is_taxonomy') && $attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product->get_id(), $attribute_name, array('fields' => 'names'));
                if (!is_wp_error($terms)) {
                    $values = $terms;
                }
            } elseif (method_exists($attribute, 'get_options')) {
                $values = $attribute->get_options();
            }
            $chunks[] = $label . ' ' . $attribute_name . ' ' . implode(' ', array_map('wp_strip_all_tags', (array) $values));
        }

        if ($product->is_type('variable')) {
            foreach ((array) $product->get_children() as $variation_id) {
                $variation = function_exists('wc_get_product') ? wc_get_product($variation_id) : null;
                if (!$variation) {
                    continue;
                }
                foreach ((array) $variation->get_attributes() as $attribute_name => $value) {
                    $label = function_exists('wc_attribute_label') ? wc_attribute_label((string) $attribute_name) : (string) $attribute_name;
                    $chunks[] = $label . ' ' . $attribute_name . ' ' . wp_strip_all_tags((string) $value);
                }
            }
        }

        return $this->search_language()->normalize_text(implode(' ', array_filter($chunks)));
    }

    private function text_has_any_search_term($text, $terms) {
        foreach ((array) $terms as $term) {
            if ($this->text_has_search_term($text, $term)) {
                return true;
            }
        }
        return false;
    }

    private function text_has_search_term($text, $term) {
        $text = ' ' . $this->search_language()->normalize_text($text) . ' ';
        $term = $this->search_language()->normalize_text($term);
        if ($term === '') {
            return false;
        }

        if (strpos($text, ' ' . $term . ' ') !== false) {
            return true;
        }

        if (function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') <= 2 : strlen($term) <= 2) {
            return false;
        }

        if (preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $term)) {
            return strpos($text, $term) !== false;
        }

        return strpos($text, $term) !== false;
    }

    private function product_price_overlaps_range($product, $range) {
        $min_price = null;
        $max_price = null;

        if ($product->is_type('variable')) {
            $min = $product->get_variation_price('min', true);
            $max = $product->get_variation_price('max', true);
            $min_price = $min === '' ? null : (float) $min;
            $max_price = $max === '' ? $min_price : (float) $max;
        } else {
            $price = $product->get_price();
            if ($price !== '') {
                $min_price = (float) $price;
                $max_price = (float) $price;
            }
        }

        if ($min_price === null && $max_price === null) {
            return false;
        }
        if ($max_price === null) {
            $max_price = $min_price;
        }
        if ($min_price === null) {
            $min_price = $max_price;
        }

        if (isset($range['min']) && $range['min'] !== null && $max_price < (float) $range['min']) {
            return false;
        }
        if (isset($range['max']) && $range['max'] !== null && $min_price > (float) $range['max']) {
            return false;
        }
        return true;
    }


    private function effective_stock_status($product) {
        return CatalogAvailabilityService::stock_status($product);
    }

    private function stock_label($stock_status) {
        if ($stock_status === 'instock') {
            return __('In stock', 'geeky-bot');
        }
        if ($stock_status === 'outofstock') {
            return __('Out of stock', 'geeky-bot');
        }
        if ($stock_status === 'onbackorder') {
            return __('On backorder', 'geeky-bot');
        }
        return $stock_status;
    }

    /**
     * Returns whether an analysis requires an active sale. The explicit flag is
     * authoritative, while intent=sale keeps older saved chat contexts working.
     */
    private function analysis_requires_sale($analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        return !empty($analysis['sale_required'])
            || (!empty($analysis['intent']) && $analysis['intent'] === 'sale');
    }

    /**
     * Confirms that a product genuinely satisfies the active sale mission. For
     * variable products, the existing variation matcher guarantees that at
     * least one published, purchasable matching variation is actively on sale
     * and available/backorderable. WooCommerce's is_on_sale() also respects
     * scheduled sale dates.
     */
    private function product_matches_sale_requirement($product, $analysis) {
        if (!$this->analysis_requires_sale($analysis)) {
            return true;
        }
        if (!$product || !$product->is_on_sale()) {
            return false;
        }
        if ($product->is_type('variable')) {
            return $this->product_matches_requested_variation($product, $analysis);
        }
        return true;
    }

    private function search_language() {
        static $language = null;
        if ($language === null) {
            $language = new SearchLanguageService();
        }
        return $language;
    }
}
