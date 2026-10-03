<?php
require __DIR__ . '/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true);
if (!is_array($input)) { exit(5); }
if (($input['mode'] ?? '') === 'read') {
    $id = (int) ($input['id'] ?? 0);
    $post = get_post($id);
    echo wp_json_encode(array(
        'id' => $id,
        'exists' => $post instanceof WP_Post,
        'title' => $post instanceof WP_Post ? $post->post_title : null,
        'destination' => get_post_meta($id, 'cleanlink_redirect_url', true),
        'groups' => $post instanceof WP_Post ? wp_get_object_terms($id, 'cleanlinks_groups', array('fields' => 'ids')) : array(),
    )) . "\n";
    exit;
}
if (($input['mode'] ?? '') === 'cache-write') {
    if (!wp_using_ext_object_cache()) { fwrite(STDERR, "Persistent object cache is not active.\n"); exit(11); }
    $saved = wp_cache_set($input['key'], $input['value'], 'cleanlinks_cache_proof', 60);
    echo wp_json_encode(array('saved' => $saved, 'external' => wp_using_ext_object_cache())) . "\n";
    exit;
}
if (($input['mode'] ?? '') === 'cache-read') {
    $found = false;
    $value = wp_cache_get($input['key'], 'cleanlinks_cache_proof', false, $found);
    echo wp_json_encode(array('found' => $found, 'value' => $value, 'external' => wp_using_ext_object_cache())) . "\n";
    exit;
}
if (in_array(($input['mode'] ?? ''), array('command', 'guard'), true)) {
    $hooks = 0;
    if ('guard' === $input['mode']) {
        add_action('save_post_cleanlinks', static function () use (&$hooks) { ++$hooks; }, 20);
    }
    $request = new WP_REST_Request($input['method'] ?? 'POST', $input['route'] ?? '/cleanlinks/v1/links');
    $request->set_header('Content-Type', 'application/json');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body(wp_json_encode($input['body'] ?? array()));
    $response = rest_get_server()->dispatch($request);
    echo wp_json_encode(array('status' => $response->get_status(), 'data' => $response->get_data(), 'save_hooks' => $hooks)) . "\n";
    exit;
}
if (($input['mode'] ?? '') === 'seed') {
    $id = wp_insert_post(array(
        'post_type' => 'cleanlinks', 'post_status' => 'publish',
        'post_title' => $input['title'], 'post_name' => $input['slug'],
        'post_author' => get_current_user_id(),
    ), true);
    if (is_wp_error($id)) { fwrite(STDERR, $id->get_error_message()); exit(9); }
    update_post_meta($id, 'cleanlink_redirect_url', $input['destination']);
    wp_set_object_terms($id, $input['groups'], 'cleanlinks_groups');
    echo wp_json_encode(array('id' => (int) $id)) . "\n";
    exit;
}
if (($input['mode'] ?? '') === 'groups') {
    $terms = array();
    foreach (array('committed', 'speculative') as $name) {
        $term = wp_insert_term('cache-proof-' . $name . '-' . sanitize_title($input['slug']), 'cleanlinks_groups');
        if (is_wp_error($term)) { fwrite(STDERR, $term->get_error_message()); exit(10); }
        $terms[$name] = (int) $term['term_id'];
    }
    echo wp_json_encode($terms) . "\n";
    exit;
}
if (($input['mode'] ?? '') === 'version') {
    global $wpdb;
    $id = (int) $input['id'];
    $post = $wpdb->get_row($wpdb->prepare("SELECT post_title, post_name, post_status, post_author, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $id));
    $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ('cleanlink_redirect_url','cleanlink_redirect_nofollow') ORDER BY meta_id", $id));
    $values = array();
    foreach ($meta as $item) { if (!isset($values[$item->meta_key])) { $values[$item->meta_key] = $item->meta_value; } }
    $groups = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = 'cleanlinks_groups' ORDER BY tt.term_id", $id)));
    $state = array('id' => $id, 'title' => $post->post_title, 'slug' => $post->post_name,
        'status' => $post->post_status, 'destination' => $values['cleanlink_redirect_url'] ?? '',
        'nofollow' => isset($values['cleanlink_redirect_nofollow']) && '1' === $values['cleanlink_redirect_nofollow'],
        'groups' => $groups);
    $version = hash('sha256', wp_json_encode(array($state, $post->post_author, $post->post_modified_gmt)));
    echo wp_json_encode(array('version' => $version)) . "\n";
    exit;
}
if (($input['mode'] ?? '') === 'metadata') {
    $result = (new \MG\CleanLinks\Includes\LinkMetadataCommand())->execute(
        (int) $input['id'],
        array('destination' => $input['destination'], 'nofollow' => false)
    );
    echo wp_json_encode(array('error' => is_wp_error($result) ? $result->get_error_code() : null,
        'result' => is_wp_error($result) ? null : $result,
        'saved' => get_post_meta((int) $input['id'], 'cleanlink_redirect_url', true))) . "\n";
    exit;
}
if (($input['mode'] ?? '') === 'legacy-form') {
    $id = (int) $input['id'];
    $old_user = get_current_user_id();
    $_POST = array(
        'cleanlink_redirect_nonce' => 'invalid' === $input['variant'] ? 'bad_nonce' : wp_create_nonce('cleanlink-save-redirect-meta'),
        'cleanlink_redirect_url' => $input['destination'],
        'cleanlink_redirect_nofollow' => '1',
    );
    if ('unauthorized' === $input['variant']) { wp_set_current_user(0); }
    wp_update_post(array('ID' => $id, 'post_content' => 'Legacy cache proof ' . $input['variant']));
    wp_set_current_user($old_user);
    unset($_POST);
    echo wp_json_encode(array('saved' => get_post_meta($id, 'cleanlink_redirect_url', true),
        'nofollow' => get_post_meta($id, 'cleanlink_redirect_nofollow', true))) . "\n";
    exit;
}
if (($input['mode'] ?? '') === 'inspect') {
    global $wpdb;
    $id = (int) ($input['id'] ?? 0);
    $slug = (string) ($input['slug'] ?? '');
    $key = (string) ($input['request_key'] ?? '');
    $receipt_name = (new \MG\CleanLinks\Application\CommandReceipts())->option_name($key, get_current_user_id());
    $wpdb->flush();
    $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT ID, post_title FROM {$wpdb->posts} WHERE ID = %d", $id), ARRAY_A) : null;
    $destination = $id ? $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'cleanlink_redirect_url' ORDER BY meta_id LIMIT 1", $id)) : null;
    $groups = $id ? $wpdb->get_col($wpdb->prepare("SELECT tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = 'cleanlinks_groups' ORDER BY tt.term_id", $id)) : array();
    $slug_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'cleanlinks' AND post_name = %s", $slug));
    $receipt = $wpdb->get_var($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name = %s", $receipt_name));
    $mutex = $wpdb->get_var($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name = %s", 'cleanlinks_command_mutex'));
    echo wp_json_encode(array('row' => $row, 'destination' => $destination, 'groups' => array_map('intval', $groups), 'slug_count' => (int) $slug_count, 'receipt' => $receipt, 'mutex' => $mutex)) . "\n";
    exit;
}
if (($input['mode'] ?? '') !== 'write') { exit(6); }
$checkpoint = getenv('CLEANLINKS_CACHE_CHECKPOINT');
$release = getenv('CLEANLINKS_CACHE_RELEASE');
if (!$checkpoint || !$release) { fwrite(STDERR, "Missing isolated checkpoint paths.\n"); exit(7); }
$hook_count = 0;
$hook = static function ($id) use ($checkpoint, $release, $input, &$hook_count) {
    ++$hook_count;
    $post = get_post($id);
    $own = array(
        'id' => (int) $id,
        'hook_count' => $hook_count,
        'title' => $post instanceof WP_Post ? $post->post_title : null,
        'destination' => get_post_meta($id, 'cleanlink_redirect_url', true),
        'groups' => wp_get_object_terms($id, 'cleanlinks_groups', array('fields' => 'ids')),
    );
    $temporary = $checkpoint . '.tmp';
    file_put_contents($temporary, wp_json_encode($own), LOCK_EX);
    rename($temporary, $checkpoint);
    $deadline = microtime(true) + 15;
    while (!file_exists($release) && microtime(true) < $deadline) { usleep(10000); }
    if (!file_exists($release)) { throw new RuntimeException('Cache proof reader timed out.'); }
    if (!empty($input['rollback'])) { throw new RuntimeException('Requested disposable rollback.'); }
};
add_action('save_post_cleanlinks', $hook, 20, 1);
$request = new WP_REST_Request($input['method'] ?? 'POST', $input['route'] ?? '/cleanlinks/v1/links');
$request->set_header('Content-Type', 'application/json');
$request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
$request->set_body(wp_json_encode($input['body'] ?? array()));
$response = rest_get_server()->dispatch($request);
remove_action('save_post_cleanlinks', $hook, 20);
echo wp_json_encode(array('status' => $response->get_status(), 'data' => $response->get_data(), 'hook_count' => $hook_count)) . "\n";
