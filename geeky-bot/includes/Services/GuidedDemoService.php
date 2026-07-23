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

    public function refresh() {
        $seed = absint(get_option(self::SEED_OPTION, 0));
        update_option(self::SEED_OPTION, $seed + 1, false);
    }

    /**
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
            $ceiling = $this->friendly_price_ceiling((float) $priced['price']);
            $subject = $this->shopper_subject($priced);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'price-filter',
                'tier' => 'free',
                'feature' => __('Price filter', 'geeky-bot'),
                'title' => __('Test a real budget request', 'geeky-bot'),
                'query' => sprintf(
                    /* translators: 1: product type, 2: currency symbol, 3: maximum price. */
                    __('%1$s under %2$s%3$s', 'geeky-bot'),
                    $subject,
                    $this->currency_symbol(),
                    $this->format_number($ceiling)
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
            $query = $recommendation_is_sale
                ? sprintf(
                    /* translators: %s: product family, such as jackets. */
                    __('Which %s on sale do you recommend?', 'geeky-bot'),
                    $family
                )
                : sprintf(
                    /* translators: %s: product family, such as jackets. */
                    __('Which %s do you recommend?', 'geeky-bot'),
                    $family
                );
            $example = $this->product_example(array(
                'id' => 'recommendation',
                'tier' => 'free',
                'feature' => __('Recommendation', 'geeky-bot'),
                'title' => __('Ask for a grounded best match', 'geeky-bot'),
                'query' => $query,
                'description' => $recommendation_is_sale ? __('Uses a real product family with an active sale item.', 'geeky-bot') : __('Uses a product family detected from this catalog.', 'geeky-bot'),
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
        } else {
            $question_row = $this->select_row($rows, function ($row) {
                return !empty($row['title']);
            }, $used_products, $used_families, false);
            if (!$question_row) {
                $question_row = $exact;
            }
            $this->mark_row_used($question_row, $used_products, $used_families);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'product-question',
                'tier' => 'free',
                'feature' => __('Product Q&A', 'geeky-bot'),
                'title' => __('Ask about store-provided product details', 'geeky-bot'),
                'query' => sprintf(
                    /* translators: %s: product title. */
                    __('What are the key details of %s?', 'geeky-bot'),
                    $question_row['title']
                ),
                'description' => __('Answers only from the product information stored in WooCommerce.', 'geeky-bot'),
                'locked' => false,
            ), $question_row));
        }

        $free_count = $this->tier_count($examples, 'free');
        if ($free_count < 5) {
            $stock_row = $this->select_row($rows, function ($row) {
                return !empty($row['title']);
            }, $used_products, $used_families, false);
            if (!$stock_row) {
                $stock_row = $exact;
            }
            $this->mark_row_used($stock_row, $used_products, $used_families);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'stock-question',
                'tier' => 'free',
                'feature' => __('Product Q&A', 'geeky-bot'),
                'title' => __('Check a live stock fact', 'geeky-bot'),
                'query' => sprintf(
                    /* translators: %s: product title. */
                    __('Is %s in stock?', 'geeky-bot'),
                    $stock_row['title']
                ),
                'description' => __('Uses the current WooCommerce stock state for this product.', 'geeky-bot'),
                'locked' => false,
            ), $stock_row));
        }

        if ($this->tier_count($examples, 'free') < 5) {
            $price_row = $this->select_row($rows, function ($row) {
                return (float) $row['price'] > 0;
            }, $used_products, $used_families, false);
            if (!$price_row) {
                $price_row = $exact;
            }
            $this->mark_row_used($price_row, $used_products, $used_families);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'price-question',
                'tier' => 'free',
                'feature' => __('Product Q&A', 'geeky-bot'),
                'title' => __('Check a confirmed product price', 'geeky-bot'),
                'query' => sprintf(
                    /* translators: %s: product title. */
                    __('How much does %s cost?', 'geeky-bot'),
                    $price_row['title']
                ),
                'description' => __('Uses only the current WooCommerce product price.', 'geeky-bot'),
                'locked' => false,
            ), $price_row));
        }

        $pro_state = apply_filters('geekybot_guided_demo_pro_state', array(
            'active' => false,
            'add_to_cart' => false,
            'variation_selection' => false,
            'cart_commands' => false,
        ));
        $pro_state = is_array($pro_state) ? $pro_state : array();

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
            $selection = is_array($selection) ? $selection : array();
            $query = $this->variation_query($variable['title'], $selection);
            $this->add_example($examples, $used_queries, $this->product_example(array(
                'id' => 'pro-variation',
                'tier' => 'pro',
                'feature' => __('Variation buying', 'geeky-bot'),
                'title' => __('Select an option and buy in chat', 'geeky-bot'),
                'query' => $query,
                'description' => __('Commerce Pro can select a real purchasable variation before adding it to the cart.', 'geeky-bot'),
                'locked' => empty($pro_state['active']) || empty($pro_state['variation_selection']),
            ), $variable));
        } else {
            $this->add_example($examples, $used_queries, array(
                'id' => 'pro-cart',
                'tier' => 'pro',
                'feature' => __('Cart assistance', 'geeky-bot'),
                'title' => __('Manage the current cart from chat', 'geeky-bot'),
                'query' => __('Show my cart', 'geeky-bot'),
                'description' => __('Commerce Pro can show, update and remove cart items.', 'geeky-bot'),
                'sourceLabel' => __('WooCommerce cart', 'geeky-bot'),
                'sourceUrl' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
                'sourceProductId' => 0,
                'locked' => empty($pro_state['active']) || empty($pro_state['cart_commands']),
            ));
        }

        $examples = apply_filters('geekybot_guided_demo_examples', $examples, $rows);

        return array_slice(array_values($examples), 0, 7);
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
        $query = sanitize_text_field((string) ($example['query'] ?? ''));
        if ($query === '') {
            return;
        }
        $hash = md5(strtolower($query));
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
        return function_exists('get_woocommerce_currency_symbol') ? wp_strip_all_tags(get_woocommerce_currency_symbol()) : '$';
    }

    private function format_number($number) {
        return rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
    }
}
