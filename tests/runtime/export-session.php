<?php
// Existing fixture actor only; no passwords, users or API credentials are created.
if ('cli' !== PHP_SAPI) { exit(2); }
$root = getenv('CLEANLINKS_UPGRADE_ROOT');
if (!$root || !is_file($root . '/.cleanlinks-116-fixture')) { exit(3); }
require $root . '/wp-load.php';
if (DB_NAME !== 'cleanlinks_116_fixture') { exit(4); }
$input = json_decode(stream_get_contents(STDIN), true);
$actors = get_users(array('role'=>'administrator','number'=>1));
if (!$actors) { throw new RuntimeException('Existing fixture administrator required'); }
$id = (int) $actors[0]->ID;
if (($input['action'] ?? '') === 'revoke') {
    WP_Session_Tokens::get_instance($id)->destroy($input['token']);
    echo "{}\n";
    exit;
}
wp_set_current_user($id);
$token = WP_Session_Tokens::get_instance($id)->create(time()+600);
$logged = wp_generate_auth_cookie($id,time()+600,'logged_in',$token);
$_COOKIE[LOGGED_IN_COOKIE] = $logged;
$cookie = AUTH_COOKIE . '=' . wp_generate_auth_cookie($id,time()+600,'auth',$token) . '; ' . LOGGED_IN_COOKIE . '=' . $logged;
$expired = AUTH_COOKIE . '=' . wp_generate_auth_cookie($id,time()-7200,'auth',$token);
$tick = wp_nonce_tick('cleanlinks_export');
$nonce_expired = substr(wp_hash(($tick-2).'|cleanlinks_export|'.$id.'|'.$token,'nonce'),-12,10);
$ids = json_decode(file_get_contents($root.'/proof-ids.json'),true);
wp_update_post(array('ID'=>$ids['publish'],'post_title'=>'=SUM(1,2) "quoted", Unicode ✓'));
echo wp_json_encode(array('cookie'=>$cookie,'expired_cookie'=>$expired,'nonce'=>wp_create_nonce('cleanlinks_export'),'expired_nonce'=>$nonce_expired,'token'=>$token,'ids'=>$ids)) . "\n";
