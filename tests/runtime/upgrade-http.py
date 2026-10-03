"""Bounded, no-follow HTTP/upgrade proof using an existing disposable SQLite WP copy.

No credentials, dependency installs, live site updates, or destination requests.
The output is evidence for these source snapshots, not a final 1.2 release gate.
"""
import argparse, hashlib, http.client, json, os, shutil, socket, subprocess, time
from pathlib import Path

parser=argparse.ArgumentParser()
parser.add_argument('--wordpress-source',required=True,type=Path)
parser.add_argument('--production',required=True,type=Path)
parser.add_argument('--candidate',required=True,type=Path)
parser.add_argument('--target',required=True,type=Path)
parser.add_argument('--fixture',required=True,type=Path)
parser.add_argument('--evidence',required=True,type=Path)
args=parser.parse_args()
fixture=args.fixture.resolve()
if fixture.exists(): raise SystemExit('Choose a fresh fixture directory; never overwrite an existing site.')
if not (args.wordpress_source/'wp-content/database/cleanlinks-fixture.sqlite').is_file():
    raise SystemExit('Requires the existing disposable SQLite fixture, not a production database.')
shutil.copytree(args.wordpress_source,fixture,ignore=shutil.ignore_patterns('plugins','mu-plugins','object-cache.php','.git'))
(fixture/'.cleanlinks-116-fixture').write_text('Disposable #116 proof only\n')
(fixture/'wp-content/mu-plugins').mkdir(parents=True)
driver=args.wordpress_source/'wp-content/mu-plugins/sqlite-database-integration'
if not driver.is_dir(): driver=args.wordpress_source/'wp-content/plugins/sqlite-database-integration'
if not driver.is_dir(): raise SystemExit('Existing SQLite adapter required; no database fallback allowed')
shutil.copytree(driver,fixture/'wp-content/mu-plugins/sqlite-database-integration')
with socket.socket() as reservation:
    reservation.bind(('127.0.0.1',0)); port=reservation.getsockname()[1]
home=f'http://127.0.0.1:{port}'
config=(fixture/'wp-config.php').read_text().replace("'cleanlinks_ui_fixture'","'cleanlinks_116_fixture'").replace('http://127.0.0.1:8777',home)
config=config.replace("require_once ABSPATH . 'wp-settings.php';", "define('DISABLE_WP_CRON', true);\ndefine('WP_HTTP_BLOCK_EXTERNAL', true);\nrequire_once ABSPATH . 'wp-settings.php';")
if 'DISABLE_WP_CRON' not in config: raise SystemExit('Unknown config bootstrap layout')
(fixture/'wp-config.php').write_text(config)
mu='''<?php
$mode=json_decode(file_get_contents(ABSPATH.'/proof-mode.json'),true);
add_filter('pre_option_active_plugins',static function(){return array();});
add_filter('pre_http_request',static function(){return new WP_Error('proof_no_fetch','Outbound contact blocked');});
add_filter('cleanlink_urls_slug',static function()use($mode){return $mode['base'];});
add_filter('cleanlinks_urls_redirect_url',static function($url,$count){return strpos($url,'/unfiltered?')!==false ? home_url('/never-contact?filtered=1&count='.$count) : $url;},10,2);
add_action('cleanlinks_urls_redirect',static function($url,$count){file_put_contents(ABSPATH.'/redirect-hooks.jsonl',wp_json_encode(array('url'=>$url,'count'=>$count))."\\n",FILE_APPEND);},10,2);
add_action('wp_loaded',static function(){if(strpos($_SERVER['REQUEST_URI']??'','/never-contact')===0){file_put_contents(ABSPATH.'/destination-contacted','FAIL');}});
require $mode['plugin'].'/cleanlinks.php';
'''
(fixture/'wp-content/mu-plugins/proof.php').write_text(mu)
(fixture/'proof-router.php').write_text("<?php require __DIR__ . '/index.php';\n")
env=dict(os.environ,CLEANLINKS_UPGRADE_ROOT=str(fixture))
helper=Path(__file__).with_name('upgrade-fixture.php').resolve()
def command(action,**fields):
    result=subprocess.run(['php',str(helper)],input=json.dumps(dict(action=action,**fields)),capture_output=True,text=True,env=env,timeout=30)
    if result.returncode: raise AssertionError((result.returncode,result.stdout,result.stderr))
    try: return json.loads(result.stdout.strip().splitlines()[-1])
    except (ValueError,IndexError): raise AssertionError(('Non-JSON fixture result',result.stdout[-2000:],result.stderr))
def mode(source,base='recommends'):
    (fixture/'proof-mode.json').write_text(json.dumps(dict(plugin=str(source.resolve()),base=base)))
