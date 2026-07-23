<?php
namespace GeekyBot\ProductExpert;

use GeekyBot\Services\CatalogVisibilityService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves named and conversational product references without exposing hidden
 * WooCommerce products or guessing between similarly scored candidates.
 */
class ProductReferenceResolver {
    const MAX_SCAN = 250;

    public function resolve($message, $route, $context_resolution = array()) {
        if (!function_exists('wc_get_product')) {
            return $this->unresolved();
        }

        $message = $this->normalize($message);
        $route = is_array($route) ? $route : array();
        $context_resolution = is_array($context_resolution) ? $context_resolution : array();
        $context_ids = $this->context_ids($context_resolution);
        $current_context_ids = $this->current_context_ids($context_resolution);

        $ordinal = $this->ordinal_index($message);
        if ($ordinal !== null && isset($context_ids[$ordinal])) {
            $product = $this->visible_product($context_ids[$ordinal]);
            if ($product) {
                return $this->resolved($product, 'ordinal');
            }
        }

        if ($this->has_pronoun_reference($message)) {
            // A selected product is trustworthy only when it is still part of
            // the current result set, or when the immediately preceding turn
            // was itself a Product Expert answer. This prevents an old single
            // product from leaking into a later multi-product search.
            $selected_product = $this->selected_context_product($context_resolution, $current_context_ids);
            if ($selected_product) {
                return $this->resolved($selected_product, 'selected_context');
            }

            if (count($current_context_ids) === 1) {
                $product = $this->visible_product($current_context_ids[0]);
                if ($product) {
                    return $this->resolved($product, 'single_current_context');
                }
            }

            if (count($current_context_ids) > 1) {
                return $this->ambiguous_context($current_context_ids, 'ambiguous_current_context');
            }

            if (count($context_ids) === 1) {
                $product = $this->visible_product($context_ids[0]);
                if ($product) {
                    return $this->resolved($product, 'single_context');
                }
            }

            if (count($context_ids) > 1) {
                return $this->ambiguous_context($context_ids, 'ambiguous_context');
            }
        }

        $subject_text = !empty($route['subjectText']) ? $this->normalize($route['subjectText']) : '';

        // Generic follow-up wording such as "What XL hoodie colour is available?"
        // should continue with the product selected by the immediately preceding
        // Product Expert answer. A new distinctive product/model token still
        // takes precedence and starts a fresh named lookup.
        $selected_product = $this->selected_context_product($context_resolution);
        $identity_tokens = $this->distinctive_query_tokens($message, $subject_text);
        if ($selected_product
            && empty($identity_tokens)
            && $this->context_product_matches_question($selected_product, $message, $subject_text)) {
            return $this->resolved($selected_product, 'selected_context_followup');
        }

        // When the storefront currently shows exactly one visible product, a
        // family reference such as "the mug", "the backpack", or "the
        // keyboard" should resolve to that product. Use only the current
        // visible result set here; an older multi-product reference list must
        // never turn this into a guess. Distinctive product/model wording still
        // starts a fresh named lookup.
        $single_current_product = $this->single_current_context_product($context_resolution);
        if ($single_current_product
            && empty($identity_tokens)
            && $this->context_product_matches_question($single_current_product, $message, $subject_text)) {
            return $this->resolved($single_current_product, 'single_current_context_followup');
        }

        $scored = $this->score_catalog($message, $subject_text);
        if (!empty($scored)) {
            $top = $scored[0];
            $second = isset($scored[1]) ? $scored[1] : null;

            $strong_identity = !empty($top['exact']) || !empty($top['distinctiveOverlap']) || (!empty($top['overlap']) && $top['overlap'] >= 3);
            if ($top['score'] >= 24 && $strong_identity) {
                $ambiguous = $second
                    && $second['score'] >= 24
                    && (!empty($second['exact']) || !empty($second['distinctiveOverlap']) || (!empty($second['overlap']) && $second['overlap'] >= 3))
                    && abs($top['score'] - $second['score']) <= 3;
                if (!$ambiguous || !empty($top['exact'])) {
                    return $this->resolved($top['product'], 'named_catalog');
                }

                return array(
                    'resolved' => false,
                    'ambiguous' => true,
                    'productId' => 0,
                    'product' => null,
                    'candidates' => array_values(array_filter(array(
                        $this->candidate($top['product']),
                        $this->candidate($second['product']),
                    ))),
                    'source' => 'ambiguous_catalog',
                );
            }
        }

        // A recent context product can still be used when the shopper asks a
        // fact question with no explicit pronoun, e.g. "What material?".
        if (!empty($context_ids) && empty($subject_text)) {
            $product = $this->visible_product($context_ids[0]);
            if ($product) {
                return $this->resolved($product, 'implicit_context');
            }
        }

        return $this->unresolved();
    }

