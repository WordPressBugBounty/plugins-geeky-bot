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
    const REVISION = 'gb-shopping-commands-2026.08.01.1';

    /**
     * Actions that act on the cart, an order, a discount or a person.
     *
     * Public because the context layer and the chat router both have to
     * recognise them, and three copies of the same list is how one of them
     * ends up missing an action nobody notices for a release.
     *
     * @return array
     */
    public static function commerce_actions() {
        return array('cart_add', 'cart_remove', 'cart_quantity', 'cart_view', 'checkout', 'deals', 'handoff');
    }

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

        // Commerce actions are resolved before the refinement commands below.
        // "add second product" has to reach the cart, not be read as a request
        // to narrow the current result set.
        $commerce = $this->commerce_command($raw, $text, $context);
        if (!empty($commerce)) {
            return array_merge($empty, $commerce);
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
        $request = $this->comparison_request($text);
        if (empty($request)) {
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
            foreach ((array) $request['namedProducts'] as $part) {
                $part = trim(preg_replace('/^(?:the|a|an)\s+/u', '', trim((string) $part)));
                if ($part !== '' && !in_array($part, $named_products, true)) {
                    $named_products[] = $part;
                }
            }
        }

        // "the cheaper one" describes something already on screen rather than
        // naming a product, so resolving it as a name finds nothing and the
        // comparison loses a side. Pricing it needs the catalog, which this
        // class does not read, so the description is reported and ChatService
        // turns it into a position.
        $price_references = array();
        foreach ($named_products as $index => $name) {
            $marker = $this->price_reference_marker($name);
            if ($marker === '') {
                continue;
            }
            $price_references[] = $marker;
            unset($named_products[$index]);
        }
        $named_products = array_values($named_products);

        $ids = array_values(array_unique(array_filter($ids)));
        return array(
            'action' => 'compare',
            'args' => array(
                'positions' => $positions,
                'productIds' => array_slice($ids, 0, 4),
                'namedProducts' => array_slice($named_products, 0, 4),
                'priceReferences' => array_slice($price_references, 0, 2),
                'explicitNames' => count($named_products) >= 2,
                'ordinalComparison' => !empty($positions),
                'availableResultCount' => count($reference_ids),
                'missingPositions' => array_values(array_unique($missing_positions)),
                'comparisonQuestion' => !empty($request['comparisonQuestion'])
                    ? $request['comparisonQuestion']
                    : '',
            ),
        );
    }

    /**
     * Whether a comparison phrase points at a product by price rather than by
     * name, and at which end of the range.
     *
     * @param string $phrase One side of a comparison, as written.
     * @return string 'cheapest', 'priciest', or '' when it names something.
     */
    private function price_reference_marker($phrase) {
        $phrase = $this->normalize((string) $phrase);
        if ($phrase === '') {
            return '';
        }

        if (preg_match('/\b(?:cheaper|cheapest|less\s+expensive|lower\s+priced?|budget)\b/u', $phrase)) {
            return 'cheapest';
        }

        if (preg_match('/\b(?:pricier|priciest|dearer|more\s+expensive|most\s+expensive|higher\s+priced?|premium)\b/u', $phrase)) {
            return 'priciest';
        }

        return '';
    }

    /**
     * Recognizes explicit comparison wording and extracts only the named
     * product phrases. The patterns are deliberately anchored so ordinary
     * product searches containing words such as "different" or "better" are
     * not converted into Commerce Pro comparison commands.
     */
    private function comparison_request($text) {
        $text = trim((string) $text);
        $parts = array();
        $question = '';

        if (preg_match('/^compare\b(?:\s+(.+))?$/u', $text, $matches)) {
            $comparison_text = isset($matches[1]) ? trim((string) $matches[1]) : '';
            $parts = $this->split_comparison_names($comparison_text);
        } elseif (preg_match('/^(?:what(?:s|\s+is|\s+are)?\s+)?(?:the\s+)?differences?\s+between\s+(.+?)\s+and\s+(.+)$/u', $text, $matches)) {
            $parts = array($matches[1], $matches[2]);
            $question = 'difference';
        } elseif (preg_match('/^how\s+(?:is|are)\s+(.+?)\s+and\s+(.+?)\s+different(?:\s+from\s+each\s+other)?$/u', $text, $matches)) {
            $parts = array($matches[1], $matches[2]);
            $question = 'difference';
        } elseif (preg_match('/^how\s+do(?:es)?\s+(.+?)\s+and\s+(.+?)\s+differ(?:\s+from\s+each\s+other)?$/u', $text, $matches)) {
            $parts = array($matches[1], $matches[2]);
            $question = 'difference';
        } elseif (preg_match('/^how\s+does\s+(.+?)\s+differ\s+from\s+(.+)$/u', $text, $matches)) {
            $parts = array($matches[1], $matches[2]);
            $question = 'difference';
        } elseif (preg_match('/^which\s+(?:one\s+)?is\s+better\s+(?:between\s+)?(.+?)\s+(?:or|and)\s+(.+)$/u', $text, $matches)) {
            $parts = array($matches[1], $matches[2]);
        } elseif (preg_match('/^(.+?)\s+(?:vs|versus)\s+(.+)$/u', $text, $matches)) {
            $parts = array($matches[1], $matches[2]);
            $question = 'difference';
        } else {
            return array();
        }

        $clean = array();
        foreach ((array) $parts as $part) {
            $part = trim(preg_replace('/^(?:the|a|an)\s+/u', '', trim((string) $part)));
            if ($part !== '' && !in_array($part, $clean, true)) {
                $clean[] = $part;
            }
        }

        return array(
            'namedProducts' => array_slice($clean, 0, 4),
            'comparisonQuestion' => $question,
        );
    }

    /**
     * Splits the original "compare" command without treating "with" inside a
     * product title (for example, "Hoodie with Logo") as a separator when an
     * unambiguous "and", "vs", or "versus" separator is also present.
     */
    private function split_comparison_names($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return array();
        }

        if (preg_match('/\s+(?:vs|versus)\s+/u', $text)) {
            return preg_split('/\s+(?:vs|versus)\s+/u', $text);
        }
        if (preg_match('/\s+and\s+/u', $text)) {
            return preg_split('/\s+and\s+/u', $text);
        }
        if (preg_match('/\s+with\s+/u', $text)) {
            return preg_split('/\s+with\s+/u', $text, 2);
        }

        return array();
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

    /**
     * Shopping actions that act on the cart, the order or a person.
     *
     * The assistant already understood the hard half of these: "add second
     * product" resolves the ordinal to the right product, and always did. What
     * it lacked was anywhere to send the verb, so the reference was resolved and
     * the action silently dropped, leaving a catalog search in its place.
     *
     * The reference itself is resolved with the same helper the comparison
     * commands use, so "second", "the Trek 32L" and "it" mean here exactly what
     * they mean everywhere else in the conversation.
     *
     * Attribute choices are captured as written rather than parsed into pairs.
     * "Blue, Logo Yes" only means something against a product's real attribute
     * names, and the catalog lives on the addon side of this boundary.
     *
     * @param string $raw     Original message.
     * @param string $text    Normalised message.
     * @param array  $context Conversation context.
     * @return array Action and args, or an empty array.
     */
    private function commerce_command($raw, $text, $context) {
        if ($text === '') {
            return array();
        }

        // A person, not a product. Checked first: "problem with my order" must
        // not be read as an order lookup or a shipping question.
        if ($this->matches($text, array(
            '/\b(?:complain|complaint|complaining)\b/u',
            '/\bproblem\s+with\s+my\s+(?:order|purchase|delivery|parcel|item)\b/u',
            '/\b(?:talk|speak|chat)\s+to\s+(?:a\s+)?(?:human|person|agent|someone|representative)\b/u',
            '/\b(?:human|live)\s+(?:help|agent|support)\b/u',
            '/\bi\s+want\s+to\s+(?:complain|report\s+a\s+problem)\b/u',
        ))) {
            return array('action' => 'handoff', 'args' => array('message' => $raw));
        }

        // Only discount codes. "show sale items" and "cheapest today" already
        // work as ordinary catalog searches, and claiming them here would take
        // working behaviour away to hand it to a feature that may be disabled.
        if ($this->matches($text, array(
            '/\b(?:coupon|voucher)\b/u',
            '/\b(?:promo|discount|offer)\s*code\b/u',
        ))) {
            return array('action' => 'deals', 'args' => array());
        }

        if ($this->matches($text, array(
            '/^(?:go\s+to\s+)?check\s?out$/u',
            '/^(?:continue|proceed)\s+to\s+check\s?out$/u',
            '/^take\s+me\s+to\s+check\s?out$/u',
        ))) {
            return array('action' => 'checkout', 'args' => array());
        }

        if ($this->matches($text, array(
            '/^(?:show|view|open|see|display|check)?\s*(?:me\s+)?(?:my\s+)?(?:cart|basket|bag)$/u',
            '/^what(?:\x27s| is)\s+in\s+my\s+(?:cart|basket|bag)$/u',
        ))) {
            return array('action' => 'cart_view', 'args' => array());
        }

        // Quantity is only a quantity command when the shopper says so. "add 2
        // belts to my cart" is an add that carries a quantity, not a change to
        // an existing line.
        if (preg_match('/\bquantit(?:y|ies)\b/u', $text)
            && preg_match('/\b(\d{1,3})\b/u', $text, $number)) {
            $reference = $this->commerce_reference($raw, $text, $context);
            return array(
                'action' => 'cart_quantity',
                'args' => $this->commerce_args($reference, array('quantity' => max(0, (int) $number[1]))),
            );
        }

        if ($this->matches($text, array(
            '/^(?:remove|delete|drop)\b/u',
            '/\b(?:remove|delete|drop|take\s+out)\b[^.]{0,40}\b(?:cart|basket|bag)\b/u',
        ))) {
            $reference = $this->commerce_reference($raw, $text, $context);
            return array(
                'action' => 'cart_remove',
                'args' => $this->commerce_args($reference, array()),
            );
        }

        // "Choose Black for the water bottle and add it to my cart" -- the verb
        // is at the end, so an opening-word test never sees it.
        $adds = $this->matches($text, array(
            '/^(?:add|put|place)\b/u',
            '/\badd\b[^.]{0,60}\b(?:to\s+)?(?:my\s+|the\s+)?(?:cart|basket|bag)\b/u',
            '/\b(?:buy|order)\s+(?:it|this|that|these|them)\s+now\b/u',
        ));

        if ($adds) {
            $reference = $this->commerce_reference($raw, $text, $context);
            $extra = array('quantity' => $this->commerce_quantity($text));

            $attributes = $this->commerce_attributes($raw);
            if ($attributes !== '') {
                $extra['attributesText'] = $attributes;
            }

            return array(
                'action' => 'cart_add',
                'args' => $this->commerce_args($reference, $extra),
            );
        }

        return array();
    }

    /**
     * Which product a commerce command acts on, or none.
     *
     * resolve_reference() falls back to the first product on screen when it
     * recognises nothing, which is the right instinct for refining a result set
     * and the wrong one for the cart: "make belt quantity 3" would have set the
     * quantity of whatever happened to be listed first. A command that acts on
     * the shopper's money only proceeds on a reference it actually matched.
     *
     * When no product on screen matches, the phrase is passed through as a
     * query for the addon to resolve against the catalog, because the named
     * product need not be one of the visible results at all.
     *
     * @param string $raw     Original message.
     * @param string $text    Normalised message.
     * @param array  $context Conversation context.
     * @return array
     */
    private function commerce_reference($raw, $text, $context) {
        $empty = array(
            'productId' => 0,
            'selectionIndex' => null,
            'productName' => '',
            'productQuery' => '',
            'cartSelectionIndex' => null,
        );

        $ordinal = $this->first_ordinal_index($text);

        // An ordinal in a cart command can point at a line in the cart rather
        // than a position in the last search: "remove first product from cart"
        // means the first thing the shopper is holding, not the first thing
        // they last looked at. Which is meant depends on the cart, which this
        // class does not read, so the position is reported alongside the
        // on-screen resolution and the addon prefers whichever it can honour.
        $empty['cartSelectionIndex'] = $ordinal;

        // When the sentence says which list it is counting -- "the first
        // product *from my cart*" -- that settles it, and the last search
        // results must not answer for the cart. Only "from/in", never "to my
        // cart", which is a destination for an add rather than a place to
        // count positions in.
        if ($ordinal !== null
            && preg_match('/\b(?:from|in|out\s+of|inside|within)\s+(?:the\s+|my\s+)?(?:cart|basket|bag)\b/u', $text)) {
            return $empty;
        }

        // An ordinal is otherwise unambiguous: it means a position on screen.
        if ($ordinal !== null) {
            $reference = $this->resolve_reference($text, $context);
            if (!empty($reference['productId'])) {
                return array_merge($empty, $reference);
            }
        }

        // A name the shopper typed that matches something already on screen.
        $names = array();
        foreach (array('lastMultiProductNames', 'productNames', 'referenceProductNames') as $key) {
            if (!empty($context[$key])) {
                $names = array_values((array) $context[$key]);
                break;
            }
        }
        $ids = array();
        foreach (array('lastMultiProductIds', 'productIds', 'referenceProductIds') as $key) {
            if (!empty($context[$key])) {
                $ids = array_values((array) $context[$key]);
                break;
            }
        }

        foreach ($names as $index => $name) {
            $needle = $this->normalize(wp_strip_all_tags((string) $name));
            if ($needle !== '' && strpos($text, $needle) !== false && isset($ids[$index])) {
                return array_merge($empty, array(
                    'productId' => absint($ids[$index]),
                    'selectionIndex' => $index,
                    'productName' => wp_strip_all_tags((string) $name),
                    'productQuery' => '',
                ));
            }
        }

        // Nothing on screen matches, so hand the phrase on rather than acting
        // on a product the shopper did not name.
        $empty['productQuery'] = $this->commerce_product_query($raw);

        // "make it quantity 2" after adding something is a real reference, not
        // a guess -- but which line it points at depends on the cart, which core
        // does not read, so the pronoun is reported and the addon resolves it.
        //
        // Only when the sentence names nothing else. "Choose Black for the
        // Tumbler and add it to my cart" carries both a name and an "it"; taking
        // the pronoun there put the wrong product in the cart.
        if ($empty['productQuery'] === ''
            && preg_match('/\b(?:it|this|that|the\s+same)\b/u', $text)) {
            $empty['referenceIsPronoun'] = true;
        }

        return $empty;
    }

    /**
     * The product phrase inside a command, as written.
     *
     * @param string $raw Original message.
     * @return string
     */
    private function commerce_product_query($raw) {
        $raw = trim((string) $raw);

        $patterns = array(
            '/\bfor\s+(.+?)\s+and\s+(?:add|put|place)\b/iu',
            '/^(?:add|put|place)\s+(?:\d{1,3}\s+)?(.+?)\s+(?:to|into)\s+(?:my\s+|the\s+)?(?:cart|basket|bag)\b/iu',
            '/^(?:make|set|change|update)\s+(.+?)\s+quantit(?:y|ies)\b/iu',
            '/^(?:remove|delete|drop)\s+(?:the\s+)?(.+?)(?:\s+from\s+.*)?$/iu',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $raw, $matches)) {
                $phrase = trim($matches[1]);
                // Strip a leading quantity and trailing filler the verb leaves behind.
                $phrase = preg_replace('/^\d{1,3}\s+/u', '', $phrase);
                $phrase = preg_replace('/\b(?:it|this|that|them|these|product|item)$/iu', '', $phrase);
                $phrase = trim($phrase, " \t\n\r\0\x0B-,");
                if ($phrase !== '' && !preg_match('/^(?:it|this|that|them|these)$/iu', $phrase)) {
                    return $phrase;
                }
            }
        }

        return '';
    }

    /**
     * @param array $reference Output of resolve_reference().
     * @param array $extra     Action-specific arguments.
     * @return array
     */
    private function commerce_args($reference, $extra) {
        $args = array(
            'productId' => !empty($reference['productId']) ? absint($reference['productId']) : 0,
            'productName' => isset($reference['productName']) ? (string) $reference['productName'] : '',
            'productQuery' => isset($reference['productQuery']) ? (string) $reference['productQuery'] : '',
            'referenceIsPronoun' => !empty($reference['referenceIsPronoun']),
            'selectionIndex' => isset($reference['selectionIndex']) ? $reference['selectionIndex'] : null,
            'cartSelectionIndex' => isset($reference['cartSelectionIndex']) ? $reference['cartSelectionIndex'] : null,
        );

        return array_merge($args, $extra);
    }

    /**
     * How many, when an add says so. Defaults to one.
     *
     * @param string $text Normalised message.
     * @return int
     */
    private function commerce_quantity($text) {
        if (preg_match('/\b(\d{1,3})\s*(?:x|pcs?|pieces?|units?)\b/u', $text, $m)) {
            return max(1, (int) $m[1]);
        }
        if (preg_match('/^(?:add|put|place)\s+(\d{1,3})\b/u', $text, $m)) {
            return max(1, (int) $m[1]);
        }

        return 1;
    }

    /**
     * The attribute choice as the shopper wrote it.
     *
     * Kept as text on purpose: "Logo Yes" is only a name/value pair against a
     * product that has an attribute called Logo, and this side of the boundary
     * does not read the catalog.
     *
     * @param string $raw Original message, for its capitalisation.
     * @return string
     */
    private function commerce_attributes($raw) {
        $raw = (string) $raw;

        if (preg_match('/\bchoose\s+(.+?)\s+for\s+/iu', $raw, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\bin\s+((?:[a-z]+\s*,\s*)*[a-z]+)\s+(?:size|colou?r)\b/iu', $raw, $m)) {
            return trim($m[1]);
        }

        return '';
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
