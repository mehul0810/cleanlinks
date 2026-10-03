"""Run actual standalone request processes against the disposable WordPress fixture."""
import json, os, subprocess, time
from pathlib import Path
adapter = str(Path(__file__).with_name('request.php'))
run = os.getenv('GITHUB_RUN_ID', str(time.time_ns()))
stamp = int(time.time())
def key(suffix): return f'{stamp}_{suffix}_{run}'
def request(body=None, method='POST', route='/cleanlinks/v1/links', query=None, probe=None):
    envelope={'method':method,'route':route}
    if body is not None: envelope['body']=body
    if query is not None: envelope['query']=query
    if probe is not None: envelope['probe']=probe
    result=subprocess.run(['php',adapter],input=json.dumps(envelope),text=True,capture_output=True,timeout=30)
    assert result.returncode==0,(result.returncode,result.stderr)
    return json.loads(result.stdout.strip().splitlines()[-1])
def race(bodies,route='/cleanlinks/v1/links',method='POST'):
    jobs=[]
    for body in bodies:
        child=subprocess.Popen(['php',adapter],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
        jobs.append((child,{'body':body,'route':route,'method':method,'hold_write_ms':200}))
    for child,envelope in jobs:
        child.stdin.write(json.dumps(envelope)); child.stdin.close(); child.stdin=None
    results=[]
    for child,envelope in jobs:
        out,err=child.communicate(timeout=30)
        assert child.returncode==0,(child.returncode,err)
        result=json.loads(out.strip().splitlines()[-1])
        if result['status']==503: result=request(envelope['body'],method,route)
        results.append(result)
    return results
def create(slug,suffix):
    return {'destination':'https://example.org/proof?a=1&b=%2F&b=two+words','title':'Runtime fixture','slug':slug+'-'+run,'status':'publish','request_key':key(suffix)}
reports=[]
body=create('race-identical','identical_create_request')
results=race([body,body]); assert all(x['status']==200 for x in results),results
assert results[0]['data']==results[1]['data'],results
records=request(method='GET',route='/wp/v2/cleanlinks',query={'slug':body['slug']})
assert records['status']==200 and len(records['data'])==1,records
reports.append({'probe':'identical create race','pass':True,'published_count':1})
a=create('race-collision','collision_create_request_a'); b=create('race-collision','collision_create_request_b')
results=race([a,b]); assert sorted(x['status'] for x in results)==[200,409],results
assert [x for x in results if x['status']==409][0]['data']['code']=='slug_conflict',results
reports.append({'probe':'same slug / different keys race','pass':True,'loser':'slug_conflict'})
saved=request(create('race-stale','stale_create_request'))['data']
a={'request_key':key('stale_update_request_a'),'expected_version':saved['version'],'title':'Writer A'}
b=dict(a,request_key=key('stale_update_request_b'),title='Writer B')
route='/cleanlinks/v1/links/'+str(saved['id'])
results=race([a,b],route,'PATCH'); assert sorted(x['status'] for x in results)==[200,409],results
assert [x for x in results if x['status']==409][0]['data']['code']=='stale_version',results
winner=[x for x in results if x['status']==200][0]['data']
assert request(method='GET',route=route)['data']==winner
reports.append({'probe':'same version update race','pass':True,'loser':'stale_version'})
for probe in ('throw_save_hook','receipt_failure'):
    body=create('failure-'+probe.replace('_','-'),'failure_'+probe+'_request')
    failed=request(body,probe=probe)
    assert failed['status']==500,failed
    if probe=='throw_save_hook':
        assert failed['probe']['post_exists_after'] is False,failed
        assert failed['probe']['destination_after']=='',failed
    records=request(method='GET',route='/wp/v2/cleanlinks',query={'slug':body['slug']})
    assert records['data']==[],records
    saved=request(body); assert saved['status']==200,saved
    assert request(body)['data']==saved['data']
    reports.append({'probe':probe+' rollback + same-key retry','pass':True})
for probe in ('cron_context','ajax_context'):
    saved=request(create(probe.replace('_','-'),probe+'_create_request'),probe=probe)
    assert saved['status']==200,saved
    assert saved['data']['destination']=='https://example.org/proof?a=1&b=%2F&b=two+words',saved
    reports.append({'probe':probe+' explicit save','pass':True})
print(json.dumps(reports,indent=2))
