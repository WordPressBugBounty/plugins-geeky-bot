<?php
namespace GeekyBot\Chat;

use GeekyBot\Services\CatalogVisibilityService;
use GeekyBot\Services\ProductDiscoveryIntentService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Guards the commerce engines from treating every shopper utterance as a
 * product search or store-knowledge request.
 *
 * Product Expert factual clarification runs before this router. This router
 * owns general product selection, acknowledgements, statements, vague product
 * references, and "the other one" corrections.
 */
class ConversationActRouter {
    const MAX_CANDIDATES = 4;

    private $selections;

    public function __construct(?PendingProductSelectionResolver $selections = null) {
        $this->selections = $selections ?: new PendingProductSelectionResolver();
    }

    public function route($message, $context_resolution = array()) {
        $message = $this->clean($message);
        $normalized = $this->normalize($message);
        $context_resolution = is_array($context_resolution) ? $context_resolution : array();

        if ($message === '' || $normalized === '') {
            return $this->unhandled();
        }

        // Product Expert owns pending selections that contain an unanswered
        // factual question. General Conversation Guard selections have no
        // originalQuestion and are resolved here before normal search.
        $pending = $this->pending_selection($context_resolution);
        if ($this->selections->is_active($pending) && empty($pending['originalQuestion'])) {
            $selection = $this->selections->resolve($message, $pending);
            if (!empty($selection['resolved']) && !empty($selection['candidate'])) {
                return $this->selection_result($selection['candidate'], 'clarification_selection', true);
            }
            if (!empty($selection['ambiguous'])) {
                return $this->ambiguity_result(
                    (array) ($selection['candidates'] ?? $pending['candidates']),
                    '',
                    $pending
                );
            }
            // A different product, a new search, or a new question cancels the
            // pending selection naturally and continues through normal routing.
        }

        // A bare ordinal after a visible result list is a product selection,
        // not an unresolved reference or a search term. Pending Product Expert
        // clarifications are handled above, so this path owns ordinary result
        // selection such as "the second one".
        $ordinal_selection = $this->resolve_current_ordinal_selection($message, $normalized, $context_resolution);
        if (!empty($ordinal_selection['handled'])) {
            return $ordinal_selection;
        }

        if ($this->is_acknowledgement($normalized)) {
            return $this->handled(
                'acknowledgement',
                __('Glad to help. What would you like to check next?', 'geeky-bot')
            );
        }

        if ($this->is_other_product_correction($normalized)) {
            return $this->resolve_other_product($context_resolution);
        }

        if ($this->is_reference_fragment($normalized)) {
            return $this->resolve_reference_fragment($normalized, $context_resolution);
        }

        // An exact or sufficiently distinctive name from the current result
        // list is a selection, not a broad OR search for related words.
        $explicit_selection = $this->resolve_explicit_current_candidate($message, $normalized, $context_resolution);
        if (!empty($explicit_selection['handled'])) {
            return $explicit_selection;
        }

        if ($this->is_declarative_statement($normalized)) {
            $selected = $this->selected_candidate($context_resolution);
            $reply = $selected
                ? sprintf(
                    /* translators: %s: selected product name. */
                    __('Got it. What would you like to check about %s next?', 'geeky-bot'),
                    $selected['name']
                )
                : __('Got it. What would you like to check next?', 'geeky-bot');

            return $this->handled(
                'statement',
                $reply,
                !empty($selected['id']) ? absint($selected['id']) : 0,
                !empty($selected['name']) ? $selected['name'] : '',
                false
            );
        }

        return $this->unhandled();
    }

