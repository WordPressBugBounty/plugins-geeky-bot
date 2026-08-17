<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {
    const OPTION = 'geekybot_v2_settings';

    public static function defaults() {
        return array(
            'widget_enabled' => 'yes',
            'assistant_name' => 'Geeky Bot',
            'assistant_subtitle' => 'WooCommerce shopping assistant',
            'welcome_message' => 'Hi! Ask me what you are looking for and I will help you find the right product.',
            'accent_color' => '#2563eb',
            'button_position' => 'right',
            'launcher_icon_source' => 'default',
            'launcher_icon_attachment_id' => 0,
            'header_logo_source' => 'same',
            'header_logo_attachment_id' => 0,
            'launcher_style' => 'icon',
            'launcher_text' => 'Ask about products',
            'shopper_invitation_enabled' => 'yes',
            'shopper_invitation_delay' => 12,
            'shopper_invitation_message' => 'Need help choosing? Ask me about products, prices, or options.',
            'header_style' => 'gradient',
            'max_products' => 4,
            'chat_history_enabled' => 'yes',
            'retention_days' => 30,
            'policy_page_ids' => array(),
            'allow_guest_sessions' => 'yes',
            'provider_mode' => 'local',
            'zywrap_endpoint' => '',
            'zywrap_api_key' => '',
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'ai_max_tokens' => 450,
            'rate_limit_messages' => 120,
            'rate_limit_window_minutes' => 5,
            'fallback_human_message' => 'The store has not provided enough information for me to answer that. Please contact the store team for confirmation.',
            'natural_search_enabled' => 'yes',
            'search_close_match_mode' => 'smart',
            'search_boost_in_stock' => 'yes',
            'search_boost_sale' => 'yes',
            'search_boost_rating' => 'yes',
            'search_boost_popularity' => 'yes',
            'search_min_score' => 1,
            'search_custom_synonyms' => "comfortable = soft, cushioned, walking
not expensive = budget, affordable, low price
trainers = sneakers, shoes",
            'delete_data_on_uninstall' => get_option('geekybot_delete_data_on_uninstall', 'no') === 'yes' ? 'yes' : 'no',
            'last_updated' => '',
        );
    }

    public static function all() {
        $saved = get_option(self::OPTION, array());
        if (!is_array($saved)) {
            $saved = array();
        }
        return wp_parse_args($saved, self::defaults());
    }

    public static function get($key, $default = null) {
        $settings = self::all();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function update($input) {
        $current = self::all();
        $clean = array();

        $clean['widget_enabled'] = isset($input['widget_enabled']) && $input['widget_enabled'] === 'yes' ? 'yes' : 'no';
        $clean['assistant_name'] = isset($input['assistant_name']) ? sanitize_text_field($input['assistant_name']) : $current['assistant_name'];
        $clean['assistant_subtitle'] = isset($input['assistant_subtitle']) ? sanitize_text_field($input['assistant_subtitle']) : $current['assistant_subtitle'];
        $clean['welcome_message'] = isset($input['welcome_message']) ? sanitize_textarea_field($input['welcome_message']) : $current['welcome_message'];
        $clean['accent_color'] = isset($input['accent_color']) ? sanitize_hex_color($input['accent_color']) : $current['accent_color'];
        if (!$clean['accent_color']) {
            $clean['accent_color'] = self::defaults()['accent_color'];
        }

        $clean['button_position'] = isset($input['button_position']) && in_array($input['button_position'], array('left', 'right'), true) ? $input['button_position'] : 'right';
        $clean['launcher_icon_source'] = isset($input['launcher_icon_source']) && in_array($input['launcher_icon_source'], array('default', 'store_logo', 'custom'), true) ? $input['launcher_icon_source'] : $current['launcher_icon_source'];
        $clean['launcher_icon_attachment_id'] = isset($input['launcher_icon_attachment_id']) ? self::sanitize_image_attachment_id($input['launcher_icon_attachment_id']) : absint($current['launcher_icon_attachment_id']);
        $clean['header_logo_source'] = isset($input['header_logo_source']) && in_array($input['header_logo_source'], array('same', 'store_logo', 'custom', 'hide'), true) ? $input['header_logo_source'] : $current['header_logo_source'];
        $clean['header_logo_attachment_id'] = isset($input['header_logo_attachment_id']) ? self::sanitize_image_attachment_id($input['header_logo_attachment_id']) : absint($current['header_logo_attachment_id']);
        $clean['launcher_style'] = isset($input['launcher_style']) && in_array($input['launcher_style'], array('icon', 'pill'), true) ? $input['launcher_style'] : $current['launcher_style'];
        $clean['launcher_text'] = isset($input['launcher_text']) ? sanitize_text_field($input['launcher_text']) : $current['launcher_text'];
        if ($clean['launcher_text'] === '') {
            $clean['launcher_text'] = self::defaults()['launcher_text'];
        }
        $clean['shopper_invitation_enabled'] = isset($input['shopper_invitation_enabled']) && $input['shopper_invitation_enabled'] === 'yes' ? 'yes' : 'no';
        $clean['shopper_invitation_delay'] = isset($input['shopper_invitation_delay']) ? max(3, min(60, absint($input['shopper_invitation_delay']))) : absint($current['shopper_invitation_delay']);
        $clean['shopper_invitation_message'] = isset($input['shopper_invitation_message']) ? sanitize_textarea_field($input['shopper_invitation_message']) : $current['shopper_invitation_message'];
        if ($clean['shopper_invitation_message'] === '') {
            $clean['shopper_invitation_message'] = self::defaults()['shopper_invitation_message'];
        }
        $clean['shopper_invitation_message'] = function_exists('mb_substr')
            ? mb_substr($clean['shopper_invitation_message'], 0, 160)
            : substr($clean['shopper_invitation_message'], 0, 160);
        $clean['header_style'] = isset($input['header_style']) && in_array($input['header_style'], array('gradient', 'solid'), true) ? $input['header_style'] : $current['header_style'];
        $clean['max_products'] = isset($input['max_products']) ? max(1, min(8, absint($input['max_products']))) : 4;
        $clean['chat_history_enabled'] = isset($input['chat_history_enabled']) && $input['chat_history_enabled'] === 'yes' ? 'yes' : 'no';
        $clean['retention_days'] = isset($input['retention_days']) ? max(1, min(365, absint($input['retention_days']))) : 30;
        $clean['allow_guest_sessions'] = isset($input['allow_guest_sessions']) && $input['allow_guest_sessions'] === 'yes' ? 'yes' : 'no';
        $clean['provider_mode'] = isset($input['provider_mode']) && in_array($input['provider_mode'], array('local', 'zywrap', 'openai'), true) ? $input['provider_mode'] : 'local';
        $clean['zywrap_endpoint'] = isset($input['zywrap_endpoint']) ? self::sanitize_provider_endpoint($input['zywrap_endpoint']) : $current['zywrap_endpoint'];
        $clean['openai_model'] = isset($input['openai_model']) ? sanitize_key($input['openai_model']) : $current['openai_model'];
        if ($clean['openai_model'] === '') {
            $clean['openai_model'] = self::defaults()['openai_model'];
        }
        $clean['ai_max_tokens'] = isset($input['ai_max_tokens']) ? max(120, min(1200, absint($input['ai_max_tokens']))) : 450;
        $clean['rate_limit_messages'] = isset($input['rate_limit_messages']) ? max(20, min(1000, absint($input['rate_limit_messages']))) : 120;
        $clean['rate_limit_window_minutes'] = isset($input['rate_limit_window_minutes']) ? max(1, min(60, absint($input['rate_limit_window_minutes']))) : 5;
        $clean['fallback_human_message'] = isset($input['fallback_human_message']) ? sanitize_textarea_field($input['fallback_human_message']) : $current['fallback_human_message'];
        $clean['natural_search_enabled'] = isset($input['natural_search_enabled']) ? ($input['natural_search_enabled'] === 'yes' ? 'yes' : 'no') : $current['natural_search_enabled'];
        $clean['search_close_match_mode'] = isset($input['search_close_match_mode']) && in_array($input['search_close_match_mode'], array('smart', 'strict'), true) ? $input['search_close_match_mode'] : $current['search_close_match_mode'];
        $clean['search_boost_in_stock'] = isset($input['search_boost_in_stock']) ? ($input['search_boost_in_stock'] === 'yes' ? 'yes' : 'no') : $current['search_boost_in_stock'];
        $clean['search_boost_sale'] = isset($input['search_boost_sale']) ? ($input['search_boost_sale'] === 'yes' ? 'yes' : 'no') : $current['search_boost_sale'];
        $clean['search_boost_rating'] = isset($input['search_boost_rating']) ? ($input['search_boost_rating'] === 'yes' ? 'yes' : 'no') : $current['search_boost_rating'];
        $clean['search_boost_popularity'] = isset($input['search_boost_popularity']) ? ($input['search_boost_popularity'] === 'yes' ? 'yes' : 'no') : $current['search_boost_popularity'];
        $clean['search_min_score'] = isset($input['search_min_score']) ? max(1, min(200, absint($input['search_min_score']))) : absint($current['search_min_score']);
        $clean['search_custom_synonyms'] = isset($input['search_custom_synonyms']) ? substr(sanitize_textarea_field((string) $input['search_custom_synonyms']), 0, 6000) : $current['search_custom_synonyms'];
        $clean['delete_data_on_uninstall'] = isset($input['delete_data_on_uninstall']) && $input['delete_data_on_uninstall'] === 'yes' ? 'yes' : 'no';
        update_option('geekybot_delete_data_on_uninstall', $clean['delete_data_on_uninstall'], false);

        $page_ids = array();
        if (!empty($input['policy_page_ids']) && is_array($input['policy_page_ids'])) {
            foreach ($input['policy_page_ids'] as $page_id) {
                $page_id = absint($page_id);
                if ($page_id > 0 && get_post_type($page_id) === 'page' && get_post_status($page_id) === 'publish') {
                    $page_ids[] = $page_id;
                }
            }
        }
        $clean['policy_page_ids'] = array_slice(array_values(array_unique($page_ids)), 0, 25);

        $clean['zywrap_api_key'] = isset($input['zywrap_api_key']) ? self::sanitize_secret($input['zywrap_api_key'], $current['zywrap_api_key']) : $current['zywrap_api_key'];
        $clean['openai_api_key'] = isset($input['openai_api_key']) ? self::sanitize_secret($input['openai_api_key'], $current['openai_api_key']) : $current['openai_api_key'];
        $clean['last_updated'] = current_time('mysql');

        update_option(self::OPTION, $clean, false);
        return $clean;
    }

    private static function sanitize_provider_endpoint($value) {
        $url = esc_url_raw(trim((string) $value), array('http', 'https'));
        if ($url === '') {
            return '';
        }

        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return '';
        }

        return $url;
    }

    private static function sanitize_image_attachment_id($attachment_id) {
        $attachment_id = absint($attachment_id);
        if ($attachment_id < 1) {
            return 0;
        }

        if (get_post_type($attachment_id) !== 'attachment') {
            return 0;
        }

        $mime = (string) get_post_mime_type($attachment_id);
        $allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');

        return in_array($mime, $allowed, true) ? $attachment_id : 0;
    }

    private static function image_url_from_attachment($attachment_id) {
        $attachment_id = absint($attachment_id);
        if ($attachment_id < 1) {
            return '';
        }

        $url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        return $url ? esc_url_raw($url) : '';
    }

    private static function store_logo_url() {
        $logo_id = function_exists('get_theme_mod') ? absint(get_theme_mod('custom_logo')) : 0;

        return self::image_url_from_attachment($logo_id);
    }

    private static function resolve_launcher_icon_url($settings) {
        if ($settings['launcher_icon_source'] === 'custom') {
            return self::image_url_from_attachment($settings['launcher_icon_attachment_id']);
        }

        if ($settings['launcher_icon_source'] === 'store_logo') {
            return self::store_logo_url();
        }

        return '';
    }

    private static function resolve_header_logo_url($settings) {
        if ($settings['header_logo_source'] === 'hide') {
            return '';
        }

        if ($settings['header_logo_source'] === 'custom') {
            return self::image_url_from_attachment($settings['header_logo_attachment_id']);
        }

        if ($settings['header_logo_source'] === 'store_logo') {
            return self::store_logo_url();
        }

        return self::resolve_launcher_icon_url($settings);
    }

    private static function sanitize_secret($value, $current) {
        $value = trim((string) $value);
        if ($value === '' || $value === '••••••••') {
            return $current;
        }

        $value = preg_replace('/[\r\n\t]+/', '', $value);
        $value = preg_replace('/[^\P{C}]+/u', '', $value);
        $value = sanitize_text_field($value);

        return function_exists('mb_substr') ? mb_substr($value, 0, 4096) : substr($value, 0, 4096);
    }

    public static function public_settings() {
        $settings = self::all();
        return array(
            'assistantName' => $settings['assistant_name'],
            'assistantSubtitle' => $settings['assistant_subtitle'],
            'welcomeMessage' => $settings['welcome_message'],
            'accentColor' => $settings['accent_color'],
            'buttonPosition' => $settings['button_position'],
            'launcherStyle' => $settings['launcher_style'],
            'launcherText' => $settings['launcher_text'],
            'shopperInvitationEnabled' => $settings['shopper_invitation_enabled'],
            'shopperInvitationDelay' => absint($settings['shopper_invitation_delay']),
            'shopperInvitationMessage' => $settings['shopper_invitation_message'],
            'launcherIconSource' => $settings['launcher_icon_source'],
            'launcherIconUrl' => self::resolve_launcher_icon_url($settings),
            'headerLogoSource' => $settings['header_logo_source'],
            'headerLogoUrl' => self::resolve_header_logo_url($settings),
            'headerStyle' => $settings['header_style'],
            'maxProducts' => absint($settings['max_products']),
            'chatHistoryEnabled' => $settings['chat_history_enabled'],
            'allowGuestSessions' => $settings['allow_guest_sessions'],
            'retentionDays' => absint($settings['retention_days']),
            'hasPolicySources' => !empty(array_filter(array_map('absint', (array) $settings['policy_page_ids']))),
        );
    }

    public static function has_secret($key) {
        return (string) self::get($key, '') !== '';
    }
}
