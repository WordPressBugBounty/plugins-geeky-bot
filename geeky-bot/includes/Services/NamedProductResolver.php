<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves explicit product/model names independently of prior chat results.
 *
 * It is intentionally conservative: broad category requests keep using normal
 * product search, while distinctive named requests resolve one visible product
 * or return a safe not-visible/unresolved result.
 */
final class NamedProductResolver {
    const MAX_CANDIDATES = 180;

    /**
     * Resolves direct requests such as "Show me the TrailGuard backpack".
     *
     * @param string $message Shopper message.
     * @return array
     */
    public function resolve_direct_request($message) {
        $message = $this->clean($message);
        if ($message === '' || !preg_match('/^(?:please\s+)?(?:show(?:\s+me)?|find|open|view|display|give(?:\s+me)?|tell(?:\s+me)?\s+about|can\s+i\s+see|i\s+want\s+to\s+see)\b/iu', $message)) {
            return array('handled' => false, 'status' => 'not_named');
        }

        $subject = preg_replace('/^(?:please\s+)?(?:show(?:\s+me)?|find|open|view|display|give(?:\s+me)?|tell(?:\s+me)?\s+about|can\s+i\s+see|i\s+want\s+to\s+see)\s+/iu', '', $message);
        $subject = preg_replace('/^(?:the|a|an)\s+/iu', '', trim((string) $subject));
        $subject = trim((string) $subject, " \t\n\r\0\x0B.?!");

        // Plural catalog/filter requests are discovery missions, not product
        // names. Without this guard, a request such as "show me products that
        // are in stock" can accidentally resolve a title containing "Stock"
        // and return one unavailable product.
        if ($subject === '' || $this->is_catalog_browse_subject($subject)) {
            return array('handled' => false, 'status' => 'broad_search');
        }

        if (empty($this->distinctive_tokens($subject))) {
            return array('handled' => false, 'status' => 'broad_search');
        }

        $result = $this->resolve_phrase($subject);
        $result['handled'] = in_array($result['status'], array('resolved', 'not_visible'), true);
        $result['subject'] = $subject;
        return $result;
    }

    /**
     * Resolves a bare, exact catalog title such as "QuietType Multi-Device Wireless Keyboard".
     *
     * This deliberately does not resolve broad family searches. A bare message
     * must match the complete visible product title, contain enough identity
     * information, and contain no commerce filters or recommendation language.
     *
     * @param string $message Shopper message.
     * @return array
     */
    public function resolve_bare_exact_name($message) {
        $subject = $this->subject_phrase($message);
        if ($subject === '') {
            return array('handled' => false, 'status' => 'not_named');
        }

        $normalized = $this->normalize($subject);
        if (empty($this->distinctive_tokens($subject))) {
            return array('handled' => false, 'status' => 'broad_search');
        }

        if (preg_match('/\b(?:show|find|search|recommend|suggest|compare|under|below|over|above|between|sale|discount|cheap|affordable|budget|best|size|colou?r|in\s+stock|only)\b/u', $normalized)) {
            return array('handled' => false, 'status' => 'filtered_search');
        }

        // Bare-name handling only accepts a complete catalog title. Use a
        // direct title lookup instead of scanning and hydrating many partially
        // matching products before normal Product Discovery begins.
        $result = $this->resolve_exact_title($subject);
        if (!in_array($result['status'], array('resolved', 'not_visible'), true) || empty($result['productName'])) {
            $result['handled'] = false;
            $result['subject'] = $subject;
            return $result;
        }

        if ($this->normalize($result['productName']) !== $normalized) {
            $result['handled'] = false;
            $result['status'] = 'not_exact';
            $result['subject'] = $subject;
            return $result;
        }

        $result['handled'] = true;
        $result['subject'] = $subject;
        return $result;
    }

