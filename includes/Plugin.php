<?php
namespace SearchJet;

use SearchJet\Shortcodes\FederatedSearch;
use SearchJet\Shortcodes\SearchForm;
use SearchJet\Helpers\Defaults;


use SearchJet\API\IndexSync;
use SearchJet\API\AnalyticsEndpoint;
use SearchJet\API\ClickAnalyticsEndpoint;
use SearchJet\API\AIAnswerEndpoint;
use SearchJet\Admin\AutomationPage;
use SearchJet\Admin\AgentsPage;
use SearchJet\Admin\DocumentationPage;
use SearchJet\Admin\DashboardWidget;
use SearchJet\Admin\IndexSettingsPage;
use SearchJet\Admin\TaskHistoryPage;
use SearchJet\Admin\IndexHealthPage;
use SearchJet\Admin\BulkReindexPage;
use SearchJet\Automations\Manager as AutomationsManager;
use SearchJet\Automations\Events as AutomationsEvents;
use SearchJet\Integrations\SeoPluginSupport;
use SearchJet\Integrations\WPMLSupport;
use SearchJet\Integrations\AstraSupport;
use SearchJet\Integrations\JetEngineSupport;
use SearchJet\Integrations\ElementorSupport;
use SearchJet\Integrations\GutenbergSupport;
use SearchJet\Integrations\AcfSupport;

class Plugin {
    public static function init() {

        add_action('init', [AutomationPage::class, 'init']);
        add_action('init', [AgentsPage::class, 'init']);
        add_action('init', [DocumentationPage::class, 'init']);
        add_action('init', [DashboardWidget::class, 'init']);
        add_action('init', [IndexSettingsPage::class, 'init']);
        add_action('init', [TaskHistoryPage::class, 'init']);
        add_action('init', [IndexHealthPage::class, 'init']);
        add_action('init', [BulkReindexPage::class, 'init']);
        add_action('init', [IndexSync::class, 'init']);
        add_action('init', [\SearchJet\Services\TaskStatusTracker::class, 'init']);
        FederatedSearch::init();
        SearchForm::init();
        if (!get_option('searchjet_disable_search_mode')) {
            add_action('template_redirect', [\SearchJet\Frontend\SearchOverride::class, 'init']);
        }
        add_action('wp_enqueue_scripts', [self::class, 'enqueueFrontendAssets']);
        add_action('rest_api_init', [AnalyticsEndpoint::class, 'registerRoutes']);
        add_action('rest_api_init', [ClickAnalyticsEndpoint::class, 'registerRoutes']);
        add_action('rest_api_init', [AIAnswerEndpoint::class, 'registerRoutes']);
        // Admin pages: Automations & Agents
        \SearchJet\Rest\EventsController::init();
        // Boot automations core (registry, schedules, event helpers)
        if (!defined('SEARCHJET_DISABLE_AUTOMATIONS') || !SEARCHJET_DISABLE_AUTOMATIONS) {
            if (class_exists(AutomationsManager::class)) {
                AutomationsManager::init();
            }
            if (class_exists(AutomationsEvents::class)) {
                AutomationsEvents::init();
            }
        }
       if (class_exists(SeoPluginSupport::class)) {
             SeoPluginSupport::init();
         }
        if (class_exists(WPMLSupport::class)) {
            WPMLSupport::init();
        }
         if (class_exists(AstraSupport::class)) {
            AstraSupport::init();
        }
        if (class_exists(JetEngineSupport::class)) {
            JetEngineSupport::init();
        }
        if (class_exists(ElementorSupport::class)) {
            ElementorSupport::init();
        }
        if (class_exists(GutenbergSupport::class)) {
            GutenbergSupport::init();
        }
        if (class_exists(AcfSupport::class)) {
            AcfSupport::init();
        }
        // Schedule daily log batch event if not already scheduled
        if (!wp_next_scheduled('searchjet_send_log_batch')) {
            wp_schedule_event(time(), 'daily', 'searchjet_send_log_batch');
        }

        // Schedule daily click analytics batch event if not already scheduled
        if (!wp_next_scheduled('searchjet_send_click_batch')) {
            wp_schedule_event(time(), 'daily', 'searchjet_send_click_batch');
        }

        add_action('searchjet_send_log_batch', [self::class, 'sendLogBatch']);
        add_action('searchjet_send_click_batch', [self::class, 'sendClickBatch']);
    }

