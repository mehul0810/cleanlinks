<?php
// CLI-only companion for the isolated upgrade/HTTP fixture. Never load a live site.
if ('cli' !== PHP_SAPI) { exit(2); }
$root = getenv('CLEANLINKS_UPGRADE_ROOT');
if (!$root || !is_file($root . '/.cleanlinks-116-fixture')) { exit(3); }
require $root . '/wp-load.php';
if (DB_NAME !== 'cleanlinks_116_fixture') { exit(4); }
$input = json_decode(stream_get_contents(STDIN), true);
$ids_file = $root . '/proof-ids.json';
$action = $input['action'] ?? 'snapshot';
if ($action === 'seed') {
    update_option('active_plugins', array());
    $term = wp_insert_term('116 preservation group', 'cleanlinks_groups');
    if (is_wp_error($term)) { throw new RuntimeException($term->get_error_code()); }
    $ids = array();
    foreach (array('publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft') as $index => $status) {
        $post = array('post_type'=>'cleanlinks', 'post_status'=>$status, 'post_title'=>'116 ' . $status, 'post_name'=>'proof-116-' . $status);
        if ($status === 'future') { $post['post_date'] = gmdate('Y-m-d H:i:s', time()+86400); }
        $id = wp_insert_post($post, true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_code()); }
        update_post_meta($id, 'cleanlink_redirect_url', WP_HOME . '/never-contact?a=1&b=%2F&b=two+words&encoded=%26%3D#part%2Ftwo');
        update_post_meta($id, 'cleanlink_redirect_nofollow', '1');
        update_post_meta($id, 'cleanlink_redirect_count', (string) (41+$index));
        wp_set_object_terms($id, array((int)$term['term_id']), 'cleanlinks_groups');
        $ids[$status] = $id;
    }
    $ids['empty'] = wp_insert_post(array('post_type'=>'cleanlinks','post_status'=>'publish','post_title'=>'116 empty','post_name'=>'proof-116-empty'));
    $ids['follow'] = wp_insert_post(array('post_type'=>'cleanlinks','post_status'=>'publish','post_title'=>'116 follow','post_name'=>'proof-116-follow'));
    update_post_meta($ids['follow'], 'cleanlink_redirect_url', WP_HOME . '/never-contact?follow=1&raw=%2B');
    update_post_meta($ids['follow'], 'cleanlink_redirect_nofollow', '0');
    $ids['filter'] = wp_insert_post(array('post_type'=>'cleanlinks','post_status'=>'publish','post_title'=>'116 filter','post_name'=>'proof-116-filter'));
    update_post_meta($ids['filter'], 'cleanlink_redirect_url', WP_HOME . '/unfiltered?original=1');
    file_put_contents($ids_file, wp_json_encode($ids));
}
$ids = json_decode(file_get_contents($ids_file), true);
if ($action === 'routing') {
    update_option('permalink_structure', $input['structure']);
    flush_rewrite_rules(false);
}
if ($action === 'snapshot' || $action === 'seed' || $action === 'routing') {
    $records = array();
    foreach ($ids as $name => $id) {
        $post = get_post($id);
        $records[$name] = array('id'=>(int)$id,'slug'=>$post->post_name,'status'=>$post->post_status,'destination'=>get_post_meta($id,'cleanlink_redirect_url',true),'nofollow'=>get_post_meta($id,'cleanlink_redirect_nofollow',true),'count'=>get_post_meta($id,'cleanlink_redirect_count',true),'groups'=>wp_get_object_terms($id,'cleanlinks_groups',array('fields'=>'ids')),'url'=>get_permalink($id));
    }
    echo wp_json_encode(array('records'=>$records,'wp'=>$GLOBALS['wp_version'],'php'=>PHP_VERSION,'engine'=>DB_ENGINE)) . "\n";
} elseif ($action === 'reset_counts') {
    foreach ($input['records'] as $record) { update_post_meta($record['id'],'cleanlink_redirect_count',$record['count']); }
    echo "{}\n";
} elseif ($action === 'roles_rest_export') {
    $actors=get_users(array('role'=>'administrator','number'=>1));
    if (!$actors) { throw new RuntimeException('No existing fixture administrator'); }
    wp_set_current_user((int)$actors[0]->ID);
    $rows=(new MG\CleanLinks\Admin\ExportQuery())->get_rows();
    $export_ids=array_map('intval',wp_list_pluck($rows,0));
    $published=array($ids['publish'],$ids['empty'],$ids['follow'],$ids['filter']);
    foreach ($ids as $name=>$id) {
        if (in_array($id,$published,true) !== in_array($id,$export_ids,true)) { throw new RuntimeException('Published-only CSV contract failed: '.$name); }
    }
    $response=rest_get_server()->dispatch(new WP_REST_Request('GET','/wp/v2/cleanlinks/'.$ids['publish']));
    if ($response->get_status()!==200 || isset($response->get_data()['meta']['cleanlink_redirect_url'])) { throw new RuntimeException('Core REST contract failed'); }
    $type=get_post_type_object('cleanlinks');
    if ($type->can_export!==false || $type->cap->edit_posts!=='edit_posts') { throw new RuntimeException('CPT capabilities/WXR contract failed'); }
    echo wp_json_encode(array('published_csv'=>true,'core_rest'=>true,'cpt_capabilities'=>true,'wxr_cpt_exclusion'=>true)) . "\n";
}
