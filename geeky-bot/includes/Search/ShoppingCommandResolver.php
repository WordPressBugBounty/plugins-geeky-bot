<?php
namespace GeekyBot\Search;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves short, commerce-specific follow-up commands.
 *
 * This class deliberately understands shopping commands only. It does not try
 * to be a general language parser. Search intent remains in BuyerIntentLibrary;
 * this resolver handles actions against an existing product result set.
 */
final class ShoppingCommandResolver {
    const REVISION = 'gb-shopping-commands-2026.07.20.1';

    public function resolve($message, $context = array()) {
        $raw = $this->clean_text($message);
        $text = $this->normalize($raw);
        $empty = array(
            'action' => '',
            'cleanMessage' => $raw,
            'args' => array(),
            'revision' => self::REVISION,
        );

        if ($text === '') {
            return $empty;
        }

        $reset = $this->reset_command($raw, $text);
        if (!empty($reset)) {
            return array_merge($empty, $reset);
        }

        if ($this->matches($text, array(
            '/\bgo back(?: to)?(?: the)? previous(?: options| results| choices)?\b/u',
            '/\bshow(?: me)?(?: the)? previous(?: options| results| choices)\b/u',
            '/\bprevious options\b/u',
            '/\bundo(?: that| the last change)?\b/u',
        ))) {
            $empty['action'] = 'go_back';
            return $empty;
        }

        $remove = $this->remove_constraint_command($text, $context);
        if (!empty($remove)) {
            return array_merge($empty, $remove);
        }

        $compare = $this->compare_command($text, $context);
        if (!empty($compare)) {
            return array_merge($empty, $compare);
        }

        $comparison_followup = $this->comparison_followup_command($text, $context);
        if (!empty($comparison_followup)) {
            return array_merge($empty, $comparison_followup);
        }

        if ($this->matches($text, array(
            '/\bwhich one has(?: the)? best discount\b/u',
            '/\b(?:best|biggest|largest|highest) discount\b/u',
            '/\bwhich(?: one)? is discounted the most\b/u',
            '/\bmost discounted(?: one| option| product)?\b/u',
        ))) {
            $empty['action'] = 'best_discount';
            return $empty;
        }

        $similar = $this->similar_command($text, $context);
        if (!empty($similar)) {
            return array_merge($empty, $similar);
        }

        if ($this->matches($text, array(
            '/\b(?:do you have|is there|show me)?\s*(?:it|that|this|the product)?\s*in (?:an )?(?:other|another|different) colou?r\b/u',
            '/\b(?:another|other|different) colou?r(?:s| options)?\b/u',
            '/\bwhat colou?rs (?:does it|do you have|are available)\b/u',
        ))) {
            $reference = $this->resolve_reference($text, $context);
            $empty['action'] = 'another_color';
            $empty['args'] = $reference;
            return $empty;
        }

        if ($this->matches($text, array(
            '/\b(?:which|what)\s+(?:one|product)?\s*(?:is|would be)?\s*(?:the\s+)?(?:best|better|strongest|top choice)\b/u',
            '/\b(?:pick|choose|recommend)\s+(?:the\s+)?(?:best|one)\b/u',
            '/\bbest one\b/u',
        ))) {
            $empty['action'] = 'best';
            return $empty;
        }

        if ($this->matches($text, array(
            '/\b(?:cheaper|more affordable|less expensive|lower priced|lower price|budget option|budget ones)\b/u',
        ))) {
            $empty['action'] = 'cheaper';
            return $empty;
        }

        if ($this->matches($text, array(
            '/\b(?:more premium|higher end|high end|better quality|upgrade option|more expensive)\b/u',
        ))) {
            $empty['action'] = 'premium';
            return $empty;
        }

        $ordinal = $this->first_ordinal_index($text);
        if ($ordinal !== null && $this->matches($text, array(
            '/\b(?:the\s+)?(?:first|second|third|fourth|1st|2nd|3rd|4th)(?:\s+one|\s+option|\s+product)?\b/u',
        ))) {
            $empty['action'] = 'select';
            $empty['args'] = array('selectionIndex' => $ordinal);
            return $empty;
        }

        $word_count = count(array_filter(preg_split('/\s+/u', $text)));
        $has_reference = (bool) preg_match('/\b(?:one|ones|option|options|them|these|those|it|results|products?)\b/u', $text);
        $has_modifier = (bool) (
            preg_match('/\b(?:in|under|below|over|above|between|without|with|not below|at least|at most)\b/u', $text)
            || preg_match('/\b(?:black|white|blue|red|green|grey|gray|brown|navy|cream|gold|silver)\s+options?\b/u', $text)
            || preg_match('/\bsize\s*(?:xs|s|m|l|xl|xxl|\d{1,3})\b/u', $text)
            || preg_match('/\b(?:sale|discounted|available|in stock|out of stock)\b/u', $text)
        );

        // An explicit discovery request with a real product/category subject is
        // a new mission, even when it also contains a short price or stock
        // modifier. This prevents "Show me drinkware under $40" from being
        // narrowed into an earlier mug search. Generic commands such as "Only
        // show black options" or "Show me options under $40" remain filters.
        if ($this->is_explicit_new_search_request($text)) {
            return $empty;
        }

        if (preg_match('/\bonly\b/u', $text) || ($has_reference && $has_modifier) || ($word_count <= 6 && $has_modifier)) {
            $empty['action'] = 'filter_current';
            return $empty;
        }

        return $empty;
    }

