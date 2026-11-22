<?php
namespace SearchJet\Helpers;

class NetworkHelper {

    public static function clientIp(): string {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $raw = (string) $_SERVER[$k];
                $ip = preg_split('/\s*,\s*/', $raw)[0];
                return $ip;
            }
        }
        return '0.0.0.0';
    }

    public static function siteContext(): array {
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
}
