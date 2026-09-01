<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Learns a store's product vocabulary from the store itself.
 *
 * Geeky Bot ships a hand-written family map, and that map can only ever describe
 * the catalogs its authors happened to look at. A plugin installed by a shop
 * selling brake discs, aquarium pumps or industrial fasteners gets nothing from
 * `hoodie` and `beanie`, and can be actively harmed by them: `cap` gating on
 * headwear is wrong in a store selling bottle caps.
 *
 * The merchant has already declared what they sell, in their own words, by
 * building a product category tree. This service reads that declaration:
 *
 * - Each populated category contributes its head noun. "Phone Cases" gives
 *   `case`, "Beanies" gives `beanie`.
 * - The canonical is the STEM of that noun, so every spelling in the store
 *   converges on it. Surface forms are collected from category names and product
 *   titles rather than guessed, which is what makes this work in any language
 *   the stemmer leaves alone.
 * - Narrow or wide comes from the tree, not from judgement. A leaf category
 *   gates on itself; a parent widens to its children, because a merchant who put
 *   Backpacks under Bags has already said a backpack is a kind of bag.
 * - Colour and size values are excluded. They are qualifiers a shopper adds to a
 *   family, never a family, and the store lists them under its own attribute
 *   taxonomies.
 *
 * The curated map still wins wherever it has an opinion. Derivation only fills
 * gaps, so a mis-learned term can never take precedence over a deliberate one,
 * and every existing behaviour test keeps passing unchanged.
 *
 * Results are cached in an option rather than a table on purpose: a query needs
 * several lookups, so the whole map is wanted per request anyway, and one
 * non-autoloaded option read beats a table round trip per term.
 */
class FamilyVocabularyService {
    const OPTION = 'geekybot_family_vocabulary';
    const VERSION = 1;

    /** @var array|null */
    private static $memo = null;

    /**
     * Words that are grammar or marketing, never a product family.
     *
     * @return array<string, bool>
     */
    private static function stopwords() {
        return array_fill_keys(array(
            'and', 'the', 'for', 'with', 'from', 'all', 'new', 'other', 'misc',
            'uncategorized', 'uncategorised', 'general', 'sale', 'offers', 'deals',
            'featured', 'popular', 'best', 'top', 'shop', 'store', 'products',
            'product', 'items', 'item', 'collection', 'collections', 'range',
            'gift', 'gifts', 'set', 'sets', 'pack', 'packs', 'more', 'accessory',
            'accessories', 'essentials', 'kids', 'men', 'women', 'unisex',
        ), true);
    }

    /**
     * The derived vocabulary, built on first use and cached.
     *
     * @return array{terms: array<string, string>, gates: array<string, array>, neighbours: array<string, array>}
     */
    public function map() {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $stored = get_option(self::OPTION, array());
        if (!is_array($stored) || empty($stored['terms']) || (int) ($stored['version'] ?? 0) !== self::VERSION) {
            self::$memo = $this->empty_map();
            return self::$memo;
        }

        self::$memo = array(
            'terms' => (array) $stored['terms'],
            'gates' => (array) ($stored['gates'] ?? array()),
            'neighbours' => (array) ($stored['neighbours'] ?? array()),
            'phrases' => (array) ($stored['phrases'] ?? array()),
        );

        return self::$memo;
    }

    /**
     * Canonical family for a shopper term, or '' when the store has not taught us one.
     *
     * @param string $term Normalised token.
     * @return string
     */
    public function canonical($term) {
        $map = $this->map();
        $term = (string) $term;

        return isset($map['terms'][$term]) ? (string) $map['terms'][$term] : '';
    }

    /**
     * Gate tokens for a shopper term.
     *
     * @param string $term Normalised token.
     * @return array<int, string>
     */
    public function gate($term) {
        $map = $this->map();
        $term = (string) $term;

        return isset($map['gates'][$term]) ? array_values((array) $map['gates'][$term]) : array();
    }

