<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detects store-policy questions before Product Discovery or generic AI.
 *
 * This service intentionally handles a small, allowlisted set of commerce
 * policy intents. It never executes dynamic handlers from shopper input.
 */
class PolicyIntentService {
    /**
     * @param string $message Shopper message.
     * @return array
     */
    public function analyze($message) {
        $normalized = $this->normalize($message);
        $types = $this->matched_types($normalized);
        // A shopper describing what went wrong rarely names the policy. "What if
        // my item arrives damaged?" is a returns question containing no returns
        // word, so the noun vocabulary alone cannot see it.
        $types = array_values(array_unique(array_merge($types, $this->situation_types($normalized))));
        // A question about one specific order's state is never a policy
        // question, whichever vocabulary matched it. "What is the status of my
        // delivery?" carries a shipping noun and reads like policy, but the
        // order record holds that answer and the shipping page does not.
        if ($this->asks_own_order_state($normalized)) {
            $types = array();
        }
        if (in_array('payment', $types, true) && strpos($normalized, 'cash on delivery') !== false) {
            $types = array_values(array_diff($types, array('shipping')));
        }
        $facets = $this->matched_facets($normalized);
        $scope = $this->question_scope($normalized, $types);

        return array(
            'isPolicyQuestion' => !empty($types),
            'isStorePolicyQuestion' => !empty($types) && $scope === 'store',
            'scope' => $scope,
            'primaryType' => !empty($types) ? $types[0] : '',
            'types' => $types,
            'label' => !empty($types) ? $this->label($types[0]) : '',
            'facets' => $facets,
            'specificTerms' => $this->specific_terms($normalized, $types),
            'normalized' => $normalized,
        );
    }

    /**
     * Classify a selected policy document by its public title and text.
     *
     * Page titles are the strongest signal. Body text may add another policy
     * area only when it contains explicit policy-style phrases. This prevents
     * ordinary pages such as Cart from becoming shipping sources merely because
     * they contain incidental words such as "shipping" or "payment".
     *
     * @param string $title Page title.
     * @param string $content Normalized public page content.
     * @return array
     */
    public function document_types($title, $content) {
        $title_normalized = $this->normalize($title);
        $content_normalized = $this->normalize($content);
        $title_types = $this->matched_types($title_normalized);
        // "About", "About Us", "Contact" and "Contact Us" are the near-universal
        // titles for store information. The bare words are far too common to put
        // in the shared phrase vocabulary -- "what about shipping?" would become
        // a store-information question -- but as a title they are unambiguous.
        if (preg_match('/^(?:about|contact)\b/u', $title_normalized)) {
            $title_types[] = 'about';
        }
        $content_types = $this->strong_document_types($content_normalized);
        $types = array_values(array_unique(array_merge($title_types, $content_types)));

        return !empty($types) ? $types : array('general');
    }

    /**
     * Keywords that can ground a question facet inside a policy excerpt.
     *
     * @param string $facet Facet key.
     * @return array
     */
    public function facet_terms($facet) {
        $map = array(
            'time' => array('day', 'days', 'hour', 'hours', 'week', 'weeks', 'time', 'processing', 'dispatch', 'delivery', 'within', 'business day', 'delayed', 'delay', 'late'),
            'cost' => array('free', 'cost', 'fee', 'fees', 'charge', 'charges', 'paid', 'price', 'shipping cost'),
            'location' => array('international', 'worldwide', 'country', 'countries', 'region', 'area', 'overseas', 'abroad', 'local'),
            'eligibility' => array('eligible', 'eligibility', 'accepted', 'allowed', 'qualify', 'may return', 'can return', 'cannot return'),
            'condition' => array('opened', 'unopened', 'used', 'unused', 'damaged', 'condition', 'packaging', 'tags', 'original'),
            'method' => array('method', 'card', 'cash', 'bank', 'transfer', 'courier', 'pickup', 'collection', 'payment'),
            'process' => array('request', 'contact', 'email', 'form', 'steps', 'process', 'start', 'initiate', 'submit'),
            'tracking' => array('track', 'tracking', 'tracking number', 'shipment status'),
        );

        return isset($map[$facet]) ? $map[$facet] : array();
    }

