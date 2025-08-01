<?php
namespace SearchJet\Admin;

use SearchJet\Helpers\IndexHelper;
use SearchJet\Services\Reindexer;
use SearchJet\API\SearchJetRemoteClient;


class SettingsPage {

    /**
     * Register WordPress admin hooks and manual reindex action.
     */
    public static function init() {
        add_action('admin_menu', [self::class, 'registerMenu']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('wp_ajax_searchjet_reindex_all', [self::class, 'reindex_all']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueScripts']);
        // Always fetch client info when settings page is submitted and API key is present
        if (
            isset($_POST['option_page']) &&
            $_POST['option_page'] === 'searchjet_settings'
        ) {
            $new_key = trim($_POST['searchjet_hosted_api_key'] ?? '');
            if (!empty($new_key)) {
                $client_info = SearchJetRemoteClient::fetchClientInfo($new_key);
                update_option('searchjet_client_info', $client_info);
                set_transient('searchjet_usage_limits', $client_info['usage_limits'], 600); // 10 min
            } else {
                delete_option('searchjet_client_info');
            }
        }

        // Handle SearchJet token connection
        if (is_admin() && isset($_GET['searchjet_token'])) {
            $token = sanitize_text_field($_GET['searchjet_token']);
            $response = wp_remote_get('https://app.searchjetengine.com/api/v1/site/key-info?token=' . urlencode($token));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['api_key']) && !empty($data['site_id'])) {
                    update_option('searchjet_hosted_api_key', sanitize_text_field($data['api_key']));
                     $client_info = SearchJetRemoteClient::fetchClientInfo(sanitize_text_field($data['api_key']));
                    update_option('searchjet_client_info', $client_info);
                    set_transient('searchjet_usage_limits', $client_info['usage_limits'], 600); // 10 min
                    update_option('searchjet_site_id', intval($data['site_id']));
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('SearchJet is now connected successfully.', 'searchjet-instant-search') . '</p></div>';
                    });
                }
            } else {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to connect to SearchJet. Please try again.', 'searchjet-instant-search') . '</p></div>';
                });
            }
        }

        // Also refresh client info when settings page is loaded directly
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'searchjet-settings') {
            $existing_key = trim(get_option('searchjet_hosted_api_key', ''));
            if (!empty($existing_key)) {
                $client_info = SearchJetRemoteClient::fetchClientInfo($existing_key);
                update_option('searchjet_client_info', $client_info);
                set_transient('searchjet_usage_limits', $client_info['usage_limits'], 600); // 10 min
            }
        }
    }

    /**
     * Register the settings page in the WordPress admin menu.
     */
    public static function registerMenu() {
        add_options_page(
            'SearchJet Settings',
            'SearchJet',
            'manage_options',
            'searchjet-settings',
            [self::class, 'render']
        );
    }

    /**
     * Register the plugin's settings options.
     */
    public static function registerSettings() {
        register_setting('searchjet_settings', 'searchjet_hosted_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        // Exclusion options
        register_setting('searchjet_settings', 'searchjet_exclude_post_types', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('searchjet_settings', 'searchjet_include_status', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('searchjet_settings', 'searchjet_exclude_sticky', ['type' => 'boolean', 'sanitize_callback' => 'absint']);
        register_setting('searchjet_settings', 'searchjet_exclude_protected', ['type' => 'boolean', 'sanitize_callback' => 'absint']);
        register_setting('searchjet_settings', 'searchjet_exclude_mime_types', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        // Search mode setting
        register_setting('searchjet_settings', 'searchjet_search_mode', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'js'
        ]);
        // Disable search mode override setting
        register_setting('searchjet_settings', 'searchjet_disable_search_mode', ['type' => 'boolean', 'sanitize_callback' => 'absint']);
    }


    /**
     * Render the admin settings page and handle the manual reindex form.
     */
    public static function render() {

        // Additional admin notices for hosted service plan enforcement
        $client_info = get_option('searchjet_client_info', []);
        $existing_key = trim(get_option('searchjet_hosted_api_key', ''));
        $client_info = SearchJetRemoteClient::fetchClientInfo($existing_key);

        if (!empty($client_info)) {

            $plan = $client_info['plan'] ?? 'free';

            $limits = get_transient('searchjet_usage_limits');

            $search_used = $limits['used_search_queries'] ?? 0;

            $index_used = $limits['used_indexed_pages'] ?? 0;

            $reindexes_today = $limits['reindexes_today'] ?? 0;
            
            $reindexing_per_day = $limits['reindexing_per_day'] ?? 0;

            // Deprecated daily limit check removed

            if ($plan === 'free') {
                echo '<div class="notice notice-info"><p><strong>' . esc_html__('Using Free Tier:', 'searchjet-instant-search') . '</strong> ' .
                    esc_html__('You are limited to 1,000 search queries and 200 indexed pages per month. For more features, upgrade at ', 'searchjet-instant-search') .
                    '<a href="https://searchjetengine.com/pricing" target="_blank">SearchJet Pricing</a>.</p></div>';
            }
        }

        // Get count from SearchJet API
        $indexed_count = SearchJetRemoteClient::fetchDocumentCount();

        if (
            isset($_POST['searchjet_reindex']) &&
            current_user_can('manage_options') &&
            check_admin_referer('searchjet_reindex_action', 'searchjet_nonce')
        ) {
            do_action('searchjet_manual_reindex');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Reindexing initiated.', 'searchjet-instant-search') . '</p></div>';
        }
        ?>
        <style>
        #searchjet_reindex_wrapper{
            margin: 20px 0;
        }
        #searchjet-progress-container{
        margin-top: 20px; display: none;
        }
        #searchjet-settings .form-table th {
            padding-top: 15px;
            font-weight: 600;
            font-size: 14px;
            color: #222;
        }

        #searchjet-settings h2 {
            border-left: 4px solid #007cba;
            padding-left: 10px;
            font-size: 16px;
            margin-top: 30px;
            margin-bottom: 10px;
            color: #0073aa;
        }

        #searchjet-settings code {
            background: #f6f7f7;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
        }

        #searchjet-settings .notice-info {
            background: #eef9ff;
            border-left-color: #00a0d2;
        }

        #searchjet-settings input[type="text"] {
            max-width: 500px;
        }

        #searchjet-settings .button-primary {
            margin-top: 20px;
        }

        #searchjet-settings .button-secondary {
            margin-top: 10px;
        }

        .searchjet-usage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .searchjet-usage-grid div {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        </style>
        <div id="searchjet-settings">
        <div class="wrap">
            <h1><?php esc_html_e('SearchJet Settings', 'searchjet-instant-search'); ?></h1>
            <?php if (empty($client_info)) : ?>
            <div class="notice notice-info">
                <p>
                    <?php echo sprintf(
                        esc_html__('To use SearchJet’s hosted service, please visit the %1$sSearchJet Dashboard%2$s to create a project and obtain your API key.', 'searchjet-instant-search'),
                        '<a href="https://app.searchjetengine.com/dashboard" target="_blank">',
                        '</a>'
                    ); ?>
                </p>
            </div>
            <?php endif; ?>            
            <form method="post" action="options.php">
                <?php
                settings_fields('searchjet_settings');
                do_settings_sections('searchjet_settings');
                // Removed $searchjet_use_hosted and $sj_hosted_checked variables as hosted fields are always shown.
                ?>
                <table class="form-table">
                    <tr class="searchjet-api-key-row searchjet-hosted">
                        <th colspan="2"><h2 style="margin-top: 20px;"><?php esc_html_e('SearchJet Hosted API Key', 'searchjet-instant-search'); ?></h2></th>
                    </tr>
                    <tr class="searchjet-api-key-row searchjet-hosted">
                        <th scope="row">
                            <label for="searchjet_hosted_api_key"><?php esc_html_e('SearchJet API Key', 'searchjet-instant-search'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="searchjet_hosted_api_key" name="searchjet_hosted_api_key" value="<?php echo esc_attr(get_option('searchjet_hosted_api_key')); ?>" class="regular-text" placeholder="<?php esc_attr_e('Enter your SearchJet API key', 'searchjet-instant-search'); ?>">
                            <p class="description"><?php esc_html_e('Get your API key from your SearchJet dashboard.', 'searchjet-instant-search'); ?></p>
                            <p><a href="https://app.searchjetengine.com/dashboard" target="_blank"><?php esc_html_e('Go to SearchJet Dashboard', 'searchjet-instant-search'); ?></a></p>
                            <p>
                                <?php
                                $site_url = site_url();
                                $site_name = get_bloginfo('name');
                                $link_url = 'https://app.searchjetengine.com/link?site=' . urlencode($site_url)
                                    . '&redirect=' . urlencode(admin_url('options-general.php?page=searchjet-settings'))
                                    . '&label=' . urlencode($site_name);
                                ?>
                                <a href="<?php echo esc_url($link_url); ?>" class="button button-secondary">
                                    <?php esc_html_e('Connect to SearchJet', 'searchjet-instant-search'); ?>
                                </a>
                            </p>
                        </td>
                    </tr>

                    <!-- Exclusions Section -->
                    <tr class="searchjet-exclusions">
                        <th colspan="2"><h2 style="margin-top: 30px;"><?php esc_html_e('Content Exclusions', 'searchjet-instant-search'); ?></h2></th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="searchjet_exclude_post_types"><?php esc_html_e('Exclude Post Types', 'searchjet-instant-search'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="searchjet_exclude_post_types" name="searchjet_exclude_post_types" value="<?php echo esc_attr(get_option('searchjet_exclude_post_types')); ?>" class="regular-text" placeholder="attachment, product">
                            <p class="description"><?php esc_html_e('Comma-separated list of post types to exclude.', 'searchjet-instant-search'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="searchjet_include_status"><?php esc_html_e('Include Post Statuses', 'searchjet-instant-search'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="searchjet_include_status" name="searchjet_include_status" value="<?php echo esc_attr(get_option('searchjet_include_status')); ?>" class="regular-text" placeholder="publish">
                            <p class="description"><?php esc_html_e('Comma-separated list of post statuses to include in indexing. Default is "publish".', 'searchjet-instant-search'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="searchjet_exclude_sticky"><?php esc_html_e('Exclude Sticky Posts?', 'searchjet-instant-search'); ?></label>
                        </th>
                        <td>
                            <label><input type="checkbox" name="searchjet_exclude_sticky" value="1" <?php checked(get_option('searchjet_exclude_sticky'), 1); ?> /> <?php esc_html_e('Yes', 'searchjet-instant-search'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="searchjet_exclude_protected"><?php esc_html_e('Exclude Password-Protected Posts?', 'searchjet-instant-search'); ?></label>
                        </th>
                        <td>
                            <label><input type="checkbox" name="searchjet_exclude_protected" value="1" <?php checked(get_option('searchjet_exclude_protected'), 1); ?> /> <?php esc_html_e('Yes', 'searchjet-instant-search'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="searchjet_exclude_mime_types"><?php esc_html_e('Exclude MIME Types', 'searchjet-instant-search'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="searchjet_exclude_mime_types" name="searchjet_exclude_mime_types" value="<?php echo esc_attr(get_option('searchjet_exclude_mime_types')); ?>" class="regular-text" placeholder="image/jpeg, application/pdf">
                            <p class="description"><?php esc_html_e('Comma-separated MIME types to exclude.', 'searchjet-instant-search'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Disable Search Rendering Mode override?', 'searchjet-instant-search'); ?></th>
                        <td>
                            <label><input type="checkbox" name="searchjet_disable_search_mode" value="1" <?php checked(get_option('searchjet_disable_search_mode'), 1); ?> />
                            <?php esc_html_e('Disable and fallback to WordPress default search rendering.', 'searchjet-instant-search'); ?></label>
                        </td>
                    </tr>

                    <!-- Search Rendering Mode -->
                    <?php if (!get_option('searchjet_disable_search_mode')) : ?>
                    <tr>
                        <th scope="row">
                            <label for="searchjet_search_mode"><?php esc_html_e('Search Rendering Mode', 'searchjet-instant-search'); ?></label>
                        </th>
                        <td>
                            <select name="searchjet_search_mode" id="searchjet_search_mode">
                                <option value="js" <?php selected(get_option('searchjet_search_mode'), 'js'); ?>><?php esc_html_e('JavaScript (Instant)', 'searchjet-instant-search'); ?></option>
                                <option value="template" <?php selected(get_option('searchjet_search_mode'), 'template'); ?>><?php esc_html_e('Template Override (PHP)', 'searchjet-instant-search'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose how the main search results page is rendered.', 'searchjet-instant-search'); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>


                    <?php
                    if (!empty($client_info)) :
                    ?>
                    <tr class="searchjet-api-key-row searchjet-hosted">
                        <th colspan="2"><h2 style="margin-top: 20px;"><?php esc_html_e('Search Analytic', 'searchjet-instant-search'); ?></h2></th>
                    </tr>
                    <?php
                    $search_analytics = \SearchJet\API\SearchJetRemoteClient::fetchSearchAnalytics();
                    ?>
                    <?php if (!empty($search_analytics)) : ?>
                        <tr class="searchjet-api-key-row searchjet-hosted">
                            <td colspan="2">
                                <div class="searchjet-usage-grid">
                                    <div>
                                        <strong><?php esc_html_e('Top Queries', 'searchjet-instant-search'); ?></strong><br>
                                        <code>
                                            <?php
                                            echo !empty($search_analytics['top_queries'])
                                                ? implode(', ', array_map(function($item) {
                                                    return $item['query'] . ' (' . $item['count'] . ')';
                                                }, array_slice($search_analytics['top_queries'], 0, 5)))
                                                : esc_html__('None', 'searchjet-instant-search');
                                            ?>
                                        </code>
                                    </div>
                                    <div>
                                        <strong><?php esc_html_e('Zero Result Queries', 'searchjet-instant-search'); ?></strong><br>
                                        <code>
                                            <?php
                                            echo !empty($search_analytics['zero_result_queries'])
                                                ? implode(', ', array_map(function($item) {
                                                    return $item['query'] . ' (' . $item['count'] . ')';
                                                }, array_slice($search_analytics['zero_result_queries'], 0, 5)))
                                                : esc_html__('None', 'searchjet-instant-search');
                                            ?>
                                        </code>
                                    </div>
                                    <div>
                                        <strong><?php esc_html_e('Total Searches', 'searchjet-instant-search'); ?></strong><br>
                                        <code><?php echo intval($search_analytics['total_searches'] ?? 0); ?></code>
                                    </div>
                                    <div>
                                        <strong><?php esc_html_e('Searches by Day (last 7)', 'searchjet-instant-search'); ?></strong><br>
                                        <code>
                                            <?php
                                            if (!empty($search_analytics['search_volume_by_day'])) {
                                                foreach ($search_analytics['search_volume_by_day'] as $day => $count) {
                                                    echo esc_html("$day: $count") . '<br>';
                                                }
                                            } else {
                                                echo esc_html__('No data', 'searchjet-instant-search');
                                            }
                                            ?>
                                        </code>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <!-- Plan Usage Summary -->
                    <tr class="searchjet-api-key-row searchjet-hosted">
                        <th colspan="2"><h2 style="margin-top: 20px;"><?php esc_html_e('API Usage Report', 'searchjet-instant-search'); ?></h2></th>
                    </tr>
                    <tr class="searchjet-api-key-row searchjet-hosted">
                        <td colspan="2">
                            <div class="searchjet-usage-grid">
                                <div><strong><?php esc_html_e('Current Plan', 'searchjet-instant-search'); ?></strong><br><code><?php echo esc_html($client_info['plan'] ?? 'Free'); ?></code></div>
                                <div><strong><?php esc_html_e('Monthly Search Limit', 'searchjet-instant-search'); ?></strong><br><code><?php echo esc_html($client_info['usage_limits']['search_queries_per_month'] ?? ''); ?></code></div>
                                <div><strong><?php esc_html_e('Index Limit', 'searchjet-instant-search'); ?></strong><br><code><?php echo isset($client_info['usage_limits']['max_indexed_pages']) && $client_info['usage_limits']['max_indexed_pages'] !== null ? esc_html($client_info['usage_limits']['max_indexed_pages']) : 'Unlimited'; ?></code></div>
                                <div><strong><?php esc_html_e('Currently Indexed Documents', 'searchjet-instant-search'); ?></strong><br><code><?php echo $indexed_count !== null ? esc_html($indexed_count) : esc_html__('Unable to retrieve', 'searchjet-instant-search'); ?></code></div>
                                <div><strong><?php esc_html_e('Reindexing Per Day', 'searchjet-instant-search'); ?></strong><br><code><?php echo isset($client_info['usage_limits']['reindexing_per_day']) && $client_info['usage_limits']['reindexing_per_day'] !== null ? esc_html($client_info['usage_limits']['reindexing_per_day']) : 'Unlimited'; ?></code></div>
                                
                            </div>
                             <div class="searchjet-usage-grid">
                                <div><strong><?php esc_html_e('Search Queries This Month', 'searchjet-instant-search'); ?></strong><br><code><?php echo esc_html($search_used); ?></code></div>
                                <div><strong><?php esc_html_e('Indexed Pages', 'searchjet-instant-search'); ?></strong><br><code><?php echo esc_html($index_used); ?></code></div>
                                <div><strong><?php esc_html_e('Reindexes Today', 'searchjet-instant-search'); ?></strong><br><code><?php echo esc_html($reindexes_today); ?></code></div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                </table>
                <?php submit_button(); ?>
            </form>

            <form method="post" action="" id="searchjet-reindex-form" style="display:none;">
                <?php wp_nonce_field('searchjet_reindex_action', 'searchjet_nonce'); ?>
            </form>
            <div id="searchjet_reindex_wrapper">
                <button id="searchjet_reindex" class="button button-primary">
                    <?php esc_html_e('Reindex Now', 'searchjet-instant-search'); ?>
                </button>
            </div>
            <div id="searchjet-progress-container">
                <label><?php esc_html_e('Reindex Progress:', 'searchjet-instant-search'); ?></label>
                <div style="width: 100%; background-color: #f3f3f3; border: 1px solid #ccc; border-radius: 4px;">
                    <div id="searchjet-progress-bar" style="width: 0%; background-color: #0073aa; height: 20px; color: white; text-align: center; line-height: 20px; border-radius: 4px;">
                        0%
                    </div>
                </div>
            </div>
            <p class="description"><?php esc_html_e('Click to reindex all published posts, pages, and products into Meilisearch. This may take a few moments.', 'searchjet-instant-search'); ?></p>
        
        
        </div>
        
        </div>
        <?php

    }

    public static function reindex_all() {
        check_ajax_referer('searchjet_reindex_nonce');

        $reindexer = new \SearchJet\Services\Reindexer();
        $result = $reindexer->reindexAll();

        $message = $result['message'] ?? '';
        $total = $result['total_indexed'] ?? 0;

        $client_info = get_option('searchjet_client_info');
        $max_pages = $client_info['usage_limits']['max_indexed_pages'] ?? null;
        $current_count = \SearchJet\API\SearchJetRemoteClient::fetchDocumentCount();

        if ($max_pages !== null && $current_count >= $max_pages) {
            wp_send_json_error([
                'message' => __('Index limit reached. You have indexed ' . $current_count . ' documents, which meets or exceeds your plan\'s limit of ' . $max_pages . '.', 'searchjet-instant-search'),
                'processed' => $total,
                'done' => true,
            ]);
        }

        wp_send_json_success([
            'processed' => $total,
            'done' => true,
            'message' => $message,
        ]);
    }

    public static function enqueueScripts($hook) {
        if ($hook !== 'settings_page_searchjet-settings') {
            return;
        }

        wp_enqueue_script('searchjet-admin', SEARCHJET_PLUGIN_URL . 'assets/js/admin-reindex.js', ['jquery'], '1.0', true);
        wp_localize_script('searchjet-admin', 'SearchJetReindex', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('searchjet_reindex_nonce'),
            'ajax_action' => 'searchjet_reindex_all',
            'search_mode' => get_option('searchjet_search_mode', 'js'),
        ]);
    }
}
