<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('geekybot_commerce_pro_license_check');
wp_clear_scheduled_hook('geekybot_commerce_pro_version_check');
wp_clear_scheduled_hook('geekybot_product_index_scheduled_rebuild');

$geekybot_delete_data = get_option('geekybot_delete_data_on_uninstall', 'no');
if ($geekybot_delete_data !== 'yes') {
    return;
}

global $wpdb;
$geekybot_tables = array(
    $wpdb->prefix . 'geekybot_sessions',
    $wpdb->prefix . 'geekybot_messages',
    $wpdb->prefix . 'geekybot_unanswered',
    $wpdb->prefix . 'geekybot_events',
    $wpdb->prefix . 'geekybot_product_index',
    $wpdb->prefix . 'geekybot_knowledge_index',
);

foreach ($geekybot_tables as $geekybot_table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall must remove the fixed allowlist of plugin-owned tables.
    $wpdb->query("DROP TABLE IF EXISTS {$geekybot_table}");
}

delete_option('geekybot_v2_settings');
delete_option('geekybot_v2_version');
delete_option('geekybot_db_version');
delete_option('geekybot_db_migration_lock');
delete_option('geekybot_last_cleanup');
delete_option('geekybot_v2_rate_limit_dev19_upgraded');
delete_option('geekybot_commerce_pro_license');
delete_option('geekybot_installation_id_v2');
delete_option('geekybot_commerce_pro_update_cache');
delete_option('geekybot_commerce_pro_cdn_update_cache');
delete_option('geekybot_commerce_pro_update_settings');
delete_option('geekybot_product_index_needs_rebuild');
delete_option('geekybot_product_index_last_rebuild');
delete_option('geekybot_product_index_auto_index_version');
delete_option('geekybot_knowledge_sync_pending');
delete_option('geekybot_knowledge_last_sync_summary');
delete_option('geekybot_review_manual_handled');
delete_option('geekybot_review_ignored');
delete_option('geekybot_onboarding_state');
delete_option('geekybot_onboarding_redirect_pending');
delete_option('geekybot_guided_demo_seed');
delete_option('geekybot_delete_data_on_uninstall');
