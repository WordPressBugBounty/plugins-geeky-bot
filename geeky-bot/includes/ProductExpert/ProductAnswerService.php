<?php
namespace GeekyBot\ProductExpert;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Produces deterministic shopper-facing answers from normalized product facts.
 * AI is not permitted to modify or invent these facts.
 */
class ProductAnswerService {
    private $facts_service;
    private $formatter;

    public function __construct(?ProductFactsService $facts_service = null, ?ProductAnswerFormatter $formatter = null) {
        $this->facts_service = $facts_service ?: new ProductFactsService();
        $this->formatter = $formatter ?: new ProductAnswerFormatter();
    }

    public function answer($message, $route, $facts) {
        $message = wp_strip_all_tags((string) $message);
        $route = is_array($route) ? $route : array();
        $facts = is_array($facts) ? $facts : array();
        if (empty($facts['id']) || empty($facts['name'])) {
            return $this->result('', true, array(), array());
        }

        $requested = $this->requested_attributes($message, $facts);
        $variation_answer = $this->variation_answer($message, $route, $facts, $requested);
        if (!empty($variation_answer['message'])) {
            return $variation_answer;
        }

        $fact_keys = !empty($route['facts']) ? array_values(array_unique((array) $route['facts'])) : array('summary');
        $parts = array();
        $answered = array();
        $missing = array();

        // Dimensions and weight are naturally answered together, but each
        // requested fact is tracked independently so a partial record is not
        // presented as fully complete.
        if (in_array('dimensions', $fact_keys, true) || in_array('weight', $fact_keys, true)) {
            $dimension_part = $this->dimensions_and_weight($facts, $fact_keys);
            if ($dimension_part !== '') {
                $parts[] = $dimension_part;
            }

            $dimensions = !empty($facts['dimensions']) && is_array($facts['dimensions'])
                ? $facts['dimensions']
                : array();
            $has_dimensions = isset($dimensions['length'], $dimensions['width'], $dimensions['height'])
                && $dimensions['length'] !== null
                && $dimensions['width'] !== null
                && $dimensions['height'] !== null;
            $has_weight = array_key_exists('weight', $facts) && $facts['weight'] !== null;

            if (in_array('dimensions', $fact_keys, true)) {
                if ($has_dimensions) {
                    $answered[] = 'dimensions';
                } else {
                    $missing[] = 'dimensions';
                }
            }
            if (in_array('weight', $fact_keys, true)) {
                if ($has_weight) {
                    $answered[] = 'weight';
                } else {
                    $missing[] = 'weight';
                }
            }
        }

        foreach ($fact_keys as $fact_key) {
            if (in_array($fact_key, array('dimensions', 'weight'), true)) {
                continue;
            }
            $part = $this->answer_fact($fact_key, $message, $facts);
            if ($part !== '') {
                $parts[] = $part;
                $answered[] = $fact_key;
            } else {
                $missing[] = $fact_key;
            }
        }

        $parts = array_values(array_unique(array_filter($parts)));
        $answered = array_values(array_unique(array_filter($answered)));
        $missing = array_values(array_unique(array_filter($missing)));

        if (empty($parts)) {
            return $this->result(
                $this->formatter->missing(!empty($missing) ? $missing : $fact_keys),
                true,
                array(),
                !empty($missing) ? $missing : $fact_keys
            );
        }

        $message_text = implode(' ', $parts);
        if (!empty($missing)) {
            $partial_missing = $this->formatter->partial_missing($missing);
            if ($partial_missing !== '') {
                $message_text .= ' ' . $partial_missing;
            }
        }

        return $this->result($message_text, !empty($missing), $answered, $missing);
    }

