<?php
/**
 * SearchJet Automations - Manager (v1.1.0 foundation)
 *
 * Boots the Automations system, wires WP hooks, and gates behavior
 * behind the `searchjet_automations_enabled` option.
 */

namespace SearchJet\Automations;

if (!defined('ABSPATH')) {
    exit;
}

final class Manager
{
    /**
     * Prevent double bootstrapping.
     *
     * @var bool
     */
    private static bool $bootstrapped = false;

    /**
     * Option key for global enable/disable.
     */
    private const OPTION_ENABLED = 'searchjet_automations_enabled';

    /** Webhook URL for external automation runners (n8n/Zapier) */
    private const OPTION_WEBHOOK_URL   = 'searchjet_webhook_url';
    /** Optional secret used to sign webhook payloads */
    private const OPTION_WEBHOOK_SECRET = 'searchjet_webhook_secret';
    /** Optional: restrict which built-ins are active */
    private const OPTION_FILTERS       = 'searchjet_automation_filters';
    /** Require X-WP-Nonce on events endpoint */
    private const OPTION_REQUIRE_NONCE = 'searchjet_require_event_nonce';

    /**
     * Entry point: called from plugin bootstrap.
     * - Sets up core hooks.
     * - Defers heavy work to callbacks that check is_enabled().
     */
    public static function init(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        // Keep it light: register our hooks on load.
        add_action('plugins_loaded', [__CLASS__, 'register_hooks']);

        // Allow external settings updates to safely re-hit our callbacks.
        add_action('update_option_' . self::OPTION_ENABLED, function () {
            // No immediate action required; callbacks guard with is_enabled().
        }, 10, 0);
    }

    /**
     * Whether Automations are globally enabled.
     */
    public static function is_enabled(): bool
    {
        if (defined('SEARCHJET_DISABLE_AUTOMATIONS') && SEARCHJET_DISABLE_AUTOMATIONS) {
            return false;
        }
        return (bool) get_option(self::OPTION_ENABLED, false);
    }

    /**
     * Register WP hooks for admin and runtime events.
     * Safe to call multiple times.
     */
    public static function register_hooks(): void
    {
        // Load and register built-in automations if Registry is available.
        if (class_exists(__NAMESPACE__ . '\\Registry')) {
            if (method_exists(Registry::class, 'init')) {
                // Registry::init() should be idempotent.
                Registry::init();
            }
        }

        // Admin-side settings (register option). Lightweight.
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Bridge: when the core plugin finishes indexing, forward to Events.
        add_action('searchjet_index_completed', [__CLASS__, 'on_index_completed'], 10, 1);

        // When a zero-result search is reported (via REST or JS), forward to Events.
        add_action('searchjet_zero_result', [__CLASS__, 'on_zero_result'], 10, 2); // (query, total)

        // Allow manual dispatch of automation webhooks (for test buttons).
        add_action('searchjet_dispatch_automation', [__CLASS__, 'on_dispatch_automation'], 10, 2); // (event, payload)

        // If Events exists, let it initialize internal caches/state.
        if (class_exists(__NAMESPACE__ . '\\Events') && method_exists(Events::class, 'init')) {
            Events::init();
        }
    }

    /**
     * Register minimal settings used by Automations.
     * (UI lives on the admin page; here we only register the option.)
     */
    public static function register_settings(): void
    {
        // Master toggle
        register_setting(
            'searchjet_automations',
            self::OPTION_ENABLED,
            [
                'type'              => 'boolean',
                'sanitize_callback' => static fn($v) => (bool) $v,
                'default'           => false,
                'show_in_rest'      => true,
            ]
        );

        // Webhook URL & Secret used by Automations\Webhooks
        register_setting(
            'searchjet_automations',
            self::OPTION_WEBHOOK_URL,
            [
                'type'              => 'string',
                'sanitize_callback' => static fn($v) => esc_url_raw((string) $v),
                'default'           => '',
                'show_in_rest'      => true,
            ]
        );

        register_setting(
            'searchjet_automations',
            self::OPTION_WEBHOOK_SECRET,
            [
                'type'              => 'string',
                'sanitize_callback' => static fn($v) => (string) $v,
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        // Optional filter list of enabled built-ins
        register_setting(
            'searchjet_automations',
            self::OPTION_FILTERS,
            [
                'type'              => 'array',
                'sanitize_callback' => static function ($v) {
                    $v = is_array($v) ? $v : [];
                    return array_values(array_filter(array_map('sanitize_text_field', $v)));
                },
                'default'           => [],
                'show_in_rest'      => true,
            ]
        );

        // Security for REST events
        register_setting(
            'searchjet_automations',
            self::OPTION_REQUIRE_NONCE,
            [
                'type'              => 'boolean',
                'sanitize_callback' => static fn($v) => (bool) $v,
                'default'           => false,
                'show_in_rest'      => true,
            ]
        );
    }

    /**
     * Handle indexing completion by emitting a normalized event.
     *
     * @param array|mixed $summary Expects keys like added, updated, skipped, duration, source
     */
    public static function on_index_completed($summary): void
    {
        if (!self::is_enabled()) {
            return;
        }

        if (class_exists(__NAMESPACE__ . '\\Events') && method_exists(Events::class, 'indexCompleted')) {
            try {
                Events::indexCompleted(is_array($summary) ? $summary : []);
            } catch (\Throwable $e) {
                if (class_exists(__NAMESPACE__ . '\\Logger')) {
                    Logger::log('error', 'Failed handling index_completed', [
                        'error'   => $e->getMessage(),
                        'summary' => is_array($summary) ? $summary : ['_type' => gettype($summary)],
                    ]);
                }
            }
        }
    }

    /**
     * Bridge for zero-result events.
     */
    public static function on_zero_result(string $query, int $total = 0): void
    {
        if (!self::is_enabled()) {
            return;
        }
        if (class_exists(__NAMESPACE__ . '\Events') && method_exists(Events::class, 'recordZeroResult')) {
            try {
                Events::recordZeroResult($query, ['total' => (int) $total]);
            } catch (\Throwable $e) {
                if (class_exists(__NAMESPACE__ . '\Logger')) {
                    Logger::log('error', 'Failed handling zero_result', [
                        'error' => $e->getMessage(),
                        'q'     => $query,
                        'total' => (int) $total,
                    ]);
                }
            }
        }
    }

    /**
     * Manual dispatch hook to Webhooks helper.
     */
    public static function on_dispatch_automation(string $event, array $payload = []): void
    {
        if (!self::is_enabled()) {
            return;
        }
        if (class_exists(__NAMESPACE__ . '\Webhooks') && method_exists(Webhooks::class, 'dispatch')) {
            try {
                Webhooks::dispatch($event, $payload);
            } catch (\Throwable $e) {
                if (class_exists(__NAMESPACE__ . '\Logger')) {
                    Logger::log('error', 'Failed manual dispatch', [
                        'error'  => $e->getMessage(),
                        'event'  => $event,
                        'payload'=> $payload,
                    ]);
                }
            }
        }
    }
}