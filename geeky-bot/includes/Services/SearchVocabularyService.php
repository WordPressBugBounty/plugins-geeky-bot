<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The store's own searchable word list, used to recover from typos.
 *
 * Before 2.1.0 typo tolerance was a hand-written list of individual
 * misspellings -- "hoodys", "reccomend", "shrit". Every typo the original
 * author did not personally anticipate returned zero results, with no
 * suggestion and no fallback, and the merchant never learned the query
 * happened. That is a silent conversion leak: the shopper does not report it,
 * does not retry, and does not buy.
 *
 * This builds the vocabulary from the indexed catalog instead, so it covers
 * whatever the store actually sells and needs no maintenance. It is
 * deliberately separate from FamilyVocabularyService: that one models product
 * *families* and is driven by categories, and widening it to every title word
 * would change family gating and ranking. This one is only ever consulted
 * after a search has already returned nothing, so it costs nothing on the hot
 * path.
 */
class SearchVocabularyService {
    const OPTION = 'geekybot_search_vocabulary';
    const VERSION = 1;

    /** Hard ceiling on stored terms, newest-catalog-first by frequency. */
    const MAX_TERMS = 5000;

    /** Tokens shorter than this are never corrected and never stored. */
    const MIN_TERM_LENGTH = 3;

    /**
     * Leading characters a correction must reproduce exactly.
     *
     * The edit-distance budget alone is too generous: at two edits, a word the
     * shopper spelled correctly can still be "corrected" into an unrelated
     * catalog word. A Spanish shopper asking for "algo barato" (cheap) was
     * answered with a leather shoe, because "barato" is two edits from the
     * indexed "zapato". Requiring the opening character to survive is the
     * standard companion to AUTO fuzziness for exactly this reason: real typos
     * are overwhelmingly not in the first character, while unrelated words
     * usually differ there. The trade is deliberate -- a mistyped first letter
     * is left uncorrected rather than risk a confident wrong answer.
     */
    const CORRECTION_PREFIX_LENGTH = 1;

    /** @var array|null */
    private static $memo = null;

    /**
     * Correction budget by token length.
     *
     * Two characters or fewer are never corrected: at that length almost
     * everything is within one edit of something else, and a confident wrong
     * correction is worse than no correction. The 3-5 / 6+ split is the same
     * heuristic Elasticsearch uses for AUTO fuzziness, and it is what lets
     * "hoodys" reach "hoodies" and "tshrit" reach "tshirt" -- two edits on a
     * six-character word -- without opening up short tokens.
     *
     * @param int $length Token length.
     * @return int Maximum edit distance, 0 to skip.
     */
    private function max_distance($length) {
        if ($length < 3) {
            return 0;
        }
        if ($length < 6) {
            return 1;
        }

        return 2;
    }

    /**
     * @return array{terms: array<string,int>, version: int}
     */
    public function map() {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $stored = get_option(self::OPTION, array());
        if (!is_array($stored) || empty($stored['terms']) || (int) ($stored['version'] ?? 0) !== self::VERSION) {
            self::$memo = array('terms' => array(), 'version' => self::VERSION);
            return self::$memo;
        }

        self::$memo = array(
            'terms' => (array) $stored['terms'],
            'version' => self::VERSION,
        );

        return self::$memo;
    }

    /**
     * Whether a token is a word this store actually uses.
     *
     * @param string $token Normalised token.
     * @return bool
     */
    public function knows($token) {
        $map = $this->map();

        return isset($map['terms'][(string) $token]);
    }

    /**
     * @return int
     */
    public function count_terms() {
        $map = $this->map();

        return count($map['terms']);
    }

    /**
     * Closest catalog word to a token the store does not use.
     *
     * @param string $token Normalised token.
     * @return array{term: string, distance: int}|null
     */
    public function nearest($token) {
        $token = (string) $token;
        $length = function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token);
        $budget = $this->max_distance($length);
        if ($budget < 1 || $this->knows($token)) {
            return null;
        }

