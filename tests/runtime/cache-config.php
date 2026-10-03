<?php
if (getenv('CLEANLINKS_REDIS_CACHE') !== '1') { return; }
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 15);
define('WP_REDIS_CLIENT', 'phpredis');
$namespace = getenv('CLEANLINKS_REDIS_PREFIX');
if (!$namespace || !preg_match('/^clproof_[a-zA-Z0-9_.-]{1,80}$/', $namespace)) {
    fwrite(STDERR, "Refusing missing or invalid disposable Redis namespace.\n");
    exit(8);
}
define('WP_REDIS_PREFIX', $namespace . ':');
define('WP_REDIS_TIMEOUT', 1);
define('WP_REDIS_READ_TIMEOUT', 1);