    /**
     * Strong answer cues for one policy type and requested facet.
     *
     * These phrases are intentionally more specific than generic facet words.
     * They help select the refund-processing sentence instead of a nearby
     * return-window sentence that merely contains the word "days".
     *
     * @param string $type Policy type.
     * @param string $facet Facet key.
     * @return array
     */
    public function answer_cues($type, $facet = '') {
        $type = sanitize_key((string) $type);
        $facet = sanitize_key((string) $facet);
        $map = array(
            'shipping' => array(
                'time' => array('processing time', 'processed within', 'dispatch within', 'shipped within', 'delivery time', 'estimated delivery', 'business days', 'arrive within', 'takes between'),
                'cost' => array('shipping cost', 'shipping fee', 'delivery fee', 'free shipping', 'shipping charges', 'calculated at checkout'),
                'location' => array('we ship to', 'we deliver to', 'international shipping', 'worldwide shipping', 'delivery areas'),
                'tracking' => array('tracking number', 'track your order', 'shipment tracking'),
            ),
            'returns' => array(
                'time' => array('return within', 'return window', 'days after delivery', 'days after purchase'),
                'condition' => array('unused', 'unopened', 'original condition', 'original packaging', 'tags attached', 'opened items', 'damaged items'),
                'eligibility' => array('eligible for a return', 'not eligible for a return', 'can be returned', 'cannot be returned', 'we accept returns'),
                'process' => array('start a return', 'request a return', 'return authorization', 'contact us to return', 'send the item back'),
            ),
            'refunds' => array(
                'time' => array('refund will be processed', 'refund is processed', 'refund will be issued', 'refund is issued', 'receive your refund', 'refund may take', 'within a certain amount of days', 'within business days'),
                'method' => array('original method of payment', 'original payment method', 'credit will be applied', 'refund to your card', 'refund to the payment method'),
                'eligibility' => array('eligible for a refund', 'not eligible for a refund', 'approved for a refund', 'refund is approved'),
                'process' => array('once your return is received', 'refund approval', 'approval or rejection of your refund', 'refund will be processed'),
            ),
            'exchanges' => array(
                'time' => array('exchange within', 'exchange window', 'days to exchange'),
                'condition' => array('unused', 'unopened', 'original condition', 'original packaging', 'tags attached'),
                'eligibility' => array('eligible for an exchange', 'can be exchanged', 'cannot be exchanged', 'we replace items'),
                'process' => array('request an exchange', 'exchange for another size', 'exchange for a different size', 'exchange for another color', 'contact us for an exchange'),
            ),
            'warranty' => array(
                'time' => array('warranty period', 'covered for', 'warranty lasts', 'limited warranty'),
                'eligibility' => array('covered by warranty', 'not covered by warranty', 'warranty covers', 'manufacturing defects'),
                'process' => array('warranty claim', 'submit a warranty claim', 'contact us for warranty'),
            ),
            'cancellation' => array(
                'time' => array('cancel within', 'before dispatch', 'before shipment', 'cancellation window'),
                'eligibility' => array('can be cancelled', 'cannot be cancelled', 'eligible for cancellation'),
                'process' => array('cancel your order', 'request cancellation', 'contact us to cancel'),
            ),
            'payment' => array(
                'method' => array('payment methods', 'we accept visa', 'we accept mastercard', 'we accept credit cards', 'we accept debit cards', 'paypal', 'apple pay', 'google pay', 'cash on delivery', 'bank transfer', 'pay with'),
                'process' => array('payment is processed', 'complete payment', 'payment at checkout'),
            ),
            'privacy' => array(
                'process' => array('information we collect', 'personal data', 'how we use your data', 'data retention', 'data protection'),
            ),
            'terms' => array(
                'process' => array('terms and conditions', 'terms of service', 'by using this website'),
            ),
            'about' => array(
                'process' => array('contact us', 'get in touch', 'email us', 'customer service', 'customer support', 'our story', 'about us', 'our team'),
                'location' => array('we are based', 'based in', 'located in', 'our address', 'head office'),
                'time' => array('opening hours', 'business hours', 'we reply within', 'respond within'),
            ),
        );

        if (!isset($map[$type])) {
            return array();
        }
        if ($facet !== '' && isset($map[$type][$facet])) {
            return $map[$type][$facet];
        }

        $terms = array();
        foreach ($map[$type] as $facet_terms) {
            $terms = array_merge($terms, $facet_terms);
        }

        return array_values(array_unique($terms));
    }

