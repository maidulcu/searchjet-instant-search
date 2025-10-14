<?php
/**
 * SearchJet – REST: AI Controller
 *
 * Exposes lightweight AI utilities over the WordPress REST API for use by the
 * admin Agents UI. Restricted to users with `manage_options`.
 */

namespace SearchJet\API;

use SearchJet\AI\Router;

if (!defined('ABSPATH')) {
    exit;
}

class AIController
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(
            'searchjet/v1',
            '/ai/actions',
            [
                'methods'  => 'GET',
                'callback' => [self::class, 'list_actions'],
                'permission_callback' => [self::class, 'can_manage'],
            ]
        );

        register_rest_route(
            'searchjet/v1',
            '/ai/dispatch',
            [
                'methods'  => 'POST',
                'callback' => [self::class, 'dispatch'],
                'permission_callback' => [self::class, 'can_manage'],
                'args' => [
                    'action'  => [ 'required' => true,  'type' => 'string' ],
                    'payload' => [ 'required' => false, 'type' => 'object' ],
                ],
            ]
        );
    }

    public static function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public static function list_actions(\WP_REST_Request $req)
    {
        $actions = Router::actions();
        // Only expose meta (id, label), not callbacks
        $out = [];
        foreach ($actions as $a) {
            $out[] = [ 'id' => $a['id'], 'label' => $a['label'] ];
        }
        return new \WP_REST_Response([
            'ok' => true,
            'actions' => $out,
        ], 200);
    }

    public static function dispatch(\WP_REST_Request $req)
    {
        $action  = (string) $req->get_param('action');
        $payload = (array) ($req->get_param('payload') ?: []);

        $res = Router::dispatch($action, $payload);
        $code = $res['ok'] ? 200 : 400;

        return new \WP_REST_Response($res, $code);
    }
}