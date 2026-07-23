<?php
namespace GeekyBot\ProductExpert;

use GeekyBot\Chat\PendingProductSelectionResolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce-native Product Expert coordinator.
 */
class ProductExpertService {
    private $router;
    private $resolver;
    private $clarifications;
    private $facts;
    private $answers;
    private $formatter;

    public function __construct(
        ?ProductQuestionRouter $router = null,
        ?ProductReferenceResolver $resolver = null,
        ?ProductFactsService $facts = null,
        ?ProductAnswerService $answers = null,
        ?ProductAnswerFormatter $formatter = null,
        ?PendingProductSelectionResolver $clarifications = null
    ) {
        $this->router = $router ?: new ProductQuestionRouter();
        $this->resolver = $resolver ?: new ProductReferenceResolver();
        $this->clarifications = $clarifications ?: new PendingProductSelectionResolver();
        $this->facts = $facts ?: new ProductFactsService();
        $this->formatter = $formatter ?: new ProductAnswerFormatter();
        $this->answers = $answers ?: new ProductAnswerService($this->facts, $this->formatter);
    }

    public function handle($message, $context_resolution = array()) {
        $context_resolution = is_array($context_resolution) ? $context_resolution : array();
        $pending = $this->pending_clarification($context_resolution);

        // A candidate name or ordinal supplied after a clarification prompt is
        // an answer to that prompt, not a fresh product-discovery request.
        if ($this->clarifications->is_active($pending)
            && !empty($pending['originalQuestion'])
            && !$this->is_explicit_command_interrupt($message)) {
            $clarification = $this->clarifications->resolve($message, $pending);
            if (!empty($clarification['resolved']) && !empty($clarification['productId'])) {
                return $this->answer_resolved_clarification($clarification, $pending);
            }

            if (!empty($clarification['ambiguous'])) {
                return $this->clarification_response(
                    (array) ($clarification['candidates'] ?? $pending['candidates']),
                    $pending,
                    'clarification_reply_ambiguous'
                );
            }
            // A different product name, a new question, or a new discovery
            // request naturally cancels the pending clarification and continues
            // through the normal router below.
        }

        $route = $this->router->route($message);
        if (empty($route['handledCandidate'])) {
            return array('handled' => false);
        }

        $reference = $this->resolver->resolve($message, $route, $context_resolution);
        if (!empty($reference['ambiguous'])) {
            $pending = $this->make_pending_clarification(
                $message,
                $route,
                (array) ($reference['candidates'] ?? array())
            );
            return $this->clarification_response(
                (array) ($reference['candidates'] ?? array()),
                $pending,
                'ambiguous'
            );
        }

        if ((empty($reference['resolved']) || empty($reference['productId'])) && !empty($route['fallbackToSearchWhenUnresolved'])) {
            return array('handled' => false);
        }

        if (empty($reference['resolved']) || empty($reference['productId'])) {
            return array(
                'handled' => true,
                'intent' => 'product_question_unresolved',
                'message' => __("I'm not sure which product you mean. Please use the product name or ask about one of the products currently shown.", 'geeky-bot'),
                'productId' => 0,
                'missing' => true,
                'meta' => array(
                    'verified' => false,
                    'routeFacts' => (array) ($route['facts'] ?? array()),
                    'referenceSource' => 'unresolved',
                ),
            );
        }

        return $this->answer_product(
            $message,
            $route,
            absint($reference['productId']),
            sanitize_key((string) ($reference['source'] ?? 'catalog'))
        );
    }

    private function is_explicit_command_interrupt($message) {
        $message = strtolower(remove_accents(wp_strip_all_tags((string) $message)));
        $message = str_replace(array('’', '`'), "'", $message);
        $message = preg_replace('/[^a-z0-9\s\-\?]+/u', ' ', $message);
        $message = trim((string) preg_replace('/\s+/u', ' ', (string) $message));

        if ($message === '') {
            return false;
        }

        return preg_match(
            '/^(?:compare\b|show\b|find\b|recommend\b|suggest\b|list\b|start\s+over\b|clear\b|reset\b|only\s+show\b|remove\b|go\s+back\b|view\s+(?:my\s+)?cart\b|show\s+(?:my\s+)?cart\b|checkout\b|go\s+to\s+checkout\b|add\b.+\bto\s+cart\b|what\s+is\s+(?:(?:your|the)\s+|the\s+store(?:\s+s)?\s+)?(?:return|refund|shipping|delivery|privacy)\s+policy\b)/u',
            $message
        ) === 1;
    }

    private function answer_resolved_clarification($clarification, $pending) {
        $original_question = !empty($pending['originalQuestion'])
            ? wp_strip_all_tags((string) $pending['originalQuestion'])
            : '';
        $route = !empty($pending['route']) && is_array($pending['route'])
            ? $pending['route']
            : $this->router->route($original_question);

        if ($original_question === '' || empty($route['handledCandidate'])) {
            return array('handled' => false);
        }

        $result = $this->answer_product(
            $original_question,
            $route,
            absint($clarification['productId']),
            'clarification_candidate'
        );
        if (!empty($result['meta']) && is_array($result['meta'])) {
            $result['meta']['clarificationResolved'] = true;
            $result['meta']['clarificationSource'] = sanitize_key((string) ($clarification['source'] ?? 'candidate'));
            $result['meta']['originalQuestion'] = $original_question;
        }
        return $result;
    }

