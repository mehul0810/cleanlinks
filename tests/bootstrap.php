<?php

//namespace SimplifiedWP\Links;

use SimplifiedWP\Links\Tests\TestEnvironment;

require __DIR__ . '/../vendor/autoload.php';

const WP_CONTENT_DIR = __DIR__;

$testEnvironment = new TestEnvironment();

// check if a required `wp-tests-config.php` is present
if (!$testEnvironment->hasConfig()) {
    die('wp-tests-config.php not found');
}

// get the current test environment (Local or Workflow)
$currentTestEnvironment = $testEnvironment->current();

// define for use in WP bootstrap file
define('WP_TESTS_CONFIG_FILE_PATH', $currentTestEnvironment->config());

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/simplified-links.php';
}

add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// install GiveWP
add_action('setup_theme', static function () {
    echo 'Installing SimplifiedWP.....' . PHP_EOL;
    // Initialize the plugin.
    $plugin = new Plugin();
    $plugin->register();
});

// pull in WP bootstrap file which looks for WP_TESTS_CONFIG_FILE_PATH defined above
require_once $currentTestEnvironment->bootstrap();