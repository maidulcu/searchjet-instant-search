<?php
namespace SearchJet\Admin;

use SearchJet\API\SearchJetRemoteClient;

class EnhancedSettingsPage {

    public static function init() {
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_init', [self::class, 'maybeValidateApiKey']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('update_option_searchjet_hosted_api_key', [self::class, 'onApiKeyUpdated'], 10, 2);
        add_action('delete_option_searchjet_hosted_api_key', [self::class, 'onApiKeyDeleted']);
    }

    /**
     * Fallback: if we have a saved API key but no client_info, validate now.
     * This covers cases where the update_option hook didn't fire (e.g. same value re-saved).
     */
    public static function maybeValidateApiKey() {
        $api_key = trim((string) get_option('searchjet_hosted_api_key', ''));
        if (empty($api_key)) {
            return;
        }
        $client_info = get_option('searchjet_client_info', []);
        if (!empty($client_info)) {
            return; // Already validated
        }
        // Rate-limit: only retry once per 5 minutes to avoid hammering a failing API
        $retry_at = get_transient('searchjet_validate_retry_at');
        if ($retry_at !== false && $retry_at > time()) {
            return;
        }
        // Key exists but no client_info — try to validate
        error_log('[SearchJet] maybeValidateApiKey: key exists but client_info empty, validating...');
        self::fetchAndPersistClientInfo($api_key);
        $check = get_option('searchjet_client_info', []);
        error_log('[SearchJet] maybeValidateApiKey: client_info is now ' . (empty($check) ? 'EMPTY' : 'SET'));
        if (empty($check)) {
            // Validation failed — retry in 5 minutes
            set_transient('searchjet_validate_retry_at', time() + 300, 300);
        }
    }

    /**
     * Fires after searchjet_hosted_api_key is saved via the Settings API.
     * Validates the new key and persists client info.
     */
    public static function onApiKeyUpdated($old_value, $new_value) {
        $new_key = is_string($new_value) ? trim($new_value) : '';
        $old_key = is_string($old_value) ? trim($old_value) : '';

        if ($new_key === '' && $old_key !== '') {
            error_log('[SearchJet] onApiKeyUpdated: KEY WAS CLEARED');
        }

        // Key was cleared
        if ($new_key === '' && $old_key !== '') {
            self::clearClientState();
            return;
        }

        // Key was set or changed — clear stale state before validating
        if ($new_key !== '' && $new_key !== $old_key) {
            self::clearClientState();
            error_log('[SearchJet] API key updated, validating...');
            self::fetchAndPersistClientInfo($new_key);
            $check = get_option('searchjet_client_info', []);
            error_log('[SearchJet] After persist: client_info is ' . (empty($check) ? 'EMPTY' : 'SET'));
        }
    }

    public static function onApiKeyDeleted(): void {
        error_log('[SearchJet] onApiKeyDeleted: API KEY OPTION WAS DELETED! Backtrace: ' . wp_debug_backtrace_summary());
        self::clearClientState();
    }

