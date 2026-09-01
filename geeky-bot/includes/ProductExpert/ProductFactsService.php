<?php
namespace GeekyBot\ProductExpert;

use GeekyBot\Services\CatalogVisibilityService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds a normalized, allowlisted fact record from WooCommerce product data.
 * No arbitrary post meta is exposed.
 */
class ProductFactsService {
    public function get($product_id) {
        if (!function_exists('wc_get_product')) {
            return array();
        }

        $product = wc_get_product(absint($product_id));
        if (!CatalogVisibilityService::is_visible($product, 'product_expert_facts')) {
            return array();
        }

        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        $categories = is_wp_error($categories) ? array() : array_map('wp_strip_all_tags', (array) $categories);
        $tags = is_wp_error($tags) ? array() : array_map('wp_strip_all_tags', (array) $tags);

        $attributes = $this->attributes($product);
        $variations = $product->is_type('variable') ? $this->variations($product) : array();

        // WooCommerce getters are authoritative. The allowlisted meta and
        // attribute fallbacks make Product Expert resilient immediately after
        // CSV imports or object-cache lag without exposing arbitrary post meta.
        $weight = $this->product_decimal_fact($product, 'get_weight', '_weight', $attributes, array('weight', 'product weight'));
        $length = $this->product_decimal_fact($product, 'get_length', '_length', $attributes, array('length', 'product length'));
        $width = $this->product_decimal_fact($product, 'get_width', '_width', $attributes, array('width', 'product width'));
        $height = $this->product_decimal_fact($product, 'get_height', '_height', $attributes, array('height', 'product height'));

        $facts = array(
            'id' => absint($product->get_id()),
            'name' => wp_strip_all_tags($product->get_name()),
            'sku' => sanitize_text_field($product->get_sku()),
            'type' => sanitize_key($product->get_type()),
            'url' => esc_url_raw(get_permalink($product->get_id())),
            'shortDescription' => $this->plain_text($product->get_short_description()),
            'description' => $this->plain_text($product->get_description()),
            'categories' => $categories,
            'tags' => $tags,
            'attributes' => $attributes,
            'variations' => $variations,
            'price' => $this->money_value($product->get_price()),
            'regularPrice' => $this->money_value($product->get_regular_price()),
            'salePrice' => $this->money_value($product->get_sale_price()),
            'isOnSale' => (bool) $product->is_on_sale(),
            'priceText' => $this->price_text($product),
            'stockStatus' => sanitize_key($product->get_stock_status()),
            'isInStock' => (bool) $product->is_in_stock(),
            'managesStock' => (bool) $product->managing_stock(),
            'stockQuantity' => $product->managing_stock() && $product->get_stock_quantity() !== null ? (int) $product->get_stock_quantity() : null,
            'backordersAllowed' => (bool) $product->backorders_allowed(),
            'backordersRequireNotification' => (bool) $product->backorders_require_notification(),
            'weight' => $weight,
            'dimensions' => array(
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'unit' => $this->dimension_unit(
                    $product,
                    $attributes,
                    function_exists('get_option') ? sanitize_text_field((string) get_option('woocommerce_dimension_unit', 'cm')) : 'cm'
                ),
            ),
            // The unit the catalog stated, when it stated one. A store that
            // records weight as an attribute writes "245 g" and leaves the
            // WooCommerce field empty; taking the number and labelling it with
            // the store setting produced "It weighs 245 lbs" -- wrong by a factor
            // of 450. A unit written down beats one inferred from a setting.
            'weightUnit' => $this->measurement_unit(
                $product,
                'get_weight',
                '_weight',
                $attributes,
                array('weight', 'product weight'),
                function_exists('get_option') ? sanitize_text_field((string) get_option('woocommerce_weight_unit', 'kg')) : 'kg'
            ),
            'shippingClass' => wp_strip_all_tags($product->get_shipping_class()),
            'sourceText' => trim($this->plain_text($product->get_short_description()) . ' ' . $this->plain_text($product->get_description())),
        );

        /**
         * Filters the normalized, allowlisted product facts used by Product Expert.
         *
         * Integrations may append trusted, store-owned facts here. Frontend or
         * shopper-supplied values must never be passed through this filter.
         *
         * @param array      $facts   Normalized product facts.
         * @param WC_Product $product WooCommerce product object.
         */
        $filtered = apply_filters('geekybot_product_expert_facts', $facts, $product);

        return is_array($filtered) ? $filtered : $facts;
    }

