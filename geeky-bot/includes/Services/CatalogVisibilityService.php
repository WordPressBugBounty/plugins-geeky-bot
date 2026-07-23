<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central shopper-facing catalog visibility guard.
 *
 * Every discovery, Product Expert, comparison, recommendation, and product-card
 * path should use this service before exposing a WooCommerce product.
 */
final class CatalogVisibilityService {
    /**
     * Returns whether a product may be exposed by the storefront assistant.
     *
     * Products must be published, unprotected, and searchable in the storefront.
     * WooCommerce visibility "visible" and "search" are allowed. Products marked
     * "catalog" (excluded from search) or "hidden" are not assistant-discoverable.
     *
     * @param int|\WC_Product $product_or_id Product object or ID.
     * @param string          $purpose       Internal allowlisted purpose label.
     * @return bool
     */
    public static function is_visible($product_or_id, $purpose = 'assistant') {
        if (!function_exists('wc_get_product')) {
            return false;
        }

        $product = is_object($product_or_id) ? $product_or_id : wc_get_product(absint($product_or_id));
        if (!$product || !method_exists($product, 'get_id') || $product->get_status() !== 'publish') {
            return false;
        }

        $product_id = absint($product->get_id());
        if (!$product_id || get_post_status($product_id) !== 'publish') {
            return false;
        }

        $post = get_post($product_id);
        if (!$post || !empty($post->post_password)) {
            return false;
        }

        // Variations are never shown as standalone catalog products. Product
        // Expert reads child variation facts only through a visible parent.
        if (method_exists($product, 'is_type') && $product->is_type('variation')) {
            return false;
        }

        $visibility = method_exists($product, 'get_catalog_visibility')
            ? sanitize_key((string) $product->get_catalog_visibility())
            : 'visible';
        $allowed = array('visible', 'search');

        if (!in_array($visibility, $allowed, true)) {
            return false;
        }

        /**
         * Filters the final shopper-facing product visibility decision.
         *
         * This filter may make the guard stricter, but integrations should not
         * expose products hidden by WooCommerce visibility settings.
         *
         * @param bool        $visible Product is visible to the assistant.
         * @param \WC_Product $product Product object.
         * @param string      $purpose Internal purpose label.
         */
        return (bool) apply_filters(
            'geekybot_product_visible_to_shopper',
            true,
            $product,
            sanitize_key((string) $purpose)
        );
    }

    /**
     * Filters and de-duplicates product IDs through the central visibility guard.
     *
     * @param array  $product_ids Product IDs.
     * @param int    $limit       Maximum returned IDs. Zero means no limit.
     * @param string $purpose     Internal purpose label.
     * @return array
     */
    public static function filter_ids($product_ids, $limit = 0, $purpose = 'assistant') {
        $clean = array();
        foreach ((array) $product_ids as $product_id) {
            $product_id = absint($product_id);
            if (!$product_id || in_array($product_id, $clean, true) || !self::is_visible($product_id, $purpose)) {
                continue;
            }
            $clean[] = $product_id;
            if ($limit > 0 && count($clean) >= absint($limit)) {
                break;
            }
        }
        return $clean;
    }
}