    public static function enqueueAssets($hook): void {
        if ($hook !== 'searchjet_page_searchjet-analytics' && $hook !== 'searchjet_page_searchjet-settings') return;

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

    /**
     * Register settings for the connection / core settings page.
     *
     * Only options actually posted by the forms on this page belong in the
     * `searchjet_settings` group. Other options (Search UI customizations,
     * attributes, index filters, etc.) are registered under their own groups
     * so that saving one form can never wipe unrelated settings.
     */
    public static function registerSettings() {
        $settings = [
            'searchjet_hosted_api_key' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'searchjet_disable_search_mode' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_delete_data_on_uninstall' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
        ];

        foreach ($settings as $option => $args) {
            register_setting('searchjet_settings', $option, $args);
        }
    }

    public static function sanitizeAttributes($value) {
        $value = is_string($value) ? trim($value) : '';
        $parts = preg_split('/[\n,]+/', $value);
        $parts = array_values(array_filter(array_map('trim', (array)$parts), function($v){ return $v !== ''; }));
        return implode(', ', $parts);
    }

    public static function sanitizeUiOptions($value) {
        if (!is_array($value)) {
            $value = [];
        }

        $defaults = [
            'show_thumbnail' => 1,
            'show_price' => 1,
            'show_rating' => 1,
            'show_stock' => 1,
            'show_category' => 1,
            'show_excerpts' => 1,
            'highlight_term' => 1,
            'view_all_link' => 1,
        ];

        $sanitized = [];
        foreach ($defaults as $key => $default) {
            $sanitized[$key] = isset($value[$key]) ? absint($value[$key]) : $default;
        }
        return $sanitized;
    }

    private static function clearClientState() {
        delete_transient('searchjet_usage_limits');
        delete_option('searchjet_client_info');
        delete_option('searchjet_site_id');
    }

    private static function persistClientInfo(array $client_info, $fallback_site_id = 0) {
        update_option('searchjet_client_info', $client_info);
        if (!empty($client_info['usage_limits'])) {
            set_transient('searchjet_usage_limits', $client_info['usage_limits'], 300);
        }
        if (!empty($client_info['site_id'])) {
            update_option('searchjet_site_id', (int) $client_info['site_id']);
        } elseif (!empty($fallback_site_id)) {
            update_option('searchjet_site_id', (int) $fallback_site_id);
        }
    }

    public static function fetchAndPersistClientInfo($api_key, $fallback_site_id = 0) {
        if (empty($api_key)) { return; }
        $client_info = SearchJetRemoteClient::fetchClientInfo($api_key, true);
        if (is_array($client_info)) {
            error_log('[SearchJet] fetchAndPersistClientInfo: API returned valid client info, persisting.');
            self::persistClientInfo($client_info, $fallback_site_id);
        } else {
            error_log('[SearchJet] fetchAndPersistClientInfo: fetchClientInfo returned null — API unreachable or invalid key.');
        }
    }

    /**
     * Public helper: refresh client_info and usage_limits from the API.
     * Called after reindex to get updated usage counts.
     */
    public static function refreshFromApi() {
        $api_key = trim((string) get_option('searchjet_hosted_api_key', ''));
        if (!empty($api_key)) {
            self::fetchAndPersistClientInfo($api_key);
        }
    }

    public static function normalizeSearchAnalytics($analytics) {
        if (!is_array($analytics)) {
            return [];
        }

        $normalized = [];
        $key_map = [
            'top_search_queries' => 'top_search_queries',
            'queries' => 'top_search_queries',
            'search_queries' => 'top_search_queries',
            'zero_results' => 'zero_result_queries',
            'zero_result_queries' => 'zero_result_queries',
            'no_results' => 'zero_result_queries',
            'daily_volume' => 'daily_volume',
            'daily_searches' => 'daily_volume',
            'search_volume' => 'daily_volume',
        ];

        foreach ($key_map as $source_key => $target_key) {
            if (isset($analytics[$source_key]) && !isset($normalized[$target_key])) {
                $normalized[$target_key] = $analytics[$source_key];
            }
        }

        return $normalized;
    }

    public static function getLocalSearchAnalytics() {
        $backlog = get_option('searchjet_log_backlog', []);
        if (empty($backlog)) {
            return null;
        }

        $query_counts = [];
        $zero_results = [];
        $daily_volume = [];

        foreach ($backlog as $entry) {
            $query = $entry['query'] ?? '';
            $had_results = ($entry['result_count'] ?? 0) > 0;
            $date = substr($entry['timestamp'] ?? '', 0, 10);

            if ($query) {
                $query_counts[$query] = ($query_counts[$query] ?? 0) + 1;
                if (!$had_results) {
                    $zero_results[$query] = ($zero_results[$query] ?? 0) + 1;
                }
            }
            if ($date) {
                $daily_volume[$date] = ($daily_volume[$date] ?? 0) + 1;
            }
        }

        arsort($query_counts);
        arsort($zero_results);
        ksort($daily_volume);

        return [
            'top_search_queries' => self::normalizeQueryList(array_slice($query_counts, 0, 10, true)),
            'zero_result_queries' => self::normalizeQueryList(array_slice($zero_results, 0, 10, true)),
            'daily_volume' => $daily_volume,
        ];
    }

    public static function normalizeQueryList($items) {
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $key => $value) {
            if (is_string($value)) {
                $result[] = ['query' => $value, 'count' => 1];
            } elseif (is_numeric($value)) {
                $result[] = ['query' => (string) $key, 'count' => (int) $value];
            } elseif (is_array($value)) {
                $result[] = [
                    'query' => $value['query'] ?? $value['term'] ?? $value['search'] ?? $value['text'] ?? (string) $key,
                    'count' => (int) ($value['count'] ?? $value['hits'] ?? $value['total'] ?? 1),
                ];
            }
        }
        return $result;
    }

