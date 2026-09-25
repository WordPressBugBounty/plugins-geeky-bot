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
use GeekyBot\Services\AiBudgetService;
use GeekyBot\Services\SmartCatalogService;
use GeekyBot\Services\SearchRescueService;
use GeekyBot\Services\SearchLearningService;

class Menu {
    public function hooks() {
        add_action('admin_menu', array($this, 'menu'));
        // After Commerce Pro, which the add-on registers at priority 99.
        add_action('admin_menu', array($this, 'menu_license'), 100);
        add_action('admin_init', array($this, 'handle_save'));
        add_action('admin_post_geekybot_rebuild_product_index', array($this, 'handle_rebuild_product_index'));
        add_action('admin_post_geekybot_smart_catalog_action', array($this, 'handle_smart_catalog_action'));
        add_action('admin_post_geekybot_search_learning_action', array($this, 'handle_search_learning_action'));
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
        /*
         * 2.1.1 admin structure: every setting lives on exactly one page.
         * Settings used to repeat eight widget fields, eight search fields and
         * the policy-page picker, and "Answer Mode" explained a switch that sat
         * on Settings. Settings is now "AI & Privacy" (answer mode, provider
         * keys, AI limits, data), and the Answer Mode URL redirects there.
         */
        add_submenu_page('geekybot', __('Dashboard', 'geeky-bot'), __('Dashboard', 'geeky-bot'), 'manage_options', 'geekybot', array($this, 'dashboard'));
        add_submenu_page('geekybot', __('Setup', 'geeky-bot'), __('Setup', 'geeky-bot'), 'manage_options', 'geekybot-setup', array($this, 'setup_wizard'));
        add_submenu_page('geekybot', __('Conversations', 'geeky-bot'), __('Conversations', 'geeky-bot'), 'manage_options', 'geekybot-conversations', array($this, 'conversations'));
        add_submenu_page('geekybot', __('Analytics', 'geeky-bot'), __('Analytics', 'geeky-bot'), 'manage_options', 'geekybot-analytics', array($this, 'analytics'));
        add_submenu_page('geekybot', __('Product Search', 'geeky-bot'), __('Product Search', 'geeky-bot'), 'manage_options', 'geekybot-product-assistant', array($this, 'product_assistant'));
        add_submenu_page('geekybot', __('Store Knowledge', 'geeky-bot'), __('Store Knowledge', 'geeky-bot'), 'manage_options', 'geekybot-store-knowledge', array($this, 'store_knowledge'));
        add_submenu_page('geekybot', __('Storefront Widget', 'geeky-bot'), __('Storefront Widget', 'geeky-bot'), 'manage_options', 'geekybot-widget', array($this, 'chat_widget'));
        add_submenu_page('geekybot', __('AI & Privacy', 'geeky-bot'), __('AI & Privacy', 'geeky-bot'), 'manage_options', 'geekybot-settings', array($this, 'settings'));
        add_submenu_page('geekybot', __('Guided Demo', 'geeky-bot'), __('Guided Demo', 'geeky-bot'), 'manage_options', 'geekybot-guided-demo', array($this, 'guided_demo'));
        if (!defined('GBCP_VERSION')) {
            add_submenu_page('geekybot', __('Commerce Pro', 'geeky-bot'), __('Commerce Pro', 'geeky-bot'), 'manage_options', 'geekybot-commerce-pro', array($this, 'commerce_pro_promo'));
        }
    }

    /**
     * License sits last, after Commerce Pro, which the add-on re-registers on
     * the default priority. It is plumbing, not a daily page.
     *
     * @return void
     */
    public function menu_license() {
        add_submenu_page('geekybot', __('License', 'geeky-bot'), __('License', 'geeky-bot'), 'manage_options', 'geekybot-addons', array($this, 'pro'));

        // Answer Mode merged into AI & Privacy. The slug stays registered but
        // hidden (WordPress refuses unregistered pages before any hook could
        // redirect), and its load hook sends old links and bookmarks on.
        $hook = add_submenu_page('', __('Answer Mode', 'geeky-bot'), '', 'manage_options', 'geekybot-integrations', '__return_null');
        if ($hook) {
            add_action('load-' . $hook, array($this, 'redirect_retired_pages'));
        }
    }

