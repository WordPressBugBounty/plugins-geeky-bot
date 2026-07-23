<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds grounded no-result explanations from the active discovery analysis.
 *
 * This service is presentation-only. It must not relax constraints, rerun a
 * search, or replace the preserved shopping mission.
 */
class ProductDiscoveryRecoveryService {
    public function no_match_message($product_search_context = array(), $action = '') {
        $context = is_array($product_search_context) ? $product_search_context : array();
        $analysis = !empty($context['analysis']) && is_array($context['analysis'])
            ? $context['analysis']
            : array();
        $action = $action !== ''
            ? sanitize_key((string) $action)
            : (!empty($context['conversationAction']) ? sanitize_key((string) $context['conversationAction']) : '');
        $requirements = $this->active_requirements($context, $analysis);
        $requested_label = !empty($context['requestedLabel'])
            ? trim(wp_strip_all_tags((string) $context['requestedLabel']))
            : '';
        $summary = $this->requirement_summary($requirements, !$this->is_generic_product_label($requested_label));

        if ($action === 'remove_constraint') {
            $command_meta = !empty($context['commandMeta']) && is_array($context['commandMeta'])
                ? $context['commandMeta']
                : array();
            $removed_count = !empty($command_meta['constraintCount'])
                ? absint($command_meta['constraintCount'])
                : (!empty($command_meta['constraintTypes']) && is_array($command_meta['constraintTypes'])
                    ? count($command_meta['constraintTypes'])
                    : 1);
            if ($summary !== '') {
                $message = sprintf(
                    /* translators: %s: remaining active shopping requirements. */
                    $removed_count > 1
                        ? /* translators: %s: remaining active shopping requirements. */
                        __("I removed those filters, but I still couldn't find a match for %s.", 'geeky-bot')
                        : /* translators: %s: remaining active shopping requirements. */
                        __("I removed that filter, but I still couldn't find a match for %s.", 'geeky-bot'),
                    $summary
                );
            } else {
                $message = $removed_count > 1
                    ? __("I removed those filters, but I still couldn't find a matching product.", 'geeky-bot')
                    : __("I removed that filter, but I still couldn't find a matching product.", 'geeky-bot');
            }
        } elseif (count($requirements) === 1) {
            $message = sprintf(
                /* translators: %s: one active shopping requirement. */
                __("I couldn't find a match for %s.", 'geeky-bot'),
                $summary
            );
        } elseif (count($requirements) > 1) {
            $message = sprintf(
                /* translators: %s: active shopping requirements. */
                __("I couldn't find a match for %s.", 'geeky-bot'),
                $summary
            );
        } else {
            $message = __("I couldn't find a product that matches your request.", 'geeky-bot');
        }

        if ($action === 'filter_current' && !empty($requirements)) {
            $message .= ' ' . __('I kept your original request unchanged.', 'geeky-bot');
        }

        $suggestion = $this->relaxation_suggestion($context, $analysis);
        if ($suggestion !== '') {
            $message .= ' ' . $suggestion;
        }

        return trim($message);
    }

    private function active_requirements($context, $analysis) {
        $requirements = array();
        $requested_label = !empty($context['requestedLabel'])
            ? trim(wp_strip_all_tags((string) $context['requestedLabel']))
            : '';

        if (!$this->is_generic_product_label($requested_label)) {
            $requirements[] = $requested_label;
        }

        $colors = $this->clean_labels(!empty($analysis['requested_color_labels']) ? $analysis['requested_color_labels'] : array());
        if (!empty($colors)) {
            $requirements[] = sprintf(
                /* translators: %s: requested colors. */
                __('in %s', 'geeky-bot'),
                $this->human_join($colors)
            );
        }

        $negative_colors = $this->clean_labels(!empty($analysis['negative_color_labels']) ? $analysis['negative_color_labels'] : array());
        if (!empty($negative_colors)) {
            $requirements[] = sprintf(
                /* translators: %s: excluded colors. */
                __('not in %s', 'geeky-bot'),
                $this->human_join($negative_colors)
            );
        }

        $sizes = $this->clean_labels(!empty($analysis['requested_size_labels']) ? $analysis['requested_size_labels'] : array());
        if (!empty($sizes)) {
            $requirements[] = sprintf(
                /* translators: %s: requested sizes. */
                __('in size %s', 'geeky-bot'),
                strtoupper($this->human_join($sizes))
            );
        }

        $negative_sizes = $this->clean_labels(!empty($analysis['negative_size_labels']) ? $analysis['negative_size_labels'] : array());
        if (!empty($negative_sizes)) {
            $requirements[] = sprintf(
                /* translators: %s: excluded sizes. */
                __('not in size %s', 'geeky-bot'),
                strtoupper($this->human_join($negative_sizes))
            );
        }

        $price_text = !empty($context['priceText'])
            ? trim(wp_strip_all_tags((string) $context['priceText']))
            : '';
        if ($price_text !== '') {
            $requirements[] = $price_text;
        }

        if ($this->requires_sale($analysis)) {
            $requirements[] = __('currently on sale', 'geeky-bot');
        }

        if (!empty($analysis['in_stock_only'])) {
            $requirements[] = __('in stock', 'geeky-bot');
        }

        $modifier_labels = $this->clean_labels(!empty($analysis['modifier_labels']) ? $analysis['modifier_labels'] : array());
        foreach (array_slice($modifier_labels, 0, 3) as $modifier_label) {
            $requirements[] = sprintf(
                /* translators: %s: shopper preference such as comfortable. */
                __('with %s', 'geeky-bot'),
                $modifier_label
            );
        }

        $audience = !empty($analysis['audience']) && is_array($analysis['audience']) ? $analysis['audience'] : array();
        if (!empty($audience['matched_phrase'])) {
            $requirements[] = trim(wp_strip_all_tags((string) $audience['matched_phrase']));
        }

        if (!empty($analysis['is_gift_request'])) {
            $requirements[] = __('suitable as a gift', 'geeky-bot');
        }

        return $this->unique_labels($requirements);
    }

