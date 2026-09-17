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

    /** @var string Query the rules were resolved for. */
    private $last_query = '';

    /** @var string Language pack actually loaded. */
    private $loaded_language = 'en';

    /** @var string Language the compiled rules belong to. */
    private $last_query_language = '';

    /**
     * Language pack currently in use, for diagnostics and admin display.
     *
     * @return string
     */
    public function loaded_language() {
        $this->rules();

        return $this->loaded_language;
    }

    public function __construct(SearchLanguageService $language) {
        $this->language = $language;
    }

    public function profile($query, $consumer = self::INDEX_CONSUMER) {
        if ($consumer !== self::INDEX_CONSUMER) {
            return $this->empty_profile();
        }

        // Detect on the raw query, before normalize_match_text() folds the
        // Arabic letterforms. ي and ك are the only characters that separate
        // Arabic from Urdu and Persian, and normalization rewrites both, so
        // detecting on the normalized text asks for the pack of a language the
        // shopper was not writing in.
        $language = $this->language->language_code($query);

        $query = $this->normalize_match_text($query);
        if ($query === '') {
            return $this->empty_profile();
        }

        // The rules are language-specific, so a query in a different language
        // must not reuse the previous query's compiled pack.
        if ($language !== $this->last_query_language) {
            $this->rules = null;
            $this->last_query_language = $language;
        }
        $this->last_query = $query;

        $cache_key = $language . '|' . $consumer . '|' . $query;
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
            $phrase = $this->normalize_match_text($phrase);
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

    /**
     * Language pack directory for a language code.
     *
     * @param string $language Two-letter code.
     * @return string Absolute path, or '' when the plugin path is unknown.
     */
    private function pack_path($language) {
        if (!defined('GEEKYBOT_PATH')) {
            return '';
        }

        $language = preg_replace('/[^a-z]/', '', strtolower((string) $language));
        if ($language === '' || strlen($language) !== 2) {
            return '';
        }

        return GEEKYBOT_PATH . 'includes/Search/Data/' . $language . '/commerce.php';
    }

    /**
     * Buyer-intent rules for the shopper's language.
     *
     * This file carries the richest shopper-facing signal in the system --
     * comfort, gift intent, decision mode, recipient hints -- and it used to be
     * loaded from the English path no matter who was asking, so a Spanish
     * shopper got term matching but no intent understanding at all. The
     * Data/{lang}/ layout was always implied by the directory naming; this
     * wires it up, with English as the fallback whenever a pack is missing so
     * an unsupported language is never worse off than before.
     *
     * @return array
     */
    private function rules() {
        if ($this->rules !== null) {
            return $this->rules;
        }

        // Resolved in profile() from the raw query. Re-detecting here would
        // read the normalized text and pick the wrong pack for Arabic.
        $language = $this->last_query_language !== ''
            ? $this->last_query_language
            : $this->language->language_code('');

        /**
         * Filters the language pack used for buyer-intent parsing.
         *
         * @param string $language Detected two-letter language code.
         */
        $language = (string) apply_filters('geekybot_buyer_intent_language', $language);

        $raw = array();
        foreach (array($language, 'en') as $candidate) {
            $file = $this->pack_path($candidate);
            if ($file !== '' && is_readable($file)) {
                $loaded = include $file;
                if (is_array($loaded) && !empty($loaded)) {
                    $raw = $loaded;
                    $this->loaded_language = $candidate;
                    break;
                }
            }
        }

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
            $item = $this->normalize_match_text($item);
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
        if ($query === '' || $phrase === '') {
            return false;
        }

        // Whole-token matching is what keeps a commerce rule precise: a size "s"
        // must not fire on "sale", and "new" must not fire on "newborn".
        if (strpos(' ' . $query . ' ', ' ' . $phrase . ' ') !== false) {
            return true;
        }

        // Japanese and Chinese write a sentence without spaces, so token
        // matching can never fire: 「軽いバッグを探しています」 contains 軽い with no
        // boundary on either side of it, and every rule in the ja and zh packs
        // failed silently -- the packs loaded, matched nothing, and the shopper
        // got no intent understanding at all. Substring matching is allowed
        // only when the PHRASE itself is CJK, so it cannot loosen a Latin or
        // Arabic rule, and mirrors what SearchLanguageService::contains_phrase()
        // already does on the product-term side.
        if (preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $phrase)) {
            return strpos($query, $phrase) !== false;
        }

        return false;
    }

    /**
     * Normalise text for rule matching.
     *
     * The Arabic reduction is applied to the pack's phrases as well, in
     * normalize_list() and longest_first(). Both sides must go through it or
     * they stop meeting: 6 of 8 sampled Arabic intent phrases lost their signal
     * the moment a shopper wrote the article, so "الحذاء المريح" was not a
     * comfort request while "حذاء مريح" was.
     *
     * @param string $text Text to normalise.
     * @return string
     */
    private function normalize_match_text($text) {
        $text = $this->language->normalize_text($text);
        $text = preg_replace('/(?<!\d)\.(?!\d)/u', ' ', (string) $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return $this->language->strip_arabic_clitic_text(trim($text));
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
