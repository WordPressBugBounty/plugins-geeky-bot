<?php
namespace GeekyBot\Chat;

use GeekyBot\Services\CatalogVisibilityService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves shopper replies against one shared pending product-selection state.
 *
 * The same resolver is used by Product Expert questions and Conversation Guard
 * selections so an exact product name, ordinal, or unique family reply never
 * falls through into a broad catalog search.
 */
class PendingProductSelectionResolver {
    const MAX_CANDIDATES = 4;
    const TTL_SECONDS = 900;

    public function resolve($message, $pending) {
        $pending = is_array($pending) ? $pending : array();
        if (!$this->is_active($pending)) {
            return $this->result('inactive');
        }

        $candidates = $this->visible_candidates($pending['candidates'] ?? array());
        if (empty($candidates)) {
            return $this->result('inactive');
        }

        $message = $this->normalize($message);
        if ($message === '') {
            return $this->result('unresolved', 0, array(), $candidates);
        }

        $ordinal = $this->ordinal_index($message);
        if ($ordinal !== null) {
            if (isset($candidates[$ordinal])) {
                return $this->result('resolved', absint($candidates[$ordinal]['id']), $candidates[$ordinal], $candidates, 'ordinal');
            }
            return $this->result('ambiguous', 0, array(), $candidates, 'ordinal_unavailable');
        }

        $matches = array();
        foreach ($candidates as $candidate) {
            $match_source = $this->candidate_match_source($message, $candidate);
            if ($match_source !== '') {
                $candidate['matchSource'] = $match_source;
                $matches[] = $candidate;
            }
        }

        if (count($matches) === 1) {
            return $this->result(
                'resolved',
                absint($matches[0]['id']),
                $matches[0],
                $candidates,
                !empty($matches[0]['matchSource']) ? $matches[0]['matchSource'] : 'candidate_name'
            );
        }

        if (count($matches) > 1) {
            return $this->result('ambiguous', 0, array(), $matches, 'candidate_name_ambiguous');
        }

        if ($this->is_generic_selection_reply($message)) {
            return $this->result('ambiguous', 0, array(), $candidates, 'selection_unclear');
        }

        return $this->result('unresolved', 0, array(), $candidates);
    }

    public function is_active($pending) {
        if (!is_array($pending) || empty($pending['candidates'])) {
            return false;
        }

        $created_at = !empty($pending['createdAt']) ? absint($pending['createdAt']) : 0;
        if (!$created_at || (time() - $created_at) > self::TTL_SECONDS) {
            return false;
        }

        return true;
    }

    public function make_pending($candidates, $source = 'conversation', $original_question = '', $route = array()) {
        $clean = array();
        foreach (array_slice((array) $candidates, 0, self::MAX_CANDIDATES) as $candidate) {
            $candidate = is_array($candidate) ? $candidate : array();
            $product_id = !empty($candidate['id']) ? absint($candidate['id']) : 0;
            $name = !empty($candidate['name']) ? wp_strip_all_tags((string) $candidate['name']) : '';
            if ($product_id && $name !== '') {
                $clean[] = array(
                    'id' => $product_id,
                    'name' => $name,
                );
            }
        }

        if (empty($clean)) {
            return array();
        }

        return array(
            'source' => sanitize_key((string) $source),
            'originalQuestion' => wp_strip_all_tags((string) $original_question),
            'route' => is_array($route) ? $route : array(),
            'candidates' => $clean,
            'createdAt' => time(),
        );
    }

    private function visible_candidates($candidates) {
        $visible = array();
        foreach (array_slice((array) $candidates, 0, self::MAX_CANDIDATES) as $candidate) {
            $candidate = is_array($candidate) ? $candidate : array();
            $product_id = !empty($candidate['id']) ? absint($candidate['id']) : 0;
            if (!$product_id || !function_exists('wc_get_product')) {
                continue;
            }

            $product = wc_get_product($product_id);
            if (!CatalogVisibilityService::is_visible($product, 'pending_product_selection')) {
                continue;
            }

            $visible[] = array(
                'id' => $product_id,
                'name' => wp_strip_all_tags($product->get_name()),
            );
        }

        return $visible;
    }

    private function candidate_match_source($message, $candidate) {
        $name = !empty($candidate['name']) ? $this->normalize($candidate['name']) : '';
        if ($name === '') {
            return '';
        }

        $selection = $this->selection_text($message);
        if ($selection === $name) {
            return 'candidate_exact_name';
        }

        if (strlen($selection) >= 4 && (strpos($name, $selection) !== false || strpos($selection, $name) !== false)) {
            return 'candidate_partial_name';
        }

        $selection_tokens = $this->meaningful_tokens($selection);
        if (empty($selection_tokens)) {
            return '';
        }

        $name_tokens = $this->meaningful_tokens($name);
        if (empty(array_diff($selection_tokens, $name_tokens))) {
            return count($selection_tokens) >= 2 ? 'candidate_token_name' : 'candidate_family';
        }

        return '';
    }

    private function is_generic_selection_reply($message) {
        return preg_match(
            '/^(?:yes|yeah|yep|this|that|it|this\s+one|that\s+one|the\s+(?:product|item|one|option)|one\s+of\s+them)$/u',
            trim((string) $message)
        ) === 1;
    }

    private function selection_text($message) {
        $message = preg_replace('/^(?:i\s+mean|it\s+is|choose|select|the\s+product\s+is|the\s+item\s+is)\s*:?\s*/u', '', $message);
        $message = preg_replace('/^(?:the|this|that)\s+/u', '', (string) $message);
        $message = preg_replace('/\s+(?:one|product|item)$/u', '', (string) $message);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $message));
    }

    private function meaningful_tokens($value) {
        $tokens = preg_split('/\s+/u', preg_replace('/[^a-z0-9]+/u', ' ', $this->normalize($value)));
        $stop = array(
            'the', 'this', 'that', 'one', 'product', 'item', 'please', 'choose',
            'select', 'i', 'mean', 'it', 'is', 'ml', 'inch', 'inches',
        );
        return array_values(array_unique(array_filter((array) $tokens, function ($token) use ($stop) {
            return strlen($token) >= 2 && !in_array($token, $stop, true);
        })));
    }

    private function ordinal_index($message) {
        $map = array(
            'first' => 0, '1st' => 0,
            'second' => 1, '2nd' => 1,
            'third' => 2, '3rd' => 2,
            'fourth' => 3, '4th' => 3,
        );
        if (preg_match('/\b(?:option|product|item|number)\s*([1-4])\b/u', $message, $match)) {
            return absint($match[1]) - 1;
        }
        foreach ($map as $word => $index) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $message)) {
                return $index;
            }
        }
        return null;
    }

    private function result($status, $product_id = 0, $candidate = array(), $candidates = array(), $source = '') {
        return array(
            'status' => sanitize_key((string) $status),
            'resolved' => $status === 'resolved',
            'ambiguous' => $status === 'ambiguous',
            'productId' => absint($product_id),
            'candidate' => is_array($candidate) ? $candidate : array(),
            'candidates' => is_array($candidates) ? array_values($candidates) : array(),
            'source' => sanitize_key((string) $source),
        );
    }

    private function normalize($value) {
        $value = strtolower(remove_accents(wp_strip_all_tags((string) $value)));
        $value = str_replace(array('–', '—', '_'), array('-', '-', ' '), $value);
        $value = preg_replace('/[^a-z0-9\-\s\/]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
