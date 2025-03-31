<?php

namespace MG\CleanLinks;

use MG\CleanLinks\Tests\Framework\TestHooks;
use MG\CleanLinks\Tests\TestEnvironment;

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
TestHooks::addFilter('muplugins_loaded', static function () {
    require_once __DIR__ . '/../cleanlinks.php';
});


// install GiveWP
TestHooks::addFilter('setup_theme', static function () {
    echo 'Installing SimplifiedWP.....' . PHP_EOL;
    // Initialize the plugin.
    $plugin = new Plugin();
    $plugin->register();
});

// pull in WP bootstrap file which looks for WP_TESTS_CONFIG_FILE_PATH defined above
require_once $currentTestEnvironment->bootstrap();