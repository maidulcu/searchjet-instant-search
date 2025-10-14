<?php
/**
 * SearchJet – AI Toolbox
 *
 * A small set of helpers used by AI Agents and future automation prompts.
 */

namespace SearchJet\AI;

if (!defined('ABSPATH')) {
    exit;
}

class Toolbox
{
    /**
     * Basic site context useful for prompts or outbound calls.
     */
    public static function site(): array
    {
        $site_id = (int) get_option('searchjet_site_id', 0);
        $lang    = get_bloginfo('language');
        $tz      = get_option('timezone_string') ?: 'UTC';
        $name    = get_bloginfo('name');

        return [
            'home'      => home_url('/'),
            'name'      => $name,
            'language'  => $lang,
            'timezone'  => $tz,
            'site_id'   => $site_id,
            'wp_version'=> (string) get_bloginfo('version'),
            'plugin'    => defined('SEARCHJET_PLUGIN_VERSION') ? SEARCHJET_PLUGIN_VERSION : 'unknown',
        ];
    }

    /**
     * Pulls a subset of SearchJet-related settings.
     */
    public static function settings(): array
    {
        $api_key   = (string) get_option('searchjet_hosted_api_key', '');
        $site_id   = (int) get_option('searchjet_site_id', 0);
        $mode      = (string) get_option('searchjet_search_mode', 'template');
        $attrs     = get_option('searchjet_search_attributes', []);
        $attrs     = is_array($attrs) ? array_values(array_map('sanitize_key', $attrs)) : [];

        return [
            'api_key' => $api_key !== '' ? '***' . substr($api_key, -4) : '', // mask
            'site_id' => $site_id,
            'mode'    => $mode ?: 'template',
            'attributes' => $attrs,
        ];
    }

    /**
     * Minimal text sanitizer suitable for model prompts.
     */
    public static function cleanText(?string $text, int $maxLen = 2000): string
    {
        if ($text === null) {
            return '';
        }
        // Remove shortcodes and HTML, normalize whitespace
        $t = strip_shortcodes($text);
        $t = wp_strip_all_tags($t, true);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/\s+/u', ' ', $t ?? '');
        $t = trim((string) $t);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($t, 'UTF-8') > $maxLen) {
                $t = mb_substr($t, 0, $maxLen - 1, 'UTF-8') . '…';
            }
        } else {
            if (strlen($t) > $maxLen) {
                $t = substr($t, 0, $maxLen - 1) . '…';
            }
        }
        return $t;
    }

    /**
     * Simple template renderer – replaces {{key}} with scalar values.
     */
    public static function render(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $val) {
            if (is_scalar($val)) {
                $out = str_replace('{{' . $key . '}}', (string) $val, $out);
            } elseif (is_array($val)) {
                $out = str_replace('{{' . $key . '}}', wp_json_encode($val), $out);
            }
        }
        return $out;
    }

    /**
     * Helper: build a markdown list from items.
     */
    public static function toMarkdownList(array $items): string
    {
        $lines = [];
        foreach ($items as $it) {
            $lines[] = '- ' . (is_scalar($it) ? (string) $it : wp_json_encode($it));
        }
        return implode("\n", $lines);
    }

    /**
     * HTTP: JSON POST wrapper with sane defaults.
     * Returns [ok, code, body, error]
     */
    public static function httpPostJson(string $url, array $body, array $headers = []): array
    {
        $args = [
            'method'      => 'POST',
            'timeout'     => 12,
            'redirection' => 2,
            'blocking'    => true,
            'headers'     => array_merge([
                'Content-Type' => 'application/json',
                'User-Agent'   => 'SearchJet/AI (+wordpress)'
            ], $headers),
            'body'        => wp_json_encode($body),
        ];
        $res = wp_remote_post(esc_url_raw($url), $args);
        if (is_wp_error($res)) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'error' => $res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $txt  = wp_remote_retrieve_body($res);
        $json = json_decode($txt, true);
        return [
            'ok'   => $code >= 200 && $code < 300,
            'code' => $code,
            'body' => $json !== null ? $json : $txt,
            'error'=> null,
        ];
    }

    /**
     * HTTP: JSON GET wrapper with sane defaults.
     */
    public static function httpGetJson(string $url, array $headers = []): array
    {
        $args = [
            'method'      => 'GET',
            'timeout'     => 12,
            'redirection' => 2,
            'blocking'    => true,
            'headers'     => array_merge([
                'Accept'      => 'application/json',
                'User-Agent'  => 'SearchJet/AI (+wordpress)'
            ], $headers),
        ];
        $res = wp_remote_get(esc_url_raw($url), $args);
        if (is_wp_error($res)) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'error' => $res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $txt  = wp_remote_retrieve_body($res);
        $json = json_decode($txt, true);
        return [
            'ok'   => $code >= 200 && $code < 300,
            'code' => $code,
            'body' => $json !== null ? $json : $txt,
            'error'=> null,
        ];
    }

    /**
     * Current user context (masked email for privacy).
     */
    public static function currentUser(): array
    {
        $u = wp_get_current_user();
        if (!$u || 0 === $u->ID) {
            return ['id' => 0, 'role' => 'guest'];
        }
        return [
            'id'    => (int) $u->ID,
            'login' => (string) $u->user_login,
            'email' => self::maskEmail((string) $u->user_email),
            'roles' => array_values((array) $u->roles),
            'can_manage_options' => current_user_can('manage_options'),
        ];
    }

    /**
     * Mask an email (e.g., a****@example.com) for logs/prompts.
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }
        $name = $parts[0];
        if (strlen($name) <= 1) {
            $name = '*';
        } else {
            $name = substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 1));
        }
        return $name . '@' . $parts[1];
    }
}