    private function answer_fact($fact_key, $message, $facts) {
        switch ($fact_key) {
            case 'material':
                return $this->material($facts);
            case 'water_protection':
                return $this->water_protection($message, $facts);
            case 'compatibility':
                return $this->compatibility($facts);
            case 'use_case':
                return $this->use_case($message, $facts);
            case 'attribute_query':
                return $this->dynamic_attribute($message, $facts);
            case 'warranty':
                return $this->warranty($facts);
            case 'price':
                return $this->price($facts);
            case 'stock':
                return $this->stock($facts);
            case 'colors':
                return $this->attribute_list_answer($facts, array('color', 'colour'), __('colors', 'geeky-bot'));
            case 'sizes':
                return $this->attribute_list_answer($facts, array('size'), __('sizes', 'geeky-bot'));
            case 'sale':
                return $this->sale($facts);
            case 'included':
                return $this->included($message, $facts);
            case 'battery':
                return $this->battery($facts);
            case 'care':
                return $this->care($message, $facts);
            case 'leak_protection':
                return $this->leak_protection($facts);
            case 'backlight':
                return $this->backlight($facts);
            case 'capacity':
                return $this->attribute_list_answer($facts, array('capacity', 'volume'), __('capacity options', 'geeky-bot'));
            case 'options':
                return $this->general_options($facts);
            case 'summary':
                return $this->summary($facts);
        }
        return '';
    }

    private function material($facts) {
        $value = $this->facts_service->attribute_value($facts, array('material', 'fabric', 'composition'));
        if ($value !== '') {
            return $this->formatter->material($value);
        }

        $sentence = $this->facts_service->source_sentence($facts, array('made from', 'made of', 'material'));
        return $sentence !== '' ? $this->formatter->verified_sentence($sentence, $facts['name']) : '';
    }

    private function water_protection($message, $facts) {
        $value = $this->facts_service->attribute_value($facts, array('water protection', 'waterproof', 'water resistance', 'water resistant'));
        $source = trim($value . ' ' . ($facts['sourceText'] ?? ''));
        $lower = $this->normalize($source);

        if ($source === '') {
            return '';
        }
        if (preg_match('/\b(not\s+waterproof|waterproof\s*:\s*no|waterproof\s+no)\b/u', $lower)) {
            $detail = $value !== '' ? $value : $this->facts_service->source_sentence($facts, array('not waterproof', 'water-resistant', 'water resistant', 'ipx'));
            $message_lower = $this->normalize($message);
            $warn_against_submersion = preg_match('/\b(submerge|submerged|submersion|immerse|immersed)\b/u', $message_lower) === 1
                && preg_match('/\b(should\s+not\s+be\s+submerged|do\s+not\s+submerge|not\s+for\s+submersion|must\s+not\s+be\s+submerged)\b/u', $lower) === 1;
            return $this->formatter->waterproof_no($detail, $warn_against_submersion);
        }
        if (preg_match('/\b(ipx\d|water[-\s]?resistant|splash[-\s]?resistant)\b/u', $lower)) {
            $detail = $value !== '' ? $value : $this->facts_service->source_sentence($facts, array('ipx', 'water-resistant', 'water resistant', 'splash'));
            return $this->formatter->water_resistant_only($detail);
        }
        if (preg_match('/\bwaterproof\b/u', $lower) && !preg_match('/\bnot\s+waterproof\b/u', $lower)) {
            $detail = $value !== '' ? $value : $this->facts_service->source_sentence($facts, array('waterproof'));
            return $this->formatter->waterproof_yes($detail);
        }
        if (preg_match('/\b(no|none)\b/u', $lower)) {
            return $this->formatter->waterproof_no('');
        }
        return '';
    }

    private function compatibility($facts) {
        $value = $this->facts_service->attribute_value($facts, array('compatibility', 'compatible devices', 'device'));
        if ($value !== '') {
            return $this->formatter->compatibility($value);
        }
        $sentence = $this->facts_service->source_sentence($facts, array('compatible', 'fits laptops', 'fits laptop', 'designed for'));
        return $sentence !== '' ? $this->formatter->verified_sentence($sentence, $facts['name']) : '';
    }

    private function use_case($message, $facts) {
        $values = $this->facts_service->attribute_values($facts, array('use case', 'intended use', 'recommended use'));
        $source = !empty($values)
            ? $this->formatter->human_join($values)
            : $this->facts_service->source_sentence($facts, array('office', 'travel', 'daily use', 'gym', 'sports'));
        if ($source === '') {
            return '';
        }

        $message_lower = $this->normalize($message);
        $source_lower = $this->normalize($source);
        $requested = array('office', 'travel', 'daily use', 'gym', 'sports', 'exercise', 'running');
        foreach ($requested as $use_case) {
            if (strpos($message_lower, $use_case) === false) {
                continue;
            }
            if (strpos($source_lower, $use_case) !== false) {
                return $this->formatter->use_case_yes($use_case);
            }
            return $this->formatter->use_case_unconfirmed($use_case, $source);
        }

        return $this->formatter->use_cases($source);
    }