    private function resolve_current_ordinal_selection($message, $normalized, $context_resolution) {
        if (!$this->is_ordinal_selection_fragment($normalized)) {
            return $this->unhandled();
        }

        $candidates = $this->current_candidates($context_resolution);
        if (empty($candidates)) {
            return $this->handled(
                'reference_unresolved',
                __('I do not have a current product list to select from. Show some products first.', 'geeky-bot')
            );
        }

        $temporary = $this->selections->make_pending($candidates, 'current_result_ordinal_selection');
        $selection = $this->selections->resolve($message, $temporary);
        if (!empty($selection['resolved']) && !empty($selection['candidate'])) {
            return $this->selection_result($selection['candidate'], 'product_selection', true);
        }

        $position = $this->ordinal_position($normalized);
        $count = count($candidates);
        $reply = $position > 0
            ? sprintf(
                /* translators: 1: number of current products, 2: requested position. */
                _n(
                    'I only have %1$d product in the current results, so there is no product %2$d to select.',
                    'I only have %1$d products in the current results, so there is no product %2$d to select.',
                    $count,
                    'geeky-bot'
                ),
                $count,
                $position
            )
            : __('I could not match that position to the current product results.', 'geeky-bot');

        return $this->handled('reference_unresolved', $reply);
    }

    private function resolve_explicit_current_candidate($message, $normalized, $context_resolution) {
        if (strpos($normalized, '?') !== false
            || $this->starts_new_request($normalized)
            || $this->is_generic_bare_family($normalized)) {
            return $this->unhandled();
        }

        $candidates = $this->current_candidates($context_resolution);
        if (empty($candidates)) {
            return $this->unhandled();
        }

        $candidate_names = array();
        foreach ($candidates as $candidate) {
            if (!empty($candidate['name'])) {
                $candidate_names[] = (string) $candidate['name'];
            }
        }
        if ((new ProductDiscoveryIntentService())->should_search_instead_of_select($message, $candidate_names)) {
            return $this->unhandled();
        }

        $temporary = $this->selections->make_pending($candidates, 'current_result_selection');
        $selection = $this->selections->resolve($message, $temporary);
        $source = !empty($selection['source']) ? sanitize_key((string) $selection['source']) : '';

        if (!empty($selection['resolved'])
            && !empty($selection['candidate'])
            && in_array($source, array('candidate_exact_name', 'candidate_partial_name', 'candidate_token_name'), true)) {
            return $this->selection_result($selection['candidate'], 'product_selection', true);
        }

        return $this->unhandled();
    }

    private function resolve_reference_fragment($normalized, $context_resolution) {
        $candidates = $this->current_candidates($context_resolution);
        $selected = $this->selected_candidate($context_resolution, $candidates);
        $family = $this->reference_family($normalized);

        if ($family !== '') {
            $family_matches = array_values(array_filter($candidates, function ($candidate) use ($family) {
                return $this->candidate_matches_family($candidate, $family);
            }));

            if (count($family_matches) === 1) {
                return $this->selection_result($family_matches[0]);
            }

            if (count($family_matches) > 1) {
                return $this->ambiguity_result($family_matches);
            }

            if ($selected && $this->candidate_matches_family($selected, $family)) {
                return $this->selection_result($selected);
            }

            return $this->handled(
                'reference_unresolved',
                sprintf(
                    /* translators: %s: referenced product family such as bottle. */
                    __('I do not have a current %s selected. Please use the product name or show matching products first.', 'geeky-bot'),
                    $family
                )
            );
        }

        if ($selected && $this->selected_reference_is_safe($context_resolution, $candidates, $selected['id'])) {
            return $this->selection_result($selected);
        }

        if (count($candidates) === 1) {
            return $this->selection_result($candidates[0]);
        }

        if (count($candidates) > 1) {
            return $this->ambiguity_result($candidates);
        }

        return $this->handled(
            'reference_unresolved',
            __('Which product do you mean? Please use the product name or show some products first.', 'geeky-bot')
        );
    }

    private function resolve_other_product($context_resolution) {
        $candidates = $this->current_candidates($context_resolution);
        $selected = $this->selected_candidate($context_resolution, $candidates);

        if (count($candidates) === 2 && $selected) {
            foreach ($candidates as $candidate) {
                if (absint($candidate['id']) !== absint($selected['id'])) {
                    return $this->selection_result($candidate, 'correction', true);
                }
            }
        }

        if (count($candidates) > 1) {
            return $this->ambiguity_result(
                $candidates,
                /* translators: %s: comma-separated product names. */
                __('Which other product do you mean—%s?', 'geeky-bot')
            );
        }

        return $this->handled(
            'correction_unresolved',
            __('I do not have another current product to switch to. Please name the product you mean.', 'geeky-bot')
        );
    }

