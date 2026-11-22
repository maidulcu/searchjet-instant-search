<?php
/**
 * Task Status Tracker Service
 *
 * Tracks indexing tasks and provides status updates to the admin interface.
 * Stores task metadata in WordPress options for polling and callback handling.
 *
 * @package SearchJet
 */

namespace SearchJet\Services;

use SearchJet\API\SearchJetRemoteClient;

class TaskStatusTracker {

    const OPTION_PREFIX = 'searchjet_task_';
    const COMPLETED_TASKS_OPTION = 'searchjet_completed_tasks';
    const ACTIVE_TASKS_OPTION = 'searchjet_active_tasks';

    /**
     * Initialize the service - register cron hook.
     */
    public static function init() {
        add_action('searchjet_check_task_status', [self::class, 'handleCronCheck']);
    }

    /**
     * Cron handler - check task status when scheduled.
     *
     * @param string $task_uid Task UID to check
     */
    public static function handleCronCheck($task_uid) {
        self::checkTaskStatus($task_uid);
    }

    /**
     * Record a new task being initiated.
     *
     * @param string $task_uid Task UID from the API
     * @param string $task_type Type of task (index, reindex, settings, etc)
     * @param array $metadata Additional metadata
     * @return bool Success
     */
    public static function recordTask($task_uid, $task_type, $metadata = []) {
        if (empty($task_uid)) {
            return false;
        }

        $task_data = [
            'uid' => $task_uid,
            'type' => $task_type,
            'status' => 'pending',
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => $metadata,
            'progress' => 0,
        ];

        // Store task record
        update_option(self::OPTION_PREFIX . $task_uid, $task_data, false);

        // Add to active tasks list
        $active = self::getActiveTasks();
        if (!in_array($task_uid, $active, true)) {
            $active[] = $task_uid;
            update_option(self::ACTIVE_TASKS_OPTION, $active, false);
        }

        // Schedule status check
        wp_schedule_single_event(time() + 5, 'searchjet_check_task_status', [$task_uid]);

        return true;
    }

    /**
     * Record a task that completed synchronously (no async status check needed).
     *
     * @param string $task_uid Task UID
     * @param string $task_type Type of task
     * @param array $metadata Additional metadata
     * @return bool Success
     */
    public static function recordCompletedTask($task_uid, $task_type, $metadata = []) {
        if (empty($task_uid)) {
            return false;
        }

        $task_data = [
            'uid' => $task_uid,
            'type' => $task_type,
            'status' => 'completed',
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => $metadata,
            'progress' => $metadata['post_count'] ?? 0,
        ];

        update_option(self::OPTION_PREFIX . $task_uid, $task_data, false);

        self::markTaskComplete($task_uid);

        return true;
    }

    /**
     * Record a task that failed synchronously.
     *
     * @param string $task_uid Task UID
     * @param string $task_type Type of task
     * @param array $metadata Additional metadata (should include 'error')
     * @return bool Success
     */
    public static function recordFailedTask($task_uid, $task_type, $metadata = []) {
        if (empty($task_uid)) {
            return false;
        }

        $task_data = [
            'uid' => $task_uid,
            'type' => $task_type,
            'status' => 'failed',
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => $metadata,
            'error' => $metadata['error'] ?? 'Unknown error',
            'progress' => 0,
        ];

        update_option(self::OPTION_PREFIX . $task_uid, $task_data, false);

        self::markTaskComplete($task_uid);

        return true;
    }

    /**
     * Get active task UIDs.
     *
     * @return array Array of task UIDs
     */
    public static function getActiveTasks() {
        $tasks = get_option(self::ACTIVE_TASKS_OPTION, []);
        return is_array($tasks) ? $tasks : [];
    }

    /**
     * Get a single task's status.
     *
     * @param string $task_uid Task UID
     * @return array|null Task data or null if not found
     */
    public static function getTaskStatus($task_uid) {
        return get_option(self::OPTION_PREFIX . $task_uid);
    }

    /**
     * Check task status from the API and update local record.
     *
     * @param string $task_uid Task UID
     * @return array|null Updated task data or null on failure
     */
    public static function checkTaskStatus($task_uid) {
        $task_data = self::getTaskStatus($task_uid);

        if (!$task_data) {
            return null;
        }

        // Fetch from API
        $api_response = SearchJetRemoteClient::getTaskStatus($task_uid);

        if (!$api_response) {
            // API call failed, try again later
            wp_schedule_single_event(time() + 10, 'searchjet_check_task_status', [$task_uid]);
            return $task_data;
        }

        // Extract status information
        $api_task = $api_response['task'] ?? $api_response;

        // Update local record
        $task_data['status'] = $api_task['status'] ?? 'unknown';
        $task_data['updated_at'] = time();

        // Extract progress if available
        if (isset($api_task['details']['numberOfDocumentsIndexed'])) {
            $task_data['progress'] = $api_task['details']['numberOfDocumentsIndexed'];
        }

        // Store error if present
        if (!empty($api_task['error'])) {
            $task_data['error'] = $api_task['error'];
        }

        // Extract timing info
        if (!empty($api_task['duration'])) {
            $task_data['duration'] = $api_task['duration'];
        }

        if (!empty($api_task['finished_at'])) {
            $task_data['finished_at'] = $api_task['finished_at'];
        }

        update_option(self::OPTION_PREFIX . $task_uid, $task_data, false);

        // If task is complete, move to completed list
        if (in_array($task_data['status'], ['completed', 'failed', 'canceled'], true)) {
            self::markTaskComplete($task_uid);
        } else {
            // Schedule next check
            $interval = $task_data['status'] === 'processing' ? 5 : 15;
            wp_schedule_single_event(time() + $interval, 'searchjet_check_task_status', [$task_uid]);
        }

        return $task_data;
    }

