<?php
namespace GeekyBot\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

use GeekyBot\Services\Settings;

class Widget {
    public function hooks() {
        add_action('wp_enqueue_scripts', array($this, 'assets'));
        add_action('wp_footer', array($this, 'render'));
    }

    public function assets() {
        if (!$this->should_render()) {
            return;
        }

        wp_enqueue_style('geekybot-frontend', GEEKYBOT_URL . 'assets/css/frontend.css', array(), GEEKYBOT_VERSION);
        wp_enqueue_script('geekybot-frontend', GEEKYBOT_URL . 'assets/js/frontend.js', array(), GEEKYBOT_VERSION, true);

        wp_localize_script('geekybot-frontend', 'GeekyBotConfig', array(
            'restUrl' => esc_url_raw(rest_url('geekybot/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => Settings::public_settings(),
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
                'suggestions' => array(
                    __('Latest products', 'geeky-bot'),
                    __('Sale products', 'geeky-bot'),
                    __('Top rated', 'geeky-bot'),
                    __('Products under 50', 'geeky-bot'),
                ),
            ),
        ));

        $accent = Settings::get('accent_color', '#2563eb');
        $css = '#geekybot-sales-assistant{--gb-accent:' . esc_html($accent) . ';}';
        wp_add_inline_style('geekybot-frontend', $css);
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
        return Settings::get('widget_enabled', 'yes') === 'yes';
    }
}