    public static function renderAnalytics() {
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
        // Refresh usage limits from client_info
        if (!empty($client_info['usage_limits'])) {
            set_transient('searchjet_usage_limits', $client_info['usage_limits'], 300);
        }
        $limits = get_transient('searchjet_usage_limits') ?: [];
        if (empty($limits) && !empty($client_info['usage_limits'])) {
            $limits = $client_info['usage_limits'];
        }

        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg" style="position: relative; overflow: hidden;">
                <div style="position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
                <div style="position: absolute; bottom: -40px; right: 80px; width: 80px; height: 80px; background: rgba(255,255,255,0.03); border-radius: 50%;"></div>
                <div class="max-w-6xl mx-auto" style="position: relative; z-index: 1;">
                    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 4px;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-chart-white.svg'); ?>" alt="" width="32" height="32" style="display:block;">
                        <h1 class="text-3xl text-white"><?php esc_html_e('Analytics', 'searchjet-instant-search'); ?></h1>
                    </div>
                    <p class="text-blue-100 text-xl"><?php esc_html_e('Search performance and usage statistics', 'searchjet-instant-search'); ?></p>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">
                <?php if (!$is_connected): ?>
                    <div class="searchjet-alert searchjet-alert-info" style="margin-top: 24px;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-info-teal.svg'); ?>" alt="" width="20" height="20" style="flex-shrink:0; margin-top:2px;display:block;">
                        <div>
                            <strong><?php esc_html_e('Not Connected', 'searchjet-instant-search'); ?></strong><br>
                            <?php esc_html_e('Connect your API key in Settings to view analytics.', 'searchjet-instant-search'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-settings')); ?>" style="margin-left: 4px;"><?php esc_html_e('Go to Settings', 'searchjet-instant-search'); ?> &rarr;</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    $indexed_count = \SearchJet\API\SearchJetRemoteClient::fetchDocumentCount();
                    $indexed_count = $indexed_count ?? 0;
                    $reindexes_today = \SearchJet\Services\PlanManager::get_reindexes_today();
                    $reindex_limit = \SearchJet\Services\PlanManager::get_limit('reindexing_per_day');
                    $current_plan = ucfirst($client_info['plan'] ?? __('Free', 'searchjet-instant-search'));
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-top: 24px; margin-bottom: 32px;">
                        <div class="searchjet-stat-card">
                            <div class="sj-stat-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-search-indigo.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div class="searchjet-stat-number"><?php echo number_format($limits['used_search_queries'] ?? 0); ?></div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Searches This Month', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-stat-card">
                            <div class="sj-stat-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-file-indigo.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div class="searchjet-stat-number"><?php echo number_format($indexed_count); ?></div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Indexed Documents', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-stat-card">
                            <div class="sj-stat-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-refresh-indigo.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div class="searchjet-stat-number"><?php echo number_format($reindexes_today); ?> <span style="font-size: 16px; font-weight: 400; color: #94a3b8;">/ <?php echo -1 === $reindex_limit ? '∞' : number_format($reindex_limit); ?></span></div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Reindexes Today', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-stat-card">
                            <div class="sj-stat-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-layers-indigo.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div class="searchjet-stat-number" style="color: #2563eb;"><?php echo esc_html($current_plan); ?></div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Current Plan', 'searchjet-instant-search'); ?></div>
                        </div>
                    </div>

                    <?php
                    $analytics = SearchJetRemoteClient::fetchSearchAnalytics($api_key ?? '');
                    if (!is_array($analytics) || empty($analytics)) {
                        $analytics = self::getLocalSearchAnalytics();
                    }
                    $analytics = self::normalizeSearchAnalytics($analytics ?: []);

                    $search_icon = '<img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-search-indigo.svg') . '" alt="" width="18" height="18" style="display:block;flex-shrink:0;">';
                    $alert_icon = '<img src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-info-indigo.svg') . '" alt="" width="18" height="18" style="display:block;flex-shrink:0;">';
                    $query_svg = '<img class="sj-query-icon" src="' . esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/enhanced-chevron-gray.svg') . '" alt="" width="16" height="16" style="display:block;">';

