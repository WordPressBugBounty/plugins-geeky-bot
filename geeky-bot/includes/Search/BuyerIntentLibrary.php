<?php
namespace GeekyBot\Search;

use GeekyBot\Services\SearchLanguageService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Local buyer-intent classifier for Geeky Bot product discovery.
 *
 * This service deliberately understands common shopping language only. It does
 * not try to become a general NLP engine. Hard catalog constraints are parsed
 * elsewhere; this class supplies soft preferences, use cases, decision modes,
 * recipient hints, and safe phrase cleanup for broad product ranking.
 */
final class BuyerIntentLibrary {
    const ENGINE_REVISION = 'gb-buyer-intent-2026.07.12.2';
    const INDEX_CONSUMER = 'geekybot-product-index-v4';

    private $language;
    private $rules = null;
    private $profile_cache = array();

    public function __construct(SearchLanguageService $language) {
        $this->language = $language;
    }

    public function profile($query, $consumer = self::INDEX_CONSUMER) {
        if ($consumer !== self::INDEX_CONSUMER) {
            return $this->empty_profile();
        }

        $query = $this->normalize_match_text($query);
        if ($query === '') {
            return $this->empty_profile();
        }

        $cache_key = $consumer . '|' . $query;
        if (isset($this->profile_cache[$cache_key])) {
            return $this->profile_cache[$cache_key];
        }

        $rules = $this->rules();
        $modifier_terms = array();
        $modifier_labels = array();
        $ranking_weights = array();
        $strip_phrases = array_merge($rules['filler_phrases'], $rules['flexibility_phrases']);
        $ignore_tokens = $rules['ignore_tokens'];
        $signals = array();
        $decision_modes = array();

        foreach (array('preferences', 'use_cases') as $group) {
            foreach ($rules[$group] as $canonical => $rule) {
                if (!$this->matches_any_phrase($query, $rule['phrases'])) {
                    continue;
                }

                $modifier_terms[] = $canonical;
                $modifier_labels[] = $rule['label'];
                $ranking_weights[$canonical] = $rule['weight'];
                $strip_phrases = array_merge($strip_phrases, $rule['phrases']);
                $signals[] = $group . ':' . $canonical;
            }
        }

        foreach ($rules['decision_modes'] as $canonical => $rule) {
            if (!$this->matches_any_phrase($query, $rule['phrases'])) {
                continue;
            }

            $decision_modes[] = $canonical;
            $modifier_terms[] = $canonical === 'best_value' ? 'value' : $canonical;
            $modifier_labels[] = $rule['label'];
            $ranking_weights[$canonical === 'best_value' ? 'value' : $canonical] = $rule['weight'];
            $strip_phrases = array_merge($strip_phrases, $rule['phrases']);
            $signals[] = 'decision:' . $canonical;
        }

        $recipient = array();
        foreach ($rules['recipients'] as $key => $rule) {
            $matched_phrase = $this->first_matching_phrase($query, $rule['phrases']);
            if ($matched_phrase === '') {
                continue;
            }

            $recipient = array(
                'key' => $key,
                'label' => $rule['label'],
                'positive_terms' => $rule['positive_terms'],
                'negative_terms' => $rule['negative_terms'],
                'matched_phrase' => $matched_phrase,
            );
            $strip_phrases = array_merge($strip_phrases, $rule['phrases']);
            $ignore_tokens = array_merge($ignore_tokens, $rule['recipient_tokens']);
            $modifier_terms[] = 'gift';
            $modifier_labels[] = __('Gift', 'geeky-bot');
            $ranking_weights['gift'] = max(20, isset($ranking_weights['gift']) ? absint($ranking_weights['gift']) : 0);
            $signals[] = 'recipient:' . $key;
            break;
        }

        $is_gift_request = in_array('gift', $modifier_terms, true) || !empty($recipient);
        if ($is_gift_request) {
            $signals[] = 'mission:gift';
        }

        $profile = array(
            'engine_revision' => self::ENGINE_REVISION,
            'pack_revision' => $rules['revision'],
            'modifier_terms' => $this->clean_terms($modifier_terms),
            'modifier_labels' => $this->clean_labels($modifier_labels),
            'ranking_weights' => $this->clean_weights($ranking_weights),
            'decision_modes' => array_values(array_unique(array_filter($decision_modes))),
            'strip_phrases' => $this->longest_first($strip_phrases),
            'ignore_tokens' => $this->clean_terms($ignore_tokens),
            'audience' => $recipient,
            'recipient' => $recipient,
            'is_gift_request' => $is_gift_request,
            'gift_signals' => $rules['gift_signals'],
            'flexible' => $this->matches_any_phrase($query, $rules['flexibility_phrases']),
            'signals' => array_values(array_unique(array_filter($signals))),
        );

        $profile = (array) apply_filters('geekybot_buyer_intent_profile', $profile, $query);
        $this->profile_cache[$cache_key] = $profile;
        return $profile;
    }

