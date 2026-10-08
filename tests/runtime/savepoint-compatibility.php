<?php
// Real MySQL proof: bootstrap refuses databases other than the disposable fixture.
require __DIR__ . '/bootstrap.php';
if (!class_exists('mysqli_driver') || (defined('DB_ENGINE') && 'sqlite' === DB_ENGINE)) {
    fwrite(STDERR, "This proof requires a disposable MySQL fixture.\n");
    exit(6);
}
$driver = new mysqli_driver();
$original_reporting = $driver->report_mode;
$original_suppression = $wpdb->suppress_errors;
$name = 'cl_savepoint_proof_' . bin2hex(random_bytes(8));
$scope = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $wpdb->suppress_errors(false);
    // Outside a transaction, RELEASE throws on PHP 8.1's default strict driver.
    $scope = new MG\CleanLinks\Application\CommandTransaction();
    if (!$scope->begin() || $wpdb->suppress_errors !== false) {
        throw new RuntimeException('Owned strict-driver transaction or suppression restoration failed.');
    }
    $wpdb->insert($wpdb->options, array('option_name' => $name, 'option_value' => 'owned', 'autoload' => 'no'));
    if (!$scope->finish(false) || $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name)) !== null) {
        throw new RuntimeException('Owned transaction rollback failed.');
    }
    // A caller transaction must retain its writes and remain rollbackable.
    $wpdb->query('START TRANSACTION');
    $wpdb->insert($wpdb->options, array('option_name' => $name, 'option_value' => 'caller', 'autoload' => 'no'));
    $scope = new MG\CleanLinks\Application\CommandTransaction();
    if (!$scope->begin()) { throw new RuntimeException('Caller strict-driver transaction failed.'); }
    $wpdb->update($wpdb->options, array('option_value' => 'inner'), array('option_name' => $name));
    if (!$scope->finish(false) || $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name)) !== 'caller') {
        throw new RuntimeException('Inner rollback changed caller state.');
    }
    $wpdb->query('ROLLBACK');
    if ($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name)) !== null) {
        throw new RuntimeException('Inner scope committed the caller transaction.');
    }
    if ($driver->report_mode !== (MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT) || $wpdb->suppress_errors !== false) {
        throw new RuntimeException('Command changed caller driver/error settings.');
    }
    echo wp_json_encode(array('probe' => 'strict mysqli expected savepoint exception', 'wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION,
        'owned_rollback' => true, 'caller_preserved' => true, 'inner_rollback' => true, 'reporting_preserved' => true, 'suppression_restored' => true, 'pass' => true)) . "\n";
} finally {
    $wpdb->query('ROLLBACK');
    $wpdb->delete($wpdb->options, array('option_name' => $name));
    $wpdb->suppress_errors($original_suppression);
    mysqli_report($original_reporting);
}
