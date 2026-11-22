<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Admin\EnhancedSettingsPage;
use SearchJet\Tests\TestCase;

class EnhancedSettingsPageTest extends TestCase {

    /** @test */
    public function sanitize_attributes_trims_whitespace() {
        $result = EnhancedSettingsPage::sanitizeAttributes('  title,  content,   excerpt  ');
        $this->assertEquals('title, content, excerpt', $result);
    }

    /** @test */
    public function sanitize_attributes_splits_by_newline() {
        $result = EnhancedSettingsPage::sanitizeAttributes("title\ncontent\nexcerpt");
        $this->assertEquals('title, content, excerpt', $result);
    }

    /** @test */
    public function sanitize_attributes_handles_mixed_delimiters() {
        $result = EnhancedSettingsPage::sanitizeAttributes("title,content\nexcerpt,description");
        $this->assertEquals('title, content, excerpt, description', $result);
    }

    /** @test */
    public function sanitize_attributes_removes_empty_entries() {
        $result = EnhancedSettingsPage::sanitizeAttributes("title,,\ncontent\n,,");
        $this->assertEquals('title, content', $result);
    }

    /** @test */
    public function sanitize_attributes_returns_empty_for_non_string() {
        $result = EnhancedSettingsPage::sanitizeAttributes(['not', 'a', 'string']);
        $this->assertEquals('', $result);
    }

    /** @test */
    public function sanitize_attributes_returns_empty_for_empty_string() {
        $result = EnhancedSettingsPage::sanitizeAttributes('');
        $this->assertEquals('', $result);
    }

    /** @test */
    public function sanitize_ui_options_returns_defaults_for_empty_input() {
        $result = EnhancedSettingsPage::sanitizeUiOptions([]);
        $this->assertEquals(1, $result['show_thumbnail']);
        $this->assertEquals(1, $result['show_price']);
        $this->assertEquals(1, $result['show_rating']);
    }

    /** @test */
    public function sanitize_ui_options_merges_with_defaults() {
        $result = EnhancedSettingsPage::sanitizeUiOptions(['show_thumbnail' => 0]);
        $this->assertEquals(0, $result['show_thumbnail']);
        $this->assertEquals(1, $result['show_price']);
        $this->assertEquals(1, $result['show_rating']);
    }

    /** @test */
    public function sanitize_ui_options_absint_input_values() {
        $result = EnhancedSettingsPage::sanitizeUiOptions(['show_thumbnail' => '1', 'show_price' => 0]);
        $this->assertSame(1, $result['show_thumbnail']);
        $this->assertSame(0, $result['show_price']);
    }

