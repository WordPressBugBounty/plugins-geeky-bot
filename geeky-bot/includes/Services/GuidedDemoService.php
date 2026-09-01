<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds zero-cost demo prompts from the merchant's own indexed catalog and
 * explicitly selected policy pages. It never calls an AI provider.
 */
class GuidedDemoService {
    const SEED_OPTION = 'geekybot_guided_demo_seed';

    /**
     * Seven cards could not carry the range: three of them went on catalog
     * shapes a search box already does, leaving no room to show a follow-up
     * turn at all.
     */
    const MAX_EXAMPLES = 15;

    /** Paid prompts are the upsell, so they get a reserved share of the board. */
    const MAX_PRO_EXAMPLES = 5;

    public function refresh() {
        $seed = absint(get_option(self::SEED_OPTION, 0));
        update_option(self::SEED_OPTION, $seed + 1, false);
    }

    /**
     * Demo prompts for the board, strongest capability first.
     *
     * The ordering is the whole point. A merchant reads the first two cards and
     * decides whether this is a search box with extra steps, so the prompts only
     * a conversation can answer come before the ones a faceted filter could
     * fake. The catalog-shaped prompts still earn a place further down -- price
     * and colour language is what shoppers actually type -- but they no longer
     * introduce the product.
     *
     * @return array<int,array<string,mixed>>
     */
    public function examples() {
        $rows = $this->catalog_rows();
        if (empty($rows)) {
            return array();
        }

        $examples = array();
        $used_queries = array();
        $used_products = array();
        $used_families = array();

        $pro_state = apply_filters('geekybot_guided_demo_pro_state', array(
            'active' => false,
            'add_to_cart' => false,
            'variation_selection' => false,
            'cart_commands' => false,
        ));
        $pro_state = is_array($pro_state) ? $pro_state : array();

        // A follow-up needs something to follow. "Which one is cheaper" means
        // nothing against a single result, so every multi-step prompt hangs off
        // a family that actually holds more than one product.
        $group = $this->family_with_multiple($rows);

        /* -- what only a conversation can do ------------------------------ */

        if ($group) {
            $family = $group['family'];
            $this->mark_row_used($group['row'], $used_products, $used_families);
            $browse = sprintf(
                /* translators: %s: product family, such as hoodie. */
                __('show me %s', 'geeky-bot'),
                $family
            );

            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'follow-up-price',
                'tier' => 'free',
                'feature' => __('Conversation memory', 'geeky-bot'),
                'title' => __('Ask a follow-up that names no product', 'geeky-bot'),
                'steps' => array($browse, __('which one is cheaper?', 'geeky-bot')),
                'description' => __('The second question names nothing. It is answered against the results already on screen.', 'geeky-bot'),
                'locked' => false,
            ), $group['row']));

            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'compare-positions',
                'tier' => 'free',
                'feature' => __('Comparison', 'geeky-bot'),
                'title' => __('Compare by position, not by name', 'geeky-bot'),
                'steps' => array($browse, __('compare the first and second', 'geeky-bot')),
                'description' => __('Shoppers point at what they can see instead of retyping two product names.', 'geeky-bot'),
                'locked' => false,
            ), $group['row']));

            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'grounded-detail',
                'tier' => 'free',
                'feature' => __('Grounded answers', 'geeky-bot'),
                'title' => __('Ask a detail across the whole result set', 'geeky-bot'),
                'steps' => array($browse, __('what material are they?', 'geeky-bot')),
                'description' => __('Answered from stored product data for every result at once, naming any product the catalog does not record it for.', 'geeky-bot'),
                'locked' => false,
            ), $group['row']));
        }

        // Refining by price mid-conversation, rather than starting a new search.
        $refine = $this->select_row($rows, function ($row) {
            return (float) $row['price'] > 0 && !empty($row['family']);
        }, $used_products, $used_families, true);
        if ($refine) {
            $this->mark_row_used($refine, $used_products, $used_families);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'refine-in-chat',
                'tier' => 'free',
                'feature' => __('Refinement', 'geeky-bot'),
                'title' => __('Narrow the results without starting over', 'geeky-bot'),
                'steps' => array(
                    sprintf(
                        /* translators: %s: product family, such as jacket. */
                        __('show me %s', 'geeky-bot'),
                        $this->shopper_subject($refine)
                    ),
                    sprintf(
                        /* translators: 1: currency symbol, 2: maximum price. */
                        __('only under %1$s%2$s', 'geeky-bot'),
                        $this->currency_symbol(),
                        $this->format_number($this->friendly_price_ceiling((float) $refine['price']))
                    ),
                ),
                'description' => __('The budget arrives as a second thought, the way it does in a real conversation.', 'geeky-bot'),
                'locked' => false,
            ), $refine));
        }

        // Two products named outright, for the shopper who already knows both.
        $pair = $this->comparable_pair($rows, $used_products);
        if ($pair) {
            $this->mark_row_used($pair[0], $used_products, $used_families);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'compare-names',
                'tier' => 'free',
                'feature' => __('Comparison', 'geeky-bot'),
                'title' => __('Put two real products side by side', 'geeky-bot'),
                'query' => sprintf(
                    /* translators: 1: first product title, 2: second product title. */
                    __('Compare %1$s and %2$s', 'geeky-bot'),
                    $pair[0]['title'],
                    $pair[1]['title']
                ),
                'description' => __('Both products, and every value compared, come from this catalog.', 'geeky-bot'),
                'locked' => false,
            ), $pair[0]));
        }

        /* -- grounded answers beyond the product grid ---------------------- */

        $recommendation_row = $this->select_row($rows, function ($row) {
            return !empty($row['is_on_sale']) && !empty($row['family']);
        }, $used_products, $used_families, true);
        $recommendation_is_sale = !empty($recommendation_row);
        if (!$recommendation_row) {
            $recommendation_row = $this->select_row($rows, function ($row) {
                return !empty($row['family']);
            }, $used_products, $used_families, true);
        }
        if ($recommendation_row) {
            $this->mark_row_used($recommendation_row, $used_products, $used_families);
            $family = !empty($recommendation_row['family']) ? $recommendation_row['family'] : $recommendation_row['title'];
            $example = $this->product_example(array(
                'id' => 'recommendation',
                'tier' => 'free',
                'feature' => __('Recommendation', 'geeky-bot'),
                'title' => __('Ask for a grounded best match', 'geeky-bot'),
                'query' => $recommendation_is_sale
                    ? sprintf(
                        /* translators: %s: product family, such as jackets. */
                        __('Which %s on sale do you recommend?', 'geeky-bot'),
                        $family
                    )
                    : sprintf(
                        /* translators: %s: product family, such as jackets. */
                        __('Which %s do you recommend?', 'geeky-bot'),
                        $family
                    ),
                'description' => $recommendation_is_sale
                    ? __('Uses a real product family with an active sale item.', 'geeky-bot')
                    : __('Uses a product family detected from this catalog.', 'geeky-bot'),
                'locked' => false,
            ), $recommendation_row);
            $example['sourceLabel'] = sprintf(
                /* translators: %s: product family name. */
                __('Catalog family: %s', 'geeky-bot'),
                $family
            );
            $this->add_example($examples, $used_queries, $example);
        }

        $policy = $this->policy_example();
        if ($policy) {
            $this->add_example($examples, $used_queries, $policy);
        }

        $this->add_example($examples, $used_queries, array(
            'id' => 'cheapest',
            'tier' => 'free',
            'feature' => __('Price intent', 'geeky-bot'),
            'title' => __('Sort the whole catalog by price', 'geeky-bot'),
            'query' => __('what is cheapest today', 'geeky-bot'),
            'description' => __('No product named at all, and the answer still comes from live WooCommerce prices.', 'geeky-bot'),
            'sourceLabel' => __('Whole catalog', 'geeky-bot'),
            'sourceUrl' => '',
            'sourceProductId' => 0,
            'locked' => false,
        ));

        $this->add_example($examples, $used_queries, array(
            'id' => 'capability',
            'tier' => 'free',
            'feature' => __('Scope', 'geeky-bot'),
            'title' => __('Ask the assistant what it covers', 'geeky-bot'),
            'query' => __('What can you do?', 'geeky-bot'),
            'description' => __('Answers from what this install actually has switched on, not a stock script.', 'geeky-bot'),
            'sourceLabel' => __('Assistant capabilities', 'geeky-bot'),
            'sourceUrl' => '',
            'sourceProductId' => 0,
            'locked' => false,
        ));

        $this->add_example($examples, $used_queries, array(
            'id' => 'handoff',
            'tier' => 'free',
            'feature' => __('Knowing when to stop', 'geeky-bot'),
            'title' => __('See it hand a complaint to a person', 'geeky-bot'),
            'query' => __('I want to complain', 'geeky-bot'),
            'description' => __('A complaint is not a shopping request, so it stops selling instead of answering with products.', 'geeky-bot'),
            'sourceLabel' => __('Support handover', 'geeky-bot'),
            'sourceUrl' => '',
            'sourceProductId' => 0,
            'locked' => false,
        ));

        /* -- the catalog-shaped prompts, which still earn a place ---------- */

        $exact = $this->best_distinctive_row($rows, $used_products);
        if (!$exact) {
            $exact = $rows[0];
        }
        $this->mark_row_used($exact, $used_products, $used_families);
        $this->add_example($examples, $used_queries, $this->product_example(array(
            'id' => 'exact-product',
            'tier' => 'free',
            'feature' => __('Exact product', 'geeky-bot'),
            'title' => __('Open a real product by name', 'geeky-bot'),
            'query' => (string) $exact['title'],
            'description' => __('Shows how shoppers can ask for one exact catalog product without browsing menus.', 'geeky-bot'),
            'locked' => false,
        ), $exact));

        $priced = $this->select_row($rows, function ($row) {
            return (float) $row['price'] > 0;
        }, $used_products, $used_families, true);
        if ($priced) {
            $this->mark_row_used($priced, $used_products, $used_families);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'price-filter',
                'tier' => 'free',
                'feature' => __('Price filter', 'geeky-bot'),
                'title' => __('Test a real budget request', 'geeky-bot'),
                'query' => sprintf(
                    /* translators: 1: product type, 2: currency symbol, 3: maximum price. */
                    __('%1$s under %2$s%3$s', 'geeky-bot'),
                    $this->shopper_subject($priced),
                    $this->currency_symbol(),
                    $this->format_number($this->friendly_price_ceiling((float) $priced['price']))
                ),
                'description' => __('Uses a confirmed current product price from this store.', 'geeky-bot'),
                'locked' => false,
            ), $priced));
        }

        $faceted = $this->select_row($rows, function ($row) {
            return !empty($row['color']) || !empty($row['size']);
        }, $used_products, $used_families, true);
        if ($faceted) {
            $this->mark_row_used($faceted, $used_products, $used_families);
            $parts = array($this->shopper_subject($faceted));
            if (!empty($faceted['color'])) {
                $parts[] = sprintf(
                    /* translators: %s: product color. */
                    __('in %s', 'geeky-bot'),
                    $faceted['color']
                );
            }
            if (!empty($faceted['size'])) {
                $parts[] = sprintf(
                    /* translators: %s: product size. */
                    __('size %s', 'geeky-bot'),
                    strtoupper($faceted['size'])
                );
            }
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'attribute-filter',
                'tier' => 'free',
                'feature' => __('Attributes', 'geeky-bot'),
                'title' => __('Try color or size language', 'geeky-bot'),
                'query' => implode(' ', $parts),
                'description' => __('Uses attributes already indexed from this product.', 'geeky-bot'),
                'locked' => false,
            ), $faceted));
        }

        // A product fact only resolves against a title distinctive enough to
        // identify one product. Asked about "Belt" in a store with four of
        // them, the assistant correctly refuses to guess -- which is right
        // behaviour and a poor advertisement, so the prompt is only built when
        // a distinctive title is available to build it from.
        $fact_row = $this->distinctive_row($rows, $used_products);
        if ($fact_row) {
            $this->mark_row_used($fact_row, $used_products, $used_families);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'stock-question',
                'tier' => 'free',
                'feature' => __('Product Q&A', 'geeky-bot'),
                'title' => __('Check a live stock fact', 'geeky-bot'),
                'query' => sprintf(
                    /* translators: %s: product title. */
                    __('Is %s in stock?', 'geeky-bot'),
                    $fact_row['title']
                ),
                'description' => __('Uses the current WooCommerce stock state for this product.', 'geeky-bot'),
                'locked' => false,
            ), $fact_row));
        }

        $price_fact_row = $this->distinctive_row($rows, $used_products, true);
        if ($price_fact_row) {
            $this->mark_row_used($price_fact_row, $used_products, $used_families);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'price-question',
                'tier' => 'free',
                'feature' => __('Product Q&A', 'geeky-bot'),
                'title' => __('Check a confirmed product price', 'geeky-bot'),
                'query' => sprintf(
                    /* translators: %s: product title. */
                    __('How much does %s cost?', 'geeky-bot'),
                    $price_fact_row['title']
                ),
                'description' => __('Uses only the current WooCommerce product price.', 'geeky-bot'),
                'locked' => false,
            ), $price_fact_row));
        }

        /* -- Commerce Pro -------------------------------------------------- */

        $simple = $this->select_row($rows, function ($row) {
            return $row['product_type'] === 'simple';
        }, $used_products, $used_families, false);
        if (!$simple) {
            $simple = $this->first_row($rows, function ($row) {
                return $row['product_type'] === 'simple';
            });
        }
        $buy_row = $simple ? $simple : $exact;
        $this->mark_row_used($buy_row, $used_products, $used_families);
        $this->add_example($examples, $used_queries, $this->product_example(array(
            'id' => 'pro-add-to-cart',
            'tier' => 'pro',
            'feature' => __('Buying action', 'geeky-bot'),
            'title' => __('Add a real product from chat', 'geeky-bot'),
            'query' => sprintf(
                /* translators: %s: product title. */
                __('Add %s to my cart', 'geeky-bot'),
                $buy_row['title']
            ),
            'description' => __('Commerce Pro turns the product conversation into a cart action.', 'geeky-bot'),
            'locked' => empty($pro_state['active']) || empty($pro_state['add_to_cart']),
        ), $buy_row));

        $variable = $this->select_row($rows, function ($row) {
            return $row['product_type'] === 'variable' && !empty($row['variation_selection']);
        }, $used_products, $used_families, false);
        if (!$variable) {
            $variable = $this->first_row($rows, function ($row) {
                return $row['product_type'] === 'variable' && !empty($row['variation_selection']);
            });
        }
        if ($variable) {
            $selection = apply_filters(
                'geekybot_guided_demo_variation_selection',
                (array) $variable['variation_selection'],
                absint($variable['product_id'])
            );
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'pro-variation',
                'tier' => 'pro',
                'feature' => __('Variation buying', 'geeky-bot'),
                'title' => __('Select an option and buy in one sentence', 'geeky-bot'),
                'query' => $this->variation_query($variable['title'], is_array($selection) ? $selection : array()),
                'description' => __('Commerce Pro can select a real purchasable variation before adding it to the cart.', 'geeky-bot'),
                'locked' => empty($pro_state['active']) || empty($pro_state['variation_selection']),
            ), $variable));
        }

        if ($group) {
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'pro-ordinal-add',
                'tier' => 'pro',
                'feature' => __('Buying by reference', 'geeky-bot'),
                'title' => __('Buy the one on screen, unnamed', 'geeky-bot'),
                'steps' => array(
                    sprintf(
                        /* translators: %s: product family, such as hoodie. */
                        __('show me %s', 'geeky-bot'),
                        $group['family']
                    ),
                    __('add the second one', 'geeky-bot'),
                ),
                'description' => __('The shopper points at a position in the results and Commerce Pro carts that product.', 'geeky-bot'),
                'locked' => empty($pro_state['active']) || empty($pro_state['cart_commands']),
            ), $group['row']));
        }

        $this->add_example($examples, $used_queries, $this->product_example(array(
            'id' => 'pro-quantity',
            'tier' => 'pro',
            'feature' => __('Cart assistance', 'geeky-bot'),
            'title' => __('Change a quantity in conversation', 'geeky-bot'),
            'steps' => array(
                sprintf(
                    /* translators: %s: product title. */
                    __('Add %s to my cart', 'geeky-bot'),
                    $buy_row['title']
                ),
                __('make it quantity 2', 'geeky-bot'),
            ),
            'description' => __('Commerce Pro updates the line it just created, without the shopper naming it again.', 'geeky-bot'),
            'locked' => empty($pro_state['active']) || empty($pro_state['cart_commands']),
        ), $buy_row));

        $this->add_example($examples, $used_queries, $this->product_example(array(
            'id' => 'pro-remove',
            'tier' => 'pro',
            'feature' => __('Cart assistance', 'geeky-bot'),
            'title' => __('Take something back out of the cart', 'geeky-bot'),
            'steps' => array(
                sprintf(
                    /* translators: %s: product title. */
                    __('Add %s to my cart', 'geeky-bot'),
                    $buy_row['title']
                ),
                __('remove first product from cart', 'geeky-bot'),
            ),
            'description' => __('Positions are counted in the cart, not in the last search.', 'geeky-bot'),
            'locked' => empty($pro_state['active']) || empty($pro_state['cart_commands']),
        ), $buy_row));

        $examples = apply_filters('geekybot_guided_demo_examples', $examples, $rows);

        // Pro is built last, so a flat truncation spent every slot on free
        // prompts and cut the paid tier off the board entirely. Each tier gets
        // its own budget, and free reclaims whatever Pro does not use.
        $free = array();
        $pro = array();
        foreach ($examples as $example) {
            if (($example['tier'] ?? 'free') === 'pro') {
                $pro[] = $example;
            } else {
                $free[] = $example;
            }
        }

        $pro = array_slice($pro, 0, self::MAX_PRO_EXAMPLES);
        $free = array_slice($free, 0, self::MAX_EXAMPLES - count($pro));

        return array_values(array_merge($free, $pro));
    }

    /**
     * A family holding more than one product, so a follow-up has something to
     * refer back to. Single-result families cannot demonstrate a comparison.
     *
     * @param array $rows Catalog rows.
     * @return array|null {family, row}
     */
    private function family_with_multiple($rows) {
        $counts = array();
        foreach ($rows as $row) {
            $family = strtolower(trim((string) ($row['family'] ?? '')));
            if ($family === '' || strlen($family) < 3) {
                continue;
            }
            if (!isset($counts[$family])) {
                $counts[$family] = array('n' => 0, 'row' => $row);
            }
            $counts[$family]['n']++;
        }

        $best = null;
        foreach ($counts as $family => $data) {
            if ($data['n'] < 2) {
                continue;
            }
            if ($best === null || $data['n'] > $best['n']) {
                $best = array('family' => $family, 'row' => $data['row'], 'n' => $data['n']);
            }
        }

        return $best;
    }

    /**
     * Two products from one family, for a named comparison.
     *
     * @param array $rows          Catalog rows.
     * @param array $used_products Already-spent products.
     * @return array|null
     */
    private function comparable_pair($rows, $used_products) {
        $by_family = array();
        foreach ($rows as $row) {
            $family = strtolower(trim((string) ($row['family'] ?? '')));
            if ($family === '' || $this->distinctive_title_score((string) $row['title']) < 20) {
                continue;
            }
            $by_family[$family][] = $row;
            if (count($by_family[$family]) >= 2) {
                return array_slice($by_family[$family], 0, 2);
            }
        }

        return null;
    }

    /**
     * A product whose title identifies it on its own.
     *
     * A one-word title in a store holding four of them cannot be resolved from
     * a question, and the assistant rightly says so -- correct behaviour that
     * reads as a failure on a board built to show the plugin off.
     *
     * @param array $rows          Catalog rows.
     * @param array $used_products Already-spent products.
     * @param bool  $needs_price   Require a price as well.
     * @return array|null
     */
    private function distinctive_row($rows, $used_products, $needs_price = false) {
        foreach ($rows as $row) {
            if (isset($used_products[absint($row['product_id'])])) {
                continue;
            }
            if ($needs_price && (float) $row['price'] <= 0) {
                continue;
            }
            if ($this->distinctive_title_score((string) $row['title']) < 25) {
                continue;
            }
            return $row;
        }

        return null;
    }

    public function counts($examples = null) {
        if (!is_array($examples)) {
            $examples = $this->examples();
        }
        $free = 0;
        $pro = 0;
        $unlocked = 0;
        foreach ($examples as $example) {
            if (($example['tier'] ?? '') === 'pro') {
                $pro++;
                if (empty($example['locked'])) {
                    $unlocked++;
                }
            } else {
                $free++;
            }
        }

        return array(
            'total' => count($examples),
            'free' => $free,
            'pro' => $pro,
            'proUnlocked' => $unlocked,
        );
    }

    private function catalog_rows() {
        global $wpdb;

        $table = ProductIndexService::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The demo requires current index availability.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded live demo rows must not be stale.
        $raw_rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed plugin index table derived from the trusted WordPress prefix.
            "SELECT product_id, title, product_type, price, sale_price, is_on_sale, stock_status, categories, color_terms, size_terms, product_url FROM {$table} WHERE stock_status IN ('instock','onbackorder') ORDER BY is_on_sale DESC, total_sales DESC, rating DESC, product_id ASC LIMIT 60",
            ARRAY_A
        );
        if (empty($raw_rows)) {
            return array();
        }

        $rows = array();
        $index = new ProductIndexService();
        foreach ($raw_rows as $row) {
            $product_id = absint($row['product_id'] ?? 0);
            $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
            if (!$product || !CatalogVisibilityService::is_visible($product, 'guided_demo')) {
                continue;
            }

            $analysis = $index->analyze_query((string) $row['title']);
            $family = !empty($analysis['product_family_term']) ? sanitize_text_field((string) $analysis['product_family_term']) : '';
            $rows[] = array(
                'product_id' => $product_id,
                'title' => sanitize_text_field((string) $row['title']),
                'product_type' => sanitize_key((string) $row['product_type']),
                'price' => (float) $row['price'],
                'sale_price' => (float) $row['sale_price'],
                'is_on_sale' => !empty($row['is_on_sale']),
                'stock_status' => sanitize_key((string) $row['stock_status']),
                'family' => $family,
                'color' => $this->first_term((string) $row['color_terms']),
                'size' => $this->first_term((string) $row['size_terms']),
                'variation_selection' => $this->purchasable_variation_selection($product),
                'product_url' => esc_url_raw((string) $row['product_url']),
                'edit_url' => esc_url_raw((string) get_edit_post_link($product_id, 'raw')),
            );
            if (count($rows) >= 30) {
                break;
            }
        }

        if (empty($rows)) {
            return array();
        }

        $seed = absint(get_option(self::SEED_OPTION, 0));
        $offset = $seed % count($rows);
        if ($offset > 0) {
            $rows = array_merge(array_slice($rows, $offset), array_slice($rows, 0, $offset));
        }

        return $rows;
    }

    private function policy_example() {
        $page_ids = array_values(array_filter(array_map('absint', (array) Settings::get('policy_page_ids', array()))));
        $intent = new PolicyIntentService();
        $queries = array(
            'shipping' => __('How long does shipping take?', 'geeky-bot'),
            'returns' => __('Can I return an opened product?', 'geeky-bot'),
            'refunds' => __('When will I receive my refund?', 'geeky-bot'),
            'exchanges' => __('Can I exchange an item for another size?', 'geeky-bot'),
            'warranty' => __('Does this store provide a warranty?', 'geeky-bot'),
            'payment' => __('Which payment methods do you accept?', 'geeky-bot'),
            'privacy' => __('How do you use my personal data?', 'geeky-bot'),
            'terms' => __('Where can I read the store terms?', 'geeky-bot'),
        );

        foreach ($page_ids as $page_id) {
            $page = get_post($page_id);
            if (!$page || $page->post_status !== 'publish' || $page->post_type !== 'page') {
                continue;
            }
            $types = $intent->document_types($page->post_title, wp_strip_all_tags($page->post_content));
            foreach ($types as $type) {
                if (!isset($queries[$type])) {
                    continue;
                }
                return array(
                    'id' => 'policy-' . $type,
                    'tier' => 'free',
                    'feature' => __('Store policy', 'geeky-bot'),
                    'title' => __('Test a grounded policy answer', 'geeky-bot'),
                    'query' => $queries[$type],
                    'description' => __('Uses one of the public policy pages approved in Store Knowledge.', 'geeky-bot'),
                    'sourceLabel' => sprintf(
                        /* translators: %s: policy page title. */
                        __('Policy page: %s', 'geeky-bot'),
                        $page->post_title
                    ),
                    'sourceUrl' => esc_url_raw((string) get_edit_post_link($page_id, 'raw')),
                    'sourceProductId' => 0,
                    'locked' => false,
                );
            }
        }

        return array();
    }

    private function add_example(&$examples, &$used_queries, $example) {
        // A prompt may be a short conversation. The first turn doubles as the
        // query, so anything reading only 'query' still behaves as it did.
        $steps = array();
        foreach ((array) ($example['steps'] ?? array()) as $step) {
            $step = sanitize_text_field((string) $step);
            if ($step !== '') {
                $steps[] = $step;
            }
        }
        $steps = array_slice($steps, 0, 4);

        $query = sanitize_text_field((string) ($example['query'] ?? ''));
        if ($query === '' && !empty($steps)) {
            $query = $steps[0];
        }
        if ($query === '') {
            return;
        }
        $example['steps'] = $steps;
        // Hash the whole conversation, not just its opening line. Three
        // prompts can share a first turn -- browse, then compare; browse, then
        // ask a detail; browse, then cart the second result -- and hashing the
        // opener alone silently dropped all but the first of them.
        $hash = md5(strtolower(implode(' | ', !empty($steps) ? $steps : array($query))));
        if (isset($used_queries[$hash])) {
            return;
        }
        $used_queries[$hash] = true;
        $example['query'] = $query;
        $example['id'] = sanitize_key((string) ($example['id'] ?? $hash));
        $example['tier'] = ($example['tier'] ?? 'free') === 'pro' ? 'pro' : 'free';
        $example['feature'] = sanitize_text_field((string) ($example['feature'] ?? ''));
        $example['title'] = sanitize_text_field((string) ($example['title'] ?? ''));
        $example['description'] = sanitize_text_field((string) ($example['description'] ?? ''));
        $example['sourceLabel'] = sanitize_text_field((string) ($example['sourceLabel'] ?? ''));
        $example['sourceUrl'] = esc_url_raw((string) ($example['sourceUrl'] ?? ''));
        $example['sourceProductId'] = absint($example['sourceProductId'] ?? 0);
        $example['locked'] = !empty($example['locked']);
        $example['isConversation'] = count($example['steps']) > 1;
        $examples[] = $example;
    }

    private function product_example($example, $row) {
        $example['sourceLabel'] = sprintf(
            /* translators: %s: product title. */
            __('Product: %s', 'geeky-bot'),
            $row['title']
        );
        $example['sourceUrl'] = $row['edit_url'];
        $example['sourceProductId'] = absint($row['product_id']);
        return $example;
    }

    private function select_row($rows, $callback, $used_products, $used_families, $prefer_new_family) {
        $fallback = array();
        foreach ($rows as $row) {
            if (!call_user_func($callback, $row)) {
                continue;
            }
            if (!isset($used_products[absint($row['product_id'])])) {
                if (!$prefer_new_family || empty($row['family']) || !isset($used_families[strtolower((string) $row['family'])])) {
                    return $row;
                }
                if (empty($fallback)) {
                    $fallback = $row;
                }
            }
        }
        if (!empty($fallback)) {
            return $fallback;
        }
        foreach ($rows as $row) {
            if (call_user_func($callback, $row) && !isset($used_products[absint($row['product_id'])])) {
                return $row;
            }
        }
        return array();
    }

    private function best_distinctive_row($rows, $used_products) {
        $best = array();
        $best_score = -1000;
        foreach ($rows as $position => $row) {
            if (isset($used_products[absint($row['product_id'])])) {
                continue;
            }
            $score = $this->distinctive_title_score((string) $row['title']) - ((int) $position / 100);
            if ($score > $best_score) {
                $best_score = $score;
                $best = $row;
            }
        }
        return $best;
    }

    private function distinctive_title_score($title) {
        $title = trim(wp_strip_all_tags((string) $title));
        $normalized = strtolower($title);
        $generic = array('hoodie', 'shirt', 't-shirt', 'shoes', 'shoe', 'jacket', 'cap', 'beanie', 'product');
        $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
        $score = count($words) * 5 + min(20, strlen($title) / 3);
        if (in_array($normalized, $generic, true)) {
            $score -= 40;
        }
        if (preg_match('/[0-9]/', $title)) {
            $score += 7;
        }
        if (count($words) >= 3) {
            $score += 10;
        }
        return $score;
    }

    private function mark_row_used($row, &$used_products, &$used_families) {
        if (empty($row)) {
            return;
        }
        $used_products[absint($row['product_id'])] = true;
        if (!empty($row['family'])) {
            $used_families[strtolower((string) $row['family'])] = true;
        }
    }

    private function shopper_subject($row) {
        $family = trim((string) ($row['family'] ?? ''));
        if ($family !== '' && strlen($family) >= 3) {
            return $family;
        }
        return (string) $row['title'];
    }

    private function tier_count($examples, $tier) {
        $count = 0;
        foreach ($examples as $example) {
            if (($example['tier'] ?? 'free') === $tier) {
                $count++;
            }
        }
        return $count;
    }

    private function purchasable_variation_selection($product) {
        if (!is_object($product) || !is_a($product, 'WC_Product_Variable')) {
            return array();
        }

        foreach ((array) $product->get_children() as $variation_id) {
            $variation = wc_get_product(absint($variation_id));
            if (!$variation || $variation->get_status() !== 'publish' || !$variation->is_purchasable()) {
                continue;
            }
            if (!$variation->is_in_stock() && !$variation->backorders_allowed()) {
                continue;
            }

            $selection = array();
            foreach ((array) $variation->get_attributes() as $attribute_name => $attribute_value) {
                $attribute_value = sanitize_text_field((string) $attribute_value);
                if ($attribute_value === '') {
                    continue;
                }
                $taxonomy = sanitize_key((string) $attribute_name);
                $label = function_exists('wc_attribute_label') ? wc_attribute_label($attribute_name, $product) : $attribute_name;
                $value_label = $attribute_value;
                if (taxonomy_exists($attribute_name)) {
                    $term = get_term_by('slug', $attribute_value, $attribute_name);
                    if ($term && !is_wp_error($term)) {
                        $value_label = $term->name;
                    }
                }
                $selection[] = array(
                    'key' => $taxonomy,
                    'label' => sanitize_text_field((string) $label),
                    'value' => sanitize_text_field((string) $value_label),
                );
                if (count($selection) >= 2) {
                    break;
                }
            }
            if (!empty($selection)) {
                return $selection;
            }
        }

        return array();
    }

    private function variation_query($title, $selection) {
        $parts = array();
        foreach (array_slice((array) $selection, 0, 2) as $attribute) {
            $key = strtolower((string) ($attribute['key'] ?? ''));
            $label = sanitize_text_field((string) ($attribute['label'] ?? ''));
            $value = sanitize_text_field((string) ($attribute['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            if (strpos($key, 'color') !== false || strpos($key, 'colour') !== false || stripos($label, 'color') !== false || stripos($label, 'colour') !== false) {
                $parts[] = $value;
            } elseif (strpos($key, 'size') !== false || stripos($label, 'size') !== false) {
                $parts[] = sprintf(
                    /* translators: %s: product size. */
                    __('size %s', 'geeky-bot'),
                    strtoupper($value)
                );
            } else {
                $parts[] = trim($label . ' ' . $value);
            }
        }

        if (empty($parts)) {
            return sprintf(
                /* translators: %s: product title. */
                __('Choose an available option for %s and add it to my cart', 'geeky-bot'),
                $title
            );
        }

        return sprintf(
            /* translators: 1: selected product options, 2: product title. */
            __('Choose %1$s for %2$s and add it to my cart', 'geeky-bot'),
            implode(', ', $parts),
            $title
        );
    }

    private function first_row($rows, $callback) {
        foreach ($rows as $row) {
            if (call_user_func($callback, $row)) {
                return $row;
            }
        }
        return array();
    }

    private function first_term($text) {
        $terms = preg_split('/\s+/u', trim((string) $text));
        return !empty($terms[0]) ? sanitize_text_field($terms[0]) : '';
    }

    private function friendly_price_ceiling($price) {
        $price = max(1, (float) $price);
        $step = $price >= 100 ? 25 : ($price >= 50 ? 10 : 5);
        $ceiling = ceil($price / $step) * $step;
        if ($ceiling <= $price) {
            $ceiling += $step;
        }
        return $ceiling;
    }

    private function currency_symbol() {
        if (!function_exists('get_woocommerce_currency_symbol')) {
            return '$';
        }

        // WooCommerce stores these HTML-encoded -- USD is "&#36;", not "$".
        // Stripping tags leaves the entity intact, so the example query held
        // six literal characters where a dollar sign belonged, and the board
        // that exists to show the plugin off read "beanie under &#036;20".
        return html_entity_decode(
            wp_strip_all_tags(get_woocommerce_currency_symbol()),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    private function format_number($number) {
        return rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
    }
}
