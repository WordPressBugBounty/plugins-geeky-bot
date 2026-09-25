<?php
namespace GeekyBot\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

use GeekyBot\Services\Settings;
use GeekyBot\Services\ShopperOutputService;
use GeekyBot\Services\StoreVisibilityService;

class Widget {
    /**
     * Product cards shown before the "show the rest" button appears.
     *
     * The widget draws the button, so this lives here rather than in the
     * script: the count in its label has to be translated in PHP, where the
     * locale's plural rules are, and PHP can only do that if it knows where
     * the list is cut.
     */
    const PRODUCT_CARDS_VISIBLE = 3;

    public function hooks() {
        add_action('wp_enqueue_scripts', array($this, 'assets'));
        add_action('wp_footer', array($this, 'render'));
    }

    /**
     * Cache-busting version for a storefront asset.
     *
     * Mirrors the admin behaviour: the plugin version alone leaves the URL
     * unchanged when a file is edited, so browsers keep the stale copy.
     *
     * @param string $relative_path Path relative to the plugin root.
     * @return string
     */
    private static function asset_version($relative_path) {
        $file = GEEKYBOT_PATH . ltrim($relative_path, '/');
        $stamp = file_exists($file) ? filemtime($file) : 0;

        return $stamp ? GEEKYBOT_VERSION . '.' . $stamp : GEEKYBOT_VERSION;
    }

    public function assets() {
        if (!$this->should_render()) {
            return;
        }

        // Versioned by file modification time, not the plugin version alone.
        // With only GEEKYBOT_VERSION, editing frontend.js without a version bump
        // left every browser on the cached copy — which is exactly why widget
        // settings appeared to have no effect on the storefront.
        wp_enqueue_style('geekybot-frontend', GEEKYBOT_URL . 'assets/css/frontend.css', array(), self::asset_version('assets/css/frontend.css'));
        wp_enqueue_script('geekybot-frontend', GEEKYBOT_URL . 'assets/js/frontend.js', array(), self::asset_version('assets/js/frontend.js'), true);

        wp_localize_script('geekybot-frontend', 'GeekyBotConfig', array(
            'restUrl' => esc_url_raw(rest_url('geekybot/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => Settings::public_settings(),
            'productsInitialLimit' => self::PRODUCT_CARDS_VISIBLE,
            'i18n' => array(
                'open' => __('Open shopping assistant', 'geeky-bot'),
                'close' => __('Close', 'geeky-bot'),
                'invitation' => __('Shopping assistant invitation', 'geeky-bot'),
                'dismissInvitation' => __('Dismiss shopping assistant invitation', 'geeky-bot'),
                'you' => __('You', 'geeky-bot'),
                'placeholder' => __('Ask about products, size, color, price, or use case…', 'geeky-bot'),
                'send' => __('Send', 'geeky-bot'),
                'viewProduct' => __('View product', 'geeky-bot'),
                'chooseOptions' => __('Choose options', 'geeky-bot'),
                'thinking' => __('Let me check that', 'geeky-bot'),
                'error' => __("I couldn't complete that request right now. Please try again.", 'geeky-bot'),
                'timeout' => __('The store is taking longer than expected. Please try again.', 'geeky-bot'),
                'sessionExpired' => __('Your shopping session expired. Please refresh the page and try again.', 'geeky-bot'),
                'rateLimited' => __('Too many requests were sent. Please wait a moment and try again.', 'geeky-bot'),
                'moreActions' => __('More actions', 'geeky-bot'),
                'clearConversation' => __('Clear conversation', 'geeky-bot'),
                'sourcesLabel' => __('Source:', 'geeky-bot'),
                'storePage' => __('Store page', 'geeky-bot'),
                'inStock' => __('In stock', 'geeky-bot'),
                'outOfStock' => __('Out of stock', 'geeky-bot'),
                'onBackorder' => __('On backorder', 'geeky-bot'),
                // One label per count the button can ever show, because the
                // count is only known in the browser and a JS `n === 1` test
                // picks the wrong form in every language with more than two:
                // Arabic has six, Russian three. _n() here uses the real rules
                // from the loaded translation.
                'showMore' => self::show_more_labels(),
                // Merchant-editable in Storefront Widget → Starter prompts.
                // Previously four hard-coded strings with no setting behind them.
                'suggestions' => Settings::starter_prompts(4),
            ),
        ));

        $accent = Settings::get('accent_color', '#2563eb');
        $css = '#geekybot-sales-assistant{--gb-accent:' . esc_html($accent) . ';}';
        wp_add_inline_style('geekybot-frontend', $css);
    }

    /**
     * Translated "show the rest" labels, keyed by how many cards stay hidden.
     *
     * A reply carries at most ShopperOutputService::MAX_PUBLIC_PRODUCTS cards
     * and the list reveals PRODUCT_CARDS_VISIBLE of them, so the hidden count
     * is always between 1 and the difference — a short, fully enumerable set.
     *
     * @return array<int,string>
     */
    private static function show_more_labels() {
        $labels = array();
        $max_hidden = ShopperOutputService::MAX_PUBLIC_PRODUCTS - self::PRODUCT_CARDS_VISIBLE;

        for ($hidden = 1; $hidden <= $max_hidden; $hidden++) {
            $labels[$hidden] = sprintf(
                /* translators: %s: number of product cards that are still hidden. */
                _n('Show %s more product', 'Show %s more products', $hidden, 'geeky-bot'),
                number_format_i18n($hidden)
            );
        }

        return $labels;
    }

    public function render() {
        if (!$this->should_render()) {
            return;
        }
        echo '<div id="geekybot-sales-assistant" data-position="' . esc_attr(Settings::get('button_position', 'right')) . '"></div>';
    }

    private function should_render() {
        if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
            return false;
        }
        // Not on a WooCommerce "Coming soon" store the visitor cannot see yet.
        if (StoreVisibilityService::store_hidden_from_visitor()) {
            return false;
        }
        return Settings::get('widget_enabled', 'yes') === 'yes';
    }
}
