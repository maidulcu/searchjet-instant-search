<?php
namespace SearchJet;


use SearchJet\API\IndexSync;
use SearchJet\API\AnalyticsEndpoint;
use SearchJet\Admin\AutomationPage;
use SearchJet\Admin\AgentsPage;
use SearchJet\Admin\DocumentationPage;
use SearchJet\Admin\DashboardWidget;
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
        add_action('init', [IndexSync::class, 'init']);
        if (!get_option('searchjet_disable_search_mode')) {
            add_action('template_redirect', [\SearchJet\Frontend\SearchOverride::class, 'init']);
        }
        add_action('wp_enqueue_scripts', [self::class, 'enqueueFrontendAssets']);
        add_action('rest_api_init', [AnalyticsEndpoint::class, 'registerRoutes']);
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

        add_action('searchjet_send_log_batch', [self::class, 'sendLogBatch']);
    }

    public static function activate() {
        if (!get_option('searchjet_api_key')) {
            update_option('searchjet_api_key', '');
        }
        if (!get_option('searchjet_project_id')) {
            update_option('searchjet_project_id', '');
        }
        // Defaults for automations and event security
        if (get_option('searchjet_automations_enabled', null) === null) {
            update_option('searchjet_automations_enabled', 1);
        }
        if (get_option('searchjet_zr_threshold', null) === null) {
            update_option('searchjet_zr_threshold', 5); // 5 zero-result searches
        }
        if (get_option('searchjet_zr_window', null) === null) {
            update_option('searchjet_zr_window', 600); // 10 minutes
        }
        if (get_option('searchjet_require_event_nonce', null) === null) {
            update_option('searchjet_require_event_nonce', 0); // public by default
        }
        // Set defaults for searchjet_ui_options if not already defined
        if (get_option('searchjet_ui_options', null) === null) {
            update_option('searchjet_ui_options', [
                'show_thumbnail' => 1,
                'show_price' => 1,
                'show_rating' => 1,
                'show_stock' => 1,
                'show_category' => 1,
                'highlight_term' => 1,
                'view_all_link' => 1,
            ]);
        }
    }

    public static function deactivate() {
        // Unschedule daily batch if present
        $timestamp = wp_next_scheduled('searchjet_send_log_batch');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'searchjet_send_log_batch');
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

         //error_log('[SearchJet] Anytic send log batch: ' . print_r($response, true));

        if (!is_wp_error($response)) {
            delete_option('searchjet_log_backlog');
        } else {
            error_log('[SearchJet] Failed to send log batch: ' . $response->get_error_message());
        }
    }

    public static function enqueueFrontendAssets() {
        $assetVersion = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)
            ? time()
            : (defined('SEARCHJET_VERSION') ? SEARCHJET_VERSION : '1.0.0');

        wp_enqueue_script(
            'searchjet-result',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/searchjet.js',
            [],
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
            'restNonce'           => wp_create_nonce('wp_rest'),
            'requireEventNonce'   => (int) get_option('searchjet_require_event_nonce', 0),
            'searchApiBaseUrl'    => SEARCHJET_API_BASE_URL . '/api/v1/search',
            'key'                 => isset($clientInfo['public_key']) ? $clientInfo['public_key'] : '',
            'attributes'          => $attrs_list,
            'ui' => [
                'showThumbnail'   => (bool) (get_option('searchjet_show_thumbnails', 1)),
                'showPrice'       => (bool) (get_option('searchjet_show_prices', 1)),
                'showRating'      => (bool) ($opts['show_rating'] ?? true),
                'showStock'       => (bool) ($opts['show_stock'] ?? true),
                'showCategory'    => (bool) (get_option('searchjet_show_categories', 1)),
                'showExcerpts'    => (bool) (get_option('searchjet_show_excerpts', 1)),
                'highlightTerms'  => (bool) ($opts['highlight_term'] ?? true),
                'viewAllLink'     => (bool) ($opts['view_all_link'] ?? true),
                'theme'           => get_option('searchjet_theme', 'default'),
                'primaryColor'    => get_option('searchjet_primary_color', '#007aff'),
                'maxResults'      => (int) get_option('searchjet_max_results', 10),
            ],
        ];

        $settings['integrations'] = [
            'ga4' => function_exists('lean_ga4_tracker')
        ];

        // Localize onto the scripts we actually enqueue on the frontend
        wp_localize_script('searchjet-result', 'SearchJetSettings', $settings);

        $mode = get_option('searchjet_search_mode', 'js') ?: 'js';

        if (is_search() && $mode === 'js') {
            wp_enqueue_script(
                'searchjet-page',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/searchjet-page.js',
                [],
                $assetVersion,
                true
            );
            // Ensure page script also receives the same settings
            wp_localize_script('searchjet-page', 'SearchJetSettings', $settings);
        }

        add_action('wp_head', [__CLASS__, 'outputInlineStyles']);
    }

    public static function outputInlineStyles() {
        $opts = get_option('searchjet_ui_options', []);
        $theme = get_option('searchjet_theme', 'default');
        $primaryColor = get_option('searchjet_primary_color', '#007aff');
        ?>
        <style id="searchjet-inline-style">
            :root {
                --searchjet-primary-color: <?= esc_attr($primaryColor) ?>;
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
