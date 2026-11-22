<?php
namespace SearchJet\API;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use SearchJet\AI\AIAnswerGenerator;

/**
 * REST endpoint for AI usage statistics
 *
 * Note: AI answers are obtained through the main search API (/api/v1/search?ai_answer=true)
 * This endpoint only provides usage statistics for the WordPress admin.
 */
class AIAnswerEndpoint {

    public static function init() {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes() {
        // Get AI usage stats only (admin only)
        register_rest_route('searchjet/v1', '/ai/usage', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [self::class, 'getUsage'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    public static function getUsage(WP_REST_Request $request) {
        $stats = AIAnswerGenerator::getUsageStats();

        return new WP_REST_Response([
            'success' => true,
            'usage' => $stats
        ], 200);
    }
}
