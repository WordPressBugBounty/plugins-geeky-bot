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

// Every option and transient this plugin owns is prefixed `geekybot_`, so
// sweeping the prefix cannot go stale the way the explicit list here did. That
// list named 22 options and still left behind geekybot_family_vocabulary,
// geekybot_fulltext_state, geekybot_product_index_rebuild_state,
// geekybot_search_cache_version and geekybot_search_vocabulary -- plus every
// _transient_geekybot_rate_* row, which is one per visitor on a busy store.
// A merchant who ticks "delete my data" is entitled to have it all gone.
$geekybot_patterns = array(
    $wpdb->esc_like('geekybot_') . '%',
    $wpdb->esc_like('_transient_geekybot_') . '%',
    $wpdb->esc_like('_transient_timeout_geekybot_') . '%',
    $wpdb->esc_like('_site_transient_geekybot_') . '%',
    $wpdb->esc_like('_site_transient_timeout_geekybot_') . '%',
);

foreach ($geekybot_patterns as $geekybot_pattern) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Removes the options this plugin owns, matched on its own prefix.
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $geekybot_pattern));
}

// Network installs keep site options in sitemeta rather than in wp_options.
if (is_multisite()) {
    foreach ($geekybot_patterns as $geekybot_pattern) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Same prefix, network option store.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $geekybot_pattern));
    }
}

// The dated geekybot_ai_calls_* counters are covered by the prefix sweep above.

