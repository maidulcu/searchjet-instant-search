<?php
namespace SearchJet\API;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class AnalyticsEndpoint {

    private const MAX_BACKLOG_ENTRIES = 500;
    private const MAX_STRING_LENGTH   = 250;
    private const MAX_FILTER_ITEMS    = 50;

    public static function init() {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes() {
        register_rest_route('searchjet/v1', '/log', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'handleLog'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handleLog(WP_REST_Request $request) {
        $entries = $request->get_json_params();

        if (!is_array($entries)) {
            return new WP_REST_Response(['success' => true, 'count' => 0], 200);
        }

        $sanitized = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $clean = self::sanitizeLogEntry($entry);
            if ($clean !== null) {
                $sanitized[] = $clean;
            }
        }

        if (empty($sanitized)) {
            return new WP_REST_Response(['success' => true, 'count' => 0], 200);
        }

        // Try to send directly to SearchJet API
        $sent = \SearchJet\API\SearchJetRemoteClient::recordSearchAnalytics($sanitized);
        
        if (!$sent) {
            // Fallback to local storage if API call fails
            $backlog = get_option('searchjet_log_backlog', []);
            $backlog = is_array($backlog) ? $backlog : [];

            $merged = array_merge($backlog, $sanitized);
            if (count($merged) > self::MAX_BACKLOG_ENTRIES) {
                $merged = array_slice($merged, -self::MAX_BACKLOG_ENTRIES);
            }

            update_option('searchjet_log_backlog', $merged, false);
        }

        return new WP_REST_Response(['success' => true, 'count' => count($sanitized)], 200);
    }

    private static function sanitizeLogEntry(array $entry): ?array
    {
        $query = self::limitString(sanitize_text_field($entry['query'] ?? ''));
        $results = isset($entry['results']) ? intval($entry['results']) : (isset($entry['results_count']) ? intval($entry['results_count']) : 0);
        if ($results < 0) {
            $results = 0;
        }

        $timestamp = isset($entry['ts']) ? intval($entry['ts']) : 0;
        if ($timestamp <= 0 || $timestamp > time() + DAY_IN_SECONDS) {
            $timestamp = time();
        }

        $filters = self::sanitizeFilters($entry['filters'] ?? null);
        if (is_array($filters) && empty($filters)) {
            $filters = null;
        }
        $referer = self::sanitizeUrl($entry['referer'] ?? null);
        $userAgent = self::limitString(sanitize_text_field($entry['user_agent'] ?? ''), 400);
        $sessionId = self::limitString(sanitize_text_field($entry['session_id'] ?? ''), 120);
        $ip = self::sanitizeIp($entry['ip'] ?? null);

        return [
            'query'        => $query,
            'results'      => $results,
            'ts'           => $timestamp,
            'filters'      => $filters,
            'referer'      => $referer,
            'user_agent'   => $userAgent !== '' ? $userAgent : null,
            'session_id'   => $sessionId !== '' ? $sessionId : null,
            'ip'           => $ip,
        ];
    }

    private static function sanitizeFilters($filters)
    {
        if (is_array($filters)) {
            return self::sanitizeArray($filters);
        }

        if (is_string($filters)) {
            $clean = self::limitString(wp_strip_all_tags($filters));
            return $clean !== '' ? $clean : null;
        }

        return null;
    }

    private static function sanitizeArray(array $value): array
    {
        $isList = array_keys($value) === range(0, count($value) - 1);
        $result = [];

        foreach ($value as $key => $item) {
            if (count($result) >= self::MAX_FILTER_ITEMS) {
                break;
            }

            if (is_array($item)) {
                $sanitized = self::sanitizeArray($item);
                if (!empty($sanitized)) {
                    if ($isList) {
                        $result[] = $sanitized;
                    } else {
                        $cleanKey = sanitize_key((string) $key);
                        if ($cleanKey === '') {
                            continue;
                        }
                        $result[$cleanKey] = $sanitized;
                    }
                }
                continue;
            }

            if (!is_scalar($item)) {
                continue;
            }

            $sanitizedItem = self::limitString((string) $item);
            if ($sanitizedItem === '') {
                continue;
            }

            if ($isList) {
                $result[] = $sanitizedItem;
            } else {
                $cleanKey = sanitize_key((string) $key);
                if ($cleanKey === '') {
                    continue;
                }
                $result[$cleanKey] = $sanitizedItem;
            }
        }

        return $result;
    }

    private static function sanitizeUrl($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $url = esc_url_raw($value);
        return $url !== '' ? $url : null;
    }

    private static function sanitizeIp($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (function_exists('rest_is_ip_address')) {
            return rest_is_ip_address($value) ? $value : null;
        }

        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    private static function limitString(string $value, int $max = self::MAX_STRING_LENGTH): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') > $max) {
                return mb_substr($value, 0, $max, 'UTF-8');
            }
            return $value;
        }

        if (strlen($value) > $max) {
            return substr($value, 0, $max);
        }

        return $value;
    }
}
