<?php
/**
 * Plugin Name: Geeky Bot
 * Plugin URI: https://geekybot.com/
 * Description: AI Sales Assistant for WooCommerce. Helps shoppers find products, understand options, and move toward purchase from a lightweight storefront widget.
 * Version: 2.0.1
 * Author: Geeky Bot
 * Author URI: https://geekybot.com/
 * Text Domain: geeky-bot
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GEEKYBOT_VERSION', '2.0.1');
define('GEEKYBOT_DB_VERSION', '2.0.0');
define('GEEKYBOT_FILE', __FILE__);
define('GEEKYBOT_PATH', plugin_dir_path(__FILE__));
define('GEEKYBOT_URL', plugin_dir_url(__FILE__));
define('GEEKYBOT_BASENAME', plugin_basename(__FILE__));

require_once GEEKYBOT_PATH . 'includes/Plugin.php';
require_once GEEKYBOT_PATH . 'includes/Services/Settings.php';
require_once GEEKYBOT_PATH . 'includes/Services/ShopperOutputService.php';
require_once GEEKYBOT_PATH . 'includes/Services/Installer.php';
require_once GEEKYBOT_PATH . 'includes/Services/DatabaseMigrator.php';
require_once GEEKYBOT_PATH . 'includes/Services/OnboardingService.php';
require_once GEEKYBOT_PATH . 'includes/Services/GuidedDemoService.php';
require_once GEEKYBOT_PATH . 'includes/Services/LicenseVault.php';
require_once GEEKYBOT_PATH . 'includes/Services/LicenseService.php';
require_once GEEKYBOT_PATH . 'includes/Services/RateLimiter.php';
require_once GEEKYBOT_PATH . 'includes/Services/CatalogVisibilityService.php';
require_once GEEKYBOT_PATH . 'includes/Services/CatalogAvailabilityService.php';
require_once GEEKYBOT_PATH . 'includes/Services/NamedProductResolver.php';
require_once GEEKYBOT_PATH . 'includes/Services/ProductDiscoveryIntentService.php';
require_once GEEKYBOT_PATH . 'includes/Search/BuyerIntentLibrary.php';
require_once GEEKYBOT_PATH . 'includes/Search/ShoppingCommandResolver.php';
require_once GEEKYBOT_PATH . 'includes/Search/SearchContextService.php';
require_once GEEKYBOT_PATH . 'includes/Chat/PendingProductSelectionResolver.php';
require_once GEEKYBOT_PATH . 'includes/Chat/ConversationActRouter.php';
require_once GEEKYBOT_PATH . 'includes/ProductExpert/ProductQuestionRouter.php';
require_once GEEKYBOT_PATH . 'includes/ProductExpert/ProductReferenceResolver.php';
require_once GEEKYBOT_PATH . 'includes/ProductExpert/ProductClarificationResolver.php';
require_once GEEKYBOT_PATH . 'includes/ProductExpert/ProductFactsService.php';
require_once GEEKYBOT_PATH . 'includes/ProductExpert/ProductAnswerFormatter.php';
require_once GEEKYBOT_PATH . 'includes/ProductExpert/ProductAnswerService.php';
require_once GEEKYBOT_PATH . 'includes/ProductExpert/ProductExpertService.php';
require_once GEEKYBOT_PATH . 'includes/Services/SearchLanguageService.php';
require_once GEEKYBOT_PATH . 'includes/Services/ProductIndexService.php';
require_once GEEKYBOT_PATH . 'includes/Services/ProductService.php';
require_once GEEKYBOT_PATH . 'includes/Services/ProductDiscoveryRecoveryService.php';
require_once GEEKYBOT_PATH . 'includes/Services/ProductRecommendationService.php';
require_once GEEKYBOT_PATH . 'includes/Services/PolicyIntentService.php';
require_once GEEKYBOT_PATH . 'includes/Services/KnowledgeIndexService.php';
require_once GEEKYBOT_PATH . 'includes/Services/KnowledgeService.php';
require_once GEEKYBOT_PATH . 'includes/Services/AiService.php';
require_once GEEKYBOT_PATH . 'includes/Services/ChatService.php';
require_once GEEKYBOT_PATH . 'includes/Services/PrivacyService.php';
require_once GEEKYBOT_PATH . 'includes/Services/AnalyticsEventService.php';
require_once GEEKYBOT_PATH . 'includes/Services/ConversationInsightsService.php';
require_once GEEKYBOT_PATH . 'includes/Admin/Menu.php';
require_once GEEKYBOT_PATH . 'includes/Frontend/Widget.php';
require_once GEEKYBOT_PATH . 'includes/REST/Api.php';


register_activation_hook(__FILE__, array('GeekyBot\\Services\\Installer', 'activate'));
register_deactivation_hook(__FILE__, array('GeekyBot\\Services\\Installer', 'deactivate'));

add_action('plugins_loaded', function () {
    GeekyBot\Plugin::instance()->boot();
});
