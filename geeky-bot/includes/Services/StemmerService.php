<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reduces English word forms to a shared stem, for the index and the query alike.
 *
 * The point is agreement between the two sides, not linguistic correctness.
 * `beani` is not a word, and it does not need to be: what matters is that
 * "beanie" and "beanies" both reach it, so a shopper's plural finds a catalog
 * singular.
 *
 * That property is exactly what the existing singulariser in SearchLanguageService
 * lacks. It rewrites `ies` to `y`, which is right for accessories -> accessory and
 * wrong for beanies -> beany, and it leaves the singular "beanie" untouched. The
 * two forms therefore land on different strings, and "beanies" returned nothing
 * while four beanies sat in the catalog. Hand-adding `beany`, `scarve` and
 * `sunglass` keys to the family map papered over three instances of a rule that
 * misfires across the whole language.
 *
 * Scope is deliberately narrow:
 *
 * - Only ASCII words are touched. Arabic, Urdu, CJK and Cyrillic queries pass
 *   through unchanged, so the multilingual handling already in
 *   SearchLanguageService keeps working while English is the focus.
 * - Only inflection is removed, never derivation. `-ing` and `-ed` are left
 *   alone: "cleaning cloth" and "heated mug" are product identities, not tenses.
 * - Words shorter than four characters are returned as-is, because a stem of one
 *   or two letters matches almost anything.
 */
class StemmerService {
    const MIN_LENGTH = 4;

    /**
     * Plurals that no suffix rule can reach. Both members of each pair have to
     * resolve to the same string or the stemmer defeats its own purpose.
     *
     * @var array<string, string>
     */
    private static $irregular = array(
        'mice' => 'mouse',
        'geese' => 'goose',
        'teeth' => 'tooth',
        'feet' => 'foot',
        'men' => 'man',
        'women' => 'woman',
        'children' => 'child',
        'people' => 'person',
        'oxen' => 'ox',
        // -f / -fe plurals: "scarves" cannot reach "scarf" by trimming suffixes.
        'scarves' => 'scarf',
        'knives' => 'knife',
        'wives' => 'wife',
        'lives' => 'life',
        'leaves' => 'leaf',
        'shelves' => 'shelf',
        'halves' => 'half',
        'loaves' => 'loaf',
        'thieves' => 'thief',
        'wolves' => 'wolf',
        'calves' => 'calf',
        'scarfs' => 'scarf',
    );

    /**
     * Reduce one token to its stem.
     *
     * @param string $token Single word.
     * @return string
     */
    public function stem($token) {
        $token = strtolower(trim((string) $token));

        if ($token === '') {
            return '';
        }

        // Anything outside plain ASCII words belongs to a language this stemmer
        // does not model. Returning it untouched is the correct answer.
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $token)) {
            return $token;
        }

        if (isset(self::$irregular[$token])) {
            return self::$irregular[$token];
        }

        if (strlen($token) < self::MIN_LENGTH) {
            return $token;
        }

        $token = $this->depluralise($token);

        return $this->normalise_tail($token);
    }

    /**
     * Stem every word in a phrase, preserving order and spacing.
     *
     * @param string $text Free text.
     * @return string
     */
    public function stem_text($text) {
        $text = (string) $text;
        if (trim($text) === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return '';
        }

        $stems = array();
        foreach ($tokens as $token) {
            $stem = $this->stem($token);
            if ($stem !== '') {
                $stems[] = $stem;
            }
        }

        return implode(' ', $stems);
    }

    /**
     * Distinct stems for a phrase, useful when building an index column.
     *
     * @param string $text Free text.
     * @return string
     */
    public function unique_stem_text($text) {
        $stems = preg_split('/\s+/u', $this->stem_text($text), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($stems)) {
            return '';
        }

        return implode(' ', array_values(array_unique($stems)));
    }

    /**
     * Remove a plural ending.
     *
     * @param string $token Lowercase ASCII word.
     * @return string
     */
    private function depluralise($token) {
        // dresses -> dress, sunglasses -> sunglass
        if (preg_match('/sses$/', $token)) {
            return substr($token, 0, -2);
        }

        // boxes -> box, watches -> watch, brushes -> brush
        if (preg_match('/(xes|ches|shes|zes)$/', $token)) {
            return substr($token, 0, -2);
        }

        // beanies -> beani, cities -> citi. Ending in `i` rather than `y` is what
        // lets the singular converge on the same stem in normalise_tail().
        if (preg_match('/ies$/', $token) && strlen($token) > 4) {
            return substr($token, 0, -3) . 'i';
        }

        // socks -> sock. `ss` and `us` are not plural markers, and a trailing
        // `is` usually is not either (analysis, chassis).
        if (preg_match('/s$/', $token) && !preg_match('/(ss|us|is)$/', $token)) {
            return substr($token, 0, -1);
        }

        return $token;
    }

    /**
     * Fold singular endings onto the same shape the plural rules produce.
     *
     * Without this the two halves never meet: "beanies" reduces to `beani` while
     * "beanie" stays `beanie`, and the shopper's plural still misses the catalog
     * singular.
     *
     * @param string $token Lowercase ASCII word.
     * @return string
     */
    private function normalise_tail($token) {
        if (strlen($token) < self::MIN_LENGTH) {
            return $token;
        }

        // beanie -> beani, hoodie -> hoodi
        if (preg_match('/ie$/', $token)) {
            return substr($token, 0, -2) . 'i';
        }

        // accessory -> accessori, city -> citi
        if (preg_match('/y$/', $token)) {
            return substr($token, 0, -1) . 'i';
        }

        return $token;
    }
}