    private function score_catalog($message, $subject_text) {
        $ids = $this->candidate_ids($message, $subject_text);
        $message_tokens = $this->tokens($message);
        $subject_tokens = $this->tokens($subject_text);
        $all_query_tokens = array_values(array_unique(array_merge($message_tokens, $subject_tokens)));
        $identity_tokens = array_values(array_filter($all_query_tokens, array($this, 'is_distinctive_token')));
        $scores = array();

        foreach (array_values(array_unique(array_filter(array_map('absint', (array) $ids)))) as $product_id) {
            $product = $this->visible_product($product_id);
            if (!$product) {
                continue;
            }

            $title = $this->normalize($product->get_name());
            $sku = $this->normalize($product->get_sku());
            $title_tokens = $this->tokens($title);
            if (empty($title_tokens)) {
                continue;
            }

            $exact = false;
            $score = 0;
            $title_identity_tokens = array_values(array_filter($title_tokens, array($this, 'is_distinctive_token')));
            $title_is_specific = !empty($title_identity_tokens) || count($title_tokens) >= 2;
            if ($title !== ''
                && $title_is_specific
                && strpos(' ' . $message . ' ', ' ' . $title . ' ') !== false) {
                $score += 130;
                $exact = true;
            }
            $subject_identity_tokens = array_values(array_filter($subject_tokens, array($this, 'is_distinctive_token')));
            $subject_is_specific = !empty($subject_identity_tokens) || count($subject_tokens) >= 2;
            if ($subject_text !== ''
                && $subject_is_specific
                && ($title === $subject_text || strpos(' ' . $title . ' ', ' ' . $subject_text . ' ') !== false)) {
                $score += 90;
                $exact = true;
            }
            if ($sku !== '' && strpos(' ' . $message . ' ', ' ' . $sku . ' ') !== false) {
                $score += 120;
                $exact = true;
            }

            foreach ($title_tokens as $token) {
                if (!in_array($token, $message_tokens, true) && !in_array($token, $subject_tokens, true)) {
                    continue;
                }
                $score += $this->token_weight($token);
            }

            $overlap_tokens = array_values(array_intersect($title_tokens, $all_query_tokens));
            $overlap = count($overlap_tokens);
            $distinctive_overlap_tokens = array_values(array_intersect($title_tokens, $identity_tokens));
            $distinctive_overlap = count($distinctive_overlap_tokens);

            // When the shopper supplies a model/brand-like token such as
            // "TrailRun", products matching only a generic family word such as
            // "Shoes" must not outrank the named product.
            if (!empty($identity_tokens) && empty($distinctive_overlap_tokens) && !$exact) {
                $score -= 100;
            } elseif (!empty($distinctive_overlap_tokens)) {
                $score += 35 * count($distinctive_overlap_tokens);
            }
            if ($overlap >= 2) {
                $score += 12 + ($overlap * 3);
            }
            if ($overlap === count($title_tokens) && $overlap > 0) {
                $score += 20;
            }

            // Distinctive brand/model prefixes such as TrailGuard, FlexShield,
            // AllWeather, or WorldPort should strongly resolve the product.
            $first_title_token = reset($title_tokens);
            if ($first_title_token && strlen($first_title_token) >= 7 && in_array($first_title_token, $message_tokens, true)) {
                $score += 26;
            }

            if ($score > 0) {
                $scores[] = array(
                    'score' => $score,
                    'exact' => $exact,
                    'overlap' => $overlap,
                    'distinctiveOverlap' => $distinctive_overlap,
                    'product' => $product,
                );
            }
        }

        usort($scores, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['product']->get_id() <=> $b['product']->get_id();
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });

