<?php
// phpcs:disable missing_direct_file_access_protection -- CLI-only test bootstrap; ABSPATH is defined below for the mocked WP env.

$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    fprintf(STDERR, "Composer autoloader not found at: %s\n", $autoloader);
    fprintf(STDERR, "Run: composer install --dev\n");
    exit(1);
}
require_once $autoloader;

// Plugin constants
define('SEARCHJET_VERSION', '2.0.4');
define('SEARCHJET_PLUGIN_VERSION', '2.0.4');
define('SEARCHJET_API_BASE_URL', 'https://app.searchjetengine.com');
define('SEARCHJET_PLUGIN_URL', 'http://example.com/wp-content/plugins/searchjet-instant-search/');
define('SEARCHJET_PLUGIN_DIR', dirname(__DIR__) . '/');
define('SEARCHJET_PLUGIN_FILE', SEARCHJET_PLUGIN_DIR . 'searchjet.php');
define('ABSPATH', SEARCHJET_PLUGIN_DIR);
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

// Start Brain Monkey early so Patchwork is active before any source files
// are autoloaded. KEEP this session alive (no tearDown) so that when()
// stubs are available during class autoloading.
Brain\Monkey\setUp();

// Global $wpdb for source code that accesses $wpdb directly
$GLOBALS['wpdb'] = new class {
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $usermeta = 'wp_usermeta';
    public $users = 'wp_users';
    public $comments = 'wp_comments';
    public $terms = 'wp_terms';
    public $term_taxonomy = 'wp_term_taxonomy';
    public $term_relationships = 'wp_term_relationships';
    public function query($sql) { return null; }
    public function prepare($query, ...$args) { return $query; }
    public function get_var($query = null) { return null; }
    public function get_row($query = null) { return null; }
    public function get_results($query = null) { return []; }
    public function esc_like($text) { return str_replace(['%', '_'], ['\%', '\_'], $text); }
    public function delete($table, $where) { return true; }
    public function insert($table, $data, $format = null) { return true; }
    public function update($table, $data, $where) { return true; }
};

// Stub esc_* and __* functions so they're available during autoloading.
// These create Patchwork redefinitions that survive until Monkey\setUp()
// is called in each test (which restores all).
Brain\Monkey\Functions\stubEscapeFunctions();
Brain\Monkey\Functions\stubTranslationFunctions();

// Functions that may be called during autoloading (static init methods).
// Each test's setUp() will call Monkey\setUp() which restores all, then
// TestCase::setUp re-creates them via expect() for specific test behavior.
$early_stubs = [
    'add_action', 'add_filter', 'apply_filters',
    'register_setting', 'register_activation_hook', 'register_deactivation_hook',
    'current_user_can', 'is_admin',
    'session_id',
    'get_option', 'update_option', 'delete_option',
    'get_transient', 'set_transient', 'delete_transient',
    'wp_next_scheduled', 'wp_schedule_event', 'wp_schedule_single_event', 'wp_unschedule_event',
    'wp_create_nonce', 'wp_verify_nonce', 'wp_unslash',
    'sanitize_key', 'sanitize_text_field', 'sanitize_title',
    'checked', 'selected', 'disabled',
    'absint',
    'wp_json_encode',
    'wp_remote_get', 'wp_remote_post', 'wp_remote_retrieve_body', 'wp_remote_retrieve_response_code', 'is_wp_error',
    'wp_die',
    'wp_enqueue_script', 'wp_enqueue_style', 'wp_localize_script',
    'admin_url', 'home_url', 'rest_url', 'plugin_dir_url', 'plugin_dir_path', 'plugin_basename',
    'add_query_arg', 'wp_safe_redirect',
    'get_locale', 'load_plugin_textdomain',
    'get_posts', 'get_post',
    'add_shortcode',
    'wp_generate_uuid4',
];
foreach ($early_stubs as $fn) {
    Brain\Monkey\Functions\when($fn)->justReturn(null);
}

// Specific return values for early-called functions
Brain\Monkey\Functions\when('sanitize_key')->returnArg(1);
Brain\Monkey\Functions\when('sanitize_text_field')->returnArg(1);
Brain\Monkey\Functions\when('apply_filters')->returnArg(1);
Brain\Monkey\Functions\when('is_admin')->justReturn(false);
Brain\Monkey\Functions\when('absint')->alias(function ($v) { return abs((int)$v); });
Brain\Monkey\Functions\when('wp_json_encode')->alias('json_encode');
Brain\Monkey\Functions\when('get_option')->returnArg(2);
Brain\Monkey\Functions\when('wp_unslash')->returnArg(1);
Brain\Monkey\Functions\when('wp_die')->alias(function ($msg = '') { throw new \RuntimeException((string)$msg); }); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test mock, escaping irrelevant.
Brain\Monkey\Functions\when('wp_remote_get')->justReturn([]);
Brain\Monkey\Functions\when('wp_remote_post')->justReturn([]);
Brain\Monkey\Functions\when('wp_remote_retrieve_body')->justReturn('{}');
Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
Brain\Monkey\Functions\when('get_posts')->justReturn([]);
Brain\Monkey\Functions\when('get_post')->alias(function ($id) { $p = new stdClass(); $p->ID = $id; $p->post_title = "Post $id"; return $p; });
Brain\Monkey\Functions\when('wp_create_nonce')->justReturn('test_nonce');
Brain\Monkey\Functions\when('wp_generate_uuid4')->justReturn('uuid-xxxxxxxxxxxx');

// DO NOT tearDown here — keep the Patchwork session alive for autoloading.
// TestCase::setUp calls Monkey\setUp() which restores all redefinitions
// and sets up fresh ones via expect(). The bootstrap's when() stubs are
// only needed during initial class autoloading (before any test runs).
