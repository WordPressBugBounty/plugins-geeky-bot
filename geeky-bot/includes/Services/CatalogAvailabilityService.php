<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalizes WooCommerce availability for storefront cards, Product Expert,
 * and Commerce Pro comparison payloads.
 *
 * Variable parent stock metadata can be stale or intentionally unmanaged.
 * Shopper-facing availability therefore follows published child variations:
 * one in-stock variation makes the parent available; otherwise an allowed
 * backorder is surfaced before falling back to the parent stock status.
 */
final class CatalogAvailabilityService {
    public static function stock_status($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return 'outofstock';
        }

        $status = sanitize_key((string) $product->get_stock_status());
        if (!$product->is_type('variable') || !method_exists($product, 'get_children')) {
            return $status !== '' ? $status : 'outofstock';
        }

        $has_backorder = false;
        foreach ((array) $product->get_children() as $variation_id) {
            $variation = function_exists('wc_get_product') ? wc_get_product(absint($variation_id)) : null;
            if (!$variation
                || !$variation->is_type('variation')
                || $variation->get_status() !== 'publish') {
                continue;
            }

            if ($variation->is_in_stock()) {
                return 'instock';
            }

            if ($variation->backorders_allowed()) {
                $has_backorder = true;
            }
        }

        if ($has_backorder) {
            return 'onbackorder';
        }

        return $status !== '' ? $status : 'outofstock';
    }

    public static function is_available($product) {
        return in_array(self::stock_status($product), array('instock', 'onbackorder'), true);
    }
}