    /**
     * @return void
     */
    public function redirect_retired_pages() {
        wp_safe_redirect(admin_url('admin.php?page=geekybot-settings#gb-settings-ai'));
        exit;
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
        // The live preview fills an empty field with the same text the widget
        // would fall back to, so it takes those from Settings instead of
        // repeating them: they are translated there, and an admin working in
        // German should not be shown an English preview of their own store.
        $defaults = Settings::defaults();

        wp_localize_script('geekybot-admin', 'GeekyBotAdmin', array(
            'mediaTitle' => __('Choose widget image', 'geeky-bot'),
            'mediaButton' => __('Use this image', 'geeky-bot'),
            'removeImage' => __('Remove image', 'geeky-bot'),
            'noImage' => __('No image selected', 'geeky-bot'),
            'copyDemo' => __('Copy', 'geeky-bot'),
            'copiedDemo' => __('Copied', 'geeky-bot'),
            'previewDefaults' => array(
                'assistantName' => $defaults['assistant_name'],
                'assistantSubtitle' => $defaults['assistant_subtitle'],
                'welcomeMessage' => $defaults['welcome_message'],
                'launcherText' => $defaults['launcher_text'],
                'invitationMessage' => $defaults['shopper_invitation_message'],
            ),
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

        // A provider key that could not be encrypted is not saved. Say so,
        // rather than letting the merchant believe a key is in place.
        $secret_errors = Settings::last_secret_errors();
        if (!empty($secret_errors)) {
            $search_notice = 'secret_refused';
        }

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

    /**
     * Wall-clock budget for rebuild batches run inside the admin request.
     *
     * Long enough that an ordinary catalog finishes before the redirect, short
     * enough that a large one hands off to cron instead of timing out.
     */
    const INLINE_REBUILD_SECONDS = 10;

    /**
     * Memory level at which inline rebuild batches stop, or 0 when the process
     * has no limit.
     *
     * Each batch retains roughly 4-5MB of primed post, meta and term caches,
     * so a long inline run climbs steadily. Stopping at 60% of the limit
     * leaves room for the rest of the admin page to render.
     *
     * @return int Bytes, or 0 for no ceiling.
     */
    private static function inline_rebuild_memory_ceiling() {
        $limit = function_exists('wp_convert_hr_to_bytes')
            ? wp_convert_hr_to_bytes((string) ini_get('memory_limit'))
            : 0;

        return $limit > 0 ? (int) ($limit * 0.6) : 0;
    }

    public function handle_rebuild_product_index() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to rebuild the product index.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_rebuild_product_index');

        // The rebuild populates a shadow table and swaps it in atomically, so
        // shoppers keep searching the current index throughout. A few batches
        // run inline, which finishes an ordinary catalog before the redirect;
        // anything larger is handed to the batch cron and reports progress.
        $index = new ProductIndexService();
        $index->start_batched_rebuild();

        $complete = false;
        $deadline = microtime(true) + self::INLINE_REBUILD_SECONDS;
        $memory_ceiling = self::inline_rebuild_memory_ceiling();

        for ($i = 0; $i < 25; $i++) {
            $batch = $index->rebuild_batch();
            if (!empty($batch['complete'])) {
                $complete = true;
                break;
            }
            if (empty($batch['running'])) {
                break;
            }

            // A batch count alone does not bound this request. Measured on a
            // 20k-product catalog, 25 batches took 175s and 211MB -- past the
            // usual max_execution_time and close enough to a 256M limit to
            // fatal once the rest of wp-admin is loaded. Stop on whichever
            // budget runs out first and let the batch cron finish the job:
            // the shadow table and the saved cursor mean handing off costs
            // nothing, and the admin screen already reports live progress.
            if (microtime(true) >= $deadline) {
                break;
            }
            if ($memory_ceiling > 0 && memory_get_usage(true) >= $memory_ceiling) {
                break;
            }
        }

        $state = ProductIndexService::rebuild_state();
        $args = array('page' => 'geekybot-product-assistant');
        if ($complete) {
            $args['gb_indexed'] = absint(is_array($state) ? $state['indexed'] : 0);
            $args['gb_skipped'] = absint(is_array($state) ? $state['skipped'] : 0);
        } else {
            $args['gb_index_queued'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
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
        if (is_wp_error($result)) {
            $this->redirect_license_result($result, 'activated');
        }

        // "I entered the key -- what now?" The next steps are always the same,
        // so carry on with them instead of leaving the merchant to find two
        // more buttons: install the add-on if it is missing, then switch it on.
        // Each step needs its own WordPress capability; without one, the page
        // shows that step as the next thing to do.
        $plugin = LicenseService::commerce_pro_plugin_status();
        $license = LicenseService::status_summary();
        $notice = 'activated';
        if (!$plugin['installed'] && current_user_can('install_plugins') && !empty($license['downloadsAllowed'])) {
            $installed = LicenseService::install_commerce_pro();
            if (is_wp_error($installed)) {
                $this->redirect_license_result(new \WP_Error(
                    'geekybot_install_after_activation',
                    sprintf(
                        /* translators: %s: installer error message. */
                        __('Your license is active, but Commerce Pro could not be installed: %s', 'geeky-bot'),
                        $installed->get_error_message()
                    )
                ), 'activated');
            }
            $notice = LicenseService::commerce_pro_plugin_status()['active'] ? 'ready' : 'installed';
        } elseif ($plugin['installed'] && !$plugin['active'] && current_user_can('activate_plugins')) {
            $switched_on = LicenseService::activate_commerce_pro_plugin();
            $notice = is_wp_error($switched_on) ? 'activated' : 'ready';
        } elseif ($plugin['active']) {
            $notice = 'ready';
        }

        $this->redirect_license_result(true, $notice);
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
        // Installing also switches the add-on on when the user may activate plugins.
        $this->redirect_license_result($result, !is_wp_error($result) && LicenseService::commerce_pro_plugin_status()['active'] ? 'ready' : 'installed');
    }

    public function handle_activate_commerce_pro() {
        if (!current_user_can('activate_plugins')) {
            wp_die(esc_html__('You do not have permission to activate plugins.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_license_action');
        $result = LicenseService::activate_commerce_pro_plugin();
        $this->redirect_license_result($result, 'ready');
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
            $level_names = array(
                'standard' => __('Standard search', 'geeky-bot'),
                'catalog' => __('Smart Catalog', 'geeky-bot'),
                'rescue' => __('Smart Catalog + Rescue', 'geeky-bot'),
            );
            $search_level = isset($settings['search_ai_level'], $level_names[$settings['search_ai_level']]) ? $settings['search_ai_level'] : 'standard';
            Components::page_header(array(
                'title' => __('Dashboard', 'geeky-bot'),
                'description' => sprintf(
                    /* translators: 1: number of searchable products, 2: search level name. */
                    __('%1$s products searchable · %2$s', 'geeky-bot'),
                    number_format_i18n($ctx['indexed_count']),
                    $level_names[$search_level]
                ),
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
                        'label' => __('Test a shopper phrase', 'geeky-bot'),
                        'url' => admin_url('admin.php?page=geekybot-product-assistant#gb-test-lab'),
                        'variant' => 'primary',
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

                // One list of everything that needs the merchant, most urgent
                // first. It replaces the setup guide and the "Needs action"
                // readiness list, which reported the same unfinished steps in
                // two places; finished steps no longer take a row.
                $waiting = array();
                $setup_steps = array(
                    array((bool) $ctx['wc_ready'], __('Connect WooCommerce', 'geeky-bot'), __('Without it the assistant has no catalog to answer from.', 'geeky-bot'), __('Check WooCommerce', 'geeky-bot'), 'geekybot-setup'),
                    array($ctx['indexed_count'] > 0 && $natural_search_ready, __('Build the product search index', 'geeky-bot'), __('Until this runs, product questions return nothing.', 'geeky-bot'), __('Index products', 'geeky-bot'), 'geekybot-product-assistant'),
                    array($ctx['policy_count'] > 0, __('Choose your policy pages', 'geeky-bot'), __('Shoppers asking about returns or delivery get the fallback answer until you do.', 'geeky-bot'), __('Choose pages', 'geeky-bot'), 'geekybot-store-knowledge'),
                    array((bool) $widget_ready, __('Turn on the storefront widget', 'geeky-bot'), __('Nobody can use the assistant until the widget is live.', 'geeky-bot'), __('Enable widget', 'geeky-bot'), 'geekybot-widget'),
                );
                foreach ($setup_steps as $step) {
                    if (!$step[0]) {
                        $waiting[] = array('title' => $step[1], 'description' => $step[2], 'severity' => 'high', 'action' => array('label' => $step[3], 'url' => admin_url('admin.php?page=' . $step[4]), 'variant' => 'primary'));
                    }
                }
                if ($review_count > 0) {
                    $waiting[] = array('title' => $priority_heading, 'description' => __('Turn repeated misses into better attributes, synonyms, selected policy pages, or Commerce Pro rules.', 'geeky-bot'), 'severity' => 'critical', 'action' => array('label' => __('Review questions', 'geeky-bot'), 'url' => admin_url('admin.php?page=geekybot-conversations')));
                }
                $learning = SearchLearningService::suggestions();
                $open_suggestions = array_filter($learning, function ($s) {
                    return ($s['status'] ?? '') === 'new';
                });
                $not_sold = array_filter($learning, function ($s) {
                    return ($s['status'] ?? '') === 'not_sold';
                });
                if (!empty($open_suggestions)) {
                    $examples = array();
                    foreach (array_slice($open_suggestions, 0, 2, true) as $key => $suggestion) {
                        $examples[] = '“' . $key . '” → ' . implode(', ', array_slice((array) $suggestion['maps_to'], 0, 2));
                    }
                    $waiting[] = array(
                        /* translators: %s: number of synonym suggestions. */
                        'title' => sprintf(_n('%s synonym suggestion', '%s synonym suggestions', count($open_suggestions), 'geeky-bot'), number_format_i18n(count($open_suggestions))),
                        'description' => implode(' · ', $examples),
                        'severity' => 'medium',
                        'action' => array('label' => __('Review', 'geeky-bot'), 'url' => admin_url('admin.php?page=geekybot-product-assistant#gb-search-learning')),
                    );
                }
                if (!empty($not_sold)) {
                    $waiting[] = array(
                        'title' => __('Shoppers wanted things you don’t sell', 'geeky-bot'),
                        'description' => implode(' · ', array_slice(array_keys($not_sold), 0, 5)),
                        'severity' => 'medium',
                        'action' => array('label' => __('See demand', 'geeky-bot'), 'url' => admin_url('admin.php?page=geekybot-product-assistant#gb-search-learning')),
                    );
                }

                $sc_counts = SmartCatalogService::counts();
                $sc_searchable = max(0, $sc_counts['total'] - $sc_counts['off'] - $sc_counts['skipped']);
                $sc_state = SmartCatalogService::state();
                $rescue_counts = (array) (SearchRescueService::state()['counts'] ?? array());

                // Before the first shopper uses the chat, the performance, questions
                // and "right now" rows are seven empty states in a row. Show one
                // card instead; the full rows return with the first conversation.
                $has_activity = absint($now_summary['sessions']) > 0
                    || absint($prev_summary['sessions']) > 0
                    || $messages_count > 0
                    || !empty($recent_rows)
                    || !empty($recent_unanswered);
                ?>

                <div class="gb2-grid">
                    <div class="gb2-col-8">
                        <?php Components::card_open(__('Waiting for you', 'geeky-bot'), empty($waiting) ? '' : sprintf(
                            /* translators: %s: number of items. */
                            _n('%s item', '%s items', count($waiting), 'geeky-bot'),
                            number_format_i18n(count($waiting))
                        ), true, 'gb2-fill'); ?>
                            <?php if (empty($waiting)) : ?>
                                <?php Components::empty_state(
                                    __('Nothing needs you right now', 'geeky-bot'),
                                    __('Setup is complete and there is nothing to review. New questions and suggestions will appear here.', 'geeky-bot')
                                ); ?>
                            <?php else : ?>
                                <ul class="gb2-tasks">
                                    <?php foreach ($waiting as $task) {
                                        Components::task($task);
                                    } ?>
                                </ul>
                            <?php endif; ?>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-4">
                        <?php Components::card_open(__('Search health', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <div class="gb2-keyvalues">
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Search level', 'geeky-bot'); ?></span><strong><?php echo esc_html($level_names[$search_level]); ?></strong></div>
                                <?php if ($search_level !== 'standard') : ?>
                                    <div class="gb2-keyvalue"><span><?php esc_html_e('AI model', 'geeky-bot'); ?></span><strong><?php echo esc_html(SmartCatalogService::model()); ?></strong></div>
                                    <div class="gb2-keyvalue"><span><?php esc_html_e('Products with AI words', 'geeky-bot'); ?></span><strong><?php echo esc_html(sprintf('%s / %s', number_format_i18n($sc_counts['done']), number_format_i18n($sc_searchable))); ?></strong></div>
                                <?php endif; ?>
                                <?php if ($search_level === 'rescue') : ?>
                                    <div class="gb2-keyvalue"><span><?php esc_html_e('Searches rescued', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n(absint($rescue_counts['rescued'] ?? 0) + absint($rescue_counts['cached'] ?? 0))); ?></strong></div>
                                <?php endif; ?>
                                <?php if (!empty($sc_state['usage']['calls']) || !empty($sc_state['rescue_usage']['calls'])) : ?>
                                    <div class="gb2-keyvalue"><span><?php esc_html_e('AI calls by search', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n(absint($sc_state['usage']['calls'] ?? 0) + absint($sc_state['rescue_usage']['calls'] ?? 0) + absint($sc_state['learning_usage']['calls'] ?? 0))); ?></strong></div>
                                <?php endif; ?>
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Last index rebuild', 'geeky-bot'); ?></span><strong><?php echo esc_html($this->compact_datetime_label($ctx['last_rebuild'])); ?></strong></div>
                            </div>
                            <a class="gb2-link" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant')); ?>"><?php esc_html_e('Open Product Search', 'geeky-bot'); ?></a>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php if (!$has_activity) : ?>
                    <?php Components::rule(__('Shopper activity', 'geeky-bot')); ?>
                    <?php Components::card_open(__('Conversations, questions and buying steps', 'geeky-bot'), __('Last 30 days', 'geeky-bot')); ?>
                        <?php Components::empty_state(
                            __('No shopper conversations yet', 'geeky-bot'),
                            __('Charts, unanswered questions and recent conversations appear here after the first shopper uses the chat.', 'geeky-bot'),
                            array('label' => __('Try the chat on your store', 'geeky-bot'), 'url' => home_url('/'))
                        ); ?>
                    <?php Components::card_close(); ?>
                <?php else : ?>

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

                <?php
                // Rendered here, shown beside the unanswered questions below:
                // "why answers failed" is about the same misses.
                ob_start();
                ?>
                        <?php
                        $reason_total = array_sum(wp_list_pluck($reasons, 'total'));
                        Components::card_open(
                            __('Why answers failed', 'geeky-bot'),
                            $reason_total > 0 ? number_format_i18n($reason_total) : '',
                            false,
                            ''
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
                <?php $why_answers_failed_card = ob_get_clean(); ?>

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

                    <div class="gb2-col-4 gb2-stack">
                        <?php
                        // Built by this method above from escaped values only.
                        echo $why_answers_failed_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                        <?php Components::card_open(__('Conversation review signal', 'geeky-bot'), '', false); ?>
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
                            <a class="gb2-link" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php
                                esc_html_e('Open conversation review', 'geeky-bot'); ?></a>
                        <?php Components::card_close(); ?>
                    </div>
                </div>

                <?php Components::rule(__('Right now', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-6">
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
                                            /* translators: %s: number of shopper questions in this conversation that got no answer. */
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

                    <div class="gb2-col-6">
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
                </div>

                <?php endif; ?>

                <details class="gb2-card gb2-details gb2-details--card gb2-details--spaced">
                    <summary><?php esc_html_e('What shoppers see, and how the assistant works', 'geeky-bot'); ?></summary>

                <div class="gb2-grid">
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

                    <div class="gb2-col-8 gb2-stack">
                        <?php Components::card_open(__('Product discovery signals', 'geeky-bot'), '', false); ?>
                            <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);color:var(--gb2-mute)"><?php
                                esc_html_e('Search uses WooCommerce product data first, then shopper-language synonyms and close-match rules.', 'geeky-bot'); ?></p>
                            <div class="gb2-keyvalues">
                                <div class="gb2-keyvalue">
                                    <span><?php esc_html_e('Natural search', 'geeky-bot'); ?></span>
                                    <strong><?php echo esc_html($natural_search_ready ? __('On', 'geeky-bot') : __('Off', 'geeky-bot')); ?></strong>
                                </div>
                                <div class="gb2-keyvalue">
                                    <span><?php esc_html_e('Match mode', 'geeky-bot'); ?></span>
                                    <strong style="font-size:var(--gb2-t-md)"><?php echo esc_html($fallback_mode); ?></strong>
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

                        <div class="gb2-grid">
                        <div class="gb2-col-6">
                        <?php Components::card_open(__('Beyond simple product search', 'geeky-bot'), $pro_active ? __('Active', 'geeky-bot') : __('Commerce Pro', 'geeky-bot'), false, 'gb2-fill'); ?>
                            <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);color:var(--gb2-mute)"><?php
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

                        <div class="gb2-col-6">
                        <?php Components::card_open(__('Try examples from this store', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                            <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);color:var(--gb2-mute)"><?php
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
                    </div>
                </div>
                </details>

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
            __('Current mode: %s. Local grounded mode is the default and calls no language model — answers are built from store data. OpenAI is optional and adds generated wording.', 'geeky-bot'),
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
            <?php $this->page_hero(__('Setup', 'geeky-bot'), __('Launch a useful WooCommerce shopping assistant through a resumable seven-step path. Every check uses the live store configuration, so you can leave and continue later without losing progress.', 'geeky-bot'), __('Guided launch', 'geeky-bot'), $hero_action_url, $hero_action_label); ?>
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
                            $this->setup_task_card(__('Answer mode', 'geeky-bot'), $readiness['steps']['provider'], $provider_detail, admin_url('admin.php?page=geekybot-settings#gb-settings-ai'), __('Review answer mode', 'geeky-bot'), '02');
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
                                    <div style="font-size:var(--gb2-t-xl);font-weight:640;line-height:1.1"><?php
                                        echo esc_html(number_format_i18n($readiness['score'])); ?>%</div>
                                    <div style="font-size:var(--gb2-t-sm);color:var(--gb2-mute)"><?php
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
                            <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
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
                            <p style="margin:0;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Products and the Guided Demo stay blocked until the store runtime is available.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>
                    </div>
                    <div class="gb2-col-4">
                        <?php Components::card_open(__('No AI account required', 'geeky-bot'), '', false); ?>
                            <p style="margin:0;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Local grounded mode is the default and works without an external provider or API key. It does not call a language model: answers are assembled from your catalog and selected policy pages. Add a provider key under Answer mode if you want generated, conversational wording.', 'geeky-bot'); ?></p>
                        <?php Components::card_close(); ?>
                    </div>
                    <div class="gb2-col-4">
                        <?php Components::card_open(__('You can stop anytime', 'geeky-bot'), '', false); ?>
                            <p style="margin:0;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
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
            <?php $this->page_hero(__('Guided Demo', 'geeky-bot'), __('Real shopper requests built from your own products and policy pages. Try any of them on your storefront.', 'geeky-bot'), __('Real-store rehearsal', 'geeky-bot'), home_url('/'), __('Open storefront', 'geeky-bot'), true); ?>
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
                <?php else :
                    // One card per example. Kept as a closure so the free and
                    // Commerce Pro groups below render identical cards.
                    $render_example = function ($example) use ($widget_enabled) {
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
                                    <span style="font-size:var(--gb2-t-sm);color:var(--gb2-faint)"><?php
                                        echo esc_html($example['feature']); ?></span>
                                    <?php if (!empty($example['isConversation'])) : ?>
                                        <span style="font-size:var(--gb2-t-sm);color:var(--gb2-faint)" title="<?php
                                            esc_attr_e('Runs as a short conversation, not a single question.', 'geeky-bot'); ?>">&middot; <?php
                                            printf(
                                                /* translators: %d: number of turns in the demo conversation. */
                                                esc_html(_n('%d turn', '%d turns', count($steps), 'geeky-bot')),
                                                (int) count($steps)
                                            ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <h3 style="margin:0 0 4px;font-size:16px;font-weight:600;line-height:1.35"><?php
                                    echo esc_html($example['title']); ?></h3>
                                <p style="margin:0 0 10px;font-size:var(--gb2-t-sm);line-height:1.5;color:var(--gb2-mute)"><?php
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

                                <p style="margin:0 0 10px;font-size:var(--gb2-t-sm);color:var(--gb2-faint)">
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
                    <?php
                    };

                    $groups = array(
                        array(
                            'label' => __('Free: find, understand and choose products', 'geeky-bot'),
                            'items' => array_values(array_filter($examples, function ($example) {
                                return $example['tier'] !== 'pro';
                            })),
                        ),
                        array(
                            'label' => __('Commerce Pro: cart, variations and checkout', 'geeky-bot'),
                            'items' => array_values(array_filter($examples, function ($example) {
                                return $example['tier'] === 'pro';
                            })),
                        ),
                    );

                    foreach ($groups as $group) :
                        if (empty($group['items'])) {
                            continue;
                        }
                        // Three cards per group on show; the rest one click away.
                        $shown = array_slice($group['items'], 0, 3);
                        $more = array_slice($group['items'], 3);
                        ?>
                        <div class="gb2-rule"><b><?php echo esc_html($group['label']); ?></b></div>
                        <div class="gb2-grid">
                            <?php foreach ($shown as $example) {
                                $render_example($example);
                            } ?>
                        </div>
                        <?php if (!empty($more)) : ?>
                            <details class="gb2-showmore">
                                <summary><?php
                                    printf(
                                        /* translators: %s: number of further demo examples. */
                                        esc_html(_n('Show %s more example', 'Show %s more examples', count($more), 'geeky-bot')),
                                        esc_html(number_format_i18n(count($more)))
                                    ); ?></summary>
                                <div class="gb2-grid">
                                    <?php foreach ($more as $example) {
                                        $render_example($example);
                                    } ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function chat_widget() {
        $ctx = $this->context();
        $settings = $ctx['settings'];
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-widget">
            <?php $this->page_hero(__('Storefront Widget', 'geeky-bot'), __('Name, messages, look and invitation for the chat on your store.', 'geeky-bot'), __('Shopper experience', 'geeky-bot'), home_url('/'), __('Open storefront', 'geeky-bot'), true); ?>
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

                            <?php Components::card_open(__('Basics', 'geeky-bot'), '', false); ?>
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

                                <div class="gb2-field">
                                    <label for="gb2-max-products"><?php esc_html_e('Products per answer', 'geeky-bot'); ?></label>
                                    <input class="gb2-input" id="gb2-max-products" name="max_products" type="number" min="1" max="8" value="<?php echo esc_attr(absint($settings['max_products'])); ?>" />
                                    <p class="gb2-field__help"><?php esc_html_e('Three or four keeps replies readable on a phone.', 'geeky-bot'); ?></p>
                                </div>
                            <?php Components::card_close(); ?>

                            <div style="height:14px"></div>

                            <?php Components::card_open(__('Look and branding', 'geeky-bot'), '', false); ?>
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

                                <div class="gb2-field">
                                    <label for="gb2-header-style"><?php esc_html_e('Header style', 'geeky-bot'); ?></label>
                                    <select class="gb2-input" id="gb2-header-style" name="header_style">
                                        <option value="gradient" <?php selected($settings['header_style'], 'gradient'); ?>><?php esc_html_e('Gradient', 'geeky-bot'); ?></option>
                                        <option value="solid" <?php selected($settings['header_style'], 'solid'); ?>><?php esc_html_e('Solid accent', 'geeky-bot'); ?></option>
                                    </select>
                                </div>

                                <div class="gb2-switch-row" style="padding-top:0">
                                    <input type="hidden" name="user_avatar_enabled" value="no" />
                                    <input type="checkbox" id="gb2-user-avatar" name="user_avatar_enabled" value="yes" <?php checked(isset($settings['user_avatar_enabled']) ? $settings['user_avatar_enabled'] : 'no', 'yes'); ?> />
                                    <span class="gb2-switch-row__text">
                                        <label for="gb2-user-avatar"><strong><?php esc_html_e('Show an icon beside the shopper\'s messages', 'geeky-bot'); ?></strong></label>
                                        <span><?php esc_html_e('Off by default. The icon reserves space in every message to repeat what the alignment already shows.', 'geeky-bot'); ?></span>
                                    </span>
                                </div>
                            <?php Components::card_close(); ?>

                            <div style="height:14px"></div>

                            <?php Components::card_open(__('Shopper invitation', 'geeky-bot'), '', false); ?>
                                <div class="gb2-switch-row" style="padding-top:0">
                                    <input type="hidden" name="shopper_invitation_enabled" value="no" />
                                    <input type="checkbox" id="gb2-invitation-enabled" name="shopper_invitation_enabled" value="yes" <?php checked($settings['shopper_invitation_enabled'], 'yes'); ?> />
                                    <span class="gb2-switch-row__text">
                                        <label for="gb2-invitation-enabled"><strong><?php esc_html_e('Show the invitation', 'geeky-bot'); ?></strong></label>
                                        <span><?php esc_html_e('One short prompt above the button after a delay, once per session. The chat stays closed until the shopper opens it.', 'geeky-bot'); ?></span>
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

                            <?php Components::card_open(__('Starter prompts', 'geeky-bot'), '', false); ?>
                                <div class="gb2-field">
                                    <label for="gb2-starter-prompts" class="gb2-screen-reader-text"><?php
                                        esc_html_e('Starter prompts', 'geeky-bot'); ?></label>
                                    <textarea class="gb2-input" id="gb2-starter-prompts" name="starter_prompts" rows="5"><?php
                                        echo esc_textarea(isset($settings['starter_prompts']) ? $settings['starter_prompts'] : ''); ?></textarea>
                                    <p class="gb2-field__help"><?php
                                        esc_html_e('One per line, written the way a customer would ask. The first four appear as buttons; leave empty for the defaults.', 'geeky-bot'); ?></p>
                                </div>
                            <?php Components::card_close(); ?>
                            <div class="gb2-inline" style="margin-top:14px">
                                <button class="gb2-btn gb2-btn--primary" type="submit"><?php esc_html_e('Save widget', 'geeky-bot'); ?></button>
                                <a class="gb2-btn" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php
                                    esc_html_e('Test on storefront', 'geeky-bot'); ?></a>
                            </div>
                        </form>
                    </div>

                    <div class="gb2-col-5 gb2-sticky-col">
                        <?php Components::card_open(__('Live preview', 'geeky-bot'), __('Updates as you type', 'geeky-bot'), false); ?>
                            <?php $this->widget_preview($settings); ?>
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
                        'base' => $this->product_index_status_text($ctx['index_status']),
                    ),
                    array(
                        'label' => __('Index status', 'geeky-bot'),
                        'value' => $this->product_index_status_label($ctx),
                        'base' => $ctx['index_status'] === 'current'
                            ? sprintf(
                                /* translators: %s: date of the last full index rebuild. */
                                __('Rebuilt %s', 'geeky-bot'),
                                strtotime((string) $ctx['last_rebuild']) ? date_i18n('M j, Y', strtotime((string) $ctx['last_rebuild'])) : __('never', 'geeky-bot')
                            )
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

                <?php
                $open_suggestion_count = count(array_filter(SearchLearningService::suggestions(), function ($s) {
                    return ($s['status'] ?? '') === 'new';
                }));
                ?>
                <nav class="gb2-jumpnav" aria-label="<?php esc_attr_e('On this page', 'geeky-bot'); ?>">
                    <a href="#gb-test-lab"><?php esc_html_e('Test a phrase', 'geeky-bot'); ?></a>
                    <a href="#gb-smart-catalog"><?php esc_html_e('Search level', 'geeky-bot'); ?></a>
                    <a href="#gb-search-learning"><?php esc_html_e('Missed searches', 'geeky-bot'); ?><?php if ($open_suggestion_count > 0) : ?> <span class="gb2-jumpnav__count"><?php echo esc_html(number_format_i18n($open_suggestion_count)); ?></span><?php endif; ?></a>
                    <a href="#gb-tune"><?php esc_html_e('Synonyms & ranking', 'geeky-bot'); ?></a>
                </nav>

                <span id="gb-test-lab" class="gb2-anchor"></span>
                <?php Components::rule(__('Test what shoppers type', 'geeky-bot')); ?>

                <?php Components::card_open(__('Shopper phrase test lab', 'geeky-bot'), '', false); ?>
                    <form method="get" class="gb2-inline" style="flex-wrap:nowrap;gap:8px">
                        <input type="hidden" name="page" value="geekybot-product-assistant" />
                        <input class="gb2-input" type="search" name="gb_test_query" value="<?php echo esc_attr($test_query); ?>" placeholder="<?php esc_attr_e('Try: comfortable shoes size 42 red and white', 'geeky-bot'); ?>" />
                        <button class="gb2-btn gb2-btn--primary" type="submit" style="flex:none"><?php
                            esc_html_e('Test search', 'geeky-bot'); ?></button>
                    </form>
                    <?php
                    // One click loads a phrase into the lab. These were a separate
                    // "Rehearsal board" section further down the page.
                    $rehearsal = array(
                        'budget hoodie' => __('Budget intent', 'geeky-bot'),
                        'hoodie between 30 and 60' => __('Price range', 'geeky-bot'),
                        'blue hoodie' => __('Colour attribute', 'geeky-bot'),
                        'shoes size 42' => __('Size attribute', 'geeky-bot'),
                        'not too expensive walking shoes in black size 9' => __('Soft preference', 'geeky-bot'),
                        'hoodies on sale' => __('Sale filter', 'geeky-bot'),
                        'something to keep warm' => __('Descriptive request', 'geeky-bot'),
                        'which hoodie do you recommend' => __('Recommendation', 'geeky-bot'),
                    );
                    ?>
                    <div class="gb2-trychips">
                        <span><?php esc_html_e('Try:', 'geeky-bot'); ?></span>
                        <?php foreach ($rehearsal as $phrase => $label) : ?>
                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'geekybot-product-assistant', 'gb_test_query' => rawurlencode($phrase)), admin_url('admin.php'))); ?>" title="<?php echo esc_attr($label); ?>"><?php echo esc_html($phrase); ?></a>
                        <?php endforeach; ?>
                    </div>

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
                    <?php endif; ?>
                <?php Components::card_close(); ?>

                <?php
                // Order follows what a merchant does: test a phrase, choose how
                // much AI search uses, act on missed searches, then fine-tune.
                // Captured here and printed after Missed searches.
                ob_start();
                ?>
                <span id="gb-tune" class="gb2-anchor"></span>
                <?php Components::rule(__('Tune the index', 'geeky-bot')); ?>

                <div class="gb2-grid">
                    <div class="gb2-col-5">
                        <?php Components::card_open(__('Product index', 'geeky-bot'), '', false); ?>
                            <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
                                esc_html_e('Product edits update the index automatically. Rebuild only after a large import, or if results look out of date.', 'geeky-bot'); ?></p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_rebuild_product_index'); ?>
                                <input type="hidden" name="action" value="geekybot_rebuild_product_index" />
                                <button class="gb2-btn gb2-btn--primary" type="submit"><?php
                                    esc_html_e('Rebuild search index', 'geeky-bot'); ?></button>
                            </form>

                            <p class="gb2-note"><?php echo esc_html($this->product_index_status_text(ProductIndexService::rebuild_status())); ?></p>
                        <?php Components::card_close(); ?>
                    </div>

                    <div class="gb2-col-7">
                        <?php $this->product_search_controls($settings); ?>
                    </div>
                </div>
                <?php $tune_section = ob_get_clean(); ?>

                <?php Components::rule(__('Search level', 'geeky-bot')); ?>

                <?php $this->smart_catalog_section($settings); ?>

                <?php Components::rule(__('Missed searches', 'geeky-bot')); ?>

                <?php $this->search_learning_section(); ?>

                <?php
                // Rendered by this method above; every value inside was escaped there.
                echo $tune_section; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>

                <details class="gb2-card gb2-details gb2-details--card">
                    <summary><?php esc_html_e('More phrases to try in the chat', 'geeky-bot'); ?></summary>
                    <?php $this->nlp_action_examples(); ?>
                </details>
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
                                <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
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
                                <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
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
                    <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
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
                            <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);color:var(--gb2-mute)"><?php
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


    public function settings() {
        $settings = Settings::all();
        $provider_label = $this->provider_label($settings);
        $budget = AiBudgetService::status();
        $key_state = Settings::secret_storage_state();
        $key_labels = array(
            'encrypted' => __('Encrypted', 'geeky-bot'),
            'unavailable' => __('Cannot store', 'geeky-bot'),
            'plaintext' => __('Unencrypted', 'geeky-bot'),
            'none' => __('None saved', 'geeky-bot'),
        );
        $level_labels = array(
            'standard' => __('Standard', 'geeky-bot'),
            'catalog' => __('Smart Catalog', 'geeky-bot'),
            'rescue' => __('Smart Catalog + Rescue', 'geeky-bot'),
        );
        $search_level = isset($settings['search_ai_level']) ? $settings['search_ai_level'] : 'standard';
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-ai-privacy">
            <?php
            // Settings used to repeat the widget, search and policy fields that
            // their own pages already own, so the same value could be edited in
            // two places. This page keeps only what has no other home: how
            // answers and search use AI, where keys are kept, and shopper data.
            Components::page_header(array(
                'title' => __('AI & Privacy', 'geeky-bot'),
                'brand' => array($this, 'brand_mark_svg'),
                'description' => __('How answers and search use AI, where your API keys are kept, and what shopper data is stored.', 'geeky-bot'),
                'status' => array(
                    'label' => $settings['provider_mode'] === 'local' ? __('No AI in answers', 'geeky-bot') : $provider_label,
                    'state' => 'ok',
                ),
                'actions' => array(
                    array(
                        'label' => __('Test on storefront', 'geeky-bot'),
                        'url' => home_url('/'),
                        'variant' => 'primary',
                        'external' => true,
                    ),
                ),
            ));
            ?>
            <div class="gb2-main">
            <?php Components::metrics(array(
                array(
                    'label' => __('Chat answers', 'geeky-bot'),
                    'value' => $provider_label,
                    'base' => $settings['provider_mode'] === 'local' ? __('Built from your store data, no AI', 'geeky-bot') : __('Written by AI from your store data', 'geeky-bot'),
                ),
                array(
                    'label' => __('Product search', 'geeky-bot'),
                    'value' => isset($level_labels[$search_level]) ? $level_labels[$search_level] : $level_labels['standard'],
                    'base' => __('Change it on Product Search', 'geeky-bot'),
                ),
                array(
                    'label' => __('API keys', 'geeky-bot'),
                    'value' => isset($key_labels[$key_state]) ? $key_labels[$key_state] : $key_labels['none'],
                    'base' => __('Never shown again after saving', 'geeky-bot'),
                ),
                array(
                    'label' => __('AI calls left today', 'geeky-bot'),
                    'value' => number_format_i18n($budget['daily']['remaining']),
                    'base' => sprintf(
                        /* translators: %s: daily AI call limit. */
                        __('of %s per day', 'geeky-bot'),
                        number_format_i18n($budget['daily']['cap'])
                    ),
                ),
            )); ?>
            </div>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php $gb_secret_refused = isset($_GET['gb_notice']) && sanitize_key(wp_unslash($_GET['gb_notice'])) === 'secret_refused'; ?>
            <?php if (!empty($_GET['updated']) && !$gb_secret_refused) : ?><div class="gb2-savednotice" role="status"><strong><?php esc_html_e('Settings saved.', 'geeky-bot'); ?></strong><span><?php esc_html_e('Your storefront assistant will use the updated configuration.', 'geeky-bot'); ?></span></div><?php endif; ?>
            <?php if ($gb_secret_refused) : ?>
                <div class="notice notice-error"><p><strong><?php esc_html_e('The API key was not saved.', 'geeky-bot'); ?></strong> <?php esc_html_e('Geeky Bot stores provider keys encrypted, and this server has neither the libsodium nor the OpenSSL PHP extension available. Ask your host to enable one, then save the key again. Every other setting on this page was saved.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>

            <form method="post" class="gb2-settings">
                <?php wp_nonce_field('geekybot_save_settings'); ?>
                <input type="hidden" name="geekybot_settings_action" value="save" />
                <?php // Partial: this form no longer carries the widget, search or policy fields, and a full save would reset them. Every checkbox here posts an explicit "no". ?>
                <input type="hidden" name="geekybot_settings_scope" value="partial" />
                <nav class="gb2-rail" aria-label="<?php esc_attr_e('Page sections', 'geeky-bot'); ?>">
                    <a href="#gb-settings-ai"><span><?php esc_html_e('01', 'geeky-bot'); ?></span><?php esc_html_e('AI connection', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-privacy"><span><?php esc_html_e('02', 'geeky-bot'); ?></span><?php esc_html_e('Privacy', 'geeky-bot'); ?></a>
                </nav>
                <div class="gb2-settings__main">
                    <section id="gb-settings-ai" class="gb2-card gb2-scard">
                        <div class="gb2-scard__head"><div><p class="gb2-eyebrow"><?php esc_html_e('Answers and AI', 'geeky-bot'); ?></p><h2><?php esc_html_e('AI connection', 'geeky-bot'); ?></h2><p><?php esc_html_e('Choose how chat answers are written, and save the key Smart Catalog and Rescue use. Keys stay on your server.', 'geeky-bot'); ?></p></div><div class="gb2-scard__meta"><span><?php echo esc_html($provider_label); ?></span><span><?php esc_html_e('Keys hidden', 'geeky-bot'); ?></span></div></div>
                        <?php $this->settings_table_ai($settings); ?>
                        <div class="gb2-snote"><strong><?php esc_html_e('After changing the answer mode', 'geeky-bot'); ?></strong><span><?php esc_html_e('Ask one product and one policy question on your storefront to check the answers.', 'geeky-bot'); ?></span></div>
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
                        <h3 style="margin:18px 0 8px;font-size:var(--gb2-t-sm);font-weight:600"><?php esc_html_e('Shopper interactions', 'geeky-bot'); ?></h3>
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
                                            <div style="font-size:var(--gb2-t-xs);color:var(--gb2-faint)"><?php
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

                <p style="margin:0 0 14px;font-size:var(--gb2-t-sm);color:var(--gb2-mute)">
                    <?php
                    echo esc_html(
                        sprintf(
                            _n(
                                /* translators: 1: number of unique issues, 2: total unanswered-message occurrences. */
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
                                    <h3 style="margin:0 0 6px;font-size:16px;font-weight:600;line-height:1.4"><?php
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
                    /* translators: %d: conversation ID as shown in the review centre. */
                    sprintf(__('Conversation #%d', 'geeky-bot'), (int) $session_id),
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
        $limit_reached = $license['status'] === 'activation_limit_reached';
        $has_key = $license['maskedKey'] !== '';
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- These sanitized values only render a status notice.
        $notice = isset($_GET['gb_license_notice']) ? sanitize_key(wp_unslash($_GET['gb_license_notice'])) : '';
        $error = isset($_GET['gb_license_error']) ? sanitize_text_field(wp_unslash($_GET['gb_license_error'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $notice_messages = array(
            'activated' => __('License activated for this site.', 'geeky-bot'),
            'ready' => __('Commerce Pro is installed and switched on. You are all set.', 'geeky-bot'),
            'deactivated' => __('License deactivated on this site.', 'geeky-bot'),
            'refreshed' => __('License status refreshed.', 'geeky-bot'),
            'installed' => __('Commerce Pro is installed. Switch it on to finish.', 'geeky-bot'),
            'plugin_activated' => __('Commerce Pro is switched on.', 'geeky-bot'),
            'update_settings_saved' => __('Update settings saved.', 'geeky-bot'),
            'update_refreshed' => __('Checked for updates.', 'geeky-bot'),
            'updated' => __('Commerce Pro updated successfully.', 'geeky-bot'),
            'updated_inactive' => __('Commerce Pro updated. Switch it on to use the buying actions again.', 'geeky-bot'),
            'updated_activated' => __('Commerce Pro updated and switched on.', 'geeky-bot'),
        );

        // The three steps every merchant goes through, in order. Each is done,
        // the current one, or waiting on the one before it.
        $step1_done = $license['active'];
        $step2_done = $plugin['installed'];
        $step3_done = $plugin['active'] && $license['active'];
        $all_done = $step1_done && $step2_done && $step3_done;
        $current = !$step1_done ? 1 : (!$step2_done ? 2 : (!$step3_done ? 3 : 0));
        $step_state = function ($number, $done) use ($current) {
            return $done ? 'done' : ($number === $current ? 'current' : 'waiting');
        };
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-pro geekybot-license-admin">
            <?php $this->page_hero(
                __('License', 'geeky-bot'),
                __('Unlock Commerce Pro on this site: enter your key, and Geeky Bot installs and switches on the add-on for you.', 'geeky-bot'),
                __('Commerce Pro', 'geeky-bot'),
                'https://geekybot.com/',
                __('Your account on geekybot.com', 'geeky-bot'),
                true
            ); ?>

            <div class="gb2-main">

            <?php if ($notice && isset($notice_messages[$notice])) : ?>
                <div class="gb2-snote gb2-snote--ok" role="status"><span aria-hidden="true">✓</span><p><?php echo esc_html($notice_messages[$notice]); ?></p></div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="gb2-snote gb2-snote--error" role="alert"><span aria-hidden="true">!</span><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php if ($all_done) : ?>
                <section class="gb2-card gb2-license-ready">
                    <div class="gb2-license-ready__head">
                        <span class="gb2-license-ready__icon" aria-hidden="true">✓</span>
                        <div>
                            <h2><?php esc_html_e('Commerce Pro is active on this site', 'geeky-bot'); ?></h2>
                            <p><?php echo esc_html(sprintf(
                                /* translators: 1: installed version, 2: masked license key. */
                                __('Version %1$s · license %2$s', 'geeky-bot'),
                                $plugin['version'] ? $plugin['version'] : '—',
                                $license['maskedKey']
                            )); ?></p>
                        </div>
                    </div>
                    <p class="gb2-license-ready__lead"><?php esc_html_e('What you can do now:', 'geeky-bot'); ?></p>
                    <div class="gb2-license-next">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-commerce-pro')); ?>"><strong><?php esc_html_e('Set up buying actions', 'geeky-bot'); ?></strong><span><?php esc_html_e('Add to cart, order lookup, comparison and checkout handoff.', 'geeky-bot'); ?></span></a>
                        <?php if (Settings::get('search_ai_level', 'standard') !== 'rescue') : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant#gb-smart-catalog')); ?>"><strong><?php esc_html_e('Turn on search Rescue', 'geeky-bot'); ?></strong><span><?php esc_html_e('Let AI answer searches that find nothing.', 'geeky-bot'); ?></span></a>
                        <?php else : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-commerce-pro')); ?>"><strong><?php esc_html_e('Set sales rules', 'geeky-bot'); ?></strong><span><?php esc_html_e('Tone, guardrails and what the assistant may promise.', 'geeky-bot'); ?></span></a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><strong><?php esc_html_e('Try it on your store', 'geeky-bot'); ?></strong><span><?php esc_html_e('Ask the assistant to add something to your cart.', 'geeky-bot'); ?></span></a>
                    </div>
                </section>
            <?php else : ?>
                <section class="gb2-card gb2-lsteps" aria-label="<?php esc_attr_e('Set up Commerce Pro', 'geeky-bot'); ?>">
                    <h2><?php esc_html_e('Set up Commerce Pro in three steps', 'geeky-bot'); ?></h2>
                    <ol>
                        <li class="gb2-lstep is-<?php echo esc_attr($step_state(1, $step1_done)); ?>">
                            <span class="gb2-lstep__num" aria-hidden="true"><?php echo $step1_done ? '✓' : '1'; ?></span>
                            <div class="gb2-lstep__body">
                                <h3><?php esc_html_e('Enter your license key', 'geeky-bot'); ?></h3>
                                <?php if ($step1_done) : ?>
                                    <p><?php echo esc_html(sprintf(
                                        /* translators: %s: masked license key. */
                                        __('Active: %s', 'geeky-bot'),
                                        $license['maskedKey']
                                    )); ?></p>
                                <?php else : ?>
                                    <?php if ($limit_reached) : ?>
                                        <p class="gb2-lstep__problem"><?php esc_html_e('This key is already used on as many sites as it allows. Deactivate it on a site you no longer use (from that site’s License page, or your account on geekybot.com), then try again here.', 'geeky-bot'); ?></p>
                                    <?php elseif ($has_key && !empty($license['message']) && $license['status'] !== 'inactive') : ?>
                                        <p class="gb2-lstep__problem"><?php echo esc_html($license['message']); ?></p>
                                    <?php else : ?>
                                        <p><?php esc_html_e('You will find it in your account on geekybot.com. After you activate it, Geeky Bot installs and switches on Commerce Pro for you.', 'geeky-bot'); ?></p>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb2-license-form">
                                        <?php wp_nonce_field('geekybot_license_action'); ?>
                                        <input type="hidden" name="action" value="geekybot_license_activate" />
                                        <label for="gb2-license-key" class="screen-reader-text"><?php esc_html_e('License key', 'geeky-bot'); ?></label>
                                        <div class="gb2-license-form__row">
                                            <input class="gb2-input gb2-input--mono" type="text" id="gb2-license-key" name="license_key" value="" placeholder="GB-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" required />
                                            <button type="submit" class="gb2-btn gb2-btn--primary" data-gb-busy="<?php esc_attr_e('Activating and installing…', 'geeky-bot'); ?>"><?php
                                                echo esc_html($can_install || $can_activate ? __('Activate and install', 'geeky-bot') : __('Activate license', 'geeky-bot')); ?></button>
                                        </div>
                                        <p class="gb2-field__help"><?php esc_html_e('This can take up to a minute while Commerce Pro downloads. The key is stored encrypted and never shown to shoppers.', 'geeky-bot'); ?></p>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>

                        <li class="gb2-lstep is-<?php echo esc_attr($step_state(2, $step2_done)); ?>">
                            <span class="gb2-lstep__num" aria-hidden="true"><?php echo $step2_done ? '✓' : '2'; ?></span>
                            <div class="gb2-lstep__body">
                                <h3><?php esc_html_e('Install Commerce Pro', 'geeky-bot'); ?></h3>
                                <?php if ($step2_done) : ?>
                                    <p><?php echo esc_html(sprintf(
                                        /* translators: %s: installed version. */
                                        __('Installed, version %s', 'geeky-bot'),
                                        $plugin['version'] ? $plugin['version'] : '—'
                                    )); ?></p>
                                <?php elseif ($current !== 2) : ?>
                                    <p><?php esc_html_e('Happens automatically once your key is active.', 'geeky-bot'); ?></p>
                                <?php elseif (!$can_install) : ?>
                                    <p class="gb2-lstep__problem"><?php esc_html_e('Your WordPress account cannot install plugins. Ask a site administrator to open this page and click Install.', 'geeky-bot'); ?></p>
                                <?php elseif (empty($license['downloadsAllowed'])) : ?>
                                    <p class="gb2-lstep__problem"><?php esc_html_e('Your license does not currently include downloads, usually because it needs renewing. Renew on geekybot.com, then click “Check my license again”.', 'geeky-bot'); ?></p>
                                <?php else : ?>
                                    <p><?php esc_html_e('Downloads the add-on securely from geekybot.com and switches it on.', 'geeky-bot'); ?></p>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('geekybot_license_action'); ?>
                                        <input type="hidden" name="action" value="geekybot_install_commerce_pro" />
                                        <button type="submit" class="gb2-btn gb2-btn--primary" data-gb-busy="<?php esc_attr_e('Installing…', 'geeky-bot'); ?>"><?php esc_html_e('Install Commerce Pro', 'geeky-bot'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>

                        <li class="gb2-lstep is-<?php echo esc_attr($step_state(3, $step3_done)); ?>">
                            <span class="gb2-lstep__num" aria-hidden="true"><?php echo $step3_done ? '✓' : '3'; ?></span>
                            <div class="gb2-lstep__body">
                                <h3><?php esc_html_e('Switch it on', 'geeky-bot'); ?></h3>
                                <?php if ($current !== 3) : ?>
                                    <p><?php esc_html_e('Happens automatically after installing.', 'geeky-bot'); ?></p>
                                <?php elseif (!$can_activate) : ?>
                                    <p class="gb2-lstep__problem"><?php esc_html_e('Your WordPress account cannot activate plugins. Ask a site administrator to open this page and click Switch on.', 'geeky-bot'); ?></p>
                                <?php else : ?>
                                    <p><?php esc_html_e('Turns on buying actions: cart, orders, comparison and checkout handoff.', 'geeky-bot'); ?></p>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('geekybot_license_action'); ?>
                                        <input type="hidden" name="action" value="geekybot_activate_commerce_pro" />
                                        <button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Switch on Commerce Pro', 'geeky-bot'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    </ol>
                </section>
            <?php endif; ?>

            <div class="gb2-grid">
                <div class="gb2-col-6">
                    <?php Components::card_open(__('Your license', 'geeky-bot'), $license['label'], false, 'gb2-fill'); ?>
                        <div class="gb2-keyvalues">
                            <div class="gb2-keyvalue"><span><?php esc_html_e('Key', 'geeky-bot'); ?></span><strong><?php echo esc_html($has_key ? $license['maskedKey'] : __('Not entered yet', 'geeky-bot')); ?></strong></div>
                            <?php if ($license['plan'] !== '') : ?>
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Plan', 'geeky-bot'); ?></span><strong><?php echo esc_html($license['plan']); ?></strong></div>
                            <?php endif; ?>
                            <div class="gb2-keyvalue"><span><?php esc_html_e('Renews or expires', 'geeky-bot'); ?></span><strong><?php echo esc_html($license['expiresAt'] ? $license['expiresAt'] : __('Never', 'geeky-bot')); ?></strong></div>
                            <div class="gb2-keyvalue"><span><?php esc_html_e('Sites using it', 'geeky-bot'); ?></span><strong><?php echo esc_html(sprintf(
                                /* translators: 1: sites activated, 2: sites allowed. */
                                __('%1$s of %2$s', 'geeky-bot'),
                                number_format_i18n($license['activationCount']),
                                number_format_i18n($license['allowedSites'])
                            )); ?></strong></div>
                            <div class="gb2-keyvalue"><span><?php esc_html_e('Last checked', 'geeky-bot'); ?></span><strong><?php echo esc_html($license['lastCheckedAt'] ? $license['lastCheckedAt'] : __('Not yet', 'geeky-bot')); ?></strong></div>
                        </div>
                        <?php if (!empty($license['isGrace'])) : ?>
                            <p class="gb2-note"><?php esc_html_e('geekybot.com could not be reached, so Commerce Pro is running on its last confirmed check. Nothing to do unless this lasts more than a few days.', 'geeky-bot'); ?></p>
                        <?php endif; ?>
                        <div class="gb2-inline" style="margin-top:14px">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_license_refresh" />
                                <button type="submit" class="gb2-btn"><?php esc_html_e('Check my license again', 'geeky-bot'); ?></button>
                            </form>
                            <?php if ($has_key) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('geekybot_license_action'); ?>
                                    <input type="hidden" name="action" value="geekybot_license_deactivate" />
                                    <?php // Removes this site's activation, so it confirms like the other destructive actions. ?>
                                    <button type="submit" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php echo esc_attr($license['active'] ? __('Deactivate Commerce Pro on this site? Buying actions stop until it is activated again, and the site slot is freed for another install.', 'geeky-bot') : __('Clear the saved license key from this site?', 'geeky-bot')); ?>"><?php
                                        echo esc_html($license['active'] ? __('Move license to another site', 'geeky-bot') : __('Clear saved key', 'geeky-bot')); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php Components::card_close(); ?>
                </div>

                <div class="gb2-col-6">
                    <?php Components::card_open(__('Updates', 'geeky-bot'), !empty($update['update_available']) ? __('Update available', 'geeky-bot') : '', false, 'gb2-fill'); ?>
                        <?php if (!$plugin['installed']) : ?>
                            <p class="gb2-note" style="margin:0"><?php esc_html_e('Updates appear here once Commerce Pro is installed.', 'geeky-bot'); ?></p>
                        <?php else : ?>
                            <div class="gb2-keyvalues">
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Installed', 'geeky-bot'); ?></span><strong><?php echo esc_html($plugin['version'] ? $plugin['version'] : '—'); ?></strong></div>
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Latest', 'geeky-bot'); ?></span><strong><?php echo esc_html(!empty($update['latest_version']) ? $update['latest_version'] : __('Not checked yet', 'geeky-bot')); ?></strong></div>
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Updates included', 'geeky-bot'); ?></span><strong><?php echo esc_html(!empty($license['updatesAllowed']) ? __('Yes', 'geeky-bot') : __('Renewal needed', 'geeky-bot')); ?></strong></div>
                            </div>
                            <?php if (!empty($update['update_available']) && !empty($update['metadata']['changelog'])) : ?>
                                <div class="gb2-note" style="margin-top:10px"><?php echo wp_kses_post($update['metadata']['changelog']); ?></div>
                            <?php endif; ?>
                            <div class="gb2-inline" style="margin-top:14px">
                                <?php if (!empty($update['update_available']) && !empty($update['can_update']) && current_user_can('update_plugins')) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('geekybot_license_action'); ?>
                                        <input type="hidden" name="action" value="geekybot_update_commerce_pro" />
                                        <button type="submit" class="gb2-btn gb2-btn--primary" data-gb-busy="<?php esc_attr_e('Updating…', 'geeky-bot'); ?>"><?php echo esc_html(sprintf(
                                            /* translators: %s: version to update to. */
                                            __('Update to %s', 'geeky-bot'),
                                            $update['latest_version']
                                        )); ?></button>
                                    </form>
                                <?php elseif (!empty($update['update_available'])) : ?>
                                    <p class="gb2-note" style="margin:0"><?php esc_html_e('A new version is out, but your license needs renewing on geekybot.com before it can be installed.', 'geeky-bot'); ?></p>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('geekybot_license_action'); ?>
                                    <input type="hidden" name="action" value="geekybot_refresh_commerce_pro_update" />
                                    <button type="submit" class="gb2-btn"><?php esc_html_e('Check for updates', 'geeky-bot'); ?></button>
                                </form>
                            </div>
                            <details class="gb2-details">
                                <summary><?php esc_html_e('Automatic update settings', 'geeky-bot'); ?></summary>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb2-license-form">
                                    <?php wp_nonce_field('geekybot_license_action'); ?>
                                    <input type="hidden" name="action" value="geekybot_save_commerce_pro_updates" />
                                    <div class="gb2-sgrid">
                                        <label class="gb2-sfield"><span><?php esc_html_e('Release channel', 'geeky-bot'); ?></span><select name="commerce_pro_updates[update_channel]"><option value="stable" <?php selected($update_settings['update_channel'], 'stable'); ?>><?php esc_html_e('Stable', 'geeky-bot'); ?></option><option value="beta" <?php selected($update_settings['update_channel'], 'beta'); ?>><?php esc_html_e('Beta', 'geeky-bot'); ?></option><option value="dev" <?php selected($update_settings['update_channel'], 'dev'); ?>><?php esc_html_e('Dev', 'geeky-bot'); ?></option></select></label>
                                        <label class="gb2-sfield"><span><?php esc_html_e('Automatic updates', 'geeky-bot'); ?></span><select name="commerce_pro_updates[auto_update_mode]"><option value="manual" <?php selected($update_settings['auto_update_mode'], 'manual'); ?>><?php esc_html_e('Manual updates only', 'geeky-bot'); ?></option><option value="critical" <?php selected($update_settings['auto_update_mode'], 'critical'); ?>><?php esc_html_e('Critical security updates only', 'geeky-bot'); ?></option><option value="patch" <?php selected($update_settings['auto_update_mode'], 'patch'); ?>><?php esc_html_e('Patch releases only', 'geeky-bot'); ?></option><option value="minor" <?php selected($update_settings['auto_update_mode'], 'minor'); ?>><?php esc_html_e('Patch and minor releases', 'geeky-bot'); ?></option><option value="stable" <?php selected($update_settings['auto_update_mode'], 'stable'); ?>><?php esc_html_e('All stable releases', 'geeky-bot'); ?></option></select></label>
                                    </div>
                                    <label class="gb2-stoggle"><input type="hidden" name="commerce_pro_updates[auto_update_critical]" value="no" /><input type="checkbox" name="commerce_pro_updates[auto_update_critical]" value="yes" <?php checked($update_settings['auto_update_critical'], 'yes'); ?> /> <span><strong><?php esc_html_e('Always allow critical security auto-updates', 'geeky-bot'); ?></strong><em><?php esc_html_e('Recommended for live stores.', 'geeky-bot'); ?></em></span></label>
                                    <button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Save update settings', 'geeky-bot'); ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                    <?php Components::card_close(); ?>
                </div>
            </div>

            <details class="gb2-card gb2-details gb2-details--card">
                <summary><?php esc_html_e('Technical details', 'geeky-bot'); ?></summary>
                <p class="gb2-note"><?php esc_html_e('What geekybot.com confirmed for this site. Useful when contacting support.', 'geeky-bot'); ?></p>
                <div class="gb2-factgrid">
                    <span><strong><?php echo esc_html($license['domain']); ?></strong><em><?php esc_html_e('Domain', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html($license['siteUrl']); ?></strong><em><?php esc_html_e('Site URL', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html($license['isStaging'] === 'yes' ? __('Staging', 'geeky-bot') : __('Production', 'geeky-bot')); ?></strong><em><?php esc_html_e('Site type', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(number_format_i18n($license['allowedStagingSites'])); ?></strong><em><?php esc_html_e('Allowed staging sites', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['bindingValid']) ? __('Bound to this site', 'geeky-bot') : __('Needs a fresh check', 'geeky-bot')); ?></strong><em><?php esc_html_e('Site binding', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['signatureRequired']) ? (!empty($license['signatureVerified']) ? __('Verified', 'geeky-bot') : __('Not verified', 'geeky-bot')) : __('HTTPS only', 'geeky-bot')); ?></strong><em><?php esc_html_e('Signed entitlement', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['runtimeAllowed']) ? __('Allowed', 'geeky-bot') : __('Locked', 'geeky-bot')); ?></strong><em><?php esc_html_e('Runtime entitlement', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['downloadsAllowed']) ? __('Allowed', 'geeky-bot') : __('Not included', 'geeky-bot')); ?></strong><em><?php esc_html_e('Downloads', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['supportAllowed']) ? __('Allowed', 'geeky-bot') : __('Renewal required', 'geeky-bot')); ?></strong><em><?php esc_html_e('Support', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($update['metadata']['channel']) ? strtoupper($update['metadata']['channel']) : strtoupper($update_settings['update_channel'])); ?></strong><em><?php esc_html_e('Update channel', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html($license['isGrace'] ? __('Grace period', 'geeky-bot') : __('Normal', 'geeky-bot')); ?></strong><em><?php esc_html_e('Verification', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html($license['status'] !== '' ? $license['status'] : '—'); ?></strong><em><?php esc_html_e('Raw status', 'geeky-bot'); ?></em></span>
                </div>
                <?php if (!empty($license['lastError'])) : ?>
                    <p class="gb2-note"><?php echo esc_html(sprintf(
                        /* translators: %s: last error from the license server. */
                        __('Last server message: %s', 'geeky-bot'),
                        $license['lastError']
                    )); ?></p>
                <?php endif; ?>
                <a class="gb2-link" href="<?php echo esc_url(admin_url('plugins.php')); ?>"><?php esc_html_e('Open the Plugins page', 'geeky-bot'); ?></a>
            </details>

            </div>
        </div>
        <script>
        (function () {
            // Long-running actions (download, install) give no feedback while the
            // server works; say what is happening and stop double submits.
            document.querySelectorAll('.geekybot-license-admin [data-gb-busy]').forEach(function (button) {
                button.form && button.form.addEventListener('submit', function () {
                    button.textContent = button.getAttribute('data-gb-busy');
                    button.disabled = true;
                });
            });
        })();
        </script>
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
        if (isset($_GET['gb_index_queued'])) :
            $progress = ProductIndexService::rebuild_progress(); ?>
            <div class="notice notice-info is-dismissible"><p><?php
                printf(
                    /* translators: 1: products processed, 2: total products. */
                    esc_html__('Rebuilding the product search index in the background (%1$s of %2$s products). Shoppers keep searching the current index until the new one is ready.', 'geeky-bot'),
                    esc_html(number_format_i18n(absint($progress['processed']))),
                    esc_html(number_format_i18n(absint($progress['total'])))
                );
            ?></p></div>
        <?php endif;
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
        // Headers carry no figure row since the 2.1.1 "Refined" pass. Each page
        // opens with its own key-numbers strip right below, so the header row
        // repeated it ("115 products indexed" twice, 40px apart) or showed
        // numbers unrelated to the page (conversation counts on License).
        $signals = array();

        $actions = array();
        if ($action_url && $action_label) {
            $actions[] = array(
                'label' => $action_label,
                'url' => $action_url,
                'variant' => 'primary',
                'external' => (bool) $external,
            );
            // No generic "Settings" button any more: each setting now lives on
            // its own page, so a shared Settings link mostly led elsewhere.
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

    private function product_search_controls($settings) {
        $index_service = new ProductIndexService();
        $indexed_count = $index_service->count_indexed();
        $last_rebuild = get_option(ProductIndexService::LAST_REBUILD_OPTION, '');
        $index_status = ProductIndexService::rebuild_status();
        ?>
        <?php Components::card_open(__('Buyer search controls', 'geeky-bot'), '', false, 'gb2-fill'); ?>
            <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
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
                    <span style="font-size:var(--gb2-t-sm);color:var(--gb2-faint)"><?php esc_html_e('Reset:', 'geeky-bot'); ?></span>
                    <button type="submit" name="geekybot_reset_search" value="synonyms" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php esc_attr_e('Restore the default synonym examples? Your custom synonym text will be replaced.', 'geeky-bot'); ?>"><?php esc_html_e('Default synonyms', 'geeky-bot'); ?></button>
                    <button type="submit" name="geekybot_reset_search" value="all" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php esc_attr_e('Reset all buyer search settings to their defaults?', 'geeky-bot'); ?>"><?php esc_html_e('All search settings', 'geeky-bot'); ?></button>
                </div>
            </form>

        <?php Components::card_close(); ?>
    <?php }

    /**
     * Search level choice and Smart Catalog progress.
     *
     * @param array $settings Current settings.
     * @return void
     */
    private function smart_catalog_section($settings) {
        $level = isset($settings['search_ai_level']) ? $settings['search_ai_level'] : 'standard';
        $chosen_languages = (array) (isset($settings['search_ai_languages']) ? $settings['search_ai_languages'] : array());
        $store_language = SmartCatalogService::store_language();
        $connection = SmartCatalogService::connection();
        $enabled = in_array($level, array('catalog', 'rescue'), true);
        $rescue_licensed = SearchRescueService::licensed();
        $rescue_state = SearchRescueService::state();
        $counts = SmartCatalogService::counts();
        $state = SmartCatalogService::state();
        $searchable = max(0, $counts['total'] - $counts['off'] - $counts['skipped']);
        $percent = $searchable > 0 ? (int) floor(($counts['done'] / $searchable) * 100) : 0;
        $calls = (int) ceil($counts['waiting'] / SmartCatalogService::BATCH_SIZE);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result flag from our own redirect.
        $notice = isset($_GET['gb_sc']) ? sanitize_key(wp_unslash($_GET['gb_sc'])) : '';

        if (!$enabled) {
            $pill = array(__('Off', 'geeky-bot'), 'neutral');
        } elseif ($connection === null) {
            $pill = array(__('Needs an AI connection', 'geeky-bot'), 'warn');
        } elseif (isset($state['status']) && $state['status'] === 'error') {
            $pill = array(__('Paused after an error', 'geeky-bot'), 'crit');
        } elseif (isset($state['status']) && $state['status'] === 'paused_budget') {
            $pill = array(__('Paused: AI limit', 'geeky-bot'), 'warn');
        } elseif ($counts['waiting'] > 0) {
            $pill = array(__('Writing words', 'geeky-bot'), 'ok');
        } elseif ($counts['failed'] > 0) {
            $pill = array(__('Done, some products failed', 'geeky-bot'), 'warn');
        } else {
            $pill = array(__('Up to date', 'geeky-bot'), 'ok');
        }
        ?>
        <div class="gb2-grid" id="gb-smart-catalog">
            <div class="gb2-col-7">
                <?php Components::card_open(__('How product search finds products', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                    <?php if ($notice === 'saved') : ?>
                        <div class="notice notice-success inline" style="margin:0 0 12px"><p><?php esc_html_e('Search level saved. The product index is being rebuilt.', 'geeky-bot'); ?></p></div>
                    <?php endif; ?>
                    <form method="post">
                        <?php wp_nonce_field('geekybot_save_settings'); ?>
                        <input type="hidden" name="geekybot_settings_action" value="save" />
                        <input type="hidden" name="geekybot_settings_scope" value="partial" />
                        <input type="hidden" name="geekybot_redirect" value="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant&gb_sc=saved#gb-smart-catalog')); ?>" />
                        <input type="hidden" name="search_ai_languages[]" value="" />

                        <div class="gb2-radios gb2-radios--stack" role="radiogroup" aria-label="<?php esc_attr_e('Search level', 'geeky-bot'); ?>">
                            <label class="gb2-radio <?php echo esc_attr(!$enabled ? 'is-selected' : ''); ?>">
                                <input type="radio" name="search_ai_level" value="standard" <?php checked(!$enabled); ?> />
                                <span><strong><?php esc_html_e('Standard', 'geeky-bot'); ?></strong><em><?php
                                    esc_html_e('Your product names, categories, tags and attributes, with typo fixing. No AI.', 'geeky-bot'); ?></em></span>
                            </label>
                            <label class="gb2-radio <?php echo esc_attr($level === 'catalog' ? 'is-selected' : ''); ?>">
                                <input type="radio" name="search_ai_level" value="catalog" <?php checked($level, 'catalog'); ?> />
                                <span><strong><?php esc_html_e('Smart Catalog', 'geeky-bot'); ?></strong><em><?php
                                    esc_html_e('AI adds the other names shoppers use for each product (sneakers, trainers, kicks). Searches never wait for AI.', 'geeky-bot'); ?></em></span>
                            </label>
                            <label class="gb2-radio <?php echo esc_attr($level === 'rescue' ? 'is-selected' : ''); ?><?php echo $rescue_licensed ? '' : ' is-locked'; ?>">
                                <input type="radio" name="search_ai_level" value="rescue" <?php checked($level, 'rescue'); ?> <?php disabled(!$rescue_licensed && $level !== 'rescue'); ?> />
                                <span><strong><?php esc_html_e('Smart Catalog + Rescue', 'geeky-bot'); ?> <?php if (!$rescue_licensed) : ?><span class="gb2-pill"><?php esc_html_e('Commerce Pro', 'geeky-bot'); ?></span><?php endif; ?></strong><em><?php
                                    esc_html_e('Also asks AI when a search finds nothing (“something to keep warm”), and remembers the answer for everyone.', 'geeky-bot'); ?></em></span>
                            </label>
                        </div>

                        <p style="margin:0 0 6px;font-size:var(--gb2-t-sm);font-weight:600"><?php esc_html_e('Also add words in these languages', 'geeky-bot'); ?></p>
                        <div class="gb2-sc-langs">
                            <?php foreach (SmartCatalogService::languages() as $code => $name) :
                                if ($code === $store_language) {
                                    continue;
                                } ?>
                                <label><input type="checkbox" name="search_ai_languages[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $chosen_languages, true)); ?> /> <?php echo esc_html($this->smart_catalog_language_label($code, $name)); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="gb2-note" style="margin:6px 0 0"><?php esc_html_e('Your store language is always included. Each extra language adds to the AI cost.', 'geeky-bot'); ?></p>

                        <div class="gb2-snote">
                            <?php if ($connection !== null) : ?>
                                <strong><?php echo esc_html(sprintf(
                                    /* translators: %s: AI provider and model, e.g. "OpenAI gpt-4o-mini". */
                                    __('Uses your AI connection: %s', 'geeky-bot'),
                                    $connection['label']
                                )); ?></strong>
                                <span><?php esc_html_e('Only product details are sent, never shopper messages.', 'geeky-bot'); ?></span>
                            <?php else : ?>
                                <strong><?php esc_html_e('Smart Catalog needs an AI connection', 'geeky-bot'); ?></strong>
                                <span><?php
                                    printf(
                                        /* translators: %s: link to the Settings page. */
                                        esc_html__('Save an OpenAI key in %s. Chat answers can stay in local mode.', 'geeky-bot'),
                                        '<a href="' . esc_url(admin_url('admin.php?page=geekybot-settings')) . '">' . esc_html__('Settings', 'geeky-bot') . '</a>'
                                    ); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($connection !== null && $connection['provider'] === 'openai') : ?>
                            <label class="gb2-sfield" style="display:block;margin-top:14px"><span><?php esc_html_e('OpenAI model for search words', 'geeky-bot'); ?></span>
                                <input id="search_ai_model" name="search_ai_model" type="text" value="<?php echo esc_attr(isset($settings['search_ai_model']) ? $settings['search_ai_model'] : ''); ?>" placeholder="<?php echo esc_attr(isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-4o-mini'); ?>" />
                                <em><?php esc_html_e('Blank uses your chat model. gpt-4o-mini gave the best search words in testing; smaller models such as gpt-4.1-nano cost about the same overall because they write more, weaker words.', 'geeky-bot'); ?></em>
                            </label>
                        <?php endif; ?>

                        <div class="gb2-inline" style="margin-top:14px">
                            <button type="submit" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Save search level', 'geeky-bot'); ?></button>
                        </div>
                    </form>
                <?php Components::card_close(); ?>
            </div>

            <div class="gb2-col-5">
                <?php Components::card_open(__('Smart Catalog progress', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                    <div style="margin-bottom:12px"><?php Components::pill($pill[0], $pill[1]); ?></div>

                    <?php if ($notice !== '' && $notice !== 'saved') : ?>
                        <div class="notice notice-info inline" style="margin:0 0 12px"><p><?php echo esc_html($this->smart_catalog_notice_text($notice)); ?></p></div>
                    <?php endif; ?>

                    <div class="gb2-bar">
                        <div class="gb2-bar__top">
                            <span class="gb2-bar__label"><?php esc_html_e('Products with search words', 'geeky-bot'); ?></span>
                            <span class="gb2-bar__value"><?php echo esc_html(sprintf('%s / %s', number_format_i18n($counts['done']), number_format_i18n($searchable))); ?></span>
                        </div>
                        <div class="gb2-bar__track"><div class="gb2-bar__fill gb2-bar__fill--accent" style="width: <?php echo esc_attr($percent); ?>%"></div></div>
                    </div>

                    <div class="gb2-keyvalues" style="margin-top:14px">
                        <div class="gb2-keyvalue"><span><?php esc_html_e('Waiting', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($counts['waiting'])); ?></strong></div>
                        <div class="gb2-keyvalue"><span><?php esc_html_e('Failed', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($counts['failed'])); ?></strong></div>
                        <div class="gb2-keyvalue"><span><?php esc_html_e('Turned off per product', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($counts['off'])); ?></strong></div>
                        <?php if (!empty($state['usage']['calls'])) : ?>
                            <div class="gb2-keyvalue"><span><?php esc_html_e('AI used so far', 'geeky-bot'); ?></span><strong style="font-size:var(--gb2-t-sm);font-weight:500"><?php echo esc_html(sprintf(
                                /* translators: 1: AI calls, 2: input tokens, 3: output tokens. */
                                __('%1$s calls · %2$s tokens in · %3$s out', 'geeky-bot'),
                                number_format_i18n($state['usage']['calls']),
                                number_format_i18n($state['usage']['input']),
                                number_format_i18n($state['usage']['output'])
                            )); ?></strong></div>
                        <?php endif; ?>
                        <?php if ($enabled && $counts['waiting'] > 0) : ?>
                            <div class="gb2-keyvalue"><span><?php esc_html_e('AI calls to finish', 'geeky-bot'); ?></span><strong><?php echo esc_html(sprintf(
                                /* translators: 1: number of AI calls, 2: products per call. */
                                __('about %1$s (%2$d products each)', 'geeky-bot'),
                                number_format_i18n($calls),
                                SmartCatalogService::BATCH_SIZE
                            )); ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($state['status']) && $state['status'] === 'error' && !empty($state['message'])) : ?>
                        <p class="gb2-note" style="margin:10px 0 0;color:var(--gb2-crit)"><?php echo esc_html(sprintf(
                            /* translators: %s: error message from the AI provider. */
                            __('Last attempt failed: %s. It retries automatically in 15 minutes.', 'geeky-bot'),
                            $state['message']
                        )); ?></p>
                    <?php elseif (!empty($state['status']) && $state['status'] === 'paused_budget') : ?>
                        <p class="gb2-note" style="margin:10px 0 0"><?php esc_html_e('Paused to keep part of your daily AI limit free for shopper chat. It continues automatically.', 'geeky-bot'); ?></p>
                    <?php endif; ?>

                    <?php if ($enabled && $connection !== null) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb2-inline" style="margin-top:14px">
                            <?php wp_nonce_field('geekybot_smart_catalog_action'); ?>
                            <input type="hidden" name="action" value="geekybot_smart_catalog_action" />
                            <?php if ($counts['waiting'] > 0) : ?>
                                <button type="submit" name="op" value="run" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Write the next 20 now', 'geeky-bot'); ?></button>
                            <?php endif; ?>
                            <?php if ($counts['failed'] > 0) : ?>
                                <button type="submit" name="op" value="retry" class="gb2-btn"><?php esc_html_e('Retry failed', 'geeky-bot'); ?></button>
                            <?php endif; ?>
                            <?php if ($counts['done'] > 0) : ?>
                                <button type="submit" name="op" value="regenerate" class="gb2-btn gb2-btn--danger" data-gb-confirm="<?php esc_attr_e('Rewrite the search words for every product? Words you removed by hand come back, and each product costs AI calls again.', 'geeky-bot'); ?>"><?php esc_html_e('Rewrite all', 'geeky-bot'); ?></button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <?php if ($level === 'rescue') :
                        $rescue_counts = isset($rescue_state['counts']) ? (array) $rescue_state['counts'] : array();
                        $sc_state_usage = isset($state['rescue_usage']) ? (array) $state['rescue_usage'] : array(); ?>
                        <div class="gb2-keyvalues" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--gb2-line-soft)">
                            <div class="gb2-keyvalue"><span><?php esc_html_e('Searches rescued', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n(absint($rescue_counts['rescued'] ?? 0))); ?></strong></div>
                            <div class="gb2-keyvalue"><span><?php esc_html_e('Nothing in the store fitted', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n(absint($rescue_counts['nothing_fits'] ?? 0))); ?></strong></div>
                            <div class="gb2-keyvalue"><span><?php esc_html_e('Answered from memory (free)', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n(absint($rescue_counts['cached'] ?? 0))); ?></strong></div>
                            <?php if (!empty($sc_state_usage['calls'])) : ?>
                                <div class="gb2-keyvalue"><span><?php esc_html_e('Rescue AI used', 'geeky-bot'); ?></span><strong style="font-size:var(--gb2-t-sm);font-weight:500"><?php echo esc_html(sprintf(
                                    /* translators: 1: AI calls, 2: input tokens, 3: output tokens. */
                                    __('%1$s calls · %2$s tokens in · %3$s out', 'geeky-bot'),
                                    number_format_i18n($sc_state_usage['calls']),
                                    number_format_i18n($sc_state_usage['input']),
                                    number_format_i18n($sc_state_usage['output'])
                                )); ?></strong></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php $this->smart_catalog_samples(); ?>
                <?php Components::card_close(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * The language name in the admin's own language when WordPress knows it.
     *
     * @param string $code ISO 639-1 code.
     * @param string $name English name.
     * @return string
     */
    private function smart_catalog_language_label($code, $name) {
        $labels = array(
            'en' => __('English', 'geeky-bot'),
            'es' => __('Spanish', 'geeky-bot'),
            'fr' => __('French', 'geeky-bot'),
            'de' => __('German', 'geeky-bot'),
            'it' => __('Italian', 'geeky-bot'),
            'pt' => __('Portuguese', 'geeky-bot'),
            'nl' => __('Dutch', 'geeky-bot'),
            'ru' => __('Russian', 'geeky-bot'),
            'ar' => __('Arabic', 'geeky-bot'),
            'zh' => __('Chinese', 'geeky-bot'),
            'ja' => __('Japanese', 'geeky-bot'),
            'ko' => __('Korean', 'geeky-bot'),
        );

        return isset($labels[$code]) ? $labels[$code] : $name;
    }

    /**
     * The three most recently written products, so the merchant sees real output.
     *
     * @return void
     */
    private function smart_catalog_samples() {
        $recent = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'meta_key' => SmartCatalogService::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Three rows on an admin screen.
            'meta_value' => 'done', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Three rows on an admin screen.
            'orderby' => 'modified',
            'no_found_rows' => true,
        ));
        if (empty($recent)) {
            return;
        }
        ?>
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--gb2-line-soft)">
            <p style="margin:0 0 8px;font-size:var(--gb2-t-sm);font-weight:600"><?php esc_html_e('Examples', 'geeky-bot'); ?></p>
            <?php foreach ($recent as $post) :
                $terms = array_slice(SmartCatalogService::effective_terms($post->ID), 0, 8); ?>
                <div style="margin-bottom:10px">
                    <a class="gb2-link" style="margin:0;font-size:var(--gb2-t-sm)" href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
                    <div class="gb2-chips" style="margin-top:5px">
                        <?php foreach ($terms as $term) : ?><span><?php echo esc_html($term); ?></span><?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @param string $notice Result code from handle_smart_catalog_action().
     * @return string
     */
    private function smart_catalog_notice_text($notice) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only result counts from our own redirect.
        $count = isset($_GET['gb_sc_count']) ? absint($_GET['gb_sc_count']) : 0;
        $failed = isset($_GET['gb_sc_failed']) ? absint($_GET['gb_sc_failed']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        switch ($notice) {
            case 'ran':
                /* translators: 1: products given words, 2: products that failed. */
                return sprintf(__('Words written for %1$d products (%2$d failed). The rest continue in the background.', 'geeky-bot'), $count, $failed);
            case 'error':
                return __('The AI call failed. The details are shown below; nothing was changed.', 'geeky-bot');
            case 'paused':
                return __('Paused to keep part of your daily AI limit free for shopper chat.', 'geeky-bot');
            case 'retry':
            case 'regenerate':
                /* translators: %d: number of products queued. */
                return sprintf(__('%d products queued. Words are written in the background.', 'geeky-bot'), $count);
        }

        return '';
    }

    /**
     * Suggestions learned from missed and rescued searches.
     *
     * @return void
     */
    private function search_learning_section() {
        $licensed = SearchLearningService::licensed();
        $active = SearchLearningService::active();
        $suggestions = SearchLearningService::suggestions();
        $last_run = get_option(SearchLearningService::OPTION . '_last_run', array());
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result flag from our own redirect.
        $notice = isset($_GET['gb_learn']) ? sanitize_key(wp_unslash($_GET['gb_learn'])) : '';

        $open = array_filter($suggestions, function ($s) {
            return ($s['status'] ?? '') === 'new';
        });
        $not_sold = array_filter($suggestions, function ($s) {
            return ($s['status'] ?? '') === 'not_sold';
        });
        $accepted = count(array_filter($suggestions, function ($s) {
            return ($s['status'] ?? '') === 'accepted';
        }));
        $by_count = function ($a, $b) {
            return (int) $b['count'] <=> (int) $a['count'];
        };
        uasort($open, $by_count);
        uasort($not_sold, $by_count);
        ?>
        <div class="gb2-grid" id="gb-search-learning">
            <div class="gb2-col-7">
                <?php Components::card_open(__('Suggested synonyms', 'geeky-bot'), $accepted ? sprintf(
                    /* translators: %d: number of approved suggestions. */
                    _n('%d approved', '%d approved', $accepted, 'geeky-bot'),
                    $accepted
                ) : '', false, 'gb2-fill'); ?>
                    <?php if ($notice !== '') : ?>
                        <div class="notice notice-info inline" style="margin:0 0 12px"><p><?php echo esc_html($this->search_learning_notice($notice)); ?></p></div>
                    <?php endif; ?>

                    <?php if (!$licensed) : ?>
                        <?php Components::empty_state(
                            __('Learn from missed searches with Commerce Pro', 'geeky-bot'),
                            __('Commerce Pro reads the searches that found nothing and suggests the synonyms that would have found your products. You approve each one.', 'geeky-bot'),
                            array('label' => __('See Commerce Pro', 'geeky-bot'), 'url' => admin_url('admin.php?page=geekybot-addons'))
                        ); ?>
                    <?php elseif (empty($open)) : ?>
                        <?php Components::empty_state(
                            __('No suggestions waiting', 'geeky-bot'),
                            $active
                                ? __('Missed searches are checked once a day. New suggestions appear here for your approval.', 'geeky-bot')
                                : (SmartCatalogService::connection() === null
                                    ? __('Save an OpenAI key in AI & Privacy to check missed searches.', 'geeky-bot')
                                    : __('Choose Smart Catalog or Smart Catalog + Rescue under Search level to check missed searches.', 'geeky-bot'))
                        ); ?>
                    <?php else : ?>
                        <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
                            esc_html_e('Shoppers searched these words and found nothing. Approving one adds it to your custom synonyms, so the next search finds your products without any AI call.', 'geeky-bot'); ?></p>
                        <div class="gb2-scroll">
                            <table class="gb2-table">
                                <thead><tr>
                                    <th><?php esc_html_e('Shoppers typed', 'geeky-bot'); ?></th>
                                    <th><?php esc_html_e('Search your products for', 'geeky-bot'); ?></th>
                                    <th><?php esc_html_e('Times', 'geeky-bot'); ?></th>
                                    <th></th>
                                </tr></thead>
                                <tbody>
                                <?php foreach (array_slice($open, 0, 25, true) as $key => $suggestion) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($key); ?></strong>
                                            <?php if (!empty($suggestion['examples'][0]) && $suggestion['examples'][0] !== $key) : ?>
                                                <div class="gb2-note" style="margin:3px 0 0">“<?php echo esc_html($suggestion['examples'][0]); ?>”</div>
                                            <?php endif; ?></td>
                                        <td><div class="gb2-chips"><?php foreach ((array) $suggestion['maps_to'] as $word) : ?><span><?php echo esc_html($word); ?></span><?php endforeach; ?></div></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $suggestion['count'])); ?></td>
                                        <td style="text-align:right;white-space:nowrap">
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                                <?php wp_nonce_field('geekybot_search_learning_action'); ?>
                                                <input type="hidden" name="action" value="geekybot_search_learning_action" />
                                                <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>" />
                                                <button type="submit" name="op" value="accept" class="gb2-btn gb2-btn--primary"><?php esc_html_e('Add synonym', 'geeky-bot'); ?></button>
                                                <button type="submit" name="op" value="dismiss" class="gb2-btn"><?php esc_html_e('Dismiss', 'geeky-bot'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($active) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb2-inline" style="margin-top:14px">
                            <?php wp_nonce_field('geekybot_search_learning_action'); ?>
                            <input type="hidden" name="action" value="geekybot_search_learning_action" />
                            <button type="submit" name="op" value="run" class="gb2-btn"><?php esc_html_e('Check missed searches now', 'geeky-bot'); ?></button>
                            <?php if (!empty($last_run['at'])) : ?>
                                <span class="gb2-note" style="margin:0"><?php echo esc_html(sprintf(
                                    /* translators: %s: date and time of the last check. */
                                    __('Last checked %s', 'geeky-bot'),
                                    mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last_run['at'])
                                )); ?></span>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                <?php Components::card_close(); ?>
            </div>

            <div class="gb2-col-5">
                <?php Components::card_open(__('Wanted, but not in your store', 'geeky-bot'), '', false, 'gb2-fill'); ?>
                    <p style="margin:0 0 12px;font-size:var(--gb2-t-sm);line-height:1.55;color:var(--gb2-mute)"><?php
                        esc_html_e('Shoppers asked for these and nothing you sell fits. They are not synonyms, but they may be worth stocking.', 'geeky-bot'); ?></p>
                    <?php if (empty($not_sold)) : ?>
                        <p class="gb2-note" style="margin:0"><?php esc_html_e('Nothing yet.', 'geeky-bot'); ?></p>
                    <?php else : ?>
                        <div class="gb2-keyvalues">
                            <?php foreach (array_slice($not_sold, 0, 12, true) as $key => $suggestion) : ?>
                                <div class="gb2-keyvalue"><span><?php echo esc_html($key); ?></span><strong><?php echo esc_html(sprintf(
                                    /* translators: %s: number of searches. */
                                    _n('%s search', '%s searches', (int) $suggestion['count'], 'geeky-bot'),
                                    number_format_i18n((int) $suggestion['count'])
                                )); ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php Components::card_close(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param string $notice Result code.
     * @return string
     */
    private function search_learning_notice($notice) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only counts from our own redirect.
        $new = isset($_GET['gb_learn_new']) ? absint($_GET['gb_learn_new']) : 0;
        $not_sold = isset($_GET['gb_learn_not_sold']) ? absint($_GET['gb_learn_not_sold']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        switch ($notice) {
            case 'ran':
                /* translators: 1: new suggestions, 2: phrases for products not sold. */
                return sprintf(__('Checked. %1$d new suggestions and %2$d requests for products you do not sell.', 'geeky-bot'), $new, $not_sold);
            case 'error':
                return __('The AI check failed. Nothing was changed; it runs again tomorrow.', 'geeky-bot');
            case 'accepted':
                return __('Synonym added. Shoppers who search that word now find your products.', 'geeky-bot');
            case 'full':
                return __('Your custom synonyms are full (6,000 characters). Remove some in the search settings, then try again.', 'geeky-bot');
            case 'dismissed':
                return __('Suggestion dismissed.', 'geeky-bot');
        }

        return '';
    }

    public function handle_search_learning_action() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage search suggestions.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_search_learning_action');

        $op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';
        $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
        $args = array('page' => 'geekybot-product-assistant');

        if ($op === 'run') {
            $result = (new SearchLearningService())->run();
            $args['gb_learn'] = $result['status'] === 'error' ? 'error' : 'ran';
            $args['gb_learn_new'] = $result['from_ai'] + $result['from_rescue'];
            $args['gb_learn_not_sold'] = $result['not_sold'];
        } elseif ($op === 'accept' && $key !== '') {
            $args['gb_learn'] = SearchLearningService::accept($key) ? 'accepted' : 'full';
        } elseif ($op === 'dismiss' && $key !== '') {
            SearchLearningService::dismiss($key);
            $args['gb_learn'] = 'dismissed';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')) . '#gb-search-learning');
        exit;
    }

    public function handle_smart_catalog_action() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Smart Catalog.', 'geeky-bot'));
        }

        check_admin_referer('geekybot_smart_catalog_action');

        $op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';
        $args = array('page' => 'geekybot-product-assistant');

        if ($op === 'run') {
            $result = (new SmartCatalogService())->run_batch();
            $map = array('error' => 'error', 'paused_budget' => 'paused');
            $args['gb_sc'] = isset($map[$result['status']]) ? $map[$result['status']] : 'ran';
            $args['gb_sc_count'] = $result['processed'];
            $args['gb_sc_failed'] = $result['failed'];
        } elseif ($op === 'retry') {
            $args['gb_sc'] = 'retry';
            $args['gb_sc_count'] = SmartCatalogService::retry_failed();
        } elseif ($op === 'regenerate') {
            $args['gb_sc'] = 'regenerate';
            $args['gb_sc_count'] = SmartCatalogService::regenerate_all();
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')) . '#gb-smart-catalog');
        exit;
    }

    /**
     * Example phrases an admin types into the widget to check intent routing.
     *
     * i18n-exempt: the phrases are deliberately not translatable. They are
     * INPUT, and an input only proves anything if the language pack for the
     * store's language actually matches it -- a translator supplying plausible
     * German would ship examples that quietly return nothing. The group
     * headings around them are translated, because those are read.
     *
     * A German set belongs in Search/Data/de/, beside the phrases that decide
     * whether it matches, not in the .pot.
     */
    private function nlp_action_examples() { ?>
            <?php // Shown inside a collapsed section on Product Search, so no card of its own. ?>
            <p class="gb2-note" style="margin-top:0"><?php
                esc_html_e('Type these into the chat on your store to check each kind of request is understood.', 'geeky-bot'); ?></p>
            <div class="gb2-grid">
                <?php $this->nlp_action_group(__('Search', 'geeky-bot'), array('comfortable shoes size 42 red and white', 'blue hoodie under 60', 'not too expensive walking shoes')); ?>
                <?php $this->nlp_action_group(__('Compare', 'geeky-bot'), array('compare first and third', 'what is difference between hoodie and hoodie with logo', 'compare cheaper one and second')); ?>
                <?php $this->nlp_action_group(__('Cart', 'geeky-bot'), array('add second product', 'remove first item', 'make belt quantity 3')); ?>
                <?php $this->nlp_action_group(__('Orders', 'geeky-bot'), array('what did I buy last time', 'show my latest order', 'where is my parcel')); ?>
                <?php $this->nlp_action_group(__('Deals', 'geeky-bot'), array('any promo code', 'cheapest today', 'show sale items')); ?>
                <?php $this->nlp_action_group(__('Human handoff', 'geeky-bot'), array('problem with my order', 'I want to complain', 'talk to human')); ?>
            </div>
    <?php }

    private function nlp_action_group($title, $phrases) { ?>
        <div class="gb2-col-4">
            <strong style="display:block;margin-bottom:6px;font-size:var(--gb2-t-sm);font-weight:600"><?php echo esc_html($title); ?></strong>
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

    private function settings_table_ai($settings) {
        // Per-mode readiness, carried over from the retired Answer Mode page.
        $zywrap_ready = Settings::has_secret('zywrap_api_key') && !empty($settings['zywrap_endpoint']);
        $openai_ready = Settings::has_secret('openai_api_key');
        ?>
        <div class="gb2-radios" role="radiogroup" aria-label="<?php esc_attr_e('Answer mode', 'geeky-bot'); ?>">
            <label class="gb2-radio <?php echo esc_attr($settings['provider_mode'] === 'local' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="local" <?php checked($settings['provider_mode'], 'local'); ?> /><span><strong><?php esc_html_e('Local grounded mode (default)', 'geeky-bot'); ?></strong><em><?php esc_html_e('Calls no language model. Answers are assembled from your catalog and selected policy pages.', 'geeky-bot'); ?></em><em class="gb2-radio__status is-ok"><?php esc_html_e('Always available', 'geeky-bot'); ?></em></span></label>
            <?php if (Settings::zywrap_visible()) : ?>
            <label class="gb2-radio <?php echo esc_attr($settings['provider_mode'] === 'zywrap' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="zywrap" <?php checked($settings['provider_mode'], 'zywrap'); ?> /><span><strong><?php esc_html_e('Zywrap endpoint', 'geeky-bot'); ?></strong><em><?php esc_html_e('Hosted AI endpoint for grounded answers.', 'geeky-bot'); ?></em><em class="gb2-radio__status<?php echo $zywrap_ready ? ' is-ok' : ''; ?>"><?php echo esc_html($zywrap_ready ? __('Endpoint and key saved', 'geeky-bot') : __('Needs an endpoint and key', 'geeky-bot')); ?></em></span></label>
            <?php endif; ?>
            <label class="gb2-radio <?php echo esc_attr($settings['provider_mode'] === 'openai' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="openai" <?php checked($settings['provider_mode'], 'openai'); ?> /><span><strong><?php esc_html_e('OpenAI BYOK', 'geeky-bot'); ?></strong><em><?php esc_html_e('Optional bring-your-own-key grounded answer mode.', 'geeky-bot'); ?></em><em class="gb2-radio__status<?php echo $openai_ready ? ' is-ok' : ''; ?>"><?php echo esc_html($openai_ready ? __('Key saved', 'geeky-bot') : __('Needs an API key', 'geeky-bot')); ?></em></span></label>
        </div>
        <div class="gb2-sgrid">
            <?php if (Settings::zywrap_visible()) : ?>
            <label class="gb2-sfield gb2-sfield--wide"><span><?php esc_html_e('Zywrap endpoint', 'geeky-bot'); ?></span><input id="zywrap_endpoint" name="zywrap_endpoint" type="url" value="<?php echo esc_attr($settings['zywrap_endpoint']); ?>" placeholder="https://api.example.com/..." /><em><?php esc_html_e('Only used when Zywrap mode is selected.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Zywrap API key', 'geeky-bot'); ?></span><input id="zywrap_api_key" name="zywrap_api_key" type="password" value="<?php echo esc_attr($settings['zywrap_api_key'] ? '••••••••' : ''); ?>" autocomplete="new-password" /><em><?php esc_html_e('Saved secret is not displayed after save.', 'geeky-bot'); ?></em></label>
            <?php endif; ?>
            <label class="gb2-sfield"><span><?php esc_html_e('OpenAI API key', 'geeky-bot'); ?></span><input id="openai_api_key" name="openai_api_key" type="password" value="<?php echo esc_attr($settings['openai_api_key'] ? '••••••••' : ''); ?>" autocomplete="new-password" /><em><?php esc_html_e('Saved secret is not displayed after save.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('OpenAI model', 'geeky-bot'); ?></span><input id="openai_model" name="openai_model" type="text" value="<?php echo esc_attr($settings['openai_model']); ?>" /><em><?php esc_html_e('Used only in OpenAI BYOK mode.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('AI answer token limit', 'geeky-bot'); ?></span><input id="ai_max_tokens" name="ai_max_tokens" type="number" min="120" max="1200" value="<?php echo esc_attr(absint($settings['ai_max_tokens'])); ?>" /><em><?php esc_html_e('Keeps generated answers short and controlled.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Daily AI call budget', 'geeky-bot'); ?></span><input id="ai_daily_call_cap" name="ai_daily_call_cap" type="number" min="<?php echo esc_attr(AiBudgetService::DAILY_MIN); ?>" max="<?php echo esc_attr(AiBudgetService::DAILY_MAX); ?>" value="<?php echo esc_attr(AiBudgetService::daily_cap()); ?>" /><em><?php esc_html_e('Site-wide limit on paid AI calls per day, staff included.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Monthly AI call cap', 'geeky-bot'); ?></span><input id="ai_monthly_call_cap" name="ai_monthly_call_cap" type="number" min="<?php echo esc_attr(AiBudgetService::MONTHLY_MIN); ?>" max="<?php echo esc_attr(AiBudgetService::MONTHLY_MAX); ?>" value="<?php echo esc_attr(AiBudgetService::monthly_cap()); ?>" /><em><?php esc_html_e('Hard stop for the month. After it, shoppers get local answers.', 'geeky-bot'); ?></em></label>
        </div>
        <?php $this->ai_budget_note(); ?>
        <?php $this->ai_secret_storage_note(); ?>
    <?php }

    /**
     * Remaining site-wide AI budget, so the ceiling is a visible dial rather
     * than a silent failure the merchant only notices as missing answers.
     */
    private function ai_budget_note() {
        $budget = AiBudgetService::status();
        ?>
        <div class="gb2-snote"><strong><?php esc_html_e('AI spend ceiling', 'geeky-bot'); ?></strong><span><?php
            printf(
                /* translators: 1: calls used today, 2: daily cap, 3: calls used this month, 4: monthly cap. */
                esc_html__('Today: %1$s of %2$s calls used. This month: %3$s of %4$s.', 'geeky-bot'),
                esc_html(number_format_i18n($budget['daily']['used'])),
                esc_html(number_format_i18n($budget['daily']['cap'])),
                esc_html(number_format_i18n($budget['monthly']['used'])),
                esc_html(number_format_i18n($budget['monthly']['cap']))
            );
            if ($budget['blocked_count'] > 0) {
                echo ' ';
                printf(
                    /* translators: %s: number of blocked calls. */
                    esc_html__('%s calls were declined today after a budget was reached; those shoppers received local grounded answers.', 'geeky-bot'),
                    esc_html(number_format_i18n($budget['blocked_count']))
                );
            }
        ?></span></div>
        <?php
    }

    /**
     * How provider keys are stored on this installation. An install that
     * cannot encrypt has to say so instead of failing invisibly.
     */
    private function ai_secret_storage_note() {
        $state = Settings::secret_storage_state();
        if ($state === 'unavailable') {
            ?>
            <div class="notice notice-error inline"><p><strong><?php esc_html_e('API keys cannot be stored on this server.', 'geeky-bot'); ?></strong> <?php esc_html_e('Geeky Bot encrypts provider keys at rest, and neither the libsodium nor the OpenSSL PHP extension is available here. Saving a key will be refused until your host enables one. Local grounded mode is unaffected.', 'geeky-bot'); ?></p></div>
            <?php
            return;
        }

        if ($state === 'plaintext') {
            ?>
            <div class="notice notice-warning inline"><p><strong><?php esc_html_e('A provider key is still stored unencrypted.', 'geeky-bot'); ?></strong> <?php esc_html_e('It was saved by an earlier version. Re-enter and save the key to store it encrypted.', 'geeky-bot'); ?></p></div>
            <?php
            return;
        }

        if ($state === 'encrypted') {
            ?>
            <div class="gb2-snote"><strong><?php esc_html_e('Key storage', 'geeky-bot'); ?></strong><span><?php esc_html_e('Encrypted with this site\'s WordPress salts and never sent to the storefront. A copied database cannot read them.', 'geeky-bot'); ?></span></div>
            <?php
        }
    }

    private function settings_table_privacy($settings) { ?>
        <div class="gb2-sgrid">
            <label class="gb2-stoggle"><input type="hidden" name="chat_history_enabled" value="no" /><input type="checkbox" name="chat_history_enabled" value="yes" <?php checked($settings['chat_history_enabled'], 'yes'); ?> /> <span><strong><?php esc_html_e('Save conversation history', 'geeky-bot'); ?></strong><em><?php esc_html_e('Needed for analytics and the unanswered-question review.', 'geeky-bot'); ?></em></span></label>
            <label class="gb2-stoggle"><input type="hidden" name="allow_guest_sessions" value="no" /><input type="checkbox" name="allow_guest_sessions" value="yes" <?php checked($settings['allow_guest_sessions'], 'yes'); ?> /> <span><strong><?php esc_html_e('Save guest conversations', 'geeky-bot'); ?></strong><em><?php esc_html_e('When off, guests can still chat, but nothing they send is stored.', 'geeky-bot'); ?></em></span></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Retention days', 'geeky-bot'); ?></span><input id="retention_days" name="retention_days" type="number" min="1" max="365" value="<?php echo esc_attr(absint($settings['retention_days'])); ?>" /><em><?php esc_html_e('Older conversations are deleted automatically.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Rate limit shopper messages', 'geeky-bot'); ?></span><input id="rate_limit_messages" name="rate_limit_messages" type="number" min="20" max="1000" value="<?php echo esc_attr(absint($settings['rate_limit_messages'])); ?>" /><em><?php esc_html_e('Per visitor, per window.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Rate limit window minutes', 'geeky-bot'); ?></span><input id="rate_limit_window_minutes" name="rate_limit_window_minutes" type="number" min="1" max="60" value="<?php echo esc_attr(absint($settings['rate_limit_window_minutes'])); ?>" /><em><?php esc_html_e('Admins, shop managers and editors are never limited.', 'geeky-bot'); ?></em></label>
            <label class="gb2-sfield"><span><?php esc_html_e('Trusted proxies in front of this site', 'geeky-bot'); ?></span><input id="trusted_proxy_count" name="trusted_proxy_count" type="number" min="0" max="10" value="<?php echo esc_attr(min(10, absint(isset($settings['trusted_proxy_count']) ? $settings['trusted_proxy_count'] : 0))); ?>" /><em><?php esc_html_e('Leave at 0 unless a CDN such as Cloudflare sits in front of the store (then 1). Too high lets visitors fake their address to dodge limits.', 'geeky-bot'); ?></em></label>
            <label class="gb2-stoggle gb2-stoggle--danger"><input type="hidden" name="delete_data_on_uninstall" value="no" /><input type="checkbox" name="delete_data_on_uninstall" value="yes" <?php checked(isset($settings['delete_data_on_uninstall']) ? $settings['delete_data_on_uninstall'] : get_option('geekybot_delete_data_on_uninstall', 'no'), 'yes'); ?> /> <span><strong><?php esc_html_e('Delete data on uninstall', 'geeky-bot'); ?></strong><em><?php esc_html_e('Removes conversations, the product index and Commerce Pro data. Leave off on live stores.', 'geeky-bot'); ?></em></span></label>
        </div>
        <div class="gb2-snote"><strong><?php esc_html_e('Conversation data tools', 'geeky-bot'); ?></strong><span><?php esc_html_e('Export or delete stored conversations on the Conversations page.', 'geeky-bot'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Open conversation data tools', 'geeky-bot'); ?></a></span></div>
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
        if ($status === 'running') {
            $progress = ProductIndexService::rebuild_progress();
            return sprintf(
                /* translators: %d: percentage complete. */
                __('Rebuilding %d%%', 'geeky-bot'),
                absint($progress['percent'])
            );
        }
        if ($status === 'waiting_for_woocommerce') {
            return __('Waiting', 'geeky-bot');
        }
        if ($status === 'scheduled') {
            return __('Scheduled', 'geeky-bot');
        }
        if ($status === 'pending') {
            return __('Pending', 'geeky-bot');
        }

        return __('Up to date', 'geeky-bot');
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
                /* translators: %s: comma-separated list of search terms the expansion added. */
                $expansions[] = sprintf(__('Added search terms: %s', 'geeky-bot'), implode(', ', array_slice($added, 0, 8)));
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
