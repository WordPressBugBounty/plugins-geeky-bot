<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieves and answers from the selected, sanitized policy-page index.
 */
class KnowledgeService {
    private $index;
    private $intents;

    public function __construct(?KnowledgeIndexService $index = null, ?PolicyIntentService $intents = null) {
        $this->index = $index ?: new KnowledgeIndexService();
        $this->intents = $intents ?: new PolicyIntentService();
    }

    /**
     * @return array
     */
    public function selected_pages() {
        return $this->index->indexed_pages();
    }

    /**
     * @return array
     */
    public function index_status() {
        return $this->index->status();
    }

    /**
     * @param string $message Shopper message.
     * @return bool
     */
    public function is_policy_question($message) {
        $analysis = $this->intents->analyze($message);

        return !empty($analysis['isStorePolicyQuestion']);
    }

    /**
     * Resolve a policy question to grounded source excerpts.
     *
     * @param string $message Shopper message.
     * @param int    $limit Maximum sources.
     * @return array
     */
    public function resolve_policy($message, $limit = 2) {
        $analysis = $this->intents->analyze($message);
        if (empty($analysis['isStorePolicyQuestion'])) {
            return $this->empty_resolution($analysis);
        }

        $types = $this->source_types_for_analysis($analysis);
        $pages = $this->index->indexed_pages($types);

        return $this->resolve_from_pages($message, $pages, $limit, $analysis);
    }

    /**
     * Resolve a policy question against an explicit set of already-sanitized
     * pages. This keeps source scoring independently testable and is also used
     * by the WP-CLI regression fixtures.
     *
     * @param string $message Shopper message.
     * @param array  $pages Indexed-style pages.
     * @param int    $limit Maximum sources.
     * @param array  $analysis Optional precomputed intent analysis.
     * @return array
     */
    public function resolve_from_pages($message, $pages, $limit = 2, $analysis = array()) {
        $analysis = !empty($analysis) && is_array($analysis)
            ? $analysis
            : $this->intents->analyze($message);
        $result = $this->empty_resolution($analysis);

        if (empty($analysis['isStorePolicyQuestion'])) {
            return $result;
        }

        $result['isPolicyQuestion'] = true;
        $primary_type = !empty($analysis['primaryType']) ? sanitize_key((string) $analysis['primaryType']) : '';
        $pages = array_values(array_filter((array) $pages, function ($page) use ($primary_type) {
            $page_types = !empty($page['types']) ? array_map('sanitize_key', (array) $page['types']) : array();
            return $primary_type !== '' && in_array($primary_type, $page_types, true);
        }));

        if (empty($pages)) {
            $result['reason'] = 'source_missing';
            return $result;
        }

        $rows = array();
        foreach ($pages as $page) {
            $excerpt = $this->best_excerpt((string) ($page['content'] ?? ''), $analysis);
            $page_score = $this->page_score($page, $analysis) + $excerpt['score'];
            $row = $page;
            $row['score'] = $page_score;
            $row['excerpt'] = $excerpt['text'];
            $row['evidence'] = array(
                'specificTerms' => $excerpt['matchedSpecificTerms'],
                'facets' => $excerpt['matchedFacets'],
                'answerCues' => $excerpt['matchedAnswerCues'],
                'policyTypes' => $excerpt['matchedPolicyTypes'],
            );
            $row['answerable'] = $this->is_answerable_excerpt($excerpt, $analysis, $page);
            $rows[] = $row;
        }

        usort($rows, function ($a, $b) {
            return (int) $b['score'] <=> (int) $a['score'];
        });

        $limit = max(1, min(4, absint($limit)));
        $result['sources'] = array_slice($rows, 0, $limit);
        $result['matches'] = array_values(array_filter($rows, function ($row) {
            return !empty($row['answerable']);
        }));
        $result['matches'] = array_slice($result['matches'], 0, $limit);

        if (!empty($result['matches'])) {
            $result['answerable'] = true;
            $result['reason'] = 'answered';
        } else {
            $result['reason'] = 'detail_missing';
        }

        return $result;
    }

    /**
     * Backwards-compatible relevant-page lookup.
     *
     * @param string $message Shopper message.
     * @param int    $limit Maximum sources.
     * @return array
     */
    public function find_relevant($message, $limit = 2) {
        $resolution = $this->resolve_policy($message, $limit);

        return !empty($resolution['matches']) ? $resolution['matches'] : array();
    }

