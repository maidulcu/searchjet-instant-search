<?php
/**
 * Task History Page
 *
 * Admin interface for viewing and managing SearchJet task history
 *
 * @package SearchJet
 */

namespace SearchJet\Admin;

use SearchJet\Services\TaskStatusTracker;

class TaskHistoryPage {

    const MENU_SLUG = 'searchjet-task-history';
    const NONCE_KEY = 'searchjet_task_history_nonce';

    public static function init() {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('wp_ajax_searchjet_get_task_history', [self::class, 'ajax_get_history']);
        add_action('wp_ajax_searchjet_cleanup_task_history', [self::class, 'ajax_cleanup']);
    }

    public static function enqueue_scripts($hook) {
        if ($hook !== 'searchjet_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_script(
            'searchjet-task-history',
            SEARCHJET_PLUGIN_URL . 'assets/js/admin-task-history.js',
            ['jquery'],
            SEARCHJET_VERSION,
            true
        );

        wp_localize_script('searchjet-task-history', 'searchjetTaskHistory', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_KEY),
            'imgUrl' => SEARCHJET_PLUGIN_URL . 'assets/img/',
            'strings' => [
                'loading' => __('Loading tasks...', 'searchjet-instant-search'),
                'noTasks' => __('No tasks found', 'searchjet-instant-search'),
                'error' => __('Failed to load task history', 'searchjet-instant-search'),
                'cleanupConfirm' => __('This will delete all tasks older than 30 days. Continue?', 'searchjet-instant-search'),
                'cleanupSuccess' => __('Old tasks cleaned up successfully', 'searchjet-instant-search'),
            ],
        ]);

        wp_enqueue_style(
            'searchjet-admin-tailwind',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_style(
            'searchjet-task-history',
            SEARCHJET_PLUGIN_URL . 'assets/css/task-history.css',
            [],
            SEARCHJET_VERSION
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'searchjet-instant-search'));
        }
        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Task History', 'searchjet-instant-search'); ?></h1>
                        <p class="text-blue-100 text-xl"><?php esc_html_e('Monitor background operations and track the status of indexing tasks.', 'searchjet-instant-search'); ?></p>
                    </div>
                    <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-lg">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <strong class="text-sm text-[#152A53]"><?php esc_html_e('Live', 'searchjet-instant-search'); ?></strong>
                    </div>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-automations')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/agents-activity-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Automations', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-agents')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/automation-user-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('AI Agents', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-analytics')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/automation-chart-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Analytics', 'searchjet-instant-search'); ?>
                    </a>
                </div>

                <!-- Stats Grid -->
                <div id="searchjet-task-stats" class="searchjet-grid searchjet-grid-4 mb-8">
                    <div class="searchjet-stat-card">
                        <div class="searchjet-stat-number">—</div>
                        <div class="searchjet-stat-label"><?php esc_html_e('Total Tasks', 'searchjet-instant-search'); ?></div>
                    </div>
                    <div class="searchjet-stat-card">
                        <div class="searchjet-stat-number" style="color:#28a745;">—</div>
                        <div class="searchjet-stat-label"><?php esc_html_e('Success Rate', 'searchjet-instant-search'); ?></div>
                    </div>
                    <div class="searchjet-stat-card">
                        <div class="searchjet-stat-number" style="color:#667eea;">—</div>
                        <div class="searchjet-stat-label"><?php esc_html_e('Active Tasks', 'searchjet-instant-search'); ?></div>
                    </div>
                    <div class="searchjet-stat-card">
                        <div class="searchjet-stat-number" style="color:#dc3545;">—</div>
                        <div class="searchjet-stat-label"><?php esc_html_e('Failed Tasks', 'searchjet-instant-search'); ?></div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="searchjet-card" style="margin-bottom:16px;">
                    <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;justify-content:space-between;">
                        <div style="display:flex;flex-wrap:wrap;gap:16px;">
                            <div class="searchjet-form-group" style="margin:0;">
                                <label><?php esc_html_e('Status', 'searchjet-instant-search'); ?></label>
                                <select id="status-filter" class="searchjet-select" style="min-width:180px;">
                                    <option value=""><?php esc_html_e('All Statuses', 'searchjet-instant-search'); ?></option>
                                    <option value="completed"><?php esc_html_e('Completed', 'searchjet-instant-search'); ?></option>
                                    <option value="pending"><?php esc_html_e('Pending', 'searchjet-instant-search'); ?></option>
                                    <option value="processing"><?php esc_html_e('Processing', 'searchjet-instant-search'); ?></option>
                                    <option value="failed"><?php esc_html_e('Failed', 'searchjet-instant-search'); ?></option>
                                    <option value="canceled"><?php esc_html_e('Canceled', 'searchjet-instant-search'); ?></option>
                                </select>
                            </div>
                            <div class="searchjet-form-group" style="margin:0;">
                                <label><?php esc_html_e('Task Type', 'searchjet-instant-search'); ?></label>
                                <select id="type-filter" class="searchjet-select" style="min-width:180px;">
                                    <option value=""><?php esc_html_e('All Types', 'searchjet-instant-search'); ?></option>
                                    <option value="reindex"><?php esc_html_e('Reindex', 'searchjet-instant-search'); ?></option>
                                    <option value="update"><?php esc_html_e('Update', 'searchjet-instant-search'); ?></option>
                                    <option value="delete"><?php esc_html_e('Delete', 'searchjet-instant-search'); ?></option>
                                    <option value="settings"><?php esc_html_e('Settings', 'searchjet-instant-search'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button id="refresh-btn" class="searchjet-btn searchjet-btn-secondary" type="button">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-refresh-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                                <span><?php esc_html_e('Refresh', 'searchjet-instant-search'); ?></span>
                            </button>
                            <button id="cleanup-btn" class="searchjet-btn searchjet-btn-danger" type="button">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/task-trash-white.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                                <span><?php esc_html_e('Clear Old Tasks', 'searchjet-instant-search'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Task Table Card -->
                <div class="searchjet-card" style="padding:0;overflow:hidden;">
                    <div id="task-loading" style="text-align:center;padding:60px 24px;">
                        <div class="sj-w-spinner" style="margin:0 auto 16px;"></div>
                        <div class="sj-loading-text"><?php esc_html_e('Loading tasks...', 'searchjet-instant-search'); ?></div>
                    </div>

                    <div id="task-content" class="hidden">
                        <table class="sj-task-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Task ID', 'searchjet-instant-search'); ?></th>
                                    <th><?php esc_html_e('Type', 'searchjet-instant-search'); ?></th>
                                    <th><?php esc_html_e('Status', 'searchjet-instant-search'); ?></th>
                                    <th><?php esc_html_e('Created', 'searchjet-instant-search'); ?></th>
                                    <th><?php esc_html_e('Duration', 'searchjet-instant-search'); ?></th>
                                    <th><?php esc_html_e('Details', 'searchjet-instant-search'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="task-tbody">
                                <!-- Populated via JS -->
                            </tbody>
                        </table>
                    </div>

                    <div id="no-tasks" class="hidden" style="text-align:center;padding:60px 24px;">
                        <div class="sj-empty-icon">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/task-clipboard-gray.svg'); ?>" alt="" width="56" height="56" style="display:block;margin:0 auto;">
                        </div>
                        <h3 class="sj-empty-title"><?php esc_html_e('No Tasks Found', 'searchjet-instant-search'); ?></h3>
                        <p class="sj-empty-description"><?php esc_html_e('No tasks match the selected filters.', 'searchjet-instant-search'); ?></p>
                    </div>

                    <!-- Inline notification container -->
                    <div id="task-notification" class="sj-task-notification hidden"></div>
                </div>
            </div>
        </div>

        <?php
    }

    public static function ajax_get_history() {
        check_ajax_referer(self::NONCE_KEY, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
        $type_filter = sanitize_text_field($_POST['type_filter'] ?? '');

        // Get all tasks
        $history = TaskStatusTracker::getTaskHistory(50);
        $stats = TaskStatusTracker::getStatistics();

        // Filter tasks
        $filtered = array_filter($history, function($task) use ($status_filter, $type_filter) {
            if ($status_filter && $task['status'] !== $status_filter) {
                return false;
            }
            if ($type_filter && $task['type'] !== $type_filter) {
                return false;
            }
            return true;
        });

        wp_send_json_success([
            'tasks' => array_values($filtered),
            'statistics' => $stats,
            'total' => count($history),
        ]);
    }

    public static function ajax_cleanup() {
        check_ajax_referer(self::NONCE_KEY, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        TaskStatusTracker::cleanupOldTasks(30);

        wp_send_json_success([
            'message' => __('Old tasks cleaned up successfully', 'searchjet-instant-search'),
        ]);
    }
}
