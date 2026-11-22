<?php
namespace SearchJet\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

abstract class TestCase extends \PHPUnit\Framework\TestCase {

    use MockeryPHPUnitIntegration;

    protected array $storedOptions = [];
    protected array $storedTransients = [];

    protected function setUp(): void {
        parent::setUp();
        Monkey\tearDown();
        Monkey\setUp();

        $this->storedOptions = [];
        $this->storedTransients = [];

        \Brain\Monkey\Functions\stubEscapeFunctions();
        \Brain\Monkey\Functions\stubTranslationFunctions();

        \Brain\Monkey\Functions\when('get_option')
            ->alias(function ($key, $default = null) {
                return $this->storedOptions[$key] ?? $default;
            });

        \Brain\Monkey\Functions\when('update_option')
            ->alias(function ($key, $value) {
                $this->storedOptions[$key] = $value;
                return true;
            });

        \Brain\Monkey\Functions\when('delete_option')
            ->alias(function ($key) {
                unset($this->storedOptions[$key]);
                return true;
            });

        \Brain\Monkey\Functions\when('get_transient')
            ->alias(function ($key) {
                return $this->storedTransients[$key] ?? false;
            });

        \Brain\Monkey\Functions\when('set_transient')
            ->alias(function ($key, $value, $ttl = 0) {
                $this->storedTransients[$key] = $value;
                return true;
            });

        \Brain\Monkey\Functions\when('delete_transient')
            ->alias(function ($key) {
                unset($this->storedTransients[$key]);
                return true;
            });

        \Brain\Monkey\Functions\when('wp_remote_get')->justReturn([]);
        \Brain\Monkey\Functions\when('wp_remote_post')->justReturn([]);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->justReturn('{}');
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);

        \Brain\Monkey\Functions\when('add_action')->justReturn(true);
        \Brain\Monkey\Functions\when('add_filter')->justReturn(true);

        \Brain\Monkey\Functions\when('apply_filters')
            ->alias(function ($tag, $value) {
                return $value;
            });

        \Brain\Monkey\Functions\when('sanitize_key')
            ->alias(function ($key) {
                return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
            });

        \Brain\Monkey\Functions\when('sanitize_text_field')
            ->alias(function ($str) {
                return trim((string) $str);
            });

        \Brain\Monkey\Functions\when('sanitize_hex_color')
            ->alias(function ($color) {
                if (preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $color)) {
                    return $color;
                }
                return '';
            });

        \Brain\Monkey\Functions\when('wp_unslash')
            ->alias(function ($value) {
                return is_string($value) ? stripslashes($value) : $value;
            });

        \Brain\Monkey\Functions\when('wp_verify_nonce')->justReturn(true);
        \Brain\Monkey\Functions\when('wp_create_nonce')->justReturn('test_nonce');
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(true);
        \Brain\Monkey\Functions\when('is_admin')->justReturn(false);

        \Brain\Monkey\Functions\when('defined')
            ->alias(function ($name) {
                if ($name === 'ABSPATH') {
                    return true;
                }
                if ($name === 'SEARCHJET_VERSION') {
                    return true;
                }
                if ($name === 'SEARCHJET_PLUGIN_VERSION') {
                    return true;
                }
                return false;
            });

        \Brain\Monkey\Functions\when('wp_die')
            ->alias(function ($msg) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test mock, escaping irrelevant.
                throw new \RuntimeException($msg);
            });

        \Brain\Monkey\Functions\when('wp_debug_backtrace_summary')->justReturn('');

        \Brain\Monkey\Functions\when('admin_url')
            ->alias(function ($path) {
                return 'http://example.com/wp-admin/' . ltrim($path, '/');
            });

        \Brain\Monkey\Functions\when('home_url')->justReturn('http://example.com');
        \Brain\Monkey\Functions\when('rest_url')->justReturn('http://example.com/wp-json/');

        \Brain\Monkey\Functions\when('wp_send_json_success')
            ->alias(function ($data) {
                throw new \RuntimeException(json_encode($data));
            });

        \Brain\Monkey\Functions\when('wp_send_json_error')
            ->alias(function ($data) {
                throw new \RuntimeException(json_encode($data));
            });
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}
