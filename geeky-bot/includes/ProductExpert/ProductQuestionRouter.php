<?php
namespace GeekyBot\ProductExpert;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detects WooCommerce product-fact questions before catalog search runs.
 *
 * The router is intentionally deterministic. It identifies broad commerce fact
 * families instead of converting shopper questions into product-search terms.
 */
class ProductQuestionRouter {
    public function route($message) {
        $message = $this->clean($message);
        $lower = $this->normalize($message);

        if ($message === '' || $this->is_discovery_request($lower) || $this->is_store_policy_request($lower)) {
            return $this->empty_route();
        }

        $facts = array();
        $patterns = array(
            'material' => '/\b(material|fabric|made\s+(?:of|from)|what\s+is\s+it\s+made)\b/u',
            'water_protection' => '/\b(waterproof|water[-\s]?resistant|water\s+protection|submerge|submerged|splash(?:es|proof|[-\s]?resistant)?)\b/u',
            'compatibility' => '/\b(compatible|compatibility|work\s+with|works\s+with|fit\s+(?:a|an|my|the)?|fits\s+(?:a|an|my|the)?|support(?:s|ed)?\s+(?:a|an|my|the)?|device(?:s)?\s+compatible)\b/u',
            'use_case' => '/\b(suitable\s+for|good\s+for|made\s+for|intended\s+for|use\s+case|office\s+use|travel\s+use|daily\s+use|gym\s+use|sports\s+use)\b/u',
            'dimensions' => '/\b(dimensions?|measurements?|how\s+big|length|width|height)\b/u',
            'weight' => '/\b(weight|weighs?|how\s+heavy)\b/u',
            'warranty' => '/\b(warranty|guarantee|guaranty)\b/u',
            'price' => '/\b(price|cost|how\s+much|current\s+price|regular\s+price|sale\s+price)\b/u',
            'stock' => '/\b(in\s+stock|out\s+of\s+stock|available|availability|order\s+(?:it|this|now)|backorder|back-order|can\s+i\s+(?:buy|order))\b/u',
            'colors' => '/\b(colou?rs?|another\s+colou?r|which\s+colou?r)\b/u',
            'sizes' => '/\b(sizes?|which\s+size|come\s+in\s+size)\b/u',
            'sale' => '/\b(on\s+sale|sale\s+price|discount(?:ed)?|which\s+.*\s+sale)\b/u',
            'included' => '/\b(include(?:s|d)?|included|comes?\s+with|in\s+the\s+box|accessories?)\b/u',
            'battery' => '/\b(battery\s+(?:life|duration|runtime|lasts?)|playback(?:\s+time)?|runtime|how\s+long(?:\s+does|\s+will)?.*\b(?:battery|last))\b/u',
            // `washable` and `dishwasher-safe` are the usual shopper phrasings and
            // neither matched: \bwash\b stops at the `a` in "washable".
            'care' => '/\b(care|wash|washes|washable|washing|dishwasher|dishwasher[-\s]?safe|machine\s+wash(?:able)?|hand\s+wash(?:able)?|clean|cleanable|microwave|microwaveable|tumble\s+dry|dry\s+clean)\b/u',
            'leak_protection' => '/\b(leak[-\s]?proof|leak[-\s]?resistant|leak|loose\s+(?:in|inside)\s+(?:a|my)\s+bag)\b/u',
            'backlight' => '/\b(backlight|backlit)\b/u',
            'capacity' => '/\b(capacity|how\s+many\s+(?:ml|litres?|liters?)|\d+\s*(?:ml|l|litres?|liters?))\b/u',
            'options' => '/\b(which|what)\s+.*\b(options?|variations?|versions?)\b/u',
        );

        foreach ($patterns as $fact => $pattern) {
            if (preg_match($pattern, $lower)) {
                $facts[] = $fact;
            }
        }

        if ($this->is_general_detail_question($lower)) {
            $facts[] = 'summary';
        }

        if (empty($facts) && preg_match('/^(does|do|is|are|can|will)\b/u', $lower)
            && preg_match('/\b(have|has|support|supports|convert|converts|free|safe|charging|rfid|latex|voltage)\b/u', $lower)) {
            $facts[] = 'attribute_query';
        }

        if (preg_match('/^do\s+you\s+have\b/u', $lower)
            && preg_match('/\b(size|colou?r|black|grey|gray|navy|blue|red|white|olive|sage|xl|xxl|iphone|galaxy|\d+\s*(?:ml|l))\b/u', $lower)) {
            $facts[] = 'stock';
        }

        $facts = array_values(array_unique($facts));
        $is_question_shape = $this->has_question_shape($lower);
        $is_question = !empty($facts) && $is_question_shape;

        if (!$is_question) {
            return $this->empty_route();
        }

        $subject_text = $this->subject_text($lower);
        $option_facts = array_intersect($facts, array('colors', 'sizes', 'options'));
        $fallback_to_search = preg_match('/^do\s+you\s+have\b/u', $lower) === 1
            || (!empty($option_facts) && $subject_text !== '');

        return array(
            'handledCandidate' => true,
            'facts' => $facts,
            'normalizedMessage' => $lower,
            'subjectText' => $subject_text,
            'asksAvailability' => in_array('stock', $facts, true) || preg_match('/\b(currently\s+available|available\s+now)\b/u', $lower),
            'asksExactVariation' => $this->asks_exact_variation($lower),
            'asksAvailableOptions' => $this->asks_available_options($lower),
            'asksUnavailableOptions' => preg_match('/\b(out\s+of\s+stock|unavailable|not\s+available)\b/u', $lower) === 1,
            // A color, size, or option question may name an exact product or a
            // broad catalog family. Product Expert gets the first chance to
            // resolve an exact/current product. When no single product can be
            // resolved, hand a real subject such as "hoodie" or "shoes" back
            // to catalog discovery instead of showing an ambiguity dead end.
            'fallbackToSearchWhenUnresolved' => $fallback_to_search,
            'isQuestion' => true,
        );
    }