    /**
     * Neighbouring families for ranking breadth.
     *
     * @param string $canonical Canonical family.
     * @return array<int, string>
     */
    public function neighbours($canonical) {
        $map = $this->map();
        $canonical = (string) $canonical;

        return isset($map['neighbours'][$canonical]) ? array_values((array) $map['neighbours'][$canonical]) : array();
    }

    /**
     * Longest multi-word family the store declared, matching these tokens.
     *
     * "Running Shoes", "T-Shirts" and "Travel Accessories" are families a
     * merchant named in full. Keeping only the head noun threw the modifier away,
     * so "running shoes" searched every shoe and "t-shirt" searched every shirt.
     * The modifier is not decoration -- it is half the name.
     *
     * @param array $stems Stemmed query tokens, in order.
     * @return array{canonical: string, qualifiers: array, source: string, index: int, length: int}|null
     */
    public function match_phrase($stems) {
        $map = $this->map();
        if (empty($map['phrases'])) {
            return null;
        }

        $stems = array_values(array_filter((array) $stems, 'strlen'));
        $count = count($stems);
        if ($count < 2) {
            return null;
        }

        // Longest first, so "running shoe" is preferred over a bare "shoe".
        for ($length = min(4, $count); $length >= 2; $length--) {
            for ($start = 0; $start + $length <= $count; $start++) {
                $key = implode(' ', array_slice($stems, $start, $length));
                if (!isset($map['phrases'][$key])) {
                    continue;
                }

                $entry = (array) $map['phrases'][$key];

                return array(
                    'canonical' => (string) ($entry['canonical'] ?? ''),
                    'qualifiers' => array_values((array) ($entry['qualifiers'] ?? array())),
                    'source' => (string) ($entry['source'] ?? ''),
                    'index' => $start + $length - 1,
                    'length' => $length,
                );
            }
        }

        return null;
    }