    private function dynamic_attribute($message, $facts) {
        $message_normalized = $this->canonical_value($message);
        $best = array();
        foreach ((array) ($facts['attributes'] ?? array()) as $attribute) {
            if (empty($attribute['label']) || empty($attribute['values'])) {
                continue;
            }
            $label = wp_strip_all_tags((string) $attribute['label']);
            $label_normalized = $this->canonical_value($label);
            $score = 0;
            if ($label_normalized !== '' && $this->contains_value($message_normalized, $label_normalized)) {
                $score += 50;
            }
            $label_tokens = array_values(array_filter(preg_split('/\s+/u', $label_normalized)));
            foreach ($label_tokens as $token) {
                if (strlen($token) >= 4 && $this->contains_value($message_normalized, $token)) {
                    $score += 8;
                }
            }

            $aliases = array(
                'voltage conversion' => array('convert voltage', 'converts voltage'),
                'wireless charging' => array('wireless charging', 'charge wirelessly'),
                'rfid protection' => array('rfid', 'rfid blocking'),
                'latex free' => array('latex free', 'contains latex'),
                'microwave safe' => array('microwave safe'),
            );
            if (isset($aliases[$label_normalized])) {
                foreach ($aliases[$label_normalized] as $alias) {
                    if (strpos($message_normalized, $this->canonical_value($alias)) !== false) {
                        $score += 45;
                    }
                }
            }

            if ($score > 0 && (empty($best) || $score > $best['score'])) {
                $best = array(
                    'score' => $score,
                    'label' => $label,
                    'values' => array_values(array_filter(array_map('wp_strip_all_tags', (array) $attribute['values']))),
                );
            }
        }

        if (empty($best['values'])) {
            return '';
        }
        $value = $this->formatter->human_join($best['values']);
        $normalized_value = $this->normalize($value);
        if (preg_match('/^(no|false|not available|none)$/u', trim($normalized_value))) {
            return $this->formatter->attribute_boolean($best['label'], false);
        }
        if (preg_match('/^(yes|true)$/u', trim($normalized_value))) {
            return $this->formatter->attribute_boolean($best['label'], true);
        }
        return $this->formatter->attribute_value($best['label'], $value);
    }

    private function dimensions_and_weight($facts, $requested_keys) {
        $dimensions = !empty($facts['dimensions']) && is_array($facts['dimensions']) ? $facts['dimensions'] : array();
        return $this->formatter->dimensions_and_weight(
            $dimensions,
            array_key_exists('weight', $facts) ? $facts['weight'] : null,
            $dimensions['unit'] ?? 'cm',
            $facts['weightUnit'] ?? 'kg',
            in_array('dimensions', $requested_keys, true),
            in_array('weight', $requested_keys, true)
        );
    }

    private function warranty($facts) {
        $value = $this->facts_service->attribute_value($facts, array('warranty', 'guarantee'));
        if ($value !== '') {
            return $this->formatter->warranty($value);
        }
        $sentence = $this->facts_service->source_sentence($facts, array('warranty', 'guarantee'));
        return $sentence !== '' ? $this->formatter->verified_sentence($sentence, $facts['name']) : '';
    }

    private function price($facts) {
        return $this->formatter->price($facts, $this->facts_service);
    }

    private function stock($facts) {
        $quantity = $facts['stockQuantity'] ?? null;
        return $this->formatter->stock(
            !empty($facts['isInStock']),
            !empty($facts['backordersAllowed']),
            !empty($facts['backordersRequireNotification']),
            $quantity !== null && $quantity <= 0
        );
    }

    private function attribute_list_answer($facts, $aliases, $noun) {
        $values = $this->facts_service->attribute_values($facts, $aliases);
        return $this->formatter->attribute_list($noun, $values);
    }

    private function sale($facts) {
        return $this->formatter->sale($facts, $this->facts_service);
    }

