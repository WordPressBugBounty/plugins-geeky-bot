<?php
namespace GeekyBot\Services;

use GeekyBot\Search\BuyerIntentLibrary;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language-aware shopper query helper.
 *
 * This class keeps product search local and fast while avoiding an English-only
 * stop-word list. It is intentionally deterministic: no AI calls, no remote data,
 * and all arrays are filterable by store owners/add-ons.
 */
class SearchLanguageService {
    private $buyer_intent_library = null;
    private $keyword_pattern_cache = array();
    private $intent_ignore_cache = array();
    private $stop_words_cache = array();
    private $stemmer = null;
    private $arabic_stripped_cache = array();
    /** @var array|null Compiled synonym lookup. See synonym_index(). */
    private $synonym_index = null;

    public function buyer_intent_profile($query) {
        return $this->buyer_intent_library()->profile($query);
    }

    public function remove_buyer_intent_tokens($terms, $profile) {
        return $this->buyer_intent_library()->remove_ignored_tokens($terms, is_array($profile) ? $profile : array());
    }

    private function buyer_intent_library() {
        if ($this->buyer_intent_library === null) {
            $this->buyer_intent_library = new BuyerIntentLibrary($this);
        }
        return $this->buyer_intent_library;
    }

    public function normalize_text($text) {
        $text = wp_strip_all_tags((string) $text);
        static $charset = null;
        if ($charset === null) {
            $charset = (string) get_bloginfo('charset');
            if ($charset === '') {
                $charset = 'UTF-8';
            }
        }
        $text = html_entity_decode($text, ENT_QUOTES, $charset);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        // Expand common English contractions before punctuation removal so
        // the apostrophe in "it's" cannot leave a standalone "s" token that
        // is later mistaken for clothing size S.
        $text = str_replace(array('’', '`'), "'", $text);
        $text = preg_replace("/\b(it|that|this|what|there|here|who|how|where|when|why)'s\b/u", '$1 is', $text);
        $text = $this->normalize_arabic_family_chars($text);
        $text = remove_accents($text);

        // Shopper-facing compound words must normalize consistently. Treat a
        // hyphen between letters/numbers as a word boundary so `in-stock`,
        // `water-resistant`, `red-white`, `size-42`, and their space-separated
        // forms produce the same search tokens. Product index text uses this
        // same normalizer, so USB-C and similar catalog values remain aligned.
        $text = preg_replace('/(?<=[\pL\pN])-(?=[\pL\pN])/u', ' ', $text);
        $text = preg_replace('/[^\pL\pN_\-\.\s\$£€₹₨¥₩₺]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim($text);
    }

    /**
     * Language the shopper is most likely writing in.
     *
     * Script detection comes first and is authoritative where it applies: a
     * query in Arabic or CJK characters is that language whatever page it was
     * typed on. It is useless between Latin-script languages, though -- Spanish
     * and English share a character set -- so for those the CMS is asked next.
     * WPML and Polylang already know which language page the shopper is on,
     * which is a far stronger signal than guessing from characters, and the
     * site locale is only the last resort.
     *
     * @param string $query Shopper query.
     * @return string Two-letter language code.
     */
    public function language_code($query = '') {
        $query = (string) $query;
        $site_locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $site_code = strtolower(substr((string) $site_locale, 0, 2));

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $query)) {
            // Order matters, and ی/ک cannot be used to tell these apart.
            // normalize_text() rewrites Arabic ي to ی and ك to ک -- correct for
            // matching -- so by the time most callers get here, Arabic text is
            // carrying the Persian/Urdu letterforms and the two are no longer
            // distinguishable by those characters alone.
            //
            // 1. Letters no Arabic text ever uses settle it for Urdu.
            if (preg_match('/[ٹڈڑںےۓہھ]/u', $query)) {
                return 'ur';
            }
            // 2. Letterforms no Urdu or Persian text uses settle it for Arabic.
            //    Only survive on a raw query; normalization folds all three.
            if (preg_match('/[يكة]/u', $query)) {
                return 'ar';
            }
            // 3. Shared Persian/Urdu letters, with Urdu already ruled out.
            if (preg_match('/[پچژگ]/u', $query)) {
                return 'fa';
            }
            // 4. Arabic is the fallback: a normalized Arabic query lands here,
            //    and so does the ambiguous residue of ی/ک with nothing else.
            return 'ar';
        }
        if (preg_match('/[\x{0900}-\x{097F}]/u', $query)) {
            return 'hi';
        }
        if (preg_match('/[\x{3040}-\x{30FF}]/u', $query)) {
            return 'ja';
        }
        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $query)) {
            return 'ko';
        }
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $query)) {
            return 'zh';
        }
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $query)) {
            return 'ru';
        }

        // Latin scripts share a character set, so nothing above can separate
        // French from English. The CMS is asked next and is usually right --
        // but on a monolingual store it answers with the SITE's language for
        // every shopper, which made the fr/de/it/pt/es packs unreachable: a
        // French question on an English shop was classified English, loaded the
        // English pack, and got no intent understanding at all. A small set of
        // function words settles the obvious cases before falling back.
        $marked = $this->latin_language_from_text($query);
        if ($marked !== '') {
            return $marked;
        }

        $cms_code = self::cms_language_code();
        if ($cms_code !== '') {
            return $cms_code;
        }

        return $site_code ? $site_code : 'en';
    }

    /**
     * Two-letter code, or '' when the value is not one.
     *
     * @param string $language Candidate code.
     * @return string
     */
    private function normalize_language_code($language) {
        $language = preg_replace('/[^a-z]/', '', strtolower((string) $language));

        return strlen((string) $language) === 2 ? $language : '';
    }

    /**
     * Latin-script language guessed from distinctive function words.
     *
     * Deliberately conservative, because a wrong answer here swaps both the
     * buyer-intent pack and the stop-word list. Only grammar words are listed:
     * a product noun would fire on a catalog term rather than on the shopper's
     * language. A guess is returned only when at least two markers hit and one
     * language leads outright -- anything ambiguous falls through to the CMS,
     * which is the behaviour this method replaced.
     *
     * Shared words are the reason for the two-hit floor: "para" is Spanish and
     * Portuguese, "una" is Spanish and Italian, "con" is Spanish and Italian.
     * One of those alone decides nothing.
     *
     * @param string $query Shopper query, raw.
     * @return string Two-letter code, or '' when undecided.
     */
    private function latin_language_from_text($query) {
        $query = $this->normalize_text($query);
        if ($query === '' || !preg_match('/[a-z]/', $query)) {
            return '';
        }

        // Grammar words first, and only ones that are not also ordinary English:
        // "son", "war", "hat", "no", "me" and "a" are all deliberately absent
        // because each is an English word or an English product, and a shopper
        // typing "de la soul t shirt" or "ich bin ein berliner mug" must stay
        // English. Inflected forms are listed because that is how the words
        // actually arrive: "gunstige", not "gunstig".
        //
        // Each list then continues with CONTENT words -- the colours, materials
        // and everyday retail nouns of that language. Grammar words alone left
        // a hole the size of ordinary shopping: "zwarte rugzak", "schwarzer
        // rucksack", "zaino nero" and "mochila negra" carry no grammar at all,
        // and every one of them was read as English, which swaps in the English
        // stop-word list and the English buyer-intent pack. "lichte rugzak voor
        // reizen" then kept `voor` as a required product term and matched
        // nothing. Short queries are the common case in search, so this was the
        // majority of real traffic in these six languages, not an edge.
        //
        // The English guard is unchanged, because none of these words is
        // decisive on its own: a content word is only ever counted towards the
        // two-hit floor, so "casa blanca poster" and "sono bello perfume" still
        // resolve to English on one hit. Words that are also English -- rot,
        // rode, hose, robe, pull, sale -- stay out of the lists entirely.
        $markers = array(
            'fr' => array('je', 'jai', 'nous', 'vous', 'avez', 'etes', 'sont', 'cherche', 'recherche', 'voudrais', 'veux', 'quelque', 'quelques', 'pour', 'avec', 'sans', 'dans', 'sous', 'cette', 'cet', 'ces', 'notre', 'votre', 'quelle', 'quelles', 'quel', 'quels', 'quoi', 'cher', 'chere', 'pas', 'mon', 'ma', 'mes', 'une', 'des', 'du', 'aux', 'est', 'qui', 'cadeau', 'moins', 'tres', 'aussi', 'encore', 'alors', 'donc', 'chez', 'bien', 'tout', 'toute', 'montrez', 'auriez', 'chaussures', 'chaussettes', 'bottes', 'pantalon', 'veste', 'manteau', 'casque', 'ecouteurs', 'montre', 'lunettes', 'bijoux', 'vetements', 'portefeuille', 'ceinture', 'echarpe', 'gants', 'jouet', 'bougie', 'sac', 'noir', 'noirs', 'noire', 'noires', 'blanche', 'bleue', 'verte', 'grise', 'rouges', 'legere', 'legers', 'impermeable', 'taille', 'confortable', 'solide', 'cuir', 'laine', 'coton', 'soie', 'argent', 'lin', 'femme', 'homme', 'enfant', 'enfants', 'tactiles', 'baskets', 'confortables'),
            'de' => array('ich', 'suche', 'suchen', 'brauche', 'mochte', 'eine', 'einen', 'einem', 'einer', 'eines', 'fur', 'mit', 'und', 'oder', 'nicht', 'sehr', 'haben', 'habt', 'hast', 'gibt', 'sind', 'bin', 'bist', 'das', 'der', 'die', 'den', 'dem', 'des', 'diese', 'dieser', 'dieses', 'mir', 'mich', 'uns', 'ihnen', 'sie', 'zeigen', 'zeig', 'geschenk', 'gunstig', 'gunstige', 'gunstigen', 'warme', 'warmen', 'leichte', 'leichten', 'neue', 'schone', 'kleine', 'beste', 'auch', 'noch', 'schon', 'etwas', 'welche', 'welcher', 'kaufen', 'bitte', 'schwarze', 'schwarzer', 'weisse', 'blaue', 'graue', 'braune', 'wasserdichte', 'kabellose', 'bequeme', 'robuste', 'rucksack', 'schuhe', 'stiefel', 'socken', 'kleid', 'pullover', 'jacke', 'regenjacke', 'kopfhorer', 'ohrhorer', 'sonnenbrille', 'schmuck', 'kleidung', 'handtasche', 'geldborse', 'gurtel', 'mutze', 'schal', 'handschuhe', 'spielzeug', 'kerze', 'grosse', 'grossen', 'grose', 'grosen', 'schwarzes', 'weisses', 'leder', 'wolle', 'baumwolle', 'seide', 'silber', 'leinen', 'damen', 'herren', 'kinder', 'wasserflasche', 'bequemer', 'sneaker'),
            'es' => array('busco', 'buscando', 'quiero', 'queria', 'necesito', 'tienes', 'tienen', 'tiene', 'hay', 'algo', 'muy', 'mas', 'para', 'con', 'sin', 'una', 'unos', 'unas', 'los', 'las', 'del', 'por', 'barato', 'barata', 'regalo', 'que', 'donde', 'cuando', 'como', 'porque', 'tambien', 'pero', 'desde', 'hasta', 'cada', 'otro', 'otra', 'mismo', 'bueno', 'buena', 'nuevo', 'nueva', 'estoy', 'este', 'esta', 'esos', 'esas', 'ese', 'esa', 'gracias', 'hola', 'muestrame', 'cuales', 'zapatos', 'botas', 'calcetines', 'vestido', 'sudadera', 'chaqueta', 'abrigo', 'auriculares', 'reloj', 'gafas', 'joyas', 'ropa', 'cinturon', 'bufanda', 'guantes', 'juguete', 'mochila', 'negra', 'negras', 'roja', 'ligera', 'comoda', 'impermeable', 'talla', 'inalambrico', 'inalambrica', 'inalambricos', 'baratas', 'baratos', 'comodo', 'cuero', 'lana', 'algodon', 'seda', 'plata', 'lino', 'mujer', 'hombre', 'ninos', 'zapatillas', 'comodas', 'comodos'),
            'it' => array('cerco', 'cercando', 'vorrei', 'voglio', 'sto', 'sono', 'siamo', 'avete', 'abbiamo', 'qualcosa', 'qualche', 'molto', 'poco', 'della', 'delle', 'degli', 'dalla', 'nella', 'sulla', 'per', 'con', 'senza', 'una', 'uno', 'che', 'non', 'mi', 'ho', 'hai', 'regalo', 'economico', 'questo', 'questa', 'quello', 'quella', 'anche', 'ancora', 'oppure', 'ogni', 'bene', 'buono', 'buona', 'nuovo', 'nuova', 'grazie', 'ciao', 'mostrami', 'serve', 'zaino', 'scarpe', 'stivali', 'calzini', 'pantaloni', 'felpa', 'giacca', 'cappotto', 'cuffie', 'orologio', 'occhiali', 'gioielli', 'abbigliamento', 'portafoglio', 'cintura', 'sciarpa', 'guanti', 'giocattolo', 'nera', 'nere', 'grigia', 'rossa', 'leggera', 'impermeabile', 'taglia', 'nero', 'neri', 'economici', 'economica', 'economiche', 'comoda', 'pelle', 'lana', 'cotone', 'seta', 'argento', 'lino', 'donna', 'uomo', 'bambini', 'comode', 'comodi'),
            'nl' => array('ik', 'zoek', 'zoeken', 'wil', 'heb', 'hebben', 'hebt', 'heeft', 'jullie', 'een', 'het', 'de', 'van', 'voor', 'met', 'zonder', 'niet', 'geen', 'mijn', 'deze', 'die', 'dat', 'welke', 'wat', 'goedkoop', 'goedkope', 'cadeau', 'nodig', 'graag', 'iets', 'erg', 'heel', 'ook', 'nog', 'maar', 'alsjeblieft', 'bedankt', 'laat', 'zien', 'rugzak', 'rugzakken', 'schoenen', 'laarzen', 'sokken', 'broek', 'jurk', 'trui', 'jassen', 'regenjas', 'koptelefoon', 'oordopjes', 'horloge', 'zonnebril', 'sieraden', 'kleding', 'handtas', 'portemonnee', 'sjaal', 'handschoenen', 'speelgoed', 'kaars', 'zwarte', 'witte', 'blauwe', 'groene', 'grijze', 'bruine', 'waterdichte', 'draadloze', 'lichte', 'zware', 'zachte', 'stevige', 'duurzame', 'comfortabele', 'dure', 'kleine', 'grote', 'maat', 'zwart', 'blauw', 'groen', 'bruin', 'wol', 'katoen', 'zijde', 'zilver', 'linnen', 'dames', 'heren', 'kinderen', 'waterfles', 'sneakers'),
            'pt' => array('estou', 'procurando', 'procuro', 'quero', 'queria', 'preciso', 'tenho', 'temos', 'voce', 'voces', 'nao', 'muito', 'uma', 'umas', 'uns', 'com', 'sem', 'para', 'pra', 'por', 'dos', 'das', 'presente', 'barato', 'barata', 'obrigado', 'obrigada', 'tem', 'esta', 'este', 'essa', 'esse', 'tambem', 'mas', 'desde', 'ate', 'cada', 'outro', 'outra', 'novo', 'nova', 'bom', 'boa', 'como', 'quando', 'onde', 'porque', 'qual', 'quais', 'mostra', 'ola', 'sapatos', 'meias', 'calca', 'calcas', 'moletom', 'jaqueta', 'casaco', 'fones', 'relogio', 'oculos', 'joias', 'roupas', 'carteira', 'cinto', 'cachecol', 'luvas', 'brinquedo', 'mochila', 'preta', 'pretas', 'branca', 'vermelha', 'leve', 'confortavel', 'impermeavel', 'tamanho', 'baratos', 'baratas', 'confortaveis', 'couro', 'algodao', 'seda', 'prata', 'linho', 'mulher', 'homem', 'criancas', 'masculino', 'feminino', 'preto', 'pretos', 'tenis'),
        );

        // Verbs and pronouns that belong to exactly one of these languages and to
        // no English sentence. One of them is enough on its own, because the
        // two-hit floor exists to guard against SHARED words -- and nothing here
        // is shared. "avete scarpe da ginnastica" and "tienen camisetas de
        // algodon" carry exactly one grammar word each, and both are decisive.
        $strong = array(
            'fr' => array('je', 'jai', 'voudrais', 'cherche', 'avez', 'montrez', 'auriez'),
            'de' => array('ich', 'suche', 'suchen', 'brauche', 'mochte', 'zeigen'),
            'es' => array('busco', 'buscando', 'quiero', 'necesito', 'tienen', 'tienes', 'estoy', 'muestrame'),
            'it' => array('cerco', 'cercando', 'vorrei', 'voglio', 'avete', 'abbiamo', 'mostrami'),
            'pt' => array('estou', 'procurando', 'procuro', 'quero', 'preciso', 'voces'),
            'nl' => array('zoek', 'zoeken', 'jullie', 'alsjeblieft', 'goedkope'),
            // 'ik' is not decisive on its own: it is two letters and turns up as
            // a brand token. A real Dutch question carries several markers, so
            // it still resolves through the ordinary two-hit rule.
        );

        $tokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || count($tokens) < 2) {
            return '';
        }
        $tokens = array_fill_keys($tokens, true);

        $scores = array();
        foreach ($markers as $code => $words) {
            $hits = 0;
            foreach ($words as $word) {
                if (isset($tokens[$word])) {
                    $hits++;
                }
            }

            $decisive = false;
            foreach ((array) ($strong[$code] ?? array()) as $word) {
                if (isset($tokens[$word])) {
                    $decisive = true;
                    break;
                }
            }

            if ($hits >= 2 || ($decisive && $hits >= 1)) {
                // A decisive marker also breaks a tie against a language that
                // only matched shared words.
                $scores[$code] = $decisive ? $hits + 1 : $hits;
            }
        }

        if (empty($scores)) {
            return '';
        }

        arsort($scores);
        $codes = array_keys($scores);
        $best = $codes[0];
        if (count($codes) > 1 && $scores[$codes[1]] === $scores[$best]) {
            // A tie means the markers that fired are the shared ones. Undecided.
            return '';
        }

        /**
         * Filters the Latin-script language guessed from a shopper query.
         *
         * @param string $best  Two-letter code, or '' when undecided.
         * @param string $query Normalized shopper query.
         * @param array  $scores Marker hit counts per language.
         */
        return (string) apply_filters('geekybot_search_latin_language', $best, $query, $scores);
    }

    /**
     * Current language according to a multilingual plugin, if one is active.
     *
     * @return string Two-letter code, or '' when no plugin answers.
     */
    public static function cms_language_code() {
        $code = '';

        // Polylang.
        if (function_exists('pll_current_language')) {
            $code = (string) pll_current_language('slug');
        }

        // WPML. The filter is the documented API; the constant is the fallback
        // for older versions that never registered it.
        if ($code === '' && function_exists('apply_filters')) {
            $wpml = apply_filters('wpml_current_language', null);
            if (is_string($wpml)) {
                $code = $wpml;
            }
        }

        if ($code === '' && defined('ICL_LANGUAGE_CODE')) {
            $code = (string) ICL_LANGUAGE_CODE;
        }

        /**
         * Filters the detected CMS language for Geeky Bot search.
         *
         * Lets a site using another multilingual plugin supply the same signal.
         *
         * @param string $code Two-letter language code, or '' when unknown.
         */
        $code = (string) apply_filters('geekybot_cms_language_code', $code);

        $code = strtolower(substr(trim($code), 0, 2));

        return preg_match('/^[a-z]{2}$/', $code) ? $code : '';
    }

    /**
     * @param string $query    Text to tokenise.
     * @param string $language Two-letter code to tokenise AS, when the caller
     *                         already knows it. See below for why that matters.
     */
    public function query_terms($query, $language = '') {
        $normalized = $this->normalize_text($query);
        if ($normalized === '') {
            return array();
        }

        // Detecting here is right for a raw question and wrong for a stripped
        // one. analyze_query() removes filler, price and buyer-modifier phrases
        // before tokenising, and those phrases are exactly where a Latin-script
        // language announces itself -- "je cherche des chaussures pas cher"
        // reaches this method as "des chaussures", which no longer looks French,
        // falls back to the site language, and keeps `des` as a REQUIRED product
        // term because it is not an English stop word. Callers that hold the
        // shopper's original wording pass the language they detected from it.
        $language = $this->normalize_language_code($language);
        if ($language === '') {
            $language = $this->language_code($normalized);
        }
        $stop = $this->stop_words($language);
        $terms = array();

        preg_match_all('/[\pL\pN][\pL\pN_\-\.]{0,60}/u', $normalized, $matches);
        foreach ((array) $matches[0] as $part) {
            $part = trim($part, " \t\n\r\0\x0B.-_");
            if ($part === '' || in_array($part, $stop, true)) {
                continue;
            }

            $part = $this->normalize_search_token($part, $language);
            if ($part === '' || in_array($part, $stop, true) || $this->is_weak_shopper_token($part)) {
                continue;
            }
            if ($this->token_length($part) < 2 && !preg_match('/^\d+$/', $part)) {
                continue;
            }
            $terms[] = $part;
        }

        // For CJK queries without spaces, keep the full phrase and useful 2-char chunks.
        if (in_array($language, array('zh', 'ja', 'ko'), true) && $this->token_length($normalized) > 2 && strpos($normalized, ' ') === false) {
            $terms[] = $normalized;
            if (function_exists('mb_substr') && function_exists('mb_strlen')) {
                $len = mb_strlen($normalized, 'UTF-8');
                for ($i = 0; $i < $len - 1; $i++) {
                    $terms[] = mb_substr($normalized, $i, 2, 'UTF-8');
                }
            }
        }

        /**
         * Filters final product-search query terms.
         *
         * @param array  $terms      Search terms after normalization/stop-word removal.
         * @param string $normalized Normalized shopper query.
         * @param string $language   Detected language code.
         */
        $terms = (array) apply_filters('geekybot_search_query_terms', $terms, $normalized, $language);
        return array_values(array_unique(array_filter($terms)));
    }


    public function product_facets_from_query($query, $terms = array()) {
        $query = $this->normalize_text($query);
        $token_text = ' ' . implode(' ', array_unique(array_merge($this->query_terms($query), (array) $terms))) . ' ';

        $colors = array();
        foreach ($this->color_keyword_map() as $canonical => $aliases) {
            foreach ((array) $aliases as $alias) {
                $alias = $this->normalize_text($alias);
                if ($alias === '') {
                    continue;
                }
                if ($this->contains_phrase($query, $alias) || strpos($token_text, ' ' . $alias . ' ') !== false) {
                    $colors[] = $canonical;
                    $colors = array_merge($colors, (array) $aliases);
                    break;
                }
            }
        }

        // Longest alias wins, and the query text decides. See
        // longest_size_matches() for why the first alias that hits is the wrong
        // one to stop at.
        $size_map = $this->size_keyword_map();
        $matched = $this->longest_size_matches($query, $size_map, false);
        foreach ($this->longest_size_matches($token_text, $size_map, true) as $canonical => $alias) {
            if (isset($matched[$canonical])) {
                continue;
            }
            // The term list has been through stop-word removal, so a qualified
            // size can arrive here with its qualifier gone: "очень большой
            // размер" reaches the tokens as "большой размер" because `очень` is
            // a Russian stop word, and "taille tres grande" as "taille grande"
            // because `tres` is a French one -- each then reads as L rather than
            // XL. A term match that the query's own alias already accounts for
            // is that same phrase, stripped: not a second size the shopper asked
            // for.
            $swallowed = false;
            foreach ($matched as $query_alias) {
                if ($query_alias !== $alias && $this->size_alias_covers($query_alias, $alias)) {
                    $swallowed = true;
                    break;
                }
            }
            if (!$swallowed) {
                $matched[$canonical] = $alias;
            }
        }

        $sizes = array();
        foreach (array_keys($matched) as $canonical) {
            $sizes[] = $canonical;
            $sizes = array_merge($sizes, (array) $size_map[$canonical]);
        }

        // The word for "size" in every language the plugin ships, so "taglia 42",
        // "tamanho 42" and "サイズ42" read as a size filter rather than as two
        // unrelated product terms.
        if (preg_match_all('/(?<![\pL\pN_])(?:sizes|sized|size|uk|us|eu|سائز|مقاس|taille|pointure|größe|grösse|grosse|grose|talla|taglia|misura|tamanho|numero|サイズ|尺码|尺碼|尺寸|码|碼|maat|사이즈|размер|разм)(?:\s*(?:of\s+)?[:#-]\s*|\s+(?:of\s+)?|\s*(?=[0-9]))([0-9]{1,3}(?:\.[0-9])?|xxxs|xxs|xs|s|m|l|xl|xxl|xxxl|small|medium|large|one\s*size)(?![\pL\pN_])/u', $query, $matches)) {
            foreach ((array) $matches[1] as $size) {
                $sizes = array_merge($sizes, $this->facet_terms_for_index(array($size), 'size'));
            }
        }
        if (preg_match_all('/\b([0-9]{1,3}(?:\.[0-9])?)\s*(?:size|sizes|uk|us|eu)\b/u', $query, $matches)) {
            foreach ((array) $matches[1] as $size) {
                $sizes[] = $size;
            }
        }

        /**
         * Filters structured search facets parsed from a shopper query.
         *
         * @param array  $facets Parsed facets with colors and sizes.
         * @param string $query  Normalized shopper query.
         * @param array  $terms  Search terms.
         */
        $facets = apply_filters('geekybot_search_facets', array(
            'colors' => $this->normalize_facet_term_list($colors),
            'sizes' => $this->normalize_facet_term_list($sizes),
        ), $query, (array) $terms);

        return is_array($facets) ? $facets : array('colors' => array(), 'sizes' => array());
    }

    public function facet_terms_for_index($values, $type) {
        $terms = array();
        $map = $type === 'color' ? $this->color_keyword_map() : $this->size_keyword_map();

        foreach ((array) $values as $value) {
            $value = $this->normalize_text($value);
            if ($value === '') {
                continue;
            }
            $terms[] = $value;
            foreach ($this->query_terms($value) as $token) {
                $terms[] = $token;
            }
            foreach ($map as $canonical => $aliases) {
                foreach ((array) $aliases as $alias) {
                    $alias = $this->normalize_text($alias);
                    if ($alias !== '' && ($value === $alias || $this->contains_phrase($value, $alias))) {
                        $terms[] = $canonical;
                        $terms = array_merge($terms, (array) $aliases);
                        break 2;
                    }
                }
            }
        }

        return $this->normalize_facet_term_list($terms);
    }

    public function is_color_attribute_label($label) {
        $label = $this->normalize_text($label);
        return (bool) preg_match('/(?:^|\s)(?:pa_)?(?:color|colour|رنگ|لون|couleur|farbe|talla-color)(?:\s|$)/u', $label)
            || strpos($label, 'pa_color') !== false
            || strpos($label, 'pa_colour') !== false;
    }

    public function is_size_attribute_label($label) {
        $label = $this->normalize_text($label);
        return (bool) preg_match('/(?:^|\s)(?:pa_)?(?:size|sizes|sizing|سائز|مقاس|taille|größe|grösse|talla|grosse)(?:\s|$)/u', $label)
            || strpos($label, 'pa_size') !== false;
    }

    public function price_range_from_query($query) {
        $query = trim($this->normalize_text($query), " \t\n\r\0\x0B.,!?;:");
        $money = '(?:\$|£|€|₹|₨|¥|₩|₺|rs\.?|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try)?\s*([0-9][0-9,]*(?:\.[0-9]+)?)';
        $currency_money = '(?:\$|£|€|₹|₨|¥|₩|₺|rs\.?|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try)\s*([0-9][0-9,]*(?:\.[0-9]+)?)';

        $between = $this->keyword_pattern($this->between_keywords());
        $under = $this->keyword_pattern($this->under_keywords());
        $over = $this->keyword_pattern($this->over_keywords());
        $around = $this->keyword_pattern($this->around_keywords());
        $budget = $this->keyword_pattern($this->budget_keywords());
        $target = $this->keyword_pattern($this->price_target_keywords());
        $and_to = $this->keyword_pattern(array('and', 'to', '-', 'اور', 'سے', 'تا', 'الى', 'إلى', 'و', 'y', 'a', 'et', 'bis', 'e', 'ile'));

        // The word that joins "budget" or "price" to the amount. Without these
        // the keyword matched and the number did not: "mijn budget is 50 euro"
        // and "mein budget ist 50" both found `budget`, then failed on the
        // copula that follows it, and returned no price filter at all -- so the
        // amount survived into the term list as a product word. Every entry is
        // only ever tested directly between one of those keywords and a digit,
        // which is why a preposition as common as `de` is safe here.
        $connector = '(?:(?:of|is|at|are|van|ist|est|sont|es|son|de|del|di|da|do)\s+)?';

        // Compound comparison phrases must be checked before generic "below"
        // and "above" keywords so "not below 25" means a minimum, not a maximum.
        if (preg_match('/(?:^|\s)(?:not below|at least|no less than)\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => $this->parse_price_number($m[1]), 'max' => null, 'mode' => 'min');
        }
        if (preg_match('/(?:^|\s)(?:not above|at most|no more than)\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }

        // Japanese and Chinese put the comparison AFTER the amount and use no
        // spaces, so every pattern below -- each anchored on whitespace and each
        // expecting operator-then-number -- misses them entirely: 「5000円以下」
        // and 「50元以下」 returned no price filter at all, and the amount then
        // survived into the term list as a product word.
        // Korean is included here, and written with spaces unlike the other two:
        // 「50000원 이하」 puts a space between the amount and the comparison, so
        // the separator below is optional whitespace rather than nothing.
        if (preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $query)) {
            $cjk_amount = '([0-9][0-9,]*(?:\.[0-9]+)?)\s*(?:円|元|块|塊|圓|원|₩|ドル|ユーロ|美元|欧元|달러)?\s*';
            if (preg_match('/' . $cjk_amount . '(?:から|到|부터)\s*' . $cjk_amount . '(?:まで|之间|之間|까지|사이)?/u', $query, $m)) {
                return array('min' => $this->parse_price_number($m[1]), 'max' => $this->parse_price_number($m[2]), 'mode' => 'range');
            }
            if (preg_match('/' . $cjk_amount . '(?:以下|未満|以内|まで|不到|不超过|不超過|最多|이하|미만|이내|까지)/u', $query, $m)) {
                return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
            }
            if (preg_match('/' . $cjk_amount . '(?:以上|超过|超過|より上|起|이상|초과)/u', $query, $m)) {
                return array('min' => $this->parse_price_number($m[1]), 'max' => null, 'mode' => 'min');
            }
            if (preg_match('/' . $cjk_amount . '(?:くらい|ぐらい|前後|程度|左右|大约|大約|정도|쯤)/u', $query, $m)) {
                $target_price = $this->parse_price_number($m[1]);
                $padding = max(5, $target_price * 0.15);
                return array('min' => max(0, $target_price - $padding), 'max' => $target_price + $padding, 'mode' => 'around', 'target' => $target_price);
            }
            // 予算5000円 / 예산은 50000원 / 价格500 -- the words for budget and
            // price are written hard against the amount here, and Korean glues
            // a topic particle onto the noun (예산은). Every pattern below
            // demands whitespace between the keyword and the digits, so none of
            // them can see any of this. Last in this block on purpose: 以下 and
            // 以上 above are more specific and must keep their answer.
            if (preg_match('/(?:予算|预算|預算|예산|価格|値段|价格|價格|售价|售價|가격|금액)[^0-9]{0,6}' . $cjk_amount . '/u', $query, $m)) {
                return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
            }
        }

        if (preg_match('/(?:^|\s)(?:' . $between . ')\s+' . $money . '\s*(?:' . $and_to . ')\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => $this->parse_price_number($m[1]), 'max' => $this->parse_price_number($m[2]), 'mode' => 'range');
        }
        if (preg_match('/(?:^|\s)(?:' . $under . ')\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)(?:' . $over . ')\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => $this->parse_price_number($m[1]), 'max' => null, 'mode' => 'min');
        }

        // Natural shopper phrases: "with price of 35", "price 35", "budget 35", "for $35".
        // These usually mean a budget/maximum in commerce search, not a keyword.
        if (preg_match('/(?:^|\s)(?:' . $budget . ')\s*' . $connector . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)(?:with\s+)?(?:' . $target . ')\s*' . $connector . $money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)(?:for\s+)' . $currency_money . '(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)' . $currency_money . '\s*(?:budget|price|dollar|dollars|rs|pkr)?(?:\s|$)/u', $query, $m)) {
            return array('min' => null, 'max' => $this->parse_price_number($m[1]), 'mode' => 'max');
        }
        if (preg_match('/(?:^|\s)(?:' . $around . ')\s*' . $money . '(?:\s|$)/u', $query, $m)) {
            $target_price = $this->parse_price_number($m[1]);
            // "circa 1950 poster" is a date, not a budget. A bare four-digit
            // year with no currency anywhere in the sentence is not an amount,
            // and reading it as one filtered the catalog to a price band no
            // product sits in. An explicit currency ("around 1950 rupees")
            // still parses, because then the shopper has said it is money.
            if ($target_price >= 1900 && $target_price <= 2099
                && floor($target_price) === $target_price
                && !preg_match('/\$|£|€|₹|₨|¥|₩|₺|\b(?:rs|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try|dollars?|euros?|pounds?|rupees?|reais|yen|yuan)\b/u', $query)) {
                return null;
            }
            $padding = max(5, $target_price * 0.15);
            return array('min' => max(0, $target_price - $padding), 'max' => $target_price + $padding, 'mode' => 'around', 'target' => $target_price);
        }

        return null;
    }

    public function strip_price_filters($query) {
        $query = trim($this->normalize_text($query), " \t\n\r\0\x0B.,!?;:");
        // The amount may be followed by the NAME of the currency rather than a
        // symbol, and whatever this leaves behind becomes a required product
        // term: "мой бюджет 5000 рублей" stripped to "мой рублей" and then
        // searched the catalog for roubles. Every price pattern below consumes
        // the word along with the number.
        $currency_word = '(?:\s*(?:dollars?|euros?|pounds?|rupees?|reais|yen|yuan|won|riyals?|dirhams?|euro|eur|usd|gbp|inr|pkr|rs|евро|рубл(?:ей|я|ь|ях)|руб|доллар(?:ов|а|ы)?|юан(?:ей|я)|вон|иен))?';
        $money = '(?:\$|£|€|₹|₨|¥|₩|₺|rs\.?|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try)?\s*[0-9][0-9,]*(?:\.[0-9]+)?' . $currency_word;
        $currency_money = '(?:\$|£|€|₹|₨|¥|₩|₺|rs\.?|pkr|usd|eur|gbp|aed|sar|qar|kwd|inr|try)\s*[0-9][0-9,]*(?:\.[0-9]+)?' . $currency_word;
        $between = $this->keyword_pattern($this->between_keywords());
        $under = $this->keyword_pattern($this->under_keywords());
        $over = $this->keyword_pattern($this->over_keywords());
        $around = $this->keyword_pattern($this->around_keywords());
        $budget = $this->keyword_pattern($this->budget_keywords());
        $target = $this->keyword_pattern($this->price_target_keywords());
        $and_to = $this->keyword_pattern(array('and', 'to', '-', 'اور', 'سے', 'تا', 'الى', 'إلى', 'و', 'y', 'a', 'et', 'bis', 'e', 'ile'));
        // Must stay identical to the connector in price_range_from_query(): a
        // phrase this function fails to strip is one the parser already turned
        // into a filter, so the words survive as required product terms and the
        // filtered result set is then searched for the word "budget".
        $connector = '(?:(?:of|is|at|are|van|ist|est|sont|es|son|de|del|di|da|do)\s+)?';
        // Japanese, Chinese and Korean write the comparison after the amount and
        // without spaces, so not one of the whitespace-anchored patterns below
        // can reach them. These mirror the CJK block in price_range_from_query()
        // -- which has parsed them since the twelve-language release, while this
        // function went on leaving 「5000円以下」 in the query as a product term.
        $cjk_amount = '[0-9][0-9,]*(?:\.[0-9]+)?\s*(?:円|元|块|塊|圓|원|₩|ドル|ユーロ|美元|欧元|달러)?\s*';

        $patterns = array(
            '/(?:予算|预算|預算|예산|価格|値段|价格|價格|售价|售價|가격|금액)[^0-9]{0,6}' . $cjk_amount . '/u',
            '/' . $cjk_amount . '(?:から|到|부터)\s*' . $cjk_amount . '(?:まで|之间|之間|까지|사이)?/u',
            '/' . $cjk_amount . '(?:以下|未満|以内|まで|不到|不超过|不超過|最多|이하|미만|이내|까지)/u',
            '/' . $cjk_amount . '(?:以上|超过|超過|より上|起|이상|초과)/u',
            '/' . $cjk_amount . '(?:くらい|ぐらい|前後|程度|左右|大约|大約|정도|쯤)/u',
            '/(?:^|\s)(?:not below|at least|no less than)\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:not above|at most|no more than)\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $between . ')\s+' . $money . '\s*(?:' . $and_to . ')\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $under . ')\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $over . ')\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $budget . ')\s*' . $connector . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:with\s+)?(?:' . $target . ')\s*' . $connector . $money . '(?=\s|$)/u',
            '/(?:^|\s)(?:for\s+)' . $currency_money . '(?=\s|$)/u',
            '/(?:^|\s)(?:' . $around . ')\s*' . $money . '(?=\s|$)/u',
            '/(?:^|\s)' . $currency_money . '\s*(?:budget|price|dollar|dollars|rs|pkr)?(?=\s|$)/u',
        );

        $query = preg_replace($patterns, ' ', $query);
        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    public function strip_commerce_phrases($query) {
        $query = $this->normalize_text($query);
        $query = $this->strip_recommendation_scaffolding($query);
        $phrases = array(
            'do you have', 'do u have', 'do have', 'have you got', 'have got', 'is there', 'are there',
            'i am looking for', 'im looking for', 'i am searching for', 'i need', 'i want', 'looking for',
            'can you please find', 'could you please find', 'please find', 'help me find', 'can you find',
            'can you show me', 'show me', 'give me', 'find me', 'find', 'search for', 'suggest me', 'recommend me', 'can you recommend',
            'what do you recommend', 'what would you recommend', 'which one should i buy', 'i am not sure what to buy',
            'available for sale', 'for sale', 'in stock', 'available', 'available ones', 'ready to ship', 'current catalog',
            'کیا اپ کے پاس', 'کیا آپ کے پاس', 'مجھے چاہیے', 'مجھے چاہیئے', 'دکھاو', 'دکھاؤ', 'تلاش کرو',
            'هل لديك', 'اريد', 'أريد', 'اعرض لي', 'اظهر لي',
            'estoy buscando', 'busco', 'muéstrame', 'muestrame', 'quiero',
            'je cherche', 'montre moi', 'je veux',
            'ich suche', 'zeige mir', 'ich mochte', 'ich möchte'
        );

        /**
         * Filters conversational shopping phrases removed before product matching.
         *
         * @param array  $phrases Phrases that express intent but are not product terms.
         * @param string $query   Normalized shopper query.
         */
        $phrases = (array) apply_filters('geekybot_search_commerce_phrases', $phrases, $query);
        foreach ($phrases as $phrase) {
            $phrase = $this->normalize_text($phrase);
            if ($phrase === '') {
                continue;
            }
            $query = preg_replace('/(?:^|\s)' . preg_quote($phrase, '/') . '(?=\s|$)/u', ' ', $query);
        }

        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    /**
     * Removes recommendation-request scaffolding while preserving the actual
     * product family, facets, price limits, and shopper preferences.
     *
     * This runs before catalog term extraction so natural requests such as
     * "recommend an affordable speaker" and "could you suggest a rain
     * jacket under $80" do not turn recommend/suggest into product identity.
     * Product Expert questions are routed before Product Discovery, so factual
     * prompts such as "which color do you recommend" remain unaffected.
     */
    private function strip_recommendation_scaffolding($query) {
        $query = $this->normalize_text($query);
        if ($query === '') {
            return '';
        }

        $patterns = array(
            // Leading imperative and polite recommendation requests.
            '/^(?:please\s+)?(?:can|could|would|will)\s+you\s+(?:please\s+)?(?:recommend|suggest)\s+(?:me\s+)?/u',
            '/^(?:please\s+)?(?:recommend|suggest)\s+(?:me\s+)?/u',
            // Noun-shaped requests such as "give me a recommendation for...".
            '/^(?:please\s+)?(?:give|show)\s+me\s+(?:(?:your|the|a|some)\s+)?(?:best\s+)?(?:recommendations?|suggestions?)\s*(?:for|on|about)?\s*/u',
            '/^(?:what|which)\s+(?:is|are)\s+(?:your|the)\s+(?:best\s+)?(?:recommendations?|suggestions?)\s*(?:for|on|about)?\s*/u',
            // Trailing decision clauses preserve the product words before them.
            '/\b(?:do|would|can|could|will|should)\s+you\s+(?:recommend|suggest)\b/u',
            '/\b(?:would|do|should)\s+i\s+(?:buy|choose|pick)\b/u',
        );

        $query = preg_replace($patterns, ' ', $query);
        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    public function remove_intent_terms($terms, $intent = 'search') {
        $terms = array_values(array_unique(array_filter((array) $terms)));
        if (empty($terms)) {
            return array();
        }

        $intent = sanitize_key((string) $intent);
        $cache_key = $intent !== '' ? $intent : 'search';
        if (!isset($this->intent_ignore_cache[$cache_key])) {
            $ignore_phrases = array_merge(
                $this->product_keywords(),
                $this->latest_keywords(),
                $this->top_rated_keywords(),
                $this->popular_keywords(),
                $this->under_keywords(),
                $this->over_keywords(),
                $this->between_keywords(),
                $this->around_keywords(),
                $this->budget_keywords(),
                $this->price_target_keywords(),
                array('sale', 'sales', 'discount', 'discounted', 'discounts', 'deal', 'deals', 'offer', 'offers', 'for sale', 'on sale')
            );

            if ($intent === 'sale') {
                $ignore_phrases = array_merge($ignore_phrases, $this->sale_keywords());
            }

            $ignore = array();
            foreach ($ignore_phrases as $phrase) {
                foreach ($this->query_terms($phrase) as $token) {
                    $ignore[$token] = true;
                }
            }
            $this->intent_ignore_cache[$cache_key] = $ignore;
        }
        $ignore = $this->intent_ignore_cache[$cache_key];

        $filtered = array();
        foreach ($terms as $term) {
            $term = $this->normalize_search_token($term, $this->language_code($term));
            if ($term === '' || isset($ignore[$term])) {
                continue;
            }
            $filtered[] = $term;
        }

        return array_values(array_unique($filtered));
    }

    public function catalog_intent($query) {
        $query = $this->normalize_text($query);

        // In shopper language, "belt for sale" usually means availability, not a discounted sale.
        if ($this->contains_any_phrase($query, array('for sale', 'available for sale'))) {
            return 'search';
        }

        if ($this->contains_any_phrase($query, $this->latest_keywords())) {
            return 'latest';
        }
        if ($this->contains_any_phrase($query, $this->sale_keywords())) {
            return 'sale';
        }
        if ($this->contains_any_phrase($query, $this->top_rated_keywords())) {
            return 'top_rated';
        }
        if ($this->contains_any_phrase($query, $this->popular_keywords())) {
            return 'popular';
        }
        return 'search';
    }

    public function is_in_stock_query($query) {
        $query = $this->normalize_text($query);
        // normalize_text deliberately preserves periods for decimal prices. A
        // shopper sentence such as “Only in stock.” must still be recognized as
        // a stock filter, so ignore sentence-ending punctuation for this check.
        $query = rtrim($query, " \t\n\r\0\x0B.,!?;:");

        if (preg_match('/(?:only\s+)?(?:available|in\s*stock|instock|ready\s+to\s+ship)(?:\s|$)/u', $query)) {
            return true;
        }

        if (preg_match('/(?:not|no|dont|don\s+t|do\s+not|exclude|excluding|avoid)\s+(?:want\s+)?(?:any\s+)?(?:out[-\s]?of[-\s]?stock|unavailable)/u', $query)) {
            return true;
        }

        return $this->contains_any_phrase($query, array(
            'in stock', 'available', 'available ones', 'only available', 'currently available', 'actually in stock', 'ready to ship', 'instock', 'for sale', 'available for sale',
            'not out of stock', 'not out-of-stock', 'not outofstock', 'do not want out of stock', 'do not want out-of-stock', 'dont want out of stock', 'dont want out-of-stock', 'don t want out of stock', 'don t want out-of-stock', "don't want out of stock", "don't want out-of-stock", 'exclude out of stock', 'exclude out-of-stock', 'no out of stock', 'no out-of-stock',
            'دستیاب', 'موجود', 'اسٹاک', 'سٹاک', 'متوفر', 'في المخزون', 'متوفره', 'متوفرة', 'متاح', 'متاحة', 'موجودة', 'متوفرين',
            'disponible', 'disponibles', 'en stock', 'solo en stock', 'hay stock',
            'auf lager', 'nur auf lager', 'vorratig', 'vorrätig', 'lieferbar', 'sofort lieferbar',
            'disponibile', 'disponibili', 'solo disponibili', 'in magazzino', 'pronta consegna',
            'em estoque', 'apenas em estoque', 'tem em estoque', 'disponivel', 'disponível', 'pronta entrega',
            '在庫', '在庫あり', '在庫がある', '入荷済み', 'すぐ発送',
            '有货', '有貨', '现货', '現貨', '有库存', '有庫存', '备货',
            'op voorraad', 'voorradig', 'direct leverbaar', 'beschikbaar',
            '재고', '재고 있는', '재고있음', '바로 배송',
            'в наличии', 'есть в наличии', 'на складе', 'доступно',
            'stokta', 'stok tersedia',
        ));
    }

    public function contains_value_signal($query) {
        $query = $this->normalize_text($query);
        return $this->contains_any_phrase($query, array(
            'best value', 'good value', 'value for money', 'balanced choice', 'safe choice', 'safest choice', 'worth buying', 'not cheapest', 'not the cheapest', 'not lowest', 'not the lowest', 'not just cheap', 'not only cheap', 'not the lowest price'
        ));
    }

    public function is_product_question($message) {
        $message = $this->normalize_text($message);
        $needles = array_merge(
            $this->product_keywords(),
            $this->under_keywords(),
            $this->over_keywords(),
            $this->between_keywords(),
            $this->sale_keywords(),
            $this->latest_keywords(),
            $this->popular_keywords(),
            $this->top_rated_keywords()
        );
        if ($this->contains_any_phrase($message, $needles)) {
            return true;
        }
        $terms = $this->query_terms($message);
        return count($terms) > 0 && count($terms) <= 5;
    }

    public function contains_budget_signal($query) {
        $query = $this->normalize_text($query);
        if ($this->contains_any_phrase($query, array('not cheapest', 'not the cheapest', 'not lowest', 'not the lowest', 'not cheap option', 'not the cheap option',
            // "not the cheapest" is a best-value request, not a budget one, in any
            // language. Without these the Arabic pack fires decision:best_value and
            // contains_budget_signal() contradicted it by sorting on price ascending.
            'ليس الارخص', 'ليست الارخص', 'ليس الاقل سعرا', 'مش الارخص',
            'no el mas barato', 'no la mas barata'))) {
            return false;
        }

        $signals = array_merge(
            array('cheap', 'cheapest', 'affordable', 'low price', 'low priced', 'budget friendly', 'budget-friendly', 'not expensive', 'not too expensive', 'reasonable price', 'good price', 'value for money'),
            $this->budget_keywords(),
            array('سستا', 'سستی', 'کم قیمت',
                'رخيص', 'رخيصة', 'ارخص', 'أرخص', 'الارخص', 'الأرخص', 'اقتصادي', 'اقتصادية', 'سعر منخفض', 'بسعر معقول', 'غير مكلف', 'غير مكلفة',
                'ليس غالي', 'ليست غالية', 'غير غالي', 'غير غالية', 'مش غالي', 'بسعر مناسب',
                'barato', 'barata', 'baratos', 'baratas', 'abordable', 'pas cher', 'günstig', 'gunstig',
                'economico', 'económico', 'economica', 'económica', 'economicos', 'economicas')
        );

        return $this->contains_any_phrase($query, $signals);
    }

    public function negated_price_terms_from_query($query) {
        $query = $this->normalize_text($query);
        if (!$this->contains_any_phrase($query, array(
            'not expensive', 'not too expensive', 'not costly', 'not too costly', 'not high price', 'not high priced',
            'not pricey', 'not too pricey', 'not premium price', 'not luxury price',
        ))) {
            return array();
        }

        return array('expensive', 'costly', 'pricey', 'premium', 'luxury', 'high', 'higher');
    }

    public function expand_synonyms($query) {
        $expansion = $this->expand_synonyms_map($query);

        return $expansion['text'];
    }

    /**
     * Synonym expansion that remembers WHICH shopper word produced each
     * alternate.
     *
     * expand_synonyms() flattens everything into one string, which loses the
     * only fact the caller needs to place an alternate correctly: a translation
     * of `ceramic` is another way of saying `ceramic`, not another way of saying
     * `mug`. Without provenance, boolean_term_groups() had nowhere to put an
     * expansion but the first group, so a two-word cross-language query compiled
     * to `+(mug* کوب* سیرامیک*) +ceramic*` -- the Arabic alternate widened the
     * noun it did not come from, while the qualifier stayed English-only and
     * excluded every Arabic product.
     *
     * Alternates are tokenised twice on purpose. query_terms() applies the
     * per-language token rules, which is what the analysis pipeline will do to
     * the same words, but it resolves one language for the whole string -- so a
     * mixed list like `حذاء, أحذية, shoe` does not singularise its English half.
     * Adding the plainly normalised tokens covers that, and a token that ends up
     * in neither list simply falls back to the old grouping.
     *
     * @param string $query Shopper query.
     * @return array{text: string, sources: array<string, array<int, string>>, alternates: array<string, array<int, string>>}
     *               `sources` maps alternate token => shopper tokens that produced it.
     *               `alternates` is its inverse: shopper token => alternate tokens.
     */
    public function expand_synonyms_map($query) {
        $query = $this->normalize_text($query);
        $index = $this->synonym_index();

        $haystack = ' ' . $query . ' ';
        $tokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = is_array($tokens) ? array_fill_keys($tokens, true) : array();

        // The Arabic definite article is written joined to its noun, so الحذاء
        // is not the token حذاء. contains_phrase() has always retried against a
        // clitic-stripped copy; the compiled path keeps that by indexing both.
        $clitic_tokens = array();
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $query)) {
            $stripped = $this->strip_arabic_clitic_text($query);
            if ($stripped !== $query) {
                $extra = preg_split('/\s+/u', $stripped, -1, PREG_SPLIT_NO_EMPTY);
                $clitic_tokens = is_array($extra) ? array_fill_keys($extra, true) : array();
            }
        }

        $matched = array();
        foreach ($index['tokens'] as $word => $alt_text) {
            if (isset($tokens[$word]) || isset($clitic_tokens[$word])) {
                $matched[$word] = $alt_text;
            }
        }
        // The genitive plural -- the form Russian uses after a quantity or a
        // negation, and so the form in "нет рюкзаков" and "пара кроссовок" --
        // reached no catalog word at all, because only the nominative is spelled
        // out in the map. Provenance stays on the word the shopper typed.
        if (!empty($index['ru_stems']) && preg_match('/[\x{0400}-\x{04FF}]/u', $query)) {
            foreach (array_keys($tokens) as $token) {
                if (isset($matched[$token]) || !preg_match('/[\x{0400}-\x{04FF}]/u', $token)) {
                    continue;
                }
                $stem = $this->stemmer()->stem($token);
                if ($stem !== '' && isset($index['ru_stems'][$stem])) {
                    $matched[$token] = $index['ru_stems'][$stem];
                }
            }
        }
        foreach ($index['phrases'] as $entry) {
            if (strpos($haystack, ' ' . $entry[0] . ' ') !== false) {
                $matched[$entry[0]] = $entry[1];
            }
        }
        // CJK writes a sentence without spaces, so these match by substring --
        // and only ever when the KEY itself is CJK, so no Latin or Arabic rule
        // is loosened by it.
        foreach ($index['cjk'] as $entry) {
            if (strpos($query, $entry[0]) !== false) {
                $matched[$entry[0]] = $entry[1];
            }
        }

        if (isset($matched['expensive']) && $this->contains_any_phrase($query, array('not expensive', 'not too expensive'))) {
            unset($matched['expensive']);
        }

        $expanded = $query;
        $sources = array();
        foreach ($matched as $word => $alt_text) {
            $expanded .= ' ' . $alt_text;

            $word_tokens = $this->expansion_tokens($word);
            if (empty($word_tokens)) {
                continue;
            }

            foreach ($this->expansion_tokens($alt_text) as $alt_token) {
                if (in_array($alt_token, $word_tokens, true)) {
                    // A synonym that restates one of its own words carries no
                    // provenance worth recording.
                    continue;
                }
                if (!isset($sources[$alt_token])) {
                    $sources[$alt_token] = array();
                }
                foreach ($word_tokens as $word_token) {
                    if (!in_array($word_token, $sources[$alt_token], true)) {
                        $sources[$alt_token][] = $word_token;
                    }
                }
            }
        }

        $alternates = array();
        foreach ($sources as $alt_token => $word_tokens) {
            foreach ($word_tokens as $word_token) {
                if (!isset($alternates[$word_token])) {
                    $alternates[$word_token] = array();
                }
                if (!in_array($alt_token, $alternates[$word_token], true)) {
                    $alternates[$word_token][] = $alt_token;
                }
            }
        }

        return array('text' => $expanded, 'sources' => $sources, 'alternates' => $alternates);
    }

    /**
     * The synonym map, compiled once into the three shapes a query needs.
     *
     * The map used to be walked entry by entry for every search, and each entry
     * called contains_phrase(), which re-normalised the WHOLE query text before
     * comparing. At sixty built-in rules that was merely wasteful. The default
     * pack now ships several hundred rules across nine languages, and a merchant
     * may add hundreds more, so the same loop would run normalize_text() -- eight
     * regular expressions over the full query -- roughly a thousand times per
     * search. Compiling lifts that to one normalisation per search plus a hash
     * lookup per query token.
     *
     * Three buckets, because the three matching rules are genuinely different:
     * a single-word key is a token test, a multi-word key is a phrase test, and
     * a CJK key is a substring test because CJK has no word boundaries.
     *
     * Cached per instance rather than statically: the map depends on a setting
     * and a filter, and a static cache would outlive a test or a request that
     * changes either.
     *
     * @return array{tokens: array<string, string>, phrases: array<int, array{0: string, 1: string}>, cjk: array<int, array{0: string, 1: string}>}
     */
    private function synonym_index() {
        if ($this->synonym_index !== null) {
            return $this->synonym_index;
        }

        $index = array('tokens' => array(), 'phrases' => array(), 'cjk' => array(), 'ru_stems' => array());
        foreach ($this->synonym_map() as $word => $alts) {
            // normalize_text() runs eight regular expressions, and compiling
            // calls it once per rule -- at this many rules that is most of the
            // compile. A key that is already lowercase ASCII letters, digits and
            // single spaces is its own normal form, so the pass is skipped for
            // the large majority and kept for the ones that need it: accents
            // (qualité), sharp s (größe), hyphens (off-white), Arabic
            // letterforms and CJK.
            if (!preg_match('/^[a-z0-9]+(?: [a-z0-9]+)*$/', $word)) {
                $word = $this->normalize_text($word);
            }
            if ($word === '') {
                continue;
            }

            $alt_text = trim(implode(' ', (array) $alts));
            if ($alt_text === '') {
                continue;
            }

            if (preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $word)) {
                $index['cjk'][] = array($word, $alt_text);
            } elseif (strpos($word, ' ') !== false) {
                $index['phrases'][] = array($word, $alt_text);
            } else {
                $index['tokens'][$word] = $alt_text;
                // Russian inflects the END of the noun, so the word the shopper
                // types is usually not the key: рюкзаков, рюкзаке and рюкзаки
                // are all the рюкзак this map holds. Filing the stem alongside
                // the word is the Cyrillic counterpart of the Arabic clitic
                // retry -- the same problem at the other end of the word.
                if (preg_match('/[\x{0400}-\x{04FF}]/u', $word)) {
                    $stem = $this->stemmer()->stem($word);
                    if ($stem !== '' && !isset($index['ru_stems'][$stem])) {
                        $index['ru_stems'][$stem] = $alt_text;
                    }
                }
            }
        }

        $this->synonym_index = $index;

        return $index;
    }

    /**
     * Shopper word => catalog words, for every language the plugin ships.
     *
     * The left side is what a shopper types, the right side is what the catalog
     * is likely to say. Most stores run an English catalog, so the non-English
     * entries translate INTO English; the reverse direction is a merchant's job
     * through the Custom synonyms setting, because only they know which of their
     * product nouns matter.
     *
     * Only nouns and hard attributes belong here. Soft shopper language -- comfy,
     * cheap, as a gift -- is handled per language by Search/Data/{lang}/commerce.php
     * as a RANKING signal, and duplicating it here would turn a preference into a
     * required catalog word.
     *
     * @return array<string, array<int, string>>
     */
    private function synonym_map() {
        $synonyms = array(
            'cheap' => array('budget', 'affordable', 'low price'),
            'cheaper' => array('budget', 'affordable', 'low price'),
            'not expensive' => array('budget affordable low price'),
            'not too expensive' => array('budget affordable low price'),
            'reasonable price' => array('affordable budget value'),
            'affordable' => array('budget', 'cheap'),
            'expensive' => array('premium', 'luxury'),
            'popular' => array('best selling', 'top rated'),
            'hoodies' => array('hoodie'),
            'hoody' => array('hoodie'),
            'hoddie' => array('hoodie'),
            'hoddies' => array('hoodie hoodies'),
            'hoodys' => array('hoodie hoodies'),
            'recomend' => array('recommend'),
            'reccomend' => array('recommend'),
            'shrit' => array('shirt'),
            'shrits' => array('shirt shirts'),
            'shoos' => array('shoe shoes'),
            'footwear' => array('shoe shoes sneaker trainer'),
            'jogger' => array('shoe sneaker trainer running'),
            'joggers' => array('shoe sneaker trainer running'),
            'new' => array('latest', 'newest'),
            'colour' => array('color'),
            'grey' => array('gray'),
            'gray' => array('grey'),
            'navy' => array('blue dark blue'),
            'cream' => array('beige off white'),
            'offwhite' => array('off white cream'),
            'off-white' => array('off white cream'),
            // Size acronyms are handled by product_facets_from_query().
            // Do not expand xl/xs into ordinary words here, otherwise "hoodie xl"
            // can leave "extra" behind as a fake product term.
            'trainer' => array('sneaker', 'shoe'),
            'trainers' => array('sneaker', 'shoe'),
            'sneaker' => array('trainer', 'shoe'),
            'sneakers' => array('trainer', 'shoe'),
            'tshirt' => array('t shirt', 'tee'),
            'tee' => array('t shirt', 'tshirt'),
            'belts' => array('belt'),
            'shoes' => array('shoe footwear'),
            'shirts' => array('shirt'),
            'mens' => array('men male'),
            'men' => array('mens male'),
            'womens' => array('women ladies female'),
            'ladies' => array('women female'),
            'kids' => array('kid children child'),
            'caps' => array('cap'),
            'hats' => array('hat cap'),

            // Homographs are deliberately absent from the lists below. A
            // shopper word only earns an entry when it cannot be mistaken for
            // an ordinary English word or product: 'rock' (skirt), 'handy'
            // (phone), 'collar' (necklace), 'gonna' (skirt), 'robe' (dress),
            // 'bone' (cap) and 'notebook' (laptop) are all correct in their own
            // language and all wrong against an English catalog, so each is left
            // to the merchant's Custom synonyms where the catalog is known.
            // ---------------------------------------------------------------
            // Urdu. Kept from the original pack.
            // ---------------------------------------------------------------
            'سستا' => array('cheap affordable budget'),
            'سستی' => array('cheap affordable budget'),
            'مہنگا' => array('premium luxury'),
            'رنگ' => array('color colour'),
            'قیمت' => array('price cost'),
            'جوتے' => array('shoe shoes footwear'),
            'قمیض' => array('shirt'),
            'بیگ' => array('bag'),
            'گھڑی' => array('watch'),
            'تحفہ' => array('gift present'),

            // ---------------------------------------------------------------
            // Arabic. Written in the folded forms normalize_text() produces --
            // أ إ آ fold to ا, ي ى ئ to ی, ك to ک, ة to ه -- with the natural
            // spelling alongside, because the pack has to read correctly to
            // whoever maintains it and the duplicates collapse at compile time.
            // Broken plurals are listed in full: no stemmer derives أحذية from
            // حذاء, so a shopper's plural reaches nothing without its own entry.
            // ---------------------------------------------------------------
            'حذاء' => array('shoe shoes footwear'),
            'أحذية' => array('shoe shoes footwear'),
            'احذية' => array('shoe shoes footwear'),
            'قميص' => array('shirt'),
            'قمصان' => array('shirt shirts'),
            'تيشيرت' => array('tshirt t shirt tee'),
            'بنطلون' => array('trousers pants'),
            'جاكيت' => array('jacket'),
            'سترة' => array('jacket coat'),
            'معطف' => array('coat'),
            'حقيبة' => array('bag'),
            'حقائب' => array('bag bags'),
            'محفظة' => array('wallet purse'),
            'حزام' => array('belt'),
            'قبعة' => array('hat cap'),
            'وشاح' => array('scarf'),
            'قفازات' => array('gloves'),
            'ساعة' => array('watch'),
            'نظارة' => array('glasses sunglasses'),
            'خاتم' => array('ring'),
            'سوار' => array('bracelet'),
            'عقد' => array('necklace'),
            'هاتف' => array('phone mobile'),
            'جوال' => array('phone mobile'),
            'شاحن' => array('charger'),
            'سماعات' => array('headphones earbuds'),
            'حاسوب' => array('laptop computer'),
            'لابتوب' => array('laptop'),
            'كاميرا' => array('camera'),
            'كوب' => array('cup mug'),
            'أكواب' => array('cup cups mug mugs'),
            'اكواب' => array('cup cups mug mugs'),
            'زجاجة' => array('bottle'),
            'منشفة' => array('towel'),
            'وسادة' => array('pillow cushion'),
            'بطانية' => array('blanket'),
            'شمعة' => array('candle'),
            'مصباح' => array('lamp light'),
            'كرسي' => array('chair'),
            'طاولة' => array('table desk'),
            'لعبة' => array('toy'),
            'دفتر' => array('notebook'),
            'قلم' => array('pen'),
            'عطر' => array('perfume fragrance'),
            'هدية' => array('gift present'),
            'هدايا' => array('gift gifts present'),
            'رخيص' => array('cheap affordable budget'),
            'لون' => array('color colour'),
            'جلد' => array('leather'),
            'قطن' => array('cotton'),
            'صوف' => array('wool'),
            'حرير' => array('silk'),
            'مقاوم للماء' => array('waterproof'),
            'لاسلكي' => array('wireless'),

            // ---------------------------------------------------------------
            // Spanish.
            // ---------------------------------------------------------------
            'zapatos' => array('shoe shoes footwear'),
            'zapatillas' => array('sneakers trainers shoe'),
            'botas' => array('boots'),
            'camisa' => array('shirt'),
            'camiseta' => array('tshirt t shirt tee'),
            'sudadera' => array('hoodie sweatshirt'),
            'chaqueta' => array('jacket'),
            'abrigo' => array('coat'),
            'pantalones' => array('trousers pants'),
            'vaqueros' => array('jeans'),
            'vestido' => array('dress'),
            'falda' => array('skirt'),
            'calcetines' => array('socks'),
            'bolso' => array('bag handbag'),
            'mochila' => array('backpack'),
            'cartera' => array('wallet'),
            'cinturon' => array('belt'),
            'gorra' => array('cap hat'),
            'sombrero' => array('hat'),
            'bufanda' => array('scarf'),
            'guantes' => array('gloves'),
            'reloj' => array('watch'),
            'gafas' => array('glasses sunglasses'),
            'anillo' => array('ring'),
            'pulsera' => array('bracelet'),
            'movil' => array('phone mobile'),
            'telefono' => array('phone mobile'),
            'cargador' => array('charger'),
            'auriculares' => array('headphones earbuds'),
            'altavoz' => array('speaker'),
            'portatil' => array('laptop'),
            'teclado' => array('keyboard'),
            'camara' => array('camera'),
            'taza' => array('cup mug'),
            'botella' => array('bottle'),
            'toalla' => array('towel'),
            'almohada' => array('pillow cushion'),
            'manta' => array('blanket'),
            'vela' => array('candle'),
            'lampara' => array('lamp light'),
            'silla' => array('chair'),
            'mesa' => array('table desk'),
            'juguete' => array('toy'),
            'cuaderno' => array('notebook'),
            'boligrafo' => array('pen'),
            'perfume' => array('perfume fragrance'),
            'regalo' => array('gift present'),
            'precio' => array('price cost'),
            'barato' => array('cheap affordable budget'),
            'cuero' => array('leather'),
            'algodon' => array('cotton'),
            'lana' => array('wool'),
            'seda' => array('silk'),
            'impermeable' => array('waterproof'),
            'inalambrico' => array('wireless'),

            // ---------------------------------------------------------------
            // French.
            // ---------------------------------------------------------------
            'chaussures' => array('shoe shoes footwear'),
            'baskets' => array('sneakers trainers shoe'),
            'bottes' => array('boots'),
            'chemise' => array('shirt'),
            'sweat' => array('hoodie sweatshirt'),
            'veste' => array('jacket'),
            'manteau' => array('coat'),
            'pantalon' => array('trousers pants'),
            'jupe' => array('skirt'),
            'chaussettes' => array('socks'),
            'sac' => array('bag'),
            'sac a dos' => array('backpack'),
            'portefeuille' => array('wallet'),
            'ceinture' => array('belt'),
            'casquette' => array('cap hat'),
            'chapeau' => array('hat'),
            'echarpe' => array('scarf'),
            'gants' => array('gloves'),
            'montre' => array('watch'),
            'lunettes' => array('glasses sunglasses'),
            'bague' => array('ring'),
            'collier' => array('necklace'),
            'telephone' => array('phone mobile'),
            'chargeur' => array('charger'),
            'ecouteurs' => array('headphones earbuds'),
            'enceinte' => array('speaker'),
            'ordinateur' => array('laptop computer'),
            'clavier' => array('keyboard'),
            'appareil photo' => array('camera'),
            'tasse' => array('cup mug'),
            'bouteille' => array('bottle'),
            'serviette' => array('towel'),
            'oreiller' => array('pillow cushion'),
            'couverture' => array('blanket'),
            'bougie' => array('candle'),
            'lampe' => array('lamp light'),
            'chaise' => array('chair'),
            'jouet' => array('toy'),
            'carnet' => array('notebook'),
            'stylo' => array('pen'),
            'parfum' => array('perfume fragrance'),
            'cadeau' => array('gift present'),
            'prix' => array('price cost'),
            'cuir' => array('leather'),
            'coton' => array('cotton'),
            'laine' => array('wool'),
            'soie' => array('silk'),
            'impermeable' => array('waterproof'),
            'sans fil' => array('wireless'),
            'taille' => array('size'),
            'couleur' => array('color colour'),

            // ---------------------------------------------------------------
            // German. Compounds are listed where the compound is the ordinary
            // way to ask -- a German shopper types Regenjacke, not "Jacke für
            // Regen", and the bare head noun would never reach that product.
            // ---------------------------------------------------------------
            'schuhe' => array('shoe shoes footwear'),
            'turnschuhe' => array('sneakers trainers shoe'),
            'stiefel' => array('boots'),
            'hemd' => array('shirt'),
            'kapuzenpullover' => array('hoodie'),
            'pullover' => array('sweater jumper'),
            'jacke' => array('jacket'),
            'regenjacke' => array('rain jacket waterproof'),
            'mantel' => array('coat'),
            'hose' => array('trousers pants'),
            'kleid' => array('dress'),
            'socken' => array('socks'),
            'tasche' => array('bag'),
            'rucksack' => array('backpack'),
            'geldborse' => array('wallet'),
            'gurtel' => array('belt'),
            'mutze' => array('hat cap beanie'),
            'schal' => array('scarf'),
            'handschuhe' => array('gloves'),
            'uhr' => array('watch'),
            'brille' => array('glasses sunglasses'),
            'sonnenbrille' => array('sunglasses'),
            'kette' => array('necklace chain'),
            'ladegerat' => array('charger'),
            'kopfhorer' => array('headphones earbuds'),
            'lautsprecher' => array('speaker'),
            'tastatur' => array('keyboard'),
            'kamera' => array('camera'),
            'tasse' => array('cup mug'),
            'flasche' => array('bottle'),
            'handtuch' => array('towel'),
            'kissen' => array('pillow cushion'),
            'decke' => array('blanket'),
            'kerze' => array('candle'),
            'lampe' => array('lamp light'),
            'stuhl' => array('chair'),
            'tisch' => array('table desk'),
            'spielzeug' => array('toy'),
            'notizbuch' => array('notebook'),
            'stift' => array('pen'),
            'parfum' => array('perfume fragrance'),
            'geschenk' => array('gift present'),
            'preis' => array('price cost'),
            'leder' => array('leather'),
            'baumwolle' => array('cotton'),
            'wolle' => array('wool'),
            'seide' => array('silk'),
            'wasserdicht' => array('waterproof'),
            'kabellos' => array('wireless'),
            'größe' => array('size'),
            'grosse' => array('size'),
            'farbe' => array('color colour'),

            // ---------------------------------------------------------------
            // Italian.
            // ---------------------------------------------------------------
            'scarpe' => array('shoe shoes footwear'),
            'stivali' => array('boots'),
            'camicia' => array('shirt'),
            'maglietta' => array('tshirt t shirt tee'),
            'felpa' => array('hoodie sweatshirt'),
            'giacca' => array('jacket'),
            'cappotto' => array('coat'),
            'pantaloni' => array('trousers pants'),
            'calzini' => array('socks'),
            'borsa' => array('bag handbag'),
            'zaino' => array('backpack'),
            'portafoglio' => array('wallet'),
            'cintura' => array('belt'),
            'cappello' => array('hat cap'),
            'sciarpa' => array('scarf'),
            'guanti' => array('gloves'),
            'orologio' => array('watch'),
            'occhiali' => array('glasses sunglasses'),
            'anello' => array('ring'),
            'collana' => array('necklace'),
            'telefono' => array('phone mobile'),
            'caricabatterie' => array('charger'),
            'cuffie' => array('headphones earbuds'),
            'altoparlante' => array('speaker'),
            'computer' => array('laptop computer'),
            'tastiera' => array('keyboard'),
            'fotocamera' => array('camera'),
            'tazza' => array('cup mug'),
            'bottiglia' => array('bottle'),
            'asciugamano' => array('towel'),
            'cuscino' => array('pillow cushion'),
            'coperta' => array('blanket'),
            'candela' => array('candle'),
            'lampada' => array('lamp light'),
            'sedia' => array('chair'),
            'tavolo' => array('table desk'),
            'giocattolo' => array('toy'),
            'quaderno' => array('notebook'),
            'penna' => array('pen'),
            'profumo' => array('perfume fragrance'),
            'regalo' => array('gift present'),
            'prezzo' => array('price cost'),
            'pelle' => array('leather'),
            'cotone' => array('cotton'),
            'lana' => array('wool'),
            'seta' => array('silk'),
            'impermeabile' => array('waterproof'),
            'senza fili' => array('wireless'),
            'taglia' => array('size'),
            'colore' => array('color colour'),

            // ---------------------------------------------------------------
            // Portuguese, Brazilian first. Where pt-BR and pt-PT differ in
            // ordinary shopping language both are listed: tenis/sapatilhas,
            // celular/telemovel, meias/peugas.
            // ---------------------------------------------------------------
            'sapatos' => array('shoe shoes footwear'),
            'tenis' => array('sneakers trainers shoe'),
            'sapatilhas' => array('sneakers trainers shoe'),
            'botas' => array('boots'),
            'camisa' => array('shirt'),
            'camiseta' => array('tshirt t shirt tee'),
            'moletom' => array('hoodie sweatshirt'),
            'casaco' => array('jacket coat'),
            'jaqueta' => array('jacket'),
            'calca' => array('trousers pants'),
            'saia' => array('skirt'),
            'meias' => array('socks'),
            'bolsa' => array('bag handbag'),
            'mochila' => array('backpack'),
            'carteira' => array('wallet'),
            'cinto' => array('belt'),
            'chapeu' => array('hat'),
            'cachecol' => array('scarf'),
            'luvas' => array('gloves'),
            'relogio' => array('watch'),
            'oculos' => array('glasses sunglasses'),
            'anel' => array('ring'),
            'colar' => array('necklace'),
            'celular' => array('phone mobile'),
            'telemovel' => array('phone mobile'),
            'carregador' => array('charger'),
            'fones' => array('headphones earbuds'),
            'caixa de som' => array('speaker'),
            'teclado' => array('keyboard'),
            'garrafa' => array('bottle'),
            'caneca' => array('cup mug'),
            'toalha' => array('towel'),
            'travesseiro' => array('pillow cushion'),
            'cobertor' => array('blanket'),
            'vela' => array('candle'),
            'luminaria' => array('lamp light'),
            'cadeira' => array('chair'),
            'brinquedo' => array('toy'),
            'caderno' => array('notebook'),
            'caneta' => array('pen'),
            'presente' => array('gift present'),
            'preco' => array('price cost'),
            'couro' => array('leather'),
            'algodao' => array('cotton'),
            'impermeavel' => array('waterproof'),
            'sem fio' => array('wireless'),
            'tamanho' => array('size'),
            'cor' => array('color colour'),

            // ---------------------------------------------------------------
            // Japanese. Matched by substring, so no particle handling is needed:
            // 「バッグが欲しい」 contains バッグ. Katakana loanwords and kanji are both
            // listed because a shopper uses whichever the store taught them.
            // ---------------------------------------------------------------
            '靴' => array('shoe shoes footwear'),
            'スニーカー' => array('sneakers trainers shoe'),
            'ブーツ' => array('boots'),
            'シャツ' => array('shirt'),
            'Tシャツ' => array('tshirt t shirt tee'),
            'パーカー' => array('hoodie'),
            'セーター' => array('sweater jumper'),
            'ジャケット' => array('jacket'),
            'コート' => array('coat'),
            'ズボン' => array('trousers pants'),
            'パンツ' => array('trousers pants'),
            'スカート' => array('skirt'),
            'ワンピース' => array('dress'),
            '靴下' => array('socks'),
            'バッグ' => array('bag'),
            'かばん' => array('bag'),
            '鞄' => array('bag'),
            'リュック' => array('backpack'),
            '財布' => array('wallet'),
            'ベルト' => array('belt'),
            '帽子' => array('hat cap'),
            'マフラー' => array('scarf'),
            '手袋' => array('gloves'),
            '時計' => array('watch'),
            '腕時計' => array('watch wristwatch'),
            'メガネ' => array('glasses'),
            'サングラス' => array('sunglasses'),
            '指輪' => array('ring'),
            'ネックレス' => array('necklace'),
            'スマホ' => array('phone mobile smartphone'),
            '充電器' => array('charger'),
            'イヤホン' => array('earbuds headphones'),
            'ヘッドホン' => array('headphones'),
            'スピーカー' => array('speaker'),
            'ノートパソコン' => array('laptop'),
            'キーボード' => array('keyboard'),
            'カメラ' => array('camera'),
            'マグカップ' => array('mug cup'),
            'コップ' => array('cup'),
            'ボトル' => array('bottle'),
            'タオル' => array('towel'),
            'まくら' => array('pillow'),
            '毛布' => array('blanket'),
            'キャンドル' => array('candle'),
            'ランプ' => array('lamp light'),
            '椅子' => array('chair'),
            'おもちゃ' => array('toy'),
            'ノート' => array('notebook'),
            'ペン' => array('pen'),
            '香水' => array('perfume fragrance'),
            'プレゼント' => array('gift present'),
            '革' => array('leather'),
            'レザー' => array('leather'),
            '綿' => array('cotton'),
            'コットン' => array('cotton'),
            'ウール' => array('wool'),
            '防水' => array('waterproof'),
            'ワイヤレス' => array('wireless'),

            // ---------------------------------------------------------------
            // Chinese. Matched by substring. Simplified and Traditional are
            // listed separately wherever they differ, because nothing folds the
            // two scripts together.
            // ---------------------------------------------------------------
            '鞋' => array('shoe shoes footwear'),
            '运动鞋' => array('sneakers trainers shoe'),
            '運動鞋' => array('sneakers trainers shoe'),
            '靴子' => array('boots'),
            '衬衫' => array('shirt'),
            '襯衫' => array('shirt'),
            'T恤' => array('tshirt t shirt tee'),
            '卫衣' => array('hoodie sweatshirt'),
            '衛衣' => array('hoodie sweatshirt'),
            '毛衣' => array('sweater jumper'),
            '夹克' => array('jacket'),
            '夾克' => array('jacket'),
            '外套' => array('jacket coat'),
            '大衣' => array('coat'),
            '裤子' => array('trousers pants'),
            '褲子' => array('trousers pants'),
            '裙子' => array('skirt dress'),
            '袜子' => array('socks'),
            '襪子' => array('socks'),
            '包' => array('bag'),
            '背包' => array('backpack'),
            '双肩包' => array('backpack'),
            '钱包' => array('wallet purse'),
            '錢包' => array('wallet purse'),
            '腰带' => array('belt'),
            '腰帶' => array('belt'),
            '帽子' => array('hat cap'),
            '围巾' => array('scarf'),
            '圍巾' => array('scarf'),
            '手套' => array('gloves'),
            '手表' => array('watch'),
            '手錶' => array('watch'),
            '眼镜' => array('glasses'),
            '墨镜' => array('sunglasses'),
            '太阳镜' => array('sunglasses'),
            '戒指' => array('ring'),
            '项链' => array('necklace'),
            '項鍊' => array('necklace'),
            '手机' => array('phone mobile'),
            '手機' => array('phone mobile'),
            '充电器' => array('charger'),
            '充電器' => array('charger'),
            '耳机' => array('headphones earbuds'),
            '耳機' => array('headphones earbuds'),
            '音箱' => array('speaker'),
            '笔记本电脑' => array('laptop'),
            '键盘' => array('keyboard'),
            '鍵盤' => array('keyboard'),
            '相机' => array('camera'),
            '相機' => array('camera'),
            '杯子' => array('cup mug'),
            '马克杯' => array('mug cup'),
            '水瓶' => array('bottle'),
            '毛巾' => array('towel'),
            '枕头' => array('pillow'),
            '枕頭' => array('pillow'),
            '毯子' => array('blanket'),
            '蜡烛' => array('candle'),
            '蠟燭' => array('candle'),
            '台灯' => array('lamp light'),
            '椅子' => array('chair'),
            '桌子' => array('table desk'),
            '玩具' => array('toy'),
            '笔记本' => array('notebook'),
            '香水' => array('perfume fragrance'),
            '礼物' => array('gift present'),
            '禮物' => array('gift present'),
            '皮革' => array('leather'),
            '真皮' => array('leather'),
            '棉' => array('cotton'),
            '羊毛' => array('wool'),
            '丝绸' => array('silk'),
            '防水' => array('waterproof'),
            '无线' => array('wireless'),
            '無線' => array('wireless'),
            '尺码' => array('size'),
            '颜色' => array('color colour'),
            '顏色' => array('color colour'),

            // ---------------------------------------------------------------
            // Second pass: the singular of every noun above, and the wider
            // catalog.
            //
            // The first pass listed whichever form came to mind, which in
            // practice meant the plural for clothing and the singular for
            // everything else -- so "zapatos" reached the catalog and "zapato"
            // reached nothing, and a shopper has no way to know which half of
            // the pair the plugin happens to know. Both forms are listed now.
            //
            // The categories below are what a general store actually carries
            // beyond apparel: consumer electronics, kitchen, home textiles,
            // beauty, baby, sport and stationery. Homographs are excluded on the
            // same rule as above -- a word earns an entry only when it cannot be
            // mistaken for an ordinary English word or for a different English
            // product.
            // ---------------------------------------------------------------

            // Spanish.
            'mochilas' => array('backpack'),
            'bolsos' => array('bag'),
            'balon' => array('ball'),
            'pelota' => array('ball'),
            'cinturones' => array('belt'),
            'bicicleta' => array('bicycle bike'),
            'edredon' => array('blanket'),
            'mantas' => array('blanket'),
            'libro' => array('book'),
            'libros' => array('book'),
            'bota' => array('boots'),
            'botellas' => array('bottle'),
            'termo' => array('bottle'),
            'bol' => array('bowl'),
            'cuenco' => array('bowl'),
            'tazon' => array('bowl'),
            'sosten' => array('bra'),
            'sujetador' => array('bra'),
            'brazalete' => array('bracelet'),
            'pulseras' => array('bracelet'),
            'camaras' => array('camera'),
            'vela aromatica' => array('candle'),
            'velas' => array('candle'),
            'chaqueta de punto' => array('cardigan'),
            'sillas' => array('chair'),
            'sillon' => array('chair'),
            'cargadores' => array('charger'),
            'reloj de pared' => array('clock'),
            'abrigos' => array('coat'),
            'cafetera' => array('coffee maker'),
            'algodon organico' => array('cotton'),
            'crema' => array('cream lotion'),
            'locion' => array('cream lotion'),
            'tazas' => array('cup mug'),
            'cortina' => array('curtain'),
            'cortinas' => array('curtain'),
            'panal' => array('diaper nappy'),
            'panales' => array('diaper nappy'),
            'vestidos' => array('dress'),
            'mancuernas' => array('dumbbell weights'),
            'pesas' => array('dumbbell weights'),
            'aretes' => array('earrings'),
            'aros' => array('earrings'),
            'pendientes' => array('earrings'),
            'obsequio' => array('gift present'),
            'regalos' => array('gift present'),
            'guante' => array('gloves'),
            'bolso de mano' => array('handbag'),
            'gorras' => array('hat cap'),
            'gorro' => array('hat cap'),
            'sombreros' => array('hat cap'),
            'audifonos' => array('headphones earbuds'),
            'auricular' => array('headphones earbuds'),
            'cascos' => array('headphones earbuds'),
            'casco' => array('helmet'),
            'buzo' => array('hoodie sweatshirt'),
            'sudaderas' => array('hoodie sweatshirt'),
            'campera' => array('jacket'),
            'chaquetas' => array('jacket'),
            'pantalon vaquero' => array('jeans'),
            'tejanos' => array('jeans'),
            'hervidor' => array('kettle'),
            'tetera' => array('kettle'),
            'teclados' => array('keyboard'),
            'cuchillo' => array('knife'),
            'cuchillos' => array('knife'),
            'bombilla' => array('lamp light'),
            'lamparas' => array('lamp light'),
            'computadora' => array('laptop computer'),
            'ordenador' => array('laptop computer'),
            'portatiles' => array('laptop computer'),
            'cuero genuino' => array('leather'),
            'piel' => array('leather'),
            'barra de labios' => array('lipstick'),
            'labial' => array('lipstick'),
            'pintalabios' => array('lipstick'),
            'maquillaje' => array('makeup'),
            'espejo' => array('mirror'),
            'pantalla' => array('monitor screen'),
            'raton' => array('mouse'),
            'cadena' => array('necklace'),
            'collares' => array('necklace'),
            'cuadernos' => array('notebook'),
            'libreta' => array('notebook'),
            'sarten' => array('pan'),
            'boligrafos' => array('pen'),
            'lapicero' => array('pen'),
            'pluma' => array('pen'),
            'lapices' => array('pencil'),
            'lapiz' => array('pencil'),
            'colonia' => array('perfume fragrance'),
            'perfumes' => array('perfume fragrance'),
            'carcasa' => array('phone case cover'),
            'funda' => array('phone case cover'),
            'funda de movil' => array('phone case cover'),
            'moviles' => array('phone mobile'),
            'telefonos' => array('phone mobile'),
            'almohadas' => array('pillow cushion'),
            'cojin' => array('pillow cushion'),
            'cojines' => array('pillow cushion'),
            'plato' => array('plate dish'),
            'platos' => array('plate dish'),
            'cazuela' => array('pot'),
            'olla' => array('pot'),
            'bateria externa' => array('power bank'),
            'impresora' => array('printer'),
            'pijama' => array('pyjamas pajamas'),
            'chubasquero' => array('rain jacket waterproof'),
            'afeitadora' => array('razor shaver'),
            'maquinilla' => array('razor shaver'),
            'rasuradora' => array('razor shaver'),
            'anillos' => array('ring'),
            'sortija' => array('ring'),
            'alfombra' => array('rug carpet'),
            'chanclas' => array('sandals'),
            'sandalia' => array('sandals'),
            'sandalias' => array('sandals'),
            'bufandas' => array('scarf'),
            'panuelo' => array('scarf'),
            'champu' => array('shampoo'),
            'camisas' => array('shirt'),
            'calzado' => array('shoe shoes footwear'),
            'zapato' => array('shoe shoes footwear'),
            'bermudas' => array('shorts'),
            'pantalon corto' => array('shorts'),
            'faldas' => array('skirt'),
            'pantuflas' => array('slippers'),
            'zapatillas de casa' => array('slippers'),
            'reloj inteligente' => array('smartwatch'),
            'zapatilla' => array('sneakers trainers shoe'),
            'jabon' => array('soap'),
            'calcetin' => array('socks'),
            'medias' => array('socks'),
            'altavoces' => array('speaker'),
            'bocina' => array('speaker'),
            'parlante' => array('speaker'),
            'carriola' => array('stroller pushchair'),
            'cochecito' => array('stroller pushchair'),
            'traje' => array('suit'),
            'equipaje' => array('suitcase luggage'),
            'maleta' => array('suitcase luggage'),
            'gafas de sol' => array('sunglasses'),
            'lentes de sol' => array('sunglasses'),
            'jersey' => array('sweater jumper'),
            'sudadera de punto' => array('sweater jumper'),
            'sueter' => array('sweater jumper'),
            'banador' => array('swimsuit swimwear'),
            'traje de bano' => array('swimsuit swimwear'),
            'escritorio' => array('table desk'),
            'mesas' => array('table desk'),
            'tableta' => array('tablet'),
            'carpa' => array('tent'),
            'tienda de campana' => array('tent'),
            'corbata' => array('tie'),
            'toallas' => array('towel'),
            'juguetes' => array('toy'),
            'peluche' => array('toy'),
            'camisetas' => array('tshirt t shirt tee'),
            'playera' => array('tshirt t shirt tee'),
            'remera' => array('tshirt t shirt tee'),
            'paraguas' => array('umbrella'),
            'sombrilla' => array('umbrella'),
            'bragas' => array('underwear'),
            'calzoncillos' => array('underwear'),
            'ropa interior' => array('underwear'),
            'billetera' => array('wallet purse'),
            'monedero' => array('wallet purse'),
            'relojes' => array('watch'),
            'a prueba de agua' => array('waterproof'),
            'resistente al agua' => array('waterproof'),
            'bluetooth' => array('wireless'),
            'sin cables' => array('wireless'),
            'colchoneta' => array('yoga mat'),
            'esterilla' => array('yoga mat'),
            'esterilla de yoga' => array('yoga mat'),

            // French.
            'sacs a dos' => array('backpack'),
            'sacs' => array('bag'),
            'balle' => array('ball'),
            'ballon' => array('ball'),
            'ceintures' => array('belt'),
            'bicyclette' => array('bicycle bike'),
            'velo' => array('bicycle bike'),
            'couvertures' => array('blanket'),
            'livre' => array('book'),
            'livres' => array('book'),
            'botte' => array('boots'),
            'bottine' => array('boots'),
            'bouteilles' => array('bottle'),
            'gourde' => array('bottle'),
            'saladier' => array('bowl'),
            'soutien gorge' => array('bra'),
            'bracelets' => array('bracelet'),
            'appareil' => array('camera'),
            'bougie parfumee' => array('candle'),
            'bougies' => array('candle'),
            'gilet' => array('cardigan'),
            'chaises' => array('chair'),
            'fauteuil' => array('chair'),
            'chargeurs' => array('charger'),
            'horloge' => array('clock'),
            'pendule' => array('clock'),
            'manteaux' => array('coat'),
            'cafetiere' => array('coffee maker'),
            'machine a cafe' => array('coffee maker'),
            'coton bio' => array('cotton'),
            'tasses' => array('cup mug'),
            'rideau' => array('curtain'),
            'rideaux' => array('curtain'),
            'couche' => array('diaper nappy'),
            'couches' => array('diaper nappy'),
            'robes' => array('dress'),
            'halteres' => array('dumbbell weights'),
            'boucles d oreilles' => array('earrings'),
            'boucles doreilles' => array('earrings'),
            'cadeaux' => array('gift present'),
            'gant' => array('gloves'),
            'sac a main' => array('handbag'),
            'bonnet' => array('hat cap'),
            'casquettes' => array('hat cap'),
            'chapeaux' => array('hat cap'),
            'casque' => array('headphones earbuds'),
            'casque audio' => array('headphones earbuds'),
            'ecouteur' => array('headphones earbuds'),
            'casque velo' => array('helmet'),
            'sweat a capuche' => array('hoodie sweatshirt'),
            'sweats' => array('hoodie sweatshirt'),
            'blouson' => array('jacket'),
            'vestes' => array('jacket'),
            'bouilloire' => array('kettle'),
            'claviers' => array('keyboard'),
            'couteau' => array('knife'),
            'couteaux' => array('knife'),
            'ampoule' => array('lamp light'),
            'lampes' => array('lamp light'),
            'ordinateur portable' => array('laptop computer'),
            'pc portable' => array('laptop computer'),
            'cuir veritable' => array('leather'),
            'rouge a levres' => array('lipstick'),
            'maquillage' => array('makeup'),
            'miroir' => array('mirror'),
            'ecran' => array('monitor screen'),
            'moniteur' => array('monitor screen'),
            'souris' => array('mouse'),
            'chaine' => array('necklace'),
            'colliers' => array('necklace'),
            'cahier' => array('notebook'),
            'carnets' => array('notebook'),
            'poele' => array('pan'),
            'stylos' => array('pen'),
            'crayon' => array('pencil'),
            'crayons' => array('pencil'),
            'eau de toilette' => array('perfume fragrance'),
            'parfums' => array('perfume fragrance'),
            'coque' => array('phone case cover'),
            'etui telephone' => array('phone case cover'),
            'portable' => array('phone mobile'),
            'telephones' => array('phone mobile'),
            'coussin' => array('pillow cushion'),
            'coussins' => array('pillow cushion'),
            'oreillers' => array('pillow cushion'),
            'assiette' => array('plate dish'),
            'assiettes' => array('plate dish'),
            'casserole' => array('pot'),
            'marmite' => array('pot'),
            'batterie externe' => array('power bank'),
            'imprimante' => array('printer'),
            'pyjama' => array('pyjamas pajamas'),
            'coupe vent' => array('rain jacket waterproof'),
            'kway' => array('rain jacket waterproof'),
            'rasoir' => array('razor shaver'),
            'bagues' => array('ring'),
            'tapis' => array('rug carpet'),
            'sandale' => array('sandals'),
            'sandales' => array('sandals'),
            'echarpes' => array('scarf'),
            'foulard' => array('scarf'),
            'shampooing' => array('shampoo'),
            'chemises' => array('shirt'),
            'chaussure' => array('shoe shoes footwear'),
            'bermuda' => array('shorts'),
            'short' => array('shorts'),
            'jupes' => array('skirt'),
            'chaussons' => array('slippers'),
            'pantoufles' => array('slippers'),
            'montre connectee' => array('smartwatch'),
            'montre intelligente' => array('smartwatch'),
            'basket' => array('sneakers trainers shoe'),
            'savon' => array('soap'),
            'chaussette' => array('socks'),
            'enceintes' => array('speaker'),
            'haut parleur' => array('speaker'),
            'poussette' => array('stroller pushchair'),
            'costume' => array('suit'),
            'bagage' => array('suitcase luggage'),
            'valise' => array('suitcase luggage'),
            'lunettes de soleil' => array('sunglasses'),
            'chandail' => array('sweater jumper'),
            'pull' => array('sweater jumper'),
            'maillot de bain' => array('swimsuit swimwear'),
            'bureau' => array('table desk'),
            'tables' => array('table desk'),
            'tablette' => array('tablet'),
            'tente' => array('tent'),
            'cravate' => array('tie'),
            'serviettes' => array('towel'),
            'jouets' => array('toy'),
            'pantalons' => array('trousers pants'),
            'maillot' => array('tshirt t shirt tee'),
            'tee shirt' => array('tshirt t shirt tee'),
            'parapluie' => array('umbrella'),
            'calecon' => array('underwear'),
            'culotte' => array('underwear'),
            'sous vetement' => array('underwear'),
            'porte monnaie' => array('wallet purse'),
            'montres' => array('watch'),
            'etanche' => array('waterproof'),
            'resistant a l eau' => array('waterproof'),
            'tapis de yoga' => array('yoga mat'),

            // German.
            'rucksacke' => array('backpack'),
            'beutel' => array('bag'),
            'taschen' => array('bag'),
            'guertel' => array('belt'),
            'fahrrad' => array('bicycle bike'),
            'decken' => array('blanket'),
            'wolldecke' => array('blanket'),
            'buch' => array('book'),
            'bucher' => array('book'),
            'stiefeln' => array('boots'),
            'flaschen' => array('bottle'),
            'trinkflasche' => array('bottle'),
            'schale' => array('bowl'),
            'schussel' => array('bowl'),
            'armband' => array('bracelet'),
            'armbander' => array('bracelet'),
            'kabel' => array('cable'),
            'kameras' => array('camera'),
            'duftkerze' => array('candle'),
            'kerzen' => array('candle'),
            'strickjacke' => array('cardigan'),
            'sessel' => array('chair'),
            'stuhle' => array('chair'),
            'ladegerate' => array('charger'),
            'ladekabel' => array('charger'),
            'wanduhr' => array('clock'),
            'kaffeemaschine' => array('coffee maker'),
            'bio baumwolle' => array('cotton'),
            'becher' => array('cup mug'),
            'tassen' => array('cup mug'),
            'gardine' => array('curtain'),
            'vorhang' => array('curtain'),
            'windel' => array('diaper nappy'),
            'windeln' => array('diaper nappy'),
            'kleider' => array('dress'),
            'gewichte' => array('dumbbell weights'),
            'hanteln' => array('dumbbell weights'),
            'ohrringe' => array('earrings'),
            'geschenke' => array('gift present'),
            'handschuh' => array('gloves'),
            'handtasche' => array('handbag'),
            'hut' => array('hat cap'),
            'kappe' => array('hat cap'),
            'mutzen' => array('hat cap'),
            'ohrhorer' => array('headphones earbuds'),
            'helm' => array('helmet'),
            'kapuzenpulli' => array('hoodie sweatshirt'),
            'jacken' => array('jacket'),
            'jeanshose' => array('jeans'),
            'wasserkocher' => array('kettle'),
            'tastaturen' => array('keyboard'),
            'messer' => array('knife'),
            'lampen' => array('lamp light'),
            'leuchte' => array('lamp light'),
            'echtleder' => array('leather'),
            'lippenstift' => array('lipstick'),
            'schminke' => array('makeup'),
            'spiegel' => array('mirror'),
            'bildschirm' => array('monitor screen'),
            'maus' => array('mouse'),
            'halskette' => array('necklace'),
            'ketten' => array('necklace'),
            'notizbucher' => array('notebook'),
            'pfanne' => array('pan'),
            'kugelschreiber' => array('pen'),
            'stifte' => array('pen'),
            'bleistift' => array('pencil'),
            'handyhulle' => array('phone case cover'),
            'schutzhulle' => array('phone case cover'),
            'handys' => array('phone mobile'),
            'mobiltelefon' => array('phone mobile'),
            'smartphone' => array('phone mobile'),
            'kissenbezug' => array('pillow cushion'),
            'kopfkissen' => array('pillow cushion'),
            'teller' => array('plate dish'),
            'kochtopf' => array('pot'),
            'topf' => array('pot'),
            'zusatzakku' => array('power bank'),
            'drucker' => array('printer'),
            'schlafanzug' => array('pyjamas pajamas'),
            'regenmantel' => array('rain jacket waterproof'),
            'rasierer' => array('razor shaver'),
            'ringe' => array('ring'),
            'teppich' => array('rug carpet'),
            'sandalen' => array('sandals'),
            'schals' => array('scarf'),
            'tuch' => array('scarf'),
            'hemden' => array('shirt'),
            'schuh' => array('shoe shoes footwear'),
            'kurze hose' => array('shorts'),
            'rocke' => array('skirt'),
            'hausschuhe' => array('slippers'),
            'turnschuh' => array('sneakers trainers shoe'),
            'seife' => array('soap'),
            'socke' => array('socks'),
            'strumpfe' => array('socks'),
            'bluetooth lautsprecher' => array('speaker'),
            'boxen' => array('speaker'),
            'kinderwagen' => array('stroller pushchair'),
            'anzug' => array('suit'),
            'gepack' => array('suitcase luggage'),
            'koffer' => array('suitcase luggage'),
            'sonnenbrillen' => array('sunglasses'),
            'strickpullover' => array('sweater jumper'),
            'badeanzug' => array('swimsuit swimwear'),
            'badehose' => array('swimsuit swimwear'),
            'schreibtisch' => array('table desk'),
            'tische' => array('table desk'),
            'zelt' => array('tent'),
            'krawatte' => array('tie'),
            'badetuch' => array('towel'),
            'handtucher' => array('towel'),
            'kuscheltier' => array('toy'),
            'spielzeuge' => array('toy'),
            'hosen' => array('trousers pants'),
            't shirt' => array('tshirt t shirt tee'),
            'regenschirm' => array('umbrella'),
            'unterhose' => array('underwear'),
            'unterwasche' => array('underwear'),
            'brieftasche' => array('wallet purse'),
            'portemonnaie' => array('wallet purse'),
            'armbanduhr' => array('watch'),
            'uhren' => array('watch'),
            'wasserabweisend' => array('waterproof'),
            'drahtlos' => array('wireless'),
            'yogamatte' => array('yoga mat'),

            // Italian.
            'zaini' => array('backpack'),
            'borse' => array('bag'),
            'pallone' => array('ball'),
            'cinture' => array('belt'),
            'bicicletta' => array('bicycle bike'),
            'coperte' => array('blanket'),
            'libri' => array('book'),
            'stivale' => array('boots'),
            'borraccia' => array('bottle'),
            'bottiglie' => array('bottle'),
            'ciotola' => array('bowl'),
            'scodella' => array('bowl'),
            'reggiseno' => array('bra'),
            'bracciale' => array('bracelet'),
            'braccialetto' => array('bracelet'),
            'cavo' => array('cable'),
            'macchina fotografica' => array('camera'),
            'candela profumata' => array('candle'),
            'candele' => array('candle'),
            'poltrona' => array('chair'),
            'sedie' => array('chair'),
            'caricabatteria' => array('charger'),
            'caricatore' => array('charger'),
            'orologio da parete' => array('clock'),
            'cappotti' => array('coat'),
            'caffettiera' => array('coffee maker'),
            'macchina da caffe' => array('coffee maker'),
            'cotone organico' => array('cotton'),
            'lozione' => array('cream lotion'),
            'tazze' => array('cup mug'),
            'tenda' => array('curtain'),
            'tende' => array('curtain'),
            'pannolini' => array('diaper nappy'),
            'pannolino' => array('diaper nappy'),
            'abito' => array('dress'),
            'vestito' => array('dress'),
            'manubri' => array('dumbbell weights'),
            'pesi' => array('dumbbell weights'),
            'orecchini' => array('earrings'),
            'regali' => array('gift present'),
            'guanto' => array('gloves'),
            'borsetta' => array('handbag'),
            'berretto' => array('hat cap'),
            'cappelli' => array('hat cap'),
            'auricolari' => array('headphones earbuds'),
            'cuffia' => array('headphones earbuds'),
            'felpe' => array('hoodie sweatshirt'),
            'giacche' => array('jacket'),
            'giubbotto' => array('jacket'),
            'bollitore' => array('kettle'),
            'tastiere' => array('keyboard'),
            'coltelli' => array('knife'),
            'coltello' => array('knife'),
            'lampade' => array('lamp light'),
            'lampadina' => array('lamp light'),
            'portatile' => array('laptop computer'),
            'cuoio' => array('leather'),
            'vera pelle' => array('leather'),
            'rossetto' => array('lipstick'),
            'trucco' => array('makeup'),
            'specchio' => array('mirror'),
            'schermo' => array('monitor screen'),
            'catenina' => array('necklace'),
            'collane' => array('necklace'),
            'quaderni' => array('notebook'),
            'taccuino' => array('notebook'),
            'padella' => array('pan'),
            'penne' => array('pen'),
            'matita' => array('pencil'),
            'matite' => array('pencil'),
            'profumi' => array('perfume fragrance'),
            'cover telefono' => array('phone case cover'),
            'custodia' => array('phone case cover'),
            'cellulare' => array('phone mobile'),
            'telefoni' => array('phone mobile'),
            'cuscini' => array('pillow cushion'),
            'federa' => array('pillow cushion'),
            'piatti' => array('plate dish'),
            'piatto' => array('plate dish'),
            'pentola' => array('pot'),
            'batteria esterna' => array('power bank'),
            'stampante' => array('printer'),
            'pigiama' => array('pyjamas pajamas'),
            'giacca antipioggia' => array('rain jacket waterproof'),
            'rasoio' => array('razor shaver'),
            'anelli' => array('ring'),
            'tappeto' => array('rug carpet'),
            'sandali' => array('sandals'),
            'sandalo' => array('sandals'),
            'sciarpe' => array('scarf'),
            'camicie' => array('shirt'),
            'calzature' => array('shoe shoes footwear'),
            'scarpa' => array('shoe shoes footwear'),
            'pantaloncini' => array('shorts'),
            'gonne' => array('skirt'),
            'ciabatte' => array('slippers'),
            'pantofole' => array('slippers'),
            'orologio intelligente' => array('smartwatch'),
            'scarpa da ginnastica' => array('sneakers trainers shoe'),
            'sapone' => array('soap'),
            'calze' => array('socks'),
            'calzino' => array('socks'),
            'altoparlanti' => array('speaker'),
            'cassa' => array('speaker'),
            'passeggino' => array('stroller pushchair'),
            'abito da uomo' => array('suit'),
            'completo' => array('suit'),
            'bagaglio' => array('suitcase luggage'),
            'valigia' => array('suitcase luggage'),
            'occhiali da sole' => array('sunglasses'),
            'maglione' => array('sweater jumper'),
            'costume da bagno' => array('swimsuit swimwear'),
            'scrivania' => array('table desk'),
            'tavoli' => array('table desk'),
            'tenda da campeggio' => array('tent'),
            'cravatta' => array('tie'),
            'asciugamani' => array('towel'),
            'giocattoli' => array('toy'),
            'pantalone' => array('trousers pants'),
            'magliette' => array('tshirt t shirt tee'),
            'ombrello' => array('umbrella'),
            'biancheria intima' => array('underwear'),
            'mutande' => array('underwear'),
            'portamonete' => array('wallet purse'),
            'orologi' => array('watch'),
            'resistente all acqua' => array('waterproof'),
            'tappetino yoga' => array('yoga mat'),

            // Portuguese.
            'bolsas' => array('bag'),
            'sacola' => array('bag'),
            'bola' => array('ball'),
            'cintos' => array('belt'),
            'cobertores' => array('blanket'),
            'livro' => array('book'),
            'livros' => array('book'),
            'garrafa termica' => array('bottle'),
            'garrafas' => array('bottle'),
            'taca' => array('bowl'),
            'tigela' => array('bowl'),
            'soutien' => array('bra'),
            'sutia' => array('bra'),
            'pulseira' => array('bracelet'),
            'pulseiras' => array('bracelet'),
            'cabo' => array('cable'),
            'maquina fotografica' => array('camera'),
            'casaco de malha' => array('cardigan'),
            'cadeiras' => array('chair'),
            'carregadores' => array('charger'),
            'relogio de parede' => array('clock'),
            'casacos' => array('coat'),
            'sobretudo' => array('coat'),
            'cafeteira' => array('coffee maker'),
            'maquina de cafe' => array('coffee maker'),
            'algodao organico' => array('cotton'),
            'locao' => array('cream lotion'),
            'canecas' => array('cup mug'),
            'chavena' => array('cup mug'),
            'fralda' => array('diaper nappy'),
            'fraldas' => array('diaper nappy'),
            'pesos' => array('dumbbell weights'),
            'brincos' => array('earrings'),
            'lembranca' => array('gift present'),
            'presentes' => array('gift present'),
            'luva' => array('gloves'),
            'bolsa de mao' => array('handbag'),
            'bones' => array('hat cap'),
            'chapeus' => array('hat cap'),
            'auscultadores' => array('headphones earbuds'),
            'fone' => array('headphones earbuds'),
            'fone de ouvido' => array('headphones earbuds'),
            'capacete' => array('helmet'),
            'casaco com capuz' => array('hoodie sweatshirt'),
            'moletons' => array('hoodie sweatshirt'),
            'blusao' => array('jacket'),
            'jaquetas' => array('jacket'),
            'calca jeans' => array('jeans'),
            'chaleira' => array('kettle'),
            'faca' => array('knife'),
            'facas' => array('knife'),
            'abajur' => array('lamp light'),
            'luminarias' => array('lamp light'),
            'computador' => array('laptop computer'),
            'couro legitimo' => array('leather'),
            'batom' => array('lipstick'),
            'maquiagem' => array('makeup'),
            'maquilhagem' => array('makeup'),
            'espelho' => array('mirror'),
            'ecra' => array('monitor screen'),
            'tela' => array('monitor screen'),
            'rato' => array('mouse'),
            'colares' => array('necklace'),
            'corrente' => array('necklace'),
            'cadernos' => array('notebook'),
            'frigideira' => array('pan'),
            'canetas' => array('pen'),
            'lapis' => array('pencil'),
            'capa de celular' => array('phone case cover'),
            'capinha' => array('phone case cover'),
            'celulares' => array('phone mobile'),
            'telemoveis' => array('phone mobile'),
            'almofada' => array('pillow cushion'),
            'travesseiros' => array('pillow cushion'),
            'prato' => array('plate dish'),
            'pratos' => array('plate dish'),
            'panela' => array('pot'),
            'carregador portatil' => array('power bank'),
            'impressora' => array('printer'),
            'capa de chuva' => array('rain jacket waterproof'),
            'barbeador' => array('razor shaver'),
            'maquina de barbear' => array('razor shaver'),
            'aneis' => array('ring'),
            'tapete' => array('rug carpet'),
            'chinelo' => array('sandals'),
            'cachecois' => array('scarf'),
            'lenco' => array('scarf'),
            'champo' => array('shampoo'),
            'xampu' => array('shampoo'),
            'calcado' => array('shoe shoes footwear'),
            'sapato' => array('shoe shoes footwear'),
            'saias' => array('skirt'),
            'chinelos' => array('slippers'),
            'pantufas' => array('slippers'),
            'relogio inteligente' => array('smartwatch'),
            'sabao' => array('soap'),
            'sabonete' => array('soap'),
            'meia' => array('socks'),
            'peugas' => array('socks'),
            'alto falante' => array('speaker'),
            'coluna de som' => array('speaker'),
            'carrinho de bebe' => array('stroller pushchair'),
            'terno' => array('suit'),
            'bagagem' => array('suitcase luggage'),
            'mala' => array('suitcase luggage'),
            'oculos de sol' => array('sunglasses'),
            'camisola de la' => array('sweater jumper'),
            'pulover' => array('sweater jumper'),
            'biquini' => array('swimsuit swimwear'),
            'sunga' => array('swimsuit swimwear'),
            'escrivaninha' => array('table desk'),
            'secretaria' => array('table desk'),
            'barraca' => array('tent'),
            'gravata' => array('tie'),
            'toalhas' => array('towel'),
            'brinquedos' => array('toy'),
            'pelucia' => array('toy'),
            'calcas' => array('trousers pants'),
            'camisola' => array('tshirt t shirt tee'),
            'guarda chuva' => array('umbrella'),
            'sombrinha' => array('umbrella'),
            'calcinha' => array('underwear'),
            'cueca' => array('underwear'),
            'roupa interior' => array('underwear'),
            'porta moedas' => array('wallet purse'),
            'relogios' => array('watch'),
            'a prova d agua' => array('waterproof'),
            'resistente a agua' => array('waterproof'),
            'colchonete' => array('yoga mat'),
            'tapete de yoga' => array('yoga mat'),

            // Japanese.
            'バックパック' => array('backpack'),
            'リュックサック' => array('backpack'),
            'トートバッグ' => array('bag'),
            'ボール' => array('ball'),
            '自転車' => array('bicycle bike'),
            'ブランケット' => array('blanket'),
            '書籍' => array('book'),
            '本' => array('book'),
            '水筒' => array('bottle'),
            'どんぶり' => array('bowl'),
            'ボウル' => array('bowl'),
            'ブラジャー' => array('bra'),
            'ブレスレット' => array('bracelet'),
            'ケーブル' => array('cable'),
            'ろうそく' => array('candle'),
            'アロマキャンドル' => array('candle'),
            'カーディガン' => array('cardigan'),
            'いす' => array('chair'),
            'チェア' => array('chair'),
            'チャージャー' => array('charger'),
            '充電' => array('charger'),
            '掛け時計' => array('clock'),
            'オーバーコート' => array('coat'),
            'コーヒーメーカー' => array('coffee maker'),
            '綿100' => array('cotton'),
            'クリーム' => array('cream lotion'),
            'ローション' => array('cream lotion'),
            'カップ' => array('cup mug'),
            'マグ' => array('cup mug'),
            'カーテン' => array('curtain'),
            'おむつ' => array('diaper nappy'),
            'ドレス' => array('dress'),
            'ダンベル' => array('dumbbell weights'),
            'イヤリング' => array('earrings'),
            'ピアス' => array('earrings'),
            'ギフト' => array('gift present'),
            '贈り物' => array('gift present'),
            'グローブ' => array('gloves'),
            'ハンドバッグ' => array('handbag'),
            'キャップ' => array('hat cap'),
            'ニット帽' => array('hat cap'),
            'イヤフォン' => array('headphones earbuds'),
            'ワイヤレスイヤホン' => array('headphones earbuds'),
            'ヘルメット' => array('helmet'),
            'スウェット' => array('hoodie sweatshirt'),
            'フーディ' => array('hoodie sweatshirt'),
            '上着' => array('jacket'),
            'ジーンズ' => array('jeans'),
            'デニム' => array('jeans'),
            'やかん' => array('kettle'),
            'ケトル' => array('kettle'),
            'ナイフ' => array('knife'),
            '包丁' => array('knife'),
            'ライト' => array('lamp light'),
            '照明' => array('lamp light'),
            'ノートPC' => array('laptop computer'),
            'パソコン' => array('laptop computer'),
            'ラップトップ' => array('laptop computer'),
            '本革' => array('leather'),
            'リップ' => array('lipstick'),
            '口紅' => array('lipstick'),
            'メイク' => array('makeup'),
            '化粧品' => array('makeup'),
            'ミラー' => array('mirror'),
            '鏡' => array('mirror'),
            'ディスプレイ' => array('monitor screen'),
            'モニター' => array('monitor screen'),
            'マウス' => array('mouse'),
            'チェーン' => array('necklace'),
            'ノートブック' => array('notebook'),
            'フライパン' => array('pan'),
            'ボールペン' => array('pen'),
            '鉛筆' => array('pencil'),
            'パフューム' => array('perfume fragrance'),
            'フレグランス' => array('perfume fragrance'),
            'ケース' => array('phone case cover'),
            'スマホケース' => array('phone case cover'),
            'スマートフォン' => array('phone mobile'),
            '携帯' => array('phone mobile'),
            '携帯電話' => array('phone mobile'),
            'クッション' => array('pillow cushion'),
            '枕' => array('pillow cushion'),
            'お皿' => array('plate dish'),
            'プレート' => array('plate dish'),
            '皿' => array('plate dish'),
            'なべ' => array('pot'),
            '鍋' => array('pot'),
            'モバイルバッテリー' => array('power bank'),
            'プリンター' => array('printer'),
            'パジャマ' => array('pyjamas pajamas'),
            'レインコート' => array('rain jacket waterproof'),
            'カミソリ' => array('razor shaver'),
            'シェーバー' => array('razor shaver'),
            'リング' => array('ring'),
            'カーペット' => array('rug carpet'),
            'ラグ' => array('rug carpet'),
            'サンダル' => array('sandals'),
            'スカーフ' => array('scarf'),
            'ストール' => array('scarf'),
            'シャンプー' => array('shampoo'),
            'ワイシャツ' => array('shirt'),
            'シューズ' => array('shoe shoes footwear'),
            'ショートパンツ' => array('shorts'),
            '半ズボン' => array('shorts'),
            'スリッパ' => array('slippers'),
            'スマートウォッチ' => array('smartwatch'),
            'スニーカ' => array('sneakers trainers shoe'),
            'ソープ' => array('soap'),
            '石鹸' => array('soap'),
            'ソックス' => array('socks'),
            'スピーカ' => array('speaker'),
            'ブルートゥーススピーカー' => array('speaker'),
            'ベビーカー' => array('stroller pushchair'),
            'スーツ' => array('suit'),
            'スーツケース' => array('suitcase luggage'),
            'ニット' => array('sweater jumper'),
            '水着' => array('swimsuit swimwear'),
            'テーブル' => array('table desk'),
            'デスク' => array('table desk'),
            '机' => array('table desk'),
            'タブレット' => array('tablet'),
            'テント' => array('tent'),
            'ネクタイ' => array('tie'),
            'バスタオル' => array('towel'),
            'ぬいぐるみ' => array('toy'),
            'ボトムス' => array('trousers pants'),
            'ティーシャツ' => array('tshirt t shirt tee'),
            'かさ' => array('umbrella'),
            '傘' => array('umbrella'),
            '下着' => array('underwear'),
            'さいふ' => array('wallet purse'),
            'ウォレット' => array('wallet purse'),
            'ウォッチ' => array('watch'),
            'ウォータープルーフ' => array('waterproof'),
            '撥水' => array('waterproof'),
            'ヨガマット' => array('yoga mat'),

            // Chinese.
            '书包' => array('backpack'),
            '書包' => array('backpack'),
            '手提包' => array('bag'),
            '袋子' => array('bag'),
            '球' => array('ball'),
            '皮带' => array('belt'),
            '皮帶' => array('belt'),
            '單車' => array('bicycle bike'),
            '腳踏車' => array('bicycle bike'),
            '自行车' => array('bicycle bike'),
            '毛毯' => array('blanket'),
            '被子' => array('blanket'),
            '书' => array('book'),
            '書' => array('book'),
            '短靴' => array('boots'),
            '长靴' => array('boots'),
            '保温杯' => array('bottle'),
            '水壶' => array('bottle'),
            '碗' => array('bowl'),
            '文胸' => array('bra'),
            '胸罩' => array('bra'),
            '手鏈' => array('bracelet'),
            '手链' => array('bracelet'),
            '手镯' => array('bracelet'),
            '数据线' => array('cable'),
            '數據線' => array('cable'),
            '攝影機' => array('camera'),
            '照相机' => array('camera'),
            '香薰蜡烛' => array('candle'),
            '开衫' => array('cardigan'),
            '開衫' => array('cardigan'),
            '凳子' => array('chair'),
            '座椅' => array('chair'),
            '充电线' => array('charger'),
            '快充' => array('charger'),
            '挂钟' => array('clock'),
            '時鐘' => array('clock'),
            '風衣' => array('coat'),
            '风衣' => array('coat'),
            '咖啡机' => array('coffee maker'),
            '咖啡機' => array('coffee maker'),
            '純棉' => array('cotton'),
            '纯棉' => array('cotton'),
            '乳液' => array('cream lotion'),
            '面霜' => array('cream lotion'),
            '咖啡杯' => array('cup mug'),
            '茶杯' => array('cup mug'),
            '窗帘' => array('curtain'),
            '窗簾' => array('curtain'),
            '尿布' => array('diaper nappy'),
            '紙尿褲' => array('diaper nappy'),
            '连衣裙' => array('dress'),
            '連衣裙' => array('dress'),
            '哑铃' => array('dumbbell weights'),
            '啞鈴' => array('dumbbell weights'),
            '耳环' => array('earrings'),
            '耳環' => array('earrings'),
            '耳钉' => array('earrings'),
            '礼品' => array('gift present'),
            '禮品' => array('gift present'),
            '棒球帽' => array('hat cap'),
            '鸭舌帽' => array('hat cap'),
            '耳塞' => array('headphones earbuds'),
            '蓝牙耳机' => array('headphones earbuds'),
            '藍牙耳機' => array('headphones earbuds'),
            '头盔' => array('helmet'),
            '安全帽' => array('helmet'),
            '頭盔' => array('helmet'),
            '帽衫' => array('hoodie sweatshirt'),
            '連帽衫' => array('hoodie sweatshirt'),
            '夹克衫' => array('jacket'),
            '牛仔裤' => array('jeans'),
            '牛仔褲' => array('jeans'),
            '電熱水壺' => array('kettle'),
            '刀具' => array('knife'),
            '菜刀' => array('knife'),
            '台燈' => array('lamp light'),
            '灯' => array('lamp light'),
            '燈' => array('lamp light'),
            '电脑' => array('laptop computer'),
            '筆記型電腦' => array('laptop computer'),
            '牛皮' => array('leather'),
            '口红' => array('lipstick'),
            '化妆品' => array('makeup'),
            '彩妝' => array('makeup'),
            '鏡子' => array('mirror'),
            '镜子' => array('mirror'),
            '屏幕' => array('monitor screen'),
            '显示器' => array('monitor screen'),
            '顯示器' => array('monitor screen'),
            '滑鼠' => array('mouse'),
            '鼠标' => array('mouse'),
            '鏈子' => array('necklace'),
            '项圈' => array('necklace'),
            '本子' => array('notebook'),
            '記事本' => array('notebook'),
            '平底锅' => array('pan'),
            '煎锅' => array('pan'),
            '原子筆' => array('pen'),
            '钢笔' => array('pen'),
            '铅笔' => array('pencil'),
            '古龙水' => array('perfume fragrance'),
            '香氛' => array('perfume fragrance'),
            '手机壳' => array('phone case cover'),
            '手機殼' => array('phone case cover'),
            '智慧型手機' => array('phone mobile'),
            '智能手机' => array('phone mobile'),
            '抱枕' => array('pillow cushion'),
            '靠枕' => array('pillow cushion'),
            '盘子' => array('plate dish'),
            '盤子' => array('plate dish'),
            '碟子' => array('plate dish'),
            '鍋子' => array('pot'),
            '锅' => array('pot'),
            '充电宝' => array('power bank'),
            '行動電源' => array('power bank'),
            '印表機' => array('printer'),
            '打印机' => array('printer'),
            '睡衣' => array('pyjamas pajamas'),
            '雨衣' => array('rain jacket waterproof'),
            '刮鬍刀' => array('razor shaver'),
            '剃须刀' => array('razor shaver'),
            '戒子' => array('ring'),
            '指环' => array('ring'),
            '地毯' => array('rug carpet'),
            '凉鞋' => array('sandals'),
            '拖鞋' => array('sandals'),
            '涼鞋' => array('sandals'),
            '丝巾' => array('scarf'),
            '絲巾' => array('scarf'),
            '洗发水' => array('shampoo'),
            '洗髮精' => array('shampoo'),
            '上衣' => array('shirt'),
            '皮鞋' => array('shoe shoes footwear'),
            '鞋子' => array('shoe shoes footwear'),
            '短裤' => array('shorts'),
            '短褲' => array('shorts'),
            '半身裙' => array('skirt'),
            '智慧手錶' => array('smartwatch'),
            '智能手表' => array('smartwatch'),
            '波鞋' => array('sneakers trainers shoe'),
            '球鞋' => array('sneakers trainers shoe'),
            '肥皂' => array('soap'),
            '香皂' => array('soap'),
            '短袜' => array('socks'),
            '長襪' => array('socks'),
            '喇叭' => array('speaker'),
            '蓝牙音箱' => array('speaker'),
            '婴儿车' => array('stroller pushchair'),
            '嬰兒車' => array('stroller pushchair'),
            '西装' => array('suit'),
            '西裝' => array('suit'),
            '旅行箱' => array('suitcase luggage'),
            '行李箱' => array('suitcase luggage'),
            '太阳眼镜' => array('sunglasses'),
            '套头衫' => array('sweater jumper'),
            '針織衫' => array('sweater jumper'),
            '针织衫' => array('sweater jumper'),
            '泳衣' => array('swimsuit swimwear'),
            '泳裤' => array('swimsuit swimwear'),
            '书桌' => array('table desk'),
            '書桌' => array('table desk'),
            '餐桌' => array('table desk'),
            '平板' => array('tablet'),
            '平板电脑' => array('tablet'),
            '帐篷' => array('tent'),
            '帳篷' => array('tent'),
            '領帶' => array('tie'),
            '领带' => array('tie'),
            '浴巾' => array('towel'),
            '毛绒玩具' => array('toy'),
            '玩偶' => array('toy'),
            '長褲' => array('trousers pants'),
            '长裤' => array('trousers pants'),
            'T恤衫' => array('tshirt t shirt tee'),
            '短袖' => array('tshirt t shirt tee'),
            '雨伞' => array('umbrella'),
            '雨傘' => array('umbrella'),
            '內衣' => array('underwear'),
            '内衣' => array('underwear'),
            '内裤' => array('underwear'),
            '皮夹' => array('wallet purse'),
            '皮夾' => array('wallet purse'),
            '腕表' => array('watch'),
            '腕錶' => array('watch'),
            '防水的' => array('waterproof'),
            '防泼水' => array('waterproof'),
            '无线的' => array('wireless'),
            '藍牙' => array('wireless'),
            '瑜伽垫' => array('yoga mat'),
            '瑜伽墊' => array('yoga mat'),

            // ---------------------------------------------------------------
            // Dutch, Korean and Russian.
            //
            // Dutch inflects its adjectives (-e) and writes compounds as one
            // word; Korean joins its particles to the noun, so these are matched
            // by substring like the other CJK-range scripts; Russian changes a
            // noun's ending by case, so the common forms are listed rather than
            // the dictionary form alone.
            //
            // The same homograph rule applies as above. Dutch is the language it
            // bites hardest: 'pet' (cap), 'rok' (skirt), 'kom' (bowl), 'mes'
            // (knife), 'das' (tie), 'bal' (ball), 'zak' (bag) and 'tas' (bag)
            // are all ordinary English words or English products, and each is
            // left to the merchant's Custom synonyms.
            // ---------------------------------------------------------------

            // Dutch.
            'rugtas' => array('backpack'),
            'rugzak' => array('backpack'),
            'rugzakken' => array('backpack'),
            'riem' => array('belt'),
            'riemen' => array('belt'),
            'fiets' => array('bicycle bike'),
            'deken' => array('blanket'),
            'boek' => array('book'),
            'boeken' => array('book'),
            'laars' => array('boots'),
            'laarzen' => array('boots'),
            'bidon' => array('bottle'),
            'drinkfles' => array('bottle'),
            'fles' => array('bottle'),
            'schaal' => array('bowl'),
            'armbanden' => array('bracelet'),
            'fototoestel' => array('camera'),
            'geurkaars' => array('candle'),
            'kaars' => array('candle'),
            'kaarsen' => array('candle'),
            'stoel' => array('chair'),
            'stoelen' => array('chair'),
            'laadkabel' => array('charger'),
            'oplader' => array('charger'),
            'klok' => array('clock'),
            'wandklok' => array('clock'),
            'winterjas' => array('coat'),
            'koffiezetapparaat' => array('coffee maker'),
            'kleur' => array('color colour'),
            'katoen' => array('cotton'),
            'katoenen' => array('cotton'),
            'bodylotion' => array('cream lotion'),
            'creme' => array('cream lotion'),
            'mok' => array('cup mug'),
            'gordijn' => array('curtain'),
            'gordijnen' => array('curtain'),
            'luier' => array('diaper nappy'),
            'luiers' => array('diaper nappy'),
            'jurk' => array('dress'),
            'jurken' => array('dress'),
            'gewichten' => array('dumbbell weights'),
            'halters' => array('dumbbell weights'),
            'oorbel' => array('earrings'),
            'oorbellen' => array('earrings'),
            'cadeautje' => array('gift present'),
            'kado' => array('gift present'),
            'handschoen' => array('gloves'),
            'handschoenen' => array('gloves'),
            'handtas' => array('handbag'),
            'handtassen' => array('handbag'),
            'hoed' => array('hat cap'),
            'muts' => array('hat cap'),
            'koptelefoon' => array('headphones earbuds'),
            'oordopjes' => array('headphones earbuds'),
            'oortjes' => array('headphones earbuds'),
            'trui met capuchon' => array('hoodie sweatshirt'),
            'jack' => array('jacket'),
            'jas' => array('jacket'),
            'jasje' => array('jacket'),
            'spijkerbroek' => array('jeans'),
            'waterkoker' => array('kettle'),
            'toetsenbord' => array('keyboard'),
            'messen' => array('knife'),
            'verlichting' => array('lamp light'),
            'echt leer' => array('leather'),
            'leer' => array('leather'),
            'leren' => array('leather'),
            'beeldscherm' => array('monitor screen'),
            'scherm' => array('monitor screen'),
            'muis' => array('mouse'),
            'halsketting' => array('necklace'),
            'ketting' => array('necklace'),
            'kettingen' => array('necklace'),
            'notitieboek' => array('notebook'),
            'schrift' => array('notebook'),
            'koekenpan' => array('pan'),
            'balpen' => array('pen'),
            'pennen' => array('pen'),
            'potlood' => array('pencil'),
            'geur' => array('perfume fragrance'),
            'hoesje' => array('phone case cover'),
            'telefoonhoesje' => array('phone case cover'),
            'mobiel' => array('phone mobile'),
            'telefoon' => array('phone mobile'),
            'kussen' => array('pillow cushion'),
            'kussens' => array('pillow cushion'),
            'bord' => array('plate dish'),
            'borden' => array('plate dish'),
            'kookpan' => array('pot'),
            'prijs' => array('price cost'),
            'regenjas' => array('rain jacket waterproof'),
            'scheerapparaat' => array('razor shaver'),
            'scheermes' => array('razor shaver'),
            'ringen' => array('ring'),
            'tapijt' => array('rug carpet'),
            'vloerkleed' => array('rug carpet'),
            'sandaal' => array('sandals'),
            'slippers' => array('sandals'),
            'sjaal' => array('scarf'),
            'sjaals' => array('scarf'),
            'blouse' => array('shirt'),
            'overhemd' => array('shirt'),
            'overhemden' => array('shirt'),
            'schoen' => array('shoe shoes footwear'),
            'schoenen' => array('shoe shoes footwear'),
            'korte broek' => array('shorts'),
            'zijde' => array('silk'),
            'zijden' => array('silk'),
            'rokken' => array('skirt'),
            'slim horloge' => array('smartwatch'),
            'gympen' => array('sneakers trainers shoe'),
            'zeep' => array('soap'),
            'kousen' => array('socks'),
            'sok' => array('socks'),
            'sokken' => array('socks'),
            'boxje' => array('speaker'),
            'luidspreker' => array('speaker'),
            'kostuum' => array('suit'),
            'pak' => array('suit'),
            'zonnebril' => array('sunglasses'),
            'zonnebrillen' => array('sunglasses'),
            'trui' => array('sweater jumper'),
            'truien' => array('sweater jumper'),
            'badpak' => array('swimsuit swimwear'),
            'zwembroek' => array('swimsuit swimwear'),
            'tafel' => array('table desk'),
            'stropdas' => array('tie'),
            'handdoek' => array('towel'),
            'handdoeken' => array('towel'),
            'knuffel' => array('toy'),
            'speelgoed' => array('toy'),
            'broek' => array('trousers pants'),
            'broeken' => array('trousers pants'),
            'shirtje' => array('tshirt t shirt tee'),
            'paraplu' => array('umbrella'),
            'onderbroek' => array('underwear'),
            'ondergoed' => array('underwear'),
            'portemonnee' => array('wallet purse'),
            'horloges' => array('watch'),
            'waterafstotend' => array('waterproof'),
            'waterdicht' => array('waterproof'),
            'draadloos' => array('wireless'),
            'draadloze' => array('wireless'),
            'wol' => array('wool'),
            'wollen' => array('wool'),
            'yogamat' => array('yoga mat'),

            // Korean.
            '배낭' => array('backpack'),
            '백팩' => array('backpack'),
            '책가방' => array('backpack'),
            '가방' => array('bag'),
            '백' => array('bag'),
            '공' => array('ball'),
            '벨트' => array('belt'),
            '허리띠' => array('belt'),
            '자전거' => array('bicycle bike'),
            '담요' => array('blanket'),
            '이불' => array('blanket'),
            '도서' => array('book'),
            '책' => array('book'),
            '부츠' => array('boots'),
            '장화' => array('boots'),
            '물병' => array('bottle'),
            '보틀' => array('bottle'),
            '텀블러' => array('bottle'),
            '그릇' => array('bowl'),
            '볼' => array('bowl'),
            '팔찌' => array('bracelet'),
            '선' => array('cable'),
            '케이블' => array('cable'),
            '카메라' => array('camera'),
            '양초' => array('candle'),
            '캔들' => array('candle'),
            '의자' => array('chair'),
            '체어' => array('chair'),
            '충전 케이블' => array('charger'),
            '충전기' => array('charger'),
            '벽시계' => array('clock'),
            '탁상시계' => array('clock'),
            '외투' => array('coat'),
            '코트' => array('coat'),
            '커피메이커' => array('coffee maker'),
            '색깔' => array('color colour'),
            '색상' => array('color colour'),
            '면' => array('cotton'),
            '코튼' => array('cotton'),
            '로션' => array('cream lotion'),
            '크림' => array('cream lotion'),
            '머그' => array('cup mug'),
            '머그컵' => array('cup mug'),
            '컵' => array('cup mug'),
            '커튼' => array('curtain'),
            '기저귀' => array('diaper nappy'),
            '드레스' => array('dress'),
            '원피스' => array('dress'),
            '덤벨' => array('dumbbell weights'),
            '아령' => array('dumbbell weights'),
            '귀걸이' => array('earrings'),
            '기프트' => array('gift present'),
            '선물' => array('gift present'),
            '장갑' => array('gloves'),
            '토트백' => array('handbag'),
            '핸드백' => array('handbag'),
            '모자' => array('hat cap'),
            '비니' => array('hat cap'),
            '캡' => array('hat cap'),
            '이어버드' => array('headphones earbuds'),
            '이어폰' => array('headphones earbuds'),
            '헤드폰' => array('headphones earbuds'),
            '헬멧' => array('helmet'),
            '맨투맨' => array('hoodie sweatshirt'),
            '후드' => array('hoodie sweatshirt'),
            '후드티' => array('hoodie sweatshirt'),
            '자켓' => array('jacket'),
            '재킷' => array('jacket'),
            '점퍼' => array('jacket'),
            '데님' => array('jeans'),
            '청바지' => array('jeans'),
            '전기포트' => array('kettle'),
            '주전자' => array('kettle'),
            '키보드' => array('keyboard'),
            '나이프' => array('knife'),
            '칼' => array('knife'),
            '램프' => array('lamp light'),
            '스탠드' => array('lamp light'),
            '조명' => array('lamp light'),
            '노트북' => array('laptop computer'),
            '랩탑' => array('laptop computer'),
            '가죽' => array('leather'),
            '천연가죽' => array('leather'),
            '립스틱' => array('lipstick'),
            '메이크업' => array('makeup'),
            '화장품' => array('makeup'),
            '거울' => array('mirror'),
            '모니터' => array('monitor screen'),
            '화면' => array('monitor screen'),
            '마우스' => array('mouse'),
            '목걸이' => array('necklace'),
            '공책' => array('notebook'),
            '노트' => array('notebook'),
            '프라이팬' => array('pan'),
            '후라이팬' => array('pan'),
            '볼펜' => array('pen'),
            '펜' => array('pen'),
            '연필' => array('pencil'),
            '향수' => array('perfume fragrance'),
            '폰케이스' => array('phone case cover'),
            '휴대폰 케이스' => array('phone case cover'),
            '스마트폰' => array('phone mobile'),
            '핸드폰' => array('phone mobile'),
            '휴대폰' => array('phone mobile'),
            '베개' => array('pillow cushion'),
            '쿠션' => array('pillow cushion'),
            '접시' => array('plate dish'),
            '냄비' => array('pot'),
            '보조배터리' => array('power bank'),
            '가격' => array('price cost'),
            '값' => array('price cost'),
            '프린터' => array('printer'),
            '잠옷' => array('pyjamas pajamas'),
            '파자마' => array('pyjamas pajamas'),
            '레인코트' => array('rain jacket waterproof'),
            '우비' => array('rain jacket waterproof'),
            '면도기' => array('razor shaver'),
            '반지' => array('ring'),
            '러그' => array('rug carpet'),
            '카펫' => array('rug carpet'),
            '샌들' => array('sandals'),
            '슬리퍼' => array('sandals'),
            '목도리' => array('scarf'),
            '스카프' => array('scarf'),
            '샴푸' => array('shampoo'),
            '셔츠' => array('shirt'),
            '와이셔츠' => array('shirt'),
            '구두' => array('shoe shoes footwear'),
            '신발' => array('shoe shoes footwear'),
            '반바지' => array('shorts'),
            '숏팬츠' => array('shorts'),
            '비단' => array('silk'),
            '실크' => array('silk'),
            '사이즈' => array('size'),
            '스커트' => array('skirt'),
            '치마' => array('skirt'),
            '스마트워치' => array('smartwatch'),
            '스니커즈' => array('sneakers trainers shoe'),
            '운동화' => array('sneakers trainers shoe'),
            '비누' => array('soap'),
            '삭스' => array('socks'),
            '양말' => array('socks'),
            '스피커' => array('speaker'),
            '유모차' => array('stroller pushchair'),
            '수트' => array('suit'),
            '정장' => array('suit'),
            '여행가방' => array('suitcase luggage'),
            '캐리어' => array('suitcase luggage'),
            '선글라스' => array('sunglasses'),
            '니트' => array('sweater jumper'),
            '스웨터' => array('sweater jumper'),
            '비키니' => array('swimsuit swimwear'),
            '수영복' => array('swimsuit swimwear'),
            '책상' => array('table desk'),
            '테이블' => array('table desk'),
            '태블릿' => array('tablet'),
            '텐트' => array('tent'),
            '넥타이' => array('tie'),
            '수건' => array('towel'),
            '타월' => array('towel'),
            '인형' => array('toy'),
            '장난감' => array('toy'),
            '바지' => array('trousers pants'),
            '슬랙스' => array('trousers pants'),
            '팬츠' => array('trousers pants'),
            '반팔' => array('tshirt t shirt tee'),
            '티셔츠' => array('tshirt t shirt tee'),
            '우산' => array('umbrella'),
            '속옷' => array('underwear'),
            '팬티' => array('underwear'),
            '지갑' => array('wallet purse'),
            '손목시계' => array('watch'),
            '시계' => array('watch'),
            '방수' => array('waterproof'),
            '무선' => array('wireless'),
            '양모' => array('wool'),
            '울' => array('wool'),
            '요가매트' => array('yoga mat'),

            // Russian.
            // Russian adjectives agree with their noun in gender and number, so a
            // single dictionary form matches almost nothing a shopper types:
            // "кожаная сумка" is the ordinary way to ask for a leather bag, and
            // only "кожаный" was listed.
            'кожаная' => array('leather'),
            'кожаные' => array('leather'),
            'кожаное' => array('leather'),
            'хлопковая' => array('cotton'),
            'хлопковые' => array('cotton'),
            'шерстяная' => array('wool'),
            'шерстяные' => array('wool'),
            'шелковая' => array('silk'),
            'шёлковая' => array('silk'),
            'водонепроницаемая' => array('waterproof'),
            'водонепроницаемые' => array('waterproof'),
            'водостойкая' => array('waterproof'),
            'беспроводная' => array('wireless'),
            'беспроводное' => array('wireless'),
            'спортивная' => array('sport sports'),
            'спортивные' => array('sport sports'),
            'спортивный' => array('sport sports'),
            'зимняя' => array('winter'),
            'зимние' => array('winter'),
            'зимний' => array('winter'),
            'летняя' => array('summer'),
            'летние' => array('summer'),
            'летний' => array('summer'),
            'детская' => array('kids child children'),
            'детские' => array('kids child children'),
            'детский' => array('kids child children'),
            'мужская' => array('men mens male'),
            'мужские' => array('men mens male'),
            'мужской' => array('men mens male'),
            'женская' => array('women womens female'),
            'женские' => array('women womens female'),
            'женский' => array('women womens female'),
            'рюкзак' => array('backpack'),
            'рюкзаки' => array('backpack'),
            'сумка' => array('bag'),
            'сумки' => array('bag'),
            'мяч' => array('ball'),
            'пояс' => array('belt'),
            'ремень' => array('belt'),
            'велосипед' => array('bicycle bike'),
            'одеяло' => array('blanket'),
            'плед' => array('blanket'),
            'книга' => array('book'),
            'книги' => array('book'),
            'сапоги' => array('boots'),
            'бутылка' => array('bottle'),
            'термос' => array('bottle'),
            'миска' => array('bowl'),
            'чаша' => array('bowl'),
            'браслет' => array('bracelet'),
            'кабель' => array('cable'),
            'провод' => array('cable'),
            'камера' => array('camera'),
            'фотоаппарат' => array('camera'),
            'свеча' => array('candle'),
            'свечи' => array('candle'),
            'кресло' => array('chair'),
            'стул' => array('chair'),
            'зарядка' => array('charger'),
            'зарядное устройство' => array('charger'),
            'будильник' => array('clock'),
            'настенные часы' => array('clock'),
            'пальто' => array('coat'),
            'плащ' => array('coat'),
            'кофеварка' => array('coffee maker'),
            'кофемашина' => array('coffee maker'),
            'цвет' => array('color colour'),
            'хлопковый' => array('cotton'),
            'хлопок' => array('cotton'),
            'крем' => array('cream lotion'),
            'лосьон' => array('cream lotion'),
            'кружка' => array('cup mug'),
            'чашка' => array('cup mug'),
            'штора' => array('curtain'),
            'шторы' => array('curtain'),
            'подгузники' => array('diaper nappy'),
            'платье' => array('dress'),
            'платья' => array('dress'),
            'гантели' => array('dumbbell weights'),
            'сережки' => array('earrings'),
            'серьги' => array('earrings'),
            'подарки' => array('gift present'),
            'подарок' => array('gift present'),
            'варежки' => array('gloves'),
            'перчатки' => array('gloves'),
            'сумочка' => array('handbag'),
            'кепка' => array('hat cap'),
            'шапка' => array('hat cap'),
            'шляпа' => array('hat cap'),
            'наушники' => array('headphones earbuds'),
            'каска' => array('helmet'),
            'шлем' => array('helmet'),
            'свитшот' => array('hoodie sweatshirt'),
            'толстовка' => array('hoodie sweatshirt'),
            'худи' => array('hoodie sweatshirt'),
            'куртка' => array('jacket'),
            'куртки' => array('jacket'),
            'пиджак' => array('jacket'),
            'джинсы' => array('jeans'),
            'чайник' => array('kettle'),
            'клавиатура' => array('keyboard'),
            'нож' => array('knife'),
            'ножи' => array('knife'),
            'лампа' => array('lamp light'),
            'светильник' => array('lamp light'),
            'компьютер' => array('laptop computer'),
            'ноутбук' => array('laptop computer'),
            'кожа' => array('leather'),
            'кожаный' => array('leather'),
            'помада' => array('lipstick'),
            'косметика' => array('makeup'),
            'макияж' => array('makeup'),
            'зеркало' => array('mirror'),
            'монитор' => array('monitor screen'),
            'экран' => array('monitor screen'),
            'мышка' => array('mouse'),
            'мышь' => array('mouse'),
            'колье' => array('necklace'),
            'ожерелье' => array('necklace'),
            'цепочка' => array('necklace'),
            'блокнот' => array('notebook'),
            'тетрадь' => array('notebook'),
            'сковорода' => array('pan'),
            'ручка' => array('pen'),
            'ручки' => array('pen'),
            'карандаш' => array('pencil'),
            'духи' => array('perfume fragrance'),
            'парфюм' => array('perfume fragrance'),
            'чехол' => array('phone case cover'),
            'чехол для телефона' => array('phone case cover'),
            'смартфон' => array('phone mobile'),
            'телефон' => array('phone mobile'),
            'подушка' => array('pillow cushion'),
            'подушки' => array('pillow cushion'),
            'тарелка' => array('plate dish'),
            'тарелки' => array('plate dish'),
            'кастрюля' => array('pot'),
            'внешний аккумулятор' => array('power bank'),
            'павербанк' => array('power bank'),
            'стоимость' => array('price cost'),
            'цена' => array('price cost'),
            'принтер' => array('printer'),
            'пижама' => array('pyjamas pajamas'),
            'дождевик' => array('rain jacket waterproof'),
            'бритва' => array('razor shaver'),
            'кольца' => array('ring'),
            'кольцо' => array('ring'),
            'ковер' => array('rug carpet'),
            'босоножки' => array('sandals'),
            'сандалии' => array('sandals'),
            'шлепанцы' => array('sandals'),
            'платок' => array('scarf'),
            'шарф' => array('scarf'),
            'шампунь' => array('shampoo'),
            'рубашка' => array('shirt'),
            'рубашки' => array('shirt'),
            'сорочка' => array('shirt'),
            'ботинки' => array('shoe shoes footwear'),
            'обувь' => array('shoe shoes footwear'),
            'туфли' => array('shoe shoes footwear'),
            'шорты' => array('shorts'),
            'шелк' => array('silk'),
            'размер' => array('size'),
            'юбка' => array('skirt'),
            'юбки' => array('skirt'),
            'смарт часы' => array('smartwatch'),
            'умные часы' => array('smartwatch'),
            'кеды' => array('sneakers trainers shoe'),
            'кроссовки' => array('sneakers trainers shoe'),
            'мыло' => array('soap'),
            'носки' => array('socks'),
            'носок' => array('socks'),
            'динамик' => array('speaker'),
            'колонка' => array('speaker'),
            'коляска' => array('stroller pushchair'),
            'костюм' => array('suit'),
            'багаж' => array('suitcase luggage'),
            'чемодан' => array('suitcase luggage'),
            'очки от солнца' => array('sunglasses'),
            'солнцезащитные очки' => array('sunglasses'),
            'джемпер' => array('sweater jumper'),
            'кофта' => array('sweater jumper'),
            'свитер' => array('sweater jumper'),
            'купальник' => array('swimsuit swimwear'),
            'плавки' => array('swimsuit swimwear'),
            'письменный стол' => array('table desk'),
            'стол' => array('table desk'),
            'планшет' => array('tablet'),
            'палатка' => array('tent'),
            'галстук' => array('tie'),
            'полотенце' => array('towel'),
            'игрушка' => array('toy'),
            'игрушки' => array('toy'),
            'брюки' => array('trousers pants'),
            'штаны' => array('trousers pants'),
            'майка' => array('tshirt t shirt tee'),
            'футболка' => array('tshirt t shirt tee'),
            'футболки' => array('tshirt t shirt tee'),
            'зонт' => array('umbrella'),
            'зонтик' => array('umbrella'),
            'белье' => array('underwear'),
            'трусы' => array('underwear'),
            'бумажник' => array('wallet purse'),
            'кошелек' => array('wallet purse'),
            'наручные часы' => array('watch'),
            'часы' => array('watch'),
            'водонепроницаемый' => array('waterproof'),
            'водостойкий' => array('waterproof'),
            'беспроводной' => array('wireless'),
            'беспроводные' => array('wireless'),
            'шерсть' => array('wool'),
            'шерстяной' => array('wool'),
            'коврик для йоги' => array('yoga mat'),
        );

        $synonyms = array_merge($synonyms, $this->custom_synonyms_from_settings());

        /**
         * Filter multilingual product-search synonyms.
         * Format: array('shopper word' => array('catalog word', 'another word')).
         */
        return (array) apply_filters('geekybot_search_synonyms', $synonyms);
    }

    /**
     * Tokens a synonym key or its alternates contribute, in both the tokenised
     * and the plainly normalised form. See expand_synonyms_map().
     *
     * @param string $text Synonym key or alternate list.
     * @return array<int, string>
     */
    private function expansion_tokens($text) {
        $text = (string) $text;
        if (trim($text) === '') {
            return array();
        }

        $tokens = $this->query_terms($text);
        $plain = preg_split('/\s+/u', $this->normalize_text($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(array_merge(
            (array) $tokens,
            is_array($plain) ? $plain : array()
        ))));
    }

    private function custom_synonyms_from_settings() {
        $raw = class_exists(__NAMESPACE__ . '\\Settings') ? Settings::get('search_custom_synonyms', '') : '';
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }

        $items = array();
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = preg_split('/\s*(?:=>|=|:)\s*/', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $word = $this->normalize_text($parts[0]);
            $alts_raw = trim((string) $parts[1]);
            if ($word === '' || $alts_raw === '') {
                continue;
            }

            $alts = array();
            foreach (preg_split('/\s*[,|]\s*/', $alts_raw) as $alt) {
                $alt = $this->normalize_text($alt);
                if ($alt !== '') {
                    $alts[] = $alt;
                }
            }

            if (!empty($alts)) {
                $items[$word] = array_values(array_unique($alts));
            }
        }

        return $items;
    }


    public function negative_facets_from_query($query) {
        $query = $this->normalize_text($query);

        // Negative facet parsing is comparatively expensive because it must
        // consider multilingual operators and aliases. Most shopper searches
        // contain no exclusion at all, so leave immediately unless a complete
        // negative operator token/phrase is present.
        if (!$this->has_negative_facet_signal($query)) {
            $facets = apply_filters('geekybot_search_negative_facets', array(
                'colors' => array(),
                'sizes' => array(),
            ), $query);
            return is_array($facets) ? $facets : array('colors' => array(), 'sizes' => array());
        }

        $colors = $this->negative_terms_for_map($query, $this->color_keyword_map());
        $sizes = $this->negative_terms_for_map($query, $this->size_keyword_map());

        /**
         * Filters negative facets parsed from a shopper query, such as "not black".
         *
         * @param array  $facets Negative facets with colors and sizes.
         * @param string $query  Normalized shopper query.
         */
        $facets = apply_filters('geekybot_search_negative_facets', array(
            'colors' => $this->normalize_facet_term_list($colors),
            'sizes' => $this->normalize_facet_term_list($sizes),
        ), $query);

        return is_array($facets) ? $facets : array('colors' => array(), 'sizes' => array());
    }

    public function buyer_modifier_terms($query) {
        $query = $this->normalize_text($query);
        $profile = $this->buyer_intent_profile($query);
        $terms = !empty($profile['modifier_terms']) ? (array) $profile['modifier_terms'] : array();

        // The versioned buyer library returns compact canonical terms for
        // supported shopper language. Keep the legacy maps only as a fallback
        // for languages/phrases not yet represented by the local rule pack.
        if (empty($terms)) {
            foreach (array_merge($this->buyer_preference_keyword_map(), $this->buyer_use_case_keyword_map()) as $canonical => $aliases) {
                foreach ((array) $aliases as $alias) {
                    $alias = $this->normalize_text($alias);
                    if ($alias === '') {
                        continue;
                    }
                    if ($this->contains_phrase($query, $alias)) {
                        $terms[] = $canonical;
                        $terms = array_merge($terms, $this->query_terms($alias));
                        foreach ((array) $aliases as $group_alias) {
                            foreach ($this->query_terms($group_alias) as $token) {
                                $terms[] = $token;
                            }
                        }
                        break;
                    }
                }
            }
        }

        /**
         * Filters soft buyer-intent terms that should boost matching products but
         * should not be required as hard catalog terms.
         *
         * @param array  $terms Soft intent terms.
         * @param string $query Normalized shopper query.
         */
        $terms = (array) apply_filters('geekybot_search_buyer_modifier_terms', $terms, $query);
        return $this->normalize_facet_term_list($terms);
    }

    public function buyer_modifier_labels($query) {
        $query = $this->normalize_text($query);
        $profile = $this->buyer_intent_profile($query);
        $labels = !empty($profile['modifier_labels']) ? (array) $profile['modifier_labels'] : array();
        $label_map = array(
            'comfortable' => __('comfort', 'geeky-bot'),
            'formal' => __('formal', 'geeky-bot'),
            'casual' => __('casual', 'geeky-bot'),
            'premium' => __('premium quality', 'geeky-bot'),
            'budget' => __('budget-friendly', 'geeky-bot'),
            'gift' => __('gift', 'geeky-bot'),
            'popular' => __('popular', 'geeky-bot'),
            'useful' => __('useful', 'geeky-bot'),
            'simple' => __('simple style', 'geeky-bot'),
            'quality' => __('quality', 'geeky-bot'),
            'value' => __('best value', 'geeky-bot'),
            'winter' => __('winter', 'geeky-bot'),
            'summer' => __('summer', 'geeky-bot'),
            'sports' => __('sports/walking', 'geeky-bot'),
            'travel' => __('travel', 'geeky-bot'),
            'party' => __('party/event', 'geeky-bot'),
            'school' => __('school/college', 'geeky-bot'),
        );

        if (empty($labels)) {
            foreach (array_merge($this->buyer_preference_keyword_map(), $this->buyer_use_case_keyword_map()) as $canonical => $aliases) {
                foreach ((array) $aliases as $alias) {
                    $alias = $this->normalize_text($alias);
                    if ($alias !== '' && $this->contains_phrase($query, $alias)) {
                        $labels[] = isset($label_map[$canonical]) ? $label_map[$canonical] : $canonical;
                        break;
                    }
                }
            }
        }

        $labels = (array) apply_filters('geekybot_search_buyer_modifier_labels', $labels, $query);
        return array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $labels))));
    }

    /**
     * @param string     $query   Text to strip.
     * @param array|null $profile Buyer-intent profile from the shopper's ORIGINAL
     *                            wording. Pass it whenever the caller has one:
     *                            profiling the already-stripped text re-detects
     *                            the language from a sentence whose giveaway
     *                            words are gone, so "einen leichten rucksack"
     *                            was profiled as English and `leichten` survived
     *                            as a required product term.
     */
    public function strip_buyer_modifier_phrases($query, $profile = null) {
        $query = $this->normalize_text($query);
        if (!is_array($profile) || empty($profile)) {
            $profile = $this->buyer_intent_profile($query);
        }
        $query = $this->buyer_intent_library()->strip_phrases($query, $profile);
        $phrases = array(
            'better color', 'preferred color', 'color preference', 'better colours', 'better colors',
            'good color', 'nice color', 'best color', 'color of', 'colour of', 'color', 'colour',
            'size of', 'size', 'sizes', 'for me', 'for someone', 'as a gift', 'gift for my brother', 'gift for brother', 'for my brother', 'for brother', 'my brother', 'gift for my sister', 'for my sister', 'my sister', 'gift for',
            'maybe', 'perhaps', 'possibly', 'if possible', 'possible', 'something', 'option', 'options',
            'that are', 'that is', 'which are', 'which is', 'only show', 'show only', 'this category', 'that category', 'category', 'categories',
            'actually', 'currently', 'right now', 'available now', 'current', 'still', 'look', 'looks', 'that still look',
            'not expensive', 'not too expensive', 'too expensive', 'expensive', 'costly', 'pricey', 'luxury', 'high price', 'high priced', 'reasonable price', 'good price', 'value for money',
            'best value', 'good value', 'not cheapest', 'not the cheapest', 'not lowest', 'not the lowest', 'the cheapest one', 'cheapest one', 'lowest price', 'safe choice', 'safest choice',
            'good quality', 'quality', 'good for', 'good', 'nice', 'useful', 'popular', 'simple',
            'do not want out of stock', 'do not want out-of-stock', 'don t want out of stock', 'don t want out-of-stock', 'dont want out of stock', 'dont want out-of-stock', 'only available ones', 'available ones', 'out of stock', 'out-of-stock', 'outofstock',
        );

        foreach (array_merge($this->buyer_preference_keyword_map(), $this->buyer_use_case_keyword_map()) as $aliases) {
            foreach ((array) $aliases as $alias) {
                $phrases[] = $alias;
            }
        }

        $phrases = (array) apply_filters('geekybot_search_strip_buyer_modifier_phrases', $phrases, $query);
        $normalized_phrases = array();
        foreach ($phrases as $phrase) {
            $phrase = $this->normalize_text($phrase);
            if ($phrase !== '') {
                $normalized_phrases[] = $phrase;
            }
        }
        $normalized_phrases = array_values(array_unique($normalized_phrases));
        usort($normalized_phrases, function ($a, $b) {
            $la = function_exists('mb_strlen') ? mb_strlen($a, 'UTF-8') : strlen($a);
            $lb = function_exists('mb_strlen') ? mb_strlen($b, 'UTF-8') : strlen($b);
            if ($la === $lb) {
                return 0;
            }
            return $la > $lb ? -1 : 1;
        });
        foreach ($normalized_phrases as $phrase) {
            $query = preg_replace('/(?:^|\s)' . preg_quote($phrase, '/') . '(?=\s|$)/u', ' ', $query);
        }

        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }

    public function strip_negative_facets($query) {
        $query = $this->normalize_text($query);
        if (!$this->has_negative_facet_signal($query)) {
            return $query;
        }

        // Same guard as negative_terms_for_map(), for the same reason: the
        // pattern embeds both strings with preg_quote, so it cannot replace
        // anything unless both are literally present. Unguarded this ran 22
        // operators x ~250 aliases = ~5,500 pattern compilations on every query
        // carrying a negation, and measured 227ms of a 590ms analyze_query().
        $operators = array();
        foreach ($this->negative_operator_keywords() as $operator) {
            $operator = $this->normalize_text($operator);
            if ($operator !== '' && strpos($query, $operator) !== false) {
                $operators[] = $operator;
            }
        }

        if (empty($operators)) {
            return trim(preg_replace('/\s+/u', ' ', (string) $query));
        }

        $all_aliases = array();
        foreach (array_merge($this->color_keyword_map(), $this->size_keyword_map()) as $aliases) {
            foreach ((array) $aliases as $alias) {
                $alias = $this->normalize_text($alias);
                if ($alias !== '' && strpos($query, $alias) !== false) {
                    $all_aliases[] = $alias;
                }
            }
        }

        foreach ($operators as $operator) {
            foreach ($all_aliases as $alias) {
                // $query shrinks as replacements land, so re-check rather than
                // trusting the pre-filter for every later pass.
                if (strpos($query, $alias) === false || strpos($query, $operator) === false) {
                    continue;
                }
                $query = preg_replace('/(?:^|\s)' . preg_quote($operator, '/') . '\s+(?:color\s+|colour\s+|size\s+|in\s+|with\s+)?' . preg_quote($alias, '/') . '(?=\s|$)/u', ' ', $query);
            }
        }

        $query = preg_replace('/\s+/u', ' ', (string) $query);
        return trim($query);
    }


    public function is_weak_shopper_token($token) {
        $token = $this->normalize_text($token);
        if ($token === '') {
            return true;
        }

        $weak = array_fill_keys(array(
            'don', 'dont', 't', 'what', 'which', 'if', 'one', 'ones', 'would', 'will', 'should', 'could', 'maybe', 'possible', 'possibly',
            'something', 'option', 'options', 'product', 'products', 'item', 'items', 'actually', 'currently', 'still', 'look', 'looks',
            'good', 'nice', 'quality', 'simple', 'useful', 'popular', 'expensive', 'costly', 'pricey', 'luxury', 'cheapest', 'lowest'
        ), true);

        return isset($weak[$token]);
    }

    public function stop_words($language = 'en') {
        // "keep" says what a product should do ("something to keep warm", "keep
        // my drinks cold"), never what it is. As a search word it matched Smart
        // Catalog names such as "time keeper", so a watch outranked the miss
        // that should have gone to Rescue. "sell" and "carry" frame the question
        // ("do you sell hairdryers?"); left in, typo recovery read "sell" as
        // "shell" and answered with a shell jacket.
        $base = array(
            'a', 'an', 'any', 'don', 'dont', 't', 'and', 'are', 'as', 'at', 'be', 'best', 'better', 'buy', 'can', 'could', 'do', 'does', 'find', 'for', 'from', 'get', 'give', 'got', 'have', 'has', 'i', 'im', "i'm", 'in', 'is', 'it', 'if', 'like', 'looking', 'look', 'maybe', 'may', 'might', 'me', 'my', 'need', 'needs', 'nice', 'no', 'not', 'of', 'on', 'one', 'ones', 'option', 'options', 'or', 'perhaps', 'please', 'kindly', 'keep', 'keeps', 'keeping', 'sell', 'sells', 'selling', 'carry', 'carries', 'also', 'prefer', 'preferably', 'preferred', 'product', 'products', 'search', 'show', 'should', 'some', 'something', 'suggest', 'the', 'there', 'that', 'this', 'these', 'those', 'then', 'to', 'too', 'u', 'want', 'wants', 'we', 'what', 'which', 'would', 'will', 'with', 'without', 'you', 'your', 'but', 'only', 'actually', 'currently', 'current', 'right', 'now', 'good', 'useful', 'simple',
            'price', 'priced', 'cost', 'costing', 'budget', 'range', 'under', 'below', 'less', 'than', 'max', 'maximum', 'up', 'over', 'above', 'more', 'min', 'minimum', 'between', 'around', 'about', 'near', 'approximately', 'approx', 'roughly', 'color', 'colour', 'colors', 'colours', 'size', 'sizes', 'sized', 'category', 'categories', 'still', 'possible', 'possibility', 'quality', 'expensive', 'costly', 'pricey', 'luxury', 'cheapest', 'lowest', 'rs', 'pkr', 'usd', 'eur', 'gbp', 'aed', 'sar', 'qar', 'kwd', 'inr', 'dollar', 'dollars',
        );

        $extra = array(
            'ur' => array('میں', 'مجھے', 'میرا', 'میرے', 'آپ', 'اپ', 'کو', 'کے', 'کی', 'کا', 'اور', 'یا', 'ہے', 'ہیں', 'دو', 'دکھاؤ', 'دکھاو', 'چاہیے', 'چاہتا', 'چاہتی', 'تلاش', 'ڈھونڈو', 'پروڈکٹ', 'مصنوعات', 'قیمت', 'کم', 'زیادہ', 'سے', 'تک', 'درمیان', 'نیچے', 'اوپر'),
            'ar' => array('انا', 'أريد', 'اريد', 'هل', 'من', 'في', 'على', 'او', 'أو', 'و', 'مع', 'لي', 'عن', 'هذا', 'هذه', 'منتج', 'منتجات', 'اعرض', 'اظهر', 'ابحث', 'السعر', 'سعر', 'اقل', 'أقل', 'اكثر', 'أكثر', 'من', 'الى', 'إلى', 'بين', 'تحت', 'فوق',
                // Follow-up and question words. Without these an Arabic follow-up
                // becomes a product search for its own grammar: "وهل يوجد أسود؟"
                // searched for وهل and يوجد, and the spelling recovery rewrote
                // "كم سعره؟" into "كم ستره" and answered with a jacket. The
                // corrector skips stop words for exactly this reason -- the English
                // list already relies on it to stop "show me" becoming "shoe".
                'منها', 'منه', 'منهم', 'وهل', 'يوجد', 'توجد', 'عنده', 'عندك', 'لديك', 'لديه',
                'كم', 'سعره', 'سعرها', 'ايضا', 'أيضا', 'اخرى', 'أخرى', 'اخر', 'آخر', 'نفس',
                'ذلك', 'تلك', 'الذي', 'التي', 'ممكن', 'ايه', 'وش', 'كيف', 'متى', 'اين', 'أين'),
            'fa' => array('من', 'میخواهم', 'میخواهم', 'برای', 'از', 'در', 'با', 'یا', 'و', 'محصول', 'محصولات', 'نمایش', 'جستجو', 'قیمت', 'کمتر', 'بیشتر', 'بین', 'تا'),
            'hi' => array('मैं', 'मुझे', 'मेरे', 'आप', 'को', 'का', 'की', 'के', 'और', 'या', 'है', 'दिखाओ', 'चाहिए', 'उत्पाद', 'प्रोडक्ट', 'कीमत', 'कम', 'से', 'ज्यादा', 'बीच'),
            'es' => array('yo', 'me', 'mi', 'mis', 'tu', 'un', 'una', 'unos', 'unas', 'el', 'la', 'los', 'las', 'lo', 'de', 'del', 'al', 'en', 'para', 'por', 'con', 'sin', 'sobre', 'y', 'o', 'que', 'no', 'es', 'son', 'hay', 'tiene', 'tienes', 'tienen', 'quiero', 'queria', 'busco', 'buscar', 'buscando', 'necesito', 'mostrar', 'muestrame', 'ver', 'cual', 'cuales', 'algo', 'producto', 'productos', 'precio', 'menos', 'mas', 'más', 'muy', 'entre', 'hola', 'gracias', 'favor'),
            'fr' => array('je', 'j', 'me', 'moi', 'mon', 'ma', 'mes', 'un', 'une', 'le', 'la', 'les', 'l', 'de', 'des', 'du', 'au', 'aux', 'ce', 'cet', 'cette', 'ces', 'pour', 'avec', 'sans', 'dans', 'sur', 'et', 'ou', 'est', 'sont', 'ai', 'as', 'avez', 'avoir', 'veux', 'voudrais', 'cherche', 'chercher', 'recherche', 'besoin', 'montrer', 'montrez', 'trouver', 'quel', 'quelle', 'quels', 'quelles', 'que', 'qui', 'quoi', 'produit', 'produits', 'prix', 'moins', 'plus', 'tres', 'entre', 'bonjour', 'merci', 'svp'),
            // German inflects its determiners and adjectives, so the base form
            // is not enough: "einen leichten Rucksack" kept `einen` as a
            // required product term because only `ein` and `eine` were listed.
            'de' => array('ich', 'mir', 'mich', 'mein', 'meine', 'meinen', 'meinem', 'meiner', 'meines', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines', 'der', 'die', 'das', 'den', 'dem', 'des', 'dieser', 'diese', 'dieses', 'diesen', 'zu', 'zum', 'zur', 'fur', 'für', 'mit', 'und', 'oder', 'aber', 'auch', 'noch', 'schon', 'sehr', 'nicht', 'kein', 'keine', 'ist', 'sind', 'habe', 'haben', 'hast', 'hat', 'braucht', 'brauche', 'suche', 'suchen', 'mochte', 'möchte', 'will', 'zeigen', 'zeig', 'finden', 'produkt', 'produkte', 'preis', 'unter', 'uber', 'über', 'zwischen', 'bitte', 'danke', 'hallo'),
            'it' => array('io', 'mi', 'me', 'mio', 'mia', 'miei', 'un', 'uno', 'una', 'il', 'lo', 'la', 'i', 'gli', 'le', 'di', 'del', 'della', 'dei', 'delle', 'da', 'in', 'su', 'per', 'con', 'senza', 'e', 'o', 'che', 'non', 'sono', 'ho', 'hai', 'avete', 'vorrei', 'voglio', 'cerco', 'cerca', 'cercando', 'serve', 'bisogno', 'mostra', 'mostrami', 'trovare', 'quale', 'quali', 'cosa', 'prodotto', 'prodotti', 'prezzo', 'meno', 'piu', 'più', 'molto', 'tra', 'ciao', 'grazie'),
            'pt' => array('eu', 'me', 'meu', 'minha', 'meus', 'minhas', 'um', 'uma', 'uns', 'umas', 'o', 'a', 'os', 'as', 'de', 'do', 'da', 'dos', 'das', 'no', 'na', 'em', 'para', 'pra', 'por', 'com', 'sem', 'e', 'ou', 'que', 'nao', 'não', 'sou', 'tem', 'tenho', 'quero', 'queria', 'preciso', 'procuro', 'procurando', 'buscar', 'mostrar', 'mostra', 'ver', 'achar', 'qual', 'quais', 'produto', 'produtos', 'preco', 'preço', 'menos', 'mais', 'muito', 'entre', 'ola', 'olá', 'obrigado'),
            'nl' => array('ik', 'mij', 'me', 'mijn', 'jij', 'je', 'jullie', 'u', 'een', 'de', 'het', 'die', 'dat', 'deze', 'dit', 'van', 'voor', 'met', 'zonder', 'op', 'in', 'aan', 'en', 'of', 'maar', 'is', 'zijn', 'heb', 'hebt', 'heeft', 'hebben', 'kan', 'kun', 'kunt', 'wil', 'zou', 'graag', 'nodig', 'zoek', 'zoeken', 'toon', 'laat', 'zien', 'vinden', 'help', 'welke', 'welk', 'wat', 'product', 'producten', 'prijs', 'onder', 'boven', 'tussen', 'hallo', 'bedankt', 'alsjeblieft'),
            'tr' => array('ben', 'bana', 'benim', 'bir', 've', 'veya', 'icin', 'için', 'ile', 'ara', 'goster', 'göster', 'urun', 'ürün', 'urunler', 'ürünler', 'fiyat', 'altinda', 'altında', 'ustunde', 'üstünde', 'arasi', 'arası'),
            'id' => array('saya', 'aku', 'mau', 'ingin', 'dan', 'atau', 'untuk', 'dengan', 'cari', 'tampilkan', 'produk', 'harga', 'dibawah', 'di bawah', 'diatas', 'di atas', 'antara'),
            'ms' => array('saya', 'mahu', 'ingin', 'dan', 'atau', 'untuk', 'dengan', 'cari', 'papar', 'produk', 'harga', 'bawah', 'atas', 'antara'),
            'ru' => array('я', 'мне', 'меня', 'мой', 'моя', 'мои', 'вы', 'ты', 'вас', 'это', 'этот', 'эта', 'эти', 'тот', 'та', 'те', 'и', 'или', 'но', 'а', 'для', 'с', 'со', 'в', 'на', 'по', 'из', 'от', 'до', 'без', 'что', 'как', 'где', 'есть', 'нет', 'нужен', 'нужна', 'нужно', 'надо', 'хочу', 'хотел', 'могу', 'можно', 'ищу', 'искать', 'найти', 'показать', 'покажите', 'посмотреть', 'подскажите', 'посоветуйте', 'помогите', 'товар', 'товары', 'цена', 'дешевле', 'меньше', 'больше', 'между', 'здравствуйте', 'привет', 'спасибо', 'пожалуйста', 'очень', 'просто', 'много', 'немного', 'несколько', 'пара', 'пары', 'штук', 'штуки', 'штука', 'два', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять', 'десять', 'осталось', 'остались', 'остался', 'имеется', 'имеются', 'продается', 'продаются', 'бывает', 'бывают', 'какой', 'какая', 'какие', 'какое', 'который', 'которые', 'ваш', 'ваша', 'ваши', 'вашем', 'у', 'же', 'ли', 'бы', 'был', 'была', 'было', 'были', 'будет', 'тут', 'здесь', 'там', 'сейчас', 'лучше', 'самый', 'самая', 'самые', 'более', 'хотелось', 'нравится', 'подойдет', 'подходит'),
            'zh' => array('我', '你', '您', '想', '要', '需要', '买', '買', '找', '看', '看看', '给', '給', '帮', '幫', '的', '了', '吗', '嗎', '呢', '吧', '是', '有', '没有', '沒有', '这个', '這個', '那个', '哪个', '哪個', '什么', '什麼', '怎么', '怎麼', '显示', '顯示', '推荐', '推薦', '介绍', '產品', '产品', '商品', '价格', '價格', '低于', '高于', '之间', '之間', '多少', '多少钱', '一些', '一个', '一個', '你好', '谢谢', '謝謝'),
            'ja' => array('私', 'わたし', '僕', '欲しい', 'ほしい', 'ください', 'です', 'ます', 'した', 'して', 'これ', 'それ', 'あれ', 'どれ', 'どの', 'この', 'その', 'あの', '買う', '買いたい', '探す', '探し', '探して', '表示', '見せて', '教えて', 'おすすめ', 'オススメ', 'お勧め', '商品', '製品', '価格', '値段', '以下', '以上', '未満', 'もの', 'こと', 'ある', 'ない', 'あります', 'ありますか', 'いくら', 'なに', '何', 'こんにちは', 'ありがとう'),
            'ko' => array('나', '저', '제', '내', '너', '당신', '이', '그', '저것', '이것', '그것', '어떤', '무슨', '뭐', '무엇', '어디', '얼마', '은', '는', '이', '가', '을', '를', '의', '에', '에서', '으로', '로', '와', '과', '도', '만', '원해', '원해요', '구매', '사고', '사려고', '찾기', '찾아요', '찾고', '필요해요', '필요한', '보기', '보여', '보여주세요', '알려주세요', '추천', '추천해', '상품', '제품', '가격', '이하', '이상', '있나요', '있어요', '주세요', '안녕하세요', '감사합니다', '좀'),
        );

        $language = strtolower((string) $language);
        if (isset($this->stop_words_cache[$language])) {
            return $this->stop_words_cache[$language];
        }

        $words = array_merge($base, isset($extra[$language]) ? $extra[$language] : array());

        /**
         * Filters language-aware product-search stop words.
         *
         * @param array  $words    Stop words.
         * @param string $language Detected language code.
         */
        $words = (array) apply_filters('geekybot_search_stop_words', $words, $language);
        $words = array_values(array_unique(array_map(array($this, 'normalize_text'), $words)));
        $this->stop_words_cache[$language] = $words;
        return $words;
    }

    private function product_keywords() {
        return array('product', 'products', 'item', 'items', 'catalog', 'price', 'cost', 'stock', 'available', 'availability', 'size', 'color', 'colour', 'brand', 'material', 'style', 'fit', 'recommend', 'suggest', 'compare', 'buy', 'looking for', 'find', 'cheap', 'affordable', 'premium', 'discount', 'deal', 'coupon', 'مصنوعات', 'پروڈکٹ', 'قیمت', 'رنگ', 'سائز', 'برانڈ', 'موجود', 'دستیاب', 'منتج', 'منتجات', 'سعر', 'لون', 'مقاس', 'ماركة', 'متوفر', 'producto', 'productos', 'precio', 'talla', 'color', 'produit', 'produits', 'prix', 'taille', 'couleur', 'produkt', 'produkte', 'preis', 'größe', 'farbe', 'prodotto', 'prodotti', 'prezzo', 'taglia', 'colore', 'marca', 'materiale', 'disponibile', 'produto', 'produtos', 'preco', 'preço', 'tamanho', 'cor', 'disponivel', 'estoque', '商品', '製品', '価格', '値段', 'サイズ', '色', 'ブランド', '在庫', 'おすすめ', '产品', '產品', '商品', '价格', '價格', '尺码', '尺碼', '颜色', '顏色', '品牌', '库存', '推荐', '推薦', 'product', 'producten', 'prijs', 'maat', 'kleur', 'merk', 'voorraad', 'beschikbaar', '상품', '제품', '가격', '사이즈', '색상', '브랜드', '재고', '추천', 'товар', 'товары', 'продукт', 'цена', 'размер', 'цвет', 'бренд', 'наличие', 'скидка');
    }

    private function latest_keywords() {
        // 'novo' and 'nova' are deliberately absent: both are ordinary English
        // brand words ("Nova" lamp), and treating them as a newest-first signal
        // reordered an ordinary product search. Portuguese keeps the forms that
        // cannot be mistaken -- novidade, lancamento, recem chegado.
    // Spanish and Arabic were carrying only part of their paradigm. Matching
    // here is whole-token (see contains_phrase), so 'nueva' does nothing for
    // "camisetas nuevas" and 'جديد' does nothing for "منتجات جديدة" -- the
    // feminine and the plural have to be listed, exactly like the colour and
    // price gaps before them.
    //
    // Bare 'ultimo'/'ultima' stay out for the reason 'novo'/'nova' do: "la
    // ultima talla" and "ultimas unidades" are about the last one LEFT, not the
    // newest, so the Spanish 'last' forms are only trusted inside a phrase --
    // the same shape Italian already uses with 'ultimo arrivo'.
        return array('latest', 'new', 'newest', 'recent', 'recently added', 'new arrivals', 'نیا', 'نئی', 'تازہ', 'جدید', 'جديد', 'أحدث', 'احدث', 'nouveau', 'nouveaux', 'nouvelle', 'nouveautes', 'nouveautés', 'nuevo', 'nuevos', 'nueva', 'novedades', 'recien llegado', 'neu', 'neue', 'neuheiten', 'neueste', 'neu eingetroffen', 'novita', 'novità', 'ultimo arrivo', 'ultimi arrivi', 'appena arrivato', 'novidade', 'novidades', 'lancamento', 'lançamento', 'recem chegado', 'acabou de chegar', '新作', '新着', '最新', '新商品', '入荷', '新款', '新品', '最新', '新到', '上新', 'nieuw', 'nieuwe', 'nieuwste', 'net binnen', '신상', '신상품', '신제품', '새로 나온', 'новинка', 'новинки', 'новый', 'новая', 'новые', 'свежее поступление', 'ultimos productos', 'ultimos articulos', 'ultimos modelos', 'ultimas llegadas', 'lo ultimo', 'lo mas nuevo', 'lo mas reciente', 'nuevas', 'reciente', 'recientes', 'recien llegados', 'nuevas llegadas', 'جديدة', 'حديثا', 'وصل حديثا', 'وصلت حديثا');
    }

    private function sale_keywords() {
    // 'rebajas' was here without 'rebaja' or any of the four participle forms,
    // and Spanish 'promocion'/'liquidacion' were missing although the
    // Portuguese and French cognates were present. Arabic had the nouns
    // (تخفيض, خصم) but not the adjective a shopper actually types:
    // "منتجات مخفضة" found nothing.
        return array('sale', 'discount', 'discounted', 'deals', 'deal', 'offer', 'offers', 'clearance', 'coupon', 'رعایت', 'سیل', 'آفر', 'افر', 'خصم', 'خصومات', 'عرض', 'عروض', 'تخفيض', 'تخفيضات', 'تنزيلات', 'اوكازيون', 'oferta', 'ofertas', 'descuento', 'descuentos', 'rebajas', 'promotion', 'promo', 'solde', 'soldes', 'en solde', 'reduction', 'remise', 'rabatt', 'angebot', 'angebote', 'reduziert', 'aktion', 'sconto', 'sconti', 'scontato', 'offerta', 'offerte', 'in offerta', 'saldi', 'promocao', 'promoção', 'em promocao', 'desconto', 'descontos', 'liquidacao', 'liquidação', 'セール', '割引', '値下げ', 'お買い得', '特価', 'アウトレット', '打折', '促销', '促銷', '特价', '特價', '优惠', '優惠', '折扣', '减价', '清仓', 'aanbieding', 'aanbiedingen', 'korting', 'afgeprijsd', 'uitverkoop', 'sale', '할인', '세일', '특가', '할인중', '떨이', 'скидка', 'скидки', 'распродажа', 'акция', 'уценка', 'со скидкой', 'rebaja', 'rebajado', 'rebajada', 'rebajados', 'rebajadas', 'promocion', 'promociones', 'en promocion', 'liquidacion', 'مخفض', 'مخفضة', 'مخفضات', 'صفقة', 'صفقات', 'حسم', 'حسومات');
    }

    private function top_rated_keywords() {
    // The Spanish entries were masculine-only, and Arabic listed تقييم but not
    // the accusative تقييما that "الأعلى تقييما" -- the ordinary way to say it --
    // actually ends in.
        return array('top rated', 'highly rated', 'highest rated', 'best rated', 'better rated', 'rating', 'ratings', 'reviews', 'well reviewed', 'بہترین ریٹنگ', 'ریٹنگ', 'اعلى تقييم', 'أعلى تقييم', 'تقييم', 'mejor valorado', 'mejor valorados', 'mejores opiniones', 'meilleure note', 'les mieux notes', 'les mieux notés', 'meilleurs avis', 'bewertet', 'bestbewertet', 'beste bewertungen', 'gut bewertet', 'valutato', 'piu votati', 'più votati', 'migliori recensioni', 'mais bem avaliado', 'melhor avaliado', 'melhores avaliacoes', 'melhores avaliações', '高評価', '評価が高い', 'レビューが良い', '口コミが良い', '好评', '好評', '评价高', '評價高', '评分高', '口碑好', 'best beoordeeld', 'hoogst beoordeeld', 'goede beoordelingen', 'goede reviews', '평점 높은', '리뷰 좋은', '후기 좋은', '별점 높은', 'высокий рейтинг', 'лучшие отзывы', 'хорошие отзывы', 'с высоким рейтингом', 'mejor valorada', 'mejor valoradas', 'mejores valorados', 'mejores valoradas', 'mas valorado', 'mas valorados', 'bien valorado', 'bien valorados', 'mejor puntuado', 'mejor puntuados', 'mejores puntuaciones', 'mejores resenas', 'mejor calificado', 'mejores calificaciones', 'تقييما', 'تقييمات', 'مراجعات', 'مراجعة', 'افضل تقييما');
    }

    private function popular_keywords() {
    // Spanish had the French 'tendance' but not its own 'tendencia', and Arabic
    // had only the مبيعا family. Bare 'طلبا' is deliberately absent -- طلب is
    // also an order, and "طلبي الأخير" is a shopper asking about their own
    // purchase, not for the best sellers.
        return array('popular', 'best selling', 'trending', 'most sold', 'مشہور', 'زیادہ فروخت', 'مقبول', 'الأكثر مبيعا', 'اكثر مبيعا', 'شائع', 'popular', 'populares', 'más vendido', 'mas vendido', 'mas vendidos', 'tendance', 'le plus vendu', 'meilleures ventes', 'beliebt', 'bestseller', 'meistverkauft', 'piu venduto', 'più venduto', 'i piu venduti', 'di tendenza', 'mais vendido', 'mais vendidos', 'em alta', 'queridinho', '人気', '売れ筋', 'ベストセラー', '定番', '热门', '熱門', '畅销', '暢銷', '爆款', '受欢迎', '人气', 'populair', 'populaire', 'meest verkocht', 'veel gekocht', '인기', '베스트셀러', '많이 팔린', '잘나가는', 'популярный', 'популярные', 'хит продаж', 'бестселлер', 'самый продаваемый', 'tendencia', 'tendencias', 'en tendencia', 'de moda', 'superventas', 'mas comprado', 'mas comprados', 'mas populares', 'شعبية', 'اكثر شعبية', 'رائج', 'رائجة', 'رواجا', 'اكثر طلبا', 'اكثر شراء');
    }

    private function under_keywords() {
        return array('under', 'below', 'less than', 'max', 'maximum', 'up to', 'at most', 'not above', 'no more than', 'کم', 'سے کم', 'نیچے', 'تک', 'اقل من', 'أقل من', 'دون', 'تحت', 'menos de', 'debajo de', 'hasta', 'moins de', 'sous', 'jusqu a', 'unter', 'bis', 'bis zu', 'hochstens', 'höchstens', 'nicht mehr als', 'sotto', 'sotto i', 'sotto ai', 'meno di', 'al massimo', 'massimo', 'fino a', 'entro', 'abaixo de', 'ate', 'até', 'no maximo', 'no máximo', 'menos de', 'ate r', '以下', '未満', '以内', 'まで', '不到', '不超过', '最多', 'onder', 'minder dan', 'tot', 'maximaal', 'hooguit', 'niet meer dan', '이하', '미만', '이내', '까지', 'до', 'меньше', 'менее', 'не больше', 'не более', 'дешевле');
    }

    private function over_keywords() {
        return array('over', 'above', 'more than', 'min', 'minimum', 'starting from', 'at least', 'not below', 'no less than', 'زیادہ', 'سے زیادہ', 'اوپر', 'اكثر من', 'أكثر من', 'فوق', 'mas de', 'más de', 'encima de', 'plus de', 'au dessus de', 'uber', 'über', 'mehr als', 'sopra', 'piu di', 'più di', 'oltre', 'almeno', 'a partire da', 'acima de', 'mais de', 'a partir de', 'no minimo', 'no mínimo', 'mindestens', 'ab', 'au moins', 'a partir de', 'à partir de', 'al menos', 'a partir de', '以上', '超', 'より上', '超过', '起', 'boven', 'meer dan', 'vanaf', 'minimaal', 'minstens', '이상', '초과', 'от', 'больше', 'более', 'не меньше', 'дороже');
    }

    private function between_keywords() {
        // 'de' is deliberately absent: it is the commonest preposition in
        // French, Spanish and Portuguese, so "chaussures de sport" would parse
        // as a price range. '〜' is absent because normalize_text() strips
        // punctuation before any of these are compared.
        return array('between', 'range', 'درمیان', 'کے درمیان', 'بين', 'entre', 'zwischen', 'tra', 'fra', 'tussen', 'arasi', 'arası', 'から', '到', '之间', '之間', 'tussen', '사이', 'между');
    }

    private function around_keywords() {
        return array('around', 'about', 'near', 'approximately', 'approx', 'roughly', 'تقريبا', 'تقریباً', 'لگ بھگ', 'قريب من', 'cerca de', 'alrededor de', 'environ', 'autour de', 'ungefahr', 'ungefähr', 'circa', 'intorno a', 'sui', 'yaklasik', 'yaklaşık', 'por volta de', 'em torno de', 'aproximadamente', 'etwa', 'rund', 'くらい', 'ぐらい', '前後', '程度', '左右', '大约', '大約', 'ongeveer', 'rond', 'omstreeks', '정도', '쯤', 'около', 'примерно', 'в районе');
    }

    private function budget_keywords() {
        return array('budget', 'budget of', 'within budget', 'max budget', 'price limit', 'my budget is', 'بجٹ', 'حد', 'ميزانية', 'ميزانيه', 'presupuesto', 'budget', 'budget maximum', 'preislimit', 'butce', 'bütçe', 'orcamento', 'orçamento', 'prijslimiet', 'бюджет', 'бюджетом', 'предел цены', '예산', '予算', '预算', '預算');
    }

    private function price_target_keywords() {
        return array('price', 'priced', 'priced at', 'cost', 'costing', 'cost of', 'rate', 'worth', 'قیمت', 'دام', 'سعر', 'السعر', 'precio', 'prix', 'preis', 'prezzo', 'preco', 'preço', 'fiyat', 'prijs', 'kosten', 'цена', 'цене', 'ценой', 'стоимость', 'стоимостью', '가격', '価格', '値段', '价格', '價格', '售价', '售價');
    }



    private function negative_terms_for_map($query, $map) {
        $terms = array();

        // Both patterns below embed the operator and the alias with preg_quote,
        // so neither can match unless that literal text is already somewhere in
        // the query. Testing that first turns the work from "compile a regex for
        // every alias times every operator" into "compile one for the handful
        // that could possibly match".
        //
        // This loop is the single most expensive thing in analyze_query(): the
        // colour map alone carries ~200 aliases and there are 22 operators, so
        // the unguarded form built and compiled ~9,000 patterns per query and
        // re-normalised each operator inside the innermost loop. Measured at
        // ~590ms per analysed query against ~10ms for a query with no negation
        // -- and analyze_query() runs on every search, cached or not.
        $operators = array();
        foreach ($this->negative_operator_keywords() as $operator) {
            $operator = $this->normalize_text($operator);
            if ($operator !== '' && strpos($query, $operator) !== false) {
                $operators[] = $operator;
            }
        }

        if (empty($operators)) {
            return $terms;
        }

        foreach ((array) $map as $canonical => $aliases) {
            foreach ((array) $aliases as $alias) {
                $alias = $this->normalize_text($alias);
                if ($alias === '' || strpos($query, $alias) === false) {
                    continue;
                }
                foreach ($operators as $operator) {
                    $patterns = array(
                        '/(?:^|\s)' . preg_quote($operator, '/') . '\s+(?:color\s+|colour\s+|size\s+|in\s+|with\s+)?' . preg_quote($alias, '/') . '(?=\s|$)/u',
                        '/(?:^|\s)' . preg_quote($alias, '/') . '\s+(?:' . preg_quote($operator, '/') . ')(?=\s|$)/u',
                    );

                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $query)) {
                            $terms[] = $canonical;
                            $terms = array_merge($terms, (array) $aliases);
                            break 2;
                        }
                    }
                }
            }
        }

        return $terms;
    }

    private function has_negative_facet_signal($query) {
        $query = $this->normalize_text($query);
        if ($query === '') {
            return false;
        }

        foreach ($this->negative_operator_keywords() as $operator) {
            $operator = $this->normalize_text($operator);
            if ($operator !== '' && $this->contains_phrase($query, $operator)) {
                return true;
            }
        }

        return false;
    }

    private function negative_operator_keywords() {
        return array(
            'not', 'no', 'without', 'except', 'excluding', 'exclude', 'avoid', 'not in', 'other than',
            'dont want', 'don t want', "don't want", 'do not want',
            'نہیں', 'بغیر', 'کے بغیر', 'نہ', 'غير', 'بدون', 'ليس', 'ليست', 'ماعدا', 'عدا',
            // Every language needs its plain negator, not only its "without":
            // "chaussures mais pas noires" and "schuhe aber nicht schwarz" were
            // read as ordinary colour requests and returned exactly what the
            // shopper had just excluded.
            'sin', 'no', 'pero no', 'excepto', 'salvo', 'menos',
            'sans', 'pas', 'ne pas', 'mais pas', 'sauf', 'hormis',
            'ohne', 'nicht', 'kein', 'keine', 'keinen', 'aber nicht', 'ausser', 'außer',
            'senza', 'non', 'ma non', 'tranne', 'eccetto',
            'sem', 'nao', 'não', 'mas nao', 'mas não', 'exceto', 'menos',
            'じゃない', 'ではない', '以外', 'なし', '抜き',
            '不要', '不是', '不', '除了', '没有', '沒有',
            'zonder', 'niet', 'geen', 'behalve', 'maar niet',
            '말고', '빼고', '제외', '아닌', '없는',
            'без', 'не', 'кроме', 'исключая', 'но не',
        );
    }

    private function buyer_preference_keyword_map() {
        $map = array(
            'comfortable' => array('comfortable', 'comfartable', 'comfertable', 'comfy', 'comfort', 'soft', 'cushioned', 'padded', 'easy to wear', 'relaxed fit', 'walking comfort'),
            'formal' => array('formal', 'office', 'work', 'business', 'professional', 'dressy'),
            'casual' => array('casual', 'daily wear', 'everyday', 'regular wear', 'normal wear'),
            'premium' => array('premium', 'luxury', 'expensive', 'costly', 'pricey', 'high end', 'high-end', 'higher end', 'high quality', 'best quality', 'better quality'),
            'budget' => array('budget friendly', 'budget-friendly', 'affordable', 'cheap', 'low price', 'low priced', 'economical', 'not expensive', 'not too expensive', 'not costly', 'not too costly', 'reasonable price', 'good price', 'value for money'),
            'gift' => array('gift', 'present', 'gift for brother', 'for my brother', 'for brother', 'brother', 'for sister', 'sister', 'for friend', 'friend', 'for husband', 'husband', 'for wife', 'wife'),
            'popular' => array('popular', 'best selling', 'trending', 'most sold', 'customer favorite', 'customer favourite'),
            'useful' => array('useful', 'practical', 'handy', 'everyday useful'),
            'simple' => array('simple', 'minimal', 'minimalist', 'plain', 'clean style', 'basic'),
            'quality' => array('good', 'nice', 'good quality', 'well made', 'durable'),
            'value' => array('best value', 'good value', 'balanced choice', 'safe choice', 'safest choice', 'worth buying', 'not the cheapest', 'not cheapest'),
        );

        return (array) apply_filters('geekybot_search_buyer_preference_keywords', $map);
    }

    private function buyer_use_case_keyword_map() {
        $map = array(
            'winter' => array('winter', 'cold weather', 'warm', 'warmer', 'cozy', 'cosy'),
            'summer' => array('summer', 'hot weather', 'lightweight', 'breathable'),
            'sports' => array('sports', 'sport', 'gym', 'training', 'workout', 'running', 'walking', 'walk', 'jogging'),
            'travel' => array('travel', 'trip', 'journey', 'outdoor'),
            'party' => array('party', 'event', 'wedding', 'occasion'),
            'school' => array('school', 'college', 'university', 'student'),
        );

        return (array) apply_filters('geekybot_search_buyer_use_case_keywords', $map);
    }

    private function color_keyword_map() {
        $map = array(
            'black' => array('black', 'jet black', 'کالا', 'کالی', 'سیاہ', 'اسود', 'أسود', 'سوداء', 'الاسود', 'الأسود', 'السوداء', 'noir', 'noire', 'noirs', 'noires', 'negro', 'negra', 'negros', 'negras', 'schwarz', 'schwarze', 'schwarzer', 'nero', 'nera', 'neri', 'nere', 'preto', 'preta', 'pretos', 'pretas', 'zwart', 'zwarte', '黒', '黒い', 'ブラック', '黑', '黑色', '검정', '검은', '블랙', 'черный', 'чёрный', 'черная', 'черные', 'чёрные'),
            'white' => array('white', 'off white', 'off-white', 'سفید', 'ابيض', 'أبيض', 'بيضاء', 'الابيض', 'الأبيض', 'البيضاء', 'blanc', 'blanche', 'blancs', 'blanches', 'blanco', 'blanca', 'blancos', 'blancas', 'weiss', 'weiß', 'weisse', 'weisser', 'bianco', 'bianca', 'bianchi', 'bianche', 'branco', 'branca', 'brancos', 'brancas', 'wit', 'witte', '白', '白い', 'ホワイト', '白色', '흰색', '하얀', '화이트', 'белый', 'белая', 'белые'),
            'blue' => array('blue', 'navy', 'navy blue', 'sky blue', 'dark blue', 'نیلا', 'نیلی', 'ازرق', 'أزرق', 'زرقاء', 'الازرق', 'الأزرق', 'الزرقاء', 'كحلي', 'كحلية', 'bleu', 'bleue', 'bleus', 'bleues', 'azul', 'azules', 'blau', 'blaue', 'blauer', 'blu', 'azzurro', 'azzurra', 'blauw', 'blauwe', '青', '青い', 'ブルー', '紺', 'ネイビー', '蓝', '蓝色', '藍', '藍色', '深蓝', '파란', '파랑', '블루', '남색', 'синий', 'синяя', 'синие', 'голубой'),
            'green' => array('green', 'olive', 'dark green', 'سبز', 'ہرا', 'اخضر', 'أخضر', 'خضراء', 'الاخضر', 'الأخضر', 'الخضراء', 'زيتي', 'vert', 'verte', 'verts', 'vertes', 'verde', 'verdes', 'grun', 'grün', 'grune', 'grüne', 'groen', 'groene', '緑', 'グリーン', '绿', '绿色', '綠', '綠色', '초록', '녹색', '그린', 'зеленый', 'зелёный', 'зеленая', 'зеленые'),
            'red' => array('red', 'maroon', 'burgundy', 'سرخ', 'لال', 'احمر', 'أحمر', 'حمراء', 'الاحمر', 'الأحمر', 'الحمراء', 'عنابي', 'rouge', 'rojo', 'roja', 'rojos', 'rojas', 'rot', 'rote', 'roter', 'rosso', 'rossa', 'rossi', 'vermelho', 'vermelha', 'rood', 'rode', '赤', '赤い', 'レッド', '红', '红色', '紅', '紅色', '빨간', '빨강', '레드', 'красный', 'красная', 'красные'),
            'yellow' => array('yellow', 'mustard', 'پیلا', 'پیلے', 'اصفر', 'أصفر', 'صفراء', 'الاصفر', 'الأصفر', 'الصفراء', 'jaune', 'amarillo', 'amarilla', 'gelb', 'gelbe', 'giallo', 'gialla', 'amarelo', 'amarela', 'geel', 'gele', '黄', '黄色', 'イエロー', '黃色', '노란', '노랑', '옐로우', 'желтый', 'жёлтый', 'желтая'),
            'pink' => array('pink', 'rose', 'گلابی', 'وردي', 'وردية', 'الوردي', 'زهري', 'زهرية', 'rose', 'rosa', 'rosado', 'rosada', 'cor de rosa', 'roze', 'ピンク', '粉', '粉色', '粉红', '粉紅', '분홍', '핑크', 'розовый', 'розовая'),
            'purple' => array('purple', 'violet', 'جامنی', 'بنفسجي', 'بنفسجية', 'البنفسجي', 'موف', 'violet', 'violette', 'morado', 'morada', 'lila', 'viola', 'roxo', 'roxa', 'paars', 'paarse', '紫', 'パープル', '紫色', '보라', '퍼플', 'фиолетовый', 'сиреневый'),
            'orange' => array('orange', 'نارنجی', 'برتقالي', 'برتقالية', 'البرتقالي', 'naranja', 'arancione', 'laranja', 'oranje', 'オレンジ', '橙', '橙色', '橘色', '주황', '오렌지', 'оранжевый'),
            'brown' => array('brown', 'tan', 'camel', 'بھورا', 'براون', 'بني', 'بنية', 'البني', 'marron', 'marrons', 'marrón', 'marrone', 'marroni', 'braun', 'braune', 'castanho', 'castanha', 'marrom', 'bruin', 'bruine', '茶', '茶色', 'ブラウン', '棕', '棕色', '咖啡色', '갈색', '브라운', 'коричневый', 'коричневая'),
            'gray' => array('gray', 'grey', 'charcoal', 'slate', 'سرمئی', 'گرے', 'رمادي', 'رمادية', 'الرمادي', 'رصاصي', 'رصاصية', 'gris', 'grise', 'grises', 'grau', 'graue', 'grigio', 'grigia', 'cinza', 'cinzento', 'grijs', 'grijze', 'グレー', '灰', '灰色', '회색', '그레이', 'серый', 'серая', 'серые'),
            'beige' => array('beige', 'cream', 'ivory', 'کریم', 'بيج', 'البيج', 'كريمي', 'كريمية', 'crema', 'creme', 'bege', 'marfim', 'beige', 'gebroken wit', 'ベージュ', '米色', '米白', '베이지', 'бежевый'),
            'gold' => array('gold', 'golden', 'سنہری', 'ذهبي', 'ذهبية', 'الذهبي', 'oro', 'dore', 'doré', 'dorado', 'dourado', 'goud', 'gouden', 'ゴールド', '金', '金色', '금색', '골드', 'золотой', 'золотистый'),
            'silver' => array('silver', 'چاندی', 'فضي', 'فضية', 'الفضي', 'argent', 'argente', 'plata', 'plateado', 'silber', 'argento', 'prata', 'prateado', 'zilver', 'zilveren', 'シルバー', '銀', '银色', '銀色', '은색', '실버', 'серебряный', 'серебристый'),
            'multi' => array('multi', 'multicolor', 'multi color', 'multicolour', 'mixed color', 'رنگ برنگا', 'متعدد الالوان', 'متعددة الالوان', 'متعدد الألوان', 'ملون', 'ملونة', 'multicolore', 'multicolor', 'bunt', 'colorato', 'colorido', 'veelkleurig', 'bont', 'マルチカラー', '多色', '彩色', '멀티', '여러 색', 'разноцветный', 'многоцветный'),
        );

        /**
         * Filters known color aliases used for product-search facets.
         *
         * @param array $map Canonical color => aliases.
         */
        return (array) apply_filters('geekybot_search_color_keywords', $map);
    }

    private function size_keyword_map() {
        // Every non-English entry is deliberately the QUALIFIED wording -- مقاس كبير
        // rather than bare كبير, "taille grande" rather than bare "grande". A size
        // alias becomes a hard filter, and the words for physically small and
        // large are ordinary adjectives in every one of these languages: "حقيبة
        // كبيرة", "grande borsa", "bolsa grande" and "große Tasche" all describe a
        // big bag, and each would otherwise be filtered to garment size L and
        // return nothing. The CJK entries qualify the same way, through サイズ, 码
        // and 号, which only ever mean a size code. The size regex above already
        // reads a Latin size code after any of those words; these cover the case
        // where the size itself is spelled as a word.
        $map = array(
            'xxxs' => array('xxxs', '3xs', 'extra extra extra small'),
            'xxs' => array('xxs', '2xs', 'extra extra small'),
            'xs' => array('xs', 'extra small', 'x small', 'مقاس صغير جدا', 'صغير جدا', 'صغيرة جدا', 'taille tres petite', 'talla muy pequena', 'taglia molto piccola', 'tamanho muito pequeno', 'extra kleine maat', 'maat extra klein', 'grosse sehr klein', 'größe sehr klein', 'サイズ極小', '極小サイズ', '超小码', '超小碼', '엑스스몰 사이즈', '아주 작은 사이즈', 'размер xs', 'очень маленький размер'),
            's' => array('s', 'small', 'sm', 'مقاس صغير', 'مقاس اس', 'taille petite', 'petite taille', 'talla pequena', 'talla pequeña', 'taglia piccola', 'tamanho pequeno', 'grosse klein', 'größe klein', 'maat klein', 'サイズ小', '小さいサイズ', '小码', '小碼', '小号', '스몰 사이즈', '작은 사이즈', 'размер s', 'маленький размер'),
            'm' => array('m', 'medium', 'med', 'مقاس متوسط', 'مقاس وسط', 'مقاس ام', 'taille moyenne', 'talla mediana', 'talla media', 'taglia media', 'tamanho medio', 'tamanho médio', 'grosse mittel', 'größe mittel', 'maat middel', 'サイズ中', '中码', '中碼', '中号', '미디엄 사이즈', '보통 사이즈', 'размер m', 'средний размер'),
            'l' => array('l', 'large', 'lg', 'مقاس كبير', 'مقاس لارج', 'مقاس ال', 'taille grande', 'grande taille', 'talla grande', 'taglia grande', 'tamanho grande', 'grosse gross', 'größe gross', 'maat groot', 'grote maat', 'サイズ大', '大きいサイズ', '大码', '大碼', '大号', '라지 사이즈', '큰 사이즈', 'размер l', 'большой размер'),
            'xl' => array('xl', 'extra large', 'x large', 'مقاس كبير جدا', 'كبير جدا', 'كبيرة جدا', 'اكس ال', 'taille tres grande', 'talla muy grande', 'taglia molto grande', 'tamanho muito grande', 'extra grote maat', 'maat extra groot', 'ubergrosse', 'übergröße', 'grosse sehr gross', 'größe sehr gross', 'サイズ特大', '特大サイズ', '加大码', '加大碼', '特大号', '特大號', '엑스라지 사이즈', '엑스엘 사이즈', '아주 큰 사이즈', 'размер xl', 'очень большой размер'),
            'xxl' => array('xxl', '2xl', 'double xl', 'extra extra large', 'دبل اكس ال', 'super grote maat', '超特大サイズ', '加加大码', '加加大碼', '超大码', '超大碼', '투엑스라지 사이즈', '더블엑스라지 사이즈', 'размер xxl'),
            'xxxl' => array('xxxl', '3xl', 'triple xl', 'extra extra extra large'),
            'one size' => array('one size', 'onesize', 'free size', 'single size', 'مقاس واحد', 'مقاس موحد', 'مقاس حر', 'taille unique', 'talla unica', 'talla única', 'taglia unica', 'tamanho unico', 'tamanho único', 'einheitsgrosse', 'einheitsgröße', 'een maat', 'フリーサイズ', '均码', '均碼', '프리사이즈', '원사이즈', 'один размер', 'универсальный размер'),
        );

        /**
         * Filters known size aliases used for product-search facets.
         *
         * @param array $map Canonical size => aliases.
         */
        return (array) apply_filters('geekybot_search_size_keywords', $map);
    }

    /**
     * Whether one size alias already accounts for another.
     *
     * Substring covers the scripts written without spaces, where the shorter
     * alias sits literally inside the longer one (大码 inside 加大码). Word
     * containment covers the rest, because the word that stop-word removal took
     * out of the middle leaves no substring behind: "taille tres grande" reaches
     * the term list as "taille grande", which is not a substring of it but is
     * every one of its words.
     *
     * @param string $alias Alias matched in the query text.
     * @param string $other Shorter alias matched only in the term list.
     * @return bool
     */
    private function size_alias_covers($alias, $other) {
        if (strpos($alias, $other) !== false) {
            return true;
        }

        $alias_words = preg_split('/\s+/u', $alias, -1, PREG_SPLIT_NO_EMPTY);
        $other_words = preg_split('/\s+/u', $other, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($alias_words) || !is_array($other_words) || count($other_words) < 2) {
            return false;
        }

        return count(array_diff($other_words, $alias_words)) === 0;
    }

    /**
     * Match size aliases longest-first, consuming each one as it is found.
     *
     * Korean, Chinese and Japanese are matched by substring, because they are
     * written without spaces -- see contains_phrase(). The word for a smaller
     * size is therefore found inside the word for a larger one: 엑스라지 사이즈
     * ("extra large") contains 라지 사이즈 ("large"), 加大码 contains 大码, and
     * 超特大サイズ contains 特大サイズ. Stopping at the first alias that hits
     * answered with the smaller size, and a size is a HARD filter -- so the
     * shopper who asked for XL was filtered to L and shown nothing else. It is
     * the same failure the Latin size regex had with "muslin", in scripts where
     * a word boundary cannot be written.
     *
     * Blanking each match before looking for the next is what keeps "large and
     * extra large" asking for both sizes: the shorter word is still there once
     * the longer phrase has been taken out, and only then does it count.
     *
     * @param string $text     Query text, or the joined term list.
     * @param array  $map      Canonical size => aliases.
     * @param bool   $as_tokens Match whole space-delimited tokens only.
     * @return array Canonical size => the alias that matched it.
     */
    private function longest_size_matches($text, $map, $as_tokens) {
        $aliases = array();
        foreach ($map as $canonical => $words) {
            foreach ((array) $words as $word) {
                $word = $this->normalize_text($word);
                if ($word !== '') {
                    $aliases[] = array($word, $canonical);
                }
            }
        }
        usort($aliases, function ($a, $b) {
            return $this->token_length($b[0]) - $this->token_length($a[0]);
        });

        $matched = array();
        foreach ($aliases as $entry) {
            list($alias, $canonical) = $entry;
            if (isset($matched[$canonical])) {
                continue;
            }
            $hit = $as_tokens
                ? strpos($text, ' ' . $alias . ' ') !== false
                : $this->contains_phrase($text, $alias);
            if (!$hit) {
                continue;
            }
            $matched[$canonical] = $alias;
            // Take the phrase out so a shorter alias cannot claim its letters.
            $text = str_replace($alias, ' ', $text);
        }

        return $matched;
    }

    private function normalize_facet_term_list($terms) {
        $clean = array();
        foreach ((array) $terms as $term) {
            $term = $this->normalize_text($term);
            if ($term !== '') {
                $clean[] = $term;
            }
        }
        return array_values(array_unique($clean));
    }

    private function contains_any_phrase($text, $phrases) {
        foreach ((array) $phrases as $phrase) {
            if ($this->contains_phrase($text, $phrase)) {
                return true;
            }
        }
        return false;
    }

    private function contains_phrase($text, $phrase) {
        $normalized_text = $this->normalize_text($text);
        $phrase = $this->normalize_text($phrase);
        if ($normalized_text === '' || $phrase === '') {
            return false;
        }

        // Exact token/phrase matching is important for commerce facets.
        // A single-letter size like "s" must not match words such as "sale"
        // or "discounted". Multi-word phrases still match when the full phrase
        // is present in normalized text.
        if (strpos(' ' . $normalized_text . ' ', ' ' . $phrase . ' ') !== false) {
            return true;
        }

        // Arabic joins the definite article to the word, and this is token
        // matching, so الرخيص is not رخيص and every phrase-level signal was lost
        // the moment a shopper wrote naturally: "الحذاء الرخيص" set no budget
        // sort, "التخفيضات" was not a sale, "المتوفر" was not a stock request.
        // Retrying against a clitic-stripped copy of the text fixes all of them
        // at once, because every one of those signals arrives through here.
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $normalized_text)) {
            $stripped = $this->strip_arabic_clitic_text($normalized_text);
            if ($stripped !== $normalized_text
                && strpos(' ' . $stripped . ' ', ' ' . $phrase . ' ') !== false) {
                return true;
            }
        }

        // CJK languages often do not use spaces. Allow substring matching only
        // when the phrase itself is CJK text.
        if (preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $phrase)) {
            return strpos($normalized_text, $phrase) !== false;
        }

        return false;
    }

    /**
     * Undo the Persian/Urdu letterforms that normalisation introduced, for text
     * that will be shown to a shopper.
     *
     * normalize_text() folds Arabic ي to ی and ك to ک because it makes matching
     * work across the whole Arabic script family. That is invisible until a
     * normalised string is quoted back at the reader: the spelling-recovery
     * message told an Arabic shopper it had searched for "قمیص", which to them
     * is simply misspelled -- ی and ک are not Arabic letters.
     *
     * Only for Arabic. In Persian and Urdu those letterforms are the correct
     * ones and reversing them would introduce the error rather than fix it.
     *
     * @param string $text     Normalised text about to be displayed.
     * @param string $language Detected language code for the shopper.
     * @return string
     */
    public function restore_arabic_letterforms($text, $language = '') {
        $text = (string) $text;
        if ($language !== 'ar' || $text === '') {
            return $text;
        }

        return strtr($text, array('ی' => 'ي', 'ک' => 'ك'));
    }

    /**
     * The same text with any joined Arabic article or particle removed.
     *
     * Public because BuyerIntentLibrary needs the identical reduction: its pack
     * phrases and the query it matches them against have to be stripped by the
     * same function, or الحذاء المريح stops matching the comfort rule that
     * حذاء مريح matches. Short function words are protected by the length floor
     * in strip_arabic_clitics(), so التي survives intact.
     *
     * Memoised: contains_phrase() runs across hundreds of aliases for a single
     * query, and the stripped form of one query never changes.
     *
     * @param string $text Normalised text.
     * @return string
     */
    public function strip_arabic_clitic_text($text) {
        $text = (string) $text;
        if (isset($this->arabic_stripped_cache[$text])) {
            return $this->arabic_stripped_cache[$text];
        }

        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stripped = array();
        foreach ((array) $tokens as $token) {
            $stripped[] = preg_match('/[\x{0600}-\x{06FF}]/u', $token)
                ? $this->stemmer()->strip_arabic_clitics($token)
                : $token;
        }

        $result = implode(' ', $stripped);
        if (count($this->arabic_stripped_cache) > 200) {
            $this->arabic_stripped_cache = array();
        }
        $this->arabic_stripped_cache[$text] = $result;

        return $result;
    }

    private function keyword_pattern($keywords) {
        $keywords = (array) $keywords;
        $cache_key = md5(serialize($keywords));
        if (isset($this->keyword_pattern_cache[$cache_key])) {
            return $this->keyword_pattern_cache[$cache_key];
        }

        $quoted = array();
        foreach ($keywords as $keyword) {
            $keyword = $this->normalize_text($keyword);
            if ($keyword !== '') {
                $quoted[] = preg_quote($keyword, '/');
            }
        }
        usort($quoted, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $pattern = implode('|', $quoted);
        $this->keyword_pattern_cache[$cache_key] = $pattern;
        return $pattern;
    }

    private function parse_price_number($value) {
        return (float) str_replace(',', '', (string) $value);
    }

    /**
     * Shared stemmer instance, used for the Arabic clitic rules.
     *
     * @return StemmerService
     */
    private function stemmer() {
        if ($this->stemmer === null) {
            $this->stemmer = new StemmerService();
        }

        return $this->stemmer;
    }

    private function normalize_search_token($token, $language = 'en') {
        $token = $this->normalize_text($token);
        if ($token === '' || preg_match('/^\d+(?:\.\d+)?$/', $token)) {
            return $token;
        }

        // Arabic joins the definite article and its particles to the front of the
        // word, so الحذاء and للسفر are single tokens. Reduce them to the word the
        // shopper meant, or every downstream comparison is against a token the
        // catalog does not contain: الحذاء الأسود returned nothing at all while
        // حذاء أسود found the shoe.
        if ($language === 'ar' && preg_match('/[\x{0600}-\x{06FF}]/u', $token)) {
            return $this->stemmer()->strip_arabic_clitics($token);
        }

        // Russian writes its grammar onto the end of the word instead, and the
        // boolean this term ends up in is a PREFIX wildcard -- so the term has
        // to be the shortest form, not the shopper's. `рюкзаков*` matched
        // nothing at all, where `рюкзак*` matches every case of the word in the
        // raw catalog text as well as in stem_text.
        if ($language === 'ru' && preg_match('/[\x{0400}-\x{04FF}]/u', $token)) {
            return $this->stemmer()->stem($token);
        }

        // Lightweight English/Latin plural handling. This turns "belts" into "belt"
        // and "shoes" into "shoe" so FULLTEXT/LIKE can match singular catalog titles.
        if (preg_match('/^[a-z][a-z0-9_-]{2,}$/', $token)) {
            $irregular = array(
                'hoodies' => 'hoodie',
                'hoddies' => 'hoodie',
                'hoddie' => 'hoodie',
                'hoody' => 'hoodie',
            );
            if (isset($irregular[$token])) {
                return $irregular[$token];
            }
            if (preg_match('/ies$/', $token) && strlen($token) > 4) {
                return substr($token, 0, -3) . 'y';
            }
            if (preg_match('/(xes|ches|shes|sses|zes)$/', $token) && strlen($token) > 4) {
                return substr($token, 0, -2);
            }
            if (preg_match('/s$/', $token) && !preg_match('/(ss|us)$/', $token) && strlen($token) > 3) {
                return substr($token, 0, -1);
            }
        }

        return $token;
    }

    private function token_length($text) {
        return function_exists('mb_strlen') ? mb_strlen((string) $text, 'UTF-8') : strlen((string) $text);
    }

    private function normalize_arabic_family_chars($text) {
        $text = (string) $text;

        // Nothing below applies to text with no Arabic-script characters, and
        // this runs on every indexed field of every product.
        if (!preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
            return $text;
        }

        // Presentation forms first: legacy systems and some copy-paste produce
        // the isolated/initial/medial/final shape of a letter rather than the
        // letter itself, and ﺣﺬﺍﺀ is not حذاء to any string comparison. These
        // expand to base letters, which the folding below then normalises --
        // some expand to a letter plus a tatweel or a diacritic, both of which
        // are stripped after. Generated from the Unicode NFKC decomposition of
        // the Arabic Presentation Forms-B block; PHP's intl extension, which
        // would do this in one call, is not guaranteed to be installed.
        if (preg_match('/[\x{FE70}-\x{FEFF}]/u', $text)) {
            $text = strtr($text, self::arabic_presentation_forms());
        }

        $map = array(
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ی', 'ي' => 'ی', 'ئ' => 'ی',
            'ك' => 'ک',
            'ة' => 'ه',
            'ؤ' => 'و',
            'َ' => '', 'ً' => '', 'ُ' => '', 'ٌ' => '', 'ِ' => '', 'ٍ' => '', 'ْ' => '', 'ّ' => '',

            // Tatweel is a decorative elongation with no meaning: حــذاء is the
            // same word as حذاء, but not the same token, so it has to go.
            'ـ' => '',

            // Arabic-Indic and Persian digits. An Arabic keyboard produces these
            // by default in much of the region, and every numeric feature reads
            // ASCII -- "حذاء أقل من ١٠٠" parsed no price at all and "مقاس ٤٢" no
            // size, while their ASCII twins worked.
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        );

        return strtr($text, $map);
    }

    /**
     * Arabic Presentation Forms-B mapped to their base letters.
     *
     * @return array<string, string>
     */
    private static function arabic_presentation_forms() {
        static $map = null;
        if ($map === null) {
            $map = array(
            'ﹰ' => ' ً', 'ﹱ' => 'ـً', 'ﹲ' => ' ٌ', 'ﹴ' => ' ٍ', 'ﹶ' => ' َ', 'ﹷ' => 'ـَ', 'ﹸ' => ' ُ',
            'ﹹ' => 'ـُ', 'ﹺ' => ' ِ', 'ﹻ' => 'ـِ', 'ﹼ' => ' ّ', 'ﹽ' => 'ـّ', 'ﹾ' => ' ْ', 'ﹿ' => 'ـْ',
            'ﺀ' => 'ء', 'ﺁ' => 'آ', 'ﺂ' => 'آ', 'ﺃ' => 'أ', 'ﺄ' => 'أ', 'ﺅ' => 'ؤ', 'ﺆ' => 'ؤ', 'ﺇ' => 'إ',
            'ﺈ' => 'إ', 'ﺉ' => 'ئ', 'ﺊ' => 'ئ', 'ﺋ' => 'ئ', 'ﺌ' => 'ئ', 'ﺍ' => 'ا', 'ﺎ' => 'ا', 'ﺏ' => 'ب',
            'ﺐ' => 'ب', 'ﺑ' => 'ب', 'ﺒ' => 'ب', 'ﺓ' => 'ة', 'ﺔ' => 'ة', 'ﺕ' => 'ت', 'ﺖ' => 'ت', 'ﺗ' => 'ت',
            'ﺘ' => 'ت', 'ﺙ' => 'ث', 'ﺚ' => 'ث', 'ﺛ' => 'ث', 'ﺜ' => 'ث', 'ﺝ' => 'ج', 'ﺞ' => 'ج', 'ﺟ' => 'ج',
            'ﺠ' => 'ج', 'ﺡ' => 'ح', 'ﺢ' => 'ح', 'ﺣ' => 'ح', 'ﺤ' => 'ح', 'ﺥ' => 'خ', 'ﺦ' => 'خ', 'ﺧ' => 'خ',
            'ﺨ' => 'خ', 'ﺩ' => 'د', 'ﺪ' => 'د', 'ﺫ' => 'ذ', 'ﺬ' => 'ذ', 'ﺭ' => 'ر', 'ﺮ' => 'ر', 'ﺯ' => 'ز',
            'ﺰ' => 'ز', 'ﺱ' => 'س', 'ﺲ' => 'س', 'ﺳ' => 'س', 'ﺴ' => 'س', 'ﺵ' => 'ش', 'ﺶ' => 'ش', 'ﺷ' => 'ش',
            'ﺸ' => 'ش', 'ﺹ' => 'ص', 'ﺺ' => 'ص', 'ﺻ' => 'ص', 'ﺼ' => 'ص', 'ﺽ' => 'ض', 'ﺾ' => 'ض', 'ﺿ' => 'ض',
            'ﻀ' => 'ض', 'ﻁ' => 'ط', 'ﻂ' => 'ط', 'ﻃ' => 'ط', 'ﻄ' => 'ط', 'ﻅ' => 'ظ', 'ﻆ' => 'ظ', 'ﻇ' => 'ظ',
            'ﻈ' => 'ظ', 'ﻉ' => 'ع', 'ﻊ' => 'ع', 'ﻋ' => 'ع', 'ﻌ' => 'ع', 'ﻍ' => 'غ', 'ﻎ' => 'غ', 'ﻏ' => 'غ',
            'ﻐ' => 'غ', 'ﻑ' => 'ف', 'ﻒ' => 'ف', 'ﻓ' => 'ف', 'ﻔ' => 'ف', 'ﻕ' => 'ق', 'ﻖ' => 'ق', 'ﻗ' => 'ق',
            'ﻘ' => 'ق', 'ﻙ' => 'ك', 'ﻚ' => 'ك', 'ﻛ' => 'ك', 'ﻜ' => 'ك', 'ﻝ' => 'ل', 'ﻞ' => 'ل', 'ﻟ' => 'ل',
            'ﻠ' => 'ل', 'ﻡ' => 'م', 'ﻢ' => 'م', 'ﻣ' => 'م', 'ﻤ' => 'م', 'ﻥ' => 'ن', 'ﻦ' => 'ن', 'ﻧ' => 'ن',
            'ﻨ' => 'ن', 'ﻩ' => 'ه', 'ﻪ' => 'ه', 'ﻫ' => 'ه', 'ﻬ' => 'ه', 'ﻭ' => 'و', 'ﻮ' => 'و', 'ﻯ' => 'ى',
            'ﻰ' => 'ى', 'ﻱ' => 'ي', 'ﻲ' => 'ي', 'ﻳ' => 'ي', 'ﻴ' => 'ي', 'ﻵ' => 'لآ', 'ﻶ' => 'لآ',
            'ﻷ' => 'لأ', 'ﻸ' => 'لأ', 'ﻹ' => 'لإ', 'ﻺ' => 'لإ', 'ﻻ' => 'لا', 'ﻼ' => 'لا',
            );
        }

        return $map;
    }
}
