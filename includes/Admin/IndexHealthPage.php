<?php
/**
 * Index Health Page
 *
 * Admin interface for checking SearchJet index health and diagnostics
 *
 * @package SearchJet
 */

namespace SearchJet\Admin;

use SearchJet\API\SearchJetRemoteClient;
use SearchJet\Services\TaskStatusTracker;

class IndexHealthPage {

    const MENU_SLUG = 'searchjet-index-health';
    const NONCE_KEY = 'searchjet_index_health_nonce';

    public static function init() {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('wp_ajax_searchjet_run_index_health', [self::class, 'ajax_run_health']);
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
            'searchjet-index-health',
            SEARCHJET_PLUGIN_URL . 'assets/css/index-health.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_script(
            'searchjet-index-health',
            SEARCHJET_PLUGIN_URL . 'assets/js/admin-index-health.js',
            ['jquery'],
            SEARCHJET_VERSION,
            true
        );

        wp_localize_script('searchjet-index-health', 'searchjetIndexHealth', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_KEY),
            'imgUrl' => SEARCHJET_PLUGIN_URL . 'assets/img/',
            'strings' => [
                'running' => __('Running diagnostics...', 'searchjet-instant-search'),
                'success' => __('All checks passed!', 'searchjet-instant-search'),
                'error' => __('Failed to run diagnostics', 'searchjet-instant-search'),
            ],
        ]);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $nonce = wp_create_nonce(self::NONCE_KEY);
        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Index Health', 'searchjet-instant-search'); ?></h1>
                        <p class="text-blue-100 text-xl"><?php esc_html_e('Monitor index status, check API connectivity, and diagnose synchronization issues.', 'searchjet-instant-search'); ?></p>
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
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-bulk-reindex')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-refresh-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Bulk Re-Index', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-settings')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-sliders-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Index Settings', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-task-history')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-clock-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Task History', 'searchjet-instant-search'); ?>
                    </a>
                </div>

                <div class="searchjet-card">
                    <h2>
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/health-clipboard-indigo.svg'); ?>" alt="" width="20" height="20" style="display:block;flex-shrink:0;">
                        <?php esc_html_e('Health Checks', 'searchjet-instant-search'); ?>
                    </h2>
                    <p class="description" style="margin-top:-12px;margin-bottom:24px;"><?php esc_html_e('Run diagnostics to verify index status', 'searchjet-instant-search'); ?></p>
                    <button id="run-diagnostics-btn" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-refresh-white.svg'); ?>" alt="" width="20" height="20" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Run Diagnostics', 'searchjet-instant-search'); ?>
                    </button>
                </div>

                <div id="health-loading" class="searchjet-card hidden" style="text-align:center;">
                    <div style="display:flex;flex-direction:column;align-items:center;gap:20px;padding:24px 0;">
                        <div class="searchjet-spinner">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/health-spinner.svg'); ?>" alt="">
                        </div>
                        <div>
                            <h3 style="font-size:20px;font-weight:700;color:#212529;margin:0 0 8px;"><?php esc_html_e('Running Diagnostics...', 'searchjet-instant-search'); ?></h3>
                            <p style="font-size:14px;color:#6c757d;margin:0;"><?php esc_html_e('Checking API connectivity, document counts, and indexing status.', 'searchjet-instant-search'); ?></p>
                        </div>
                        <div class="searchjet-progress" style="width:100%;max-width:300px;">
                            <div class="searchjet-progress-bar" style="width:70%;"></div>
                        </div>
                    </div>
                </div>

                <div id="health-results" class="hidden">
                </div>

                <div id="health-empty" class="searchjet-card" style="text-align:center;border:2px dashed #e9ecef;background:#f8f9fa;">
                    <div style="padding:40px 24px;">
                        <div class="searchjet-tip-icon" style="width:80px;height:80px;margin:0 auto 20px;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/health-shield-indigo.svg'); ?>" alt="" width="48" height="48" style="display:block;">
                        </div>
                        <h4 style="font-size:18px;font-weight:700;color:#495057;margin:0 0 8px;"><?php esc_html_e('Ready to Scan', 'searchjet-instant-search'); ?></h4>
                        <p style="font-size:14px;color:#6c757d;margin:0;"><?php esc_html_e('Click "Run Diagnostics" to check your index health and identify any issues.', 'searchjet-instant-search'); ?></p>
                    </div>
                </div>

                <div class="searchjet-grid searchjet-grid-3">
                    <div class="searchjet-stat-card">
                        <div class="searchjet-stat-number" style="font-size:24px;line-height:1.3;color:#28a745;"><?php esc_html_e('Connected', 'searchjet-instant-search'); ?></div>
                        <div class="searchjet-stat-label"><?php esc_html_e('API Status', 'searchjet-instant-search'); ?></div>
                    </div>
                    <div class="searchjet-stat-card">
                        <div class="searchjet-stat-number" style="font-size:24px;line-height:1.3;"><?php esc_html_e('Never', 'searchjet-instant-search'); ?></div>
                        <div class="searchjet-stat-label"><?php esc_html_e('Last Scan', 'searchjet-instant-search'); ?></div>
                    </div>
                    <div class="searchjet-stat-card" style="text-align:left;">
                        <div class="searchjet-tip-icon">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-lightbulb-indigo.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                        </div>
                        <div style="font-size:15px;font-weight:600;color:#212529;margin-bottom:6px;"><?php esc_html_e('Optimization Tip', 'searchjet-instant-search'); ?></div>
                        <p class="description" style="margin:0 0 12px;"><?php esc_html_e('Run diagnostics regularly to ensure your search index stays in sync with your content.', 'searchjet-instant-search'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-bulk-reindex')); ?>" class="searchjet-btn searchjet-btn-secondary searchjet-btn-sm" style="justify-content:center;">
                            <?php esc_html_e('Sync Settings', 'searchjet-instant-search'); ?>
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/health-chevron-right-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-left:6px;">
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function ajax_run_health() {
        check_ajax_referer(self::NONCE_KEY, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $checks = [];

        // 1. API Connectivity — use fetchDocumentCount which is known to work
        $api_key = get_option('searchjet_hosted_api_key', '');
        $indexed_docs = SearchJetRemoteClient::fetchDocumentCount();
        $api_connected = $indexed_docs !== null;
        $checks[] = [
            'title' => __('API Connectivity', 'searchjet-instant-search'),
            'status' => $api_connected ? 'success' : 'error',
            'message' => $api_connected ? __('Connected to SearchJet API', 'searchjet-instant-search') : __('Unable to connect to SearchJet API', 'searchjet-instant-search'),
        ];

        if (!$api_connected) {
            wp_send_json_success(['checks' => $checks]);
        }

        // 2. Document Count
        $wp_total = self::count_indexable_documents();
        $diff = abs($wp_total - $indexed_docs);
        $doc_status = $diff <= 5 ? 'success' : ($diff <= 20 ? 'warning' : 'error');
        $checks[] = [
            'title' => __('Document Count', 'searchjet-instant-search'),
            'status' => $doc_status,
            'message' => sprintf(
                __('Indexed: %d, Expected: %d (diff: %d)', 'searchjet-instant-search'),
                $indexed_docs,
                $wp_total,
                $diff
            ),
        ];

        // 3. Index Settings
        $settings = SearchJetRemoteClient::getIndexSettings();
        $settings_loaded = is_array($settings);
        $searchable = $settings['searchableAttributes'] ?? [];
        $filterable = $settings['filterableAttributes'] ?? [];
        $sortable = $settings['sortableAttributes'] ?? [];
        $checks[] = [
            'title' => __('Index Settings', 'searchjet-instant-search'),
            'status' => $settings_loaded ? 'success' : 'warning',
            'message' => $settings_loaded
                ? sprintf(
                    __('Searchable: %d, Filterable: %d, Sortable: %d', 'searchjet-instant-search'),
                    count($searchable),
                    count($filterable),
                    count($sortable)
                )
                : __('Could not load index settings', 'searchjet-instant-search'),
        ];

        // 4. Pending Tasks
        $task_stats = TaskStatusTracker::getStatistics();
        $pending = $task_stats['total_active'] ?? 0;
        $checks[] = [
            'title' => __('Pending Tasks', 'searchjet-instant-search'),
            'status' => $pending === 0 ? 'success' : 'warning',
            'message' => sprintf(
                __('%d task(s) in progress', 'searchjet-instant-search'),
                $pending
            ),
        ];

        // 5. Recent Failures
        $history = TaskStatusTracker::getTaskHistory(20);
        $failures = array_filter($history, function($t) {
            return $t['status'] === 'failed';
        });
        $checks[] = [
            'title' => __('Recent Task Failures', 'searchjet-instant-search'),
            'status' => count($failures) === 0 ? 'success' : 'error',
            'message' => count($failures) === 0 ?
                __('No failed tasks', 'searchjet-instant-search') :
                sprintf(__('%d failed task(s) in last 20', 'searchjet-instant-search'), count($failures)),
        ];

        // 6. Index Sync
        $last_reindex = get_option('searchjet_last_reindex_time', 0);
        $hours_ago = $last_reindex ? round((time() - (int) $last_reindex) / 3600, 1) : null;
        $sync_status = $hours_ago === null ? 'warning' : ($hours_ago <= 24 ? 'success' : 'warning');
        $checks[] = [
            'title' => __('Index Freshness', 'searchjet-instant-search'),
            'status' => $sync_status,
            'message' => $hours_ago !== null
                ? sprintf(__('Last reindex: %s hour(s) ago', 'searchjet-instant-search'), $hours_ago)
                : __('No reindex recorded yet', 'searchjet-instant-search'),
        ];

        wp_send_json_success(['checks' => $checks]);
    }

    /**
     * Count the number of indexable documents in WordPress.
     *
     * Mirrors the criteria used by the Reindexer so the health check compares
     * the index against an accurate expected count (all indexable post types
     * across all indexable statuses, not just published "post" entries).
     *
     * @return int Expected number of indexed documents.
     */
    private static function count_indexable_documents() {
        global $wpdb;

        $indexable_types = \SearchJet\Helpers\IndexScope::post_types();

        $indexable_statuses = apply_filters(
            'searchjet_indexable_post_statuses',
            array_filter(array_map('trim', explode(',', get_option('searchjet_include_status', 'publish'))))
        );
        if (empty($indexable_statuses)) {
            $indexable_statuses = ['publish'];
        }

        if (empty($indexable_types)) {
            return 0;
        }

        $type_placeholders = implode(',', array_fill(0, count($indexable_types), '%s'));
        $status_placeholders = implode(',', array_fill(0, count($indexable_statuses), '%s'));

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ({$type_placeholders}) AND post_status IN ({$status_placeholders}) AND post_password = ''",
            array_merge(array_values($indexable_types), array_values($indexable_statuses))
        );

        return (int) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only aggregate count on prepared query.
    }
}
