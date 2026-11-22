<?php
/**
 * Bulk Reindex Page
 *
 * Admin interface for bulk re-indexing SearchJet content
 *
 * @package SearchJet
 */

namespace SearchJet\Admin;

use SearchJet\Services\MeilisearchClient;
use SearchJet\Services\TaskStatusTracker;
use SearchJet\Helpers\BatchDeleteHelper;

class BulkReindexPage {

    const MENU_SLUG = 'searchjet-bulk-reindex';
    const NONCE_KEY = 'searchjet_bulk_reindex_nonce';

    public static function init() {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('wp_ajax_searchjet_start_bulk_reindex', [self::class, 'ajax_start_reindex']);
        add_action('wp_ajax_searchjet_bulk_reindex_progress', [self::class, 'ajax_get_progress']);
    }

    public static function enqueue_scripts($hook) {
        if ($hook !== 'searchjet_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'searchjet-admin-tailwind',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_style(
            'searchjet-bulk-reindex',
            SEARCHJET_PLUGIN_URL . 'assets/css/bulk-reindex.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_script(
            'searchjet-bulk-reindex',
            SEARCHJET_PLUGIN_URL . 'assets/js/admin-bulk-reindex.js',
            ['jquery'],
            SEARCHJET_VERSION,
            true
        );

        $limits = \SearchJet\Services\PlanManager::get_usage_limits();
        $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();
        $reindexing_per_day = (int) \SearchJet\Services\PlanManager::get_limit('reindexing_per_day');
        wp_localize_script('searchjet-bulk-reindex', 'searchjetBulkReindex', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_KEY),
            'imgUrl' => SEARCHJET_PLUGIN_URL . 'assets/img/',
            'reindexLimit' => [
                'used' => $reindexes_today,
                'max' => $reindexing_per_day,
            ],
            'strings' => [
                'starting' => __('Starting reindex...', 'searchjet-instant-search'),
                'processing' => __('Reindexing in progress...', 'searchjet-instant-search'),
                'success' => __('Reindex completed successfully!', 'searchjet-instant-search'),
                'error' => __('Reindex failed. Check your API key and logs.', 'searchjet-instant-search'),
                'confirm' => __('This will reindex your content. Proceed?', 'searchjet-instant-search'),
                'limitReached' => __('Daily reindex limit reached. The limit resets tomorrow.', 'searchjet-instant-search'),
            ],
        ]);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $nonce = wp_create_nonce(self::NONCE_KEY);
        $post_types = \SearchJet\Helpers\IndexScope::post_types();
        $count = wp_count_posts();
        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Bulk Re-Index', 'searchjet-instant-search'); ?></h1>
                        <p class="text-blue-100 text-xl"><?php esc_html_e('Synchronize your WordPress content with the search index. Rebuild your search data to ensure accurate and relevant results.', 'searchjet-instant-search'); ?></p>
                    </div>
                    <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-lg">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <strong class="text-sm text-[#152A53]"><?php esc_html_e('Ready', 'searchjet-instant-search'); ?></strong>
                    </div>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-indexing')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-refresh-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Indexing', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-settings')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-sliders-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Index Settings', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-health')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-zap-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Index Health', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-task-history')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-clock-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Task History', 'searchjet-instant-search'); ?>
                    </a>
                </div>

                <div class="searchjet-card" id="reindex-form-card">
                    <h2><?php esc_html_e('Reindex Configuration', 'searchjet-instant-search'); ?></h2>
                    <p class="description" style="margin-top:-12px;margin-bottom:24px;"><?php esc_html_e('Choose what content to reindex', 'searchjet-instant-search'); ?></p>
                <div class="searchjet-scope-section">
                    <label class="searchjet-section-label"><?php esc_html_e('Synchronization Scope', 'searchjet-instant-search'); ?></label>

                    <div class="searchjet-scope-options">
                        <div class="searchjet-scope-option sj-scope-option active" data-scope="all">
                            <input type="radio" id="reindex-all" name="reindex_mode" value="all" checked>
                            <div class="searchjet-scope-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-globe-indigo.svg'); ?>" alt="" width="20" height="20" style="display:block;">
                            </div>
                            <div class="searchjet-scope-content">
                                <span class="searchjet-scope-title"><?php esc_html_e('Global Sync', 'searchjet-instant-search'); ?></span>
                                <span class="searchjet-scope-desc"><?php esc_html_e('Index all public content types across the entire database.', 'searchjet-instant-search'); ?></span>
                            </div>
                            <div class="searchjet-scope-check">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-check-white.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                            </div>
                        </div>

                        <div class="searchjet-scope-option sj-scope-option" data-scope="type">
                            <input type="radio" id="reindex-type" name="reindex_mode" value="type">
                            <div class="searchjet-scope-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-file-indigo.svg'); ?>" alt="" width="20" height="20" style="display:block;">
                            </div>
                            <div class="searchjet-scope-content">
                                <span class="searchjet-scope-title"><?php esc_html_e('Targeted Sync', 'searchjet-instant-search'); ?></span>
                                <span class="searchjet-scope-desc"><?php esc_html_e('Select specific content types to update.', 'searchjet-instant-search'); ?></span>
                                <select id="post-type-select" disabled>
                                    <?php foreach ($post_types as $ptype): ?>
                                        <option value="<?php echo esc_attr($ptype); ?>"><?php echo esc_html(ucfirst($ptype)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="searchjet-scope-check">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-check-white.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                            </div>
                        </div>

                        <div class="searchjet-scope-option sj-scope-option" data-scope="date">
                            <input type="radio" id="reindex-date" name="reindex_mode" value="date">
                            <div class="searchjet-scope-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-calendar-indigo.svg'); ?>" alt="" width="20" height="20" style="display:block;">
                            </div>
                            <div class="searchjet-scope-content">
                                <span class="searchjet-scope-title"><?php esc_html_e('Differential Sync', 'searchjet-instant-search'); ?></span>
                                <span class="searchjet-scope-desc"><?php esc_html_e('Only process entries modified after a specific date.', 'searchjet-instant-search'); ?></span>
                                <input type="date" id="date-from" disabled>
                            </div>
                            <div class="searchjet-scope-check">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-check-white.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="searchjet-modifier">
                    <div class="searchjet-modifier-info">
                        <strong><?php esc_html_e('Force Full Reindex', 'searchjet-instant-search'); ?></strong>
                        <div class="description"><?php esc_html_e('Purges the current index before rebuilding. Use only if severe data inconsistency is detected.', 'searchjet-instant-search'); ?></div>
                    </div>
                    <div class="searchjet-toggle-switch">
                        <input type="checkbox" id="force-full" name="force_full">
                        <label for="force-full"></label>
                    </div>
                </div>

                <div class="searchjet-reindex-actions">
                    <?php
                    $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();
                    $reindexing_per_day = (int) \SearchJet\Services\PlanManager::get_limit('reindexing_per_day');
                    $limit_reached = $reindexes_today >= $reindexing_per_day;
                    ?>
                    <button id="start-reindex-btn" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg"<?php echo $limit_reached ? ' disabled' : ''; ?>>
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-refresh-white.svg'); ?>" alt="" width="18" height="18" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Start Reindex', 'searchjet-instant-search'); ?>
                    </button>
                    <?php if ($limit_reached): ?>
                        <div class="searchjet-alert searchjet-alert-warning" style="width:100%;">
                            <strong><?php esc_html_e('Daily reindex limit reached.', 'searchjet-instant-search'); ?></strong>
                            <?php printf(
                                esc_html__('You have used %1$d of %2$d reindexes today. The limit resets tomorrow.', 'searchjet-instant-search'),
                                (int) $reindexes_today,
                                (int) $reindexing_per_day
                            ); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="searchjet-card" id="progress-card" style="display: none;">
                <h2><?php esc_html_e('Progress', 'searchjet-instant-search'); ?></h2>
                <div class="searchjet-progress-header">
                    <span class="searchjet-status searchjet-status-info" id="progress-status-text"><?php esc_html_e('Starting...', 'searchjet-instant-search'); ?></span>
                    <strong class="searchjet-progress-percent" id="progress-percent">0%</strong>
                </div>

                <div class="searchjet-progress">
                    <div class="searchjet-progress-bar" id="progress-bar"></div>
                </div>

                <div class="searchjet-progress-stats">
                    <div>
                        <div class="searchjet-progress-text"><?php esc_html_e('Records Processed', 'searchjet-instant-search'); ?></div>
                        <strong style="font-size:15px;color:#212529;" id="records-processed">0 / 0</strong>
                    </div>
                    <div>
                        <div class="searchjet-progress-text"><?php esc_html_e('Task ID', 'searchjet-instant-search'); ?></div>
                        <code style="font-size:13px;" id="task-uid">--</code>
                    </div>
                </div>

                <div id="result-message" class="hidden" style="margin-top:16px;"></div>
            </div>

            <div class="searchjet-grid searchjet-grid-3">
                <div class="searchjet-stat-card">
                    <div class="searchjet-stat-number"><?php echo number_format($count->publish); ?></div>
                    <div class="searchjet-stat-label"><?php esc_html_e('Total Entries', 'searchjet-instant-search'); ?></div>
                </div>
                <div class="searchjet-stat-card">
                    <div class="searchjet-stat-number" style="font-size:24px;line-height:1.3;"><?php echo esc_html(get_option('searchjet_last_reindex', __('Never', 'searchjet-instant-search'))); ?></div>
                    <div class="searchjet-stat-label"><?php esc_html_e('Last Sync', 'searchjet-instant-search'); ?></div>
                </div>
                <div class="searchjet-stat-card" style="text-align:left;">
                    <div class="searchjet-tip-icon">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-lightbulb-indigo.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                    </div>
                    <div style="font-size:15px;font-weight:600;color:#212529;margin-bottom:6px;"><?php esc_html_e('Pro Tip', 'searchjet-instant-search'); ?></div>
                    <p class="description" style="margin:0;"><?php esc_html_e('Large indexes are automatically processed in optimized asynchronous batches to maintain high system availability.', 'searchjet-instant-search'); ?></p>
                </div>
            </div>
        </div>
        </div>
        <?php
    }

    public static function ajax_start_reindex() {
        check_ajax_referer(self::NONCE_KEY, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        // Server-side daily reindex limit check
        $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();
        $reindexing_per_day = (int) \SearchJet\Services\PlanManager::get_limit('reindexing_per_day');
        if ($reindexes_today >= $reindexing_per_day) {
            wp_send_json_error(sprintf(
                __('Daily reindex limit reached. You have used %1$d of %2$d reindexes today. The limit resets tomorrow.', 'searchjet-instant-search'),
                $reindexes_today,
                $reindexing_per_day
            ));
        }

        $mode = sanitize_text_field($_POST['mode'] ?? 'all');
        $force_full = isset($_POST['force_full']) && $_POST['force_full'] === 'true';

        try {
            $args = [
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'wpml_language' => 'all',
                'suppress_filters' => true,
            ];

            // Same scope as every other index path: public, searchable post types only.
            $scope_types = \SearchJet\Helpers\IndexScope::post_types();
            if (empty($scope_types)) {
                wp_send_json_error(__('No posts found to reindex', 'searchjet-instant-search'));
            }
            $args['post_type'] = $scope_types;

            if ($mode === 'type') {
                $post_type = sanitize_key(wp_unslash($_POST['post_type'] ?? 'post'));
                if (!in_array($post_type, $scope_types, true)) {
                    wp_send_json_error(__('This post type is not indexed by SearchJet.', 'searchjet-instant-search'));
                }
                $args['post_type'] = $post_type;
            } elseif ($mode === 'date') {
                $date_from = sanitize_text_field($_POST['date_from'] ?? '');
                if ($date_from) {
                    $args['date_query'] = [
                        [
                            'after' => $date_from,
                            'inclusive' => true,
                        ]
                    ];
                }
            }

            $post_ids = get_posts($args);

            if (empty($post_ids)) {
                wp_send_json_error(__('No posts found to reindex', 'searchjet-instant-search'));
            }

            $posts = array_map('get_post', $post_ids);
            $posts = array_values(array_filter($posts, function ($post) {
                return $post && !\SearchJet\Helpers\IndexScope::is_protected($post);
            }));

            if (empty($posts)) {
                wp_send_json_error(__('No posts found to reindex', 'searchjet-instant-search'));
            }

            $meilisearch = new MeilisearchClient();
            $result = $meilisearch->bulk_index_posts($posts, $force_full);

            if (!isset($result['count'])) {
                wp_send_json_error(__('Reindex failed', 'searchjet-instant-search'));
            }

            delete_transient('searchjet_document_count');
            \SearchJet\Services\PlanManager::clear_cache();

            $task_uid = 'reindex-' . time() . '-' . wp_generate_uuid4();

            TaskStatusTracker::recordCompletedTask($task_uid, 'reindex', [
                'mode' => $mode,
                'post_count' => count($posts),
                'force_full' => $force_full,
            ]);

            // Refresh usage limits from API to get updated reindex count
            \SearchJet\Services\PlanManager::record_reindex_today();
            \SearchJet\Admin\EnhancedSettingsPage::refreshFromApi();
            $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();

            wp_send_json_success([
                'task_uid' => $task_uid,
                'total_posts' => count($posts),
                'reindexes_today' => $reindexes_today,
                'message' => sprintf(__('Reindexed %d posts successfully', 'searchjet-instant-search'), count($posts)),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public static function ajax_get_progress() {
        check_ajax_referer(self::NONCE_KEY, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $task_uid = sanitize_text_field($_POST['task_uid'] ?? '');

        if (empty($task_uid)) {
            wp_send_json_error(__('Invalid task UID', 'searchjet-instant-search'));
        }

        $task = TaskStatusTracker::getTaskStatus($task_uid);

        if (!$task) {
            wp_send_json_error(__('Task not found', 'searchjet-instant-search'));
        }

        wp_send_json_success([
            'status' => $task['status'],
            'progress' => $task['progress'] ?? 0,
            'message' => self::get_status_message($task['status']),
            'finished' => in_array($task['status'], ['completed', 'failed']),
        ]);
    }

    private static function get_status_message($status) {
        $messages = [
            'pending' => __('Queued for reindexing', 'searchjet-instant-search'),
            'processing' => __('Reindex in progress', 'searchjet-instant-search'),
            'completed' => __('Reindex completed successfully', 'searchjet-instant-search'),
            'failed' => __('Reindex failed', 'searchjet-instant-search'),
            'canceled' => __('Reindex canceled', 'searchjet-instant-search'),
        ];

        return $messages[$status] ?? __('Unknown status', 'searchjet-instant-search');
    }
}