    /**
     * Returns true when the shopper clearly starts a fresh catalog mission and
     * supplies a meaningful product/category subject. Price, stock, colour,
     * size, and generic result words alone do not count as a new subject.
     */
    private function is_explicit_new_search_request($text) {
        if (!preg_match('/^(?:show(?:\s+me)?|find(?:\s+me)?|search(?:\s+for)?|look(?:ing)?\s+for|i\s+(?:need|want)|do\s+you\s+have)\b/u', $text)) {
            return false;
        }

        $subject = preg_replace('/^(?:show(?:\s+me)?|find(?:\s+me)?|search(?:\s+for)?|look(?:ing)?\s+for|i\s+(?:need|want)|do\s+you\s+have)\s*/u', '', $text);
        $subject = preg_replace('/(?:\$|£|€)?\s*\d+(?:\.\d+)?/u', ' ', (string) $subject);
        $subject = preg_replace('/\b(?:under|below|over|above|between|from|to|not\s+below|at\s+least|at\s+most|less\s+than|more\s+than)\b/u', ' ', (string) $subject);
        $subject = preg_replace('/\b(?:in\s+stock|out\s+of\s+stock|available|sale|discounted|only)\b/u', ' ', (string) $subject);
        $subject = preg_replace('/\b(?:black|white|blue|red|green|grey|gray|brown|navy|cream|gold|silver|pink|purple|orange|yellow|beige|tan)\b/u', ' ', (string) $subject);
        $subject = preg_replace('/\b(?:size\s*)?(?:xs|s|m|l|xl|xxl|\d{1,3})\b/u', ' ', (string) $subject);
        $subject = preg_replace('/\b(?:the|a|an|some|any|more|product|products|item|items|option|options|one|ones|result|results|them|these|those|it|with|without|and|or)\b/u', ' ', (string) $subject);
        $subject = trim((string) preg_replace('/\s+/u', ' ', (string) $subject));

        if ($subject === '') {
            return false;
        }

        foreach (preg_split('/\s+/u', $subject) as $token) {
            if ($this->token_length($token) >= 3) {
                return true;
            }
        }

        return false;
    }

    private function reset_command($raw, $text) {
        if (!preg_match('/^(?:start over|start again|new search|reset(?: the)? search|clear(?: the)? search|forget that)(?:\b|\s*[-:—])/u', $text)) {
            return array();
        }

        $clean = preg_replace('/^\s*(?:start over|start again|new search|reset(?: the)? search|clear(?: the)? search|forget that)\s*(?:[-:—]\s*)?/iu', '', $raw);
        $clean = $this->clean_text($clean);

        return array(
            'action' => 'reset_search',
            'cleanMessage' => $clean,
            'args' => array('resetOnly' => $clean === ''),
        );
    }

