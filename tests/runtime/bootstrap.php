<?php
// Runtime proof only: refuse every database/table prefix except the disposable CI fixture.
$config = getenv('CLEANLINKS_RUNTIME_CONFIG');
if (!$config || !is_file($config)) { fwrite(STDERR, "Set CLEANLINKS_RUNTIME_CONFIG to the disposable test configuration.\n"); exit(2); }
require $config;
if (!defined('DB_NAME') || DB_NAME !== 'wordpress_tests' || $table_prefix !== 'wptests_') {
    fwrite(STDERR, "Runtime proof refuses a non-test database/table prefix.\n"); exit(3);
}
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
require_once ABSPATH . 'wp-includes/plugin.php';
add_action('muplugins_loaded', static function () { require dirname(__DIR__, 2) . '/cleanlinks.php'; });
add_filter('pre_http_request', static function () { return new WP_Error('proof_no_fetch', 'Outbound HTTP is not part of command proof.'); });
require ABSPATH . 'wp-settings.php';
$actors = get_users(array('role' => 'administrator', 'number' => 1));
if (!$actors) { fwrite(STDERR, "No installed disposable administrator fixture.\n"); exit(4); }
wp_set_current_user((int) $actors[0]->ID);
