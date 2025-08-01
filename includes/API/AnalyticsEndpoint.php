<?php
namespace SearchJet\API;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class AnalyticsEndpoint {
    public static function init() {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes() {
        register_rest_route('searchjet/v1', '/log', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'handleLog'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handleLog(WP_REST_Request $request) {
    $body = json_decode(file_get_contents('php://input'), true);

    // Handle modern beacon-style batch logs
    if (is_array($body) && isset($body[0]['query'])) {
        foreach ($body as $entry) {
            $query = sanitize_text_field($entry['query'] ?? '');
            $results = intval($entry['results'] ?? 0);
            $ts = intval($entry['ts'] ?? time());
        }

        // Store logs in WP option for scheduled dispatch
        $backlog = get_option('searchjet_log_backlog', []);
        $backlog = is_array($backlog) ? $backlog : [];
        $backlog = array_merge($backlog, $body);
        update_option('searchjet_log_backlog', $backlog);

        return new WP_REST_Response(['success' => true, 'count' => count($body)], 200);
    }

    return new WP_REST_Response(['success' => true], 200);
}
}