    private function relaxation_suggestion($context, $analysis) {
        if ($this->requires_sale($analysis)) {
            return __('Try removing the sale filter.', 'geeky-bot');
        }

        if (!empty($analysis['price_range'])) {
            return __('Try a higher budget or remove the price limit.', 'geeky-bot');
        }

        if (!empty($analysis['requested_color_labels']) || !empty($analysis['negative_color_labels'])) {
            return __('Try another color or remove the color filter.', 'geeky-bot');
        }

        if (!empty($analysis['requested_size_labels']) || !empty($analysis['negative_size_labels'])) {
            return __('Try another size or remove the size filter.', 'geeky-bot');
        }

        if (!empty($analysis['in_stock_only'])) {
            return __('Try including products that are not currently in stock.', 'geeky-bot');
        }

        if (!empty($analysis['modifier_labels'])) {
            return __('Try removing one preference.', 'geeky-bot');
        }

        $requested_label = !empty($context['requestedLabel'])
            ? trim(wp_strip_all_tags((string) $context['requestedLabel']))
            : '';
        if (!$this->is_generic_product_label($requested_label)) {
            return __('Try a broader product name or a different type of product.', 'geeky-bot');
        }

        return __('Try a broader product search.', 'geeky-bot');
    }

    private function requires_sale($analysis) {
        return !empty($analysis['sale_required'])
            || (!empty($analysis['intent']) && $analysis['intent'] === 'sale');
    }

    private function is_generic_product_label($label) {
        $label = strtolower(trim((string) $label));
        return $label === '' || in_array($label, array('product', 'products', 'matching products', 'that product'), true);
    }

    private function clean_labels($labels) {
        $clean = array();
        foreach ((array) $labels as $label) {
            $label = trim(wp_strip_all_tags((string) $label));
            if ($label !== '') {
                $clean[] = $label;
            }
        }
        return $this->unique_labels($clean);
    }

    private function unique_labels($labels) {
        $unique = array();
        $seen = array();
        foreach ((array) $labels as $label) {
            $label = trim(wp_strip_all_tags((string) $label));
            $key = strtolower($label);
            if ($label === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $label;
        }
        return $unique;
    }

    private function requirement_summary($items, $starts_with_product) {
        $items = array_values(array_filter(array_map('trim', (array) $items)));
        if (empty($items)) {
            return '';
        }
        if (!$starts_with_product || count($items) === 1) {
            return $this->human_join($items);
        }

        $product = array_shift($items);
        return trim($product . ' ' . $this->human_join($items));
    }

    private function human_join($items) {
        $items = array_values(array_filter(array_map('trim', (array) $items)));
        $count = count($items);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $items[0];
        }
        if ($count === 2) {
            return sprintf(
                /* translators: 1: first item, 2: second item. */
                __('%1$s and %2$s', 'geeky-bot'),
                $items[0],
                $items[1]
            );
        }

        $last = array_pop($items);
        return sprintf(
            /* translators: 1: comma-separated items, 2: final item. */
            __('%1$s, and %2$s', 'geeky-bot'),
            implode(', ', $items),
            $last
        );
    }
}
