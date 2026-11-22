<?php
/**
 * Plugin Name: SearchJet – AI-Powered Instant Search for WooCommerce & WordPress
 * Plugin URI: https://searchjetengine.com/
 * Description: AI-powered instant search for WooCommerce & WordPress. Supports SKUs, smart filters, typo-tolerance & GA4 tracking.
 * Version: 2.0.8
 * Author: Maidul
 * Author URI: https://searchjetengine.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: searchjet-instant-search
 * Domain Path: /languages
 * Requires at least: 5.5
 * Tested up to: 7.1
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
define('SEARCHJET_VERSION', '2.0.8');
define('SEARCHJET_PLUGIN_VERSION', '2.0.8');
define('SEARCHJET_PLUGIN_FILE', __FILE__);
define('SEARCHJET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEARCHJET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SEARCHJET_API_BASE_URL', 'https://app.searchjetengine.com');

// Autoload classes
require_once SEARCHJET_PLUGIN_DIR . 'autoload.php';

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
            \SearchJet\Admin\DashboardPage::init();
            \SearchJet\Admin\SettingsPage::init();
            \SearchJet\Admin\SearchUIPage::init();
            \SearchJet\Admin\IndexingPage::init();
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
        $default_options = \SearchJet\Helpers\Defaults::options();
        
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
                esc_html__('SearchJet requires PHP version %s or higher. You are running version %s.', 'searchjet-instant-search'),
                esc_html($php_version),
                esc_html(PHP_VERSION)
            )
        );
    }
    
    if (version_compare(get_bloginfo('version'), $wp_version, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                esc_html__('SearchJet requires WordPress version %s or higher. You are running version %s.', 'searchjet-instant-search'),
                esc_html($wp_version),
                esc_html(get_bloginfo('version'))
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
            '<a href="https://searchjetengine.com/vector-search-demo/" target="_blank">' . __('Demo', 'searchjet-instant-search') . '</a>',
        ];
        
        $links = array_merge($links, $meta_links);
    }
    
    return $links;
}
add_filter('plugin_row_meta', 'searchjet_plugin_row_meta', 10, 2);



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
        'href' => admin_url('admin.php?page=searchjet'),
    ]);
    
    $wp_admin_bar->add_node([
        'id' => 'searchjet-settings',
        'parent' => 'searchjet',
        'title' => __('Settings', 'searchjet-instant-search'),
        'href' => admin_url('admin.php?page=searchjet'),
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
            'searchjet-admin-tailwind',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            [],
            SEARCHJET_VERSION
        );

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
    // Health check endpoint (admin only - exposes version info)
    register_rest_route('searchjet/v1', '/health', [
        'methods' => 'GET',
        'callback' => function() {
            return new WP_REST_Response([
                'status' => 'ok',
                'version' => SEARCHJET_VERSION,
                'timestamp' => time(),
            ]);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
}
add_action('rest_api_init', 'searchjet_register_rest_routes');