    private function selection_result($candidate, $act = 'product_reference', $confirmed_selection = false) {
        $candidate = is_array($candidate) ? $candidate : array();
        $product_id = !empty($candidate['id']) ? absint($candidate['id']) : 0;
        $product_name = !empty($candidate['name']) ? wp_strip_all_tags((string) $candidate['name']) : '';

        if (!$product_id || $product_name === '') {
            return $this->unhandled();
        }

        $message = $confirmed_selection
            ? sprintf(
                /* translators: %s: selected product name. */
                __('You selected %s. What would you like to know about it?', 'geeky-bot'),
                $product_name
            )
            : sprintf(
                /* translators: %s: selected product name. */
                __('You are looking at %s. What would you like to know about it?', 'geeky-bot'),
                $product_name
            );

        return $this->handled(
            $act,
            $message,
            $product_id,
            $product_name,
            true,
            array()
        );
    }

    private function ambiguity_result($candidates, $template = '', $pending = array()) {
        $names = array();
        foreach (array_slice((array) $candidates, 0, self::MAX_CANDIDATES) as $candidate) {
            if (!empty($candidate['name'])) {
                $names[] = wp_strip_all_tags((string) $candidate['name']);
            }
        }

        $template = $template !== ''
            ? $template
            /* translators: %s: comma-separated product names. */
            : __('Which product do you mean—%s?', 'geeky-bot');
        $reply = !empty($names)
            ? sprintf($template, $this->human_join($names))
            : __('Which product do you mean? Please use the product name.', 'geeky-bot');

        if (empty($pending)) {
            $pending = $this->selections->make_pending($candidates, 'conversation_reference');
        }

        return $this->handled('reference_ambiguous', $reply, 0, '', false, $pending);
    }

    private function current_candidates($context_resolution) {
        $ids = !empty($context_resolution['previousCurrentProductIds'])
            ? (array) $context_resolution['previousCurrentProductIds']
            : (!empty($context_resolution['previousContext']['productIds'])
                ? (array) $context_resolution['previousContext']['productIds']
                : array());

        $candidates = array();
        foreach (array_slice(array_values(array_unique(array_filter(array_map('absint', $ids)))), 0, self::MAX_CANDIDATES) as $product_id) {
            $candidate = $this->visible_candidate($product_id);
            if (!empty($candidate)) {
                $candidates[] = $candidate;
            }
        }
        return $candidates;
    }

    private function selected_candidate($context_resolution, $candidates = array()) {
        $selected_id = !empty($context_resolution['selectedProductId'])
            ? absint($context_resolution['selectedProductId'])
            : (!empty($context_resolution['previousContext']['selectedProductId'])
                ? absint($context_resolution['previousContext']['selectedProductId'])
                : 0);
        if (!$selected_id) {
            return array();
        }

        foreach ((array) $candidates as $candidate) {
            if (!empty($candidate['id']) && absint($candidate['id']) === $selected_id) {
                return $candidate;
            }
        }

        return $this->visible_candidate($selected_id);
    }

    private function selected_reference_is_safe($context_resolution, $candidates, $selected_id) {
        if (!$selected_id) {
            return false;
        }

        if (count($candidates) <= 1) {
            return true;
        }

        $previous_action = !empty($context_resolution['previousContext']['action'])
            ? sanitize_key((string) $context_resolution['previousContext']['action'])
            : '';

        return in_array($previous_action, array(
            'product_question',
            'conversation_product_reference',
            'conversation_product_selection',
            'conversation_clarification_selection',
            'conversation_correction',
            'conversation_statement',
            'conversation_acknowledgement',
        ), true);
    }

