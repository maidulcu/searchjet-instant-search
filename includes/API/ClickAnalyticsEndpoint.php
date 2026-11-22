<?php
namespace SearchJet\API;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use SearchJet\Helpers\NetworkHelper;

/**
 * REST endpoint for tracking click analytics
 * Tracks which search results users click to improve relevance
 */
class ClickAnalyticsEndpoint {

    private const MAX_BACKLOG_ENTRIES = 1000;

    public static function init() {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes() {
        register_rest_route('searchjet/v1', '/analytics/click', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'handleClick'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'document_id' => [
                    'type'     => 'string',
                    'required' => true,
                    'description' => 'ID of the clicked document',
                ],
                'position' => [
                    'type'     => 'integer',
                    'required' => true,
                    'description' => '1-based index in the search results',
                ],
                'query' => [
                    'type'     => 'string',
                    'required' => false,
                    'description' => 'Search query that produced this result',
                ],
                'search_log_id' => [
                    'type'     => 'string',
                    'required' => false,
                    'description' => 'Optional search log ID for correlation',
                ],
                'session_id' => [
                    'type'     => 'string',
                    'required' => false,
                    'description' => 'Optional session ID for tracking',
                ],
            ],
        ]);
    }

    public static function handleClick(WP_REST_Request $request) {
        // Rate limiting per IP
        $ip = NetworkHelper::clientIp();
        $rl_key = 'sj_click_rl_' . md5($ip);
        $rl = (int) get_transient($rl_key);

        if ($rl > 500) { // Max 500 clicks per 10 minutes per IP
            return new WP_REST_Response(['success' => false, 'error' => 'rate_limited'], 429);
        }
        set_transient($rl_key, $rl + 1, 10 * MINUTE_IN_SECONDS);

        // Sanitize input
        $click_data = self::sanitizeClickData($request);

        if (!$click_data) {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_data'], 400);
        }

        // Try to send directly to SearchJet API
        $sent = \SearchJet\API\SearchJetRemoteClient::recordClickAnalytics($click_data);

        if (!$sent) {
            // Fallback to local storage if API call fails
            $backlog = get_option('searchjet_click_backlog', []);
            $backlog = is_array($backlog) ? $backlog : [];

            $backlog[] = $click_data;

            if (count($backlog) > self::MAX_BACKLOG_ENTRIES) {
                $backlog = array_slice($backlog, -self::MAX_BACKLOG_ENTRIES);
            }

            update_option('searchjet_click_backlog', $backlog, false);
        }

        return new WP_REST_Response(['success' => true, 'tracked' => true], 200);
    }

    private static function sanitizeClickData(WP_REST_Request $request): ?array {
        $document_id = sanitize_text_field($request->get_param('document_id'));
        $position = (int) $request->get_param('position');
        $query = sanitize_text_field($request->get_param('query') ?? '');
        $search_log_id = sanitize_text_field($request->get_param('search_log_id') ?? '');
        $session_id = sanitize_text_field($request->get_param('session_id') ?? '');

        // Validate required fields
        if (empty($document_id) || $position < 1) {
            return null;
        }

        // Limit position to reasonable range
        if ($position > 1000) {
            $position = 1000;
        }

        return [
            'document_id' => substr($document_id, 0, 100),
            'position' => $position,
            'query' => $query !== '' ? substr($query, 0, 250) : null,
            'search_log_id' => $search_log_id !== '' ? substr($search_log_id, 0, 100) : null,
            'session_id' => $session_id !== '' ? substr($session_id, 0, 120) : null,
            'timestamp' => time(),
            'ip' => NetworkHelper::clientIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 250) : null,
            'referer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : null,
        ];
    }
}
