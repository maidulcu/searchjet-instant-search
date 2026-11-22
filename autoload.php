<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SearchJet – standalone PSR-4 autoloader.
 *
 * Maps the SearchJet\ namespace to the includes/ directory so the plugin
 * runs without bundled dependencies. SearchJet has no runtime third-party
 * packages; Composer is used only for development tooling (PHPUnit,
 * Brain Monkey, Mockery) and is intentionally not shipped.
 *
 * @package SearchJet
 */

spl_autoload_register(static function ($class) {
    $prefix   = 'SearchJet\\';
    $base_dir = __DIR__ . '/includes/';

    if (0 !== strncmp($prefix, $class, strlen($prefix))) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});