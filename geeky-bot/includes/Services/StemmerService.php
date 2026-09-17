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

    /** Shortest Arabic stem worth keeping; below this a stem matches almost anything. */
    const ARABIC_MIN_STEM = 3;

    /**
     * Shortest Russian stem worth keeping.
     *
     * Russian endings are long -- ами, ого, ыми are three letters each -- and a
     * short noun stripped by one of them stops naming anything: "часы" must
     * reach `час`, but nothing shorter than that identifies a watch.
     */
    const RUSSIAN_MIN_STEM = 3;

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

        // Arabic is modelled, lightly. Everything else outside plain ASCII words
        // belongs to a language this stemmer does not model, and returning it
        // untouched is the correct answer.
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $token)) {
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $token)) {
                return $this->stem_arabic($token);
            }
            if (preg_match('/[\x{0400}-\x{04FF}]/u', $token)) {
                return $this->stem_russian($token);
            }

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

    /**
     * Reduce one Arabic token to a shared stem.
     *
     * Arabic writes its grammar onto the word. The definite article ال is joined
     * to the noun, and so are the particles that precede it, so الحذاء، والحذاء
     * and للسفر are the same words a shopper elsewhere would write with a space.
     * Matching is token-based, so without this a search for الحذاء الأسود found
     * nothing at all while حذاء أسود found the shoe -- and the first is how
     * Arabic is ordinarily written.
     *
     * The tokens here are already normalised by SearchLanguageService, which
     * folds أ إ آ to ا, ي ى ئ to ی, ك to ک and ة to ه. The prefixes below are
     * therefore spelled in that folded form -- کال, not كال.
     *
     * Over-stemming is acceptable and occasionally unavoidable: ألعاب (toys) has
     * no article, but its first two letters look exactly like one, and it is
     * reduced to عاب. That costs nothing, because the index side runs through
     * this same function -- both sides land on عاب and still meet. Only one
     * suffix is removed, for the same reason the English side stops at
     * inflection: past that point the stem stops identifying the product.
     *
     * Broken plurals (حذاء/أحذية، قميص/قمصان) are out of reach of any suffix rule
     * and are left to the term expansion and the LIKE pass.
     *
     * @param string $token Single normalised Arabic word.
     * @return string
     */
    private function stem_arabic($token) {
        $token = $this->strip_arabic_clitics($token);
        $suffixes = array('ات', 'ون', 'ین', 'ان', 'ها', 'یه', 'ه', 'ی');

        foreach ($suffixes as $suffix) {
            if ($this->arabic_length($token) - $this->arabic_length($suffix) < self::ARABIC_MIN_STEM) {
                continue;
            }
            if (mb_substr($token, -mb_strlen($suffix, 'UTF-8'), null, 'UTF-8') === $suffix) {
                $token = mb_substr($token, 0, -mb_strlen($suffix, 'UTF-8'), 'UTF-8');
                break;
            }
        }

        return $token;
    }

    /**
     * Reduce one Russian token to a shared stem.
     *
     * Russian writes its grammar onto the END of the word, and a noun carries a
     * different ending in each of six cases and two numbers. Matching is
     * token-based, so without this the shopper's word and the catalog's word are
     * simply two different strings: the index holds рюкзак, the shopper asking
     * "нет рюкзаков" sends рюкзаков, and nothing at all comes back. It is the
     * mirror of strip_arabic_clitics(), which does the same job at the front of
     * the word -- and the genitive plural below is the form Russian uses after a
     * quantity or a negation, which is to say most of the time a shopper is
     * asking whether a store has something.
     *
     * Endings are tried longest first so ами is never mistaken for и.
     *
     * Over-stemming is acceptable here for the same reason it is in Arabic: both
     * sides of a FULLTEXT match run through this function, so the query and the
     * catalog land on the same string whether or not that string is a word. The
     * cost is paid by word pairs that collide, which is cheaper than a case
     * ending that matches nothing.
     *
     * @param string $token Single normalised Russian word.
     * @return string
     */
    private function stem_russian($token) {
        $suffixes = array(
            // adjective and participle endings, then the noun cases
            'ами', 'ями', 'ого', 'его', 'ому', 'ему', 'ыми', 'ими',
            'ов', 'ев', 'ёв', 'ей', 'ой', 'ый', 'ий', 'ая', 'яя', 'ое', 'ее',
            'ые', 'ие', 'ых', 'их', 'ом', 'ем', 'ам', 'ям', 'ах', 'ях', 'ью',
            'ия', 'ию', 'ей',
            'а', 'я', 'о', 'е', 'ы', 'и', 'у', 'ю', 'ь', 'й',
        );

        foreach ($suffixes as $suffix) {
            $length = mb_strlen($suffix, 'UTF-8');
            if (mb_strlen($token, 'UTF-8') - $length < self::RUSSIAN_MIN_STEM) {
                continue;
            }
            if (mb_substr($token, -$length, null, 'UTF-8') === $suffix) {
                $token = mb_substr($token, 0, -$length, 'UTF-8');
                break;
            }
        }

        return $this->drop_russian_fleeting_vowel($token);
    }

    /**
     * Remove the vowel Russian inserts into a stem when the ending disappears.
     *
     * The genitive plural of a feminine noun has no ending at all, and the
     * consonant cluster left behind is broken up by an о or an е that is in no
     * other form of the word: сумка and сумки stem to сумк, but сумок has
     * nothing to strip and stays сумок, so the three never meet. Removing that
     * vowel is what makes сумок, курток, футболок and кроссовок land on the same
     * stem as their nominative.
     *
     * It also fires on words whose vowel is not fleeting -- город becomes горд --
     * and that is harmless, because города reduces to город and then to горд by
     * this same rule. Both sides agree, which is the only property that matters.
     *
     * @param string $token Russian token with its ending already removed.
     * @return string
     */
    private function drop_russian_fleeting_vowel($token) {
        if (mb_strlen($token, 'UTF-8') - 1 < self::RUSSIAN_MIN_STEM) {
            return $token;
        }

        $consonant = '[бвгджзклмнпрстфхцчшщ]';
        if (preg_match('/(' . $consonant . ')[оеё](' . $consonant . ')$/u', $token)) {
            return preg_replace('/(' . $consonant . ')[оеё](' . $consonant . ')$/u', '$1$2', $token);
        }

        return $token;
    }

    /**
     * Remove a joined Arabic article or particle from the front of a token.
     *
     * Split out from stem_arabic() because the two halves of the problem are
     * served in different places. The stemmer keeps both sides of a FULLTEXT
     * match in agreement and may over-reduce freely. The colour and size alias
     * lists, and the PHP gates in ProductIndexService, compare a shopper's term
     * against catalog wording directly, and those need a word that is still a
     * word -- الأسود reduced to اسود is a colour the alias list knows, whereas
     * the fully stemmed form would not be.
     *
     * Prefixes are spelled in normalised form (کال, not كال) and tried longest
     * first, so لل is never mistaken for ال.
     *
     * @param string $token Single normalised Arabic word.
     * @return string
     */
    public function strip_arabic_clitics($token) {
        $token = (string) $token;

        foreach (array('وال', 'بال', 'کال', 'فال', 'لل', 'ال') as $prefix) {
            if ($this->arabic_length($token) - $this->arabic_length($prefix) < self::ARABIC_MIN_STEM) {
                continue;
            }
            if (mb_strpos($token, $prefix, 0, 'UTF-8') === 0) {
                return mb_substr($token, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8');
            }
        }

        return $token;
    }

    /**
     * Character count, not byte count -- every Arabic letter is two bytes.
     *
     * @param string $text Text to measure.
     * @return int
     */
    private function arabic_length($text) {
        return function_exists('mb_strlen') ? mb_strlen((string) $text, 'UTF-8') : strlen((string) $text);
    }
}