    private function remove_constraint_command($text, $context) {
        $types = array();
        $values = array();
        $active_types = $this->active_constraint_types($context);
        $has_remove_verb = (bool) preg_match(
            '/\b(?:remove|drop|clear|ignore|forget|skip|leave out|take off|stop using|do not require|dont require|no longer require)\b/u',
            $text
        );

        // Natural reset phrases such as "any price" and "include regular
        // price items" are removal commands even without an explicit verb.
        $has_color_reset = $this->matches($text, array(
            '/\bany colou?r\b/u',
            '/\ball colou?rs\b/u',
            '/\bno colou?r preference\b/u',
        ));
        $has_size_reset = $this->matches($text, array(
            '/\bany size\b/u',
            '/\bno size preference\b/u',
        ));
        $has_price_reset = $this->matches($text, array(
            '/\bno price limit\b/u',
            '/\bany price\b/u',
            '/\bno budget limit\b/u',
        ));
        $has_sale_reset = $this->matches($text, array(
            '/\bnot only (?:sale|discounted)\b/u',
            '/\binclude (?:regular|full)[ -]?price(?: items| products)?\b/u',
        ));
        $has_stock_reset = $this->matches($text, array(
            '/\binclude out of stock(?: items| products)?\b/u',
        ));

        $colors = array('black', 'white', 'blue', 'red', 'green', 'grey', 'gray', 'brown', 'navy', 'cream', 'gold', 'silver', 'pink', 'purple', 'orange', 'yellow', 'beige', 'tan');
        $mentioned_colors = array();
        foreach ($colors as $color) {
            if (preg_match('/\b' . preg_quote($color, '/') . '\b/u', $text)) {
                $mentioned_colors[] = $color;
            }
        }
        $mentions_color_label = (bool) preg_match('/\bcolou?r(?:s)?\b/u', $text);
        $has_color_negative = (bool) preg_match('/\b(?:not|no longer)\s+(?:only\s+)?(?:black|white|blue|red|green|grey|gray|brown|navy|cream|gold|silver|pink|purple|orange|yellow|beige|tan)\b/u', $text);
        if ($has_color_reset || $has_color_negative || ($has_remove_verb && ($mentions_color_label || !empty($mentioned_colors)))) {
            $types[] = 'color';
            // A single named color can be removed while keeping other colors.
            // Multiple named colors or a generic color reset removes the color
            // constraint as a whole, which is the least surprising behavior.
            $values['color'] = (!$has_color_reset && count($mentioned_colors) === 1)
                ? $mentioned_colors[0]
                : '';
        }

        $mentions_size_label = (bool) preg_match('/\bsize(?:s)?\b/u', $text);
        $mentions_named_size = (bool) preg_match('/\b(?:xs|s|m|l|xl|xxl|xxxl)\b/u', $text);
        if ($has_size_reset || ($has_remove_verb && ($mentions_size_label || $mentions_named_size))) {
            $types[] = 'size';
            $values['size'] = '';
        }

        $mentions_price_label = (bool) preg_match('/\b(?:price|prices|budget|cost|range|limit|ceiling|amount)\b/u', $text);
        $mentions_money = (bool) preg_match('/(?:[$£€]\s*\d|\b\d+(?:\.\d+)?\s*(?:dollars?|usd|pounds?|gbp|euros?|eur)\b)/u', $text);
        $mentions_price_comparison = (bool) preg_match('/\b(?:under|below|less than|at most|up to|over|above|more than|at least|from|between)\s*(?:[$£€]\s*)?\d/u', $text);
        $mentions_bare_number = (bool) preg_match('/\b\d+(?:\.\d+)?\b/u', $text);
        $numeric_is_active_price = $has_remove_verb
            && $mentions_bare_number
            && in_array('price', $active_types, true)
            && !in_array('size', $active_types, true);
        if ($has_price_reset || ($has_remove_verb && ($mentions_price_label || $mentions_money || $mentions_price_comparison || $numeric_is_active_price))) {
            $types[] = 'price';
            $values['price'] = '';
        }

        $mentions_sale = (bool) preg_match('/\b(?:sale|on sale|discount|discounted|regular[ -]?price|full[ -]?price)\b/u', $text);
        if ($has_sale_reset || ($has_remove_verb && $mentions_sale)) {
            $types[] = 'sale';
            $values['sale'] = '';
        }

        $mentions_stock = (bool) preg_match('/\b(?:stock|in stock|out of stock|availability)\b/u', $text);
        if ($has_stock_reset || ($has_remove_verb && $mentions_stock)) {
            $types[] = 'stock';
            $values['stock'] = '';
        }

        $preferences = array('premium', 'budget', 'gift', 'popular', 'useful', 'comfortable', 'office', 'summer', 'travel');
        foreach ($preferences as $preference) {
            if ($has_remove_verb && preg_match('/\b' . preg_quote($preference, '/') . '\b/u', $text)) {
                $types[] = 'preference';
                $values['preference'] = $preference;
                break;
            }
        }

        $types = array_values(array_unique(array_filter($types)));
        if (empty($types)) {
            return array();
        }

        $args = array(
            'constraintType' => $types[0],
            'constraintTypes' => $types,
            'constraintValues' => $values,
            'value' => isset($values[$types[0]]) ? $values[$types[0]] : '',
        );

        return array('action' => 'remove_constraint', 'args' => $args);
    }

