<?php
namespace SearchJet;

use SearchJet\Admin\SettingsPage;
use SearchJet\API\IndexSync;
use SearchJet\API\AnalyticsEndpoint;

class Plugin {
    public static function init() {
        add_action('init', [SettingsPage::class, 'init']);
        add_action('init', [IndexSync::class, 'init']);
        if (!get_option('searchjet_disable_search_mode')) {
            add_action('template_redirect', [\SearchJet\Frontend\SearchOverride::class, 'init']);
        }
        add_action('wp_enqueue_scripts', [self::class, 'enqueueFrontendAssets']);
        add_action('rest_api_init', [AnalyticsEndpoint::class, 'registerRoutes']);
        // Schedule daily log batch event if not already scheduled
        if (!wp_next_scheduled('searchjet_send_log_batch')) {
            wp_schedule_event(time(), 'daily', 'searchjet_send_log_batch');
        }

        add_action('searchjet_send_log_batch', [self::class, 'sendLogBatch']);
    }

    public static function activate() {
        if (!get_option('searchjet_api_key')) {
            update_option('searchjet_api_key', '');
        }
        if (!get_option('searchjet_project_id')) {
            update_option('searchjet_project_id', '');
        }
    }

    public static function sendLogBatch() {
        $logs = get_option('searchjet_log_backlog', []);
        if (empty($logs)) return;

        $clientInfo = get_option('searchjet_client_info', []);
        $response = wp_remote_post(SEARCHJET_API_BASE_URL . '/api/v1/search-analytics', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . (get_option('searchjet_hosted_api_key', '')),
            ],
            'body' => wp_json_encode([
                'logs' => array_map(function ($log) {
                    return [
                        'query' => $log['query'] ?? '',
                        'results_count' => $log['results'] ?? 0,
                        'filters' => $log['filters'] ?? null,
                        'referer' => $log['referer'] ?? null,
                        'user_agent' => $log['user_agent'] ?? null,
                        'session_id' => $log['session_id'] ?? null,
                        'ip' => $log['ip'] ?? null,
                    ];
                }, $logs)
            ]),
            'timeout' => 30,
        ]);

         //error_log('[SearchJet] Anytic send log batch: ' . print_r($response, true));

        if (!is_wp_error($response)) {
            delete_option('searchjet_log_backlog');
        } else {
            error_log('[SearchJet] Failed to send log batch: ' . $response->get_error_message());
        }
    }

    public static function enqueueFrontendAssets() {
        wp_enqueue_script(
            'searchjet-ajax',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/searchjet.js',
            [],
            time(),
            true
        );

        wp_enqueue_style(
            'searchjet-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/searchjet.css',
            [],
            time()
        );

        $clientInfo = get_option('searchjet_client_info', []);
        wp_localize_script('searchjet-ajax', 'SearchJetSettings', [
            'logApiUrl' => rest_url('searchjet/v1/log'),
            'searchApiBaseUrl' => SEARCHJET_API_BASE_URL . '/api/v1/search',
            'key' => isset($clientInfo['public_key']) ? $clientInfo['public_key'] : '',
        ]);

        $mode = get_option('searchjet_search_mode', 'js') ?: 'js';

        if (is_search() && $mode === 'js') {
            wp_enqueue_script(
                'searchjet-page',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/searchjet-page.js',
                [],
                time(),
                true
            );
        }
    }

}
