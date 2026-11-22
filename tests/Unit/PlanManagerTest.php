<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Services\PlanManager;
use SearchJet\Tests\TestCase;

class PlanManagerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->storedOptions = [
            'searchjet_client_info' => [
                'plan' => 'pro',
                'plan_limits' => [
                    'search_queries_per_month' => 50000,
                    'max_indexed_pages' => 5000,
                    'ai_answers_per_month' => 500,
                    'semantic_search' => true,
                    'priority_support' => true,
                    'multisite_support' => 3,
                    'analytics_retention_days' => 30,
                    'reindexing_per_day' => 10,
                ],
            ],
        ];
    }

    /** @test */
    public function get_current_plan_returns_plan_from_client_info() {
        $plan = PlanManager::get_current_plan();
        $this->assertEquals('pro', $plan);
    }

    /** @test */
    public function get_current_plan_returns_free_when_no_client_info() {
        unset($this->storedOptions['searchjet_client_info']);
        $this->assertEquals('free', PlanManager::get_current_plan());
    }

    /** @test */
    public function get_plan_limits_returns_limits_from_client_info() {
        $limits = PlanManager::get_plan_limits();
        $this->assertEquals(50000, $limits['search_queries_per_month']);
        $this->assertEquals(5000, $limits['max_indexed_pages']);
    }

    /** @test */
    public function get_plan_limits_returns_free_defaults_when_no_client_info() {
        unset($this->storedOptions['searchjet_client_info']);
        $limits = PlanManager::get_plan_limits();
        $this->assertArrayHasKey('search_queries_per_month', $limits);
        $this->assertArrayHasKey('max_indexed_pages', $limits);
    }

    /** @test */
    public function has_feature_returns_true_for_available_feature() {
        $this->assertTrue(PlanManager::has_feature('semantic_search'));
        $this->assertTrue(PlanManager::has_feature('priority_support'));
    }

    /** @test */
    public function has_feature_returns_true_when_ai_answers_limit_positive() {
        $this->assertTrue(PlanManager::has_feature('ai_answers'));
    }

    /** @test */
    public function has_feature_returns_false_when_feature_not_in_limits() {
        $result = PlanManager::has_feature('nonexistent_feature');
        $this->assertFalse($result);
    }

    /** @test */
    public function get_limit_returns_correct_value() {
        $this->assertEquals(50000, PlanManager::get_limit('search_queries_per_month'));
        $this->assertEquals(5000, PlanManager::get_limit('max_indexed_pages'));
    }

    /** @test */
    public function get_limit_returns_zero_for_missing_key() {
        $this->assertEquals(0, PlanManager::get_limit('nonexistent'));
    }

    /** @test */
    public function get_usage_returns_zero_for_unknown_feature() {
        $this->assertEquals(0, PlanManager::get_usage('unknown'));
    }

    /** @test */
    public function quota_exceeded_returns_false_when_unlimited() {
        $this->storedOptions['searchjet_client_info']['plan_limits']['max_indexed_pages'] = -1;
        $this->assertFalse(PlanManager::quota_exceeded('indexed_pages'));
    }

    /** @test */
    public function quota_exceeded_returns_true_when_limit_is_zero() {
        $this->storedOptions['searchjet_client_info']['plan_limits']['max_indexed_pages'] = 0;
        $this->assertTrue(PlanManager::quota_exceeded('indexed_pages'));
    }

    /** @test */
    public function get_remaining_returns_minus_one_for_unlimited() {
        $this->storedOptions['searchjet_client_info']['plan_limits']['max_indexed_pages'] = -1;
        $this->assertEquals(-1, PlanManager::get_remaining('indexed_pages'));
    }

    /** @test */
    public function get_usage_percentage_returns_minus_one_for_unlimited() {
        $this->storedOptions['searchjet_client_info']['plan_limits']['max_indexed_pages'] = -1;
        $this->assertEquals(-1, PlanManager::get_usage_percentage('indexed_pages'));
    }

    /** @test */
    public function get_usage_percentage_returns_minus_one_when_limit_zero() {
        $this->storedOptions['searchjet_client_info']['plan_limits']['max_indexed_pages'] = 0;
        $this->assertEquals(-1, PlanManager::get_usage_percentage('indexed_pages'));
    }

    /** @test */
    public function can_upgrade_to_returns_true_for_higher_plan() {
        $this->assertTrue(PlanManager::can_upgrade_to('growth'));
        $this->assertTrue(PlanManager::can_upgrade_to('enterprise'));
    }

    /** @test */
    public function can_upgrade_to_returns_false_for_lower_or_same_plan() {
        $this->assertFalse(PlanManager::can_upgrade_to('free'));
        $this->assertFalse(PlanManager::can_upgrade_to('pro'));
    }

    /** @test */
    public function can_upgrade_to_returns_false_for_unknown_plan() {
        $this->assertFalse(PlanManager::can_upgrade_to('nonexistent'));
    }

    /** @test */
    public function get_required_plan_returns_null_when_feature_available() {
        $this->assertNull(PlanManager::get_required_plan('semantic_search'));
    }

    /** @test */
    public function get_upgrade_url_contains_pricing_path() {
        $url = PlanManager::get_upgrade_url();
        $this->assertStringContainsString('pricing', $url);
        $this->assertStringContainsString('app.searchjetengine.com', $url);
    }

    /** @test */
    public function get_all_features_returns_expected_structure() {
        $features = PlanManager::get_all_features();
        $this->assertArrayHasKey('search_queries', $features);
        $this->assertArrayHasKey('indexed_pages', $features);
        $this->assertArrayHasKey('ai_answers', $features);
        $this->assertArrayHasKey('semantic_search', $features);
        $this->assertArrayHasKey('priority_support', $features);
    }

    /** @test */
    public function get_all_features_returns_search_queries_limit() {
        $features = PlanManager::get_all_features();
        $this->assertEquals(50000, $features['search_queries']['limit']);
        $this->assertTrue($features['search_queries']['available']);
    }

    /** @test */
    public function get_all_features_ai_answers_has_quota_check() {
        $features = PlanManager::get_all_features();
        $this->assertArrayHasKey('quota_exceeded', $features['ai_answers']);
        $this->assertFalse($features['ai_answers']['quota_exceeded']);
    }

    /** @test */
    public function get_plan_limits_falls_back_to_usage_limits_when_plan_limits_missing() {
        $this->storedOptions['searchjet_client_info'] = [
            'plan' => 'pro',
            'usage_limits' => [
                'search_queries_per_month' => 50000,
                'max_indexed_pages' => 25000,
                'reindexing_per_day' => 8,
                'ai_answers_per_month' => 500,
            ],
        ];
        $limits = PlanManager::get_plan_limits();
        $this->assertEquals(50000, $limits['search_queries_per_month']);
        $this->assertEquals(25000, $limits['max_indexed_pages']);
        $this->assertEquals(8, $limits['reindexing_per_day']);
    }

    /** @test */
    public function get_plan_limits_uses_plan_limits_when_both_exist() {
        // plan_limits should take priority over usage_limits
        $this->storedOptions['searchjet_client_info'] = [
            'plan' => 'pro',
            'plan_limits' => [
                'search_queries_per_month' => 50000,
                'max_indexed_pages' => 5000,
                'reindexing_per_day' => 10,
            ],
            'usage_limits' => [
                'search_queries_per_month' => 10000,
                'max_indexed_pages' => 25000,
                'reindexing_per_day' => 8,
            ],
        ];
        $limits = PlanManager::get_plan_limits();
        $this->assertEquals(50000, $limits['search_queries_per_month'], 'plan_limits should take priority');
        $this->assertEquals(5000, $limits['max_indexed_pages'], 'plan_limits should take priority');
        $this->assertEquals(10, $limits['reindexing_per_day'], 'plan_limits should take priority');
    }

    /** @test */
    public function get_limit_reads_from_usage_limits_when_plan_limits_missing() {
        $this->storedOptions['searchjet_client_info'] = [
            'plan' => 'pro',
            'usage_limits' => [
                'search_queries_per_month' => 50000,
                'max_indexed_pages' => 25000,
                'reindexing_per_day' => 8,
            ],
        ];
        $this->assertEquals(50000, PlanManager::get_limit('search_queries_per_month'));
        $this->assertEquals(25000, PlanManager::get_limit('max_indexed_pages'));
        $this->assertEquals(8, PlanManager::get_limit('reindexing_per_day'));
    }

    /** @test */
    public function clear_cache_deletes_option() {
        $this->storedOptions['searchjet_client_info'] = ['plan' => 'free'];
        $this->storedOptions['searchjet_hosted_api_key'] = 'test-key-123';

        PlanManager::clear_cache();
        $this->assertArrayNotHasKey('searchjet_client_info', $this->storedOptions);
    }

    /** @test */
    public function get_plan_display_name_returns_friendly_name() {
        $this->assertEquals('Free', PlanManager::get_plan_display_name('free'));
        $this->assertEquals('Pro', PlanManager::get_plan_display_name('pro'));
        $this->assertEquals('Growth', PlanManager::get_plan_display_name('growth'));
        $this->assertEquals('Enterprise', PlanManager::get_plan_display_name('enterprise'));
    }

    /** @test */
    public function get_plan_display_name_returns_ucfirst_for_unknown() {
        $this->assertEquals('Custom', PlanManager::get_plan_display_name('custom'));
    }

    /** @test */
    public function free_plan_defaults_have_expected_values() {
        unset($this->storedOptions['searchjet_client_info']);
        $limits = PlanManager::get_plan_limits();
        $this->assertEquals(10000, $limits['search_queries_per_month']);
        $this->assertEquals(1000, $limits['max_indexed_pages']);
        $this->assertEquals(1, $limits['reindexing_per_day']);
        $this->assertEquals(7, $limits['analytics_retention_days']);
    }

    /** @test */
    public function free_plan_defaults_ai_answers_per_month() {
        unset($this->storedOptions['searchjet_client_info']);
        $limits = PlanManager::get_plan_limits();
        $this->assertEquals(50, $limits['ai_answers_per_month']);
    }

    /** @test */
    public function get_reindexes_today_ignores_stale_backend_counter() {
        $this->storedTransients['searchjet_usage_limits'] = [
            'reindexes_today' => 1,
            'last_reindexed' => '2000-01-01T00:00:00+00:00',
        ];
        $this->assertEquals(0, PlanManager::get_reindexes_today());
    }

    /** @test */
    public function get_reindexes_today_returns_api_count_when_last_reindexed_today() {
        $today = gmdate('Y-m-d');
        $this->storedTransients['searchjet_usage_limits'] = [
            'reindexes_today' => 1,
            'last_reindexed' => $today . 'T00:00:00+00:00',
        ];
        $this->assertEquals(1, PlanManager::get_reindexes_today());
    }

    /** @test */
    public function record_reindex_today_increments_local_counter() {
        PlanManager::record_reindex_today();
        PlanManager::record_reindex_today();
        $this->assertEquals(2, PlanManager::get_reindexes_today());
    }

    /** @test */
    public function get_reindexes_today_resets_local_counter_on_new_day() {
        $this->storedOptions['searchjet_reindex_daily'] = [
            'date' => '2000-01-01',
            'count' => 5,
        ];
        $this->assertEquals(0, PlanManager::get_reindexes_today());
    }
}