    private function included($message, $facts) {
        $included = $this->facts_service->attribute_value($facts, array('included', 'in the box', 'package contents', 'accessories'));
        $sentence = $this->facts_service->source_sentence($facts, array('not included', 'included', 'comes with', 'includes'));
        $source = trim($included . ' ' . $sentence);
        if ($source === '') {
            return '';
        }

        $lower = $this->normalize($source);
        $message_lower = $this->normalize($message);
        $asked_cable = preg_match('/\bcable\b/u', $message_lower) === 1;
        if ($asked_cable && preg_match('/\bcable\b.*\bnot\s+included\b|\bnot\s+included\b.*\bcable\b/u', $lower)) {
            return $this->formatter->cable_included(false);
        }
        if ($asked_cable && preg_match('/\bcable\b.*\bincluded\b|\bincluded\b.*\bcable\b/u', $lower)) {
            return $this->formatter->cable_included(true);
        }
        if ($included !== '') {
            return $this->formatter->included_items($included);
        }
        return $this->formatter->verified_sentence($sentence, $facts['name']);
    }

    private function battery($facts) {
        $value = $this->facts_service->attribute_value($facts, array('battery life', 'battery', 'playback', 'runtime'));
        if ($value !== '') {
            return $this->formatter->battery($value);
        }
        $sentence = $this->facts_service->source_sentence($facts, array('hours of playback', 'battery', 'playback'));
        return $sentence !== '' ? $this->formatter->verified_sentence($sentence, $facts['name']) : '';
    }

    private function care($message, $facts) {
        $value = $this->facts_service->attribute_value($facts, array('care', 'washing', 'cleaning', 'microwave safe', 'dishwasher safe'));
        $message_lower = $this->normalize($message);
        $keywords = array('dishwasher', 'machine wash', 'hand wash', 'microwave', 'wipe clean', 'care');
        $sentence = $this->facts_service->source_sentence($facts, $keywords);
        $source = trim($value . ' ' . $sentence);
        if ($source === '') {
            return '';
        }

        $lower = $this->normalize($source);
        if (strpos($message_lower, 'dishwasher') !== false) {
            if (preg_match('/\b(bottle|body)\b.*\bdishwasher\s+safe\b/u', $lower) && preg_match('/\blid\b.*\bhand\s+wash/u', $lower)) {
                return $this->formatter->dishwasher_split();
            }
            if (preg_match('/\bnot\s+dishwasher\s+safe\b/u', $lower)) {
                return $this->formatter->not_dishwasher_safe();
            }
        }
        if (strpos($message_lower, 'microwave') !== false && preg_match('/\b(no|not\s+microwave\s+safe)\b/u', $lower)) {
            return $this->formatter->not_microwave_safe();
        }
        return $this->formatter->care($value !== '' ? $value : $sentence);
    }

    private function leak_protection($facts) {
        $value = $this->facts_service->attribute_value($facts, array('leak protection', 'leak proof', 'leak resistant'));
        $sentence = $this->facts_service->source_sentence($facts, array('not leak-proof', 'not leak proof', 'leak-resistant', 'leak resistant'));
        $source = trim($value . ' ' . $sentence);
        if ($source === '') {
            return '';
        }
        $lower = $this->normalize($source);
        if (preg_match('/\bnot\s+leak\s*proof\b/u', $lower)) {
            return $this->formatter->not_leak_proof();
        }
        return $this->formatter->leak_protection($value !== '' ? $value : $sentence);
    }

    private function backlight($facts) {
        $value = $this->facts_service->attribute_value($facts, array('backlight', 'backlit'));
        if ($value === '') {
            $value = $this->facts_service->source_sentence($facts, array('backlight', 'backlit'));
        }
        if ($value === '') {
            return '';
        }
        return $this->formatter->backlight(!preg_match('/\b(no|not|without)\b/u', $this->normalize($value)));
    }

    private function general_options($facts) {
        $attributes = array();
        foreach ((array) ($facts['attributes'] ?? array()) as $attribute) {
            if (empty($attribute['label']) || empty($attribute['values'])) {
                continue;
            }
            $attributes[] = $attribute['label'] . ': ' . $this->formatter->human_join($attribute['values']);
        }
        if (empty($attributes)) {
            return '';
        }
        return $this->formatter->options(array_slice($attributes, 0, 5));
    }

    private function summary($facts) {
        $description = !empty($facts['shortDescription']) ? $facts['shortDescription'] : ($facts['description'] ?? '');
        return $this->formatter->summary(
            $description,
            $this->formatter->price($facts, $this->facts_service),
            !empty($facts['isInStock'])
        );
    }

