<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds a grounded recommendation presentation after Product Discovery has
 * already produced a valid result set.
 *
 * This service does not search the catalog and does not relax constraints. It
 * only chooses the strongest explainable option from products that already
 * passed Product Discovery filtering.
 */
class ProductRecommendationService {
    /**
     * Prepares a deterministic recommendation response for explicit shopper
     * recommendation requests.
     *
     * @param string $message Shopper message.
     * @param array  $products Hydrated Product Discovery payloads.
     * @param array  $search_context ProductService search context.
     * @return array
     */
    public function prepare($message, $products, $search_context = array()) {
        $products = array_values(array_filter((array) $products, function ($product) {
            return is_array($product) && !empty($product['id']) && !empty($product['name']);
        }));
        $search_context = is_array($search_context) ? $search_context : array();

        if (empty($products) || !$this->is_explicit_recommendation_request($message, $search_context)) {
            return array('handled' => false);
        }

        $analysis = !empty($search_context['analysis']) && is_array($search_context['analysis'])
            ? $search_context['analysis']
            : array();

        $choice = $this->choose_product($products, $analysis);
        $selected_index = isset($choice['index']) ? absint($choice['index']) : 0;
        if (!isset($products[$selected_index])) {
            $selected_index = 0;
        }

        $recommended = $products[$selected_index];
        if ($selected_index > 0) {
            unset($products[$selected_index]);
            array_unshift($products, $recommended);
            $products = array_values($products);
        }

        $message_text = $this->recommendation_message(
            $recommended,
            $products,
            $analysis,
            !empty($choice['reason']) ? sanitize_key((string) $choice['reason']) : 'ranked_match',
            isset($choice['metric']) ? $choice['metric'] : null
        );

        return array(
            'handled' => true,
            'products' => $products,
            'message' => $message_text,
            'meta' => array(
                'recommendedProductId' => absint($recommended['id']),
                'recommendedProductName' => wp_strip_all_tags((string) $recommended['name']),
                'reason' => !empty($choice['reason']) ? sanitize_key((string) $choice['reason']) : 'ranked_match',
            ),
        );
    }

    private function is_explicit_recommendation_request($message, $search_context) {
        $note = !empty($search_context['note']) ? sanitize_key((string) $search_context['note']) : '';
        if (in_array($note, array('named_product_match', 'facet_alternatives', 'price_alternatives', 'price_only_results'), true)) {
            return false;
        }

        if (!empty($search_context['conversationAction'])) {
            return false;
        }

        $analysis = !empty($search_context['analysis']) && is_array($search_context['analysis'])
            ? $search_context['analysis']
            : array();
        $requested_label = !empty($search_context['requestedLabel'])
            ? trim(wp_strip_all_tags((string) $search_context['requestedLabel']))
            : '';
        $has_product_identity = $requested_label !== '' && $requested_label !== __('products', 'geeky-bot');
        if (!$has_product_identity && empty($analysis['core_terms'])) {
            return false;
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(wp_strip_all_tags((string) $message), 'UTF-8')
            : strtolower(wp_strip_all_tags((string) $message));
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized));

        if ($normalized === '') {
            return false;
        }

