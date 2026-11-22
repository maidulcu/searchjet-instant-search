<?php

namespace SearchJet\Rest;

use WP_REST_Request;
use WP_REST_Response;
use SearchJet\Helpers\NetworkHelper;

/**
 * REST controller to receive client-side SearchJet events.
 * MVP scope: zero-result search burst tracking.
 */
class EventsController
{
    /**
     * Wire the controller into WordPress REST API.
     */
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register']);
    }

    /**
     * Register the routes.
     */
    public static function register(): void
    {
        register_rest_route(
            'searchjet/v1',
            '/event',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'handle'],
                'permission_callback' => [self::class, 'permission'],
                'args'                => [
                    'type'  => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'q'     => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'total' => [
                        'type'     => 'integer',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    /**
     * Permission check: if `searchjet_require_event_nonce` is enabled, require a valid wp_rest nonce.
     * Otherwise allow public access (suitable for anonymous search pages).
     */
    public static function permission(WP_REST_Request $request): bool
    {
        $require_nonce = (int) get_option('searchjet_require_event_nonce', 1) === 1;
        if (!$require_nonce) {
            return true; // public endpoint for anonymous search logging
        }

        // Accept either header or request param for nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }

        // Always allow admins
        return current_user_can('manage_options');
    }

    /**
     * Handle POST /searchjet/v1/event
     */
    public static function handle(WP_REST_Request $request)
    {
        $type = sanitize_key((string) $request->get_param('type'));

        // Very small IP-based throttle to avoid abuse when public.
        $ip = NetworkHelper::clientIp();
        $rl_key = 'sj_evt_rl_' . md5($ip . '|' . $type);
        $rl = (int) get_transient($rl_key);
        if ($rl > 240) { // ~240 events / 10 minutes per type per IP
            return new WP_REST_Response(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        set_transient($rl_key, $rl + 1, 10 * MINUTE_IN_SECONDS);

        switch ($type) {
            case 'zero_result':
                $q     = trim((string) $request->get_param('q'));
                $total = (int) $request->get_param('total'); // optional diagnostic

                // Sanitize/limit
                if ($q !== '') {
                    $q = wp_strip_all_tags($q);
                    if (strlen($q) > 200) {
                        $q = substr($q, 0, 200);
                    }
                }

                if (class_exists('SearchJet\\Automations\\Events') && is_callable(['SearchJet\\Automations\\Events', 'recordZeroResult'])) {
                    $meta = [
                        'total' => $total,
                        'ua'    => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 190) : '',
                        'ref'   => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw((string) $_SERVER['HTTP_REFERER']) : '',
                        'ip'    => $ip,
                    ];
                    \SearchJet\Automations\Events::recordZeroResult($q, $meta);
                    return new WP_REST_Response(['ok' => true], 200);
                }

                return new WP_REST_Response(['ok' => false, 'error' => 'events_module_missing'], 501);

            default:
                return new WP_REST_Response(['ok' => false, 'error' => 'unsupported_type'], 400);
        }
    }
}