    private function answer_product($message, $route, $product_id, $reference_source) {
        $facts = $this->facts->get($product_id);
        if (empty($facts)) {
            return array(
                'handled' => true,
                'intent' => 'product_question_unresolved',
                'message' => __("I couldn't access that product's store details.", 'geeky-bot'),
                'productId' => 0,
                'missing' => true,
                'meta' => array(
                    'verified' => false,
                    'routeFacts' => (array) ($route['facts'] ?? array()),
                    'referenceSource' => sanitize_key((string) $reference_source),
                ),
            );
        }

        $answer = $this->answers->answer($message, $route, $facts);
        $message_text = !empty($answer['message'])
            ? wp_strip_all_tags((string) $answer['message'])
            : $this->formatter->missing((array) ($route['facts'] ?? array()));

        return array(
            'handled' => true,
            'intent' => !empty($answer['missing']) ? 'product_fact_missing' : 'product_fact_answer',
            'message' => $message_text,
            'productId' => absint($facts['id']),
            'missing' => !empty($answer['missing']),
            'meta' => array(
                'verified' => true,
                'verifiedLabel' => $this->formatter->source_label(!empty($answer['missing'])),
                'sourceStatus' => !empty($answer['missing']) ? 'checked' : 'confirmed',
                'productId' => absint($facts['id']),
                'productName' => wp_strip_all_tags((string) $facts['name']),
                'routeFacts' => (array) ($route['facts'] ?? array()),
                'answeredFacts' => (array) ($answer['answeredFacts'] ?? array()),
                'missingFacts' => (array) ($answer['missingFacts'] ?? array()),
                'referenceSource' => sanitize_key((string) $reference_source),
            ),
        );
    }

    private function clarification_response($candidates, $pending, $reference_source) {
        $names = array();
        foreach ((array) $candidates as $candidate) {
            if (!empty($candidate['name'])) {
                $names[] = wp_strip_all_tags((string) $candidate['name']);
            }
        }
        $question = !empty($names)
            ? sprintf(
                /* translators: %s: possible product names. */
                __('Are you asking about %s?', 'geeky-bot'),
                $this->human_join($names)
            )
            : __('Which product are you asking about?', 'geeky-bot');

        return array(
            'handled' => true,
            'intent' => 'product_question_clarification',
            'message' => $question,
            'productId' => 0,
            'missing' => false,
            'meta' => array(
                'verified' => false,
                'routeFacts' => (array) ($pending['route']['facts'] ?? array()),
                'referenceSource' => sanitize_key((string) $reference_source),
                'candidateProducts' => (array) $candidates,
                'pendingClarification' => $pending,
            ),
        );
    }

    private function make_pending_clarification($message, $route, $candidates) {
        $clean_candidates = array();
        foreach (array_slice((array) $candidates, 0, PendingProductSelectionResolver::MAX_CANDIDATES) as $candidate) {
            $candidate = is_array($candidate) ? $candidate : array();
            $product_id = !empty($candidate['id']) ? absint($candidate['id']) : 0;
            $name = !empty($candidate['name']) ? wp_strip_all_tags((string) $candidate['name']) : '';
            if ($product_id && $name !== '') {
                $clean_candidates[] = array('id' => $product_id, 'name' => $name);
            }
        }

        return $this->clarifications->make_pending(
            $clean_candidates,
            'product_expert_question',
            wp_strip_all_tags((string) $message),
            $this->clarification_route($route)
        );
    }

    private function clarification_route($route) {
        $route = is_array($route) ? $route : array();
        return array(
            'handledCandidate' => !empty($route['handledCandidate']),
            'facts' => array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($route['facts'] ?? array()))))),
            'normalizedMessage' => wp_strip_all_tags((string) ($route['normalizedMessage'] ?? '')),
            'subjectText' => wp_strip_all_tags((string) ($route['subjectText'] ?? '')),
            'asksAvailability' => !empty($route['asksAvailability']),
            'asksExactVariation' => !empty($route['asksExactVariation']),
            'asksAvailableOptions' => !empty($route['asksAvailableOptions']),
            'asksUnavailableOptions' => !empty($route['asksUnavailableOptions']),
            'fallbackToSearchWhenUnresolved' => !empty($route['fallbackToSearchWhenUnresolved']),
            'isQuestion' => !empty($route['isQuestion']),
        );
    }

    private function pending_clarification($context_resolution) {
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

    private function human_join($values) {
        $values = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $values))));
        if (count($values) <= 1) {
            return !empty($values) ? $values[0] : '';
        }
        if (count($values) === 2) {
            return $values[0] . ' ' . __('or', 'geeky-bot') . ' ' . $values[1];
        }
        $last = array_pop($values);
        return implode(', ', $values) . ', ' . __('or', 'geeky-bot') . ' ' . $last;
    }
}
