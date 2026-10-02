<?php
require __DIR__ . '/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true);
if (!is_array($input)) { exit(5); }
if (($input['probe'] ?? '') === 'core_update') {
    $id = (int) $input['id'];
    if (get_post_type($id) !== 'cleanlinks') { exit(6); }
    $result = wp_update_post(array('ID' => $id, 'post_title' => 'Core writer newer'), true);
    update_post_meta($id, 'cleanlink_redirect_url', 'https://example.org/core-new?a=1&b=%2F');
    echo wp_json_encode(array('status' => is_wp_error($result) ? 500 : 200, 'data' => array('id' => $id))) . "\n";
    exit;
}
$request = new WP_REST_Request($input['method'] ?? 'POST', $input['route'] ?? '/cleanlinks/v1/links');
$request->set_header('Content-Type', 'application/json');
$request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
if (isset($input['body'])) { $request->set_body(wp_json_encode($input['body'])); }
if (isset($input['query'])) { $request->set_query_params($input['query']); }
$allocated = 0;
if (!empty($input['hold_write_ms'])) {
    add_filter('wp_insert_post_data', static function ($data) use ($input) { usleep(min(1000, (int) $input['hold_write_ms']) * 1000); return $data; });
}
if (($input['probe'] ?? '') === 'throw_save_hook') {
    add_action('save_post_cleanlinks', static function ($id) use (&$allocated) {
        $allocated = $id;
        get_post($id); get_post_meta($id, 'cleanlink_redirect_url', true);
        throw new RuntimeException('Disposable runtime save-hook failure');
    }, 20);
}
if (($input['probe'] ?? '') === 'receipt_failure') {
    $wpdb->suppress_errors(true);
    add_filter('query', static function ($query) use ($wpdb) {
        if (preg_match('/^INSERT/i', $query) && strpos($query, 'cleanlinks_command_receipt_') !== false) {
            return "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES ('cleanlinks_command_mutex','test','no')";
        }
        return $query;
    });
}
if (($input['probe'] ?? '') === 'cron_context') { define('DOING_CRON', true); }
if (($input['probe'] ?? '') === 'ajax_context') { define('DOING_AJAX', true); }
$response = rest_get_server()->dispatch($request);
$output = array('status' => $response->get_status(), 'data' => $response->get_data());
if ($allocated) {
    $output['probe'] = array('allocated_id' => $allocated, 'post_exists_after' => get_post($allocated) !== null, 'destination_after' => get_post_meta($allocated, 'cleanlink_redirect_url', true));
}
echo wp_json_encode($output) . "\n";