    /**
     * Returns the constraint families currently active in saved shopping state.
     * This is used only to resolve otherwise ambiguous corrections such as
     * "remove the 20 requirement" after an under-$20 search.
     */
    private function active_constraint_types($context) {
        $context = is_array($context) ? $context : array();
        $analysis = !empty($context['activeAnalysis']) && is_array($context['activeAnalysis'])
            ? $context['activeAnalysis']
            : (!empty($context['analysis']) && is_array($context['analysis']) ? $context['analysis'] : array());
        $types = array();

        if (!empty($analysis['color_terms']) || !empty($analysis['requested_color_labels']) || !empty($analysis['negative_color_terms']) || !empty($analysis['negative_color_labels'])) {
            $types[] = 'color';
        }
        if (!empty($analysis['size_terms']) || !empty($analysis['requested_size_labels']) || !empty($analysis['negative_size_terms']) || !empty($analysis['negative_size_labels'])) {
            $types[] = 'size';
        }
        if (!empty($analysis['price_range'])) {
            $types[] = 'price';
        }
        if (!empty($analysis['sale_required']) || (!empty($analysis['intent']) && $analysis['intent'] === 'sale')) {
            $types[] = 'sale';
        }
        if (!empty($analysis['in_stock_only'])) {
            $types[] = 'stock';
        }
        if (!empty($analysis['modifier_terms']) || !empty($analysis['modifier_labels'])) {
            $types[] = 'preference';
        }

        return array_values(array_unique($types));
    }