    public static function activate() {
        $defaults = Defaults::options();
        foreach ($defaults as $option => $default_value) {
            if (get_option($option, null) === null) {
                update_option($option, $default_value);
            }
        }
    }

    public static function deactivate() {
        // Unschedule daily batch if present
        $timestamp = wp_next_scheduled('searchjet_send_log_batch');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'searchjet_send_log_batch');
        }

        // Unschedule click analytics batch if present
        $timestamp = wp_next_scheduled('searchjet_send_click_batch');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'searchjet_send_click_batch');
        }
    }

    public static function sendLogBatch() {
        $logs = get_option('searchjet_log_backlog', []);
        if (empty($logs)) return;

        $clientInfo = get_option('searchjet_client_info', []);
        $formatted_logs = array_map(function ($log) {
            return [
                'query' => $log['query'] ?? '',
                'results_count' => $log['results'] ?? 0,
                'filters' => $log['filters'] ?? null,
                'referer' => $log['referer'] ?? null,
                'user_agent' => $log['user_agent'] ?? null,
                'session_id' => $log['session_id'] ?? null,
                'ip' => $log['ip'] ?? null,
                'timestamp' => isset($log['ts']) ? date('c', $log['ts']) : date('c'),
            ];
        }, $logs);

        $response = wp_remote_post(SEARCHJET_API_BASE_URL . '/api/v1/search-analytics', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . (get_option('searchjet_hosted_api_key', '')),
            ],
            'body' => wp_json_encode(['logs' => $formatted_logs]),
            'timeout' => 30,
        ]);

        if (!is_wp_error($response)) {
            delete_option('searchjet_log_backlog');
        } else {
            error_log('[SearchJet] Failed to send log batch: ' . $response->get_error_message());
        }
    }

    public static function sendClickBatch() {
        $clicks = get_option('searchjet_click_backlog', []);
        if (empty($clicks)) return;

        // Send clicks in batches of 50
        $batch_size = 50;
        $batches = array_chunk($clicks, $batch_size);
        $failed_clicks = [];

        foreach ($batches as $batch) {
            foreach ($batch as $click_data) {
                $sent = \SearchJet\API\SearchJetRemoteClient::recordClickAnalytics($click_data);
                if (!$sent) {
                    $failed_clicks[] = $click_data;
                }
            }
        }

        // Keep only failed clicks in backlog
        if (empty($failed_clicks)) {
            delete_option('searchjet_click_backlog');
        } else {
            update_option('searchjet_click_backlog', $failed_clicks, false);
            error_log('[SearchJet] Failed to send ' . count($failed_clicks) . ' click analytics entries');
        }
    }

    public static function enqueueFrontendAssets() {
        $assetVersion = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)
            ? time()
            : (defined('SEARCHJET_VERSION') ? SEARCHJET_VERSION : '1.0.0');

        // Always use SearchJet Connect with fallback to original implementation
        wp_enqueue_script(
            'searchjet-connect',
             plugin_dir_url(dirname(__FILE__)) . 'assets/js/searchjet-connect.min.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'searchjet-helpers',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/searchjet-helpers.js',
            [],
            $assetVersion,
            true
        );

        wp_enqueue_script(
            'searchjet-autocomplete',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/searchjet-autocomplete.js',
            ['searchjet-connect', 'searchjet-helpers'],
            $assetVersion,
            true
        );

        wp_enqueue_style(
            'searchjet-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/searchjet.css',
            [],
            $assetVersion
        );

        $clientInfo = get_option('searchjet_client_info', []);

        // Optional attributes to retrieve (comma or newline separated in settings)
        $attrs_raw  = get_option('searchjet_attributes', '');
        $attrs_list = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', (string) $attrs_raw)), function($v){ return $v !== ''; }));

        $opts = array_merge([
            'show_thumbnail' => 1,
            'show_price' => 1,
            'show_rating' => 1,
            'show_stock' => 1,
            'show_category' => 1,
            'highlight_term' => 1,
            'view_all_link' => 1,
        ], get_option('searchjet_ui_options', []));
        $settings = [
            'logApiUrl'           => rest_url('searchjet/v1/log'),
            'eventsEndpoint'      => rest_url('searchjet/v1/event'),
            'clickAnalyticsEndpoint' => rest_url('searchjet/v1/analytics/click'),
            'aiAnswerEndpoint'    => rest_url('searchjet/v1/ai/answer'),
            'aiUsageEndpoint'     => rest_url('searchjet/v1/ai/usage'),
            'restNonce'           => wp_create_nonce('wp_rest'),
            'requireEventNonce'   => (int) get_option('searchjet_require_event_nonce', 0),
            'searchApiBaseUrl'    => SEARCHJET_API_BASE_URL . '/api/v1/search',
            'key'                 => isset($clientInfo['public_key']) ? $clientInfo['public_key'] : '',
            'attributes'          => $attrs_list,
            'session_id'          => session_id() ?: wp_generate_uuid4(),
            'aiAnswersEnabled'    => (bool) get_option('searchjet_ai_answers_enabled', false),
            'language'            => function_exists('icl_get_languages') ? substr(get_locale(), 0, 2) : '',
            'ui' => [
                'showThumbnail'   => (bool) get_option('searchjet_show_thumbnails', 1),
                'showPrice'       => (bool) get_option('searchjet_show_prices', 1),
                'showRating'      => (bool) get_option('searchjet_show_rating', 1),
                'showStock'       => (bool) get_option('searchjet_show_stock', 1),
                'showCategory'    => (bool) get_option('searchjet_show_categories', 1),
                'showExcerpts'    => (bool) get_option('searchjet_show_excerpts', 1),
                'highlightTerms'  => (bool) ($opts['highlight_term'] ?? true),
                'viewAllLink'     => (bool) ($opts['view_all_link'] ?? true),
                'theme'           => get_option('searchjet_theme', 'default'),
                'primaryColor'    => get_option('searchjet_primary_color', '#000000'),
                'maxResults'      => (int) get_option('searchjet_max_results', 10),
            ],
            'integrations' => [
                'ga4' => function_exists('lean_ga4_tracker')
            ]
        ];

        // Localize onto the scripts we actually enqueue on the frontend
        wp_localize_script('searchjet-autocomplete', 'SearchJetSettings', $settings);

        // SearchOverride.php will handle search page scripts and use same settings

        add_action('wp_head', [__CLASS__, 'outputInlineStyles']);
    }

    public static function outputInlineStyles() {
        $opts = get_option('searchjet_ui_options', []);
        $theme = get_option('searchjet_theme', 'default');
        $primaryColor = get_option('searchjet_primary_color', '#000000');
        ?>
        <style id="searchjet-inline-style">
            :root {
                --searchjet-primary-color: <?= esc_attr($primaryColor) ?>;
                --searchjet-text-color: <?= esc_attr($primaryColor) ?>;
            }
            <?php if ($theme && $theme !== 'default'): ?>
            #searchjet-results {
                <?php if ($theme === 'dark'): ?>
                --searchjet-background: #1c1c1e;
                --searchjet-text-color: #ffffff;
                --searchjet-text-secondary: #8e8e93;
                --searchjet-border-color: #38383a;
                --searchjet-hover-bg: #2c2c2e;
                <?php elseif ($theme === 'minimal'): ?>
                --searchjet-border-radius: 0;
                --searchjet-shadow: 0 1px 3px rgba(0,0,0,0.1);
                --searchjet-spacing: 12px;
                <?php elseif ($theme === 'rounded'): ?>
                --searchjet-border-radius: 16px;
                <?php endif; ?>
            }
            <?php endif; ?>
            <?php if (!empty($opts['font_size'])): ?>
            #searchjet-results {
                font-size: <?= esc_attr($opts['font_size']) ?>px;
            }
            <?php endif; ?>
            <?php if (!empty($opts['title_color'])): ?>
            .searchjet-hit .hit-title {
                color: <?= esc_attr($opts['title_color']) ?>;
            }
            <?php endif; ?>
            <?php if (!empty($opts['highlight_color'])): ?>
            .searchjet-highlight {
                color: <?= esc_attr($opts['highlight_color']) ?>;
            }
            <?php endif; ?>
            <?php if (!empty($opts['background_color'])): ?>
            #searchjet-results {
                background-color: <?= esc_attr($opts['background_color']) ?>;
            }
            <?php endif; ?>
            <?php if (!empty($opts['border_radius'])): ?>
            #searchjet-results {
                border-radius: <?= esc_attr($opts['border_radius']) ?>px;
            }
            <?php endif; ?>
            <?php if (!empty($opts['image_size'])): ?>
            .searchjet-hit .product-image {
                width: <?= esc_attr($opts['image_size']) ?>px;
                height: <?= esc_attr($opts['image_size']) ?>px;
            }
            <?php endif; ?>
        </style>
        <?php
    }

}