    /**
     * @param string $type Policy type.
     * @return array
     */
    public function type_terms($type) {
        $map = $this->type_phrases();

        return isset($map[$type]) ? $map[$type] : array();
    }

    /**
     * @param string $type Policy type.
     * @return string
     */
    public function label($type) {
        $labels = array(
            'shipping' => __('shipping and delivery', 'geeky-bot'),
            'returns' => __('returns', 'geeky-bot'),
            'refunds' => __('refunds', 'geeky-bot'),
            'exchanges' => __('exchanges', 'geeky-bot'),
            'warranty' => __('warranty', 'geeky-bot'),
            'cancellation' => __('cancellation', 'geeky-bot'),
            'payment' => __('payment', 'geeky-bot'),
            'privacy' => __('privacy', 'geeky-bot'),
            'terms' => __('terms', 'geeky-bot'),
            'about' => __('store information', 'geeky-bot'),
            'general' => __('store policy', 'geeky-bot'),
        );

        return isset($labels[$type]) ? $labels[$type] : __('store policy', 'geeky-bot');
    }

    /**
     * @param string $normalized Normalized message/document.
     * @return array
     */
    private function matched_types($normalized) {
        $map = $this->type_phrases();
        $matches = array();

        foreach ($map as $type => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->contains_phrase($normalized, $phrase)) {
                    $matches[] = $type;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @return array
     */
    private function type_phrases() {
        // Order is intentional: specific policies are checked before broad
        // shipping/payment wording when a question mentions more than one.
        return array(
            'exchanges' => array('exchange', 'exchanges', 'swap item', 'change item'),
            'refunds' => array('refund', 'refunds', 'money back', 'reimbursement'),
            'returns' => array('return', 'returns', 'returning', 'send back'),
            'warranty' => array('warranty', 'warranties', 'guarantee', 'guaranteed'),
            'cancellation' => array('cancel', 'cancel order', 'cancel my order', 'cancellation', 'cancellations'),
            'payment' => array('payment', 'payment method', 'pay', 'pay with', 'paypal', 'apple pay', 'google pay', 'cash on delivery', 'cod', 'credit card', 'debit card', 'bank transfer'),
            'shipping' => array('shipping', 'ship', 'ship to', 'ship internationally', 'delivery', 'deliver', 'dispatch', 'postage', 'courier', 'tracking'),
            'privacy' => array('privacy', 'personal data', 'data policy', 'data protection'),
            'terms' => array('terms', 'terms and conditions', 'terms of service', 'store terms'),
            // Store information is last so a question naming a real policy is
            // never answered from the About page instead. Every phrase here is
            // multi-word on purpose: bare "contact" appears in "contact lenses"
            // and bare "about" in "what about shipping?".
            'about' => array('about this store', 'about the store', 'about your store', 'about us', 'about you', 'who runs', 'who owns', 'who is behind', 'contact us', 'contact you', 'contact the store', 'get in touch', 'customer service', 'customer support', 'where are you based', 'where are you located', 'store address', 'opening hours', 'business hours', 'real store', 'real shop', 'real online store', 'real online shop', 'real business'),
        );
    }

    /**
     * Strong body-text signals used for source classification.
     *
     * @param string $normalized Normalized page content.
     * @return array
     */
    private function strong_document_types($normalized) {
        $signals = array(
            'shipping' => array('shipping policy', 'delivery policy', 'we ship to', 'we deliver to', 'shipping rates', 'shipping fee', 'delivery time', 'estimated delivery', 'orders are shipped', 'orders are dispatched', 'tracking number'),
            'returns' => array('return policy', 'eligible for a return', 'return your item', 'return an item', 'return window', 'we accept returns', 'send the item back'),
            'refunds' => array('refund policy', 'refund will be processed', 'refund is processed', 'refund will be issued', 'approval or rejection of your refund', 'receive your refund', 'refunds once'),
            'exchanges' => array('exchange policy', 'eligible for an exchange', 'exchange an item', 'exchange for another size', 'exchange for a different size', 'request an exchange', 'we replace items'),
            'warranty' => array('warranty policy', 'warranty period', 'covered by warranty', 'warranty covers', 'limited warranty', 'warranty claim'),
            'cancellation' => array('cancellation policy', 'cancel your order', 'order cancellation', 'request cancellation'),
            'payment' => array('payment methods', 'accepted payment methods', 'we accept credit cards', 'we accept debit cards', 'we accept visa', 'we accept mastercard', 'cash on delivery', 'apple pay', 'google pay', 'bank transfer'),
            'privacy' => array('privacy policy', 'information we collect', 'personal data', 'data protection', 'how we use your data'),
            'terms' => array('terms and conditions', 'terms of service', 'by using this website'),
            'about' => array('about us', 'our story', 'who we are', 'contact us', 'get in touch', 'customer service', 'customer support', 'our team', 'opening hours', 'we are based'),
        );
        $types = array();

        foreach ($signals as $type => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->contains_phrase($normalized, $phrase)) {
                    $types[] = $type;
                    break;
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Distinguish store-level policy questions from product-specific warranty
     * questions. Returns, refunds, shipping, exchange, payment and similar
     * intents are always store policy. Warranty may belong to one product.
     *
     * @param string $normalized Normalized shopper message.
     * @param array  $types Matched types.
     * @return string store, product, or empty string.
     */
    private function question_scope($normalized, $types) {
        if (empty($types)) {
            return '';
        }

        if (!in_array('warranty', $types, true)) {
            return 'store';
        }

        if ($this->contains_phrase($normalized, 'this store')
            || $this->contains_phrase($normalized, 'the store')
            || $this->contains_phrase($normalized, 'your store')
            || $this->contains_phrase($normalized, 'warranty policy')
            || preg_match('/\b(?:do|does)\s+you\s+(?:offer|provide)\b/u', $normalized)
            || preg_match('/\bwhat\s+warranty\s+do\s+you\s+(?:offer|provide)\b/u', $normalized)) {
            return 'store';
        }

        if (preg_match('/\b(?:this|that|the)\s+(?:product|item|one)\b/u', $normalized)
            || preg_match('/\b(?:warranty|guarantee)\s+(?:on|for)\s+(?:this|that|the)\b/u', $normalized)
            || preg_match('/^does\s+(?!this\s+store\b|the\s+store\b|your\s+store\b).+\s+have\s+(?:a\s+)?(?:warranty|guarantee)\b/u', $normalized)
            || preg_match('/^is\s+.+\s+(?:covered|protected)\s+by\s+(?:a\s+)?warranty\b/u', $normalized)
            || preg_match('/\bdoes\s+it\s+have\s+(?:a\s+)?(?:warranty|guarantee)\b/u', $normalized)) {
            return 'product';
        }

        return 'store';
    }

    /**
     * @param string $normalized Normalized message.
     * @return array
     */
    private function matched_facets($normalized) {
        $map = array(
            'time' => array('how long', 'when will', 'when do', 'days', 'hours', 'weeks', 'delivery time', 'processing time', 'return window', 'refund time', 'delayed', 'delay', 'late', 'still waiting'),
            'cost' => array('how much', 'cost', 'fee', 'fees', 'free', 'charge', 'charges', 'paid'),
            'location' => array('ship to', 'deliver to', 'international', 'worldwide', 'country', 'countries', 'abroad', 'overseas', 'outside', 'region', 'area'),
            'eligibility' => array('eligible', 'allowed', 'can i', 'may i', 'do you accept', 'qualify', 'does this store provide', 'do you provide'),
            'condition' => array('opened', 'unopened', 'used', 'unused', 'damaged', 'original packaging', 'tags'),
            'method' => array('how can i', 'how do i', 'method', 'methods', 'card', 'cash', 'bank transfer', 'courier', 'pickup'),
            'process' => array('process', 'steps', 'request', 'start a', 'initiate', 'contact'),
            'tracking' => array('track', 'tracking', 'tracking number'),
        );
        $facets = array();

        foreach ($map as $facet => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->contains_phrase($normalized, $phrase)) {
                    $facets[] = $facet;
                    break;
                }
            }
        }

        return array_values(array_unique($facets));
    }

    /**
     * @param string $normalized Normalized message.
     * @param array  $types Matched policy types.
     * @return array
     */
    private function specific_terms($normalized, $types) {
        $words = preg_split('/[^a-z0-9]+/i', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $stop = array(
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'what', 'when', 'where', 'which',
            'your', 'you', 'can', 'could', 'does', 'do', 'how', 'are', 'will', 'about', 'please', 'need',
            'want', 'tell', 'give', 'me', 'my', 'our', 'store', 'policy', 'policies', 'is', 'it', 'a', 'an',
            'to', 'of', 'on', 'in', 'at', 'be', 'i', 'we', 'they', 'there', 'any', 'if', 'or', 'only',
            'provide', 'offer', 'accept', 'item', 'items', 'product', 'products', 'another', 'take', 'long', 'reach',
        );

        foreach ($this->type_phrases() as $type => $phrases) {
            if (!in_array($type, $types, true)) {
                continue;
            }
            foreach ($phrases as $phrase) {
                foreach (preg_split('/\s+/', $phrase, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $stop[] = strtolower($word);
                }
            }
        }

        $terms = array();
        foreach ((array) $words as $word) {
            $word = strtolower((string) $word);
            if (strlen($word) < 3 || in_array($word, $stop, true)) {
                continue;
            }
            $terms[] = $word;
        }

        return array_values(array_unique($terms));
    }

    /**
     * @param string $haystack Normalized haystack.
     * @param string $phrase Phrase.
     * @return bool
     */
    private function contains_phrase($haystack, $phrase) {
        $phrase = $this->normalize($phrase);
        if ($phrase === '') {
            return false;
        }

        if (strpos($phrase, ' ') !== false) {
            return strpos($haystack, $phrase) !== false;
        }

        return (bool) preg_match('/(?:^|[^a-z0-9])' . preg_quote($phrase, '/') . '(?:$|[^a-z0-9])/i', $haystack);
    }

    /**
     * @param string $text Text.
     * @return string
     */
    private function normalize($text) {
        $text = strtolower(wp_strip_all_tags((string) $text));
        $text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset'));
        $text = preg_replace('/\s+/', ' ', $text);

        return trim((string) $text);
    }

    /**
     * Policy types implied by the situation a shopper describes.
     *
     * The phrase vocabulary is built from policy nouns -- "refund", "shipping",
     * "warranty" -- so it only recognises a question that already knows which
     * policy it is asking about. Shoppers routinely do not. "What if my item
     * arrives damaged?" and "What happens if my order is delayed?" are answered
     * on almost every store's returns and shipping pages, yet neither contains a
     * single word from that vocabulary. Both fell through to catalog search, and
     * because discovery almost always returns something, "What if my item
     * arrives damaged?" was answered with a bottle of shampoo.
     *
     * The gate that keeps this from swallowing ordinary shopping is that the
     * message has to be about the shopper's own purchase, so "do you sell
     * damaged stock cheap?" stays a product search.
     *
     * The second gate lives in analyze(), because it has to cover the noun
     * vocabulary too: a question about one specific order's state is left alone
     * whichever way it was matched.
     *
     * @param string $normalized Normalized shopper message.
     * @return array
     */
    private function situation_types($normalized) {
        if ($normalized === '' || !$this->describes_own_purchase($normalized)) {
            return array();
        }

        $types = array();
        foreach ($this->situation_patterns() as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $normalized)) {
                    $types[] = $type;
                    break;
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * What went wrong, and which policy answers it.
     *
     * Ordered like type_phrases(): the specific remedy first, so a damaged item
     * is answered from the returns page rather than the general shipping one.
     *
     * @return array
     */
    private function situation_patterns() {
        return array(
            'returns' => array(
                '/\b(?:damaged|broken|faulty|defective|cracked|torn|leaking|scratched)\b/u',
                '/\b(?:doesn|didn|does|did)[\x{2019}\']?\s*(?:n[\x{2019}\']?t)?\s*work\b/u',
                '/\b(?:not|stopped)\s+working\b/u',
                '/\bwrong\s+(?:item|items|product|products|thing|order)\b/u',
                '/\bmissing\s+(?:item|items|part|parts|piece|pieces)\b/u',
                '/\bnot\s+(?:what|as)\s+(?:i\s+)?(?:ordered|described|expected|advertised)\b/u',
            ),
            'exchanges' => array(
                '/\b(?:doesn|didn)[\x{2019}\']?t\s+fit\b/u',
                '/\bdoes\s+not\s+fit\b/u',
                '/\bwrong\s+(?:size|colour|color)\b/u',
                '/\btoo\s+(?:big|small|large|tight|loose|short|long)\b/u',
            ),
            'shipping' => array(
                '/\b(?:delayed|delay|delays)\b/u',
                '/\b(?:is|are|was|were|arrives|arrived|running|showed\s+up)\s+late\b/u',
                '/\b(?:hasn|haven|hadn)[\x{2019}\']?t\s+(?:yet\s+)?(?:arrived|shipped|turned\s+up|been\s+delivered|come)\b/u',
                '/\b(?:has|have|had)\s+not\s+(?:yet\s+)?(?:arrived|shipped|been\s+delivered)\b/u',
                '/\bnever\s+(?:arrived|turned\s+up|showed\s+up|came|got\s+here)\b/u',
                '/\b(?:still|not\s+yet)\s+(?:waiting|arrived|here|delivered|received)\b/u',
                '/\b(?:lost|stuck)\s+in\s+(?:transit|the\s+post|the\s+mail|customs)\b/u',
            ),
        );
    }

    /**
     * Whether the message is about something the shopper bought or is buying.
     *
     * This is the gate that keeps the situation words from reaching the catalog
     * side of the store. "Damaged" on its own is a perfectly good product search
     * term for a salvage shop; "my order is damaged" never is.
     *
     * @param string $normalized Normalized shopper message.
     * @return bool
     */
    private function describes_own_purchase($normalized) {
        $frames = array(
            '/\b(?:my|the|this|that)\s+(?:order|orders|item|items|parcel|parcels|package|packages|delivery|deliveries|shipment|shipments|purchase|purchases|goods)\b/u',
            '/\bi\s+(?:received|receive|got|ordered|bought|purchased)\b/u',
            '/\bwhat\s+(?:if|happens\s+if|happens\s+when|do\s+i\s+do\s+if)\b/u',
            '/\bif\s+(?:my|the|it)\b/u',
            '/\barrives?\s+(?:damaged|broken|faulty|late)\b/u',
        );

        foreach ($frames as $frame) {
            if (preg_match($frame, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the shopper is asking after one specific order rather than the
     * rule that governs it.
     *
     * Mirrors the wording the assistant already routes to an order lookup, so
     * widening policy detection cannot quietly take those questions away from
     * the part of the system that can answer them properly.
     *
     * @param string $normalized Normalized shopper message.
     * @return bool
     */
    private function asks_own_order_state($normalized) {
        $patterns = array(
            '/\bwhere\s+(?:is|are)\s+my\s+(?:order|orders|parcel|package|delivery|shipment|item)\b/u',
            '/\b(?:track|tracking)\b[^?]{0,16}\bmy\s+(?:order|parcel|package|shipment|delivery)\b/u',
            '/\bmy\s+order\s+(?:status|number|id|tracking)\b/u',
            '/\bstatus\s+of\s+my\s+(?:order|delivery|shipment)\b/u',
            '/\bwhen\s+will\s+my\s+(?:order|parcel|package|delivery|shipment|item)\b/u',
            '/\bhas\s+my\s+(?:order|parcel|package)\s+(?:shipped|arrived|dispatched)\b/u',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }
}