    /**
     * Mark a task as complete and move it to history.
     *
     * @param string $task_uid Task UID
     * @return bool Success
     */
    public static function markTaskComplete($task_uid) {
        // Remove from active list
        $active = self::getActiveTasks();
        $active = array_filter($active, function($uid) use ($task_uid) {
            return $uid !== $task_uid;
        });
        update_option(self::ACTIVE_TASKS_OPTION, array_values($active), false);

        // Add to completed list
        $completed = self::getCompletedTasks();
        if (!in_array($task_uid, $completed, true)) {
            $completed[] = $task_uid;
            // Keep only last 50 completed tasks
            $completed = array_slice($completed, -50);
            update_option(self::COMPLETED_TASKS_OPTION, $completed, false);
        }

        return true;
    }

    /**
     * Get completed task UIDs.
     *
     * @return array Array of task UIDs
     */
    public static function getCompletedTasks() {
        $tasks = get_option(self::COMPLETED_TASKS_OPTION, []);
        return is_array($tasks) ? $tasks : [];
    }

    /**
     * Get task history (active and completed).
     *
     * @param int $limit Maximum number of tasks to return
     * @return array Array of task data
     */
    public static function getTaskHistory($limit = 20) {
        $history = [];

        // Get active tasks first
        $active = self::getActiveTasks();
        foreach ($active as $uid) {
            $task = self::getTaskStatus($uid);
            if ($task) {
                $history[] = $task;
            }
        }

        // Add completed tasks
        $completed = self::getCompletedTasks();
        foreach (array_reverse($completed) as $uid) {
            if (count($history) >= $limit) {
                break;
            }
            $task = self::getTaskStatus($uid);
            if ($task) {
                $history[] = $task;
            }
        }

        // Sort by updated_at (newest first)
        usort($history, function($a, $b) {
            return ($b['updated_at'] ?? 0) - ($a['updated_at'] ?? 0);
        });

        return array_slice($history, 0, $limit);
    }

    /**
     * Clean up old completed tasks.
     *
     * @param int $days_old Delete tasks older than this many days
     * @return int Number of tasks deleted
     */
    public static function cleanupOldTasks($days_old = 30) {
        global $wpdb;

        $cutoff_time = time() - ($days_old * 86400);
        $deleted = 0;

        // Get all task options
        $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only lookup of task option names.
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like(self::OPTION_PREFIX) . '%'
            )
        );

        foreach ($results as $row) {
            $task = get_option($row->option_name);

            // Delete if old and completed
            if (is_array($task) && isset($task['updated_at'])) {
                if ($task['updated_at'] < $cutoff_time && in_array($task['status'], ['completed', 'failed', 'canceled'], true)) {
                    delete_option($row->option_name);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Clear all task history.
     *
     * @return bool Success
     */
    public static function clearHistory() {
        global $wpdb;

        // Delete all task options
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional schema-scoped cleanup of task options.
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like(self::OPTION_PREFIX) . '%'
            )
        );

        // Clear lists
        delete_option(self::ACTIVE_TASKS_OPTION);
        delete_option(self::COMPLETED_TASKS_OPTION);

        return true;
    }

    /**
     * Get summary statistics for tasks.
     *
     * @return array Statistics
     */
    public static function getStatistics() {
        $active = self::getActiveTasks();
        $completed = self::getCompletedTasks();

        $active_count = count($active);
        $completed_count = count($completed);

        // Count by status
        $by_status = [
            'completed' => 0,
            'failed' => 0,
            'processing' => 0,
            'pending' => 0,
            'canceled' => 0,
        ];

        // Active tasks
        foreach ($active as $uid) {
            $task = self::getTaskStatus($uid);
            if ($task && isset($task['status'])) {
                $status = $task['status'];
                if (isset($by_status[$status])) {
                    $by_status[$status]++;
                }
            }
        }

        // Completed tasks
        foreach ($completed as $uid) {
            $task = self::getTaskStatus($uid);
            if ($task && isset($task['status'])) {
                $status = $task['status'];
                if (isset($by_status[$status])) {
                    $by_status[$status]++;
                }
            }
        }

        return [
            'total_active' => $active_count,
            'total_completed' => $completed_count,
            'total' => $active_count + $completed_count,
            'by_status' => $by_status,
        ];
    }
}