                    if (!empty($analytics['top_search_queries'])): ?>
                        <div class="searchjet-card">
                            <div class="sj-card-header">
                                <h2><?php echo wp_kses_post($search_icon); ?><?php esc_html_e('Top Search Queries', 'searchjet-instant-search'); ?></h2>
                                <span class="sj-card-badge"><?php echo count($analytics['top_search_queries']); ?> <?php esc_html_e('queries', 'searchjet-instant-search'); ?></span>
                            </div>
                            <div class="searchjet-analytics-list" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                                <?php foreach ($analytics['top_search_queries'] as $index => $item): ?>
                                    <div class="searchjet-analytics-item">
                                        <span class="sj-rank"><?php echo (int) $index + 1; ?></span>
                                        <div class="sj-query-wrap">
                                            <?php echo wp_kses_post($query_svg); ?>
                                            <span class="query"><?php echo esc_html($item['query'] ?? ''); ?></span>
                                        </div>
                                        <span class="count"><?php echo number_format($item['count'] ?? 0); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="searchjet-card">
                            <div class="sj-card-header">
                                <h2><?php echo wp_kses_post($search_icon); ?><?php esc_html_e('Top Search Queries', 'searchjet-instant-search'); ?></h2>
                                <span class="sj-card-badge"><?php esc_html_e('sample', 'searchjet-instant-search'); ?></span>
                            </div>
                            <div class="searchjet-analytics-list" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                                <?php $sample_queries = [
                                    ['query' => __('blue running shoes', 'searchjet-instant-search'), 'count' => 47],
                                    ['query' => __('wireless headphones', 'searchjet-instant-search'), 'count' => 35],
                                    ['query' => __('organic coffee beans', 'searchjet-instant-search'), 'count' => 28],
                                    ['query' => __('yoga mat premium', 'searchjet-instant-search'), 'count' => 22],
                                    ['query' => __('leather messenger bag', 'searchjet-instant-search'), 'count' => 19],
                                    ['query' => __('mechanical keyboard', 'searchjet-instant-search'), 'count' => 15],
                                ]; ?>
                                <?php foreach ($sample_queries as $index => $item): ?>
                                    <div class="searchjet-analytics-item">
                                        <span class="sj-rank"><?php echo (int) $index + 1; ?></span>
                                        <div class="sj-query-wrap">
                                            <?php echo wp_kses_post($query_svg); ?>
                                            <span class="query"><?php echo esc_html($item['query']); ?></span>
                                        </div>
                                        <span class="count"><?php echo number_format($item['count']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($analytics['zero_result_queries'])): ?>
                        <div class="searchjet-card">
                            <div class="sj-card-header">
                                <h2><?php echo wp_kses_post($alert_icon); ?><?php esc_html_e('Zero-Result Queries', 'searchjet-instant-search'); ?></h2>
                                <span class="sj-card-badge" style="background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(220,38,38,0.1)); color: #ef4444;"><?php echo count($analytics['zero_result_queries']); ?> <?php esc_html_e('queries', 'searchjet-instant-search'); ?></span>
                            </div>
                            <div class="searchjet-analytics-list" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                                <?php foreach ($analytics['zero_result_queries'] as $index => $item): ?>
                                    <div class="searchjet-analytics-item zero-result">
                                        <span class="sj-rank"><?php echo (int) $index + 1; ?></span>
                                        <div class="sj-query-wrap">
                                            <?php echo wp_kses_post($query_svg); ?>
                                            <span class="query"><?php echo esc_html($item['query'] ?? ''); ?></span>
                                        </div>
                                        <span class="count"><?php echo number_format($item['count'] ?? 0); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="searchjet-card">
                            <div class="sj-card-header">
                                <h2><?php echo wp_kses_post($alert_icon); ?><?php esc_html_e('Zero-Result Queries', 'searchjet-instant-search'); ?></h2>
                                <span class="sj-card-badge" style="background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(220,38,38,0.1)); color: #ef4444;"><?php esc_html_e('sample', 'searchjet-instant-search'); ?></span>
                            </div>
                            <div class="searchjet-analytics-list" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                                <?php $sample_zero_queries = [
                                    ['query' => __('vintage typewriter parts', 'searchjet-instant-search'), 'count' => 12],
                                    ['query' => __('polaroid camera film 600', 'searchjet-instant-search'), 'count' => 8],
                                    ['query' => __('silk pillowcase travel', 'searchjet-instant-search'), 'count' => 6],
                                    ['query' => __('espresso machine descaling kit', 'searchjet-instant-search'), 'count' => 4],
                                ]; ?>
                                <?php foreach ($sample_zero_queries as $index => $item): ?>
                                    <div class="searchjet-analytics-item zero-result">
                                        <span class="sj-rank"><?php echo (int) $index + 1; ?></span>
                                        <div class="sj-query-wrap">
                                            <?php echo wp_kses_post($query_svg); ?>
                                            <span class="query"><?php echo esc_html($item['query']); ?></span>
                                        </div>
                                        <span class="count"><?php echo number_format($item['count']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
