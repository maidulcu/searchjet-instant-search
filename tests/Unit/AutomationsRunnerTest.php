<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Automations\Runner;
use SearchJet\Tests\TestCase;

class AutomationsRunnerTest extends TestCase {

    private array $scheduledEvents = [];

    protected function setUp(): void {
        parent::setUp();
        $this->scheduledEvents = [];

        \Brain\Monkey\Functions\expect('wp_next_scheduled')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($hook) {
                return isset($this->scheduledEvents[$hook]) ? $this->scheduledEvents[$hook] : false;
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

        \Brain\Monkey\Functions\expect('current_time')
            ->zeroOrMoreTimes()
            ->andReturn(1000000);

        \Brain\Monkey\Functions\expect('sanitize_key')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($key) { return $key; });

        \Brain\Monkey\Functions\expect('add_filter')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('add_action')
            ->zeroOrMoreTimes()
            ->andReturn(true);
    }

    /** @test */
    public function add_daily_schedule_adds_missing_schedule() {
        $schedules = Runner::addDailySchedule([]);
        $this->assertArrayHasKey('searchjet_daily', $schedules);
        $this->assertEquals(86400, $schedules['searchjet_daily']['interval']);
    }

    /** @test */
    public function add_daily_schedule_does_not_overwrite_existing() {
        $schedules = Runner::addDailySchedule(['searchjet_daily' => ['interval' => 12345]]);
        $this->assertEquals(12345, $schedules['searchjet_daily']['interval']);
    }

    /** @test */
    public function ensure_scheduled_clears_when_disabled_by_constant() {
        \Brain\Monkey\Functions\expect('defined')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($name) {
                return $name === 'SEARCHJET_DISABLE_AUTOMATIONS';
            });
        \Brain\Monkey\Functions\expect('constant')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($name) {
                return $name === 'SEARCHJET_DISABLE_AUTOMATIONS' ? true : null;
            });

        $this->scheduledEvents['searchjet_daily_automations'] = 12345;
        Runner::ensureScheduled();
        $this->assertArrayNotHasKey('searchjet_daily_automations', $this->scheduledEvents);
    }

    /** @test */
    public function ensure_scheduled_clears_when_automations_disabled() {
        $this->storedOptions['searchjet_automations_enabled'] = 0;
        $this->scheduledEvents['searchjet_daily_automations'] = 12345;

        Runner::ensureScheduled();
        $this->assertArrayNotHasKey('searchjet_daily_automations', $this->scheduledEvents);
    }

    /** @test */
    public function ensure_scheduled_schedules_when_enabled() {
        $this->storedOptions['searchjet_automations_enabled'] = 1;

        \Brain\Monkey\Functions\expect('defined')
            ->zeroOrMoreTimes()
            ->andReturn(false);

        Runner::ensureScheduled();
        $this->assertArrayHasKey('searchjet_daily_automations', $this->scheduledEvents);
    }

    /** @test */
    public function run_daily_does_nothing_when_disabled() {
        $this->storedOptions['searchjet_automations_enabled'] = 0;

        \Brain\Monkey\Functions\expect('class_exists')
            ->zeroOrMoreTimes()
            ->andReturn(false);

        Runner::runDaily();
        $this->assertTrue(true);
    }
}
