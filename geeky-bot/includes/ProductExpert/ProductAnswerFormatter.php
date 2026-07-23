<?php
namespace GeekyBot\ProductExpert;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralizes shopper-facing Product Expert wording.
 *
 * ProductAnswerService decides which verified facts apply. This formatter only
 * turns those facts into concise, natural-language responses. It must never
 * infer or add product facts.
 */
class ProductAnswerFormatter {
    public function material($value) {
        $value = $this->clean($value);
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/u', $value))));

        if (count($parts) === 2 && preg_match('/\b(?:lid|cover|lining|shell|upper|outsole|handle|strap)\b/iu', $parts[1])) {
            return sprintf(
                /* translators: 1: primary verified material, 2: verified component material. */
                __('It\'s made from %1$s with %2$s.', 'geeky-bot'),
                $this->sentence_value($parts[0]),
                $this->component_value($parts[1])
            );
        }

        return sprintf(
            /* translators: %s: verified material. */
            __("It's made from %s.", 'geeky-bot'),
            $this->sentence_value($value)
        );
    }

    public function waterproof_no($detail = '', $warn_against_submersion = false) {
        $detail = $this->clean_protection_detail($detail);
        if ($warn_against_submersion) {
            if ($detail !== '') {
                return sprintf(
                    /* translators: %s: verified water-protection wording. */
                    __("No—don't submerge it. It's %s, not waterproof.", 'geeky-bot'),
                    $detail
                );
            }
            return __("No—don't submerge it. It's not waterproof.", 'geeky-bot');
        }
        if ($detail !== '') {
            return sprintf(
                /* translators: %s: verified water-protection wording. */
                __("No. It's %s, not waterproof.", 'geeky-bot'),
                $detail
            );
        }
        return __("No. It's not waterproof.", 'geeky-bot');
    }

    public function waterproof_yes($detail = '') {
        $detail = $this->clean($detail);
        if ($detail !== '') {
            return sprintf(
                /* translators: %s: verified waterproof wording. */
                __("Yes. The store describes it as waterproof: %s.", 'geeky-bot'),
                rtrim($detail, '.')
            );
        }
        return __('Yes. The store describes it as waterproof.', 'geeky-bot');
    }

    public function water_resistant_only($detail) {
        $detail = $this->clean_protection_detail($detail);
        if ($detail === '') {
            return __("It's described as water-resistant, but the store does not confirm that it is waterproof.", 'geeky-bot');
        }
        return sprintf(
            /* translators: %s: verified water-protection wording. */
            __("It's described as %s, but the store does not confirm that it is waterproof.", 'geeky-bot'),
            $detail
        );
    }

    public function verified_sentence($sentence, $product_name = '') {
        $sentence = $this->clean($sentence);
        $product_name = $this->clean($product_name);
        if ($sentence === '') {
            return '';
        }
        if ($product_name !== '' && stripos($sentence, $product_name) === 0) {
            $sentence = 'It' . substr($sentence, strlen($product_name));
        }
        return rtrim($sentence, '.') . '.';
    }

    public function compatibility($value) {
        $value = $this->clean($value);
        $value = preg_replace('/^compatible(?:\s+with)?\s+/iu', '', $value);
        return sprintf(
            /* translators: %s: verified compatibility. */
            __("It's compatible with %s.", 'geeky-bot'),
            $value
        );
    }

    public function use_case_yes($use_case) {
        return sprintf(
            /* translators: %s: verified use case. */
            __('Yes. The store lists it for %s.', 'geeky-bot'),
            $this->clean($use_case)
        );
    }

    public function use_case_unconfirmed($use_case, $confirmed) {
        return sprintf(
            /* translators: 1: requested use case, 2: verified use information. */
            __('The store does not specifically list it for %1$s. Its confirmed uses are %2$s.', 'geeky-bot'),
            $this->clean($use_case),
            $this->clean($confirmed)
        );
    }

    public function use_cases($confirmed) {
        return sprintf(
            /* translators: %s: verified use information. */
            __('Its listed uses are %s.', 'geeky-bot'),
            $this->clean($confirmed)
        );
    }

    public function attribute_boolean($label, $enabled) {
        $label = $this->clean($label);
        if ($enabled) {
            return sprintf(
                /* translators: %s: product attribute label. */
                __('Yes. %s is listed as available.', 'geeky-bot'),
                $label
            );
        }
        return sprintf(
            /* translators: %s: product attribute label. */
            __('No. %s is listed as unavailable.', 'geeky-bot'),
            $label
        );
    }