        $map = $this->map();
        $best = null;
        $prefix = $this->token_prefix($token);

        foreach ($map['terms'] as $candidate => $frequency) {
            $candidate_length = function_exists('mb_strlen') ? mb_strlen($candidate, 'UTF-8') : strlen($candidate);
            if (abs($candidate_length - $length) > $budget) {
                continue;
            }

            // Cheapest test first, and the one that removes most false
            // corrections: a candidate that does not even start the same way
            // is a different word, not a misspelling of this one.
            if ($prefix !== '' && $this->token_prefix($candidate) !== $prefix) {
                continue;
            }

            // levenshtein() is native and cheap, so it screens the pool before
            // the transposition-aware pass runs on the few survivors.
            $rough = levenshtein($token, $candidate);
            if ($rough > $budget + 1) {
                continue;
            }

            $distance = $this->osa_distance($token, $candidate, $budget);
            if ($distance < 0 || $distance > $budget) {
                continue;
            }

            if ($best === null
                || $distance < $best['distance']
                || ($distance === $best['distance'] && $frequency > $best['frequency'])) {
                $best = array('term' => $candidate, 'distance' => $distance, 'frequency' => $frequency);
            }
        }

        if ($best === null) {
            return null;
        }

        return array('term' => $best['term'], 'distance' => $best['distance']);
    }

    /**
     * Leading characters used to gate a correction.
     *
     * @param string $token Normalised token.
     * @return string
     */
    private function token_prefix($token) {
        $token = (string) $token;
        if ($token === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($token, 0, self::CORRECTION_PREFIX_LENGTH, 'UTF-8')
            : substr($token, 0, self::CORRECTION_PREFIX_LENGTH);
    }

    /**
     * Rewrite a query's unknown words to the nearest catalog words.
     *
     * @param string $query Raw shopper query.
     * @return array{query: string, corrections: array<int, array{from: string, to: string}>}
     */
    public function correct_query($query) {
        $result = array('query' => (string) $query, 'corrections' => array());
        if ($this->count_terms() < 1) {
            return $result;
        }

        $language = new SearchLanguageService();
        $normalized = $language->normalize_text((string) $query);
        if ($normalized === '') {
            return $result;
        }

        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || empty($tokens)) {
            return $result;
        }

        // Conversational words are never product words, so correcting one can
        // only do damage: the catalog contains "shoe" but not "show", so an
        // uncorrected pass rewrites "show me something similar" into a shoe
        // search and then tells the shopper that is what it searched for.
        // The same trap catches have/wave, looking/cooking, want/watt and
        // find/fine. The stop-word list already knows these are not product
        // terms -- this pass simply has to ask.
        $stop_words = array_fill_keys($language->stop_words($language->language_code($normalized)), true);

        $out = array();
        $corrections = array();
        foreach ($tokens as $token) {
            if (preg_match('/\d/', $token)) {
                // Sizes, model numbers and prices are not typos.
                $out[] = $token;
                continue;
            }

            if (isset($stop_words[$token])) {
                $out[] = $token;
                continue;
            }

            $match = $this->nearest($token);
            if ($match === null) {
                $out[] = $token;
                continue;
            }

            $out[] = $match['term'];
            $corrections[] = array('from' => $token, 'to' => $match['term']);
        }

        if (empty($corrections)) {
            return $result;
        }

        return array('query' => implode(' ', $out), 'corrections' => $corrections);
    }

    /**
     * Optimal string alignment distance: Levenshtein plus adjacent
     * transposition as a single edit.
     *
     * Transposition is the most common real typing error -- "tshrit" for
     * "tshirt" is two edits under plain Levenshtein and one under this, which
     * is the difference between recovering the search and not.
     *
     * @param string $a First string.
     * @param string $b Second string.
     * @param int    $budget Abort once every cell exceeds this.
     * @return int Distance, or -1 when it cannot beat the budget.
     */
    private function osa_distance($a, $b, $budget) {
        $len_a = strlen($a);
        $len_b = strlen($b);

        if ($a === $b) {
            return 0;
        }
        if ($len_a === 0) {
            return $len_b;
        }
        if ($len_b === 0) {
            return $len_a;
        }

        $previous_previous = array();
        $previous = range(0, $len_b);
        $current = array();

        for ($i = 1; $i <= $len_a; $i++) {
            $current = array_fill(0, $len_b + 1, 0);
            $current[0] = $i;
            $row_best = $current[0];

            for ($j = 1; $j <= $len_b; $j++) {
                $cost = ($a[$i - 1] === $b[$j - 1]) ? 0 : 1;
                $value = min(
                    $current[$j - 1] + 1,
                    $previous[$j] + 1,
                    $previous[$j - 1] + $cost
                );

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $value = min($value, $previous_previous[$j - 2] + 1);
                }

                $current[$j] = $value;
                if ($value < $row_best) {
                    $row_best = $value;
                }
            }

            if ($row_best > $budget) {
                return -1;
            }

            $previous_previous = $previous;
            $previous = $current;
        }

        return $current[$len_b];
    }

    /**
     * Relearn the vocabulary from the product index.
     *
     * Called from a completed index rebuild, so the word list is always in step
     * with the catalog that is actually searchable.
     *
     * @return array{terms: int}
     */
    public function rebuild() {
        global $wpdb;

        $table = ProductIndexService::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table, read in full to derive the word list.
        $rows = $wpdb->get_results("SELECT title, categories, tags, attributes, color_terms, size_terms FROM {$table}");
        if (!is_array($rows) || empty($rows)) {
            return $this->store(array());
        }

        $language = new SearchLanguageService();
        $stemmer = new StemmerService();
        $frequency = array();

        foreach ($rows as $row) {
            $text = implode(' ', array_filter(array(
                (string) $row->title,
                (string) $row->categories,
                (string) $row->tags,
                (string) $row->attributes,
                (string) $row->color_terms,
                (string) $row->size_terms,
            )));

            $tokens = preg_split('/\s+/u', $language->normalize_text($text), -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($tokens)) {
                continue;
            }

            foreach (array_unique($tokens) as $token) {
                if (!$this->usable_term($token)) {
                    continue;
                }
                $frequency[$token] = isset($frequency[$token]) ? $frequency[$token] + 1 : 1;

                // A catalog that only ever writes "T-Shirts" still has to
                // recover "tshrit", so the singular stem earns its own entry.
                // Stems start at zero, which keeps a real surface form winning
                // a tie against the stem of a different word.
                $stem = $stemmer->stem($token);
                if ($stem !== '' && $stem !== $token && $this->usable_term($stem) && !isset($frequency[$stem])) {
                    $frequency[$stem] = 0;
                }
            }
        }

        arsort($frequency);

        return $this->store(array_slice($frequency, 0, self::MAX_TERMS, true));
    }

    /**
     * @param string $token Candidate word.
     * @return bool
     */
    private function usable_term($token) {
        $token = (string) $token;
        $length = function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token);

        if ($length < self::MIN_TERM_LENGTH || $length > 40) {
            return false;
        }

        // Numbers and codes are not words a shopper mistypes into another word.
        if (preg_match('/\d/', $token)) {
            return false;
        }

        return (bool) preg_match('/^[\pL][\pL\-\.]*$/u', $token);
    }

    /**
     * @param array $terms term => document frequency.
     * @return array{terms: int}
     */
    private function store($terms) {
        update_option(self::OPTION, array(
            'version' => self::VERSION,
            'terms' => $terms,
            'built_at' => current_time('mysql'),
        ), false);

        self::$memo = array('terms' => $terms, 'version' => self::VERSION);

        return array('terms' => count($terms));
    }

    /**
     * @return void
     */
    public static function flush() {
        self::$memo = null;
    }
}
