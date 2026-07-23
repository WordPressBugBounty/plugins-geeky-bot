<?php
namespace GeekyBot\Services;

use GeekyBot\Search\BuyerIntentLibrary;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language-aware shopper query helper.
 *
 * This class keeps product search local and fast while avoiding an English-only
 * stop-word list. It is intentionally deterministic: no AI calls, no remote data,
 * and all arrays are filterable by store owners/add-ons.
 */
class SearchLanguageService {
    private $buyer_intent_library = null;
    private $keyword_pattern_cache = array();
    private $intent_ignore_cache = array();
    private $stop_words_cache = array();

    public function buyer_intent_profile($query) {
        return $this->buyer_intent_library()->profile($query);
    }

    public function remove_buyer_intent_tokens($terms, $profile) {
        return $this->buyer_intent_library()->remove_ignored_tokens($terms, is_array($profile) ? $profile : array());
    }

    private function buyer_intent_library() {
        if ($this->buyer_intent_library === null) {
            $this->buyer_intent_library = new BuyerIntentLibrary($this);
        }
        return $this->buyer_intent_library;
    }

    public function normalize_text($text) {
        $text = wp_strip_all_tags((string) $text);
        static $charset = null;
        if ($charset === null) {
            $charset = (string) get_bloginfo('charset');
            if ($charset === '') {
                $charset = 'UTF-8';
            }
        }
        $text = html_entity_decode($text, ENT_QUOTES, $charset);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        // Expand common English contractions before punctuation removal so
        // the apostrophe in "it's" cannot leave a standalone "s" token that
        // is later mistaken for clothing size S.
        $text = str_replace(array('’', '`'), "'", $text);
        $text = preg_replace("/\b(it|that|this|what|there|here|who|how|where|when|why)'s\b/u", '$1 is', $text);
        $text = $this->normalize_arabic_family_chars($text);
        $text = remove_accents($text);

        // Shopper-facing compound words must normalize consistently. Treat a
        // hyphen between letters/numbers as a word boundary so `in-stock`,
        // `water-resistant`, `red-white`, `size-42`, and their space-separated
        // forms produce the same search tokens. Product index text uses this
        // same normalizer, so USB-C and similar catalog values remain aligned.
        $text = preg_replace('/(?<=[\pL\pN])-(?=[\pL\pN])/u', ' ', $text);
        $text = preg_replace('/[^\pL\pN_\-\.\s\$£€₹₨¥₩₺]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim($text);
    }

    public function language_code($query = '') {
        $query = (string) $query;
        $site_locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $site_code = strtolower(substr((string) $site_locale, 0, 2));

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $query)) {
            if (preg_match('/[پچژکگیےھں]/u', $query)) {
                return 'ur';
            }
            if (preg_match('/[گچپژک]/u', $query)) {
                return 'fa';
            }
            return 'ar';
        }
        if (preg_match('/[\x{0900}-\x{097F}]/u', $query)) {
            return 'hi';
        }
        if (preg_match('/[\x{3040}-\x{30FF}]/u', $query)) {
            return 'ja';
        }
        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $query)) {
            return 'ko';
        }
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $query)) {
            return 'zh';
        }
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $query)) {
            return 'ru';
        }

        return $site_code ? $site_code : 'en';
    }

    public function query_terms($query) {
        $normalized = $this->normalize_text($query);
        if ($normalized === '') {
            return array();
        }

        $language = $this->language_code($normalized);
        $stop = $this->stop_words($language);
        $terms = array();

        preg_match_all('/[\pL\pN][\pL\pN_\-\.]{0,60}/u', $normalized, $matches);
        foreach ((array) $matches[0] as $part) {
            $part = trim($part, " \t\n\r\0\x0B.-_");
            if ($part === '' || in_array($part, $stop, true)) {
                continue;
            }

            $part = $this->normalize_search_token($part, $language);
            if ($part === '' || in_array($part, $stop, true) || $this->is_weak_shopper_token($part)) {
                continue;
            }
            if ($this->token_length($part) < 2 && !preg_match('/^\d+$/', $part)) {
                continue;
            }
            $terms[] = $part;
        }

        // For CJK queries without spaces, keep the full phrase and useful 2-char chunks.
        if (in_array($language, array('zh', 'ja', 'ko'), true) && $this->token_length($normalized) > 2 && strpos($normalized, ' ') === false) {
            $terms[] = $normalized;
            if (function_exists('mb_substr') && function_exists('mb_strlen')) {
                $len = mb_strlen($normalized, 'UTF-8');
                for ($i = 0; $i < $len - 1; $i++) {
                    $terms[] = mb_substr($normalized, $i, 2, 'UTF-8');
                }
            }
        }

        /**
         * Filters final product-search query terms.
         *
         * @param array  $terms      Search terms after normalization/stop-word removal.
         * @param string $normalized Normalized shopper query.
         * @param string $language   Detected language code.
         */
        $terms = (array) apply_filters('geekybot_search_query_terms', $terms, $normalized, $language);
        return array_values(array_unique(array_filter($terms)));
    }


    public function product_facets_from_query($query, $terms = array()) {
        $query = $this->normalize_text($query);
        $token_text = ' ' . implode(' ', array_unique(array_merge($this->query_terms($query), (array) $terms))) . ' ';

        $colors = array();
        foreach ($this->color_keyword_map() as $canonical => $aliases) {
            foreach ((array) $aliases as $alias) {
                $alias = $this->normalize_text($alias);
                if ($alias === '') {
                    continue;
                }
                if ($this->contains_phrase($query, $alias) || strpos($token_text, ' ' . $alias . ' ') !== false) {
                    $colors[] = $canonical;
                    $colors = array_merge($colors, (array) $aliases);
                    break;
                }
            }
        }

        $sizes = array();
        foreach ($this->size_keyword_map() as $canonical => $aliases) {
            foreach ((array) $aliases as $alias) {
                $alias = $this->normalize_text($alias);
                if ($alias === '') {
                    continue;
                }
                if ($this->contains_phrase($query, $alias) || strpos($token_text, ' ' . $alias . ' ') !== false) {
                    $sizes[] = $canonical;
                    $sizes = array_merge($sizes, (array) $aliases);
                    break;
                }
            }
        }

        if (preg_match_all('/(?:size|sizes|sized|uk|us|eu|سائز|مقاس|taille|größe|grösse|talla)\s*(?:of\s+)?[:#-]?\s*([0-9]{1,3}(?:\.[0-9])?|xxxs|xxs|xs|s|m|l|xl|xxl|xxxl|small|medium|large|one\s*size)/u', $query, $matches)) {
            foreach ((array) $matches[1] as $size) {
                $sizes = array_merge($sizes, $this->facet_terms_for_index(array($size), 'size'));
            }
        }
        if (preg_match_all('/\b([0-9]{1,3}(?:\.[0-9])?)\s*(?:size|sizes|uk|us|eu)\b/u', $query, $matches)) {
            foreach ((array) $matches[1] as $size) {
                $sizes[] = $size;
            }
        }

        /**
         * Filters structured search facets parsed from a shopper query.
         *
         * @param array  $facets Parsed facets with colors and sizes.
         * @param string $query  Normalized shopper query.
         * @param array  $terms  Search terms.
         */
        $facets = apply_filters('geekybot_search_facets', array(
            'colors' => $this->normalize_facet_term_list($colors),
            'sizes' => $this->normalize_facet_term_list($sizes),
        ), $query, (array) $terms);

        return is_array($facets) ? $facets : array('colors' => array(), 'sizes' => array());
    }

    public function facet_terms_for_index($values, $type) {
        $terms = array();
        $map = $type === 'color' ? $this->color_keyword_map() : $this->size_keyword_map();

        foreach ((array) $values as $value) {
            $value = $this->normalize_text($value);
            if ($value === '') {
                continue;
            }
            $terms[] = $value;
            foreach ($this->query_terms($value) as $token) {
                $terms[] = $token;
            }
            foreach ($map as $canonical => $aliases) {
                foreach ((array) $aliases as $alias) {
                    $alias = $this->normalize_text($alias);
                    if ($alias !== '' && ($value === $alias || $this->contains_phrase($value, $alias))) {
                        $terms[] = $canonical;
                        $terms = array_merge($terms, (array) $aliases);
                        break 2;
                    }
                }
            }
        }

        return $this->normalize_facet_term_list($terms);
    }

    public function is_color_attribute_label($label) {
        $label = $this->normalize_text($label);
        return (bool) preg_match('/(?:^|\s)(?:pa_)?(?:color|colour|رنگ|لون|couleur|farbe|talla-color)(?:\s|$)/u', $label)
            || strpos($label, 'pa_color') !== false
            || strpos($label, 'pa_colour') !== false;
    }

    public function is_size_attribute_label($label) {
        $label = $this->normalize_text($label);
        return (bool) preg_match('/(?:^|\s)(?:pa_)?(?:size|sizes|sizing|سائز|مقاس|taille|größe|grösse|talla|grosse)(?:\s|$)/u', $label)
            || strpos($label, 'pa_size') !== false;
    }

    public function price_range_from_query($query) {
        $query = trim($this->normalize_text($query), " \t\n\r\0\x0B.,!?;:");
        $money = '(?:\$|£|€|₹|₨|¥|₩|₺|rs\.?|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try)?\s*([0-9][0-9,]*(?:\.[0-9]+)?)';
        $currency_money = '(?:\$|£|€|₹|₨|¥|₩|₺|rs\.?|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try)\s*([0-9][0-9,]*(?:\.[0-9]+)?)';

        $between = $this->keyword_pattern($this->between_keywords());
        $under = $this->keyword_pattern($this->under_keywords());
        $over = $this->keyword_pattern($this->over_keywords());
        $around = $this->keyword_pattern($this->around_keywords());
        $budget = $this->keyword_pattern($this->budget_keywords());
        $target = $this->keyword_pattern($this->price_target_keywords());
        $and_to = $this->keyword_pattern(array('and', 'to', '-', 'اور', 'سے', 'تا', 'الى', 'إلى', 'و', 'y', 'a', 'et', 'bis', 'e', 'ile'));

        // Compound comparison phrases must be checked before generic "below"
        // and "above" keywords so "not below 25" means a minimum, not a maximum.
        if (preg_match('/(?:^|\s)(?:not below|at least|no less than)\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => $this->parse_price_number($m[1]), 'max' => null, 'mode' => 'min');
        }
        if (preg_match('/(?:^|\s)(?:not above|at most|no more than)\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }

        if (preg_match('/(?:^|\s)(?:' . $between . ')\s+' . $money . '\s*(?:' . $and_to . ')\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => $this->parse_price_number($m[1]), 'max' => $this->parse_price_number($m[2]), 'mode' => 'range');
        }
        if (preg_match('/(?:^|\s)(?:' . $under . ')\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)(?:' . $over . ')\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => $this->parse_price_number($m[1]), 'max' => null, 'mode' => 'min');
        }

        // Natural shopper phrases: "with price of 35", "price 35", "budget 35", "for $35".
        // These usually mean a budget/maximum in commerce search, not a keyword.
        if (preg_match('/(?:^|\s)(?:' . $budget . ')\s*(?:of\s+)?' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)(?:with\s+)?(?:' . $target . ')\s*(?:of\s+|at\s+|is\s+)?' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)(?:for\s+)' . $currency_money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)' . $currency_money . '\s*(?:budget|price|dollar|dollars|rs|pkr)?(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)(?:' . $around . ')\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            $target_price = $this->parse_price_number($m[1]);
            $padding = max(5, $target_price * 0.15);
            return array('min' => max(0, $target_price - $padding), 'max' => $target_price + $padding, 'mode' => 'around', 'target' => $target_price);
        }

        return null;
    }

    public function strip_price_filters($query) {
        $query = trim($this->normalize_text($query), " \t\n\r\0\x0B.,!?;:");
        $money = '(?:\$|£|€|₹|₨|¥|₩|₺|rs\.?|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try)?\s*[0-9][0-9,]*(?:\.[0-9]+)?';
        $currency_money = '(?:\$|£|€|₹|₨|¥|₩|₺|rs\.?|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try)\s*[0-9][0-9,]*(?:\.[0-9]+)?';
        $between = $this->keyword_pattern($this->between_keywords());
        $under = $this->keyword_pattern($this->under_keywords());
        $over = $this->keyword_pattern($this->over_keywords());
        $around = $this->keyword_pattern($this->around_keywords());
        $budget = $this->keyword_pattern($this->budget_keywords());
        $target = $this->keyword_pattern($this->price_target_keywords());
        $and_to = $this->keyword_pattern(array('and', 'to', '-', 'اور', 'سے', 'تا', 'الى', 'إلى', 'و', 'y', 'a', 'et', 'bis', 'e', 'ile'));

        $patterns = array(
            '/(?:^|\s)(?:not below|at least|no less than)\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:not above|at most|no more than)\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $between . ')\s+' . $money . '\s*(?:' . $and_to . ')\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $under . ')\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $over . ')\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $budget . ')\s*(?:of\s+)?' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:with\s+)?(?:' . $target . ')\s*(?:of\s+|at\s+|is\s+)?' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:for\s+)' . $currency_money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $around . ')\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)' . $currency_money . '\s*(?:budget|price|dollar|dollars|rs|pkr)?(?=\s|$)/u',
        );

        $query = preg_replace($patterns, ' ', $query);
        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    public function strip_commerce_phrases($query) {
        $query = $this->normalize_text($query);
        $query = $this->strip_recommendation_scaffolding($query);
        $phrases = array(
            'do you have', 'do u have', 'do have', 'have you got', 'have got', 'is there', 'are there',
            'i am looking for', 'im looking for', 'i am searching for', 'i need', 'i want', 'looking for',
            'can you please find', 'could you please find', 'please find', 'help me find', 'can you find',
            'can you show me', 'show me', 'give me', 'find me', 'find', 'search for', 'suggest me', 'recommend me', 'can you recommend',
            'what do you recommend', 'what would you recommend', 'which one should i buy', 'i am not sure what to buy',
            'available for sale', 'for sale', 'in stock', 'available', 'available ones', 'ready to ship', 'current catalog',
            'کیا اپ کے پاس', 'کیا آپ کے پاس', 'مجھے چاہیے', 'مجھے چاہیئے', 'دکھاو', 'دکھاؤ', 'تلاش کرو',
            'هل لديك', 'اريد', 'أريد', 'اعرض لي', 'اظهر لي',
            'estoy buscando', 'busco', 'muéstrame', 'muestrame', 'quiero',
            'je cherche', 'montre moi', 'je veux',
            'ich suche', 'zeige mir', 'ich mochte', 'ich möchte'
        );

        /**
         * Filters conversational shopping phrases removed before product matching.
         *
         * @param array  $phrases Phrases that express intent but are not product terms.
         * @param string $query   Normalized shopper query.
         */
        $phrases = (array) apply_filters('geekybot_search_commerce_phrases', $phrases, $query);
        foreach ($phrases as $phrase) {
            $phrase = $this->normalize_text($phrase);
            if ($phrase === '') {
                continue;
            }
            $query = preg_replace('/(?:^|\s)' . preg_quote($phrase, '/') . '(?=\s|$)/u', ' ', $query);
        }

        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    /**
     * Removes recommendation-request scaffolding while preserving the actual
     * product family, facets, price limits, and shopper preferences.
     *
     * This runs before catalog term extraction so natural requests such as
     * "recommend an affordable speaker" and "could you suggest a rain
     * jacket under $80" do not turn recommend/suggest into product identity.
     * Product Expert questions are routed before Product Discovery, so factual
     * prompts such as "which color do you recommend" remain unaffected.
     */
    private function strip_recommendation_scaffolding($query) {
        $query = $this->normalize_text($query);
        if ($query === '') {
            return '';
        }

        $patterns = array(
            // Leading imperative and polite recommendation requests.
            '/^(?:please\s+)?(?:can|could|would|will)\s+you\s+(?:please\s+)?(?:recommend|suggest)\s+(?:me\s+)?/u',
            '/^(?:please\s+)?(?:recommend|suggest)\s+(?:me\s+)?/u',
            // Noun-shaped requests such as "give me a recommendation for...".
            '/^(?:please\s+)?(?:give|show)\s+me\s+(?:(?:your|the|a|some)\s+)?(?:best\s+)?(?:recommendations?|suggestions?)\s*(?:for|on|about)?\s*/u',
            '/^(?:what|which)\s+(?:is|are)\s+(?:your|the)\s+(?:best\s+)?(?:recommendations?|suggestions?)\s*(?:for|on|about)?\s*/u',
            // Trailing decision clauses preserve the product words before them.
            '/\b(?:do|would|can|could|will|should)\s+you\s+(?:recommend|suggest)\b/u',
            '/\b(?:would|do|should)\s+i\s+(?:buy|choose|pick)\b/u',
        );

        $query = preg_replace($patterns, ' ', $query);
        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    public function remove_intent_terms($terms, $intent = 'search') {
        $terms = array_values(array_unique(array_filter((array) $terms)));
        if (empty($terms)) {
            return array();
        }

        $intent = sanitize_key((string) $intent);
        $cache_key = $intent !== '' ? $intent : 'search';
        if (!isset($this->intent_ignore_cache[$cache_key])) {
            $ignore_phrases = array_merge(
                $this->product_keywords(),
                $this->latest_keywords(),
                $this->top_rated_keywords(),
                $this->popular_keywords(),
                $this->under_keywords(),
                $this->over_keywords(),
                $this->between_keywords(),
                $this->around_keywords(),
                $this->budget_keywords(),
                $this->price_target_keywords(),
                array('sale', 'sales', 'discount', 'discounted', 'discounts', 'deal', 'deals', 'offer', 'offers', 'for sale', 'on sale')
            );

            if ($intent === 'sale') {
                $ignore_phrases = array_merge($ignore_phrases, $this->sale_keywords());
            }

            $ignore = array();
            foreach ($ignore_phrases as $phrase) {
                foreach ($this->query_terms($phrase) as $token) {
                    $ignore[$token] = true;
                }
            }
            $this->intent_ignore_cache[$cache_key] = $ignore;
        }
        $ignore = $this->intent_ignore_cache[$cache_key];

        $filtered = array();
        foreach ($terms as $term) {
            $term = $this->normalize_search_token($term, $this->language_code($term));
            if ($term === '' || isset($ignore[$term])) {
                continue;
            }
            $filtered[] = $term;
        }

        return array_values(array_unique($filtered));
    }

    public function catalog_intent($query) {
        $query = $this->normalize_text($query);

        // In shopper language, "belt for sale" usually means availability, not a discounted sale.
        if ($this->contains_any_phrase($query, array('for sale', 'available for sale'))) {
            return 'search';
        }

        if ($this->contains_any_phrase($query, $this->latest_keywords())) {
            return 'latest';
        }
        if ($this->contains_any_phrase($query, $this->sale_keywords())) {
            return 'sale';
        }
        if ($this->contains_any_phrase($query, $this->top_rated_keywords())) {
            return 'top_rated';
        }
        if ($this->contains_any_phrase($query, $this->popular_keywords())) {
            return 'popular';
        }
        return 'search';
    }

    public function is_in_stock_query($query) {
        $query = $this->normalize_text($query);
        // normalize_text deliberately preserves periods for decimal prices. A
        // shopper sentence such as “Only in stock.” must still be recognized as
        // a stock filter, so ignore sentence-ending punctuation for this check.
        $query = rtrim($query, " \t\n\r\0\x0B.,!?;:");

        if (preg_match('/(?:only\s+)?(?:available|in\s*stock|instock|ready\s+to\s+ship)(?:\s|$)/u', $query)) {
            return true;
        }

        if (preg_match('/(?:not|no|dont|don\s+t|do\s+not|exclude|excluding|avoid)\s+(?:want\s+)?(?:any\s+)?(?:out[-\s]?of[-\s]?stock|unavailable)/u', $query)) {
            return true;
        }

        return $this->contains_any_phrase($query, array(
            'in stock', 'available', 'available ones', 'only available', 'currently available', 'actually in stock', 'ready to ship', 'instock', 'for sale', 'available for sale',
            'not out of stock', 'not out-of-stock', 'not outofstock', 'do not want out of stock', 'do not want out-of-stock', 'dont want out of stock', 'dont want out-of-stock', 'don t want out of stock', 'don t want out-of-stock', "don't want out of stock", "don't want out-of-stock", 'exclude out of stock', 'exclude out-of-stock', 'no out of stock', 'no out-of-stock',
            'دستیاب', 'موجود', 'اسٹاک', 'سٹاک', 'متوفر', 'في المخزون', 'متوفره',
            'disponible', 'en stock', 'auf lager', 'disponibile', 'em estoque', 'stokta', 'stok tersedia',
        ));
    }

    public function contains_value_signal($query) {
        $query = $this->normalize_text($query);
        return $this->contains_any_phrase($query, array(
            'best value', 'good value', 'value for money', 'balanced choice', 'safe choice', 'safest choice', 'worth buying', 'not cheapest', 'not the cheapest', 'not lowest', 'not the lowest', 'not just cheap', 'not only cheap', 'not the lowest price'
        ));
    }

    public function is_product_question($message) {
        $message = $this->normalize_text($message);
        $needles = array_merge(
            $this->product_keywords(),
            $this->under_keywords(),
            $this->over_keywords(),
            $this->between_keywords(),
            $this->sale_keywords(),
            $this->latest_keywords(),
            $this->popular_keywords(),
            $this->top_rated_keywords()
        );
        if ($this->contains_any_phrase($message, $needles)) {
            return true;
        }
        $terms = $this->query_terms($message);
        return count($terms) > 0 && count($terms) <= 5;
    }

    public function contains_budget_signal($query) {
        $query = $this->normalize_text($query);
        if ($this->contains_any_phrase($query, array('not cheapest', 'not the cheapest', 'not lowest', 'not the lowest', 'not cheap option', 'not the cheap option'))) {
            return false;
        }

        $signals = array_merge(
            array('cheap', 'cheapest', 'affordable', 'low price', 'low priced', 'budget friendly', 'budget-friendly', 'not expensive', 'not too expensive', 'reasonable price', 'good price', 'value for money'),
            $this->budget_keywords(),
            array('سستا', 'سستی', 'کم قیمت', 'رخيص', 'اقتصادي', 'barato', 'abordable', 'pas cher', 'günstig', 'gunstig', 'economico', 'económico')
        );

        return $this->contains_any_phrase($query, $signals);
    }

    public function negated_price_terms_from_query($query) {
        $query = $this->normalize_text($query);
        if (!$this->contains_any_phrase($query, array(
            'not expensive', 'not too expensive', 'not costly', 'not too costly', 'not high price', 'not high priced',
            'not pricey', 'not too pricey', 'not premium price', 'not luxury price',
        ))) {
            return array();
        }

        return array('expensive', 'costly', 'pricey', 'premium', 'luxury', 'high', 'higher');
    }

    public function expand_synonyms($query) {
        $query = $this->normalize_text($query);
        $synonyms = array(
            'cheap' => array('budget', 'affordable', 'low price'),
            'cheaper' => array('budget', 'affordable', 'low price'),
            'not expensive' => array('budget affordable low price'),
            'not too expensive' => array('budget affordable low price'),
            'reasonable price' => array('affordable budget value'),
            'affordable' => array('budget', 'cheap'),
            'expensive' => array('premium', 'luxury'),
            'popular' => array('best selling', 'top rated'),
            'hoodies' => array('hoodie'),
            'hoody' => array('hoodie'),
            'hoddie' => array('hoodie'),
            'hoddies' => array('hoodie hoodies'),
            'hoodys' => array('hoodie hoodies'),
            'recomend' => array('recommend'),
            'reccomend' => array('recommend'),
            'shrit' => array('shirt'),
            'shrits' => array('shirt shirts'),
            'shoos' => array('shoe shoes'),
            'footwear' => array('shoe shoes sneaker trainer'),
            'jogger' => array('shoe sneaker trainer running'),
            'joggers' => array('shoe sneaker trainer running'),
            'new' => array('latest', 'newest'),
            'colour' => array('color'),
            'grey' => array('gray'),
            'gray' => array('grey'),
            'navy' => array('blue dark blue'),
            'cream' => array('beige off white'),
            'offwhite' => array('off white cream'),
            'off-white' => array('off white cream'),
            // Size acronyms are handled by product_facets_from_query().
            // Do not expand xl/xs into ordinary words here, otherwise "hoodie xl"
            // can leave "extra" behind as a fake product term.
            'trainer' => array('sneaker', 'shoe'),
            'trainers' => array('sneaker', 'shoe'),
            'sneaker' => array('trainer', 'shoe'),
            'sneakers' => array('trainer', 'shoe'),
            'tshirt' => array('t shirt', 'tee'),
            'tee' => array('t shirt', 'tshirt'),
            'belts' => array('belt'),
            'shoes' => array('shoe footwear'),
            'shirts' => array('shirt'),
            'mens' => array('men male'),
            'men' => array('mens male'),
            'womens' => array('women ladies female'),
            'ladies' => array('women female'),
            'kids' => array('kid children child'),
            'caps' => array('cap'),
            'hats' => array('hat cap'),
            'سستا' => array('cheap affordable budget'),
            'سستی' => array('cheap affordable budget'),
            'مہنگا' => array('premium luxury'),
            'رنگ' => array('color colour'),
            'قیمت' => array('price cost'),
            'حذاء' => array('shoe shoes'),
            'رخيص' => array('cheap affordable budget'),
            'لون' => array('color colour'),
            'precio' => array('price cost'),
            'barato' => array('cheap affordable budget'),
            'taille' => array('size'),
            'couleur' => array('color colour'),
            'größe' => array('size'),
            'farbe' => array('color colour'),
        );

        $synonyms = array_merge($synonyms, $this->custom_synonyms_from_settings());

        /**
         * Filter multilingual product-search synonyms.
         * Format: array('shopper word' => array('catalog word', 'another word')).
         */
        $synonyms = (array) apply_filters('geekybot_search_synonyms', $synonyms);
        $expanded = $query;
        foreach ($synonyms as $word => $alts) {
            $word = $this->normalize_text($word);
            if ($word === '') {
                continue;
            }
            if ($word === 'expensive' && $this->contains_any_phrase($query, array('not expensive', 'not too expensive'))) {
                continue;
            }
            if ($this->contains_phrase($query, $word)) {
                $expanded .= ' ' . implode(' ', (array) $alts);
            }
        }
        return $expanded;
    }

    private function custom_synonyms_from_settings() {
        $raw = class_exists(__NAMESPACE__ . '\\Settings') ? Settings::get('search_custom_synonyms', '') : '';
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }

        $items = array();
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = preg_split('/\s*(?:=>|=|:)\s*/', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $word = $this->normalize_text($parts[0]);
            $alts_raw = trim((string) $parts[1]);
            if ($word === '' || $alts_raw === '') {
                continue;
            }

            $alts = array();
            foreach (preg_split('/\s*[,|]\s*/', $alts_raw) as $alt) {
                $alt = $this->normalize_text($alt);
                if ($alt !== '') {
                    $alts[] = $alt;
                }
            }

            if (!empty($alts)) {
                $items[$word] = array_values(array_unique($alts));
            }
        }

        return $items;
    }


    public function negative_facets_from_query($query) {
        $query = $this->normalize_text($query);

        // Negative facet parsing is comparatively expensive because it must
        // consider multilingual operators and aliases. Most shopper searches
        // contain no exclusion at all, so leave immediately unless a complete
        // negative operator token/phrase is present.
        if (!$this->has_negative_facet_signal($query)) {
            $facets = apply_filters('geekybot_search_negative_facets', array(
                'colors' => array(),
                'sizes' => array(),
            ), $query);
            return is_array($facets) ? $facets : array('colors' => array(), 'sizes' => array());
        }

        $colors = $this->negative_terms_for_map($query, $this->color_keyword_map());
        $sizes = $this->negative_terms_for_map($query, $this->size_keyword_map());

        /**
         * Filters negative facets parsed from a shopper query, such as "not black".
         *
         * @param array  $facets Negative facets with colors and sizes.
         * @param string $query  Normalized shopper query.
         */
        $facets = apply_filters('geekybot_search_negative_facets', array(
            'colors' => $this->normalize_facet_term_list($colors),
            'sizes' => $this->normalize_facet_term_list($sizes),
        ), $query);

        return is_array($facets) ? $facets : array('colors' => array(), 'sizes' => array());
    }

    public function buyer_modifier_terms($query) {
        $query = $this->normalize_text($query);
        $profile = $this->buyer_intent_profile($query);
        $terms = !empty($profile['modifier_terms']) ? (array) $profile['modifier_terms'] : array();

        // The versioned buyer library returns compact canonical terms for
        // supported shopper language. Keep the legacy maps only as a fallback
        // for languages/phrases not yet represented by the local rule pack.
        if (empty($terms)) {
            foreach (array_merge($this->buyer_preference_keyword_map(), $this->buyer_use_case_keyword_map()) as $canonical => $aliases) {
                foreach ((array) $aliases as $alias) {
                    $alias = $this->normalize_text($alias);
                    if ($alias === '') {
                        continue;
                    }
                    if ($this->contains_phrase($query, $alias)) {
                        $terms[] = $canonical;
                        $terms = array_merge($terms, $this->query_terms($alias));
                        foreach ((array) $aliases as $group_alias) {
                            foreach ($this->query_terms($group_alias) as $token) {
                                $terms[] = $token;
                            }
                        }
                        break;
                    }
                }
            }
        }

        /**
         * Filters soft buyer-intent terms that should boost matching products but
         * should not be required as hard catalog terms.
         *
         * @param array  $terms Soft intent terms.
         * @param string $query Normalized shopper query.
         */
        $terms = (array) apply_filters('geekybot_search_buyer_modifier_terms', $terms, $query);
        return $this->normalize_facet_term_list($terms);
    }

    public function buyer_modifier_labels($query) {
        $query = $this->normalize_text($query);
        $profile = $this->buyer_intent_profile($query);
        $labels = !empty($profile['modifier_labels']) ? (array) $profile['modifier_labels'] : array();
        $label_map = array(
            'comfortable' => __('comfort', 'geeky-bot'),
            'formal' => __('formal', 'geeky-bot'),
            'casual' => __('casual', 'geeky-bot'),
            'premium' => __('premium quality', 'geeky-bot'),
            'budget' => __('budget-friendly', 'geeky-bot'),
            'gift' => __('gift', 'geeky-bot'),
            'popular' => __('popular', 'geeky-bot'),
            'useful' => __('useful', 'geeky-bot'),
            'simple' => __('simple style', 'geeky-bot'),
            'quality' => __('quality', 'geeky-bot'),
            'value' => __('best value', 'geeky-bot'),
            'winter' => __('winter', 'geeky-bot'),
            'summer' => __('summer', 'geeky-bot'),
            'sports' => __('sports/walking', 'geeky-bot'),
            'travel' => __('travel', 'geeky-bot'),
            'party' => __('party/event', 'geeky-bot'),
            'school' => __('school/college', 'geeky-bot'),
        );

        if (empty($labels)) {
            foreach (array_merge($this->buyer_preference_keyword_map(), $this->buyer_use_case_keyword_map()) as $canonical => $aliases) {
                foreach ((array) $aliases as $alias) {
                    $alias = $this->normalize_text($alias);
                    if ($alias !== '' && $this->contains_phrase($query, $alias)) {
                        $labels[] = isset($label_map[$canonical]) ? $label_map[$canonical] : $canonical;
                        break;
                    }
                }
            }
        }

        $labels = (array) apply_filters('geekybot_search_buyer_modifier_labels', $labels, $query);
        return array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $labels))));
    }

    public function strip_buyer_modifier_phrases($query) {
        $query = $this->normalize_text($query);
        $profile = $this->buyer_intent_profile($query);
        $query = $this->buyer_intent_library()->strip_phrases($query, $profile);
        $phrases = array(
            'better color', 'preferred color', 'color preference', 'better colours', 'better colors',
            'good color', 'nice color', 'best color', 'color of', 'colour of', 'color', 'colour',
            'size of', 'size', 'sizes', 'for me', 'for someone', 'as a gift', 'gift for my brother', 'gift for brother', 'for my brother', 'for brother', 'my brother', 'gift for my sister', 'for my sister', 'my sister', 'gift for',
            'maybe', 'perhaps', 'possibly', 'if possible', 'possible', 'something', 'option', 'options',
            'that are', 'that is', 'which are', 'which is', 'only show', 'show only', 'this category', 'that category', 'category', 'categories',
            'actually', 'currently', 'right now', 'available now', 'current', 'still', 'look', 'looks', 'that still look',
            'not expensive', 'not too expensive', 'too expensive', 'expensive', 'costly', 'pricey', 'luxury', 'high price', 'high priced', 'reasonable price', 'good price', 'value for money',
            'best value', 'good value', 'not cheapest', 'not the cheapest', 'not lowest', 'not the lowest', 'the cheapest one', 'cheapest one', 'lowest price', 'safe choice', 'safest choice',
            'good quality', 'quality', 'good for', 'good', 'nice', 'useful', 'popular', 'simple',
            'do not want out of stock', 'do not want out-of-stock', 'don t want out of stock', 'don t want out-of-stock', 'dont want out of stock', 'dont want out-of-stock', 'only available ones', 'available ones', 'out of stock', 'out-of-stock', 'outofstock',
        );

        foreach (array_merge($this->buyer_preference_keyword_map(), $this->buyer_use_case_keyword_map()) as $aliases) {
            foreach ((array) $aliases as $alias) {
                $phrases[] = $alias;
            }
        }

        $phrases = (array) apply_filters('geekybot_search_strip_buyer_modifier_phrases', $phrases, $query);
        $normalized_phrases = array();
        foreach ($phrases as $phrase) {
            $phrase = $this->normalize_text($phrase);
            if ($phrase !== '') {
                $normalized_phrases[] = $phrase;
            }
        }
        $normalized_phrases = array_values(array_unique($normalized_phrases));
        usort($normalized_phrases, function ($a, $b) {
            $la = function_exists('mb_strlen') ? mb_strlen($a, 'UTF-8') : strlen($a);
            $lb = function_exists('mb_strlen') ? mb_strlen($b, 'UTF-8') : strlen($b);
            if ($la === $lb) {
                return 0;
            }
            return $la > $lb ? -1 : 1;
        });
        foreach ($normalized_phrases as $phrase) {
            $query = preg_replace('/(?:^|\s)' . preg_quote($phrase, '/') . '(?=\s|$)/u', ' ', $query);
        }

        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    public function strip_negative_facets($query) {
        $query = $this->normalize_text($query);
        if (!$this->has_negative_facet_signal($query)) {
            return $query;
        }

        $operators = $this->negative_operator_keywords();
        $all_aliases = array();
        foreach (array_merge($this->color_keyword_map(), $this->size_keyword_map()) as $aliases) {
            foreach ((array) $aliases as $alias) {
                $alias = $this->normalize_text($alias);
                if ($alias !== '') {
                    $all_aliases[] = $alias;
                }
            }
        }

        foreach ($operators as $operator) {
            $operator = $this->normalize_text($operator);
            if ($operator === '') {
                continue;
            }
            foreach ($all_aliases as $alias) {
                $query = preg_replace('/(?:^|\s)' . preg_quote($operator, '/') . '\s+(?:color\s+|colour\s+|size\s+|in\s+|with\s+)?' . preg_quote($alias, '/') . '(?=\s|$)/u', ' ', $query);
            }
        }

        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }


    public function is_weak_shopper_token($token) {
        $token = $this->normalize_text($token);
        if ($token === '') {
            return true;
        }

        $weak = array_fill_keys(array(
            'don', 'dont', 't', 'what', 'which', 'if', 'one', 'ones', 'would', 'will', 'should', 'could', 'maybe', 'possible', 'possibly',
            'something', 'option', 'options', 'product', 'products', 'item', 'items', 'actually', 'currently', 'still', 'look', 'looks',
            'good', 'nice', 'quality', 'simple', 'useful', 'popular', 'expensive', 'costly', 'pricey', 'luxury', 'cheapest', 'lowest'
        ), true);

        return isset($weak[$token]);
    }

    public function stop_words($language = 'en') {
        $base = array(
            'a', 'an', 'any', 'don', 'dont', 't', 'and', 'are', 'as', 'at', 'be', 'best', 'better', 'buy', 'can', 'could', 'do', 'does', 'find', 'for', 'from', 'get', 'give', 'got', 'have', 'has', 'i', 'im', "i'm", 'in', 'is', 'it', 'if', 'like', 'looking', 'look', 'maybe', 'may', 'might', 'me', 'my', 'need', 'needs', 'nice', 'no', 'not', 'of', 'on', 'one', 'ones', 'option', 'options', 'or', 'perhaps', 'please', 'kindly', 'also', 'prefer', 'preferably', 'preferred', 'product', 'products', 'search', 'show', 'should', 'some', 'something', 'suggest', 'the', 'there', 'that', 'this', 'these', 'those', 'then', 'to', 'too', 'u', 'want', 'wants', 'we', 'what', 'which', 'would', 'will', 'with', 'without', 'you', 'your', 'but', 'only', 'actually', 'currently', 'current', 'right', 'now', 'good', 'useful', 'simple',
            'price', 'priced', 'cost', 'costing', 'budget', 'range', 'under', 'below', 'less', 'than', 'max', 'maximum', 'up', 'over', 'above', 'more', 'min', 'minimum', 'between', 'around', 'about', 'near', 'approximately', 'approx', 'roughly', 'color', 'colour', 'colors', 'colours', 'size', 'sizes', 'sized', 'category', 'categories', 'still', 'possible', 'possibility', 'quality', 'expensive', 'costly', 'pricey', 'luxury', 'cheapest', 'lowest', 'rs', 'pkr', 'usd', 'eur', 'gbp', 'aed', 'sar', 'qar', 'kwd', 'inr', 'dollar', 'dollars',
        );

        $extra = array(
            'ur' => array('میں', 'مجھے', 'میرا', 'میرے', 'آپ', 'اپ', 'کو', 'کے', 'کی', 'کا', 'اور', 'یا', 'ہے', 'ہیں', 'دو', 'دکھاؤ', 'دکھاو', 'چاہیے', 'چاہتا', 'چاہتی', 'تلاش', 'ڈھونڈو', 'پروڈکٹ', 'مصنوعات', 'قیمت', 'کم', 'زیادہ', 'سے', 'تک', 'درمیان', 'نیچے', 'اوپر'),
            'ar' => array('انا', 'أريد', 'اريد', 'هل', 'من', 'في', 'على', 'او', 'أو', 'و', 'مع', 'لي', 'عن', 'هذا', 'هذه', 'منتج', 'منتجات', 'اعرض', 'اظهر', 'ابحث', 'السعر', 'سعر', 'اقل', 'أقل', 'اكثر', 'أكثر', 'من', 'الى', 'إلى', 'بين', 'تحت', 'فوق'),
            'fa' => array('من', 'میخواهم', 'میخواهم', 'برای', 'از', 'در', 'با', 'یا', 'و', 'محصول', 'محصولات', 'نمایش', 'جستجو', 'قیمت', 'کمتر', 'بیشتر', 'بین', 'تا'),
            'hi' => array('मैं', 'मुझे', 'मेरे', 'आप', 'को', 'का', 'की', 'के', 'और', 'या', 'है', 'दिखाओ', 'चाहिए', 'उत्पाद', 'प्रोडक्ट', 'कीमत', 'कम', 'से', 'ज्यादा', 'बीच'),
            'es' => array('yo', 'me', 'mi', 'mis', 'tu', 'un', 'una', 'el', 'la', 'los', 'las', 'de', 'del', 'para', 'por', 'con', 'y', 'o', 'quiero', 'buscar', 'mostrar', 'producto', 'productos', 'precio', 'menos', 'mas', 'más', 'entre'),
            'fr' => array('je', 'me', 'mon', 'ma', 'mes', 'un', 'une', 'le', 'la', 'les', 'de', 'des', 'du', 'pour', 'avec', 'et', 'ou', 'veux', 'chercher', 'montrer', 'produit', 'produits', 'prix', 'moins', 'plus', 'entre'),
            'de' => array('ich', 'mir', 'mein', 'meine', 'ein', 'eine', 'der', 'die', 'das', 'den', 'dem', 'zu', 'fur', 'für', 'mit', 'und', 'oder', 'suche', 'zeigen', 'produkt', 'produkte', 'preis', 'unter', 'uber', 'über', 'zwischen'),
            'it' => array('io', 'mi', 'mio', 'mia', 'un', 'una', 'il', 'lo', 'la', 'gli', 'le', 'di', 'per', 'con', 'e', 'o', 'voglio', 'cerca', 'mostra', 'prodotto', 'prodotti', 'prezzo', 'meno', 'piu', 'più', 'tra'),
            'pt' => array('eu', 'me', 'meu', 'minha', 'um', 'uma', 'o', 'a', 'os', 'as', 'de', 'para', 'com', 'e', 'ou', 'quero', 'buscar', 'mostrar', 'produto', 'produtos', 'preco', 'preço', 'menos', 'mais', 'entre'),
            'nl' => array('ik', 'mijn', 'een', 'de', 'het', 'voor', 'met', 'en', 'of', 'zoek', 'toon', 'product', 'producten', 'prijs', 'onder', 'boven', 'tussen'),
            'tr' => array('ben', 'bana', 'benim', 'bir', 've', 'veya', 'icin', 'için', 'ile', 'ara', 'goster', 'göster', 'urun', 'ürün', 'urunler', 'ürünler', 'fiyat', 'altinda', 'altında', 'ustunde', 'üstünde', 'arasi', 'arası'),
            'id' => array('saya', 'aku', 'mau', 'ingin', 'dan', 'atau', 'untuk', 'dengan', 'cari', 'tampilkan', 'produk', 'harga', 'dibawah', 'di bawah', 'diatas', 'di atas', 'antara'),
            'ms' => array('saya', 'mahu', 'ingin', 'dan', 'atau', 'untuk', 'dengan', 'cari', 'papar', 'produk', 'harga', 'bawah', 'atas', 'antara'),
            'ru' => array('я', 'мне', 'мой', 'моя', 'и', 'или', 'для', 'с', 'найти', 'показать', 'товар', 'товары', 'цена', 'дешевле', 'меньше', 'больше', 'между'),
            'zh' => array('我', '想', '要', '买', '找', '显示', '产品', '商品', '价格', '低于', '高于', '之间'),
            'ja' => array('私', '欲しい', '買う', '探す', '表示', '商品', '製品', '価格', '以下', '以上', '未満'),
            'ko' => array('나', '저', '원해', '구매', '찾기', '보기', '상품', '제품', '가격', '이하', '이상'),
        );

        $language = strtolower((string) $language);
        if (isset($this->stop_words_cache[$language])) {
            return $this->stop_words_cache[$language];
        }

        $words = array_merge($base, isset($extra[$language]) ? $extra[$language] : array());

        /**
         * Filters language-aware product-search stop words.
         *
         * @param array  $words    Stop words.
         * @param string $language Detected language code.
         */
        $words = (array) apply_filters('geekybot_search_stop_words', $words, $language);
        $words = array_values(array_unique(array_map(array($this, 'normalize_text'), $words)));
        $this->stop_words_cache[$language] = $words;
        return $words;
    }

    private function product_keywords() {
        return array('product', 'products', 'item', 'items', 'catalog', 'price', 'cost', 'stock', 'available', 'availability', 'size', 'color', 'colour', 'brand', 'material', 'style', 'fit', 'recommend', 'suggest', 'compare', 'buy', 'looking for', 'find', 'cheap', 'affordable', 'premium', 'discount', 'deal', 'coupon', 'مصنوعات', 'پروڈکٹ', 'قیمت', 'رنگ', 'سائز', 'برانڈ', 'موجود', 'دستیاب', 'منتج', 'منتجات', 'سعر', 'لون', 'مقاس', 'ماركة', 'متوفر', 'producto', 'productos', 'precio', 'talla', 'color', 'produit', 'produits', 'prix', 'taille', 'couleur', 'produkt', 'produkte', 'preis', 'größe', 'farbe');
    }

    private function latest_keywords() {
        return array('latest', 'new', 'newest', 'recent', 'recently added', 'new arrivals', 'نیا', 'نئی', 'تازہ', 'جدید', 'جديد', 'أحدث', 'احدث', 'nouveau', 'nouveaux', 'nuevo', 'nuevos', 'neu', 'neue', 'novita', 'novità');
    }

    private function sale_keywords() {
        return array('sale', 'discount', 'discounted', 'deals', 'deal', 'offer', 'offers', 'clearance', 'coupon', 'رعایت', 'سیل', 'آفر', 'افر', 'خصم', 'عرض', 'تخفيض', 'oferta', 'descuento', 'promotion', 'promo', 'soldes', 'rabatt', 'angebot', 'sconto');
    }

    private function top_rated_keywords() {
        return array('top rated', 'highly rated', 'highest rated', 'best rated', 'better rated', 'rating', 'ratings', 'reviews', 'well reviewed', 'بہترین ریٹنگ', 'ریٹنگ', 'اعلى تقييم', 'أعلى تقييم', 'تقييم', 'mejor valorado', 'meilleure note', 'bewertet', 'valutato');
    }

    private function popular_keywords() {
        return array('popular', 'best selling', 'trending', 'most sold', 'مشہور', 'زیادہ فروخت', 'مقبول', 'الأكثر مبيعا', 'اكثر مبيعا', 'شائع', 'popular', 'más vendido', 'mas vendido', 'tendance', 'beliebt', 'bestseller');
    }

    private function under_keywords() {
        return array('under', 'below', 'less than', 'max', 'maximum', 'up to', 'at most', 'not above', 'no more than', 'کم', 'سے کم', 'نیچے', 'تک', 'اقل من', 'أقل من', 'دون', 'تحت', 'menos de', 'debajo de', 'hasta', 'moins de', 'sous', 'jusqu a', 'unter', 'bis', 'sotto', 'meno di', 'abaixo de', 'ate', 'até', 'altinda', 'altında');
    }

    private function over_keywords() {
        return array('over', 'above', 'more than', 'min', 'minimum', 'starting from', 'at least', 'not below', 'no less than', 'زیادہ', 'سے زیادہ', 'اوپر', 'اكثر من', 'أكثر من', 'فوق', 'mas de', 'más de', 'encima de', 'plus de', 'au dessus de', 'uber', 'über', 'mehr als', 'sopra', 'piu di', 'più di', 'acima de', 'ustunde', 'üstünde');
    }

    private function between_keywords() {
        return array('between', 'range', 'درمیان', 'کے درمیان', 'بين', 'entre', 'zwischen', 'tra', 'tussen', 'arasi', 'arası');
    }

    private function around_keywords() {
        return array('around', 'about', 'near', 'approximately', 'approx', 'roughly', 'تقريبا', 'تقریباً', 'لگ بھگ', 'قريب من', 'cerca de', 'alrededor de', 'environ', 'autour de', 'ungefahr', 'ungefähr', 'circa', 'yaklasik', 'yaklaşık');
    }

    private function budget_keywords() {
        return array('budget', 'budget of', 'within budget', 'max budget', 'price limit', 'my budget is', 'بجٹ', 'حد', 'ميزانية', 'ميزانيه', 'presupuesto', 'budget', 'budget maximum', 'preislimit', 'butce', 'bütçe');
    }

    private function price_target_keywords() {
        return array('price', 'priced', 'priced at', 'cost', 'costing', 'cost of', 'rate', 'worth', 'قیمت', 'دام', 'سعر', 'السعر', 'precio', 'prix', 'preis', 'prezzo', 'preco', 'preço', 'fiyat');
    }



    private function negative_terms_for_map($query, $map) {
        $terms = array();
        $operators = $this->negative_operator_keywords();

        foreach ((array) $map as $canonical => $aliases) {
            foreach ((array) $aliases as $alias) {
                $alias = $this->normalize_text($alias);
                if ($alias === '') {
                    continue;
                }
                foreach ($operators as $operator) {
                    $operator = $this->normalize_text($operator);
                    if ($operator === '') {
                        continue;
                    }

                    $patterns = array(
                        '/(?:^|\s)' . preg_quote($operator, '/') . '\s+(?:color\s+|colour\s+|size\s+|in\s+|with\s+)?' . preg_quote($alias, '/') . '(?=\s|$)/u',
                        '/(?:^|\s)' . preg_quote($alias, '/') . '\s+(?:' . preg_quote($operator, '/') . ')(?=\s|$)/u',
                    );

                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $query)) {
                            $terms[] = $canonical;
                            $terms = array_merge($terms, (array) $aliases);
                            break 2;
                        }
                    }
                }
            }
        }

        return $terms;
    }

    private function has_negative_facet_signal($query) {
        $query = $this->normalize_text($query);
        if ($query === '') {
            return false;
        }

        foreach ($this->negative_operator_keywords() as $operator) {
            $operator = $this->normalize_text($operator);
            if ($operator !== '' && $this->contains_phrase($query, $operator)) {
                return true;
            }
        }

        return false;
    }

    private function negative_operator_keywords() {
        return array(
            'not', 'no', 'without', 'except', 'excluding', 'exclude', 'avoid', 'not in', 'other than',
            'dont want', 'don t want', "don't want", 'do not want',
            'نہیں', 'بغیر', 'کے بغیر', 'نہ', 'غير', 'بدون', 'sin', 'sans', 'ohne', 'senza', 'sem',
        );
    }

    private function buyer_preference_keyword_map() {
        $map = array(
            'comfortable' => array('comfortable', 'comfartable', 'comfertable', 'comfy', 'comfort', 'soft', 'cushioned', 'padded', 'easy to wear', 'relaxed fit', 'walking comfort'),
            'formal' => array('formal', 'office', 'work', 'business', 'professional', 'dressy'),
            'casual' => array('casual', 'daily wear', 'everyday', 'regular wear', 'normal wear'),
            'premium' => array('premium', 'luxury', 'expensive', 'costly', 'pricey', 'high end', 'high-end', 'higher end', 'high quality', 'best quality', 'better quality'),
            'budget' => array('budget friendly', 'budget-friendly', 'affordable', 'cheap', 'low price', 'low priced', 'economical', 'not expensive', 'not too expensive', 'not costly', 'not too costly', 'reasonable price', 'good price', 'value for money'),
            'gift' => array('gift', 'present', 'gift for brother', 'for my brother', 'for brother', 'brother', 'for sister', 'sister', 'for friend', 'friend', 'for husband', 'husband', 'for wife', 'wife'),
            'popular' => array('popular', 'best selling', 'trending', 'most sold', 'customer favorite', 'customer favourite'),
            'useful' => array('useful', 'practical', 'handy', 'everyday useful'),
            'simple' => array('simple', 'minimal', 'minimalist', 'plain', 'clean style', 'basic'),
            'quality' => array('good', 'nice', 'good quality', 'well made', 'durable'),
            'value' => array('best value', 'good value', 'balanced choice', 'safe choice', 'safest choice', 'worth buying', 'not the cheapest', 'not cheapest'),
        );

        return (array) apply_filters('geekybot_search_buyer_preference_keywords', $map);
    }

    private function buyer_use_case_keyword_map() {
        $map = array(
            'winter' => array('winter', 'cold weather', 'warm', 'warmer', 'cozy', 'cosy'),
            'summer' => array('summer', 'hot weather', 'lightweight', 'breathable'),
            'sports' => array('sports', 'sport', 'gym', 'training', 'workout', 'running', 'walking', 'walk', 'jogging'),
            'travel' => array('travel', 'trip', 'journey', 'outdoor'),
            'party' => array('party', 'event', 'wedding', 'occasion'),
            'school' => array('school', 'college', 'university', 'student'),
        );

        return (array) apply_filters('geekybot_search_buyer_use_case_keywords', $map);
    }

    private function color_keyword_map() {
        $map = array(
            'black' => array('black', 'jet black', 'کالا', 'کالی', 'سیاہ', 'اسود', 'أسود', 'noir', 'negro', 'schwarz', 'nero'),
            'white' => array('white', 'off white', 'off-white', 'سفید', 'ابيض', 'أبيض', 'blanc', 'blanco', 'weiss', 'weiß', 'bianco'),
            'blue' => array('blue', 'navy', 'navy blue', 'sky blue', 'dark blue', 'نیلا', 'نیلی', 'ازرق', 'أزرق', 'bleu', 'azul', 'blau'),
            'green' => array('green', 'olive', 'dark green', 'سبز', 'ہرا', 'اخضر', 'أخضر', 'vert', 'verde', 'grun', 'grün'),
            'red' => array('red', 'maroon', 'burgundy', 'سرخ', 'لال', 'احمر', 'أحمر', 'rouge', 'rojo', 'rot', 'rosso'),
            'yellow' => array('yellow', 'mustard', 'پیلا', 'پیلے', 'اصفر', 'أصفر', 'jaune', 'amarillo', 'gelb', 'giallo'),
            'pink' => array('pink', 'rose', 'گلابی', 'وردي', 'rose', 'rosa'),
            'purple' => array('purple', 'violet', 'جامنی', 'بنفسجي', 'violet', 'morado', 'lila', 'viola'),
            'orange' => array('orange', 'نارنجی', 'برتقالي', 'naranja', 'arancione'),
            'brown' => array('brown', 'tan', 'camel', 'بھورا', 'براون', 'بني', 'marron', 'marrón', 'braun'),
            'gray' => array('gray', 'grey', 'charcoal', 'slate', 'سرمئی', 'گرے', 'رمادي', 'gris', 'grau', 'grigio'),
            'beige' => array('beige', 'cream', 'ivory', 'کریم', 'بيج', 'crema'),
            'gold' => array('gold', 'golden', 'سنہری', 'ذهبي', 'oro'),
            'silver' => array('silver', 'چاندی', 'فضي', 'argent', 'plata', 'silber', 'argento'),
            'multi' => array('multi', 'multicolor', 'multi color', 'multicolour', 'mixed color', 'رنگ برنگا', 'متعدد الالوان'),
        );

        /**
         * Filters known color aliases used for product-search facets.
         *
         * @param array $map Canonical color => aliases.
         */
        return (array) apply_filters('geekybot_search_color_keywords', $map);
    }

    private function size_keyword_map() {
        $map = array(
            'xxxs' => array('xxxs', '3xs', 'extra extra extra small'),
            'xxs' => array('xxs', '2xs', 'extra extra small'),
            'xs' => array('xs', 'extra small', 'x small'),
            's' => array('s', 'small', 'sm'),
            'm' => array('m', 'medium', 'med'),
            'l' => array('l', 'large', 'lg'),
            'xl' => array('xl', 'extra large', 'x large'),
            'xxl' => array('xxl', '2xl', 'double xl', 'extra extra large'),
            'xxxl' => array('xxxl', '3xl', 'triple xl', 'extra extra extra large'),
            'one size' => array('one size', 'onesize', 'free size', 'single size'),
        );

        /**
         * Filters known size aliases used for product-search facets.
         *
         * @param array $map Canonical size => aliases.
         */
        return (array) apply_filters('geekybot_search_size_keywords', $map);
    }

    private function normalize_facet_term_list($terms) {
        $clean = array();
        foreach ((array) $terms as $term) {
            $term = $this->normalize_text($term);
            if ($term !== '') {
                $clean[] = $term;
            }
        }
        return array_values(array_unique($clean));
    }

    private function contains_any_phrase($text, $phrases) {
        foreach ((array) $phrases as $phrase) {
            if ($this->contains_phrase($text, $phrase)) {
                return true;
            }
        }
        return false;
    }

    private function contains_phrase($text, $phrase) {
        $normalized_text = $this->normalize_text($text);
        $phrase = $this->normalize_text($phrase);
        if ($normalized_text === '' || $phrase === '') {
            return false;
        }

        // Exact token/phrase matching is important for commerce facets.
        // A single-letter size like "s" must not match words such as "sale"
        // or "discounted". Multi-word phrases still match when the full phrase
        // is present in normalized text.
        if (strpos(' ' . $normalized_text . ' ', ' ' . $phrase . ' ') !== false) {
            return true;
        }

        // CJK languages often do not use spaces. Allow substring matching only
        // when the phrase itself is CJK text.
        if (preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $phrase)) {
            return strpos($normalized_text, $phrase) !== false;
        }

        return false;
    }

    private function keyword_pattern($keywords) {
        $keywords = (array) $keywords;
        $cache_key = md5(serialize($keywords));
        if (isset($this->keyword_pattern_cache[$cache_key])) {
            return $this->keyword_pattern_cache[$cache_key];
        }

        $quoted = array();
        foreach ($keywords as $keyword) {
            $keyword = $this->normalize_text($keyword);
            if ($keyword !== '') {
                $quoted[] = preg_quote($keyword, '/');
            }
        }
        usort($quoted, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $pattern = implode('|', $quoted);
        $this->keyword_pattern_cache[$cache_key] = $pattern;
        return $pattern;
    }

    private function parse_price_number($value) {
        return (float) str_replace(',', '', (string) $value);
    }

    private function normalize_search_token($token, $language = 'en') {
        $token = $this->normalize_text($token);
        if ($token === '' || preg_match('/^\d+(?:\.\d+)?$/', $token)) {
            return $token;
        }

        // Lightweight English/Latin plural handling. This turns "belts" into "belt"
        // and "shoes" into "shoe" so FULLTEXT/LIKE can match singular catalog titles.
        if (preg_match('/^[a-z][a-z0-9_-]{2,}$/', $token)) {
            $irregular = array(
                'hoodies' => 'hoodie',
                'hoddies' => 'hoodie',
                'hoddie' => 'hoodie',
                'hoody' => 'hoodie',
            );
            if (isset($irregular[$token])) {
                return $irregular[$token];
            }
            if (preg_match('/ies$/', $token) && strlen($token) > 4) {
                return substr($token, 0, -3) . 'y';
            }
            if (preg_match('/(xes|ches|shes|sses|zes)$/', $token) && strlen($token) > 4) {
                return substr($token, 0, -2);
            }
            if (preg_match('/s$/', $token) && !preg_match('/(ss|us)$/', $token) && strlen($token) > 3) {
                return substr($token, 0, -1);
            }
        }

        return $token;
    }

    private function token_length($text) {
        return function_exists('mb_strlen') ? mb_strlen((string) $text, 'UTF-8') : strlen((string) $text);
    }

    private function normalize_arabic_family_chars($text) {
        $map = array(
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ی', 'ي' => 'ی', 'ئ' => 'ی',
            'ك' => 'ک',
            'ة' => 'ه',
            'ؤ' => 'و',
            'َ' => '', 'ً' => '', 'ُ' => '', 'ٌ' => '', 'ِ' => '', 'ٍ' => '', 'ْ' => '', 'ّ' => '',
        );
        return strtr((string) $text, $map);
    }
}