    public function attribute_value($label, $value) {
        return sprintf(
            /* translators: 1: product attribute label, 2: verified value. */
            __('%1$s: %2$s.', 'geeky-bot'),
            $this->clean($label),
            $this->clean($value)
        );
    }

    public function dimensions_and_weight($dimensions, $weight, $dimension_unit, $weight_unit, $include_dimensions, $include_weight) {
        $has_dimensions = $include_dimensions
            && isset($dimensions['length'], $dimensions['width'], $dimensions['height'])
            && $dimensions['length'] !== null
            && $dimensions['width'] !== null
            && $dimensions['height'] !== null;
        $has_weight = $include_weight && $weight !== null;

        if ($has_dimensions && $has_weight) {
            return sprintf(
                /* translators: 1: length, 2: width, 3: height, 4: dimension unit, 5: weight, 6: weight unit. */
                __('It measures %1$s × %2$s × %3$s %4$s and weighs %5$s %6$s.', 'geeky-bot'),
                $this->number($dimensions['length']),
                $this->number($dimensions['width']),
                $this->number($dimensions['height']),
                $this->clean($dimension_unit),
                $this->number($weight),
                $this->clean($weight_unit)
            );
        }
        if ($has_dimensions) {
            return sprintf(
                /* translators: 1: length, 2: width, 3: height, 4: dimension unit. */
                __('It measures %1$s × %2$s × %3$s %4$s.', 'geeky-bot'),
                $this->number($dimensions['length']),
                $this->number($dimensions['width']),
                $this->number($dimensions['height']),
                $this->clean($dimension_unit)
            );
        }
        if ($has_weight) {
            return sprintf(
                /* translators: 1: weight, 2: weight unit. */
                __('It weighs %1$s %2$s.', 'geeky-bot'),
                $this->number($weight),
                $this->clean($weight_unit)
            );
        }
        return '';
    }

    public function warranty($value) {
        $value = $this->normalize_warranty($value);
        if (preg_match('/^(one|two|three|four|five)-year\b/iu', $value)) {
            $value = 'a ' . $value;
        }
        return sprintf(
            /* translators: %s: verified warranty. */
            __('It comes with %s.', 'geeky-bot'),
            $value
        );
    }

    public function price($facts, ProductFactsService $facts_service) {
        $current = array_key_exists('price', $facts) ? $facts['price'] : null;
        $regular = array_key_exists('regularPrice', $facts) ? $facts['regularPrice'] : null;
        $sale = array_key_exists('salePrice', $facts) ? $facts['salePrice'] : null;

        if (!empty($facts['isOnSale']) && $sale !== null && $regular !== null) {
            return sprintf(
                /* translators: 1: sale price, 2: regular price. */
                __('It\'s currently %1$s, reduced from %2$s.', 'geeky-bot'),
                $facts_service->format_money($sale),
                $facts_service->format_money($regular)
            );
        }
        if ($current !== null) {
            return sprintf(
                /* translators: %s: current price. */
                __("It's currently %s.", 'geeky-bot'),
                $facts_service->format_money($current)
            );
        }
        return '';
    }

    public function stock($is_in_stock, $backorders_allowed, $notify_customer, $zero_stock = false) {
        if ($backorders_allowed && ($zero_stock || !$is_in_stock)) {
            $answer = __("It's currently out of stock, but you can still order it on backorder.", 'geeky-bot');
            if ($notify_customer) {
                $answer .= ' ' . __('The item will be clearly marked as backordered before purchase.', 'geeky-bot');
            }
            return $answer;
        }
        if ($is_in_stock) {
            return __("Yes, it's currently in stock.", 'geeky-bot');
        }
        return __("No, it's currently out of stock.", 'geeky-bot');
    }

    public function attribute_list($noun, $values) {
        $values = array_values(array_filter((array) $values));
        if (empty($values)) {
            return '';
        }
        return sprintf(
            /* translators: 1: fact noun, 2: verified values. */
            __('Available %1$s: %2$s.', 'geeky-bot'),
            $this->clean($noun),
            $this->human_join($values)
        );
    }