    private function variation_answer($message, $route, $facts, $requested) {
        if (($facts['type'] ?? '') !== 'variable' || empty($facts['variations'])) {
            return array();
        }

        $route_facts = (array) ($route['facts'] ?? array());
        $is_variation_question = !empty($requested)
            || !empty($route['asksExactVariation'])
            || !empty($route['asksAvailableOptions'])
            || array_intersect($route_facts, array('colors', 'sizes', 'options', 'sale'));
        if (!$is_variation_question) {
            return array();
        }

        $target_key = '';
        if (in_array('colors', $route_facts, true)) {
            $target_key = 'color';
        } elseif (in_array('sizes', $route_facts, true)) {
            $target_key = 'size';
        }

        if ($target_key !== ''
            && !empty($route['asksAvailableOptions'])
            && !in_array('sale', $route_facts, true)
            && !in_array('price', $route_facts, true)) {
            return $this->variation_dimension_availability($facts, $requested, $target_key);
        }

        if (in_array('options', $route_facts, true) && empty($requested)) {
            return $this->variation_options_overview($facts);
        }

        $matching = $this->matching_variations($facts['variations'], $requested);

        if ($target_key !== ''
            && count($requested) === 1
            && isset($requested[$target_key])
            && empty($route['asksAvailableOptions'])
            && empty(array_intersect($route_facts, array('sale', 'price', 'stock', 'options')))) {
            $display_value = !empty($matching)
                ? $this->variation_attribute_value($matching[0], $target_key)
                : strtoupper((string) $requested[$target_key]);
            $label = $target_key === 'size'
                ? sprintf(
                    /* translators: %s: product size value. */
                    __('size %s', 'geeky-bot'),
                    $display_value
                )
                : $display_value;
            $available = array_values(array_filter($matching, function ($variation) {
                return !empty($variation['isInStock']);
            }));
            if (!empty($available)) {
                return $this->result(
                    $this->formatter->variation_exact($label, 'in_stock'),
                    false,
                    array('variation_availability'),
                    array()
                );
            }
            if (!empty($matching)) {
                return $this->result(
                    $this->formatter->variation_exact($label, 'out_of_stock'),
                    false,
                    array('variation_availability'),
                    array()
                );
            }
        }

        if (!empty($requested) && empty($matching)) {
            return $this->result(
                $this->formatter->variation_no_match(),
                false,
                array('variation_availability'),
                array()
            );
        }

        if (in_array('sale', $route_facts, true)) {
            $pool = !empty($matching) ? $matching : $facts['variations'];
            $sale_rows = array_values(array_filter($pool, function ($variation) {
                return !empty($variation['isOnSale']);
            }));
            if (empty($sale_rows)) {
                return $this->result(
                    $this->formatter->variation_no_sale(),
                    false,
                    array('variation_sale'),
                    array()
                );
            }
            if (count($sale_rows) === 1) {
                return $this->result(
                    $this->formatter->variation_sale(
                        $this->variation_label($sale_rows[0]),
                        $sale_rows[0],
                        $this->facts_service
                    ),
                    false,
                    array('variation_sale'),
                    array()
                );
            }
            $labels = array();
            foreach ($sale_rows as $variation) {
                $labels[] = $this->variation_label($variation) . ' — ' . ($variation['priceText'] ?? '');
            }
            return $this->result(
                sprintf(
                    /* translators: %s: comma-separated sale variation labels and prices. */
                    __('On sale: %s.', 'geeky-bot'),
                    $this->formatter->human_join($labels)
                ),
                false,
                array('variation_sale'),
                array()
            );
        }

        if (!empty($matching)) {
            if (count($matching) === 1) {
                $variation = $matching[0];
                $label = $this->variation_label($variation);
                $state = !empty($variation['isInStock'])
                    ? 'in_stock'
                    : (!empty($variation['backordersAllowed']) ? 'backorder' : 'out_of_stock');
                $price_sentence = in_array('price', $route_facts, true)
                    ? $this->formatter->variation_price($variation, $this->facts_service)
                    : '';
                return $this->result(
                    $this->formatter->variation_exact($label, $state, $price_sentence),
                    false,
                    array('variation_availability'),
                    array()
                );
            }

            $available = array();
            $unavailable = array();
            foreach ($matching as $variation) {
                $row = $this->variation_label($variation);
                if (!empty($variation['priceText'])) {
                    $row .= ' — ' . $variation['priceText'];
                }
                if (!empty($variation['isInStock'])) {
                    $available[] = $row;
                } else {
                    $unavailable[] = $row;
                }
            }
            return $this->result(
                $this->formatter->variation_options($available, $unavailable),
                false,
                array('variation_availability'),
                array()
            );
        }

        return array();
    }

