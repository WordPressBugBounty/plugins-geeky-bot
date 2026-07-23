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

        add_submenu_page('geekybot', __('Dashboard', 'geeky-bot'), __('Dashboard', 'geeky-bot'), 'manage_options', 'geekybot', array($this, 'dashboard'));
        add_submenu_page('geekybot', __('Setup Wizard', 'geeky-bot'), __('Setup Wizard', 'geeky-bot'), 'manage_options', 'geekybot-setup', array($this, 'setup_wizard'));
        add_submenu_page('geekybot', __('Guided Demo', 'geeky-bot'), __('Guided Demo', 'geeky-bot'), 'manage_options', 'geekybot-guided-demo', array($this, 'guided_demo'));
        add_submenu_page('geekybot', __('Storefront Widget', 'geeky-bot'), __('Storefront Widget', 'geeky-bot'), 'manage_options', 'geekybot-widget', array($this, 'chat_widget'));
        add_submenu_page('geekybot', __('Product Assistant', 'geeky-bot'), __('Product Assistant', 'geeky-bot'), 'manage_options', 'geekybot-product-assistant', array($this, 'product_assistant'));
        add_submenu_page('geekybot', __('Store Knowledge', 'geeky-bot'), __('Store Knowledge', 'geeky-bot'), 'manage_options', 'geekybot-store-knowledge', array($this, 'store_knowledge'));
        add_submenu_page('geekybot', __('Conversations', 'geeky-bot'), __('Conversations', 'geeky-bot'), 'manage_options', 'geekybot-conversations', array($this, 'conversations'));
        add_submenu_page('geekybot', __('Analytics', 'geeky-bot'), __('Analytics', 'geeky-bot'), 'manage_options', 'geekybot-analytics', array($this, 'analytics'));
        add_submenu_page('geekybot', __('Integrations', 'geeky-bot'), __('Integrations', 'geeky-bot'), 'manage_options', 'geekybot-integrations', array($this, 'integrations'));
        add_submenu_page('geekybot', __('Settings', 'geeky-bot'), __('Settings', 'geeky-bot'), 'manage_options', 'geekybot-settings', array($this, 'settings'));
        add_submenu_page('geekybot', __('Add-ons', 'geeky-bot'), __('Add-ons', 'geeky-bot'), 'manage_options', 'geekybot-addons', array($this, 'pro'));
        if (!defined('GBCP_VERSION')) {
            add_submenu_page('geekybot', __('Commerce Pro', 'geeky-bot'), __('Commerce Pro', 'geeky-bot'), 'manage_options', 'geekybot-commerce-pro', array($this, 'commerce_pro_promo'));
        }
    }

    public function assets($hook) {
        if (strpos((string) $hook, 'geekybot') === false) {
            return;
        }
        wp_enqueue_style('geekybot-admin', GEEKYBOT_URL . 'assets/css/admin.css', array(), GEEKYBOT_VERSION);
        wp_enqueue_media();
        wp_enqueue_script('geekybot-admin', GEEKYBOT_URL . 'assets/js/admin.js', array(), GEEKYBOT_VERSION, true);
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
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-dashboard geekybot-command-dashboard geekybot-cockpit-dashboard-v3">
            <?php $this->admin_notice_indexed(); ?>

            <section class="gb-cockpit-hero" aria-label="<?php esc_attr_e('Geeky Bot assistant dashboard overview', 'geeky-bot'); ?>">
                <div class="gb-cockpit-hero__main">
                    <div class="gb-brand-head">
                        <?php $this->brand_lockup(__('AI sales assistant for WooCommerce', 'geeky-bot')); ?>
                    </div>
                    <h1><?php esc_html_e('Sales assistant dashboard', 'geeky-bot'); ?></h1>
                    <p><?php esc_html_e('Manage the WooCommerce shopping assistant from one focused dashboard: product discovery, buyer language, cart actions, policy answers, and the real questions shoppers ask.', 'geeky-bot'); ?></p>
                    <div class="gb-cockpit-actions">
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant')); ?>"><?php esc_html_e('Tune product intelligence', 'geeky-bot'); ?></a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Review shopper questions', 'geeky-bot'); ?></a>
                        <a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open live store', 'geeky-bot'); ?></a>
                    </div>
                    <div class="gb-cockpit-signal-bar" aria-label="<?php esc_attr_e('Assistant operating signals', 'geeky-bot'); ?>">
                        <span><strong><?php echo esc_html(number_format_i18n($ctx['indexed_count'])); ?></strong><em><?php esc_html_e('Products indexed', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html(number_format_i18n($messages_count)); ?></strong><em><?php esc_html_e('Messages learned', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html(number_format_i18n($handled_count)); ?></strong><em><?php esc_html_e('Questions handled', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($fallback_short_label); ?></strong><em><?php esc_html_e('Search fallback', 'geeky-bot'); ?></em></span>
                    </div>
                </div>
                <aside class="gb-cockpit-readiness <?php echo esc_attr($completion >= 100 ? 'is-ready' : 'is-warning'); ?>">
                    <div class="gb-cockpit-ring" style="--gb-progress: <?php echo esc_attr($completion); ?>;"><strong><?php echo esc_html($completion); ?>%</strong><span><?php echo esc_html($readiness_label); ?></span></div>
                    <div class="gb-cockpit-readiness__body">
                        <p class="gb-kicker"><?php esc_html_e('Readiness monitor', 'geeky-bot'); ?></p>
                        <h2><?php echo esc_html($attention_label); ?></h2>
                        <ul>
                            <li class="<?php echo esc_attr($ctx['wc_ready'] ? 'is-ok' : 'is-warn'); ?>"><?php esc_html_e('WooCommerce catalog connected', 'geeky-bot'); ?></li>
                            <li class="<?php echo esc_attr($ctx['indexed_count'] > 0 && $natural_search_ready ? 'is-ok' : 'is-warn'); ?>"><?php echo esc_html($search_status_label); ?></li>
                            <li class="<?php echo esc_attr($ctx['policy_count'] > 0 ? 'is-ok' : 'is-warn'); ?>"><?php esc_html_e('Policy answers grounded', 'geeky-bot'); ?></li>
                            <li class="<?php echo esc_attr($widget_ready ? 'is-ok' : 'is-warn'); ?>"><?php esc_html_e('Storefront widget enabled', 'geeky-bot'); ?></li>
                        </ul>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-setup')); ?>"><?php esc_html_e('Open readiness checklist', 'geeky-bot'); ?></a>
                    </div>
                </aside>
            </section>

            <nav class="gb-cockpit-nav" aria-label="<?php esc_attr_e('Geeky Bot admin sections', 'geeky-bot'); ?>">
                <?php $this->admin_link('geekybot-setup', __('Setup', 'geeky-bot')); ?>
                <?php $this->admin_link('geekybot-guided-demo', __('Guided Demo', 'geeky-bot')); ?>
                <?php $this->admin_link('geekybot-widget', __('Widget', 'geeky-bot')); ?>
                <?php $this->admin_link('geekybot-product-assistant', __('Product assistant', 'geeky-bot')); ?>
                <?php $this->admin_link('geekybot-store-knowledge', __('Store knowledge', 'geeky-bot')); ?>
                <?php $this->admin_link('geekybot-conversations', __('Conversations', 'geeky-bot')); ?>
                <?php $this->admin_link('geekybot-analytics', __('Analytics', 'geeky-bot')); ?>
                <?php $this->admin_link('geekybot-integrations', __('Integrations', 'geeky-bot')); ?>
            </nav>

            <section class="gb-cockpit-main-grid">
                <div class="gb-cockpit-command-panel">
                    <div class="gb-cockpit-section-head">
                        <p class="gb-kicker"><?php esc_html_e('Mission control', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Next highest-impact actions', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Focus on the work that makes the assistant sell better: answer more buyer language, improve catalog signals, and keep policy answers safe.', 'geeky-bot'); ?></p>
                    </div>

                    <div class="gb-cockpit-priority-feature <?php echo esc_attr($review_count > 0 ? 'is-warning' : 'is-ready'); ?>">
                        <div>
                            <span><?php echo esc_html($review_count > 0 ? __('Priority 01', 'geeky-bot') : __('Healthy', 'geeky-bot')); ?></span>
                            <h3><?php echo esc_html($priority_heading); ?></h3>
                            <p><?php echo esc_html($review_count > 0 ? __('Turn repeated misses into better attributes, synonyms, selected policy pages, or Commerce Pro rules.', 'geeky-bot') : __('The current build is handling the latest reviewed shopper questions.', 'geeky-bot')); ?></p>
                        </div>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php echo esc_html($review_count > 0 ? __('Review questions', 'geeky-bot') : __('Open review center', 'geeky-bot')); ?></a>
                    </div>

                    <div class="gb-cockpit-task-matrix">
                        <a class="gb-cockpit-task <?php echo esc_attr($ctx['indexed_count'] > 0 && $natural_search_ready ? 'is-ready' : 'is-warning'); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant')); ?>">
                            <span><?php esc_html_e('Buyer search', 'geeky-bot'); ?></span>
                            <strong><?php echo esc_html($natural_search_ready ? __('Buyer phrases understood', 'geeky-bot') : __('Tune natural search', 'geeky-bot')); ?></strong>
                            <em><?php esc_html_e('Long phrases, size/color, price, synonyms and close matches.', 'geeky-bot'); ?></em>
                        </a>
                        <a class="gb-cockpit-task <?php echo esc_attr($ctx['policy_count'] > 0 ? 'is-ready' : 'is-warning'); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-store-knowledge')); ?>">
                            <span><?php esc_html_e('Policy grounding', 'geeky-bot'); ?></span>
                            <strong><?php echo esc_html($ctx['policy_count'] > 0 ? __('Safe answers available', 'geeky-bot') : __('Select policy pages', 'geeky-bot')); ?></strong>
                            <em><?php esc_html_e('Shipping, refunds, returns, payment and warranty answers.', 'geeky-bot'); ?></em>
                        </a>
                        <a class="gb-cockpit-task <?php echo esc_attr($widget_ready ? 'is-ready' : 'is-warning'); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-widget')); ?>">
                            <span><?php esc_html_e('Storefront widget', 'geeky-bot'); ?></span>
                            <strong><?php echo esc_html($widget_ready ? __('Live for shoppers', 'geeky-bot') : __('Not live yet', 'geeky-bot')); ?></strong>
                            <em><?php esc_html_e('Welcome copy, starter prompts, product cards and mobile layout.', 'geeky-bot'); ?></em>
                        </a>
                    </div>
                </div>

                <aside class="gb-cockpit-preview-panel">
                    <div class="gb-cockpit-section-head">
                        <p class="gb-kicker"><?php esc_html_e('Shopper view', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Live assistant preview', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('The assistant should feel like guided shopping, not a generic support widget.', 'geeky-bot'); ?></p>
                    </div>
                    <?php $this->widget_preview($settings); ?>
                    <div class="gb-cockpit-try-panel">
                        <span><?php esc_html_e('Try on storefront', 'geeky-bot'); ?></span>
                        <code>comfortable shoes size 42 red and white</code>
                        <code>compare cheaper one and second</code>
                        <code>add 2 hoodies to cart</code>
                    </div>
                    <div class="gb-cockpit-preview-actions">
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-widget')); ?>"><?php esc_html_e('Tune widget', 'geeky-bot'); ?></a>
                        <a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open storefront', 'geeky-bot'); ?></a>
                    </div>
                </aside>
            </section>

            <section class="gb-cockpit-intel-grid" aria-label="<?php esc_attr_e('Assistant intelligence summary', 'geeky-bot'); ?>">
                <article class="gb-cockpit-intel-card gb-cockpit-intel-card--dark">
                    <div class="gb-cockpit-section-head">
                        <p class="gb-kicker"><?php esc_html_e('Catalog intelligence', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Product discovery signals', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Search uses WooCommerce product data first, then shopper-language synonyms and close-match rules.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-cockpit-mini-metrics">
                        <span><strong><?php echo esc_html(number_format_i18n($ctx['indexed_count'])); ?></strong><em><?php esc_html_e('Products', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($natural_search_ready ? __('On', 'geeky-bot') : __('Off', 'geeky-bot')); ?></strong><em><?php esc_html_e('Natural search', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($fallback_mode); ?></strong><em><?php esc_html_e('Match mode', 'geeky-bot'); ?></em></span>
                    </div>
                    <div class="gb-cockpit-chip-cloud">
                        <span><?php esc_html_e('Product name', 'geeky-bot'); ?></span><span><?php esc_html_e('SKU', 'geeky-bot'); ?></span><span><?php esc_html_e('Categories', 'geeky-bot'); ?></span><span><?php esc_html_e('Tags', 'geeky-bot'); ?></span><span><?php esc_html_e('Attributes', 'geeky-bot'); ?></span><span><?php esc_html_e('Price phrases', 'geeky-bot'); ?></span><span><?php esc_html_e('Stock', 'geeky-bot'); ?></span><span><?php esc_html_e('Sale signals', 'geeky-bot'); ?></span>
                    </div>
                </article>

                <article class="gb-cockpit-intel-card">
                    <div class="gb-cockpit-section-head">
                        <p class="gb-kicker"><?php esc_html_e('Buyer language learned', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Conversation review signal', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Every unanswered shopper phrase becomes a clear signal for product data, synonyms, policy pages, or future sales rules.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-cockpit-language-bars">
                        <div><span><?php esc_html_e('Messages stored', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($messages_count)); ?></strong></div>
                        <div><span><?php esc_html_e('Now handled', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($handled_count)); ?></strong></div>
                        <div><span><?php esc_html_e('Needs review', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($review_count)); ?></strong></div>
                    </div>
                    <a class="gb-cockpit-text-link" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Open conversation review', 'geeky-bot'); ?></a>
                </article>

                <article class="gb-cockpit-intel-card">
                    <div class="gb-cockpit-section-head">
                        <p class="gb-kicker"><?php esc_html_e('Commerce actions', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Beyond simple product search', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Commerce Pro extends the assistant into buying actions while keeping answers grounded in store data.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-cockpit-action-list">
                        <span><?php esc_html_e('Natural product search', 'geeky-bot'); ?></span>
                        <span><?php esc_html_e('Product comparison', 'geeky-bot'); ?></span>
                        <span><?php esc_html_e('Cart add, update and remove', 'geeky-bot'); ?></span>
                        <span><?php esc_html_e('Checkout handoff', 'geeky-bot'); ?></span>
                        <span><?php esc_html_e('Order history', 'geeky-bot'); ?></span>
                        <span><?php esc_html_e('Coupons and deals', 'geeky-bot'); ?></span>
                    </div>
                </article>
            </section>

            <section class="gb-cockpit-qa-panel">
                <div class="gb-cockpit-section-head">
                    <p class="gb-kicker"><?php esc_html_e('Launch rehearsal', 'geeky-bot'); ?></p>
                    <h2><?php esc_html_e('Try examples generated from this store', 'geeky-bot'); ?></h2>
                    <p><?php esc_html_e('Guided Demo uses visible indexed products and approved policy pages, then labels which requests are Free and which buying actions require Commerce Pro.', 'geeky-bot'); ?></p>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><?php esc_html_e('Open Guided Demo', 'geeky-bot'); ?></a>
                </div>
                <div class="gb-cockpit-qa-grid">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><span><?php esc_html_e('Real catalog', 'geeky-bot'); ?></span><code><?php esc_html_e('product names, prices, attributes and sale state', 'geeky-bot'); ?></code></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><span><?php esc_html_e('Grounded policies', 'geeky-bot'); ?></span><code><?php esc_html_e('selected shipping, refund and store pages', 'geeky-bot'); ?></code></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><span><?php esc_html_e('Free examples', 'geeky-bot'); ?></span><code><?php esc_html_e('find, understand and choose products', 'geeky-bot'); ?></code></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><span><?php esc_html_e('Commerce Pro examples', 'geeky-bot'); ?></span><code><?php esc_html_e('variations, cart actions and checkout flow', 'geeky-bot'); ?></code></a>
                </div>
            </section>
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
            <?php $this->page_hero(__('First-run setup', 'geeky-bot'), __('Launch a useful WooCommerce shopping assistant through a resumable seven-step path. Every check uses the live store configuration, so you can leave and continue later without losing progress.', 'geeky-bot'), __('Guided launch', 'geeky-bot'), $hero_action_url, $hero_action_label); ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only first-run notice flag. ?>
            <?php if (!empty($_GET['gb_first_run'])) : ?>
                <div class="notice notice-info"><p><?php esc_html_e('Welcome to Geeky Bot. Complete the important checks below, or leave this page and return from Geeky Bot → Setup Wizard at any time.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php if (!empty($_GET['gb_onboarding'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Setup review status updated.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>

            <section class="gb-first-run-overview" aria-label="<?php esc_attr_e('First-run progress', 'geeky-bot'); ?>">
                <div class="gb-first-run-score" style="--gb-progress: <?php echo esc_attr($readiness['score']); ?>;">
                    <div class="gb-launch-ring"><strong><?php echo esc_html($readiness['score']); ?>%</strong><span><?php echo esc_html($readiness['score'] >= 100 ? __('Ready', 'geeky-bot') : $ready_count_label); ?></span></div>
                    <div>
                        <p class="gb-kicker"><?php esc_html_e('Live setup progress', 'geeky-bot'); ?></p>
                        <h2><?php echo esc_html($readiness['score'] >= 100 ? __('The assistant is ready for a complete rehearsal', 'geeky-bot') : __('Finish the remaining launch checks', 'geeky-bot')); ?></h2>
                        <p><?php echo esc_html($readiness_summary); ?></p>
                    </div>
                </div>
                <div class="gb-first-run-principles">
                    <span><strong><?php esc_html_e('WooCommerce first', 'geeky-bot'); ?></strong><em><?php esc_html_e('Products and the Guided Demo remain blocked until the store runtime is available.', 'geeky-bot'); ?></em></span>
                    <span><strong><?php esc_html_e('No forced AI account', 'geeky-bot'); ?></strong><em><?php esc_html_e('Local grounded mode works without an external provider.', 'geeky-bot'); ?></em></span>
                    <span><strong><?php esc_html_e('Resumable', 'geeky-bot'); ?></strong><em><?php esc_html_e('Leave and return without losing progress.', 'geeky-bot'); ?></em></span>
                </div>
            </section>

            <div class="gb-first-run-layout">
                <section class="gb-panel gb-first-run-path">
                    <div class="gb-panel-heading">
                        <p class="gb-kicker"><?php esc_html_e('Seven-step launch path', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Set up the assistant in dependency order', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Start with WooCommerce, prepare products and the search index, approve policy data, shape the widget, confirm privacy, then rehearse with real examples.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-launch-step-list">
                        <?php $this->setup_task_card(__('Store system check', 'geeky-bot'), $readiness['steps']['system'], __('Confirm WooCommerce, WordPress, PHP and the local REST runtime before configuring shopper features.', 'geeky-bot'), $ctx['wc_ready'] ? admin_url('admin.php?page=geekybot-setup#gb-system-checks') : $woocommerce_action['url'], $ctx['wc_ready'] ? __('Review system', 'geeky-bot') : $woocommerce_action['label'], '01'); ?>
                        <?php $this->setup_task_card(__('Answer mode', 'geeky-bot'), $readiness['steps']['provider'], $provider_detail, admin_url('admin.php?page=geekybot-integrations'), __('Review integration', 'geeky-bot'), '02'); ?>
                        <?php $this->setup_task_card(__('Product discovery index', 'geeky-bot'), $readiness['steps']['index'], $index_detail, $product_action_url, $product_action_label, '03'); ?>
                        <?php $this->setup_task_card(__('Store Knowledge', 'geeky-bot'), $readiness['steps']['knowledge'], __('Approve public shipping, returns, refunds, payment or warranty pages. This is optional for product discovery, and missing information remains unanswered rather than invented.', 'geeky-bot'), admin_url('admin.php?page=geekybot-store-knowledge'), __('Select policy pages', 'geeky-bot'), '04'); ?>
                        <?php $this->setup_task_card(__('Storefront widget', 'geeky-bot'), $readiness['steps']['widget'], __('Confirm the assistant name, welcome copy, launcher, mobile position and product-card volume.', 'geeky-bot'), admin_url('admin.php?page=geekybot-widget'), __('Configure widget', 'geeky-bot'), '05'); ?>
                        <?php $this->setup_task_card(__('Privacy and conversations', 'geeky-bot'), $readiness['steps']['privacy'], $privacy_detail, admin_url('admin.php?page=geekybot-settings#gb-settings-privacy'), __('Review privacy', 'geeky-bot'), '06'); ?>
                        <?php $this->setup_task_card(__('Guided Demo', 'geeky-bot'), $readiness['steps']['demo'], $demo_detail, $demo_action_url, $demo_action_label, '07'); ?>
                    </div>
                </section>

                <aside class="gb-first-run-aside">
                    <section class="gb-panel" id="gb-system-checks">
                        <div class="gb-panel-heading"><p class="gb-kicker"><?php esc_html_e('Environment', 'geeky-bot'); ?></p><h2><?php esc_html_e('System checks', 'geeky-bot'); ?></h2><p><?php esc_html_e('These checks are local and do not contact an external service.', 'geeky-bot'); ?></p></div>
                        <div class="gb-system-check-list">
                            <?php foreach ($system_checks as $check) : ?>
                                <div class="gb-system-check <?php echo esc_attr(!empty($check['ready']) ? (!empty($check['warning']) ? 'is-warning' : 'is-ready') : 'is-error'); ?>">
                                    <span aria-hidden="true"><?php echo !empty($check['ready']) ? (!empty($check['warning']) ? '!' : '✓') : '×'; ?></span>
                                    <div><strong><?php echo esc_html($check['label']); ?></strong><em><?php echo esc_html($check['detail']); ?></em></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!$ctx['wc_ready']) : ?>
                            <p class="gb-system-primary-action"><a class="button button-primary" href="<?php echo esc_url($woocommerce_action['url']); ?>"><?php echo esc_html($woocommerce_action['label']); ?></a></p>
                        <?php endif; ?>
                    </section>

                    <section class="gb-panel gb-first-run-actions">
                    <div class="gb-panel-heading"><p class="gb-kicker"><?php esc_html_e('Setup control', 'geeky-bot'); ?></p><h2><?php esc_html_e('Review status', 'geeky-bot'); ?></h2><p><?php echo esc_html($setup_status_detail); ?></p></div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('geekybot_onboarding_action'); ?>
                            <input type="hidden" name="action" value="geekybot_onboarding_action" />
                            <button class="button button-primary" type="submit" name="onboarding_action" value="reviewed"><?php esc_html_e('Finish setup review', 'geeky-bot'); ?></button>
                            <button class="button" type="submit" name="onboarding_action" value="dismiss"><?php esc_html_e('Skip for now', 'geeky-bot'); ?></button>
                        </form>
                        <p class="description"><?php esc_html_e('The Setup Wizard always remains available from the Geeky Bot menu.', 'geeky-bot'); ?></p>
                    </section>
                </aside>
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
                <div class="notice notice-warning"><p><?php esc_html_e('The storefront widget is disabled. You can still copy examples, but enable the widget before using Try on storefront.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>

            <section class="gb-demo-overview" aria-label="<?php esc_attr_e('Guided Demo summary', 'geeky-bot'); ?>">
                <div><strong><?php echo esc_html(number_format_i18n(absint($counts['total']))); ?></strong><span><?php esc_html_e('Real-store examples', 'geeky-bot'); ?></span><em><?php esc_html_e('Generated locally with no AI usage.', 'geeky-bot'); ?></em></div>
                <div><strong><?php echo esc_html(number_format_i18n(absint($counts['free']))); ?></strong><span><?php esc_html_e('Free capabilities', 'geeky-bot'); ?></span><em><?php esc_html_e('Find, understand and choose products.', 'geeky-bot'); ?></em></div>
                <div><strong><?php echo esc_html(number_format_i18n(absint($counts['pro']))); ?></strong><span><?php esc_html_e('Commerce Pro examples', 'geeky-bot'); ?></span><em><?php echo esc_html(absint($counts['proUnlocked']) > 0 ? __('Buying actions are unlocked.', 'geeky-bot') : __('Preview the paid buying journey.', 'geeky-bot')); ?></em></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('geekybot_refresh_guided_demo'); ?>
                    <input type="hidden" name="action" value="geekybot_refresh_guided_demo" />
                    <button class="button" type="submit"><?php esc_html_e('Refresh examples', 'geeky-bot'); ?></button>
                </form>
            </section>

            <?php if (empty($examples)) : ?>
                <section class="gb-panel gb-demo-empty gb-demo-empty--guided">
                    <div class="gb-demo-empty__icon" aria-hidden="true">01</div>
                    <?php if (!$ctx['wc_ready']) : ?>
                        <p class="gb-kicker"><?php esc_html_e('WooCommerce required', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Connect the store catalog before generating examples', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Guided Demo uses real visible WooCommerce products. Activate WooCommerce first, then add or import products and prepare the Product Discovery index.', 'geeky-bot'); ?></p>
                        <div class="gb-demo-empty__actions">
                            <a class="button button-primary" href="<?php echo esc_url($woocommerce_action['url']); ?>"><?php echo esc_html($woocommerce_action['label']); ?></a>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><?php esc_html_e('Recheck store', 'geeky-bot'); ?></a>
                        </div>
                    <?php elseif ($published_products < 1) : ?>
                        <p class="gb-kicker"><?php esc_html_e('Catalog is empty', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Add or import at least one visible product', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Examples are never generated from fake demo data. Add a real WooCommerce product, then prepare the Product Discovery index.', 'geeky-bot'); ?></p>
                        <div class="gb-demo-empty__actions">
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>"><?php esc_html_e('Add a product', 'geeky-bot'); ?></a>
                            <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=product_importer')); ?>"><?php esc_html_e('Import products', 'geeky-bot'); ?></a>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><?php esc_html_e('Recheck store', 'geeky-bot'); ?></a>
                        </div>
                    <?php else : ?>
                        <p class="gb-kicker"><?php esc_html_e('Product index required', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Prepare Product Discovery to generate real examples', 'geeky-bot'); ?></h2>
                        <p><?php echo esc_html(sprintf(
                            /* translators: %s: number of published WooCommerce products. */
                            __('%s published products are available, but none are currently ready in the Geeky Bot index.', 'geeky-bot'),
                            number_format_i18n($published_products)
                        )); ?></p>
                        <div class="gb-demo-empty__actions">
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant')); ?>"><?php esc_html_e('Prepare products', 'geeky-bot'); ?></a>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><?php esc_html_e('Recheck store', 'geeky-bot'); ?></a>
                        </div>
                    <?php endif; ?>
                </section>
            <?php else : ?>
                <section class="gb-demo-grid" aria-label="<?php esc_attr_e('Store-grounded demo examples', 'geeky-bot'); ?>">
                    <?php foreach ($examples as $example) :
                        $is_pro = $example['tier'] === 'pro';
                        $locked = !empty($example['locked']);
                        $try_url = add_query_arg(array('geekybot_demo' => $example['query']), home_url('/'));
                        ?>
                        <article class="gb-demo-card <?php echo esc_attr($is_pro ? 'is-pro' : 'is-free'); ?> <?php echo $locked ? 'is-locked' : ''; ?>">
                            <div class="gb-demo-card__top">
                                <span class="gb-demo-tier <?php echo esc_attr($is_pro ? 'is-pro' : 'is-free'); ?>"><?php echo esc_html($is_pro ? __('Commerce Pro', 'geeky-bot') : __('Free', 'geeky-bot')); ?></span>
                                <span class="gb-demo-feature"><?php echo esc_html($example['feature']); ?></span>
                            </div>
                            <h2><?php echo esc_html($example['title']); ?></h2>
                            <p><?php echo esc_html($example['description']); ?></p>
                            <blockquote><code><?php echo esc_html($example['query']); ?></code></blockquote>
                            <div class="gb-demo-source">
                                <span><?php esc_html_e('Built from', 'geeky-bot'); ?></span>
                                <?php if (!empty($example['sourceUrl'])) : ?><a href="<?php echo esc_url($example['sourceUrl']); ?>"><?php echo esc_html($example['sourceLabel']); ?></a><?php else : ?><strong><?php echo esc_html($example['sourceLabel']); ?></strong><?php endif; ?>
                            </div>
                            <div class="gb-demo-actions">
                                <?php if ($locked) : ?>
                                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-addons')); ?>"><?php esc_html_e('View Commerce Pro', 'geeky-bot'); ?></a>
                                <?php else : ?>
                                    <a class="button button-primary <?php echo !$widget_enabled ? 'disabled' : ''; ?>" href="<?php echo esc_url($try_url); ?>" target="_blank" rel="noopener noreferrer" <?php echo !$widget_enabled ? 'aria-disabled="true"' : ''; ?>><?php esc_html_e('Try on storefront', 'geeky-bot'); ?></a>
                                <?php endif; ?>
                                <button class="button" type="button" data-gb-copy-demo="<?php echo esc_attr($example['query']); ?>"><?php esc_html_e('Copy', 'geeky-bot'); ?></button>
                            </div>
                            <?php if ($locked) : ?><p class="gb-demo-lock-note"><?php esc_html_e('This example uses a buying action available in Commerce Pro.', 'geeky-bot'); ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <section class="gb-panel gb-demo-boundary">
                <div><strong><?php esc_html_e('Free proves product intelligence', 'geeky-bot'); ?></strong><p><?php esc_html_e('Shoppers find products, apply real constraints, ask product questions, receive grounded recommendations and read approved store-policy answers.', 'geeky-bot'); ?></p></div>
                <div><strong><?php esc_html_e('Commerce Pro completes the buying action', 'geeky-bot'); ?></strong><p><?php esc_html_e('Shoppers select variations, add products, manage the cart and continue to checkout without leaving the assistant flow.', 'geeky-bot'); ?></p></div>
            </section>
        </div>
        <?php
    }

    public function chat_widget() {
        $ctx = $this->context();
        $settings = $ctx['settings'];
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-admin-widget">
            <?php $this->page_hero(__('Storefront assistant builder', 'geeky-bot'), __('Shape the live shopper panel: welcome copy, product-card volume, starter prompts, accent color, placement and mobile-ready preview.', 'geeky-bot'), __('Shopper experience', 'geeky-bot'), home_url('/'), __('Open storefront', 'geeky-bot'), true); ?>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php if (!empty($_GET['updated'])) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Widget settings saved.', 'geeky-bot'); ?></p></div><?php endif; ?>
            <div class="gb-workbench gb-widget-workbench">
                <form method="post" class="gb-panel gb-control-panel">
                    <?php wp_nonce_field('geekybot_save_settings'); ?>
                    <input type="hidden" name="geekybot_settings_action" value="save" />
                    <input type="hidden" name="geekybot_settings_scope" value="partial" />
                    <input type="hidden" name="geekybot_redirect" value="<?php echo esc_url(admin_url('admin.php?page=geekybot-widget')); ?>" />
                    <div class="gb-panel-heading">
                        <h2><?php esc_html_e('Widget builder', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Edit the shopper-facing widget and compare changes with the preview before testing on the storefront.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-form-grid">
                        <label class="gb-toggle-row"><input type="hidden" name="widget_enabled" value="no" /><input type="checkbox" name="widget_enabled" value="yes" <?php checked($settings['widget_enabled'], 'yes'); ?> /> <span><strong><?php esc_html_e('Enable storefront widget', 'geeky-bot'); ?></strong><em><?php esc_html_e('Show the assistant button on public store pages.', 'geeky-bot'); ?></em></span></label>
                        <label><span><?php esc_html_e('Assistant name', 'geeky-bot'); ?></span><input name="assistant_name" type="text" value="<?php echo esc_attr($settings['assistant_name']); ?>" /></label>
                        <label><span><?php esc_html_e('Assistant subtitle', 'geeky-bot'); ?></span><input name="assistant_subtitle" type="text" value="<?php echo esc_attr($settings['assistant_subtitle']); ?>" /></label>
                        <label><span><?php esc_html_e('Welcome message', 'geeky-bot'); ?></span><textarea name="welcome_message" rows="3"><?php echo esc_textarea($settings['welcome_message']); ?></textarea></label>
                        <label><span><?php esc_html_e('Safe fallback answer', 'geeky-bot'); ?></span><textarea name="fallback_human_message" rows="3"><?php echo esc_textarea($settings['fallback_human_message']); ?></textarea></label>
                        <div class="gb-inline-fields">
                            <label><span><?php esc_html_e('Accent color', 'geeky-bot'); ?></span><input name="accent_color" type="color" value="<?php echo esc_attr($settings['accent_color']); ?>" /></label>
                            <label><span><?php esc_html_e('Position', 'geeky-bot'); ?></span><select name="button_position"><option value="right" <?php selected($settings['button_position'], 'right'); ?>><?php esc_html_e('Right', 'geeky-bot'); ?></option><option value="left" <?php selected($settings['button_position'], 'left'); ?>><?php esc_html_e('Left', 'geeky-bot'); ?></option></select></label>
                            <label><span><?php esc_html_e('Products per answer', 'geeky-bot'); ?></span><input name="max_products" type="number" min="1" max="8" value="<?php echo esc_attr(absint($settings['max_products'])); ?>" /></label>
                        </div>
                        <div class="gb-branding-controls">
                            <div class="gb-branding-section">
                                <div class="gb-branding-section__head">
                                    <strong><?php esc_html_e('Launcher button', 'geeky-bot'); ?></strong>
                                    <span><?php esc_html_e('Choose what shoppers see before they open the assistant.', 'geeky-bot'); ?></span>
                                </div>
                                <div class="gb-branding-grid">
                                    <label><span><?php esc_html_e('Icon source', 'geeky-bot'); ?></span><select name="launcher_icon_source"><option value="default" <?php selected($settings['launcher_icon_source'], 'default'); ?>><?php esc_html_e('Default GeekyBot icon', 'geeky-bot'); ?></option><option value="store_logo" <?php selected($settings['launcher_icon_source'], 'store_logo'); ?>><?php esc_html_e('Store logo', 'geeky-bot'); ?></option><option value="custom" <?php selected($settings['launcher_icon_source'], 'custom'); ?>><?php esc_html_e('Custom image', 'geeky-bot'); ?></option></select><em><?php esc_html_e('Use the GeekyBot mark, your store logo, or a custom Media Library image.', 'geeky-bot'); ?></em></label>
                                    <?php $this->branding_image_picker('launcher_icon_attachment_id', $settings['launcher_icon_attachment_id'], __('Custom icon', 'geeky-bot')); ?>
                                    <label><span><?php esc_html_e('Launcher style', 'geeky-bot'); ?></span><select name="launcher_style"><option value="icon" <?php selected($settings['launcher_style'], 'icon'); ?>><?php esc_html_e('Icon only', 'geeky-bot'); ?></option><option value="pill" <?php selected($settings['launcher_style'], 'pill'); ?>><?php esc_html_e('Icon + text', 'geeky-bot'); ?></option></select><em><?php esc_html_e('Icon-only is compact. Icon + text can increase trust for new visitors.', 'geeky-bot'); ?></em></label>
                                    <label><span><?php esc_html_e('Launcher text', 'geeky-bot'); ?></span><input name="launcher_text" type="text" value="<?php echo esc_attr($settings['launcher_text']); ?>" /><em><?php esc_html_e('Shown only when launcher style is Icon + text.', 'geeky-bot'); ?></em></label>
                                </div>
                            </div>
                            <div class="gb-branding-section">
                                <div class="gb-branding-section__head">
                                    <strong><?php esc_html_e('Widget header', 'geeky-bot'); ?></strong>
                                    <span><?php esc_html_e('Keep the opened assistant aligned with your store brand.', 'geeky-bot'); ?></span>
                                </div>
                                <div class="gb-branding-grid">
                                    <label><span><?php esc_html_e('Header logo', 'geeky-bot'); ?></span><select name="header_logo_source"><option value="same" <?php selected($settings['header_logo_source'], 'same'); ?>><?php esc_html_e('Same as launcher', 'geeky-bot'); ?></option><option value="store_logo" <?php selected($settings['header_logo_source'], 'store_logo'); ?>><?php esc_html_e('Store logo', 'geeky-bot'); ?></option><option value="custom" <?php selected($settings['header_logo_source'], 'custom'); ?>><?php esc_html_e('Custom image', 'geeky-bot'); ?></option><option value="hide" <?php selected($settings['header_logo_source'], 'hide'); ?>><?php esc_html_e('Hide logo', 'geeky-bot'); ?></option></select><em><?php esc_html_e('Appears beside the assistant name in the open widget.', 'geeky-bot'); ?></em></label>
                                    <?php $this->branding_image_picker('header_logo_attachment_id', $settings['header_logo_attachment_id'], __('Custom header logo', 'geeky-bot')); ?>
                                    <label><span><?php esc_html_e('Header style', 'geeky-bot'); ?></span><select name="header_style"><option value="gradient" <?php selected($settings['header_style'], 'gradient'); ?>><?php esc_html_e('Gradient', 'geeky-bot'); ?></option><option value="solid" <?php selected($settings['header_style'], 'solid'); ?>><?php esc_html_e('Solid accent', 'geeky-bot'); ?></option></select><em><?php esc_html_e('Gradient gives a premium assistant feel. Solid keeps the store accent simple.', 'geeky-bot'); ?></em></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="gb-button-row"><?php submit_button(__('Save widget', 'geeky-bot'), 'primary', 'submit', false); ?><a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Test on storefront', 'geeky-bot'); ?></a></div>
                </form>
                <div class="gb-panel gb-preview-panel">
                    <div class="gb-panel-heading"><h2><?php esc_html_e('Live-style preview', 'geeky-bot'); ?></h2><p><?php esc_html_e('Preview updates while you edit. Save before testing on the storefront.', 'geeky-bot'); ?></p></div>
                    <?php $this->widget_preview($settings); ?>
                    <div class="gb-mini-checklist"><span><?php esc_html_e('Scoped CSS', 'geeky-bot'); ?></span><span><?php esc_html_e('Mobile-first panel', 'geeky-bot'); ?></span><span><?php esc_html_e('Product cards ready', 'geeky-bot'); ?></span></div>
                </div>
            </div>
            <div class="gb-panel">
                <div class="gb-panel-heading"><h2><?php esc_html_e('Starter prompt strategy', 'geeky-bot'); ?></h2><p><?php esc_html_e('Starter prompts teach shoppers what the assistant can do. These are generated from the current assistant capabilities.', 'geeky-bot'); ?></p></div>
                <div class="gb-chip-row"><code>Latest products</code><code>Sale products</code><code>Top rated</code><code>Products under 50</code></div>
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
            <?php $this->page_hero(__('Catalog intelligence', 'geeky-bot'), __('Test real shopper phrases, inspect match reasons, tune synonyms, rebuild the product index, and control product-match ranking.', 'geeky-bot'), __('Product discovery', 'geeky-bot'), admin_url('edit.php?post_type=product'), __('Open products', 'geeky-bot')); ?>

            <section class="gb-panel gb-product-lab-panel">
                <div class="gb-panel-heading gb-product-lab-heading">
                    <div>
                        <span class="gb-section-kicker"><?php esc_html_e('Test workbench', 'geeky-bot'); ?></span>
                        <h2><?php esc_html_e('Shopper phrase test lab', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Run real buyer language, inspect what Geeky Bot understood, then tune catalog data or synonyms before checking the storefront widget.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-product-brain-strip" aria-label="<?php esc_attr_e('Buyer search status', 'geeky-bot'); ?>">
                        <span><strong><?php echo esc_html(number_format_i18n($ctx['indexed_count'])); ?></strong><em><?php esc_html_e('Indexed products', 'geeky-bot'); ?></em></span>
                        <span title="<?php echo esc_attr($this->product_index_status_title($ctx)); ?>"><strong><?php echo esc_html($this->product_index_status_label($ctx)); ?></strong><em><?php echo esc_html($ctx['index_status'] === 'current' ? __('Last full index', 'geeky-bot') : __('Index status', 'geeky-bot')); ?></em></span>
                        <span><strong><?php echo esc_html($settings['natural_search_enabled'] === 'yes' ? __('Natural', 'geeky-bot') : __('Keyword', 'geeky-bot')); ?></strong><em><?php esc_html_e('Buyer search', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($settings['search_close_match_mode'] === 'strict' ? __('Exact', 'geeky-bot') : __('Smart', 'geeky-bot')); ?></strong><em><?php esc_html_e('Product matches', 'geeky-bot'); ?></em></span>
                    </div>
                </div>
                <form method="get" class="gb-search-form gb-search-form--workbench">
                    <input type="hidden" name="page" value="geekybot-product-assistant" />
                    <input type="search" name="gb_test_query" value="<?php echo esc_attr($test_query); ?>" placeholder="<?php esc_attr_e('Try: comfortable shoes size 42 red and white', 'geeky-bot'); ?>" />
                    <?php submit_button(__('Test search', 'geeky-bot'), 'primary', 'submit', false); ?>
                </form>
                <?php if ($test_query !== '') : ?>
                    <div class="gb-search-result-summary"><strong><?php echo esc_html(sprintf(
                        /* translators: %s: admin test-search query. */
                        __('Search: %s', 'geeky-bot'),
                        $test_query
                    )); ?></strong><span><?php echo esc_html(sprintf(
                        /* translators: %s: product search result mode. */
                        __('Result mode: %s', 'geeky-bot'),
                        $search_note ? $search_note : __('catalog search', 'geeky-bot')
                    )); ?></span></div>
                    <?php $this->search_intent_debug($search_analysis); ?>
                    <?php if (empty($search_results)) : ?>
                        <div class="gb-empty-state"><strong><?php esc_html_e('No matching products found.', 'geeky-bot'); ?></strong><p><?php esc_html_e('Try adding product attributes, improving titles/tags, adding synonyms below, or switching close matches back to smart mode.', 'geeky-bot'); ?></p></div>
                    <?php else : ?>
                        <div class="gb-admin-product-results">
                            <?php foreach (array_slice($search_results, 0, 4) as $product) : ?>
                                <div class="gb-admin-product-card">
                                    <img src="<?php echo esc_url($product['image']); ?>" alt="" />
                                    <div class="gb-admin-product-card__body">
                                        <strong><?php echo esc_html($product['name']); ?></strong>
                                        <div class="gb-admin-product-card__meta">
                                            <span class="gb-admin-product-price"><?php echo esc_html(isset($product['priceText']) && $product['priceText'] !== '' ? $product['priceText'] : wp_strip_all_tags($product['priceHtml'])); ?></span>
                                            <span class="gb-admin-product-stock"><?php echo esc_html($product['stockLabel']); ?></span>
                                            <span class="gb-admin-product-type"><?php echo esc_html(ucfirst((string) $product['type'])); ?></span>
                                        </div>
                                        <em><?php echo esc_html(wp_trim_words($product['shortDescription'], 14)); ?></em>
                                        <?php $this->admin_product_match_debug($product, $search_analysis, $search_debug); ?>
                                        <div class="gb-admin-product-card__actions">
                                            <a href="<?php echo esc_url($product['url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View product', 'geeky-bot'); ?></a>
                                            <a href="<?php echo esc_url(get_edit_post_link(absint($product['id']))); ?>"><?php esc_html_e('Edit product', 'geeky-bot'); ?></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($search_results) > 4) : ?>
                            <div class="gb-admin-product-more"><?php echo esc_html(sprintf(
                                /* translators: %d: number of additional product matches. */
                                __('%d more close matches are available. Narrow the phrase or check the storefront widget for the shopper view.', 'geeky-bot'),
                                count($search_results) - 4
                            )); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="gb-search-lab-guide" aria-label="<?php esc_attr_e('How the search lab works', 'geeky-bot'); ?>">
                        <div><b><?php esc_html_e('1. Parse buyer language', 'geeky-bot'); ?></b><span><?php esc_html_e('Reads product terms, color, size, price phrases and soft preferences.', 'geeky-bot'); ?></span></div>
                        <div><b><?php esc_html_e('2. Match catalog signals', 'geeky-bot'); ?></b><span><?php esc_html_e('Scores product names, categories, tags, attributes, stock and sale status.', 'geeky-bot'); ?></span></div>
                        <div><b><?php esc_html_e('3. Tune what shoppers say', 'geeky-bot'); ?></b><span><?php esc_html_e('Use synonyms and product-match settings when store wording differs from buyer wording.', 'geeky-bot'); ?></span></div>
                    </div>
                <?php endif; ?>
            </section>

            <div class="gb-product-tune-grid">
                <section class="gb-panel gb-index-control-panel">
                    <div class="gb-panel-heading">
                        <span class="gb-section-kicker"><?php esc_html_e('Catalog signals', 'geeky-bot'); ?></span>
                        <h2><?php esc_html_e('Product index controls', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Rebuild after imports, category changes, product updates, attribute edits, or sale/stock changes.', 'geeky-bot'); ?></p>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb-index-rebuild-form">
                        <?php wp_nonce_field('geekybot_rebuild_product_index'); ?>
                        <input type="hidden" name="action" value="geekybot_rebuild_product_index" />
                        <?php submit_button(__('Rebuild Product Search Index', 'geeky-bot'), 'primary', 'submit', false); ?>
                    </form>
                    <div class="gb-index-signal-grid">
                        <span><strong><?php esc_html_e('Product data', 'geeky-bot'); ?></strong><em><?php esc_html_e('Name, SKU, category, tags and attributes.', 'geeky-bot'); ?></em></span>
                        <span><strong><?php esc_html_e('Buyer intent', 'geeky-bot'); ?></strong><em><?php esc_html_e('Price, color, size and recommendation phrases.', 'geeky-bot'); ?></em></span>
                        <span><strong><?php esc_html_e('Commerce signals', 'geeky-bot'); ?></strong><em><?php esc_html_e('Stock, sale status, ratings and popularity.', 'geeky-bot'); ?></em></span>
                    </div>
                    <div class="gb-signal-cloud">
                        <?php $this->feature_pill(__('Product name', 'geeky-bot')); ?><?php $this->feature_pill(__('SKU', 'geeky-bot')); ?><?php $this->feature_pill(__('Categories', 'geeky-bot')); ?><?php $this->feature_pill(__('Tags', 'geeky-bot')); ?><?php $this->feature_pill(__('Attributes', 'geeky-bot')); ?><?php $this->feature_pill(__('Buyer intent', 'geeky-bot')); ?><?php $this->feature_pill(__('Soft preferences', 'geeky-bot')); ?><?php $this->feature_pill(__('Price phrases', 'geeky-bot')); ?><?php $this->feature_pill(__('Product matches', 'geeky-bot')); ?><?php $this->feature_pill(__('Sale status', 'geeky-bot')); ?><?php $this->feature_pill(__('Stock', 'geeky-bot')); ?>
                    </div>
                </section>
                <?php $this->product_search_controls($settings); ?>
            </div>

            <section class="gb-panel gb-qa-workbench-panel">
                <div class="gb-panel-heading"><span class="gb-section-kicker"><?php esc_html_e('Search rehearsal', 'geeky-bot'); ?></span><h2><?php esc_html_e('Search rehearsal board', 'geeky-bot'); ?></h2><p><?php esc_html_e('Click a phrase to load it into the search lab and confirm that buyer language still maps to the right products.', 'geeky-bot'); ?></p></div>
                <div class="gb-qa-board">
                    <?php $this->qa_link('budget hoodie', __('Budget intent', 'geeky-bot')); ?>
                    <?php $this->qa_link('hoodie between 30 and 60', __('Price range', 'geeky-bot')); ?>
                    <?php $this->qa_link('blue hoodie', __('Color attribute', 'geeky-bot')); ?>
                    <?php $this->qa_link('shoes size 42', __('Size attribute', 'geeky-bot')); ?>
                    <?php $this->qa_link('comfortable shoes size 42 red and white', __('Natural buyer query', 'geeky-bot')); ?>
                    <?php $this->qa_link('not too expensive walking shoes in black size 9', __('Soft preference query', 'geeky-bot')); ?>
                    <?php $this->qa_link('hoodies on sale', __('Sale filter', 'geeky-bot')); ?>
                    <?php $this->qa_link('which hoodie do you recommend', __('Recommendation phrasing', 'geeky-bot')); ?>
                </div>
            </section>
            <?php $this->nlp_action_examples(); ?>
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
            <?php $this->page_hero(__('Policy knowledge center', 'geeky-bot'), __('Choose the public policy pages Geeky Bot may use for shipping, returns, refunds, payment and warranty answers. Selected pages are indexed locally and missing details produce a safe fallback instead of guesses.', 'geeky-bot'), __('Grounded policy answers', 'geeky-bot'), admin_url('post-new.php?post_type=page'), __('Create policy page', 'geeky-bot')); ?>
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

            <section class="gb-panel gb-policy-source-strip">
                <div class="gb-policy-source-strip__item">
                    <span class="gb-policy-source-strip__value"><?php echo esc_html(number_format_i18n(count($selected))); ?></span>
                    <strong><?php esc_html_e('Selected public sources', 'geeky-bot'); ?></strong>
                    <em><?php esc_html_e('Only these pages may ground policy answers.', 'geeky-bot'); ?></em>
                </div>
                <div class="gb-policy-source-strip__item <?php echo esc_attr($index_status['ready'] ? 'is-safe' : ''); ?>">
                    <span class="gb-policy-source-strip__value"><?php echo esc_html(number_format_i18n(absint($index_status['usable']))); ?></span>
                    <strong><?php esc_html_e('Recognized policy sources', 'geeky-bot'); ?></strong>
                    <em><?php echo esc_html($last_indexed ? sprintf(
                        /* translators: %s: date and time of the last knowledge-index refresh. */
                        __('Last refreshed %s.', 'geeky-bot'),
                        $last_indexed
                    ) : __('Save or refresh to build the local index.', 'geeky-bot')); ?></em>
                </div>
                <div class="gb-policy-source-strip__item <?php echo esc_attr($needs_refresh === 0 ? 'is-safe' : ''); ?>">
                    <span class="gb-policy-source-strip__value"><?php echo esc_html($needs_refresh === 0 ? __('Ready', 'geeky-bot') : number_format_i18n($needs_refresh)); ?></span>
                    <strong><?php echo esc_html($needs_refresh === 0 ? __('Knowledge is current', 'geeky-bot') : __('Sources need attention', 'geeky-bot')); ?></strong>
                    <em><?php esc_html_e('Changed, missing, draft, and removed pages are never trusted silently.', 'geeky-bot'); ?></em>
                </div>
            </section>

            <form method="post" class="gb-knowledge-form">
                <?php wp_nonce_field('geekybot_save_settings'); ?>
                <input type="hidden" name="geekybot_settings_action" value="save" />
                <input type="hidden" name="geekybot_settings_scope" value="partial" />
                <input type="hidden" name="geekybot_redirect" value="<?php echo esc_url(admin_url('admin.php?page=geekybot-store-knowledge')); ?>" />
                <input type="hidden" name="policy_page_ids[]" value="0" />
                <div class="gb-grid gb-grid-2">
                    <div class="gb-panel">
                        <div class="gb-panel-heading"><h2><?php esc_html_e('Choose public answer sources', 'geeky-bot'); ?></h2><p><?php esc_html_e('Select a small set of accurate shopper-facing pages. Geeky Bot indexes stored page text only; it does not crawl arbitrary URLs or execute page shortcodes.', 'geeky-bot'); ?></p></div>
                        <div class="gb-page-picker">
                            <?php foreach ($pages as $page) : ?>
                                <?php
                                $is_suggested = in_array($page, $suggested, true);
                                $page_types = !empty($policy_classifications[$page->ID]) ? $policy_classifications[$page->ID] : array('general');
                                $type_summary = $this->policy_type_summary($page_types);
                                $source_label = $is_suggested ? __('Suggested policy page', 'geeky-bot') : __('Public page', 'geeky-bot');
                                ?>
                                <label class="gb-page-option <?php echo esc_attr($is_suggested ? 'is-suggested' : ''); ?>">
                                    <input type="checkbox" name="policy_page_ids[]" value="<?php echo esc_attr($page->ID); ?>" <?php checked(in_array(absint($page->ID), $selected, true)); ?> />
                                    <span><strong><?php echo esc_html($page->post_title); ?></strong><em><?php echo esc_html($source_label . ' — ' . $type_summary); ?></em></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="gb-button-row"><?php submit_button(__('Save and index knowledge pages', 'geeky-bot'), 'primary', 'submit', false); ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant&gb_test_query=refund+policy')); ?>"><?php esc_html_e('Test refund policy query', 'geeky-bot'); ?></a></div>
                    </div>
                    <div class="gb-panel gb-selected-knowledge-panel">
                        <div class="gb-panel-heading">
                            <span class="gb-section-kicker"><?php esc_html_e('Grounded library', 'geeky-bot'); ?></span>
                            <h2><?php esc_html_e('Selected knowledge library', 'geeky-bot'); ?></h2>
                            <p><?php printf(
                                /* translators: %d: number of suggested policy pages. */
                                esc_html__('%d suggested policy pages were detected. Only checked pages are approved sources.', 'geeky-bot'),
                                count($suggested)
                            ); ?></p>
                        </div>
                        <?php $this->page_list($pages, $selected, true, $policy_classifications); ?>
                        <div class="gb-rule-stack gb-rule-stack--knowledge">
                            <div><strong><?php esc_html_e('Deterministic policy routing', 'geeky-bot'); ?></strong><span><?php esc_html_e('Shipping, return, refund, exchange, warranty, payment and cancellation questions are checked here before product search or external AI.', 'geeky-bot'); ?></span></div>
                            <div><strong><?php esc_html_e('No invented details', 'geeky-bot'); ?></strong><span><?php esc_html_e('A source must contain evidence for the shopper’s specific question. Otherwise Geeky Bot says the detail is not confirmed.', 'geeky-bot'); ?></span></div>
                            <div><strong><?php esc_html_e('Source shown to shoppers', 'geeky-bot'); ?></strong><span><?php esc_html_e('Grounded answers include the selected page title and a link below the answer.', 'geeky-bot'); ?></span></div>
                        </div>
                    </div>
                </div>
            </form>

            <section class="gb-panel gb-knowledge-index-panel">
                <div class="gb-panel-heading"><h2><?php esc_html_e('Refresh knowledge index', 'geeky-bot'); ?></h2><p><?php esc_html_e('Selected pages refresh automatically when saved. Use this action after imports, page-builder migrations, or when the status above shows a stale or missing source.', 'geeky-bot'); ?></p></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('geekybot_refresh_knowledge_index'); ?>
                    <input type="hidden" name="action" value="geekybot_refresh_knowledge_index" />
                    <?php submit_button(__('Refresh selected sources', 'geeky-bot'), 'secondary', 'submit', false); ?>
                </form>
            </section>
        </div>
        <?php
    }

    public function analytics() {
        $insights = new ConversationInsightsService();
        $events = new AnalyticsEventService();
        $summary = $insights->summary(30);
        $daily = $insights->daily_activity(14);
        $top_products = $events->top_clicked_products(30, 8);
        $reasons = $insights->reason_breakdown(30);
        $max_activity = 1;
        foreach ($daily as $day) {
            $max_activity = max($max_activity, absint($day['shopper_messages']));
        }
        ?>
        <div class="wrap geekybot-admin-wrap geekybot-analytics-page geekybot-insights-page">
            <?php $this->page_hero(__('Store insight', 'geeky-bot'), __('See how shoppers use Geeky Bot, which products they open, and where catalog or policy data still needs attention. Free analytics stays focused on assistant quality rather than conversion attribution.', 'geeky-bot'), __('Last 30 days', 'geeky-bot'), admin_url('admin.php?page=geekybot-conversations'), __('Review conversations', 'geeky-bot')); ?>

            <section class="gb-insight-metrics" aria-label="<?php esc_attr_e('Assistant insight summary', 'geeky-bot'); ?>">
                <article><span><?php esc_html_e('Conversations', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($summary['sessions'])); ?></strong><em><?php esc_html_e('Active in the last 30 days', 'geeky-bot'); ?></em></article>
                <article><span><?php esc_html_e('Shopper messages', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($summary['shopper_messages'])); ?></strong><em><?php echo esc_html(sprintf(
                    /* translators: %s: average number of messages per conversation. */
                    __('Average %s total messages per conversation', 'geeky-bot'),
                    number_format_i18n($summary['average_messages'], 1)
                )); ?></em></article>
                <article><span><?php esc_html_e('Product clicks', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($summary['product_clicks'])); ?></strong><em><?php esc_html_e('Clicks from product cards in chat', 'geeky-bot'); ?></em></article>
                <article class="<?php echo esc_attr($summary['unanswered'] > 0 ? 'is-warning' : 'is-good'); ?>"><span><?php esc_html_e('Needs attention', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($summary['unanswered'])); ?></strong><em><?php esc_html_e('Grounded-answer gaps logged', 'geeky-bot'); ?></em></article>
            </section>

            <section class="gb-insight-grid">
                <article class="gb-panel gb-insight-activity-panel">
                    <div class="gb-panel-heading"><p class="gb-kicker"><?php esc_html_e('Conversation activity', 'geeky-bot'); ?></p><h2><?php esc_html_e('Daily shopper messages', 'geeky-bot'); ?></h2><p><?php esc_html_e('A lightweight 14-day activity view. It does not identify individual shoppers.', 'geeky-bot'); ?></p></div>
                    <div class="gb-insight-bars" aria-label="<?php esc_attr_e('Daily shopper message activity', 'geeky-bot'); ?>">
                        <?php foreach ($daily as $day) :
                            $day_messages = absint($day['shopper_messages']);
                            $height = $day_messages > 0 ? max(4, round(($day_messages / $max_activity) * 100)) : 0;
                            $date_label = date_i18n('M j', strtotime($day['date']));
                            ?>
                            <div class="gb-insight-bar <?php echo esc_attr($day_messages > 0 ? '' : 'is-empty'); ?>" title="<?php echo esc_attr(sprintf(
                                /* translators: 1: date, 2: shopper message count, 3: conversation count. */
                                __('%1$s: %2$s shopper messages in %3$s conversations', 'geeky-bot'),
                                $date_label,
                                number_format_i18n($day['shopper_messages']),
                                number_format_i18n($day['sessions'])
                            )); ?>">
                                <span style="height:<?php echo esc_attr($height); ?>%"></span>
                                <em><?php echo esc_html($date_label); ?></em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="gb-panel gb-insight-clicks-panel">
                    <div class="gb-panel-heading"><p class="gb-kicker"><?php esc_html_e('Product interest', 'geeky-bot'); ?></p><h2><?php esc_html_e('Most-opened products', 'geeky-bot'); ?></h2><p><?php esc_html_e('Counts storefront clicks from Geeky Bot product cards. Cart and checkout attribution remain Commerce Pro features.', 'geeky-bot'); ?></p></div>
                    <div class="gb-insight-ranked-list">
                        <?php if (empty($top_products)) : ?>
                            <div class="gb-empty-state"><strong><?php esc_html_e('No product clicks yet', 'geeky-bot'); ?></strong><span><?php esc_html_e('Product interest appears after shoppers open products from chat.', 'geeky-bot'); ?></span></div>
                        <?php else : foreach ($top_products as $index => $product) :
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
                            <div class="gb-insight-ranked-item">
                                <span><?php echo esc_html(number_format_i18n($index + 1)); ?></span>
                                <div><strong><?php echo esc_html($label); ?></strong><em><?php echo esc_html(sprintf(
                                    /* translators: %s: number of product clicks. */
                                    _n('%s click', '%s clicks', absint($product['clicks']), 'geeky-bot'),
                                    number_format_i18n($product['clicks'])
                                )); ?></em></div>
                                <?php if ($product_url) : ?><a href="<?php echo esc_url($product_url); ?>"><?php esc_html_e('Open product', 'geeky-bot'); ?></a><?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </article>
            </section>

            <section class="gb-insight-grid gb-insight-grid--lower">
                <article class="gb-panel gb-insight-gap-panel">
                    <div class="gb-panel-heading"><p class="gb-kicker"><?php esc_html_e('Missing store data', 'geeky-bot'); ?></p><h2><?php esc_html_e('Why answers were unavailable', 'geeky-bot'); ?></h2><p><?php esc_html_e('Each reason maps to a concrete store-owner action. Geeky Bot never fills these gaps by guessing.', 'geeky-bot'); ?></p></div>
                    <div class="gb-insight-reason-list">
                        <?php if (empty($reasons)) : ?>
                            <div class="gb-empty-state"><strong><?php esc_html_e('No grounded-answer gaps', 'geeky-bot'); ?></strong><span><?php esc_html_e('Keep monitoring after catalog and policy updates.', 'geeky-bot'); ?></span></div>
                        <?php else : foreach ($reasons as $reason) : ?>
                            <div class="gb-insight-reason-item">
                                <span><?php echo esc_html(number_format_i18n($reason['total'])); ?></span>
                                <div><strong><?php echo esc_html($reason['meta']['label']); ?></strong><em><?php echo esc_html($reason['meta']['action']); ?></em></div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations&gb_view=review&gb_review_status=needs-review')); ?>"><?php esc_html_e('Open unanswered questions', 'geeky-bot'); ?></a>
                </article>

                <article class="gb-panel gb-insight-privacy-panel">
                    <div class="gb-panel-heading"><p class="gb-kicker"><?php esc_html_e('Privacy controls', 'geeky-bot'); ?></p><h2><?php esc_html_e('Useful insight without shopper profiling', 'geeky-bot'); ?></h2><p><?php esc_html_e('The admin view uses conversation IDs and aggregate events. It does not display IP hashes, browser fingerprints, or API secrets.', 'geeky-bot'); ?></p></div>
                    <ul class="gb-insight-check-list">
                        <li><?php echo esc_html(sprintf(
                            /* translators: %s: conversation retention period in days. */
                            __('Conversation retention: %s days', 'geeky-bot'),
                            number_format_i18n(absint(Settings::get('retention_days', 30)))
                        )); ?></li>
                        <li><?php esc_html_e('WordPress personal-data export and erasure remain supported.', 'geeky-bot'); ?></li>
                        <li><?php esc_html_e('Admins can export or delete conversation records from the Conversations page.', 'geeky-bot'); ?></li>
                        <li><?php esc_html_e('Product clicks are basic interest signals, not conversion attribution.', 'geeky-bot'); ?></li>
                    </ul>
                    <div class="gb-inline-actions"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-settings#gb-settings-privacy')); ?>"><?php esc_html_e('Open privacy settings', 'geeky-bot'); ?></a><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Manage conversation data', 'geeky-bot'); ?></a></div>
                </article>
            </section>
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
            <?php $this->page_hero(__('Answer mode controls', 'geeky-bot'), __('Choose local grounded answers, Zywrap, or BYOK while keeping API keys server-side and answers grounded in store data.', 'geeky-bot'), __('Answer mode', 'geeky-bot'), admin_url('admin.php?page=geekybot-settings#gb-settings-ai'), __('Configure answer mode', 'geeky-bot')); ?>

            <section class="gb-integration-status-strip" aria-label="<?php esc_attr_e('Current answer mode status', 'geeky-bot'); ?>">
                <article class="gb-integration-status-card is-active">
                    <span><?php esc_html_e('Current mode', 'geeky-bot'); ?></span>
                    <strong><?php echo esc_html($this->provider_label($settings)); ?></strong>
                    <em><?php esc_html_e('Used for grounded answer generation.', 'geeky-bot'); ?></em>
                </article>
                <article class="gb-integration-status-card">
                    <span><?php esc_html_e('Secrets', 'geeky-bot'); ?></span>
                    <strong><?php esc_html_e('Server-side only', 'geeky-bot'); ?></strong>
                    <em><?php esc_html_e('Saved keys are never shown after save.', 'geeky-bot'); ?></em>
                </article>
                <article class="gb-integration-status-card">
                    <span><?php esc_html_e('Answer modes available', 'geeky-bot'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($configured_count)); ?>/3</strong>
                    <em><?php esc_html_e('Local mode is always available.', 'geeky-bot'); ?></em>
                </article>
            </section>

            <section class="gb-integration-provider-grid" aria-label="<?php esc_attr_e('Answer mode options', 'geeky-bot'); ?>">
                <article class="gb-integration-provider-card <?php echo esc_attr($local_active ? 'is-active' : ''); ?>">
                    <div class="gb-integration-provider-card__top">
                        <span class="gb-status-pill <?php echo esc_attr($local_active ? 'is-ready' : ''); ?>"><?php echo esc_html($local_active ? __('Active', 'geeky-bot') : __('Available', 'geeky-bot')); ?></span>
                        <span class="gb-integration-provider-icon">01</span>
                    </div>
                    <h2><?php esc_html_e('Local grounded mode', 'geeky-bot'); ?></h2>
                    <p><?php esc_html_e('Fastest and safest default. Answers use WooCommerce product data and selected public policy pages without sending catalog context to an external provider.', 'geeky-bot'); ?></p>
                    <div class="gb-integration-provider-checks">
                        <span><?php esc_html_e('No API key required', 'geeky-bot'); ?></span>
                        <span><?php esc_html_e('Catalog grounded', 'geeky-bot'); ?></span>
                        <span><?php esc_html_e('Policy fallback safe', 'geeky-bot'); ?></span>
                    </div>
                    <div class="gb-integration-provider-actions">
                        <a class="button <?php echo esc_attr($local_active ? 'button-primary' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-settings#gb-settings-ai')); ?>"><?php echo esc_html($local_active ? __('Review active mode', 'geeky-bot') : __('Choose local mode', 'geeky-bot')); ?></a>
                    </div>
                </article>

                <article class="gb-integration-provider-card <?php echo esc_attr($zywrap_ready ? 'is-active' : ''); ?>">
                    <div class="gb-integration-provider-card__top">
                        <span class="gb-status-pill <?php echo esc_attr($zywrap_ready ? 'is-ready' : ''); ?>"><?php echo esc_html($zywrap_ready ? __('Configured', 'geeky-bot') : __('Available', 'geeky-bot')); ?></span>
                        <span class="gb-integration-provider-icon">02</span>
                    </div>
                    <h2><?php esc_html_e('Zywrap endpoint', 'geeky-bot'); ?></h2>
                    <p><?php esc_html_e('Hosted Zywrap endpoint for store-grounded answers when you want stronger AI response generation without exposing secrets to the storefront.', 'geeky-bot'); ?></p>
                    <div class="gb-integration-provider-checks">
                        <span class="<?php echo esc_attr($zywrap_endpoint_saved ? 'is-ready' : 'is-missing'); ?>"><?php echo esc_html($zywrap_endpoint_saved ? __('Endpoint saved', 'geeky-bot') : __('Endpoint missing', 'geeky-bot')); ?></span>
                        <span class="<?php echo esc_attr($zywrap_key_saved ? 'is-ready' : 'is-missing'); ?>"><?php echo esc_html($zywrap_key_saved ? __('API key hidden', 'geeky-bot') : __('API key missing', 'geeky-bot')); ?></span>
                        <span><?php esc_html_e('Server-side request', 'geeky-bot'); ?></span>
                    </div>
                    <div class="gb-integration-provider-actions">
                        <a class="button <?php echo esc_attr($zywrap_ready ? 'button-primary' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-settings#gb-settings-ai')); ?>"><?php echo esc_html($zywrap_ready ? __('Review Zywrap setup', 'geeky-bot') : __('Configure Zywrap', 'geeky-bot')); ?></a>
                    </div>
                </article>

                <article class="gb-integration-provider-card <?php echo esc_attr($openai_ready ? 'is-active' : ''); ?>">
                    <div class="gb-integration-provider-card__top">
                        <span class="gb-status-pill <?php echo esc_attr($openai_ready ? 'is-ready' : ''); ?>"><?php echo esc_html($openai_ready ? __('Configured', 'geeky-bot') : __('Available', 'geeky-bot')); ?></span>
                        <span class="gb-integration-provider-icon">03</span>
                    </div>
                    <h2><?php esc_html_e('OpenAI BYOK', 'geeky-bot'); ?></h2>
                    <p><?php esc_html_e('Optional bring-your-own-key answer mode. Geeky Bot sends only the shopper question and selected grounded store context from the server.', 'geeky-bot'); ?></p>
                    <div class="gb-integration-provider-checks">
                        <span class="<?php echo esc_attr($openai_key_saved ? 'is-ready' : 'is-missing'); ?>"><?php echo esc_html($openai_key_saved ? __('API key hidden', 'geeky-bot') : __('API key missing', 'geeky-bot')); ?></span>
                        <span><?php esc_html_e('Model controlled', 'geeky-bot'); ?></span>
                        <span><?php esc_html_e('Token limit enforced', 'geeky-bot'); ?></span>
                    </div>
                    <div class="gb-integration-provider-actions">
                        <a class="button <?php echo esc_attr($openai_ready ? 'button-primary' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-settings#gb-settings-ai')); ?>"><?php echo esc_html($openai_ready ? __('Review OpenAI setup', 'geeky-bot') : __('Configure BYOK', 'geeky-bot')); ?></a>
                    </div>
                </article>
            </section>

            <section class="gb-integration-action-grid" aria-label="<?php esc_attr_e('Answer mode safety actions', 'geeky-bot'); ?>">
                <div class="gb-panel gb-integration-next-panel">
                    <div class="gb-panel-heading">
                        <p class="gb-kicker"><?php esc_html_e('Next answer-mode action', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Confirm answer mode before launch', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Keep local mode for the safest baseline, or configure Zywrap/BYOK from Settings when you need AI-generated grounded answers.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-integration-next-action">
                        <div>
                            <span><?php esc_html_e('Recommended check', 'geeky-bot'); ?></span>
                            <strong><?php esc_html_e('Test the storefront widget after changing answer mode.', 'geeky-bot'); ?></strong>
                            <em><?php esc_html_e('Use a product search and one policy question to confirm the assistant still stays grounded.', 'geeky-bot'); ?></em>
                        </div>
                        <a class="button button-primary" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Test storefront widget', 'geeky-bot'); ?></a>
                    </div>
                </div>

                <div class="gb-panel gb-integration-security-panel">
                    <div class="gb-panel-heading">
                        <p class="gb-kicker"><?php esc_html_e('Security posture', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Secrets stay behind WordPress', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('The storefront receives public widget settings and REST nonce only. Provider secrets are saved server-side and are not printed into JavaScript.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-integration-security-grid">
                        <div><strong><?php esc_html_e('API keys hidden', 'geeky-bot'); ?></strong><span><?php esc_html_e('Saved secrets are not displayed after save.', 'geeky-bot'); ?></span></div>
                        <div><strong><?php esc_html_e('Nonce protected', 'geeky-bot'); ?></strong><span><?php esc_html_e('Admin changes require capability and nonce checks.', 'geeky-bot'); ?></span></div>
                        <div><strong><?php esc_html_e('Rate limited', 'geeky-bot'); ?></strong><span><?php esc_html_e('Public assistant requests are throttled for visitors.', 'geeky-bot'); ?></span></div>
                        <div><strong><?php esc_html_e('Grounded output', 'geeky-bot'); ?></strong><span><?php esc_html_e('No invented products, prices, coupons or policies.', 'geeky-bot'); ?></span></div>
                    </div>
                </div>
            </section>
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
            <section class="gb-settings-cockpit-hero" aria-label="<?php esc_attr_e('Geeky Bot settings control center', 'geeky-bot'); ?>">
                <div class="gb-settings-cockpit-hero__main">
                    <div class="gb-brand-head">
                        <?php $this->brand_lockup(__('AI sales assistant for WooCommerce', 'geeky-bot')); ?>
                        <span class="gb-brand-context"><?php esc_html_e('Assistant settings', 'geeky-bot'); ?></span>
                    </div>
                    <h1><?php esc_html_e('Sales assistant settings', 'geeky-bot'); ?></h1>
                    <p><?php esc_html_e('Configure the shopper-facing assistant in one place: widget behavior, buyer-language search, grounded answers, provider security, privacy, and rate limits.', 'geeky-bot'); ?></p>
                    <div class="gb-settings-cockpit-actions">
                        <a class="button button-primary" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Test storefront widget', 'geeky-bot'); ?></a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant')); ?>"><?php esc_html_e('Tune product intelligence', 'geeky-bot'); ?></a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot')); ?>"><?php esc_html_e('Back to dashboard', 'geeky-bot'); ?></a>
                    </div>
                    <div class="gb-settings-cockpit-signals" aria-label="<?php esc_attr_e('Current configuration signals', 'geeky-bot'); ?>">
                        <span><strong><?php echo esc_html($widget_enabled ? __('Live widget', 'geeky-bot') : __('Widget off', 'geeky-bot')); ?></strong><em><?php esc_html_e('Storefront', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($natural_enabled ? __('Natural search', 'geeky-bot') : __('Keyword search', 'geeky-bot')); ?></strong><em><?php esc_html_e('Buyer language', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($settings['provider_mode'] === 'local' ? __('Grounded mode', 'geeky-bot') : $provider_label); ?></strong><em><?php esc_html_e('Answer mode', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($history_enabled ? __('Review on', 'geeky-bot') : __('Review off', 'geeky-bot')); ?></strong><em><?php esc_html_e('Conversation review', 'geeky-bot'); ?></em></span>
                    </div>
                </div>
                <aside class="gb-settings-control-monitor">
                    <div class="gb-settings-monitor-ring" style="--gb-progress: <?php echo esc_attr($configuration_score); ?>;"><strong><?php echo esc_html($configuration_score); ?>%</strong><span><?php esc_html_e('Ready', 'geeky-bot'); ?></span></div>
                    <div class="gb-settings-monitor-body">
                        <p class="gb-kicker"><?php esc_html_e('Readiness monitor', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Configuration status', 'geeky-bot'); ?></h2>
                        <ul>
                            <li class="<?php echo esc_attr($widget_enabled ? 'is-ready' : 'is-warning'); ?>"><?php echo esc_html($widget_enabled ? __('Storefront widget is live', 'geeky-bot') : __('Storefront widget is disabled', 'geeky-bot')); ?></li>
                            <li class="<?php echo esc_attr($natural_enabled ? 'is-ready' : 'is-warning'); ?>"><?php echo esc_html($natural_enabled ? __('Natural buyer search is active', 'geeky-bot') : __('Natural buyer search is off', 'geeky-bot')); ?></li>
                            <li class="is-ready"><?php echo esc_html($provider_label); ?></li>
                            <li class="<?php echo esc_attr($history_enabled ? 'is-ready' : 'is-warning'); ?>"><?php echo esc_html($history_enabled ? __('Conversation review is on', 'geeky-bot') : __('Conversation history is off', 'geeky-bot')); ?></li>
                        </ul>
                    </div>
                </aside>
            </section>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice flag. ?>
            <?php if (!empty($_GET['updated'])) : ?><div class="gb-settings-notice" role="status"><strong><?php esc_html_e('Settings saved.', 'geeky-bot'); ?></strong><span><?php esc_html_e('Your storefront assistant will use the updated configuration.', 'geeky-bot'); ?></span></div><?php endif; ?>

            <form method="post" class="gb-settings-form gb-settings-layout gb-settings-premium-form">
                <?php wp_nonce_field('geekybot_save_settings'); ?>
                <input type="hidden" name="geekybot_settings_action" value="save" />
                <nav class="gb-settings-side gb-settings-side--modern" aria-label="<?php esc_attr_e('Settings sections', 'geeky-bot'); ?>">
                    <a href="#gb-settings-assistant"><span><?php esc_html_e('01', 'geeky-bot'); ?></span><?php esc_html_e('Storefront', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-search"><span><?php esc_html_e('02', 'geeky-bot'); ?></span><?php esc_html_e('Search', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-knowledge"><span><?php esc_html_e('03', 'geeky-bot'); ?></span><?php esc_html_e('Knowledge', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-ai"><span><?php esc_html_e('04', 'geeky-bot'); ?></span><?php esc_html_e('Answer mode', 'geeky-bot'); ?></a>
                    <a href="#gb-settings-privacy"><span><?php esc_html_e('05', 'geeky-bot'); ?></span><?php esc_html_e('Privacy', 'geeky-bot'); ?></a>
                </nav>
                <div class="gb-settings-main">
                    <section id="gb-settings-assistant" class="gb-panel gb-settings-card gb-settings-modern-card">
                        <div class="gb-settings-card-head"><div><p class="gb-kicker"><?php esc_html_e('Storefront experience', 'geeky-bot'); ?></p><h2><?php esc_html_e('Assistant and widget', 'geeky-bot'); ?></h2><p><?php esc_html_e('Set the shopper-facing name, first message, safe fallback answer, accent color, and product-card volume.', 'geeky-bot'); ?></p></div><div class="gb-settings-head-meta"><span><?php echo esc_html($widget_enabled ? __('Live', 'geeky-bot') : __('Off', 'geeky-bot')); ?></span><span><?php echo esc_html(absint($settings['max_products'])); ?> <?php esc_html_e('products', 'geeky-bot'); ?></span><span><?php echo esc_html(ucfirst($settings['button_position'])); ?></span></div></div>
                        <?php $this->settings_table_assistant($settings); ?>
                    </section>

                    <section id="gb-settings-search" class="gb-panel gb-settings-card gb-settings-modern-card">
                        <div class="gb-settings-card-head"><div><p class="gb-kicker"><?php esc_html_e('Catalog intelligence', 'geeky-bot'); ?></p><h2><?php esc_html_e('Buyer-language search', 'geeky-bot'); ?></h2><p><?php esc_html_e('Tune buyer-language understanding, synonym expansion, product matches, and ranking boosts.', 'geeky-bot'); ?></p></div><div class="gb-settings-head-meta"><span><?php echo esc_html($natural_enabled ? __('Natural search on', 'geeky-bot') : __('Natural search off', 'geeky-bot')); ?></span><span><?php echo esc_html($settings['search_close_match_mode'] === 'strict' ? __('Exact only', 'geeky-bot') : __('Smart matches', 'geeky-bot')); ?></span><span><?php echo esc_html($boost_count); ?> <?php esc_html_e('boosts', 'geeky-bot'); ?></span></div></div>
                        <?php $this->settings_table_search($settings, 'compact'); ?>
                    </section>

                    <section id="gb-settings-knowledge" class="gb-panel gb-settings-card gb-settings-modern-card">
                        <div class="gb-settings-card-head"><div><p class="gb-kicker"><?php esc_html_e('Grounded answers', 'geeky-bot'); ?></p><h2><?php esc_html_e('Store knowledge', 'geeky-bot'); ?></h2><p><?php esc_html_e('Select safe public pages for shipping, refund, return, payment and warranty answers.', 'geeky-bot'); ?></p></div><div class="gb-settings-head-meta"><span><?php echo esc_html(number_format_i18n($selected_policy_count)); ?> <?php esc_html_e('selected', 'geeky-bot'); ?></span><span><?php esc_html_e('Safe fallback answer', 'geeky-bot'); ?></span></div></div>
                        <div class="gb-settings-policy-grid">
                            <?php if (empty($pages)) : ?>
                                <div class="gb-settings-empty-card"><strong><?php esc_html_e('No public pages found.', 'geeky-bot'); ?></strong><span><?php esc_html_e('Create shipping, returns, refund, payment, warranty or privacy pages before enabling policy answers.', 'geeky-bot'); ?></span></div>
                            <?php else : foreach ($pages as $page) :
                                $page_id = absint($page->ID);
                                $is_selected = in_array($page_id, $selected_pages, true);
                                $title = strtolower((string) $page->post_title);
                                $looks_policy = preg_match('/shipping|return|refund|privacy|terms|warranty|policy|delivery|payment/', $title);
                                ?>
                                <label class="gb-settings-page-card <?php echo esc_attr($is_selected ? 'is-selected' : ''); ?> <?php echo esc_attr($looks_policy ? 'is-suggested' : ''); ?>">
                                    <input type="checkbox" name="policy_page_ids[]" value="<?php echo esc_attr($page_id); ?>" <?php checked($is_selected); ?> />
                                    <span><strong><?php echo esc_html($page->post_title); ?></strong><em><?php echo esc_html($looks_policy ? __('Likely policy page', 'geeky-bot') : __('Public page', 'geeky-bot')); ?></em></span>
                                </label>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="gb-settings-safe-note"><strong><?php esc_html_e('Safe policy answer rule', 'geeky-bot'); ?></strong><span><?php esc_html_e('If selected pages do not confirm the detail, Geeky Bot says it is not confirmed and directs the shopper to the source or store team.', 'geeky-bot'); ?></span></div>
                    </section>

                    <section id="gb-settings-ai" class="gb-panel gb-settings-card gb-settings-modern-card">
                        <div class="gb-settings-card-head"><div><p class="gb-kicker"><?php esc_html_e('Answer mode and security', 'geeky-bot'); ?></p><h2><?php esc_html_e('Answer mode', 'geeky-bot'); ?></h2><p><?php esc_html_e('Choose local grounded mode, Zywrap, or BYOK while keeping secrets server-side.', 'geeky-bot'); ?></p></div><div class="gb-settings-head-meta"><span><?php echo esc_html($provider_label); ?></span><span><?php esc_html_e('Keys hidden', 'geeky-bot'); ?></span></div></div>
                        <?php $this->settings_table_ai($settings); ?>
                    </section>

                    <section id="gb-settings-privacy" class="gb-panel gb-settings-card gb-settings-modern-card">
                        <div class="gb-settings-card-head"><div><p class="gb-kicker"><?php esc_html_e('Privacy and abuse protection', 'geeky-bot'); ?></p><h2><?php esc_html_e('History, retention and rate limits', 'geeky-bot'); ?></h2><p><?php esc_html_e('Set conversation storage, retention, visitor rate limits, and uninstall cleanup behavior.', 'geeky-bot'); ?></p></div><div class="gb-settings-head-meta"><span><?php echo esc_html(absint($settings['retention_days'])); ?> <?php esc_html_e('days', 'geeky-bot'); ?></span><span><?php echo esc_html(absint($settings['rate_limit_messages'])); ?> <?php esc_html_e('messages/window', 'geeky-bot'); ?></span></div></div>
                        <?php $this->settings_table_privacy($settings); ?>
                    </section>

                    <div class="gb-settings-savebar gb-settings-savebar--premium"><div><strong><?php esc_html_e('Save Geeky Bot settings', 'geeky-bot'); ?></strong><span><?php esc_html_e('Changes apply to the storefront assistant after saving.', 'geeky-bot'); ?></span></div><?php submit_button(__('Save Settings', 'geeky-bot'), 'primary', 'submit', false); ?></div>
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

            <section class="gb-conversation-metrics" aria-label="<?php esc_attr_e('Conversation summary', 'geeky-bot'); ?>">
                <article><span><?php esc_html_e('Conversations', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($summary['sessions'])); ?></strong><em><?php esc_html_e('Last 30 days', 'geeky-bot'); ?></em></article>
                <article><span><?php esc_html_e('Shopper messages', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($summary['shopper_messages'])); ?></strong><em><?php esc_html_e('Questions and refinements', 'geeky-bot'); ?></em></article>
                <article><span><?php esc_html_e('Product clicks', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($summary['product_clicks'])); ?></strong><em><?php esc_html_e('From chat product cards', 'geeky-bot'); ?></em></article>
                <article class="<?php echo esc_attr($active_review_count > 0 ? 'is-warning' : 'is-good'); ?>"><span><?php esc_html_e('Needs review', 'geeky-bot'); ?></span><strong><?php echo esc_html(number_format_i18n($active_review_count)); ?></strong><em><?php esc_html_e('Unique grounded-answer gaps', 'geeky-bot'); ?></em></article>
            </section>

            <nav class="gb-conversation-tabs" aria-label="<?php esc_attr_e('Conversation workspace', 'geeky-bot'); ?>">
                <a class="<?php echo esc_attr($workspace === 'conversations' ? 'is-active' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations&gb_view=conversations')); ?>">
                    <span><?php esc_html_e('Conversation stream', 'geeky-bot'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($summary['sessions'])); ?></strong>
                </a>
                <a class="<?php echo esc_attr($workspace === 'review' ? 'is-active' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations&gb_view=review&gb_review_status=needs-review')); ?>">
                    <span><?php esc_html_e('Needs review', 'geeky-bot'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($active_review_count)); ?></strong>
                </a>
            </nav>

            <?php if ($workspace === 'conversations') : ?>
            <section class="gb-conversation-toolbar">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="gb-conversation-search">
                    <input type="hidden" name="page" value="geekybot-conversations" />
                    <input type="hidden" name="gb_view" value="conversations" />
                    <label><span class="screen-reader-text"><?php esc_html_e('Search conversations', 'geeky-bot'); ?></span><input type="search" name="gb_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search shopper messages or conversation ID', 'geeky-bot'); ?>" /></label>
                    <button type="submit" class="button"><?php esc_html_e('Search', 'geeky-bot'); ?></button>
                    <?php if ($search !== '') : ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations&gb_view=conversations')); ?>"><?php esc_html_e('Clear', 'geeky-bot'); ?></a><?php endif; ?>
                </form>
                <div class="gb-inline-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('geekybot_export_conversations'); ?>
                        <input type="hidden" name="action" value="geekybot_export_conversations" />
                        <button type="submit" class="button"><?php esc_html_e('Export CSV', 'geeky-bot'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('geekybot_delete_all_conversations'); ?>
                        <input type="hidden" name="action" value="geekybot_delete_all_conversations" />
                        <button type="submit" class="button gb-button-danger" data-gb-confirm="<?php echo esc_attr(__('Delete all Geeky Bot conversations, unanswered questions, and click events? This cannot be undone.', 'geeky-bot')); ?>"><?php esc_html_e('Delete all data', 'geeky-bot'); ?></button>
                    </form>
                </div>
            </section>

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
                <section class="gb-panel gb-conversation-detail">
                    <div class="gb-conversation-detail__head">
                        <div><p class="gb-kicker"><?php echo esc_html($session_label); ?></p><h2><?php esc_html_e('Conversation transcript', 'geeky-bot'); ?></h2><p><?php echo esc_html($session_activity_label); ?></p></div>
                        <div class="gb-inline-actions">
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Back to list', 'geeky-bot'); ?></a>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_export_conversations'); ?>
                                <input type="hidden" name="action" value="geekybot_export_conversations" /><input type="hidden" name="session_id" value="<?php echo esc_attr(absint($session['id'])); ?>" />
                                <button type="submit" class="button"><?php esc_html_e('Export this conversation', 'geeky-bot'); ?></button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_delete_conversation_' . absint($session['id'])); ?>
                                <input type="hidden" name="action" value="geekybot_delete_conversation" /><input type="hidden" name="session_id" value="<?php echo esc_attr(absint($session['id'])); ?>" />
                                <button type="submit" class="button gb-button-danger" data-gb-confirm="<?php echo esc_attr(__('Delete this conversation and its analytics events?', 'geeky-bot')); ?>"><?php esc_html_e('Delete conversation', 'geeky-bot'); ?></button>
                            </form>
                        </div>
                    </div>
                    <div class="gb-conversation-transcript">
                        <?php if (empty($session_detail['messages'])) : ?><div class="gb-empty-state"><strong><?php esc_html_e('No stored messages', 'geeky-bot'); ?></strong></div><?php else : foreach ($session_detail['messages'] as $message) : ?>
                            <article class="gb-transcript-message gb-transcript-message--<?php echo esc_attr($message['direction']); ?>">
                                <div class="gb-transcript-message__meta"><strong><?php echo esc_html($message['direction'] === 'user' ? __('Shopper', 'geeky-bot') : __('Geeky Bot', 'geeky-bot')); ?></strong><span><?php echo esc_html($message['created_at']); ?></span><?php if ($message['intent'] !== '') : ?><code><?php echo esc_html($message['intent']); ?></code><?php endif; ?></div>
                                <p><?php echo esc_html($message['message']); ?></p>
                                <?php if (!empty($message['products'])) : ?><div class="gb-transcript-products"><span><?php esc_html_e('Products shown:', 'geeky-bot'); ?></span><?php foreach ($message['products'] as $product_name) : ?><em><?php echo esc_html($product_name); ?></em><?php endforeach; ?></div><?php endif; ?>
                            </article>
                        <?php endforeach; endif; ?>
                    </div>
                    <?php if (!empty($session_detail['events'])) : ?>
                        <div class="gb-conversation-events"><h3><?php esc_html_e('Shopper interactions', 'geeky-bot'); ?></h3><?php foreach ($session_detail['events'] as $event) : ?><div><span><?php echo esc_html($event['event_type'] === 'product_click' ? __('Product opened', 'geeky-bot') : __('Policy source opened', 'geeky-bot')); ?></span><strong><?php echo esc_html($event['object_label']); ?></strong><em><?php echo esc_html($event['created_at']); ?></em></div><?php endforeach; ?></div>
                    <?php endif; ?>
                </section>
            <?php else : ?>
                <section class="gb-panel gb-conversation-list-panel">
                    <div class="gb-panel-heading"><p class="gb-kicker"><?php esc_html_e('Recent conversations', 'geeky-bot'); ?></p><h2><?php esc_html_e('Conversation stream', 'geeky-bot'); ?></h2><p><?php esc_html_e('Open a conversation to see the transcript, response intents, products shown, unanswered questions, and product clicks.', 'geeky-bot'); ?></p></div>
                    <div class="gb-conversation-table-wrap"><table class="widefat striped gb-conversation-table"><thead><tr><th><?php esc_html_e('Conversation', 'geeky-bot'); ?></th><th><?php esc_html_e('Last shopper message', 'geeky-bot'); ?></th><th><?php esc_html_e('Messages', 'geeky-bot'); ?></th><th><?php esc_html_e('Gaps', 'geeky-bot'); ?></th><th><?php esc_html_e('Product clicks', 'geeky-bot'); ?></th><th><?php esc_html_e('Last activity', 'geeky-bot'); ?></th></tr></thead><tbody>
                        <?php if (empty($session_page['items'])) : ?><tr><td colspan="6"><div class="gb-empty-state"><strong><?php esc_html_e('No conversations found', 'geeky-bot'); ?></strong><span><?php esc_html_e('Storefront conversations appear here when history is enabled.', 'geeky-bot'); ?></span></div></td></tr><?php else : foreach ($session_page['items'] as $row) : ?>
                            <?php
                            $row_conversation_label = sprintf(
                                /* translators: %d: conversation ID. */
                                __('Conversation #%d', 'geeky-bot'),
                                absint($row['id'])
                            );
                            ?>
                            <tr><td><a href="<?php echo esc_url(add_query_arg(array('page' => 'geekybot-conversations', 'gb_session' => absint($row['id'])), admin_url('admin.php'))); ?>"><strong><?php echo esc_html($row_conversation_label); ?></strong></a><span><?php echo esc_html(absint($row['user_id']) > 0 ? __('Registered shopper', 'geeky-bot') : __('Guest shopper', 'geeky-bot')); ?></span></td><td><?php echo esc_html(wp_trim_words((string) $row['last_shopper_message'], 14)); ?></td><td><?php echo esc_html(number_format_i18n($row['message_count'])); ?></td><td><?php echo esc_html(number_format_i18n($row['unanswered_count'])); ?></td><td><?php echo esc_html(number_format_i18n($row['product_clicks'])); ?></td><td><?php echo esc_html($row['updated_at']); ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody></table></div>
                    <?php if ($session_page['pages'] > 1) : ?><nav class="gb-review-pagination"><?php echo wp_kses_post(paginate_links(array('base' => add_query_arg(array('page' => 'geekybot-conversations', 'gb_view' => 'conversations', 'gb_search' => $search, 'gb_paged' => '%#%'), admin_url('admin.php')), 'format' => '', 'current' => $session_page['page'], 'total' => $session_page['pages'], 'prev_text' => __('Previous', 'geeky-bot'), 'next_text' => __('Next', 'geeky-bot')))); ?></nav><?php endif; ?>
                </section>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($workspace === 'review') : ?>
            <section class="gb-review-workbench" aria-label="<?php esc_attr_e('Unanswered question workbench', 'geeky-bot'); ?>">
                <div class="gb-review-workbench__head">
                    <div>
                        <p class="gb-kicker"><?php esc_html_e('Unanswered-question log', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Fix the store data behind failed answers', 'geeky-bot'); ?></h2>
                        <p>
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
                    </div>
                    <div class="gb-review-head-controls">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="gb-review-search">
                            <input type="hidden" name="page" value="geekybot-conversations" />
                            <input type="hidden" name="gb_view" value="review" />
                            <input type="hidden" name="gb_review_status" value="<?php echo esc_attr($filter); ?>" />
                            <label>
                                <span class="screen-reader-text"><?php esc_html_e('Search needs review', 'geeky-bot'); ?></span>
                                <input type="search" name="gb_review_search" value="<?php echo esc_attr($review_search); ?>" placeholder="<?php esc_attr_e('Search questions, products, or policy areas', 'geeky-bot'); ?>" />
                            </label>
                            <button type="submit" class="button"><?php esc_html_e('Search', 'geeky-bot'); ?></button>
                            <?php if ($review_search !== '') : ?>
                                <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'geekybot-conversations', 'gb_view' => 'review', 'gb_review_status' => $filter), admin_url('admin.php'))); ?>"><?php esc_html_e('Clear', 'geeky-bot'); ?></a>
                            <?php endif; ?>
                        </form>
                        <div class="gb-review-filters">
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
                </div>
                <div class="gb-review-bucket-grid gb-review-bucket-grid--compact">
                    <?php $this->review_bucket_card(__('Catalog data', 'geeky-bot'), $review_buckets['product'], __('Unique product-data issues', 'geeky-bot'), 'product'); ?>
                    <?php $this->review_bucket_card(__('Policy coverage', 'geeky-bot'), $review_buckets['policy'], __('Unique selected-page gaps', 'geeky-bot'), 'policy'); ?>
                    <?php $this->review_bucket_card(__('Shopper wording', 'geeky-bot'), $review_buckets['synonym'], __('Unique wording gaps', 'geeky-bot'), 'synonym'); ?>
                    <?php $this->review_bucket_card(__('Buying actions', 'geeky-bot'), $review_buckets['commerce'], __('Unique Commerce Pro intents', 'geeky-bot'), 'commerce'); ?>
                </div>
                <div class="gb-review-card-list">
                    <?php if (empty($visible_questions)) : ?>
                        <div class="gb-empty-state gb-empty-state--large">
                            <strong><?php esc_html_e('No matching review issues', 'geeky-bot'); ?></strong>
                            <span><?php esc_html_e('Try another filter or search phrase, or continue testing the storefront assistant.', 'geeky-bot'); ?></span>
                        </div>
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
                            <article class="gb-review-card <?php echo esc_attr($handled_now ? 'is-handled' : ($is_ignored ? 'is-ignored' : 'needs-review')); ?>">
                                <div class="gb-review-card__main">
                                    <div class="gb-review-card__topline">
                                        <span class="gb-review-intent gb-review-intent--<?php echo esc_attr($bucket); ?>"><?php echo esc_html($meta['label']); ?></span>
                                        <span class="gb-status-pill <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                                    </div>
                                    <h3><?php echo esc_html(wp_trim_words($question->question, 24)); ?></h3>
                                    <div class="gb-review-meta">
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
                                    <div class="gb-review-suggestion">
                                        <strong><?php esc_html_e('Recommended action', 'geeky-bot'); ?></strong>
                                        <p><?php echo esc_html($handled_now ? __('Retest only if this still fails in the storefront widget.', 'geeky-bot') : $meta['action']); ?></p>
                                    </div>
                                </div>
                                <div class="gb-review-card__actions">
                                    <?php if (!$handled_now && !$is_ignored) : ?>
                                        <a class="button button-primary" href="<?php echo esc_url($source_url); ?>"><?php echo esc_html($fix_label); ?></a>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('geekybot_review_action'); ?>
                                            <input type="hidden" name="action" value="geekybot_review_action" />
                                            <input type="hidden" name="review_hash" value="<?php echo esc_attr($question->gb_hash); ?>" />
                                            <input type="hidden" name="legacy_review_hash" value="<?php echo esc_attr($question->gb_legacy_hash); ?>" />
                                            <input type="hidden" name="review_action" value="handled" />
                                            <?php echo $redirect_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped above. ?>
                                            <button type="submit" class="button"><?php esc_html_e('Mark handled', 'geeky-bot'); ?></button>
                                        </form>
                                    <?php elseif ($is_ignored || $is_manual_handled) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('geekybot_review_action'); ?>
                                            <input type="hidden" name="action" value="geekybot_review_action" />
                                            <input type="hidden" name="review_hash" value="<?php echo esc_attr($question->gb_hash); ?>" />
                                            <input type="hidden" name="legacy_review_hash" value="<?php echo esc_attr($question->gb_legacy_hash); ?>" />
                                            <input type="hidden" name="review_action" value="restore" />
                                            <?php echo $redirect_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped above. ?>
                                            <button type="submit" class="button button-primary"><?php esc_html_e('Restore to review', 'geeky-bot'); ?></button>
                                        </form>
                                    <?php else : ?>
                                        <a class="button button-primary" href="<?php echo esc_url($test_url); ?>"><?php esc_html_e('Retest phrase', 'geeky-bot'); ?></a>
                                    <?php endif; ?>

                                    <details class="gb-review-more">
                                        <summary aria-label="<?php esc_attr_e('More review actions', 'geeky-bot'); ?>">•••</summary>
                                        <div class="gb-review-more__menu">
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
                    <nav class="gb-review-pagination" aria-label="<?php esc_attr_e('Unanswered question pages', 'geeky-bot'); ?>">
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
                __('Commerce Pro license and installer', 'geeky-bot'),
                __('Activate a license, connect this site to geekybot.com, install the protected Commerce Pro add-on, and keep buying actions locked to authorized sites.', 'geeky-bot'),
                __('Commerce Pro', 'geeky-bot'),
                'https://geekybot.com/',
                __('Open geekybot.com', 'geeky-bot'),
                true
            ); ?>

            <?php if ($notice && isset($notice_messages[$notice])) : ?>
                <div class="gb-license-page-message is-success"><span aria-hidden="true">✓</span><p><?php echo esc_html($notice_messages[$notice]); ?></p></div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="gb-license-page-message is-error"><span aria-hidden="true">!</span><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <section class="gb-license-command-panel gb-panel <?php echo esc_attr($status_class); ?>">
                <div class="gb-license-command-panel__main">
                    <p class="gb-kicker"><?php esc_html_e('Protected add-on access', 'geeky-bot'); ?></p>
                    <h2><?php echo esc_html($license['active']
                        ? __('Commerce Pro is authorized on this site', 'geeky-bot')
                        : ($limit_reached ? __('Commerce Pro is not authorized on this site', 'geeky-bot') : __('Activate Commerce Pro for this site', 'geeky-bot'))
                    ); ?></h2>
                    <p><?php echo esc_html($limit_reached
                        ? __('The license is valid, but this site is beyond its included activation allowance. Commerce Pro remains unavailable here until an activation is freed or added.', 'geeky-bot')
                        : __('A copied ZIP is not enough. Commerce Pro buying actions require a valid license, an allowed site activation, and a live or cached entitlement check.', 'geeky-bot')
                    ); ?></p>
                    <div class="gb-license-status-strip">
                        <span><strong><?php echo esc_html($license['label']); ?></strong><em><?php esc_html_e('License', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($plugin_label); ?></strong><em><?php esc_html_e('Commerce Pro plugin', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($update_label); ?></strong><em><?php esc_html_e('Update', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($license['isStaging'] === 'yes' ? __('Staging', 'geeky-bot') : __('Production', 'geeky-bot')); ?></strong><em><?php esc_html_e('Site type', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($license['maskedKey'] ? $license['maskedKey'] : __('No key', 'geeky-bot')); ?></strong><em><?php esc_html_e('Key', 'geeky-bot'); ?></em></span>
                        <span><strong><?php echo esc_html($license['lastCheckedAt'] ? $license['lastCheckedAt'] : __('Not checked yet', 'geeky-bot')); ?></strong><em><?php esc_html_e('License last checked', 'geeky-bot'); ?></em></span>
                    </div>
                    <?php if (!empty($license['lastError'])) : ?>
                        <p class="gb-license-server-error"><span aria-hidden="true">!</span><?php echo esc_html($license['lastError']); ?></p>
                    <?php elseif (!$error && !$license['active'] && $license['status'] !== 'inactive' && !empty($license['message'])) : ?>
                        <p class="gb-license-server-error"><span aria-hidden="true">!</span><?php echo esc_html($license['message']); ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="gb-grid gb-grid-2 gb-license-grid">
                <div class="gb-panel gb-license-form-panel">
                    <div class="gb-panel-heading"><h2><?php esc_html_e('License activation', 'geeky-bot'); ?></h2><p><?php esc_html_e('Enter the Commerce Pro key from geekybot.com. The key is encrypted with this WordPress installation’s salts, masked after save, and never sent to storefront JavaScript.', 'geeky-bot'); ?></p></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb-license-form">
                        <?php wp_nonce_field('geekybot_license_action'); ?>
                        <input type="hidden" name="action" value="geekybot_license_activate" />
                        <label>
                            <span><?php esc_html_e('License key', 'geeky-bot'); ?></span>
                            <input type="text" name="license_key" value="" placeholder="GB-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" />
                        </label>
                        <?php submit_button(__('Activate license', 'geeky-bot'), 'primary', 'submit', false); ?>
                    </form>
                    <div class="gb-license-action-row">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('geekybot_license_action'); ?>
                            <input type="hidden" name="action" value="geekybot_license_refresh" />
                            <?php submit_button(__('Refresh status', 'geeky-bot'), 'secondary', 'submit', false); ?>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('geekybot_license_action'); ?>
                            <input type="hidden" name="action" value="geekybot_license_deactivate" />
                            <?php submit_button($license['active'] ? __('Deactivate this site', 'geeky-bot') : __('Clear saved license', 'geeky-bot'), 'secondary', 'submit', false); ?>
                        </form>
                    </div>
                </div>

                <div class="gb-panel gb-license-install-panel">
                    <div class="gb-panel-heading"><h2><?php esc_html_e('Commerce Pro add-on', 'geeky-bot'); ?></h2><p><?php esc_html_e('Install, activate, and update Commerce Pro from a protected geekybot.com package after the site entitlement is valid.', 'geeky-bot'); ?></p></div>
                    <ul class="gb-checklist gb-license-checklist">
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
                    <div class="gb-license-action-row">
                        <?php if (!$plugin['installed'] && $can_install) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_install_commerce_pro" />
                                <?php submit_button(__('Install Commerce Pro', 'geeky-bot'), 'primary', 'submit', false, !empty($license['downloadsAllowed']) ? array() : array('disabled' => 'disabled')); ?>
                            </form>
                        <?php endif; ?>
                        <?php if ($plugin['installed'] && !$plugin['active'] && $can_activate) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_activate_commerce_pro" />
                                <?php submit_button(__('Activate Commerce Pro', 'geeky-bot'), 'primary', 'submit', false, $license['active'] ? array() : array('disabled' => 'disabled')); ?>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($update['update_available']) && !empty($update['can_update']) && current_user_can('update_plugins')) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('geekybot_license_action'); ?>
                                <input type="hidden" name="action" value="geekybot_update_commerce_pro" />
                                <?php submit_button(__('Update Commerce Pro', 'geeky-bot'), 'primary', 'submit', false); ?>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('geekybot_license_action'); ?>
                            <input type="hidden" name="action" value="geekybot_refresh_commerce_pro_update" />
                            <?php submit_button(__('Check for update', 'geeky-bot'), 'secondary', 'submit', false); ?>
                        </form>
                        <a class="button" href="<?php echo esc_url(admin_url('plugins.php')); ?>"><?php esc_html_e('Open plugins', 'geeky-bot'); ?></a>
                    </div>
                </div>
            </section>

            <section class="gb-panel gb-license-update-panel">
                <div class="gb-panel-heading"><h2><?php esc_html_e('Commerce Pro update channel', 'geeky-bot'); ?></h2><p><?php esc_html_e('Version metadata is checked from the CDN first. Protected ZIP downloads are requested from geekybot.com only when a valid update entitlement exists.', 'geeky-bot'); ?></p></div>
                <?php if (!empty($update['update_available'])) : ?>
                    <div class="gb-pro-update-card">
                        <div class="gb-pro-update-card__icon" aria-hidden="true">↻</div>
                        <div class="gb-pro-update-card__body">
                            <p class="gb-kicker"><?php esc_html_e('Update available', 'geeky-bot'); ?></p>
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
                                <div class="gb-pro-update-card__changelog"><?php echo wp_kses_post($update['metadata']['changelog']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="gb-pro-update-card__actions">
                            <?php if (!empty($update['can_update']) && current_user_can('update_plugins')) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('geekybot_license_action'); ?>
                                    <input type="hidden" name="action" value="geekybot_update_commerce_pro" />
                                    <?php submit_button(__('Update Commerce Pro', 'geeky-bot'), 'primary', 'submit', false); ?>
                                </form>
                            <?php else : ?>
                                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-addons')); ?>"><?php esc_html_e('Review update entitlement', 'geeky-bot'); ?></a>
                            <?php endif; ?>
                            <a class="button" href="<?php echo esc_url(admin_url('plugins.php')); ?>"><?php esc_html_e('Open plugins page', 'geeky-bot'); ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="gb-license-detail-grid">
                    <span><strong><?php echo esc_html(!empty($update['latest_version']) ? $update['latest_version'] : __('Unknown', 'geeky-bot')); ?></strong><em><?php esc_html_e('Latest CDN version', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($update['metadata']['channel']) ? strtoupper($update['metadata']['channel']) : strtoupper($update_settings['update_channel'])); ?></strong><em><?php esc_html_e('Channel', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($license['updatesAllowed']) ? __('Allowed', 'geeky-bot') : __('Renewal required', 'geeky-bot')); ?></strong><em><?php esc_html_e('Update entitlement', 'geeky-bot'); ?></em></span>
                    <span><strong><?php echo esc_html(!empty($update['metadata']['critical']) && $update['metadata']['critical'] === 'yes' ? __('Yes', 'geeky-bot') : __('No', 'geeky-bot')); ?></strong><em><?php esc_html_e('Critical flag', 'geeky-bot'); ?></em></span>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gb-settings-form gb-license-update-form">
                    <?php wp_nonce_field('geekybot_license_action'); ?>
                    <input type="hidden" name="action" value="geekybot_save_commerce_pro_updates" />
                    <div class="gb-update-settings-grid">
                        <label class="gb-update-setting-card"><span><?php esc_html_e('Release channel', 'geeky-bot'); ?></span><select name="commerce_pro_updates[update_channel]"><option value="stable" <?php selected($update_settings['update_channel'], 'stable'); ?>><?php esc_html_e('Stable', 'geeky-bot'); ?></option><option value="beta" <?php selected($update_settings['update_channel'], 'beta'); ?>><?php esc_html_e('Beta', 'geeky-bot'); ?></option><option value="dev" <?php selected($update_settings['update_channel'], 'dev'); ?>><?php esc_html_e('Dev', 'geeky-bot'); ?></option></select></label>
                        <label class="gb-update-setting-card"><span><?php esc_html_e('Automatic updates', 'geeky-bot'); ?></span><select name="commerce_pro_updates[auto_update_mode]"><option value="manual" <?php selected($update_settings['auto_update_mode'], 'manual'); ?>><?php esc_html_e('Manual updates only', 'geeky-bot'); ?></option><option value="critical" <?php selected($update_settings['auto_update_mode'], 'critical'); ?>><?php esc_html_e('Critical security updates only', 'geeky-bot'); ?></option><option value="patch" <?php selected($update_settings['auto_update_mode'], 'patch'); ?>><?php esc_html_e('Patch releases only', 'geeky-bot'); ?></option><option value="minor" <?php selected($update_settings['auto_update_mode'], 'minor'); ?>><?php esc_html_e('Patch and minor releases', 'geeky-bot'); ?></option><option value="stable" <?php selected($update_settings['auto_update_mode'], 'stable'); ?>><?php esc_html_e('All stable releases', 'geeky-bot'); ?></option></select></label>
                    </div>
                    <label class="gb-update-security-toggle"><input type="hidden" name="commerce_pro_updates[auto_update_critical]" value="no" /><input type="checkbox" name="commerce_pro_updates[auto_update_critical]" value="yes" <?php checked($update_settings['auto_update_critical'], 'yes'); ?> /> <span><strong><?php esc_html_e('Always allow critical security auto-updates', 'geeky-bot'); ?></strong><em><?php esc_html_e('Recommended for live WooCommerce stores. The package still requires valid update entitlement from geekybot.com.', 'geeky-bot'); ?></em></span></label>
                    <?php submit_button(__('Save update settings', 'geeky-bot'), 'primary', 'submit', false); ?>
                </form>
            </section>

            <section class="gb-panel gb-license-details-panel">
                <div class="gb-panel-heading"><h2><?php esc_html_e('Site activation details', 'geeky-bot'); ?></h2><p><?php esc_html_e('These values are sent to geekybot.com during activation, refresh, protected download, and update checks.', 'geeky-bot'); ?></p></div>
                <div class="gb-license-detail-grid">
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
            <section class="gb-unified-hero gb-pro-promo-hero" aria-label="<?php esc_attr_e('Commerce Pro overview', 'geeky-bot'); ?>">
                <div class="gb-unified-hero__main">
                    <div class="gb-brand-head">
                        <?php $this->brand_lockup(__('AI sales assistant for WooCommerce', 'geeky-bot')); ?>
                    </div>
                    <span class="gb-brand-context gb-page-context"><?php esc_html_e('Commerce Pro', 'geeky-bot'); ?></span>
                    <h1><?php esc_html_e('Let shoppers buy without leaving the chat', 'geeky-bot'); ?></h1>
                    <p><?php esc_html_e('The free assistant helps shoppers find products. Commerce Pro finishes the sale: variation selection, cart control, checkout handoff, order help, comparisons, recommendations, coupons, and human follow-up — all inside the same conversation and grounded in your WooCommerce data.', 'geeky-bot'); ?></p>
                    <div class="gb-unified-actions">
                        <a class="button button-primary" href="<?php echo esc_url($cta_url); ?>" <?php echo $has_license ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>><?php echo esc_html($cta_label); ?></a>
                        <?php if (!$has_license) : ?>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-addons')); ?>"><?php esc_html_e('I already have a license', 'geeky-bot'); ?></a>
                        <?php endif; ?>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><?php esc_html_e('Preview real examples', 'geeky-bot'); ?></a>
                    </div>
                    <div class="gb-unified-signals" aria-label="<?php esc_attr_e('Commerce Pro highlights', 'geeky-bot'); ?>">
                        <span><strong><?php esc_html_e('18', 'geeky-bot'); ?></strong><em><?php esc_html_e('Pro buying features', 'geeky-bot'); ?></em></span>
                        <span><strong><?php esc_html_e('In-chat', 'geeky-bot'); ?></strong><em><?php esc_html_e('Cart and checkout', 'geeky-bot'); ?></em></span>
                        <span><strong><?php esc_html_e('Grounded', 'geeky-bot'); ?></strong><em><?php esc_html_e('Safe sales rules', 'geeky-bot'); ?></em></span>
                    </div>
                </div>
                <aside class="gb-unified-monitor">
                    <p class="gb-kicker"><?php esc_html_e('Why stores upgrade', 'geeky-bot'); ?></p>
                    <h2><?php esc_html_e('From product finder to sales channel', 'geeky-bot'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Shoppers complete the purchase inside the assistant flow.', 'geeky-bot'); ?></li>
                        <li><?php esc_html_e('Every buying action uses your live WooCommerce cart and orders.', 'geeky-bot'); ?></li>
                        <li><?php esc_html_e('All controls stay in this dashboard, protected by safe guardrails.', 'geeky-bot'); ?></li>
                    </ul>
                </aside>
            </section>

            <?php if ($has_license && !$plugin['active']) : ?>
                <div class="gb-license-page-message is-success"><span aria-hidden="true">✓</span><p><?php esc_html_e('Your license is already active on this site. Install and activate the Commerce Pro add-on to unlock buying actions.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>

            <section class="gb-panel gb-pro-promo-features">
                <div class="gb-panel-heading">
                    <p class="gb-kicker"><?php esc_html_e('Buying engine', 'geeky-bot'); ?></p>
                    <h2><?php esc_html_e('What Commerce Pro unlocks', 'geeky-bot'); ?></h2>
                    <p><?php esc_html_e('Every feature works inside the storefront assistant your shoppers already use — no extra widget, no theme changes.', 'geeky-bot'); ?></p>
                </div>
                <div class="gb-pro-promo-feature-grid">
                    <div class="gb-pro-promo-feature"><span>01</span><strong><?php esc_html_e('Add to cart in chat', 'geeky-bot'); ?></strong><em><?php esc_html_e('Simple products and variable products with option selection.', 'geeky-bot'); ?></em></div>
                    <div class="gb-pro-promo-feature"><span>02</span><strong><?php esc_html_e('Cart control', 'geeky-bot'); ?></strong><em><?php esc_html_e('Shoppers review the cart, change quantities, and remove items by asking.', 'geeky-bot'); ?></em></div>
                    <div class="gb-pro-promo-feature"><span>03</span><strong><?php esc_html_e('Checkout handoff', 'geeky-bot'); ?></strong><em><?php esc_html_e('A checkout button appears as soon as the cart has items.', 'geeky-bot'); ?></em></div>
                    <div class="gb-pro-promo-feature"><span>04</span><strong><?php esc_html_e('Order help', 'geeky-bot'); ?></strong><em><?php esc_html_e('Logged-in customers ask about their own recent orders and status.', 'geeky-bot'); ?></em></div>
                    <div class="gb-pro-promo-feature"><span>05</span><strong><?php esc_html_e('Product comparison', 'geeky-bot'); ?></strong><em><?php esc_html_e('Clear side-by-side answers when shoppers compare products.', 'geeky-bot'); ?></em></div>
                    <div class="gb-pro-promo-feature"><span>06</span><strong><?php esc_html_e('Smart recommendations', 'geeky-bot'); ?></strong><em><?php esc_html_e('Suggestions grounded in your own catalog, never invented.', 'geeky-bot'); ?></em></div>
                    <div class="gb-pro-promo-feature"><span>07</span><strong><?php esc_html_e('Coupons and deals', 'geeky-bot'); ?></strong><em><?php esc_html_e('Safe exposure of existing store coupons only.', 'geeky-bot'); ?></em></div>
                    <div class="gb-pro-promo-feature"><span>08</span><strong><?php esc_html_e('Leads and human handoff', 'geeky-bot'); ?></strong><em><?php esc_html_e('Capture follow-up requests when a human touch is needed.', 'geeky-bot'); ?></em></div>
                </div>
            </section>

            <section class="gb-grid gb-grid-2 gb-pro-promo-lower">
                <div class="gb-panel gb-pro-promo-try">
                    <div class="gb-panel-heading">
                        <p class="gb-kicker"><?php esc_html_e('Try before you buy', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('See it on your own store data', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('Guided Demo generates real shopper requests from your indexed products and labels the buying actions that Commerce Pro completes.', 'geeky-bot'); ?></p>
                    </div>
                    <div class="gb-button-row">
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-guided-demo')); ?>"><?php esc_html_e('Open Guided Demo', 'geeky-bot'); ?></a>
                        <a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Test storefront widget', 'geeky-bot'); ?></a>
                    </div>
                </div>
                <div class="gb-panel gb-pro-promo-steps">
                    <div class="gb-panel-heading">
                        <p class="gb-kicker"><?php esc_html_e('Getting started', 'geeky-bot'); ?></p>
                        <h2><?php esc_html_e('Live in three steps', 'geeky-bot'); ?></h2>
                        <p><?php esc_html_e('No FTP and no manual ZIP handling — everything happens from this dashboard.', 'geeky-bot'); ?></p>
                    </div>
                    <ol class="gb-pro-promo-step-list">
                        <li><strong><?php esc_html_e('Get a license', 'geeky-bot'); ?></strong><em><?php esc_html_e('Purchase Commerce Pro on geekybot.com.', 'geeky-bot'); ?></em></li>
                        <li><strong><?php esc_html_e('Activate it here', 'geeky-bot'); ?></strong><em><?php esc_html_e('Enter the key on the Add-ons page to authorize this site.', 'geeky-bot'); ?></em></li>
                        <li><strong><?php esc_html_e('Install with one click', 'geeky-bot'); ?></strong><em><?php esc_html_e('The protected add-on installs and updates from this dashboard.', 'geeky-bot'); ?></em></li>
                    </ol>
                    <div class="gb-button-row">
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

    private function page_hero($title, $description, $kicker, $action_url = '', $action_label = '', $external = false) {
        $monitor = $this->page_monitor_data($kicker, $title);
        ?>
        <section class="gb-unified-hero" aria-label="<?php echo esc_attr($title); ?>">
            <div class="gb-unified-hero__main">
                <div class="gb-brand-head">
                    <?php $this->brand_lockup(__('AI sales assistant for WooCommerce', 'geeky-bot')); ?>
                </div>
                <span class="gb-brand-context gb-page-context"><?php echo esc_html($kicker); ?></span>
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html($description); ?></p>
                <?php if ($action_url && $action_label) : ?>
                    <div class="gb-unified-actions">
                        <a class="button button-primary" href="<?php echo esc_url($action_url); ?>" <?php echo $external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html($action_label); ?></a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=geekybot-settings')); ?>"><?php esc_html_e('Open settings', 'geeky-bot'); ?></a>
                    </div>
                <?php endif; ?>
                <div class="gb-unified-signals" aria-label="<?php esc_attr_e('Page signals', 'geeky-bot'); ?>">
                    <?php foreach ($monitor['signals'] as $signal) : ?>
                        <span><strong><?php echo esc_html($signal[0]); ?></strong><em><?php echo esc_html($signal[1]); ?></em></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <aside class="gb-unified-monitor">
                <p class="gb-kicker"><?php esc_html_e('Readiness monitor', 'geeky-bot'); ?></p>
                <h2><?php echo esc_html($monitor['title']); ?></h2>
                <ul>
                    <?php foreach ($monitor['checks'] as $check) : ?>
                        <li><?php echo esc_html($check); ?></li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        </section>
    <?php }

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

        $default = array(
            'title' => $title,
            'signals' => array(
                array(__('Guided', 'geeky-bot'), __('Admin flow', 'geeky-bot')),
                array(__('Grounded', 'geeky-bot'), __('Store data', 'geeky-bot')),
                array(__('Scoped', 'geeky-bot'), __('Plugin UI', 'geeky-bot')),
            ),
            'checks' => array(
                __('Widget settings stay inside Geeky Bot pages.', 'geeky-bot'),
                __('Actions use store catalog, policy pages, and saved settings.', 'geeky-bot'),
                __('Public output remains grounded and safe.', 'geeky-bot'),
            ),
        );

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

        if (strpos($key, 'safe') !== false) {
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
        <div class="gb-panel gb-search-controls">
            <div class="gb-panel-heading"><h2><?php esc_html_e('Buyer search controls', 'geeky-bot'); ?></h2><p><?php esc_html_e('Tune buyer-language understanding without editing code. Use the product index tools above after catalog changes.', 'geeky-bot'); ?></p></div>
            <form method="post" class="gb-search-controls-form">
                <?php wp_nonce_field('geekybot_save_settings'); ?>
                <input type="hidden" name="geekybot_settings_action" value="save" />
                <input type="hidden" name="geekybot_settings_scope" value="partial" />
                <input type="hidden" name="geekybot_redirect" value="<?php echo esc_url(admin_url('admin.php?page=geekybot-product-assistant')); ?>" />
                <?php $this->settings_table_search($settings, 'compact'); ?>
                <div class="gb-search-control-actions">
                    <div class="gb-search-control-primary">
                        <?php submit_button(__('Save buyer search settings', 'geeky-bot'), 'primary', 'submit', false); ?>
                    </div>
                    <div class="gb-search-control-reset-group" aria-label="<?php esc_attr_e('Reset actions', 'geeky-bot'); ?>">
                        <span><?php esc_html_e('Reset tools', 'geeky-bot'); ?></span>
                        <button type="submit" name="geekybot_reset_search" value="synonyms" class="button" data-gb-confirm="<?php esc_attr_e('Restore the default synonym examples? Your custom synonym text will be replaced.', 'geeky-bot'); ?>"><?php esc_html_e('Reset default synonyms', 'geeky-bot'); ?></button>
                        <button type="submit" name="geekybot_reset_search" value="all" class="button" data-gb-confirm="<?php esc_attr_e('Reset all buyer search settings to their defaults?', 'geeky-bot'); ?>"><?php esc_html_e('Reset buyer search settings', 'geeky-bot'); ?></button>
                    </div>
                </div>
            </form>
            <div class="gb-index-mini-status">
                <span><b><?php esc_html_e('Products indexed', 'geeky-bot'); ?></b><?php echo esc_html(number_format_i18n($indexed_count)); ?></span>
                <span><b><?php esc_html_e('Last full index', 'geeky-bot'); ?></b><?php echo esc_html($last_rebuild ? $last_rebuild : __('Never', 'geeky-bot')); ?></span>
                <span><b><?php esc_html_e('Automatic indexing', 'geeky-bot'); ?></b><?php echo esc_html($this->product_index_status_text($index_status)); ?></span>
            </div>
        </div>
    <?php }

    private function nlp_action_examples() { ?>
        <div class="gb-panel gb-nlp-action-panel">
            <div class="gb-panel-heading"><span class="gb-section-kicker"><?php esc_html_e('Intent separation', 'geeky-bot'); ?></span><h2><?php esc_html_e('Storefront intent checklist', 'geeky-bot'); ?></h2><p><?php esc_html_e('Use these grouped phrases in the storefront widget to confirm that search, compare, cart, orders, deals, and human handoff stay separated.', 'geeky-bot'); ?></p></div>
            <div class="gb-nlp-action-grid">
                <?php $this->nlp_action_group(__('Search', 'geeky-bot'), array('comfortable shoes size 42 red and white', 'blue hoodie under 60', 'not too expensive walking shoes')); ?>
                <?php $this->nlp_action_group(__('Compare', 'geeky-bot'), array('compare first and third', 'what is difference between hoodie and hoodie with logo', 'compare cheaper one and second')); ?>
                <?php $this->nlp_action_group(__('Cart', 'geeky-bot'), array('add second product', 'remove first item', 'make belt quantity 3')); ?>
                <?php $this->nlp_action_group(__('Orders', 'geeky-bot'), array('what did I buy last time', 'show my latest order', 'where is my parcel')); ?>
                <?php $this->nlp_action_group(__('Deals', 'geeky-bot'), array('any promo code', 'cheapest today', 'show sale items')); ?>
                <?php $this->nlp_action_group(__('Human handoff', 'geeky-bot'), array('problem with my order', 'I want to complain', 'talk to human')); ?>
            </div>
        </div>
    <?php }

    private function nlp_action_group($title, $phrases) { ?>
        <div class="gb-nlp-action-group"><strong><?php echo esc_html($title); ?></strong><?php foreach ((array) $phrases as $phrase) : ?><code><?php echo esc_html($phrase); ?></code><?php endforeach; ?></div>
    <?php }

    private function settings_table_search($settings, $mode = 'table') {
        $compact = $mode === 'compact';
        $wrap_class = $compact ? 'gb-search-control-stack' : 'form-table gb-form-table';
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
            <label class="gb-toggle-row"><input type="hidden" name="natural_search_enabled" value="no" /><input type="checkbox" name="natural_search_enabled" value="yes" <?php checked($settings['natural_search_enabled'], 'yes'); ?> /> <span><strong><?php esc_html_e('Natural buyer search', 'geeky-bot'); ?></strong><em><?php esc_html_e('Understand long phrases, price words, color, size and soft preferences.', 'geeky-bot'); ?></em></span></label>
            <label><span><?php esc_html_e('Product match behavior', 'geeky-bot'); ?></span><select name="search_close_match_mode"><option value="smart" <?php selected($settings['search_close_match_mode'], 'smart'); ?>><?php esc_html_e('Smart product matches', 'geeky-bot'); ?></option><option value="strict" <?php selected($settings['search_close_match_mode'], 'strict'); ?>><?php esc_html_e('Exact matches only', 'geeky-bot'); ?></option></select></label>
            <label><span><?php esc_html_e('Minimum score', 'geeky-bot'); ?></span><input name="search_min_score" type="number" min="1" max="200" value="<?php echo esc_attr(absint($settings['search_min_score'])); ?>" /></label>
            <div class="gb-checkbox-grid">
                <?php $this->search_boost_checkbox('search_boost_in_stock', __('Boost in-stock products', 'geeky-bot'), $settings); ?>
                <?php $this->search_boost_checkbox('search_boost_sale', __('Boost sale products', 'geeky-bot'), $settings); ?>
                <?php $this->search_boost_checkbox('search_boost_rating', __('Boost ratings', 'geeky-bot'), $settings); ?>
                <?php $this->search_boost_checkbox('search_boost_popularity', __('Boost popularity', 'geeky-bot'), $settings); ?>
            </div>
            <label><span><?php esc_html_e('Custom synonyms', 'geeky-bot'); ?></span><textarea name="search_custom_synonyms" rows="7" placeholder="<?php echo esc_attr($synonym_placeholder); ?>"><?php echo esc_textarea($settings['search_custom_synonyms']); ?></textarea><em class="gb-synonym-help"><?php esc_html_e('One rule per line: shopper words = catalog words. Example: comfy = comfortable, soft, cushioned. Use words shoppers type on the left and catalog words/attributes on the right.', 'geeky-bot'); ?></em></label>
        <?php else : ?>
            <tr><th scope="row"><?php esc_html_e('Natural buyer search', 'geeky-bot'); ?></th><td><label><input type="hidden" name="natural_search_enabled" value="no" /><input type="checkbox" name="natural_search_enabled" value="yes" <?php checked($settings['natural_search_enabled'], 'yes'); ?> /> <?php esc_html_e('Understand long buyer phrases instead of keyword-only search.', 'geeky-bot'); ?></label></td></tr>
            <tr><th scope="row"><label for="search_close_match_mode"><?php esc_html_e('Product match behavior', 'geeky-bot'); ?></label></th><td><select id="search_close_match_mode" name="search_close_match_mode"><option value="smart" <?php selected($settings['search_close_match_mode'], 'smart'); ?>><?php esc_html_e('Smart product matches when exact color/size is not confirmed', 'geeky-bot'); ?></option><option value="strict" <?php selected($settings['search_close_match_mode'], 'strict'); ?>><?php esc_html_e('Exact matches only', 'geeky-bot'); ?></option></select></td></tr>
            <tr><th scope="row"><label for="search_min_score"><?php esc_html_e('Minimum result score', 'geeky-bot'); ?></label></th><td><input id="search_min_score" name="search_min_score" type="number" min="1" max="200" value="<?php echo esc_attr(absint($settings['search_min_score'])); ?>" /><p class="description"><?php esc_html_e('Keep low unless you are seeing weak results. Higher values hide more close matches.', 'geeky-bot'); ?></p></td></tr>
            <tr><th scope="row"><?php esc_html_e('Ranking boosts', 'geeky-bot'); ?></th><td><div class="gb-checkbox-grid"><?php $this->search_boost_checkbox('search_boost_in_stock', __('Boost in-stock products', 'geeky-bot'), $settings); ?><?php $this->search_boost_checkbox('search_boost_sale', __('Boost sale products', 'geeky-bot'), $settings); ?><?php $this->search_boost_checkbox('search_boost_rating', __('Boost ratings', 'geeky-bot'), $settings); ?><?php $this->search_boost_checkbox('search_boost_popularity', __('Boost popularity', 'geeky-bot'), $settings); ?></div></td></tr>
            <tr><th scope="row"><label for="search_custom_synonyms"><?php esc_html_e('Custom synonyms', 'geeky-bot'); ?></label></th><td><textarea id="search_custom_synonyms" name="search_custom_synonyms" rows="7" class="large-text code" placeholder="<?php echo esc_attr($synonym_placeholder); ?>"><?php echo esc_textarea($settings['search_custom_synonyms']); ?></textarea><p class="description gb-synonym-help"><?php esc_html_e('One rule per line: shopper words = catalog words. Example: comfy = comfortable, soft, cushioned. Use words shoppers type on the left and catalog words/attributes on the right. These are added to the built-in search synonyms.', 'geeky-bot'); ?></p></td></tr>
        <?php endif;
    }

    private function search_boost_checkbox($name, $label, $settings) { ?>
        <label class="gb-check-option"><input type="hidden" name="<?php echo esc_attr($name); ?>" value="no" /><input type="checkbox" name="<?php echo esc_attr($name); ?>" value="yes" <?php checked(isset($settings[$name]) ? $settings[$name] : 'yes', 'yes'); ?> /> <span><?php echo esc_html($label); ?></span></label>
    <?php }

    private function settings_table_assistant($settings) { ?>
        <div class="gb-settings-field-grid">
            <label class="gb-toggle-row gb-settings-toggle-card"><input type="hidden" name="widget_enabled" value="no" /><input type="checkbox" name="widget_enabled" value="yes" <?php checked($settings['widget_enabled'], 'yes'); ?> /> <span><strong><?php esc_html_e('Enable storefront widget', 'geeky-bot'); ?></strong><em><?php esc_html_e('Show the floating assistant button on public store pages.', 'geeky-bot'); ?></em></span></label>
            <label class="gb-settings-field"><span><?php esc_html_e('Assistant name', 'geeky-bot'); ?></span><input id="assistant_name" name="assistant_name" type="text" value="<?php echo esc_attr($settings['assistant_name']); ?>" /><em><?php esc_html_e('Shown in the widget header.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('Assistant subtitle', 'geeky-bot'); ?></span><input id="assistant_subtitle" name="assistant_subtitle" type="text" value="<?php echo esc_attr($settings['assistant_subtitle']); ?>" /><em><?php esc_html_e('Shown below the assistant name.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field gb-settings-field--wide"><span><?php esc_html_e('Welcome message', 'geeky-bot'); ?></span><textarea id="welcome_message" name="welcome_message" rows="3"><?php echo esc_textarea($settings['welcome_message']); ?></textarea><em><?php esc_html_e('Use shopper-friendly language that invites natural product questions.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field gb-settings-field--wide"><span><?php esc_html_e('Safe fallback answer', 'geeky-bot'); ?></span><textarea id="fallback_human_message" name="fallback_human_message" rows="3"><?php echo esc_textarea($settings['fallback_human_message']); ?></textarea><em><?php esc_html_e('Shown when catalog data or selected policy pages cannot answer the shopper safely.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field gb-settings-field--color"><span><?php esc_html_e('Accent color', 'geeky-bot'); ?></span><input id="accent_color" name="accent_color" type="color" value="<?php echo esc_attr($settings['accent_color']); ?>" /><em><?php esc_html_e('Used for widget buttons and header accents.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('Button position', 'geeky-bot'); ?></span><select name="button_position"><option value="right" <?php selected($settings['button_position'], 'right'); ?>><?php esc_html_e('Right', 'geeky-bot'); ?></option><option value="left" <?php selected($settings['button_position'], 'left'); ?>><?php esc_html_e('Left', 'geeky-bot'); ?></option></select><em><?php esc_html_e('Floating widget placement on the storefront.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('Products per answer', 'geeky-bot'); ?></span><input id="max_products" name="max_products" type="number" min="1" max="8" value="<?php echo esc_attr(absint($settings['max_products'])); ?>" /><em><?php esc_html_e('Recommended: 3–4 products for clean assistant replies.', 'geeky-bot'); ?></em></label>
        </div>
    <?php }

    private function settings_table_ai($settings) { ?>
        <div class="gb-provider-mode-grid" role="radiogroup" aria-label="<?php esc_attr_e('Answer mode', 'geeky-bot'); ?>">
            <label class="gb-provider-option <?php echo esc_attr($settings['provider_mode'] === 'local' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="local" <?php checked($settings['provider_mode'], 'local'); ?> /><span><strong><?php esc_html_e('Local grounded mode', 'geeky-bot'); ?></strong><em><?php esc_html_e('No external AI needed. Uses catalog and selected policy pages.', 'geeky-bot'); ?></em></span></label>
            <label class="gb-provider-option <?php echo esc_attr($settings['provider_mode'] === 'zywrap' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="zywrap" <?php checked($settings['provider_mode'], 'zywrap'); ?> /><span><strong><?php esc_html_e('Zywrap endpoint', 'geeky-bot'); ?></strong><em><?php esc_html_e('Hosted AI endpoint for grounded answers.', 'geeky-bot'); ?></em></span></label>
            <label class="gb-provider-option <?php echo esc_attr($settings['provider_mode'] === 'openai' ? 'is-selected' : ''); ?>"><input type="radio" name="provider_mode" value="openai" <?php checked($settings['provider_mode'], 'openai'); ?> /><span><strong><?php esc_html_e('OpenAI BYOK', 'geeky-bot'); ?></strong><em><?php esc_html_e('Optional bring-your-own-key grounded answer mode.', 'geeky-bot'); ?></em></span></label>
        </div>
        <div class="gb-settings-field-grid gb-settings-field-grid--provider">
            <label class="gb-settings-field gb-settings-field--wide"><span><?php esc_html_e('Zywrap endpoint', 'geeky-bot'); ?></span><input id="zywrap_endpoint" name="zywrap_endpoint" type="url" value="<?php echo esc_attr($settings['zywrap_endpoint']); ?>" placeholder="https://api.example.com/..." /><em><?php esc_html_e('Only used when Zywrap mode is selected.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('Zywrap API key', 'geeky-bot'); ?></span><input id="zywrap_api_key" name="zywrap_api_key" type="password" value="<?php echo esc_attr($settings['zywrap_api_key'] ? '••••••••' : ''); ?>" autocomplete="new-password" /><em><?php esc_html_e('Saved secret is not displayed after save.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('OpenAI API key', 'geeky-bot'); ?></span><input id="openai_api_key" name="openai_api_key" type="password" value="<?php echo esc_attr($settings['openai_api_key'] ? '••••••••' : ''); ?>" autocomplete="new-password" /><em><?php esc_html_e('Saved secret is not displayed after save.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('OpenAI model', 'geeky-bot'); ?></span><input id="openai_model" name="openai_model" type="text" value="<?php echo esc_attr($settings['openai_model']); ?>" /><em><?php esc_html_e('Used only in OpenAI BYOK mode.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('AI answer token limit', 'geeky-bot'); ?></span><input id="ai_max_tokens" name="ai_max_tokens" type="number" min="120" max="1200" value="<?php echo esc_attr(absint($settings['ai_max_tokens'])); ?>" /><em><?php esc_html_e('Keeps generated answers short and controlled.', 'geeky-bot'); ?></em></label>
        </div>
        <div class="gb-settings-safe-note"><strong><?php esc_html_e('Security posture', 'geeky-bot'); ?></strong><span><?php esc_html_e('API keys remain server-side. The storefront receives public widget settings and REST nonce only.', 'geeky-bot'); ?></span></div>
    <?php }

    private function settings_table_privacy($settings) { ?>
        <div class="gb-settings-field-grid">
            <label class="gb-toggle-row gb-settings-toggle-card"><input type="hidden" name="chat_history_enabled" value="no" /><input type="checkbox" name="chat_history_enabled" value="yes" <?php checked($settings['chat_history_enabled'], 'yes'); ?> /> <span><strong><?php esc_html_e('Save conversation history', 'geeky-bot'); ?></strong><em><?php esc_html_e('Store shopper conversations for analytics and unanswered-question review.', 'geeky-bot'); ?></em></span></label>
            <label class="gb-toggle-row gb-settings-toggle-card"><input type="hidden" name="allow_guest_sessions" value="no" /><input type="checkbox" name="allow_guest_sessions" value="yes" <?php checked($settings['allow_guest_sessions'], 'yes'); ?> /> <span><strong><?php esc_html_e('Save guest conversations', 'geeky-bot'); ?></strong><em><?php esc_html_e('When disabled, logged-out shoppers can still use Geeky Bot, but their server-side conversation history and click events are not stored.', 'geeky-bot'); ?></em></span></label>
            <label class="gb-settings-field"><span><?php esc_html_e('Retention days', 'geeky-bot'); ?></span><input id="retention_days" name="retention_days" type="number" min="1" max="365" value="<?php echo esc_attr(absint($settings['retention_days'])); ?>" /><em><?php esc_html_e('How long conversation data is retained.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('Rate limit shopper messages', 'geeky-bot'); ?></span><input id="rate_limit_messages" name="rate_limit_messages" type="number" min="20" max="1000" value="<?php echo esc_attr(absint($settings['rate_limit_messages'])); ?>" /><em><?php esc_html_e('Maximum public shopper messages per window.', 'geeky-bot'); ?></em></label>
            <label class="gb-settings-field"><span><?php esc_html_e('Rate limit window minutes', 'geeky-bot'); ?></span><input id="rate_limit_window_minutes" name="rate_limit_window_minutes" type="number" min="1" max="60" value="<?php echo esc_attr(absint($settings['rate_limit_window_minutes'])); ?>" /><em><?php esc_html_e('Administrators, shop managers, and local development environments are not subject to public visitor rate limits.', 'geeky-bot'); ?></em></label>
            <label class="gb-toggle-row gb-settings-toggle-card gb-settings-danger-toggle"><input type="hidden" name="delete_data_on_uninstall" value="no" /><input type="checkbox" name="delete_data_on_uninstall" value="yes" <?php checked(isset($settings['delete_data_on_uninstall']) ? $settings['delete_data_on_uninstall'] : get_option('geekybot_delete_data_on_uninstall', 'no'), 'yes'); ?> /> <span><strong><?php esc_html_e('Delete data on uninstall', 'geeky-bot'); ?></strong><em><?php esc_html_e('Remove conversations, review queue, product index, and Commerce Pro data when the plugin is uninstalled. Keep disabled on live stores unless intentional.', 'geeky-bot'); ?></em></span></label>
        </div>
        <div class="gb-settings-safe-note"><strong><?php esc_html_e('Conversation data tools', 'geeky-bot'); ?></strong><span><?php esc_html_e('Export individual or complete conversation records, inspect product-click signals, or delete stored conversation data from Geeky Bot → Conversations.', 'geeky-bot'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=geekybot-conversations')); ?>"><?php esc_html_e('Open conversation data tools', 'geeky-bot'); ?></a></span></div>
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
    private function metric_card($label, $value, $state = 'neutral') { ?><div class="gb-card gb-card--metric gb-card--<?php echo esc_attr($state); ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></div><?php }

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

    private function status_item($label, $ready, $description) { ?><li class="gb-status-item <?php echo esc_attr($ready ? 'is-ready' : 'is-warning'); ?>"><div><strong><?php echo esc_html($label); ?></strong><span><?php echo esc_html($description); ?></span></div><em><?php echo esc_html($ready ? __('Ready', 'geeky-bot') : __('Needs attention', 'geeky-bot')); ?></em></li><?php }
    private function timeline_step($num, $label, $ready, $description, $url) { ?><a class="gb-timeline-step <?php echo esc_attr($ready ? 'is-ready' : 'is-warning'); ?>" href="<?php echo esc_url($url); ?>"><b><?php echo esc_html($num); ?></b><span><strong><?php echo esc_html($label); ?></strong><em><?php echo esc_html($description); ?></em></span></a><?php }
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
        $classes = 'is-' . str_replace('_', '-', $status);
        if ($status === 'needs_attention') {
            $classes .= ' is-warning';
        }
        ?><div class="gb-setup-task <?php echo esc_attr($classes); ?>"><?php if ($number !== '') : ?><span class="gb-setup-task__number"><?php echo esc_html($number); ?></span><?php endif; ?><div class="gb-setup-task__content"><span class="gb-status-pill <?php echo esc_attr($classes); ?>"><?php echo esc_html($labels[$status]); ?></span><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($description); ?></p></div><?php if ($url !== '') : ?><a class="button <?php echo esc_attr($status === 'blocked' ? 'button-primary' : ''); ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($action); ?></a><?php else : ?><span class="gb-setup-dependency" aria-disabled="true"><?php echo esc_html($action); ?></span><?php endif; ?></div><?php
    }
    private function module_card($title, $desc, $ready, $url, $action) { ?><div class="gb-module-card <?php echo esc_attr($ready ? 'is-ready' : 'is-warning'); ?>"><span class="gb-module-icon"><?php echo esc_html($ready ? '✓' : '!'); ?></span><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($desc); ?></p><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($action); ?></a></div><?php }
    private function feature_pill($label) { ?><span class="gb-feature-pill"><?php echo esc_html($label); ?></span><?php }
    private function qa_item($prompt, $label) { ?><div class="gb-qa-item"><code><?php echo esc_html($prompt); ?></code><span><?php echo esc_html($label); ?></span></div><?php }
    private function qa_link($prompt, $label) { ?><a class="gb-qa-item gb-qa-item--link" href="<?php echo esc_url(add_query_arg(array('page' => 'geekybot-product-assistant', 'gb_test_query' => rawurlencode($prompt)), admin_url('admin.php'))); ?>"><code><?php echo esc_html($prompt); ?></code><span><?php echo esc_html($label); ?></span><em><?php esc_html_e('Run test', 'geeky-bot'); ?></em></a><?php }
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
        <div class="gb-search-intent-debug">
            <strong><?php esc_html_e('Parsed buyer intent', 'geeky-bot'); ?></strong>
            <div class="gb-search-intent-debug__grid">
                <span><b><?php esc_html_e('Intent', 'geeky-bot'); ?></b><?php echo esc_html(!empty($analysis['intent']) ? $analysis['intent'] : 'search'); ?></span>
                <?php foreach ($groups as $label => $values) : $values = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $values)))); ?>
                    <?php if (!empty($values)) : ?>
                        <span><b><?php echo esc_html($label); ?></b><?php echo esc_html(implode(', ', array_slice($values, 0, 6))); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php $synonym_expansions = $this->search_synonym_expansion_debug($analysis); ?>
            <?php if (!empty($synonym_expansions)) : ?>
                <div class="gb-search-synonym-expansion">
                    <b><?php esc_html_e('Synonym expansion', 'geeky-bot'); ?></b>
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
        <div class="gb-admin-match-debug">
            <strong><?php esc_html_e('Why this product matched', 'geeky-bot'); ?></strong>
            <div class="gb-admin-match-debug__chips">
                <?php if ($score !== null) : ?><span class="gb-match-strength" title="<?php echo esc_attr(sprintf(
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
        <div class="gb-analytics-bucket-card <?php echo esc_attr($count > 0 ? 'has-gaps' : 'is-clear'); ?>">
            <strong><?php echo esc_html(number_format_i18n(absint($count))); ?></strong>
            <span><?php echo esc_html($title); ?></span>
            <em><?php echo esc_html($description); ?></em>
        </div>
    <?php }

    private function integration_card($title, $desc, $enabled) { ?><div class="gb-integration-card <?php echo esc_attr($enabled ? 'is-ready' : ''); ?>"><span class="gb-status-pill <?php echo esc_attr($enabled ? 'is-ready' : ''); ?>"><?php echo esc_html($enabled ? __('Active', 'geeky-bot') : __('Available', 'geeky-bot')); ?></span><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($desc); ?></p></div><?php }
    private function admin_link($page, $label) { ?><a href="<?php echo esc_url(admin_url('admin.php?page=' . $page)); ?>"><?php echo esc_html($label); ?></a><?php }
    private function branding_image_picker($name, $value, $label, $settings_field = false) {
        $value = absint($value);
        $url = $value ? wp_get_attachment_image_url($value, 'thumbnail') : '';
        $class = $settings_field ? 'gb-settings-field gb-media-field' : 'gb-media-field';
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

    private function widget_preview($settings) {
        $public = Settings::public_settings();
        $header_logo = !empty($public['headerLogoUrl']) ? $public['headerLogoUrl'] : '';
        $launcher_icon = !empty($public['launcherIconUrl']) ? $public['launcherIconUrl'] : '';
        $launcher_style = isset($settings['launcher_style']) && $settings['launcher_style'] === 'pill' ? 'pill' : 'icon';
        ?>
        <div class="gb-widget-preview-stack" style="--gb-preview-accent: <?php echo esc_attr($settings['accent_color']); ?>">
            <div class="gb-launcher-preview gb-launcher-preview--<?php echo esc_attr($launcher_style); ?>" data-gb-preview-launcher>
                <span class="gb-launcher-preview__mark" data-gb-preview-launcher-mark>
                    <?php if ($launcher_icon) : ?><img src="<?php echo esc_url($launcher_icon); ?>" alt="" /><?php else : ?><?php $this->brand_mark_svg(); ?><?php endif; ?>
                </span>
                <span class="gb-launcher-preview__text" data-gb-preview-launcher-text><?php echo esc_html($settings['launcher_text']); ?></span>
            </div>
            <div class="gb-widget-preview gb-widget-preview--<?php echo esc_attr($settings['header_style']); ?>">
                <div class="gb-widget-preview__header">
                    <span class="gb-widget-preview__logo" data-gb-preview-header-logo>
                        <?php if ($header_logo) : ?><img src="<?php echo esc_url($header_logo); ?>" alt="" /><?php else : ?><?php $this->brand_mark_svg(); ?><?php endif; ?>
                    </span>
                    <div><strong><?php echo esc_html($settings['assistant_name']); ?></strong><span><?php echo esc_html($settings['assistant_subtitle']); ?></span></div>
                </div>
                <div class="gb-widget-preview__body"><p><?php echo esc_html($settings['welcome_message']); ?></p><div class="gb-chip-row"><code>Latest products</code><code>Sale products</code><code>Top rated</code></div><div class="gb-product-mini"><span></span><div><strong>Blue hoodie</strong><em>$45.00 · In stock</em></div></div></div>
            </div>
        </div>
        <?php
    }

    private function menu_icon_data_uri() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><g transform="translate(0,8)"><path d="M20 15 C21 22 23 24 30 25 C23 26 21 28 20 35 C19 28 17 26 10 25 C17 24 19 22 20 15 Z" fill="#fff"/><path d="M55 20 H73 Q88 20 88 35 V45 Q88 60 73 60 L70 60 L73 72 L62 60 H55 Q40 60 40 45 V35 Q40 20 55 20 Z" fill="none" stroke="#fff" stroke-width="5.5" stroke-linejoin="round"/><line x1="66" y1="20" x2="66" y2="13" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/><circle cx="66" cy="10" r="3" fill="#fff"/><rect x="51" y="31" width="26" height="16" rx="7" fill="#fff"/><ellipse cx="59" cy="39" rx="2.3" ry="3" fill="#6d28d9"/><ellipse cx="69" cy="39" rx="2.3" ry="3" fill="#6d28d9"/><circle cx="58" cy="53" r="1.7" fill="#fff"/><circle cx="64" cy="53" r="1.7" fill="#fff"/><circle cx="70" cy="53" r="1.7" fill="#fff"/><path d="M20 46 L48 46 L44 60 L27 60 Z" fill="none" stroke="#fff" stroke-width="4.5" stroke-linejoin="round"/><path d="M20 46 L15 40 L11 40" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="29" y1="60" x2="29" y2="63" stroke="#fff" stroke-width="4"/><line x1="42" y1="60" x2="42" y2="63" stroke="#fff" stroke-width="4"/><circle cx="29" cy="66" r="3" fill="#fff"/><circle cx="42" cy="66" r="3" fill="#fff"/></g></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function brand_mark_svg() {
        static $n = 0;
        $gid = 'gbSiteMark' . ( ++$n );
        $g   = 'url(#' . $gid . ')';
        ?>
        <svg class="gb-brand-mark-svg" viewBox="0 0 100 100" role="img" focusable="false" aria-hidden="true">
            <defs><linearGradient id="<?php echo esc_attr($gid); ?>" x1="8" y1="16" x2="92" y2="88" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#2f6bed" /><stop offset="1" stop-color="#7c3aed" /></linearGradient></defs>
            <g transform="translate(0,8)">
                <path d="M20 15 C21 22 23 24 30 25 C23 26 21 28 20 35 C19 28 17 26 10 25 C17 24 19 22 20 15 Z" fill="<?php echo esc_attr($g); ?>" />
                <path d="M55 20 H73 Q88 20 88 35 V45 Q88 60 73 60 L70 60 L73 72 L62 60 H55 Q40 60 40 45 V35 Q40 20 55 20 Z" fill="none" stroke="<?php echo esc_attr($g); ?>" stroke-width="5.5" stroke-linejoin="round" />
                <line x1="66" y1="20" x2="66" y2="13" stroke="<?php echo esc_attr($g); ?>" stroke-width="3.5" stroke-linecap="round" /><circle cx="66" cy="10" r="3" fill="<?php echo esc_attr($g); ?>" />
                <rect x="51" y="31" width="26" height="16" rx="7" fill="<?php echo esc_attr($g); ?>" />
                <ellipse cx="59" cy="39" rx="2.3" ry="3" fill="#fff" /><ellipse cx="69" cy="39" rx="2.3" ry="3" fill="#fff" />
                <circle cx="58" cy="53" r="1.7" fill="<?php echo esc_attr($g); ?>" /><circle cx="64" cy="53" r="1.7" fill="<?php echo esc_attr($g); ?>" /><circle cx="70" cy="53" r="1.7" fill="<?php echo esc_attr($g); ?>" />
                <path d="M20 46 L48 46 L44 60 L27 60 Z" fill="none" stroke="<?php echo esc_attr($g); ?>" stroke-width="4.5" stroke-linejoin="round" />
                <path d="M20 46 L15 40 L11 40" fill="none" stroke="<?php echo esc_attr($g); ?>" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" />
                <line x1="29" y1="60" x2="29" y2="63" stroke="<?php echo esc_attr($g); ?>" stroke-width="4" /><line x1="42" y1="60" x2="42" y2="63" stroke="<?php echo esc_attr($g); ?>" stroke-width="4" />
                <circle cx="29" cy="66" r="3" fill="<?php echo esc_attr($g); ?>" /><circle cx="42" cy="66" r="3" fill="<?php echo esc_attr($g); ?>" />
            </g>
        </svg>
        <?php
    }

    private function suggested_policy_pages($pages) { $matches = array(); foreach ((array) $pages as $page) { $title = strtolower((string) $page->post_title); if (preg_match('/shipping|return|refund|privacy|terms|warranty|policy|delivery|payment/', $title)) { $matches[] = $page; } } return $matches; }
    private function page_list($pages, $selected, $only_selected, $classifications = array()) {
        $shown = 0;
        echo '<ul class="gb-page-list">';
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
        <div class="gb-review-bucket-card gb-review-bucket-card--<?php echo esc_attr($bucket); ?>">
            <span><?php echo esc_html(number_format_i18n(absint($count))); ?></span>
            <strong><?php echo esc_html($title); ?></strong>
            <em><?php echo esc_html($description); ?></em>
        </div>
    <?php }

    private function brand_lockup($tagline = '') {
        ?>
        <span class="gb-brand-lockup" aria-label="<?php esc_attr_e('GeekyBot', 'geeky-bot'); ?>">
            <span class="gb-brand-mark" aria-hidden="true">
                <?php $this->brand_mark_svg(); ?>
            </span>
            <span class="gb-brand-copy">
                <span class="gb-brand-word">Geeky<span>Bot</span></span>
                <?php if ($tagline) : ?><span class="gb-brand-tagline"><?php echo esc_html($tagline); ?></span><?php endif; ?>
            </span>
        </span>
        <?php
    }

    private function table_exists($table) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live schema check for a fixed plugin table.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $exists === $table;
    }
    private function provider_label($settings) { if ($settings['provider_mode'] === 'zywrap') { return Settings::has_secret('zywrap_api_key') && !empty($settings['zywrap_endpoint']) ? __('Zywrap enabled', 'geeky-bot') : __('Zywrap selected but not fully configured', 'geeky-bot'); } if ($settings['provider_mode'] === 'openai') { return Settings::has_secret('openai_api_key') ? __('OpenAI enabled', 'geeky-bot') : __('OpenAI selected but API key missing', 'geeky-bot'); } return __('Local grounded mode', 'geeky-bot'); }
}