    private function pending_selection($context_resolution) {
        if (!empty($context_resolution['pendingProductSelection']) && is_array($context_resolution['pendingProductSelection'])) {
            return $context_resolution['pendingProductSelection'];
        }
        if (!empty($context_resolution['pendingProductClarification']) && is_array($context_resolution['pendingProductClarification'])) {
            return $context_resolution['pendingProductClarification'];
        }
        if (!empty($context_resolution['previousContext']['pendingProductSelection'])
            && is_array($context_resolution['previousContext']['pendingProductSelection'])) {
            return $context_resolution['previousContext']['pendingProductSelection'];
        }
        if (!empty($context_resolution['previousContext']['pendingProductClarification'])
            && is_array($context_resolution['previousContext']['pendingProductClarification'])) {
            return $context_resolution['previousContext']['pendingProductClarification'];
        }
        return array();
    }

    private function visible_candidate($product_id) {
        if (!$product_id || !function_exists('wc_get_product')) {
            return array();
        }

        $product = wc_get_product(absint($product_id));
        if (!CatalogVisibilityService::is_visible($product, 'conversation_reference')) {
            return array();
        }

        return array(
            'id' => absint($product->get_id()),
            'name' => wp_strip_all_tags((string) $product->get_name()),
        );
    }

    private function candidate_matches_family($candidate, $family) {
        $name = !empty($candidate['name']) ? $this->normalize($candidate['name']) : '';
        if ($name === '' || $family === '') {
            return false;
        }

        $aliases = $this->family_aliases($family);
        foreach ($aliases as $alias) {
            if (preg_match('/\b' . preg_quote($alias, '/') . 's?\b/u', $name)) {
                return true;
            }
        }
        return false;
    }

    private function reference_family($normalized) {
        if (!preg_match('/^(?:the|this|that)\s+([a-z0-9-]+)(?:\s+(?:one|product|item))?$/u', $normalized, $match)) {
            return '';
        }

        $family = sanitize_key((string) $match[1]);
        $generic = array('product', 'item', 'one', 'option', 'thing');
        if ($family === '' || in_array($family, $generic, true)) {
            return '';
        }

        return $this->canonical_family($family);
    }

    private function canonical_family($family) {
        $family = sanitize_key((string) $family);
        $map = array(
            'mugs' => 'mug', 'cups' => 'cup', 'bottles' => 'bottle', 'tumblers' => 'tumbler',
            'backpacks' => 'backpack', 'bags' => 'bag', 'hoodies' => 'hoodie', 'shoes' => 'shoe',
            'sneakers' => 'sneaker', 'jackets' => 'jacket', 'keyboards' => 'keyboard', 'speakers' => 'speaker',
            'chargers' => 'charger', 'wallets' => 'wallet', 'cases' => 'case', 'sleeves' => 'sleeve',
            'organisers' => 'organiser', 'organizers' => 'organizer', 'watches' => 'watch', 'belts' => 'belt',
        );
        return isset($map[$family]) ? $map[$family] : $family;
    }

    private function family_aliases($family) {
        $groups = array(
            'mug' => array('mug', 'cup'),
            'cup' => array('cup', 'mug'),
            'bottle' => array('bottle', 'flask'),
            'tumbler' => array('tumbler'),
            'backpack' => array('backpack', 'rucksack'),
            'bag' => array('bag', 'backpack', 'tote'),
            'shoe' => array('shoe', 'sneaker', 'trainer'),
            'sneaker' => array('sneaker', 'shoe', 'trainer'),
            'case' => array('case', 'cover'),
            'sleeve' => array('sleeve', 'case'),
            'organiser' => array('organiser', 'organizer', 'pouch'),
            'organizer' => array('organizer', 'organiser', 'pouch'),
        );
        return isset($groups[$family]) ? $groups[$family] : array($family);
    }

    private function is_ordinal_selection_fragment($normalized) {
        return preg_match(
            '/^(?:the\s+)?(?:first|second|third|fourth|1st|2nd|3rd|4th)(?:\s+(?:one|product|item|option))?$|^(?:option|product|item|number)\s*[1-4]$/u',
            trim((string) $normalized)
        ) === 1;
    }

