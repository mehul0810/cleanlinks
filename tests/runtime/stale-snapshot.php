<?php
require __DIR__ . '/bootstrap.php';
use MG\CleanLinks\Application\LinkCommands;
$service = new LinkCommands();
$run = getenv('GITHUB_RUN_ID') ?: (string) time();
$body = array('destination' => 'https://example.org/snapshot?a=1&b=%2F', 'title' => 'Snapshot original', 'slug' => 'snapshot-' . $run, 'status' => 'publish', 'request_key' => time() . '_snapshot_create_request_' . $run);
$saved = $service->execute($body);
if (is_wp_error($saved)) { throw new RuntimeException($saved->get_error_code()); }
$isolation = $wpdb->get_var('SELECT @@transaction_isolation');
if (strtoupper($isolation) !== 'REPEATABLE-READ') { throw new RuntimeException('Expected actual MySQL REPEATABLE READ for snapshot probe.'); }
$wpdb->query('START TRANSACTION');
$old = $service->read($saved['id']); // Establish an older consistent-read snapshot.
$process = proc_open(array(PHP_BINARY, __DIR__ . '/request.php'), array(0 => array('pipe','r'), 1 => array('pipe','w'), 2 => array('pipe','w')), $pipes);
fwrite($pipes[0], wp_json_encode(array('probe' => 'core_update', 'id' => $saved['id']))); fclose($pipes[0]);
$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
$error = stream_get_contents($pipes[2]); fclose($pipes[2]);
if (proc_close($process) !== 0 || json_decode(trim($output), true)['status'] !== 200) { throw new RuntimeException('Core writer probe failed: ' . $error); }
// Capacity must also use current reads despite the caller's established snapshot.
$process = proc_open(array(PHP_BINARY, __DIR__ . '/request.php'), array(0 => array('pipe','r'), 1 => array('pipe','w'), 2 => array('pipe','w')), $pipes);
$other = $body; $other['slug'] .= '-capacity'; $other['request_key'] = time() . '_capacity_create_request_' . $run;
fwrite($pipes[0], wp_json_encode(array('body' => $other))); fclose($pipes[0]);
$output = stream_get_contents($pipes[1]); fclose($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
if (proc_close($process) !== 0 || json_decode(trim($output), true)['status'] !== 200) { throw new RuntimeException('Capacity writer failed: ' . $error); }
$transaction = new MG\CleanLinks\Application\CommandTransaction();
if (!$transaction->begin()) { throw new RuntimeException('Capacity lock failed.'); }
$ceiling = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s FOR UPDATE", $wpdb->esc_like(MG\CleanLinks\Application\CommandReceipts::PREFIX) . '%'));
if ((new MG\CleanLinks\Application\CommandReceipts())->has_capacity(get_current_user_id(), 20000, $ceiling)) { throw new RuntimeException('Capacity accepted an older snapshot.'); }
$transaction->finish(false);
$result = $service->execute(array('id' => $saved['id'], 'expected_version' => $old['version'], 'title' => 'Stale overwrite', 'request_key' => time() . '_snapshot_update_request_' . $run));
if (!is_wp_error($result) || $result->get_error_code() !== 'stale_version') { throw new RuntimeException('Stale caller snapshot was accepted.'); }
$still_old = $wpdb->get_var($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", $saved['id']));
if ($still_old !== 'Snapshot original') { throw new RuntimeException('Caller-owned transaction was committed/replaced.'); }
$wpdb->query('ROLLBACK');
$fresh = $service->read($saved['id']);
if ($fresh['title'] !== 'Core writer newer' || $fresh['destination'] !== 'https://example.org/core-new?a=1&b=%2F') { throw new RuntimeException('Newer core write was lost.'); }
echo wp_json_encode(array('probe' => 'MySQL REPEATABLE READ caller snapshot vs core writer', 'result' => 'pass', 'error' => 'stale_version', 'caller_transaction_preserved' => true, 'newer_core_state_preserved' => true, 'current_capacity_read' => true)) . "\n";
