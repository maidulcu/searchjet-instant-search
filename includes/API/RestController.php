<?php
/**
 * SearchJet – REST API bootstrapper
 */

namespace SearchJet\API;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class RestController
{
    /**
     * Primary REST namespace for this plugin.
     */
    public const NS = 'searchjet/v1';

    /**
     * Boot all REST sub-controllers and provide a simple health endpoint.
     */
    public static function init(): void
    {
        // Ensure sub-controllers are registered.
        add_action('rest_api_init', [self::class, 'bootstrap_controllers'], 5);

        // Health/ping route for quick checks
        add_action('rest_api_init', function () {
            register_rest_route(self::NS, '/ping', [
                [
                    'methods'             => 'GET',
                    'callback'            => [self::class, 'ping'],
                    'permission_callback' => '__return_true',
                ],
            ]);
        }, 20);
    }

    /**
     * Initialize child controllers.
     */
    public static function bootstrap_controllers(): void
    {
        // AI Agents REST
        if (class_exists(\SearchJet\API\AIController::class) && method_exists(\SearchJet\API\AIController::class, 'init')) {
            \SearchJet\API\AIController::init();
        }

        // Automations REST
        if (class_exists(\SearchJet\API\AutomationController::class) && method_exists(\SearchJet\API\AutomationController::class, 'init')) {
            \SearchJet\API\AutomationController::init();
        }

        // Events REST (if present in your codebase)
        if (class_exists(\SearchJet\Rest\EventsController::class) && method_exists(\SearchJet\Rest\EventsController::class, 'init')) {
            \SearchJet\Rest\EventsController::init();
        }
    }

    /**
     * Simple health check endpoint.
     */
    public static function ping(WP_REST_Request $req): WP_REST_Response
    {
        $plugin_version = defined('SEARCHJET_PLUGIN_VERSION') ? SEARCHJET_PLUGIN_VERSION : 'unknown';
        return new WP_REST_Response([
            'ok'      => true,
            'time'    => time(),
            'version' => $plugin_version,
            'routes'  => [
                '/ping',
                '/automations',
                '/automations/definitions',
                '/automations/settings',
                '/automations/run-daily',
                '/automations/test-dispatch',
                '/ai-agents',
            ],
        ], 200);
    }
}

// Auto-boot
RestController::init();