    /**
     * Resolves explicit named products for comparison without using old results.
     *
     * @param string $message Shopper message.
     * @param array  $parts   Pre-parsed product-name parts.
     * @return array
     */
    public function resolve_comparison($message, $parts = array()) {
        $parts = $this->clean_comparison_parts($parts);
        if (count($parts) < 2) {
            $parts = $this->comparison_parts($message);
        }
        if (count($parts) < 2) {
            return array('handled' => false, 'resolved' => false, 'productIds' => array());
        }

        $ids = array();
        $resolved_names = array();
        $problems = array();

        foreach (array_slice($parts, 0, 4) as $part) {
            $result = $this->resolve_phrase($part);
            if ($result['status'] === 'resolved' && !empty($result['productId'])) {
                $product_id = absint($result['productId']);
                if (!in_array($product_id, $ids, true)) {
                    $ids[] = $product_id;
                    $resolved_names[] = !empty($result['productName']) ? $result['productName'] : $part;
                }
                continue;
            }

            $problems[] = array(
                'requested' => wp_strip_all_tags($part),
                'status' => sanitize_key((string) $result['status']),
                'candidates' => !empty($result['candidates']) ? (array) $result['candidates'] : array(),
            );
        }

        return array(
            'handled' => true,
            'resolved' => count($ids) >= 2 && empty($problems),
            'productIds' => array_slice($ids, 0, 4),
            'productNames' => array_slice($resolved_names, 0, 4),
            'problems' => $problems,
            'requestedNames' => $parts,
        );
    }


    /**
     * Resolves a complete product title with one database lookup.
     *
     * Hidden/private products are still passed through the central visibility
     * service and are never exposed as normal catalog matches.
     *
     * @param string $title Complete product title.
     * @return array
     */
    private function resolve_exact_title($title) {
        global $wpdb;

        $title = $this->subject_phrase($title);
        if ($title === '' || empty($wpdb)) {
            return $this->result('unresolved');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact-title resolution is bounded and must use the current product catalog.
        $product_id = absint($wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title = %s AND post_status <> 'trash' ORDER BY CASE WHEN post_status = 'publish' THEN 0 ELSE 1 END, ID ASC LIMIT 1",
            $title
        )));
        if (!$product_id || !function_exists('wc_get_product')) {
            return $this->result('unresolved');
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return $this->result('unresolved');
        }

        if (!CatalogVisibilityService::is_visible($product, 'named_resolution')) {
            return $this->result('not_visible', $product, array($this->candidate($product)));
        }

