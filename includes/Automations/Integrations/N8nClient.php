<?php
/**
 * SearchJet – n8n Integration Client
 */

namespace SearchJet\Automations\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class N8nClient
{
    /**
     * Are outbound webhooks to n8n enabled?
     */
    public static function isEnabled(): bool
    {
        $url = trim((string) get_option('searchjet_webhook_url', ''));
        return (bool) $url;
    }

    /**
     * Get configured endpoint URL (sanitized).
     */
    public static function endpoint(): string
    {
        $url = trim((string) get_option('searchjet_webhook_url', ''));
        return esc_url_raw($url);
    }

    /**
     * Shared secret for HMAC signing.
     */
    public static function secret(): string
    {
        $secret = (string) get_option('searchjet_webhook_secret', '');
        return $secret;
    }

    /**
     * Timeout for requests (in seconds).
     */
    protected static function timeout(): int
    {
        return 8; // keep it snappy in admin/cron
    }

    /**
     * Dispatch a normalized automation event to n8n.
     *
     * @param string $event   Event name, e.g. 'search.zero_result_burst'
     * @param array  $payload Flat array payload (will be JSON-encoded)
     * @param array  $context Extra context (optional)
     * @return array { 'ok' => bool, 'code' => int, 'body' => mixed, 'error' => string|null }
     */
    public static function post(string $event, array $payload, array $context = []): array
    {
        if (!self::isEnabled()) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'error' => 'n8n disabled'];
        }

        $url = self::endpoint();
        if (!$url) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'error' => 'invalid endpoint'];
        }

        $body = [
            'event'   => $event,
            'ts'      => time(),
            'site'    => self::siteContext(),
            'payload' => $payload,
            'meta'    => $context,
        ];

        $json      = wp_json_encode($body);
        $timestamp = (string) time();
        $sig       = self::signature($json, $timestamp);

        $args = [
            'method'      => 'POST',
            'timeout'     => self::timeout(),
            'redirection' => 2,
            'blocking'    => true,
            'headers'     => [
                'Content-Type'            => 'application/json',
                'X-SearchJet-Event'       => $event,
                'X-SearchJet-Timestamp'   => $timestamp,
                'X-SearchJet-Signature'   => 'sha256=' . $sig,
                'User-Agent'              => 'SearchJet/Automations (+wordpress)'
            ],
            'body'        => $json,
        ];

        $res = wp_remote_post($url, $args);
        $ok  = (!is_wp_error($res) && (int) wp_remote_retrieve_response_code($res) < 500);

        // Simple one-time retry on 5xx
        if (!$ok && !is_wp_error($res) && (int) wp_remote_retrieve_response_code($res) >= 500) {
            $res = wp_remote_post($url, $args);
        }

        if (is_wp_error($res)) {
            return [
                'ok'    => false,
                'code'  => 0,
                'body'  => null,
                'error' => $res->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $bodyTxt = wp_remote_retrieve_body($res);
        $parsed  = json_decode($bodyTxt, true);

        return [
            'ok'    => $code >= 200 && $code < 300,
            'code'  => $code,
            'body'  => $parsed !== null ? $parsed : $bodyTxt,
            'error' => null,
        ];
    }

    /**
     * Compose site context (domain, site_id if known, WP version, plugin version).
     */
    protected static function siteContext(): array
    {
        $site_id = (int) get_option('searchjet_site_id', 0);
        $plugin_version = defined('SEARCHJET_PLUGIN_VERSION') ? SEARCHJET_PLUGIN_VERSION : 'unknown';
        global $wp_version;
        return [
            'home'    => home_url('/'),
            'site_id' => $site_id,
            'wp'      => (string) $wp_version,
            'plugin'  => (string) $plugin_version,
        ];
    }

    /**
     * Build HMAC signature of the JSON body and timestamp.
     */
    protected static function signature(string $json, string $timestamp): string
    {
        $secret = self::secret();
        $data   = $json . '|' . $timestamp;
        return hash_hmac('sha256', $data, $secret);
    }
}