    public function sale($facts, ProductFactsService $facts_service) {
        if (!empty($facts['isOnSale'])) {
            return $this->price($facts, $facts_service);
        }
        if (array_key_exists('price', $facts) && $facts['price'] !== null) {
            return __("It isn't currently on sale.", 'geeky-bot');
        }
        return '';
    }

    public function cable_included($included) {
        return $included
            ? __('Yes, a charging cable is included.', 'geeky-bot')
            : __("No, a charging cable isn't included.", 'geeky-bot');
    }

    public function included_items($value) {
        return sprintf(
            /* translators: %s: verified included items. */
            __('Included: %s.', 'geeky-bot'),
            $this->clean($value)
        );
    }

    public function battery($value) {
        return sprintf(
            /* translators: %s: verified battery/runtime information. */
            __('Battery life: %s.', 'geeky-bot'),
            $this->clean($value)
        );
    }

    public function care($value) {
        return sprintf(
            /* translators: %s: verified care instructions. */
            __('Care instructions: %s.', 'geeky-bot'),
            $this->clean($value)
        );
    }

    public function dishwasher_split() {
        return __('The bottle body is dishwasher safe, but the lid should be hand washed.', 'geeky-bot');
    }

    public function not_dishwasher_safe() {
        return __("No, it isn't dishwasher safe.", 'geeky-bot');
    }

    public function not_microwave_safe() {
        return __("No, it isn't microwave safe.", 'geeky-bot');
    }

    public function not_leak_proof() {
        return __("No. It's leak-resistant, not leak-proof.", 'geeky-bot');
    }

    public function leak_protection($value) {
        return sprintf(
            /* translators: %s: verified leak-protection wording. */
            __('Leak protection: %s.', 'geeky-bot'),
            $this->clean($value)
        );
    }

    public function backlight($has_backlight) {
        return $has_backlight
            ? __('Yes, it has a backlight.', 'geeky-bot')
            : __("No, it doesn't have a backlight.", 'geeky-bot');
    }

    public function options($attributes) {
        return sprintf(
            /* translators: %s: verified option summary. */
            __('Available options: %s.', 'geeky-bot'),
            $this->clean(implode('; ', (array) $attributes))
        );
    }

    public function summary($description, $price_sentence, $in_stock) {
        $parts = array();
        $description = $this->clean($description);
        if ($description !== '') {
            $parts[] = rtrim($description, '.') . '.';
        }
        if ($price_sentence !== '') {
            $parts[] = $price_sentence;
        }
        $parts[] = $in_stock
            ? __("It's currently in stock.", 'geeky-bot')
            : __("It's currently out of stock.", 'geeky-bot');
        return implode(' ', array_filter($parts));
    }

    public function missing($missing_keys) {
        $labels = $this->fact_labels($missing_keys);
        if (empty($labels)) {
            return __("I couldn't find that information in this product's store details.", 'geeky-bot');
        }
        return sprintf(
            /* translators: %s: human-readable missing facts. */
            __("I couldn't find %s in this product's store details.", 'geeky-bot'),
            $this->human_join($labels, __('or', 'geeky-bot'))
        );
    }

    public function partial_missing($missing_keys) {
        $labels = $this->fact_labels($missing_keys);
        if (empty($labels)) {
            return '';
        }
        return sprintf(
            /* translators: %s: human-readable missing facts. */
            __("I couldn't find %s in the store details.", 'geeky-bot'),
            $this->human_join($labels, __('or', 'geeky-bot'))
        );
    }

    public function variation_exact($label, $state, $price_sentence = '') {
        $label = $this->clean($label);
        if ($state === 'in_stock') {
            $answer = sprintf(
                /* translators: %s: option label. */
                __('Yes, %s is in stock.', 'geeky-bot'),
                $label
            );
        } elseif ($state === 'backorder') {
            $answer = sprintf(
                /* translators: %s: option label. */
                __('%s is out of stock, but it can be ordered on backorder.', 'geeky-bot'),
                ucfirst($label)
            );
        } else {
            $answer = sprintf(
                /* translators: %s: option label. */
                __('%s is currently out of stock.', 'geeky-bot'),
                ucfirst($label)
            );
        }
        if ($price_sentence !== '') {
            $answer .= ' ' . $price_sentence;
        }
        return $answer;
    }