    private function variation_dimension_availability($facts, $requested, $target_key) {
        $filter = $requested;
        unset($filter[$target_key]);
        $pool = $this->matching_variations($facts['variations'], $filter);
        if (empty($pool)) {
            return $this->result(
                $this->formatter->variation_no_filtered_options(),
                false,
                array('variation_availability'),
                array()
            );
        }

        $available = array();
        $unavailable = array();
        foreach ($pool as $variation) {
            $value = $this->variation_attribute_value($variation, $target_key);
            if ($value === '') {
                continue;
            }
            if (!empty($variation['isInStock'])) {
                $available[] = $value;
            } else {
                $unavailable[] = $value;
            }
        }
        $available = array_values(array_unique($available));
        $unavailable = array_values(array_unique(array_diff(array_unique($unavailable), $available)));
        if (empty($available) && empty($unavailable)) {
            return array();
        }

        $context = array();
        foreach ($filter as $filter_key => $filter_value) {
            $actual = $this->variation_attribute_value($pool[0], $filter_key);
            if ($actual === '') {
                $actual = strtoupper((string) $filter_value);
            }
            if ($filter_key === 'size') {
                $context[] = sprintf(
                    /* translators: %s: product size value. */
                    __('size %s', 'geeky-bot'),
                    $actual
                );
            } else {
                $context[] = $actual;
            }
        }

        return $this->result(
            $this->formatter->variation_available_values(
                $target_key,
                $this->formatter->human_join($context),
                $available,
                $unavailable
            ),
            false,
            array('variation_availability'),
            array()
        );
    }

    private function variation_options_overview($facts) {
        $available = array();
        $unavailable = array();
        foreach ((array) $facts['variations'] as $variation) {
            $label = $this->variation_label($variation);
            if ($label === '') {
                continue;
            }
            if (!empty($variation['isInStock'])) {
                $available[] = $label;
            } else {
                $unavailable[] = $label;
            }
        }
        return $this->result(
            $this->formatter->variation_options($available, $unavailable),
            false,
            array('variation_options'),
            array()
        );
    }

    private function requested_attributes($message, $facts) {
        $message_normalized = $this->canonical_value($message);
        $requested = array();
        $available = array();

        foreach ((array) ($facts['attributes'] ?? array()) as $attribute) {
            $key = $this->canonical_attribute_key($attribute['label'] ?? ($attribute['key'] ?? ''));
            if ($key === '' || empty($attribute['values'])) {
                continue;
            }
            foreach ((array) $attribute['values'] as $value) {
                $available[$key][] = (string) $value;
            }
        }
        foreach ((array) ($facts['variations'] ?? array()) as $variation) {
            foreach ((array) ($variation['attributes'] ?? array()) as $attribute) {
                $key = $this->canonical_attribute_key($attribute['label'] ?? ($attribute['key'] ?? ''));
                if ($key !== '' && !empty($attribute['value'])) {
                    $available[$key][] = (string) $attribute['value'];
                }
            }
        }

        foreach ($available as $key => $values) {
            foreach (array_values(array_unique($values)) as $value) {
                $canonical = $this->canonical_value($value);
                if ($canonical !== '' && $this->contains_value($message_normalized, $canonical)) {
                    $requested[$key] = $canonical;
                    break;
                }
                if ($key === 'size') {
                    $size_aliases = $this->size_aliases($canonical);
                    foreach ($size_aliases as $alias) {
                        if ($this->contains_value($message_normalized, $alias)) {
                            $requested[$key] = $canonical;
                            break 2;
                        }
                    }
                }
            }
        }

        return $requested;
    }

