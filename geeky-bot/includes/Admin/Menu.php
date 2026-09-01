<?php
namespace GeekyBot\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use GeekyBot\Services\Settings;
use GeekyBot\Services\ProductService;
use GeekyBot\Services\ProductIndexService;
use GeekyBot\Services\KnowledgeIndexService;
use GeekyBot\Services\KnowledgeService;
use GeekyBot\Services\LicenseService;
use GeekyBot\Services\ConversationInsightsService;
use GeekyBot\Services\AnalyticsEventService;
use GeekyBot\Services\GuidedDemoService;
use GeekyBot\Services\OnboardingService;

class Menu {
    public function hooks() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'handle_save'));
        add_action('admin_post_geekybot_rebuild_product_index', array($this, 'handle_rebuild_product_index'));
        add_action('admin_post_geekybot_refresh_knowledge_index', array($this, 'handle_refresh_knowledge_index'));
        add_action('admin_post_geekybot_refresh_guided_demo', array($this, 'handle_refresh_guided_demo'));
        add_action('admin_post_geekybot_onboarding_action', array($this, 'handle_onboarding_action'));
        add_action('admin_post_geekybot_review_action', array($this, 'handle_review_action'));
        add_action('admin_post_geekybot_export_conversations', array($this, 'handle_export_conversations'));
        add_action('admin_post_geekybot_delete_conversation', array($this, 'handle_delete_conversation'));
        add_action('admin_post_geekybot_delete_all_conversations', array($this, 'handle_delete_all_conversations'));
        add_action('admin_post_geekybot_license_activate', array($this, 'handle_license_activate'));
        add_action('admin_post_geekybot_license_deactivate', array($this, 'handle_license_deactivate'));
        add_action('admin_post_geekybot_license_refresh', array($this, 'handle_license_refresh'));
        add_action('admin_post_geekybot_install_commerce_pro', array($this, 'handle_install_commerce_pro'));
        add_action('admin_post_geekybot_activate_commerce_pro', array($this, 'handle_activate_commerce_pro'));
        add_action('admin_post_geekybot_save_commerce_pro_updates', array($this, 'handle_save_commerce_pro_updates'));
        add_action('admin_post_geekybot_refresh_commerce_pro_update', array($this, 'handle_refresh_commerce_pro_update'));
        add_action('admin_post_geekybot_update_commerce_pro', array($this, 'handle_update_commerce_pro'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
    }

    public function menu() {
        add_menu_page(
            __('Geeky Bot', 'geeky-bot'),
            __('Geeky Bot', 'geeky-bot'),
            'manage_options',
            'geekybot',
            array($this, 'dashboard'),
            $this->menu_icon_data_uri(),
            56
        );

        /*
         * Ordered by what a merchant does, in sequence: see the store, set it
         * up, configure what shoppers meet, then review what happened, then
         * the back-office screens.
         *
         * Guided Demo moved down from third place. It is a testing tool, and
         * sitting above the pages you must configure first implied it was a
         * setup step.
         *
         * Labels match each page's heading exactly. Seven of them did not:
         * "Analytics" opened "Store insight", "Product Assistant" opened
         * "Catalog intelligence". Clicking one name and landing on another
         * makes one product read as several.
         */
        add_submenu_page('geekybot', __('Dashboard', 'geeky-bot'), __('Dashboard', 'geeky-bot'), 'manage_options', 'geekybot', array($this, 'dashboard'));
        add_submenu_page('geekybot', __('Setup Wizard', 'geeky-bot'), __('Setup Wizard', 'geeky-bot'), 'manage_options', 'geekybot-setup', array($this, 'setup_wizard'));
        add_submenu_page('geekybot', __('Storefront Widget', 'geeky-bot'), __('Storefront Widget', 'geeky-bot'), 'manage_options', 'geekybot-widget', array($this, 'chat_widget'));
        add_submenu_page('geekybot', __('Product Search', 'geeky-bot'), __('Product Search', 'geeky-bot'), 'manage_options', 'geekybot-product-assistant', array($this, 'product_assistant'));
        add_submenu_page('geekybot', __('Store Knowledge', 'geeky-bot'), __('Store Knowledge', 'geeky-bot'), 'manage_options', 'geekybot-store-knowledge', array($this, 'store_knowledge'));
        add_submenu_page('geekybot', __('Conversations', 'geeky-bot'), __('Conversations', 'geeky-bot'), 'manage_options', 'geekybot-conversations', array($this, 'conversations'));
        add_submenu_page('geekybot', __('Analytics', 'geeky-bot'), __('Analytics', 'geeky-bot'), 'manage_options', 'geekybot-analytics', array($this, 'analytics'));
        add_submenu_page('geekybot', __('Guided Demo', 'geeky-bot'), __('Guided Demo', 'geeky-bot'), 'manage_options', 'geekybot-guided-demo', array($this, 'guided_demo'));
        add_submenu_page('geekybot', __('Answer Mode', 'geeky-bot'), __('Answer Mode', 'geeky-bot'), 'manage_options', 'geekybot-integrations', array($this, 'integrations'));
        add_submenu_page('geekybot', __('Settings', 'geeky-bot'), __('Settings', 'geeky-bot'), 'manage_options', 'geekybot-settings', array($this, 'settings'));
        add_submenu_page('geekybot', __('Add-ons', 'geeky-bot'), __('Add-ons', 'geeky-bot'), 'manage_options', 'geekybot-addons', array($this, 'pro'));
        if (!defined('GBCP_VERSION')) {
            add_submenu_page('geekybot', __('Commerce Pro', 'geeky-bot'), __('Commerce Pro', 'geeky-bot'), 'manage_options', 'geekybot-commerce-pro', array($this, 'commerce_pro_promo'));
        }
    }

    /**
     * Cache-busting version for an admin asset.
     *
     * The plugin version alone is not enough: an asset edited without a version
     * bump keeps the same URL, so browsers keep serving the stale copy. Mixing
     * in the file's modification time means changing a stylesheet is always
     * enough to invalidate it.
     *
     * @param string $relative_path Path relative to the plugin root.
     * @return string
     */
    private static function asset_version($relative_path) {
        $file = GEEKYBOT_PATH . ltrim($relative_path, '/');
        $stamp = file_exists($file) ? filemtime($file) : 0;

        return $stamp ? GEEKYBOT_VERSION . '.' . $stamp : GEEKYBOT_VERSION;
    }

    public function assets($hook) {
        if (strpos((string) $hook, 'geekybot') === false) {
            return;
        }
        // 2.0.2 design system, and the only admin-specific stylesheet.
        wp_enqueue_style('geekybot-admin', GEEKYBOT_URL . 'assets/css/admin-2.css', array(), self::asset_version('assets/css/admin-2.css'));

        // The storefront stylesheet, loaded so the widget preview is drawn by the
        // same CSS shoppers receive rather than an admin-only imitation. This is
        // what keeps the preview honest: there is no second set of rules left to
        // drift out of sync with the live widget.
        wp_enqueue_style('geekybot-widget-preview', GEEKYBOT_URL . 'assets/css/frontend.css', array('geekybot-admin'), self::asset_version('assets/css/frontend.css'));

        wp_enqueue_media();
        wp_enqueue_script('geekybot-admin', GEEKYBOT_URL . 'assets/js/admin.js', array(), self::asset_version('assets/js/admin.js'), true);
        wp_localize_script('geekybot-admin', 'GeekyBotAdmin', array(
            'mediaTitle' => __('Choose widget image', 'geeky-bot'),
            'mediaButton' => __('Use this image', 'geeky-bot'),
            'removeImage' => __('Remove image', 'geeky-bot'),
            'copyDemo' => __('Copy', 'geeky-bot'),
            'copiedDemo' => __('Copied', 'geeky-bot'),
        ));
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (empty($_POST['geekybot_settings_action']) || $_POST['geekybot_settings_action'] !== 'save') {
            return;
        }

        check_admin_referer('geekybot_save_settings');

        $input = wp_unslash($_POST);
        if (!empty($input['geekybot_settings_scope']) && $input['geekybot_settings_scope'] === 'partial') {
            $input = wp_parse_args($input, Settings::all());
        }

        $search_notice = 'saved';
        if (!empty($input['geekybot_reset_search'])) {
            $defaults = Settings::defaults();
            if ($input['geekybot_reset_search'] === 'synonyms' || $input['geekybot_reset_search'] === 'all') {
                $input['search_custom_synonyms'] = $defaults['search_custom_synonyms'];
                $search_notice = 'synonyms_reset';
            }
            if ($input['geekybot_reset_search'] === 'all') {
                $input['natural_search_enabled'] = $defaults['natural_search_enabled'];
                $input['search_close_match_mode'] = $defaults['search_close_match_mode'];
                $input['search_boost_in_stock'] = $defaults['search_boost_in_stock'];
                $input['search_boost_sale'] = $defaults['search_boost_sale'];
                $input['search_boost_rating'] = $defaults['search_boost_rating'];
                $input['search_boost_popularity'] = $defaults['search_boost_popularity'];
                $input['search_min_score'] = $defaults['search_min_score'];
                $search_notice = 'controls_reset';
            }
        }

        Settings::update($input);
        (new KnowledgeIndexService())->sync_selected_pages();

        $redirect = !empty($input['geekybot_redirect']) ? esc_url_raw((string) $input['geekybot_redirect']) : '';
        if (!$redirect) {
            $redirect = wp_get_referer();
        }
        if (!$redirect) {
            $redirect = add_query_arg(array('page' => 'geekybot-settings'), admin_url('admin.php'));
        }

        wp_safe_redirect(add_query_arg(array('updated' => '1', 'gb_notice' => $search_notice), $redirect));
        exit;
    }

    public function handle_rebuild_product_index() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to rebuild the product index.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_rebuild_product_index');
        $result = (new ProductIndexService())->rebuild();
        wp_safe_redirect(add_query_arg(array(
            'page' => 'geekybot-product-assistant',
            'gb_indexed' => absint($result['indexed']),
            'gb_skipped' => absint($result['skipped']),
        ), admin_url('admin.php')));
        exit;
    }

    public function handle_refresh_knowledge_index() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to refresh store knowledge.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_refresh_knowledge_index');
        $result = (new KnowledgeIndexService())->refresh_selected_pages();
        wp_safe_redirect(add_query_arg(array(
            'page' => 'geekybot-store-knowledge',
            'gb_knowledge_refreshed' => '1',
            'gb_knowledge_indexed' => absint($result['indexed']),
            'gb_knowledge_unchanged' => absint($result['unchanged']),
            'gb_knowledge_removed' => absint($result['removed']),
            'gb_knowledge_failed' => absint($result['failed']),
        ), admin_url('admin.php')));
        exit;
    }

    public function handle_refresh_guided_demo() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to refresh Guided Demo examples.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_refresh_guided_demo');
        (new GuidedDemoService())->refresh();
        wp_safe_redirect(add_query_arg(array(
            'page' => 'geekybot-guided-demo',
            'gb_demo_refreshed' => '1',
        ), admin_url('admin.php')));
        exit;
    }

    public function handle_onboarding_action() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to update setup progress.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_onboarding_action');
        $onboarding_action = isset($_POST['onboarding_action']) ? sanitize_key(wp_unslash($_POST['onboarding_action'])) : '';
        if ($onboarding_action === 'restart') {
            OnboardingService::reset();
        } elseif ($onboarding_action === 'dismiss') {
            OnboardingService::set_status('dismissed');
        } else {
            OnboardingService::set_status('reviewed');
        }

        wp_safe_redirect(add_query_arg(array(
            'page' => 'geekybot-setup',
            'gb_onboarding' => $onboarding_action ? $onboarding_action : 'reviewed',
        ), admin_url('admin.php')));
        exit;
    }


    public function handle_license_activate() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Geeky Bot licenses.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        $result = LicenseService::activate($key);
        $this->redirect_license_result($result, 'activated');
    }

    public function handle_license_deactivate() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Geeky Bot licenses.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        $result = LicenseService::deactivate();
        $this->redirect_license_result($result, 'deactivated');
    }

    public function handle_license_refresh() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Geeky Bot licenses.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        $result = LicenseService::refresh();
        $this->redirect_license_result($result, 'refreshed');
    }

    public function handle_install_commerce_pro() {
        if (!current_user_can('install_plugins')) {
            wp_die(esc_html__('You do not have permission to install plugins.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        $result = LicenseService::install_commerce_pro();
        $this->redirect_license_result($result, 'installed');
    }

    public function handle_activate_commerce_pro() {
        if (!current_user_can('activate_plugins')) {
            wp_die(esc_html__('You do not have permission to activate plugins.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        $result = LicenseService::activate_commerce_pro_plugin();
        $this->redirect_license_result($result, 'plugin_activated');
    }

    public function handle_save_commerce_pro_updates() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Commerce Pro updates.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        $input = isset($_POST['commerce_pro_updates']) && is_array($_POST['commerce_pro_updates'])
            ? map_deep(wp_unslash($_POST['commerce_pro_updates']), 'sanitize_text_field')
            : array();
        LicenseService::save_update_settings($input);
        $this->redirect_license_result(true, 'update_settings_saved');
    }

    public function handle_refresh_commerce_pro_update() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to check Commerce Pro updates.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        LicenseService::refresh_update_status();
        $this->redirect_license_result(true, 'update_refreshed');
    }

    public function handle_update_commerce_pro() {
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('You do not have permission to update Commerce Pro.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        $result = LicenseService::update_commerce_pro();
        $notice = 'updated';
        if (is_array($result)) {
            $current_status = LicenseService::commerce_pro_plugin_status();
            if (!empty($result['was_active']) && (!empty($current_status['active']) || !empty($result['reactivated']))) {
                $notice = 'updated_activated';
            } elseif (empty($current_status['active'])) {
                $notice = 'updated_inactive';
            }
        }
        $this->redirect_license_result($result, $notice);
    }

    private function redirect_license_result($result, $success_notice) {
        $args = array('page' => 'geekybot-addons');
        if (is_wp_error($result)) {
            $args['gb_license_error'] = rawurlencode($result->get_error_message());
        } else {
            $args['gb_license_notice'] = sanitize_key($success_notice);
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Format a period-over-period change for a metric tile.
     *
     * @param int  $current          This period's value.
     * @param int  $previous         Previous period's value.
     * @param bool $higher_is_better Whether growth is good. Unanswered questions
     *                               going up is bad, conversations going up is good.
     * @return array array('delta' => string, 'direction' => 'up|down|flat')
     */
    private function metric_delta($current, $previous, $higher_is_better = true) {
        $current = (int) $current;
        $previous = (int) $previous;

        if ($previous <= 0) {
            return array('delta' => '', 'direction' => 'flat');
        }

        $change = (int) round((($current - $previous) / $previous) * 100);

        if ($change === 0) {
            return array('delta' => '', 'direction' => 'flat');
        }

        $grew = $change > 0;
        $good = $higher_is_better ? $grew : !$grew;

        return array(
            'delta' => ($grew ? '+' : '') . number_format_i18n($change) . '%',
            'direction' => $good ? 'up' : 'down',
        );
    }

    public function dashboard() {
        $ctx = $this->context();
        $settings = $ctx['settings'];
        $natural_search_ready = isset($settings['natural_search_enabled']) && $settings['natural_search_enabled'] === 'yes';
        $widget_ready = isset($settings['widget_enabled']) && $settings['widget_enabled'] === 'yes';
        $last_rebuild = $ctx['last_rebuild'] ? $ctx['last_rebuild'] : __('Never', 'geeky-bot');
        $review_count = absint($ctx['stats']['review_needed']);
        $handled_count = absint($ctx['stats']['handled_now']);
        $messages_count = absint($ctx['stats']['messages']);
        $completion = absint($ctx['completion']);
        $readiness_label = $completion >= 100 ? __('Live-ready', 'geeky-bot') : __('Needs attention', 'geeky-bot');
        $attention_label = $review_count > 0
            ? sprintf(
                /* translators: %s: number of shopper questions requiring review. */
                _n('%s shopper question', '%s shopper questions', $review_count, 'geeky-bot'),
                number_format_i18n($review_count)
            )
            : __('No urgent questions', 'geeky-bot');
        $priority_heading = $review_count > 0
            ? sprintf(
                /* translators: %s: number of shopper questions requiring review. */
                _n('Review %s shopper question', 'Review %s shopper questions', $review_count, 'geeky-bot'),
                number_format_i18n($review_count)
            )
            : __('No urgent shopper questions', 'geeky-bot');
        $fallback_mode = !empty($settings['search_close_match_mode']) && $settings['search_close_match_mode'] === 'strict' ? __('Exact matches', 'geeky-bot') : __('Smart product matches', 'geeky-bot');
        $fallback_short_label = !empty($settings['search_close_match_mode']) && $settings['search_close_match_mode'] === 'strict' ? __('Exact match', 'geeky-bot') : __('Smart matches', 'geeky-bot');
        $search_status_label = $natural_search_ready ? __('Natural search is active', 'geeky-bot') : __('Natural search needs tuning', 'geeky-bot');

        // --- Reporting window -------------------------------------------------
        // These all read tables that already exist; only the previous-period
        // comparison is new, and that reuses summary()'s $offset_days argument.
        $window = 30;
        $insights = new ConversationInsightsService();
        $now_summary = $insights->summary($window);
        $prev_summary = $insights->summary($window, $window);

        // daily_activity() returns one zero-filled row per day ending today, so
        // asking for two windows and splitting gives the comparison series
        // without a second query.
        $activity = $insights->daily_activity($window * 2);
        $activity = array_slice($activity, -($window * 2));
        $current_days = array_slice($activity, -$window);
        $previous_days = count($activity) > $window ? array_slice($activity, 0, count($activity) - $window) : array();

        $chart_series = array();
        foreach ($current_days as $day) {
            $chart_series[] = array('date' => $day['date'], 'value' => absint($day['sessions']));
        }
        $chart_compare = array();
        foreach ($previous_days as $day) {
            $chart_compare[] = array('date' => $day['date'], 'value' => absint($day['sessions']));
        }
        if (count($chart_compare) !== count($chart_series)) {
            $chart_compare = array();
        }

        $reasons = $insights->reason_breakdown($window);
        $recent_unanswered = $insights->recent_unanswered(6);
        $recent_sessions = $insights->recent_sessions(1, 6);
        $recent_rows = isset($recent_sessions['items']) ? (array) $recent_sessions['items'] : array();

        // Answer coverage: what share of shopper questions produced a grounded
        // answer rather than the fallback.
        $shopper_messages = absint($now_summary['shopper_messages']);
        $prev_shopper_messages = absint($prev_summary['shopper_messages']);
        $answered_rate = $shopper_messages > 0
            ? (int) round((($shopper_messages - absint($now_summary['unanswered'])) / $shopper_messages) * 100)
            : 0;
        $prev_answered_rate = $prev_shopper_messages > 0
            ? (int) round((($prev_shopper_messages - absint($prev_summary['unanswered'])) / $prev_shopper_messages) * 100)
            : 0;

        // Commerce Pro buying events, when the add-on is active.
        $pro_active = defined('GBCP_VERSION') && class_exists('\\GeekyBotCommercePro\\Services\\AnalyticsService');

        // Order attribution ships with Commerce Pro 2.0.2. Guarded by class_exists
        // so an older add-on simply reports nothing rather than fataling.
        $attribution_ready = $pro_active && class_exists('\\GeekyBotCommercePro\\Services\\AttributionService');
        $revenue = array('available' => false, 'orders' => 0, 'revenue' => 0.0, 'influenced' => 0.0, 'currency' => '');
        $prev_revenue = $revenue;
        $attribution_has_history = false;
        if ($attribution_ready) {
            $revenue = \GeekyBotCommercePro\Services\AttributionService::revenue_summary($window);
            $prev_revenue = \GeekyBotCommercePro\Services\AttributionService::revenue_summary($window, $window);
            $attribution_has_history = \GeekyBotCommercePro\Services\AttributionService::has_history();
        }
        $money = static function ($amount) {
            return function_exists('wc_price')
                ? wp_strip_all_tags(wc_price((float) $amount))
                : number_format_i18n((float) $amount, 2);
        };
        $pro_events = array();
        $pro_products = array();
        if ($pro_active) {
            $pro_summary = \GeekyBotCommercePro\Services\AnalyticsService::summary($window);
            $pro_events = isset($pro_summary['events']) ? (array) $pro_summary['events'] : array();
            $pro_products = isset($pro_summary['top_products']) ? (array) $pro_summary['top_products'] : array();
        }
        $pro_event = static function ($key) use ($pro_events) {
            return isset($pro_events[$key]) ? absint($pro_events[$key]) : 0;
        };

        $funnel_stages = array(
            array('label' => __('Conversations started', 'geeky-bot'), 'value' => absint($now_summary['sessions'])),
            array('label' => __('Products opened', 'geeky-bot'), 'value' => absint($now_summary['product_clicks'])),
        );
        if ($pro_active) {
            $funnel_stages[] = array('label' => __('Products compared', 'geeky-bot'), 'value' => $pro_event('compare_products'));
            $funnel_stages[] = array(
                'label' => __('Added to cart', 'geeky-bot'),
                'value' => $pro_event('add_to_cart') + $pro_event('add_variation_to_cart'),
            );
        }
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-dashboard geekybot-command-dashboard geekybot-cockpit-dashboard-v3">
            <?php $this->admin_notice_indexed(); ?>

            <?php
            Components::page_header(array(
                'title' => __('Geeky Bot', 'geeky-bot'),
                /* translators: %s: number of indexed products. */
                'description' => sprintf(__('AI sales assistant · connected to %s products', 'geeky-bot'), number_format_i18n($ctx['indexed_count'])),
                'brand' => array($this, 'brand_mark_svg'),
                'status' => array(
                    'label' => $widget_ready ? __('Live on storefront', 'geeky-bot') : __('Widget is off', 'geeky-bot'),
                    'state' => $widget_ready ? 'ok' : 'warn',
                ),
                // No signal row here. The metric strip sits directly below and
                // reports the same figures; showing "115 products indexed" in
                // both puts the same number on screen twice, 40px apart.
                'signals' => array(),
                'actions' => array(
                    array(
                        'label' => __('Tune product intelligence', 'geeky-bot'),
                        'url' => admin_url('admin.php?page=geekybot-product-assistant'),
                        'variant' => 'primary',
                    ),
                    array(
                        'label' => __('Review questions', 'geeky-bot'),
                        'url' => admin_url('admin.php?page=geekybot-conversations'),
                    ),
                    array(
                        'label' => __('Open store', 'geeky-bot'),
                        'url' => home_url('/'),
                        'external' => true,
                    ),
                ),
            ));
            ?>

            <div class="gb2-main">

                <?php
                // Renders only while a step is outstanding; once every step is
                // done the panel disappears and the dashboard becomes purely
                // reporting. Ordered by dependency: nothing works without
                // WooCommerce, and the widget goes last so shoppers never meet
                // an assistant that cannot answer them yet.
                Components::setup_guide(array(
                    array(
                        'title' => __('Connect WooCommerce', 'geeky-bot'),
                        'description' => __('Geeky Bot reads your live products, prices, stock and variations directly from WooCommerce.', 'geeky-bot'),
                        'consequence' => __('Without it the assistant has no catalog to answer from.', 'geeky-bot'),
                        'done' => (bool) $ctx['wc_ready'],
                        'action' => array(
                            'label' => __('Check WooCommerce', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-setup'),
                        ),
                    ),
                    array(
                        'title' => __('Build the product search index', 'geeky-bot'),
                        'description' => __('Indexing lets shoppers search in their own words, like "warm jacket under 60", instead of exact product names.', 'geeky-bot'),
                        'consequence' => __('Until this runs, product questions return nothing.', 'geeky-bot'),
                        'done' => $ctx['indexed_count'] > 0,
                        'action' => array(
                            'label' => __('Index products', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-product-assistant'),
                        ),
                    ),
                    array(
                        'title' => __('Choose your policy pages', 'geeky-bot'),
                        'description' => __('Pick the published pages covering shipping, refunds and returns. The assistant quotes only these and never invents a policy.', 'geeky-bot'),
                        'consequence' => __('Shoppers asking about returns or delivery get the fallback answer instead.', 'geeky-bot'),
                        'done' => $ctx['policy_count'] > 0,
                        'action' => array(
                            'label' => __('Select pages', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-store-knowledge'),
                        ),
                    ),
                    array(
                        'title' => __('Turn on the storefront widget', 'geeky-bot'),
                        'description' => __('This puts the assistant on your public store pages. Do it last, once the steps above are done.', 'geeky-bot'),
                        'consequence' => __('Nobody can use the assistant until the widget is live.', 'geeky-bot'),
                        'done' => (bool) $widget_ready,
                        'action' => array(
                            'label' => __('Enable widget', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-widget'),
                        ),
                    ),
                ));
                ?>

                <?php
                $dashboard_metrics = array(
                    array_merge(
                        array(
                            'label' => __('Conversations', 'geeky-bot'),
                            'value' => number_format_i18n($now_summary['sessions']),
                            /* translators: %s: count from the previous period. */
                            'base' => sprintf(__('vs %s previous 30 days', 'geeky-bot'), number_format_i18n($prev_summary['sessions'])),
                        ),
                        $this->metric_delta($now_summary['sessions'], $prev_summary['sessions'])
                    ),
                    array_merge(
                        array(
                            'label' => __('Shopper questions', 'geeky-bot'),
                            'value' => number_format_i18n($shopper_messages),
                            /* translators: %s: count from the previous period. */
                            'base' => sprintf(__('vs %s previous 30 days', 'geeky-bot'), number_format_i18n($prev_shopper_messages)),
                        ),
                        $this->metric_delta($shopper_messages, $prev_shopper_messages)
                    ),
                    array_merge(
                        array(
                            'label' => __('Answered from store data', 'geeky-bot'),
                            'value' => $shopper_messages > 0 ? $answered_rate . '%' : '—',
                            /* translators: %s: percentage from the previous period. */
                            'base' => $prev_answered_rate > 0 ? sprintf(__('vs %s%% previous 30 days', 'geeky-bot'), number_format_i18n($prev_answered_rate)) : __('No history yet', 'geeky-bot'),
                        ),
                        $this->metric_delta($answered_rate, $prev_answered_rate)
                    ),
                    array_merge(
                        array(
                            'label' => __('Needs review', 'geeky-bot'),
                            'value' => number_format_i18n($review_count),
                            'base' => __('Unanswered shopper phrases', 'geeky-bot'),
                        ),
                        $this->metric_delta($now_summary['unanswered'], $prev_summary['unanswered'], false)
                    ),
                    array(
                        'label' => __('Products indexed', 'geeky-bot'),
                        'value' => number_format_i18n($ctx['indexed_count']),
                        /* translators: %s: date of the last full index rebuild. */
                        'base' => sprintf(__('Rebuilt %s', 'geeky-bot'), $this->compact_datetime_label($ctx['last_rebuild'])),
                    ),
                );

                // Revenue only joins the strip once there is something real to
                // report. An empty money tile invites the reader to assume the
                // assistant earned nothing, when the truth may be that nothing
                // has been measured yet.
                if ($attribution_ready && $revenue['orders'] > 0) {
                    array_splice($dashboard_metrics, 3, 0, array(
                        array_merge(
                            array(
                                'label' => __('Assisted revenue', 'geeky-bot'),
                                'value' => $money($revenue['revenue']),
                                'base' => sprintf(
                                    /* translators: %s: number of assisted orders. */
                                    _n('%s order in 30 days', '%s orders in 30 days', $revenue['orders'], 'geeky-bot'),
                                    number_format_i18n($revenue['orders'])
                                ),
                            ),
                            $this->metric_delta((int) round($revenue['revenue']), (int) round($prev_revenue['revenue']))
                        ),
                    ));
                }

                Components::metrics($dashboard_metrics);
                ?>

                <?php Components::rule(__('Performance', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-8">
                        <?php Components::card_open(__('Conversations over time', 'geeky-bot'), __('Last 30 days', 'geeky-bot')); ?>
                            <?php
                            Components::chart_legend(
                                /* translators: %s: number of conversations this period. */
                                sprintf(__('This period · %s', 'geeky-bot'), number_format_i18n($now_summary['sessions'])),
                                $chart_compare ? sprintf(
                                    /* translators: %s: number of conversations in the previous period. */
                                    __('Previous 30 days · %s', 'geeky-bot'),
                                    number_format_i18n($prev_summary['sessions'])
                                ) : '',
                                ''
                            );
                            Components::chart($chart_series, $chart_compare, __('Daily conversations over the last 30 days', 'geeky-bot'));
                            ?>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('What shoppers do next', 'geeky-bot'), __('Last 30 days', 'geeky-bot'), false, 'gb2-fill'); ?>
                            <?php Components::funnel($funnel_stages); ?>

                            <?php if (!$pro_active) : ?>
                                <p class="gb2-note"><?php esc_html_e('Comparison and cart steps appear once Commerce Pro is active, which adds buying actions to the assistant.', 'geeky-bot'); ?></p>
                            <?php elseif (!$attribution_ready) : ?>
                                <p class="gb2-note"><?php esc_html_e('Revenue attribution arrives with Commerce Pro 2.0.2. Update the add-on to see what the assistant actually sold.', 'geeky-bot'); ?></p>
                            <?php elseif ($revenue['orders'] > 0) : ?>
                                <div class="gb2-revenue">
                                    <span class="gb2-revenue__label"><?php esc_html_e('Revenue from items added in chat', 'geeky-bot'); ?></span>
                                    <span class="gb2-revenue__value">
                                        <?php echo esc_html($money($revenue['revenue'])); ?>
                                        <?php
                                        $rev_delta = $this->metric_delta((int) round($revenue['revenue']), (int) round($prev_revenue['revenue']));
                                        if ($rev_delta['delta'] !== '') : ?>
                                            <em class="gb2-metric__delta gb2-metric__delta--<?php echo esc_attr($rev_delta['direction']); ?>"><?php
                                                echo esc_html($rev_delta['delta']); ?></em>
                                        <?php endif; ?>
                                    </span>
                                    <span class="gb2-revenue__base">
                                        <?php
                                        printf(
                                            /* translators: 1: number of orders, 2: total value of those orders. */
                                            esc_html(_n('Across %1$s order worth %2$s', 'Across %1$s orders worth %2$s', $revenue['orders'], 'geeky-bot')),
                                            esc_html(number_format_i18n($revenue['orders'])),
                                            esc_html($money($revenue['influenced']))
                                        );
                                        ?>
                                    </span>
                                </div>
                                <p class="gb2-note"><?php esc_html_e('Only the lines the shopper added through the assistant are counted, not the whole order.', 'geeky-bot'); ?></p>
                            <?php elseif ($attribution_has_history) : ?>
                                <p class="gb2-note"><?php esc_html_e('No orders in this period included an item added through the assistant.', 'geeky-bot'); ?></p>
                            <?php else : ?>
                                <p class="gb2-note"><?php esc_html_e('Revenue appears here after the first order containing an item a shopper added through the assistant. Earlier orders cannot be attributed retroactively.', 'geeky-bot'); ?></p>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php Components::rule(__('Needs action', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-8">
                <?php
                Components::card_open(
                    __('Next highest-impact actions', 'geeky-bot'),
                    /* translators: %s: setup completion percentage. */
                    sprintf(__('%s%% set up', 'geeky-bot'), number_format_i18n($completion)),
                    true
                );
                ?>
                <ul class="gb2-tasks">
                    <?php
                    Components::task(array(
                        'title' => $priority_heading,
                        'description' => $review_count > 0
                            ? __('Turn repeated misses into better attributes, synonyms, selected policy pages, or Commerce Pro rules.', 'geeky-bot')
                            : __('The current build is handling the latest reviewed shopper questions.', 'geeky-bot'),
                        'severity' => $review_count > 0 ? 'critical' : 'done',
                        'action' => array(
                            'label' => $review_count > 0 ? __('Review questions', 'geeky-bot') : __('Open review', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-conversations'),
                            'variant' => $review_count > 0 ? 'primary' : 'default',
                        ),
                    ));

                    // The 2.0.1 page showed these facts twice — once as the
                    // "task matrix" and again as the readiness aside. One list
                    // now, carrying the task matrix's wording as descriptions.
                    $readiness_checks = array(
                        array(
                            'ready' => (bool) $ctx['wc_ready'],
                            'title' => __('WooCommerce catalog connected', 'geeky-bot'),
                            'description' => __('The assistant reads live products, prices and stock from WooCommerce.', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-setup'),
                            'action' => __('Check', 'geeky-bot'),
                        ),
                        array(
                            'ready' => $ctx['indexed_count'] > 0 && $natural_search_ready,
                            'title' => $search_status_label,
                            'description' => __('Buyer-language search turns shopper phrasing into catalog matches.', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-product-assistant'),
                            'action' => __('Tune search', 'geeky-bot'),
                        ),
                        array(
                            'ready' => $ctx['policy_count'] > 0,
                            'title' => __('Policy answers grounded', 'geeky-bot'),
                            'description' => __('Shipping, refund and returns questions need a selected policy page, or the assistant sends the fallback answer.', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-store-knowledge'),
                            'action' => __('Choose pages', 'geeky-bot'),
                        ),
                        array(
                            'ready' => (bool) $widget_ready,
                            'title' => __('Storefront widget enabled', 'geeky-bot'),
                            'description' => __('Shoppers only see the assistant once the widget is live on public store pages.', 'geeky-bot'),
                            'url' => admin_url('admin.php?page=geekybot-widget'),
                            'action' => __('Open widget', 'geeky-bot'),
                        ),
                    );

                    foreach ($readiness_checks as $check) {
                        Components::task(array(
                            'title' => $check['title'],
                            'description' => $check['description'],
                            'severity' => $check['ready'] ? 'done' : 'high',
                            'action' => $check['ready'] ? array() : array(
                                'label' => $check['action'],
                                'url' => $check['url'],
                            ),
                            'status' => $check['ready'] ? array(
                                'label' => __('Ready', 'geeky-bot'),
                                'state' => 'ok',
                            ) : array(),
                        ));
                    }
                    ?>
                </ul>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php
                        $reason_total = array_sum(wp_list_pluck($reasons, 'total'));
                        Components::card_open(
                            __('Why answers failed', 'geeky-bot'),
                            $reason_total > 0 ? number_format_i18n($reason_total) : '',
                            false,
                            'gb2-fill'
                        );
                        ?>
                            <?php if (empty($reasons)) : ?>
                                <?php Components::empty_state(
                                    __('No failed answers recorded', 'geeky-bot'),
                                    __('When the assistant cannot ground an answer, the reason is logged here so you can fix the cause.', 'geeky-bot')
                                ); ?>
                            <?php else :
                                $reason_bars = array();
                                $tones = array('crit', 'warn', 'accent', 'mute');
                                // Resolve the label here rather than trusting the row's
                                // 'meta' key, so a raw database value can never leak into
                                // the interface.
                                foreach (array_slice($reasons, 0, 5) as $index => $reason) {
                                    $reason_meta = ConversationInsightsService::reason_meta(isset($reason['reason']) ? $reason['reason'] : '');
                                    $reason_bars[] = array(
                                        'label' => $reason_meta['label'],
                                        'value' => absint($reason['total']),
                                        'tone' => isset($tones[$index]) ? $tones[$index] : 'mute',
                                    );
                                }
                                Components::bars($reason_bars);
                                $top_reason_meta = ConversationInsightsService::reason_meta(isset($reasons[0]['reason']) ? $reasons[0]['reason'] : '');
                                ?>
                                <?php if (!empty($top_reason_meta['action'])) : ?>
                                    <p class="gb2-note"><?php echo esc_html($top_reason_meta['action']); ?></p>
                                <?php endif; ?>
                                <a class="gb2-link" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php
                                    esc_html_e('Open conversation review', 'geeky-bot'); ?></a>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php Components::rule(__('What shoppers ask', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-8">
                        <?php Components::card_open(__('Latest unanswered questions', 'geeky-bot'), '', true); ?>
                            <?php if (empty($recent_unanswered)) : ?>
                                <?php Components::empty_state(
                                    __('Nothing unanswered right now', 'geeky-bot'),
                                    __('Shopper phrases the assistant could not ground in your catalog or policy pages will appear here.', 'geeky-bot')
                                ); ?>
                            <?php else : ?>
                                <div class="gb2-scroll">
                                    <table class="gb2-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Shopper asked', 'geeky-bot'); ?></th>
                                                <th><?php esc_html_e('Reason', 'geeky-bot'); ?></th>
                                                <th><?php esc_html_e('When', 'geeky-bot'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($recent_unanswered as $row) :
                                            // recent_unanswered() casts each row to an object, so these
                                            // are property reads, not array offsets.
                                            $question = isset($row->question) ? (string) $row->question : '';
                                            $meta = ConversationInsightsService::reason_meta(isset($row->reason) ? $row->reason : '');
                                            ?>
                                            <tr>
                                                <td><code><?php echo esc_html(wp_trim_words($question, 12, '…')); ?></code></td>
                                                <td><?php Components::pill($meta['label'], 'neutral', false); ?></td>
                                                <td><?php echo esc_html($this->compact_datetime_label(isset($row->created_at) ? $row->created_at : '')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Conversation review signal', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <div class="gb2-keyvalues">
                                <div class="gb2-keyvalue">
                                    <span><?php esc_html_e('Messages stored', 'geeky-bot'); ?></span>
                                    <strong><?php echo esc_html(number_format_i18n($messages_count)); ?></strong>
                                </div>
                                <div class="gb2-keyvalue">
                                    <span><?php esc_html_e('Now handled', 'geeky-bot'); ?></span>
                                    <strong><?php echo esc_html(number_format_i18n($handled_count)); ?></strong>
                                </div>
                                <div class="gb2-keyvalue">
                                    <span><?php esc_html_e('Needs review', 'geeky-bot'); ?></span>
                                    <strong><?php echo esc_html(number_format_i18n($review_count)); ?></strong>
                                </div>
                            </div>
                            <p class="gb2-note"><?php esc_html_e('Every unanswered shopper phrase becomes a signal for product data, synonyms, policy pages, or future sales rules.', 'geeky-bot'); ?></p>
                            <a class="gb2-link" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php
                                esc_html_e('Open conversation review', 'geeky-bot'); ?></a>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php Components::rule(__('Right now', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Recent conversations', 'geeky-bot'), '', true, 'gb2-fill'); ?>
                            <?php if (empty($recent_rows)) : ?>
                                <?php Components::empty_state(
                                    __('No conversations yet', 'geeky-bot'),
                                    __('Shopper conversations appear here as soon as the widget is used on your store.', 'geeky-bot'),
                                    array('label' => __('Open store', 'geeky-bot'), 'url' => home_url('/'))
                                ); ?>
                            <?php else : ?>
                                <ul class="gb2-feed">
                                    <?php foreach ($recent_rows as $row) :
                                        $unanswered_in_session = absint(isset($row['unanswered_count']) ? $row['unanswered_count'] : 0);
                                        $clicks = absint(isset($row['product_clicks']) ? $row['product_clicks'] : 0);
                                        $message = trim((string) (isset($row['last_shopper_message']) ? $row['last_shopper_message'] : ''));

                                        if ($unanswered_in_session > 0) {
                                            $tone = 'miss';
                                            $meta = _n('%s question went unanswered', '%s questions went unanswered', $unanswered_in_session, 'geeky-bot');
                                            $meta = sprintf($meta, number_format_i18n($unanswered_in_session));
                                        } elseif ($clicks > 0) {
                                            $tone = 'cart';
                                            /* translators: %s: number of products the shopper opened. */
                                            $meta = sprintf(_n('%s product opened', '%s products opened', $clicks, 'geeky-bot'), number_format_i18n($clicks));
                                        } else {
                                            $tone = 'ask';
                                            /* translators: %s: number of messages in the conversation. */
                                            $meta = sprintf(_n('%s message', '%s messages', absint($row['message_count']), 'geeky-bot'), number_format_i18n(absint($row['message_count'])));
                                        }

                                        Components::feed_item(array(
                                            'text' => $message !== '' ? $message : __('(no shopper message stored)', 'geeky-bot'),
                                            'meta' => $meta,
                                            'when' => $this->compact_datetime_label(isset($row['updated_at']) ? $row['updated_at'] : ''),
                                            'tone' => $tone,
                                        ));
                                    endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Products the assistant shows', 'geeky-bot'), '', true, 'gb2-fill'); ?>
                            <?php if (!$pro_active || empty($pro_products)) : ?>
                                <?php Components::empty_state(
                                    __('No product activity recorded', 'geeky-bot'),
                                    $pro_active
                                        ? __('Once shoppers open products through the assistant, the most requested ones are listed here.', 'geeky-bot')
                                        : __('Per-product tracking is part of Commerce Pro, which records the buying actions shoppers take inside the chat.', 'geeky-bot')
                                ); ?>
                            <?php else : ?>
                                <div class="gb2-scroll">
                                    <table class="gb2-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Product', 'geeky-bot'); ?></th>
                                                <th style="text-align:right"><?php esc_html_e('Requests', 'geeky-bot'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($pro_products as $product) : ?>
                                            <tr>
                                                <td><?php echo esc_html($product['name']); ?></td>
                                                <td class="gb2-table__num"><?php echo esc_html(number_format_i18n($product['total'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('What shoppers see', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <?php $this->widget_preview($settings, 'open'); ?>
                            <div class="gb2-code-list" style="margin-top:12px">
                                <code>comfortable shoes size 42 red and white</code>
                                <code>compare cheaper one and second</code>
                                <code>add 2 hoodies to cart</code>
                            </div>
                            <div class="gb2-inline" style="margin-top:12px">
                                <?php
                                Components::action_link(array(
                                    'label' => __('Tune widget', 'geeky-bot'),
                                    'url' => admin_url('admin.php?page=geekybot-widget'),
                                    'variant' => 'primary',
                                ));
                                Components::action_link(array(
                                    'label' => __('Open storefront', 'geeky-bot'),
                                    'url' => home_url('/'),
                                    'external' => true,
                                ));
                                ?>
                            </div>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php Components::rule(__('Reference', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Product discovery signals', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <p style="margin:0 0 12px;font-size:12px;color:var(--gb2-mute)"><?php
                                esc_html_e('Search uses WooCommerce product data first, then shopper-language synonyms and close-match rules.', 'geeky-bot'); ?></p>
                            <div class="gb2-keyvalues">
                                <div class="gb2-keyvalue">
                                    <span><?php esc_html_e('Natural search', 'geeky-bot'); ?></span>
                                    <strong><?php echo esc_html($natural_search_ready ? __('On', 'geeky-bot') : __('Off', 'geeky-bot')); ?></strong>
                                </div>
                                <div class="gb2-keyvalue">
                                    <span><?php esc_html_e('Match mode', 'geeky-bot'); ?></span>
                                    <strong style="font-size:13px"><?php echo esc_html($fallback_mode); ?></strong>
                                </div>
                            </div>
                            <div class="gb2-chips" style="margin-top:12px">
                                <span><?php esc_html_e('Product name', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('SKU', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Categories', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Tags', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Attributes', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Price phrases', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Stock', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Sale signals', 'geeky-bot'); ?></span>
                            </div>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Beyond simple product search', 'geeky-bot'), $pro_active ? __('Active', 'geeky-bot') : __('Commerce Pro', 'geeky-bot'), false, 'gb2-fill'); ?>
                            <p style="margin:0 0 12px;font-size:12px;color:var(--gb2-mute)"><?php
                                esc_html_e('Commerce Pro extends the assistant into buying actions while keeping answers grounded in store data.', 'geeky-bot'); ?></p>
                            <ul class="gb2-checklist">
                                <li><?php esc_html_e('Natural product search', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Product comparison', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Cart add, update and remove', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Checkout handoff', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Order history', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Coupons and deals', 'geeky-bot'); ?></li>
                            </ul>
                            <?php if (!$pro_active) : ?>
                                <a class="gb2-link" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-commerce-pro')); ?>"><?php
                                    esc_html_e('See what Commerce Pro adds', 'geeky-bot'); ?></a>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Try examples from this store', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <p style="margin:0 0 12px;font-size:12px;color:var(--gb2-mute)"><?php
                                esc_html_e('Guided Demo uses visible indexed products and approved policy pages, then labels which requests are free and which need Commerce Pro.', 'geeky-bot'); ?></p>
                            <ul class="gb2-checklist">
                                <li><?php esc_html_e('Product names, prices, attributes and sale state', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Selected shipping, refund and store pages', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Find, understand and choose products', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Variations, cart actions and checkout flow', 'geeky-bot'); ?></li>
                            </ul>
                            <div style="margin-top:12px">
                                <?php Components::action_link(array(
                                    'label' => __('Open Guided Demo', 'geeky-bot'),
                                    'url' => admin_url('admin.php?page=geekybot-guided-demo'),
                                    'variant' => 'primary',
                                )); ?>
                            </div>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php Components::rule(__('Go to', 'geeky-bot')); ?>

                <nav class="gb2-quicknav" aria-label="<?php esc_attr_e('Geeky Bot admin sections', 'geeky-bot'); ?>">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-setup')); ?>"><?php esc_html_e('Setup', 'geeky-bot'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><?php esc_html_e('Guided Demo', 'geeky-bot'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-widget')); ?>"><?php esc_html_e('Widget', 'geeky-bot'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant')); ?>"><?php esc_html_e('Product search', 'geeky-bot'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-store-knowledge')); ?>"><?php esc_html_e('Store knowledge', 'geeky-bot'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Conversations', 'geeky-bot'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-analytics')); ?>"><?php esc_html_e('Analytics', 'geeky-bot'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-integrations')); ?>"><?php esc_html_e('Integrations', 'geeky-bot'); ?></a>
                </nav>
            </div>

        </div>
        <?php
    }

    public function setup_wizard() {
        $ctx = $this->context();
        $system_checks = OnboardingService::system_checks();
        $demo_service = new GuidedDemoService();
        $demo_counts = $demo_service->counts();
        $privacy_ready = in_array($ctx['settings']['chat_history_enabled'], array('yes', 'no'), true)
            && absint($ctx['settings']['retention_days']) >= 1;
        $readiness = $this->setup_readiness_summary(
            $ctx['wc_ready'],
            $ctx['indexed_count'],
            $ctx['policy_count'],
            $ctx['provider_ready'],
            $ctx['settings'],
            $demo_counts,
            $privacy_ready
        );
        $state = OnboardingService::state();
        if ($state['status'] === 'not_started') {
            OnboardingService::set_status('started');
            $state = OnboardingService::state();
        }
        $guest_storage = $ctx['settings']['allow_guest_sessions'] === 'yes'
            ? __('Guest conversations appear in the admin review center.', 'geeky-bot')
            : __('Guest conversations stay in the shopper browser and do not appear in admin.', 'geeky-bot');
        $woocommerce_action = $this->woocommerce_setup_action();
        $product_action_url = $ctx['wc_ready'] ? admin_url('admin.php?page=geekybot-product-assistant') : '';
        $product_action_label = $ctx['wc_ready'] ? __('Prepare products', 'geeky-bot') : __('Waiting for WooCommerce', 'geeky-bot');
        if ($ctx['wc_ready'] && $ctx['indexed_count'] > 0) {
            $demo_action_url = admin_url('admin.php?page=geekybot-guided-demo');
            $demo_action_label = __('Try real examples', 'geeky-bot');
        } elseif ($ctx['wc_ready']) {
            $demo_action_url = '';
            $demo_action_label = __('Waiting for product index', 'geeky-bot');
        } else {
            $demo_action_url = '';
            $demo_action_label = __('Waiting for WooCommerce', 'geeky-bot');
        }

        if (!$ctx['wc_ready']) {
            $hero_action_url = $woocommerce_action['url'];
            $hero_action_label = $woocommerce_action['label'];
        } elseif ($ctx['indexed_count'] < 1) {
            $hero_action_url = admin_url('admin.php?page=geekybot-product-assistant');
            $hero_action_label = __('Prepare products', 'geeky-bot');
        } else {
            $hero_action_url = admin_url('admin.php?page=geekybot-guided-demo');
            $hero_action_label = __('Open Guided Demo', 'geeky-bot');
        }

        $ready_count_label = sprintf(
            /* translators: %s: number of launch checks that are ready. */
            _n('%s launch check ready', '%s launch checks ready', absint($readiness['counts']['ready']), 'geeky-bot'),
            number_format_i18n(absint($readiness['counts']['ready']))
        );
        $readiness_summary = sprintf(
            /* translators: 1: ready checks, 2: unconfigured checks, 3: blocked checks, 4: optional checks. */
            __('%1$s ready · %2$s not configured · %3$s blocked · %4$s optional. The same live readiness score is used throughout Geeky Bot.', 'geeky-bot'),
            number_format_i18n($readiness['counts']['ready']),
            number_format_i18n($readiness['counts']['needs_attention']),
            number_format_i18n($readiness['counts']['blocked']),
            number_format_i18n($readiness['counts']['optional'])
        );
        $provider_detail = sprintf(
            /* translators: %s: active answer-provider mode. */
            __('Current mode: %s. Local grounded mode is available without an external provider; Zywrap and OpenAI remain optional.', 'geeky-bot'),
            $this->provider_label($ctx['settings'])
        );
        $index_detail = $ctx['wc_ready']
            ? sprintf(
                /* translators: %s: number of indexed products. */
                __('Indexed products: %s. Rebuild after large imports or major catalog changes.', 'geeky-bot'),
                number_format_i18n($ctx['indexed_count'])
            )
            : __('Blocked until WooCommerce is active. Product preparation and indexing cannot run without the store catalog.', 'geeky-bot');
        $privacy_detail = sprintf(
            /* translators: 1: conversation retention in days, 2: guest-storage status. */
            __('Retention: %1$s days. %2$s', 'geeky-bot'),
            number_format_i18n(absint($ctx['settings']['retention_days'])),
            $guest_storage
        );
        $demo_detail = $ctx['wc_ready'] && $ctx['indexed_count'] > 0
            ? sprintf(
                /* translators: 1: number of Free demo examples, 2: number of Commerce Pro demo examples. */
                __('%1$s store-grounded Free examples and %2$s Commerce Pro examples are available.', 'geeky-bot'),
                number_format_i18n(absint($demo_counts['free'])),
                number_format_i18n(absint($demo_counts['pro']))
            )
            : __('Blocked until WooCommerce has at least one visible indexed product.', 'geeky-bot');
        $setup_status_detail = sprintf(
            /* translators: %s: current setup-review status. */
            __('Current status: %s. This does not disable any feature or remove settings.', 'geeky-bot'),
            str_replace('_', ' ', (string) $state['status'])
        );
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-setup-wizard geekybot-first-run-v2">
            <?php $this->page_hero(__('Setup Wizard', 'geeky-bot'), __('Launch a useful WooCommerce shopping assistant through a resumable seven-step path. Every check uses the live store configuration, so you can leave and continue later without losing progress.', 'geeky-bot'), __('Guided launch', 'geeky-bot'), $hero_action_url, $hero_action_label); ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only first-run notice flag. ?>
            <?php if (!empty($_GET['gb_first_run'])) : ?>
                <div class="notice notice-info"><p><?php esc_html_e('Welcome to Geeky Bot. Complete the important checks below, or leave this page and return from Geeky Bot → Setup Wizard at any time.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php if (!empty($_GET['gb_onboarding'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Setup review status updated.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>

            <div class="gb2-main">

                <div class="gb2-grid">
                    <div class="gb2-col-8">
                        <?php Components::card_open(
                            __('Set up the assistant in dependency order', 'geeky-bot'),
                            $readiness['score'] >= 100
                                ? __('All checks ready', 'geeky-bot')
                                : $ready_count_label,
                            true
                        ); ?>
                        <ul class="gb2-tasks">
                            <?php
                            $this->setup_task_card(__('Store system check', 'geeky-bot'), $readiness['steps']['system'], __('Confirm WooCommerce, WordPress, PHP and the local REST runtime before configuring shopper features.', 'geeky-bot'), $ctx['wc_ready'] ? admin_url('admin.php?page=geekybot-setup#gb-system-checks') : $woocommerce_action['url'], $ctx['wc_ready'] ? __('Review system', 'geeky-bot') : $woocommerce_action['label'], '01');
                            $this->setup_task_card(__('Answer mode', 'geeky-bot'), $readiness['steps']['provider'], $provider_detail, admin_url('admin.php?page=geekybot-integrations'), __('Review answer mode', 'geeky-bot'), '02');
                            $this->setup_task_card(__('Product search index', 'geeky-bot'), $readiness['steps']['index'], $index_detail, $product_action_url, $product_action_label, '03');
                            $this->setup_task_card(__('Store knowledge', 'geeky-bot'), $readiness['steps']['knowledge'], __('Approve public shipping, returns, refunds, payment or warranty pages. Optional for product discovery — missing information stays unanswered rather than invented.', 'geeky-bot'), admin_url('admin.php?page=geekybot-store-knowledge'), __('Select policy pages', 'geeky-bot'), '04');
                            $this->setup_task_card(__('Storefront widget', 'geeky-bot'), $readiness['steps']['widget'], __('Confirm the assistant name, welcome copy, launcher, mobile position and product-card volume.', 'geeky-bot'), admin_url('admin.php?page=geekybot-widget'), __('Configure widget', 'geeky-bot'), '05');
                            $this->setup_task_card(__('Privacy and conversations', 'geeky-bot'), $readiness['steps']['privacy'], $privacy_detail, admin_url('admin.php?page=geekybot-settings#gb-settings-privacy'), __('Review privacy', 'geeky-bot'), '06');
                            $this->setup_task_card(__('Guided Demo', 'geeky-bot'), $readiness['steps']['demo'], $demo_detail, $demo_action_url, $demo_action_label, '07');
                            ?>
                        </ul>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Launch progress', 'geeky-bot'), '', false); ?>
                            <div class="gb2-inline" style="gap:14px;flex-wrap:nowrap">
                                <svg width="52" height="52" viewBox="0 0 52 52" aria-hidden="true" style="flex:none">
                                    <circle cx="26" cy="26" r="21" fill="none" stroke="var(--gb2-line-soft)" stroke-width="6" />
                                    <circle cx="26" cy="26" r="21" fill="none"
                                        stroke="<?php echo esc_attr($readiness['score'] >= 100 ? 'var(--gb2-ok)' : 'var(--gb2-accent)'); ?>"
                                        stroke-width="6" stroke-linecap="round"
                                        stroke-dasharray="<?php echo esc_attr(round(2 * M_PI * 21, 1)); ?>"
                                        stroke-dashoffset="<?php echo esc_attr(round((2 * M_PI * 21) * (1 - (min(100, max(0, (int) $readiness['score'])) / 100)), 1)); ?>"
                                        transform="rotate(-90 26 26)" />
                                </svg>
                                <div style="min-width:0">
                                    <div style="font-size:20px;font-weight:640;line-height:1.1"><?php
                                        echo esc_html(number_format_i18n($readiness['score'])); ?>%</div>
                                    <div style="font-size:11.5px;color:var(--gb2-mute)"><?php
                                        echo esc_html($readiness['score'] >= 100 ? __('Ready for a full rehearsal', 'geeky-bot') : $ready_count_label); ?></div>
                                </div>
                            </div>
                            <p class="gb2-note"><?php echo esc_html($readiness_summary); ?></p>
                        <?php Components::card_close(); ?>

                        <div style="height:14px"></div>

                        <?php Components::card_open(__('System checks', 'geeky-bot'), __('Local only', 'geeky-bot'), true); ?>
                            <ul class="gb2-tasks" id="gb-system-checks">
                                <?php foreach ($system_checks as $check) :
                                    $ok = !empty($check['ready']);
                                    $warn = $ok && !empty($check['warning']);
                                    Components::task(array(
                                        'title' => $check['label'],
                                        'description' => $check['detail'],
                                        'severity' => $ok ? ($warn ? 'high' : 'done') : 'critical',
                                        'status' => array(
                                            'label' => $ok ? ($warn ? __('Check', 'geeky-bot') : __('OK', 'geeky-bot')) : __('Failed', 'geeky-bot'),
                                            'state' => $ok ? ($warn ? 'warn' : 'ok') : 'crit',
                                        ),
                                    ));
                                endforeach; ?>
                            </ul>
                        <?php Components::card_close(); ?>

                        <?php if (!$ctx['wc_ready']) : ?>
                            <div style="margin-top:14px">
                                <?php Components::action_link(array(
                                    'label' => $woocommerce_action['label'],
                                    'url' => $woocommerce_action['url'],
                                    'variant' => 'primary',
                                )); ?>
                            </div>
                        <?php endif; ?>

                        <div style="height:14px"></div>

                        <?php Components::card_open(__('Review status', 'geeky-bot'), '', false); ?>
                            <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                echo esc_html($setup_status_detail); ?></p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_onboarding_action'); ?>
                                <input type="hidden" name="action" value="geekybot_onboarding_action" />
                                <div class="gb2-inline">
                                    <button class="gb2-btn gb2-btn--primary" type="submit" name="onboarding_action" value="reviewed"><?php
                                        esc_html_e('Finish setup review', 'geeky-bot'); ?></button>
                                    <button class="gb2-btn" type="submit" name="onboarding_action" value="dismiss"><?php
                                        esc_html_e('Skip for now', 'geeky-bot'); ?></button>
                                </div>
                            </form>
                            <p class="gb2-field__help" style="margin-top:10px"><?php
                                esc_html_e('The Setup Wizard stays available from the Geeky Bot menu.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php Components::rule(__('How this setup works', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-4">
                        <?php Components::card_open(__('WooCommerce first', 'geeky-bot'), '', false); ?>
                            <p style="margin:0;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Products and the Guided Demo stay blocked until the store runtime is available.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>
                    </div>
                    <div class="gb2-col-4">
                        <?php Components::card_open(__('No AI account required', 'geeky-bot'), '', false); ?>
                            <p style="margin:0;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Local grounded mode works without an external provider or API key.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>
                    </div>
                    <div class="gb2-col-4">
                        <?php Components::card_open(__('You can stop anytime', 'geeky-bot'), '', false); ?>
                            <p style="margin:0;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Leave this page and return later. Progress is read from your live store, so nothing is lost.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function guided_demo() {
        $ctx = $this->context();
        $service = new GuidedDemoService();
        $examples = $service->examples();
        $counts = $service->counts($examples);
        $widget_enabled = $ctx['settings']['widget_enabled'] === 'yes';
        $product_count_object = post_type_exists('product') ? wp_count_posts('product') : null;
        $published_products = is_object($product_count_object) ? absint($product_count_object->publish ?? 0) : 0;
        $woocommerce_action = $this->woocommerce_setup_action();
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-guided-demo">
            <?php $this->page_hero(__('Guided Demo', 'geeky-bot'), __('Try Geeky Bot with realistic shopper requests generated from this store’s own indexed products and approved policy pages. Refresh the board whenever you want a different catalog sample.', 'geeky-bot'), __('Real-store rehearsal', 'geeky-bot'), home_url('/'), __('Open storefront', 'geeky-bot'), true); ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php if (!empty($_GET['gb_demo_refreshed'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Guided Demo examples regenerated from the current store data.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>
            <?php if (!$widget_enabled) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e('The storefront widget is off. You can still copy examples, but turn the widget on before using Try on storefront.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>

            <div class="gb2-main">

                <?php Components::metrics(array(
                    array(
                        'label' => __('Real-store examples', 'geeky-bot'),
                        'value' => number_format_i18n(absint($counts['total'])),
                        'base' => __('Generated locally, with no AI usage', 'geeky-bot'),
                    ),
                    array(
                        'label' => __('Free capabilities', 'geeky-bot'),
                        'value' => number_format_i18n(absint($counts['free'])),
                        'base' => __('Find, understand and choose products', 'geeky-bot'),
                    ),
                    array(
                        'label' => __('Commerce Pro examples', 'geeky-bot'),
                        'value' => number_format_i18n(absint($counts['pro'])),
                        'base' => absint($counts['proUnlocked']) > 0
                            ? __('Buying actions are unlocked', 'geeky-bot')
                            : __('Preview of the paid buying journey', 'geeky-bot'),
                    ),
                )); ?>

                <div class="gb2-rule"><b><?php esc_html_e('Examples from your catalog', 'geeky-bot'); ?></b></div>

                <div class="gb2-inline" style="justify-content:flex-end;margin-bottom:14px">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('geekybot_refresh_guided_demo'); ?>
                        <input type="hidden" name="action" value="geekybot_refresh_guided_demo" />
                        <button class="gb2-btn" type="submit"><?php esc_html_e('Refresh examples', 'geeky-bot'); ?></button>
                    </form>
                </div>

                <?php if (empty($examples)) : ?>
                    <?php
                    Components::card_open('', '', true);

                    if (!$ctx['wc_ready']) {
                        Components::empty_state(
                            __('Connect the store catalog before generating examples', 'geeky-bot'),
                            __('Guided Demo uses real, visible WooCommerce products. Activate WooCommerce, add or import products, then build the product search index.', 'geeky-bot'),
                            array('label' => $woocommerce_action['label'], 'url' => $woocommerce_action['url'])
                        );
                    } elseif ($published_products < 1) {
                        Components::empty_state(
                            __('Add or import at least one visible product', 'geeky-bot'),
                            __('Examples are never generated from fake demo data. Add a real WooCommerce product, then build the product search index.', 'geeky-bot'),
                            array('label' => __('Add a product', 'geeky-bot'), 'url' => admin_url('post-new.php?post_type=product'))
                        );
                    } else {
                        Components::empty_state(
                            __('Build the product index to generate real examples', 'geeky-bot'),
                            sprintf(
                                /* translators: %s: number of published WooCommerce products. */
                                __('%s published products are available, but none are ready in the Geeky Bot index yet.', 'geeky-bot'),
                                number_format_i18n($published_products)
                            ),
                            array('label' => __('Build the index', 'geeky-bot'), 'url' => admin_url('admin.php?page=geekybot-product-assistant'))
                        );
                    }

                    Components::card_close();
                    ?>
                <?php else : ?>
                    <div class="gb2-grid">
                        <?php foreach ($examples as $example) :
                            $is_pro = $example['tier'] === 'pro';
                            $locked = !empty($example['locked']);
                            $steps = !empty($example['steps']) ? (array) $example['steps'] : array($example['query']);
                            $demo_args = array('geekybot_demo' => $steps[0]);
                            if (count($steps) > 1) {
                                // The storefront sends the opener, then hands the
                                // merchant each remaining turn once the previous
                                // one has been answered.
                                $demo_args['geekybot_demo_steps'] = wp_json_encode(array_slice($steps, 1));
                            }
                            $try_url = add_query_arg($demo_args, home_url('/'));
                            ?>
                            <div class="gb2-col-4">
                                <?php Components::card_open('', '', false, 'gb2-fill'); ?>
                                    <div class="gb2-inline" style="margin-bottom:10px">
                                        <?php Components::pill(
                                            $is_pro ? __('Commerce Pro', 'geeky-bot') : __('Free', 'geeky-bot'),
                                            $is_pro ? 'neutral' : 'ok',
                                            false
                                        ); ?>
                                        <span style="font-size:11.5px;color:var(--gb2-faint)"><?php
                                            echo esc_html($example['feature']); ?></span>
                                        <?php if (!empty($example['isConversation'])) : ?>
                                            <span style="font-size:11.5px;color:var(--gb2-faint)" title="<?php
                                                esc_attr_e('Runs as a short conversation, not a single question.', 'geeky-bot'); ?>">&middot; <?php
                                                printf(
                                                    /* translators: %d: number of turns in the demo conversation. */
                                                    esc_html(_n('%d turn', '%d turns', count($steps), 'geeky-bot')),
                                                    (int) count($steps)
                                                ); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <h3 style="margin:0 0 4px;font-size:14px;font-weight:600;line-height:1.35"><?php
                                        echo esc_html($example['title']); ?></h3>
                                    <p style="margin:0 0 10px;font-size:12px;line-height:1.5;color:var(--gb2-mute)"><?php
                                        echo esc_html($example['description']); ?></p>

                                    <div class="gb2-code-list" style="margin-bottom:10px">
                                        <?php foreach ($steps as $step_index => $step) : ?>
                                            <code><?php
                                                if (count($steps) > 1) {
                                                    /* translators: %d: turn number in a demo conversation. */
                                                    echo esc_html(sprintf(__('%d.', 'geeky-bot'), $step_index + 1)) . ' ';
                                                }
                                                echo esc_html($step);
                                            ?></code>
                                        <?php endforeach; ?>
                                    </div>

                                    <p style="margin:0 0 10px;font-size:11.5px;color:var(--gb2-faint)">
                                        <?php esc_html_e('Built from', 'geeky-bot'); ?>
                                        <?php if (!empty($example['sourceUrl'])) : ?>
                                            <a class="gb2-link" style="margin:0" href="<?php echo esc_url($example['sourceUrl']); ?>"><?php
                                                echo esc_html($example['sourceLabel']); ?></a>
                                        <?php else : ?>
                                            <strong style="color:var(--gb2-mute)"><?php echo esc_html($example['sourceLabel']); ?></strong>
                                        <?php endif; ?>
                                    </p>

                                    <div class="gb2-inline">
                                        <?php if ($locked) : ?>
                                            <?php Components::action_link(array(
                                                'label' => __('View Commerce Pro', 'geeky-bot'),
                                                'url' => admin_url('admin.php?page=geekybot-addons'),
                                                'variant' => 'primary',
                                            )); ?>
                                        <?php elseif ($widget_enabled) : ?>
                                            <?php Components::action_link(array(
                                                'label' => __('Try on storefront', 'geeky-bot'),
                                                'url' => $try_url,
                                                'variant' => 'primary',
                                                'external' => true,
                                            )); ?>
                                        <?php else : ?>
                                            <span class="gb2-btn" aria-disabled="true" style="opacity:.5"><?php
                                                esc_html_e('Widget is off', 'geeky-bot'); ?></span>
                                        <?php endif; ?>
                                        <?php // data-gb-copy-demo is the hook assets/js/admin.js binds to. ?>
                                        <button class="gb2-btn" type="button" data-gb-copy-demo="<?php echo esc_attr($example['query']); ?>"><?php
                                            esc_html_e('Copy', 'geeky-bot'); ?></button>
                                    </div>

                                    <?php if ($locked) : ?>
                                        <p class="gb2-note"><?php esc_html_e('This example uses a buying action available in Commerce Pro.', 'geeky-bot'); ?></p>
                                    <?php endif; ?>
                                <?php Components::card_close(); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="gb2-rule"><b><?php esc_html_e('What each tier proves', 'geeky-bot'); ?></b></div>

                <div class="gb2-grid">
                    <div class="gb2-col-6">
                        <?php Components::card_open(__('Free proves product intelligence', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <p style="margin:0;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Shoppers find products, apply real constraints, ask product questions, receive grounded recommendations, and read approved store-policy answers.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>
                    </div>
                    <div class="gb2-col-6">
                        <?php Components::card_open(__('Commerce Pro completes the sale', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <p style="margin:0;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Shoppers select variations, add products, manage the cart, and continue to checkout without leaving the assistant.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function chat_widget() {
        $ctx = $this->context();
        $settings = $ctx['settings'];
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-widget">
            <?php $this->page_hero(__('Storefront Widget', 'geeky-bot'), __('Shape the live shopper panel: welcome copy, product-card volume, starter prompts, accent color, placement and mobile-ready preview.', 'geeky-bot'), __('Shopper experience', 'geeky-bot'), home_url('/'), __('Open storefront', 'geeky-bot'), true); ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php if (!empty($_GET['updated'])) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Widget settings saved.', 'geeky-bot'); ?></p></div><?php endif; ?>
            <div class="gb2-main">
                <div class="gb2-grid">
                    <div class="gb2-col-7">
                        <form method="post">
                            <?php wp_nonce_field('geekybot_save_settings'); ?>
                            <input type="hidden" name="geekybot_settings_action" value="save" />
                            <input type="hidden" name="geekybot_settings_scope" value="partial" />
                            <input type="hidden" name="geekybot_redirect" value="<?php echo esc_url(admin_url('admin.php?page=geekybot-widget')); ?>" />

                            <?php Components::card_open(__('Shopper-facing basics', 'geeky-bot'), '', false); ?>
                                <div class="gb2-switch-row" style="padding-top:0">
                                    <input type="hidden" name="widget_enabled" value="no" />
                                    <input type="checkbox" id="gb2-widget-enabled" name="widget_enabled" value="yes" <?php checked($settings['widget_enabled'], 'yes'); ?> />
                                    <span class="gb2-switch-row__text">
                                        <label for="gb2-widget-enabled"><strong><?php esc_html_e('Show the assistant on my store', 'geeky-bot'); ?></strong></label>
                                        <span><?php esc_html_e('Adds the assistant button to your public store pages.', 'geeky-bot'); ?></span>
                                    </span>
                                </div>

                                <div style="height:14px"></div>

                                <div class="gb2-field-row">
                                    <div class="gb2-field">
                                        <label for="gb2-assistant-name"><?php esc_html_e('Assistant name', 'geeky-bot'); ?></label>
                                        <input class="gb2-input" id="gb2-assistant-name" name="assistant_name" type="text" value="<?php echo esc_attr($settings['assistant_name']); ?>" />
                                    </div>
                                    <div class="gb2-field">
                                        <label for="gb2-assistant-subtitle"><?php esc_html_e('Subtitle', 'geeky-bot'); ?></label>
                                        <input class="gb2-input" id="gb2-assistant-subtitle" name="assistant_subtitle" type="text" value="<?php echo esc_attr($settings['assistant_subtitle']); ?>" />
                                    </div>
                                </div>

                                <div class="gb2-field">
                                    <label for="gb2-welcome"><?php esc_html_e('Welcome message', 'geeky-bot'); ?></label>
                                    <textarea class="gb2-input" id="gb2-welcome" name="welcome_message" rows="3"><?php echo esc_textarea($settings['welcome_message']); ?></textarea>
                                    <p class="gb2-field__help"><?php esc_html_e('The first thing a shopper reads. Keep it short and inviting.', 'geeky-bot'); ?></p>
                                </div>

                                <div class="gb2-field">
                                    <label for="gb2-fallback"><?php esc_html_e('When the assistant cannot answer', 'geeky-bot'); ?></label>
                                    <textarea class="gb2-input" id="gb2-fallback" name="fallback_human_message" rows="3"><?php echo esc_textarea($settings['fallback_human_message']); ?></textarea>
                                    <p class="gb2-field__help"><?php esc_html_e('Sent when no catalog data or approved policy page covers the question.', 'geeky-bot'); ?></p>
                                </div>

                                <div class="gb2-field-row">
                                    <div class="gb2-field">
                                        <label for="gb2-accent"><?php esc_html_e('Accent color', 'geeky-bot'); ?></label>
                                        <input class="gb2-input" id="gb2-accent" name="accent_color" type="color" value="<?php echo esc_attr($settings['accent_color']); ?>" style="height:34px;padding:3px" />
                                    </div>
                                    <div class="gb2-field">
                                        <label for="gb2-position"><?php esc_html_e('Position on screen', 'geeky-bot'); ?></label>
                                        <select class="gb2-input" id="gb2-position" name="button_position">
                                            <option value="right" <?php selected($settings['button_position'], 'right'); ?>><?php esc_html_e('Right', 'geeky-bot'); ?></option>
                                            <option value="left" <?php selected($settings['button_position'], 'left'); ?>><?php esc_html_e('Left', 'geeky-bot'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="gb2-field">
                                    <label for="gb2-max-products"><?php esc_html_e('Products per answer', 'geeky-bot'); ?></label>
                                    <input class="gb2-input" id="gb2-max-products" name="max_products" type="number" min="1" max="8" value="<?php echo esc_attr(absint($settings['max_products'])); ?>" />
                                    <p class="gb2-field__help"><?php esc_html_e('Three or four keeps replies readable on a phone.', 'geeky-bot'); ?></p>
                                </div>
                            <?php Components::card_close(); ?>

                            <div style="height:14px"></div>

                            <?php Components::card_open(__('Shopper invitation', 'geeky-bot'), '', false); ?>
                                <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                    esc_html_e('Shows one friendly prompt above the button after a shopper has been on the page a while. The chat stays closed until they open it.', 'geeky-bot'); ?></p>

                                <div class="gb2-switch-row" style="padding-top:0">
                                    <input type="hidden" name="shopper_invitation_enabled" value="no" />
                                    <input type="checkbox" id="gb2-invitation-enabled" name="shopper_invitation_enabled" value="yes" <?php checked($settings['shopper_invitation_enabled'], 'yes'); ?> />
                                    <span class="gb2-switch-row__text">
                                        <label for="gb2-invitation-enabled"><strong><?php esc_html_e('Show the invitation', 'geeky-bot'); ?></strong></label>
                                        <span><?php esc_html_e('Shown once per browser session, and never while the assistant is already open.', 'geeky-bot'); ?></span>
                                    </span>
                                </div>

                                <div style="height:14px"></div>

                                <div class="gb2-field">
                                    <label for="gb2-invitation-delay"><?php esc_html_e('Show it after', 'geeky-bot'); ?></label>
                                    <input class="gb2-input" id="gb2-invitation-delay" name="shopper_invitation_delay" type="number" min="3" max="60" value="<?php echo esc_attr(absint($settings['shopper_invitation_delay'])); ?>" />
                                    <p class="gb2-field__help"><?php esc_html_e('Seconds after the page loads. Ten to fifteen works well.', 'geeky-bot'); ?></p>
                                </div>

                                <div class="gb2-field">
                                    <label for="gb2-invitation-message"><?php esc_html_e('Invitation message', 'geeky-bot'); ?></label>
                                    <textarea class="gb2-input" id="gb2-invitation-message" name="shopper_invitation_message" rows="3" maxlength="160"><?php echo esc_textarea($settings['shopper_invitation_message']); ?></textarea>
                                    <p class="gb2-field__help"><?php esc_html_e('Keep it short and focused on helping shoppers choose.', 'geeky-bot'); ?></p>
                                </div>
                            <?php Components::card_close(); ?>

                            <div style="height:14px"></div>

                            <?php Components::card_open(__('Launcher button', 'geeky-bot'), '', false); ?>
                                <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                    esc_html_e('What shoppers see before they open the assistant.', 'geeky-bot'); ?></p>

                                <div class="gb2-field-row">
                                    <div class="gb2-field">
                                        <label for="gb2-launcher-icon"><?php esc_html_e('Icon', 'geeky-bot'); ?></label>
                                        <select class="gb2-input" id="gb2-launcher-icon" name="launcher_icon_source">
                                            <option value="default" <?php selected($settings['launcher_icon_source'], 'default'); ?>><?php esc_html_e('Default GeekyBot icon', 'geeky-bot'); ?></option>
                                            <option value="store_logo" <?php selected($settings['launcher_icon_source'], 'store_logo'); ?>><?php esc_html_e('Store logo', 'geeky-bot'); ?></option>
                                            <option value="custom" <?php selected($settings['launcher_icon_source'], 'custom'); ?>><?php esc_html_e('Custom image', 'geeky-bot'); ?></option>
                                        </select>
                                    </div>
                                    <div class="gb2-field">
                                        <?php $this->branding_image_picker('launcher_icon_attachment_id', $settings['launcher_icon_attachment_id'], __('Custom icon', 'geeky-bot')); ?>
                                    </div>
                                </div>

                                <div class="gb2-field-row">
                                    <div class="gb2-field">
                                        <label for="gb2-launcher-style"><?php esc_html_e('Style', 'geeky-bot'); ?></label>
                                        <select class="gb2-input" id="gb2-launcher-style" name="launcher_style">
                                            <option value="icon" <?php selected($settings['launcher_style'], 'icon'); ?>><?php esc_html_e('Icon only', 'geeky-bot'); ?></option>
                                            <option value="pill" <?php selected($settings['launcher_style'], 'pill'); ?>><?php esc_html_e('Icon and text', 'geeky-bot'); ?></option>
                                        </select>
                                        <p class="gb2-field__help"><?php esc_html_e('Icon only is compact. Adding text can reassure first-time visitors.', 'geeky-bot'); ?></p>
                                    </div>
                                    <div class="gb2-field">
                                        <label for="gb2-launcher-text"><?php esc_html_e('Button text', 'geeky-bot'); ?></label>
                                        <input class="gb2-input" id="gb2-launcher-text" name="launcher_text" type="text" value="<?php echo esc_attr($settings['launcher_text']); ?>" />
                                        <p class="gb2-field__help"><?php esc_html_e('Used only when the style is Icon and text.', 'geeky-bot'); ?></p>
                                    </div>
                                </div>
                            <?php Components::card_close(); ?>

                            <div style="height:14px"></div>

                            <?php Components::card_open(__('Widget header', 'geeky-bot'), '', false); ?>
                                <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                    esc_html_e('Keeps the opened assistant aligned with your store brand.', 'geeky-bot'); ?></p>

                                <div class="gb2-field-row">
                                    <div class="gb2-field">
                                        <label for="gb2-header-logo"><?php esc_html_e('Logo', 'geeky-bot'); ?></label>
                                        <select class="gb2-input" id="gb2-header-logo" name="header_logo_source">
                                            <option value="same" <?php selected($settings['header_logo_source'], 'same'); ?>><?php esc_html_e('Same as launcher', 'geeky-bot'); ?></option>
                                            <option value="store_logo" <?php selected($settings['header_logo_source'], 'store_logo'); ?>><?php esc_html_e('Store logo', 'geeky-bot'); ?></option>
                                            <option value="custom" <?php selected($settings['header_logo_source'], 'custom'); ?>><?php esc_html_e('Custom image', 'geeky-bot'); ?></option>
                                            <option value="hide" <?php selected($settings['header_logo_source'], 'hide'); ?>><?php esc_html_e('No logo', 'geeky-bot'); ?></option>
                                        </select>
                                    </div>
                                    <div class="gb2-field">
                                        <?php $this->branding_image_picker('header_logo_attachment_id', $settings['header_logo_attachment_id'], __('Custom header logo', 'geeky-bot')); ?>
                                    </div>
                                </div>

                                <div class="gb2-field-row">
                                    <div class="gb2-field">
                                        <label for="gb2-color-mode"><?php esc_html_e('Colour mode', 'geeky-bot'); ?></label>
                                        <select class="gb2-input" id="gb2-color-mode" name="widget_color_mode">
                                            <option value="light" <?php selected(isset($settings['widget_color_mode']) ? $settings['widget_color_mode'] : 'light', 'light'); ?>><?php esc_html_e('Light', 'geeky-bot'); ?></option>
                                            <option value="dark" <?php selected(isset($settings['widget_color_mode']) ? $settings['widget_color_mode'] : 'light', 'dark'); ?>><?php esc_html_e('Dark', 'geeky-bot'); ?></option>
                                            <option value="auto" <?php selected(isset($settings['widget_color_mode']) ? $settings['widget_color_mode'] : 'light', 'auto'); ?>><?php esc_html_e('Match the shopper device', 'geeky-bot'); ?></option>
                                        </select>
                                        <p class="gb2-field__help"><?php esc_html_e('Choose Match the shopper device if your storefront has a dark theme.', 'geeky-bot'); ?></p>
                                    </div>
                                    <div class="gb2-field">
                                        <label for="gb2-launcher-shape"><?php esc_html_e('Button shape', 'geeky-bot'); ?></label>
                                        <select class="gb2-input" id="gb2-launcher-shape" name="launcher_shape">
                                            <option value="round" <?php selected(isset($settings['launcher_shape']) ? $settings['launcher_shape'] : 'round', 'round'); ?>><?php esc_html_e('Circle', 'geeky-bot'); ?></option>
                                            <option value="rounded" <?php selected(isset($settings['launcher_shape']) ? $settings['launcher_shape'] : 'round', 'rounded'); ?>><?php esc_html_e('Rounded square', 'geeky-bot'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="gb2-field">
                                    <label for="gb2-header-style"><?php esc_html_e('Header style', 'geeky-bot'); ?></label>
                                    <select class="gb2-input" id="gb2-header-style" name="header_style">
                                        <option value="gradient" <?php selected($settings['header_style'], 'gradient'); ?>><?php esc_html_e('Gradient', 'geeky-bot'); ?></option>
                                        <option value="solid" <?php selected($settings['header_style'], 'solid'); ?>><?php esc_html_e('Solid accent', 'geeky-bot'); ?></option>
                                    </select>
                                </div>
                            <?php Components::card_close(); ?>

                            <div style="height:14px"></div>

                            <?php Components::card_open(__('Starter prompts', 'geeky-bot'), '', false); ?>
                                <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                    esc_html_e('The buttons a shopper sees before typing anything. Write them the way a customer would ask.', 'geeky-bot'); ?></p>
                                <div class="gb2-field">
                                    <label for="gb2-starter-prompts" class="gb2-screen-reader-text"><?php
                                        esc_html_e('Starter prompts', 'geeky-bot'); ?></label>
                                    <textarea class="gb2-input" id="gb2-starter-prompts" name="starter_prompts" rows="5"><?php
                                        echo esc_textarea(isset($settings['starter_prompts']) ? $settings['starter_prompts'] : ''); ?></textarea>
                                    <p class="gb2-field__help"><?php
                                        esc_html_e('One per line. The first four appear in the widget. Leave it empty to restore the defaults.', 'geeky-bot'); ?></p>
                                </div>
                            <?php Components::card_close(); ?>

                            <div style="height:14px"></div>

                            <?php Components::card_open(__('Shopper messages', 'geeky-bot'), '', false); ?>
                                <div class="gb2-switch-row" style="padding-top:0">
                                    <input type="hidden" name="user_avatar_enabled" value="no" />
                                    <input type="checkbox" id="gb2-user-avatar" name="user_avatar_enabled" value="yes" <?php checked(isset($settings['user_avatar_enabled']) ? $settings['user_avatar_enabled'] : 'no', 'yes'); ?> />
                                    <span class="gb2-switch-row__text">
                                        <label for="gb2-user-avatar"><strong><?php esc_html_e('Show an icon beside the shopper\'s messages', 'geeky-bot'); ?></strong></label>
                                        <span><?php esc_html_e('Off by default. The icon reserves space in every message to repeat what the alignment already shows.', 'geeky-bot'); ?></span>
                                    </span>
                                </div>
                            <?php Components::card_close(); ?>
                            <div class="gb2-inline" style="margin-top:14px">
                                <button class="gb2-btn gb2-btn--primary" type="submit"><?php esc_html_e('Save widget', 'geeky-bot'); ?></button>
                                <a class="gb2-btn" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php
                                    esc_html_e('Test on storefront', 'geeky-bot'); ?></a>
                            </div>
                        </form>
                    </div>

                    <div class="gb2-col-5">
                        <?php Components::card_open(__('Live preview', 'geeky-bot'), __('Updates as you type', 'geeky-bot'), false); ?>
                            <?php $this->widget_preview($settings); ?>
                            <ul class="gb2-checklist" style="margin-top:12px">
                                <li><?php esc_html_e('Styles are scoped so your theme is untouched', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Mobile-first panel', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Product cards ready', 'geeky-bot'); ?></li>
                            </ul>
                            <p class="gb2-note"><?php esc_html_e('Save before testing on the storefront — the preview shows unsaved changes.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>


                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function product_assistant() {
        $ctx = $this->context();
        $settings = $ctx['settings'];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search-lab filter.
        $test_query = isset($_GET['gb_test_query']) ? sanitize_text_field(wp_unslash($_GET['gb_test_query'])) : '';
        $search_results = array();
        $search_note = '';
        $search_analysis = array();
        $search_debug = array();
        if ($test_query !== '') {
            $product_service = new ProductService();
            $search_results = $product_service->search($test_query, 6);
            $search_context = $product_service->last_search_context();
            $search_note = isset($search_context['note']) ? (string) $search_context['note'] : '';
            $search_analysis = !empty($search_context['analysis']) && is_array($search_context['analysis']) ? $search_context['analysis'] : array();
            $search_debug = (new ProductIndexService())->debug_scores($test_query, 16);
        }
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-product-assistant">
            <?php $this->admin_notice_indexed(); ?>
            <?php $this->admin_notice_search_controls(); ?>
            <?php $this->page_hero(__('Product Search', 'geeky-bot'), __('Test real shopper phrases, inspect match reasons, tune synonyms, rebuild the product index, and control product-match ranking.', 'geeky-bot'), __('Product discovery', 'geeky-bot'), admin_url('edit.php?post_type=product'), __('Open products', 'geeky-bot')); ?>

            <div class="gb2-main">

                <?php Components::metrics(array(
                    array(
                        'label' => __('Products indexed', 'geeky-bot'),
                        'value' => number_format_i18n($ctx['indexed_count']),
                        'base' => $this->product_index_status_title($ctx),
                    ),
                    array(
                        'label' => __('Index status', 'geeky-bot'),
                        'value' => $this->product_index_status_label($ctx),
                        'base' => $ctx['index_status'] === 'current'
                            ? __('Last full index', 'geeky-bot')
                            : __('Rebuild after catalog changes', 'geeky-bot'),
                    ),
                    array(
                        'label' => __('Buyer search', 'geeky-bot'),
                        'value' => $settings['natural_search_enabled'] === 'yes' ? __('Natural', 'geeky-bot') : __('Keyword', 'geeky-bot'),
                        'base' => __('How shopper phrasing is read', 'geeky-bot'),
                    ),
                    array(
                        'label' => __('Product matches', 'geeky-bot'),
                        'value' => $settings['search_close_match_mode'] === 'strict' ? __('Exact', 'geeky-bot') : __('Smart', 'geeky-bot'),
                        'base' => __('How close a match must be', 'geeky-bot'),
                    ),
                )); ?>

                <?php Components::rule(__('Test what shoppers type', 'geeky-bot')); ?>

                <?php Components::card_open(__('Shopper phrase test lab', 'geeky-bot'), '', false); ?>
                    <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                        esc_html_e('Run real buyer language, see what Geeky Bot understood, then tune catalog data or synonyms before checking the storefront.', 'geeky-bot'); ?></p>

                    <form method="get" class="gb2-inline" style="flex-wrap:nowrap;gap:8px">
                        <input type="hidden" name="page" value="geekybot-product-assistant" />
                        <input class="gb2-input" type="search" name="gb_test_query" value="<?php echo esc_attr($test_query); ?>" placeholder="<?php esc_attr_e('Try: comfortable shoes size 42 red and white', 'geeky-bot'); ?>" />
                        <button class="gb2-btn gb2-btn--primary" type="submit" style="flex:none"><?php
                            esc_html_e('Test search', 'geeky-bot'); ?></button>
                    </form>

                    <?php if ($test_query !== '') : ?>
                        <div class="gb2-inline" style="margin-top:12px">
                            <?php Components::pill(sprintf(
                                /* translators: %s: product search result mode. */
                                __('Result mode: %s', 'geeky-bot'),
                                $search_note ? $search_note : __('catalog search', 'geeky-bot')
                            ), 'neutral', false); ?>
                            <?php Components::pill(sprintf(
                                /* translators: %s: number of matching products. */
                                _n('%s match', '%s matches', count($search_results), 'geeky-bot'),
                                number_format_i18n(count($search_results))
                            ), count($search_results) > 0 ? 'ok' : 'warn'); ?>
                        </div>

                        <div style="margin-top:12px"><?php $this->search_intent_debug($search_analysis); ?></div>

                        <?php if (empty($search_results)) : ?>
                            <?php Components::empty_state(
                                __('No matching products found', 'geeky-bot'),
                                __('Add product attributes, improve titles and tags, add a synonym below, or switch product matches back to Smart.', 'geeky-bot')
                            ); ?>
                        <?php else : ?>
                            <div class="gb2-scroll" style="margin-top:12px">
                                <table class="gb2-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Product', 'geeky-bot'); ?></th>
                                            <th><?php esc_html_e('Price', 'geeky-bot'); ?></th>
                                            <th><?php esc_html_e('Stock', 'geeky-bot'); ?></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach (array_slice($search_results, 0, 4) as $product) : ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($product['name']); ?></strong>
                                                <div style="margin-top:3px"><?php
                                                    $this->admin_product_match_debug($product, $search_analysis, $search_debug); ?></div>
                                            </td>
                                            <td><?php echo esc_html(isset($product['priceText']) && $product['priceText'] !== '' ? $product['priceText'] : wp_strip_all_tags($product['priceHtml'])); ?></td>
                                            <td><?php echo esc_html($product['stockLabel']); ?></td>
                                            <td style="text-align:right;white-space:nowrap">
                                                <a class="gb2-link" style="margin:0" href="<?php echo esc_url($product['url']); ?>" target="_blank" rel="noopener noreferrer"><?php
                                                    esc_html_e('View', 'geeky-bot'); ?></a>
                                                &nbsp;
                                                <a class="gb2-link" style="margin:0" href="<?php echo esc_url(get_edit_post_link(absint($product['id']))); ?>"><?php
                                                    esc_html_e('Edit', 'geeky-bot'); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($search_results) > 4) : ?>
                                <p class="gb2-note"><?php echo esc_html(sprintf(
                                    /* translators: %d: number of additional product matches. */
                                    __('%d more close matches are available. Narrow the phrase, or check the storefront widget for the shopper view.', 'geeky-bot'),
                                    count($search_results) - 4
                                )); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="gb2-grid" style="margin-top:14px">
                            <div class="gb2-col-4">
                                <p class="gb2-note" style="margin:0"><strong style="color:var(--gb2-ink)"><?php
                                    esc_html_e('1. Read the phrase', 'geeky-bot'); ?></strong><br><?php
                                    esc_html_e('Product terms, colour, size, price phrases and soft preferences.', 'geeky-bot'); ?></p>
                            </div>
                            <div class="gb2-col-4">
                                <p class="gb2-note" style="margin:0"><strong style="color:var(--gb2-ink)"><?php
                                    esc_html_e('2. Score the catalog', 'geeky-bot'); ?></strong><br><?php
                                    esc_html_e('Names, categories, tags, attributes, stock and sale status.', 'geeky-bot'); ?></p>
                            </div>
                            <div class="gb2-col-4">
                                <p class="gb2-note" style="margin:0"><strong style="color:var(--gb2-ink)"><?php
                                    esc_html_e('3. Close the gap', 'geeky-bot'); ?></strong><br><?php
                                    esc_html_e('Add synonyms when your wording differs from your shoppers.', 'geeky-bot'); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php Components::card_close(); ?>

                <?php Components::rule(__('Tune the index', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-5">
                        <?php Components::card_open(__('Product index', 'geeky-bot'), '', false); ?>
                            <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Rebuild after imports, category changes, product updates, attribute edits, or stock and sale changes.', 'geeky-bot'); ?></p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_rebuild_product_index'); ?>
                                <input type="hidden" name="action" value="geekybot_rebuild_product_index" />
                                <button class="gb2-btn gb2-btn--primary" type="submit"><?php
                                    esc_html_e('Rebuild search index', 'geeky-bot'); ?></button>
                            </form>

                            <div class="gb2-keyvalues" style="margin-top:14px">
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Product data', 'geeky-bot'); ?></span>
                                    <strong style="font-size:12px;font-weight:500;color:var(--gb2-mute)"><?php
                                        esc_html_e('Name, SKU, category, tags, attributes', 'geeky-bot'); ?></strong></div>
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Buyer intent', 'geeky-bot'); ?></span>
                                    <strong style="font-size:12px;font-weight:500;color:var(--gb2-mute)"><?php
                                        esc_html_e('Price, colour, size, recommendations', 'geeky-bot'); ?></strong></div>
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Commerce signals', 'geeky-bot'); ?></span>
                                    <strong style="font-size:12px;font-weight:500;color:var(--gb2-mute)"><?php
                                        esc_html_e('Stock, sale, ratings, popularity', 'geeky-bot'); ?></strong></div>
                            </div>

                            <div class="gb2-chips" style="margin-top:12px">
                                <span><?php esc_html_e('Product name', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('SKU', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Categories', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Tags', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Attributes', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Price phrases', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Sale status', 'geeky-bot'); ?></span>
                                <span><?php esc_html_e('Stock', 'geeky-bot'); ?></span>
                            </div>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-7">
                        <?php $this->product_search_controls($settings); ?>
                    </div>
                </div>

                <?php Components::rule(__('Rehearsal board', 'geeky-bot')); ?>

                <?php Components::card_open(__('Phrases worth re-testing', 'geeky-bot'), '', false); ?>
                    <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                        esc_html_e('Click a phrase to load it into the test lab and confirm buyer language still maps to the right products.', 'geeky-bot'); ?></p>
                    <div class="gb2-inline">
                        <?php
                        $rehearsal = array(
                            'budget hoodie' => __('Budget intent', 'geeky-bot'),
                            'hoodie between 30 and 60' => __('Price range', 'geeky-bot'),
                            'blue hoodie' => __('Colour attribute', 'geeky-bot'),
                            'shoes size 42' => __('Size attribute', 'geeky-bot'),
                            'comfortable shoes size 42 red and white' => __('Natural buyer query', 'geeky-bot'),
                            'not too expensive walking shoes in black size 9' => __('Soft preference', 'geeky-bot'),
                            'hoodies on sale' => __('Sale filter', 'geeky-bot'),
                            'which hoodie do you recommend' => __('Recommendation', 'geeky-bot'),
                        );
                        foreach ($rehearsal as $phrase => $label) :
                            $url = add_query_arg(array('page' => 'geekybot-product-assistant', 'gb_test_query' => rawurlencode($phrase)), admin_url('admin.php'));
                            ?>
                            <a class="gb2-btn" href="<?php echo esc_url($url); ?>" title="<?php echo esc_attr($label); ?>">
                                <code style="font-family:var(--gb2-mono);font-size:11px"><?php echo esc_html($phrase); ?></code>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php Components::card_close(); ?>

                <div style="margin-top:14px"><?php $this->nlp_action_examples(); ?></div>
            </div>
        </div>
        <?php
    }

    public function store_knowledge() {
        $ctx = $this->context();
        $settings = $ctx['settings'];
        $pages = get_pages(array('post_status' => 'publish', 'sort_column' => 'post_title'));
        $selected = array_map('absint', (array) $settings['policy_page_ids']);
        $suggested = $this->suggested_policy_pages($pages);
        $policy_classifications = $this->policy_page_classifications($pages);
        $unclassified_selected = array();
        foreach ($selected as $selected_page_id) {
            if (!empty($policy_classifications[$selected_page_id])
                && in_array('general', $policy_classifications[$selected_page_id], true)) {
                $selected_post = get_post($selected_page_id);
                if ($selected_post) {
                    $unclassified_selected[] = $selected_post->post_title;
                }
            }
        }
        $knowledge = new KnowledgeService();
        $index_status = $knowledge->index_status();
        $needs_refresh = absint($index_status['missing']) + absint($index_status['stale']);
        $last_indexed = '';
        if (!empty($index_status['lastIndexedGmt'])) {
            $last_indexed = get_date_from_gmt($index_status['lastIndexedGmt'], get_option('date_format') . ' ' . get_option('time_format'));
        }
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-store-knowledge">
            <?php $this->page_hero(__('Store Knowledge', 'geeky-bot'), __('Choose the public policy pages Geeky Bot may use for shipping, returns, refunds, payment and warranty answers. Selected pages are indexed locally and missing details produce a safe fallback instead of guesses.', 'geeky-bot'), __('Grounded policy answers', 'geeky-bot'), admin_url('post-new.php?post_type=page'), __('Create policy page', 'geeky-bot')); ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php if (!empty($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Store knowledge saved and synchronized.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only refresh-result notice values. ?>
            <?php if (!empty($_GET['gb_knowledge_refreshed'])) : ?>
                <?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only refresh-result notice values. ?>
                <div class="notice <?php echo !empty($_GET['gb_knowledge_failed']) ? 'notice-warning' : 'notice-success'; ?> is-dismissible"><p>
                    <?php
                    printf(
                        /* translators: 1: indexed count, 2: unchanged count, 3: removed count, 4: failed count. */
                        esc_html__('Knowledge refresh complete: %1$d indexed, %2$d unchanged, %3$d removed, %4$d failed.', 'geeky-bot'),
                        isset($_GET['gb_knowledge_indexed']) ? absint($_GET['gb_knowledge_indexed']) : 0,
                        isset($_GET['gb_knowledge_unchanged']) ? absint($_GET['gb_knowledge_unchanged']) : 0,
                        isset($_GET['gb_knowledge_removed']) ? absint($_GET['gb_knowledge_removed']) : 0,
                        isset($_GET['gb_knowledge_failed']) ? absint($_GET['gb_knowledge_failed']) : 0
                    );
                    ?>
                </p></div>
                <?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>
            <?php endif; ?>
            <?php if (!empty($unclassified_selected)) : ?>
                <div class="notice notice-warning"><p>
                    <?php
                    printf(
                        /* translators: %s: comma-separated selected page titles. */
                        esc_html__('These selected pages are not recognized as policy sources and will not answer specific policy questions: %s', 'geeky-bot'),
                        esc_html(implode(', ', $unclassified_selected))
                    );
                    ?>
                </p></div>
            <?php endif; ?>

            <div class="gb2-main">

                <?php Components::metrics(array(
                    array(
                        'label' => __('Selected sources', 'geeky-bot'),
                        'value' => number_format_i18n(count($selected)),
                        'base' => __('Only these pages can ground policy answers', 'geeky-bot'),
                    ),
                    array(
                        'label' => __('Recognized sources', 'geeky-bot'),
                        'value' => number_format_i18n(absint($index_status['usable'])),
                        'base' => $last_indexed
                            ? sprintf(
                                /* translators: %s: date and time of the last knowledge-index refresh. */
                                __('Last refreshed %s', 'geeky-bot'),
                                $last_indexed
                            )
                            : __('Save or refresh to build the index', 'geeky-bot'),
                    ),
                    array(
                        'label' => $needs_refresh === 0 ? __('Knowledge is current', 'geeky-bot') : __('Sources need attention', 'geeky-bot'),
                        'value' => $needs_refresh === 0 ? __('Ready', 'geeky-bot') : number_format_i18n($needs_refresh),
                        'base' => __('Changed, missing or draft pages are never trusted silently', 'geeky-bot'),
                    ),
                )); ?>

                <?php Components::rule(__('Answer sources', 'geeky-bot')); ?>

                <form method="post">
                    <?php wp_nonce_field('geekybot_save_settings'); ?>
                    <input type="hidden" name="geekybot_settings_action" value="save" />
                    <input type="hidden" name="geekybot_settings_scope" value="partial" />
                    <input type="hidden" name="geekybot_redirect" value="<?php echo esc_url(admin_url('admin.php?page=geekybot-store-knowledge')); ?>" />
                    <?php // Zero entry so unchecking every box still submits an empty selection. ?>
                    <input type="hidden" name="policy_page_ids[]" value="0" />

                    <div class="gb2-grid">
                        <div class="gb2-col-7">
                            <?php Components::card_open(__('Choose public answer sources', 'geeky-bot'), '', false); ?>
                                <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                    esc_html_e('Pick a small set of accurate, shopper-facing pages. Geeky Bot reads the stored page text only — it never crawls arbitrary URLs or runs shortcodes.', 'geeky-bot'); ?></p>

                                <?php if (empty($pages)) : ?>
                                    <?php Components::empty_state(
                                        __('No published pages found', 'geeky-bot'),
                                        __('Create a shipping, refund or returns page, then select it here so the assistant can answer policy questions.', 'geeky-bot'),
                                        array('label' => __('Create a page', 'geeky-bot'), 'url' => admin_url('post-new.php?post_type=page'))
                                    ); ?>
                                <?php else : ?>
                                    <div class="gb2-picker">
                                        <?php foreach ($pages as $page) :
                                            $is_suggested = in_array($page, $suggested, true);
                                            $page_types = !empty($policy_classifications[$page->ID]) ? $policy_classifications[$page->ID] : array('general');
                                            $type_summary = $this->policy_type_summary($page_types);
                                            $source_label = $is_suggested ? __('Suggested policy page', 'geeky-bot') : __('Public page', 'geeky-bot');
                                            ?>
                                            <label class="gb2-picker__option<?php echo $is_suggested ? ' gb2-picker__option--suggested' : ''; ?>">
                                                <input type="checkbox" name="policy_page_ids[]" value="<?php echo esc_attr($page->ID); ?>" <?php checked(in_array(absint($page->ID), $selected, true)); ?> />
                                                <span class="gb2-picker__text">
                                                    <strong><?php echo esc_html($page->post_title); ?></strong>
                                                    <em><?php echo esc_html($source_label . ' · ' . $type_summary); ?></em>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="gb2-inline" style="margin-top:14px">
                                    <button class="gb2-btn gb2-btn--primary" type="submit"><?php
                                        esc_html_e('Save and index pages', 'geeky-bot'); ?></button>
                                    <a class="gb2-btn" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant&gb_test_query=refund+policy')); ?>"><?php
                                        esc_html_e('Test a refund question', 'geeky-bot'); ?></a>
                                </div>
                            <?php Components::card_close(); ?>
                        </div>

                        <div class="gb2-col-5">
                            <?php Components::card_open(
                                __('Approved library', 'geeky-bot'),
                                sprintf(
                                    /* translators: %s: number of selected policy pages. */
                                    _n('%s page', '%s pages', count($selected), 'geeky-bot'),
                                    number_format_i18n(count($selected))
                                ),
                                false
                            ); ?>
                                <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                    printf(
                                        /* translators: %d: number of suggested policy pages. */
                                        esc_html__('%d suggested policy pages were detected. Only checked pages become approved sources.', 'geeky-bot'),
                                        count($suggested)
                                    ); ?></p>

                                <?php $this->page_list($pages, $selected, true, $policy_classifications); ?>

                                <p class="gb2-note"><?php esc_html_e('Shipping, return, refund, exchange, warranty, payment and cancellation questions are checked here before product search or any external AI.', 'geeky-bot'); ?></p>
                                <ul class="gb2-checklist" style="margin-top:10px">
                                    <li><?php esc_html_e('A source must actually answer the question, or the assistant says it is not confirmed', 'geeky-bot'); ?></li>
                                    <li><?php esc_html_e('Grounded answers show the page title and a link', 'geeky-bot'); ?></li>
                                </ul>
                            <?php Components::card_close(); ?>
                        </div>
                    </div>
                </form>

                <?php Components::rule(__('Index maintenance', 'geeky-bot')); ?>

                <?php Components::card_open(__('Refresh the knowledge index', 'geeky-bot'), '', false); ?>
                    <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                        esc_html_e('Selected pages refresh automatically when you save. Use this after imports, page-builder migrations, or when a source shows as stale or missing above.', 'geeky-bot'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('geekybot_refresh_knowledge_index'); ?>
                        <input type="hidden" name="action" value="geekybot_refresh_knowledge_index" />
                        <button class="gb2-btn" type="submit"><?php esc_html_e('Refresh selected sources', 'geeky-bot'); ?></button>
                    </form>
                <?php Components::card_close(); ?>
            </div>
        </div>
        <?php
    }

    public function analytics() {
        $window = 30;
        $insights = new ConversationInsightsService();
        $events = new AnalyticsEventService();
        $summary = $insights->summary($window);
        $previous = $insights->summary($window, $window);
        $top_products = $events->top_clicked_products($window, 8);
        $reasons = $insights->reason_breakdown($window);

        // Same split as the dashboard: one query covering both windows.
        $activity = $insights->daily_activity($window * 2);
        $current_days = array_slice($activity, -$window);
        $previous_days = count($activity) > $window ? array_slice($activity, 0, count($activity) - $window) : array();

        $chart_series = array();
        foreach ($current_days as $day) {
            $chart_series[] = array('date' => $day['date'], 'value' => absint($day['shopper_messages']));
        }
        $chart_compare = array();
        foreach ($previous_days as $day) {
            $chart_compare[] = array('date' => $day['date'], 'value' => absint($day['shopper_messages']));
        }
        if (count($chart_compare) !== count($chart_series)) {
            $chart_compare = array();
        }
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-analytics-page geekybot-insights-page">
            <?php $this->page_hero(__('Analytics', 'geeky-bot'), __('See how shoppers use Geeky Bot, which products they open, and where catalog or policy data still needs attention. Free analytics stays focused on assistant quality rather than conversion attribution.', 'geeky-bot'), __('Last 30 days', 'geeky-bot'), admin_url('admin.php?page=geekybot-conversations'), __('Review conversations', 'geeky-bot')); ?>

            <div class="gb2-main">

                <?php Components::metrics(array(
                    array_merge(
                        array(
                            'label' => __('Conversations', 'geeky-bot'),
                            'value' => number_format_i18n($summary['sessions']),
                            /* translators: %s: count from the previous period. */
                            'base' => sprintf(__('vs %s previous 30 days', 'geeky-bot'), number_format_i18n($previous['sessions'])),
                        ),
                        $this->metric_delta($summary['sessions'], $previous['sessions'])
                    ),
                    array_merge(
                        array(
                            'label' => __('Shopper messages', 'geeky-bot'),
                            'value' => number_format_i18n($summary['shopper_messages']),
                            /* translators: %s: average number of messages per conversation. */
                            'base' => sprintf(__('%s messages per conversation', 'geeky-bot'), number_format_i18n($summary['average_messages'], 1)),
                        ),
                        $this->metric_delta($summary['shopper_messages'], $previous['shopper_messages'])
                    ),
                    array_merge(
                        array(
                            'label' => __('Product clicks', 'geeky-bot'),
                            'value' => number_format_i18n($summary['product_clicks']),
                            'base' => __('Opened from product cards in chat', 'geeky-bot'),
                        ),
                        $this->metric_delta($summary['product_clicks'], $previous['product_clicks'])
                    ),
                    array_merge(
                        array(
                            'label' => __('Needs attention', 'geeky-bot'),
                            'value' => number_format_i18n($summary['unanswered']),
                            'base' => __('Grounded-answer gaps logged', 'geeky-bot'),
                        ),
                        $this->metric_delta($summary['unanswered'], $previous['unanswered'], false)
                    ),
                )); ?>

                <?php Components::rule(__('Conversation activity', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-8">
                        <?php Components::card_open(__('Daily shopper messages', 'geeky-bot'), __('Last 30 days', 'geeky-bot')); ?>
                            <?php
                            Components::chart_legend(
                                /* translators: %s: shopper message count this period. */
                                sprintf(__('This period · %s', 'geeky-bot'), number_format_i18n($summary['shopper_messages'])),
                                $chart_compare ? sprintf(
                                    /* translators: %s: shopper message count in the previous period. */
                                    __('Previous 30 days · %s', 'geeky-bot'),
                                    number_format_i18n($previous['shopper_messages'])
                                ) : '',
                                __('Aggregate only — individual shoppers are not identified.', 'geeky-bot')
                            );
                            Components::chart($chart_series, $chart_compare, __('Daily shopper messages over the last 30 days', 'geeky-bot'));
                            ?>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Why answers were unavailable', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <?php if (empty($reasons)) : ?>
                                <?php Components::empty_state(
                                    __('No grounded-answer gaps', 'geeky-bot'),
                                    __('Keep monitoring after catalog and policy updates.', 'geeky-bot')
                                ); ?>
                            <?php else :
                                $reason_bars = array();
                                $tones = array('crit', 'warn', 'accent', 'mute');
                                foreach (array_slice($reasons, 0, 5) as $index => $reason) {
                                    $reason_meta = ConversationInsightsService::reason_meta(isset($reason['reason']) ? $reason['reason'] : '');
                                    $reason_bars[] = array(
                                        'label' => $reason_meta['label'],
                                        'value' => absint($reason['total']),
                                        'tone' => isset($tones[$index]) ? $tones[$index] : 'mute',
                                    );
                                }
                                Components::bars($reason_bars);
                                $top_reason_meta = ConversationInsightsService::reason_meta(isset($reasons[0]['reason']) ? $reasons[0]['reason'] : '');
                                ?>
                                <?php if (!empty($top_reason_meta['action'])) : ?>
                                    <p class="gb2-note"><?php echo esc_html($top_reason_meta['action']); ?></p>
                                <?php endif; ?>
                                <div style="margin-top:12px">
                                    <?php Components::action_link(array(
                                        'label' => __('Open unanswered questions', 'geeky-bot'),
                                        'url' => admin_url('admin.php?page=geekybot-conversations&gb_view=review&gb_review_status=needs-review'),
                                        'variant' => 'primary',
                                    )); ?>
                                </div>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php Components::rule(__('Product interest', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-8">
                        <?php Components::card_open(__('Most-opened products', 'geeky-bot'), __('Last 30 days', 'geeky-bot'), true); ?>
                            <?php if (empty($top_products)) : ?>
                                <?php Components::empty_state(
                                    __('No product clicks yet', 'geeky-bot'),
                                    __('Product interest appears after shoppers open products from chat.', 'geeky-bot')
                                ); ?>
                            <?php else : ?>
                                <div class="gb2-scroll">
                                    <table class="gb2-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Product', 'geeky-bot'); ?></th>
                                                <th style="text-align:right"><?php esc_html_e('Clicks', 'geeky-bot'); ?></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($top_products as $product) :
                                            $product_id = absint($product['object_id']);
                                            $label = $product['object_label'] !== ''
                                                ? $product['object_label']
                                                : sprintf(
                                                    /* translators: %d: WooCommerce product ID. */
                                                    __('Product #%d', 'geeky-bot'),
                                                    $product_id
                                                );
                                            $product_url = $product_id ? get_edit_post_link($product_id, '') : '';
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html($label); ?></td>
                                                <td class="gb2-table__num"><?php echo esc_html(number_format_i18n($product['clicks'])); ?></td>
                                                <td style="text-align:right">
                                                    <?php if ($product_url) : ?>
                                                        <a class="gb2-link" style="margin:0" href="<?php echo esc_url($product_url); ?>"><?php
                                                            esc_html_e('Edit', 'geeky-bot'); ?></a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="gb2-note" style="margin:12px 16px 14px"><?php
                                    esc_html_e('Clicks are an interest signal, not conversion attribution. Cart and checkout tracking are Commerce Pro features.', 'geeky-bot'); ?></p>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Insight without profiling', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <p style="margin:0 0 12px;font-size:12px;color:var(--gb2-mute)"><?php
                                esc_html_e('This page uses conversation IDs and aggregate events. It never shows IP hashes, browser fingerprints, or API secrets.', 'geeky-bot'); ?></p>
                            <ul class="gb2-checklist">
                                <li><?php echo esc_html(sprintf(
                                    /* translators: %s: conversation retention period in days. */
                                    __('Conversations kept for %s days', 'geeky-bot'),
                                    number_format_i18n(absint(Settings::get('retention_days', 30)))
                                )); ?></li>
                                <li><?php esc_html_e('WordPress personal-data export and erasure are supported.', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Conversation records can be exported or deleted.', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Product clicks are interest signals, not attribution.', 'geeky-bot'); ?></li>
                            </ul>
                            <div class="gb2-inline" style="margin-top:12px">
                                <?php
                                Components::action_link(array(
                                    'label' => __('Privacy settings', 'geeky-bot'),
                                    'url' => admin_url('admin.php?page=geekybot-settings#gb-settings-privacy'),
                                ));
                                Components::action_link(array(
                                    'label' => __('Manage data', 'geeky-bot'),
                                    'url' => admin_url('admin.php?page=geekybot-conversations'),
                                ));
                                ?>
                            </div>
                        <?php Components::card_close(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }


    public function integrations() {
        $ctx = $this->context();
        $settings = $ctx['settings'];
        $mode = isset($settings['provider_mode']) ? (string) $settings['provider_mode'] : 'local';
        $zywrap_key_saved = Settings::has_secret('zywrap_api_key');
        $openai_key_saved = Settings::has_secret('openai_api_key');
        $zywrap_endpoint_saved = !empty($settings['zywrap_endpoint']);
        $local_active = $mode === 'local';
        $zywrap_ready = $mode === 'zywrap' && $zywrap_key_saved && $zywrap_endpoint_saved;
        $openai_ready = $mode === 'openai' && $openai_key_saved;
        $configured_count = 1 + ($zywrap_key_saved && $zywrap_endpoint_saved ? 1 : 0) + ($openai_key_saved ? 1 : 0);
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-integrations">
            <?php $this->page_hero(__('Answer Mode', 'geeky-bot'), __('Choose local grounded answers, Zywrap, or BYOK while keeping API keys server-side and answers grounded in store data.', 'geeky-bot'), __('Answer mode', 'geeky-bot'), admin_url('admin.php?page=geekybot-settings#gb-settings-ai'), __('Configure answer mode', 'geeky-bot')); ?>

            <div class="gb2-main">

                <?php Components::metrics(array(
                    array(
                        'label' => __('Current answer mode', 'geeky-bot'),
                        'value' => $this->provider_label($settings),
                        'base' => __('Used to generate grounded answers', 'geeky-bot'),
                    ),
                    array(
                        'label' => __('API keys', 'geeky-bot'),
                        'value' => __('Server-side', 'geeky-bot'),
                        'base' => __('Never shown again after saving', 'geeky-bot'),
                    ),
                    array(
                        'label' => __('Modes available', 'geeky-bot'),
                        'value' => number_format_i18n($configured_count) . '/3',
                        'base' => __('Local mode is always available', 'geeky-bot'),
                    ),
                )); ?>

                <?php Components::rule(__('Answer modes', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <?php
                    $providers = array(
                        array(
                            'title' => __('Local grounded mode', 'geeky-bot'),
                            'description' => __('The fastest and safest default. Answers use WooCommerce product data and your selected policy pages, without sending catalog context to an external provider.', 'geeky-bot'),
                            'active' => $local_active,
                            'state_label' => $local_active ? __('Active', 'geeky-bot') : __('Available', 'geeky-bot'),
                            'state' => $local_active ? 'ok' : 'neutral',
                            'checks' => array(
                                array('label' => __('No API key required', 'geeky-bot'), 'ok' => true),
                                array('label' => __('Grounded in your catalog', 'geeky-bot'), 'ok' => true),
                                array('label' => __('Safe policy fallback', 'geeky-bot'), 'ok' => true),
                            ),
                            'action' => $local_active ? __('Review active mode', 'geeky-bot') : __('Choose local mode', 'geeky-bot'),
                        ),
                        array(
                            'title' => __('Zywrap endpoint', 'geeky-bot'),
                            'description' => __('A hosted endpoint for store-grounded answers, when you want stronger response generation without exposing secrets to the storefront.', 'geeky-bot'),
                            'active' => $zywrap_ready,
                            'state_label' => $zywrap_ready ? __('Configured', 'geeky-bot') : __('Available', 'geeky-bot'),
                            'state' => $zywrap_ready ? 'ok' : 'neutral',
                            'checks' => array(
                                array('label' => $zywrap_endpoint_saved ? __('Endpoint saved', 'geeky-bot') : __('Endpoint missing', 'geeky-bot'), 'ok' => $zywrap_endpoint_saved),
                                array('label' => $zywrap_key_saved ? __('API key stored', 'geeky-bot') : __('API key missing', 'geeky-bot'), 'ok' => $zywrap_key_saved),
                            ),
                            'action' => $zywrap_ready ? __('Review Zywrap setup', 'geeky-bot') : __('Configure Zywrap', 'geeky-bot'),
                        ),
                        array(
                            'title' => __('OpenAI (your own key)', 'geeky-bot'),
                            'description' => __('Optional bring-your-own-key mode. Geeky Bot sends only the shopper question and the grounded store context, from the server.', 'geeky-bot'),
                            'active' => $openai_ready,
                            'state_label' => $openai_ready ? __('Configured', 'geeky-bot') : __('Available', 'geeky-bot'),
                            'state' => $openai_ready ? 'ok' : 'neutral',
                            'checks' => array(
                                array('label' => $openai_key_saved ? __('API key stored', 'geeky-bot') : __('API key missing', 'geeky-bot'), 'ok' => $openai_key_saved),
                                array('label' => __('Model is controlled', 'geeky-bot'), 'ok' => true),
                                array('label' => __('Token limit enforced', 'geeky-bot'), 'ok' => true),
                            ),
                            'action' => $openai_ready ? __('Review OpenAI setup', 'geeky-bot') : __('Configure your key', 'geeky-bot'),
                        ),
                    );

                    foreach ($providers as $provider) : ?>
                        <div class="gb2-col-4">
                            <?php Components::card_open('', '', false, 'gb2-fill'); ?>
                                <div class="gb2-inline" style="margin-bottom:10px">
                                    <?php Components::pill($provider['state_label'], $provider['state']); ?>
                                </div>
                                <h3 style="margin:0 0 5px;font-size:14px;font-weight:600"><?php
                                    echo esc_html($provider['title']); ?></h3>
                                <p style="margin:0 0 12px;font-size:12px;line-height:1.55;color:var(--gb2-mute)"><?php
                                    echo esc_html($provider['description']); ?></p>

                                <ul class="gb2-checklist" style="margin-bottom:12px">
                                    <?php foreach ($provider['checks'] as $check) : ?>
                                        <li<?php echo $check['ok'] ? '' : ' class="gb2-checklist__missing"'; ?>><?php
                                            echo esc_html($check['label']); ?></li>
                                    <?php endforeach; ?>
                                </ul>

                                <?php Components::action_link(array(
                                    'label' => $provider['action'],
                                    'url' => admin_url('admin.php?page=geekybot-settings#gb-settings-ai'),
                                    'variant' => $provider['active'] ? 'primary' : 'default',
                                )); ?>
                            <?php Components::card_close(); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php Components::rule(__('Before you change it', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-6">
                        <?php Components::card_open(__('Confirm the mode before launch', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Keep local mode for the safest baseline, or configure Zywrap or your own OpenAI key from Settings when you want AI-generated grounded answers.', 'geeky-bot'); ?></p>
                            <p class="gb2-note"><?php
                                esc_html_e('After changing answer mode, run one product search and one policy question on the storefront to confirm the assistant is still grounded.', 'geeky-bot'); ?></p>
                            <div style="margin-top:12px">
                                <?php Components::action_link(array(
                                    'label' => __('Test on storefront', 'geeky-bot'),
                                    'url' => home_url('/'),
                                    'variant' => 'primary',
                                    'external' => true,
                                )); ?>
                            </div>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-6">
                        <?php Components::card_open(__('Secrets stay behind WordPress', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('The storefront receives public widget settings and a REST nonce only. Provider secrets are saved server-side and never printed into JavaScript.', 'geeky-bot'); ?></p>
                            <ul class="gb2-checklist">
                                <li><?php esc_html_e('Saved API keys are not displayed after saving', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Admin changes require capability and nonce checks', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('Public assistant requests are rate limited', 'geeky-bot'); ?></li>
                                <li><?php esc_html_e('No invented products, prices, coupons or policies', 'geeky-bot'); ?></li>
                            </ul>
                        <?php Components::card_close(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function settings() {
        $settings = Settings::all();
        $pages = get_pages(array('post_status' => 'publish', 'sort_column' => 'post_title'));
        $selected_pages = array_map('absint', (array) $settings['policy_page_ids']);
        $widget_enabled = isset($settings['widget_enabled']) && $settings['widget_enabled'] === 'yes';
        $natural_enabled = isset($settings['natural_search_enabled']) && $settings['natural_search_enabled'] === 'yes';
        $history_enabled = isset($settings['chat_history_enabled']) && $settings['chat_history_enabled'] === 'yes';
        $provider_label = $this->provider_label($settings);
        $selected_policy_count = count($selected_pages);
        $boost_count = 0;
        foreach (array('search_boost_in_stock', 'search_boost_sale', 'search_boost_rating', 'search_boost_popularity') as $boost_key) {
            $boost_count += (isset($settings[$boost_key]) && $settings[$boost_key] === 'yes') ? 1 : 0;
        }
        $configuration_score = 25 + ($widget_enabled ? 25 : 0) + ($natural_enabled ? 25 : 0) + ($history_enabled ? 25 : 0);
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-settings-modern geekybot-admin-settings-cockpit geekybot-settings-premium-v2">
            <?php
            // The 2.0.1 readiness aside listed widget / natural search /
            // provider / history — the same four facts as the signal row beside
            // it. Collapsed into one fact row here; nothing is lost.
            Components::page_header(array(
                'title' => __('Settings', 'geeky-bot'),
                'brand' => array($this, 'brand_mark_svg'),
                'description' => __('Widget behavior, buyer-language search, grounded answers, provider security, privacy and rate limits.', 'geeky-bot'),
                'status' => array(
                    /* translators: %s: configuration completeness percentage. */
                    'label' => sprintf(__('%s%% configured', 'geeky-bot'), number_format_i18n($configuration_score)),
                    'state' => $configuration_score >= 100 ? 'ok' : 'warn',
                ),
                'signals' => array(
                    array(
                        'value' => $widget_enabled ? __('Live', 'geeky-bot') : __('Off', 'geeky-bot'),
                        'label' => __('storefront widget', 'geeky-bot'),
                    ),
                    array(
                        'value' => $natural_enabled ? __('Natural', 'geeky-bot') : __('Keyword', 'geeky-bot'),
                        'label' => __('buyer language', 'geeky-bot'),
                    ),
                    array(
                        'value' => $settings['provider_mode'] === 'local' ? __('Grounded', 'geeky-bot') : $provider_label,
                        'label' => __('answer mode', 'geeky-bot'),
                    ),
                    array(
                        'value' => $history_enabled ? __('On', 'geeky-bot') : __('Off', 'geeky-bot'),
                        'label' => __('conversation review', 'geeky-bot'),
                    ),
                ),
                'actions' => array(
                    array(
                        'label' => __('Test on storefront', 'geeky-bot'),
                        'url' => home_url('/'),
                        'variant' => 'primary',
                        'external' => true,
                    ),
                    array(
                        'label' => __('Product Search', 'geeky-bot'),
                        'url' => admin_url('admin.php?page=geekybot-product-assistant'),
                    ),
                    array(
                        'label' => __('Dashboard', 'geeky-bot'),
                        'url' => admin_url('admin.php?page=geekybot'),
                    ),
                ),
            ));
            ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php if (!empty($_GET['updated'])) : ?><div class="gb2-savednotice" role="status"><strong><?php esc_html_e('Settings saved.', 'geeky-bot'); ?></strong><span><?php esc_html_e('Your storefront assistant will use the updated configuration.', 'geeky-bot'); ?></span></div><?php endif; ?>

            <form method="post" class="gb2-settings">
                <?php wp_nonce_field('geekybot_save_settings'); ?>
                <input type="hidden" name="geekybot_settings_action" value="save" />
                <nav class="gb2-rail" aria-label="<?php esc_attr_e('Settings sections', 'geeky-bot'); ?>">
                    <a href="#gb-settings-assistant"><span><?php esc_html_e('01', 'geeky-bot'); ?></span><?php esc_html_e('Storefront', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-search"><span><?php esc_html_e('02', 'geeky-bot'); ?></span><?php esc_html_e('Search', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-knowledge"><span><?php esc_html_e('03', 'geeky-bot'); ?></span><?php esc_html_e('Knowledge', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-ai"><span><?php esc_html_e('04', 'geeky-bot'); ?></span><?php esc_html_e('Answer mode', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-privacy"><span><?php esc_html_e('05', 'geeky-bot'); ?></span><?php esc_html_e('Privacy', 'geeky-bot'); ?></a>
                </nav>
                <div class="gb2-settings__main">
                    <section id="gb-settings-assistant" class="gb2-card gb2-scard">
                        <div class="gb2-scard__head"><div><p class="gb2-eyebrow"><?php esc_html_e('Storefront experience', 'geeky-bot'); ?></p><h2><?php esc_html_e('Assistant and widget', 'geeky-bot'); ?></h2><p><?php esc_html_e('Set the shopper-facing name, first message, safe fallback answer, accent color, and product-card volume.', 'geeky-bot'); ?></p></div><div class="gb2-scard__meta"><span><?php echo esc_html($widget_enabled ? __('Live', 'geeky-bot') : __('Off', 'geeky-bot')); ?></span><span><?php echo esc_html(absint($settings['max_products'])); ?> <?php esc_html_e('products', 'geeky-bot'); ?></span><span><?php echo esc_html(ucfirst($settings['button_position'])); ?></span></div></div>
                        <?php $this->settings_table_assistant($settings); ?>
                    </section>

                    <section id="gb-settings-search" class="gb2-card gb2-scard">
                        <div class="gb2-scard__head"><div><p class="gb2-eyebrow"><?php esc_html_e('Catalog intelligence', 'geeky-bot'); ?></p><h2><?php esc_html_e('Buyer-language search', 'geeky-bot'); ?></h2><p><?php esc_html_e('Tune buyer-language understanding, synonym expansion, product matches, and ranking boosts.', 'geeky-bot'); ?></p></div><div class="gb2-scard__meta"><span><?php echo esc_html($natural_enabled ? __('Natural search on', 'geeky-bot') : __('Natural search off', 'geeky-bot')); ?></span><span><?php echo esc_html($settings['search_close_match_mode'] === 'strict' ? __('Exact only', 'geeky-bot') : __('Smart matches', 'geeky-bot')); ?></span><span><?php echo esc_html($boost_count); ?> <?php esc_html_e('boosts', 'geeky-bot'); ?></span></div></div>
                        <?php $this->settings_table_search($settings, 'compact'); ?>
                    </section>

                    <section id="gb-settings-knowledge" class="gb2-card gb2-scard">
                        <div class="gb2-scard__head"><div><p class="gb2-eyebrow"><?php esc_html_e('Grounded answers', 'geeky-bot'); ?></p><h2><?php esc_html_e('Store knowledge', 'geeky-bot'); ?></h2><p><?php esc_html_e('Select safe public pages for shipping, refund, return, payment and warranty answers.', 'geeky-bot'); ?></p></div><div class="gb2-scard__meta"><span><?php echo esc_html(number_format_i18n($selected_policy_count)); ?> <?php esc_html_e('selected', 'geeky-bot'); ?></span><span><?php esc_html_e('Safe fallback answer', 'geeky-bot'); ?></span></div></div>
                        <div class="gb2-sgrid">
                            <?php if (empty($pages)) : ?>
                                <div class="gb2-snote"><strong><?php esc_html_e('No public pages found.', 'geeky-bot'); ?></strong><span><?php esc_html_e('Create shipping, returns, refund, payment, warranty or privacy pages before enabling policy answers.', 'geeky-bot'); ?></span></div>
                            <?php else : foreach ($pages as $page) :
                                $page_id = absint($page->ID);
                                $is_selected = in_array($page_id, $selected_pages, true);
                                $title = strtolower((string) $page->post_title);
                                $looks_policy = preg_match('/shipping|return|refund|privacy|terms|warranty|policy|delivery|payment/', $title);
                                ?>
                                <label class="gb2-pagecard <?php echo esc_attr($is_selected ? 'is-selected' : ''); ?> <?php echo esc_attr($looks_policy ? 'is-suggested' : ''); ?>">
                                    <input type="checkbox" name="policy_page_ids[]" value="<?php echo esc_attr($page_id); ?>" <?php checked($is_selected); ?> />
                                    <span><strong><?php echo esc_html($page->post_title); ?></strong><em><?php echo esc_html($looks_policy ? __('Likely policy page', 'geeky-bot') : __('Public page', 'geeky-bot')); ?></em></span>
                                </label>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="gb2-snote"><strong><?php esc_html_e('Safe policy answer rule', 'geeky-bot'); ?></strong><span><?php esc_html_e('If selected pages do not confirm the detail, Geeky Bot says it is not confirmed and directs the shopper to the source or store team.', 'geeky-bot'); ?></span></div>
                    </section>

                    <section id="gb-settings-ai" class="gb2-card gb2-scard">
                        <div class="gb2-scard__head"><div><p class="gb2-eyebrow"><?php esc_html_e('Answer mode and security', 'geeky-bot'); ?></p><h2><?php esc_html_e('Answer mode', 'geeky-bot'); ?></h2><p><?php esc_html_e('Choose local grounded mode, Zywrap, or BYOK while keeping secrets server-side.', 'geeky-bot'); ?></p></div><div class="gb2-scard__meta"><span><?php echo esc_html($provider_label); ?></span><span><?php esc_html_e('Keys hidden', 'geeky-bot'); ?></span></div></div>
                        <?php $this->settings_table_ai($settings); ?>
                    </section>

                    <section id="gb-settings-privacy" class="gb2-card gb2-scard">
                        <div class="gb2-scard__head"><div><p class="gb2-eyebrow"><?php esc_html_e('Privacy and abuse protection', 'geeky-bot'); ?></p><h2><?php esc_html_e('History, retention and rate limits', 'geeky-bot'); ?></h2><p><?php esc_html_e('Set conversation storage, retention, visitor rate limits, and uninstall cleanup behavior.', 'geeky-bot'); ?></p></div><div class="gb2-scard__meta"><span><?php echo esc_html(absint($settings['retention_days'])); ?> <?php esc_html_e('days', 'geeky-bot'); ?></span><span><?php echo esc_html(absint($settings['rate_limit_messages'])); ?> <?php esc_html_e('messages/window', 'geeky-bot'); ?></span></div></div>
                        <?php $this->settings_table_privacy($settings); ?>
                    </section>

                    <div class="gb2-savebar"><div><strong><?php esc_html_e('Save Geeky Bot settings', 'geeky-bot'); ?></strong><span><?php esc_html_e('Changes apply to the storefront assistant after saving.', 'geeky-bot'); ?></span></div><button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Save settings', 'geeky-bot'); ?></button></div>
                </div>
            </form>
        </div>
        <?php
    }

    public function conversations() {
        $insights = new ConversationInsightsService();
        $summary = $insights->summary(30);
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- These sanitized GET values only select read-only admin views and filters.
        $search = isset($_GET['gb_search']) ? sanitize_text_field(wp_unslash($_GET['gb_search'])) : '';
        $page_number = isset($_GET['gb_paged']) ? max(1, absint($_GET['gb_paged'])) : 1;
        $selected_session_id = isset($_GET['gb_session']) ? absint($_GET['gb_session']) : 0;
        $workspace = isset($_GET['gb_view']) ? sanitize_key(wp_unslash($_GET['gb_view'])) : '';
        if (!in_array($workspace, array('conversations', 'review'), true)) {
            $workspace = isset($_GET['gb_review_status']) ? 'review' : 'conversations';
        }
        if ($selected_session_id > 0) {
            $workspace = 'conversations';
        }

        $review_page_number = isset($_GET['gb_review_page']) ? max(1, absint($_GET['gb_review_page'])) : 1;
        $review_per_page = 15;
        $review_search = isset($_GET['gb_review_search']) ? sanitize_text_field(wp_unslash($_GET['gb_review_search'])) : '';
        $review_search_normalized = ConversationInsightsService::normalize_review_question($review_search);
        $session_page = $insights->recent_sessions($page_number, 15, $search);
        $session_detail = $selected_session_id ? $insights->session_detail($selected_session_id) : array();
        $questions = $insights->grouped_unanswered(5000);
        $filter = isset($_GET['gb_review_status']) ? sanitize_key(wp_unslash($_GET['gb_review_status'])) : 'needs-review';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (!in_array($filter, array('needs-review', 'handled', 'ignored', 'all'), true)) {
            $filter = 'needs-review';
        }

        $manual_handled = array_fill_keys(array_map('sanitize_key', (array) get_option('geekybot_review_manual_handled', array())), true);
        $ignored = array_fill_keys(array_map('sanitize_key', (array) get_option('geekybot_review_ignored', array())), true);
        $active_review_count = 0;
        $active_review_occurrences = 0;
        $handled_count = 0;
        $ignored_count = 0;
        $visible_questions = array();
        $review_buckets = array('product' => 0, 'policy' => 0, 'synonym' => 0, 'commerce' => 0);

        foreach ($questions as $question) {
            $hash = $this->review_hash($question->question);
            $legacy_hash = $this->legacy_review_hash($question->question, $question->reason);
            $is_ignored = isset($ignored[$hash]) || isset($ignored[$legacy_hash]);
            $is_manual_handled = isset($manual_handled[$hash]) || isset($manual_handled[$legacy_hash]);
            $is_auto_handled = $this->is_now_handled_question($question->question);
            $handled_now = !$is_ignored && ($is_manual_handled || $is_auto_handled);
            $reason_meta = ConversationInsightsService::reason_meta($question->reason);
            $bucket = $reason_meta['bucket'] === 'coverage' ? $this->review_bucket_for_question($question->question, $question->reason) : $reason_meta['bucket'];
            if (!isset($review_buckets[$bucket])) {
                $bucket = $this->review_bucket_for_question($question->question, $question->reason);
            }

            if ($is_ignored) {
                $ignored_count++;
            } elseif ($handled_now) {
                $handled_count++;
            } else {
                $active_review_count++;
                $active_review_occurrences += max(1, absint($question->occurrence_count ?? 1));
                if (isset($review_buckets[$bucket])) {
                    $review_buckets[$bucket]++;
                }
            }

            $matches_search = true;
            if ($review_search_normalized !== '') {
                $search_parts = array(
                    ConversationInsightsService::normalize_review_question($question->question),
                    ConversationInsightsService::normalize_review_question($question->reason),
                    ConversationInsightsService::normalize_review_question($reason_meta['label']),
                );
                foreach ((array) ($question->product_names ?? array()) as $product_name) {
                    $search_parts[] = ConversationInsightsService::normalize_review_question($product_name);
                }
                foreach ((array) ($question->policy_labels ?? array()) as $policy_label) {
                    $search_parts[] = ConversationInsightsService::normalize_review_question($policy_label);
                }
                $matches_search = strpos(implode(' ', $search_parts), $review_search_normalized) !== false;
            }

            $matches_filter = $filter === 'all'
                || ($filter === 'handled' && $handled_now)
                || ($filter === 'ignored' && $is_ignored)
                || ($filter === 'needs-review' && !$handled_now && !$is_ignored);
            if ($matches_filter && $matches_search) {
                $question->gb_hash = $hash;
                $question->gb_legacy_hash = $legacy_hash;
                $question->gb_handled_now = $handled_now;
                $question->gb_manual_handled = $is_manual_handled;
                $question->gb_auto_handled = $is_auto_handled;
                $question->gb_ignored = $is_ignored;
                $question->gb_bucket = $bucket;
                $question->gb_reason_meta = $reason_meta;
                $visible_questions[] = $question;
            }
        }

        $visible_question_total = count($visible_questions);
        $review_pages = max(1, (int) ceil($visible_question_total / $review_per_page));
        if ($review_page_number > $review_pages) {
            $review_page_number = $review_pages;
        }
        $visible_questions = array_slice($visible_questions, ($review_page_number - 1) * $review_per_page, $review_per_page);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice value.
        $notice = isset($_GET['gb_conversation_notice']) ? sanitize_key(wp_unslash($_GET['gb_conversation_notice'])) : '';
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-conversations geekybot-conversation-center geekybot-review-center">
            <?php $this->admin_notice_review_action(); ?>
            <?php if ($notice === 'deleted') : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Conversation deleted.', 'geeky-bot'); ?></p></div><?php endif; ?>
            <?php if ($notice === 'all-deleted') : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('All Geeky Bot conversation and interaction data was deleted.', 'geeky-bot'); ?></p></div><?php endif; ?>
            <?php if ($notice === 'delete-failed') : ?><div class="notice notice-error is-dismissible"><p><?php esc_html_e('Some conversation data could not be deleted. Check the database permissions and try again.', 'geeky-bot'); ?></p></div><?php endif; ?>

            <?php $this->page_hero(__('Conversations', 'geeky-bot'), __('Review stored shopper conversations, understand product interest, and turn unanswered questions into better catalog and policy data.', 'geeky-bot'), __('Privacy-safe review', 'geeky-bot'), admin_url('admin.php?page=geekybot-analytics'), __('Open analytics', 'geeky-bot')); ?>

            <div class="gb2-main">

            <?php Components::metrics(array(
                array(
                    'label' => __('Conversations', 'geeky-bot'),
                    'value' => number_format_i18n($summary['sessions']),
                    'base' => __('Last 30 days', 'geeky-bot'),
                ),
                array(
                    'label' => __('Shopper messages', 'geeky-bot'),
                    'value' => number_format_i18n($summary['shopper_messages']),
                    'base' => __('Questions and refinements', 'geeky-bot'),
                ),
                array(
                    'label' => __('Product clicks', 'geeky-bot'),
                    'value' => number_format_i18n($summary['product_clicks']),
                    'base' => __('From chat product cards', 'geeky-bot'),
                ),
                array(
                    'label' => __('Needs review', 'geeky-bot'),
                    'value' => number_format_i18n($active_review_count),
                    'base' => __('Unique grounded-answer gaps', 'geeky-bot'),
                ),
            )); ?>

            <nav class="gb2-tabs" aria-label="<?php esc_attr_e('Conversation workspace', 'geeky-bot'); ?>">
                <a class="<?php echo esc_attr($workspace === 'conversations' ? 'is-active' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations&gb_view=conversations')); ?>">
                    <?php esc_html_e('Conversation stream', 'geeky-bot'); ?>
                    <b><?php echo esc_html(number_format_i18n($summary['sessions'])); ?></b>
                </a>
                <a class="<?php echo esc_attr($workspace === 'review' ? 'is-active' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations&gb_view=review&gb_review_status=needs-review')); ?>">
                    <?php esc_html_e('Needs review', 'geeky-bot'); ?>
                    <b class="<?php echo esc_attr($active_review_count > 0 ? 'gb2-tabs__count--warn' : ''); ?>"><?php
                        echo esc_html(number_format_i18n($active_review_count)); ?></b>
                </a>
            </nav>

            <?php if ($workspace === 'conversations') : ?>
            <div class="gb2-toolbar">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="gb2-toolbar__search">
                    <input type="hidden" name="page" value="geekybot-conversations" />
                    <input type="hidden" name="gb_view" value="conversations" />
                    <label><span class="gb2-screen-reader-text"><?php esc_html_e('Search conversations', 'geeky-bot'); ?></span><input class="gb2-input" type="search" name="gb_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search shopper messages or conversation ID', 'geeky-bot'); ?>" /></label>
                    <button type="submit" class="gb2-btn"><?php esc_html_e('Search', 'geeky-bot'); ?></button>
                    <?php if ($search !== '') : ?><a class="gb2-btn gb2-btn--ghost" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations&gb_view=conversations')); ?>"><?php esc_html_e('Clear', 'geeky-bot'); ?></a><?php endif; ?>
                </form>
                <div class="gb2-toolbar__actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('geekybot_export_conversations'); ?>
                        <input type="hidden" name="action" value="geekybot_export_conversations" />
                        <button type="submit" class="gb2-btn"><?php esc_html_e('Export CSV', 'geeky-bot'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('geekybot_delete_all_conversations'); ?>
                        <input type="hidden" name="action" value="geekybot_delete_all_conversations" />
                        <?php // data-gb-confirm drives the JS confirmation prompt; keep the attribute. ?>
                        <button type="submit" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php echo esc_attr(__('Delete all Geeky Bot conversations, unanswered questions, and click events? This cannot be undone.', 'geeky-bot')); ?>"><?php esc_html_e('Delete all data', 'geeky-bot'); ?></button>
                    </form>
                </div>
            </div>

            <?php if (!empty($session_detail)) :
                $session = $session_detail['session'];
                $session_label = sprintf(
                    /* translators: %d: conversation ID. */
                    __('Conversation #%d', 'geeky-bot'),
                    absint($session['id'])
                );
                $session_activity_label = sprintf(
                    /* translators: 1: conversation start date/time, 2: last activity date/time. */
                    __('Started %1$s · Last activity %2$s', 'geeky-bot'),
                    $session['created_at'],
                    $session['updated_at']
                );
                ?>
                <?php Components::card_open(esc_html($session_label), esc_html($session_activity_label), false); ?>
                    <div class="gb2-toolbar" style="margin:0 0 14px;padding:0;border:0;background:none">
                        <div class="gb2-toolbar__actions" style="margin-left:0">
                            <a class="gb2-btn" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Back to list', 'geeky-bot'); ?></a>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_export_conversations'); ?>
                                <input type="hidden" name="action" value="geekybot_export_conversations" /><input type="hidden" name="session_id" value="<?php echo esc_attr(absint($session['id'])); ?>" />
                                <button type="submit" class="gb2-btn"><?php esc_html_e('Export this conversation', 'geeky-bot'); ?></button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php // Per-session nonce; the session id is part of the action name. ?>
                                <?php wp_nonce_field('geekybot_delete_conversation_' . absint($session['id'])); ?>
                                <input type="hidden" name="action" value="geekybot_delete_conversation" /><input type="hidden" name="session_id" value="<?php echo esc_attr(absint($session['id'])); ?>" />
                                <button type="submit" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php echo esc_attr(__('Delete this conversation and its analytics events?', 'geeky-bot')); ?>"><?php esc_html_e('Delete conversation', 'geeky-bot'); ?></button>
                            </form>
                        </div>
                    </div>

                    <div class="gb2-transcript">
                        <?php if (empty($session_detail['messages'])) : ?>
                            <?php Components::empty_state(__('No stored messages', 'geeky-bot'), __('This conversation has no saved messages.', 'geeky-bot')); ?>
                        <?php else : foreach ($session_detail['messages'] as $message) : ?>
                            <div class="gb2-msg gb2-msg--<?php echo esc_attr($message['direction']); ?>">
                                <div class="gb2-msg__meta">
                                    <strong><?php echo esc_html($message['direction'] === 'user' ? __('Shopper', 'geeky-bot') : __('Geeky Bot', 'geeky-bot')); ?></strong>
                                    <span><?php echo esc_html($message['created_at']); ?></span>
                                    <?php if ($message['intent'] !== '') : ?><code><?php echo esc_html($message['intent']); ?></code><?php endif; ?>
                                </div>
                                <p><?php echo esc_html($message['message']); ?></p>
                                <?php if (!empty($message['products'])) : ?>
                                    <div class="gb2-chips" style="margin-top:8px">
                                        <?php foreach ($message['products'] as $product_name) : ?>
                                            <span><?php echo esc_html($product_name); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <?php if (!empty($session_detail['events'])) : ?>
                        <h3 style="margin:18px 0 8px;font-size:12.5px;font-weight:600"><?php esc_html_e('Shopper interactions', 'geeky-bot'); ?></h3>
                        <ul class="gb2-feed" style="border:1px solid var(--gb2-line);border-radius:var(--gb2-r-sm)">
                            <?php foreach ($session_detail['events'] as $event) : ?>
                                <?php Components::feed_item(array(
                                    'text' => $event['object_label'],
                                    'meta' => $event['event_type'] === 'product_click' ? __('Product opened', 'geeky-bot') : __('Policy source opened', 'geeky-bot'),
                                    'when' => $event['created_at'],
                                    'tone' => $event['event_type'] === 'product_click' ? 'cart' : 'ask',
                                )); ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php Components::card_close(); ?>
            <?php else : ?>
                <?php Components::card_open(__('Conversation stream', 'geeky-bot'), '', true); ?>
                    <?php if (empty($session_page['items'])) : ?>
                        <?php Components::empty_state(
                            __('No conversations found', 'geeky-bot'),
                            $search !== ''
                                ? __('No conversation matches that search. Try a shorter phrase, or clear the search.', 'geeky-bot')
                                : __('Storefront conversations appear here once shoppers use the assistant and history is enabled.', 'geeky-bot')
                        ); ?>
                    <?php else : ?>
                        <div class="gb2-scroll">
                            <table class="gb2-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Conversation', 'geeky-bot'); ?></th>
                                        <th><?php esc_html_e('Last shopper message', 'geeky-bot'); ?></th>
                                        <th style="text-align:right"><?php esc_html_e('Messages', 'geeky-bot'); ?></th>
                                        <th style="text-align:right"><?php esc_html_e('Gaps', 'geeky-bot'); ?></th>
                                        <th style="text-align:right"><?php esc_html_e('Clicks', 'geeky-bot'); ?></th>
                                        <th><?php esc_html_e('Last activity', 'geeky-bot'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($session_page['items'] as $row) :
                                    $row_conversation_label = sprintf(
                                        /* translators: %d: conversation ID. */
                                        __('Conversation #%d', 'geeky-bot'),
                                        absint($row['id'])
                                    );
                                    $gaps = absint($row['unanswered_count']);
                                    ?>
                                    <tr>
                                        <td>
                                            <a class="gb2-link" style="margin:0" href="<?php echo esc_url(add_query_arg(array('page' => 'geekybot-conversations', 'gb_session' => absint($row['id'])), admin_url('admin.php'))); ?>"><strong><?php
                                                echo esc_html($row_conversation_label); ?></strong></a>
                                            <div style="font-size:11px;color:var(--gb2-faint)"><?php
                                                echo esc_html(absint($row['user_id']) > 0 ? __('Registered shopper', 'geeky-bot') : __('Guest shopper', 'geeky-bot')); ?></div>
                                        </td>
                                        <td><?php echo esc_html(wp_trim_words((string) $row['last_shopper_message'], 14)); ?></td>
                                        <td class="gb2-table__num"><?php echo esc_html(number_format_i18n($row['message_count'])); ?></td>
                                        <td class="gb2-table__num"<?php echo $gaps > 0 ? ' style="color:var(--gb2-warn)"' : ''; ?>><?php
                                            echo esc_html(number_format_i18n($gaps)); ?></td>
                                        <td class="gb2-table__num"><?php echo esc_html(number_format_i18n($row['product_clicks'])); ?></td>
                                        <td><?php echo esc_html($row['updated_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if ($session_page['pages'] > 1) : ?>
                        <nav class="gb2-pagination"><?php echo wp_kses_post(paginate_links(array('base' => add_query_arg(array('page' => 'geekybot-conversations', 'gb_view' => 'conversations', 'gb_search' => $search, 'gb_paged' => '%#%'), admin_url('admin.php')), 'format' => '', 'current' => $session_page['page'], 'total' => $session_page['pages'], 'prev_text' => __('Previous', 'geeky-bot'), 'next_text' => __('Next', 'geeky-bot')))); ?></nav>
                    <?php endif; ?>
                <?php Components::card_close(); ?>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($workspace === 'review') : ?>
            <section aria-label="<?php esc_attr_e('Unanswered question workbench', 'geeky-bot'); ?>">
                <div class="gb2-toolbar">
                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="gb2-toolbar__search">
                        <input type="hidden" name="page" value="geekybot-conversations" />
                        <input type="hidden" name="gb_view" value="review" />
                        <input type="hidden" name="gb_review_status" value="<?php echo esc_attr($filter); ?>" />
                        <label>
                            <span class="gb2-screen-reader-text"><?php esc_html_e('Search needs review', 'geeky-bot'); ?></span>
                            <input class="gb2-input" type="search" name="gb_review_search" value="<?php echo esc_attr($review_search); ?>" placeholder="<?php esc_attr_e('Search questions, products, or policy areas', 'geeky-bot'); ?>" />
                        </label>
                        <button type="submit" class="gb2-btn"><?php esc_html_e('Search', 'geeky-bot'); ?></button>
                        <?php if ($review_search !== '') : ?>
                            <a class="gb2-btn gb2-btn--ghost" href="<?php echo esc_url(add_query_arg(array('page' => 'geekybot-conversations', 'gb_view' => 'review', 'gb_review_status' => $filter), admin_url('admin.php'))); ?>"><?php esc_html_e('Clear', 'geeky-bot'); ?></a>
                        <?php endif; ?>
                    </form>
                    <div class="gb2-toolbar__actions gb2-filters">
                        <?php
                        $review_filter_labels = array(
                            'needs-review' => __('Needs review', 'geeky-bot'),
                            'handled' => __('Handled', 'geeky-bot'),
                            'ignored' => __('Ignored', 'geeky-bot'),
                            'all' => __('All', 'geeky-bot'),
                        );
                        foreach ($review_filter_labels as $filter_key => $filter_label) :
                            $filter_args = array(
                                'page' => 'geekybot-conversations',
                                'gb_view' => 'review',
                                'gb_review_status' => $filter_key,
                            );
                            if ($review_search !== '') {
                                $filter_args['gb_review_search'] = $review_search;
                            }
                            ?>
                            <a class="<?php echo esc_attr($filter === $filter_key ? 'is-active' : ''); ?>" href="<?php echo esc_url(add_query_arg($filter_args, admin_url('admin.php'))); ?>"><?php echo esc_html($filter_label); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p style="margin:0 0 14px;font-size:12.5px;color:var(--gb2-mute)">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: number of unique issues, 2: total unanswered-message occurrences. */
                            _n(
                                '%1$s unique issue from %2$s unanswered message.',
                                '%1$s unique issues from %2$s unanswered messages.',
                                $active_review_count,
                                'geeky-bot'
                            ),
                            number_format_i18n($active_review_count),
                            number_format_i18n($active_review_occurrences)
                        )
                    );
                    ?>
                </p>

                <div class="gb2-grid" style="margin-bottom:14px">
                    <?php $this->review_bucket_card(__('Catalog data', 'geeky-bot'), $review_buckets['product'], __('Unique product-data issues', 'geeky-bot'), 'product'); ?>
                    <?php $this->review_bucket_card(__('Policy coverage', 'geeky-bot'), $review_buckets['policy'], __('Unique selected-page gaps', 'geeky-bot'), 'policy'); ?>
                    <?php $this->review_bucket_card(__('Shopper wording', 'geeky-bot'), $review_buckets['synonym'], __('Unique wording gaps', 'geeky-bot'), 'synonym'); ?>
                    <?php $this->review_bucket_card(__('Buying actions', 'geeky-bot'), $review_buckets['commerce'], __('Unique Commerce Pro intents', 'geeky-bot'), 'commerce'); ?>
                </div>
                <div class="gb2-stack">
                    <?php if (empty($visible_questions)) : ?>
                        <?php Components::card_open('', '', true); ?>
                            <?php Components::empty_state(
                                __('No matching review issues', 'geeky-bot'),
                                __('Try another filter or search phrase, or keep testing the storefront assistant.', 'geeky-bot')
                            ); ?>
                        <?php Components::card_close(); ?>
                    <?php else : ?>
                        <?php foreach ($visible_questions as $question) :
                            $handled_now = !empty($question->gb_handled_now);
                            $is_manual_handled = !empty($question->gb_manual_handled);
                            $is_auto_handled = !empty($question->gb_auto_handled);
                            $is_ignored = !empty($question->gb_ignored);
                            $bucket = $question->gb_bucket;
                            $meta = $question->gb_reason_meta;
                            $occurrence_count = max(1, absint($question->occurrence_count ?? 1));
                            $conversation_count = max(1, absint($question->conversation_count ?? 1));
                            $product_names = array_values(array_filter(array_map('sanitize_text_field', (array) ($question->product_names ?? array()))));
                            $policy_labels = array_values(array_filter(array_map('sanitize_text_field', (array) ($question->policy_labels ?? array()))));
                            $last_asked_at = !empty($question->last_asked_at) ? (string) $question->last_asked_at : (string) $question->created_at;
                            $last_asked_timestamp = strtotime($last_asked_at);
                            $last_asked_label = $last_asked_timestamp
                                ? sprintf(
                                    /* translators: %s: human-readable time difference. */
                                    __('Last asked %s ago', 'geeky-bot'),
                                    human_time_diff($last_asked_timestamp, current_time('timestamp'))
                                )
                                : $last_asked_at;
                            $test_url = add_query_arg(
                                array(
                                    'page' => 'geekybot-product-assistant',
                                    'gb_test_query' => (string) $question->question,
                                ),
                                admin_url('admin.php')
                            );
                            $source_url = $bucket === 'policy'
                                ? admin_url('admin.php?page=geekybot-store-knowledge')
                                : admin_url('admin.php?page=geekybot-product-assistant');
                            $fix_label = $bucket === 'policy' ? __('Fix policy source', 'geeky-bot') : __('Improve store data', 'geeky-bot');
                            $status_label = $handled_now ? __('Handled now', 'geeky-bot') : ($is_ignored ? __('Ignored', 'geeky-bot') : __('Needs review', 'geeky-bot'));
                            $status_class = $handled_now ? 'is-ready' : ($is_ignored ? 'is-muted' : 'is-warning');
                            $redirect_hidden = sprintf(
                                '<input type="hidden" name="redirect_status" value="%1$s" /><input type="hidden" name="redirect_page" value="%2$d" /><input type="hidden" name="redirect_search" value="%3$s" />',
                                esc_attr($filter),
                                absint($review_page_number),
                                esc_attr($review_search)
                            );
                            ?>
                            <article class="gb2-review <?php echo esc_attr($handled_now ? 'gb2-review--handled' : ($is_ignored ? 'gb2-review--ignored' : 'gb2-review--open')); ?>">
                                <div class="gb2-review__main">
                                    <div class="gb2-inline" style="margin-bottom:6px">
                                        <?php
                                        Components::pill($meta['label'], 'neutral', false);
                                        Components::pill($status_label, $handled_now ? 'ok' : ($is_ignored ? 'neutral' : 'warn'));
                                        ?>
                                    </div>
                                    <h3 style="margin:0 0 6px;font-size:14px;font-weight:600;line-height:1.4"><?php
                                        echo esc_html(wp_trim_words($question->question, 24)); ?></h3>
                                    <div class="gb2-review__meta">
                                        <span><?php echo esc_html(sprintf(
                                            /* translators: %s: number of times this question was asked. */
                                            _n('Asked %s time', 'Asked %s times', $occurrence_count, 'geeky-bot'),
                                            number_format_i18n($occurrence_count)
                                        )); ?></span>
                                        <span><?php echo esc_html(sprintf(
                                            /* translators: %s: number of affected conversations. */
                                            _n('%s conversation', '%s conversations', $conversation_count, 'geeky-bot'),
                                            number_format_i18n($conversation_count)
                                        )); ?></span>
                                        <span><?php echo esc_html($last_asked_label); ?></span>
                                        <?php if (count($product_names) === 1) : ?>
                                            <span><?php echo esc_html(sprintf(
                                                /* translators: %s: product name. */
                                                __('Product: %s', 'geeky-bot'),
                                                $product_names[0]
                                            )); ?></span>
                                        <?php elseif (count($product_names) > 1) : ?>
                                            <span><?php echo esc_html(sprintf(
                                                /* translators: %s: number of affected products. */
                                                __('%s products affected', 'geeky-bot'),
                                                number_format_i18n(count($product_names))
                                            )); ?></span>
                                        <?php endif; ?>
                                        <?php if (count($policy_labels) === 1) : ?>
                                            <span><?php echo esc_html(sprintf(
                                                /* translators: %s: policy area label. */
                                                __('Policy area: %s', 'geeky-bot'),
                                                $policy_labels[0]
                                            )); ?></span>
                                        <?php elseif (count($policy_labels) > 1) : ?>
                                            <span><?php echo esc_html(sprintf(
                                                /* translators: %s: number of affected policy areas. */
                                                __('%s policy areas', 'geeky-bot'),
                                                number_format_i18n(count($policy_labels))
                                            )); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="gb2-note">
                                        <strong style="color:var(--gb2-ink)"><?php esc_html_e('Recommended action', 'geeky-bot'); ?></strong><br>
                                        <?php echo esc_html($handled_now ? __('Retest only if this still fails in the storefront widget.', 'geeky-bot') : $meta['action']); ?>
                                    </p>
                                </div>
                                <div class="gb2-review__actions">
                                    <?php if (!$handled_now && !$is_ignored) : ?>
                                        <a class="gb2-btn gb2-btn--primary" href="<?php echo esc_url($source_url); ?>"><?php echo esc_html($fix_label); ?></a>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('geekybot_review_action'); ?>
                                            <input type="hidden" name="action" value="geekybot_review_action" />
                                            <input type="hidden" name="review_hash" value="<?php echo esc_attr($question->gb_hash); ?>" />
                                            <input type="hidden" name="legacy_review_hash" value="<?php echo esc_attr($question->gb_legacy_hash); ?>" />
                                            <input type="hidden" name="review_action" value="handled" />
                                            <?php echo $redirect_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped above. ?>
                                            <button type="submit" class="gb2-btn"><?php esc_html_e('Mark handled', 'geeky-bot'); ?></button>
                                        </form>
                                    <?php elseif ($is_ignored || $is_manual_handled) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('geekybot_review_action'); ?>
                                            <input type="hidden" name="action" value="geekybot_review_action" />
                                            <input type="hidden" name="review_hash" value="<?php echo esc_attr($question->gb_hash); ?>" />
                                            <input type="hidden" name="legacy_review_hash" value="<?php echo esc_attr($question->gb_legacy_hash); ?>" />
                                            <input type="hidden" name="review_action" value="restore" />
                                            <?php echo $redirect_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped above. ?>
                                            <button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Restore to review', 'geeky-bot'); ?></button>
                                        </form>
                                    <?php else : ?>
                                        <a class="gb2-btn gb2-btn--primary" href="<?php echo esc_url($test_url); ?>"><?php esc_html_e('Retest phrase', 'geeky-bot'); ?></a>
                                    <?php endif; ?>

                                    <details class="gb2-more">
                                        <summary aria-label="<?php esc_attr_e('More review actions', 'geeky-bot'); ?>">•••</summary>
                                        <div class="gb2-more__menu">
                                            <a href="<?php echo esc_url($test_url); ?>"><?php esc_html_e('Test phrase', 'geeky-bot'); ?></a>
                                            <a href="<?php echo esc_url($source_url); ?>"><?php echo esc_html($fix_label); ?></a>
                                            <?php if (!$is_ignored && !$handled_now) : ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                    <?php wp_nonce_field('geekybot_review_action'); ?>
                                                    <input type="hidden" name="action" value="geekybot_review_action" />
                                                    <input type="hidden" name="review_hash" value="<?php echo esc_attr($question->gb_hash); ?>" />
                                                    <input type="hidden" name="legacy_review_hash" value="<?php echo esc_attr($question->gb_legacy_hash); ?>" />
                                                    <input type="hidden" name="review_action" value="ignore" />
                                                    <?php echo $redirect_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped above. ?>
                                                    <button type="submit"><?php esc_html_e('Ignore issue', 'geeky-bot'); ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($review_pages > 1) : ?>
                    <nav class="gb2-pagination" aria-label="<?php esc_attr_e('Unanswered question pages', 'geeky-bot'); ?>">
                        <?php
                        $pagination_args = array(
                            'page' => 'geekybot-conversations',
                            'gb_view' => 'review',
                            'gb_review_status' => $filter,
                            'gb_review_page' => '%#%',
                        );
                        if ($review_search !== '') {
                            $pagination_args['gb_review_search'] = $review_search;
                        }
                        echo wp_kses_post(
                            paginate_links(
                                array(
                                    'base' => add_query_arg($pagination_args, admin_url('admin.php')),
                                    'format' => '',
                                    'current' => $review_page_number,
                                    'total' => $review_pages,
                                    'prev_text' => __('Previous', 'geeky-bot'),
                                    'next_text' => __('Next', 'geeky-bot'),
                                )
                            )
                        );
                        ?>
                    </nav>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }


    public function handle_review_action() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to update the review queue.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_review_action');

        $hash = isset($_POST['review_hash']) ? sanitize_key(wp_unslash($_POST['review_hash'])) : '';
        $legacy_hash = isset($_POST['legacy_review_hash']) ? sanitize_key(wp_unslash($_POST['legacy_review_hash'])) : '';
        $review_action = isset($_POST['review_action']) ? sanitize_key(wp_unslash($_POST['review_action'])) : '';
        $redirect_status = isset($_POST['redirect_status']) ? sanitize_key(wp_unslash($_POST['redirect_status'])) : 'needs-review';
        $redirect_page = isset($_POST['redirect_page']) ? max(1, absint($_POST['redirect_page'])) : 1;
        $redirect_search = isset($_POST['redirect_search']) ? sanitize_text_field(wp_unslash($_POST['redirect_search'])) : '';
        if (!in_array($redirect_status, array('needs-review', 'handled', 'ignored', 'all'), true)) {
            $redirect_status = 'needs-review';
        }
        $redirect_args = array(
            'page' => 'geekybot-conversations',
            'gb_view' => 'review',
            'gb_review_status' => $redirect_status,
            'gb_review_page' => $redirect_page,
        );
        if ($redirect_search !== '') {
            $redirect_args['gb_review_search'] = $redirect_search;
        }

        if (!$hash || !in_array($review_action, array('handled', 'ignore', 'restore'), true)) {
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        $manual_handled = array_fill_keys(array_map('sanitize_key', (array) get_option('geekybot_review_manual_handled', array())), true);
        $ignored = array_fill_keys(array_map('sanitize_key', (array) get_option('geekybot_review_ignored', array())), true);

        if ($review_action === 'handled') {
            $manual_handled[$hash] = true;
            unset($manual_handled[$legacy_hash], $ignored[$hash], $ignored[$legacy_hash]);
            $notice = 'handled';
        } elseif ($review_action === 'ignore') {
            $ignored[$hash] = true;
            unset($ignored[$legacy_hash], $manual_handled[$hash], $manual_handled[$legacy_hash]);
            $notice = 'ignored';
        } else {
            unset($ignored[$hash], $ignored[$legacy_hash], $manual_handled[$hash], $manual_handled[$legacy_hash]);
            $notice = 'restored';
        }

        update_option('geekybot_review_manual_handled', array_keys($manual_handled), false);
        update_option('geekybot_review_ignored', array_keys($ignored), false);

        $redirect_args['gb_review_notice'] = $notice;
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public function handle_export_conversations() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export Geeky Bot conversations.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_export_conversations');

        $insights = new ConversationInsightsService();
        $session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
        $filename = $session_id
            ? 'geekybot-conversation-' . $session_id . '-' . wp_date('Y-m-d') . '.csv'
            : 'geekybot-conversations-' . wp_date('Y-m-d') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($filename));
        header('X-Content-Type-Options: nosniff');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- The php://output response stream is not supported by WP_Filesystem.
        $output = fopen('php://output', 'w');
        if ($output === false) {
            wp_die(esc_html__('Could not create the conversation export.', 'geeky-bot'));
        }

        if ($session_id) {
            $detail = $insights->session_detail($session_id);
            fputcsv($output, array('conversation', 'direction', 'message', 'intent', 'products', 'created_at'));
            foreach ((array) ($detail['messages'] ?? array()) as $message) {
                fputcsv($output, array_map(array($this, 'csv_safe_cell'), array(
                    'Conversation #' . $session_id,
                    $message['direction'],
                    $message['message'],
                    $message['intent'],
                    implode(' | ', (array) $message['products']),
                    $message['created_at'],
                )));
            }
        } else {
            fputcsv($output, array('conversation', 'session_created_at', 'session_updated_at', 'direction', 'message', 'intent', 'created_at'));
            foreach ($insights->export_rows(5000) as $row) {
                fputcsv($output, array_map(array($this, 'csv_safe_cell'), array(
                    $row['conversation'],
                    $row['session_created_at'],
                    $row['session_updated_at'],
                    $row['direction'],
                    $row['message'],
                    $row['intent'],
                    $row['created_at'],
                )));
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- The php://output response stream is not supported by WP_Filesystem.
        fclose($output);
        exit;
    }

    public function handle_delete_conversation() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete Geeky Bot conversations.', 'geeky-bot'));
        }

        $session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
        if (!$session_id) {
            wp_safe_redirect(admin_url('admin.php?page=geekybot-conversations'));
            exit;
        }

        check_admin_referer('geekybot_delete_conversation_' . $session_id);
        (new ConversationInsightsService())->delete_session($session_id);
        wp_safe_redirect(add_query_arg(array('page' => 'geekybot-conversations', 'gb_conversation_notice' => 'deleted'), admin_url('admin.php')));
        exit;
    }

    public function handle_delete_all_conversations() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete Geeky Bot conversation data.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_delete_all_conversations');
        $deleted = (new ConversationInsightsService())->delete_all();
        wp_safe_redirect(add_query_arg(array(
            'page' => 'geekybot-conversations',
            'gb_conversation_notice' => $deleted ? 'all-deleted' : 'delete-failed',
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Prevent spreadsheet applications from evaluating exported shopper text.
     *
     * @param mixed $value CSV cell value.
     * @return string
     */
    private function csv_safe_cell($value) {
        $value = (string) $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        if (preg_match('/^[\t\r\n ]*[=+\-@]/', $value)) {
            $value = "'" . $value;
        }
        return $value;
    }

    public function pro() {
        $license = LicenseService::status_summary();
        $plugin = LicenseService::commerce_pro_plugin_status();
        $update = LicenseService::update_summary();
        $update_settings = LicenseService::update_settings();
        $can_install = current_user_can('install_plugins');
        $can_activate = current_user_can('activate_plugins');
        $status_class = $license['active'] ? 'is-ready' : 'is-warning';
        $plugin_label = $plugin['active']
            ? __('Installed and active', 'geeky-bot')
            : ($plugin['installed'] ? __('Installed, inactive', 'geeky-bot') : __('Not installed', 'geeky-bot'));
        $update_label = !$plugin['installed']
            ? __('Not applicable', 'geeky-bot')
            : (!empty($update['update_available']) ? __('Available', 'geeky-bot') : __('Current', 'geeky-bot'));
        $limit_reached = $license['status'] === 'activation_limit_reached';
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- These sanitized values only render a status notice.
        $notice = isset($_GET['gb_license_notice']) ? sanitize_key(wp_unslash($_GET['gb_license_notice'])) : '';
        $error = isset($_GET['gb_license_error']) ? sanitize_text_field(wp_unslash($_GET['gb_license_error'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $notice_messages = array(
            'activated' => __('Commerce Pro license activated for this site.', 'geeky-bot'),
            'deactivated' => __('Commerce Pro license deactivated on this site.', 'geeky-bot'),
            'refreshed' => __('Commerce Pro license status refreshed.', 'geeky-bot'),
            'installed' => __('Commerce Pro installed and activated.', 'geeky-bot'),
            'plugin_activated' => __('Commerce Pro plugin activated.', 'geeky-bot'),
            'update_settings_saved' => __('Commerce Pro update settings saved.', 'geeky-bot'),
            'update_refreshed' => __('Commerce Pro update status refreshed.', 'geeky-bot'),
            'updated' => __('Commerce Pro updated successfully.', 'geeky-bot'),
            'updated_inactive' => __('Commerce Pro updated successfully. Activate the add-on to enable Pro buying actions.', 'geeky-bot'),
            'updated_activated' => __('Commerce Pro updated and activated successfully.', 'geeky-bot'),
        );
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-pro geekybot-license-admin">
            <?php $this->page_hero(
                __('Add-ons', 'geeky-bot'),
                __('Activate a license, connect this site to geekybot.com, install the protected Commerce Pro add-on, and keep buying actions locked to authorized sites.', 'geeky-bot'),
                __('Commerce Pro', 'geeky-bot'),
                'https://geekybot.com/',
                __('Open geekybot.com', 'geeky-bot'),
                true
            ); ?>

            <?php if ($notice && isset($notice_messages[$notice])) : ?>
                <div class="gb2-snote gb2-snote--ok"><span aria-hidden="true">✓</span><p><?php echo esc_html($notice_messages[$notice]); ?></p></div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="gb2-snote gb2-snote--error"><span aria-hidden="true">!</span><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <section class="gb2-card gb2-scard gb2-license-hero <?php echo esc_attr($status_class); ?>">
                <div class="gb2-license-hero__main">
                    <p class="gb2-eyebrow"><?php esc_html_e('Protected add-on access', 'geeky-bot'); ?></p>
                    <h2><?php echo esc_html($license['active']
                        ? __('Commerce Pro is authorized on this site', 'geeky-bot')
                        : ($limit_reached ? __('Commerce Pro is not authorized on this site', 'geeky-bot') : __('Activate Commerce Pro for this site', 'geeky-bot'))
                    ); ?></h2>
                    <p><?php echo esc_html($limit_reached
                        ? __('The license is valid, but this site is beyond its included activation allowance. Commerce Pro remains unavailable here until an activation is freed or added.', 'geeky-bot')
                        : __('A copied ZIP is not enough. Commerce Pro buying actions require a valid license, an allowed site activation, and a live or cached entitlement check.', 'geeky-bot')
                    ); ?></p>
                    <div class="gb2-license-strip">
                        <span><strong><?php echo esc_html($license['label']); ?></strong><em><?php esc_html_e('License', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($plugin_label); ?></strong><em><?php esc_html_e('Commerce Pro plugin', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($update_label); ?></strong><em><?php esc_html_e('Update', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($license['isStaging'] === 'yes' ? __('Staging', 'geeky-bot') : __('Production', 'geeky-bot')); ?></strong><em><?php esc_html_e('Site type', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($license['maskedKey'] ? $license['maskedKey'] : __('No key', 'geeky-bot')); ?></strong><em><?php esc_html_e('Key', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($license['lastCheckedAt'] ? $license['lastCheckedAt'] : __('Not checked yet', 'geeky-bot')); ?></strong><em><?php esc_html_e('License last checked', 'geeky-bot'); ?></em></span>
                    </div>
                    <?php if (!empty($license['lastError'])) : ?>
                        <p class="gb2-snote gb2-snote--error"><span aria-hidden="true">!</span><?php echo esc_html($license['lastError']); ?></p>
                    <?php elseif (!$error && !$license['active'] && $license['status'] !== 'inactive' && !empty($license['message'])) : ?>
                        <p class="gb2-snote gb2-snote--error"><span aria-hidden="true">!</span><?php echo esc_html($license['message']); ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="gb2-sgrid gb2-sgrid--top">
                <div class="gb2-card gb2-scard">
                    <div class="gb2-scard__head"><h2><?php esc_html_e('License activation', 'geeky-bot'); ?></h2><p><?php esc_html_e('Enter the Commerce Pro key from geekybot.com. The key is encrypted with this WordPress installation’s salts, masked after save, and never sent to storefront JavaScript.', 'geeky-bot'); ?></p></div>
                    <?php
                    // Entering a key and managing an existing activation are two
                    // different jobs. They were previously one undifferentiated
                    // row of WordPress buttons, so the destructive action looked
                    // identical to the harmless one.
                    ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb2-license-form">
                        <?php wp_nonce_field('geekybot_license_action'); ?>
                        <input type="hidden" name="action" value="geekybot_license_activate" />
                        <div class="gb2-field" style="margin-bottom:0">
                            <label for="gb2-license-key"><?php esc_html_e('License key', 'geeky-bot'); ?></label>
                            <div class="gb2-license-form__row">
                                <input class="gb2-input gb2-input--mono" type="text" id="gb2-license-key" name="license_key" value="" placeholder="GB-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" />
                                <button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Activate license', 'geeky-bot'); ?></button>
                            </div>
                            <p class="gb2-field__help"><?php esc_html_e('Find your key in your account on geekybot.com.', 'geeky-bot'); ?></p>
                        </div>
                    </form>

                    <div class="gb2-license-manage">
                        <span class="gb2-license-manage__title"><?php esc_html_e('This activation', 'geeky-bot'); ?></span>
                        <div class="gb2-inline">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_license_refresh" />
                                <button type="submit" class="gb2-btn"><?php esc_html_e('Refresh status', 'geeky-bot'); ?></button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_license_deactivate" />
                                <?php // Removes this site's activation, so it confirms like the other destructive actions. ?>
                                <button type="submit" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php echo esc_attr($license['active'] ? __('Deactivate Commerce Pro on this site? Buying actions will stop until it is activated again.', 'geeky-bot') : __('Clear the saved license key from this site?', 'geeky-bot')); ?>"><?php
                                    echo esc_html($license['active'] ? __('Deactivate this site', 'geeky-bot') : __('Clear saved license', 'geeky-bot')); ?></button>
                            </form>
                        </div>
                        <p class="gb2-field__help"><?php esc_html_e('Refreshing re-checks entitlement with geekybot.com. Deactivating frees this site\'s activation slot for another install.', 'geeky-bot'); ?></p>
                    </div>
                </div>

                <div class="gb2-card gb2-scard">
                    <div class="gb2-scard__head"><h2><?php esc_html_e('Commerce Pro add-on', 'geeky-bot'); ?></h2><p><?php esc_html_e('Install, activate, and update Commerce Pro from a protected geekybot.com package after the site entitlement is valid.', 'geeky-bot'); ?></p></div>
                    <ul class="gb2-checklist">
                        <li><?php echo esc_html($license['active'] ? __('Valid entitlement is available.', 'geeky-bot') : __('Valid entitlement is required before install or activation.', 'geeky-bot')); ?></li>
                        <li><?php echo esc_html(!empty($license['bindingValid']) ? __('This entitlement is bound to the current site installation.', 'geeky-bot') : __('This site still needs a fresh entitlement binding.', 'geeky-bot')); ?></li>
                        <li><?php echo esc_html(!empty($license['signatureRequired']) ? (!empty($license['signatureVerified']) ? __('The server-signed entitlement was verified.', 'geeky-bot') : __('The configured signed-entitlement check has not passed.', 'geeky-bot')) : __('Entitlement uses standard HTTPS verification; signed-response mode is not configured.', 'geeky-bot')); ?></li>
                        <li><?php echo esc_html($plugin['installed'] ? sprintf(
                            /* translators: %s: installed Commerce Pro version. */
                            __('Installed version: %s', 'geeky-bot'),
                            $plugin['version'] ? $plugin['version'] : __('unknown', 'geeky-bot')
                        ) : __('Commerce Pro is not installed yet.', 'geeky-bot')); ?></li>
                        <li><?php echo esc_html($plugin['active'] ? __('Commerce Pro plugin is active.', 'geeky-bot') : __('Commerce Pro plugin is not active.', 'geeky-bot')); ?></li>
                        <li><?php echo esc_html($license['isGrace'] ? __('Running on cached license verification grace period.', 'geeky-bot') : __('Live entitlement or normal cached check required.', 'geeky-bot')); ?></li>
                        <li><?php echo esc_html(!empty($update['latest_version']) ? sprintf(
                            /* translators: %s: latest available Commerce Pro version. */
                            __('Latest release: %s', 'geeky-bot'),
                            $update['latest_version']
                        ) : __('Latest release has not been checked yet.', 'geeky-bot')); ?></li>
                        <li><?php echo esc_html(!empty($license['updatesAllowed']) ? __('Updates are allowed for this license.', 'geeky-bot') : __('Updates require an active renewal entitlement.', 'geeky-bot')); ?></li>
                    </ul>
                    <div class="gb2-inline">
                        <?php if (!$plugin['installed'] && $can_install) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_install_commerce_pro" />
                                <button type="submit" class="gb2-btn gb2-btn--primary" <?php disabled(empty($license['downloadsAllowed'])); ?>><?php esc_html_e('Install Commerce Pro', 'geeky-bot'); ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($plugin['installed'] && !$plugin['active'] && $can_activate) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_activate_commerce_pro" />
                                <button type="submit" class="gb2-btn gb2-btn--primary" <?php disabled(!$license['active']); ?>><?php esc_html_e('Activate Commerce Pro', 'geeky-bot'); ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($update['update_available']) && !empty($update['can_update']) && current_user_can('update_plugins')) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_update_commerce_pro" />
                                <button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Update Commerce Pro', 'geeky-bot'); ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('geekybot_license_action'); ?>
                            <input type="hidden" name="action" value="geekybot_refresh_commerce_pro_update" />
                            <button type="submit" class="gb2-btn"><?php esc_html_e('Check for update', 'geeky-bot'); ?></button>
                        </form>
                        <a class="button" href="<?php echo esc_url(admin_url('plugins.php')); ?>"><?php esc_html_e('Open plugins', 'geeky-bot'); ?></a>
                    </div>
                </div>
            </section>

            <section class="gb2-card gb2-scard">
                <div class="gb2-scard__head"><h2><?php esc_html_e('Commerce Pro update channel', 'geeky-bot'); ?></h2><p><?php esc_html_e('Version metadata is checked from the CDN first. Protected ZIP downloads are requested from geekybot.com only when a valid update entitlement exists.', 'geeky-bot'); ?></p></div>
                <?php if (!empty($update['update_available'])) : ?>
                    <div class="gb2-card gb2-update">
                        <div class="gb2-update__icon" aria-hidden="true">↻</div>
                        <div class="gb2-update__body">
                            <p class="gb2-eyebrow"><?php esc_html_e('Update available', 'geeky-bot'); ?></p>
                            <h3><?php echo esc_html(sprintf(
                                /* translators: %1$s: latest available Commerce Pro version. */
                                __('Commerce Pro %1$s is ready', 'geeky-bot'),
                                $update['latest_version']
                            )); ?></h3>
                            <p><?php echo esc_html(sprintf(
                                /* translators: %s: installed Commerce Pro version. */
                                __('Installed version: %s. This update is served from geekybot.com after license verification.', 'geeky-bot'),
                                $update['installed_version']
                            )); ?></p>
                            <?php if (!empty($update['metadata']['changelog'])) : ?>
                                <div class="gb2-note"><?php echo wp_kses_post($update['metadata']['changelog']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="gb2-inline">
                            <?php if (!empty($update['can_update']) && current_user_can('update_plugins')) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('geekybot_license_action'); ?>
                                    <input type="hidden" name="action" value="geekybot_update_commerce_pro" />
                                    <button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Update Commerce Pro', 'geeky-bot'); ?></button>
                                </form>
                            <?php else : ?>
                                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-addons')); ?>"><?php esc_html_e('Review update entitlement', 'geeky-bot'); ?></a>
                            <?php endif; ?>
                            <a class="button" href="<?php echo esc_url(admin_url('plugins.php')); ?>"><?php esc_html_e('Open plugins page', 'geeky-bot'); ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="gb2-factgrid">
                    <span><strong><?php echo esc_html(!empty($update['latest_version']) ? $update['latest_version'] : __('Unknown', 'geeky-bot')); ?></strong><em><?php esc_html_e('Latest CDN version', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($update['metadata']['channel']) ? strtoupper($update['metadata']['channel']) : strtoupper($update_settings['update_channel'])); ?></strong><em><?php esc_html_e('Channel', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['updatesAllowed']) ? __('Allowed', 'geeky-bot') : __('Renewal required', 'geeky-bot')); ?></strong><em><?php esc_html_e('Update entitlement', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($update['metadata']['critical']) && $update['metadata']['critical'] === 'yes' ? __('Yes', 'geeky-bot') : __('No', 'geeky-bot')); ?></strong><em><?php esc_html_e('Critical flag', 'geeky-bot'); ?></em></span>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb2-license-form">
                    <?php wp_nonce_field('geekybot_license_action'); ?>
                    <input type="hidden" name="action" value="geekybot_save_commerce_pro_updates" />
                    <div class="gb2-sgrid">
                        <label class="gb2-sfield"><span><?php esc_html_e('Release channel', 'geeky-bot'); ?></span><select name="commerce_pro_updates[update_channel]"><option value="stable" <?php selected($update_settings['update_channel'], 'stable'); ?>><?php esc_html_e('Stable', 'geeky-bot'); ?></option><option value="beta" <?php selected($update_settings['update_channel'], 'beta'); ?>><?php esc_html_e('Beta', 'geeky-bot'); ?></option><option value="dev" <?php selected($update_settings['update_channel'], 'dev'); ?>><?php esc_html_e('Dev', 'geeky-bot'); ?></option></select></label>
                        <label class="gb2-sfield"><span><?php esc_html_e('Automatic updates', 'geeky-bot'); ?></span><select name="commerce_pro_updates[auto_update_mode]"><option value="manual" <?php selected($update_settings['auto_update_mode'], 'manual'); ?>><?php esc_html_e('Manual updates only', 'geeky-bot'); ?></option><option value="critical" <?php selected($update_settings['auto_update_mode'], 'critical'); ?>><?php esc_html_e('Critical security updates only', 'geeky-bot'); ?></option><option value="patch" <?php selected($update_settings['auto_update_mode'], 'patch'); ?>><?php esc_html_e('Patch releases only', 'geeky-bot'); ?></option><option value="minor" <?php selected($update_settings['auto_update_mode'], 'minor'); ?>><?php esc_html_e('Patch and minor releases', 'geeky-bot'); ?></option><option value="stable" <?php selected($update_settings['auto_update_mode'], 'stable'); ?>><?php esc_html_e('All stable releases', 'geeky-bot'); ?></option></select></label>
                    </div>
                    <label class="gb2-stoggle"><input type="hidden" name="commerce_pro_updates[auto_update_critical]" value="no" /><input type="checkbox" name="commerce_pro_updates[auto_update_critical]" value="yes" <?php checked($update_settings['auto_update_critical'], 'yes'); ?> /> <span><strong><?php esc_html_e('Always allow critical security auto-updates', 'geeky-bot'); ?></strong><em><?php esc_html_e('Recommended for live WooCommerce stores. The package still requires valid update entitlement from geekybot.com.', 'geeky-bot'); ?></em></span></label>
                    <button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Save update settings', 'geeky-bot'); ?></button>
                </form>
            </section>

            <section class="gb2-card gb2-scard">
                <div class="gb2-scard__head"><h2><?php esc_html_e('Site activation details', 'geeky-bot'); ?></h2><p><?php esc_html_e('These values are sent to geekybot.com during activation, refresh, protected download, and update checks.', 'geeky-bot'); ?></p></div>
                <div class="gb2-factgrid">
                    <span><strong><?php echo esc_html($license['domain']); ?></strong><em><?php esc_html_e('Domain', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html($license['siteUrl']); ?></strong><em><?php esc_html_e('Site URL', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(number_format_i18n($license['activationCount'])); ?> / <?php echo esc_html(number_format_i18n($license['allowedSites'])); ?></strong><em><?php esc_html_e('Production activations', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(number_format_i18n($license['allowedStagingSites'])); ?></strong><em><?php esc_html_e('Allowed staging sites', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html($license['expiresAt'] ? $license['expiresAt'] : __('None', 'geeky-bot')); ?></strong><em><?php esc_html_e('Expires', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['runtimeAllowed']) ? __('Allowed', 'geeky-bot') : __('Locked', 'geeky-bot')); ?></strong><em><?php esc_html_e('Runtime entitlement', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['supportAllowed']) ? __('Allowed', 'geeky-bot') : __('Renewal required', 'geeky-bot')); ?></strong><em><?php esc_html_e('Support entitlement', 'geeky-bot'); ?></em></span>
                </div>
            </section>

        </div>
        <?php
    }

    public function commerce_pro_promo() {
        $license = LicenseService::status_summary();
        $plugin = LicenseService::commerce_pro_plugin_status();
        $has_license = !empty($license['active']);
        if ($has_license) {
            $cta_url = admin_url('admin.php?page=geekybot-addons');
            $cta_label = $plugin['installed'] ? __('Activate the Commerce Pro add-on', 'geeky-bot') : __('Install the Commerce Pro add-on', 'geeky-bot');
        } else {
            $cta_url = 'https://geekybot.com/';
            $cta_label = __('Get Commerce Pro', 'geeky-bot');
        }
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-commerce-pro-promo">
            <?php
            $promo_actions = array(
                array(
                    'label' => $cta_label,
                    'url' => $cta_url,
                    'variant' => 'primary',
                    'external' => !$has_license,
                ),
            );

            if (!$has_license) {
                $promo_actions[] = array(
                    'label' => __('I already have a license', 'geeky-bot'),
                    'url' => admin_url('admin.php?page=geekybot-addons'),
                );
            }

            $promo_actions[] = array(
                'label' => __('Preview examples', 'geeky-bot'),
                'url' => admin_url('admin.php?page=geekybot-guided-demo'),
            );

            Components::page_header(array(
                'title' => __('Let shoppers buy without leaving the chat', 'geeky-bot'),
                'brand' => array($this, 'brand_mark_svg'),
                'description' => __('Commerce Pro finishes the sale: variations, cart control, checkout handoff, order help, comparisons, recommendations and coupons — grounded in your WooCommerce data.', 'geeky-bot'),
                'signals' => array(
                    array('value' => __('18', 'geeky-bot'), 'label' => __('pro buying features', 'geeky-bot')),
                    array('value' => __('In-chat', 'geeky-bot'), 'label' => __('cart and checkout', 'geeky-bot')),
                    array('value' => __('Grounded', 'geeky-bot'), 'label' => __('safe sales rules', 'geeky-bot')),
                ),
                'actions' => $promo_actions,
            ));
            ?>

            <div class="gb2-main">
                <?php Components::card_open(__('From product finder to sales channel', 'geeky-bot'), __('Why stores upgrade', 'geeky-bot'), true); ?>
                <ul class="gb2-tasks">
                    <?php
                    $promo_points = array(
                        __('Shoppers complete the purchase inside the assistant flow.', 'geeky-bot'),
                        __('Every buying action uses your live WooCommerce cart and orders.', 'geeky-bot'),
                        __('All controls stay in this dashboard, protected by safe guardrails.', 'geeky-bot'),
                    );

                    foreach ($promo_points as $point) {
                        Components::task(array(
                            'title' => $point,
                            'severity' => 'done',
                        ));
                    }
                    ?>
                </ul>
                <?php Components::card_close(); ?>
            </div>

            <?php if ($has_license && !$plugin['active']) : ?>
                <div class="gb2-snote gb2-snote--ok"><span aria-hidden="true">✓</span><p><?php esc_html_e('Your license is already active on this site. Install and activate the Commerce Pro add-on to unlock buying actions.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>

            <section class="gb2-card gb2-scard">
                <div class="gb2-scard__head">
                    <p class="gb2-eyebrow"><?php esc_html_e('Buying engine', 'geeky-bot'); ?></p>
                    <h2><?php esc_html_e('What Commerce Pro unlocks', 'geeky-bot'); ?></h2>
                    <p><?php esc_html_e('Every feature works inside the storefront assistant your shoppers already use — no extra widget, no theme changes.', 'geeky-bot'); ?></p>
                </div>
                <div class="gb2-sgrid">
                    <div class="gb2-promo-feature"><span>01</span><strong><?php esc_html_e('Add to cart in chat', 'geeky-bot'); ?></strong><em><?php esc_html_e('Simple products and variable products with option selection.', 'geeky-bot'); ?></em></div>
                    <div class="gb2-promo-feature"><span>02</span><strong><?php esc_html_e('Cart control', 'geeky-bot'); ?></strong><em><?php esc_html_e('Shoppers review the cart, change quantities, and remove items by asking.', 'geeky-bot'); ?></em></div>
                    <div class="gb2-promo-feature"><span>03</span><strong><?php esc_html_e('Checkout handoff', 'geeky-bot'); ?></strong><em><?php esc_html_e('A checkout button appears as soon as the cart has items.', 'geeky-bot'); ?></em></div>
                    <div class="gb2-promo-feature"><span>04</span><strong><?php esc_html_e('Order help', 'geeky-bot'); ?></strong><em><?php esc_html_e('Logged-in customers ask about their own recent orders and status.', 'geeky-bot'); ?></em></div>
                    <div class="gb2-promo-feature"><span>05</span><strong><?php esc_html_e('Product comparison', 'geeky-bot'); ?></strong><em><?php esc_html_e('Clear side-by-side answers when shoppers compare products.', 'geeky-bot'); ?></em></div>
                    <div class="gb2-promo-feature"><span>06</span><strong><?php esc_html_e('Smart recommendations', 'geeky-bot'); ?></strong><em><?php esc_html_e('Suggestions grounded in your own catalog, never invented.', 'geeky-bot'); ?></em></div>
                    <div class="gb2-promo-feature"><span>07</span><strong><?php esc_html_e('Coupons and deals', 'geeky-bot'); ?></strong><em><?php esc_html_e('Safe exposure of existing store coupons only.', 'geeky-bot'); ?></em></div>
                    <div class="gb2-promo-feature"><span>08</span><strong><?php esc_html_e('Leads and human handoff', 'geeky-bot'); ?></strong><em><?php esc_html_e('Capture follow-up requests when a human touch is needed.', 'geeky-bot'); ?></em></div>
                </div>
            </section>

            <section class="gb2-sgrid gb2-sgrid">
                <div class="gb2-card gb2-scard">
                    <div class="gb2-scard__head">
                        <p class="gb2-eyebrow"><?php esc_html_e('Try before you buy', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('See it on your own store data', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Guided Demo generates real shopper requests from your indexed products and labels the buying actions that Commerce Pro completes.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb2-inline">
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><?php esc_html_e('Open Guided Demo', 'geeky-bot'); ?></a>
                        <a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Test storefront widget', 'geeky-bot'); ?></a>
                    </div>
                </div>
                <div class="gb2-card gb2-scard">
                    <div class="gb2-scard__head">
                        <p class="gb2-eyebrow"><?php esc_html_e('Getting started', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Live in three steps', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('No FTP and no manual ZIP handling — everything happens from this dashboard.', 'geeky-bot'); ?></p>
                    </div>
                    <ol class="gb2-sgrid">
                        <li><strong><?php esc_html_e('Get a license', 'geeky-bot'); ?></strong><em><?php esc_html_e('Purchase Commerce Pro on geekybot.com.', 'geeky-bot'); ?></em></li>
                        <li><strong><?php esc_html_e('Activate it here', 'geeky-bot'); ?></strong><em><?php esc_html_e('Enter the key on the Add-ons page to authorize this site.', 'geeky-bot'); ?></em></li>
                        <li><strong><?php esc_html_e('Install with one click', 'geeky-bot'); ?></strong><em><?php esc_html_e('The protected add-on installs and updates from this dashboard.', 'geeky-bot'); ?></em></li>
                    </ol>
                    <div class="gb2-inline">
                        <a class="button button-primary" href="<?php echo esc_url($cta_url); ?>" <?php echo $has_license ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>><?php echo esc_html($cta_label); ?></a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-addons')); ?>"><?php esc_html_e('Open license page', 'geeky-bot'); ?></a>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    private function context() {
        $settings = Settings::all();
        $products = new ProductService();
        $knowledge = new KnowledgeService();
        $index = new ProductIndexService();
        $wc_ready = $products->is_woocommerce_ready();
        $policy_count = count($knowledge->selected_pages());
        $indexed_count = $index->count_indexed();
        $provider_ready = $settings['provider_mode'] === 'local' || ($settings['provider_mode'] === 'zywrap' && Settings::has_secret('zywrap_api_key') && !empty($settings['zywrap_endpoint'])) || ($settings['provider_mode'] === 'openai' && Settings::has_secret('openai_api_key'));
        return array('settings' => $settings, 'wc_ready' => $wc_ready, 'policy_count' => $policy_count, 'indexed_count' => $indexed_count, 'last_rebuild' => get_option(ProductIndexService::LAST_REBUILD_OPTION, ''), 'index_status' => ProductIndexService::rebuild_status(), 'provider_ready' => $provider_ready, 'stats' => $this->stats(), 'completion' => $this->setup_completion($wc_ready, $indexed_count, $policy_count, $provider_ready, $settings));
    }

    private function admin_notice_indexed() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- These integer values only render an indexing result notice.
        if (isset($_GET['gb_indexed'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php printf(
                /* translators: 1: number of indexed products, 2: number of skipped products. */
                esc_html__('Product search index rebuilt. Indexed: %1$s. Skipped: %2$s.', 'geeky-bot'),
                esc_html(number_format_i18n(absint($_GET['gb_indexed']))),
                esc_html(number_format_i18n(absint(isset($_GET['gb_skipped']) ? $_GET['gb_skipped'] : 0)))
            ); ?></p></div>
        <?php endif;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    private function admin_notice_search_controls() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- These sanitized values only render a settings result notice.
        if (empty($_GET['updated'])) {
            return;
        }

        $notice = isset($_GET['gb_notice']) ? sanitize_key(wp_unslash($_GET['gb_notice'])) : 'saved';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $message = __('Buyer search settings saved.', 'geeky-bot');
        if ($notice === 'synonyms_reset') {
            $message = __('Default synonyms restored.', 'geeky-bot');
        } elseif ($notice === 'controls_reset') {
            $message = __('Buyer search settings reset to defaults.', 'geeky-bot');
        }
        ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php
    }

    /**
     * Page header.
     *
     * Kept on the 2.0.1 signature so all existing call sites upgrade at once.
     * The 2.0.1 markup opened every page with roughly 470px of hero — brand
     * lockup, oversized headline, paragraph, buttons, signal chips and a
     * readiness aside — repeated identically on ten pages. This renders the
     * same information as a compact header:
     *
     *   - the brand lockup moves to the Dashboard only,
     *   - the signal chips become a single inline fact row,
     *   - the readiness aside's static reassurance copy is dropped; live
     *     readiness now belongs on the Dashboard, where it is actionable.
     *
     * @param string $title        Page title.
     * @param string $description  Supporting sentence.
     * @param string $kicker       Context key used to look up page signals.
     * @param string $action_url   Primary action URL. Optional.
     * @param string $action_label Primary action label. Optional.
     * @param bool   $external     Whether the primary action opens a new tab.
     * @param bool   $brand        Show the brand mark. Dashboard only.
     * @return void
     */
    private function page_hero($title, $description, $kicker, $action_url = '', $action_label = '', $external = false, $brand = false) {
        $monitor = $this->page_monitor_data($kicker, $title);

        $signals = array();
        foreach ((array) $monitor['signals'] as $signal) {
            if (!isset($signal[0], $signal[1])) {
                continue;
            }
            $signals[] = array('value' => $signal[0], 'label' => $signal[1]);
        }

        $actions = array();
        if ($action_url && $action_label) {
            $actions[] = array(
                'label' => $action_label,
                'url' => $action_url,
                'variant' => 'primary',
                'external' => (bool) $external,
            );
            $actions[] = array(
                'label' => __('Settings', 'geeky-bot'),
                'url' => admin_url('admin.php?page=geekybot-settings'),
            );
        }

        $settings = Settings::all();
        $widget_live = !empty($settings['widget_enabled']) && $settings['widget_enabled'] === 'yes';

        Components::page_header(array(
            'title' => $title,
            'description' => $description,
            // The mark now appears on every page. It was Dashboard-only, which
            // left the other nine screens with no product identity at all.
            'brand' => array($this, 'brand_mark_svg'),
            'status' => array(
                'label' => $widget_live ? __('Live on storefront', 'geeky-bot') : __('Widget is off', 'geeky-bot'),
                'state' => $widget_live ? 'ok' : 'warn',
            ),
            'signals' => $signals,
            'actions' => $actions,
        ));
    }

    private function page_monitor_data($kicker, $title) {
        $ctx = $this->context();
        $settings = $ctx['settings'];
        $key = strtolower(trim(wp_strip_all_tags((string) $kicker)));
        $widget_status = !empty($settings['widget_enabled']) && $settings['widget_enabled'] === 'yes' ? __('Live', 'geeky-bot') : __('Draft', 'geeky-bot');
        $search_status = !empty($settings['natural_search_enabled']) && $settings['natural_search_enabled'] === 'yes' ? __('Natural search', 'geeky-bot') : __('Keyword search', 'geeky-bot');
        $provider_status = $this->provider_label($settings);
        $policy_count = absint($ctx['policy_count']);
        $indexed_count = absint($ctx['indexed_count']);
        $stats = $ctx['stats'];

        // No signals by default. The 2.0.1 fallback printed "Guided / Admin
        // flow", "Grounded / Store data", "Scoped / Plugin UI" — labels that
        // carry no store data and appeared on every page that matched none of
        // the branches below. An empty row is better than filler.
        $default = array(
            'title' => $title,
            'signals' => array(),
            'checks' => array(),
        );

        // Checked before 'safe', because the Conversations kicker is
        // "Privacy-safe review" and would otherwise match the policy branch
        // and show policy-page counts on a conversations page.
        if (strpos($key, 'review') !== false || strpos($key, 'privacy') !== false) {
            return array(
                'title' => __('Conversation review', 'geeky-bot'),
                'signals' => array(
                    array(number_format_i18n($stats['sessions']), __('conversations', 'geeky-bot')),
                    array(number_format_i18n($stats['messages']), __('messages stored', 'geeky-bot')),
                    array(number_format_i18n($stats['review_needed']), __('need review', 'geeky-bot')),
                ),
                'checks' => array(),
            );
        }

        if (strpos($key, 'guided') !== false) {
            return array(
                'title' => __('Guided launch', 'geeky-bot'),
                'signals' => array(
                    array(absint($ctx['completion']) . '%', __('Readiness', 'geeky-bot')),
                    array(number_format_i18n($indexed_count), __('Indexed products', 'geeky-bot')),
                    array(number_format_i18n($policy_count), __('Policy sources', 'geeky-bot')),
                ),
                'checks' => array(
                    __('WooCommerce catalog is checked before launch.', 'geeky-bot'),
                    __('Search index, policy pages, widget and privacy are reviewed.', 'geeky-bot'),
                    __('Final test links keep setup focused.', 'geeky-bot'),
                ),
            );
        }

        if (strpos($key, 'shopper') !== false) {
            return array(
                'title' => __('Shopper experience', 'geeky-bot'),
                'signals' => array(
                    array($widget_status, __('Storefront widget', 'geeky-bot')),
                    array(absint($settings['max_products']), __('Products shown', 'geeky-bot')),
                    array(ucfirst((string) $settings['button_position']), __('Placement', 'geeky-bot')),
                ),
                'checks' => array(
                    __('Widget preview follows the live storefront panel.', 'geeky-bot'),
                    __('Welcome copy, fallback text and product card volume are controlled here.', 'geeky-bot'),
                    __('Scoped frontend CSS avoids theme layout changes.', 'geeky-bot'),
                ),
            );
        }

        if (strpos($key, 'product') !== false) {
            $fallback = !empty($settings['search_close_match_mode']) && $settings['search_close_match_mode'] === 'strict' ? __('Exact matches', 'geeky-bot') : __('Smart matches', 'geeky-bot');
            return array(
                'title' => __('Product discovery', 'geeky-bot'),
                'signals' => array(
                    array(number_format_i18n($indexed_count), __('Products indexed', 'geeky-bot')),
                    array($search_status, __('Buyer language', 'geeky-bot')),
                    array($fallback, __('Match mode', 'geeky-bot')),
                ),
                'checks' => array(
                    __('Search lab explains parsed buyer intent and match reasons.', 'geeky-bot'),
                    __('Synonyms, close matches and ranking boosts are configurable.', 'geeky-bot'),
                    __('Product index tools stay separate from storefront behavior.', 'geeky-bot'),
                ),
            );
        }

        // 'policy' included so Store Knowledge ("Grounded policy answers")
        // gets these signals instead of falling through to none.
        if (strpos($key, 'safe') !== false || strpos($key, 'policy') !== false) {
            return array(
                'title' => __('Safe answer sources', 'geeky-bot'),
                'signals' => array(
                    array(number_format_i18n($policy_count), __('Selected pages', 'geeky-bot')),
                    array(__('Safe', 'geeky-bot'), __('Fallback', 'geeky-bot')),
                    array(__('Public only', 'geeky-bot'), __('Source rule', 'geeky-bot')),
                ),
                'checks' => array(
                    __('Only selected public pages can ground policy answers.', 'geeky-bot'),
                    __('Missing policy details use the fallback message.', 'geeky-bot'),
                    __('No shipping, refund or warranty policy is invented.', 'geeky-bot'),
                ),
            );
        }

        if (strpos($key, 'commerce') !== false) {
            return array(
                'title' => __('Assistant performance', 'geeky-bot'),
                'signals' => array(
                    array(number_format_i18n($stats['sessions']), __('Conversations', 'geeky-bot')),
                    array(number_format_i18n($stats['messages']), __('Messages', 'geeky-bot')),
                    array(number_format_i18n($stats['review_needed']), __('Needs review', 'geeky-bot')),
                ),
                'checks' => array(
                    __('Conversation counts show usage and learning volume.', 'geeky-bot'),
                    __('Needs-review signals expose product, policy, and synonym issues.', 'geeky-bot'),
                    __('Commerce Pro analytics are kept clearly separated.', 'geeky-bot'),
                ),
            );
        }

        if (strpos($key, 'ai') !== false) {
            return array(
                'title' => __('Answer mode', 'geeky-bot'),
                'signals' => array(
                    array($provider_status, __('Answer mode', 'geeky-bot')),
                    array(__('Hidden', 'geeky-bot'), __('API keys', 'geeky-bot')),
                    array(__('Protected', 'geeky-bot'), __('REST requests', 'geeky-bot')),
                ),
                'checks' => array(
                    __('API keys stay server-side and are never exposed to frontend JavaScript.', 'geeky-bot'),
                    __('Admin changes use nonce and capability checks.', 'geeky-bot'),
                    __('Grounded answering avoids invented products, prices, coupons and policies.', 'geeky-bot'),
                ),
            );
        }

        return $default;
    }

    private function product_search_controls($settings) {
        $index_service = new ProductIndexService();
        $indexed_count = $index_service->count_indexed();
        $last_rebuild = get_option(ProductIndexService::LAST_REBUILD_OPTION, '');
        $index_status = ProductIndexService::rebuild_status();
        ?>
        <?php Components::card_open(__('Buyer search controls', 'geeky-bot'), '', false, 'gb2-fill'); ?>
            <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                esc_html_e('Tune how shopper wording is understood, without editing code. Rebuild the index after catalog changes.', 'geeky-bot'); ?></p>
            <form method="post">
                <?php wp_nonce_field('geekybot_save_settings'); ?>
                <input type="hidden" name="geekybot_settings_action" value="save" />
                <input type="hidden" name="geekybot_settings_scope" value="partial" />
                <input type="hidden" name="geekybot_redirect" value="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant')); ?>" />
                <?php $this->settings_table_search($settings, 'compact'); ?>

                <div class="gb2-inline" style="margin-top:14px">
                    <button type="submit" class="gb2-btn gb2-btn--primary"><?php
                        esc_html_e('Save search settings', 'geeky-bot'); ?></button>
                </div>

                <div class="gb2-inline" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--gb2-line-soft)"
                     aria-label="<?php esc_attr_e('Reset actions', 'geeky-bot'); ?>">
                    <span style="font-size:11.5px;color:var(--gb2-faint)"><?php esc_html_e('Reset:', 'geeky-bot'); ?></span>
                    <button type="submit" name="geekybot_reset_search" value="synonyms" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php esc_attr_e('Restore the default synonym examples? Your custom synonym text will be replaced.', 'geeky-bot'); ?>"><?php esc_html_e('Default synonyms', 'geeky-bot'); ?></button>
                    <button type="submit" name="geekybot_reset_search" value="all" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php esc_attr_e('Reset all buyer search settings to their defaults?', 'geeky-bot'); ?>"><?php esc_html_e('All search settings', 'geeky-bot'); ?></button>
                </div>
            </form>

            <div class="gb2-keyvalues" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--gb2-line-soft)">
                <div class="gb2-keyvalue"><span><?php esc_html_e('Products indexed', 'geeky-bot'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($indexed_count)); ?></strong></div>
                <div class="gb2-keyvalue"><span><?php esc_html_e('Last full index', 'geeky-bot'); ?></span>
                    <strong style="font-size:12px;font-weight:500;color:var(--gb2-mute)"><?php
                        echo esc_html($last_rebuild ? $last_rebuild : __('Never', 'geeky-bot')); ?></strong></div>
                <div class="gb2-keyvalue"><span><?php esc_html_e('Automatic indexing', 'geeky-bot'); ?></span>
                    <strong style="font-size:12px;font-weight:500;color:var(--gb2-mute)"><?php
                        echo esc_html($this->product_index_status_text($index_status)); ?></strong></div>
            </div>
        <?php Components::card_close(); ?>
    <?php }

    private function nlp_action_examples() { ?>
        <?php Components::card_open(__('Storefront intent checklist', 'geeky-bot'), '', false); ?>
            <p style="margin:0 0 12px;font-size:12.5px;line-height:1.55;color:var(--gb2-mute)"><?php
                esc_html_e('Try these grouped phrases in the storefront widget to confirm search, compare, cart, orders, deals and human handoff stay separated.', 'geeky-bot'); ?></p>
            <div class="gb2-grid">
                <?php $this->nlp_action_group(__('Search', 'geeky-bot'), array('comfortable shoes size 42 red and white', 'blue hoodie under 60', 'not too expensive walking shoes')); ?>
                <?php $this->nlp_action_group(__('Compare', 'geeky-bot'), array('compare first and third', 'what is difference between hoodie and hoodie with logo', 'compare cheaper one and second')); ?>
                <?php $this->nlp_action_group(__('Cart', 'geeky-bot'), array('add second product', 'remove first item', 'make belt quantity 3')); ?>
                <?php $this->nlp_action_group(__('Orders', 'geeky-bot'), array('what did I buy last time', 'show my latest order', 'where is my parcel')); ?>
                <?php $this->nlp_action_group(__('Deals', 'geeky-bot'), array('any promo code', 'cheapest today', 'show sale items')); ?>
                <?php $this->nlp_action_group(__('Human handoff', 'geeky-bot'), array('problem with my order', 'I want to complain', 'talk to human')); ?>
            </div>
        <?php Components::card_close(); ?>
    <?php }

    private function nlp_action_group($title, $phrases) { ?>
        <div class="gb2-col-4">
            <strong style="display:block;margin-bottom:6px;font-size:12px;font-weight:600"><?php echo esc_html($title); ?></strong>
            <div class="gb2-code-list">
                <?php foreach ((array) $phrases as $phrase) : ?><code><?php echo esc_html($phrase); ?></code><?php endforeach; ?>
            </div>
        </div>
    <?php }

    private function settings_table_search($settings, $mode = 'table') {
        $compact = $mode === 'compact';
        $wrap_class = $compact ? 'gb2-stack' : 'form-table gb-form-table';
        ?>
        <?php if ($compact) : ?>
            <div class="<?php echo esc_attr($wrap_class); ?>">
                <?php $this->search_control_fields($settings, true); ?>
            </div>
        <?php else : ?>
            <table class="<?php echo esc_attr($wrap_class); ?>" role="presentation">
                <?php $this->search_control_fields($settings, false); ?>
            </table>
        <?php endif; ?>
    <?php }

    private function search_control_fields($settings, $compact = false) {
        $synonym_placeholder = "comfy = comfortable, soft, cushioned\ntrainers = sneakers, shoes\nnot expensive = budget, affordable, low price";
        if ($compact) : ?>
            <div class="gb2-switch-row" style="padding-top:0">
                <input type="hidden" name="natural_search_enabled" value="no" />
                <input type="checkbox" id="gb2-natural-search" name="natural_search_enabled" value="yes" <?php checked($settings['natural_search_enabled'], 'yes'); ?> />
                <span class="gb2-switch-row__text">
                    <label for="gb2-natural-search"><strong><?php esc_html_e('Understand natural buyer language', 'geeky-bot'); ?></strong></label>
                    <span><?php esc_html_e('Long phrases, price words, colour, size and soft preferences.', 'geeky-bot'); ?></span>
                </span>
            </div>

            <div style="height:14px"></div>

            <div class="gb2-field-row">
                <div class="gb2-field">
                    <label for="gb2-match-mode"><?php esc_html_e('Product match behaviour', 'geeky-bot'); ?></label>
                    <select class="gb2-input" id="gb2-match-mode" name="search_close_match_mode">
                        <option value="smart" <?php selected($settings['search_close_match_mode'], 'smart'); ?>><?php esc_html_e('Smart product matches', 'geeky-bot'); ?></option>
                        <option value="strict" <?php selected($settings['search_close_match_mode'], 'strict'); ?>><?php esc_html_e('Exact matches only', 'geeky-bot'); ?></option>
                    </select>
                </div>
                <div class="gb2-field">
                    <label for="gb2-min-score"><?php esc_html_e('Minimum score', 'geeky-bot'); ?></label>
                    <input class="gb2-input" id="gb2-min-score" name="search_min_score" type="number" min="1" max="200" value="<?php echo esc_attr(absint($settings['search_min_score'])); ?>" />
                    <p class="gb2-field__help"><?php esc_html_e('Raise it only if weak results appear. Higher values hide close matches.', 'geeky-bot'); ?></p>
                </div>
            </div>

            <div class="gb2-field">
                <label><?php esc_html_e('Ranking boosts', 'geeky-bot'); ?></label>
                <div class="gb2-field-row" style="gap:8px">
                    <?php $this->search_boost_checkbox('search_boost_in_stock', __('In-stock products', 'geeky-bot'), $settings); ?>
                    <?php $this->search_boost_checkbox('search_boost_sale', __('Sale products', 'geeky-bot'), $settings); ?>
                    <?php $this->search_boost_checkbox('search_boost_rating', __('Well-rated products', 'geeky-bot'), $settings); ?>
                    <?php $this->search_boost_checkbox('search_boost_popularity', __('Popular products', 'geeky-bot'), $settings); ?>
                </div>
            </div>

            <div class="gb2-field">
                <label for="gb2-synonyms"><?php esc_html_e('Custom synonyms', 'geeky-bot'); ?></label>
                <textarea class="gb2-input gb2-input--mono" id="gb2-synonyms" name="search_custom_synonyms" rows="7" placeholder="<?php echo esc_attr($synonym_placeholder); ?>"><?php echo esc_textarea($settings['search_custom_synonyms']); ?></textarea>
                <p class="gb2-field__help"><?php esc_html_e('One rule per line: what shoppers type = what your catalog calls it. For example: comfy = comfortable, soft, cushioned.', 'geeky-bot'); ?></p>
            </div>
        <?php else : ?>
            <tr><th scope="row"><?php esc_html_e('Natural buyer search', 'geeky-bot'); ?></th><td><label><input type="hidden" name="natural_search_enabled" value="no" /><input type="checkbox" name="natural_search_enabled" value="yes" <?php checked($settings['natural_search_enabled'], 'yes'); ?> /> <?php esc_html_e('Understand long buyer phrases instead of keyword-only search.', 'geeky-bot'); ?></label></td></tr>
            <tr><th scope="row"><label for="search_close_match_mode"><?php esc_html_e('Product match behavior', 'geeky-bot'); ?></label></th><td><select id="search_close_match_mode" name="search_close_match_mode"><option value="smart" <?php selected($settings['search_close_match_mode'], 'smart'); ?>><?php esc_html_e('Smart product matches when exact color/size is not confirmed', 'geeky-bot'); ?></option><option value="strict" <?php selected($settings['search_close_match_mode'], 'strict'); ?>><?php esc_html_e('Exact matches only', 'geeky-bot'); ?></option></select></td></tr>
            <tr><th scope="row"><label for="search_min_score"><?php esc_html_e('Minimum result score', 'geeky-bot'); ?></label></th><td><input id="search_min_score" name="search_min_score" type="number" min="1" max="200" value="<?php echo esc_attr(absint($settings['search_min_score'])); ?>" /><p class="description"><?php esc_html_e('Keep low unless you are seeing weak results. Higher values hide more close matches.', 'geeky-bot'); ?></p></td></tr>
            <tr><th scope="row"><?php esc_html_e('Ranking boosts', 'geeky-bot'); ?></th><td><div class="gb2-field-row"><?php $this->search_boost_checkbox('search_boost_in_stock', __('Boost in-stock products', 'geeky-bot'), $settings); ?><?php $this->search_boost_checkbox('search_boost_sale', __('Boost sale products', 'geeky-bot'), $settings); ?><?php $this->search_boost_checkbox('search_boost_rating', __('Boost ratings', 'geeky-bot'), $settings); ?><?php $this->search_boost_checkbox('search_boost_popularity', __('Boost popularity', 'geeky-bot'), $settings); ?></div></td></tr>
            <tr><th scope="row"><label for="search_custom_synonyms"><?php esc_html_e('Custom synonyms', 'geeky-bot'); ?></label></th><td><textarea id="search_custom_synonyms" name="search_custom_synonyms" rows="7" class="large-text code" placeholder="<?php echo esc_attr($synonym_placeholder); ?>"><?php echo esc_textarea($settings['search_custom_synonyms']); ?></textarea><p class="description gb2-field__help"><?php esc_html_e('One rule per line: shopper words = catalog words. Example: comfy = comfortable, soft, cushioned. Use words shoppers type on the left and catalog words/attributes on the right. These are added to the built-in search synonyms.', 'geeky-bot'); ?></p></td></tr>
        <?php endif;
    }

    private function search_boost_checkbox($name, $label, $settings) { ?>
        <label class="gb2-check"><input type="hidden" name="<?php echo esc_attr($name); ?>" value="no" /><input type="checkbox" name="<?php echo esc_attr($name); ?>" value="yes" <?php checked(isset($settings[$name]) ? $settings[$name] : 'yes', 'yes'); ?> /> <span><?php echo esc_html($label); ?></span></label>
    <?php }

    private function settings_table_assistant($settings) { ?>
        <div class="gb2-sgrid">
            <label class="gb2-stoggle"><input type="hidden" name="widget_enabled" value="no" /><input type="checkbox" name="widget_enabled" value="yes" <?php checked($settings['widget_enabled'], 'yes'); ?> /> <span><strong><?php esc_html_e('Enable storefront widget', 'geeky-bot'); ?></strong><em><?php esc_html_e('Show the floating assistant button on public store pages.', 'geeky-bot'); ?></em></span></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Assistant name', 'geeky-bot'); ?></span><input id="assistant_name" name="assistant_name" type="text" value="<?php echo esc_attr($settings['assistant_name']); ?>" /><em><?php esc_html_e('Shown in the widget header.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Assistant subtitle', 'geeky-bot'); ?></span><input id="assistant_subtitle" name="assistant_subtitle" type="text" value="<?php echo esc_attr($settings['assistant_subtitle']); ?>" /><em><?php esc_html_e('Shown below the assistant name.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield gb2-sfield--wide"><span><?php esc_html_e('Welcome message', 'geeky-bot'); ?></span><textarea id="welcome_message" name="welcome_message" rows="3"><?php echo esc_textarea($settings['welcome_message']); ?></textarea><em><?php esc_html_e('Use shopper-friendly language that invites natural product questions.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield gb2-sfield--wide"><span><?php esc_html_e('Safe fallback answer', 'geeky-bot'); ?></span><textarea id="fallback_human_message" name="fallback_human_message" rows="3"><?php echo esc_textarea($settings['fallback_human_message']); ?></textarea><em><?php esc_html_e('Shown when catalog data or selected policy pages cannot answer the shopper safely.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield gb2-sfield--color"><span><?php esc_html_e('Accent color', 'geeky-bot'); ?></span><input id="accent_color" name="accent_color" type="color" value="<?php echo esc_attr($settings['accent_color']); ?>" /><em><?php esc_html_e('Used for widget buttons and header accents.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Button position', 'geeky-bot'); ?></span><select name="button_position"><option value="right" <?php selected($settings['button_position'], 'right'); ?>><?php esc_html_e('Right', 'geeky-bot'); ?></option><option value="left" <?php selected($settings['button_position'], 'left'); ?>><?php esc_html_e('Left', 'geeky-bot'); ?></option></select><em><?php esc_html_e('Floating widget placement on the storefront.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Products per answer', 'geeky-bot'); ?></span><input id="max_products" name="max_products" type="number" min="1" max="8" value="<?php echo esc_attr(absint($settings['max_products'])); ?>" /><em><?php esc_html_e('Recommended: 3–4 products for clean assistant replies.', 'geeky-bot'); ?></em></label>
        </div>
    <?php }

    private function settings_table_ai($settings) { ?>
        <div class="gb2-radios" role="radiogroup" aria-label="<?php esc_attr_e('Answer mode', 'geeky-bot'); ?>">
            <label class="gb2-radio <?php echo esc_attr($settings['provider_mode'] === 'local' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="local" <?php checked($settings['provider_mode'], 'local'); ?> /><span><strong><?php esc_html_e('Local grounded mode', 'geeky-bot'); ?></strong><em><?php esc_html_e('No external AI needed. Uses catalog and selected policy pages.', 'geeky-bot'); ?></em></span></label>
            <label class="gb2-radio <?php echo esc_attr($settings['provider_mode'] === 'zywrap' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="zywrap" <?php checked($settings['provider_mode'], 'zywrap'); ?> /><span><strong><?php esc_html_e('Zywrap endpoint', 'geeky-bot'); ?></strong><em><?php esc_html_e('Hosted AI endpoint for grounded answers.', 'geeky-bot'); ?></em></span></label>
            <label class="gb2-radio <?php echo esc_attr($settings['provider_mode'] === 'openai' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="openai" <?php checked($settings['provider_mode'], 'openai'); ?> /><span><strong><?php esc_html_e('OpenAI BYOK', 'geeky-bot'); ?></strong><em><?php esc_html_e('Optional bring-your-own-key grounded answer mode.', 'geeky-bot'); ?></em></span></label>
        </div>
        <div class="gb2-sgrid">
            <label class="gb2-sfield gb2-sfield--wide"><span><?php esc_html_e('Zywrap endpoint', 'geeky-bot'); ?></span><input id="zywrap_endpoint" name="zywrap_endpoint" type="url" value="<?php echo esc_attr($settings['zywrap_endpoint']); ?>" placeholder="https://api.example.com/..." /><em><?php esc_html_e('Only used when Zywrap mode is selected.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Zywrap API key', 'geeky-bot'); ?></span><input id="zywrap_api_key" name="zywrap_api_key" type="password" value="<?php echo esc_attr($settings['zywrap_api_key'] ? '••••••••' : ''); ?>" autocomplete="new-password" /><em><?php esc_html_e('Saved secret is not displayed after save.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('OpenAI API key', 'geeky-bot'); ?></span><input id="openai_api_key" name="openai_api_key" type="password" value="<?php echo esc_attr($settings['openai_api_key'] ? '••••••••' : ''); ?>" autocomplete="new-password" /><em><?php esc_html_e('Saved secret is not displayed after save.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('OpenAI model', 'geeky-bot'); ?></span><input id="openai_model" name="openai_model" type="text" value="<?php echo esc_attr($settings['openai_model']); ?>" /><em><?php esc_html_e('Used only in OpenAI BYOK mode.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('AI answer token limit', 'geeky-bot'); ?></span><input id="ai_max_tokens" name="ai_max_tokens" type="number" min="120" max="1200" value="<?php echo esc_attr(absint($settings['ai_max_tokens'])); ?>" /><em><?php esc_html_e('Keeps generated answers short and controlled.', 'geeky-bot'); ?></em></label>
        </div>
        <div class="gb2-snote"><strong><?php esc_html_e('Security posture', 'geeky-bot'); ?></strong><span><?php esc_html_e('API keys remain server-side. The storefront receives public widget settings and REST nonce only.', 'geeky-bot'); ?></span></div>
    <?php }

    private function settings_table_privacy($settings) { ?>
        <div class="gb2-sgrid">
            <label class="gb2-stoggle"><input type="hidden" name="chat_history_enabled" value="no" /><input type="checkbox" name="chat_history_enabled" value="yes" <?php checked($settings['chat_history_enabled'], 'yes'); ?> /> <span><strong><?php esc_html_e('Save conversation history', 'geeky-bot'); ?></strong><em><?php esc_html_e('Store shopper conversations for analytics and unanswered-question review.', 'geeky-bot'); ?></em></span></label>
            <label class="gb2-stoggle"><input type="hidden" name="allow_guest_sessions" value="no" /><input type="checkbox" name="allow_guest_sessions" value="yes" <?php checked($settings['allow_guest_sessions'], 'yes'); ?> /> <span><strong><?php esc_html_e('Save guest conversations', 'geeky-bot'); ?></strong><em><?php esc_html_e('When disabled, logged-out shoppers can still use Geeky Bot, but their server-side conversation history and click events are not stored.', 'geeky-bot'); ?></em></span></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Retention days', 'geeky-bot'); ?></span><input id="retention_days" name="retention_days" type="number" min="1" max="365" value="<?php echo esc_attr(absint($settings['retention_days'])); ?>" /><em><?php esc_html_e('How long conversation data is retained.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Rate limit shopper messages', 'geeky-bot'); ?></span><input id="rate_limit_messages" name="rate_limit_messages" type="number" min="20" max="1000" value="<?php echo esc_attr(absint($settings['rate_limit_messages'])); ?>" /><em><?php esc_html_e('Maximum public shopper messages per window.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Rate limit window minutes', 'geeky-bot'); ?></span><input id="rate_limit_window_minutes" name="rate_limit_window_minutes" type="number" min="1" max="60" value="<?php echo esc_attr(absint($settings['rate_limit_window_minutes'])); ?>" /><em><?php esc_html_e('Administrators, shop managers, and local development environments are not subject to public visitor rate limits.', 'geeky-bot'); ?></em></label>
            <label class="gb2-stoggle gb2-stoggle--danger"><input type="hidden" name="delete_data_on_uninstall" value="no" /><input type="checkbox" name="delete_data_on_uninstall" value="yes" <?php checked(isset($settings['delete_data_on_uninstall']) ? $settings['delete_data_on_uninstall'] : get_option('geekybot_delete_data_on_uninstall', 'no'), 'yes'); ?> /> <span><strong><?php esc_html_e('Delete data on uninstall', 'geeky-bot'); ?></strong><em><?php esc_html_e('Remove conversations, review queue, product index, and Commerce Pro data when the plugin is uninstalled. Keep disabled on live stores unless intentional.', 'geeky-bot'); ?></em></span></label>
        </div>
        <div class="gb2-snote"><strong><?php esc_html_e('Conversation data tools', 'geeky-bot'); ?></strong><span><?php esc_html_e('Export individual or complete conversation records, inspect product-click signals, or delete stored conversation data from Geeky Bot → Conversations.', 'geeky-bot'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Open conversation data tools', 'geeky-bot'); ?></a></span></div>
    <?php }

    private function setup_completion($wc_ready, $indexed_count, $policy_count, $provider_ready, $settings) {
        $demo_counts = (new GuidedDemoService())->counts();
        $privacy_ready = in_array($settings['chat_history_enabled'], array('yes', 'no'), true)
            && absint($settings['retention_days']) >= 1;
        $summary = $this->setup_readiness_summary(
            $wc_ready,
            $indexed_count,
            $policy_count,
            $provider_ready,
            $settings,
            $demo_counts,
            $privacy_ready
        );
        return absint($summary['score']);
    }

    private function setup_readiness_summary($wc_ready, $indexed_count, $policy_count, $provider_ready, $settings, $demo_counts, $privacy_ready) {
        $system_ready = $wc_ready && OnboardingService::system_ready();
        $steps = array(
            'system' => $system_ready ? 'ready' : ($wc_ready ? 'needs_attention' : 'blocked'),
            'provider' => $provider_ready ? 'ready' : 'optional',
            'index' => !$wc_ready ? 'blocked' : ($indexed_count > 0 ? 'ready' : 'needs_attention'),
            'knowledge' => $policy_count > 0 ? 'ready' : 'optional',
            'widget' => $settings['widget_enabled'] === 'yes' ? 'ready' : 'needs_attention',
            'privacy' => $privacy_ready ? 'ready' : 'needs_attention',
            'demo' => (!$wc_ready || $indexed_count < 1) ? 'blocked' : (absint($demo_counts['free'] ?? 0) >= 3 ? 'ready' : 'needs_attention'),
        );
        $weights = array(
            'system' => 25,
            'provider' => 5,
            'index' => 25,
            'knowledge' => 10,
            'widget' => 10,
            'privacy' => 10,
            'demo' => 15,
        );
        $score = 0.0;
        $counts = array('ready' => 0, 'blocked' => 0, 'optional' => 0, 'needs_attention' => 0);
        foreach ($steps as $key => $status) {
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if ($status === 'ready') {
                $score += $weights[$key];
            } elseif ($status === 'optional') {
                $score += $weights[$key] / 2;
            }
        }
        return array(
            'score' => min(100, absint(round($score))),
            'steps' => $steps,
            'counts' => $counts,
        );
    }

    private function woocommerce_setup_action() {
        $plugin_file = 'woocommerce/woocommerce.php';
        if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed = function_exists('get_plugins') && isset(get_plugins()[$plugin_file]);
        if ($installed && current_user_can('activate_plugins')) {
            return array(
                'label' => __('Activate WooCommerce', 'geeky-bot'),
                'url' => wp_nonce_url(
                    self_admin_url('plugins.php?action=activate&plugin=' . rawurlencode($plugin_file)),
                    'activate-plugin_' . $plugin_file
                ),
            );
        }
        return array(
            'label' => __('Install WooCommerce', 'geeky-bot'),
            'url' => self_admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'),
        );
    }

    private function product_index_status_label($context) {
        $status = isset($context['index_status']) ? (string) $context['index_status'] : 'current';
        if ($status === 'waiting_for_woocommerce') {
            return __('Waiting', 'geeky-bot');
        }
        if ($status === 'scheduled') {
            return __('Scheduled', 'geeky-bot');
        }
        if ($status === 'pending') {
            return __('Pending', 'geeky-bot');
        }

        return $this->compact_datetime_label(isset($context['last_rebuild']) ? $context['last_rebuild'] : '');
    }

    private function product_index_status_text($status) {
        if ($status === 'waiting_for_woocommerce') {
            return __('Waiting for WooCommerce', 'geeky-bot');
        }
        if ($status === 'scheduled') {
            return __('Full index scheduled', 'geeky-bot');
        }
        if ($status === 'pending') {
            return __('Full index pending', 'geeky-bot');
        }

        return __('Product changes sync automatically', 'geeky-bot');
    }

    private function product_index_status_title($context) {
        $status = isset($context['index_status']) ? (string) $context['index_status'] : 'current';
        $last_rebuild = !empty($context['last_rebuild']) ? (string) $context['last_rebuild'] : __('Never', 'geeky-bot');

        if ($status === 'waiting_for_woocommerce') {
            return __('Indexing starts automatically after WooCommerce is activated. Last full index:', 'geeky-bot') . ' ' . $last_rebuild;
        }
        if ($status === 'scheduled' || $status === 'pending') {
            return __('A full product index is queued. Last full index:', 'geeky-bot') . ' ' . $last_rebuild;
        }

        return __('Product changes sync automatically. Last full index:', 'geeky-bot') . ' ' . $last_rebuild;
    }

    private function compact_datetime_label($datetime) {
        $datetime = trim((string) $datetime);
        if ($datetime === '') {
            return __('Never', 'geeky-bot');
        }

        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return $datetime;
        }

        return date_i18n(get_option('date_format', 'M j, Y'), $timestamp);
    }

    private function admin_match_strength_label($score) {
        $score = (float) $score;
        if ($score >= 180) {
            return __('Strong match', 'geeky-bot');
        }
        if ($score >= 80) {
            return __('Good match', 'geeky-bot');
        }
        return __('Close match', 'geeky-bot');
    }

    private function setup_task_card($title, $status, $description, $url, $action, $number = '') {
        $status = sanitize_key((string) $status);
        if (!in_array($status, array('ready', 'needs_attention', 'blocked', 'optional'), true)) {
            $status = !empty($status) ? 'ready' : 'needs_attention';
        }
        $labels = array(
            'ready' => __('Ready', 'geeky-bot'),
            'needs_attention' => __('Not configured', 'geeky-bot'),
            'blocked' => __('Blocked', 'geeky-bot'),
            'optional' => __('Optional', 'geeky-bot'),
        );
        // Severity drives the left rail colour; a blocked step is the one the
        // merchant cannot work around, so it reads strongest.
        $severity = array(
            'ready' => 'done',
            'needs_attention' => 'high',
            'blocked' => 'critical',
            'optional' => 'medium',
        );
        $pill_state = array(
            'ready' => 'ok',
            'needs_attention' => 'warn',
            'blocked' => 'crit',
            'optional' => 'neutral',
        );

        $task = array(
            'title' => $number !== ''
                /* translators: 1: step number, 2: step title. */
                ? sprintf(__('%1$s. %2$s', 'geeky-bot'), $number, $title)
                : $title,
            'description' => $description,
            'severity' => $severity[$status],
        );

        $task['status'] = array('label' => $labels[$status], 'state' => $pill_state[$status]);

        if ($url !== '') {
            $task['action'] = array(
                'label' => $action,
                'url' => $url,
                'variant' => $status === 'blocked' ? 'primary' : 'default',
            );
        } else {
            // No URL means the step is waiting on a dependency. Say what it is
            // waiting for rather than rendering a dead button.
            $task['status'] = array('label' => $action, 'state' => 'neutral');
        }

        Components::task($task);
    }
    private function search_intent_debug($analysis) {
        if (empty($analysis) || !is_array($analysis)) {
            return;
        }

        $groups = array(
            __('Product terms', 'geeky-bot') => !empty($analysis['display_core_terms']) ? (array) $analysis['display_core_terms'] : (isset($analysis['core_terms']) ? (array) $analysis['core_terms'] : array()),
            __('Colors', 'geeky-bot') => isset($analysis['requested_color_labels']) ? (array) $analysis['requested_color_labels'] : array(),
            __('Sizes', 'geeky-bot') => isset($analysis['requested_size_labels']) ? (array) $analysis['requested_size_labels'] : array(),
            __('Soft preference', 'geeky-bot') => isset($analysis['modifier_labels']) ? (array) $analysis['modifier_labels'] : array(),
            __('Shopping mission', 'geeky-bot') => !empty($analysis['is_gift_request']) ? array(__('Gift discovery', 'geeky-bot')) : array(),
            __('Decision mode', 'geeky-bot') => !empty($analysis['decision_modes']) ? array_map(function ($mode) {
                return ucwords(str_replace('_', ' ', (string) $mode));
            }, (array) $analysis['decision_modes']) : array(),
            __('Audience preference', 'geeky-bot') => !empty($analysis['audience']['label']) ? array($analysis['audience']['label']) : array(),
        );

        if (!empty($analysis['price_range']) && is_array($analysis['price_range'])) {
            $range = $analysis['price_range'];
            $price_bits = array();
            if (isset($range['min']) && $range['min'] !== null) {
                $min_price = function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $range['min'])) : (string) $range['min'];
                $price_bits[] = sprintf(
                    /* translators: %s: minimum shopper price. */
                    __('Min %s', 'geeky-bot'),
                    $min_price
                );
            }
            if (isset($range['max']) && $range['max'] !== null) {
                $max_price = function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $range['max'])) : (string) $range['max'];
                $price_bits[] = sprintf(
                    /* translators: %s: maximum shopper price. */
                    __('Max %s', 'geeky-bot'),
                    $max_price
                );
            }
            if (!empty($price_bits)) {
                $groups[__('Price', 'geeky-bot')] = $price_bits;
            }
        }
        ?>
        <div class="gb2-debug">
            <strong><?php esc_html_e('What Geeky Bot understood', 'geeky-bot'); ?></strong>
            <div class="gb2-debug__grid">
                <span><b><?php esc_html_e('Intent', 'geeky-bot'); ?></b><?php echo esc_html(!empty($analysis['intent']) ? $analysis['intent'] : 'search'); ?></span>
                <?php foreach ($groups as $label => $values) : $values = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $values)))); ?>
                    <?php if (!empty($values)) : ?>
                        <span><b><?php echo esc_html($label); ?></b><?php echo esc_html(implode(', ', array_slice($values, 0, 6))); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php $synonym_expansions = $this->search_synonym_expansion_debug($analysis); ?>
            <?php if (!empty($synonym_expansions)) : ?>
                <div class="gb2-chips" style="margin-top:8px">
                    <?php foreach (array_slice($synonym_expansions, 0, 5) as $expansion) : ?><span><?php echo esc_html($expansion); ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function admin_product_match_debug($product, $analysis, $search_debug) {
        $product_id = isset($product['id']) ? absint($product['id']) : 0;
        $debug = $product_id && isset($search_debug[$product_id]) && is_array($search_debug[$product_id]) ? $search_debug[$product_id] : array();
        $score = isset($debug['score']) ? (float) $debug['score'] : null;
        $reasons = !empty($debug['reasons']) && is_array($debug['reasons']) ? $debug['reasons'] : array();

        if (empty($reasons) && !empty($product['searchMatch']) && is_array($product['searchMatch'])) {
            $match = $product['searchMatch'];
            if (!empty($match['matched'])) {
                foreach ((array) $match['matched'] as $item) {
                    $reasons[] = sprintf(
                        /* translators: %s: matched product-search signal. */
                        __('Matched: %s', 'geeky-bot'),
                        wp_strip_all_tags((string) $item)
                    );
                }
            }
            if (!empty($match['notConfirmed'])) {
                foreach ((array) $match['notConfirmed'] as $item) {
                    $reasons[] = sprintf(
                        /* translators: %s: unconfirmed product-search signal. */
                        __('Not confirmed: %s', 'geeky-bot'),
                        wp_strip_all_tags((string) $item)
                    );
                }
            }
        }

        $reasons = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $reasons))));
        if ($score === null && empty($reasons)) {
            return;
        }
        ?>
        <div class="gb2-debug gb2-debug--inline">
            <strong><?php esc_html_e('Why this product matched', 'geeky-bot'); ?></strong>
            <div class="gb2-chips">
                <?php if ($score !== null) : ?><span class="gb2-pill" title="<?php echo esc_attr(sprintf(
                    /* translators: %s: raw product-search match score. */
                    __('Raw score: %s', 'geeky-bot'),
                    number_format_i18n($score, 1)
                )); ?>"><?php echo esc_html($this->admin_match_strength_label($score)); ?></span><?php endif; ?>
                <?php foreach (array_slice($reasons, 0, 7) as $reason) : ?><span><?php echo esc_html($reason); ?></span><?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function search_synonym_expansion_debug($analysis) {
        if (empty($analysis) || !is_array($analysis)) {
            return array();
        }

        $query = !empty($analysis['lower']) ? (string) $analysis['lower'] : (!empty($analysis['raw']) ? (string) $analysis['raw'] : '');
        $query = strtolower($query);
        $map = array(
            'comfy' => array('comfortable', 'soft', 'cushioned', 'walking'),
            'comfortable' => array('soft', 'cushioned', 'walking'),
            'not expensive' => array('budget', 'affordable', 'low price'),
            'not too expensive' => array('budget', 'affordable', 'low price'),
            'cheap' => array('budget', 'affordable', 'low price'),
            'trainers' => array('sneaker', 'shoe'),
            'trainer' => array('sneaker', 'shoe'),
            'sneakers' => array('trainer', 'shoe'),
            'sneaker' => array('trainer', 'shoe'),
            'footwear' => array('shoe', 'sneaker', 'trainer'),
            'cream' => array('beige', 'off white'),
            'hoodies' => array('hoodie'),
            'shirts' => array('shirt'),
            'belts' => array('belt'),
        );

        $custom = Settings::get('search_custom_synonyms', '');
        foreach (preg_split('/\r\n|\r|\n/', (string) $custom) as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = preg_split('/\s*(?:=>|=|:)\s*/', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $word = strtolower(trim($parts[0]));
            $alts = array();
            foreach (preg_split('/\s*[,|]\s*/', (string) $parts[1]) as $alt) {
                $alt = strtolower(trim($alt));
                if ($alt !== '') {
                    $alts[] = $alt;
                }
            }
            if ($word !== '' && !empty($alts)) {
                $map[$word] = array_values(array_unique($alts));
            }
        }

        $expansions = array();
        foreach ($map as $word => $alts) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }
            if (preg_match('/(?<![\pL\pN])' . preg_quote($word, '/') . '(?![\pL\pN])/u', $query)) {
                $expansions[] = $word . ' → ' . implode(', ', array_slice(array_values(array_unique($alts)), 0, 6));
            }
        }

        if (empty($expansions) && !empty($analysis['expanded']) && !empty($analysis['searchable'])) {
            $base = array_fill_keys(preg_split('/\s+/', strtolower((string) $analysis['searchable'])), true);
            $added = array();
            foreach (preg_split('/\s+/', strtolower((string) $analysis['expanded'])) as $term) {
                $term = trim($term);
                if ($term !== '' && empty($base[$term]) && strlen($term) > 2) {
                    $added[] = $term;
                }
            }
            $added = array_values(array_unique($added));
            if (!empty($added)) {
                $expansions[] = __('Added search terms: ', 'geeky-bot') . implode(', ', array_slice($added, 0, 8));
            }
        }

        return array_values(array_unique($expansions));
    }

    private function analytics_bucket_card($title, $count, $description) { ?>
        <div class="gb2-card gb2-scard <?php echo esc_attr($count > 0 ? 'has-gaps' : 'is-clear'); ?>">
            <strong><?php echo esc_html(number_format_i18n(absint($count))); ?></strong>
            <span><?php echo esc_html($title); ?></span>
            <em><?php echo esc_html($description); ?></em>
        </div>
    <?php }

    private function admin_link($page, $label) { ?><a href="<?php echo esc_url(admin_url('admin.php?page=' . $page)); ?>"><?php echo esc_html($label); ?></a><?php }
    private function branding_image_picker($name, $value, $label, $settings_field = false) {
        $value = absint($value);
        $url = $value ? wp_get_attachment_image_url($value, 'thumbnail') : '';
        $class = $settings_field ? 'gb2-sfield gb-media-field' : 'gb-media-field';
        $class .= $url ? ' has-image' : ' is-empty';
        ?>
        <label class="<?php echo esc_attr($class); ?>">
            <span><?php echo esc_html($label); ?></span>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" data-gb-media-id />
            <span class="gb-media-field__row">
                <span class="gb-media-field__preview <?php echo esc_attr($url ? 'has-image' : ''); ?>" data-gb-media-preview>
                    <?php if ($url) : ?>
                        <img src="<?php echo esc_url($url); ?>" alt="" />
                    <?php else : ?>
                        <span class="gb-media-field__empty"><?php esc_html_e('No image selected', 'geeky-bot'); ?></span>
                    <?php endif; ?>
                </span>
                <span class="gb-media-field__actions">
                    <button type="button" class="button" data-gb-media-select><?php esc_html_e('Choose image', 'geeky-bot'); ?></button>
                    <button type="button" class="button button-link-delete" data-gb-media-remove <?php disabled(!$url); ?>><?php esc_html_e('Remove', 'geeky-bot'); ?></button>
                </span>
            </span>
            <em><?php esc_html_e('Uses a Media Library image. PNG, JPG, WEBP, or GIF recommended.', 'geeky-bot'); ?></em>
        </label>
        <?php
    }

    /**
     * Storefront widget preview.
     *
     * This renders the *real* widget: the same class names, the same structure
     * and the same stylesheet the storefront loads. Before 2.0.2 the preview was
     * a separate hand-built imitation with its own markup and its own CSS, and
     * the two had already drifted — the preview drew a 22px window with a navy
     * gradient while shoppers saw a 24px window with a slate one. A merchant
     * choosing "Gradient" was previewing a colour their customers never saw.
     *
     * Because the preview and the widget are now the same thing, any future
     * change to frontend.css appears in both automatically and the drift cannot
     * come back.
     *
     * The widget root keeps its real id, which is safe here because the live
     * widget never renders on an admin screen. Open and closed are the real
     * `gb-widget--open` state, not two separate mock-ups.
     *
     * @param string $default_state closed|open.
     * @return void
     */
    private function widget_preview($settings, $default_state = 'closed') {
        $public = Settings::public_settings();
        $header_logo = !empty($public['headerLogoUrl']) ? $public['headerLogoUrl'] : '';
        $launcher_icon = !empty($public['launcherIconUrl']) ? $public['launcherIconUrl'] : '';
        $launcher_style = isset($settings['launcher_style']) && $settings['launcher_style'] === 'pill' ? 'pill' : 'icon';
        $preview_state = $default_state === 'open' ? 'open' : 'closed';
        $position = isset($settings['button_position']) && $settings['button_position'] === 'left' ? 'left' : 'right';
        $header_style = isset($settings['header_style']) && $settings['header_style'] === 'solid' ? 'solid' : 'gradient';
        $invitation_on = isset($settings['shopper_invitation_enabled']) && $settings['shopper_invitation_enabled'] === 'yes';

        $color_mode = isset($settings['widget_color_mode']) && in_array($settings['widget_color_mode'], array('light', 'dark', 'auto'), true)
            ? $settings['widget_color_mode']
            : 'light';
        $launcher_shape = isset($settings['launcher_shape']) && $settings['launcher_shape'] === 'rounded' ? 'rounded' : 'round';

        $root_classes = array(
            'gb-widget--' . $position,
            'gb-widget--preview',
            'gb-widget--launcher-' . $launcher_style,
            'gb-widget--header-' . $header_style,
            'gb-widget--launcher-shape-' . $launcher_shape,
            'gb-widget--' . $color_mode,
        );
        if ($preview_state === 'open') {
            $root_classes[] = 'gb-widget--open';
        }
        ?>
        <div class="gb-widget-preview-stack" data-gb-preview-root data-gb-preview-state="<?php echo esc_attr($preview_state); ?>">
            <div class="gb-widget-preview-switch" role="group" aria-label="<?php esc_attr_e('Preview display', 'geeky-bot'); ?>">
                <button type="button" data-gb-preview-state-button="closed" aria-pressed="<?php echo $preview_state === 'closed' ? 'true' : 'false'; ?>" class="<?php echo $preview_state === 'closed' ? 'is-active' : ''; ?>"><?php esc_html_e('Invitation & launcher', 'geeky-bot'); ?></button>
                <button type="button" data-gb-preview-state-button="open" aria-pressed="<?php echo $preview_state === 'open' ? 'true' : 'false'; ?>" class="<?php echo $preview_state === 'open' ? 'is-active' : ''; ?>"><?php esc_html_e('Open chat', 'geeky-bot'); ?></button>
            </div>

            <div class="gb-widget-preview-stage">
                <?php // Real widget root, real classes, real stylesheet. ?>
                <div id="geekybot-sales-assistant" class="<?php echo esc_attr(implode(' ', $root_classes)); ?>" data-gb-preview-widget style="--gb-accent: <?php echo esc_attr($settings['accent_color']); ?>">

                    <div class="gb-shopper-invitation is-visible" data-gb-preview-invitation<?php echo $invitation_on ? '' : ' hidden'; ?>>
                        <span class="gb-shopper-invitation__text" data-gb-preview-invitation-message><?php echo esc_html($settings['shopper_invitation_message']); ?></span>
                        <span class="gb-shopper-invitation__close" aria-hidden="true">&times;</span>
                    </div>

                    <button type="button" class="gb-launcher" data-gb-preview-launcher aria-hidden="true" tabindex="-1">
                        <span class="gb-launcher__icon" data-gb-preview-launcher-mark>
                            <?php if ($launcher_icon) : ?><img src="<?php echo esc_url($launcher_icon); ?>" alt="" /><?php else : ?><?php Components::brand_mark(); ?><?php endif; ?>
                        </span>
                        <span class="gb-launcher__text" data-gb-preview-launcher-text><?php echo esc_html($settings['launcher_text']); ?></span>
                    </button>

                    <div class="gb-window">
                        <div class="gb-window__header">
                            <span class="gb-window__brand">
                                <span class="gb-window__brand-logo" data-gb-preview-header-logo>
                                    <?php if ($header_logo) : ?><img src="<?php echo esc_url($header_logo); ?>" alt="" /><?php else : ?><?php Components::brand_mark(); ?><?php endif; ?>
                                </span>
                                <span>
                                    <strong data-gb-preview-name><?php echo esc_html($settings['assistant_name']); ?></strong>
                                    <span data-gb-preview-subtitle><?php echo esc_html($settings['assistant_subtitle']); ?></span>
                                </span>
                            </span>
                        </div>

                        <div class="gb-messages">
                            <div class="gb-message gb-message--bot">
                                <span class="gb-message__text" data-gb-preview-welcome><?php echo esc_html($settings['welcome_message']); ?></span>
                            </div>
                            <div class="gb-suggestion-chips">
                                <span class="gb-suggestion-chip"><?php esc_html_e('Latest products', 'geeky-bot'); ?></span>
                                <span class="gb-suggestion-chip"><?php esc_html_e('Sale products', 'geeky-bot'); ?></span>
                                <span class="gb-suggestion-chip"><?php esc_html_e('Top rated', 'geeky-bot'); ?></span>
                            </div>
                            <div class="gb-product-card">
                                <span class="gb-product-card__image"></span>
                                <div class="gb-product-card__body">
                                    <span class="gb-product-card__title"><?php esc_html_e('Blue hoodie', 'geeky-bot'); ?></span>
                                    <div class="gb-product-card__price">$45.00</div>
                                    <span class="gb-product-card__stock gb-stock--instock"><?php esc_html_e('In stock', 'geeky-bot'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="gb-form">
                            <span class="gb-input"><?php esc_html_e('Ask about products, size, colour, price…', 'geeky-bot'); ?></span>
                            <span class="gb-send"><?php esc_html_e('Send', 'geeky-bot'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function menu_icon_data_uri() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><g transform="translate(0,8)"><path d="M20 15 C21 22 23 24 30 25 C23 26 21 28 20 35 C19 28 17 26 10 25 C17 24 19 22 20 15 Z" fill="#fff"/><path d="M55 20 H73 Q88 20 88 35 V45 Q88 60 73 60 L70 60 L73 72 L62 60 H55 Q40 60 40 45 V35 Q40 20 55 20 Z" fill="none" stroke="#fff" stroke-width="5.5" stroke-linejoin="round"/><line x1="66" y1="20" x2="66" y2="13" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/><circle cx="66" cy="10" r="3" fill="#fff"/><rect x="51" y="31" width="26" height="16" rx="7" fill="#fff"/><ellipse cx="59" cy="39" rx="2.3" ry="3" fill="#6d28d9"/><ellipse cx="69" cy="39" rx="2.3" ry="3" fill="#6d28d9"/><circle cx="58" cy="53" r="1.7" fill="#fff"/><circle cx="64" cy="53" r="1.7" fill="#fff"/><circle cx="70" cy="53" r="1.7" fill="#fff"/><path d="M20 46 L48 46 L44 60 L27 60 Z" fill="none" stroke="#fff" stroke-width="4.5" stroke-linejoin="round"/><path d="M20 46 L15 40 L11 40" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="29" y1="60" x2="29" y2="63" stroke="#fff" stroke-width="4"/><line x1="42" y1="60" x2="42" y2="63" stroke="#fff" stroke-width="4"/><circle cx="29" cy="66" r="3" fill="#fff"/><circle cx="42" cy="66" r="3" fill="#fff"/></g></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    // Public so Components::page_header() can render the real mark rather than
    // Kept as a thin delegate so existing callers keep working; the artwork
    // itself now lives in Components so both plugins share one copy.
    public function brand_mark_svg() {
        Components::brand_mark();
    }

    private function suggested_policy_pages($pages) { $matches = array(); foreach ((array) $pages as $page) { $title = strtolower((string) $page->post_title); if (preg_match('/shipping|return|refund|privacy|terms|warranty|policy|delivery|payment/', $title)) { $matches[] = $page; } } return $matches; }
    private function page_list($pages, $selected, $only_selected, $classifications = array()) {
        $shown = 0;
        echo '<ul class="gb2-pagelist">';
        foreach ((array) $pages as $page) {
            $is_selected = in_array(absint($page->ID), $selected, true);
            if ($only_selected && !$is_selected) {
                continue;
            }
            $shown++;
            $types = !empty($classifications[$page->ID]) ? $classifications[$page->ID] : array('general');
            $state = $is_selected ? __('Selected', 'geeky-bot') : __('Suggested', 'geeky-bot');
            echo '<li><span>' . esc_html($page->post_title) . '</span><em>' . esc_html($state . ' — ' . $this->policy_type_summary($types)) . '</em></li>';
        }
        if ($shown === 0) {
            echo '<li><span>' . esc_html($only_selected ? __('No pages selected yet.', 'geeky-bot') : __('No obvious policy pages found.', 'geeky-bot')) . '</span></li>';
        }
        echo '</ul>';
    }

    private function policy_page_classifications($pages) {
        $service = new \GeekyBot\Services\PolicyIntentService();
        $classifications = array();
        foreach ((array) $pages as $page) {
            $content = wp_strip_all_tags(strip_shortcodes((string) $page->post_content), true);
            $classifications[absint($page->ID)] = $service->document_types((string) $page->post_title, $content);
        }

        return $classifications;
    }

    private function policy_type_summary($types) {
        $labels = array(
            'shipping' => __('Shipping', 'geeky-bot'),
            'returns' => __('Returns', 'geeky-bot'),
            'refunds' => __('Refunds', 'geeky-bot'),
            'exchanges' => __('Exchanges', 'geeky-bot'),
            'warranty' => __('Warranty', 'geeky-bot'),
            'cancellation' => __('Cancellation', 'geeky-bot'),
            'payment' => __('Payment', 'geeky-bot'),
            'privacy' => __('Privacy', 'geeky-bot'),
            'terms' => __('Terms', 'geeky-bot'),
        );
        $found = array();
        foreach ((array) $types as $type) {
            $type = sanitize_key((string) $type);
            if (isset($labels[$type])) {
                $found[] = $labels[$type];
            }
        }

        return !empty($found)
            ? implode(', ', array_values(array_unique($found)))
            : __('Not recognized as a policy source', 'geeky-bot');
    }

    private function stats() {
        global $wpdb;

        $sessions = $wpdb->prefix . 'geekybot_sessions';
        $messages = $wpdb->prefix . 'geekybot_messages';
        $unanswered = $wpdb->prefix . 'geekybot_unanswered';
        $unanswered_count = 0;
        $review_needed = 0;
        $handled_now = 0;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard counters must reflect current custom-table data.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- These are fixed plugin-owned table names derived from $wpdb->prefix.
        if ($this->table_exists($unanswered)) {
            $questions = $wpdb->get_col("SELECT question FROM {$unanswered}");
            $unanswered_count = count($questions);
            foreach ($questions as $question) {
                if ($this->is_now_handled_question($question)) {
                    $handled_now++;
                } else {
                    $review_needed++;
                }
            }
        }

        $stats = array(
            'sessions' => $this->table_exists($sessions) ? absint($wpdb->get_var("SELECT COUNT(*) FROM {$sessions}")) : 0,
            'messages' => $this->table_exists($messages) ? absint($wpdb->get_var("SELECT COUNT(*) FROM {$messages}")) : 0,
            'unanswered' => $unanswered_count,
            'review_needed' => $review_needed,
            'handled_now' => $handled_now,
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $stats;
    }
    private function is_now_handled_question($question) { $query = strtolower(trim((string) $question)); $handled_patterns = array('cart', 'show my cart', 'view my cart', 'what is in my cart', 'how my cart', 'remove all items', 'my orders', 'recent orders', 'order history', 'track my order', 'where is my order', 'checkout', 'hoodie xl', 'discounted hoodie', 'hoodies on sale', 'looking for belts', 'belt under', 'blue shirt on sale'); foreach ($handled_patterns as $pattern) { if (strpos($query, $pattern) !== false) { return true; } } return false; }
    private function review_hint_for_question($question, $reason) {
        $query = strtolower((string) $question);
        $reason_text = strtolower((string) $reason);
        if (preg_match('/order|parcel|track|purchase|bought|buy last|checkout|cart|compare|coupon|promo|discount|human|complain|complaint/', $query)) {
            return __('Retest this phrase in the storefront widget. If it still fails, tune the Commerce Pro action wording or add a sales rule.', 'geeky-bot');
        }
        if (preg_match('/refund|return|shipping|delivery|warranty|payment|privacy|terms|policy/', $query)) {
            return __('Select or improve the matching public policy page, then test the phrase again.', 'geeky-bot');
        }
        if (preg_match('/comfortable|comfy|cheap|budget|not expensive|trainer|sneaker|walking|similar|like this/', $query)) {
            return __('Add or improve synonyms so shopper wording maps to catalog words, then run the phrase in Search Lab.', 'geeky-bot');
        }
        if (preg_match('/size|color|colour|under|sale|stock|hoodie|shirt|shoe|belt|product|products|show products/', $query)) {
            return __('Check product titles, categories, tags, price, stock and attributes, then rebuild the product index.', 'geeky-bot');
        }
        if (strpos($reason_text, 'policy') !== false) {
            return __('Check whether this is a product, policy, synonym, or Commerce Pro wording issue before marking it handled.', 'geeky-bot');
        }
        return __('Review catalog terms, synonyms, policy pages or buying-action rules, then retest the shopper phrase.', 'geeky-bot');
    }
    private function admin_notice_review_action() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- This sanitized value only renders a review result notice.
        if (empty($_GET['gb_review_notice'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['gb_review_notice']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $message = __('Review queue updated.', 'geeky-bot');
        if ($notice === 'handled') {
            $message = __('Review issue marked as handled.', 'geeky-bot');
        } elseif ($notice === 'ignored') {
            $message = __('Review issue ignored.', 'geeky-bot');
        } elseif ($notice === 'restored') {
            $message = __('Review issue restored to review.', 'geeky-bot');
        }
        ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php
    }

    private function review_hash($question, $reason = '') {
        return md5(ConversationInsightsService::normalize_review_question($question));
    }

    private function legacy_review_hash($question, $reason = '') {
        return md5(strtolower(trim((string) $question)) . '|' . strtolower(trim((string) $reason)));
    }

    private function review_bucket_for_question($question, $reason = '') {
        $question_text = strtolower((string) $question);
        $reason_text = strtolower((string) $reason);

        if (preg_match('/cart|checkout|order|parcel|track|compare|coupon|discount|promo|human|agent|support|complain|complaint|purchase|bought|buy last/i', $question_text)) {
            return 'commerce';
        }
        if (preg_match('/refund|return|shipping|delivery|warranty|payment|privacy|terms|policy/i', $question_text)) {
            return 'policy';
        }
        if (preg_match('/similar|like this|comfy|comfortable|cheap|budget|affordable|trainer|sneaker|word|phrase|synonym|not expensive|walking/i', $question_text)) {
            return 'synonym';
        }
        if (preg_match('/size|color|colour|under|sale|stock|product|products|hoodie|shirt|shoe|belt|beanie|cap|sku|category|tag|attribute/i', $question_text)) {
            return 'product';
        }
        if (preg_match('/refund|return|shipping|delivery|warranty|payment|privacy|terms/i', $reason_text)) {
            return 'policy';
        }
        return 'product';
    }

    private function review_bucket_label($bucket) {
        $labels = array(
            'product' => __('Catalog data', 'geeky-bot'),
            'policy' => __('Policy answer', 'geeky-bot'),
            'synonym' => __('Synonym / wording', 'geeky-bot'),
            'commerce' => __('Commerce action', 'geeky-bot'),
        );
        return isset($labels[$bucket]) ? $labels[$bucket] : $labels['product'];
    }

    private function review_bucket_card($title, $count, $description, $bucket) { ?>
        <div class="gb2-col-3">
            <?php Components::card_open('', '', false, 'gb2-fill'); ?>
                <span class="gb2-metric__label"><?php echo esc_html($title); ?></span>
                <span class="gb2-metric__value" style="margin-top:2px"><?php
                    echo esc_html(number_format_i18n(absint($count))); ?></span>
                <span class="gb2-metric__base"><?php echo esc_html($description); ?></span>
            <?php Components::card_close(); ?>
        </div>
    <?php }


    private function table_exists($table) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live schema check for a fixed plugin table.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $exists === $table;
    }
    private function provider_label($settings) { if ($settings['provider_mode'] === 'zywrap') { return Settings::has_secret('zywrap_api_key') && !empty($settings['zywrap_endpoint']) ? __('Zywrap enabled', 'geeky-bot') : __('Zywrap selected but not fully configured', 'geeky-bot'); } if ($settings['provider_mode'] === 'openai') { return Settings::has_secret('openai_api_key') ? __('OpenAI enabled', 'geeky-bot') : __('OpenAI selected but API key missing', 'geeky-bot'); } return __('Local grounded mode', 'geeky-bot'); }
}