    public function variation_price($variation, ProductFactsService $facts_service) {
        if (!empty($variation['isOnSale']) && $variation['salePrice'] !== null && $variation['regularPrice'] !== null) {
            return sprintf(
                /* translators: 1: sale price, 2: regular price. */
                __('It is %1$s, reduced from %2$s.', 'geeky-bot'),
                $facts_service->format_money($variation['salePrice']),
                $facts_service->format_money($variation['regularPrice'])
            );
        }
        if (array_key_exists('price', $variation) && $variation['price'] !== null) {
            return sprintf(
                /* translators: %s: variation price. */
                __('It is %s.', 'geeky-bot'),
                $facts_service->format_money($variation['price'])
            );
        }
        return '';
    }

    public function variation_sale($label, $variation, ProductFactsService $facts_service) {
        $label = ucfirst($this->clean($label));
        if (!empty($variation['isOnSale']) && $variation['salePrice'] !== null && $variation['regularPrice'] !== null) {
            return sprintf(
                /* translators: 1: option label, 2: sale price, 3: regular price. */
                __('%1$s is on sale for %2$s, reduced from %3$s.', 'geeky-bot'),
                $label,
                $facts_service->format_money($variation['salePrice']),
                $facts_service->format_money($variation['regularPrice'])
            );
        }
        return sprintf(
            /* translators: 1: option label, 2: price text. */
            __('%1$s is on sale at %2$s.', 'geeky-bot'),
            $label,
            $this->clean($variation['priceText'] ?? '')
        );
    }

    public function variation_available_values($target_key, $context_value, $available, $unavailable) {
        $target_noun = $target_key === 'color' ? __('colors', 'geeky-bot') : __('sizes', 'geeky-bot');
        $parts = array();
        $context_value = $this->clean($context_value);

        if (!empty($available)) {
            $available_text = $this->human_join($available);
            $available_verb = count(array_unique($available)) === 1 ? __('is', 'geeky-bot') : __('are', 'geeky-bot');
            if ($context_value !== '') {
                $parts[] = sprintf(
                    /* translators: 1: context value, 2: available values, 3: is/are. */
                    __('In %1$s, %2$s %3$s available.', 'geeky-bot'),
                    $context_value,
                    $available_text,
                    $available_verb
                );
            } else {
                $parts[] = sprintf(
                    /* translators: 1: option noun, 2: available values. */
                    __('Available %1$s: %2$s.', 'geeky-bot'),
                    $target_noun,
                    $available_text
                );
            }
        }
        if (!empty($unavailable)) {
            $unavailable_verb = count(array_unique($unavailable)) === 1 ? __('is', 'geeky-bot') : __('are', 'geeky-bot');
            $parts[] = sprintf(
                /* translators: 1: unavailable values, 2: is/are. */
                __('%1$s %2$s currently out of stock.', 'geeky-bot'),
                ucfirst($this->human_join($unavailable)),
                $unavailable_verb
            );
        }
        return implode(' ', $parts);
    }

    public function variation_options($available, $unavailable) {
        $parts = array();
        if (!empty($available)) {
            $parts[] = sprintf(
                /* translators: %s: available option labels. */
                __('Available: %s.', 'geeky-bot'),
                $this->human_join($available)
            );
        }
        if (!empty($unavailable)) {
            $parts[] = sprintf(
                /* translators: %s: unavailable option labels. */
                __('Out of stock: %s.', 'geeky-bot'),
                $this->human_join($unavailable)
            );
        }
        return implode(' ', $parts);
    }

    public function variation_no_match() {
        return __("I couldn't find that exact option combination for this product.", 'geeky-bot');
    }

    public function variation_no_sale() {
        return __('None of the matching options is currently on sale.', 'geeky-bot');
    }

    public function variation_no_filtered_options() {
        return __("I couldn't find matching options for that request.", 'geeky-bot');
    }

    public function source_label($missing) {
        return $missing
            ? __('Checked store details', 'geeky-bot')
            : __('From store details', 'geeky-bot');
    }

    public function human_join($values, $conjunction = '') {
        $values = array_values(array_unique(array_filter(array_map(array($this, 'clean'), (array) $values))));
        $count = count($values);
        if ($count <= 1) {
            return $count ? $values[0] : '';
        }
        $conjunction = $conjunction !== '' ? $conjunction : __('and', 'geeky-bot');
        if ($count === 2) {
            return $values[0] . ' ' . $conjunction . ' ' . $values[1];
        }
        $last = array_pop($values);
        return implode(', ', $values) . ', ' . $conjunction . ' ' . $last;
    }

