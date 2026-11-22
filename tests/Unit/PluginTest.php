<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Plugin;
use SearchJet\Tests\TestCase;

class PluginTest extends TestCase {

    private array $scheduledEvents = [];

    protected function setUp(): void {
        parent::setUp();

        $this->scheduledEvents = [];

        \Brain\Monkey\Functions\expect('wp_next_scheduled')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($hook) {
                return $this->scheduledEvents[$hook] ?? false;
            });

        \Brain\Monkey\Functions\expect('wp_schedule_event')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($ts, $freq, $hook) {
                $this->scheduledEvents[$hook] = $ts;
                return true;
            });

        \Brain\Monkey\Functions\expect('wp_unschedule_event')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($ts, $hook) {
                unset($this->scheduledEvents[$hook]);
                return true;
            });

        \Brain\Monkey\Functions\expect('add_action')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('add_filter')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('class_exists')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('wp_create_nonce')
            ->zeroOrMoreTimes()
            ->andReturn('test_nonce');

        \Brain\Monkey\Functions\expect('wp_generate_uuid4')
            ->zeroOrMoreTimes()
            ->andReturn('uuid-12345');

        \Brain\Monkey\Functions\expect('add_shortcode')
            ->zeroOrMoreTimes()
            ->andReturn(true);
    }

    /** @test */
    public function activate_sets_default_api_key() {
        Plugin::activate();
        $this->assertEquals('', $this->storedOptions['searchjet_api_key']);
    }

    /** @test */
    public function activate_sets_default_project_id() {
        Plugin::activate();
        $this->assertEquals('', $this->storedOptions['searchjet_project_id']);
    }

    /** @test */
    public function activate_enables_automations_by_default() {
        Plugin::activate();
        $this->assertEquals(1, $this->storedOptions['searchjet_automations_enabled']);
    }

    /** @test */
    public function activate_sets_zero_result_threshold() {
        Plugin::activate();
        $this->assertEquals(5, $this->storedOptions['searchjet_zr_threshold']);
    }

    /** @test */
    public function activate_sets_zero_result_window() {
        Plugin::activate();
        $this->assertEquals(600, $this->storedOptions['searchjet_zr_window']);
    }

    /** @test */
    public function activate_enables_event_nonce_by_default() {
        Plugin::activate();
        $this->assertEquals(1, $this->storedOptions['searchjet_require_event_nonce']);
    }

    /** @test */
    public function activate_sets_ui_options_defaults() {
        Plugin::activate();
        $this->assertArrayHasKey('searchjet_ui_options', $this->storedOptions);
        $options = $this->storedOptions['searchjet_ui_options'];
        $this->assertEquals(1, $options['show_thumbnail']);
        $this->assertEquals(1, $options['show_price']);
        $this->assertEquals(1, $options['show_rating']);
        $this->assertEquals(1, $options['show_stock']);
        $this->assertEquals(1, $options['show_category']);
        $this->assertEquals(1, $options['show_excerpts']);
    }

    /** @test */
    public function activate_does_not_overwrite_existing_values() {
        $this->storedOptions['searchjet_api_key'] = 'existing-key';
        $this->storedOptions['searchjet_zr_threshold'] = 10;

        Plugin::activate();
        $this->assertEquals('existing-key', $this->storedOptions['searchjet_api_key']);
    }

    /** @test */
    public function deactivate_unschedules_log_batch() {
        $this->scheduledEvents['searchjet_send_log_batch'] = 12345;

        Plugin::deactivate();
        $this->assertArrayNotHasKey('searchjet_send_log_batch', $this->scheduledEvents);
    }

    /** @test */
    public function deactivate_does_not_unschedule_if_not_scheduled() {
        Plugin::deactivate();
        $this->assertArrayNotHasKey('searchjet_send_log_batch', $this->scheduledEvents);
    }

    /** @test */
    public function init_registers_expected_hooks() {
        \Brain\Monkey\Functions\expect('wp_enqueue_scripts')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('wp_localize_script')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        Plugin::init();
        $this->assertTrue(true);
    }

    /** @test */
    public function send_log_batch_does_nothing_when_empty() {
        $this->storedOptions['searchjet_log_backlog'] = [];
        Plugin::sendLogBatch();
        $this->assertTrue(true);
    }

    /** @test */
    public function send_log_batch_sends_and_clears_backlog() {
        $this->storedOptions['searchjet_log_backlog'] = [
            ['query' => 'test', 'results' => 5, 'ts' => 1000000],
        ];

        \Brain\Monkey\Functions\expect('wp_json_encode')
            ->zeroOrMoreTimes()
            ->andReturn('{"logs":[]}');

        \Brain\Monkey\Functions\expect('wp_remote_post')
            ->zeroOrMoreTimes()
            ->andReturn(['response' => ['code' => 200]]);

        \Brain\Monkey\Functions\expect('is_wp_error')
            ->zeroOrMoreTimes()
            ->andReturn(false);

        Plugin::sendLogBatch();
        $this->assertArrayNotHasKey('searchjet_log_backlog', $this->storedOptions);
    }

    /** @test */
    public function send_click_batch_does_nothing_when_empty() {
        $this->storedOptions['searchjet_click_backlog'] = [];
        Plugin::sendClickBatch();
        $this->assertTrue(true);
    }

    /** @test */
    public function searchjet_plugin_version_constant_is_defined() {
        $this->assertTrue(defined('SEARCHJET_PLUGIN_VERSION'));
        $this->assertEquals('2.0.4', SEARCHJET_PLUGIN_VERSION);
    }

    /** @test */
    public function searchjet_version_constant_matches_plugin_version() {
        $this->assertTrue(defined('SEARCHJET_VERSION'));
        $this->assertEquals(SEARCHJET_VERSION, SEARCHJET_PLUGIN_VERSION);
    }
}
