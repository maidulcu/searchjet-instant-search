<?php
/**
 * SearchJet – Automations REST Controller (MVP)
 */

namespace SearchJet\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use SearchJet\Automations\Registry;
use SearchJet\Automations\Runner;
use SearchJet\Automations\Events;
use SearchJet\Automations\Integrations\N8nClient;
use SearchJet\Automations\Integrations\ZapierClient;

if (!defined('ABSPATH')) {
    exit;
}

class AutomationController
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('searchjet/v1', '/automations', [
            [
                'methods'  => 'GET',
                'callback' => [self::class, 'get_status'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('searchjet/v1', '/automations/definitions', [
            [
                'methods'  => 'GET',
                'callback' => [self::class, 'get_definitions'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route('searchjet/v1', '/automations/settings', [
            [
                'methods'  => 'POST',
                'callback' => [self::class, 'save_settings'],
                'permission_callback' => [self::class, 'can_manage'],
                'args'     => [
                    'enabled' => [ 'type' => 'boolean', 'required' => false ],
                    'filters' => [ 'type' => 'array',   'required' => false ],
                    'webhook_url'    => [ 'type' => 'string', 'required' => false ],
                    'webhook_secret' => [ 'type' => 'string', 'required' => false ],
                    'zapier_webhook_url' => [ 'type' => 'string', 'required' => false ],
                    'zapier_secret'      => [ 'type' => 'string', 'required' => false ],
                ],
            ],
        ]);

        // Manual run (daily bundle)
        register_rest_route('searchjet/v1', '/automations/run-daily', [
            [
                'methods'  => 'POST',
                'callback' => [self::class, 'run_daily_now'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        // Fire a test zero-result burst
        register_rest_route('searchjet/v1', '/automations/test-dispatch', [
            [
                'methods'  => 'POST',
                'callback' => [self::class, 'test_dispatch'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
    }

    public static function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public static function get_status(WP_REST_Request $req): WP_REST_Response
    {
        $enabled = (int) get_option('searchjet_automations_enabled', 0) === 1;
        $filters = get_option('searchjet_automation_filters', null);
        $filters = is_array($filters) ? array_values(array_map('sanitize_key', $filters)) : null; // null => default all

        $webhook_url    = (string) get_option('searchjet_webhook_url', '');
        $zapier_url     = (string) get_option('searchjet_zapier_webhook_url', '');

        $data = [
            'enabled'       => $enabled,
            'filters'       => $filters,
            'has_webhook'   => !empty($webhook_url),
            'has_zapier'    => !empty($zapier_url),
            'scheduled'     => (bool) wp_next_scheduled(\SearchJet\Automations\Runner::CRON_HOOK),
            'next_run'      => wp_next_scheduled(\SearchJet\Automations\Runner::CRON_HOOK) ?: null,
        ];
        return new WP_REST_Response($data, 200);
    }

    public static function get_definitions(WP_REST_Request $req): WP_REST_Response
    {
        if (class_exists(Registry::class)) {
            Registry::init();
            $defs = Registry::all();
        } else {
            $defs = [];
        }
        return new WP_REST_Response(['definitions' => array_values($defs)], 200);
    }

    public static function save_settings(WP_REST_Request $req): WP_REST_Response
    {
        $changed = [];

        if ($req->offsetExists('enabled')) {
            $enabled = (bool) $req->get_param('enabled');
            update_option('searchjet_automations_enabled', $enabled ? 1 : 0);
            $changed['enabled'] = $enabled;
        }

        if ($req->offsetExists('filters')) {
            $filters = (array) $req->get_param('filters');
            $filters = array_values(array_unique(array_map('sanitize_key', $filters)));
            update_option('searchjet_automation_filters', $filters);
            $changed['filters'] = $filters;
        }

        if ($req->offsetExists('webhook_url')) {
            $url = esc_url_raw((string) $req->get_param('webhook_url'));
            if ($url !== '' && !wp_http_validate_url($url)) {
                return new WP_Error('searchjet_invalid_webhook_url', __('Webhook URL must be a public, resolvable URL.', 'searchjet-instant-search'), ['status' => 400]);
            }
            update_option('searchjet_webhook_url', $url);
            $changed['webhook_url'] = $url;
        }
        if ($req->offsetExists('webhook_secret')) {
            $secret = (string) $req->get_param('webhook_secret');
            update_option('searchjet_webhook_secret', $secret);
            $changed['webhook_secret'] = ($secret !== '');
        }

        if ($req->offsetExists('zapier_webhook_url')) {
            $url = esc_url_raw((string) $req->get_param('zapier_webhook_url'));
            if ($url !== '' && !wp_http_validate_url($url)) {
                return new WP_Error('searchjet_invalid_zapier_url', __('Zapier webhook URL must be a public, resolvable URL.', 'searchjet-instant-search'), ['status' => 400]);
            }
            update_option('searchjet_zapier_webhook_url', $url);
            $changed['zapier_webhook_url'] = $url;
        }
        if ($req->offsetExists('zapier_secret')) {
            $secret = (string) $req->get_param('zapier_secret');
            update_option('searchjet_zapier_secret', $secret);
            $changed['zapier_secret'] = ($secret !== '');
        }

        // Reschedule cron according to the updated enabled flag.
        if (class_exists(Runner::class)) {
            Runner::ensureScheduled();
        }

        return new WP_REST_Response(['ok' => true, 'changed' => $changed], 200);
    }

    public static function run_daily_now(WP_REST_Request $req): WP_REST_Response
    {
        if (!class_exists(Runner::class)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'runner missing'], 500);
        }
        Runner::runDaily();
        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function test_dispatch(WP_REST_Request $req): WP_REST_Response
    {
        $payload = [
            'count'   => 3,
            'window'  => '5m',
            'samples' => ['example query', 'another term', 'third'],
        ];

        $results = [];

        if (class_exists(N8nClient::class) && N8nClient::isEnabled()) {
            $results['n8n'] = N8nClient::post('search.test_event', $payload, ['source' => 'rest_test']);
        }
        if (class_exists(ZapierClient::class) && ZapierClient::isEnabled()) {
            $results['zapier'] = ZapierClient::post('search.test_event', $payload, ['source' => 'rest_test']);
        }

        if (empty($results)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'no integrations enabled'], 400);
        }
        return new WP_REST_Response(['ok' => true, 'results' => $results], 200);
    }
}

// Auto‑boot
AutomationController::init();