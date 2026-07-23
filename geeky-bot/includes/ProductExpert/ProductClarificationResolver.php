<?php
namespace GeekyBot\ProductExpert;

use GeekyBot\Chat\PendingProductSelectionResolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Backward-compatible alias for the unified pending product-selection resolver.
 *
 * New code should depend on PendingProductSelectionResolver directly. Keeping
 * this class avoids breaking any integrations that instantiated the V1.9 class.
 */
class ProductClarificationResolver extends PendingProductSelectionResolver {
}
