<?php
/**
 * Plugin Name: SearchJet – Instant Search
 * Plugin URI:  https://www.searchjetengine.com
 * Description: Elevate your WordPress search with real-time, AI-enhanced results backed by secure, cloud-powered search infrastructure.
 * Version: 1.0.9
 * Author: Maidul
 * Author URI: https://dynamicweblab.com
 * License: GPLv2 or later
 * Text Domain: searchjet-instant-search
 */


defined('ABSPATH') || exit;

if ( ! defined( 'SEARCHJET_API_BASE_URL' ) ) {
    define( 'SEARCHJET_API_BASE_URL', 'https://app.searchjetengine.com' );
}

if ( ! defined( 'SEARCHJET_PLUGIN_URL' ) ) {
    define( 'SEARCHJET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'SEARCHJET_PLUGIN_PATH' ) ) {
    define( 'SEARCHJET_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Initialize the plugin.
 */
function searchjet_init() {
    \SearchJet\Plugin::init();
}
add_action('plugins_loaded', 'searchjet_init');

/**
 * Handle plugin activation.
 */
register_activation_hook(__FILE__, [\SearchJet\Plugin::class, 'activate']);