    private function is_store_policy_request($lower) {
        return preg_match('/\b(shipping\s+(?:cost|price|policy|time)|how\s+much\s+is\s+shipping|delivery\s+(?:time|date|policy|cost)|return\s+policy|refund\s+policy|exchange\s+policy|privacy\s+policy|payment\s+methods?)\b/u', $lower) === 1;
    }

    private function is_discovery_request($lower) {
        // Whole-catalog questions are discovery requests, not questions about
        // one selected product.
        if (preg_match('/^(?:what|which)\s+(?:products?|items?)\b/u', $lower)) {
            return true;
        }

        if (preg_match('/^(show|find|recommend|suggest|list|give|help\s+me\s+find|i\s+need|i\s+want|i\s+am\s+looking|looking\s+for)\b/u', $lower)) {
            return true;
        }

        // Polite and noun-shaped recommendation requests are also discovery.
        // Route these before fact detection so a phrase such as "waterproof"
        // remains a shopper preference instead of triggering Product Expert.
        if (preg_match('/^(?:can|could|would|will)\s+you\s+(?:please\s+)?(?:recommend|suggest)\b/u', $lower)
            || preg_match('/^(?:what|which)\s+(?:is|are)\s+(?:your|the)\s+(?:best\s+)?(?:recommendations?|suggestions?)\b/u', $lower)
            || preg_match('/^(?:give|show)\s+me\s+(?:(?:your|the|a|some)\s+)?(?:best\s+)?(?:recommendations?|suggestions?)\b/u', $lower)) {
            return true;
        }

        // Recommendation-shaped family requests are catalog discovery, not a
        // factual question about one already selected product. Keep option
        // questions such as "which color do you recommend" in Product Expert.
        if (preg_match('/^(?:which|what)\b.+\b(?:do|would|can|could)\s+you\s+(?:recommend|suggest)\b/u', $lower)
            && !preg_match('/^(?:which|what)\s+(?:colou?rs?|sizes?|options?|variations?)\b/u', $lower)) {
            return true;
        }

        // "Which products..." is discovery, while "Which colours..." is Q&A.
        return preg_match('/^which\s+(products?|items?|backpacks?|shoes?|hoodies?|cases?|jackets?|mugs?|bottles?)\b/u', $lower) === 1;
    }

    private function has_question_shape($lower) {
        if (strpos($lower, '?') !== false) {
            return true;
        }

        return preg_match('/^(what|which|is|are|does|do|can|could|will|would|how|tell\s+me|describe)\b/u', $lower) === 1;
    }

    private function is_general_detail_question($lower) {
        return preg_match('/\b(tell\s+me\s+(?:more\s+)?about|tell\s+me\s+more|what\s+about|describe|product\s+details?|what\s+can\s+you\s+tell\s+me)\b/u', $lower) === 1;
    }

