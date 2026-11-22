<?php
/**
 * SearchJet uninstall handler.
 *
 * Runs only when the plugin is deleted (not deactivated) from the WordPress
 * admin. By default plugin data is preserved. Data is only removed when the
 * "Delete Data on Uninstall" option is enabled under Advanced Settings.
 *
 * @package SearchJet
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Preserve data unless the admin explicitly opted in to deletion.
$delete_data = (bool) get_option('searchjet_delete_data_on_uninstall', 0);
if (!$delete_data) {
    return;
}

global $wpdb;

// 1. Remove all plugin options (searchjet_* prefix).
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('searchjet_') . '%'
    )
);

// 2. Remove task tracking options (searchjet_task_* prefix).
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('searchjet_task_') . '%'
    )
);

// 3. Remove any left-over plugin options by name.
$option_names = [
    'searchjet_api_key',
    'searchjet_project_id',
    'searchjet_hosted_api_key',
    'searchjet_search_mode',
    'searchjet_disable_search_mode',
    'searchjet_delete_data_on_uninstall',
    'searchjet_automations_enabled',
    'searchjet_zr_threshold',
    'searchjet_zr_window',
    'searchjet_require_event_nonce',
    'searchjet_ui_options',
    'searchjet_exclude_post_types',
    'searchjet_include_status',
    'searchjet_exclude_sticky',
    'searchjet_exclude_protected',
    'searchjet_exclude_mime_types',
    'searchjet_attributes',
    'searchjet_client_info',
    'searchjet_site_id',
    'searchjet_log_backlog',
    'searchjet_click_backlog',
    'searchjet_automation_webhook_url',
    'searchjet_automation_webhook_secret',
    'searchjet_automation_filters',
    'searchjet_webhook_provider',
    'searchjet_webhook_url',
    'searchjet_webhook_secret',
    'searchjet_webhook_url_n8n',
    'searchjet_webhook_url_zapier',
    'searchjet_zapier_webhook_url',
    'searchjet_zapier_secret',
    'searchjet_agents_enabled',
    'searchjet_agents_enabled_ids',
    'searchjet_agents_notes',
    'searchjet_ai_agents',
    'searchjet_ai_answers_enabled',
    'searchjet_search_attributes',
    'searchjet_index_attachments',
    'searchjet_indexable_post_types',
    'searchjet_indexable_post_statuses',
    'searchjet_last_reindex',
    'searchjet_last_reindex_time',
    'searchjet_document_count',
    'searchjet_max_results',
    'searchjet_primary_color',
    'searchjet_theme',
    'searchjet_show_thumbnails',
    'searchjet_show_prices',
    'searchjet_show_rating',
    'searchjet_show_stock',
    'searchjet_show_categories',
    'searchjet_show_excerpts',
    'searchjet_dashboard_widget',
    'searchjet_dashboard_widget_stats',
    'searchjet_welcome_seen',
    'searchjet_help_seen',
    'searchjet_host_url',
];
foreach ($option_names as $option) {
    delete_option($option);
}

// 4. Remove transient data (searchjet_* prefix).
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_searchjet_') . '%',
        $wpdb->esc_like('_transient_timeout_searchjet_') . '%'
    )
);

// 5. Clear scheduled cron events.
$cron_hooks = [
    'searchjet_send_log_batch',
    'searchjet_send_click_batch',
    'searchjet_check_task_status',
    'searchjet_reindex_daily',
];
foreach ($cron_hooks as $hook) {
    $timestamp = wp_next_scheduled($hook);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
    }
    wp_clear_scheduled_hook($hook);
}