    private function ordinal_position($normalized) {
        $map = array(
            'first' => 1, '1st' => 1,
            'second' => 2, '2nd' => 2,
            'third' => 3, '3rd' => 3,
            'fourth' => 4, '4th' => 4,
        );

        if (preg_match('/\b(?:option|product|item|number)\s*([1-4])\b/u', (string) $normalized, $match)) {
            return absint($match[1]);
        }
        foreach ($map as $word => $position) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', (string) $normalized)) {
                return $position;
            }
        }
        return 0;
    }

    private function is_generic_bare_family($normalized) {
        return preg_match(
            '/^(?:product|item|option|mug|cup|bottle|tumbler|backpack|bag|hoodie|shoe|shoes|sneaker|sneakers|jacket|keyboard|speaker|charger|wallet|case|sleeve|organiser|organizer|watch|belt)$/u',
            trim((string) $normalized)
        ) === 1;
    }

    private function is_acknowledgement($normalized) {
        return preg_match(
            '/^(?:yes|yeah|yep|correct|right|exactly|okay|ok|thanks|thank\s+you|got\s+it|understood|that\s+helps|perfect|great|sounds\s+good|makes\s+sense|(?:yes\s+)?that\s+is\s+right)$/u',
            $normalized
        ) === 1;
    }

    private function is_other_product_correction($normalized) {
        return preg_match(
            '/^(?:no\s*[,\-]?\s*)?(?:i\s+meant\s+)?(?:the\s+)?other\s+(?:one|product|item|option)$/u',
            $normalized
        ) === 1;
    }

    private function is_reference_fragment($normalized) {
        if (preg_match('/^(?:it|this|that|this\s+one|that\s+one|the\s+(?:product|item|one|option))$/u', $normalized)) {
            return true;
        }

        return preg_match('/^(?:the|this|that)\s+[a-z0-9-]+(?:\s+(?:one|product|item))?$/u', $normalized) === 1;
    }

    private function is_declarative_statement($normalized) {
        if (strpos($normalized, '?') !== false || $this->starts_new_request($normalized)) {
            return false;
        }

        return preg_match(
            '/^(?:it|this|that|the\s+(?:product|item|one|[a-z0-9-]+))\s+(?:is|has|comes|includes|uses|fits|supports|weighs|costs|looks|seems)\b/u',
            $normalized
        ) === 1;
    }

    private function starts_new_request($normalized) {
        return preg_match(
            '/^(?:show|find|recommend|suggest|list|compare|remove|only|start\s+over|clear|reset|help\s+me\s+find|i\s+need|i\s+want|i\s+am\s+looking|looking\s+for|what|which|is|are|does|do|can|could|will|would|how|tell\s+me|describe)\b/u',
            $normalized
        ) === 1;
    }

    private function handled($act, $message, $product_id = 0, $product_name = '', $show_product = false, $pending_selection = array()) {
        return array(
            'handled' => true,
            'act' => sanitize_key((string) $act),
            'message' => wp_strip_all_tags((string) $message),
            'productId' => absint($product_id),
            'productName' => wp_strip_all_tags((string) $product_name),
            'showProduct' => (bool) $show_product,
            'pendingSelection' => is_array($pending_selection) ? $pending_selection : array(),
            'clearPendingSelection' => empty($pending_selection),
        );
    }

    private function unhandled() {
        return array(
            'handled' => false,
            'act' => '',
            'message' => '',
            'productId' => 0,
            'productName' => '',
            'showProduct' => false,
            'pendingSelection' => array(),
            'clearPendingSelection' => false,
        );
    }

    private function human_join($items) {
        $items = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $items))));
        $count = count($items);
        if ($count < 2) {
            return !empty($items[0]) ? $items[0] : '';
        }
        if ($count === 2) {
            return $items[0] . ' ' . __('or', 'geeky-bot') . ' ' . $items[1];
        }
        $last = array_pop($items);
        return implode(', ', $items) . ', ' . __('or', 'geeky-bot') . ' ' . $last;
    }

    private function clean($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim((string) $value);
    }

    private function normalize($value) {
        $value = strtolower(remove_accents($this->clean($value)));
        $value = str_replace(array('’', '`'), "'", $value);
        $value = preg_replace("/\b(it|that|this|what|there|here|who|how|where|when|why)'s\b/u", '$1 is', $value);
        $value = str_replace(array('–', '—', '_'), array('-', '-', ' '), $value);
        $value = preg_replace('/[^a-z0-9\-\s\?]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
