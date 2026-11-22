<?php
/**
 * SearchJet Index Settings Page
 *
 * Allows users to configure index settings directly from WordPress admin,
 * including searchable/filterable attributes, ranking rules, and sorting options.
 */

namespace SearchJet\Admin;

use SearchJet\API\SearchJetRemoteClient;
use SearchJet\Services\TaskStatusTracker;

class IndexSettingsPage {

    const MENU_SLUG = 'searchjet-index-settings';

    public static function init() {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('wp_ajax_searchjet_load_index_settings', [self::class, 'ajax_load_settings']);
        add_action('wp_ajax_searchjet_save_index_settings', [self::class, 'ajax_save_settings']);
        add_action('wp_ajax_searchjet_load_index_stats', [self::class, 'ajax_load_stats']);
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
            'searchjet-index-settings',
            SEARCHJET_PLUGIN_URL . 'assets/css/index-settings.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_script(
            'searchjet-index-settings',
            SEARCHJET_PLUGIN_URL . 'assets/js/admin-index-settings.js',
            ['jquery'],
            SEARCHJET_VERSION,
            true
        );

        wp_localize_script('searchjet-index-settings', 'searchjetIndexSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('searchjet_index_settings_nonce'),
            'imgUrl' => SEARCHJET_PLUGIN_URL . 'assets/img/',
            'strings' => [
                'saving' => __('Saving...', 'searchjet-instant-search'),
                'saved' => __('Settings saved!', 'searchjet-instant-search'),
                'error' => __('Failed to save settings', 'searchjet-instant-search'),
                'loading' => __('Loading settings...', 'searchjet-instant-search'),
            ],
        ]);
    }
    
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $api_key = get_option('searchjet_hosted_api_key', '');
        if (empty($api_key)) {
            ?>
            <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
                <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                    <div class="max-w-6xl mx-auto flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Index Settings', 'searchjet-instant-search'); ?></h1>
                            <p class="text-blue-100 text-xl"><?php esc_html_e('Configure search index attributes and ranking rules', 'searchjet-instant-search'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="searchjet-content max-w-6xl mx-auto">
                    <div class="searchjet-alert searchjet-alert-warning" style="align-items:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/index-key-amber.svg'); ?>" alt="" width="24" height="24" style="display:block;flex-shrink:0;">
                        <div>
                            <strong><?php esc_html_e('API Key Required', 'searchjet-instant-search'); ?></strong>
                            <p style="margin:4px 0 12px;"><?php esc_html_e('Please configure your SearchJet API key in the main settings to access index configuration.', 'searchjet-instant-search'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet')); ?>" class="searchjet-btn searchjet-btn-primary">
                                <?php esc_html_e('Go to Settings', 'searchjet-instant-search'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return;
        }

        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Index Settings', 'searchjet-instant-search'); ?></h1>
                        <p class="text-blue-100 text-xl"><?php esc_html_e('Configure searchable attributes, filters, and ranking rules for your search index.', 'searchjet-instant-search'); ?></p>
                    </div>
                    <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-lg">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <strong class="text-sm text-[#152A53]"><?php esc_html_e('Configuration', 'searchjet-instant-search'); ?></strong>
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
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-health')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-zap-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Index Health', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-task-history')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-clock-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Task History', 'searchjet-instant-search'); ?>
                    </a>
                </div>

                <div class="searchjet-card">
                    <h2>
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/index-database-indigo.svg'); ?>" alt="" width="20" height="20" style="display:block;flex-shrink:0;">
                        <?php esc_html_e('Index Configuration', 'searchjet-instant-search'); ?>
                    </h2>
                    <p class="description" style="margin-top:-12px;margin-bottom:24px;"><?php esc_html_e('Define how your content is searched and displayed', 'searchjet-instant-search'); ?></p>

                    <form id="searchjet-index-settings-form">
                        <?php wp_nonce_field('searchjet_index_settings_nonce', 'searchjet_nonce'); ?>

                        <div id="searchjet-form-loading" class="searchjet-loading-state">
                            <div class="searchjet-spinner"></div>
                            <p><?php esc_html_e('Loading index settings...', 'searchjet-instant-search'); ?></p>
                        </div>

                        <div id="searchjet-form-content" class="hidden">
                            <div class="searchjet-form-section">
                                <div class="searchjet-section-header">
                                    <div>
                                        <h4><?php esc_html_e('Searchable Attributes', 'searchjet-instant-search'); ?></h4>
                                        <p class="description"><?php esc_html_e('Fields used for text search. Order matters - top fields have higher priority.', 'searchjet-instant-search'); ?></p>
                                    </div>
                                    <button type="button" class="sj-btn-add" data-type="searchable">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/index-plus-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                                        <?php esc_html_e('Add', 'searchjet-instant-search'); ?>
                                    </button>
                                </div>
                                <div id="searchjet-searchable-attributes" class="searchjet-tags"></div>
                            </div>

                            <div class="searchjet-form-section">
                                <div class="searchjet-section-header">
                                    <div>
                                        <h4><?php esc_html_e('Filterable Attributes', 'searchjet-instant-search'); ?></h4>
                                        <p class="description"><?php esc_html_e('Fields available for filtering and faceted search.', 'searchjet-instant-search'); ?></p>
                                    </div>
                                    <button type="button" class="sj-btn-add" data-type="filterable">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/index-plus-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                                        <?php esc_html_e('Add', 'searchjet-instant-search'); ?>
                                    </button>
                                </div>
                                <div id="searchjet-filterable-attributes" class="searchjet-tags"></div>
                            </div>

                            <div class="searchjet-form-section">
                                <div class="searchjet-section-header">
                                    <div>
                                        <h4><?php esc_html_e('Sortable Attributes', 'searchjet-instant-search'); ?></h4>
                                        <p class="description"><?php esc_html_e('Fields that can be used to sort search results.', 'searchjet-instant-search'); ?></p>
                                    </div>
                                    <button type="button" class="sj-btn-add" data-type="sortable">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/index-plus-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                                        <?php esc_html_e('Add', 'searchjet-instant-search'); ?>
                                    </button>
                                </div>
                                <div id="searchjet-sortable-attributes" class="searchjet-tags"></div>
                            </div>

                            <div class="searchjet-form-section">
                                <div class="searchjet-section-header">
                                    <div>
                                        <h4><?php esc_html_e('Ranking Rules', 'searchjet-instant-search'); ?></h4>
                                        <p class="description"><?php esc_html_e('Define the importance order for search result ranking.', 'searchjet-instant-search'); ?></p>
                                    </div>
                                </div>
                                <textarea
                                    id="searchjet-ranking-rules"
                                    name="ranking_rules"
                                    rows="2"
                                    placeholder="words, typo, proximity, attribute, sort, exactness"
                                    class="searchjet-textarea"
                                ></textarea>
                                <p class="searchjet-help-text"><?php esc_html_e('Comma-separated list: words, typo, proximity, attribute, sort, exactness', 'searchjet-instant-search'); ?></p>
                            </div>

                            <div class="searchjet-form-actions">
                                <span id="last-saved" class="searchjet-save-status"></span>
                                <button type="submit" class="searchjet-btn searchjet-btn-primary">
                                    <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-check-white.svg'); ?>" alt="" width="18" height="18" style="display:block;margin-right:6px;">
                                    <?php esc_html_e('Save Changes', 'searchjet-instant-search'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="searchjet-card">
                    <h2>
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/index-chart-indigo.svg'); ?>" alt="" width="20" height="20" style="display:block;flex-shrink:0;">
                        <?php esc_html_e('Index Stats', 'searchjet-instant-search'); ?>
                    </h2>
                    <div id="searchjet-stats-loading" class="searchjet-loading-state">
                        <div class="searchjet-spinner searchjet-spinner-sm"></div>
                        <span><?php esc_html_e('Loading...', 'searchjet-instant-search'); ?></span>
                    </div>
                    <div id="searchjet-stats-content" class="searchjet-grid searchjet-grid-3 hidden">
                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number" id="stat-documents">-</div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Documents', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number" id="stat-indexing">-</div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Indexing', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number" id="stat-pending">-</div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Queue', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-stat-card" style="text-align:left;">
                            <div class="searchjet-tip-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/index-info-indigo.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                            </div>
                            <div style="font-size:15px;font-weight:600;color:#212529;margin-bottom:6px;"><?php esc_html_e('Tip', 'searchjet-instant-search'); ?></div>
                            <p class="description" style="margin:0;"><?php esc_html_e('Searchable attributes are weighted by order. Place the most important fields at the top for better search results.', 'searchjet-instant-search'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function ajax_load_settings() {
        check_ajax_referer('searchjet_index_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $settings = SearchJetRemoteClient::getIndexSettings();

        if ($settings === null) {
            $plan = \SearchJet\Services\PlanManager::get_current_plan();
            if ($plan === 'free') {
                wp_send_json_error(__('Custom index settings are not available on the Free plan as it uses a shared index. Please upgrade to Pro to get a dedicated index.', 'searchjet-instant-search'));
            }
            wp_send_json_error(__('Failed to load index settings. Please try again later.', 'searchjet-instant-search'));
        }

        if (empty($settings)) {
            $settings = [
                'searchableAttributes' => [],
                'filterableAttributes' => ['language'],
                'sortableAttributes'   => [],
            ];
        }

        // Always include 'language' in filterable attributes for WPML/Polylang support.
        if ( isset( $settings['filterableAttributes'] ) && is_array( $settings['filterableAttributes'] ) ) {
            if ( ! in_array( 'language', $settings['filterableAttributes'], true ) ) {
                $settings['filterableAttributes'][] = 'language';
            }
        }

        wp_send_json_success($settings);
    }

    public static function ajax_save_settings() {
        check_ajax_referer('searchjet_index_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $settings = [];

        if (isset($_POST['searchableAttributes'])) {
            $attrs = json_decode(stripslashes($_POST['searchableAttributes']), true);
            if (is_array($attrs)) {
                $settings['searchableAttributes'] = array_filter(array_map('sanitize_text_field', $attrs));
            }
        }

        if (isset($_POST['filterableAttributes'])) {
            $attrs = json_decode(stripslashes($_POST['filterableAttributes']), true);
            if (is_array($attrs)) {
                $settings['filterableAttributes'] = array_filter(array_map('sanitize_text_field', $attrs));
                // Always include 'language' for WPML/Polylang support.
                if ( ! in_array( 'language', $settings['filterableAttributes'], true ) ) {
                    $settings['filterableAttributes'][] = 'language';
                }
            }
        }

        if (isset($_POST['sortableAttributes'])) {
            $attrs = json_decode(stripslashes($_POST['sortableAttributes']), true);
            if (is_array($attrs)) {
                $settings['sortableAttributes'] = array_filter(array_map('sanitize_text_field', $attrs));
            }
        }

        if (isset($_POST['displayedAttributes'])) {
            $attrs = json_decode(stripslashes($_POST['displayedAttributes']), true);
            if (is_array($attrs)) {
                $settings['displayedAttributes'] = array_filter(array_map('sanitize_text_field', $attrs));
            }
        }

        if (isset($_POST['rankingRules'])) {
            $rules = sanitize_text_field($_POST['rankingRules']);
            if (!empty($rules)) {
                $parsed_rules = array_filter(array_map('trim', explode(',', $rules)));
                if (!empty($parsed_rules)) {
                    $settings['rankingRules'] = array_values($parsed_rules);
                }
            }
        }

        if (empty($settings)) {
            wp_send_json_error(__('No settings to save', 'searchjet-instant-search'));
        }

        $result = SearchJetRemoteClient::updateIndexSettings($settings);

        if (!$result) {
            $plan = \SearchJet\Services\PlanManager::get_current_plan();
            if ($plan === 'free') {
                wp_send_json_error(__('Custom index settings are not available on the Free plan as it uses a shared index. Please upgrade to Pro to get a dedicated index.', 'searchjet-instant-search'));
            }
            wp_send_json_error(__('Failed to update index settings', 'searchjet-instant-search'));
        }

        $task_uid = $result['taskUid'] ?? ('settings-' . time() . '-' . wp_generate_uuid4());
        TaskStatusTracker::recordTask($task_uid, 'settings', [
            'settings_changed' => array_keys($settings),
        ]);

        wp_send_json_success([
            'message' => __('Index settings updated successfully!', 'searchjet-instant-search'),
            'taskUid' => $task_uid,
        ]);
    }

    public static function ajax_load_stats() {
        check_ajax_referer('searchjet_index_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        $stats = SearchJetRemoteClient::fetchDocumentCount();
        $statsData = [
            'numberOfDocuments' => $stats ?? 0,
            'isIndexing' => false,
            'pendingTasks' => 0,
        ];

        wp_send_json_success($statsData);
    }
}
