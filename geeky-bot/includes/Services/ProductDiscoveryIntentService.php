<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classifies catalog-discovery messages before shopping-context merging.
 *
 * This service has one job: decide whether a shopper is starting a new product
 * mission or applying a short modifier to the current mission. It is deliberately
 * deterministic and commerce-specific so normal searches are not polluted by
 * earlier product families, sizes, colors, or prices.
 */
final class ProductDiscoveryIntentService {
    /**
     * Analyze a shopper message against the previous product-search analysis.
     *
     * @param string $message           Shopper message.
     * @param array  $previous_analysis Previous normalized search analysis.
     * @return array
     */
    public function analyze($message, $previous_analysis = array()) {
        $message = wp_strip_all_tags((string) $message);
        $previous_analysis = is_array($previous_analysis) ? $previous_analysis : array();
        $index = new ProductIndexService();
        $analysis = $index->analyze_query($message);
        $normalized = !empty($analysis['lower']) ? (string) $analysis['lower'] : $this->normalize($message);
        $subject_terms = $this->subject_terms($analysis);
        $previous_terms = $this->subject_terms($previous_analysis);
        $global_browse = $this->is_global_browse($normalized, $analysis, $previous_analysis);
        $explicit_discovery = $this->starts_discovery_request($normalized);
        $short_refinement = $this->is_short_refinement($normalized, $analysis, $subject_terms);
        $has_subject = !empty($subject_terms);

        $is_new_mission = false;
        if ($global_browse) {
            $is_new_mission = true;
        } elseif ($has_subject && !$short_refinement) {
            // A message that repeats a product family is still self-contained:
            // "hoodie under $50", "black hoodie", and "hoodie XL" must not
            // inherit hidden constraints from an older hoodie mission.
            $is_new_mission = true;
        } elseif ($explicit_discovery && $has_subject) {
            $is_new_mission = true;
        }

        $is_product_discovery = $global_browse
            || $has_subject
            || $this->looks_like_catalog_browse($normalized)
            || $explicit_discovery;

        return array(
            'isProductDiscovery' => (bool) $is_product_discovery,
            'isNewMission' => (bool) $is_new_mission,
            'isGlobalBrowse' => (bool) $global_browse,
            'isRefinement' => (bool) (!$is_new_mission && $short_refinement),
            'subjectTerms' => $subject_terms,
            'previousSubjectTerms' => $previous_terms,
            'productPhrase' => !empty($analysis['product_phrase']) ? (string) $analysis['product_phrase'] : '',
            'productFamily' => !empty($analysis['product_family_term']) ? (string) $analysis['product_family_term'] : '',
            'analysis' => $analysis,
        );
    }

    /**
     * Returns true when a current-results phrase should remain a search rather
     * than being interpreted as selecting a product with a similar title.
     */
    public function should_search_instead_of_select($message, $candidate_names = array()) {
        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return false;
        }

        foreach ((array) $candidate_names as $name) {
            if ($normalized === $this->normalize($name)) {
                return false;
            }
        }

        $analysis = (new ProductIndexService())->analyze_query($message);
        $terms = $this->subject_terms($analysis);
        if (empty($terms)) {
            return false;
        }

        if ($this->starts_discovery_request($normalized)) {
            return true;
        }

        $distinctive = $this->distinctive_identity_tokens($normalized);
        if (!empty($distinctive)) {
            return false;
        }