        return array_slice($scores, 0, 5);
    }

    /**
     * Uses prepared title/SKU lookups first so named product questions work on
     * large catalogs without loading every product into PHP.
     */
    private function candidate_ids($message, $subject_text) {
        $ids = array();
        $tokens = array_values(array_unique(array_merge($this->tokens($subject_text), $this->tokens($message))));
        usort($tokens, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });
        $tokens = array_values(array_filter($tokens, function ($token) {
            return $this->is_distinctive_token($token);
        }));
        $tokens = array_slice($tokens, 0, 6);

        global $wpdb;
        if (!empty($wpdb) && !empty($tokens)) {
            $conditions = array();
            $args = array();
            foreach ($tokens as $token) {
                $conditions[] = 'post_title LIKE %s';
                $args[] = '%' . $wpdb->esc_like($token) . '%';
            }
            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND (" . implode(' OR ', $conditions) . ') ORDER BY post_date DESC LIMIT 120';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name and condition fragments are generated internally; all shopper terms use placeholders and this bounded resolver lookup must be current.
            $ids = array_merge($ids, array_map('absint', (array) $wpdb->get_col($wpdb->prepare($sql, $args))));

            $sku_probe = trim((string) $subject_text);
            if ($sku_probe !== '') {
                $sku_sql = "SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_sku' AND pm.meta_value LIKE %s AND p.post_type = 'product' AND p.post_status = 'publish' LIMIT 20";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The trusted table names are internal, the SKU value uses a placeholder, and this bounded resolver lookup must be current.
                $ids = array_merge($ids, array_map('absint', (array) $wpdb->get_col($wpdb->prepare($sku_sql, '%' . $wpdb->esc_like($sku_probe) . '%'))));
            }
        }

        if (count(array_unique($ids)) < 10 && function_exists('wc_get_products')) {
            $ids = array_merge($ids, (array) wc_get_products(array(
                'status' => 'publish',
                'limit' => self::MAX_SCAN,
                'return' => 'ids',
                'orderby' => 'date',
                'order' => 'DESC',
            )));
        }

        if (empty($ids)) {
            $ids = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => self::MAX_SCAN,
                'no_found_rows' => true,
            ));
        }

        return array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
    }

    private function context_ids($context_resolution) {
        $groups = array(
            !empty($context_resolution['previousCurrentProductIds']) ? $context_resolution['previousCurrentProductIds'] : array(),
            !empty($context_resolution['previousProductIds']) ? $context_resolution['previousProductIds'] : array(),
            !empty($context_resolution['previousLastMultiProductIds']) ? $context_resolution['previousLastMultiProductIds'] : array(),
            !empty($context_resolution['previousReferenceProductIds']) ? $context_resolution['previousReferenceProductIds'] : array(),
        );

        $ids = array();
        foreach ($groups as $group) {
            foreach ((array) $group as $id) {
                $id = absint($id);
                if ($id && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }
        return array_slice($ids, 0, 12);
    }


    private function selected_context_product($context_resolution, $current_ids = array()) {
        $selected_id = !empty($context_resolution['selectedProductId'])
            ? absint($context_resolution['selectedProductId'])
            : (!empty($context_resolution['previousContext']['selectedProductId'])
                ? absint($context_resolution['previousContext']['selectedProductId'])
                : 0);
        if (!$selected_id) {
            return null;
        }

        $current_ids = !empty($current_ids)
            ? array_values(array_unique(array_filter(array_map('absint', (array) $current_ids))))
            : $this->current_context_ids($context_resolution);

        // With no current storefront list, the last explicitly selected
        // product remains the best available reference.
        if (empty($current_ids)) {
            return $this->visible_product($selected_id);
        }

        // A one-product storefront result is unambiguous, but never trust a
        // selected ID that points to some older product.
        if (count($current_ids) === 1) {
            return $current_ids[0] === $selected_id
                ? $this->visible_product($selected_id)
                : null;
        }

        // After a Product Expert answer or a harmless Conversation Guard turn,
        // the explicitly selected product remains in focus while the original
        // multi-product list stays available for ordinal comparison. A fresh
        // multi-product search still clears the selection before this resolver runs.
        $previous_action = !empty($context_resolution['previousContext']['action'])
            ? sanitize_key((string) $context_resolution['previousContext']['action'])
            : '';
        if (in_array($previous_action, array(
            'product_question',
            'conversation_product_reference',
            'conversation_product_selection',
            'conversation_clarification_selection',
            'conversation_correction',
            'conversation_statement',
            'conversation_acknowledgement',
        ), true) && in_array($selected_id, $current_ids, true)) {
            return $this->visible_product($selected_id);
        }

        return null;
    }

    private function current_context_ids($context_resolution) {
        $current_ids = !empty($context_resolution['previousCurrentProductIds'])
            ? (array) $context_resolution['previousCurrentProductIds']
            : (!empty($context_resolution['previousContext']['productIds'])
                ? (array) $context_resolution['previousContext']['productIds']
                : array());

        return array_values(array_unique(array_filter(array_map('absint', $current_ids))));
    }

    private function ambiguous_context($product_ids, $source) {
        $candidates = array();
        foreach (array_slice(array_values((array) $product_ids), 0, 4) as $product_id) {
            $product = $this->visible_product($product_id);
            if (!$product) {
                continue;
            }
            $candidate = $this->candidate($product);
            if (!empty($candidate)) {
                $candidates[] = $candidate;
            }
        }

        return array(
            'resolved' => false,
            'ambiguous' => true,
            'productId' => 0,
            'product' => null,
            'candidates' => $candidates,
            'source' => sanitize_key((string) $source),
        );
    }

    /**
     * Returns the current visible product only when the latest storefront
     * result set contains exactly one product. Historical comparison/reference
     * lists are intentionally ignored.
     */
    private function single_current_context_product($context_resolution) {
        $current_ids = $this->current_context_ids($context_resolution);
        if (count($current_ids) !== 1) {
            return null;
        }

        return $this->visible_product($current_ids[0]);
    }

    private function distinctive_query_tokens($message, $subject_text = '') {
        $tokens = array_values(array_unique(array_merge(
            $this->tokens($message),
            $this->tokens($subject_text)
        )));

        return array_values(array_filter($tokens, array($this, 'is_distinctive_token')));
    }

    private function context_product_matches_question($product, $message, $subject_text = '') {
        if (!$product) {
            return false;
        }

        $query_tokens = array_values(array_unique(array_merge(
            $this->tokens($message),
            $this->tokens($subject_text)
        )));
        if (empty($query_tokens)) {
            return true;
        }

        $title_tokens = $this->tokens($product->get_name());
        $family_tokens = array_values(array_intersect(
            $query_tokens,
            $this->product_family_tokens()
        ));

        // A generic follow-up that names the same product family should retain
        // the selected product. For example: selected AllWeather hoodie +
        // "What XL hoodie colour is available?"
        if (!empty($family_tokens) && array_intersect($family_tokens, $title_tokens)) {
            return true;
        }

        // Attribute-only follow-ups such as "Which XL colour is available?"
        // may omit the product family entirely. Keep the selected product when
        // there is no competing catalog identity in the message.
        $attribute_tokens = array(
            'xl', 'xxl', 'small', 'medium', 'large', 'black', 'white', 'grey',
            'gray', 'blue', 'red', 'navy', 'olive', 'sage', 'size', 'color',
            'colour', 'capacity', 'device',
        );
        $non_attribute_tokens = array_values(array_diff($query_tokens, $attribute_tokens));
        return empty($non_attribute_tokens);
    }

    private function product_family_tokens() {
        return array(
            'backpack', 'bag', 'bags', 'laptop', 'sleeve', 'organiser', 'organizer',
            'shoes', 'shoe', 'sneakers', 'hoodie', 'keyboard', 'charger', 'speaker',
            'bottle', 'mug', 'jacket', 'case', 'wallet', 'clock', 'adapter',
            'tumbler', 'footrest', 'scarf', 'earbuds', 'mat', 'bands', 'pillow',
        );
    }

    private function ordinal_index($message) {
        $map = array(
            'first' => 0,
            '1st' => 0,
            'second' => 1,
            '2nd' => 1,
            'third' => 2,
            '3rd' => 2,
            'fourth' => 3,
            '4th' => 3,
        );
        foreach ($map as $word => $index) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $message)) {
                return $index;
            }
        }
        return null;
    }

    private function has_pronoun_reference($message) {
        return preg_match('/\b(this\s+product|that\s+product|this\s+item|that\s+item|this\s+one|that\s+one|the\s+product|the\s+item|it|its|that\s+colou?r|this\s+colou?r)\b/u', $message) === 1;
    }

    private function visible_product($product_id) {
        $product = wc_get_product(absint($product_id));
        return CatalogVisibilityService::is_visible($product, 'product_expert_reference') ? $product : null;
    }

    private function resolved($product, $source) {
        return array(
            'resolved' => true,
            'ambiguous' => false,
            'productId' => absint($product->get_id()),
            'product' => $product,
            'candidates' => array(),
            'source' => sanitize_key($source),
        );
    }

    private function unresolved() {
        return array(
            'resolved' => false,
            'ambiguous' => false,
            'productId' => 0,
            'product' => null,
            'candidates' => array(),
            'source' => 'unresolved',
        );
    }

    private function candidate($product) {
        if (!$product) {
            return array();
        }
        return array(
            'id' => absint($product->get_id()),
            'name' => wp_strip_all_tags($product->get_name()),
        );
    }

    private function tokens($value) {
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $this->normalize($value));
        $stop = array(
            'the', 'a', 'an', 'is', 'are', 'does', 'do', 'can', 'could', 'will',
            'would', 'what', 'which', 'how', 'this', 'that', 'it', 'its', 'one',
            'product', 'item', 'made', 'from', 'of', 'in', 'on', 'for', 'with',
            'have', 'has', 'come', 'comes', 'currently', 'available', 'price',
            'stock', 'material', 'waterproof', 'warranty', 'size', 'color', 'colour',
        );
        $tokens = array();
        foreach (preg_split('/\s+/u', trim((string) $value)) as $token) {
            if ($token === '' || in_array($token, $stop, true)) {
                continue;
            }
            $tokens[] = $token;
        }
        return array_values(array_unique($tokens));
    }

    private function is_distinctive_token($token) {
        $generic = array(
            'backpack', 'laptop', 'shoes', 'shoe', 'hoodie', 'keyboard', 'charger',
            'speaker', 'bottle', 'mug', 'jacket', 'case', 'wallet', 'clock', 'adapter',
            'tumbler', 'sleeve', 'footrest', 'organiser', 'organizer', 'travel', 'office',
            'black', 'white', 'grey', 'gray', 'blue', 'red', 'navy', 'olive', 'sage',
            'small', 'medium', 'large', 'size',
        );
        return strlen((string) $token) >= 5 && !in_array($token, $generic, true);
    }

    private function token_weight($token) {
        $generic = array(
            'backpack', 'laptop', 'shoes', 'shoe', 'hoodie', 'keyboard', 'charger',
            'speaker', 'bottle', 'mug', 'jacket', 'case', 'wallet', 'clock', 'adapter',
            'tumbler', 'sleeve', 'footrest', 'organiser', 'organizer', 'travel', 'office',
            'black', 'white', 'grey', 'gray', 'blue', 'red', 'navy', 'olive', 'sage',
        );
        if (in_array($token, $generic, true)) {
            return 6;
        }
        if (strlen($token) >= 9) {
            return 16;
        }
        if (strlen($token) >= 6) {
            return 12;
        }
        return 7;
    }

    private function normalize($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = strtolower(remove_accents($value));
        $value = str_replace(array('–', '—', '_', '/'), array('-', '-', ' ', ' '), $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim((string) $value);
    }
}
