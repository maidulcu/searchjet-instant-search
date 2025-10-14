<?php

namespace SearchJet\API;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class AIController {

    public static function init() {
        add_action('rest_api_init', function () {
            register_rest_route('searchjet/v1', '/ai-agents', [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'get_agents'],
                    'permission_callback' => [self::class, 'can_manage'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [self::class, 'save_agents'],
                    'permission_callback' => [self::class, 'can_manage'],
                ],
            ]);
        });
    }

    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    public static function get_agents(WP_REST_Request $request) {
        $agents = get_option('searchjet_ai_agents', []);
        return rest_ensure_response($agents);
    }

    public static function save_agents(WP_REST_Request $request) {
        $agents = $request->get_param('agents');
        if (!is_array($agents)) {
            return new WP_Error(
                'searchjet_invalid_agents',
                __('Invalid agents data.', 'searchjet-instant-search'),
                ['status' => 400]
            );
        }

        update_option('searchjet_ai_agents', $agents, false);

        return rest_ensure_response(['success' => true]);
    }
}
