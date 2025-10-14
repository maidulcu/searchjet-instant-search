<?php

namespace SearchJet\Automations;

class Webhooks
{
    /**
     * Dispatch a webhook event (n8n/Zapier/etc).
     * Respects Automations toggle and supports HMAC signing.
     *
     * @return array{ok:bool,status:string,code:int|null,error:?string,response_body:?string}
     */
    public static function dispatch(string $event, array $payload): array
    {
        // Respect master toggle
        $enabled = (int) get_option('searchjet_automations_enabled', 0) === 1;
        if (!$enabled) {
            return [
                'ok' => false,
                'status' => 'skipped',
                'code' => null,
                'error' => 'automations_disabled',
                'response_body' => null,
            ];
        }

        // Allow either the new or legacy option keys
        $webhook_url = (string) get_option('searchjet_automation_webhook_url');
        if ($webhook_url === '') {
            $webhook_url = (string) get_option('searchjet_webhook_url');
        }
        $webhook_url = apply_filters('searchjet/automation/webhook_url', $webhook_url, $event, $payload);

        if ($webhook_url === '') {
            return [
                'ok' => false,
                'status' => 'skipped',
                'code' => null,
                'error' => 'no_webhook_configured',
                'response_body' => null,
            ];
        }

        $ts = time();
        $body_arr = [
            'site'    => home_url('/'),
            'event'   => $event,
            'payload' => $payload,
            'ts'      => $ts,
        ];
        $body_arr = apply_filters('searchjet/automation/webhook_body', $body_arr, $event, $payload);
        $json = wp_json_encode($body_arr);

        $secret = (string) get_option('searchjet_automation_webhook_secret');
        if ($secret === '') {
            $secret = (string) get_option('searchjet_webhook_secret');
        }

        $headers = [
            'Content-Type'          => 'application/json',
            'X-SearchJet-Timestamp' => (string) $ts,
        ];

        if ($secret !== '') {
            $headers['X-SearchJet-Signature'] = 'sha256=' . hash_hmac('sha256', $json . '|' . $ts, $secret);
        }

        $headers = apply_filters('searchjet/automation/webhook_headers', $headers, $event, $payload, $ts);

        $args = [
            'headers' => $headers,
            'body'    => $json,
            'timeout' => 12,
        ];

        $resp = wp_remote_post($webhook_url, $args);
        if (is_wp_error($resp)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'code' => null,
                'error' => $resp->get_error_message(),
                'response_body' => null,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);

        // One immediate retry on 5xx
        if ($code >= 500) {
            $resp = wp_remote_post($webhook_url, $args);
            if (!is_wp_error($resp)) {
                $code = (int) wp_remote_retrieve_response_code($resp);
                $body = (string) wp_remote_retrieve_body($resp);
            }
        }

        $ok = ($code >= 200 && $code < 300);
        return [
            'ok' => $ok,
            'status' => $ok ? 'success' : 'failed',
            'code' => $code,
            'error' => $ok ? null : 'http_' . $code,
            'response_body' => $body !== '' ? $body : null,
        ];
    }
}
