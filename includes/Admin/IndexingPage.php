<?php
namespace SearchJet\Admin;

use SearchJet\Services\Reindexer;
use SearchJet\Services\TaskStatusTracker;

class IndexingPage {

    public static function init(): void {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('wp_ajax_searchjet_reindex_all', [self::class, 'handleReindex']);
        add_action('wp_ajax_searchjet_enhanced_reindex', [self::class, 'handleReindex']);
        add_action('wp_ajax_searchjet_save_attributes', [self::class, 'handleSaveAttributes']);
    }

    public static function enqueueAssets($hook): void {
        if ($hook !== 'searchjet_page_searchjet-indexing') return;

        wp_enqueue_style(
            'searchjet-admin-tailwind',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_style(
            'searchjet-admin-enhanced',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-style-enhanced.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_script(
            'searchjet-admin-enhanced',
            SEARCHJET_PLUGIN_URL . 'assets/js/admin-enhanced.js',
            ['jquery'],
            SEARCHJET_VERSION,
            true
        );

        $attrs_raw  = get_option('searchjet_attributes', '');
        $attrs_list = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', (string) $attrs_raw)), function($v){ return $v !== ''; }));

        $limits = \SearchJet\Services\PlanManager::get_usage_limits();
        $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();
        $reindexing_per_day = (int) \SearchJet\Services\PlanManager::get_limit('reindexing_per_day');
        wp_localize_script('searchjet-admin-enhanced', 'SearchJetAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('searchjet_reindex_nonce'),
            'ajax_action' => 'searchjet_reindex_all',
            'attributes_nonce' => wp_create_nonce('searchjet_attributes_nonce'),
            'search_mode' => get_option('searchjet_search_mode', 'js'),
            'attributes' => $attrs_list,
            'batch_size' => 100,
            'reindex_limit' => [
                'used' => $reindexes_today,
                'max' => $reindexing_per_day,
            ],
            'strings' => [
                'connecting' => __('Connecting...', 'searchjet-instant-search'),
                'connected' => __('Connected', 'searchjet-instant-search'),
                'disconnected' => __('Disconnected', 'searchjet-instant-search'),
                'reindexing' => __('Reindexing...', 'searchjet-instant-search'),
                'complete' => __('Complete', 'searchjet-instant-search'),
                'error' => __('Error', 'searchjet-instant-search'),
                'limit_reached' => __('Daily reindex limit reached. The limit resets tomorrow.', 'searchjet-instant-search'),
            ]
        ]);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
        }

        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto">
                    <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Indexing', 'searchjet-instant-search'); ?></h1>
                    <p class="text-blue-100 text-xl"><?php esc_html_e('Manage your search index and content attributes', 'searchjet-instant-search'); ?></p>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">
                <div class="searchjet-card">
                    <h2><?php esc_html_e('Index Management', 'searchjet-instant-search'); ?></h2>

                    <div class="searchjet-form-group">
                        <?php
                        $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();
                        $reindexing_per_day = (int) \SearchJet\Services\PlanManager::get_limit('reindexing_per_day');
                        $limit_reached = $reindexes_today >= $reindexing_per_day;
                        ?>
                        <button type="button" id="searchjet-reindex-btn" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg"<?php echo $limit_reached ? ' disabled' : ''; ?>>
                            <?php esc_html_e('Reindex All Content', 'searchjet-instant-search'); ?>
                        </button>
                        <?php if ($limit_reached): ?>
                            <div class="searchjet-alert searchjet-alert-warning" style="margin-top: 12px; padding: 12px 16px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404;">
                                <strong><?php esc_html_e('Daily reindex limit reached.', 'searchjet-instant-search'); ?></strong>
                                <?php printf(
                                    esc_html__('You have used %1$d of %2$d reindexes today. The limit resets tomorrow.', 'searchjet-instant-search'),
                                    (int) $reindexes_today,
                                    (int) $reindexing_per_day
                                ); ?>
                            </div>
                        <?php else: ?>
                            <div class="description">
                                <?php esc_html_e('Rebuild the search index with all published content.', 'searchjet-instant-search'); ?>
                                <span style="color: #6b7280; font-size: 0.85em;">
                                    <?php printf(
                                        esc_html__('(%1$d of %2$d daily reindexes used)', 'searchjet-instant-search'),
                                        (int) $reindexes_today,
                                        (int) $reindexing_per_day
                                    ); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="searchjet-reindex-progress" style="display: none;">
                        <div class="searchjet-progress">
                            <div class="searchjet-progress-bar" id="searchjet-progress-bar"></div>
                        </div>
                        <div class="searchjet-progress-text" id="searchjet-progress-text"></div>
                    </div>
                </div>

                <div class="searchjet-card">
                    <h2><?php esc_html_e('Custom Attributes', 'searchjet-instant-search'); ?></h2>

                    <div class="searchjet-form-group">
                        <div class="searchjet-attributes-container">
                            <div class="searchjet-attributes-presets">
                                <h4><?php esc_html_e('Quick Presets', 'searchjet-instant-search'); ?></h4>
                                <div class="searchjet-preset-buttons">
                                    <button type="button" class="searchjet-preset-btn" data-preset="basic"><?php esc_html_e('Basic', 'searchjet-instant-search'); ?></button>
                                    <button type="button" class="searchjet-preset-btn" data-preset="woocommerce"><?php esc_html_e('WooCommerce', 'searchjet-instant-search'); ?></button>
                                    <button type="button" class="searchjet-preset-btn" data-preset="advanced"><?php esc_html_e('Advanced', 'searchjet-instant-search'); ?></button>
                                </div>
                            </div>

                            <div class="searchjet-attributes-builder">
                                <h4><?php esc_html_e('Selected Attributes', 'searchjet-instant-search'); ?></h4>
                                <div id="searchjet-attributes-list" class="searchjet-attributes-list"></div>
                                <div class="searchjet-add-attribute">
                                    <input type="text" id="searchjet-new-attribute" class="searchjet-input" placeholder="<?php esc_attr_e('Add custom attribute...', 'searchjet-instant-search'); ?>">
                                    <button type="button" id="searchjet-add-btn" class="searchjet-btn searchjet-btn-secondary"><?php esc_html_e('Add', 'searchjet-instant-search'); ?></button>
                                </div>
                            </div>

                            <textarea id="searchjet_attributes"
                                     name="searchjet_attributes"
                                     style="display: none;"><?php echo esc_textarea(get_option('searchjet_attributes', '')); ?></textarea>
                        </div>
                        <div class="description">
                            <?php esc_html_e('Select which data fields to retrieve from SearchJet for search results. More attributes may slow down search performance.', 'searchjet-instant-search'); ?>
                        </div>
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="button" id="searchjet-save-attributes-btn" class="searchjet-btn searchjet-btn-primary">
                            <?php esc_html_e('Save Attributes', 'searchjet-instant-search'); ?>
                        </button>
                    </div>
                </div>

                <div class="searchjet-card">
                    <h2><?php esc_html_e('Advanced Indexing', 'searchjet-instant-search'); ?></h2>
                    <p><?php esc_html_e('Need more control over your index? Use these advanced tools:', 'searchjet-instant-search'); ?></p>
                    <div class="searchjet-grid searchjet-grid-3" style="margin-top: 16px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-bulk-reindex')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/indexing-code-gray.svg'); ?>" alt="" width="16" height="16" style="display:block;margin-right:6px;">
                            <?php esc_html_e('Bulk Re-Index', 'searchjet-instant-search'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-settings')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-sliders-gray.svg'); ?>" alt="" width="16" height="16" style="display:block;margin-right:6px;">
                            <?php esc_html_e('Index Settings', 'searchjet-instant-search'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-health')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-zap-gray.svg'); ?>" alt="" width="16" height="16" style="display:block;margin-right:6px;">
                            <?php esc_html_e('Index Health', 'searchjet-instant-search'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handleReindex(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'searchjet-instant-search')]);
        }

        check_ajax_referer('searchjet_reindex_nonce', 'nonce');

        // Server-side daily reindex limit check
        $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();
        $reindexing_per_day = (int) \SearchJet\Services\PlanManager::get_limit('reindexing_per_day');
        if ($reindexes_today >= $reindexing_per_day) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Daily reindex limit reached. You have used %1$d of %2$d reindexes today. The limit resets tomorrow.', 'searchjet-instant-search'),
                    $reindexes_today,
                    $reindexing_per_day
                ),
                'done' => true,
                'error' => 'rate_limited',
            ]);
        }

        try {
            $api_key = get_option('searchjet_hosted_api_key', '');
            if (empty($api_key)) {
                wp_send_json_error([
                    'message' => __('No API key configured. Please enter your SearchJet API key in Settings.', 'searchjet-instant-search'),
                    'done' => true,
                ]);
            }

            $client_info = \SearchJet\API\SearchJetRemoteClient::fetchClientInfo($api_key, true);
            if ($client_info === null) {
                wp_send_json_error([
                    'message' => __('Cannot connect to SearchJet servers. Your API key may be invalid.', 'searchjet-instant-search'),
                    'done' => true,
                    'error' => 'api_connectivity',
                ]);
            }

            $reindexer = new Reindexer();
            $result = $reindexer->reindex_all();

            $message = $result['message'] ?? __('Reindexing completed successfully', 'searchjet-instant-search');
            $total = $result['total_indexed'] ?? 0;

            if (isset($result['error'])) {
                wp_send_json_error([
                    'message' => $message,
                    'processed' => $total,
                    'done' => true,
                    'error' => $result['error'],
                    'quota' => $result['quota'] ?? null,
                    'upgrade_url' => $result['upgrade_url'] ?? null,
                ]);
            }

            delete_transient('searchjet_document_count');
            \SearchJet\Services\PlanManager::clear_cache();
            update_option('searchjet_last_reindex_time', time());

            $current_count = null;
            for ($i = 0; $i < 3; $i++) {
                usleep(1000000);
                delete_transient('searchjet_document_count');
                $current_count = \SearchJet\API\SearchJetRemoteClient::fetchDocumentCount();
                if ($current_count !== null && $current_count > 0) {
                    break;
                }
            }
            $current_count = $current_count ?? 0;

            $client_info = get_option('searchjet_client_info', []);
            $max_pages = \SearchJet\Services\PlanManager::get_limit('max_indexed_pages');
            if ($max_pages > 0 && $current_count !== null && $current_count >= $max_pages) {
                wp_send_json_error([
                    'message' => sprintf(__('Index limit reached. You have indexed %d documents, which meets or exceeds your plan\'s limit of %d.', 'searchjet-instant-search'), $current_count, $max_pages),
                    'processed' => $total,
                    'done' => true,
                ]);
            }

            $task_uid = 'reindex-' . time() . '-' . wp_generate_uuid4();
            TaskStatusTracker::recordCompletedTask($task_uid, 'reindex', [
                'mode' => 'full',
                'post_count' => $total,
            ]);

            // Refresh usage limits from API to get updated reindex count
            \SearchJet\Services\PlanManager::record_reindex_today();
            \SearchJet\Admin\EnhancedSettingsPage::refreshFromApi();
            $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();

            wp_send_json_success([
                'processed' => $total,
                'document_count' => $current_count,
                'done' => true,
                'message' => $message,
                'reindexes_today' => $reindexes_today,
            ]);
        } catch (\Throwable $e) {
            error_log('[SearchJet] Reindex exception: ' . $e->getMessage());

            $task_uid = 'reindex-' . time() . '-' . wp_generate_uuid4();
            TaskStatusTracker::recordFailedTask($task_uid, 'reindex', [
                'error' => $e->getMessage(),
            ]);

            wp_send_json_error([
                'message' => __('Reindexing failed: ', 'searchjet-instant-search') . $e->getMessage(),
                'done' => true,
            ]);
        }
    }

    public static function handleSaveAttributes(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'searchjet-instant-search')]);
        }

        check_ajax_referer('searchjet_attributes_nonce', 'nonce');

        $raw = isset($_POST['attributes']) ? (string) $_POST['attributes'] : '';
        $parts = preg_split('/[\n,]+/', $raw);
        if (!is_array($parts)) {
            $parts = [$raw];
        }

        $attributes = [];
        foreach ($parts as $part) {
            $attr = sanitize_key(trim($part));
            if ($attr !== '' && !in_array($attr, $attributes, true)) {
                $attributes[] = $attr;
            }
        }

        update_option('searchjet_attributes', implode(',', $attributes));

        wp_send_json_success([
            'attributes' => $attributes,
            'message' => __('Attributes saved successfully', 'searchjet-instant-search'),
        ]);
    }
}
