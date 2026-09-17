<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {
    const OPTION = 'geekybot_v2_settings';

    /**
     * Reasons a submitted secret was refused during the current request.
     *
     * @var array
     */
    private static $secret_errors = array();

    /**
     * Shopper-facing text defaults, as English source => translation.
     *
     * Both halves are literals, in one place, on purpose. The KEY is what a
     * store actually has in its database: every install persists the defaults
     * at setup, so these fields are never blank and the saved value is the
     * English source rather than nothing. The VALUE is what the shopper should
     * read. Calling __() on a variable instead would leave every one of these
     * strings out of the .pot, which is the failure this exists to fix.
     *
     * i18n-exempt: the bare keys are not display text. They are what the store
     * has in `geekybot_v2_settings`, compared byte for byte, and translating a
     * key would mean a German store no longer matched its own saved default.
     *
     * @return array<string,array<string,string>>
     */
    private static function shopper_text_map() {
        return array(
            'assistant_subtitle' => array(
                'WooCommerce shopping assistant' => __('WooCommerce shopping assistant', 'geeky-bot'),
            ),
            'welcome_message' => array(
                'Hi! Ask me what you are looking for and I will help you find the right product.' => __('Hi! Ask me what you are looking for and I will help you find the right product.', 'geeky-bot'),
            ),
            'launcher_text' => array(
                'Ask about products' => __('Ask about products', 'geeky-bot'),
            ),
            'shopper_invitation_message' => array(
                'Need help choosing? Ask me about products, prices, or options.' => __('Need help choosing? Ask me about products, prices, or options.', 'geeky-bot'),
            ),
            'fallback_human_message' => array(
                'The store has not provided enough information for me to answer that. Please contact the store team for confirmation.' => __('The store has not provided enough information for me to answer that. Please contact the store team for confirmation.', 'geeky-bot'),
            ),
        );
    }

    /**
     * The starter prompt defaults, as English source => translation.
     *
     * Kept per prompt rather than as one block so a merchant who deleted one
     * line still gets the other three in their shopper's language.
     *
     * Each one is a shopper QUERY as well as a label -- the chip sends its own
     * text to the assistant -- so a translation has to be wording the language
     * pack recognises, not a literal rendering.
     *
     * i18n-exempt: as above, the keys are the saved English to match on.
     *
     * @return array<string,string>
     */
    private static function starter_prompt_defaults() {
        return array(
            'Latest products' => __('Latest products', 'geeky-bot'),
            'Sale products' => __('Sale products', 'geeky-bot'),
            'Top rated' => __('Top rated', 'geeky-bot'),
            'Products under 50' => __('Products under 50', 'geeky-bot'),
        );
    }

    /**
     * The translated default for one shopper-facing setting.
     *
     * @param string $key Setting key.
     * @return string
     */
    private static function shopper_text_default($key) {
        $map = self::shopper_text_map();

        return isset($map[$key]) ? (string) reset($map[$key]) : '';
    }

    /**
     * A stored value, with an untouched English default swapped for its translation.
     *
     * A store that never edited the field has the English source sitting in the
     * database, so the translated default in defaults() is never reached and a
     * German shop greets its shoppers in English. Matching on the exact English
     * source is what keeps this safe: anything the merchant actually wrote --
     * including their own translation -- does not match and is returned
     * untouched. Nothing is written back, so the admin screen still shows, and
     * saves, exactly what is stored.
     *
     * @param string $key Setting key.
     * @param mixed $value Stored value.
     * @return mixed
     */
    private static function translated_if_untouched($key, $value) {
        $map = self::shopper_text_map();
        if (!isset($map[$key]) || !is_string($value)) {
            return $value;
        }

        $stored = trim($value);

        return isset($map[$key][$stored]) ? $map[$key][$stored] : $value;
    }

    public static function defaults() {
        return array(
            'widget_enabled' => 'yes',
            // i18n-exempt: the product's name, so it is the same in every language. Every
            // default below it IS shopper-facing text and is translated, or a
            // German store greets its shoppers in English until the merchant
            // notices and retypes all four by hand.
            'assistant_name' => 'Geeky Bot',
            'assistant_subtitle' => self::shopper_text_default('assistant_subtitle'),
            'welcome_message' => self::shopper_text_default('welcome_message'),
            'accent_color' => '#2563eb',
            'button_position' => 'right',
            'launcher_icon_source' => 'default',
            'launcher_icon_attachment_id' => 0,
            'header_logo_source' => 'same',
            'header_logo_attachment_id' => 0,
            'launcher_style' => 'icon',
            'launcher_text' => self::shopper_text_default('launcher_text'),
            'shopper_invitation_enabled' => 'yes',
            'shopper_invitation_delay' => 12,
            'shopper_invitation_message' => self::shopper_text_default('shopper_invitation_message'),
            'header_style' => 'gradient',
            // One per line. These were hard-coded in Frontend/Widget.php, so a
            // merchant could see them in the admin but had no way to change
            // them — and the label claimed they were "generated from the current
            // capabilities", which was never true. Blank falls back to these.
            // Four msgids, not one four-line msgid. A translator handed the
            // blob has to preserve the line breaks exactly or the merchant
            // silently loses chips, and a reviewer cannot see which line is
            // which. Each one is also a shopper QUERY, not just a label: the
            // chip sends its own text to the assistant, so a translation has
            // to be wording the language pack recognises. "ultimos productos"
            // returns nothing in Spanish where "nuevos productos" works.
            'starter_prompts' => implode("\n", array_values(self::starter_prompt_defaults())),
            // The small avatar beside a shopper's own message. Off by default:
            // it costs 42px of every message bubble to repeat what the message's
            // right alignment already says. Merchants who want it can enable it.
            'user_avatar_enabled' => 'no',
            // Taste, so it belongs to the site admin rather than to us.
            // auto follows the shopper's own OS preference.
            'widget_color_mode' => 'light',
            'launcher_shape' => 'round',
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
            // A per-visitor limit keyed on IP + User-Agent is defeated by
            // rotating either one, so it never bounded total provider spend.
            // These two are site-wide and enforced server-side in AiBudgetService.
            'ai_daily_call_cap' => 1000,
            'ai_monthly_call_cap' => 20000,
            // X-Forwarded-For is only believed when the merchant declares how
            // many proxies sit in front of the site. 0 means "no proxy", and
            // REMOTE_ADDR stays the only source of the client address.
            'trusted_proxy_count' => 0,
            'fallback_human_message' => self::shopper_text_default('fallback_human_message'),
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
        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        return self::translated_if_untouched($key, $settings[$key]);
    }

    /**
     * Starter prompts as a clean list.
     *
     * @param int $limit Maximum prompts to return. The widget shows four.
     * @return array
     */
    public static function starter_prompts($limit = 4) {
        $raw = (string) self::get('starter_prompts', '');
        if (trim($raw) === '') {
            $raw = self::defaults()['starter_prompts'];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $prompts = array();
        foreach ((array) $lines as $line) {
            $line = trim(wp_strip_all_tags((string) $line));
            if ($line !== '') {
                $prompts[] = $line;
            }
        }

        // Per line, so a merchant who replaced one chip keeps their own wording
        // and still gets the rest in the shopper's language.
        $translations = self::starter_prompt_defaults();
        foreach ($prompts as $index => $prompt) {
            if (isset($translations[$prompt])) {
                $prompts[$index] = $translations[$prompt];
            }
        }

        $prompts = array_values(array_unique($prompts));

        return $limit > 0 ? array_slice($prompts, 0, absint($limit)) : $prompts;
    }

    public static function update($input) {
        self::$secret_errors = array();
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

        $clean['starter_prompts'] = isset($input['starter_prompts'])
            ? substr(sanitize_textarea_field((string) $input['starter_prompts']), 0, 1000)
            : (isset($current['starter_prompts']) ? $current['starter_prompts'] : self::defaults()['starter_prompts']);
        if (trim($clean['starter_prompts']) === '') {
            $clean['starter_prompts'] = self::defaults()['starter_prompts'];
        }

        $clean['widget_color_mode'] = isset($input['widget_color_mode']) && in_array($input['widget_color_mode'], array('light', 'dark', 'auto'), true)
            ? $input['widget_color_mode']
            : (isset($current['widget_color_mode']) ? $current['widget_color_mode'] : self::defaults()['widget_color_mode']);

        $clean['launcher_shape'] = isset($input['launcher_shape']) && in_array($input['launcher_shape'], array('round', 'rounded'), true)
            ? $input['launcher_shape']
            : (isset($current['launcher_shape']) ? $current['launcher_shape'] : self::defaults()['launcher_shape']);

        // Only treated as a checkbox when the field was actually submitted. The
        // Settings screen posts the full option set but does not render this
        // control, so a plain "isset ? yes : no" would silently switch it back
        // off every time that page was saved.
        $clean['user_avatar_enabled'] = isset($input['user_avatar_enabled'])
            ? ($input['user_avatar_enabled'] === 'yes' ? 'yes' : 'no')
            : (isset($current['user_avatar_enabled']) ? $current['user_avatar_enabled'] : self::defaults()['user_avatar_enabled']);
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
        $clean['ai_daily_call_cap'] = isset($input['ai_daily_call_cap'])
            ? max(AiBudgetService::DAILY_MIN, min(AiBudgetService::DAILY_MAX, absint($input['ai_daily_call_cap'])))
            : max(AiBudgetService::DAILY_MIN, min(AiBudgetService::DAILY_MAX, absint($current['ai_daily_call_cap'])));
        $clean['ai_monthly_call_cap'] = isset($input['ai_monthly_call_cap'])
            ? max(AiBudgetService::MONTHLY_MIN, min(AiBudgetService::MONTHLY_MAX, absint($input['ai_monthly_call_cap'])))
            : max(AiBudgetService::MONTHLY_MIN, min(AiBudgetService::MONTHLY_MAX, absint($current['ai_monthly_call_cap'])));
        $clean['trusted_proxy_count'] = isset($input['trusted_proxy_count'])
            ? min(10, absint($input['trusted_proxy_count']))
            : min(10, absint($current['trusted_proxy_count']));
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

        // Ranking boosts and match mode are part of the ranking cache key, but
        // custom synonyms change the analysed query itself, so any search
        // setting change has to retire the cached lists.
        foreach (array('search_boost_in_stock', 'search_boost_sale', 'search_boost_rating', 'search_boost_popularity', 'search_min_score', 'search_close_match_mode', 'search_custom_synonyms', 'natural_search_enabled') as $search_key) {
            if (!isset($current[$search_key]) || $current[$search_key] !== $clean[$search_key]) {
                ProductIndexService::flush_search_cache();
                break;
            }
        }

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
        $value = function_exists('mb_substr') ? mb_substr($value, 0, 4096) : substr($value, 0, 4096);

        if ($value === '') {
            return $current;
        }

        // Fail closed. A provider key written in the clear into a serialized
        // option is readable by anything that can read the database -- a second
        // vulnerable plugin, a leaked backup, a compromised credential -- and
        // is spendable entirely outside the store. If this installation cannot
        // encrypt, the key is refused and the merchant is told why, rather than
        // being silently downgraded to plaintext storage.
        if (!LicenseVault::available()) {
            self::$secret_errors[] = 'vault_unavailable';
            return $current;
        }

        $encrypted = LicenseVault::encrypt($value);
        if (!is_string($encrypted) || $encrypted === '') {
            self::$secret_errors[] = 'encrypt_failed';
            return $current;
        }

        return $encrypted;
    }

    /**
     * Plaintext value of an encrypted provider secret.
     *
     * Everything that actually talks to a provider must read keys through
     * here. Settings::get() intentionally returns the stored (encrypted) value
     * so nothing leaks a usable key by accident.
     *
     * @param string $key Settings key.
     * @return string
     */
    public static function secret($key) {
        $stored = (string) self::get($key, '');
        if ($stored === '') {
            return '';
        }

        if (!LicenseVault::is_encrypted($stored)) {
            // Pre-2.0.3 plaintext value that the migration has not reached yet.
            return $stored;
        }

        return LicenseVault::decrypt($stored);
    }

    /**
     * How provider secrets are being stored on this installation.
     *
     * Surfaced in the admin so an installation without libsodium or OpenSSL
     * shows the state instead of failing invisibly.
     *
     * @return string encrypted|plaintext|unavailable|none
     */
    public static function secret_storage_state() {
        $stored = array();
        foreach (self::secret_keys() as $key) {
            $value = (string) self::get($key, '');
            if ($value !== '') {
                $stored[] = $value;
            }
        }

        if (!LicenseVault::available()) {
            return 'unavailable';
        }

        if (empty($stored)) {
            return 'none';
        }

        foreach ($stored as $value) {
            if (!LicenseVault::is_encrypted($value)) {
                return 'plaintext';
            }
        }

        return 'encrypted';
    }

    /**
     * @return array
     */
    public static function secret_keys() {
        return array('zywrap_api_key', 'openai_api_key');
    }

    /**
     * Reasons a submitted secret was refused during the last update() call.
     *
     * @return array
     */
    public static function last_secret_errors() {
        return array_values(array_unique(self::$secret_errors));
    }

    public static function public_settings() {
        $settings = self::all();
        return array(
            'assistantName' => $settings['assistant_name'],
            'assistantSubtitle' => self::get('assistant_subtitle'),
            'welcomeMessage' => self::get('welcome_message'),
            'accentColor' => $settings['accent_color'],
            'buttonPosition' => $settings['button_position'],
            'launcherStyle' => $settings['launcher_style'],
            'launcherText' => self::get('launcher_text'),
            'shopperInvitationEnabled' => $settings['shopper_invitation_enabled'],
            'shopperInvitationDelay' => absint($settings['shopper_invitation_delay']),
            'shopperInvitationMessage' => self::get('shopper_invitation_message'),
            'userAvatarEnabled' => isset($settings['user_avatar_enabled']) ? $settings['user_avatar_enabled'] : 'no',
            'colorMode' => isset($settings['widget_color_mode']) ? $settings['widget_color_mode'] : 'light',
            'launcherShape' => isset($settings['launcher_shape']) ? $settings['launcher_shape'] : 'round',
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
