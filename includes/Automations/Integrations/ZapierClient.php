<?php
/**
 * SearchJet – Zapier Integration Client
 */

namespace SearchJet\Automations\Integrations;

use SearchJet\Helpers\NetworkHelper;

if (!defined('ABSPATH')) {
    exit;
}

class ZapierClient
{
    /**
     * Are outbound webhooks to Zapier enabled?
     */
    public static function isEnabled(): bool
    {
        $url = trim((string) get_option('searchjet_zapier_webhook_url', ''));
        return (bool) $url;
    }

    /**
     * Get configured catch-hook endpoint URL (sanitized).
     */
    public static function endpoint(): string
    {
        $url = trim((string) get_option('searchjet_zapier_webhook_url', ''));
        return esc_url_raw($url);
    }

    /**
     * Optional shared secret for simple verification on the Zap side.
     * (You can add a Filter step in Zapier to check this header.)
     */
    public static function secret(): string
    {
        return (string) get_option('searchjet_zapier_secret', '');
    }

    /**
     * Timeout (seconds) for requests.
     */
    protected static function timeout(): int
    {
        return 8;
    }

    /**
     * Dispatch a normalized automation event to a Zapier Catch Hook.
     *
     * @param string $event   Event name, e.g. 'search.zero_result_burst'
     * @param array  $payload Flat array payload
     * @param array  $context Extra context (optional)
     * @return array { 'ok' => bool, 'code' => int, 'body' => mixed, 'error' => string|null }
     */
    public static function post(string $event, array $payload, array $context = []): array
    {
        if (!self::isEnabled()) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'error' => 'zapier disabled'];
        }

        $url = self::endpoint();
        if (!$url) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'error' => 'invalid endpoint'];
        }

        $body = [
            'event'   => $event,
            'ts'      => time(),
            'site'    => NetworkHelper::siteContext(),
            'payload' => $payload,
            'meta'    => $context,
        ];

        $json = wp_json_encode($body);

        $headers = [
            'Content-Type'          => 'application/json',
            'User-Agent'            => 'SearchJet/Automations (+wordpress)',
            'X-SearchJet-Event'     => $event,
        ];

        $secret = self::secret();
        if ($secret !== '') {
            $headers['X-SearchJet-Secret'] = $secret;
        }

        $args = [
            'method'      => 'POST',
            'timeout'     => self::timeout(),
            'redirection' => 2,
            'blocking'    => true,
            'headers'     => $headers,
            'body'        => $json,
        ];

        $res = wp_safe_remote_post($url, $args);

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
}