    private function asks_exact_variation($lower) {
        if (preg_match('/\b(?:size|colou?r|device|capacity)\b/u', $lower) && preg_match('/\b(in\s+stock|available|price|sale|cost|come\s+in)\b/u', $lower)) {
            return true;
        }

        return preg_match('/\b\d+\s*(?:ml|l)\b/u', $lower) === 1;
    }

    private function asks_available_options($lower) {
        return preg_match('/\b(which|what)\s+.*\b(colou?rs?|sizes?|options?|variations?)\b.*\b(available|in\s+stock|sale)\b/u', $lower) === 1
            || preg_match('/\bwhich\s+.*\b(colou?rs?|sizes?|options?)\s+are\s+currently\s+available\b/u', $lower) === 1;
    }

    /**
     * Best guess at the product a question is about, or '' when it names none.
     *
     * Extraction is subtractive: strip the question wording, strip the fact
     * wording, and treat whatever survives as the product the shopper meant.
     * That makes leftover grammar dangerous. "what material is used?" reduced to
     * the subject "is used", and ProductReferenceResolver then compared the
     * product the shopper had just selected against that fragment. The
     * comparison failed, so the bot replied "I'm not sure which product you
     * mean" to a question it had invited one turn earlier.
     *
     * '' is the honest answer for a question that names no product. It lets the
     * resolver stay on the selected product instead of testing it against a
     * scrap of English.
     */
    private function subject_text($lower) {
        $text = $lower;
        $patterns = array(
            // Runs before the leading question word is stripped, because "how"
            // is the only thing marking these adjectives as a measurement rather
            // than part of a product name. Removing a bare `long` or `big` would
            // corrupt real titles such as "Long Sleeve Tee".
            '/\bhow\s+(?:heavy|big|large|small|long|wide|tall|deep|many|much)\b/u',
            '/^(what|which|is|are|does|do|can|could|will|would|how|tell\s+me(?:\s+more)?(?:\s+about)?|describe)\b/u',
            // Multi-word fact phrases come first: alternation is first-match-wins,
            // so a bare `dishwasher` alternative placed earlier would strip only
            // half of "dishwasher safe" and leave "safe" behind as a fake subject.
            '/\b(machine\s+wash(?:able)?|hand\s+wash(?:able)?|dishwasher[-\s]?safe|microwave[-\s]?safe|tumble\s+dry|dry\s+clean|made\s+(?:of|from)|water[-\s]?resistant|suitable\s+for|good\s+for|intended\s+for|use\s+case|in\s+stock|out\s+of\s+stock|sale\s+price|on\s+sale|comes?\s+with|leak[-\s]?proof|leak[-\s]?resistant|product\s+details?|material|fabric|waterproof|warranty|guarantee|price|cost|dimensions?|measurements?|weight|weigh|compatible|compatibility|available|availability|colou?rs?|sizes?|included|battery|playback|care|wash(?:able|es|ing)?|dishwasher|microwave|backlight|backlit|capacity|options?|variations?)\b/u',
            '/\b(the|this|that|it|one|currently|now|please|from|for|with|have|has|made|come|comes)\b/u',
            // Residual grammar. Without this an auxiliary or participle left
            // behind by the strips above is mistaken for a product name.
            '/\b(a|an|any|some|is|are|am|was|were|be|been|being|do|does|did|use|used|using|last|lasts|take|takes|there|of|in|on|at|to|and|or|but|its|their|your|my|much|many|kind|sort|type)\b/u',
            '/\?+$/u',
        );
        $text = preg_replace($patterns, ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        $text = trim($text);

        // Nothing under three characters identifies a product on its own, so a
        // remainder of only stray fragments means there is no subject at all.
        $meaningful = array_filter(explode(' ', $text), array($this, 'is_subject_token'));

        return empty($meaningful) ? '' : $text;
    }

    private function is_subject_token($token) {
        $length = function_exists('mb_strlen') ? mb_strlen((string) $token, 'UTF-8') : strlen((string) $token);
        return $length >= 3;
    }

    private function empty_route() {
        return array(
            'handledCandidate' => false,
            'facts' => array(),
            'normalizedMessage' => '',
            'subjectText' => '',
            'asksAvailability' => false,
            'asksExactVariation' => false,
            'asksAvailableOptions' => false,
            'asksUnavailableOptions' => false,
            'fallbackToSearchWhenUnresolved' => false,
            'isQuestion' => false,
        );
    }

    private function clean($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim((string) $value);
    }

    private function normalize($value) {
        $value = strtolower(remove_accents($this->clean($value)));
        $value = str_replace(array('–', '—', '_'), array('-', '-', ' '), $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