    /**
     * Build a deterministic shopper-facing answer from source text.
     *
     * @param string $message Shopper message.
     * @param array  $matches Optional pre-resolved matches.
     * @return string
     */
    public function local_policy_answer($message, $matches = array()) {
        if (empty($matches)) {
            $resolution = $this->resolve_policy($message, 1);
            $matches = !empty($resolution['matches']) ? $resolution['matches'] : array();
        }

        if (empty($matches[0]['excerpt'])) {
            return '';
        }

        $page = $matches[0];

        return sprintf(
            /* translators: 1: policy page title, 2: exact grounded excerpt. */
            __('According to the store’s “%1$s” page: %2$s', 'geeky-bot'),
            wp_strip_all_tags((string) $page['title']),
            wp_strip_all_tags((string) $page['excerpt'])
        );
    }

    /**
     * Safe fallback for a policy question that the selected sources do not
     * clearly answer.
     *
     * @param array $resolution Policy resolution.
     * @return string
     */
    public function missing_policy_answer($resolution) {
        $analysis = !empty($resolution['analysis']) && is_array($resolution['analysis'])
            ? $resolution['analysis']
            : array();
        $label = !empty($analysis['label'])
            ? wp_strip_all_tags((string) $analysis['label'])
            : __('store policy', 'geeky-bot');

        if (!empty($resolution['reason']) && $resolution['reason'] === 'source_missing') {
            return sprintf(
                /* translators: %s: policy area such as shipping and delivery. */
                __("I couldn't find this information in the store’s published %s pages. Please contact the store for confirmation.", 'geeky-bot'),
                $label
            );
        }

        return sprintf(
            /* translators: %s: policy area such as returns. */
            __("I couldn't find that detail in the store’s published %s information. Please review the page below or contact the store for confirmation.", 'geeky-bot'),
            $label
        );
    }