    private function compare_command($text, $context) {
        if (!preg_match('/^compare\b/u', $text)) {
            return array();
        }

        $positions = $this->all_ordinal_indices($text);
        $ids = array();
        $named_products = array();
        $reference_ids = !empty($context['lastMultiProductIds'])
            ? (array) $context['lastMultiProductIds']
            : (!empty($context['referenceProductIds'])
                ? (array) $context['referenceProductIds']
                : (!empty($context['productIds']) ? (array) $context['productIds'] : array()));

        $missing_positions = array();
        foreach ($positions as $position) {
            if (isset($reference_ids[$position])) {
                $ids[] = absint($reference_ids[$position]);
            } else {
                $missing_positions[] = absint($position);
            }
        }

        // Explicit product names are resolved later against the visible catalog.
        // Never fall back each name to the first product from old chat context.
        if (count($positions) < 2) {
            $comparison_text = preg_replace('/^compare\s+/u', '', $text);
            $parts = preg_split('/\s+(?:and|with|vs\.?|versus)\s+/u', (string) $comparison_text);
            foreach ((array) $parts as $part) {
                $part = trim(preg_replace('/^(?:the|a|an)\s+/u', '', trim((string) $part)));
                if ($part !== '' && !in_array($part, $named_products, true)) {
                    $named_products[] = $part;
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        return array(
            'action' => 'compare',
            'args' => array(
                'positions' => $positions,
                'productIds' => array_slice($ids, 0, 4),
                'namedProducts' => array_slice($named_products, 0, 4),
                'explicitNames' => count($named_products) >= 2,
                'ordinalComparison' => !empty($positions),
                'availableResultCount' => count($reference_ids),
                'missingPositions' => array_values(array_unique($missing_positions)),
            ),
        );
    }

    private function comparison_followup_command($text, $context) {
        $previous_action = !empty($context['action']) ? sanitize_key((string) $context['action']) : '';
        if ($previous_action !== 'compare') {
            return array();
        }

        $reference_ids = !empty($context['lastMultiProductIds'])
            ? (array) $context['lastMultiProductIds']
            : (!empty($context['referenceProductIds'])
                ? (array) $context['referenceProductIds']
                : (!empty($context['productIds']) ? (array) $context['productIds'] : array()));
        $reference_ids = array_slice(array_values(array_unique(array_filter(array_map('absint', $reference_ids)))), 0, 4);
        if (count($reference_ids) < 2) {
            return array();
        }

        $question = '';
        if (preg_match('/\b(?:difference|differences|different|differ)\b/u', $text)
            && preg_match('/\b(?:these|those|them|two|products?|ones?)\b/u', $text)) {
            $question = 'difference';
        } elseif (preg_match('/\b(?:which|what)\s+(?:one\s+)?(?:is|costs?|has)\s+(?:the\s+)?(?:cheaper|less expensive|lower priced|lower price)\b/u', $text)
            || preg_match('/\bwhich\s+(?:one\s+)?costs?\s+less\b/u', $text)) {
            $question = 'cheaper';
        } elseif (preg_match('/\b(?:better|higher|best|highest)\s+(?:customer\s+)?(?:rating|rated|reviews?)\b/u', $text)
            || preg_match('/\bwhich\s+(?:one\s+)?has\s+(?:the\s+)?better\s+(?:rating|reviews?)\b/u', $text)) {
            $question = 'rating';
        }

        if ($question === '') {
            return array();
        }

        return array(
            'action' => 'compare',
            'args' => array(
                'productIds' => $reference_ids,
                'comparisonQuestion' => $question,
                'availableResultCount' => count($reference_ids),
            ),
        );
    }

    private function similar_command($text, $context) {
        if (!$this->matches($text, array(
            '/\b(?:show|find|give me|do you have)\s+(?:something|products?|options?)?\s*(?:similar to|like|related to|alternatives? to)\b/u',
            '/\b(?:something|products?|options?)\s+(?:similar to|like|related to)\b/u',
            '/\b(?:similar to|alternatives? to)\s+(?:this|that|it|the|a|an|[\p{L}\p{N}])/u',
            '/\bmore like (?:this|that|it|the)\b/u',
        ))) {
            return array();
        }

        $reference = $this->resolve_reference($text, $context);
        return array(
            'action' => 'similar',
            'args' => $reference,
        );
    }

    private function resolve_reference($text, $context) {
        $current_ids = !empty($context['productIds']) ? array_values((array) $context['productIds']) : array();
        $current_names = !empty($context['productNames']) ? array_values((array) $context['productNames']) : array();
        $multi_ids = !empty($context['lastMultiProductIds'])
            ? array_values((array) $context['lastMultiProductIds'])
            : (!empty($context['referenceProductIds']) ? array_values((array) $context['referenceProductIds']) : array());
        $multi_names = !empty($context['lastMultiProductNames'])
            ? array_values((array) $context['lastMultiProductNames'])
            : (!empty($context['referenceProductNames']) ? array_values((array) $context['referenceProductNames']) : array());
        $selected = !empty($context['selectedProductId']) ? absint($context['selectedProductId']) : 0;

        // Ordinals always refer to the latest preserved multi-product list.
        $ordinal = $this->first_ordinal_index($text);
        $ordinal_ids = !empty($multi_ids) ? $multi_ids : $current_ids;
        $ordinal_names = !empty($multi_names) ? $multi_names : $current_names;
        if ($ordinal !== null && isset($ordinal_ids[$ordinal])) {
            return array(
                'productId' => absint($ordinal_ids[$ordinal]),
                'selectionIndex' => $ordinal,
                'productName' => isset($ordinal_names[$ordinal]) ? wp_strip_all_tags($ordinal_names[$ordinal]) : '',
            );
        }

        // Explicit names may refer either to the visible result or the preserved list.
        $pairs = array();
        foreach (array(
            array($current_ids, $current_names),
            array($multi_ids, $multi_names),
        ) as $list) {
            foreach ((array) $list[0] as $index => $id) {
                $id = absint($id);
                if (!$id || isset($pairs[$id])) {
                    continue;
                }
                $pairs[$id] = isset($list[1][$index]) ? wp_strip_all_tags($list[1][$index]) : '';
            }
        }

        $normalized_text = ' ' . $this->normalize($text) . ' ';
        $best = array('score' => 0, 'id' => 0, 'name' => '');
        foreach ($pairs as $id => $name) {
            $normalized_name = $this->normalize($name);
            if ($normalized_name === '') {
                continue;
            }

            $score = 0;
            if (strpos($normalized_text, ' ' . $normalized_name . ' ') !== false) {
                $score += 100;
            }

            foreach (preg_split('/\s+/u', $normalized_name) as $token) {
                if ($this->token_length($token) < 4 || in_array($token, array('with', 'and', 'the', 'for'), true)) {
                    continue;
                }
                if (strpos($normalized_text, ' ' . $token . ' ') !== false) {
                    $score += 20;
                }
            }

            if ($score > $best['score']) {
                $best = array('score' => $score, 'id' => absint($id), 'name' => $name);
            }
        }

        if ($best['id'] && $best['score'] >= 20) {
            return array(
                'productId' => $best['id'],
                'selectionIndex' => null,
                'productName' => $best['name'],
            );
        }

        if ($selected && preg_match('/\b(?:it|that|this|that one|this one|the product)\b/u', $text)) {
            return array('productId' => $selected, 'selectionIndex' => null, 'productName' => '');
        }

        if (!empty($current_ids)) {
            return array(
                'productId' => absint($current_ids[0]),
                'selectionIndex' => 0,
                'productName' => isset($current_names[0]) ? wp_strip_all_tags($current_names[0]) : '',
            );
        }

        if (!empty($multi_ids)) {
            return array(
                'productId' => absint($multi_ids[0]),
                'selectionIndex' => 0,
                'productName' => isset($multi_names[0]) ? wp_strip_all_tags($multi_names[0]) : '',
            );
        }

        return array('productId' => 0, 'selectionIndex' => null, 'productName' => '');
    }

    private function all_ordinal_indices($text) {
        $map = array(
            'first' => 0, '1st' => 0,
            'second' => 1, '2nd' => 1,
            'third' => 2, '3rd' => 2,
            'fourth' => 3, '4th' => 3,
            'fifth' => 4, '5th' => 4,
            'sixth' => 5, '6th' => 5,
            'seventh' => 6, '7th' => 6,
            'eighth' => 7, '8th' => 7,
            'ninth' => 8, '9th' => 8,
            'tenth' => 9, '10th' => 9,
        );
        $found = array();
        if (preg_match_all('/\b(first|1st|second|2nd|third|3rd|fourth|4th|fifth|5th|sixth|6th|seventh|7th|eighth|8th|ninth|9th|tenth|10th)\b/u', $text, $matches)) {
            foreach ($matches[1] as $word) {
                if (isset($map[$word])) {
                    $found[] = $map[$word];
                }
            }
        }
        return array_values(array_unique($found));
    }

    private function first_ordinal_index($text) {
        $positions = $this->all_ordinal_indices($text);
        return isset($positions[0]) ? $positions[0] : null;
    }

    private function matches($text, $patterns) {
        foreach ((array) $patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    private function clean_text($text) {
        $text = wp_strip_all_tags((string) $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim((string) $text);
        return function_exists('mb_substr') ? mb_substr($text, 0, 1000) : substr($text, 0, 1000);
    }

    private function normalize($text) {
        $text = $this->clean_text($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = str_replace(array('’', "'"), '', $text);
        $text = preg_replace('/[^\p{L}\p{N}\$£€\-\s]+/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }

    private function token_length($text) {
        return function_exists('mb_strlen') ? mb_strlen((string) $text, 'UTF-8') : strlen((string) $text);
    }
}
