<?php
namespace SearchJet\Admin;

use SearchJet\API\SearchJetRemoteClient;
use SearchJet\Services\Reindexer;

class EnhancedSettingsPage {
    
    public static function init() {
        add_action('admin_menu', [self::class, 'registerMenu']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('wp_ajax_searchjet_reindex_all', [self::class, 'handleReindex']);
        add_action('wp_ajax_searchjet_enhanced_reindex', [self::class, 'handleReindex']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        
        self::handleSettingsSubmission();
        self::handleTokenConnect();
        self::maybeRefreshClientInfo();
    }

    public static function registerMenu() {
        add_menu_page(
            'SearchJet',
            'SearchJet',
            'manage_options',
            'searchjet',
            [self::class, 'render'],
            'dashicons-search',
            56
        );
        
        // Add submenu pages
        add_submenu_page(
            'searchjet',
            __('Analytics', 'searchjet-instant-search'),
            __('Analytics', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-analytics',
            [self::class, 'renderAnalytics']
        );
        
        add_submenu_page(
            'searchjet',
            __('Automations', 'searchjet-instant-search'),
            __('Automations', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-automations',
            [self::class, 'renderAutomationsProxy']
        );
        
        add_submenu_page(
            'searchjet',
            __('AI Agents', 'searchjet-instant-search'),
            __('AI Agents', 'searchjet-instant-search'),
            'manage_options',
            'searchjet-agents',
            [self::class, 'renderAgentsProxy']
        );
    }

    public static function registerSettings() {
        $settings = [
            'searchjet_hosted_api_key' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'searchjet_exclude_post_types' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'searchjet_include_status' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'searchjet_exclude_sticky' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_exclude_protected' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_exclude_mime_types' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'searchjet_search_mode' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'js'],
            'searchjet_disable_search_mode' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_attributes' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeAttributes']],
            'searchjet_theme' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'searchjet_primary_color' => ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color'],
            'searchjet_max_results' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'searchjet_show_thumbnails' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_show_prices' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_show_categories' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_show_excerpts' => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
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

    public static function enqueueAssets($hook) {
        $searchjet_pages = [
            'toplevel_page_searchjet',
            'searchjet_page_searchjet-analytics',
            'searchjet_page_searchjet-automations'
        ];
        
        if (!in_array($hook, $searchjet_pages)) return;

        wp_enqueue_style(
            'searchjet-admin-enhanced',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-style-enhanced.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'searchjet-admin-enhanced',
            SEARCHJET_PLUGIN_URL . 'assets/js/admin-enhanced.js',
            ['jquery'],
            '1.0.0',
            true
        );

        $attrs_raw  = get_option('searchjet_attributes', '');
        $attrs_list = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', (string) $attrs_raw)), function($v){ return $v !== ''; }));
        
        wp_localize_script('searchjet-admin-enhanced', 'SearchJetAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('searchjet_reindex_nonce'),
            'ajax_action' => 'searchjet_reindex_all',
            'search_mode' => get_option('searchjet_search_mode', 'js'),
            'attributes' => $attrs_list,
            'strings' => [
                'connecting' => __('Connecting...', 'searchjet'),
                'connected' => __('Connected', 'searchjet'),
                'disconnected' => __('Disconnected', 'searchjet'),
                'reindexing' => __('Reindexing...', 'searchjet'),
                'complete' => __('Complete', 'searchjet'),
                'error' => __('Error', 'searchjet'),
            ]
        ]);
    }

    private static function handleSettingsSubmission() {
        if (!is_admin()) { return; }
        if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'searchjet_settings') { return; }

        $new_key = isset($_POST['searchjet_hosted_api_key']) ? trim((string) wp_unslash($_POST['searchjet_hosted_api_key'])) : '';
        $old_key = trim((string) get_option('searchjet_hosted_api_key', ''));

        if ($new_key === '' && $old_key !== '') {
            // Key cleared by user
            self::clearClientState();
            update_option('searchjet_hosted_api_key', '');
            return;
        }

        if ($new_key !== '' && $new_key !== $old_key) {
            // Key changed
            update_option('searchjet_hosted_api_key', $new_key);
            self::clearClientState();
            self::fetchAndPersistClientInfo($new_key);
        }
    }

    private static function handleTokenConnect() {
        if (!is_admin() || !isset($_GET['searchjet_token'])) { return; }
        if (!current_user_can('manage_options')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('You do not have permission to connect SearchJet.', 'searchjet-instant-search') . '</p></div>';
            });
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['searchjet_token']));
        if ($token === '') {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid connection token.', 'searchjet-instant-search') . '</p></div>';
            });
            return;
        }

        $url = SEARCHJET_API_BASE_URL . '/api/v1/site/key-info?token=' . rawurlencode($token);
        $response = wp_safe_remote_get($url, [
            'timeout' => 12,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'SearchJetWP/' . (defined('SEARCHJET_VERSION') ? SEARCHJET_VERSION : 'dev'),
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to connect to SearchJet. Please try again.', 'searchjet-instant-search') . '</p></div>';
            });
            return;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to connect to SearchJet: invalid response.', 'searchjet-instant-search') . '</p></div>';
            });
            return;
        }

        $api_key = isset($data['api_key']) ? sanitize_text_field($data['api_key']) : '';
        $site_id = isset($data['site_id']) ? (int) $data['site_id'] : (isset($data['id']) ? (int) $data['id'] : 0);

        if ($api_key === '') {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to connect to SearchJet: missing API key.', 'searchjet-instant-search') . '</p></div>';
            });
            return;
        }

        // If key changed, update and clear stale state
        $old_key = trim((string) get_option('searchjet_hosted_api_key', ''));
        if ($old_key !== $api_key) {
            update_option('searchjet_hosted_api_key', $api_key);
            self::clearClientState();
        }

        self::fetchAndPersistClientInfo($api_key, $site_id);

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('SearchJet is now connected successfully.', 'searchjet-instant-search') . '</p></div>';
        });

        // Redirect to the SearchJet admin page
        $redirect = admin_url('admin.php?page=searchjet');
        wp_safe_redirect($redirect);
        exit;
    }

    private static function clearClientState() {
        delete_transient('searchjet_usage_limits');
        delete_option('searchjet_client_info');
        delete_option('searchjet_site_id');
    }

    private static function persistClientInfo(array $client_info, $fallback_site_id = 0) {
        update_option('searchjet_client_info', $client_info);
        if (!empty($client_info['usage_limits'])) {
            set_transient('searchjet_usage_limits', $client_info['usage_limits'], 600); // 10 min
        }
        if (!empty($client_info['site_id'])) {
            update_option('searchjet_site_id', (int) $client_info['site_id']);
        } elseif (!empty($fallback_site_id)) {
            update_option('searchjet_site_id', (int) $fallback_site_id);
        }
    }

    private static function fetchAndPersistClientInfo($api_key, $fallback_site_id = 0) {
        if (empty($api_key)) { return; }
        $client_info = SearchJetRemoteClient::fetchClientInfo($api_key);
        if (is_array($client_info)) {
            self::persistClientInfo($client_info, $fallback_site_id);
        }
    }

    private static function maybeRefreshClientInfo() {
        if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'searchjet') return;
        
        $existing_key = trim(get_option('searchjet_hosted_api_key', ''));
        if ($existing_key && !get_option('searchjet_client_info')) {
            self::fetchAndPersistClientInfo($existing_key);
        }
    }

    public static function handleReindex() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'searchjet')]);
        }

        check_ajax_referer('searchjet_reindex_nonce', 'nonce');

        try {
            $reindexer = new \SearchJet\Services\Reindexer();
            $result = $reindexer->reindexAll();

            $message = $result['message'] ?? __('Reindexing completed successfully', 'searchjet');
            $total = $result['total_indexed'] ?? 0;

            $client_info = get_option('searchjet_client_info');
            $max_pages = $client_info['usage_limits']['max_indexed_pages'] ?? null;
            $current_count = \SearchJet\API\SearchJetRemoteClient::fetchDocumentCount();

            if ($max_pages !== null && $current_count >= $max_pages) {
                wp_send_json_error([
                    'message' => sprintf(__('Index limit reached. You have indexed %d documents, which meets or exceeds your plan\'s limit of %d.', 'searchjet'), $current_count, $max_pages),
                    'processed' => $total,
                    'done' => true,
                ]);
            }

            wp_send_json_success([
                'processed' => $total,
                'done' => true,
                'message' => $message,
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Reindexing failed: ', 'searchjet') . $e->getMessage(),
                'done' => true,
            ]);
        }
    }

    public static function render() {
        $client_info = get_option('searchjet_client_info', []);
        $api_key = get_option('searchjet_hosted_api_key', '');
        $is_connected = !empty($client_info) && !empty($api_key);
        $limits = get_transient('searchjet_usage_limits') ?: [];
        $indexed_count = SearchJetRemoteClient::fetchDocumentCount() ?: 0;
        
        ?>
        <div class="searchjet-admin-wrap">
            <div class="searchjet-header">
                <h1><?php _e('SearchJet', 'searchjet'); ?></h1>
                <div class="subtitle"><?php _e('AI-Powered Instant Search for WordPress & WooCommerce', 'searchjet'); ?></div>
            </div>

            <div class="searchjet-content">
                <?php if (!$is_connected): ?>
                    <div class="searchjet-setup-banner">
                        <div class="searchjet-setup-content">
                            <h2>🚀 <?php _e('Get Started with SearchJet', 'searchjet'); ?></h2>
                            <p><?php _e('Enable AI-powered instant search in 2 minutes', 'searchjet'); ?></p>
                            <button type="button" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg" id="searchjet-start-setup">
                                <?php _e('Start Setup Wizard', 'searchjet'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Connection Status -->
                <div class="searchjet-connection-status">
                    <div class="searchjet-connection-indicator <?php echo $is_connected ? 'connected' : ''; ?>"></div>
                    <div>
                        <strong><?php echo $is_connected ? __('Connected', 'searchjet') : __('Not Connected', 'searchjet'); ?></strong>
                        <?php if ($is_connected): ?>
                            <div style="font-size: 12px; color: #6c757d;">
                                <?php printf(__('Plan: %s', 'searchjet'), esc_html($client_info['plan'] ?? 'Free')); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_connected): ?>
                    <!-- Post-Connection Checklist -->
                    <?php if ($indexed_count === 0): ?>
                        <div class="searchjet-checklist-banner">
                            <h3>🎉 <?php _e('Great! You\'re connected to SearchJet', 'searchjet'); ?></h3>
                            <p><?php _e('Complete these steps to activate your search:', 'searchjet'); ?></p>
                            <div class="searchjet-checklist">
                                <div class="searchjet-checklist-item completed">
                                    <span class="searchjet-checklist-icon">✓</span>
                                    <span><?php _e('Connected to SearchJet', 'searchjet'); ?></span>
                                </div>
                                <div class="searchjet-checklist-item pending" id="checklist-index">
                                    <span class="searchjet-checklist-icon">⏳</span>
                                    <span><?php _e('Index your content', 'searchjet'); ?></span>
                                    <a href="#indexing" class="searchjet-btn searchjet-btn-sm searchjet-btn-primary" onclick="document.querySelector('a[href=\"#indexing\"]').click()">
                                        <?php _e('Index Now', 'searchjet'); ?>
                                    </a>
                                </div>
                                <div class="searchjet-checklist-item pending">
                                    <span class="searchjet-checklist-icon">⏳</span>
                                    <span><?php _e('Test search functionality', 'searchjet'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statistics Grid -->
                    <div class="searchjet-grid searchjet-grid-3">
                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number"><?php echo number_format($limits['used_search_queries'] ?? 0); ?></div>
                            <div class="searchjet-stat-label"><?php _e('Searches This Month', 'searchjet'); ?></div>
                            <?php if (isset($limits['search_queries_per_month'])): ?>
                                <div class="searchjet-progress">
                                    <div class="searchjet-progress-bar" style="width: <?php echo min(100, ($limits['used_search_queries'] / $limits['search_queries_per_month']) * 100); ?>%"></div>
                                </div>
                                <div class="searchjet-progress-text">
                                    <?php printf(__('%s of %s', 'searchjet'), 
                                        number_format($limits['used_search_queries']), 
                                        number_format($limits['search_queries_per_month'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="searchjet-stat-card <?php echo $indexed_count > 0 ? 'searchjet-stat-success' : 'searchjet-stat-warning'; ?>">
                            <div class="searchjet-stat-number"><?php echo number_format($indexed_count); ?></div>
                            <div class="searchjet-stat-label"><?php _e('Indexed Documents', 'searchjet'); ?></div>
                            <?php if ($indexed_count === 0): ?>
                                <div class="searchjet-stat-action">
                                    <a href="#indexing" class="searchjet-btn searchjet-btn-sm searchjet-btn-primary" onclick="document.querySelector('a[href=\"#indexing\"]').click()">
                                        <?php _e('Start Indexing', 'searchjet'); ?>
                                    </a>
                                </div>
                            <?php elseif (isset($limits['max_indexed_pages'])): ?>
                                <div class="searchjet-progress">
                                    <div class="searchjet-progress-bar" style="width: <?php echo min(100, ($indexed_count / $limits['max_indexed_pages']) * 100); ?>%"></div>
                                </div>
                                <div class="searchjet-progress-text">
                                    <?php printf(__('%s of %s', 'searchjet'), 
                                        number_format($indexed_count), 
                                        number_format($limits['max_indexed_pages'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number"><?php echo $limits['reindexes_today'] ?? 0; ?></div>
                            <div class="searchjet-stat-label"><?php _e('Reindexes Today', 'searchjet'); ?></div>
                            <?php if (isset($limits['reindexing_per_day'])): ?>
                                <div class="searchjet-progress-text">
                                    <?php printf(__('Limit: %s/day', 'searchjet'), $limits['reindexing_per_day']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Ongoing Guidance Section -->
                    <?php self::renderOngoingGuidance($limits, $indexed_count, $client_info); ?>
                <?php endif; ?>

                <?php
                // Show plan notice for free tier
                if (!empty($client_info) && ($client_info['plan'] ?? 'free') === 'free') {
                    echo '<div class="searchjet-alert searchjet-alert-info">';
                    echo '<div><strong>' . esc_html__('Using Free Tier:', 'searchjet-instant-search') . '</strong><br>';
                    echo esc_html__('You are limited to 1,000 search queries and 200 indexed pages per month. For more features, upgrade at ', 'searchjet-instant-search');
                    echo '<a href="https://searchjetengine.com/pricing" target="_blank">SearchJet Pricing</a>.</div>';
                    echo '</div>';
                }
                ?>


                <!-- Navigation -->
                <div class="searchjet-nav">
                    <div class="nav-tab-wrapper">
                        <a href="#connection" class="nav-tab nav-tab-active"><?php _e('Connection', 'searchjet'); ?></a>
                        <a href="#indexing" class="nav-tab"><?php _e('Indexing', 'searchjet'); ?></a>
                        <a href="#exclusions" class="nav-tab"><?php _e('Exclusions', 'searchjet'); ?></a>
                        <a href="#ui-customization" class="nav-tab"><?php _e('UI & Styling', 'searchjet'); ?></a>
                        <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'searchjet'); ?></a>
                        <a href="#documentation" class="nav-tab"><?php _e('Documentation', 'searchjet'); ?></a>
                    </div>
                </div>

                <form method="post" action="options.php" id="searchjet-settings-form" autocomplete="off">
                    <?php settings_fields('searchjet_settings'); ?>

                    <!-- Connection Tab -->
                    <div id="connection" class="searchjet-tab-content active">
                        <div class="searchjet-card">
                            <h2><?php _e('API Connection', 'searchjet'); ?></h2>
                            
                            <div class="searchjet-form-group">
                                <label for="searchjet_hosted_api_key"><?php _e('SearchJet API Key', 'searchjet'); ?></label>
                                <div class="searchjet-api-key-group">
                                    <input type="password" 
                                           id="searchjet_hosted_api_key" 
                                           name="searchjet_hosted_api_key" 
                                           value="<?php echo esc_attr($api_key); ?>" 
                                           class="searchjet-input"
                                           placeholder="<?php _e('Enter your API key', 'searchjet'); ?>">
                                    <button type="button" class="searchjet-api-key-toggle" data-show="👁️" data-hide="🙈">
                                        👁️
                                    </button>
                                </div>
                                <div class="description">
                                    <?php _e('Get your API key from the SearchJet dashboard.', 'searchjet'); ?>
                                </div>
                            </div>

                            <?php if (!$api_key): ?>
                                <div style="margin-top: 20px;">
                                    <?php
                                    $connect_url = str_replace('/api', '', SEARCHJET_API_BASE_URL) . '/link?site=' . urlencode(site_url()) .
                                                  '&redirect=' . urlencode(admin_url('admin.php?page=searchjet')) .
                                                  '&label=' . urlencode(get_bloginfo('name'));
                                    ?>
                                    <a href="<?php echo esc_url($connect_url); ?>" class="searchjet-btn searchjet-btn-primary">
                                        <?php _e('Connect to SearchJet', 'searchjet'); ?>
                                    </a>
                                    <a href="https://app.searchjetengine.com/dashboard" target="_blank" class="searchjet-btn searchjet-btn-secondary">
                                        <?php _e('Go to Dashboard', 'searchjet'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Indexing Tab -->
                    <div id="indexing" class="searchjet-tab-content">
                        <div class="searchjet-card">
                            <h2><?php _e('Index Management', 'searchjet'); ?></h2>
                            
                            <div class="searchjet-form-group">
                                <button type="button" id="searchjet-reindex-btn" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg">
                                    <?php _e('Reindex All Content', 'searchjet'); ?>
                                </button>
                                <div class="description">
                                    <?php _e('Rebuild the search index with all published content.', 'searchjet'); ?>
                                </div>
                            </div>

                            <div id="searchjet-reindex-progress" style="display: none;">
                                <div class="searchjet-progress">
                                    <div class="searchjet-progress-bar" id="searchjet-progress-bar"></div>
                                </div>
                                <div class="searchjet-progress-text" id="searchjet-progress-text"></div>
                            </div>

                            <div class="searchjet-form-group">
                                <label><?php _e('Custom Attributes', 'searchjet'); ?></label>
                                <div class="searchjet-attributes-container">
                                    <div class="searchjet-attributes-presets">
                                        <h4><?php _e('Quick Presets', 'searchjet'); ?></h4>
                                        <div class="searchjet-preset-buttons">
                                            <button type="button" class="searchjet-preset-btn" data-preset="basic"><?php _e('Basic', 'searchjet'); ?></button>
                                            <button type="button" class="searchjet-preset-btn" data-preset="woocommerce"><?php _e('WooCommerce', 'searchjet'); ?></button>
                                            <button type="button" class="searchjet-preset-btn" data-preset="advanced"><?php _e('Advanced', 'searchjet'); ?></button>
                                        </div>
                                    </div>
                                    
                                    <div class="searchjet-attributes-builder">
                                        <h4><?php _e('Selected Attributes', 'searchjet'); ?></h4>
                                        <div id="searchjet-attributes-list" class="searchjet-attributes-list"></div>
                                        <div class="searchjet-add-attribute">
                                            <input type="text" id="searchjet-new-attribute" class="searchjet-input" placeholder="<?php _e('Add custom attribute...', 'searchjet'); ?>">
                                            <button type="button" id="searchjet-add-btn" class="searchjet-btn searchjet-btn-secondary"><?php _e('Add', 'searchjet'); ?></button>
                                        </div>
                                    </div>
                                    
                                    <textarea id="searchjet_attributes" 
                                             name="searchjet_attributes" 
                                             style="display: none;"><?php echo esc_textarea(get_option('searchjet_attributes', '')); ?></textarea>
                                </div>
                                <div class="description">
                                    <?php _e('Select which data fields to retrieve from SearchJet for search results. More attributes may slow down search performance.', 'searchjet'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exclusions Tab -->
                    <div id="exclusions" class="searchjet-tab-content">
                        <div class="searchjet-card">
                            <h2><?php _e('Content Exclusions', 'searchjet'); ?></h2>
                            
                            <!-- Post Types Section -->
                            <div class="searchjet-exclusion-section">
                                <h3><?php _e('Post Types', 'searchjet'); ?></h3>
                                <div class="searchjet-exclusion-container">
                                    <div class="searchjet-available-items">
                                        <h4><?php _e('Available Post Types', 'searchjet'); ?></h4>
                                        <div id="searchjet-available-post-types" class="searchjet-items-list">
                                            <?php
                                            $all_post_types = get_post_types(['public' => true], 'objects');
                                            $excluded_types = array_filter(array_map('trim', explode(',', get_option('searchjet_exclude_post_types', ''))));
                                            foreach ($all_post_types as $post_type) {
                                                if (!in_array($post_type->name, $excluded_types)) {
                                                    echo '<div class="searchjet-item" data-value="' . esc_attr($post_type->name) . '">';
                                                    echo '<span>' . esc_html($post_type->label) . ' (' . esc_html($post_type->name) . ')</span>';
                                                    echo '<button type="button" class="searchjet-exclude-item">→</button>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="searchjet-excluded-items">
                                        <h4><?php _e('Excluded Post Types', 'searchjet'); ?></h4>
                                        <div id="searchjet-excluded-post-types" class="searchjet-items-list">
                                            <?php
                                            foreach ($excluded_types as $excluded_type) {
                                                if ($excluded_type && isset($all_post_types[$excluded_type])) {
                                                    $post_type = $all_post_types[$excluded_type];
                                                    echo '<div class="searchjet-item excluded" data-value="' . esc_attr($excluded_type) . '">';
                                                    echo '<button type="button" class="searchjet-include-item">←</button>';
                                                    echo '<span>' . esc_html($post_type->label) . ' (' . esc_html($excluded_type) . ')</span>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="searchjet_exclude_post_types" name="searchjet_exclude_post_types" value="<?php echo esc_attr(get_option('searchjet_exclude_post_types')); ?>">
                            </div>

                            <!-- Post Status Section -->
                            <div class="searchjet-exclusion-section">
                                <h3><?php _e('Post Status', 'searchjet'); ?></h3>
                                <div class="searchjet-status-options">
                                    <?php
                                    $all_statuses = ['publish', 'private', 'draft', 'pending', 'future'];
                                    $included_statuses = array_filter(array_map('trim', explode(',', get_option('searchjet_include_status', 'publish'))));
                                    foreach ($all_statuses as $status) {
                                        $checked = in_array($status, $included_statuses) ? 'checked' : '';
                                        echo '<label class="searchjet-status-option">';
                                        echo '<input type="checkbox" class="searchjet-status-checkbox" value="' . esc_attr($status) . '" ' . $checked . '>';
                                        echo '<span class="searchjet-status-label">' . esc_html(ucfirst($status)) . '</span>';
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                                <input type="hidden" id="searchjet_include_status" name="searchjet_include_status" value="<?php echo esc_attr(get_option('searchjet_include_status', 'publish')); ?>">
                                <div class="description">
                                    <?php _e('Select which post statuses to include in search indexing.', 'searchjet'); ?>
                                </div>
                            </div>

                            <!-- Special Options -->
                            <div class="searchjet-exclusion-section">
                                <h3><?php _e('Special Content', 'searchjet'); ?></h3>
                                <div class="searchjet-special-options">
                                    <label class="searchjet-toggle-option">
                                        <input type="checkbox" name="searchjet_exclude_sticky" value="1" <?php checked(get_option('searchjet_exclude_sticky'), 1); ?>>
                                        <span class="searchjet-toggle-slider"></span>
                                        <span class="searchjet-toggle-label"><?php _e('Exclude Sticky Posts', 'searchjet'); ?></span>
                                    </label>
                                    <label class="searchjet-toggle-option">
                                        <input type="checkbox" name="searchjet_exclude_protected" value="1" <?php checked(get_option('searchjet_exclude_protected'), 1); ?>>
                                        <span class="searchjet-toggle-slider"></span>
                                        <span class="searchjet-toggle-label"><?php _e('Exclude Password-Protected Posts', 'searchjet'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- MIME Types Section -->
                            <div class="searchjet-exclusion-section">
                                <h3><?php _e('File Types (MIME)', 'searchjet'); ?></h3>
                                <div class="searchjet-mime-container">
                                    <div class="searchjet-mime-presets">
                                        <button type="button" class="searchjet-mime-preset" data-types="image/jpeg,image/png,image/gif"><?php _e('Images', 'searchjet'); ?></button>
                                        <button type="button" class="searchjet-mime-preset" data-types="application/pdf"><?php _e('PDFs', 'searchjet'); ?></button>
                                        <button type="button" class="searchjet-mime-preset" data-types="video/mp4,video/avi"><?php _e('Videos', 'searchjet'); ?></button>
                                        <button type="button" class="searchjet-mime-preset" data-types="audio/mp3,audio/wav"><?php _e('Audio', 'searchjet'); ?></button>
                                    </div>
                                    <div class="searchjet-mime-list" id="searchjet-mime-list"></div>
                                    <div class="searchjet-add-mime">
                                        <input type="text" id="searchjet-new-mime" class="searchjet-input" placeholder="<?php _e('Add MIME type...', 'searchjet'); ?>">
                                        <button type="button" id="searchjet-add-mime-btn" class="searchjet-btn searchjet-btn-secondary"><?php _e('Add', 'searchjet'); ?></button>
                                    </div>
                                </div>
                                <input type="hidden" id="searchjet_exclude_mime_types" name="searchjet_exclude_mime_types" value="<?php echo esc_attr(get_option('searchjet_exclude_mime_types')); ?>">
                                <div class="description">
                                    <?php _e('Exclude specific file types from being indexed.', 'searchjet'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- UI Customization Tab -->
                    <div id="ui-customization" class="searchjet-tab-content">
                        <!-- Sample Search Demo -->
                        <div class="searchjet-card">
                            <h2><?php _e('Try SearchJet Demo', 'searchjet'); ?></h2>
                            <p><?php _e('Experience how SearchJet works with this interactive demo:', 'searchjet'); ?></p>
                            <div id="searchjet-demo-container" class="searchjet-demo-container">
                                <div class="searchjet-demo-search">
                                    <input type="text" class="searchjet-demo-input" placeholder="<?php _e('Try typing "sample" or "product"...', 'searchjet'); ?>">
                                </div>
                                <div class="searchjet-demo-results" style="display: none;"></div>
                            </div>
                        </div>
                        
                        <div class="searchjet-flex-row" style="display:flex; gap:24px; align-items:flex-start;">
                            <div class="searchjet-card" style="flex:1;">
                                <h2><?php _e('Search UI Customization', 'searchjet'); ?></h2>
                                
                                <div class="searchjet-form-group">
                                    <label for="searchjet_theme"><?php _e('Theme Style', 'searchjet'); ?></label>
                                    <select name="searchjet_theme" id="searchjet_theme" class="searchjet-select">
                                        <option value="default" <?php selected(get_option('searchjet_theme'), 'default'); ?>>
                                            <?php _e('Default', 'searchjet'); ?>
                                        </option>
                                        <option value="dark" <?php selected(get_option('searchjet_theme'), 'dark'); ?>>
                                            <?php _e('Dark Mode', 'searchjet'); ?>
                                        </option>
                                        <option value="minimal" <?php selected(get_option('searchjet_theme'), 'minimal'); ?>>
                                            <?php _e('Minimal', 'searchjet'); ?>
                                        </option>
                                        <option value="rounded" <?php selected(get_option('searchjet_theme'), 'rounded'); ?>>
                                            <?php _e('Rounded', 'searchjet'); ?>
                                        </option>
                                    </select>
                                    <div class="description">
                                        <?php _e('Choose a pre-built theme style for the search interface.', 'searchjet'); ?>
                                    </div>
                                </div>

                                <div class="searchjet-form-group">
                                    <label for="searchjet_primary_color"><?php _e('Primary Color', 'searchjet'); ?></label>
                                    <input type="color" 
                                           id="searchjet_primary_color" 
                                           name="searchjet_primary_color" 
                                           value="<?php echo esc_attr(get_option('searchjet_primary_color', '#007aff')); ?>" 
                                           class="searchjet-color-input">
                                    <div class="description">
                                        <?php _e('Primary color for highlights and selections.', 'searchjet'); ?>
                                    </div>
                                </div>

                                <div class="searchjet-form-group">
                                    <label for="searchjet_max_results"><?php _e('Max Results', 'searchjet'); ?></label>
                                    <input type="number" 
                                           id="searchjet_max_results" 
                                           name="searchjet_max_results" 
                                           value="<?php echo esc_attr(get_option('searchjet_max_results', 10)); ?>" 
                                           min="3" 
                                           max="20" 
                                           class="searchjet-input">
                                    <div class="description">
                                        <?php _e('Maximum number of results to show in dropdown.', 'searchjet'); ?>
                                    </div>
                                </div>

                                <div class="searchjet-form-group">
                                    <label><?php _e('Display Options', 'searchjet'); ?></label>
                                    <div class="searchjet-checkbox-group">
                                        <label class="searchjet-checkbox-option">
                                            <input type="checkbox" name="searchjet_show_thumbnails" value="1" <?php checked(get_option('searchjet_show_thumbnails', 1)); ?>>
                                            <span><?php _e('Show Product Thumbnails', 'searchjet'); ?></span>
                                        </label>
                                        <label class="searchjet-checkbox-option">
                                            <input type="checkbox" name="searchjet_show_prices" value="1" <?php checked(get_option('searchjet_show_prices', 1)); ?>>
                                            <span><?php _e('Show Product Prices', 'searchjet'); ?></span>
                                        </label>
                                        <label class="searchjet-checkbox-option">
                                            <input type="checkbox" name="searchjet_show_categories" value="1" <?php checked(get_option('searchjet_show_categories', 1)); ?>>
                                            <span><?php _e('Show Product Categories', 'searchjet'); ?></span>
                                        </label>
                                        <label class="searchjet-checkbox-option">
                                            <input type="checkbox" name="searchjet_show_excerpts" value="1" <?php checked(get_option('searchjet_show_excerpts', 1)); ?>>
                                            <span><?php _e('Show Content Excerpts', 'searchjet'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Preview -->
                            <div class="searchjet-card" style="flex:1; position:sticky; top:100px;">
                                <h2><?php _e('Live Preview', 'searchjet'); ?></h2>
                                <div class="searchjet-preview-container">
                                    <div class="searchjet-preview-search">
                                        <input type="text" placeholder="<?php _e('Search products...', 'searchjet'); ?>" class="searchjet-preview-input" readonly>
                                    </div>
                                    <div id="searchjet-preview-results" class="searchjet-preview-results" style="--searchjet-primary-color: <?php echo esc_attr(get_option('searchjet_primary_color', '#007aff')); ?>">
                                        <h3 class="searchjet-group-title"><?php _e('Products', 'searchjet'); ?></h3>
                                        <a href="#" class="searchjet-hit product-hit" onclick="return false;">
                                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' fill='%23ddd'%3E%3Crect width='48' height='48'/%3E%3C/svg%3E" alt="Product" class="product-image" style="display: <?php echo get_option('searchjet_show_thumbnails', 1) ? 'block' : 'none'; ?>">
                                            <div class="hit-details">
                                                <div class="hit-title"><?php _e('Sample Product', 'searchjet'); ?></div>
                                                <div class="hit-description" style="display: <?php echo get_option('searchjet_show_excerpts', 1) ? 'block' : 'none'; ?>"><?php _e('This is a sample product description', 'searchjet'); ?></div>
                                                <div class="hit-meta" style="display: <?php echo get_option('searchjet_show_categories', 1) ? 'block' : 'none'; ?>"><?php _e('Electronics', 'searchjet'); ?></div>
                                            </div>
                                            <div class="hit-price" style="display: <?php echo get_option('searchjet_show_prices', 1) ? 'block' : 'none'; ?>">$29.99</div>
                                        </a>
                                        <h3 class="searchjet-group-title"><?php _e('Pages', 'searchjet'); ?></h3>
                                        <a href="#" class="searchjet-hit page-hit" onclick="return false;">
                                            <div class="page-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6z"/></svg>
                                            </div>
                                            <div class="hit-details">
                                                <div class="hit-title"><?php _e('Sample Page', 'searchjet'); ?></div>
                                                <div class="hit-description" style="display: <?php echo get_option('searchjet_show_excerpts', 1) ? 'block' : 'none'; ?>"><?php _e('This is a sample page description', 'searchjet'); ?></div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Tab -->
                    <div id="advanced" class="searchjet-tab-content">
                        <div class="searchjet-card">
                            <h2><?php _e('Advanced Settings', 'searchjet'); ?></h2>
                            
                            <div class="searchjet-form-group">
                                <label>
                                    <input type="checkbox" 
                                           name="searchjet_disable_search_mode" 
                                           value="1" 
                                           <?php checked(get_option('searchjet_disable_search_mode'), 1); ?>>
                                    <?php _e('Disable Search Override', 'searchjet'); ?>
                                </label>
                                <div class="description">
                                    <?php _e('Disable SearchJet and use WordPress default search.', 'searchjet'); ?>
                                </div>
                            </div>

                            <?php if (!get_option('searchjet_disable_search_mode')): ?>
                                <div class="searchjet-form-group">
                                    <label for="searchjet_search_mode"><?php _e('Search Rendering Mode', 'searchjet'); ?></label>
                                    <select name="searchjet_search_mode" id="searchjet_search_mode" class="searchjet-select">
                                        <option value="js" <?php selected(get_option('searchjet_search_mode'), 'js'); ?>>
                                            <?php _e('JavaScript (Instant)', 'searchjet'); ?>
                                        </option>
                                        <option value="template" <?php selected(get_option('searchjet_search_mode'), 'template'); ?>>
                                            <?php _e('Template Override (PHP)', 'searchjet'); ?>
                                        </option>
                                    </select>
                                    <div class="description">
                                        <?php _e('Choose how search results are rendered on your site.', 'searchjet'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Documentation Tab -->
                    <div id="documentation" class="searchjet-tab-content">
                        <div class="searchjet-card">
                            <h2><?php _e('SearchJet Documentation', 'searchjet'); ?></h2>
                            
                            <div class="searchjet-docs-nav">
                                <div class="searchjet-docs-tabs">
                                    <button type="button" class="searchjet-docs-tab active" data-tab="overview"><?php _e('Overview', 'searchjet'); ?></button>
                                    <button type="button" class="searchjet-docs-tab" data-tab="api"><?php _e('API Reference', 'searchjet'); ?></button>
                                    <button type="button" class="searchjet-docs-tab" data-tab="features"><?php _e('Features', 'searchjet'); ?></button>
                                    <button type="button" class="searchjet-docs-tab" data-tab="troubleshooting"><?php _e('Troubleshooting', 'searchjet'); ?></button>
                                </div>
                            </div>

                            <div class="searchjet-docs-content">
                                <!-- Overview Tab -->
                                <div id="docs-overview" class="searchjet-docs-section active">
                                    <h3><?php _e('What is SearchJet?', 'searchjet'); ?></h3>
                                    <p><?php _e('SearchJet is an AI-powered instant search plugin for WordPress and WooCommerce that provides blazing-fast, typo-tolerant search functionality with real-time analytics, automation capabilities, and AI agents.', 'searchjet'); ?></p>
                                    
                                    <h4><?php _e('Key Features', 'searchjet'); ?></h4>
                                    <div class="searchjet-feature-grid">
                                        <div class="searchjet-feature-card">
                                            <div class="searchjet-feature-icon">⚡</div>
                                            <h5><?php _e('Instant Search', 'searchjet'); ?></h5>
                                            <p><?php _e('Lightning-fast search results as users type', 'searchjet'); ?></p>
                                        </div>
                                        <div class="searchjet-feature-card">
                                            <div class="searchjet-feature-icon">🧠</div>
                                            <h5><?php _e('AI-Powered', 'searchjet'); ?></h5>
                                            <p><?php _e('Intelligent search with typo tolerance and semantic understanding', 'searchjet'); ?></p>
                                        </div>
                                        <div class="searchjet-feature-card">
                                            <div class="searchjet-feature-icon">📊</div>
                                            <h5><?php _e('Analytics', 'searchjet'); ?></h5>
                                            <p><?php _e('Detailed search analytics and performance insights', 'searchjet'); ?></p>
                                        </div>
                                        <div class="searchjet-feature-card">
                                            <div class="searchjet-feature-icon">🔧</div>
                                            <h5><?php _e('Automations', 'searchjet'); ?></h5>
                                            <p><?php _e('Automated optimization and intelligent improvements', 'searchjet'); ?></p>
                                        </div>
                                    </div>

                                    <h4><?php _e('Getting Started', 'searchjet'); ?></h4>
                                    <ol class="searchjet-steps">
                                        <li><?php _e('Connect your site to SearchJet using the API key', 'searchjet'); ?></li>
                                        <li><?php _e('Configure your search settings and exclusions', 'searchjet'); ?></li>
                                        <li><?php _e('Run the initial content indexing', 'searchjet'); ?></li>
                                        <li><?php _e('Customize the search UI to match your theme', 'searchjet'); ?></li>
                                        <li><?php _e('Monitor performance through the analytics dashboard', 'searchjet'); ?></li>
                                    </ol>
                                </div>

                                <!-- API Reference Tab -->
                                <div id="docs-api" class="searchjet-docs-section">
                                    <h3><?php _e('REST API Endpoints', 'searchjet'); ?></h3>
                                    
                                    <div class="searchjet-api-section">
                                        <h4><?php _e('Health Check', 'searchjet'); ?></h4>
                                        <div class="searchjet-code-block">
                                            <code>GET /wp-json/searchjet/v1/ping</code>
                                        </div>
                                        <p><?php _e('Simple health check endpoint to verify the plugin is active.', 'searchjet'); ?></p>
                                    </div>

                                    <div class="searchjet-api-section">
                                        <h4><?php _e('Search Analytics', 'searchjet'); ?></h4>
                                        <div class="searchjet-code-block">
                                            <code>POST /wp-json/searchjet/v1/log</code>
                                        </div>
                                        <p><?php _e('Log search analytics data including queries, results count, and user behavior.', 'searchjet'); ?></p>
                                    </div>

                                    <div class="searchjet-api-section">
                                        <h4><?php _e('AI Agents', 'searchjet'); ?></h4>
                                        <div class="searchjet-code-block">
                                            <code>GET /wp-json/searchjet/v1/ai-agents</code><br>
                                            <code>POST /wp-json/searchjet/v1/ai-agents</code>
                                        </div>
                                        <p><?php _e('Manage AI agent configurations and retrieve agent status.', 'searchjet'); ?></p>
                                    </div>

                                    <div class="searchjet-api-section">
                                        <h4><?php _e('Automations', 'searchjet'); ?></h4>
                                        <div class="searchjet-code-block">
                                            <code>GET /wp-json/searchjet/v1/automations</code><br>
                                            <code>POST /wp-json/searchjet/v1/automations/settings</code>
                                        </div>
                                        <p><?php _e('Configure and manage search automations and optimization rules.', 'searchjet'); ?></p>
                                    </div>

                                    <h4><?php _e('JavaScript API', 'searchjet'); ?></h4>
                                    <div class="searchjet-code-block">
                                        <code>searchJet.search(query, options)</code><br>
                                        <code>searchJet.logSearch(query, results, metadata)</code><br>
                                        <code>searchJet.recordZeroResult(query)</code>
                                    </div>
                                </div>

                                <!-- Features Tab -->
                                <div id="docs-features" class="searchjet-docs-section">
                                    <h3><?php _e('Advanced Features', 'searchjet'); ?></h3>
                                    
                                    <div class="searchjet-feature-detail">
                                        <h4><?php _e('Search Modes', 'searchjet'); ?></h4>
                                        <ul>
                                            <li><strong><?php _e('JavaScript Mode:', 'searchjet'); ?></strong> <?php _e('Instant search with real-time results as users type', 'searchjet'); ?></li>
                                            <li><strong><?php _e('Template Mode:', 'searchjet'); ?></strong> <?php _e('Server-side rendering for better SEO and compatibility', 'searchjet'); ?></li>
                                        </ul>
                                    </div>

                                    <div class="searchjet-feature-detail">
                                        <h4><?php _e('Content Indexing', 'searchjet'); ?></h4>
                                        <ul>
                                            <li><?php _e('Automatic indexing of posts, pages, and custom post types', 'searchjet'); ?></li>
                                            <li><?php _e('WooCommerce product support with pricing and inventory', 'searchjet'); ?></li>
                                            <li><?php _e('Custom field and metadata indexing', 'searchjet'); ?></li>
                                            <li><?php _e('Flexible exclusion rules and filters', 'searchjet'); ?></li>
                                        </ul>
                                    </div>

                                    <div class="searchjet-feature-detail">
                                        <h4><?php _e('AI Capabilities', 'searchjet'); ?></h4>
                                        <ul>
                                            <li><?php _e('Typo tolerance and fuzzy matching', 'searchjet'); ?></li>
                                            <li><?php _e('Semantic search understanding', 'searchjet'); ?></li>
                                            <li><?php _e('Auto-complete and search suggestions', 'searchjet'); ?></li>
                                            <li><?php _e('Intelligent result ranking', 'searchjet'); ?></li>
                                        </ul>
                                    </div>

                                    <div class="searchjet-feature-detail">
                                        <h4><?php _e('Integrations', 'searchjet'); ?></h4>
                                        <ul>
                                            <li><?php _e('WooCommerce - Products, categories, and attributes', 'searchjet'); ?></li>
                                            <li><?php _e('Advanced Custom Fields (ACF)', 'searchjet'); ?></li>
                                            <li><?php _e('Elementor and page builders', 'searchjet'); ?></li>
                                            <li><?php _e('WPML and multilingual support', 'searchjet'); ?></li>
                                            <li><?php _e('SEO plugins (Yoast, RankMath)', 'searchjet'); ?></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Troubleshooting Tab -->
                                <div id="docs-troubleshooting" class="searchjet-docs-section">
                                    <h3><?php _e('Common Issues & Solutions', 'searchjet'); ?></h3>
                                    
                                    <div class="searchjet-troubleshoot-item">
                                        <h4><?php _e('Search not working', 'searchjet'); ?></h4>
                                        <ul>
                                            <li><?php _e('Verify API key is correctly entered and valid', 'searchjet'); ?></li>
                                            <li><?php _e('Check that content has been indexed', 'searchjet'); ?></li>
                                            <li><?php _e('Ensure search mode is properly configured', 'searchjet'); ?></li>
                                            <li><?php _e('Clear browser cache and try again', 'searchjet'); ?></li>
                                        </ul>
                                    </div>

                                    <div class="searchjet-troubleshoot-item">
                                        <h4><?php _e('Indexing issues', 'searchjet'); ?></h4>
                                        <ul>
                                            <li><?php _e('Check your plan limits for indexed pages', 'searchjet'); ?></li>
                                            <li><?php _e('Review exclusion settings for post types', 'searchjet'); ?></li>
                                            <li><?php _e('Verify content is published and accessible', 'searchjet'); ?></li>
                                            <li><?php _e('Try manual reindexing from the Indexing tab', 'searchjet'); ?></li>
                                        </ul>
                                    </div>

                                    <div class="searchjet-troubleshoot-item">
                                        <h4><?php _e('Performance issues', 'searchjet'); ?></h4>
                                        <ul>
                                            <li><?php _e('Switch to JavaScript mode for faster results', 'searchjet'); ?></li>
                                            <li><?php _e('Reduce the number of indexed attributes', 'searchjet'); ?></li>
                                            <li><?php _e('Optimize your exclusion rules', 'searchjet'); ?></li>
                                            <li><?php _e('Check for plugin conflicts', 'searchjet'); ?></li>
                                        </ul>
                                    </div>

                                    <div class="searchjet-troubleshoot-item">
                                        <h4><?php _e('Getting Help', 'searchjet'); ?></h4>
                                        <ul>
                                            <li><a href="https://searchjetengine.com/docs" target="_blank"><?php _e('Official Documentation', 'searchjet'); ?></a></li>
                                            <li><a href="https://demo.searchjetengine.com/" target="_blank"><?php _e('Live Demo', 'searchjet'); ?></a></li>
                                            <li><a href="https://wordpress.org/support/plugin/searchjet/" target="_blank"><?php _e('WordPress Support Forum', 'searchjet'); ?></a></li>
                                            <li><a href="https://searchjetengine.com/pricing" target="_blank"><?php _e('Upgrade Plans', 'searchjet'); ?></a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 32px; display: flex; gap: 16px; align-items: center;">
                        <button type="submit" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg">
                            <?php _e('Save Settings', 'searchjet'); ?>
                        </button>
                        <button type="button" id="searchjet-discard-btn" class="searchjet-btn searchjet-btn-secondary searchjet-btn-lg" style="display: none;">
                            <?php _e('Discard Changes', 'searchjet'); ?>
                        </button>
                        <div id="searchjet-draft-indicator" class="searchjet-draft-indicator" style="display: none;">
                            <span class="searchjet-draft-dot"></span>
                            <?php _e('Unsaved changes', 'searchjet'); ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function toggleApiKey() {
            const input = document.getElementById('searchjet_hosted_api_key');
            const button = document.querySelector('.searchjet-api-key-toggle');
            
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = '🙈';
            } else {
                input.type = 'password';
                button.innerHTML = '👁️';
            }
        }

        // Draft management
        let originalFormData = {};
        let hasUnsavedChanges = false;

        function captureOriginalData() {
            const settingsForm = document.getElementById('searchjet-settings-form');
            const formData = new FormData(settingsForm);
            originalFormData = {};
            for (let [key, value] of formData.entries()) {
                originalFormData[key] = value;
            }
            settingsForm.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                if (!checkbox.checked) {
                    originalFormData[checkbox.name] = '';
                }
            });
        }

        function detectChanges() {
            const settingsForm = document.getElementById('searchjet-settings-form');
            const currentData = new FormData(settingsForm);
            let changed = false;

            for (let [key, value] of currentData.entries()) {
                if (originalFormData[key] !== value) {
                    changed = true;
                    break;
                }
            }

            if (!changed) {
                settingsForm.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                    const currentValue = checkbox.checked ? checkbox.value : '';
                    if (originalFormData[checkbox.name] !== currentValue) {
                        changed = true;
                    }
                });
            }

            hasUnsavedChanges = changed;
            toggleDraftIndicator(changed);
        }

        function toggleDraftIndicator(show) {
            const indicator = document.getElementById('searchjet-draft-indicator');
            const discardBtn = document.getElementById('searchjet-discard-btn');
            
            if (show) {
                indicator.style.display = 'flex';
                discardBtn.style.display = 'inline-flex';
            } else {
                indicator.style.display = 'none';
                discardBtn.style.display = 'none';
            }
        }

        function discardChanges() {
            const settingsForm = document.getElementById('searchjet-settings-form');
            
            Object.keys(originalFormData).forEach(key => {
                const field = settingsForm.querySelector(`[name="${key}"]`);
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = originalFormData[key] === field.value;
                    } else {
                        field.value = originalFormData[key] || '';
                    }
                }
            });

            // Clear all possible draft storage locations
            localStorage.clear();
            sessionStorage.clear();
            
            // Clear WordPress auto-save data
            if (window.wp && window.wp.autosave) {
                window.wp.autosave.server.suspend();
            }
            
            hasUnsavedChanges = false;
            toggleDraftIndicator(false);
            
            // Recapture original data to reset baseline
            setTimeout(() => {
                captureOriginalData();
            }, 100);
        }

        // Tab switching
        document.addEventListener('DOMContentLoaded', function() {
            // Handle hash-based navigation
            function switchToTabFromHash() {
                const hash = window.location.hash;
                if (hash) {
                    const targetTab = document.querySelector(`a[href="${hash}"]`);
                    if (targetTab) {
                        targetTab.click();
                    }
                }
            }
            
            // Switch to tab on page load if hash exists
            switchToTabFromHash();
            
            // Listen for hash changes
            window.addEventListener('hashchange', switchToTabFromHash);
            // Completely disable all auto-save and draft functionality
            if (window.wp && window.wp.autosave) {
                window.wp.autosave.server.suspend();
                window.wp.autosave.local.suspend();
            }
            
            // Clear all storage completely
            try {
                localStorage.clear();
                sessionStorage.clear();
                // Clear any WordPress specific storage
                Object.keys(localStorage).forEach(key => {
                    if (key.includes('wp') || key.includes('searchjet') || key.includes('draft')) {
                        localStorage.removeItem(key);
                    }
                });
            } catch(e) {}
            
            // Disable browser form restoration
            const settingsForm = document.getElementById('searchjet-settings-form');
            if (settingsForm) {
                settingsForm.setAttribute('autocomplete', 'off');
                settingsForm.querySelectorAll('input, textarea, select').forEach(field => {
                    field.setAttribute('autocomplete', 'off');
                });
            }
            
            captureOriginalData();
            // API Key toggle
            const apiKeyToggle = document.querySelector('.searchjet-api-key-toggle');
            if (apiKeyToggle) {
                apiKeyToggle.addEventListener('click', function() {
                    const input = document.getElementById('searchjet_hosted_api_key');
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.innerHTML = '🙈';
                    } else {
                        input.type = 'password';
                        this.innerHTML = '👁️';
                    }
                });
            }
            
            const tabs = document.querySelectorAll('.nav-tab');
            const contents = document.querySelectorAll('.searchjet-tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    tabs.forEach(t => t.classList.remove('nav-tab-active'));
                    contents.forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('nav-tab-active');
                    const target = this.getAttribute('href').substring(1);
                    document.getElementById(target).classList.add('active');
                });
            });

            // Documentation tabs
            const docsTabs = document.querySelectorAll('.searchjet-docs-tab');
            const docsSections = document.querySelectorAll('.searchjet-docs-section');
            
            docsTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    docsTabs.forEach(t => t.classList.remove('active'));
                    docsSections.forEach(s => s.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById('docs-' + targetTab).classList.add('active');
                });
            });

            // Attributes builder
            const attributesPresets = {
                basic: ['id', 'title', 'url', 'type', 'excerpt'],
                woocommerce: ['id', 'title', 'url', 'type', 'excerpt', 'image', 'price', 'sku', 'stock_status', 'categories'],
                advanced: ['id', 'title', 'url', 'type', 'excerpt', 'image', 'price', 'sku', 'stock_status', 'categories', 'tags', 'meta', 'author']
            };
            
            let selectedAttributes = [];
            
            function initAttributesBuilder() {
                const currentValue = document.getElementById('searchjet_attributes').value;
                selectedAttributes = currentValue ? currentValue.split(',').map(attr => attr.trim()).filter(attr => attr) : [];
                renderAttributesList();
            }
            
            function renderAttributesList() {
                const container = document.getElementById('searchjet-attributes-list');
                container.innerHTML = '';
                
                selectedAttributes.forEach((attr, index) => {
                    const item = document.createElement('div');
                    item.className = 'searchjet-attribute-item';
                    item.innerHTML = `
                        <span class="searchjet-attribute-name">${attr}</span>
                        <button type="button" class="searchjet-remove-attr" data-index="${index}">×</button>
                    `;
                    container.appendChild(item);
                });
                
                updateHiddenField();
            }
            
            function updateHiddenField() {
                document.getElementById('searchjet_attributes').value = selectedAttributes.join(', ');
            }
            
            function addAttribute(attr) {
                if (attr && !selectedAttributes.includes(attr)) {
                    selectedAttributes.push(attr);
                    renderAttributesList();
                }
            }
            
            function removeAttribute(index) {
                selectedAttributes.splice(index, 1);
                renderAttributesList();
            }
            
            function applyPreset(presetName) {
                selectedAttributes = [...attributesPresets[presetName]];
                renderAttributesList();
            }

            // Setup wizard functionality
            const setupBtn = document.getElementById('searchjet-start-setup');
            if (setupBtn) {
                setupBtn.addEventListener('click', function() {
                    showSetupWizard();
                });
            }
            
            function showSetupWizard() {
                const modal = document.createElement('div');
                modal.className = 'searchjet-modal';
                modal.innerHTML = `
                    <div class="searchjet-modal-content">
                        <div class="searchjet-modal-header">
                            <h2>🎆 Welcome to SearchJet!</h2>
                            <button class="searchjet-modal-close">&times;</button>
                        </div>
                        <div class="searchjet-modal-body">
                            <div class="searchjet-wizard-step active" data-step="1">
                                <h3>Transform Your Site Search</h3>
                                <div class="searchjet-benefits">
                                    <div class="searchjet-benefit">
                                        <span class="searchjet-benefit-icon">⚡</span>
                                        <div>
                                            <strong>Lightning Fast</strong><br>
                                            Instant search results as users type
                                        </div>
                                    </div>
                                    <div class="searchjet-benefit">
                                        <span class="searchjet-benefit-icon">🤖</span>
                                        <div>
                                            <strong>AI-Powered</strong><br>
                                            Smart typo tolerance and relevance
                                        </div>
                                    </div>
                                    <div class="searchjet-benefit">
                                        <span class="searchjet-benefit-icon">📊</span>
                                        <div>
                                            <strong>Analytics</strong><br>
                                            Track search performance and insights
                                        </div>
                                    </div>
                                </div>
                                <button class="searchjet-btn searchjet-btn-primary" onclick="nextWizardStep()">Let's Get Started!</button>
                            </div>
                            
                            <div class="searchjet-wizard-step" data-step="2">
                                <h3>Connect to SearchJet</h3>
                                <p>We need to connect your site to our search engine to enable instant search.</p>
                                <div class="searchjet-connect-options">
                                    <a href="<?php echo esc_url(str_replace('/api', '', SEARCHJET_API_BASE_URL) . '/link?site=' . urlencode(site_url()) . '&redirect=' . urlencode(admin_url('admin.php?page=searchjet')) . '&label=' . urlencode(get_bloginfo('name'))); ?>" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg">
                                        🔗 Connect Automatically
                                    </a>
                                    <p style="margin: 16px 0; color: #666;">or</p>
                                    <button class="searchjet-btn searchjet-btn-secondary" onclick="showManualConnect()">Enter API Key Manually</button>
                                </div>
                                <div id="manual-connect" style="display: none; margin-top: 20px;">
                                    <input type="text" id="wizard-api-key" placeholder="Enter your API key" class="searchjet-input">
                                    <button class="searchjet-btn searchjet-btn-primary" onclick="saveApiKey()">Save & Continue</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Close modal functionality
                modal.querySelector('.searchjet-modal-close').addEventListener('click', function() {
                    document.body.removeChild(modal);
                });
                
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        document.body.removeChild(modal);
                    }
                });
            }
            
            window.nextWizardStep = function() {
                const currentStep = document.querySelector('.searchjet-wizard-step.active');
                const nextStep = document.querySelector(`[data-step="${parseInt(currentStep.dataset.step) + 1}"]`);
                
                if (nextStep) {
                    currentStep.classList.remove('active');
                    nextStep.classList.add('active');
                }
            };
            
            window.showManualConnect = function() {
                document.getElementById('manual-connect').style.display = 'block';
            };
            
            window.saveApiKey = function() {
                const apiKey = document.getElementById('wizard-api-key').value.trim();
                if (apiKey) {
                    document.getElementById('searchjet_hosted_api_key').value = apiKey;
                    document.querySelector('.searchjet-modal').remove();
                    // Switch to connection tab and show success message
                    document.querySelector('a[href="#connection"]').click();
                    alert('API key saved! Please save settings and then index your content.');
                }
            };
            
            // Auto-trigger reindex after API key is saved
            const apiKeyInput = document.getElementById('searchjet_hosted_api_key');
            if (apiKeyInput) {
                let lastValue = apiKeyInput.value;
                apiKeyInput.addEventListener('input', function() {
                    const newValue = this.value.trim();
                    if (newValue && newValue !== lastValue && newValue.length > 10) {
                        setTimeout(() => {
                            showIndexingPrompt();
                        }, 1000);
                    }
                    lastValue = newValue;
                });
            }
            
            function showIndexingPrompt() {
                showCelebrationModal('api_connected', () => {
                    // Switch to indexing tab
                    document.querySelector('a[href="#indexing"]').click();
                    // Start interactive tutorial
                    setTimeout(() => {
                        startInteractiveTutorial('indexing');
                    }, 500);
                });
            }
            
            function showCelebrationModal(type, callback) {
                const celebrations = {
                    api_connected: {
                        title: '🎉 API Connected!',
                        message: 'Great! Your site is now connected to SearchJet. Let\'s index your content to make it searchable.',
                        action: 'Index Content Now'
                    },
                    indexing_complete: {
                        title: '✨ Content Indexed!',
                        message: 'Perfect! Your content is now searchable. Let\'s test the search functionality.',
                        action: 'Test Search'
                    },
                    setup_complete: {
                        title: '🚀 Setup Complete!',
                        message: 'Congratulations! SearchJet is now active on your site. Your visitors will love the fast, AI-powered search.',
                        action: 'View Analytics'
                    }
                };
                
                const config = celebrations[type];
                const modal = document.createElement('div');
                modal.className = 'searchjet-celebration-modal';
                modal.innerHTML = `
                    <div class="searchjet-celebration-content">
                        <div class="searchjet-celebration-animation">
                            <div class="searchjet-celebration-icon">${config.title.split(' ')[0]}</div>
                        </div>
                        <h2>${config.title}</h2>
                        <p>${config.message}</p>
                        <div class="searchjet-celebration-actions">
                            <button class="searchjet-btn searchjet-btn-primary searchjet-btn-lg" onclick="this.closest('.searchjet-celebration-modal').remove(); ${callback ? 'arguments[0]()' : ''}">
                                ${config.action}
                            </button>
                            <button class="searchjet-btn searchjet-btn-secondary" onclick="this.closest('.searchjet-celebration-modal').remove()">
                                Maybe Later
                            </button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Auto-remove after 10 seconds
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.remove();
                    }
                }, 10000);
                
                // Execute callback when primary button is clicked
                modal.querySelector('.searchjet-btn-primary').addEventListener('click', () => {
                    if (callback) callback();
                });
            }
            
            function startInteractiveTutorial(step) {
                const tutorials = {
                    indexing: {
                        target: '#searchjet-reindex-btn',
                        title: 'Index Your Content',
                        message: 'Click this button to scan and index all your content. This makes it searchable through SearchJet.',
                        position: 'bottom'
                    },
                    search_test: {
                        target: '.searchjet-preview-input',
                        title: 'Test Your Search',
                        message: 'Try typing in this search box to see how your new search works!',
                        position: 'bottom'
                    }
                };
                
                const config = tutorials[step];
                if (!config) return;
                
                const target = document.querySelector(config.target);
                if (!target) return;
                
                // Create tutorial overlay
                const overlay = document.createElement('div');
                overlay.className = 'searchjet-tutorial-overlay';
                overlay.innerHTML = `
                    <div class="searchjet-tutorial-backdrop"></div>
                    <div class="searchjet-tutorial-tooltip" data-position="${config.position}">
                        <div class="searchjet-tutorial-content">
                            <h3>${config.title}</h3>
                            <p>${config.message}</p>
                            <div class="searchjet-tutorial-actions">
                                <button class="searchjet-btn searchjet-btn-primary searchjet-btn-sm" onclick="this.closest('.searchjet-tutorial-overlay').remove()">
                                    Got it!
                                </button>
                                <button class="searchjet-btn searchjet-btn-secondary searchjet-btn-sm" onclick="this.closest('.searchjet-tutorial-overlay').remove(); localStorage.setItem('searchjet_skip_tutorials', '1')">
                                    Skip tutorials
                                </button>
                            </div>
                        </div>
                        <div class="searchjet-tutorial-arrow"></div>
                    </div>
                `;
                
                document.body.appendChild(overlay);
                
                // Position tooltip relative to target
                const tooltip = overlay.querySelector('.searchjet-tutorial-tooltip');
                const targetRect = target.getBoundingClientRect();
                
                if (config.position === 'bottom') {
                    tooltip.style.top = (targetRect.bottom + 10) + 'px';
                    tooltip.style.left = (targetRect.left + targetRect.width / 2) + 'px';
                    tooltip.style.transform = 'translateX(-50%)';
                }
                
                // Highlight target element
                target.style.position = 'relative';
                target.style.zIndex = '100001';
                target.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.5)';
                target.style.borderRadius = '8px';
                
                // Clean up when tutorial is closed
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay || e.target.classList.contains('searchjet-tutorial-backdrop')) {
                        target.style.position = '';
                        target.style.zIndex = '';
                        target.style.boxShadow = '';
                        target.style.borderRadius = '';
                        overlay.remove();
                    }
                });
            }
            
            // Sample search demo
            function initSampleSearchDemo() {
                const demoContainer = document.getElementById('searchjet-demo-container');
                if (!demoContainer) return;
                
                const demoInput = demoContainer.querySelector('.searchjet-demo-input');
                const demoResults = demoContainer.querySelector('.searchjet-demo-results');
                
                if (!demoInput || !demoResults) return;
                
                const sampleResults = [
                    { title: 'Sample Product 1', type: 'product', price: '$29.99', image: '📱' },
                    { title: 'Sample Blog Post', type: 'post', excerpt: 'This is a sample blog post...', image: '📄' },
                    { title: 'Sample Page', type: 'page', excerpt: 'This is a sample page...', image: '📋' }
                ];
                
                demoInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase();
                    
                    if (!query) {
                        demoResults.innerHTML = '';
                        demoResults.style.display = 'none';
                        return;
                    }
                    
                    const filteredResults = sampleResults.filter(item => 
                        item.title.toLowerCase().includes(query)
                    );
                    
                    if (filteredResults.length === 0) {
                        demoResults.innerHTML = '<div class="searchjet-demo-no-results">No results found</div>';
                    } else {
                        demoResults.innerHTML = filteredResults.map(item => `
                            <div class="searchjet-demo-result">
                                <div class="searchjet-demo-icon">${item.image}</div>
                                <div class="searchjet-demo-details">
                                    <div class="searchjet-demo-title">${item.title}</div>
                                    ${item.excerpt ? `<div class="searchjet-demo-excerpt">${item.excerpt}</div>` : ''}
                                    ${item.price ? `<div class="searchjet-demo-price">${item.price}</div>` : ''}
                                </div>
                            </div>
                        `).join('');
                    }
                    
                    demoResults.style.display = 'block';
                });
            }
            
            // Initialize demo when page loads
            setTimeout(initSampleSearchDemo, 1000);
            
            // Initialize sample search demo
            initSampleSearchDemo();
            
            // Check if we should show tutorial
            if (!localStorage.getItem('searchjet_skip_tutorials')) {
                // Show tutorial after successful indexing
                const originalHandleReindex = window.SearchJetAdmin?.handleReindex;
                if (originalHandleReindex) {
                    window.SearchJetAdmin.handleReindex = function() {
                        const result = originalHandleReindex.apply(this, arguments);
                        
                        // Show celebration after successful reindex
                        setTimeout(() => {
                            const reindexBtn = document.getElementById('searchjet-reindex-btn');
                            if (reindexBtn && reindexBtn.classList.contains('searchjet-btn-success')) {
                                showCelebrationModal('indexing_complete', () => {
                                    document.querySelector('a[href="#ui-customization"]').click();
                                    setTimeout(() => startInteractiveTutorial('search_test'), 500);
                                });
                            }
                        }, 2000);
                        
                        return result;
                    };
                }
            }
            
            // Reindex functionality is handled by external JS

            // Draft detection
            const formElement = document.getElementById('searchjet-settings-form');
            if (formElement) {
                formElement.addEventListener('input', detectChanges);
                formElement.addEventListener('change', detectChanges);
            }

            // Discard changes button
            const discardBtn = document.getElementById('searchjet-discard-btn');
            if (discardBtn) {
                discardBtn.addEventListener('click', function() {
                    if (confirm('<?php _e('Are you sure you want to discard all unsaved changes?', 'searchjet'); ?>')) {
                        discardChanges();
                        // Also clear any stored drafts
                        if (window.SearchJetAdmin && window.SearchJetAdmin.discardDraft) {
                            window.SearchJetAdmin.discardDraft();
                        }
                    }
                });
            }

            // Warn before leaving page with unsaved changes
            window.addEventListener('beforeunload', function(e) {
                if (hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = '<?php _e('You have unsaved changes. Are you sure you want to leave?', 'searchjet'); ?>';
                    return e.returnValue;
                }
            });

            // Reset draft state after successful form submission
            formElement.addEventListener('submit', function() {
                localStorage.removeItem('searchjet_draft_data');
                sessionStorage.removeItem('searchjet_draft_data');
                hasUnsavedChanges = false;
            });
            
            // Initialize attributes builder
            initAttributesBuilder();
            
            // Preset buttons
            document.querySelectorAll('.searchjet-preset-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const preset = this.getAttribute('data-preset');
                    applyPreset(preset);
                    detectChanges();
                });
            });
            
            // Add attribute button
            document.getElementById('searchjet-add-btn').addEventListener('click', function() {
                const input = document.getElementById('searchjet-new-attribute');
                const value = input.value.trim();
                if (value) {
                    addAttribute(value);
                    input.value = '';
                    detectChanges();
                }
            });
            
            // Add attribute on Enter key
            document.getElementById('searchjet-new-attribute').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('searchjet-add-btn').click();
                }
            });
            
            // Remove attribute buttons (delegated)
            document.getElementById('searchjet-attributes-list').addEventListener('click', function(e) {
                if (e.target.classList.contains('searchjet-remove-attr')) {
                    const index = parseInt(e.target.getAttribute('data-index'));
                    removeAttribute(index);
                    detectChanges();
                }
            });
            
            // Exclusions functionality
            function initExclusions() {
                // Post types exclusion
                document.querySelectorAll('.searchjet-exclude-item').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const item = this.parentElement;
                        const value = item.getAttribute('data-value');
                        movePostType(value, 'exclude');
                    });
                });
                
                document.querySelectorAll('.searchjet-include-item').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const item = this.parentElement;
                        const value = item.getAttribute('data-value');
                        movePostType(value, 'include');
                    });
                });
                
                // Post status checkboxes
                document.querySelectorAll('.searchjet-status-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', updatePostStatuses);
                });
                
                // MIME types
                initMimeTypes();
                
                document.querySelectorAll('.searchjet-mime-preset').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const types = this.getAttribute('data-types').split(',');
                        types.forEach(type => addMimeType(type.trim()));
                    });
                });
                
                document.getElementById('searchjet-add-mime-btn').addEventListener('click', function() {
                    const input = document.getElementById('searchjet-new-mime');
                    const value = input.value.trim();
                    if (value) {
                        addMimeType(value);
                        input.value = '';
                    }
                });
            }
            
            function movePostType(value, action) {
                const availableContainer = document.getElementById('searchjet-available-post-types');
                const excludedContainer = document.getElementById('searchjet-excluded-post-types');
                const item = document.querySelector(`[data-value="${value}"]`);
                
                if (action === 'exclude') {
                    item.classList.add('excluded');
                    item.innerHTML = item.innerHTML.replace('→', '←').replace('searchjet-exclude-item', 'searchjet-include-item');
                    excludedContainer.appendChild(item);
                } else {
                    item.classList.remove('excluded');
                    item.innerHTML = item.innerHTML.replace('←', '→').replace('searchjet-include-item', 'searchjet-exclude-item');
                    availableContainer.appendChild(item);
                }
                
                // Update event listeners
                const btn = item.querySelector('button');
                btn.replaceWith(btn.cloneNode(true));
                const newBtn = item.querySelector('button');
                newBtn.addEventListener('click', function() {
                    movePostType(value, action === 'exclude' ? 'include' : 'exclude');
                });
                
                updateExcludedPostTypes();
                detectChanges();
            }
            
            function updateExcludedPostTypes() {
                const excluded = Array.from(document.querySelectorAll('#searchjet-excluded-post-types .searchjet-item'))
                    .map(item => item.getAttribute('data-value'));
                document.getElementById('searchjet_exclude_post_types').value = excluded.join(', ');
            }
            
            function updatePostStatuses() {
                const checked = Array.from(document.querySelectorAll('.searchjet-status-checkbox:checked'))
                    .map(cb => cb.value);
                document.getElementById('searchjet_include_status').value = checked.join(', ');
                detectChanges();
            }
            
            let excludedMimeTypes = [];
            
            function initMimeTypes() {
                const currentValue = document.getElementById('searchjet_exclude_mime_types').value;
                excludedMimeTypes = currentValue ? currentValue.split(',').map(type => type.trim()).filter(type => type) : [];
                renderMimeTypes();
            }
            
            function renderMimeTypes() {
                const container = document.getElementById('searchjet-mime-list');
                container.innerHTML = '';
                
                excludedMimeTypes.forEach((type, index) => {
                    const item = document.createElement('div');
                    item.className = 'searchjet-mime-item';
                    item.innerHTML = `
                        <span class="searchjet-mime-name">${type}</span>
                        <button type="button" class="searchjet-remove-mime" data-index="${index}">×</button>
                    `;
                    container.appendChild(item);
                });
                
                updateMimeTypesField();
            }
            
            function addMimeType(type) {
                if (type && !excludedMimeTypes.includes(type)) {
                    excludedMimeTypes.push(type);
                    renderMimeTypes();
                    detectChanges();
                }
            }
            
            function removeMimeType(index) {
                excludedMimeTypes.splice(index, 1);
                renderMimeTypes();
                detectChanges();
            }
            
            function updateMimeTypesField() {
                document.getElementById('searchjet_exclude_mime_types').value = excludedMimeTypes.join(', ');
            }
            
            // MIME type removal (delegated)
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('searchjet-remove-mime')) {
                    const index = parseInt(e.target.getAttribute('data-index'));
                    removeMimeType(index);
                }
            });
            
            // Initialize exclusions
            initExclusions();
            
            // Initialize live preview
            initLivePreview();
        });
        
        function initLivePreview() {
            const preview = document.getElementById('searchjet-preview-results');
            if (!preview) return;
            
            // Theme selector
            const themeSelect = document.getElementById('searchjet_theme');
            if (themeSelect) {
                themeSelect.addEventListener('change', updatePreview);
            }
            
            // Color picker
            const colorInput = document.getElementById('searchjet_primary_color');
            if (colorInput) {
                colorInput.addEventListener('input', updatePreview);
            }
            
            // Display options
            document.querySelectorAll('input[name^="searchjet_show_"]').forEach(checkbox => {
                checkbox.addEventListener('change', updatePreview);
            });
            
            updatePreview();
        }
        
        function updatePreview() {
            const preview = document.getElementById('searchjet-preview-results');
            if (!preview) return;
            
            // Apply theme
            const theme = document.getElementById('searchjet_theme')?.value || 'default';
            preview.className = 'searchjet-preview-results';
            if (theme && theme !== 'default') {
                preview.classList.add(`searchjet-theme-${theme}`);
            }
            
            // Apply custom color
            const color = document.getElementById('searchjet_primary_color')?.value;
            if (color) {
                preview.style.setProperty('--searchjet-primary-color', color);
            }
            
            // Toggle display options
            const showThumbnails = document.querySelector('input[name="searchjet_show_thumbnails"]')?.checked ?? true;
            const showPrices = document.querySelector('input[name="searchjet_show_prices"]')?.checked ?? true;
            const showCategories = document.querySelector('input[name="searchjet_show_categories"]')?.checked ?? true;
            const showExcerpts = document.querySelector('input[name="searchjet_show_excerpts"]')?.checked ?? true;
            
            // Update all elements
            const elements = {
                '.product-image': showThumbnails,
                '.hit-price': showPrices,
                '.hit-meta': showCategories,
                '.hit-description': showExcerpts
            };
            
            Object.entries(elements).forEach(([selector, show]) => {
                preview.querySelectorAll(selector).forEach(el => {
                    el.style.display = show ? '' : 'none';
                });
            });
            
            // Add visual feedback
            preview.style.transition = 'all 0.3s ease';
            preview.style.transform = 'scale(0.98)';
            setTimeout(() => {
                preview.style.transform = 'scale(1)';
            }, 150);
        }
        </script>
        <?php
    }

    // Add missing methods from original SettingsPage
    public static function renderAnalytics() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
        }

        // Fetch client info and analytics
        $client_info = get_option('searchjet_client_info', []);
        $indexed_count = SearchJetRemoteClient::fetchDocumentCount();
        $limits = get_transient('searchjet_usage_limits');
        $search_used = $limits['used_search_queries'] ?? 0;
        $index_used = $limits['used_indexed_pages'] ?? 0;
        $reindexes_today = $limits['reindexes_today'] ?? 0;
        $search_analytics = SearchJetRemoteClient::fetchSearchAnalytics();
        
        // Limits & progress calculations
        $search_limit = $client_info['usage_limits']['search_queries_per_month'] ?? null;
        $index_limit = $client_info['usage_limits']['max_indexed_pages'] ?? null;
        $reindex_limit = $client_info['usage_limits']['reindexing_per_day'] ?? null;
        
        ?>
        <div class="searchjet-admin-wrap">
            <div class="searchjet-header">
                <h1><?php _e('Analytics', 'searchjet'); ?></h1>
                <div class="subtitle"><?php _e('Search performance insights and usage statistics', 'searchjet'); ?></div>
            </div>

            <div class="searchjet-content">
                <?php if (empty($client_info)) : ?>
                    <div class="searchjet-alert searchjet-alert-info">
                        <div>
                            <strong><?php _e('Connect Required', 'searchjet'); ?></strong><br>
                            <?php _e('Connect your site to SearchJet to view analytics and performance data.', 'searchjet'); ?>
                            <a href="<?php echo admin_url('admin.php?page=searchjet'); ?>" class="searchjet-btn searchjet-btn-primary" style="margin-top: 10px;">
                                <?php _e('Go to Settings', 'searchjet'); ?>
                            </a>
                        </div>
                    </div>
                <?php else : ?>


                <!-- GA4 Tracker Notice -->
                <div class="searchjet-alert searchjet-alert-info">
                    <div>
                        <?php echo sprintf(
                            esc_html__('Want to track your site analytics alongside search performance? Install our lightweight %1$sLean GA4 Tracker%2$s plugin to add Google Analytics 4 easily.', 'searchjet-instant-search'),
                            '<a href="https://wordpress.org/plugins/lean-ga4-tracker/" target="_blank">',
                            '</a>'
                        ); ?>
                    </div>
                </div>
                
                    <!-- Usage Statistics -->
                    <div class="searchjet-grid searchjet-grid-4">
                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number"><?php echo number_format($search_used); ?></div>
                            <div class="searchjet-stat-label"><?php _e('Searches This Month', 'searchjet'); ?></div>
                            <?php if ($search_limit): ?>
                                <div class="searchjet-progress">
                                    <div class="searchjet-progress-bar" style="width: <?php echo min(100, ($search_used / $search_limit) * 100); ?>%"></div>
                                </div>
                                <div class="searchjet-progress-text">
                                    <?php printf(__('%s of %s', 'searchjet'), number_format($search_used), number_format($search_limit)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number"><?php echo number_format($indexed_count); ?></div>
                            <div class="searchjet-stat-label"><?php _e('Indexed Documents', 'searchjet'); ?></div>
                            <?php if ($index_limit): ?>
                                <div class="searchjet-progress">
                                    <div class="searchjet-progress-bar" style="width: <?php echo min(100, ($indexed_count / $index_limit) * 100); ?>%"></div>
                                </div>
                                <div class="searchjet-progress-text">
                                    <?php printf(__('%s of %s', 'searchjet'), number_format($indexed_count), number_format($index_limit)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number"><?php echo $reindexes_today; ?></div>
                            <div class="searchjet-stat-label"><?php _e('Reindexes Today', 'searchjet'); ?></div>
                            <?php if ($reindex_limit): ?>
                                <div class="searchjet-progress-text">
                                    <?php printf(__('Limit: %s/day', 'searchjet'), $reindex_limit); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number"><?php echo esc_html($client_info['plan'] ?? 'Free'); ?></div>
                            <div class="searchjet-stat-label"><?php _e('Current Plan', 'searchjet'); ?></div>
                            <div class="searchjet-progress-text">
                                <a href="https://searchjetengine.com/pricing" target="_blank"><?php _e('Upgrade Plan', 'searchjet'); ?></a>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($search_analytics)) : ?>
                        <!-- Analytics Data -->
                        <div class="searchjet-grid searchjet-grid-2" style="margin-top: 32px;">
                            <div class="searchjet-card">
                                <h2><?php _e('Top Search Queries', 'searchjet'); ?></h2>
                                <?php if (!empty($search_analytics['top_queries'])): ?>
                                    <div class="searchjet-analytics-list">
                                        <?php foreach (array_slice($search_analytics['top_queries'], 0, 10) as $item): ?>
                                            <div class="searchjet-analytics-item">
                                                <span class="query"><?php echo esc_html($item['query']); ?></span>
                                                <span class="count"><?php echo number_format($item['count'] ?? 0); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="searchjet-empty-state">
                                        <p><?php _e('No search queries recorded yet.', 'searchjet'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="searchjet-card">
                                <h2><?php _e('Zero-Result Queries', 'searchjet'); ?></h2>
                                <?php if (!empty($search_analytics['zero_result_queries'])): ?>
                                    <div class="searchjet-analytics-list">
                                        <?php foreach (array_slice($search_analytics['zero_result_queries'], 0, 10) as $item): ?>
                                            <div class="searchjet-analytics-item">
                                                <span class="query"><?php echo esc_html($item['query']); ?></span>
                                                <span class="count"><?php echo number_format($item['count'] ?? 0); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="searchjet-empty-state">
                                        <p><?php _e('No zero-result queries recorded.', 'searchjet'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($search_analytics['search_volume_by_day'])): ?>
                            <div class="searchjet-card" style="margin-top: 32px;">
                                <h2><?php _e('Daily Search Volume', 'searchjet'); ?></h2>
                                <div class="searchjet-table-container">
                                    <table class="searchjet-table">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Date', 'searchjet'); ?></th>
                                                <th><?php _e('Searches', 'searchjet'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($search_analytics['search_volume_by_day'] as $day => $count): ?>
                                                <tr>
                                                    <td><?php echo esc_html($day); ?></td>
                                                    <td><?php echo number_format($count); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="searchjet-card" style="margin-top: 32px;">
                            <div class="searchjet-empty-state">
                                <h3><?php _e('No Analytics Data', 'searchjet'); ?></h3>
                                <p><?php _e('Analytics data will appear here once users start searching your site.', 'searchjet'); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function renderAutomationsProxy() {
        if (class_exists('\SearchJet\Admin\AutomationPage') && is_callable(['\SearchJet\Admin\AutomationPage', 'render'])) {
            \SearchJet\Admin\AutomationPage::render();
            return;
        }
        
        // Enhanced fallback design
        ?>
        <div class="searchjet-admin-wrap">
            <div class="searchjet-header">
                <h1><?php _e('Automations', 'searchjet'); ?></h1>
                <div class="subtitle"><?php _e('Intelligent search optimization and automated improvements', 'searchjet'); ?></div>
            </div>

            <div class="searchjet-content">
                <div class="searchjet-alert searchjet-alert-info">
                    <div>
                        <strong><?php _e('Automations Module', 'searchjet'); ?></strong><br>
                        <?php _e('The Automations module provides intelligent search optimization features.', 'searchjet'); ?>
                    </div>
                </div>

                <div class="searchjet-grid searchjet-grid-2">
                    <div class="searchjet-card">
                        <h2><?php _e('Zero-Result Query Detection', 'searchjet'); ?></h2>
                        <p><?php _e('Automatically detect when users search for terms that return no results and take action to improve content or search configuration.', 'searchjet'); ?></p>
                        <div class="searchjet-feature-list">
                            <div class="searchjet-feature-item">
                                <span class="searchjet-feature-icon">🔍</span>
                                <span><?php _e('Real-time monitoring', 'searchjet'); ?></span>
                            </div>
                            <div class="searchjet-feature-item">
                                <span class="searchjet-feature-icon">📊</span>
                                <span><?php _e('Trend analysis', 'searchjet'); ?></span>
                            </div>
                            <div class="searchjet-feature-item">
                                <span class="searchjet-feature-icon">⚡</span>
                                <span><?php _e('Automated alerts', 'searchjet'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="searchjet-card">
                        <h2><?php _e('Search Performance Optimization', 'searchjet'); ?></h2>
                        <p><?php _e('Continuously optimize search performance based on user behavior and search patterns.', 'searchjet'); ?></p>
                        <div class="searchjet-feature-list">
                            <div class="searchjet-feature-item">
                                <span class="searchjet-feature-icon">🎯</span>
                                <span><?php _e('Query optimization', 'searchjet'); ?></span>
                            </div>
                            <div class="searchjet-feature-item">
                                <span class="searchjet-feature-icon">📈</span>
                                <span><?php _e('Performance tracking', 'searchjet'); ?></span>
                            </div>
                            <div class="searchjet-feature-item">
                                <span class="searchjet-feature-icon">🔧</span>
                                <span><?php _e('Auto-tuning', 'searchjet'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="searchjet-card">
                    <h2><?php _e('Configuration', 'searchjet'); ?></h2>
                    <div class="searchjet-grid searchjet-grid-2">
                        <div class="searchjet-form-group">
                            <label><?php _e('Zero-Result Threshold', 'searchjet'); ?></label>
                            <input type="number" value="<?php echo esc_attr(get_option('searchjet_zr_threshold', 5)); ?>" class="searchjet-input" readonly>
                            <div class="description"><?php _e('Number of zero-result searches before triggering automation.', 'searchjet'); ?></div>
                        </div>
                        <div class="searchjet-form-group">
                            <label><?php _e('Time Window (seconds)', 'searchjet'); ?></label>
                            <input type="number" value="<?php echo esc_attr(get_option('searchjet_zr_window', 600)); ?>" class="searchjet-input" readonly>
                            <div class="description"><?php _e('Time window for counting zero-result searches.', 'searchjet'); ?></div>
                        </div>
                    </div>
                    <div class="searchjet-form-group">
                        <label>
                            <input type="checkbox" <?php checked(get_option('searchjet_automations_enabled', 1)); ?> disabled>
                            <?php _e('Enable Automations', 'searchjet'); ?>
                        </label>
                        <div class="description"><?php _e('Enable or disable automated search optimizations.', 'searchjet'); ?></div>
                    </div>
                </div>

                <div class="searchjet-alert searchjet-alert-warning">
                    <div>
                        <strong><?php _e('Module Not Available', 'searchjet'); ?></strong><br>
                        <?php _e('The Automations module is not currently available. Please ensure it is properly installed and initialized.', 'searchjet'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function renderAgentsProxy() {
        if (class_exists('\SearchJet\Admin\AgentsPage') && is_callable(['\SearchJet\Admin\AgentsPage', 'render'])) {
            \SearchJet\Admin\AgentsPage::render();
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('SearchJet AI Agents', 'searchjet-instant-search') . '</h1>';
        echo '<p>' . esc_html__('The AI Agents module is not available. Please make sure it is included and initialized.', 'searchjet-instant-search') . '</p></div>';
    }
    
    private static function renderOngoingGuidance($limits, $indexed_count, $client_info) {
        $search_used = $limits['used_search_queries'] ?? 0;
        $search_limit = $limits['search_queries_per_month'] ?? null;
        $plan = $client_info['plan'] ?? 'free';
        $searches_today = get_transient('searchjet_searches_today') ?: 0;
        
        // Determine guidance based on usage patterns
        $guidance_items = [];
        
        // Usage optimization tips
        if ($search_limit && $search_used > ($search_limit * 0.8)) {
            $guidance_items[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => __('High Usage Alert', 'searchjet'),
                'message' => sprintf(__('You\'ve used %d%% of your monthly search quota. Consider upgrading your plan.', 'searchjet'), round(($search_used / $search_limit) * 100)),
                'action' => [
                    'text' => __('Upgrade Plan', 'searchjet'),
                    'url' => 'https://searchjetengine.com/pricing'
                ]
            ];
        }
        
        // Performance insights
        if ($searches_today > 50) {
            $guidance_items[] = [
                'type' => 'success',
                'icon' => '📈',
                'title' => __('Great Search Activity!', 'searchjet'),
                'message' => sprintf(__('Your site had %d searches today. Users are actively engaging with your search.', 'searchjet'), $searches_today),
                'action' => [
                    'text' => __('View Analytics', 'searchjet'),
                    'url' => admin_url('admin.php?page=searchjet-analytics')
                ]
            ];
        }
        
        // Content optimization
        if ($indexed_count > 0 && $searches_today < 5) {
            $guidance_items[] = [
                'type' => 'info',
                'icon' => '💡',
                'title' => __('Boost Search Visibility', 'searchjet'),
                'message' => __('Low search activity detected. Consider adding a prominent search box or promoting search features.', 'searchjet'),
                'action' => [
                    'text' => __('Customization Tips', 'searchjet'),
                    'url' => '#ui-customization',
                    'onclick' => 'switchToSearchJetTab("ui-customization"); return false;',
                ]
            ];
        }
        
        // Plan recommendations
        if ($plan === 'free' && $search_used > 500) {
            $guidance_items[] = [
                'type' => 'info',
                'icon' => '🚀',
                'title' => __('Ready to Scale?', 'searchjet'),
                'message' => __('Your search usage suggests you might benefit from a paid plan with more features and higher limits.', 'searchjet'),
                'action' => [
                    'text' => __('Compare Plans', 'searchjet'),
                    'url' => 'https://searchjetengine.com/pricing'
                ]
            ];
        }
        
        // Maintenance reminders
        $last_reindex = get_option('searchjet_last_reindex_time', 0);
        if ($last_reindex && (time() - $last_reindex) > (7 * 24 * 60 * 60)) { // 7 days
            $guidance_items[] = [
                'type' => 'info',
                'icon' => '🔄',
                'title' => __('Content Refresh Recommended', 'searchjet'),
                'message' => __('It\'s been a week since your last reindex. Consider refreshing your search index to include recent content.', 'searchjet'),
                'action' => [
                    'text' => __('Reindex Now', 'searchjet'),
                    'url' => '#indexing',
                    'onclick' => 'document.querySelector("a[href=\"#indexing\"]").click(); return false;'
                ]
            ];
        }
        
        // Default helpful tips if no specific guidance
        if (empty($guidance_items)) {
            $tips = [
                [
                    'icon' => '🎨',
                    'title' => __('Customize Your Search', 'searchjet'),
                    'message' => __('Make your search interface match your brand with custom colors and themes.', 'searchjet'),
                    'action' => [
                        'text' => __('Customize UI', 'searchjet'),
                        'url' => '#ui-customization',
                        'onclick' => 'document.querySelector("a[href=\"#ui-customization\"]").click(); return false;'
                    ]
                ],
                [
                    'icon' => '📊',
                    'title' => __('Monitor Performance', 'searchjet'),
                    'message' => __('Track search queries and user behavior to optimize your content strategy.', 'searchjet'),
                    'action' => [
                        'text' => __('View Analytics', 'searchjet'),
                        'url' => admin_url('admin.php?page=searchjet-analytics')
                    ]
                ],
                [
                    'icon' => '⚡',
                    'title' => __('Explore Automations', 'searchjet'),
                    'message' => __('Set up automated workflows to optimize search performance and user experience.', 'searchjet'),
                    'action' => [
                        'text' => __('Learn More', 'searchjet'),
                        'url' => admin_url('admin.php?page=searchjet-automations')
                    ]
                ]
            ];
            
            // Show random tip
            $guidance_items[] = array_merge($tips[array_rand($tips)], ['type' => 'tip']);
        }
        
        if (!empty($guidance_items)): ?>
            <div class="searchjet-guidance-section">
                <h3><?php _e('💡 Recommendations', 'searchjet'); ?></h3>
                <div class="searchjet-guidance-grid">
                    <?php foreach ($guidance_items as $item): ?>
                        <div class="searchjet-guidance-card searchjet-guidance-<?php echo esc_attr($item['type']); ?>">
                            <div class="searchjet-guidance-icon"><?php echo $item['icon']; ?></div>
                            <div class="searchjet-guidance-content">
                                <h4><?php echo esc_html($item['title']); ?></h4>
                                <p><?php echo esc_html($item['message']); ?></p>
                                <?php if (isset($item['action'])): ?>
                                    <a href="<?php echo esc_url($item['action']['url']); ?>" 
                                       class="searchjet-btn searchjet-btn-sm searchjet-btn-secondary"
                                       <?php if (isset($item['action']['onclick'])): ?>onclick="<?php echo esc_attr($item['action']['onclick']); ?>; return false;"<?php endif; ?>
                                       <?php if (strpos($item['action']['url'], 'http') === 0): ?>target="_blank"<?php endif; ?>>
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