    /**
     * @param array $matches Grounded matches.
     * @return string
     */
    public function context_for_ai($matches) {
        $lines = array();
        foreach ((array) $matches as $page) {
            $excerpt = isset($page['excerpt']) ? $page['excerpt'] : wp_trim_words((string) $page['content'], 90);
            $lines[] = sprintf(
                'Policy page: %s | URL: %s | Relevant excerpt: %s',
                wp_strip_all_tags((string) $page['title']),
                esc_url_raw((string) $page['url']),
                wp_strip_all_tags((string) $excerpt)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param array $analysis Intent analysis.
     * @return array
     */
    private function source_types_for_analysis($analysis) {
        $primary = !empty($analysis['primaryType']) ? sanitize_key((string) $analysis['primaryType']) : '';

        return $primary !== '' ? array($primary) : array();
    }

    /**
     * @param array $analysis Intent analysis.
     * @return array
     */
    private function empty_resolution($analysis) {
        return array(
            'isPolicyQuestion' => !empty($analysis['isStorePolicyQuestion']),
            'analysis' => $analysis,
            'answerable' => false,
            'reason' => 'not_policy',
            'matches' => array(),
            'sources' => array(),
        );
    }

    /**
     * @param array $page Indexed page.
     * @param array $analysis Intent analysis.
     * @return int
     */
    private function page_score($page, $analysis) {
        $score = 0;
        $types = !empty($page['types']) ? (array) $page['types'] : array();
        $primary = !empty($analysis['primaryType']) ? sanitize_key((string) $analysis['primaryType']) : '';

        if ($primary !== '' && in_array($primary, $types, true)) {
            $score += 40;
        }

        $title = strtolower((string) ($page['title'] ?? ''));
        if ($primary !== '' && strpos($title, rtrim($primary, 's')) !== false) {
            $score += 12;
        }

        return $score;
    }

    /**
     * @param array $excerpt Excerpt scoring data.
     * @param array $analysis Intent analysis.
     * @param array $page Indexed page.
     * @return bool
     */
    private function is_answerable_excerpt($excerpt, $analysis, $page) {
        $primary = !empty($analysis['primaryType']) ? sanitize_key((string) $analysis['primaryType']) : '';
        $page_types = !empty($page['types']) ? array_map('sanitize_key', (array) $page['types']) : array();
        if ($primary === '' || !in_array($primary, $page_types, true) || empty($excerpt['text'])) {
            return false;
        }

        $specific = !empty($analysis['specificTerms']) ? (array) $analysis['specificTerms'] : array();
        $facets = !empty($analysis['facets']) ? (array) $analysis['facets'] : array();
        $strict_facets = array('time', 'cost', 'location', 'method', 'tracking');
        $has_strict_facet = !empty(array_intersect($facets, $strict_facets));

        if ($has_strict_facet) {
            return !empty($excerpt['matchedAnswerCues']);
        }

        if (!empty($facets)) {
            return !empty($excerpt['matchedAnswerCues'])
                || (!empty($excerpt['matchedFacets']) && !empty($excerpt['matchedPolicyTypes']));
        }

        if (!empty($specific)) {
            return !empty($excerpt['matchedSpecificTerms']) && !empty($excerpt['matchedPolicyTypes']);
        }

        return !empty($excerpt['matchedPolicyTypes']);
    }

    /**
     * Find a short source excerpt that actually overlaps the shopper's detail.
     *
     * @param string $content Indexed page text.
     * @param array  $analysis Policy analysis.
     * @return array
     */
    private function best_excerpt($content, $analysis) {
        $chunks = $this->content_chunks($content);
        $polarity = $this->question_polarity($analysis);
        $specific_terms = !empty($analysis['specificTerms']) ? (array) $analysis['specificTerms'] : array();
        $facets = !empty($analysis['facets']) ? (array) $analysis['facets'] : array();
        $primary_type = !empty($analysis['primaryType']) ? sanitize_key((string) $analysis['primaryType']) : '';
        $best = array(
            'text' => '',
            'score' => 0,
            'matchedSpecificTerms' => array(),
            'matchedFacets' => array(),
            'matchedPolicyTypes' => array(),
            'matchedAnswerCues' => array(),
        );

        foreach ($chunks as $chunk) {
            $matched_specific = array();
            $matched_facets = array();
            $matched_policy_types = array();
            $matched_answer_cues = array();
            $score = 0;

            if ($primary_type !== '') {
                foreach ($this->intents->type_terms($primary_type) as $type_term) {
                    if ($this->text_contains_term($chunk, $type_term)) {
                        $matched_policy_types[] = $primary_type;
                        $score += 7;
                        break;
                    }
                }
            }

            foreach ($specific_terms as $term) {
                if ($this->text_contains_term($chunk, $term)) {
                    $matched_specific[] = $term;
                    $score += 6;
                }
            }

            foreach ($facets as $facet) {
                foreach ($this->intents->facet_terms($facet) as $facet_term) {
                    if ($this->text_contains_term($chunk, $facet_term)) {
                        $matched_facets[] = $facet;
                        $score += 3;
                        break;
                    }
                }

                // One credit per facet. Several cues in the list describe the
                // same sentence -- "may be returned" and "items may be returned"
                // both match one clause -- and counting each of them stacked the
                // score for a single piece of evidence.
                foreach ($this->intents->answer_cues($primary_type, $facet) as $answer_cue) {
                    if ($this->text_contains_term($chunk, $answer_cue)) {
                        $matched_answer_cues[] = $answer_cue;
                        $score += 14;
                        break;
                    }
                }

                foreach ($this->structured_answer_cues($chunk, $primary_type, $facet) as $answer_cue) {
                    $matched_answer_cues[] = $answer_cue;
                    $score += 18;
                }
            }

            if (empty($facets)) {
                foreach ($this->intents->answer_cues($primary_type) as $answer_cue) {
                    if ($this->text_contains_term($chunk, $answer_cue)) {
                        $matched_answer_cues[] = $answer_cue;
                        $score += 10;
                        break;
                    }
                }
            }

            // Almost every policy page carries a section listing what is NOT
            // allowed, written in the same vocabulary as the rule itself, and
            // usually longer. Asked "Can I return an item if I change my mind?",
            // selection picked "the following items are not eligible for a
            // change-of-mind return" -- the shopper's own words, answering the
            // opposite of the question. Score has to know which way a chunk
            // points, not merely that it is on topic.
            // A published page carries more than its policy: a hero lead, a
            // sidebar of links, an example question printed as a prompt, button
            // labels. Those passages repeat the page's topic words -- and a
            // printed example question repeats the SHOPPER's words exactly --
            // so they score like answers while stating nothing. Quoting them
            // back is worse than quoting the wrong section, because it reads as
            // the store having no answer at all.
            $score -= 16 * $this->furniture_weight($chunk);

            if ($polarity !== '') {
                list($affirms, $denies) = $this->chunk_polarity($chunk);
                if ($denies > $affirms) {
                    $score += ($polarity === 'negative') ? 8 : -14;
                } elseif ($affirms > $denies) {
                    $score += ($polarity === 'affirmative') ? 6 : -8;
                }
            }

            if ($score > $best['score'] || ($best['text'] === '' && $score === 0)) {
                $best = array(
                    'text' => wp_trim_words($this->without_furniture($chunk), 110),
                    'score' => $score,
                    'matchedSpecificTerms' => array_values(array_unique($matched_specific)),
                    'matchedFacets' => array_values(array_unique($matched_facets)),
                    'matchedPolicyTypes' => array_values(array_unique($matched_policy_types)),
                    'matchedAnswerCues' => array_values(array_unique($matched_answer_cues)),
                );
            }
        }

        if ($best['text'] === '') {
            $best['text'] = wp_trim_words((string) $content, 110);
        }

        return $best;
    }

    /**
     * Drop navigation and button text from the ends of a passage.
     *
     * The chunker joins neighbouring sentences, so a section that ends where a
     * sidebar begins produces a passage that answers the question and then runs
     * on into "On this page. Order processing. Delivery methods...". The answer
     * is right; the tail makes it read like a scrape. Only whole segments that
     * are recognisably furniture are removed, and only when something is left.
     *
     * @param string $chunk Chosen excerpt.
     * @return string
     */
    private function without_furniture($chunk) {
        $segments = preg_split('/(?<=[.!?])\s+/u', (string) $chunk, -1, PREG_SPLIT_NO_EMPTY);
        if (count($segments) < 2) {
            return (string) $chunk;
        }

        $kept = array();
        foreach ($segments as $segment) {
            $is_nav = preg_match('/^\s*(?:on this page|related policies|more store information|popular topics|browse answers|see the answer|back to shop|quick answers)\b/iu', $segment)
                || preg_match('/[\x{2192}\x{2190}\x{21BA}\x{2794}]/u', $segment);
            $is_prompt = preg_match('/[\x{201C}\x{201D}"][^\x{201C}\x{201D}"]{6,120}\?[\x{201C}\x{201D}"]/u', $segment);

            if (!$is_nav && !$is_prompt) {
                $kept[] = $segment;
            }
        }

        $text = trim(implode(' ', $kept));

        // Never hand back nothing: a passage that is entirely furniture stays as
        // it was, and the score penalty is what keeps it from being chosen.
        return $text !== '' ? $text : (string) $chunk;
    }

    /**
     * How much of a passage is page furniture rather than statement.
     *
     * Returns 0 for ordinary prose and 1 for a passage that is entirely
     * navigation, headings or quoted questions, so the caller can scale a
     * penalty rather than take an all-or-nothing decision on a mixed chunk.
     *
     * @param string $chunk Candidate excerpt.
     * @return float
     */
    private function furniture_weight($chunk) {
        $text = trim((string) $chunk);
        if ($text === '') {
            return 1.0;
        }

        $signals = 0;
        $possible = 3;

        // A question is not an answer. Pages print example questions as
        // prompts, and they match a shopper's wording better than any policy
        // sentence ever will.
        if (preg_match('/[\x{201C}\x{201D}"][^\x{201C}\x{201D}"]{6,120}\?[\x{201C}\x{201D}"]/u', $text)
            || preg_match_all('/\?/u', $text) >= 2) {
            $signals++;
        }

        // Link and button furniture.
        if (preg_match('/[\x{2192}\x{2190}\x{21BA}\x{2794}]/u', $text)
            || preg_match('/\b(?:on this page|browse answers|see the answer|back to shop|related policies|popular topics|quick answers|more store information)\b/iu', $text)) {
            $signals++;
        }

        // Runs of short fragments -- nav lists and heading stacks -- rather
        // than sentences. Measured on the longest run, so one heading above a
        // real paragraph does not disqualify the paragraph.
        $longest = 0;
        foreach (preg_split('/(?<=[.!?:])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $sentence) {
            $words = str_word_count($sentence);
            if ($words > $longest) {
                $longest = $words;
            }
        }
        if ($longest < 9) {
            $signals++;
        }

        return $signals / $possible;
    }

    /**
     * Which way the shopper's question points.
     *
     * "Can I return this?" wants the rule. "What can't I return?" wants the
     * exceptions. The same page answers both, in adjacent paragraphs, using
     * the same words -- so the question has to say which one it meant.
     *
     * @param array $analysis Policy analysis.
     * @return string affirmative, negative, or an empty string when unclear.
     */
    private function question_polarity($analysis) {
        $text = isset($analysis['normalized']) ? (string) $analysis['normalized'] : '';
        if ($text === '') {
            return '';
        }

        // Checked first: "can I return something I cannot use" is still a
        // question about exclusions.
        if (preg_match('/\b(?:cannot|can\s*not|non-?returnable|non-?refundable|not\s+eligible|not\s+allowed|excluded|exclusions|ineligible|do\s+not\s+accept|refuse)\b/u', $text)
            || preg_match('/\b(?:can|could|may|will|should)\s+not\b/u', $text)
            || preg_match('/\b(?:can|won|couldn|shouldn|wouldn|isn|aren|doesn|don)[\x{2019}\']?t\b/u', $text)
            || preg_match('/\bwhat\s+(?:items\s+)?(?:are\s+)?not\b/u', $text)) {
            return 'negative';
        }

        if (preg_match('/^(?:can|could|may|am\s+i|are\s+we|do\s+you|does\s+the\s+store|is\s+it\s+possible)\b/u', $text)
            || preg_match('/\b(?:eligible|eligibility|allowed|qualify|do\s+you\s+accept)\b/u', $text)) {
            return 'affirmative';
        }

        return '';
    }

    /**
     * How many statements in a chunk grant something versus withhold it.
     *
     * @param string $chunk Candidate excerpt.
     * @return array{0:int,1:int} Affirming count, denying count.
     */
    private function chunk_polarity($chunk) {
        // Judged per sentence, because the phrases overlap. "eligible for" sits
        // inside "not eligible for a return", so counting phrases scored that
        // sentence as both affirming and denying -- a tie, which cancelled the
        // adjustment entirely and let the exclusions passage keep its lead. A
        // sentence either grants the thing or withholds it.
        $affirms = 0;
        $denies = 0;

        $sentences = preg_split('/(?<=[.!?:])\s+/u', (string) $chunk, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($sentences)) {
            return array(0, 0);
        }

        foreach ($sentences as $sentence) {
            if (preg_match('/\b(?:not|never|cannot|can\'t|non-?returnable|non-?refundable|ineligible|excluded|exclusions|refuse)\b/iu', $sentence)) {
                $denies++;
                continue;
            }

            if (preg_match('/\b(?:may\s+be\s+returned|can\s+be\s+returned|may\s+be\s+exchanged|can\s+be\s+exchanged|eligible\s+for|are\s+eligible|is\s+eligible|we\s+accept|are\s+accepted|qualify\s+for|within\s+\d+\s+days|refund\s+will\s+be)\b/iu', $sentence)) {
                $affirms++;
            }
        }

        return array($affirms, $denies);
    }

    /**
     * @param string $content Indexed page content.
     * @return array
     */
    private function content_chunks($content) {
        $content = trim((string) $content);
        if ($content === '') {
            return array();
        }

        $paragraphs = preg_split('/\n{2,}/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = array();

        foreach ((array) $paragraphs as $paragraph) {
            $sentences = preg_split('/(?<=[.!?])\s+/', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY);
            if (empty($sentences)) {
                continue;
            }

            for ($i = 0, $count = count($sentences); $i < $count; $i++) {
                $chunk = trim($sentences[$i]);
                if (isset($sentences[$i + 1])) {
                    $chunk .= ' ' . trim($sentences[$i + 1]);
                }
                if ($chunk !== '') {
                    $chunks[] = $chunk;
                }
            }
        }

        return !empty($chunks) ? array_slice($chunks, 0, 300) : array($content);
    }

    /**
     * Recognize strong policy evidence when a merchant writes the same fact in
     * natural sentence order rather than using one exact allowlisted phrase.
     *
     * Example: "Approved refunds are normally processed within five business
     * days" is valid refund-timing evidence even though modifiers appear
     * between "refunds", "processed", and "within".
     *
     * The match remains deliberately narrow: the excerpt must contain a
     * policy-specific subject/action and a concrete time expression in the same
     * short chunk. This is not fuzzy semantic guessing.
     *
     * @param string $chunk Source excerpt candidate.
     * @param string $type Policy type.
     * @param string $facet Requested facet.
     * @return array
     */
    private function structured_answer_cues($chunk, $type, $facet) {
        $type = sanitize_key((string) $type);
        $facet = sanitize_key((string) $facet);
        if ($facet !== 'time') {
            return array();
        }

        $text = strtolower(wp_strip_all_tags((string) $chunk));
        $text = preg_replace('/\s+/', ' ', $text);
        if ($text === '') {
            return array();
        }

        $number_words = 'one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|twenty|thirty|few|several';
        $duration = '/\b(?:within|in|after|up\s+to|takes?|lasting|lasts?)\s+(?:about\s+|approximately\s+|around\s+)?(?:\d+|' . $number_words . ')\s+(?:(?:business|working|calendar)\s+)?(?:hours?|days?|weeks?|months?|years?)\b/i';
        if (!preg_match($duration, $text)) {
            return array();
        }

        $patterns = array(
            'refunds' => array(
                '/\brefunds?\b.{0,120}\b(?:processed|issued|credited|applied|received|returned|takes?|arrives?)\b/i',
                '/\b(?:process(?:ed|ing)?|issu(?:ed|ing)|credit(?:ed|ing)?|appl(?:ied|ying)|receiv(?:ed|ing))\b.{0,120}\brefunds?\b/i',
            ),
            'shipping' => array(
                '/\b(?:shipping|delivery|dispatch|orders?|parcels?|packages?)\b.{0,120}\b(?:processed|shipped|dispatched|delivered|arrives?|takes?)\b/i',
                '/\b(?:processed|shipped|dispatched|delivered|arrives?|takes?)\b.{0,120}\b(?:shipping|delivery|dispatch|orders?|parcels?|packages?)\b/i',
            ),
            'returns' => array(
                '/\breturns?\b.{0,120}\b(?:accepted|allowed|requested|made|sent|received|takes?)\b/i',
                '/\b(?:accepted|allowed|requested|made|sent|received|takes?)\b.{0,120}\breturns?\b/i',
            ),
            'exchanges' => array(
                '/\bexchanges?\b.{0,120}\b(?:accepted|allowed|requested|made|processed|takes?)\b/i',
                '/\b(?:accepted|allowed|requested|made|processed|takes?)\b.{0,120}\bexchanges?\b/i',
            ),
            'warranty' => array(
                '/\b(?:warranty|warranties|guarantee)\b.{0,120}\b(?:covers?|covered|lasts?|valid|period)\b/i',
                '/\b(?:covers?|covered|lasts?|valid|period)\b.{0,120}\b(?:warranty|warranties|guarantee)\b/i',
            ),
            'cancellation' => array(
                '/\b(?:cancel|cancelled|canceled|cancellation)\b.{0,120}\b(?:allowed|requested|made|processed|takes?)\b/i',
            ),
        );

        if (empty($patterns[$type])) {
            return array();
        }

        foreach ($patterns[$type] as $pattern) {
            if (preg_match($pattern, $text)) {
                return array($type . '_structured_time');
            }
        }

        return array();
    }

    /**
     * Match simple word variants without broad fuzzy guessing.
     *
     * @param string $text Text.
     * @param string $term Term/phrase.
     * @return bool
     */
    private function text_contains_term($text, $term) {
        $text = strtolower((string) $text);
        $term = strtolower(trim((string) $term));
        if ($term === '') {
            return false;
        }

        if (strpos($term, ' ') !== false) {
            return strpos($text, $term) !== false;
        }

        if (preg_match('/(?:^|[^a-z0-9])' . preg_quote($term, '/') . '(?:$|[^a-z0-9])/i', $text)) {
            return true;
        }

        $stem = $this->simple_stem($term);
        if (strlen($stem) < 4) {
            return false;
        }

        return (bool) preg_match('/(?:^|[^a-z0-9])' . preg_quote($stem, '/') . '[a-z]{0,5}(?:$|[^a-z0-9])/i', $text);
    }

    /**
     * @param string $word Word.
     * @return string
     */
    private function simple_stem($word) {
        $word = strtolower((string) $word);
        foreach (array('ingly', 'edly', 'ing', 'ed', 'ly', 'es', 's') as $suffix) {
            if (strlen($word) > strlen($suffix) + 3 && substr($word, -strlen($suffix)) === $suffix) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }
}