    /**
     * Read the store's taxonomy and catalog, and store what it teaches.
     *
     * @return array Summary of what was learned.
     */
    public function rebuild() {
        if (!taxonomy_exists('product_cat')) {
            return array('families' => 0, 'terms' => 0, 'skipped' => 0);
        }

        $excluded = $this->excluded_tokens();
        $stemmer = new StemmerService();
        $language = new SearchLanguageService();

        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
        ));

        if (is_wp_error($categories) || empty($categories)) {
            return array('families' => 0, 'terms' => 0, 'skipped' => 0);
        }

        // stem => working record
        $families = array();
        $phrases = array();
        $skipped = 0;
        $term_stem_by_id = array();

        foreach ($categories as $category) {
            $head = $this->head_noun($language->normalize_text($category->name), $excluded);
            if ($head === '') {
                $skipped++;
                continue;
            }

            $stem = $stemmer->stem($head);
            if ($stem === '') {
                $skipped++;
                continue;
            }

            $term_stem_by_id[(int) $category->term_id] = $stem;

            if (!isset($families[$stem])) {
                $families[$stem] = array(
                    'surface' => array(),
                    'children' => array(),
                    'count' => 0,
                );
            }

            $families[$stem]['surface'][$head] = true;
            $families[$stem]['count'] += (int) $category->count;

            $phrase = $this->phrase_entry($category->name, $head, $stem, $stemmer, $language, $excluded);
            if ($phrase !== null) {
                $phrases[$phrase['key']] = $phrase['entry'];
            }
        }

        // A merchant who nested Backpacks under Bags has already said a backpack
        // is a kind of bag. Read that instead of deciding it here.
        foreach ($categories as $category) {
            $parent_id = (int) $category->parent;
            if ($parent_id === 0 || !isset($term_stem_by_id[$parent_id], $term_stem_by_id[(int) $category->term_id])) {
                continue;
            }

            $parent_stem = $term_stem_by_id[$parent_id];
            $child_stem = $term_stem_by_id[(int) $category->term_id];
            if ($parent_stem !== $child_stem) {
                $families[$parent_stem]['children'][$child_stem] = true;
            }
        }

        $this->collect_title_surface_forms($families, $stemmer, $language, $excluded);

        return $this->store($families, $phrases);
    }

    /**
     * Turn a multi-word category name into a phrase family.
     *
     * The head noun stays the family, so everything downstream keeps working;
     * the leading words become REQUIRED qualifiers, which is what makes
     * "running shoes" narrower than "shoes" rather than identical to it.
     *
     * Short modifiers are kept here even though they are rejected as families.
     * "T-Shirts" is the reason: `t` cannot name a product on its own, but it is
     * the entire difference between a t-shirt and a shirt, and dropping it is
     * why "t-shirt" used to return office shirts.
     *
     * @param string                $name     Raw category name.
     * @param string                $head     Head noun already chosen.
     * @param string                $stem     Stem of the head noun.
     * @param StemmerService        $stemmer  Stemmer.
     * @param SearchLanguageService $language Normaliser.
     * @param array                 $excluded Tokens that can never be a family.
     * @return array{key: string, entry: array}|null
     */
    private function phrase_entry($name, $head, $stem, $stemmer, $language, $excluded) {
        $tokens = preg_split('/\s+/u', $language->normalize_text((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || count($tokens) < 2) {
            return null;
        }

        // Everything up to the head noun qualifies it.
        $head_position = null;
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            if ($tokens[$i] === $head) {
                $head_position = $i;
                break;
            }
        }

        if ($head_position === null || $head_position === 0) {
            return null;
        }

        $qualifiers = array();
        foreach (array_slice($tokens, 0, $head_position) as $token) {
            // Marketing and grammar are not qualifiers, but a one-letter
            // distinction such as the `t` in "T-Shirts" absolutely is.
            if ($token === '' || isset($excluded[$token]) || preg_match('/^[0-9]+$/', $token)) {
                continue;
            }
            $qualifiers[] = $stemmer->stem($token);
        }

        $qualifiers = array_values(array_unique(array_filter($qualifiers)));
        if (empty($qualifiers)) {
            return null;
        }

        $key = implode(' ', array_merge($qualifiers, array($stem)));

        return array(
            'key' => $key,
            'entry' => array(
                'canonical' => $stem,
                'qualifiers' => $qualifiers,
                'source' => $head,
            ),
        );
    }

    /**
     * Learn the spellings a store actually uses, from its own product titles.
     *
     * A category named "Beanies" never proves the singular exists; the titles do.
     * Collecting observed forms is what keeps this free of language rules.
     *
     * @param array               $families Working records, by stem.
     * @param StemmerService      $stemmer  Stemmer.
     * @param SearchLanguageService $language Normaliser.
     * @param array               $excluded Tokens that can never be a family.
     * @return void
     */
    private function collect_title_surface_forms(&$families, $stemmer, $language, $excluded) {
        global $wpdb;

        $table = $wpdb->prefix . 'geekybot_product_index';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        $titles = $wpdb->get_col("SELECT title FROM {$table}");
        if (!is_array($titles)) {
            return;
        }

        foreach ($titles as $title) {
            $tokens = preg_split('/\s+/u', $language->normalize_text((string) $title), -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($tokens)) {
                continue;
            }

            foreach ($tokens as $token) {
                if (!$this->usable_token($token, $excluded)) {
                    continue;
                }

                $stem = $stemmer->stem($token);
                if ($stem === '' || !isset($families[$stem])) {
                    continue;
                }

                $families[$stem]['surface'][$token] = true;
            }
        }
    }

    /**
     * Flatten working records into lookup tables and persist them.
     *
     * @param array $families Working records, by stem.
     * @return array
     */
    private function store($families, $phrases = array()) {
        $terms = array();
        $gates = array();
        $neighbours = array();

        foreach ($families as $stem => $record) {
            $surface = array_keys((array) $record['surface']);
            sort($surface);

            // The canonical leads its own gate, so the compiled OR group always
            // contains it. Gate matching is anchored to a word start, so the stem
            // alone already covers every longer spelling that shares it.
            $gate = array_values(array_unique(array_merge(array($stem), $surface)));

            $children = array_keys((array) $record['children']);
            if (!empty($children)) {
                // A parent widens to its children: asking for "bags" should reach
                // the backpacks the merchant filed underneath.
                $gate = array_values(array_unique(array_merge($gate, $children)));
                $neighbours[$stem] = array_values(array_unique(array_merge(array($stem), $children)));
            }

            foreach (array_unique(array_merge($surface, array($stem))) as $lookup) {
                // Never overwrite: the first family to claim a token keeps it, so
                // a token shared by two categories stays stable between rebuilds.
                if (!isset($terms[$lookup])) {
                    $terms[$lookup] = $stem;
                    $gates[$lookup] = $gate;
                }
            }
        }

        $payload = array(
            'version' => self::VERSION,
            'built_at' => current_time('mysql'),
            'terms' => $terms,
            'gates' => $gates,
            'neighbours' => $neighbours,
            'phrases' => (array) $phrases,
        );

        update_option(self::OPTION, $payload, false);
        self::$memo = null;

        return array(
            'families' => count($families),
            'terms' => count($terms),
            'neighbours' => count($neighbours),
            'phrases' => count((array) $phrases),
        );
    }

    /**
     * Last usable token of a category name.
     *
     * Retail names its categories after the product: "Phone Cases" is a kind of
     * case, "Laptop Sleeves" a kind of sleeve. The leading words are qualifiers.
     *
     * @param string $name     Normalised category name.
     * @param array  $excluded Tokens that can never be a family.
     * @return string
     */
    private function head_noun($name, $excluded) {
        $tokens = preg_split('/\s+/u', (string) $name, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || empty($tokens)) {
            return '';
        }

        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            if ($this->usable_token($tokens[$i], $excluded)) {
                return $tokens[$i];
            }
        }

        return '';
    }

    /**
     * @param string $token    Candidate token.
     * @param array  $excluded Tokens that can never be a family.
     * @return bool
     */
    private function usable_token($token, $excluded) {
        $token = (string) $token;

        if ($token === '' || isset($excluded[$token])) {
            return false;
        }

        // Two characters cannot identify a product family, and InnoDB will not
        // index them either.
        if (strlen($token) < 3) {
            return false;
        }

        if (preg_match('/^[0-9]+$/', $token)) {
            return false;
        }

        return true;
    }

    /**
     * Tokens that must never become a family: grammar, marketing, and the
     * store's own colour and size values.
     *
     * @return array<string, bool>
     */
    private function excluded_tokens() {
        global $wpdb;

        $excluded = self::stopwords();
        $language = new SearchLanguageService();

        // Attribute values are how a shopper narrows a family, never the family.
        if (function_exists('wc_get_attribute_taxonomies')) {
            foreach (wc_get_attribute_taxonomies() as $attribute) {
                $taxonomy = 'pa_' . $attribute->attribute_name;
                if (!taxonomy_exists($taxonomy)) {
                    continue;
                }

                $values = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'names'));
                if (is_wp_error($values)) {
                    continue;
                }

                foreach ((array) $values as $value) {
                    foreach (preg_split('/\s+/u', $language->normalize_text((string) $value), -1, PREG_SPLIT_NO_EMPTY) as $token) {
                        $excluded[$token] = true;
                    }
                }
            }
        }

        // Colours and sizes already recorded against indexed products.
        $table = $wpdb->prefix . 'geekybot_product_index';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        $facets = $wpdb->get_col("SELECT CONCAT_WS(' ', color_terms, size_terms) FROM {$table}");
        if (is_array($facets)) {
            foreach ($facets as $facet) {
                foreach (preg_split('/\s+/u', $language->normalize_text((string) $facet), -1, PREG_SPLIT_NO_EMPTY) as $token) {
                    $excluded[$token] = true;
                }
            }
        }

        return $excluded;
    }

    /**
     * @return array
     */
    private function empty_map() {
        return array('terms' => array(), 'gates' => array(), 'neighbours' => array(), 'phrases' => array());
    }

    /**
     * Drop the cached vocabulary, so the next rebuild starts clean.
     *
     * @return void
     */
    public static function flush() {
        self::$memo = null;
        delete_option(self::OPTION);
    }
}