http_requests=0
def request(url):
    global http_requests
    http_requests+=1
    from urllib.parse import urlsplit
    parsed=urlsplit(url)
    assert parsed.hostname=='127.0.0.1' and parsed.port==port,'Only fixture URLs may be requested'
    connection=http.client.HTTPConnection('127.0.0.1',port,timeout=20)
    connection.request('GET',parsed.path+('?' + parsed.query if parsed.query else ''))
    response=connection.getresponse(); result=dict(status=response.status,location=response.getheader('Location'),robots=response.getheader('X-Robots-Tag'))
    response.read(); connection.close(); return result
def resolve_variant(url,expected):
    chain=[]
    for hop in range(3):
        response=request(url); chain.append(dict(url=url,response=response))
        if response==expected:return chain
        assert response['status'] in (301,302) and response['location'],('Unexpected response',chain)
        url=response['location']
        from urllib.parse import urlsplit
        parsed=urlsplit(url)
        assert parsed.hostname=='127.0.0.1' and parsed.port==port and parsed.path!='/never-contact',('Refuse destination contact',chain)
    raise AssertionError(('Too many local canonical hops',chain))
mode(args.production)
baseline=command('seed')['records']
mode(args.candidate); assert command('snapshot')['records']==baseline,'Production to candidate changed data'
mode(args.target); assert command('snapshot')['records']==baseline,'Candidate to target changed data'
reports=[dict(probe='production -> candidate -> target preserves IDs/slugs/status/meta/counts/groups/URLs',passed=True,records=len(baseline))]
log=(fixture/'server.log').open('w')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(fixture),str(fixture/'proof-router.php')],stdout=log,stderr=log,env=env)
try:
    for attempt in range(100):
        if server.poll() is not None: raise AssertionError('Loopback server failed to start')
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.1): break
        except OSError: time.sleep(.05)
    for source_name,source in [('candidate',args.candidate),('target',args.target)]:
        for structure,base in [('', 'recommends'),('/%postname%/','recommends'),('/%postname%/','custom-links')]:
            mode(source,base); records=command('routing',structure=structure)['records']; before=records['publish']['count']
            response=request(records['publish']['url'])
            variants=[home+f"/?post_type=cleanlinks&p={records['publish']['id']}",home+'/?cleanlinks='+records['publish']['slug'],records['publish']['url']+('&' if '?' in records['publish']['url'] else '?')+'incoming=one%26two&repeat=a&repeat=b']
            variant_chains=[resolve_variant(url,response) for url in variants]
            assert response==dict(status=301,location=records['publish']['destination'],robots='nofollow'),response
            follow=request(records['follow']['url']); assert follow==dict(status=301,location=records['follow']['destination'],robots=None),follow
            empty=request(records['empty']['url']); assert empty['status']==302 and empty['location']==home and empty['robots'] is None,empty
            filtered=request(records['filter']['url']); assert filtered['status']==301 and filtered['location'].startswith(home+'/never-contact?filtered=1&count='),filtered
            for name in ['draft','pending','private','future','trash','auto-draft']:
                rejected=request(home+f"/?post_type=cleanlinks&p={records[name]['id']}")
                assert rejected['location']!=records[name]['destination'],(name,rejected)
            after=command('snapshot')['records']
            assert int(after['publish']['count'])==int(before)+4,after['publish']
            for name in ['draft','pending','private','future','trash','auto-draft']:
                assert after[name]['count']==records[name]['count'],(name,after[name])
            assert not (fixture/'destination-contacted').exists(),'Destination contacted'
            reports.append(dict(probe='actual HTTP redirects',source=source_name,structure=structure,base=base,passed=True,nonpublished_count_unchanged=True,publish=response,follow=follow,empty=empty,filtered=filtered,count_before=int(before),count_after=int(after['publish']['count']),variant_chains=variant_chains))
    mode(args.target)
    reports.append(dict(probe='existing core REST / published CSV / CPT capability contracts',passed=True,details=command('roles_rest_export')))
    assert len((fixture/'redirect-hooks.jsonl').read_text().splitlines())==42,'Redirect action hooks changed'
    command('reset_counts',records=baseline)
    command('routing',structure='')
    mode(args.production); assert command('snapshot')['records']==baseline,'Rollback to production lost stored data'
    reports.append(dict(probe='code rollback to production preserves original records',passed=True))
    report=dict(environment=dict(wordpress=command('snapshot')['wp'],php=command('snapshot')['php'],engine=command('snapshot')['engine'],loopback_only=True,no_follow=True,http_requests=http_requests),reports=reports)
    args.evidence.parent.mkdir(parents=True,exist_ok=True)
    args.evidence.write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))
finally:
    server.terminate()
    try: server.wait(timeout=5)
    except subprocess.TimeoutExpired: server.kill(); server.wait()
    log.close()