    public function strip_phrases($query, $profile = array()) {
        $query = $this->normalize_match_text($query);
        $phrases = !empty($profile['strip_phrases']) ? (array) $profile['strip_phrases'] : $this->profile($query)['strip_phrases'];

        foreach ((array) $phrases as $phrase) {
            $phrase = $this->language->normalize_text($phrase);
            if ($phrase === '') {
                continue;
            }
            $query = preg_replace('/(?:^|\s)' . preg_quote($phrase, '/') . '(?=\s|$)/u', ' ', $query);
        }

        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    public function remove_ignored_tokens($terms, $profile) {
        $ignore = array_fill_keys($this->clean_terms(isset($profile['ignore_tokens']) ? $profile['ignore_tokens'] : array()), true);
        $clean = array();

        foreach ((array) $terms as $term) {
            $term = $this->language->normalize_text($term);
            if ($term === '' || isset($ignore[$term])) {
                continue;
            }
            $clean[] = $term;
        }

        return array_values(array_unique($clean));
    }

    private function rules() {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $file = defined('GEEKYBOT_PATH') ? GEEKYBOT_PATH . 'includes/Search/Data/en/commerce.php' : '';
        $raw = $file && is_readable($file) ? include $file : array();
        if (!is_array($raw)) {
            $raw = array();
        }

        $this->rules = array(
            'revision' => isset($raw['revision']) ? sanitize_text_field($raw['revision']) : 'unknown',
            'preferences' => $this->compile_named_rules(isset($raw['preferences']) ? $raw['preferences'] : array()),
            'use_cases' => $this->compile_named_rules(isset($raw['use_cases']) ? $raw['use_cases'] : array()),
            'decision_modes' => $this->compile_named_rules(isset($raw['decision_modes']) ? $raw['decision_modes'] : array()),
            'recipients' => $this->compile_recipient_rules(isset($raw['recipients']) ? $raw['recipients'] : array()),
            'gift_signals' => $this->compile_term_groups(isset($raw['gift_signals']) ? $raw['gift_signals'] : array()),
            'flexibility_phrases' => $this->longest_first(isset($raw['flexibility_phrases']) ? $raw['flexibility_phrases'] : array()),
            'filler_phrases' => $this->longest_first(isset($raw['filler_phrases']) ? $raw['filler_phrases'] : array()),
            'ignore_tokens' => $this->normalize_list(isset($raw['ignore_tokens']) ? $raw['ignore_tokens'] : array()),
        );

        return $this->rules;
    }

    private function compile_named_rules($rows) {
        $compiled = array();
        foreach ((array) $rows as $canonical => $row) {
            if (!is_array($row)) {
                continue;
            }
            $canonical = $this->language->normalize_text($canonical);
            if ($canonical === '') {
                continue;
            }
            $compiled[$canonical] = array(
                'label' => isset($row['label']) ? wp_strip_all_tags((string) $row['label']) : $canonical,
                'weight' => isset($row['weight']) ? max(1, min(40, absint($row['weight']))) : 12,
                'phrases' => $this->longest_first(isset($row['phrases']) ? $row['phrases'] : array()),
            );
        }
        return $compiled;
    }

    private function compile_recipient_rules($rows) {
        $compiled = array();
        foreach ((array) $rows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $compiled[$key] = array(
                'label' => isset($row['label']) ? wp_strip_all_tags((string) $row['label']) : $key,
                'phrases' => $this->longest_first(isset($row['phrases']) ? $row['phrases'] : array()),
                'recipient_tokens' => $this->normalize_list(isset($row['recipient_tokens']) ? $row['recipient_tokens'] : array()),
                'positive_terms' => $this->normalize_list(isset($row['positive_terms']) ? $row['positive_terms'] : array()),
                'negative_terms' => $this->normalize_list(isset($row['negative_terms']) ? $row['negative_terms'] : array()),
            );
        }
        return $compiled;
    }

    private function compile_term_groups($groups) {
        $compiled = array();
        foreach ((array) $groups as $key => $terms) {
            $compiled[$key] = $this->normalize_list($terms);
        }
        return $compiled;
    }

    private function normalize_list($items) {
        if (is_string($items)) {
            $items = preg_split('/\s*\|\s*/', $items);
        }

        $clean = array();
        foreach ((array) $items as $item) {
            $item = $this->language->normalize_text($item);
            if ($item !== '') {
                $clean[] = $item;
            }
        }
        return array_values(array_unique($clean));
    }

    private function matches_any_phrase($query, $phrases) {
        return $this->first_matching_phrase($query, $phrases) !== '';
    }

    private function first_matching_phrase($query, $phrases) {
        foreach ((array) $phrases as $phrase) {
            if ($this->contains_phrase($query, $phrase)) {
                return $phrase;
            }
        }
        return '';
    }

    private function contains_phrase($query, $phrase) {
        $query = (string) $query;
        $phrase = (string) $phrase;
        return $query !== '' && $phrase !== '' && strpos(' ' . $query . ' ', ' ' . $phrase . ' ') !== false;
    }

    private function normalize_match_text($text) {
        $text = $this->language->normalize_text($text);
        $text = preg_replace('/(?<!\d)\.(?!\d)/u', ' ', (string) $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim($text);
    }

    private function longest_first($phrases) {
        $phrases = $this->normalize_list($phrases);
        usort($phrases, function ($a, $b) {
            $la = function_exists('mb_strlen') ? mb_strlen($a, 'UTF-8') : strlen($a);
            $lb = function_exists('mb_strlen') ? mb_strlen($b, 'UTF-8') : strlen($b);
            return $la === $lb ? 0 : ($la > $lb ? -1 : 1);
        });
        return $phrases;
    }

    private function clean_terms($terms) {
        return $this->normalize_list($terms);
    }

    private function clean_labels($labels) {
        return array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $labels))));
    }

    private function clean_weights($weights) {
        $clean = array();
        foreach ((array) $weights as $term => $weight) {
            $term = $this->language->normalize_text($term);
            if ($term !== '') {
                $clean[$term] = max(1, min(40, absint($weight)));
            }
        }
        return $clean;
    }

    private function empty_profile() {
        return array(
            'engine_revision' => self::ENGINE_REVISION,
            'pack_revision' => '',
            'modifier_terms' => array(),
            'modifier_labels' => array(),
            'ranking_weights' => array(),
            'decision_modes' => array(),
            'strip_phrases' => array(),
            'ignore_tokens' => array(),
            'audience' => array(),
            'recipient' => array(),
            'is_gift_request' => false,
            'gift_signals' => array(),
            'flexible' => false,
            'signals' => array(),
        );
    }
}
