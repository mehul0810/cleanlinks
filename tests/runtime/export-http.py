"""Actual cookie-authenticated admin-post export proof using existing copied actors."""
import argparse,csv,hashlib,http.client,io,json,os,shutil,socket,subprocess,time
from pathlib import Path
from urllib.parse import urlencode
parser=argparse.ArgumentParser()
parser.add_argument('--source-fixture',type=Path,required=True)
parser.add_argument('--package',type=Path,required=True)
parser.add_argument('--fixture',type=Path,required=True)
parser.add_argument('--evidence',type=Path,required=True)
args=parser.parse_args(); root=args.fixture.resolve()
if root.exists():raise SystemExit('Refuse to overwrite an existing fixture')
if not (args.source_fixture/'.cleanlinks-116-fixture').is_file():raise SystemExit('Existing isolated #116 fixture required')
shutil.copytree(args.source_fixture,root)
with socket.socket() as reservation:reservation.bind(('127.0.0.1',0));port=reservation.getsockname()[1]
home=f'http://127.0.0.1:{port}'
config=(root/'wp-config.php').read_text()
import re
config=re.sub(r'http://127\.0\.0\.1:[0-9]+',home,config)
(root/'wp-config.php').write_text(config)
(root/'proof-router.php').write_text("<?php if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/wp-admin/admin-post.php'){require __DIR__.'/wp-admin/admin-post.php';return;} require __DIR__.'/index.php';\n")
(root/'proof-mode.json').write_text(json.dumps(dict(plugin=str(args.package.resolve()),base='recommends')))
# A capability-denied request uses the same real authenticated actor. No role/account changes.
with (root/'wp-content/mu-plugins/proof.php').open('a') as output:
    output.write("\nadd_filter('user_has_cap',static function($caps){if(($_SERVER['HTTP_X_PROOF_CAPABILITY']??'')==='deny'){unset($caps['manage_options']);}return $caps;});\n")
env=dict(os.environ,CLEANLINKS_UPGRADE_ROOT=str(root))
helper=Path(__file__).with_name('export-session.php').resolve()
def session(**fields):
    result=subprocess.run(['php',str(helper)],input=json.dumps(fields),capture_output=True,text=True,env=env,timeout=30)
    if result.returncode:raise AssertionError((result.returncode,result.stderr))
    return json.loads(result.stdout.strip().splitlines()[-1])
auth=session()
log=(root/'export-server.log').open('w')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(root),str(root/'proof-router.php')],stdout=log,stderr=log,env=env)
reports=[]
def export(nonce=None,cookie=None,deny=False):
    data=dict(action='cleanlinks_export')
    if nonce is not None:data['cleanlinks_export_nonce']=nonce
    headers={'Content-Type':'application/x-www-form-urlencoded'}
    if cookie:headers['Cookie']=cookie
    if deny:headers['X-Proof-Capability']='deny'
    connection=http.client.HTTPConnection('127.0.0.1',port,timeout=20)
    connection.request('POST','/wp-admin/admin-post.php',urlencode(data),headers)
    response=connection.getresponse();body=response.read();result=dict(status=response.status,content_type=response.getheader('Content-Type'),disposition=response.getheader('Content-Disposition'))
    connection.close();return result,body
try:
    for attempt in range(100):
        if server.poll() is not None:raise AssertionError('Export server failed')
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.1):break
        except OSError:time.sleep(.05)
    result,body=export(auth['nonce'],auth['cookie'])
    assert result['status']==200 and result['content_type'].startswith('text/csv') and 'attachment' in result['disposition'],result
    rows=list(csv.reader(io.StringIO(body.decode('utf-8'))))
    assert rows[0]==['ID','Title','Redirect From','Redirect To'],rows[0]
    by_id={int(row[0]):row for row in rows[1:]}
    assert by_id[auth['ids']['publish']][1]=="'=SUM(1,2) \"quoted\", Unicode ✓",by_id[auth['ids']['publish']]
    for status in ['publish','empty','follow','filter']:assert auth['ids'][status] in by_id,status
    for status in ['draft','pending','private','future','trash','auto-draft']:assert auth['ids'][status] not in by_id,status
    reports.append(dict(probe='real authenticated administrator CSV',passed=True,response=result,rows=len(rows)-1,body_sha256=hashlib.sha256(body).hexdigest(),formula_quoting_unicode_and_published_only=True))
    for name,nonce,cookie,deny,expected in [('missing nonce',None,auth['cookie'],False,403),('invalid nonce','invalid',auth['cookie'],False,403),('expired nonce',auth['expired_nonce'],auth['cookie'],False,403),('authenticated missing manage_options',auth['nonce'],auth['cookie'],True,403),('anonymous',auth['nonce'],None,False,400),('expired auth cookie',auth['nonce'],auth['expired_cookie'],False,400)]:
        response,body=export(nonce,cookie,deny)
        assert response['status']==expected and response['disposition'] is None and not body.startswith(b'"ID","Title"'),(name,response)
        reports.append(dict(probe=name,passed=True,response=response))
    session(action='revoke',token=auth['token'])
    response,body=export(auth['nonce'],auth['cookie'])
    assert response['status']==400 and response['disposition'] is None,response
    reports.append(dict(probe='revoked real fixture session',passed=True,response=response))
    args.evidence.parent.mkdir(parents=True,exist_ok=True)
    evidence=dict(environment=dict(wordpress='7.1.2',php='8.5.10',engine='sqlite',existing_actor_only=True,loopback_only=True),reports=reports,unrun=['Distinct editor/author/contributor/subscriber accounts: no existing actors in fixture; no accounts or passwords created'])
    args.evidence.write_text(json.dumps(evidence,indent=2)+'\n');print(json.dumps(evidence,indent=2))
finally:
    server.terminate()
    try:server.wait(timeout=5)
    except subprocess.TimeoutExpired:server.kill();server.wait()
    log.close()
