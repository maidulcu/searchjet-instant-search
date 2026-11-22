<?php
namespace SearchJet\Admin;

use SearchJet\API\SearchJetRemoteClient;
use SearchJet\Services\PlanManager;
use SearchJet\Services\TaskStatusTracker;
use SearchJet\Automations\Events;

class DashboardPage {

    public static function init(): void {
        add_action('admin_menu', [self::class, 'registerMenu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('admin_head', [self::class, 'hideSecondaryMenuItems']);
        add_action('wp_ajax_searchjet_save_api_key', [self::class, 'ajaxSaveApiKey']);

        DashboardChatbot::init();
    }

    public static function hideSecondaryMenuItems(): void {
        ?>
        <style>
        #adminmenu a[href*="page=searchjet-bulk-reindex"],
        #adminmenu a[href*="page=searchjet-index-settings"],
        #adminmenu a[href*="page=searchjet-index-health"],
        #adminmenu a[href*="page=searchjet-automations"],
        #adminmenu a[href*="page=searchjet-agents"],
        #adminmenu a[href*="page=searchjet-task-history"],
        #adminmenu a[href*="page=searchjet-docs"] {
            display: none;
        }
        </style>
        <?php
    }

    public static function registerMenu(): void {
        add_menu_page(
            'SearchJet',
            'SearchJet',
            'manage_options',
            'searchjet',
            [self::class, 'render'],
            'dashicons-search',
            56
        );

        add_submenu_page(
            'searchjet',
            __('Dashboard', 'searchjet-instant-search'),
            __('Dashboard', 'searchjet-instant-search'),
            'manage_options',
            'searchjet',
            [self::class, 'render']
        );

        add_submenu_page(
            'searchjet',
            __('Settings', 'searchjet-instant-search'),
            __('Settings', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-settings',
            ['\SearchJet\Admin\SettingsPage', 'render']
        );

        add_submenu_page(
            'searchjet',
            __('Search UI', 'searchjet-instant-search'),
            __('Search UI', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-search-ui',
            ['\SearchJet\Admin\SearchUIPage', 'render']
        );

        add_submenu_page(
            'searchjet',
            __('Indexing', 'searchjet-instant-search'),
            __('Indexing', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-indexing',
            ['\SearchJet\Admin\IndexingPage', 'render']
        );

        add_submenu_page(
            'searchjet',
            __('Bulk Re-Index', 'searchjet-instant-search'),
            __('Bulk Re-Index', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-bulk-reindex',
            ['\SearchJet\Admin\BulkReindexPage', 'render_page']
        );

        add_submenu_page(
            'searchjet',
            __('Index Settings', 'searchjet-instant-search'),
            __('Index Settings', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-index-settings',
            ['\SearchJet\Admin\IndexSettingsPage', 'render_page']
        );

        add_submenu_page(
            'searchjet',
            __('Index Health', 'searchjet-instant-search'),
            __('Index Health', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-index-health',
            ['\SearchJet\Admin\IndexHealthPage', 'render_page']
        );

        add_submenu_page(
            'searchjet',
            __('Analytics', 'searchjet-instant-search'),
            __('Analytics', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-analytics',
            ['\SearchJet\Admin\EnhancedSettingsPage', 'renderAnalytics']
        );

        add_submenu_page(
            'searchjet',
            __('Automations', 'searchjet-instant-search'),
            __('Automations', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-automations',
            ['\SearchJet\Admin\AutomationPage', 'render']
        );

        add_submenu_page(
            'searchjet',
            __('AI Agents', 'searchjet-instant-search'),
            __('AI Agents', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-agents',
            ['\SearchJet\Admin\AgentsPage', 'render']
        );

        add_submenu_page(
            'searchjet',
            __('Task History', 'searchjet-instant-search'),
            __('Task History', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-task-history',
            ['\SearchJet\Admin\TaskHistoryPage', 'render_page']
        );

        add_submenu_page(
            'searchjet',
            __('Documentation', 'searchjet-instant-search'),
            __('Documentation', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-docs',
            ['\SearchJet\Admin\DocumentationPage', 'render']
        );
    }

    public static function enqueueAssets($hook): void {
        if ($hook !== 'toplevel_page_searchjet') return;

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
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
        }

        // Auto-validate if key exists but client_info is missing
        $api_key = get_option('searchjet_hosted_api_key', '');
        $client_info = get_option('searchjet_client_info', []);
        if (!empty($api_key) && empty($client_info)) {
            $info = \SearchJet\API\SearchJetRemoteClient::fetchClientInfo($api_key, false);
            if (is_array($info)) {
                update_option('searchjet_client_info', $info);
                $client_info = $info;
            }
        }
        $is_connected = !empty($client_info) && !empty($api_key);
        $limits = get_transient('searchjet_usage_limits') ?: [];
        // Keep limits in sync with client_info
        if (empty($limits) && !empty($client_info['usage_limits'])) {
            $limits = $client_info['usage_limits'];
        }

        delete_transient('searchjet_document_count');
        $indexed_count = SearchJetRemoteClient::fetchDocumentCount();
        if ($indexed_count === null) {
            usleep(500000);
            $indexed_count = SearchJetRemoteClient::fetchDocumentCount();
        }
        $indexed_count = $indexed_count ?? 0;
        $plan = strtolower($client_info['plan'] ?? 'free');
        $is_free_plan = $plan === 'free';

        $task_history = TaskStatusTracker::getTaskHistory(5);
        $task_stats = TaskStatusTracker::getStatistics();
        $last_reindex = get_option('searchjet_last_reindex_time', 0);
        $features = PlanManager::get_all_features();

        $searches_today = get_transient('searchjet_searches_today') ?: 0;

        $daily_health = null;
        if (class_exists(Events::class) && method_exists(Events::class, 'dailyHealthSnapshot')) {
            $daily_health = Events::dailyHealthSnapshot();
        }

        $has_woocommerce = class_exists('WooCommerce');
        $reindex_age = $last_reindex ? human_time_diff($last_reindex) : null;

        $search_used = $limits['used_search_queries'] ?? 0;
        $search_limit = \SearchJet\Services\PlanManager::get_limit('search_queries_per_month');
        $search_pct = $search_limit ? round(($search_used / $search_limit) * 100) : 0;

        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Dashboard', 'searchjet-instant-search'); ?></h1>
                        <p class="text-blue-100 text-xl"><?php esc_html_e('SearchJet AI-Powered Instant Search', 'searchjet-instant-search'); ?></p>
                    </div>
                    <div class="searchjet-connection-status flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-lg border-2 border-yellow-400">
                        <div class="searchjet-connection-indicator w-2 h-2 bg-green-500 rounded-full animate-pulse <?php echo $is_connected ? 'connected' : ''; ?>"></div>
                        <div>
                            <strong class="text-sm text-[#152A53]"><?php echo $is_connected ? esc_html__('Connected', 'searchjet-instant-search') : esc_html__('Not Connected', 'searchjet-instant-search'); ?></strong>
                            <?php if ($is_connected): ?>
                                <div class="text-xs text-slate-500"><?php printf(esc_html__('Plan: %s', 'searchjet-instant-search'), esc_html(PlanManager::get_plan_display_name($plan))); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">

                <?php if (!$is_connected): ?>
                    <div class="searchjet-setup-banner">
                        <div class="searchjet-setup-content">
                            <h2><?php esc_html_e('Get Started with SearchJet', 'searchjet-instant-search'); ?></h2>
                            <p><?php esc_html_e('Enable AI-powered instant search in 2 minutes', 'searchjet-instant-search'); ?></p>
                            <button type="button" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg" id="searchjet-start-setup">
                                <?php esc_html_e('Start Setup Wizard', 'searchjet-instant-search'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($is_connected): ?>

                    <!-- System Status Bar -->
                    <div class="searchjet-card" style="display:flex;flex-wrap:wrap;gap:16px;padding:16px 24px;align-items:center;">
                        <div style="display:flex;align-items:center;gap:8px;font-size:14px;">
                            <span style="width:10px;height:10px;border-radius:50%;display:inline-block;background:<?php echo $is_connected ? '#28a745' : '#dc3545'; ?>;"></span>
                            <strong><?php echo $is_connected ? esc_html__('API Connected', 'searchjet-instant-search') : esc_html__('API Disconnected', 'searchjet-instant-search'); ?></strong>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;font-size:14px;color:#6c757d;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-file-gray.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                            <span><?php printf(esc_html__('%d Indexed', 'searchjet-instant-search'), (int) $indexed_count); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;font-size:14px;color:#6c757d;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-refresh-gray.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                            <span><?php echo $reindex_age ? esc_html(sprintf(__('Last reindex: %s ago', 'searchjet-instant-search'), $reindex_age)) : esc_html__('Not yet indexed', 'searchjet-instant-search'); ?></span>
                        </div>
                        <?php if ($has_woocommerce): ?>
                            <div style="display:flex;align-items:center;gap:8px;font-size:14px;background:#d4edda;color:#155724;padding:4px 10px;border-radius:12px;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-check-green-dark.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                                <span><?php esc_html_e('WooCommerce', 'searchjet-instant-search'); ?></span>
                            </div>
                        <?php endif; ?>
                        <div style="flex:1;text-align:right;font-size:13px;color:#6c757d;">
                            <?php printf(esc_html(_n('%d active task', '%d active tasks', (int) ($task_stats['total_active'] ?? 0), 'searchjet-instant-search')), (int) ($task_stats['total_active'] ?? 0)); ?>
                        </div>
                    </div>

                    <?php if ($indexed_count === 0): ?>
                        <div class="bg-gradient-to-r from-blue-900 to-blue-600 rounded-xl p-6 mb-8 text-white">
                            <h3 class="text-xl text-white font-bold mb-4"><?php esc_html_e('Great! You\'re connected to SearchJet', 'searchjet-instant-search'); ?></h3>
                            <p class="text-blue-100 mb-6"><?php esc_html_e('Complete these steps to activate your search:', 'searchjet-instant-search'); ?></p>
                            <div class="space-y-4">
                                <div class="flex items-center space-x-4 bg-white/20 rounded-lg p-4">
                                    <span class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-check-white.svg'); ?>" alt="" width="16" height="16" style="display:block;"></span>
                                    <span class="text-white/90"><?php esc_html_e('Connected to SearchJet', 'searchjet-instant-search'); ?></span>
                                </div>
                                <div class="flex items-center justify-between bg-white/10 rounded-lg p-4">
                                    <div class="flex items-center space-x-4">
                                        <span class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-clock-white.svg'); ?>" alt="" width="16" height="16" style="display:block;"></span>
                                        <span class="text-white/90"><?php esc_html_e('Index your content', 'searchjet-instant-search'); ?></span>
                                    </div>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-indexing')); ?>" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-50 transition-colors">
                                        <?php esc_html_e('Index Now', 'searchjet-instant-search'); ?>
                                    </a>
                                </div>
                                <div class="flex items-center space-x-4 bg-white/10 rounded-lg p-4">
                                    <span class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-clock-white.svg'); ?>" alt="" width="16" height="16" style="display:block;"></span>
                                    <span class="text-white/90"><?php esc_html_e('Test search functionality', 'searchjet-instant-search'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    if ($is_free_plan) {
                        echo '<div class="searchjet-alert searchjet-alert-info bg-gradient-to-r from-amber-100 via-yellow-100 to-orange-200 border border-amber-400 rounded-2xl p-5 mb-6 shadow-lg">';
                        echo '<div class="flex flex-col items-center gap-1"><strong class="text-amber-950 text-xl font-extrabold tracking-wide">' . esc_html__('Using Free Tier:', 'searchjet-instant-search') . '</strong><br>';
                        echo '<span class="text-amber-800">' . esc_html__('You are limited to 1,000 search queries and 200 indexed pages per month. For more features, upgrade at ', 'searchjet-instant-search');
                        echo '<a href="https://searchjetengine.com/pricing" target="_blank" class="font-semibold text-amber-700 hover:text-amber-900 underline decoration-amber-400 hover:decoration-amber-600">SearchJet Pricing</a>.</span></div>';
                        echo '</div>';
                    }
                    ?>

                    <?php self::renderOngoingGuidance($limits, $indexed_count, $client_info, $features); ?>

                    <!-- Feature Usage Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-xl p-6 text-center border border-blue-200 shadow-lg hover:shadow-xl transition-shadow">
                            <div class="text-4xl font-bold mb-2 bg-gradient-to-r from-blue-900 to-blue-600 bg-clip-text text-transparent">
                                <?php echo number_format($search_used); ?>
                            </div>
                            <div class="text-gray-600 text-sm mb-3"><?php esc_html_e('Searches This Month', 'searchjet-instant-search'); ?></div>
                            <?php if ($search_limit): ?>
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                    <div class="bg-gradient-to-r from-blue-900 to-blue-600 h-2 rounded-full" style="width: <?php echo (int) min(100, $search_pct); ?>%"></div>
                                </div>
                                <div class="text-xs <?php echo $search_pct > 80 ? 'text-red-500 font-semibold' : 'text-gray-500'; ?>">
                                    <?php printf(esc_html__('%s of %s used', 'searchjet-instant-search'), number_format($search_used), number_format($search_limit)); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-gray-500"><?php esc_html_e('Unlimited', 'searchjet-instant-search'); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-white rounded-xl p-6 text-center border border-blue-200 shadow-lg hover:shadow-xl transition-shadow">
                            <div class="text-4xl font-bold mb-2 bg-gradient-to-r from-blue-900 to-blue-600 bg-clip-text text-transparent">
                                <?php echo number_format($indexed_count); ?>
                            </div>
                            <div class="text-gray-600 text-sm mb-3"><?php esc_html_e('Indexed Documents', 'searchjet-instant-search'); ?></div>
                            <?php
                            $max_pages = \SearchJet\Services\PlanManager::get_limit('max_indexed_pages');
                            ?>
                            <?php if ($indexed_count === 0): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-indexing')); ?>" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-blue-700 transition-colors inline-block">
                                    <?php esc_html_e('Start Indexing', 'searchjet-instant-search'); ?>
                                </a>
                            <?php elseif ($max_pages > 0): ?>
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                    <div class="bg-gradient-to-r from-blue-900 to-blue-600 h-2 rounded-full" style="width: <?php echo (int) min(100, ($indexed_count / $max_pages) * 100); ?>%"></div>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php printf(esc_html__('%s of %s', 'searchjet-instant-search'), number_format($indexed_count), number_format($max_pages)); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-gray-500"><?php esc_html_e('Unlimited', 'searchjet-instant-search'); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-white rounded-xl p-6 text-center border border-blue-200 shadow-lg hover:shadow-xl transition-shadow">
                            <div class="text-4xl font-bold mb-2 bg-gradient-to-r from-blue-900 to-blue-600 bg-clip-text text-transparent">
                                <?php echo (int) \SearchJet\Services\PlanManager::get_reindexes_today(); ?>
                            </div>
                            <div class="text-gray-600 text-sm mb-3"><?php esc_html_e('Reindexes Today', 'searchjet-instant-search'); ?></div>
                            <?php $reindex_limit = \SearchJet\Services\PlanManager::get_limit('reindexing_per_day'); ?>
                            <?php if ($reindex_limit > 0): ?>
                                <div class="text-xs text-gray-500">
                                    <?php printf(esc_html__('Limit: %s/day', 'searchjet-instant-search'), esc_html($reindex_limit)); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($reindex_age): ?>
                                <div class="text-xs text-gray-400 mt-1"><?php printf(esc_html__('Last: %s ago', 'searchjet-instant-search'), esc_html($reindex_age)); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-white rounded-xl p-6 text-center border border-blue-200 shadow-lg hover:shadow-xl transition-shadow">
                            <div class="text-4xl font-bold mb-2" style="color:#2563eb;">
                                <?php echo esc_html(ucfirst(PlanManager::get_plan_display_name($plan))); ?>
                            </div>
                            <div class="text-gray-600 text-sm mb-3"><?php esc_html_e('Current Plan', 'searchjet-instant-search'); ?></div>
                            <div class="text-xs text-gray-500">
                                <?php echo $is_free_plan
                                    ? '<a href="https://searchjetengine.com/pricing" target="_blank" class="text-blue-600 font-medium hover:underline">' . esc_html__('Upgrade for more', 'searchjet-instant-search') . '</a>'
                                    : esc_html__('All features active', 'searchjet-instant-search'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <?php if (!empty($task_history)): ?>
                    <div class="searchjet-card mb-8">
                        <h2 style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-clock-indigo.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                            <?php esc_html_e('Recent Activity', 'searchjet-instant-search'); ?>
                        </h2>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <?php foreach ($task_history as $task):
                                $status_icon = '';
                                $status_color = '';
                                switch ($task['status']) {
                                    case 'completed': $status_icon = SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-check-green.svg'; $status_color = '#28a745'; break;
                                    case 'failed': $status_icon = SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-x-red.svg'; $status_color = '#dc3545'; break;
                                    case 'processing': $status_icon = SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-clock-amber.svg'; $status_color = '#ffc107'; break;
                                    case 'pending': $status_icon = SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-dot-gray.svg'; $status_color = '#6c757d'; break;
                                    default: $status_icon = SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-dot-gray.svg'; $status_color = '#6c757d'; break;
                                }
                                $time_ago = isset($task['updated_at']) ? human_time_diff($task['updated_at']) : (isset($task['created_at']) ? human_time_diff($task['created_at']) : '');
                                $type_label = ucfirst($task['type'] ?? __('unknown', 'searchjet-instant-search'));
                            ?>
                                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f8f9fa;border-radius:8px;border-left:3px solid <?php echo esc_attr($status_color); ?>;">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;"><img src="<?php echo esc_url($status_icon); ?>" alt="" width="16" height="16" style="display:block;"></span>
                                    <div style="flex:1;">
                                        <strong style="font-size:14px;color:#212529;"><?php echo esc_html($type_label); ?></strong>
                                        <span style="font-size:13px;color:#6c757d;margin-left:8px;"><?php echo $time_ago ? esc_html(sprintf(__('%s ago', 'searchjet-instant-search'), $time_ago)) : ''; ?></span>
                                    </div>
                                    <span style="font-size:12px;padding:2px 8px;border-radius:10px;background:<?php echo esc_attr($status_color); ?>20;color:<?php echo esc_attr($status_color); ?>;font-weight:500;">
                                        <?php echo esc_html(ucfirst($task['status'])); ?>
                                    </span>
                                    <?php if (!empty($task['metadata']['post_count'])): ?>
                                        <span style="font-size:12px;color:#6c757d;"><?php printf(esc_html__('%d posts', 'searchjet-instant-search'), (int) $task['metadata']['post_count']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:12px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-task-history')); ?>" class="searchjet-btn searchjet-btn-sm searchjet-btn-secondary">
                                <?php esc_html_e('View All Tasks', 'searchjet-instant-search'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Search Health -->
                    <?php if ($daily_health && !empty($daily_health['payload'])): $health = $daily_health['payload']; ?>
                    <div class="searchjet-card mb-8">
                        <h2 style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-zap-indigo.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                            <?php esc_html_e('Search Health', 'searchjet-instant-search'); ?>
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white rounded-lg p-4 border border-gray-100 text-center">
                                <div class="text-2xl font-bold text-blue-600"><?php echo esc_html($health['total_searches'] ?? 0); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php esc_html_e('Total Searches', 'searchjet-instant-search'); ?></div>
                            </div>
                            <div class="bg-white rounded-lg p-4 border border-gray-100 text-center">
                                <div class="text-2xl font-bold text-amber-500"><?php echo esc_html($health['zero_results'] ?? 0); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php esc_html_e('Zero-Result Queries', 'searchjet-instant-search'); ?></div>
                            </div>
                            <div class="bg-white rounded-lg p-4 border border-gray-100 text-center">
                                <div class="text-2xl font-bold text-green-600"><?php echo esc_html($searches_today); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php esc_html_e('Searches Today', 'searchjet-instant-search'); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($health['top_queries'])): ?>
                            <div style="margin-top:16px;">
                                <strong style="font-size:14px;color:#212529;"><?php esc_html_e('Top Queries', 'searchjet-instant-search'); ?></strong>
                                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                                    <?php foreach (array_slice($health['top_queries'], 0, 5) as $q): ?>
                                        <span style="padding:4px 12px;background:#e9ecef;border-radius:12px;font-size:13px;color:#495057;"><?php echo esc_html(is_string($q) ? $q : ($q['query'] ?? '')); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>

                <!-- Quick Access -->
                <div class="searchjet-card mb-8">
                    <h2 style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-chart-indigo.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                        <?php esc_html_e('Quick Access', 'searchjet-instant-search'); ?>
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-bulk-reindex')); ?>" class="searchjet-quick-card">
                            <div class="searchjet-quick-icon" style="background:#e8f4fd;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-refresh-blue.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div>
                                <strong><?php esc_html_e('Bulk Re-Index', 'searchjet-instant-search'); ?></strong>
                                <span><?php esc_html_e('Full or targeted content reindexing', 'searchjet-instant-search'); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-settings')); ?>" class="searchjet-quick-card">
                            <div class="searchjet-quick-icon" style="background:#f0f0ff;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-sliders-indigo.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div>
                                <strong><?php esc_html_e('Index Settings', 'searchjet-instant-search'); ?></strong>
                                <span><?php esc_html_e('Configure post types, taxonomies & fields', 'searchjet-instant-search'); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-health')); ?>" class="searchjet-quick-card">
                            <div class="searchjet-quick-icon" style="background:#fff0f0;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-zap-red.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div>
                                <strong><?php esc_html_e('Index Health', 'searchjet-instant-search'); ?></strong>
                                <span><?php esc_html_e('Monitor index status & errors', 'searchjet-instant-search'); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-automations')); ?>" class="searchjet-quick-card">
                            <div class="searchjet-quick-icon" style="background:#fff8e8;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-chart-amber.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div>
                                <strong><?php esc_html_e('Automations', 'searchjet-instant-search'); ?></strong>
                                <span><?php esc_html_e('Workflows, n8n & Zapier integrations', 'searchjet-instant-search'); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-agents')); ?>" class="searchjet-quick-card">
                            <div class="searchjet-quick-icon" style="background:#f0fdf4;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-pin-green.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div>
                                <strong><?php esc_html_e('AI Agents', 'searchjet-instant-search'); ?></strong>
                                <span><?php esc_html_e('Manage AI search & content agents', 'searchjet-instant-search'); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-task-history')); ?>" class="searchjet-quick-card">
                            <div class="searchjet-quick-icon" style="background:#f5f0ff;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-clock-purple.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div>
                                <strong><?php esc_html_e('Task History', 'searchjet-instant-search'); ?></strong>
                                <span><?php esc_html_e('View past reindex & automation tasks', 'searchjet-instant-search'); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-docs')); ?>" class="searchjet-quick-card">
                            <div class="searchjet-quick-icon" style="background:#f8f9fa;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-chip-gray.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div>
                                <strong><?php esc_html_e('Documentation', 'searchjet-instant-search'); ?></strong>
                                <span><?php esc_html_e('Guides, API reference & changelog', 'searchjet-instant-search'); ?></span>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- How It Works Button -->
                <div class="searchjet-card mb-8" style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-search-navy.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                        <span style="font-size:14px;font-weight:600;color:#152A53;"><?php esc_html_e('How It Works', 'searchjet-instant-search'); ?></span>
                        <span style="font-size:13px;color:#6c757d;"><?php esc_html_e('See how AI-powered search works on your site', 'searchjet-instant-search'); ?></span>
                    </div>
                    <button type="button" class="searchjet-btn searchjet-btn-secondary" id="searchjet-show-how-it-works" style="white-space:nowrap;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-help-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('View Demo', 'searchjet-instant-search'); ?>
                    </button>
                </div>

                <!-- Environment Info -->
                <div class="searchjet-card mb-8">
                    <h2 style="display:flex;align-items:center;gap:8px;cursor:pointer;" onclick="var e=document.getElementById('env-details');e.style.display=e.style.display==='none'?'block':'none';">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-help-indigo.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                        <?php esc_html_e('System Information', 'searchjet-instant-search'); ?>
                        <span style="font-size:12px;color:#6c757d;font-weight:400;"><?php esc_html_e('(click to expand)', 'searchjet-instant-search'); ?></span>
                    </h2>
                    <div id="env-details" style="display:none;margin-top:12px;">
                        <table class="searchjet-table" style="width:100%;border-collapse:collapse;">
                            <tbody>
                                <tr><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#6c757d;width:200px;"><?php esc_html_e('Site URL', 'searchjet-instant-search'); ?></td><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#212529;"><?php echo esc_html(home_url('/')); ?></td></tr>
                                <tr><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#6c757d;"><?php esc_html_e('WordPress', 'searchjet-instant-search'); ?></td><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#212529;"><span class="searchjet-debug-badge"><?php echo esc_html(get_bloginfo('version')); ?></span></td></tr>
                                <tr><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#6c757d;"><?php esc_html_e('PHP', 'searchjet-instant-search'); ?></td><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#212529;"><span class="searchjet-debug-badge"><?php echo esc_html(PHP_VERSION); ?></span></td></tr>
                                <tr><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#6c757d;"><?php esc_html_e('Plugin Version', 'searchjet-instant-search'); ?></td><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#212529;"><span class="searchjet-debug-badge"><?php echo esc_html(SEARCHJET_VERSION); ?></span></td></tr>
                                <tr><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#6c757d;"><?php esc_html_e('WooCommerce', 'searchjet-instant-search'); ?></td><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#212529;"><?php echo $has_woocommerce ? '<span style="color:#28a745;"><img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-status-check-green.svg') . '" alt="" width="14" height="14" style="display:inline-block;vertical-align:-2px;margin-right:4px;"> ' . esc_html__('Active', 'searchjet-instant-search') . '</span>' : '<span style="color:#6c757d;">' . esc_html__('Not detected', 'searchjet-instant-search') . '</span>'; ?></td></tr>
                                <?php if ($is_connected): ?>
                                <tr><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#6c757d;"><?php esc_html_e('Plan', 'searchjet-instant-search'); ?></td><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#212529;"><span class="searchjet-status <?php echo $is_free_plan ? 'searchjet-status-warning' : 'searchjet-status-connected'; ?>"><?php echo esc_html(PlanManager::get_plan_display_name($plan)); ?></span></td></tr>
                                <tr><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#6c757d;"><?php esc_html_e('Task Status', 'searchjet-instant-search'); ?></td><td style="padding:8px 12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#212529;"><?php printf(esc_html__('%d active, %d completed', 'searchjet-instant-search'), (int) ($task_stats['total_active'] ?? 0), (int) ($task_stats['total_completed'] ?? 0)); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php DashboardScripts::renderInlineScripts(); ?>
        <?php
    }

    public static function ajaxSaveApiKey(): void {
        check_ajax_referer('searchjet_wizard_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        if (empty($key)) {
            wp_send_json_error(['message' => 'API key is empty']);
        }

        update_option('searchjet_hosted_api_key', $key);
        \SearchJet\Admin\EnhancedSettingsPage::fetchAndPersistClientInfo($key);
        $client_info = get_option('searchjet_client_info', []);

        wp_send_json_success([
            'connected' => !empty($client_info),
        ]);
    }

    private static function renderOngoingGuidance($limits, $indexed_count, $client_info, $features = []): void {
        $search_used = $limits['used_search_queries'] ?? 0;
        $search_limit = \SearchJet\Services\PlanManager::get_limit('search_queries_per_month') ?: null;
        $plan = $client_info['plan'] ?? 'free';
        $searches_today = get_transient('searchjet_searches_today') ?: 0;

        $guidance_items = [];

        if ($search_limit && $search_used > ($search_limit * 0.8)) {
            $guidance_items[] = [
                'type' => 'warning',
                'icon' => '<img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-alert-amber.svg') . '" alt="" width="24" height="24" style="display:block;">',
                'title' => __('High Usage Alert', 'searchjet-instant-search'),
                'message' => sprintf(__('You\'ve used %d%% of your monthly search quota. Consider upgrading your plan.', 'searchjet-instant-search'), round(($search_used / $search_limit) * 100)),
                'action' => ['text' => __('Upgrade Plan', 'searchjet-instant-search'), 'url' => 'https://searchjetengine.com/pricing']
            ];
        }

        if ($searches_today > 50) {
            $guidance_items[] = [
                'type' => 'success',
                'icon' => '<img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-trending-green2.svg') . '" alt="" width="24" height="24" style="display:block;">',
                'title' => __('Great Search Activity!', 'searchjet-instant-search'),
                'message' => sprintf(__('Your site had %d searches today. Users are actively engaging with your search.', 'searchjet-instant-search'), $searches_today),
                'action' => ['text' => __('View Analytics', 'searchjet-instant-search'), 'url' => admin_url('admin.php?page=searchjet-analytics')]
            ];
        }

        if ($indexed_count > 0 && $searches_today < 5) {
            $guidance_items[] = [
                'type' => 'info',
                'icon' => '<img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-pin-indigo.svg') . '" alt="" width="24" height="24" style="display:block;">',
                'title' => __('Boost Search Visibility', 'searchjet-instant-search'),
                'message' => __('Low search activity detected. Consider adding a prominent search box or promoting search features.', 'searchjet-instant-search'),
                'action' => ['text' => __('Customization Tips', 'searchjet-instant-search'), 'url' => admin_url('admin.php?page=searchjet-search-ui')]
            ];
        }

        if ($plan === 'free' && $search_used > 500) {
            $guidance_items[] = [
                'type' => 'info',
                'icon' => '<img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-edit-indigo.svg') . '" alt="" width="24" height="24" style="display:block;">',
                'title' => __('Ready to Scale?', 'searchjet-instant-search'),
                'message' => __('Your search usage suggests you might benefit from a paid plan.', 'searchjet-instant-search'),
                'action' => ['text' => __('Compare Plans', 'searchjet-instant-search'), 'url' => 'https://searchjetengine.com/pricing']
            ];
        }

        $last_reindex = get_option('searchjet_last_reindex_time', 0);
        if ($last_reindex && (time() - $last_reindex) > (7 * 24 * 60 * 60)) {
            $guidance_items[] = [
                'type' => 'info',
                'icon' => '<img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-refresh-indigo.svg') . '" alt="" width="24" height="24" style="display:block;">',
                'title' => __('Content Refresh Recommended', 'searchjet-instant-search'),
                'message' => __('It\'s been a week since your last reindex. Consider refreshing your search index.', 'searchjet-instant-search'),
                'action' => ['text' => __('Reindex Now', 'searchjet-instant-search'), 'url' => admin_url('admin.php?page=searchjet-indexing')]
            ];
        }

        if (empty($guidance_items)) {
            $tips = [
                __('Make your search interface match your brand with custom colors and themes.', 'searchjet-instant-search'),
                __('Track search queries and user behavior to optimize your content strategy.', 'searchjet-instant-search'),
                __('Set up automated workflows to optimize search performance.', 'searchjet-instant-search'),
            ];
            $guidance_items[] = [
                'type' => 'tip',
                'icon' => '<img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-shield-indigo.svg') . '" alt="" width="24" height="24" style="display:block;">',
                'title' => __('Customize Your Search', 'searchjet-instant-search'),
                'message' => $tips[array_rand($tips)],
                'action' => ['text' => __('Get Started', 'searchjet-instant-search'), 'url' => admin_url('admin.php?page=searchjet-search-ui')]
            ];
        }

        if (!empty($guidance_items)): ?>
            <div class="searchjet-guidance-section mb-8">
                <h3 style="font-size:18px;font-weight:600;margin-bottom:16px;color:#152A53;">
                    <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-pin-navy.svg'); ?>" alt="" width="22" height="22" style="display:inline-block;vertical-align:middle;margin-right:6px;">
                    <?php esc_html_e('Recommendations', 'searchjet-instant-search'); ?>
                </h3>
                <div class="searchjet-guidance-grid">
                    <?php foreach ($guidance_items as $item): ?>
                        <div class="searchjet-guidance-card searchjet-guidance-<?php echo esc_attr($item['type']); ?>">
                            <div class="searchjet-guidance-icon"><?php echo wp_kses_post($item['icon']); ?></div>
                            <div class="searchjet-guidance-content">
                                <h4 class="mb-1 text-[#152A53]"><?php echo esc_html($item['title']); ?></h4>
                                <p class="text-sm text-slate-600 mb-3"><?php echo esc_html($item['message']); ?></p>
                                <?php if (isset($item['action'])): ?>
                                    <a href="<?php echo esc_url($item['action']['url']); ?>"
                                       class="searchjet-btn searchjet-btn-sm searchjet-btn-secondary"
                                                                               <?php
                                        $url_host = wp_parse_url($item['action']['url'], PHP_URL_HOST);
                                        $site_host = wp_parse_url(admin_url(), PHP_URL_HOST);
                                        ?>
                                        <?php if ($url_host && $url_host !== $site_host): ?>target="_blank"<?php endif; ?>>
                                        <?php echo esc_html($item['action']['text']); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif;
    }

}
