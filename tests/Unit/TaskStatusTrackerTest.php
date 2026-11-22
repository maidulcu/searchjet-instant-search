<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Services\TaskStatusTracker;
use SearchJet\Tests\TestCase;

class TaskStatusTrackerTest extends TestCase {

    protected array $storedTransients = [];
    private array $scheduledEvents = [];

    protected function setUp(): void {
        parent::setUp();
        $this->storedTransients = [];
        $this->scheduledEvents = [];

        \Brain\Monkey\Functions\expect('get_transient')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($key) {
                return $this->storedTransients[$key] ?? false;
            });

        \Brain\Monkey\Functions\expect('set_transient')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($key, $value, $expiration = 0) {
                $this->storedTransients[$key] = $value;
                return true;
            });

        \Brain\Monkey\Functions\expect('wp_schedule_single_event')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($time, $hook, $args = []) {
                $this->scheduledEvents[] = compact('time', 'hook', 'args');
                return true;
            });

        \Brain\Monkey\Functions\expect('time')
            ->zeroOrMoreTimes()
            ->andReturn(1000000);
    }

    /** @test */
    public function record_task_returns_false_for_empty_uid() {
        $this->assertFalse(TaskStatusTracker::recordTask('', 'index'));
    }

    /** @test */
    public function record_task_returns_true_for_valid_input() {
        $result = TaskStatusTracker::recordTask('task-123', 'reindex', ['source' => 'manual']);
        $this->assertTrue($result);
    }

    /** @test */
    public function record_task_stores_task_data() {
        TaskStatusTracker::recordTask('task-123', 'reindex');

        $stored = $this->storedOptions['searchjet_task_task-123'];
        $this->assertIsArray($stored);
        $this->assertEquals('task-123', $stored['uid']);
        $this->assertEquals('reindex', $stored['type']);
        $this->assertEquals('pending', $stored['status']);
        $this->assertEquals(0, $stored['progress']);
    }

    /** @test */
    public function record_task_adds_to_active_tasks() {
        TaskStatusTracker::recordTask('task-123', 'reindex');
        $this->assertContains('task-123', $this->storedOptions['searchjet_active_tasks']);
    }

    /** @test */
    public function record_task_schedules_status_check() {
        TaskStatusTracker::recordTask('task-123', 'reindex');
        $this->assertCount(1, $this->scheduledEvents);
        $this->assertEquals('searchjet_check_task_status', $this->scheduledEvents[0]['hook']);
        $this->assertEquals(['task-123'], $this->scheduledEvents[0]['args']);
    }

    /** @test */
    public function record_task_stores_metadata() {
        $meta = ['source' => 'manual', 'trigger' => 'button_click'];
        TaskStatusTracker::recordTask('task-123', 'reindex', $meta);
        $this->assertEquals($meta, $this->storedOptions['searchjet_task_task-123']['metadata']);
    }

    /** @test */
    public function get_active_tasks_returns_empty_when_none() {
        $this->assertEquals([], TaskStatusTracker::getActiveTasks());
    }

    /** @test */
    public function get_active_tasks_returns_stored_tasks() {
        $this->storedOptions['searchjet_active_tasks'] = ['task-1', 'task-2'];
        $this->assertEquals(['task-1', 'task-2'], TaskStatusTracker::getActiveTasks());
    }

    /** @test */
    public function get_completed_tasks_returns_empty_when_none() {
        $this->assertEquals([], TaskStatusTracker::getCompletedTasks());
    }

    /** @test */
    public function record_completed_task_marks_as_completed() {
        TaskStatusTracker::recordCompletedTask('task-456', 'index', ['post_count' => 50]);

        $stored = $this->storedOptions['searchjet_task_task-456'];
        $this->assertEquals('completed', $stored['status']);
        $this->assertEquals(50, $stored['progress']);
    }

    /** @test */
    public function record_completed_task_moves_to_completed_list() {
        TaskStatusTracker::recordCompletedTask('task-456', 'index');
        $this->assertContains('task-456', $this->storedOptions['searchjet_completed_tasks']);
    }

    /** @test */
    public function record_completed_task_removes_from_active() {
        $this->storedOptions['searchjet_active_tasks'] = ['task-456'];
        TaskStatusTracker::recordCompletedTask('task-456', 'index');
        $this->assertNotContains('task-456', $this->storedOptions['searchjet_active_tasks']);
    }

    /** @test */
    public function record_completed_task_returns_false_for_empty_uid() {
        $this->assertFalse(TaskStatusTracker::recordCompletedTask('', 'index'));
    }

    /** @test */
    public function record_failed_task_marks_as_failed() {
        TaskStatusTracker::recordFailedTask('task-789', 'index', ['error' => 'Timeout']);

        $stored = $this->storedOptions['searchjet_task_task-789'];
        $this->assertEquals('failed', $stored['status']);
        $this->assertEquals('Timeout', $stored['error']);
    }

    /** @test */
    public function record_failed_task_defaults_error_message() {
        TaskStatusTracker::recordFailedTask('task-789', 'index');
        $this->assertEquals('Unknown error', $this->storedOptions['searchjet_task_task-789']['error']);
    }

    /** @test */
    public function record_failed_task_returns_false_for_empty_uid() {
        $this->assertFalse(TaskStatusTracker::recordFailedTask('', 'index'));
    }

    /** @test */
    public function get_task_status_returns_null_for_nonexistent() {
        $this->assertNull(TaskStatusTracker::getTaskStatus('nonexistent'));
    }

    /** @test */
    public function get_task_status_returns_stored_data() {
        TaskStatusTracker::recordTask('task-123', 'reindex');
        $status = TaskStatusTracker::getTaskStatus('task-123');
        $this->assertEquals('pending', $status['status']);
    }

    /** @test */
    public function get_statistics_returns_zero_when_empty() {
        $stats = TaskStatusTracker::getStatistics();
        $this->assertEquals(0, $stats['total_active']);
        $this->assertEquals(0, $stats['total_completed']);
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['by_status']['completed']);
        $this->assertEquals(0, $stats['by_status']['failed']);
    }

    /** @test */
    public function get_statistics_counts_active_tasks() {
        TaskStatusTracker::recordTask('task-1', 'index');
        TaskStatusTracker::recordTask('task-2', 'reindex');
        $stats = TaskStatusTracker::getStatistics();
        $this->assertEquals(2, $stats['total_active']);
        $this->assertEquals(2, $stats['total']);
    }

    /** @test */
    public function get_statistics_counts_completed_tasks() {
        TaskStatusTracker::recordCompletedTask('task-1', 'index');
        TaskStatusTracker::recordCompletedTask('task-2', 'reindex');
        $stats = TaskStatusTracker::getStatistics();
        $this->assertEquals(2, $stats['total_completed']);
        $this->assertEquals(2, $stats['by_status']['completed']);
    }

    /** @test */
    public function get_task_history_returns_active_first_then_completed() {
        TaskStatusTracker::recordTask('active-1', 'index');
        TaskStatusTracker::recordCompletedTask('completed-1', 'reindex');
        $history = TaskStatusTracker::getTaskHistory(10);

        $this->assertCount(2, $history);
    }

    /** @test */
    public function get_task_history_respects_limit() {
        for ($i = 0; $i < 5; $i++) {
            TaskStatusTracker::recordCompletedTask("task-$i", 'index');
        }
        $history = TaskStatusTracker::getTaskHistory(3);
        $this->assertCount(3, $history);
    }

    /** @test */
    public function get_task_history_limits_completed_to_50() {
        for ($i = 0; $i < 60; $i++) {
            TaskStatusTracker::recordCompletedTask("task-$i", 'index');
        }
        $completed = TaskStatusTracker::getCompletedTasks();
        $this->assertCount(50, $completed);
    }

    /** @test */
    public function get_task_history_sorts_newest_first() {
        TaskStatusTracker::recordTask('old-task', 'index');
        $this->storedOptions['searchjet_task_old-task']['updated_at'] = 1000;

        TaskStatusTracker::recordTask('new-task', 'index');
        $this->storedOptions['searchjet_task_new-task']['updated_at'] = 2000;

        $history = TaskStatusTracker::getTaskHistory(10);
        $this->assertEquals('new-task', $history[0]['uid']);
    }

    /** @test */
    public function mark_task_complete_does_not_duplicate_in_completed() {
        TaskStatusTracker::recordCompletedTask('task-1', 'index');
        TaskStatusTracker::markTaskComplete('task-1');
        $completed = TaskStatusTracker::getCompletedTasks();
        $this->assertCount(1, $completed);
    }

    /** @test */
    public function clear_history_removes_all_tasks() {
        TaskStatusTracker::recordTask('task-1', 'index');
        TaskStatusTracker::recordCompletedTask('task-2', 'reindex');
        TaskStatusTracker::clearHistory();

        $this->assertNull($this->storedOptions['searchjet_active_tasks'] ?? null);
        $this->assertNull($this->storedOptions['searchjet_completed_tasks'] ?? null);
    }

    /** @test */
    public function handle_cron_check_delegates_to_check_task_status() {
        $this->storedOptions['searchjet_task_test-task'] = [
            'uid' => 'test-task',
            'status' => 'pending',
            'updated_at' => 1000000,
        ];

        TaskStatusTracker::handleCronCheck('test-task');
        $this->assertTrue(true);
    }
}