        return $this->result('resolved', $product);
    }

    /**
     * Resolves a single explicit product-name phrase.
     *
     * @param string $phrase Product name or distinctive partial name.
     * @return array
     */
    public function resolve_phrase($phrase) {
        $phrase = $this->subject_phrase($phrase);
        if ($phrase === '') {
            return $this->result('unresolved');
        }

        // Exact titles may consist entirely of short or generic catalog words,
        // for example "Black Zip Hoodie". Resolve those before applying the
        // conservative distinctive-token rules used for partial names.
        $exact_result = $this->resolve_exact_title($phrase);
        if (in_array($exact_result['status'], array('resolved', 'not_visible'), true)) {
            return $exact_result;
        }

        $query_tokens = $this->tokens($phrase);
        $identity_tokens = $this->distinctive_tokens($phrase);
        $generic_phrase = empty($identity_tokens);
        if ($generic_phrase && count($query_tokens) < 2) {
            return $this->result('unresolved');
        }
        $lookup_tokens = $generic_phrase ? $query_tokens : $identity_tokens;
        $normalized_phrase = $this->normalize($phrase);

        $scored = array();
        foreach ($this->candidate_ids($lookup_tokens) as $product_id) {
            $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
            if (!$product) {
                continue;
            }

            $title = $this->normalize($product->get_name());
            $title_tokens = $this->tokens($title);
            $title_identity = array_values(array_filter($title_tokens, array($this, 'is_distinctive_token')));
            if ($title === '' || empty($title_tokens)) {
                continue;
            }

            $exact = $title === $normalized_phrase;
            $phrase_in_title = strpos(' ' . $title . ' ', ' ' . $normalized_phrase . ' ') !== false;
            $title_in_phrase = strpos(' ' . $normalized_phrase . ' ', ' ' . $title . ' ') !== false;
            $identity_overlap = array_values(array_intersect($identity_tokens, $title_tokens));
            $generic_overlap = array_values(array_intersect($query_tokens, $title_tokens));

            $score = 0;
            if ($exact) {
                $score += 260;
            } elseif ($phrase_in_title || $title_in_phrase) {
                $score += 150;
            }
            $score += count($identity_overlap) * 55;
            $score += count($generic_overlap) * 9;
            if (count($identity_overlap) === count($identity_tokens)) {
                $score += 65;
            }
            if (!empty($title_identity) && empty($identity_overlap)) {
                $score -= 120;
            }
            if (!empty($title_tokens[0]) && in_array($title_tokens[0], $identity_overlap, true)) {
                $score += 28;
            }

            $strong = $exact
                || ($generic_phrase && ($phrase_in_title || $title_in_phrase) && count($generic_overlap) === count($query_tokens))
                || ($score >= 105 && count($identity_overlap) === count($identity_tokens))
                || ($score >= 130 && !empty($identity_overlap));
            if (!$strong) {
                continue;
            }

            $scored[] = array(
                'score' => $score,
                'exact' => $exact,
                'visible' => CatalogVisibilityService::is_visible($product, 'named_resolution'),
                'product' => $product,
            );
        }

        if (empty($scored)) {
            return $this->result('unresolved');
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                if ($a['visible'] !== $b['visible']) {
                    return $a['visible'] ? -1 : 1;
                }
                return $a['product']->get_id() <=> $b['product']->get_id();
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });

        $top = $scored[0];
        if (!$top['visible']) {
            return $this->result(
                'not_visible',
                $top['product'],
                array($this->candidate($top['product']))
            );
        }

        $visible = array_values(array_filter($scored, function ($item) {
            return !empty($item['visible']);
        }));
        $second = isset($visible[1]) ? $visible[1] : null;
        if ($second && abs($top['score'] - $second['score']) <= 4 && empty($top['exact'])) {
            return $this->result(
                'ambiguous',
                null,
                array($this->candidate($top['product']), $this->candidate($second['product']))
            );
        }

        return $this->result('resolved', $top['product']);
    }

    /**
     * Returns whether a direct "show/find" subject is clearly a catalog browse
     * or filter request rather than one product's name.
     *
     * @param string $subject Direct-request subject.
     * @return bool
     */
    private function is_catalog_browse_subject($subject) {
        $subject = $this->normalize($subject);
        if ($subject === '') {
            return false;
        }

        if (preg_match('/^(?:(?:all|some|any|available|current|latest|newest|sale|discounted)\s+)?(?:products?|items?|options?|catalog)\b/u', $subject)) {
            return true;
        }

        return (bool) preg_match(
            '/(?:\b(?:products?|items?|options?|catalog)\b.*\b(?:available|in\s+stock|out\s+of\s+stock|on\s+sale|discounted|rated)\b|\b(?:in\s+stock|out\s+of\s+stock|on\s+sale|highly\s+rated|top\s+rated|best\s+rated)\b)/u',
            $subject
        );
    }

    private function candidate_ids($identity_tokens) {
        $ids = array();
        global $wpdb;

        if (!empty($wpdb) && !empty($identity_tokens)) {
            $conditions = array();
            $args = array();
            foreach (array_slice($identity_tokens, 0, 5) as $token) {
                $conditions[] = 'post_title LIKE %s';
                $args[] = '%' . $wpdb->esc_like($token) . '%';
            }
            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND (" . implode(' OR ', $conditions) . ") ORDER BY post_date DESC LIMIT " . absint(self::MAX_CANDIDATES);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name and clauses are internal; all product-title terms use placeholders and the bounded product lookup must be current.
            $ids = array_map('absint', (array) $wpdb->get_col($wpdb->prepare($sql, $args)));
        }

        if (empty($ids) && function_exists('wc_get_products')) {
            $ids = (array) wc_get_products(array(
                'status' => 'publish',
                'limit' => self::MAX_CANDIDATES,
                'return' => 'ids',
                'orderby' => 'date',
                'order' => 'DESC',
            ));
        }

        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    private function comparison_parts($message) {
        $message = $this->normalize($message);
        $message = preg_replace('/^compare\s+/u', '', $message);
        return $this->clean_comparison_parts(preg_split('/\s+(?:and|with|vs\.?|versus)\s+/u', (string) $message));
    }

    private function clean_comparison_parts($parts) {
        $clean = array();
        foreach ((array) $parts as $part) {
            $part = $this->subject_phrase($part);
            if ($part !== '' && !in_array($part, $clean, true)) {
                $clean[] = $part;
            }
        }
        return array_slice($clean, 0, 4);
    }

    private function subject_phrase($phrase) {
        $phrase = $this->clean($phrase);
        $phrase = preg_replace('/^(?:compare|the|a|an)\s+/iu', '', $phrase);
        return trim((string) $phrase, " \t\n\r\0\x0B.?!");
    }

    private function result($status, $product = null, $candidates = array()) {
        return array(
            'handled' => false,
            'status' => sanitize_key((string) $status),
            'productId' => $product ? absint($product->get_id()) : 0,
            'productName' => $product ? wp_strip_all_tags($product->get_name()) : '',
            'product' => $product,
            'candidates' => array_values(array_filter((array) $candidates)),
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

    private function distinctive_tokens($value) {
        return array_values(array_filter($this->tokens($value), array($this, 'is_distinctive_token')));
    }

    private function tokens($value) {
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $this->normalize($value));
        $tokens = array();
        foreach (preg_split('/\s+/u', trim((string) $value)) as $token) {
            if ($token === '' || in_array($token, $this->stop_words(), true)) {
                continue;
            }
            $tokens[] = $token;
        }
        return array_values(array_unique($tokens));
    }

    private function is_distinctive_token($token) {
        return strlen((string) $token) >= 5 && !in_array($token, $this->generic_tokens(), true);
    }

    private function generic_tokens() {
        return array(
            'product', 'products', 'item', 'items', 'backpack', 'laptop', 'bag', 'bags',
            'shoes', 'shoe', 'sneakers', 'hoodie', 'keyboard', 'charger', 'speaker',
            'bottle', 'mug', 'jacket', 'case', 'wallet', 'clock', 'adapter', 'tumbler',
            'sleeve', 'footrest', 'organiser', 'organizer', 'travel', 'office', 'black',
            'wireless', 'bluetooth', 'water', 'waterproof', 'resistant', 'running', 'walking',
            'comfortable', 'comfort', 'premium', 'budget', 'insulated', 'lightweight', 'fleece',
            'leather', 'gaming', 'sports', 'white', 'grey', 'gray', 'blue', 'red', 'navy', 'olive', 'sage', 'small',
            'medium', 'large', 'size', 'color', 'colour', 'available', 'current',
        );
    }

    private function stop_words() {
        return array(
            'the', 'a', 'an', 'me', 'my', 'please', 'show', 'find', 'open', 'view',
            'display', 'give', 'tell', 'about', 'compare', 'and', 'with', 'versus',
            'vs', 'to', 'for', 'of', 'in', 'on', 'is', 'are', 'this', 'that',
        );
    }

    private function clean($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim((string) $value);
    }

    private function normalize($value) {
        $value = strtolower(remove_accents($this->clean($value)));
        $value = str_replace(array('–', '—', '_', '/'), array('-', '-', ' ', ' '), $value);
        $value = preg_replace('/[^a-z0-9\-\s]+/u', ' ', $value);
        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }
}