    private function matching_variations($variations, $requested) {
        $requested = is_array($requested) ? $requested : array();
        $rows = array();
        foreach ((array) $variations as $variation) {
            $matches = true;
            foreach ($requested as $requested_key => $requested_value) {
                $actual = $this->canonical_value($this->variation_attribute_value($variation, $requested_key));
                if ($actual === '' || $actual !== $requested_value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $rows[] = $variation;
            }
        }
        return $rows;
    }

    private function variation_attribute_value($variation, $target_key) {
        foreach ((array) ($variation['attributes'] ?? array()) as $attribute) {
            $key = $this->canonical_attribute_key($attribute['label'] ?? ($attribute['key'] ?? ''));
            if ($key === $target_key) {
                return (string) ($attribute['value'] ?? '');
            }
        }
        return '';
    }

    private function variation_label($variation) {
        $values = array();
        foreach ((array) ($variation['attributes'] ?? array()) as $attribute) {
            if (empty($attribute['value'])) {
                continue;
            }
            $key = $this->canonical_attribute_key($attribute['label'] ?? ($attribute['key'] ?? ''));
            $values[$key] = wp_strip_all_tags((string) $attribute['value']);
        }

        if (!empty($values['color']) && !empty($values['size'])) {
            return sprintf(
                /* translators: 1: color, 2: size. */
                __('%1$s in size %2$s', 'geeky-bot'),
                $values['color'],
                $values['size']
            );
        }
        if (!empty($values['color']) && !empty($values['device'])) {
            return sprintf(
                /* translators: 1: color, 2: device/model. */
                __('%1$s for %2$s', 'geeky-bot'),
                $values['color'],
                $values['device']
            );
        }
        if (!empty($values['color']) && !empty($values['capacity'])) {
            return sprintf(
                /* translators: 1: color, 2: capacity. */
                __('%1$s in %2$s', 'geeky-bot'),
                $values['color'],
                $values['capacity']
            );
        }
        if (!empty($values['size']) && count($values) === 1) {
            return sprintf(
                /* translators: %s: product size value. */
                __('size %s', 'geeky-bot'),
                $values['size']
            );
        }
        return $this->formatter->human_join(array_values($values));
    }

    private function canonical_attribute_key($value) {
        $value = $this->canonical_value($value);
        if (preg_match('/\b(colour|color)\b/u', $value)) {
            return 'color';
        }
        if (preg_match('/\bsize\b/u', $value)) {
            return 'size';
        }
        if (preg_match('/\b(device|model|compatibility)\b/u', $value)) {
            return 'device';
        }
        if (preg_match('/\b(capacity|volume)\b/u', $value)) {
            return 'capacity';
        }
        return sanitize_key(str_replace(' ', '_', $value));
    }

    private function canonical_value($value) {
        $value = $this->facts_service->normalize_value($value);
        $value = preg_replace('/\b(and|the|a|an)\b/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function contains_value($message, $value) {
        if ($value === '') {
            return false;
        }
        return preg_match('/(?:^|\s)' . preg_quote($value, '/') . '(?:$|\s)/u', $message) === 1;
    }

    private function size_aliases($canonical) {
        $map = array(
            's' => array('small'),
            'm' => array('medium'),
            'l' => array('large'),
            'xl' => array('extra large', 'extra-large'),
            'xxl' => array('double extra large', '2xl'),
        );
        return isset($map[$canonical]) ? $map[$canonical] : array();
    }

    private function result($message, $missing, $answered, $missing_keys) {
        return array(
            'message' => trim((string) $message),
            'missing' => (bool) $missing,
            'answeredFacts' => array_values(array_unique(array_filter((array) $answered))),
            'missingFacts' => array_values(array_unique(array_filter((array) $missing_keys))),
        );
    }

    private function human_join($values) {
        $values = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $values))));
        $count = count($values);
        if ($count <= 1) {
            return $count ? $values[0] : '';
        }
        if ($count === 2) {
            return $values[0] . ' ' . __('and', 'geeky-bot') . ' ' . $values[1];
        }
        $last = array_pop($values);
        return implode(', ', $values) . ', ' . __('and', 'geeky-bot') . ' ' . $last;
    }

    private function number($value) {
        $value = (float) $value;
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function normalize($value) {
        $value = strtolower(remove_accents(wp_strip_all_tags((string) $value)));
        $value = str_replace(array('–', '—', '_', '-'), array(' ', ' ', ' ', ' '), $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