        return preg_match('/\b(?:recommend|recommendation|suggest)\b/u', $normalized) === 1
            || preg_match('/\b(?:which|what)\b.+\bshould\s+i\s+(?:buy|choose|pick)\b/u', $normalized) === 1
            || preg_match('/\b(?:pick|choose)\s+(?:the\s+)?best\b/u', $normalized) === 1
            || preg_match('/\b(?:best|strongest)\s+(?:choice|option|match)\b/u', $normalized) === 1;
    }

    private function choose_product($products, $analysis) {
        if ($this->is_budget_request($analysis) && $this->all_products_have_fixed_prices($products)) {
            $index = $this->lowest_price_index($products);
            if ($index !== null) {
                return array(
                    'index' => $index,
                    'reason' => 'lowest_price',
                    'metric' => $this->current_price($products[$index]['id']),
                );
            }
        }

        if ($this->requires_sale($analysis) && $this->all_products_have_fixed_prices($products)) {
            $discount = $this->highest_discount_index($products);
            if ($discount !== null && !empty($discount['percent'])) {
                return array(
                    'index' => $discount['index'],
                    'reason' => 'strongest_discount',
                    'metric' => $discount['percent'],
                );
            }
        }

        return array(
            'index' => 0,
            'reason' => 'ranked_match',
            'metric' => null,
        );
    }

    private function recommendation_message($recommended, $products, $analysis, $reason, $metric) {
        $name = wp_strip_all_tags((string) $recommended['name']);
        $reply = sprintf(
            /* translators: %s: recommended product name. */
            __('My top match is %s.', 'geeky-bot'),
            $name
        );

        $confirmed = $this->confirmed_reason_parts($recommended, $analysis, $reason);
        if ($reason === 'lowest_price' && is_numeric($metric) && (float) $metric > 0) {
            $reply .= ' ' . sprintf(
                /* translators: %s: formatted current product price. */
                __('It has the lowest current price among these matches at %s.', 'geeky-bot'),
                $this->money_text((float) $metric)
            );
        } elseif ($reason === 'strongest_discount' && is_numeric($metric) && absint($metric) > 0) {
            $reply .= ' ' . sprintf(
                /* translators: %d: discount percentage. */
                __('It has the largest confirmed discount among these matches at about %d%%.', 'geeky-bot'),
                absint($metric)
            );
        } elseif (!empty($confirmed)) {
            $reply .= ' ' . sprintf(
                /* translators: %s: joined catalog-confirmed match reasons. */
                __('It matches %s.', 'geeky-bot'),
                $this->human_join($confirmed)
            );
            $confirmed = array();
        } else {
            $reply .= ' ' . __('It is the closest overall match based on the available product details.', 'geeky-bot');
        }

        if (!empty($confirmed)) {
            $reply .= ' ' . sprintf(
                /* translators: %s: joined additional confirmed match reasons. */
                __('It also matches %s.', 'geeky-bot'),
                $this->human_join($confirmed)
            );
        }

        if (count($products) > 1) {
            $reply .= ' ' . __('The other matching options are listed below for comparison.', 'geeky-bot');
        } else {
            $reply .= ' ' . __('Open it to review the full price, stock, and available options.', 'geeky-bot');
        }

        return $reply;
    }

    private function confirmed_reason_parts($product, $analysis, $reason = '') {
        $parts = array();
        $analysis = is_array($analysis) ? $analysis : array();
        $product_text = implode(' ', array_filter(array(
            !empty($product['name']) ? (string) $product['name'] : '',
            !empty($product['shortDescription']) ? (string) $product['shortDescription'] : '',
            !empty($product['categories']) ? implode(' ', (array) $product['categories']) : '',
        )));
        $normalized_product_text = function_exists('mb_strtolower')
            ? mb_strtolower(wp_strip_all_tags($product_text), 'UTF-8')
            : strtolower(wp_strip_all_tags($product_text));

        $colors = !empty($analysis['requested_color_labels'])
            ? array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $analysis['requested_color_labels']))))
            : array();
        if (!empty($colors)) {
            $parts[] = sprintf(
                /* translators: %s: requested product color. */
                __('the requested %s color', 'geeky-bot'),
                $this->human_join($colors)
            );
        }

        $sizes = !empty($analysis['requested_size_labels'])
            ? array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $analysis['requested_size_labels']))))
            : array();
        if (!empty($sizes)) {
            $parts[] = sprintf(
                /* translators: %s: requested product size. */
                __('size %s', 'geeky-bot'),
                strtoupper($this->human_join($sizes))
            );
        }

        if (!empty($analysis['price_range']) && $reason !== 'lowest_price') {
            $parts[] = __('your price range', 'geeky-bot');
        }

        if ($this->requires_sale($analysis) && $reason !== 'strongest_discount') {
            $parts[] = __('the sale filter', 'geeky-bot');
        }

        $modifier_terms = !empty($analysis['modifier_terms']) ? array_values((array) $analysis['modifier_terms']) : array();
        $modifier_labels = !empty($analysis['modifier_labels']) ? array_values((array) $analysis['modifier_labels']) : array();
        foreach ($modifier_terms as $index => $term) {
            $term = trim(wp_strip_all_tags((string) $term));
            if ($term === '') {
                continue;
            }
            $normalized_term = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
            if ($normalized_product_text === '' || strpos($normalized_product_text, $normalized_term) === false) {
                continue;
            }
            $label = isset($modifier_labels[$index]) ? wp_strip_all_tags((string) $modifier_labels[$index]) : $term;
            if ($label !== '') {
                $parts[] = strtolower($label);
            }
        }

        if (!empty($product['isInStock'])) {
            $parts[] = __('in-stock availability', 'geeky-bot');
        }

        if (!empty($product['requiresOptions'])) {
            $parts[] = __('selectable options', 'geeky-bot');
        }

        // Keep only two additional facts so the response remains useful rather
        // than reading like a technical match dump.
        return array_slice(array_values(array_unique(array_filter($parts))), 0, 2);
    }

    private function all_products_have_fixed_prices($products) {
        if (empty($products)) {
            return false;
        }

        foreach ((array) $products as $product) {
            if (!empty($product['requiresOptions']) || (!empty($product['type']) && $product['type'] === 'variable')) {
                return false;
            }
            $price = $this->current_price(!empty($product['id']) ? $product['id'] : 0);
            if ($price === null || $price <= 0) {
                return false;
            }
        }

        return true;
    }

    private function is_budget_request($analysis) {
        if (!empty($analysis['budget_sort']) || !empty($analysis['value_sort'])) {
            return true;
        }

        $modes = !empty($analysis['decision_modes']) ? (array) $analysis['decision_modes'] : array();
        return !empty(array_intersect($modes, array('budget', 'cheapest', 'best_value')));
    }

    private function requires_sale($analysis) {
        return !empty($analysis['sale_required'])
            || (!empty($analysis['intent']) && sanitize_key((string) $analysis['intent']) === 'sale');
    }

    private function lowest_price_index($products) {
        $best_index = null;
        $best_price = PHP_FLOAT_MAX;

        foreach ($products as $index => $product) {
            $price = $this->current_price($product['id']);
            if ($price === null || $price <= 0) {
                continue;
            }
            if ($price < $best_price) {
                $best_price = $price;
                $best_index = $index;
            }
        }

        return $best_index;
    }

    private function highest_discount_index($products) {
        $best_index = null;
        $best_discount = 0;

        foreach ($products as $index => $product) {
            $discount = $this->discount_percent_from_id($product['id']);
            if ($discount > $best_discount) {
                $best_discount = $discount;
                $best_index = $index;
            }
        }

        if ($best_index === null) {
            return null;
        }

        return array(
            'index' => $best_index,
            'percent' => $best_discount,
        );
    }

    private function current_price($product_id) {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product(absint($product_id));
        if (!$product) {
            return null;
        }

        if ($product->is_type('variable')) {
            $price = $product->get_variation_price('min', true);
            return $price === '' ? null : (float) $price;
        }

        $price = $product->get_price();
        return $price === '' ? null : (float) $price;
    }

    private function discount_percent_from_id($product_id) {
        if (!function_exists('wc_get_product')) {
            return 0;
        }

        return $this->discount_percent(wc_get_product(absint($product_id)));
    }

    private function discount_percent($product) {
        if (!$product || !$product->is_on_sale()) {
            return 0;
        }

        if ($product->is_type('variable')) {
            $best = 0;
            foreach ((array) $product->get_children() as $variation_id) {
                $variation = wc_get_product(absint($variation_id));
                if (!$variation || !$variation->is_on_sale()) {
                    continue;
                }
                $best = max($best, $this->discount_percent($variation));
            }
            return $best;
        }

        $regular = (float) $product->get_regular_price();
        $sale = (float) $product->get_sale_price();
        if ($regular <= 0 || $sale <= 0 || $sale >= $regular) {
            return 0;
        }

        return (int) round((($regular - $sale) / $regular) * 100);
    }

    private function money_text($amount) {
        $price = function_exists('wc_price') ? wc_price((float) $amount) : number_format_i18n((float) $amount, 2);
        $charset = function_exists('get_bloginfo') ? get_bloginfo('charset') : 'UTF-8';
        return html_entity_decode(wp_strip_all_tags((string) $price), ENT_QUOTES, $charset ? $charset : 'UTF-8');
    }

    private function human_join($parts) {
        $parts = array_values(array_unique(array_filter(array_map('trim', (array) $parts))));
        if (empty($parts)) {
            return '';
        }
        if (count($parts) === 1) {
            return $parts[0];
        }
        if (count($parts) === 2) {
            return sprintf(
                /* translators: 1: first reason, 2: second reason. */
                __('%1$s and %2$s', 'geeky-bot'),
                $parts[0],
                $parts[1]
            );
        }

        $last = array_pop($parts);
        return sprintf(
            /* translators: 1: comma-separated reasons, 2: final reason. */
            __('%1$s, and %2$s', 'geeky-bot'),
            implode(', ', $parts),
            $last
        );
    }
}