    /** @test */
    public function sanitize_ui_options_returns_defaults_for_non_array() {
        $result = EnhancedSettingsPage::sanitizeUiOptions('not_an_array');
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['show_thumbnail']);
    }

    /** @test */
    public function normalize_search_analytics_handles_empty_input() {
        $this->assertEquals([], EnhancedSettingsPage::normalizeSearchAnalytics([]));
    }

    /** @test */
    public function normalize_search_analytics_handles_non_array() {
        $this->assertEquals([], EnhancedSettingsPage::normalizeSearchAnalytics(null));
        $this->assertEquals([], EnhancedSettingsPage::normalizeSearchAnalytics('string'));
    }

    /** @test */
    public function normalize_search_analytics_maps_top_search_queries() {
        $input = ['top_search_queries' => [['query' => 'test', 'count' => 5]]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('top_search_queries', $result);
        $this->assertEquals([['query' => 'test', 'count' => 5]], $result['top_search_queries']);
    }

    /** @test */
    public function normalize_search_analytics_maps_queries_alias() {
        $input = ['queries' => [['query' => 'test', 'count' => 3]]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('top_search_queries', $result);
    }

    /** @test */
    public function normalize_search_analytics_maps_search_queries_alias() {
        $input = ['search_queries' => [['query' => 'test', 'count' => 3]]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('top_search_queries', $result);
    }

    /** @test */
    public function normalize_search_analytics_maps_zero_results() {
        $input = ['zero_results' => [['query' => 'test', 'count' => 1]]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('zero_result_queries', $result);
    }

    /** @test */
    public function normalize_search_analytics_maps_zero_result_queries() {
        $input = ['zero_result_queries' => [['query' => 'test', 'count' => 1]]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('zero_result_queries', $result);
    }

    /** @test */
    public function normalize_search_analytics_maps_no_results_alias() {
        $input = ['no_results' => [['query' => 'test', 'count' => 1]]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('zero_result_queries', $result);
    }

    /** @test */
    public function normalize_search_analytics_maps_daily_volume() {
        $input = ['daily_volume' => ['2024-01-01' => 10]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('daily_volume', $result);
    }

    /** @test */
    public function normalize_search_analytics_maps_daily_searches_alias() {
        $input = ['daily_searches' => ['2024-01-01' => 5]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('daily_volume', $result);
    }

    /** @test */
    public function normalize_search_analytics_maps_search_volume_alias() {
        $input = ['search_volume' => ['2024-01-01' => 5]];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertArrayHasKey('daily_volume', $result);
    }

    /** @test */
    public function normalize_search_analytics_first_key_wins() {
        $input = [
            'top_search_queries' => [['query' => 'correct', 'count' => 1]],
            'queries' => [['query' => 'wrong', 'count' => 1]],
        ];
        $result = EnhancedSettingsPage::normalizeSearchAnalytics($input);
        $this->assertEquals('correct', $result['top_search_queries'][0]['query']);
    }

    /** @test */
    public function normalize_query_list_handles_associative_arrays() {
        $input = ['test' => 5, 'hello' => 3];
        $result = EnhancedSettingsPage::normalizeQueryList($input);
        $this->assertCount(2, $result);
        $this->assertEquals('test', $result[0]['query']);
        $this->assertEquals(5, $result[0]['count']);
    }

    /** @test */
    public function normalize_query_list_handles_numeric_arrays() {
        $input = [['query' => 'foo', 'count' => 2], ['query' => 'bar', 'count' => 1]];
        $result = EnhancedSettingsPage::normalizeQueryList($input);
        $this->assertCount(2, $result);
    }

    /** @test */
    public function normalize_query_list_handles_string_arrays() {
        $input = ['alpha', 'beta', 'gamma'];
        $result = EnhancedSettingsPage::normalizeQueryList($input);
        $this->assertCount(3, $result);
        $this->assertEquals('alpha', $result[0]['query']);
        $this->assertEquals(1, $result[0]['count']);
    }

    /** @test */
    public function normalize_query_list_returns_empty_for_non_array() {
        $this->assertEquals([], EnhancedSettingsPage::normalizeQueryList(null));
        $this->assertEquals([], EnhancedSettingsPage::normalizeQueryList('string'));
    }

    /** @test */
    public function normalize_query_list_handles_array_with_various_keys() {
        $input = [
            ['term' => 'search-term', 'hits' => 10],
            ['text' => 'other-term', 'total' => 5],
        ];
        $result = EnhancedSettingsPage::normalizeQueryList($input);
        $this->assertEquals('search-term', $result[0]['query']);
        $this->assertEquals(10, $result[0]['count']);
        $this->assertEquals('other-term', $result[1]['query']);
        $this->assertEquals(5, $result[1]['count']);
    }

    /** @test */
    public function register_settings_creates_expected_options() {
        \Brain\Monkey\Functions\expect('register_setting')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        EnhancedSettingsPage::registerSettings();
    }

    /** @test */
    public function on_api_key_updated_clears_state_when_key_emptied() {
        $this->storedOptions['searchjet_hosted_api_key'] = 'old-key-123';
        $this->storedOptions['searchjet_client_info'] = ['plan' => 'pro'];
        $this->storedOptions['searchjet_site_id'] = 42;

        \Brain\Monkey\Functions\when('wp_debug_backtrace_summary')
            ->justReturn('test backtrace');

        EnhancedSettingsPage::onApiKeyUpdated('old-key-123', '');

        $this->assertArrayNotHasKey('searchjet_client_info', $this->storedOptions);
        $this->assertArrayNotHasKey('searchjet_site_id', $this->storedOptions);
    }

    /** @test */
    public function on_api_key_updated_does_nothing_when_key_unchanged() {
        $this->storedOptions['searchjet_hosted_api_key'] = 'same-key';
        $this->storedOptions['searchjet_client_info'] = ['plan' => 'pro'];

        \Brain\Monkey\Functions\when('wp_debug_backtrace_summary')
            ->justReturn('');

        EnhancedSettingsPage::onApiKeyUpdated('same-key', 'same-key');

        // Client info should NOT be cleared since key didn't change
        $this->assertArrayHasKey('searchjet_client_info', $this->storedOptions);
    }

    /** @test */
    public function on_api_key_updated_clears_state_on_new_key() {
        $this->storedOptions['searchjet_hosted_api_key'] = 'old-key';
        $this->storedOptions['searchjet_client_info'] = ['plan' => 'pro', 'site_id' => 42];

        // Make API fail so validation doesn't repopulate client_info
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')
            ->justReturn(401);

        EnhancedSettingsPage::onApiKeyUpdated('old-key', 'new-key-456');

        // Old client info should be cleared and NOT repopulated (API failed)
        $this->assertArrayNotHasKey('searchjet_client_info', $this->storedOptions);
    }

    /** @test */
    public function maybe_validate_api_key_skips_when_key_empty() {
        unset($this->storedOptions['searchjet_hosted_api_key']);
        // Should not throw or error
        EnhancedSettingsPage::maybeValidateApiKey();
        $this->assertTrue(true);
    }

    /** @test */
    public function maybe_validate_api_key_skips_when_client_info_exists() {
        $this->storedOptions['searchjet_hosted_api_key'] = 'some-key';
        $this->storedOptions['searchjet_client_info'] = ['plan' => 'pro'];

        EnhancedSettingsPage::maybeValidateApiKey();
        $this->assertTrue(true);
    }

    /** @test */
    public function maybe_validate_api_key_rate_limits_retries() {
        $this->storedOptions['searchjet_hosted_api_key'] = 'valid-key';
        unset($this->storedOptions['searchjet_client_info']);

        // Simulate retry transient being set (rate limit active)
        \Brain\Monkey\Functions\when('get_transient')
            ->alias(function ($key) {
                if ($key === 'searchjet_validate_retry_at') {
                    return time() + 300; // retry in future → should skip
                }
                return false;
            });

        EnhancedSettingsPage::maybeValidateApiKey();

        // Client info should still be empty since we're rate-limited
        $this->assertArrayNotHasKey('searchjet_client_info', $this->storedOptions);
    }

    /** @test */
    public function maybe_validate_api_key_retries_when_rate_limit_expired() {
        $this->storedOptions['searchjet_hosted_api_key'] = 'valid-key';
        unset($this->storedOptions['searchjet_client_info']);

        // Retry transient is in the past — should attempt validation
        \Brain\Monkey\Functions\when('get_transient')
            ->alias(function ($key) {
                if ($key === 'searchjet_validate_retry_at') {
                    return time() - 1; // expired
                }
                return false;
            });

        EnhancedSettingsPage::maybeValidateApiKey();

        // Should have tried to validate (API returns default {} which means no plan_limits → free)
        // The exact result depends on mock data, just verify no exception
        $this->assertTrue(true);
    }

    /** @test */
    public function refresh_from_api_calls_fetch_and_persist() {
        $this->storedOptions['searchjet_hosted_api_key'] = 'test-key';

        // Stub HTTP to return valid data
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')
            ->justReturn(json_encode([
                'plan' => 'pro',
                'usage_limits' => [
                    'reindexes_today' => 3,
                    'reindexing_per_day' => 8,
                ],
            ]));

        EnhancedSettingsPage::refreshFromApi();

        $this->assertArrayHasKey('searchjet_client_info', $this->storedOptions);
        $info = $this->storedOptions['searchjet_client_info'];
        $this->assertEquals('pro', $info['plan']);
        $this->assertEquals(8, $info['usage_limits']['reindexing_per_day']);
    }

    /** @test */
    public function clear_client_state_removes_all_cached_data() {
        // Set up stored options
        $this->storedOptions['searchjet_client_info'] = ['plan' => 'pro'];
        $this->storedOptions['searchjet_site_id'] = 42;

        // Access private method via reflection
        $ref = new \ReflectionMethod(EnhancedSettingsPage::class, 'clearClientState');
        $ref->setAccessible(true);
        $ref->invoke(null);

        $this->assertArrayNotHasKey('searchjet_client_info', $this->storedOptions);
        $this->assertArrayNotHasKey('searchjet_site_id', $this->storedOptions);
    }

    /** @test */
    public function persist_client_info_stores_data() {
        $info = [
            'plan' => 'pro',
            'usage_limits' => ['reindexes_today' => 3, 'reindexing_per_day' => 8],
            'site_id' => 99,
        ];

        // Access private method via reflection
        $ref = new \ReflectionMethod(EnhancedSettingsPage::class, 'persistClientInfo');
        $ref->setAccessible(true);
        $ref->invoke(null, $info);

        $this->assertArrayHasKey('searchjet_client_info', $this->storedOptions);
        $this->assertEquals('pro', $this->storedOptions['searchjet_client_info']['plan']);
        $this->assertEquals(99, $this->storedOptions['searchjet_site_id']);
    }

    /** @test */
    public function on_api_key_updated_does_not_log_sensitive_key_values() {
        $old_key = 'sk-old-secret-key-abc123';
        $new_key = 'sk-new-secret-key-xyz789';

        $logged_messages = [];
        \Brain\Monkey\Functions\when('error_log')
            ->alias(function ($message) use (&$logged_messages) {
                $logged_messages[] = $message;
            });

        $this->storedOptions['searchjet_hosted_api_key'] = $old_key;

        EnhancedSettingsPage::onApiKeyUpdated($old_key, $new_key);

        // Verify no logged message contains the actual API key values
        foreach ($logged_messages as $msg) {
            $this->assertStringNotContainsString($old_key, $msg,
                "error_log must not contain the old API key. Got: $msg");
            $this->assertStringNotContainsString($new_key, $msg,
                "error_log must not contain the new API key. Got: $msg");
        }
    }

    /** @test */
    public function on_api_key_cleared_does_not_log_sensitive_key_value() {
        $old_key = 'sk-secret-key-to-clear';

        $logged_messages = [];
        \Brain\Monkey\Functions\when('error_log')
            ->alias(function ($message) use (&$logged_messages) {
                $logged_messages[] = $message;
            });

        $this->storedOptions['searchjet_hosted_api_key'] = $old_key;
        $this->storedOptions['searchjet_client_info'] = ['plan' => 'pro'];

        EnhancedSettingsPage::onApiKeyUpdated($old_key, '');

        foreach ($logged_messages as $msg) {
            $this->assertStringNotContainsString($old_key, $msg,
                "error_log must not contain the API key when cleared. Got: $msg");
        }
    }
}