    private function fact_labels($keys) {
        $keys = array_values(array_unique((array) $keys));
        $combined_dimensions = in_array('dimensions', $keys, true) && in_array('weight', $keys, true);
        if ($combined_dimensions) {
            $keys = array_values(array_diff($keys, array('dimensions', 'weight')));
        }

        $map = array(
            'material' => __('the material', 'geeky-bot'),
            'water_protection' => __('water-protection information', 'geeky-bot'),
            'compatibility' => __('compatibility details', 'geeky-bot'),
            'use_case' => __('suitability details', 'geeky-bot'),
            'attribute_query' => __('that product detail', 'geeky-bot'),
            'dimensions' => __('the dimensions', 'geeky-bot'),
            'weight' => __('the weight', 'geeky-bot'),
            'warranty' => __('warranty information', 'geeky-bot'),
            'price' => __('the current price', 'geeky-bot'),
            'stock' => __('stock information', 'geeky-bot'),
            'colors' => __('available colors', 'geeky-bot'),
            'sizes' => __('available sizes', 'geeky-bot'),
            'sale' => __('sale information', 'geeky-bot'),
            'included' => __('included-item information', 'geeky-bot'),
            'battery' => __('battery-life information', 'geeky-bot'),
            'care' => __('care instructions', 'geeky-bot'),
            'leak_protection' => __('leak-protection information', 'geeky-bot'),
            'backlight' => __('backlight information', 'geeky-bot'),
            'capacity' => __('capacity options', 'geeky-bot'),
            'options' => __('product options', 'geeky-bot'),
            'summary' => __('a product summary', 'geeky-bot'),
        );
        $labels = $combined_dimensions
            ? array(__('the dimensions or weight', 'geeky-bot'))
            : array();
        foreach ($keys as $key) {
            if (isset($map[$key])) {
                $labels[] = $map[$key];
            }
        }
        return $labels;
    }

    private function normalize_warranty($value) {
        $value = $this->clean($value);
        if (preg_match('/^(\d+)\s+year\s+/iu', $value, $matches)) {
            $number_words = array('1' => 'one', '2' => 'two', '3' => 'three', '4' => 'four', '5' => 'five');
            if (isset($number_words[$matches[1]])) {
                $value = preg_replace('/^' . preg_quote($matches[1], '/') . '\s+year\s+/iu', $number_words[$matches[1]] . '-year ', $value);
            }
        }
        return $value;
    }

    private function clean_protection_detail($detail) {
        $detail = $this->clean($detail);
        $detail = preg_replace('/\bnot\s+waterproof\b/iu', '', $detail);
        $detail = preg_replace('/\bwaterproof\s*:\s*no\b/iu', '', $detail);
        $detail = preg_replace('/\s*;\s*/u', ', ', $detail);
        $detail = trim((string) preg_replace('/\s+/u', ' ', $detail), " ,.;:\t\n\r\0\x0B");
        if (preg_match('/\bIPX\d+\s+(?:water|splash)[-\s]?resistant\b/iu', $detail, $matches)) {
            return $matches[0];
        }
        if (preg_match('/\b(?:water|splash)[-\s]?resistant\b/iu', $detail, $matches)) {
            $detail = $matches[0];
        }
        if (preg_match('/^[A-Z][a-z]/u', $detail)) {
            $detail = lcfirst($detail);
        }
        return $detail;
    }

    /**
     * Adds a neutral article to a singular verified component phrase.
     */
    private function component_value($value) {
        $value = $this->sentence_value($value);
        if ($value === '' || preg_match('/^(?:a|an|the|\d)\b/iu', $value)) {
            return $value;
        }

        $article = preg_match('/^[aeiou]/iu', $value) ? 'an' : 'a';
        return $article . ' ' . $value;
    }

    /**
     * Makes a verified catalog phrase read naturally in the middle of a
     * sentence without changing acronyms such as ABS, BPA, IPX, or TPE.
     */
    private function sentence_value($value) {
        $value = $this->clean($value);
        if (preg_match('/^[A-Z][a-z]/u', $value)) {
            return lcfirst($value);
        }
        return $value;
    }

    private function clean($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        // Keep shopper-facing compound option names compact without changing
        // the stored WooCommerce attribute value (for example Red / White).
        return (string) preg_replace('/\s*\/\s*/u', '/', $value);
    }

    private function number($value) {
        $value = (float) $value;
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
