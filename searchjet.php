<?php
/**
 * Plugin Name: SearchJet – AI-Powered Instant Search for WooCommerce & WordPress
 * Plugin URI: https://searchjetengine.com/
 * Description: AI-powered instant search for WooCommerce & WordPress. Supports SKUs, smart filters, typo-tolerance & GA4 tracking.
 * Version: 1.2.0
 * Author: Maidul Islam
 * Author URI: https://searchjetengine.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: searchjet-instant-search
 * Domain Path: /languages
 * Requires at least: 5.5
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 * Woo: 8.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SEARCHJET_VERSION', '1.2.0');
define('SEARCHJET_PLUGIN_FILE', __FILE__);
define('SEARCHJET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEARCHJET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SEARCHJET_API_BASE_URL', 'https://app.searchjetengine.com');

// Autoload classes
require_once SEARCHJET_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main SearchJet Plugin Class
 */
class SearchJet_Plugin {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_action('init', [$this, 'load_textdomain']);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize the plugin
     */
    public function init_plugin() {
        // Declare WooCommerce HPOS compatibility
        add_action('before_woocommerce_init', function() {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
        
        // Initialize core plugin functionality
        \SearchJet\Plugin::init();
        
        // Initialize admin panel
        if (is_admin()) {
            \SearchJet\Admin\EnhancedSettingsPage::init();
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'searchjet-instant-search',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        \SearchJet\Plugin::activate();
        
        // Create necessary database tables or options
        $this->create_plugin_options();
        
        // Schedule cron events
        if (!wp_next_scheduled('searchjet_send_log_batch')) {
            wp_schedule_event(time(), 'daily', 'searchjet_send_log_batch');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        \SearchJet\Plugin::deactivate();
        
        // Clear scheduled events
        wp_clear_scheduled_hook('searchjet_send_log_batch');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create default plugin options
     */
    private function create_plugin_options() {
        $default_options = [
            'searchjet_api_key' => '',
            'searchjet_project_id' => '',
            'searchjet_hosted_api_key' => '',
            'searchjet_search_mode' => 'js',
            'searchjet_disable_search_mode' => 0,
            'searchjet_automations_enabled' => 1,
            'searchjet_zr_threshold' => 5,
            'searchjet_zr_window' => 600,
            'searchjet_require_event_nonce' => 0,
            'searchjet_ui_options' => [
                'show_thumbnail' => 1,
                'show_price' => 1,
                'show_rating' => 1,
                'show_stock' => 1,
                'show_category' => 1,
                'highlight_term' => 1,
                'view_all_link' => 1,
            ],
            'searchjet_exclude_post_types' => '',
            'searchjet_include_status' => 'publish',
            'searchjet_exclude_sticky' => 0,
            'searchjet_exclude_protected' => 0,
            'searchjet_exclude_mime_types' => '',
            'searchjet_attributes' => '',
        ];
        
        foreach ($default_options as $option => $default_value) {
            if (get_option($option, null) === null) {
                update_option($option, $default_value);
            }
        }
    }
}

/**
 * Initialize the plugin
 */
function searchjet_init() {
    return SearchJet_Plugin::get_instance();
}

// Start the plugin
searchjet_init();

/**
 * Plugin compatibility checks
 */
function searchjet_check_requirements() {
    $php_version = '7.4';
    $wp_version = '5.5';
    
    if (version_compare(PHP_VERSION, $php_version, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                __('SearchJet requires PHP version %s or higher. You are running version %s.', 'searchjet-instant-search'),
                $php_version,
                PHP_VERSION
            )
        );
    }
    
    if (version_compare(get_bloginfo('version'), $wp_version, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                __('SearchJet requires WordPress version %s or higher. You are running version %s.', 'searchjet-instant-search'),
                $wp_version,
                get_bloginfo('version')
            )
        );
    }
}

// Check requirements on activation
register_activation_hook(__FILE__, 'searchjet_check_requirements');

/**
 * Add action links to plugin page
 */
function searchjet_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=searchjet') . '">' . __('Settings', 'searchjet-instant-search') . '</a>';
    array_unshift($links, $settings_link);
    
    $docs_link = '<a href="https://searchjetengine.com/docs" target="_blank">' . __('Documentation', 'searchjet-instant-search') . '</a>';
    array_push($links, $docs_link);
    
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'searchjet_plugin_action_links');

/**
 * Add meta links to plugin page
 */
function searchjet_plugin_row_meta($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $meta_links = [
            '<a href="https://searchjetengine.com/support" target="_blank">' . __('Support', 'searchjet-instant-search') . '</a>',
            '<a href="https://searchjetengine.com/pricing" target="_blank">' . __('Upgrade', 'searchjet-instant-search') . '</a>',
            '<a href="https://demo.searchjetengine.com" target="_blank">' . __('Demo', 'searchjet-instant-search') . '</a>',
        ];
        
        $links = array_merge($links, $meta_links);
    }
    
    return $links;
}
add_filter('plugin_row_meta', 'searchjet_plugin_row_meta', 10, 2);

/**
 * Add admin notices for important updates
 */
function searchjet_admin_notices() {
    $screen = get_current_screen();
    
    // Only show on SearchJet admin pages
    if (!$screen || strpos($screen->id, 'searchjet') === false) {
        return;
    }
    
    // Check if API key is configured
    $api_key = get_option('searchjet_hosted_api_key', '');
    if (empty($api_key)) {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('SearchJet Setup Required', 'searchjet-instant-search'); ?></strong><br>
                <?php _e('Please configure your SearchJet API key to start using AI-powered search.', 'searchjet-instant-search'); ?>
                <a href="<?php echo admin_url('admin.php?page=searchjet'); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php _e('Configure Now', 'searchjet-instant-search'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'searchjet_admin_notices');

/**
 * AJAX handler for admin dashboard widgets
 */
function searchjet_ajax_dashboard_stats() {
    check_ajax_referer('searchjet_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions', 'searchjet-instant-search'));
    }
    
    $stats = [
        'searches_today' => get_transient('searchjet_searches_today') ?: 0,
        'indexed_documents' => \SearchJet\API\SearchJetRemoteClient::fetchDocumentCount() ?: 0,
        'connection_status' => !empty(get_option('searchjet_hosted_api_key')) ? 'connected' : 'disconnected',
    ];
    
    wp_send_json_success($stats);
}
add_action('wp_ajax_searchjet_dashboard_stats', 'searchjet_ajax_dashboard_stats');

/**
 * Add SearchJet to WordPress admin bar
 */
function searchjet_admin_bar_menu($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node([
        'id' => 'searchjet',
        'title' => '<span class="ab-icon dashicons-search"></span> SearchJet',
        'href' => admin_url('admin.php?page=searchjet-enhanced'),
    ]);
    
    $wp_admin_bar->add_node([
        'id' => 'searchjet-settings',
        'parent' => 'searchjet',
        'title' => __('Settings', 'searchjet-instant-search'),
        'href' => admin_url('admin.php?page=searchjet-enhanced'),
    ]);
    
    $wp_admin_bar->add_node([
        'id' => 'searchjet-analytics',
        'parent' => 'searchjet',
        'title' => __('Analytics', 'searchjet-instant-search'),
        'href' => admin_url('admin.php?page=searchjet-analytics'),
    ]);
}
add_action('admin_bar_menu', 'searchjet_admin_bar_menu', 100);

/**
 * Enqueue admin styles globally for SearchJet branding
 */
function searchjet_admin_styles() {
    $screen = get_current_screen();
    
    if ($screen && strpos($screen->id, 'searchjet') !== false) {
        wp_enqueue_style(
            'searchjet-admin-global',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-style-enhanced.css',
            [],
            SEARCHJET_VERSION
        );
    }
}
add_action('admin_enqueue_scripts', 'searchjet_admin_styles');

/**
 * Register REST API endpoints
 */
function searchjet_register_rest_routes() {
    // Health check endpoint
    register_rest_route('searchjet/v1', '/health', [
        'methods' => 'GET',
        'callback' => function() {
            return new WP_REST_Response([
                'status' => 'ok',
                'version' => SEARCHJET_VERSION,
                'timestamp' => time(),
                'wordpress' => get_bloginfo('version'),
                'php' => PHP_VERSION,
            ]);
        },
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'searchjet_register_rest_routes');

/**
 * Add custom dashboard widget
 */
function searchjet_add_dashboard_widget() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wp_add_dashboard_widget(
        'searchjet_dashboard_widget',
        __('SearchJet Overview', 'searchjet-instant-search'),
        'searchjet_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'searchjet_add_dashboard_widget');

/**
 * Dashboard widget content
 */
function searchjet_dashboard_widget_content() {
    $api_key = get_option('searchjet_hosted_api_key', '');
    $client_info = get_option('searchjet_client_info', []);
    $indexed_count = \SearchJet\API\SearchJetRemoteClient::fetchDocumentCount() ?: 0;
    
    ?>
    <div class="searchjet-dashboard-widget">
        <?php if (empty($api_key)): ?>
            <p><?php _e('SearchJet is not configured yet.', 'searchjet-instant-search'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=searchjet-enhanced'); ?>" class="button button-primary">
                <?php _e('Configure SearchJet', 'searchjet-instant-search'); ?>
            </a>
        <?php else: ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <div>
                    <strong><?php echo number_format($indexed_count); ?></strong><br>
                    <small><?php _e('Indexed Documents', 'searchjet-instant-search'); ?></small>
                </div>
                <div>
                    <strong><?php echo esc_html($client_info['plan'] ?? 'Free'); ?></strong><br>
                    <small><?php _e('Current Plan', 'searchjet-instant-search'); ?></small>
                </div>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=searchjet-enhanced'); ?>"><?php _e('Manage Settings', 'searchjet-instant-search'); ?></a> |
                <a href="<?php echo admin_url('admin.php?page=searchjet-analytics'); ?>"><?php _e('View Analytics', 'searchjet-instant-search'); ?></a>
            </p>
        <?php endif; ?>
    </div>
    
    <style>
    .searchjet-dashboard-widget {
        font-size: 13px;
    }
    .searchjet-dashboard-widget strong {
        font-size: 18px;
        color: #667eea;
    }
    </style>
    <?php
}