    public function attribute_values($facts, $aliases) {
        $facts = is_array($facts) ? $facts : array();
        $aliases = array_map(array($this, 'normalize_key'), (array) $aliases);
        foreach ((array) ($facts['attributes'] ?? array()) as $attribute) {
            $key = $this->normalize_key(isset($attribute['key']) ? $attribute['key'] : '');
            $label = $this->normalize_key(isset($attribute['label']) ? $attribute['label'] : '');
            foreach ($aliases as $alias) {
                if ($alias !== '' && ($key === $alias || $label === $alias || strpos($key, $alias) !== false || strpos($label, $alias) !== false)) {
                    return array_values(array_filter(array_map('wp_strip_all_tags', (array) ($attribute['values'] ?? array()))));
                }
            }
        }
        return array();
    }

    public function attribute_value($facts, $aliases) {
        $values = $this->attribute_values($facts, $aliases);
        return !empty($values) ? implode(', ', $values) : '';
    }

    public function source_sentence($facts, $keywords) {
        $text = !empty($facts['sourceText']) ? (string) $facts['sourceText'] : '';
        if ($text === '') {
            return '';
        }
        $keywords = array_values(array_filter(array_map('strtolower', (array) $keywords)));
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text);
        foreach ((array) $sentences as $sentence) {
            $lower = strtolower(remove_accents((string) $sentence));
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && strpos($lower, $keyword) !== false) {
                    return trim(wp_strip_all_tags($sentence));
                }
            }
        }
        return '';
    }

    private function attributes($product) {
        $result = array();
        foreach ((array) $product->get_attributes() as $attribute) {
            if (!is_object($attribute) || !method_exists($attribute, 'get_name')) {
                continue;
            }
            $name = (string) $attribute->get_name();
            $label = function_exists('wc_attribute_label') ? wc_attribute_label($name, $product) : $name;
            $values = array();

            if (method_exists($attribute, 'is_taxonomy') && $attribute->is_taxonomy()) {
                $values = wc_get_product_terms($product->get_id(), $name, array('fields' => 'names'));
                $values = is_wp_error($values) ? array() : (array) $values;
            } elseif (method_exists($attribute, 'get_options')) {
                $values = (array) $attribute->get_options();
            }

            $values = array_values(array_unique(array_filter(array_map(function ($value) {
                return trim(wp_strip_all_tags((string) $value));
            }, $values))));

            if (empty($values)) {
                continue;
            }

            $result[] = array(
                'key' => sanitize_key(str_replace('pa_', '', $name)),
                'label' => wp_strip_all_tags($label),
                'values' => $values,
                'variation' => method_exists($attribute, 'get_variation') ? (bool) $attribute->get_variation() : false,
            );
        }
        return $result;
    }

    private function variations($parent) {
        $rows = array();
        foreach ((array) $parent->get_children() as $variation_id) {
            $variation = wc_get_product(absint($variation_id));
            if (!$variation || !$variation->is_type('variation') || $variation->get_status() !== 'publish') {
                continue;
            }

            $attributes = array();
            foreach ((array) $variation->get_variation_attributes() as $raw_key => $raw_value) {
                $key = preg_replace('/^attribute_/', '', (string) $raw_key);
                $label = function_exists('wc_attribute_label') ? wc_attribute_label($key, $parent) : $key;
                $value = $this->variation_value_label($key, $raw_value);
                if ($value === '') {
                    continue;
                }
                $attributes[$this->normalize_key($label)] = array(
                    'key' => sanitize_key(str_replace('pa_', '', $key)),
                    'label' => wp_strip_all_tags($label),
                    'value' => wp_strip_all_tags($value),
                    'normalizedValue' => $this->normalize_value($value),
                );
            }

            $rows[] = array(
                'id' => absint($variation->get_id()),
                'sku' => sanitize_text_field($variation->get_sku()),
                'attributes' => $attributes,
                'price' => $this->money_value($variation->get_price()),
                'regularPrice' => $this->money_value($variation->get_regular_price()),
                'salePrice' => $this->money_value($variation->get_sale_price()),
                'isOnSale' => (bool) $variation->is_on_sale(),
                'priceText' => $this->price_text($variation),
                'stockStatus' => sanitize_key($variation->get_stock_status()),
                'isInStock' => (bool) $variation->is_in_stock(),
                'managesStock' => (bool) $variation->managing_stock(),
                'stockQuantity' => $variation->managing_stock() && $variation->get_stock_quantity() !== null ? (int) $variation->get_stock_quantity() : null,
                'backordersAllowed' => (bool) $variation->backorders_allowed(),
                'backordersRequireNotification' => (bool) $variation->backorders_require_notification(),
            );
        }
        return $rows;
    }

    private function variation_value_label($taxonomy, $value) {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (taxonomy_exists($taxonomy)) {
            $term = get_term_by('slug', $value, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return (string) $term->name;
            }
        }
        return str_replace('-', ' ', $value);
    }


    /**
     * The unit the three dimensions should be reported in.
     *
     * Length, width and height share a single unit, so they are resolved
     * together. If any of them came from a real WooCommerce field the store
     * setting stays authoritative, because those fields are bare numbers and
     * mixing a written unit with an unwritten one would mislabel the rest.
     * Otherwise the first unit the catalog states is used.
     *
     * @param mixed  $product    WooCommerce product.
     * @param array  $attributes Normalised product attributes.
     * @param string $store_unit Unit configured for the store.
     * @return string
     */
    private function dimension_unit($product, $attributes, $store_unit) {
        $sources = array(
            array('get_length', '_length', array('length', 'product length')),
            array('get_width', '_width', array('width', 'product width')),
            array('get_height', '_height', array('height', 'product height')),
        );

        foreach ($sources as $source) {
            $native = $this->native_measurement($product, $source[0], $source[1]);
            if ($native !== '' && $native !== null) {
                return $store_unit;
            }
        }

        foreach ($sources as $source) {
            $written = $this->attribute_unit($attributes, $source[2]);
            if ($written !== '') {
                return $written;
            }
        }

        return $store_unit;
    }

    /**
     * The unit a measurement should be reported in.
     *
     * A WooCommerce field or meta value is a bare number, so the store setting is
     * the right label for it. An attribute is free text the merchant wrote, and
     * when it carries its own unit that wins -- it describes the value actually
     * being shown.
     *
     * @param mixed  $product    WooCommerce product.
     * @param string $getter     Product getter for the native field.
     * @param string $meta_key   Allowlisted meta fallback.
     * @param array  $attributes Normalised product attributes.
     * @param array  $aliases    Attribute names to look for.
     * @param string $store_unit Unit configured for the store.
     * @return string
     */
    private function measurement_unit($product, $getter, $meta_key, $attributes, $aliases, $store_unit) {
        $native = $this->native_measurement($product, $getter, $meta_key);

        // The store setting describes its own fields, so it stays authoritative
        // whenever one of them supplied the number.
        if ($native !== '' && $native !== null) {
            return $store_unit;
        }

        $written = $this->attribute_unit($attributes, $aliases);

        return $written !== '' ? $written : $store_unit;
    }

    /**
     * A measurement taken straight from WooCommerce, before any attribute
     * fallback. Empty when the store records none.
     *
     * @param mixed  $product  WooCommerce product.
     * @param string $getter   Product getter for the native field.
     * @param string $meta_key Allowlisted meta fallback.
     * @return mixed
     */
    private function native_measurement($product, $getter, $meta_key) {
        $value = '';
        if ($product && method_exists($product, $getter)) {
            $value = $product->{$getter}();
        }
        if (($value === '' || $value === null) && $product && function_exists('get_post_meta')) {
            $value = get_post_meta($product->get_id(), $meta_key, true);
        }

        return $value;
    }

    /**
     * The unit written inside an attribute value, or '' when it states none.
     *
     * @param array $attributes Normalised product attributes.
     * @param array $aliases    Attribute names to look for.
     * @return string
     */
    private function attribute_unit($attributes, $aliases) {
        $raw = $this->attribute_value(array('attributes' => $attributes), $aliases);
        if ($raw === '') {
            return '';
        }

        // "245 g" -> "g", "1.4 kg" -> "kg", "245" -> "".
        if (!preg_match('~^\s*[0-9]+(?:[.,][0-9]+)?\s*([A-Za-z]{1,12})~u', $raw, $m)) {
            return '';
        }

        return sanitize_text_field($m[1]);
    }

    private function product_decimal_fact($product, $getter, $meta_key, $attributes, $aliases) {
        $value = '';
        if ($product && method_exists($product, $getter)) {
            $value = $product->{$getter}();
        }
        if (($value === '' || $value === null) && $product && function_exists('get_post_meta')) {
            $value = get_post_meta($product->get_id(), $meta_key, true);
        }
        if ($value === '' || $value === null) {
            $value = $this->attribute_decimal_value($attributes, $aliases);
        }
        return $this->decimal_or_null($value);
    }

    private function attribute_decimal_value($attributes, $aliases) {
        $aliases = array_map(array($this, 'normalize_key'), (array) $aliases);
        foreach ((array) $attributes as $attribute) {
            $key = $this->normalize_key(isset($attribute['key']) ? $attribute['key'] : '');
            $label = $this->normalize_key(isset($attribute['label']) ? $attribute['label'] : '');
            $matched = false;
            foreach ($aliases as $alias) {
                if ($alias !== '' && ($key === $alias || $label === $alias)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched || empty($attribute['values'])) {
                continue;
            }
            foreach ((array) $attribute['values'] as $attribute_value) {
                if (preg_match('/-?\d+(?:[\.,]\d+)?/u', (string) $attribute_value, $matches)) {
                    return str_replace(',', '.', $matches[0]);
                }
            }
        }
        return null;
    }

    private function price_text($product) {
        if (!$product) {
            return '';
        }
        $price = $product->get_price();
        $regular = $product->get_regular_price();
        $sale = $product->get_sale_price();
        if ($price === '') {
            return '';
        }
        if ($product->is_on_sale() && $sale !== '' && $regular !== '') {
            return sprintf(
                /* translators: 1: current price, 2: regular price. */
                __('%1$s sale price; regular price %2$s', 'geeky-bot'),
                $this->format_money($sale),
                $this->format_money($regular)
            );
        }
        return $this->format_money($price);
    }

    public function format_money($value) {
        if ($value === '' || $value === null) {
            return '';
        }
        if (function_exists('wc_price')) {
            $formatted = wp_strip_all_tags(wc_price((float) $value));
            $formatted = html_entity_decode($formatted, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
            return trim((string) preg_replace('/\s+/u', ' ', $formatted));
        }
        return number_format_i18n((float) $value, 2);
    }

    private function money_value($value) {
        return ($value === '' || $value === null) ? null : (float) $value;
    }

    private function decimal_or_null($value) {
        return ($value === '' || $value === null) ? null : (float) $value;
    }

    private function plain_text($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim((string) $value);
    }

    private function normalize_key($value) {
        $value = strtolower(remove_accents(wp_strip_all_tags((string) $value)));
        $value = str_replace(array('pa_', '-', '_'), array('', ' ', ' '), $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public function normalize_value($value) {
        $value = strtolower(remove_accents(wp_strip_all_tags((string) $value)));
        $value = str_replace(array('&', '/', '-', '_'), array(' and ', ' ', ' ', ' '), $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