        // Generic descriptive phrases such as "black hoodie", "laptop
        // backpack", and "wireless keyboard" are discovery phrases even when
        // one currently shown title happens to contain the same words.
        return !empty($analysis['product_family_term']) || count($terms) >= 1;
    }

    private function subject_terms($analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        if (!empty($analysis['product_phrase_terms'])) {
            return $this->clean_terms($analysis['product_phrase_terms']);
        }
        if (!empty($analysis['display_core_terms'])) {
            return $this->clean_terms($analysis['display_core_terms']);
        }
        if (!empty($analysis['core_terms'])) {
            return $this->clean_terms($analysis['core_terms']);
        }
        return array();
    }

    private function is_global_browse($normalized, $analysis, $previous_analysis = array()) {
        $analysis = is_array($analysis) ? $analysis : array();
        $previous_analysis = is_array($previous_analysis) ? $previous_analysis : array();
        $intent = !empty($analysis['intent']) ? sanitize_key((string) $analysis['intent']) : 'search';
        $global_intent = in_array($intent, array('sale', 'latest', 'popular', 'top_rated'), true);
        $generic_catalog_noun = preg_match('/\b(?:products?|items?|catalog|collection|arrivals?)\b/u', $normalized) === 1;
        $stock_browse = !empty($analysis['in_stock_only']) && $generic_catalog_noun;

        // A short sale-only message is a refinement when a real product mission
        // already exists. Without this guard, "On sale only" is mistaken for a
        // global sale browse and the current family gate is discarded.
        if ($this->is_contextual_sale_refinement($normalized, $analysis, $previous_analysis)) {
            return false;
        }

        if ($global_intent && (empty($this->subject_terms($analysis)) || $generic_catalog_noun)) {
            return true;
        }

        // "cheapest" with nothing named is a browse of the catalog by price, the
        // same shape as "show sale items". With a product named it stays a
        // search, so "cheapest backpack" is unaffected.
        if (!empty($analysis['budget_sort']) && empty($this->subject_terms($analysis))) {
            return true;
        }
        if ($stock_browse) {
            return true;
        }

        return preg_match('/^(?:show(?:\s+me)?|find(?:\s+me)?|list)?\s*(?:all\s+)?(?:sale|discounted|in[-\s]?stock|available|new|latest|popular|top[-\s]?rated)\s+(?:products?|items?)$/u', $normalized) === 1;
    }

    /**
     * Distinguish a short sale refinement from an explicit whole-catalog sale
     * request. The previous product family remains mandatory for refinements.
     *
     * @param string $normalized        Normalized shopper message.
     * @param array  $analysis          Current search analysis.
     * @param array  $previous_analysis Previous active search analysis.
     * @return bool
     */
    private function is_contextual_sale_refinement($normalized, $analysis, $previous_analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        $previous_analysis = is_array($previous_analysis) ? $previous_analysis : array();
        $intent = !empty($analysis['intent']) ? sanitize_key((string) $analysis['intent']) : 'search';

        if ($intent !== 'sale' || empty($this->subject_terms($previous_analysis))) {
            return false;
        }

        // A new subject such as "sale keyboards" is a self-contained mission,
        // not a refinement of the previous family.
        if (!empty($this->subject_terms($analysis))) {
            return false;
        }

        $normalized = trim((string) $normalized);
        $word_count = count(array_filter(preg_split('/\s+/u', $normalized)));
        if ($normalized === '' || $word_count > 9) {
            return false;
        }

        // These are explicit global catalog requests even when another mission
        // is active. They intentionally replace the previous product family.
        $explicit_global_patterns = array(
            '/^(?:show(?:\s+me)?|find(?:\s+me)?|list)\s+(?:all\s+)?(?:sale|discounted)\s+(?:products?|items?)$/u',
            '/^(?:show(?:\s+me)?|find(?:\s+me)?|list)\s+(?:all\s+)?(?:products?|items?)\s+(?:that\s+are\s+)?on\s+sale$/u',
            '/^(?:all\s+)?(?:products?|items?)\s+on\s+sale$/u',
            '/^what(?:\s+(?:products?|items?))?\s+(?:is|are)\s+(?:currently\s+)?on\s+sale$/u',
        );
        foreach ($explicit_global_patterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return false;
            }
        }

        $has_refinement_language = preg_match('/\bonly\b/u', $normalized) === 1
            || preg_match('/\b(?:one|ones|option|options|result|results|them|these|those)\b/u', $normalized) === 1
            || preg_match('/^(?:on\s+sale|sale|discounted|discounted\s+ones?)\b/u', $normalized) === 1;

        return $has_refinement_language;
    }

    private function is_short_refinement($normalized, $analysis, $subject_terms) {
        $analysis = is_array($analysis) ? $analysis : array();
        $word_count = count(array_filter(preg_split('/\s+/u', trim((string) $normalized))));
        $has_reference = preg_match('/\b(?:one|ones|option|options|results?|products?|items?|them|these|those)\b/u', $normalized) === 1;
        $starts_refinement = preg_match('/^(?:only|just|but|and|also|now|show(?:\s+me)?\s+(?:only|options?|ones?|results?)|remove|without|not\s+below|at\s+least|at\s+most|under|below|over|above)\b/u', $normalized) === 1;
        $has_filter = !empty($analysis['price_range'])
            || !empty($analysis['color_terms'])
            || !empty($analysis['size_terms'])
            || !empty($analysis['negative_color_terms'])
            || !empty($analysis['negative_size_terms'])
            || !empty($analysis['in_stock_only'])
            || (!empty($analysis['intent']) && in_array($analysis['intent'], array('sale'), true));

        if ($starts_refinement && empty($subject_terms)) {
            return true;
        }
        if ($has_reference && $has_filter && $word_count <= 8) {
            return true;
        }
        if ($has_filter && empty($subject_terms) && $word_count <= 7) {
            return true;
        }
        return false;
    }

    private function starts_discovery_request($normalized) {
        return preg_match('/^(?:show(?:\s+me)?|find(?:\s+me)?|search(?:\s+for)?|look(?:ing)?\s+for|recommend|suggest|list|i\s+(?:need|want)|do\s+you\s+have)\b/u', $normalized) === 1;
    }

    private function looks_like_catalog_browse($normalized) {
        if ($normalized === '') {
            return false;
        }
        return preg_match('/\b(?:products?|items?|accessories|clothing|footwear|drinkware|electronics|bags?|shoes?|hoodies?|jackets?|keyboards?|speakers?|bottles?|mugs?)\b/u', $normalized) === 1;
    }

    private function subjects_overlap($current, $previous) {
        $current = $this->clean_terms($current);
        $previous = $this->clean_terms($previous);
        if (empty($current) || empty($previous)) {
            return false;
        }

        $current_family = $this->family_term($current);
        $previous_family = $this->family_term($previous);
        if ($current_family !== '' && $previous_family !== '') {
            return $this->families_related($current_family, $previous_family);
        }

        return !empty(array_intersect($current, $previous));
    }

    private function contains_new_family_phrase($analysis, $previous_analysis) {
        $current = !empty($analysis['product_family_term']) ? sanitize_key((string) $analysis['product_family_term']) : '';
        $previous = !empty($previous_analysis['product_family_term']) ? sanitize_key((string) $previous_analysis['product_family_term']) : '';
        return $current !== '' && $previous !== '' && !$this->families_related($current, $previous);
    }

    private function family_term($terms) {
        $terms = array_reverse($this->clean_terms($terms));
        foreach ($terms as $term) {
            $term = sanitize_key($term);
            if (in_array($term, $this->family_terms(), true)) {
                return $term;
            }
        }
        return '';
    }

    private function families_related($a, $b) {
        $a = sanitize_key($a);
        $b = sanitize_key($b);
        if ($a === $b) {
            return true;
        }
        foreach ($this->family_groups() as $group) {
            if (in_array($a, $group, true) && in_array($b, $group, true)) {
                return true;
            }
        }
        return false;
    }

    private function distinctive_identity_tokens($normalized) {
        $generic = array_fill_keys(array_merge(
            $this->family_terms(),
            array(
                'black', 'white', 'blue', 'red', 'green', 'grey', 'gray', 'brown', 'navy', 'cream',
                'wireless', 'water', 'water-resistant', 'waterproof', 'laptop', 'office', 'travel', 'running',
                'comfortable', 'comfort', 'premium', 'budget', 'sale', 'insulated', 'lightweight', 'fleece',
                'zip', 'size', 'small', 'medium', 'large', 'xl', 'xxl',
            )
        ), true);

        $tokens = array();
        foreach (preg_split('/\s+/u', $normalized) as $token) {
            $token = trim((string) $token, " \t\n\r\0\x0B.-_");
            if ($token === '' || strlen($token) < 4 || is_numeric($token) || isset($generic[$token])) {
                continue;
            }
            $tokens[] = $token;
        }
        return array_values(array_unique($tokens));
    }

    private function family_terms() {
        return array(
            'accessory', 'accessories', 'adapter', 'adapters', 'bag', 'bags', 'backpack', 'backpacks',
            'belt', 'belts', 'bottle', 'bottles', 'case', 'cases', 'charger', 'chargers', 'clock', 'clocks',
            'coat', 'coats', 'cup', 'cups', 'dress', 'dresses', 'drinkware', 'earbud', 'earbuds',
            'footrest', 'footrests', 'headphone', 'headphones', 'hoodie', 'hoodies', 'jacket', 'jackets',
            'keyboard', 'keyboards', 'lamp', 'lamps', 'mat', 'mats', 'mug', 'mugs', 'organiser', 'organisers',
            'organizer', 'organizers', 'pillow', 'pillows', 'pouch', 'pouches', 'powerbank', 'powerbanks',
            'scarf', 'scarves', 'shirt', 'shirts', 'shoe', 'shoes', 'sleeve', 'sleeves', 'sneaker', 'sneakers',
            'speaker', 'speakers', 'tote', 'totes', 'trainer', 'trainers', 'tumbler', 'tumblers', 'wallet', 'wallets',
            'watch', 'watches',
        );
    }

    private function family_groups() {
        return array(
            array('shoe', 'shoes', 'sneaker', 'sneakers', 'trainer', 'trainers'),
            array('bag', 'bags', 'backpack', 'backpacks', 'tote', 'totes', 'sleeve', 'sleeves', 'organiser', 'organisers', 'organizer', 'organizers', 'pouch', 'pouches'),
            array('mug', 'mugs', 'cup', 'cups', 'bottle', 'bottles', 'tumbler', 'tumblers', 'drinkware'),
            array('earbud', 'earbuds', 'headphone', 'headphones'),
            array('case', 'cases', 'sleeve', 'sleeves'),
            array('accessory', 'accessories', 'adapter', 'adapters', 'organiser', 'organisers', 'organizer', 'organizers', 'pouch', 'pouches'),
        );
    }

    private function clean_terms($terms) {
        $clean = array();
        foreach ((array) $terms as $term) {
            $term = $this->normalize($term);
            if ($term !== '') {
                $clean[] = $term;
            }
        }
        return array_values(array_unique($clean));
    }

    private function normalize($value) {
        $value = function_exists('remove_accents') ? remove_accents(wp_strip_all_tags((string) $value)) : wp_strip_all_tags((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        $value = str_replace(array('–', '—', '_'), array('-', '-', ' '), $value);
        $value = preg_replace('/[^\pL\pN\-\s]+/u', ' ', (string) $value);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
